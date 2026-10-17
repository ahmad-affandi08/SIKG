# ADR-0008: Desain cetak PDF dan Excel yang seragam

- Status: Disetujui (permintaan pengguna: semua cetakan PDF dan Excel harus profesional, konsisten, dan berdasar referensi; dapat ditinjau ulang)
- Konteks terkait: RM-09, RM-10, ODO-06, OBT-03, KSR-06, LAP-01 sampai LAP-10; ADR-0007; Q-26

## Konteks

Cetakan klinik dipakai sebagai dokumen resmi: informed consent, surat keterangan, surat rujukan, resep, invoice, kwitansi, odontogram, dan laporan. Versi awal memakai kop, ukuran huruf, dan tabel yang berbeda-beda di tiap berkas. Keputusan ini menyatukan semuanya dan menetapkan isi dokumen mengikuti contoh nyata di Indonesia.

## Keputusan

**Satu sistem desain, satu tempat.** Semua PDF memakai `resources/views/pdf/layout.blade.php` dengan gaya di `pdf/partials/style.blade.php` dan kop di `pdf/partials/letterhead.blade.php`. Pembuat PDF satu pintu: port `Src\Shared\Application\PdfRenderer` dengan implementasi dompdf yang mengatur kertas, metadata berkas, dan penomoran "Halaman X dari Y". Tidak ada templat yang memuat gaya kop sendiri.

**Hanya format, tanpa warna.** Hitam putih: hierarki dibentuk oleh ukuran, ketebalan, garis, dan jarak. Satu keluarga huruf (Helvetica atau Arial), badan teks 10 pt, tabel 8,5 sampai 9 pt, judul 12 pt kapital, tabel dengan garis tajuk atas dan bawah, garis tipis antarbaris, dan garis tebal sebelum total. Angka rata kanan dengan jumlah desimal sama; teks rata kiri.

**Kop surat.** Mengikuti konvensi surat resmi Indonesia: logo di kiri, nama klinik kapital dan tebal di tengah, alamat dan nomor izin di bawahnya, dipisah garis ganda. Kop yang sama dipakai di semua dokumen; hanya ukurannya mengecil pada resep A5 dan struk termal 80 mm.

**Isi per dokumen mengikuti contoh nyata.**

| Dokumen | Susunan |
| --- | --- |
| Informed consent | Judul dan nomor; pernyataan pasien atau wali; tabel informasi tindakan (diagnosis, tindakan, tujuan, tata cara, risiko dan komplikasi, alternatif dan risikonya, prognosis, perkiraan biaya) mengikuti Permenkes 290/2008 dan formulir KKI; pernyataan persetujuan yang dapat ditarik tertulis sebelum tindakan dimulai; penutup; tanda tangan pasien atau wali, saksi, dan dokter |
| Surat keterangan | Judul dan nomor; "Yang bertanda tangan di bawah ini ... menerangkan bahwa"; identitas pasien; hasil pemeriksaan, diagnosa, dan anjuran istirahat; keperluan; "Demikian surat keterangan ini dibuat ... sebagaimana mestinya"; tanda tangan dokter dengan SIP dan ruang stempel |
| Surat rujukan | Blok Nomor, Lampiran, Perihal dan tanggal; "Kepada Yth. ... di tempat"; "Dengan hormat"; identitas pasien; keluhan dan riwayat, diagnosa sementara, terapi yang telah diberikan, alasan rujukan; permintaan balasan dan ucapan terima kasih; tanda tangan dokter pengirim dengan SIP |
| Resep | Kop; dokter dengan SIP dan tanggal; tiap obat ditulis `R/` dengan nama, jumlah, dan signa (aturan pakai); `Pro:` identitas pasien; paraf dokter |
| Invoice | Judul dan nomor; ditagihkan kepada; tabel nomor, uraian, jumlah, harga, diskon, jumlah; subtotal, diskon, pajak, total, sisa; terbilang; riwayat pembayaran; tanda tangan kasir |
| Kwitansi | "Telah terima dari", "Uang sejumlah" (terbilang), "Untuk pembayaran"; kotak jumlah angka; sisa tagihan; penerima; kotak meterai Rp10.000 bila bernilai Rp5.000.000 atau lebih |
| Odontogram | Identitas pasien; bagan FDI dengan kode kondisi tercetak di tiap kotak sehingga terbaca hitam putih; daftar kondisi; tanda tangan dokter pemeriksa |
| Laporan | Judul, kode, periode, dan waktu cetak; ringkasan; tabel; catatan definisi; tanda tangan disusun oleh, diperiksa oleh, dan mengetahui |

**Nomor surat** mengikuti kaidah Indonesia: nomor urut, kode, bulan Romawi, tahun, contoh `001/SKD/VI/2025`. Token `{rom}` ditambahkan ke `NumberPattern`; urutan berlaku per tahun dan per jenis, tidak memulai ulang tiap bulan.

