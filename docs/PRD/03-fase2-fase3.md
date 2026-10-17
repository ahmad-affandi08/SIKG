# PRD SIKG: Kebutuhan Fase 2 dan Fase 3

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 7. Kebutuhan fungsional Fase 2 dan 3

Fase 2 menambah kepatuhan regulasi (integrasi SATUSEHAT), pendapatan, dan kontrol operasional (penjamin, pengingat otomatis, stok lanjutan, lab, ortodonti); Fase 3 menambah pengalaman pasien dan integrasi eksternal. Cakupan akhir tiap fitur dirinci ulang sebelum fasenya dimulai.

| ID | Fitur | Fase | Ringkasan |
| --- | --- | --- | --- |
| P2-01 | Integrasi SATUSEHAT | 2 (wajib regulasi) | Registrasi sistem elektronik ke Kemenkes dan pengiriman data kunjungan ke platform SATUSEHAT dengan standar HL7 FHIR; dikerjakan pertama di Fase 2 |
| P2-02 | Penjamin asuransi dan perusahaan | 2 | Master penjamin, plafon, penjamin per pasien, tagihan ke penjamin, status klaim, piutang penjamin |
| P2-03 | Pengingat WhatsApp otomatis | 2 | Integrasi penyedia WhatsApp Business API, template H-1 dan hari-H, pengingat kontrol 6 bulan, status terkirim |
| P2-04 | Ortodonti | 2 | Paket perawatan, jadwal kontrol bulanan, catatan kawat atau aligner, cicilan terjadwal, foto progres |
| P2-05 | Stok lanjutan | 2 | Pembelian ke pemasok, batch dan kedaluwarsa, pemakaian bahan habis pakai per tindakan, opname terjadwal |
| P2-06 | Pesanan lab gigi | 2 | Pesan mahkota, gigi tiruan, aligner ke lab; status, tanggal kirim dan terima, biaya lab ke tagihan |
| P2-07 | Informed consent digital | 2 | Tanda tangan di tablet, PDF tersimpan di rekam medis |
| P2-08 | Pengeluaran dan laba rugi sederhana | 2 | Biaya operasional, laporan laba rugi bulanan |
| P2-09 | Jasa dokter lanjutan | 2 | Skema bertingkat, potongan biaya lab, rekap per periode, slip |
| P3-01 | Booking online pasien | 3 | Halaman publik pilih dokter dan slot, verifikasi nomor HP, batas janji per nomor |
| P3-02 | Mode offline (PWA) | 3 | Pencatatan tetap berjalan saat internet putus, sinkron saat tersambung |
| P3-03 | Integrasi rontgen digital | 3 | Menarik gambar dari alat rontgen atau sensor (DICOM) |
| P3-04 | Multi-cabang penuh | 3 | Pilih cabang, laporan konsolidasi, stok antar cabang |
| P3-05 | Pembayaran online | 3 | QRIS dinamis atau payment gateway dengan rekonsiliasi otomatis |
| P3-06 | Voucher dan membership | 3 | Kode promo, paket kunjungan, poin loyalitas |
