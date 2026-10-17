# Pertanyaan terbuka dan konflik

Claude Code menambahkan entri di sini bila menemukan kebutuhan ambigu, konflik antar dokumen, atau keputusan yang bukan wewenangnya. Pengguna menjawab, lalu entri dipindah ke bagian "Terjawab" beserta keputusannya. Jangan menulis tanggal atau estimasi.

Format entri:

```
### Q-<nomor>: <judul singkat>
- Kebutuhan terkait: <ID>
- Dokumen yang bertabrakan atau kurang: <path dan butir>
- Opsi: (a) ... akibatnya ...; (b) ... akibatnya ...
- Rekomendasi:
```

## Terbuka

Pertanyaan bisnis untuk pemilik klinik ada di `docs/PRD/09-risiko-pertanyaan-terbuka.md` (15 butir). Sampai dijawab, pakai asumsi A1 sampai A8 di `docs/PRD/00-ringkasan-lingkup-persona.md`, kecuali pertanyaan itu menentukan struktur data (misalnya skema jasa dokter, butir 8): berhenti dan tanya.

### Q-1: RPO pada shared hosting (tanpa binlog)
- Kebutuhan terkait: NFR-03, SYS-02
- Dokumen yang bertabrakan atau kurang: `docs/PRD/04-non-fungsional.md` NFR-03 (RPO 1 jam) tidak dapat dipenuhi tanpa binlog; `docs/rules/hosting.md` bagian 4
- Opsi: (a) terima RPO sebesar interval dump, usulan maksimal 6 jam, dump lebih sering di jam praktik, akibatnya sampai 6 jam data dapat hilang bila server rusak; (b) wajibkan Profil A untuk klinik yang butuh RPO 1 jam, akibatnya biaya dan keahlian operasional lebih tinggi
- Rekomendasi: (a) untuk klinik 1-3 dokter dengan formulir cetak sebagai cadangan (R6), keputusan pemilik klinik

### Q-2: Dukungan MariaDB
- Kebutuhan terkait: NFR-05, `docs/rules/database.md`
- Dokumen yang bertabrakan atau kurang: banyak shared hosting menyediakan MariaDB, sedangkan stack menetapkan MySQL 8 (M1, M3)
- Opsi: (a) hanya MySQL 8.0+ (penyedia dipilih sesuai), akibatnya pilihan penyedia lebih sempit; (b) uji dan dukung MariaDB versi tertentu di CI, akibatnya perubahan stack yang perlu ADR dan tes ganda (JSON, FULLTEXT, kunci baris, `utf8mb4` collation)
- Rekomendasi: (a) bawaan; (b) hanya bila tidak ada penyedia dalam negeri yang menyediakan MySQL 8

### Q-3: Mekanisme cadangan di Profil B
- Kebutuhan terkait: SYS-02, `docs/operations.md` bagian 3
- Dokumen yang bertabrakan atau kurang: M17 dan `scripts/` terlindungi; M21 melarang `exec` di kode aplikasi, sedangkan dump butuh `mysqldump` dan pengiriman keluar server butuh SFTP atau rclone
- Opsi: (a) skrip shell operasional di `scripts/ops/` yang dipanggil cron panel (mysqldump, enkripsi, kirim), ditulis dan disetujui pengguna; (b) paket `spatie/laravel-backup` (butuh persetujuan dependensi; memakai `Process` di dalam paket dan biner `mysqldump`, bertentangan dengan semangat M21 dan mungkin tidak jalan di hosting); (c) fitur cadangan penyedia hosting saja, akibatnya tidak terenkripsi sendiri dan tidak terpisah penyedia
- Rekomendasi: (a)

