# ADR-0007: Laporan teragregasi dan berkas ekspor lewat antrean

- Status: Disetujui (keputusan diserahkan pengguna kepada Claude Code; dapat ditinjau ulang)
- Konteks terkait: LAP-01 sampai LAP-10, SYS-07, NFR-01; ADR-0006; Q-23

## Konteks

PRD 11 meminta sepuluh laporan, ekspor PDF dan Excel, dan laporan berat lewat antrean dengan status terlihat. Pada shared hosting antrean digerakkan cron dan job harus pendek dan idempoten (ADR-0006). Laporan memuat angka keuangan dan, pada LAP-06 dan LAP-09, nama pasien.

## Keputusan

- Laporan dihitung saat dibuka dengan kueri agregasi terindeks lewat satu pintu baca (`ReportSource`) yang selalu membatasi klinik dan cabang. Batas rentang 366 hari dan 2.000 baris per tabel menjaga waktu respons dan memori.
- Berkas PDF dan Excel selalu dibuat oleh job antrean. Tabel `report_jobs` mencatat status menunggu, berjalan, selesai, dan gagal. Baris tidak dihapus; yang dilepas hanya berkas di disk privat setelah 7 hari. Kolom `requested_by` menggantikan `created_by`, dan tidak ada `updated_by` atau `deleted_at` karena tabel ini hanya melacak pekerjaan, bukan data klinis atau keuangan.
- Berkas dienkripsi di aplikasi, hanya dapat dilihat dan diunduh pemintanya, dan akses dicek ulang terhadap izin saat ini. Permintaan, unduhan, dan pembukaan laporan yang memuat nama pasien tercatat di `audit_logs`.
- Excel memakai `maatwebsite/excel` (teks selalu string eksplisit), PDF memakai dompdf; keduanya sudah disetujui dan berjalan tanpa proses sistem.
- Endpoint JSON (ekspor dan status berkas) berada di `/internal-api`.

## Alternatif yang dipertimbangkan

- Semua laporan lewat antrean: menambah menunggu cron pada pemakaian harian tanpa manfaat, karena kueri agregasi cepat.
- Tabel `report_jobs` dengan kolom standar lengkap dan hapus lunak: menambah kolom yang tidak bermakna bagi catatan pekerjaan.
- Pemecahan potongan untuk Excel: tidak perlu selama keluaran dibatasi 2.000 baris per tabel; ditinjau ulang bila batas dinaikkan.

## Akibat

- Layar laporan selalu langsung, ekspor menunggu antrean dan menampilkan status jujur.
- Laporan baru cukup menambah satu kelas `Report` dan satu entri `ReportKey`.
