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
Saya yang bertanda tangan di bawah ini, sebagai pasien atau wali pasien atas nama {{pasien}} (Nomor RM {{no_rm}}, tanggal lahir {{tanggal_lahir}}), menyatakan telah menerima penjelasan dari {{dokter}} mengenai tindakan kedokteran gigi berikut:

Diagnosis: {{diagnosa}}
Tindakan: {{tindakan}}
Tujuan tindakan: Menghilangkan keluhan serta memulihkan fungsi dan kesehatan gigi dan mulut.
Tata cara: Sesuai penjelasan dokter sebelum tindakan dimulai.
Risiko dan komplikasi: Nyeri, bengkak, perdarahan, infeksi, atau reaksi terhadap obat dan anestesi; risiko khusus tindakan telah dijelaskan dokter.
Alternatif dan risikonya: Perawatan lain atau tidak melakukan tindakan, beserta risikonya, telah dijelaskan dokter.
Prognosis: Sesuai penjelasan dokter.
Perkiraan biaya: Sesuai tarif klinik yang berlaku.

Saya telah diberi kesempatan untuk bertanya dan seluruh penjelasan telah saya pahami. Dengan penuh kesadaran saya MENYETUJUI dilakukannya tindakan tersebut di {{klinik}}. Saya memahami bahwa hasil perawatan tidak dapat dijamin sepenuhnya dan bahwa persetujuan ini dapat saya tarik kembali secara tertulis sebelum tindakan dimulai.

Demikian surat pernyataan ini saya buat dengan sebenarnya dan penuh rasa tanggung jawab.
TEXT,
            DocumentType::Certificate => <<<'TEXT'
Yang bertanda tangan di bawah ini, {{dokter}}, dokter gigi pada {{klinik}}, menerangkan bahwa:

Nama: {{pasien}}
Nomor RM: {{no_rm}}
Tanggal lahir: {{tanggal_lahir}} (umur {{umur}} tahun)

telah menjalani pemeriksaan dan perawatan gigi pada tanggal {{tanggal_kunjungan}} dengan diagnosa: {{diagnosa}}.

Berdasarkan hasil pemeriksaan tersebut, pasien {{istirahat}}.

Surat keterangan ini dibuat untuk keperluan {{keperluan}}.

Demikian surat keterangan ini dibuat dan diberikan untuk diketahui dan digunakan sebagaimana mestinya.
TEXT,
            DocumentType::Referral => <<<'TEXT'
Kepada Yth.
{{tujuan}}
di tempat

Dengan hormat,

Mohon pemeriksaan dan penanganan lebih lanjut terhadap pasien berikut:

Nama: {{pasien}}
Nomor RM: {{no_rm}}
Tanggal lahir: {{tanggal_lahir}} (umur {{umur}} tahun)

Keluhan dan riwayat: {{keluhan}}
Diagnosa sementara: {{diagnosa}}
Terapi atau tindakan yang telah diberikan: {{terapi}}
Alasan rujukan: {{alasan}}

Pasien kami periksa di {{klinik}} pada tanggal {{tanggal_kunjungan}}. Mohon balasan mengenai hasil pemeriksaan dan penanganan selanjutnya. Atas perhatian dan kerja sama Bapak atau Ibu, kami ucapkan terima kasih.
TEXT,
        };
    }
}
