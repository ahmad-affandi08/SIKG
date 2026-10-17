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
- Keputusan: (a) disetujui pengguna dan dikerjakan sebagai AUTH-07 (Should); asumsi rinci di Q-25

### Q-9: Pergantian kata sandi oleh pengguna sendiri
- Kebutuhan terkait: AUTH-03, AUTH-01
- Dokumen yang kurang: PRD hanya meminta admin membuat pengguna dan mereset kata sandi (kata sandi sementara tampil sekali kepada admin). Tidak ada ID untuk pengguna mengganti kata sandinya sendiri atau memaksa ganti saat masuk pertama, sehingga admin mengetahui kata sandi sementara itu sampai direset lagi
- Opsi: (a) tambah ID (ganti kata sandi sendiri dan wajib ganti setelah reset); (b) biarkan, dengan prosedur bahwa admin mereset lagi bila perlu
- Rekomendasi: (a) sebagai Must kecil, karena menyangkut kerahasiaan akun tenaga kesehatan (Permenkes 24/2022 Pasal 29)
- Keputusan: (a) disetujui pengguna dan dikerjakan sebagai AUTH-06 (Must kecil); asumsi rinci di Q-25

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
- Keputusan sementara: (a) dipertahankan. LAP-03 sudah memakai skema ini (Q-23 butir 5) dan menampilkan catatan asumsinya. Ini keputusan bisnis pemilik klinik, bukan teknis, sehingga tetap terbuka sampai pemilik menjawab tiga hal: (1) jasa dokter berupa persentase, jumlah tetap per tindakan, atau gabungan; (2) dihitung dari tagihan terbit atau saat dibayar lunas; (3) apakah biaya laboratorium atau bahan dipotong lebih dulu. Skema yang berbeda diubah lewat migrasi `doctors.fee_scheme` dan `FeeScheme`, tanpa mengubah laporan lain

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

### Q-14: Penjadwalan: asumsi aturan dan batas pengujian konkurensi
- Kebutuhan terkait: JDW-01 sampai JDW-06, JDW-08
- Butir: (1) Bentrok dicegah dengan kunci baris pada dokter, kursi, dan pasien lewat tabel `sequences` (nama `lock`) di dalam transaksi, lalu pemeriksaan tumpang tindih; rentang setengah terbuka sehingga janji yang selesai tepat saat janji lain mulai tidak bentrok. Pasien yang sama juga tidak boleh punya dua janji bertumpuk. (2) Walk-in tidak diperiksa terhadap jadwal praktik dan bentrok karena urutannya ditentukan antrean; janji berstatus Batal atau Tidak datang melepas slot. (3) Check-in hanya pada hari janji (zona waktu klinik); Tidak datang hanya setelah jam janji; Jadwal ulang hanya untuk janji Dijadwalkan atau Dikonfirmasi dan mengembalikan status ke Dijadwalkan. (4) Status Sedang diperiksa dan Selesai diubah konteks Clinical lewat kontrak `AppointmentProgress` (belum ada tombol manual untuk resepsionis). (5) Uji konkurensi dengan dua proses paralel (`docs/PRD/12`) belum ada: tes Pest berjalan dalam satu transaksi per tes sehingga tidak dapat meniru dua koneksi; yang diuji adalah penguncian baris (kunci terambil) dan pemeriksaan tumpang tindih. (6) Papan antrean (JDW-06) memakai satu halaman Antrean yang di-poll tiap 10 detik dan dipakai resepsionis maupun dokter; layar khusus dokter tidak dibuat terpisah.
- Opsi: (a) pertahankan; (b) pengguna meminta uji konkurensi proses paralel dan layar dokter khusus
- Rekomendasi: (a); (b) dapat menjadi ID baru bila diperlukan

