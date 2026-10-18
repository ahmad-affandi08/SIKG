<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Install;

use Src\Clinic\Contracts\ClinicProvisioner;
use Src\IdentityAccess\Domain\FirstOwnerRegistrar;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;

/**
 * Pemasangan awal: membuat klinik pertama dan pengguna Pemilik dengan kata sandi sementara (wajib diganti saat masuk
 * pertama), dalam satu transaksi bersama catatan auditnya. Menolak bila sudah ada klinik.
 */
final class CreateFirstOwner
{
    public function __construct(
        private readonly ClinicProvisioner $clinics,
        private readonly FirstOwnerRegistrar $owners,
        private readonly Transaction $tx,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @throws AlreadyInstalled
     */
    public function __invoke(string $clinicName, string $name, string $username, string $email, string $temporaryPassword): string
    {
        return $this->tx->run(function () use ($clinicName, $name, $username, $email, $temporaryPassword): string {
            if ($this->clinics->anyClinicExists()) {
                throw new AlreadyInstalled;
            }
            $clinicId = $this->clinics->create($clinicName, 'Asia/Jakarta');
            $ownerId = $this->owners->register($clinicId, $name, $username, $email, $temporaryPassword);

            $this->tenant->runAs($clinicId, null, fn () => $this->audit->record(
                new AuditEntry($ownerId, 'install.owner_created', 'clinic', $clinicId, null, ['username' => $username]),
            ));

            return $ownerId;
        });
    }
}
