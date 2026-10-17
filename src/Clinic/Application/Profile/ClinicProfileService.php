<?php

declare(strict_types=1);

namespace Src\Clinic\Application\Profile;

/**
 * MST-01: profil klinik (nama, alamat, nomor izin, logo, header kwitansi dan resep). Port Application untuk konteks
 * CRUD terstruktur (ADR-0001). Logo disimpan di disk privat dan hanya dilayani lewat route berizin.
 */
interface ClinicProfileService
{
    public function get(): ClinicProfile;

    public function update(string $actorId, ClinicProfile $profile): void;

    /**
     * @param  string  $extension  png atau jpg
     */
    public function replaceLogo(string $actorId, string $contents, string $extension): void;

    public function removeLogo(string $actorId): void;

    /**
     * @return array{path: string, mime: string}|null lokasi di disk privat, atau null bila belum ada logo
     */
    public function logo(): ?array;
}