### Q-15: Rekam medis: tafsiran kunci catatan dan batas yang dipilih
- Kebutuhan terkait: RM-01 sampai RM-05, RM-07
- Butir: (1) Kunjungan dibuat otomatis oleh event `PatientCheckedIn` (idempoten per janji); `POST /encounters` hanya membuat atau mengembalikan kunjungan dari janji yang sudah check-in. (2) Selama kunjungan berjalan, catatan SOAP dapat diubah penulis yang berhak (`encounter.write`); setiap perubahan membuat versi baru yang menunjuk versi lama (`supersedes_id`), baris lama tidak pernah ditimpa. (3) Setelah ditutup: perbaikan langsung hanya oleh pemegang `encounter.close` (Dokter) dalam 2x24 jam sejak input pertama catatan itu; bagian catatan yang belum pernah ditulis tidak dapat ditambah langsung; selebihnya lewat addendum (`encounter.addendum`, Dokter) dengan isi dan alasan wajib, bertanda waktu dan nama penulis. (4) Diagnosa dan tindakan hanya dapat ditambah atau dibatalkan selama kunjungan berjalan; setelah ditutup tidak ada perubahan struktur (koreksi lewat addendum), karena tindakan memengaruhi tagihan. (5) PRD RM-07 menyebut addendum "dengan persetujuan Admin atau pimpinan klinik", tetapi matriks hak akses tidak memiliki izin persetujuan addendum dan matriks termasuk berkas terlindungi; addendum dicatat langsung tanpa langkah persetujuan dan seluruhnya masuk audit. Usulan: tambah izin `encounter.addendum.approve` bila pemilik klinik menghendaki persetujuan. (6) Kunjungan tidak dapat ditutup bila tidak memiliki catatan, diagnosa, ataupun tindakan. (7) Simpan otomatis draf tiap 30 detik (PRD 07) belum dibuat: setiap penyimpanan catatan membuat versi baru sehingga simpan otomatis akan membanjiri riwayat versi; perlu keputusan apakah draf disimpan di tempat terpisah. (8) Suhu disimpan sebagai persepuluh derajat (bilangan bulat) dan tekanan darah wajib berpasangan. (9) Tabel `clinical_notes` mendapat kolom `section` (ekstra oral atau intra oral untuk bagian O) dan `reason`; `encounters` mendapat `anamnesis_at` untuk menghitung batas koreksi anamnesis.
- Opsi: (a) pertahankan; (b) pengguna menetapkan izin persetujuan addendum dan perlakuan draf otomatis
- Rekomendasi: (a) sementara; (b) sebelum go-live

### Q-16: Odontogram: asumsi model data
- Kebutuhan terkait: ODO-01 sampai ODO-04
- Butir: (1) Snapshot kunjungan hanya memuat perubahan yang dicatat pada kunjungan itu; odontogram terkini dihitung dari entri terbaru tiap pasangan gigi dan permukaan lintas snapshot (sesuai aturan basis data). Kondisi seluruh gigi (permukaan `W`) menimpa semua entri permukaan yang dicatat lebih dulu atau bersamaan. (2) "Menghapus" kondisi dilakukan dengan menetapkan kondisi Sehat (SEH); baris odontogram tidak dihapus (M5). (3) Setiap kondisi memiliki cakupan (per permukaan, seluruh gigi, atau keduanya) yang dijaga server; cakupan bawaan: karies dan tambalan per permukaan; mahkota, perawatan saluran akar, impaksi, hilang, sisa akar, implan, gigi tiruan seluruh gigi; sehat dan lainnya keduanya. (4) Kode kondisi tidak dapat diubah setelah dibuat karena snapshot menyimpan kode, bukan id. (5) Odontogram hanya dapat diubah selama kunjungan berjalan; setelah ditutup terkunci (koreksi lewat addendum teks). (6) Daftar kondisi bawaan dibuat otomatis saat klinik dibuat (dan lewat migrasi untuk klinik yang sudah ada). (7) Tabel `odontogram_tooth_states` mendapat `set_by` dan `set_at` untuk menentukan urutan entri.
- Opsi: (a) pertahankan; (b) pengguna menetapkan daftar kondisi bawaan dan aturan cakupan lain
- Rekomendasi: (a)

