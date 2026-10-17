---
description: Kerjakan satu kebutuhan PRD (ID) sesuai alur SIKG
argument-hint: <ID, contoh PAS-02>
---

Kerjakan kebutuhan **$ARGUMENTS** mengikuti `docs/rules/workflow.md`. Jangan menulis tanggal atau estimasi waktu di mana pun (CLAUDE.md M15).

Langkah:

1. Pastikan ID ada di `docs/STATUS.md` dan belum selesai. Bila ID tidak ada atau di luar lingkup (CLAUDE.md M14), berhenti dan tanya.
2. Baca baris kebutuhan itu: `grep -rn "$ARGUMENTS" docs/PRD`. Baca aturan `docs/rules/` yang relevan (backend-ddd, database, frontend, security-privacy, testing-dod, hosting) dan ADR terkait.
3. Periksa apakah ada pertanyaan terbuka yang menentukan (`docs/PRD/09-risiko-pertanyaan-terbuka.md`, `docs/OPEN-QUESTIONS.md`). Bila ya, berhenti dan tanya.
4. Tulis rencana singkat (maksimal sepuluh baris, tanpa tanggal): lapisan, tabel/migrasi, endpoint, halaman, tes. Bila perlu dependensi baru, tabel di luar `docs/rules/database.md`, atau konteks baru, berhenti dan minta persetujuan.
5. Tulis tes yang namanya memuat `$ARGUMENTS` (unit domain, fitur, otorisasi per peran, audit, komponen). Gunakan templat di `tests-templates/`.
6. Implementasi per lapisan: Domain, Application, Infrastructure, Presentation, lalu frontend. Controller tipis dan memanggil satu use case; pemeriksaan izin di server; audit dalam transaksi yang sama.
7. Jalankan `scripts/verify.sh` sampai lulus. Perbaiki penyebabnya; dilarang melemahkan tes, lint, atau pengaman (M16).
8. Gunakan sub-agent `rules-reviewer` pada diff Anda; perbaiki temuan.
9. Centang ID di `docs/STATUS.md`; tulis ADR baru bila ada keputusan arsitektur.
10. Commit: `feat($ARGUMENTS): <ringkasan>` (bahasa Indonesia, tanpa tanggal/estimasi).

Laporan akhir ke pengguna: ID yang selesai, file utama yang berubah, keputusan penting, pertanyaan yang tersisa. Jangan menyatakan selesai bila `scripts/verify.sh` belum lulus.
