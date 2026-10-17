<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

/**
 * Jenis pengingat: janji besok (h1), janji hari ini (h0), dan ajakan kontrol berkala setelah kunjungan terakhir (recall).
 */
enum ReminderKind: string
{
    case DayBefore = 'h1';
    case SameDay = 'h0';
    case Recall = 'recall';

    public function label(): string
    {
        return match ($this) {
            self::DayBefore => 'Janji besok (H-1)',
            self::SameDay => 'Janji hari ini (hari-H)',
            self::Recall => 'Kontrol berkala',
        };
    }

    public function isAppointment(): bool
    {
        return $this !== self::Recall;
    }

    public function defaultTemplate(): string
    {
        return match ($this) {
            self::DayBefore => 'Yth. {nama}, pengingat dari {klinik}: Anda memiliki jadwal kunjungan besok, {tanggal} pukul {jam} dengan {dokter}. Bila perlu mengubah jadwal, mohon hubungi kami. Terima kasih.',
            self::SameDay => 'Yth. {nama}, pengingat dari {klinik}: jadwal kunjungan Anda hari ini pukul {jam} dengan {dokter}. Kami menunggu kedatangan Anda. Terima kasih.',
            self::Recall => 'Yth. {nama}, sudah sekitar {bulan} bulan sejak kunjungan terakhir Anda di {klinik}. Kami menyarankan pemeriksaan gigi berkala. Silakan hubungi kami untuk membuat jadwal. Terima kasih.',
        };
    }

    /**
     * Urutan parameter templat WhatsApp Business API (isi {{1}}, {{2}}, ... pada templat yang disetujui Meta).
     *
     * @return list<string>
     */
    public function apiParameters(): array
    {
        return match ($this) {
            self::DayBefore, self::SameDay => ['nama', 'tanggal', 'jam', 'dokter', 'klinik'],
            self::Recall => ['nama', 'bulan', 'klinik'],
        };
    }
}