### Q-17: Obat dan stok: asumsi aturan
- Kebutuhan terkait: OBT-01 sampai OBT-05
- Butir: (1) Stok disimpan sebagai bilangan bulat dalam satuan terkecil obat; satuan pecahan belum didukung. (2) Peringatan alergi memakai pencocokan nama obat, golongan obat, dan kata kunci alergi pasien (tanpa basis data interaksi obat); dokter dapat tetap meresepkan dengan alasan konfirmasi yang masuk audit. (3) Obat yang ditandai dijual di klinik mengurangi stok saat resep dibuat dan memicu event `MedicineDispensed`; pembatalan resep hanya selama kunjungan berjalan dan mengembalikan stok lewat event `MedicineReturned`. (4) Stok tidak boleh negatif; hanya pemegang izin `stock.override` (Admin) yang dapat memaksa pengeluaran melebihi stok, dan kekurangannya dicatat sebagai penyesuaian bertanda dengan alasan di audit. (5) Mutasi stok tidak dapat diubah atau dihapus (hanya mutasi koreksi baru). (6) Nomor batch dan tanggal kedaluwarsa dicatat pada mutasi masuk, tetapi stok belum dilacak per batch; OBT-06 (peringatan kedaluwarsa) hanya dapat memakai tanggal kedaluwarsa dari mutasi masuk terbaru sampai pelacakan per batch (P2-05) dibuat. (7) Kop surat resep dan nomor SIP dokter diambil lewat kontrak `LetterheadProvider` dan `DoctorDirectory`; resep dicetak sebagai PDF lewat dompdf.
- Opsi: (a) pertahankan; (b) pengguna menetapkan pelacakan stok per batch lebih awal dari P2-05
- Rekomendasi: (a)

### Q-18: scripts/verify.sh gagal pada Pint dan Deptrac karena berkas terlindungi
- Kebutuhan terkait: M16, M19
- Butir: (1) Pint gagal hanya pada `tests-templates/` (CronJobsTest, IdempotentPaymentTest, AuthorizationMatrixTest), berkas terlindungi yang tidak boleh diedit Claude Code; Pint pada `app src tests database routes config` lulus. (2) Deptrac gagal sebelum menganalisis: jalur `./src` di konfigurasi Deptrac dibaca relatif terhadap folder konfigurasi (`tooling/src`), dan collector `className` dengan `regex` tidak dikenal oleh deptrac terpasang (perlu `classNameRegex` dengan `value` berpembatas `#...#`). Dengan salinan konfigurasi sementara di luar repositori yang menyesuaikan kedua hal itu, Deptrac melaporkan 0 pelanggaran (satu pelanggaran nyata pada `Pharmacy\Contracts\StockLedger` sudah diperbaiki).
- Opsi: (a) pengguna memperbaiki konfigurasi Deptrac (jalur dan sintaks collector) dan merapikan `tests-templates/` dengan Pint; (b) pengecualian jalur `tests-templates` pada konfigurasi Pint
- Rekomendasi: (a)

### Q-19: Aset huruf Schibsted Grotesk dihosting sendiri
- Kebutuhan terkait: M1 (dependensi), `docs/rules/frontend.md` bagian 6
- Butir: Desain tampilan Swiss (pilihan pengguna) memakai huruf Schibsted Grotesk (lisensi SIL OFL). Dua berkas woff2 (latin dan latin-ext) disimpan di `resources/fonts/` dan dimuat lewat `@font-face` di `resources/css/app.css`, sehingga tidak ada permintaan ke CDN dan CSP `font-src 'self'` tetap berlaku. Ini aset statis, bukan paket npm atau Composer, tetapi dicatat di sini agar pengguna dapat meninjau atau menggantinya.
- Opsi: (a) pertahankan; (b) pakai huruf sistem saja (hapus dua berkas dan blok `@font-face`)
- Rekomendasi: (a)

