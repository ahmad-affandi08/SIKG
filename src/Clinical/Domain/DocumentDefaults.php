<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

/**
 * Templat bawaan bila klinik belum membuat templat sendiri (RM-09, RM-10). Penanda `{{nama}}` diisi saat dokumen dibuat
 * (daftar penanda di Src\Clinic\Contracts\DocumentPlaceholders). Isi hanya kerangka; dokter menyuntingnya sebelum dicetak.
 */
final class DocumentDefaults
{
    public static function body(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::Consent => <<<'TEXT'
Saya yang bertanda tangan di bawah ini, sebagai pasien atau wali pasien atas nama {{pasien}} (Nomor RM {{no_rm}}, tanggal lahir {{tanggal_lahir}}), menyatakan bahwa:

1. Saya telah mendapat penjelasan dari {{dokter}} mengenai diagnosa, tujuan, cara, manfaat, risiko, kemungkinan komplikasi, dan alternatif dari tindakan {{tindakan}}.
2. Saya telah diberi kesempatan untuk bertanya dan semua pertanyaan saya telah dijawab dengan jelas.
3. Saya mengerti bahwa hasil perawatan tidak dapat dijamin sepenuhnya.

Dengan ini saya MENYETUJUI dilakukannya tindakan tersebut di {{klinik}}. Persetujuan ini dapat saya cabut sebelum tindakan dimulai.
TEXT,
            DocumentType::Certificate => <<<'TEXT'
Yang bertanda tangan di bawah ini, {{dokter}}, menerangkan bahwa:

Nama: {{pasien}}
Nomor RM: {{no_rm}}
Tanggal lahir: {{tanggal_lahir}} (umur {{umur}} tahun)

telah menjalani pemeriksaan dan perawatan gigi di {{klinik}} pada {{tanggal_kunjungan}} dengan diagnosa: {{diagnosa}}.

Berdasarkan pemeriksaan tersebut, pasien {{istirahat}}.

Surat keterangan ini dibuat untuk keperluan {{keperluan}} dan dapat dipergunakan sebagaimana mestinya.
TEXT,
            DocumentType::Referral => <<<'TEXT'
Kepada Yth.
{{tujuan}}

Dengan hormat,
Mohon pemeriksaan dan penanganan lebih lanjut untuk pasien:

Nama: {{pasien}}
Nomor RM: {{no_rm}}
Tanggal lahir: {{tanggal_lahir}} (umur {{umur}} tahun)

Diagnosa: {{diagnosa}}
Alasan rujukan: {{alasan}}

Pasien telah diperiksa di {{klinik}} pada {{tanggal_kunjungan}}. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.
TEXT,
        };
    }
}
