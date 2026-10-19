<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

/**
 * Cara klinik mengirim WhatsApp. `Link`: petugas membuka WhatsApp dengan satu klik (tanpa API, tanpa pengiriman otomatis).
 * `Meta`: WhatsApp Business Cloud API resmi, pesan berbasis templat yang disetujui Meta. Sisanya penyedia tidak resmi yang
 * mengirim teks biasa lewat nomor WhatsApp klinik yang ditautkan; penyedia tidak resmi melanggar ketentuan layanan WhatsApp
 * dan nomornya dapat diblokir, sehingga pilihan ini menjadi tanggung jawab klinik.
 */
enum WhatsAppProvider: string
{
    case Link = 'link';
    case Meta = 'meta';
    case Fonnte = 'fonnte';
    case Wablas = 'wablas';
    case Waha = 'waha';

    public function label(): string
    {
        return match ($this) {
            self::Link => 'Tautan WhatsApp (manual, gratis)',
            self::Meta => 'WhatsApp Resmi (Meta Cloud API)',
            self::Fonnte => 'Fonnte (tidak resmi)',
            self::Wablas => 'Wablas (tidak resmi)',
            self::Waha => 'WAHA (tidak resmi, server sendiri)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Link => 'Pengingat disiapkan, lalu petugas membuka WhatsApp dan menekan kirim. Pemberitahuan staf dan kode booking tidak terkirim otomatis.',
            self::Meta => 'Resmi dan aman dari pemblokiran, berbiaya per percakapan. Pesan memakai templat yang harus disetujui Meta lebih dulu.',
            self::Fonnte => 'Mengirim teks biasa lewat nomor WhatsApp klinik yang ditautkan di fonnte.com. Murah, tetapi tidak resmi.',
            self::Wablas => 'Mengirim teks biasa lewat server Wablas milik akun klinik. Murah, tetapi tidak resmi.',
            self::Waha => 'Mengirim teks biasa lewat server WAHA (WhatsApp HTTP API) yang dipasang klinik sendiri. Tidak resmi.',
        };
    }

    public function official(): bool
    {
        return $this === self::Meta;
    }

    /** Pengiriman otomatis lewat API (bukan tautan manual). */
    public function automatic(): bool
    {
        return $this !== self::Link;
    }

    /** Pesan berbasis templat (Meta) atau teks biasa (penyedia tidak resmi). */
    public function usesTemplates(): bool
    {
        return $this === self::Meta;
    }

    /**
     * Isian kredensial yang wajib agar penyedia siap dipakai.
     *
     * @return list<string> dari: phone_number_id, token, base_url
     */
    public function requiredFields(): array
    {
        return match ($this) {
            self::Link => [],
            self::Meta => ['phone_number_id', 'token'],
            self::Fonnte => ['token'],
            self::Wablas => ['token', 'base_url'],
            self::Waha => ['base_url'],
        };
    }

    /**
     * Isian yang ditampilkan untuk penyedia ini (wajib maupun pilihan).
     *
     * @return list<string> dari: phone_number_id, token, base_url, session, language, staff_template
     */
    public function fields(): array
    {
        return match ($this) {
            self::Link => [],
            self::Meta => ['phone_number_id', 'token', 'language', 'staff_template'],
            self::Fonnte => ['token'],
            self::Wablas => ['base_url', 'token'],
            self::Waha => ['base_url', 'token', 'session'],
        };
    }
}
