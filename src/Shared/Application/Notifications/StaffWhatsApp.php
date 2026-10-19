<?php

declare(strict_types=1);

namespace Src\Shared\Application\Notifications;

/**
 * Pengirim pemberitahuan staf lewat WhatsApp: WhatsApp Resmi (Meta, lewat templat) atau penyedia tidak resmi (teks biasa) sesuai
 * pengaturan klinik. Diimplementasikan konteks Integration. Gagal kirim tidak melempar: hasilnya false dan log tidak memuat nomor,
 * token, atau isi pesan.
 */
interface StaffWhatsApp
{
    /** True bila klinik memilih saluran otomatis dan kredensialnya lengkap. */
    public function configured(): bool;

    /** @param  string  $phone  nomor internasional tanpa plus (628...) */
    public function send(string $phone, string $title, string $body): bool;
}