### Q-20: Desain Klasik menggantikan sidebar dan toggle sidebar
- Kebutuhan terkait: `docs/rules/frontend.md` bagian 6 (tata letak), permintaan pengguna sebelumnya (sidebar yang dapat ditutup dan dibuka)
- Butir: Atas pilihan pengguna ("A. Klasik"), tata letak aplikasi kini berupa menubar teks dengan menu turun, bar jejak halaman, satu panel isi yang menggulir sendiri, dan status bar biru (klinik, pengguna dan peran, jam). Sidebar dan tombol buka-tutupnya dihapus karena menubar sudah memuat seluruh menu. Halaman Pemeriksaan memakai tiga panel (antrean hari ini, tab pemeriksaan, ringkasan pasien). Target sentuh tetap 44 px pada layar sentuh lewat `@media (pointer: coarse)`; pada pointer halus tinggi kontrol 28 px agar padat. Bila dokumen aturan memuat ketentuan sidebar atau ukuran kontrol, perlu disesuaikan oleh pengguna.
- Opsi: (a) pertahankan menubar; (b) tambahkan kembali sidebar sebagai opsi tampilan
- Rekomendasi: (a)

### Q-21: Keputusan asumsi Billing (KSR-01 sampai KSR-08)
- Kebutuhan terkait: KSR-01, KSR-02, KSR-04, KSR-06, KSR-07, KSR-08, M7, M10
- Butir: (1) Satu tagihan per kunjungan; draft dibuat otomatis oleh event tindakan dan obat dan terbit (bernomor) saat kunjungan ditutup; kunjungan tanpa item tidak menghasilkan tagihan. (2) Uang muka boleh dicatat pada draft (jenis dp); pembayaran biasa hanya setelah terbit; kelebihan uang muka dibanding total menahan penerbitan otomatis sampai diselesaikan kasir. (3) Item klinis tidak dapat diubah kasir (hanya diskon); kasir hanya menambah, mengubah jumlah, dan menghapus item manual, dan hanya sebelum ada pembayaran. (4) Batas diskon per peran disimpan di pengaturan klinik (bawaan Pemilik 100%, Admin 100%, Kasir 10%); peran yang tidak tercantum 0%. (5) Format nomor kwitansi tetap KW-{yyyy}-{seq:5} (belum menjadi pengaturan). (6) Tutup kas menghitung tunai bersih milik kasir yang menutup (pembayaran dan uang muka dikurangi refund yang dicatat kasir itu sendiri); refund tunai yang dicatat Admin atau Pemilik tidak masuk hitungan kasir mana pun, sehingga uang yang keluar dari laci karena refund Admin tampil sebagai selisih yang dijelaskan di catatan. (7) Item draft yang dibuat dari event tidak punya pelaku sendiri; auditnya bertanda pelaku kosong, sedangkan pelakunya tercatat di catatan klinis asalnya. (8) Event datang setelah commit transaksi klinis; bila penanganannya gagal, tindakan tercatat tanpa item tagihan. Pemeriksaan rekonsiliasi (tindakan tanpa tagihan = 0) menjadi bagian laporan LAP.
- Opsi: (a) pertahankan asumsi di atas; (b) ubah per butir
- Rekomendasi: (a)

### Q-22: Keputusan asumsi cadangan (SYS-02)
- Kebutuhan terkait: SYS-02, NFR-03, M1, M21, Q-1, Q-3
- Butir: (1) Cadangan memakai mesin PHP murni (data saja, dienkripsi XChaCha20-Poly1305 dengan kunci dari `BACKUP_KEY` atau diturunkan dari `APP_KEY`), bukan `mysqldump`, karena kode aplikasi dilarang memakai proses sistem (M21). (2) Disk tujuan bawaan di server yang sama; salinan ke lokasi terpisah dilakukan penyedia atau cron hosting, bukan kode aplikasi, karena adaptor SFTP atau S3 akan menjadi dependensi baru (M1). Mekanisme akhir mengikuti Q-3. (3) Interval bawaan 24 jam; interval 6 jam pada shared hosting menunggu keputusan pemilik di Q-1. (4) Uji pemulihan terjadwal memulihkan ke tabel sementara (perlu hak `CREATE TEMPORARY TABLES`); uji ke server terpisah tetap dilakukan manual dan dicatat. (5) Pemulihan bencana hanya ke basis data kosong yang sudah dimigrasi sampai versi yang sama; perintah menolak basis data berisi. (6) Binlog dan pemulihan ke titik waktu (RPO 1 jam di server) berada di luar aplikasi dan diatur di server.
- Opsi: (a) pertahankan; (b) tambahkan dependensi adaptor penyimpanan jarak jauh lewat persetujuan di `tooling/approved-dependencies.json`
- Rekomendasi: (a), lalu (b) hanya bila hosting sasaran tidak menyediakan alat sinkronisasi

