<?php

declare(strict_types=1);

namespace Src\Clinic\Application\Doctors;

/**
 * MST-02: dokter, jadwal praktik mingguan, dan skema jasa. Port Application untuk konteks CRUD terstruktur (ADR-0001).
 * Dokter tidak pernah dihapus (M5): dinonaktifkan agar kunjungan lama tetap menunjuk dokternya.
 */
interface DoctorAdministration
{
    /**
     * @return list<array{id: string, name: string, sip_no: ?string, specialty: ?string, branch: ?string, user: ?string, is_active: bool, schedule_count: int}>
     */
    public function list(): array;

    /**
     * @return array{id: string, name: string, sip_no: ?string, specialty: ?string, branch_id: ?string, user_id: ?string, is_active: bool, fee_scheme: array<string, mixed>, schedule: list<array{weekday: int, start: string, end: string}>}
     */
    public function find(string $id): array;

    public function create(string $actorId, DoctorData $data): string;

    public function update(string $actorId, string $id, DoctorData $data): void;

    public function setActive(string $actorId, string $id, bool $active): void;
}
