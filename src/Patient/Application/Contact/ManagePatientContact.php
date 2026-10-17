<?php

declare(strict_types=1);

namespace Src\Patient\Application\Contact;

use DateInterval;
use Src\Patient\Domain\InvalidContact;
use Src\Patient\Domain\PatientContactRepository;
use Src\Patient\Domain\PatientNotFound;
use Src\Patient\Domain\PatientRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\EmailSender;
use Src\Shared\Application\Notifications\TelegramSender;
use Src\Shared\Application\Transaction;

/**
 * JDW-07: kontak pengingat pasien. Pasien memberi persetujuan eksplisit (opt-in) dan minimal satu saluran: email atau Telegram.
 * Telegram ditautkan sendiri oleh pasien lewat tautan sekali pakai ke bot; token tautan hanya disimpan sebagai hash dan
 * kedaluwarsa. Email dan ID obrolan terenkripsi (M11); audit tidak memuat nilainya.
 */
final readonly class ManagePatientContact
{
    public const LINK_TTL_DAYS = 7;

    public function __construct(
        private PatientRepository $patients,
        private PatientContactRepository $contacts,
        private EmailSender $email,
        private TelegramSender $telegram,
        private AuditLogger $audit,
        private Transaction $tx,
        private Clock $clock,
    ) {}

    /**
     * @return array{email: string|null, telegram_linked: bool, link_pending: bool, reminders_opt_in: bool, opt_in_at: string|null, email_available: bool, telegram_available: bool}
     */
    public function view(string $patientId): array
    {
        $this->patients->find($patientId) ?? throw new PatientNotFound;
        $contact = $this->contacts->find($patientId);

        return [
            'email' => $contact?->email,
            'telegram_linked' => $contact?->telegramChatId !== null,
            'link_pending' => $contact->linkPending ?? false,
            'reminders_opt_in' => $contact->remindersOptIn ?? false,
            'opt_in_at' => $contact?->optInAt?->format('c'),
            'email_available' => $this->email->configured(),
            'telegram_available' => $this->telegram->configured() && $this->telegram->botUsername() !== null,
        ];
    }

    public function save(string $actorId, string $patientId, ?string $email, bool $optIn): void
    {
        $this->tx->run(function () use ($actorId, $patientId, $email, $optIn): void {
            $this->patients->find($patientId) ?? throw new PatientNotFound;
            $before = $this->contacts->find($patientId);

            $email = $email === null || trim($email) === '' ? null : mb_strtolower(trim($email));
            $hasChannel = $email !== null || $before?->telegramChatId !== null;
            if ($optIn && ! $hasChannel) {
                throw InvalidContact::noChannel();
            }

            $this->contacts->saveContact($actorId, $patientId, $email, $optIn, $this->clock->now());
            $this->audit->record(new AuditEntry(
                $actorId, 'patient.contact_updated', 'patient', $patientId,
                ['has_email' => $before?->email !== null, 'reminders_opt_in' => $before->remindersOptIn ?? false],
                ['has_email' => $email !== null, 'reminders_opt_in' => $optIn],
            ));
        });
    }

    /**
     * Membuat tautan penautan Telegram untuk pasien. Token mentah hanya dikembalikan sekali ini.
     *
     * @return array{url: string, expires_at: string}
     */
    public function startTelegramLink(string $actorId, string $patientId): array
    {
        $username = $this->telegram->botUsername();
        if (! $this->telegram->configured() || $username === null) {
            throw InvalidContact::notConfigured();
        }

        return $this->tx->run(function () use ($actorId, $patientId, $username): array {
            $this->patients->find($patientId) ?? throw new PatientNotFound;

            $token = bin2hex(random_bytes(16));
            $expires = $this->clock->now()->add(new DateInterval('P'.self::LINK_TTL_DAYS.'D'));
            $this->contacts->setLink($actorId, $patientId, hash('sha256', $token), $expires);
            $this->audit->record(new AuditEntry($actorId, 'patient.telegram_link_started', 'patient', $patientId, null, null));

            return ['url' => 'https://t.me/'.$username.'?start='.$token, 'expires_at' => $expires->format('c')];
        });
    }

    public function unlinkTelegram(string $actorId, string $patientId): void
    {
        $this->tx->run(function () use ($actorId, $patientId): void {
            $this->patients->find($patientId) ?? throw new PatientNotFound;
            $before = $this->contacts->find($patientId);
            $this->contacts->detachTelegram($actorId, $patientId);
            // Tanpa saluran tersisa, persetujuan pengingat tidak lagi bermakna.
            if ($before !== null && $before->email === null && $before->remindersOptIn) {
                $this->contacts->saveContact($actorId, $patientId, null, false, null);
            }
            $this->audit->record(new AuditEntry($actorId, 'patient.telegram_unlinked', 'patient', $patientId, null, null));
        });
    }
}
