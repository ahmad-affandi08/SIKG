# Aturan pengujian dan Definition of Done

## 1. Prinsip

- Tes adalah bagian dari fitur, bukan tambahan. Tidak ada kebutuhan selesai tanpa tes yang memuat ID-nya (contoh `it('KSR-04 pembayaran idempoten')`).
- Dilarang menghapus, melewati (`skip`, `todo` untuk kebutuhan yang sudah dikerjakan), atau melemahkan tes agar lulus (M16).
- Tes backend memakai MySQL asli. Tes frontend memakai jsdom dan Testing Library.
- Tes deterministik: waktu lewat `Clock` palsu, ID lewat generator uji, tanpa jaringan.

## 2. Piramida tes

| Jenis | Alat | Isi | Target |
| --- | --- | --- | --- |
| Unit domain | Pest | Entity, value object, aturan bisnis (`Money`, tagihan, stok, jadwal, kunci klinis) | Cakupan domain minimal 70%, 100% untuk aturan uang |
| Fitur / integrasi | Pest + MySQL | Use case, route, repository, migrasi, audit | Setiap ID Must punya tes fitur jalur utama dan satu jalur galat |
| Arsitektur | Pest arch + Deptrac | Aturan lapisan dan lintas konteks | Selalu hijau |
| Otorisasi | Pest | Setiap sel matriks peran x aksi, IDOR antar-klinik | 100% sel dalam matriks |
| Konkurensi | Pest + proses paralel | Bentrok jadwal, nomor urut, pembayaran ganda, stok | Lulus berulang kali |
| Kontrak API | Pest + skema | Bentuk respons `{data, meta}`, 422, kode galat bisnis | Setiap endpoint `/internal-api` |
| Komponen | Vitest + Testing Library | Form, odontogram, tabel, autocomplete | Komponen kritis |
| Skema Zod | Vitest | Kasus sah dan tidak sah | Semua skema form |
| E2E | Playwright | Alur utama (bagian 4) | Lulus di CI sebelum rilis |
| Beban | k6 | Pencarian pasien, antrean, kasir | Memenuhi NFR sebelum go-live, diuji pada hosting sasaran |
| Kompatibilitas hosting | Pest + job CI | Seluruh suite fitur dengan driver `database`, tanpa Redis, dan `disable_functions=exec,shell_exec,system,passthru,proc_open,popen`; arch test larangan fungsi dan facade Redis | Selalu hijau (M21) |

## 3. Tes wajib per area

**Uang dan tagihan (100%):** pembulatan persen, diskon, total, selisih pembayaran, pembayaran sebagian, deposit, cicilan, void, refund, idempotensi (`Idempotency-Key` ganda), kas tidak boleh menyimpang dari pembayaran, tarif salinan tidak berubah saat master berubah.

**Jadwal:** tidak ada janji ganda untuk dokter yang sama dan kursi yang sama (termasuk uji konkurensi dua proses), jam praktik dan libur, status janji, antrean walk-in.

**Rekam medis:** kunjungan ditutup tidak bisa diedit, koreksi 2x24 jam oleh yang berwenang, addendum, riwayat versi, peringatan alergi, `access_logs` tertulis saat dibuka.

**Odontogram:** penomoran FDI, snapshot per kunjungan, keadaan terkini dari snapshot terbaru, kondisi dari master.

**Obat dan stok:** stok tidak negatif, override Admin dengan alasan, kedaluwarsa, penyesuaian tercatat, resep terkait kunjungan.

**Keamanan:** akses tanpa login ditolak, tanpa izin ditolak (403), IDOR antar-klinik ditolak, rate limit login, PHI tidak muncul di log (tes pemindai log), unggahan berbahaya ditolak, URL bertanda tangan kedaluwarsa.

**Audit:** tiap aksi sensitif menulis `audit_logs` dengan sebelum dan sesudah.

## 4. Alur E2E wajib (Playwright)

1. Masuk sebagai resepsionis, daftar pasien baru, buat janji.
2. Pasien datang, masuk antrean, dokter memanggil, kunjungan dimulai.
3. Dokter mengisi SOAP, odontogram, tindakan, diagnosis, resep, lalu menutup kunjungan.
4. Kasir melihat tagihan otomatis, membayar sebagian lalu lunas, mencetak kuitansi.
5. Pemilik melihat laporan harian dan menutup kas.
6. Pengguna tanpa izin tidak melihat atau tidak bisa memanggil aksi terlarang.

## 5. Definition of Done (per kebutuhan)

- [ ] Kriteria penerimaan di PRD terpenuhi
- [ ] Tes unit, fitur, otorisasi, dan komponen yang relevan ada dan lulus; nama memuat ID
- [ ] `scripts/verify.sh` hijau tanpa tes yang dilewati
- [ ] Larastan level maksimal yang ditetapkan di `tooling/phpstan.neon` bersih, tanpa penambahan baseline
- [ ] Deptrac dan arch test bersih
- [ ] ESLint dan `tsc --noEmit` bersih
- [ ] Pint dan Prettier bersih
- [ ] Tidak ada PHI di log, data uji, atau URL
- [ ] Bila menambah job, tugas terjadwal, berkas, PDF, atau Excel: memenuhi `docs/rules/hosting.md` (job pendek dan idempoten, tanpa Redis/daemon/exec/symlink) dan lulus job kompatibilitas hosting
- [ ] Aksi sensitif menulis audit; pembukaan rekam medis menulis akses
- [ ] Migrasi dapat naik dan turun
- [ ] Pesan antarmuka berbahasa Indonesia; format tanggal dan uang benar
- [ ] Halaman memiliki keadaan memuat, kosong, dan galat; dapat dipakai dengan klaviatur
- [ ] `docs/STATUS.md` diperbarui dan ADR ditulis bila ada keputusan arsitektur
- [ ] Tidak ada tanggal atau estimasi waktu yang ditulis (M15)

## 6. Kriteria siap go-live (gerbang, bukan tanggal)

Go-live hanya setelah semua poin ini terpenuhi dan dinyatakan pengguna:

- [ ] Semua kebutuhan Must Fase 1 selesai dan lulus DoD
- [ ] Seluruh tes otomatis hijau di CI, termasuk E2E dan konkurensi
- [ ] Uji beban memenuhi NFR (halaman p95 di bawah 1,5 detik, pencarian pasien di bawah 300 ms) pada hosting sasaran
- [ ] Halaman Kesiapan Lingkungan (SYS-06) tanpa butir gagal pada hosting sasaran, dan denyut cron dan antrean (SYS-07) hidup
- [ ] Bila Profil B: batas di `docs/rules/hosting.md` bagian 4 disetujui pemilik secara tertulis
- [ ] Tes pemulihan cadangan berhasil dan didokumentasikan
- [ ] Pemeriksaan keamanan (`composer audit`, `npm audit`, tinjauan header, tinjauan izin) bersih
- [ ] Migrasi data lama sudah dicoba pada salinan, rekonsiliasi cocok
- [ ] Pelatihan peran selesai dan UAT disetujui klinik
- [ ] Pendaftaran PSE dan akun SATUSEHAT berjalan paralel (tidak menghalangi Fase 1, menghalangi P2-01)
- [ ] Rencana kembali (rollback) dan kontak dukungan tersedia

## 7. Cakupan minimum (NFR)

- Domain minimal 70%, aturan uang dan tagihan 100%.
- Setiap endpoint `/internal-api` punya tes kontrak.
- Setiap sel matriks otorisasi punya tes.