### Q-23: Keputusan asumsi pelaporan (LAP-01 sampai LAP-10)
- Kebutuhan terkait: LAP-01 sampai LAP-10, dasbor Pemilik, M4, M9, M21, hosting.md butir 3 sampai 10 dan 13, Q-11, Q-15, Q-21
- Butir:
  1. **Hak akses.** Laporan dan cakupan diputuskan per laporan di Application dari izin yang sudah ada; matriks peran tidak diubah. Laporan keuangan (LAP-01, 02, 03, 06, 08): `report.financial` (penuh) atau `report.financial.own` (Kasir: hanya penerimaan dan penutupan kas yang ia catat; LAP-01 untuk Kasir tanpa angka tagihan klinik; piutang LAP-06 tetap terlihat karena Kasir menagihnya dan sudah berhak membuka seluruh tagihan lewat `invoice.view`). Laporan klinis (LAP-04, 05, 09): `report.clinical` atau `report.clinical.own` (Dokter: hanya kunjungan yang ia tangani, lewat tautan `doctors.user_id`; dokter tanpa profil tidak melihat apa pun). LAP-10: `audit.view`. PRD menyebut Dokter di LAP-05 dan LAP-09, Kasir di LAP-01, 06, 08 sesuai ini. **LAP-07 untuk Perawat**: matriks PRD 00 tidak memberi Perawat `report.view`, padahal PRD 11 menyebut Perawat di LAP-07; laporan stok dibuka bagi pemegang `report.clinical` atau `stock.manage`, dan pintu masuk halaman laporan (`reports.open`) menerima `report.view` atau `stock.manage`. Perawat melihat laporan stok saja.
  2. **Dua dasar angka yang tidak dicampur.** Pendapatan = total tagihan final (terbit, bukan draft, bukan batal) menurut tanggal terbit; penerimaan = pembayaran dan uang muka dikurangi refund menurut tanggal bayar. Tagihan batal tampil di tabel terpisah dan tidak masuk pendapatan; refund otomatis akibat pembatalan ikut mengurangi penerimaan pada tanggalnya. Filter metode bayar pada angka pendapatan hanya mengambil tagihan yang pernah dibayar dengan metode itu.
  3. **Ekspor selalu lewat antrean; tampilan langsung.** Laporan dihitung dengan kueri agregasi terindeks saat dibuka (batas rentang 366 hari, tiap tabel dipotong pada 2.000 baris, uji NFR-01 harian < 3 detik pada 1.500 tagihan) sehingga tidak ada laporan layar yang mendekati 5 detik. Berkas PDF dan XLSX selalu dibuat oleh job antrean (`report_jobs`: menunggu, berjalan, selesai, gagal; `GET /internal-api/report-jobs/{id}` dipoll tiap 2 detik; berkas dienkripsi di disk privat, hanya pemintanya yang dapat melihat dan mengunduh, masa simpan 7 hari lalu dilepas oleh `sikg:reports:prune` harian). Job pendek dan idempoten (`tries` 1; galat menandai berkas gagal dengan pesan aman dan pengguna meminta ulang); berkas berjalan lebih dari 10 menit dinyatakan gagal agar layar tidak menunggu selamanya. Karena keluaran sudah teragregasi dan dibatasi, tidak ada pemecahan potongan; XLSX tidak memuat data mentah per baris besar. Permintaan dan unduhan tercatat di audit log (`report.export_requested`, `report.downloaded`).
  4. **Definisi.** Pasien baru = pasien yang kunjungan pertamanya (pada dokter yang dipilih bila ada) jatuh dalam rentang. Waktu tunggu = menit dari check-in sampai anamnesis atau catatan pertama (mana yang lebih dulu), hanya kunjungan ber-janji; belum ada penanda "mulai periksa" tersendiri. Tingkat tidak datang = janji Tidak datang dibagi janji yang seharusnya hadir (check-in, sedang diperiksa, selesai, tidak datang). Umur piutang dihitung dari tanggal terbit sampai hari ini. Uang muka belum terpakai = uang muka pada tagihan yang belum diterbitkan. Hampir kedaluwarsa = batch yang tercatat saat stok masuk dengan tanggal kedaluwarsa dalam 30 hari (OBT-06) atau sudah lewat pada obat yang stoknya masih ada; sisa per batch tidak dilacak sehingga kolom Diterima adalah jumlah saat batch masuk. Stok di bawah minimum = stok kurang dari minimum (minimum nol berarti tidak dipantau). Nilai tindakan LAP-05 = jumlah x tarif saat dicatat, sebelum diskon tagihan.
  5. **Jasa dokter (LAP-03)** dihitung dari skema asumsi Q-11 (persentase atau jumlah tetap per tindakan, pengecualian per kode) atas nilai item tindakan pada tagihan final setelah diskon item; diskon tagihan keseluruhan tidak dibagi ke item; jasa timbul saat tagihan terbit (bukan saat lunas); obat dan item manual tidak berjasa. Potongan biaya lab, skema bertingkat, dan slip tetap P2-09. Hasil bergantung pada jawaban pemilik atas Q-11.
  6. **Kelengkapan rekam medis (LAP-09)**: lengkap = kunjungan ditutup dengan sedikitnya satu diagnosa dan satu tindakan (indikator PRD 00); catatan SOAP dilaporkan terpisah. "Tanda tangan" belum punya data tersendiri; penutupan kunjungan oleh dokter (RM-07) dipakai sebagai tanda tangan, sehingga kunjungan yang masih terbuka lewat hari kunjungan dihitung "belum ditutup dokter".
  7. **Dasbor Pemilik** tampil di Beranda bagi pemegang `report.financial` dan `report.clinical` (Pemilik dan Admin). Bulan ini dibandingkan dengan bulan lalu pada rentang hari yang sama (pembanding yang adil), total bulan lalu juga dikirim. Grafik 30 hari berupa SVG tanpa pustaka grafik (M1) dengan tabel tersembunyi bagi pembaca layar.
  8. **Zona waktu.** Hari dan bulan dikelompokkan menurut zona klinik dengan selisih tetap pada awal rentang (`CONVERT_TZ` dengan selisih, tanpa tabel zona MySQL); benar untuk zona Indonesia tanpa waktu musim panas.
  9. Daftar kebutuhan LAP-01 sampai LAP-10 belum ada di `docs/STATUS.md`; ditambahkan sebagai prioritas Should (S) agar hitungan Must Fase 1 tidak berubah. Pengguna dapat mengubah prioritasnya.
  10. **Keputusan atas penyerahan pengguna ("buat yang terbaik")**: (a) `audit_logs` dipakai untuk pembukaan (hanya laporan yang memuat nama pasien: LAP-06 dan LAP-09), permintaan ekspor, dan unduhan; `access_logs` tetap khusus pembukaan rekam medis satu pasien dan tidak diubah. Alasan wajib seperti `record.export` tidak diminta karena laporan bukan salinan rekam medis. (b) LAP-09 tetap terbuka bagi Pemilik (pemilik klinik penanggung jawab data); tidak ada izin baru. (c) Kasir tetap melihat seluruh piutang LAP-06 karena menagihnya dan sudah berhak membuka seluruh tagihan; penerimaan dan penutupan kas tetap dibatasi kas sendiri. (d) Excel tanpa pemecahan potongan, dibenarkan oleh batas 2.000 baris per tabel dan rentang 366 hari; ditinjau ulang bila batas dinaikkan. (e) Notifikasi selesai menunggu SYS-05 (Could); sementara itu status berpolling di layar. (f) ADR-0007 mencatat `report_jobs`. (g) Ekspor dan status berkas di `/internal-api`, menggantikan `POST /reports/{key}/export` di tabel route PRD (aturan proyek menaruh endpoint JSON di sana). (h) Perubahan `tests/Feature/SoftDeleteTest.php` hanya mendaftarkan `report_jobs` (hanya-tambah) dan dua penghapusan berkas ekspor, bukan data klinis atau keuangan; tidak ada aturan yang dilonggarkan.