**Excel.** Semua lembar memakai `SheetStyler`: kop tiga baris (klinik, alamat dan izin, judul laporan) dengan garis ganda, judul tabel, rentang dan waktu cetak, tajuk tebal berlatar abu muda, angka bertipe dengan format Rupiah (nol tetap tertulis), garis tipis antarbaris, total tebal dengan garis ganda, baris tajuk dibekukan dan diberi filter otomatis, lebar kolom dari isi, kisi layar dimatikan. Cetak: A4, muat satu halaman lebar, tajuk diulang tiap halaman, kepala dan kaki berisi klinik, judul, waktu cetak, dan "Halaman X dari Y". Properti berkas (judul, penyusun, perusahaan) terisi. Lembar Ringkasan memuat blok tanda tangan.

## Referensi

Isi dan tata letak dirumuskan dari sumber berikut (diakses saat perancangan); sumber blog dan contoh pengguna dipakai hanya untuk pola susunan, bukan sebagai regulasi.

- Persetujuan tindakan: Permenkes 290/2008 ([JDIH Kemenkes](https://jdih.kemkes.go.id/common/dokumen/2008permenkes290.pdf)); ringkasan Manual PTK KKI ([galihendradita.wordpress.com](https://galihendradita.wordpress.com/2019/11/20/persetujuan-tindakan-kedokteran-di-indonesia/)); isi penjelasan yang wajib ([Hukumonline](https://www.hukumonline.com/berita/a/informed-consent-sebagai-fondasi-tindakan-medis-lt678547a8b1595/?page=all)); contoh formulir perawatan gigi ([Poltekkes Denpasar](http://repository.poltekkes-denpasar.ac.id/7299/9/Lampiran%20-%20lampiran.pdf)).
- Surat keterangan: [Gramedia](https://www.gramedia.com/best-seller/contoh-format-surat-keterangan-sakit-pribadi-dan-resmi/), [detikHealth](https://health.detik.com/berita-detikhealth/d-6975818/contoh-surat-dokter-asli-dan-cara-membedakan-dengan-yang-palsu).
- Surat rujukan: [contoh surat rujukan drg](http://yuniar-dahriani.blogspot.com/2014/01/contoh-surat-rujukan.html), [panduan surat konsul ISPMI](https://www.ispmi.or.id/blog/panduan-penulisan-surat-konsul).
- Kaidah surat resmi dan kop: [BikinSurat](https://www.bikinsurat.id/panduan/kaidah-surat-resmi-indonesia/), [Gramedia](https://www.gramedia.com/literasi/bagian-bagian-surat-resmi/).
- Resep: manual penulisan resep ([UNTAR](https://lintar.untar.ac.id/repository/pengabdian/buktiabdi_10486006_00Z210421080851.pdf)); kelengkapan administratif menurut Permenkes 72/2016 dan 73/2016 sebagaimana dirangkum penelitian apotek.
- Kwitansi dan meterai: [Paper.id](https://www.paper.id/blog/kwitansi/format-kwitansi-pembayaran/), [Bisnis.com](https://ekonomi.bisnis.com/read/20210105/9/1338751/daftar-8-dokumen-yang-dikenai-bea-materai-baru-rp10000), UU 10/2020 ([JDIH Setkab](https://jdih.setkab.go.id/PUUdoc/176264/UU_Nomor_10_Tahun_2020.pdf)).
- Odontogram: [Info DRG, kaidah penulisan odontogram](https://infodrg.com/kaidah-penulisan-odontogram/) berdasar Permenkes 269/2008.
- Desain invoice dan tabel: [panduan desain invoice](https://invoicequickly.com/guides/invoice-design-guide), [tinjauan 1.068 tabel (PMC)](https://pmc.ncbi.nlm.nih.gov/articles/PMC10346464), [PolicyViz](https://policyviz.com/2018/05/03/remake-tables-from-the-usda/).
- Cetak PDF dan Excel: [optimasi HTML ke PDF](https://htmlcsstoimage.com/blog/optimize-html-for-pdf-printing), [resep PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/en/latest/topics/recipes/).

## Alternatif yang dipertimbangkan

- Warna aksen pada judul dan tajuk: ditolak; cetakan klinik sering difotokopi hitam putih dan dokumen resmi tidak berwarna.
- Tanggal dengan nama bulan seperti surat resmi: ditolak karena aturan proyek M18 mewajibkan DD/MM/YYYY.
- Kota pada "Kota, tanggal": ditolak sementara karena profil klinik belum menyimpan kota; dokumen memakai tanggal terbit saja.

## Akibat

- Dokumen baru cukup memperluas layout bersama dan memakai komponen yang ada.
- Isi bawaan informed consent adalah kerangka umum yang dapat disunting dokter dan diganti templat Admin; kecocokannya dengan formulir PDGI atau dinas kesehatan setempat perlu ditinjau klinik (Q-26).
