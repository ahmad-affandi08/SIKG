# PRD SIKG: Urutan Rilis dan Gerbang

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 18. Urutan rilis dan gerbang persetujuan

Pekerjaan berjalan berurutan dari Fase 0 sampai Fase 3 dengan tiga gerbang persetujuan dari pemilik klinik, tanpa tanggal target. Dokumen ini sengaja tidak memuat tanggal maupun estimasi durasi; urutan di bawah adalah urutan prioritas kerja. Kecepatan datang dari urutan yang tegas, cakupan Must yang dikunci, dan otomatisasi tes, bukan dari batas waktu.

| Tahap | Isi | Keluaran |
| --- | --- | --- |
| Fase 0: Fondasi | Proyek Laravel 13 dengan Inertia React TypeScript, Tailwind 4, CI, Deptrac dan arch test, Shared, IdentityAccess (AUTH), Clinic dan master data (MST), serta kompatibilitas dua profil hosting (arch test dan job CI hosting bersama; SYS-06 dan SYS-07 menyusul di Fase 1c). Pengurusan registrasi sistem elektronik dan akun SATUSEHAT berjalan paralel. | Kerangka yang lulus semua pengaman |
| **Gerbang 1** | PRD dan prototipe layar utama disetujui pemilik | Cakupan Must dikunci |
| Fase 1a | Patient (PAS), Scheduling (JDW), audit log (SYS-01), soft delete (SYS-03), pengaturan (SYS-04) | Pasien dan jadwal berjalan |
| Fase 1b | Clinical (RM, ODO), Pharmacy (OBT) | Pemeriksaan, odontogram, resep, stok |
| Fase 1c | Billing (KSR), Reporting (LAP), impor pasien (PAS-08), cadangan (SYS-02), kesiapan lingkungan dan denyut cron (SYS-06, SYS-07) | Tagihan sampai laporan |
| Uji penerimaan | UAT dengan staf klinik, migrasi data pasien, pelatihan per peran, uji pemulihan, uji beban pada hosting sasaran (shared hosting atau server) | Temuan UAT diperbaiki |
| **Gerbang 2: go-live Fase 1** | Kriteria go-live bagian 16 terpenuhi dan disetujui pemilik | Klinik berhenti memakai buku dan Excel |
| Fase 2 | P2-01 (SATUSEHAT) lebih dulu, lalu P2-02 sampai P2-09 berurutan prioritas yang ditetapkan pemilik | Kepatuhan, pendapatan, kontrol operasional |
| **Gerbang 3** | Fase 2 selesai dan lulus UAT dengan staf klinik | Persetujuan memulai Fase 3 |
| Fase 3 | P3-01 sampai P3-06 sesuai prioritas pemilik | Pengalaman pasien dan integrasi lanjutan |

**Aturan urutan**

- Kebutuhan Must Fase 1 dikerjakan sebelum Should, Should sebelum Could.
- Fase berikutnya tidak dimulai sebelum gerbang sebelumnya disetujui, kecuali pemilik memintanya secara tertulis.
- Permintaan baru di luar PRD masuk daftar dan diprioritaskan; tidak menyela pekerjaan berjalan.
- Estimasi durasi tidak dibuat. Kemajuan diukur dari ID kebutuhan yang selesai (`docs/STATUS.md`).