- Opsi: (a) pertahankan asumsi di atas; (b) pengguna menetapkan lain per butir (terutama butir 1 untuk Perawat dan butir 5 setelah Q-11 dijawab)
- Rekomendasi: (a); (b) pada butir 5 sebelum laporan jasa dipakai menggaji dokter

### Q-24: Keputusan asumsi PAS-07, RM-06, ODO-05, ODO-06, RM-09, dan RM-10
- Kebutuhan terkait: PAS-07, RM-06, ODO-05, ODO-06, RM-09, RM-10, M5, M6, M7, M9, M10, M11, M2
- Butir:
  1. **PAS-07 penggabungan.** Hanya `patient.merge` (Admin). Pasien ganda diarsipkan (`merged_into_id`, `merged_at`), tidak dihapus (M5); semua riwayat konteks lain (janji, kunjungan, odontogram, berkas, rencana, dokumen, resep, tagihan) dipindah ke pasien utama dalam satu transaksi lewat port `PatientReferenceMover` yang didaftarkan tiap konteks sehingga antar-konteks tetap lewat kontrak (M2). Bila kedua pasien memiliki NIK berbeda, penggabungan ditolak dan Admin harus membereskannya dulu; data kosong pasien utama diisi dari pasien ganda; alasan minimal 5 karakter dan konfirmasi wajib; audit mencatat kedua sisi serta jumlah baris yang dipindah.
  2. **RM-06 rencana perawatan.** Izin baru `treatment_plan.manage` untuk Dokter (matriks PRD tidak menyebutnya); melihat rencana cukup `encounter.view`. Estimasi = jumlah x tarif katalog yang disalin saat langkah dibuat (MST-08); status rencana diturunkan dari status langkah (M7). Langkah yang sudah berjalan atau selesai tidak dapat dihapus; langkah dilaksanakan lewat tindakan kunjungan yang menautkan `plan_item_id`, dan menghapus tindakan itu mengembalikan langkahnya.
  3. **ODO-05.** Tindakan katalog memiliki kondisi hasil opsional. Setelah tindakan dicatat, kondisi gigi hanya ditawarkan sebagai saran; dokter menerapkan atau menolaknya (`odontogram.write`), tidak pernah otomatis, agar snapshot odontogram tetap keputusan klinis dokter (M8).
  4. **ODO-06.** Cetak PDF odontogram terkini membutuhkan `odontogram.view`, tercatat di access log dan audit (`odontogram.printed`).
  5. **RM-09 dan RM-10.** Dokumen dicetak dari templat; yang disimpan adalah salinan teks akhir beserta nama dan SIP dokter, hanya-tambah (koreksi berarti menerbitkan dokumen baru). Nomor IC/SKD/RJK per tahun menurut zona klinik. Izin: informed consent bagi pemegang `encounter.write` atau `record.print`; surat keterangan dan rujukan bagi pemegang `encounter.close` atau `record.print`; melihat dan mencetak butuh `encounter.view`. Templat bawaan tersedia tanpa konfigurasi; Admin (`setting.manage`) dapat membuat templat sendiri dan penanda yang tidak dikenal ditolak. Teks yang masih memuat `{{...}}` tidak dapat diterbitkan. Tanda tangan pasien dan dokter adalah tanda tangan basah di kertas; tanda tangan digital di luar lingkup.
  6. Kebutuhan PAS-07, RM-06, RM-09, RM-10, ODO-05, dan ODO-06 dicentang di `docs/STATUS.md`. Perubahan `tests/Feature/SoftDeleteTest.php` hanya mendaftarkan `clinical_documents` sebagai hanya-tambah dan repository baru pada daftar pengecualian, tanpa melonggarkan aturan.
