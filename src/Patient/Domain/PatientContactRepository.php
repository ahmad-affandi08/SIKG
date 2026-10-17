<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

use DateTimeImmutable;

interface PatientContactRepository
{
    public function find(string $patientId): ?ContactRecord;

    /**
     * @param  list<string>  $patientIds
     * @return array<string, ContactRecord> patient_id => kontak
     */
    public function forPatients(array $patientIds): array;

    public function saveContact(string $actorId, string $patientId, ?string $email, bool $optIn, ?DateTimeImmutable $optInAt): void;

    /** Menyimpan hash tautan penautan Telegram (atau menghapusnya bila null). */
    public function setLink(string $actorId, string $patientId, ?string $hash, ?DateTimeImmutable $expiresAt): void;

    /**
     * Mencari pemilik tautan lintas klinik (bot Telegram satu untuk seluruh instalasi).
     *
     * @return array{clinic_id: string, patient_id: string}|null
     */
    public function findByLink(string $hash, DateTimeImmutable $now): ?array;

    public function attachTelegram(string $patientId, string $chatId): void;

    public function detachTelegram(string $actorId, string $patientId): void;
}
