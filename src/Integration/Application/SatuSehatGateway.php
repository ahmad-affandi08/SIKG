<?php

declare(strict_types=1);

namespace Src\Integration\Application;

use Src\Integration\Domain\SatuSehatFailure;
use Src\Integration\Domain\SatuSehatSettings;

/**
 * Pintu ke platform SATUSEHAT (anti-corruption layer, ADR-0005). Implementasi HTTP ada di Infrastructure; tes memakai pemalsu.
 */
interface SatuSehatGateway
{
    /**
     * Memastikan kredensial dapat memperoleh token.
     *
     * @throws SatuSehatFailure
     */
    public function verify(SatuSehatSettings $settings): void;

    /**
     * @return string|null ID pasien (IHS number) menurut NIK, atau null bila tidak ditemukan
     *
     * @throws SatuSehatFailure
     */
    public function findPatientIhs(SatuSehatSettings $settings, string $nik): ?string;

    /**
     * @return string|null ID praktisi (IHS number) menurut NIK, atau null bila tidak ditemukan
     *
     * @throws SatuSehatFailure
     */
    public function findPractitionerIhs(SatuSehatSettings $settings, string $nik): ?string;

    /**
     * Mengirim satu Bundle transaksi.
     *
     * @param  array<string, mixed>  $bundle
     * @return list<array{type: string, id: string}> sumber daya yang dibuat, urut sama dengan entri bundel
     *
     * @throws SatuSehatFailure
     */
    public function submit(SatuSehatSettings $settings, array $bundle): array;
}