- Opsi: (a) pertahankan asumsi di atas; (b) pengguna menetapkan lain per butir (terutama butir 2 bila Perawat atau Admin juga perlu membuat rencana)
- Rekomendasi: (a)

### Q-25: Keputusan asumsi AUTH-06 dan AUTH-07
- Kebutuhan terkait: AUTH-06, AUTH-07, AUTH-02, AUTH-03, M9, M10, M11, Q-8, Q-9
- Butir:
  1. **AUTH-06 ganti kata sandi sendiri.** Setiap pengguna aktif dapat membuka `/account/password` (gerbang `account.password`, bukan izin peran) dan harus mengisi kata sandi saat ini; kata sandi baru minimal 10 karakter, memuat huruf dan angka, berbeda dari yang lama, dengan konfirmasi. Percobaan dibatasi 6 per menit. Daftar kata sandi bocor (layanan luar) tidak dipakai karena menambah ketergantungan eksternal (M21). Setelah berhasil, sesi pengguna itu di perangkat lain diakhiri (sesi lewat database), token ingat-saya dibuang, dan `user.password_changed` tercatat tanpa kata sandi; kegagalan tercatat `user.password_change_failed`.
  2. **Wajib ganti.** Akun baru dan akun hasil reset memakai kata sandi sementara dengan `must_change_password`; sebelum diganti, semua halaman dialihkan ke halaman ganti kata sandi (permintaan JSON mendapat 403 `auth.password_change_required`) kecuali keluar. Reset oleh Admin juga mengakhiri sesi lama pengguna itu. Akun yang sudah ada sebelum perubahan ini tidak dipaksa.
  3. **AUTH-07 peran dan izin.** Halaman `/settings/roles` dibuka pemegang `role.manage` (Pemilik dan Admin menurut matriks). Setiap izin ditampilkan dengan penjelasan Indonesia per modul dan ditandai bila berbeda dari bawaan. Hanya Pemilik yang mengubah peran Pemilik (selaras aturan pengguna), dan peran Pemilik tidak pernah kehilangan `user.view`, `user.manage`, dan `role.manage` agar klinik tidak terkunci. Perubahan berlaku pada permintaan berikutnya pengguna terkait dan tercatat sebagai `role.permissions_changed` (sebelum, sesudah, ditambah, dicabut) atau `role.permissions_reset`.
  4. **Seeder tidak menimpa penyesuaian.** Kolom `roles.customized_at` menandai peran yang disesuaikan; seeder melewati peran itu. Konsekuensinya, izin baru yang ditambahkan kode pada rilis berikutnya tidak otomatis masuk ke peran yang sudah disesuaikan dan harus dicentang lewat halaman ini (atau dengan Kembalikan ke bawaan). Peran bersifat global, bukan per klinik, sesuai skema izin yang ada.
  5. Perubahan pada tes yang sudah ada: tidak ada yang dilonggarkan. `RouteAuthorizationCoverageTest` tetap tanpa pengecualian baru karena route akun memakai gerbang `account.password`; `SoftDeleteTest` hanya mendaftarkan dua penghapusan baris sesi login lama (tabel `sessions`), bukan data klinis atau keuangan.
  6. AUTH-06 dicatat sebagai Must Fase 1 (Must menjadi 55 dari 55) dan AUTH-07 sebagai Should di `docs/STATUS.md`; dokumen PRD tidak diubah.
- Opsi: (a) pertahankan asumsi di atas; (b) pengguna menetapkan lain per butir (mis. panjang minimal kata sandi atau memaksa ganti berkala)
- Rekomendasi: (a)

### Q-7: Tabel pengguna bawaan skeleton Laravel
- Kebutuhan terkait: AUTH-01, M3
- Dokumen yang bertabrakan atau kurang: migrasi `users` dan `sessions` bawaan memakai kunci angka, tanpa ULID, `clinic_id`, dan `branch_id`
- Opsi: (a) ditulis ulang sebagai bagian AUTH-01 sebelum ada migrasi yang digabung (menyunting migrasi yang belum dipakai); (b) migrasi baru yang mengubahnya
- Rekomendasi: (a)

## Terjawab

(belum ada)
