# PRD SIKG: Risiko dan Pertanyaan Terbuka

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 19. Risiko dan mitigasi

Risiko terbesar bukan teknis: kewajiban SATUSEHAT yang belum jelas statusnya, cakupan yang melebar, dan staf yang lambat beralih dari buku.

| ID | Risiko | Dampak | Kemungkinan | Mitigasi |
| --- | --- | --- | --- | --- |
| R1 | Registrasi sistem elektronik dan integrasi SATUSEHAT belum dikerjakan saat klinik mulai memakai SIKG | Ketidakpatuhan terhadap Permenkes 24/2022, risiko sanksi administratif | Tinggi | Tanyakan status ke pemilik segera; mulai urus registrasi dan akun SATUSEHAT paralel sejak Fase 0; P2-01 menjadi pekerjaan pertama Fase 2; model data memakai NIK dan kode standar sejak Fase 1 |
| R2 | Cakupan melebar karena kebutuhan belum tergali | Pengerjaan melebar | Tinggi | PRD dibekukan per fase; permintaan baru masuk daftar dan diprioritaskan; Must dan Should dipisahkan jelas |
| R3 | Staf lambat atau menolak beralih dari buku | Adopsi rendah | Sedang | Perwakilan tiap peran ikut uji prototipe; pelatihan per peran; masa pendampingan; tugas harian maksimal 3 klik |
| R4 | Kebocoran data pasien | Dampak hukum dan reputasi berat | Rendah sampai sedang | Kontrol bagian 14, enkripsi kolom sensitif, jejak akses, cadangan terenkripsi, uji penetrasi dasar, prosedur insiden |
| R5 | Server rusak atau data hilang | Operasional berhenti | Rendah | Cadangan di lokasi terpisah, uji pemulihan berkala, target RPO 1 jam dan RTO 4 jam |
| R6 | Internet klinik putus | Pencatatan terhambat | Sedang | Fase 1: formulir cetak sementara dan input susulan, draf tersimpan di server begitu koneksi pulih; Fase 3: mode offline |
| R7 | Salah hitung tagihan atau pembulatan | Kerugian dan sengketa dengan pasien | Sedang | Value object Money, uji aturan uang 100%, tutup kas harian, void dan refund selalu tercatat |
| R8 | Odontogram dianggap terlalu rumit oleh dokter | Dokter tidak mau memakai | Sedang | Prototipe diuji dengan dokter sebelum dibangun penuh; mulai dari kondisi umum; daftar kondisi dapat disesuaikan |
| R9 | Data migrasi kotor (duplikat, tanpa NIK) | Data pasien tidak andal | Tinggi | Pratinjau impor, deteksi duplikat, penanda perlu dilengkapi, verifikasi sampel 100 pasien |
| R10 | Ketergantungan pada satu pengembang | Kesinambungan terancam | Sedang | Dokumentasi, ADR, tes otomatis, struktur DDD yang jelas, repositori dan akun milik klinik |
| R11 | Pembaruan versi Laravel, Inertia, atau React | Pekerjaan upgrade tak terduga | Rendah | Kunci versi, jadwal dukungan resmi Laravel 13, CI yang menangkap regresi |
| R12 | Biaya meleset dari perkiraan | Anggaran | Sedang | Cakupan Must dikunci lebih dulu; biaya ditinjau di akhir tiap tahap |

## 20. Pertanyaan terbuka untuk pemilik klinik

Satu pertanyaan harus dijawab sebelum desain dibekukan: apakah klinik sudah terdaftar di SATUSEHAT, karena itu menentukan apakah integrasi tetap di Fase 2 atau dimajukan ke Fase 1.

| No | Pertanyaan | Mengapa penting | Bagian terdampak |
| --- | --- | --- | --- |
| 1 | Apakah klinik sudah punya izin operasional, akun SATUSEHAT, dan siapa yang mengurus registrasi sistem elektronik? | Kewajiban regulasi; menentukan urutan fase | 7, 14, 18, 19 |
| 2 | Berapa dokter dan kursi sekarang, dan apakah ada rencana cabang? | Asumsi A1; bentuk jadwal dan laporan | 3, 11 |
| 3 | Apakah ada pasien asuransi atau perusahaan, dan berapa persentasenya? | Asumsi A2; menentukan kapan modul penjamin dibutuhkan | 7 |
| 4 | Apakah sudah ada rontgen digital (sensor atau panoramik digital)? | Asumsi A3; unggah gambar cukup atau perlu integrasi | 6.5, 7 |
| 5 | Perangkat apa yang dipakai (PC, tablet, jenis printer kwitansi)? | Asumsi A4; tata letak cetak dan layar sentuh | 12 |
| 6 | Seberapa stabil internet di klinik, dan apakah pernah putus berjam-jam? | Asumsi A5; perlu tidaknya mode offline | 7, 19 |
| 7 | Berapa banyak data lama dan dalam bentuk apa (buku, Excel, aplikasi lain)? | Asumsi A6; beban migrasi | 17 |
| 8 | Bagaimana skema jasa dokter (persentase, nominal tetap, campuran) dan siapa yang menghitungnya sekarang? | Menentukan struktur master dokter dan LAP-03 | 6.2, 15 |
| 9 | Metode pembayaran apa yang dipakai (tunai, transfer, QRIS statis, mesin EDC)? | Daftar metode dan tutup kas | 6.8 |
| 10 | Apakah klinik menjual obat atau produk (sikat gigi, obat kumur), atau hanya menulis resep? | Perlu tidaknya stok dan penjualan di Fase 1 | 6.7 |
| 11 | Apakah pengingat WhatsApp memakai nomor klinik, dan bersedia membayar penyedia WhatsApp API? | Biaya berjalan dan syarat Fase 2 | 6.4, 7 |
| 12 | Bagaimana format cetak yang diinginkan (logo, header, ukuran kertas A4 atau termal)? | Template invoice, resep, surat keterangan | 6.8, 12 |
| 13 | Apakah ada kebutuhan ekspor untuk akuntan atau konsultan pajak? | Format laporan keuangan | 15 |
| 14 | Hosting: server sendiri, VPS, atau cloud, dan apakah ada preferensi penyedia? | Biaya, lokasi data, dan kepatuhan | 17 |
| 15 | Anggaran dan siapa pemegang keputusan persetujuan PRD? | Gerbang fase | 18 |
