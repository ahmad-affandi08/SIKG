<?php

declare(strict_types=1);

namespace Src\Integration\Application\Reminder;

use Src\Integration\Domain\Reminder\EndpointGuard;
use Src\Integration\Domain\Reminder\InvalidReminder;
use Src\Integration\Domain\Reminder\ReminderSettingsRepository;
use Src\Integration\Domain\Reminder\WhatsAppFailure;
use Src\Integration\Domain\Reminder\WhatsAppProvider;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Notifications\StaffDestinations;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\Brand;
use Src\Shared\Domain\WhatsAppNumber;

/**
 * Pengaturan saluran WhatsApp klinik: pilihan penyedia (WhatsApp Resmi/Meta atau penyedia tidak resmi), kredensial, dan pesan
 * uji. Satu saluran dipakai untuk pengingat pasien, kode verifikasi booking, dan pemberitahuan staf. Token hanya masuk dan tidak
 * pernah dikirim balik ke layar.
 */
final readonly class WhatsAppAdministration
{
    public function __construct(
        private ReminderSettingsRepository $settings,
        private WhatsAppMessenger $messenger,
        private AuditLogger $audit,
        private Transaction $tx,
        private StaffDestinations $staff,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $channel = $this->settings->get()->channel;
        $providers = [];
        foreach (WhatsAppProvider::cases() as $provider) {
            $providers[] = [
                'value' => $provider->value,
                'label' => $provider->label(),
                'description' => $provider->description(),
                'official' => $provider->official(),
                'fields' => $provider->fields(),
                'required' => $provider->requiredFields(),
            ];
        }

        return [
            'providers' => $providers,
            'settings' => [
                'provider' => $channel->provider->value,
                'phone_number_id' => $channel->phoneNumberId,
                'base_url' => $channel->baseUrl,
                'session' => $channel->session,
                'language' => $channel->language,
                'staff_template' => $channel->staffTemplate,
                'has_token' => $this->settings->hasToken(),
                'ready' => $channel->ready(),
            ],
        ];
    }

    /**
     * @throws InvalidReminder
     */
    public function save(string $actorId, string $provider, ?string $phoneNumberId, ?string $token, ?string $baseUrl, ?string $session, string $language, ?string $staffTemplate): void
    {
        $chosen = WhatsAppProvider::tryFrom($provider) ?? throw new InvalidReminder('Penyedia WhatsApp tidak dikenal.');
        $clean = fn (?string $v) => $v === null || trim($v) === '' ? null : trim($v);
        [$phoneNumberId, $token, $baseUrl, $session, $staffTemplate] = [$clean($phoneNumberId), $clean($token), $clean($baseUrl), $clean($session), $clean($staffTemplate)];

        // Isian yang tidak dipakai penyedia terpilih tidak disimpan.
        $fields = $chosen->fields();
        if (! in_array('phone_number_id', $fields, true)) {
            $phoneNumberId = null;
        }
        if (! in_array('base_url', $fields, true)) {
            $baseUrl = null;
        }
        if (! in_array('session', $fields, true)) {
            $session = null;
        }
        if (! in_array('staff_template', $fields, true)) {
            $staffTemplate = null;
        }
        if ($baseUrl !== null) {
            $baseUrl = EndpointGuard::normalize($baseUrl);
        }

        // Token milik satu penyedia tidak boleh terbawa ke penyedia lain (akan terkirim ke server yang berbeda).
        $current = $this->settings->get()->channel;
        $sameProvider = $current->provider === $chosen && $current->baseUrl === $baseUrl;
        $hasToken = $sameProvider && $this->settings->hasToken();
        $replaceToken = $token !== null || ! $sameProvider || ! in_array('token', $fields, true);
        foreach ($chosen->requiredFields() as $field) {
            $missing = match ($field) {
                'phone_number_id' => $phoneNumberId === null,
                'token' => $token === null && ! $hasToken,
                'base_url' => $baseUrl === null,
                default => false,
            };
            if ($missing) {
                throw new InvalidReminder('Isian '.match ($field) {
                    'phone_number_id' => 'ID nomor telepon',
                    'token' => 'token',
                    default => 'alamat server',
                }.' wajib diisi untuk '.$chosen->label().'.');
            }
        }

        $language = trim($language) === '' ? 'id' : trim($language);
        $this->tx->run(function () use ($actorId, $chosen, $phoneNumberId, $token, $replaceToken, $baseUrl, $session, $language, $staffTemplate, $current): void {
            $this->settings->saveChannel($actorId, $chosen, $phoneNumberId, $token, $replaceToken, $baseUrl, $session, $language, $staffTemplate);
            // Hanya isian bukan rahasia yang dicatat; token tidak pernah masuk audit.
            $this->audit->record(new AuditEntry(
                $actorId, 'whatsapp.settings_updated', 'reminder_settings', $actorId,
                $this->snapshot($current->provider->value, $current->phoneNumberId, $current->baseUrl, $current->session, $current->language, $current->staffTemplate),
                $this->snapshot($chosen->value, $phoneNumberId, $baseUrl, $session, $language, $staffTemplate) + ['token_changed' => $replaceToken],
            ));
        });
    }

    /**
     * Mengirim pesan uji ke satu nomor. Mengembalikan hasil yang aman ditampilkan (tanpa nomor, token, atau isi pesan).
     *
     * @return array{ok: bool, message: string}
     */
    public function sendTest(string $actorId, string $phone): array
    {
        $number = WhatsAppNumber::tryFrom($phone);
        if ($number === null) {
            return ['ok' => false, 'message' => 'Nomor tujuan harus nomor Indonesia (08..., 628..., atau +628...).'];
        }
        // Tujuan uji dibatasi pada nomor staf klinik agar fitur ini tidak dapat dipakai mengirim pesan ke sembarang nomor.
        if (! in_array($number->value, $this->staff->whatsappNumbers(), true)) {
            return ['ok' => false, 'message' => 'Pesan uji hanya dapat dikirim ke nomor WhatsApp staf. Tambahkan nomor itu lebih dulu di Pengaturan klinik, Notifikasi.'];
        }
        $channel = $this->settings->get()->channel;
        if (! $channel->ready()) {
            return ['ok' => false, 'message' => 'Saluran WhatsApp belum lengkap. Simpan pengaturan lebih dulu.'];
        }

        $title = 'Uji '.Brand::NAME;
        $body = 'Pesan uji dari pengaturan WhatsApp '.Brand::NAME.'. Bila Anda menerimanya, saluran sudah berfungsi.';
        try {
            $this->messenger->deliver($channel, $number->value, '['.Brand::NAME."] {$title}\n{$body}", $channel->staffTemplate, [$title, $body]);
        } catch (WhatsAppFailure $e) {
            $this->recordTest($actorId, $number, false);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $this->recordTest($actorId, $number, true);

        return ['ok' => true, 'message' => 'Pesan uji dikirim. Periksa WhatsApp pada nomor tujuan.'];
    }

    /**
     * @return array<string, string|null>
     */
    private function snapshot(string $provider, ?string $phoneNumberId, ?string $baseUrl, ?string $session, string $language, ?string $staffTemplate): array
    {
        return ['provider' => $provider, 'phone_number_id' => $phoneNumberId, 'base_url' => $baseUrl, 'session' => $session, 'language' => $language, 'staff_template' => $staffTemplate];
    }

    private function recordTest(string $actorId, WhatsAppNumber $number, bool $ok): void
    {
        $this->audit->record(new AuditEntry($actorId, 'whatsapp.test_sent', 'reminder_settings', $actorId, null, ['ok' => $ok, 'to' => $number->masked()]));
    }
}