### Q-4: Pemantau eksternal untuk `/health`
- Kebutuhan terkait: SYS-07, NFR-13
- Dokumen yang bertabrakan atau kurang: aplikasi tidak bisa memberi tahu bahwa cron-nya sendiri mati; butuh layanan eksternal (uptime/dead-man's-switch) yang mengakses `/up` atau `/health`
- Opsi: (a) layanan uptime pihak ketiga dengan header token; (b) layanan yang hanya mendukung token di URL, dengan token khusus-baca yang hanya menampilkan status ya/tidak
- Rekomendasi: (a) bila tersedia, kalau tidak (b); tidak ada PHI yang pernah dikirim ke layanan itu (M11)

### Q-5: Lingkungan sandbox untuk Composer dan MySQL (catatan lingkungan)
- Kebutuhan terkait: Fase 0 langkah 5, 7, 8; `scripts/verify.sh`
- Keadaan: di sandbox sesi ini `api.github.com/.../zipball` dan `codeload` ditolak (403) walau `git clone` repositori publik berhasil. Paket dev dipasang dengan `composer config --global use-github-api false` dan `composer install --prefer-source`. `phpstan/phpstan` tidak punya sumber git di metadata, jadi arsipnya dibuat dari git publik pada commit yang sama dengan lock lalu ditaruh di cache Composer (tidak ada perubahan pada `composer.lock`). Docker daemon tidak ada; MySQL 8 dipasang dari paket Ubuntu dan dijalankan langsung (`mysqld` dengan datadir terpisah) untuk tes
- Dampak: tidak ada pada repositori. Di mesin lokal atau CI yang normal, `composer install` dan `docker compose up -d` cukup
- Rekomendasi: tidak ada tindakan; dicatat agar sesi berikutnya di sandbox tahu caranya

### Q-6: Berkas kit yang menghalangi `scripts/verify.sh` hijau
- Kebutuhan terkait: M16, `docs/BOOTSTRAP.md` langkah 3 dan 11
- Dokumen yang bertabrakan atau kurang (semua berkas terlindungi, hanya pengguna yang boleh mengubah): (1) `tooling/pint.json` memindai `tests-templates/`, dan tiga templat (`Feature/CronJobsTest.php`, `Feature/IdempotentPaymentTest.php`, `Feature/AuthorizationMatrixTest.php`) gagal Pint; (2) `tooling/tsconfig.base.json` memakai `baseUrl` yang usang di TypeScript 6 dan berhenti berfungsi di 7.0, jadi `tsconfig.json` memakai `ignoreDeprecations: "6.0"` sementara; (3) `.claude/settings.json` menolak `Read(./.env.*)` sehingga `.env.example` tidak dapat disunting Claude Code padahal `CLAUDE.md` M13 membolehkannya, dan hook `pre_bash` ikut memblokir perintah yang menyebut kata kunci `.env` pada isi skrip; (4) `.env.example` di akar masih bawaan Laravel, perlu diganti dengan `tooling/templates/env.example`
(5) `tooling/deptrac.yaml` ditulis untuk Deptrac 2.x dan gagal di 4.7: `paths: ./src` dihitung dari folder `tooling/` dan collector `className` tidak ada (diganti `classNameRegex` dengan pembatas `#...#`). Usulan pengganti yang sudah diuji (0 pelanggaran, aturan lapisan identik) ada di `docs/plans/deptrac-v4.usulan.yaml`; (6) `scripts/guard.py` aturan LAYER menolak Application mengimpor Infrastructure untuk SEMUA konteks, padahal ADR-0001 dan `tooling/deptrac.yaml` mengizinkan konteks CRUD (Clinic, IdentityAccess, Reporting); kode saat ini menyiasatinya dengan port di Domain sehingga tidak melanggar, tetapi aturan itu sebaiknya disamakan; (7) arch test `Src\*\Infrastructure` tidak bekerja pada `toOnlyBeUsedIn` di Pest (daftar konteks ditulis eksplisit di `tests/Architecture/ArchitectureTest.php`; templat di `tests-templates/` perlu disamakan)
(8) `scripts/selftest.py` menyalin `docs/` (termasuk `STATUS.md`) ke proyek sementara tanpa `tests/`, sehingga begitu satu ID dicentang `[x]` kasus "kit bersih" gagal karena aturan REQID (terjadi sekarang pada AUTH-01; job CI `guard` menjalankan selftest lebih dulu). Usulan: selftest mengosongkan centang pada salinan `STATUS.md`; (9) suite "shared hosting" (`scripts/verify.sh --shared` dan job CI `hosting-shared`) mematikan `proc_open`, tetapi PHPUnit sendiri memakainya saat start (`sebastian/version`, perintah `git describe`) sehingga runner langsung fatal. Suite lulus penuh (104 tes) dengan `disable_functions=exec,shell_exec,system,passthru,popen`; larangan `proc_open` pada kode aplikasi tetap dijaga `guard.py` aturan HOST dan arch test. Usulan: hapus `proc_open` dari daftar di `scripts/verify.sh`, `.github/workflows/ci.yml`, dan kalimat serupa di `docs/rules/hosting.md` bagian 3, `docs/PRD/04`, `docs/PRD/12`, `docs/rules/testing-dod.md`, serta templat `tests-templates/Architecture/HostingCompatibilityTest.php`
- Opsi: (a) pengguna memperbaiki butir itu (kecualikan `tests-templates` dari Pint, hapus `baseUrl` dari tsconfig dasar dan sesuaikan alias, longgarkan deny `.env.example`, salin templat env, ganti Deptrac dengan usulan, samakan guard LAYER); (b) membiarkan, akibatnya langkah Pint dan Deptrac di `verify.sh` terus gagal
- Rekomendasi: (a)

### Q-8: Antarmuka untuk menyesuaikan peran dan izin
- Kebutuhan terkait: AUTH-02, AUTH-03, `docs/PRD/00` bagian 4 ("Pemilik klinik dapat menyesuaikan peran tanpa mengubah kode")
- Dokumen yang bertabrakan atau kurang: kalimat itu ada di PRD, tetapi tidak ada ID yang meminta halaman untuk mengubah izin per peran (AUTH-02 hanya meminta peran dan izin granular, dicek di backend, dan menu disembunyikan; AUTH-03 hanya mengelola pengguna). Izin dan peran sudah disimpan di database sehingga dapat diubah tanpa kode, tetapi belum ada layarnya
- Opsi: (a) tambah ID baru (mis. halaman Pengguna dan peran: ubah izin per peran, dengan perlindungan agar Pemilik tidak kehilangan `user.manage` dan `role.manage`); (b) cukup lewat seeder dan perubahan data oleh pengembang pada Fase 1
- Rekomendasi: (a) sebagai Should, dikerjakan setelah Must Fase 1; jangan dibangun sebelum disetujui (M14)

### Q-9: Pergantian kata sandi oleh pengguna sendiri
- Kebutuhan terkait: AUTH-03, AUTH-01
- Dokumen yang kurang: PRD hanya meminta admin membuat pengguna dan mereset kata sandi (kata sandi sementara tampil sekali kepada admin). Tidak ada ID untuk pengguna mengganti kata sandinya sendiri atau memaksa ganti saat masuk pertama, sehingga admin mengetahui kata sandi sementara itu sampai direset lagi
- Opsi: (a) tambah ID (ganti kata sandi sendiri dan wajib ganti setelah reset); (b) biarkan, dengan prosedur bahwa admin mereset lagi bila perlu
- Rekomendasi: (a) sebagai Must kecil, karena menyangkut kerahasiaan akun tenaga kesehatan (Permenkes 24/2022 Pasal 29)

### Q-10: Sumber nama diagnosa ICD-10 bab K00-K14
- Kebutuhan terkait: MST-07, RM-04
- Dokumen yang kurang: tidak ada daftar resmi berbahasa Indonesia di repositori; kode ICD-10 baku, tetapi nama Indonesianya ditulis oleh Claude Code dan belum diverifikasi terhadap daftar resmi (Kemenkes atau PDGI)
- Opsi: (a) tetap memakai daftar awal ini dan menyempurnakannya lewat migrasi data setelah pengguna menyerahkan daftar resmi; (b) pengguna menyediakan berkas CSV resmi untuk diimpor
- Rekomendasi: (b); sampai itu, (a). Kode tidak berubah sehingga data rekam medis yang menautkan kode tetap benar

### Q-11: Skema jasa dokter (asumsi struktur data)
- Kebutuhan terkait: MST-02, KSR-01, P2-09; PRD bagian risiko butir 8
- Dokumen yang kurang: PRD 09 butir 8 menyatakan struktur data bergantung pada jawaban pemilik klinik, yang belum ada. Instruksi untuk tetap bekerja tanpa persetujuan memaksa asumsi sementara
- Asumsi yang dipakai: `doctors.fee_scheme` JSON berisi `default` (`mode` `percentage` dengan `value` basis poin, atau `fixed` dengan `value` rupiah per tindakan) dan `overrides` per kode tindakan dengan bentuk aturan yang sama. Hanya disimpan dan ditampilkan; belum ada perhitungan jasa di laporan (itu P2-09)
- Opsi: (a) pertahankan asumsi ini; (b) pemilik klinik menjawab butir 8 lalu skema diubah lewat migrasi
- Rekomendasi: (b) sebelum KSR/LAP memakai data ini

### Q-12: Penyimpangan kecil pada modul pasien (dikerjakan dengan asumsi)
- Kebutuhan terkait: PAS-01, PAS-02, PAS-03, PAS-04
- Butir: (1) `docs/PRD/08-route-api-kontrak.md` menuliskan pencarian pasien sebagai `GET /internal-api/patients/search?q=`, padahal M11 melarang PHI di query string (kata kunci bisa nama, NIK, atau HP). Dipakai `POST /internal-api/patients/search` dengan badan JSON, throttle 60 per menit sama seperti PRD. (2) `docs/rules/database.md` bagian 3 meminta indeks FULLTEXT pada `name_search`, tetapi indeks FULLTEXT InnoDB tidak melihat baris yang belum di-commit sehingga tes yang dibungkus transaksi tidak dapat memverifikasinya. Dipakai pencarian awalan kata (`LIKE 'kata%'` dan `LIKE '% kata%'`) pada kolom `name_search` yang diindeks bersama `clinic_id`; diukur sekitar 100 milidetik pada 50.000 pasien sintetis (batas PRD 1 detik). Evaluasi mesin pencari terpisah tetap pada batas 200.000 pasien menurut PRD 06. (3) Kolom `nik_missing_reason` ditambahkan pada `patients` untuk alasan NIK belum ada (PAS-01); tabel tidak berubah. (4) Duplikat (PAS-03): NIK sama, atau tanggal lahir sama dengan (nama mirip, selisih maksimal dua karakter, atau HP sama). Pendaftaran ditahan dengan kode `patient.duplicate_suspected` sampai petugas mengonfirmasi. (5) Persetujuan data wajib saat mendaftar; pasien hasil impor (PAS-08) dapat dicatat persetujuannya menyusul. (6) Perawat dan dokter memperbarui riwayat medis lewat izin gabungan `patient.history.manage` (patient.update atau encounter.write) karena matriks PRD tidak memberi mereka `patient.update` padahal alur kerja meminta perawat memperbarui riwayat saat anamnesis. (7) Audit pasien menyimpan NIK dan HP dalam bentuk tersamar, bukan nilai penuh.
- Opsi: (a) pertahankan asumsi ini; (b) pengguna menetapkan lain, lalu disesuaikan lewat perubahan kecil
- Rekomendasi: (a); PRD route table sebaiknya diselaraskan oleh pengguna

### Q-13: Impor pasien (PAS-08): pembaca berpotongan dan batas baris
- Kebutuhan terkait: PAS-08
- Butir: (1) Pembacaan berpotongan memakai kelas `PhpOffice\PhpSpreadsheet` langsung di `Infrastructure/Import`, yaitu mesin yang sudah menjadi dependensi wajib paket `maatwebsite/excel` yang disetujui; fasad `Excel` tidak dipakai karena tidak menyediakan filter baca per potongan tanpa memuat seluruh lembar. Tidak ada paket baru. (2) Batas 5.000 baris data per berkas (`SIKG_IMPORT_MAX_ROWS`) agar pratinjau selesai dalam batas waktu hosting; berkas lebih besar dipecah pengguna. (3) Baris tanpa NIK tidak ditolak dan diberi alasan "Perlu dilengkapi (hasil impor)" sesuai alur migrasi data awal; dugaan duplikat dilewati kecuali petugas memilih ikut mengimpornya. Fitur penandaan "perlu dilengkapi" sebagai daftar kerja tersendiri tidak dibuat (di luar ID); pasien tersebut dapat dicari dan dilengkapi lewat Ubah data. (4) Tabel `import_batches` memuat nomor baris dan pesan galat saja; berkas Excel yang diunggah dienkripsi di disk privat dan dihapus setelah impor selesai.
- Opsi: (a) pertahankan; (b) pengguna menetapkan batas baris lain atau meminta daftar kerja "perlu dilengkapi"
- Rekomendasi: (a)

### Q-7: Tabel pengguna bawaan skeleton Laravel
- Kebutuhan terkait: AUTH-01, M3
- Dokumen yang bertabrakan atau kurang: migrasi `users` dan `sessions` bawaan memakai kunci angka, tanpa ULID, `clinic_id`, dan `branch_id`
- Opsi: (a) ditulis ulang sebagai bagian AUTH-01 sebelum ada migrasi yang digabung (menyunting migrasi yang belum dipakai); (b) migrasi baru yang mengubahnya
- Rekomendasi: (a)

## Terjawab

(belum ada)
