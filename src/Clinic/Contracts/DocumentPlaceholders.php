<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Penanda yang dapat dipakai pada templat dokumen (RM-09, RM-10), ditulis `{{nama}}`. Diisi saat dokumen dibuat dari data pasien,
 * dokter, kunjungan, dan isian surat. Penanda yang tidak dikenal ditolak saat templat disimpan agar salah ketik terdeteksi.
 */
final class DocumentPlaceholders
{
    /** @var array<string, string> penanda => arti */
    public const ALL = [
        'pasien' => 'nama pasien',
        'no_rm' => 'nomor rekam medis',
        'tanggal_lahir' => 'tanggal lahir pasien (DD/MM/YYYY)',
        'umur' => 'umur pasien dalam tahun',
        'dokter' => 'nama dokter',
        'sip' => 'nomor SIP dokter',
        'klinik' => 'nama klinik',
        'tanggal' => 'tanggal surat (DD/MM/YYYY)',
        'tanggal_kunjungan' => 'tanggal kunjungan yang dipilih',
        'tindakan' => 'nama tindakan (informed consent)',
        'diagnosa' => 'diagnosa kunjungan yang dipilih',
        'keperluan' => 'keperluan surat keterangan',
        'istirahat' => 'kalimat anjuran istirahat',
        'tujuan' => 'tujuan rujukan',
        'alasan' => 'alasan rujukan',
    ];

    /**
     * Penanda dalam teks yang tidak dikenal.
     *
     * @return list<string>
     */
    public static function unknownIn(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $body, $found);
        $unknown = array_values(array_unique(array_filter($found[1], fn (string $name) => ! isset(self::ALL[$name]))));

        // Kurung ganda yang bukan penanda sah (huruf kapital, spasi dalam nama, dan sebagainya) juga dianggap salah ketik.
        preg_match_all('/\{\{(.*?)\}\}/s', $body, $all);
        foreach ($all[1] as $raw) {
            if (preg_match('/^\s*[a-z_]+\s*$/', $raw) !== 1) {
                $unknown[] = trim($raw);
            }
        }

        return array_values(array_unique($unknown));
    }
}
