# Aturan alur kerja

## 1. Siklus satu kebutuhan

1. **Pilih** ID dari `docs/STATUS.md`. Urutan: Fase 1 Must, lalu Fase 1 Should, lalu Fase 1 Could, baru Fase 2, lalu Fase 3. Satu ID per siklus; ID kecil yang sangat berkaitan boleh digabung dalam satu cabang, tetapi commit tetap terpisah.
2. **Baca** baris PRD untuk ID itu (cari dengan `grep -rn "PAS-02" docs/PRD`) dan aturan di `docs/rules/` untuk area yang disentuh.
3. **Rencana singkat** (maksimal sepuluh baris): lapisan yang berubah, tabel atau migrasi, endpoint, halaman, tes. Tanpa tanggal dan tanpa estimasi waktu (Aturan Mutlak M15).
4. **Tes**: tulis atau perbarui tes yang memuat ID kebutuhan di namanya.
5. **Implementasi** berurutan: Domain, Application, Infrastructure, Presentation, frontend.
6. **Verifikasi**: `scripts/verify.sh`. Perbaiki penyebab, jangan melemahkan pengaman.
7. **Catat**: centang ID di `docs/STATUS.md`; bila ada keputusan arsitektur baru, tulis ADR.
8. **Commit**.

## 2. Kapan wajib berhenti dan bertanya

Berhenti dan tanya pengguna (jangan menebak) bila:

- Dua dokumen atau dua aturan saling bertentangan.
- Kebutuhan tidak jelas, tidak lengkap, atau tidak ada di PRD.
- Pekerjaan membutuhkan dependensi baru, tabel di luar `docs/rules/database.md`, atau perubahan pada aturan mutlak.
- Perubahan akan menghapus data, mengubah data produksi, atau mengubah skema tabel yang sudah dipakai (selain migrasi expand-contract biasa).
- Tes yang sah gagal dan satu-satunya jalan keluar tampak melemahkan tes atau aturan.
- Menemukan indikasi data pasien nyata, rahasia, atau kebocoran di repositori.
- Pertanyaan terbuka di `docs/PRD/09-risiko-pertanyaan-terbuka.md` menentukan keputusan (contoh: skema jasa dokter).

Cara bertanya: satu pesan, sebutkan ID kebutuhan, dokumen dan butir aturan yang bertabrakan, dan dua atau tiga opsi dengan akibatnya. Catat juga di `docs/OPEN-QUESTIONS.md`.

## 3. Hal yang tidak boleh dilakukan di dokumen dan komunikasi

- Menulis tanggal target, estimasi hari atau minggu, jadwal, atau durasi pekerjaan (M15). Cukup tulis urutan dan prioritas.
- Mengubah `docs/PRD/**`, `docs/rules/**`, `docs/adr/**` (kecuali menambah ADR baru yang diminta), `CLAUDE.md`, `.claude/**`, `scripts/**`, `.github/**`, atau `tooling/**` tanpa permintaan eksplisit. Hook akan menolak; jangan mencari jalan memutarnya.
- Mengklaim selesai tanpa verifikasi (M19).
- Menambah fitur "bonus" di luar ID yang dikerjakan.

## 4. Commit dan cabang

- Cabang: `feat/<ID>-<slug>`, `fix/<slug>`, `chore/<slug>`.
- Pesan commit: `<tipe>(<ID>): <ringkasan imperatif>` dalam bahasa Indonesia; tipe: `feat`, `fix`, `test`, `refactor`, `docs`, `chore`.
- Satu commit = satu perubahan koheren yang lulus `scripts/verify.sh`.
- Jangan commit: `.env`, kunci, berkas unggahan, dump database, data pasien.
- Migrasi yang sudah digabung tidak diedit; buat migrasi baru.

## 5. Daftar periksa sebelum menyatakan selesai

- [ ] Semua kriteria penerimaan pada baris PRD terpenuhi
- [ ] Tes memuat ID, termasuk tes otorisasi dan audit bila relevan
- [ ] `scripts/verify.sh` hijau (tidak ada tes yang dilewati atau dinonaktifkan)
- [ ] Tidak ada pelanggaran Aturan Mutlak, tidak ada tanggal atau estimasi waktu yang ditulis
- [ ] `docs/STATUS.md` diperbarui
- [ ] Ringkasan ke pengguna memuat: ID yang selesai, file utama yang berubah, keputusan penting, pertanyaan yang tersisa

## 6. Penanganan kebutuhan Should dan Could

- Should dikerjakan setelah semua Must pada fase yang sama selesai, kecuali pengguna meminta lain.
- Could hanya bila diminta.
- Bila sebuah Should menghalangi Must lain, naikkan sebagai pertanyaan, jangan menaikkan prioritasnya sendiri.
