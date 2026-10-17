<?php

declare(strict_types=1);

namespace Src\Patient\Application\Import;

use DateTimeImmutable;
use Src\Patient\Application\PatientDraft;
use Src\Patient\Domain\InvalidPatientData;
use Src\Patient\Domain\MedicalHistoryEntry;
use Src\Patient\Domain\MedicalHistoryType;
use Src\Patient\Domain\PatientProfile;
use Src\Patient\Domain\Sex;

/**
 * Memetakan satu baris Excel ke profil pasien dan riwayat medis. Baris tanpa NIK tidak ditolak: diberi alasan
 * "perlu dilengkapi" agar dapat dilengkapi kemudian (alur migrasi data awal). Pesan galat tidak memuat isi data pasien.
 */
final class ImportRowMapper
{
    public const NIK_PENDING_REASON = 'Perlu dilengkapi (hasil impor)';

    /**
     * @param  array<string, scalar|null>  $row
     * @return array{profile: PatientProfile, histories: list<MedicalHistoryEntry>}
     *
     * @throws InvalidPatientData
     */
    public function map(array $row, DateTimeImmutable $today): array
    {
        $text = fn (string $key): ?string => isset($row[$key]) && trim((string) $row[$key]) !== '' ? trim((string) $row[$key]) : null;

        $nik = $text('nik');
        $draft = new PatientDraft(
            name: (string) $text('nama'),
            nik: $nik,
            nikMissingReason: $nik === null ? self::NIK_PENDING_REASON : null,
            birthDate: $this->birthDate($row['tanggal_lahir'] ?? null),
            sex: $this->sex($text('jenis_kelamin')),
            address: $text('alamat'),
            phone: $text('hp'),
            occupation: $text('pekerjaan'),
            guardianName: $text('wali_nama'),
            guardianRelation: $text('wali_hubungan'),
            guardianPhone: $text('wali_hp'),
        );

        $histories = [];
        foreach ($this->split($text('alergi')) as $name) {
            $histories[] = new MedicalHistoryEntry(MedicalHistoryType::Allergy, $name);
        }
        foreach ($this->split($text('penyakit_sistemik')) as $name) {
            $histories[] = new MedicalHistoryEntry(MedicalHistoryType::SystemicDisease, $name);
        }

        return ['profile' => $draft->toProfile($today), 'histories' => $histories];
    }

    /**
     * @param  array<string, scalar|null>  $row
     */
    public function isEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function birthDate(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            // Tanggal Excel disimpan sebagai angka hari sejak 1899-12-30.
            return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) $value.' days')->format('Y-m-d');
        }
        $text = is_scalar($value) ? trim((string) $value) : '';
        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $text, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return $text;
    }

    private function sex(?string $value): string
    {
        return match (mb_strtolower($value ?? '')) {
            'l', 'laki-laki', 'laki laki', 'pria', 'male' => Sex::Male->value,
            'p', 'perempuan', 'wanita', 'female' => Sex::Female->value,
            default => '',
        };
    }

    /**
     * @return list<string>
     */
    private function split(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[;,\n]+/', $value) ?: [])));
    }
}
