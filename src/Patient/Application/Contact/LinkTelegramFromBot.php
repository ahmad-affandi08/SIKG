<?php

declare(strict_types=1);

namespace Src\Patient\Application\Contact;

use Src\Patient\Domain\PatientContactRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;

/**
 * JDW-07: pasien membuka tautan t.me dan bot menerima `/start <token>`. Token dicocokkan lewat hash, berlaku sekali pakai,
 * lalu ID obrolan disimpan terenkripsi. Dijalankan oleh perintah polling tanpa pengguna, sehingga konteks klinik diambil
 * dari baris tautan itu sendiri. Audit mencatat penautan tanpa ID obrolan.
 */
final readonly class LinkTelegramFromBot
{
    public function __construct(
        private PatientContactRepository $contacts,
        private TenantContext $tenant,
        private AuditLogger $audit,
        private Transaction $tx,
        private Clock $clock,
    ) {}

    /**
     * @return bool true bila token sah dan obrolan berhasil ditautkan
     */
    public function __invoke(string $token, string $chatId): bool
    {
        if ($token === '' || $chatId === '') {
            return false;
        }

        $owner = $this->contacts->findByLink(hash('sha256', $token), $this->clock->now());
        if ($owner === null) {
            return false;
        }

        $this->tenant->runAs($owner['clinic_id'], null, function () use ($owner, $chatId): void {
            $this->tx->run(function () use ($owner, $chatId): void {
                $this->contacts->attachTelegram($owner['patient_id'], $chatId);
                $this->audit->record(new AuditEntry(null, 'patient.telegram_linked', 'patient', $owner['patient_id'], null, null));
            });
        });

        return true;
    }
}
