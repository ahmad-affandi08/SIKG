# ADR-0005: SATUSEHAT di Fase 2, persiapan data di Fase 1

- Status: Disetujui
- Konteks terkait: P2-01, PAS-01, NFR-14, NFR-15

## Konteks

Permenkes 24/2022 mewajibkan rekam medis elektronik, interoperabilitas, registrasi sistem, retensi 25 tahun, dan penyimpanan data di dalam negeri.

## Keputusan

- Integrasi SATUSEHAT (HL7 FHIR) adalah P2-01, pekerjaan pertama Fase 2. Pendaftaran PSE dan akun SATUSEHAT diurus paralel sejak Fase 0 oleh klinik.
- Fase 1 menyiapkan: NIK wajib (pengecualian dicatat), ICD-10, penomoran FDI, kode standar, ULID, dan pemisahan pasien, kunjungan, tindakan agar mudah dipetakan ke sumber daya FHIR.
- Tidak ada hapus permanen lewat aplikasi; cadangan menjaga retensi 25 tahun.
- Integrasi eksternal ditempatkan di konteks Integration (anti-corruption layer) saat dibuat. Tidak ada integrasi ad hoc sebelumnya.

## Akibat

Dokumen ini bukan nasihat hukum. Konfirmasi kewajiban ke dinas kesehatan atau konsultan hukum sebelum go-live (`docs/PRD/10-keamanan-kepatuhan.md`).
