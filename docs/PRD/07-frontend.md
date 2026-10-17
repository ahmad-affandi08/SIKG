# PRD SIKG: Desain Frontend

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 12. Desain frontend

Antarmuka dirancang agar resepsionis, dokter, dan kasir menyelesaikan tugas harian dengan keyboard atau layar sentuh tanpa berpindah-pindah halaman.

**Peta halaman**

| Area | Halaman | Pengguna utama |
| --- | --- | --- |
| Beranda | Ringkasan hari ini (Pemilik), antrean hari ini (Resepsionis), pasien saya (Dokter) | Semua peran |
| Pasien | Daftar dan pencarian, detail pasien (tab Ringkasan, Riwayat medis, Linimasa, Berkas), form pasien baru, impor Excel | Resepsionis, Admin |
| Jadwal | Kalender harian dan mingguan, antrean hari ini, form janji temu | Resepsionis, Dokter |
| Pemeriksaan | Satu layar bertab: anamnesis, odontogram, diagnosa, tindakan, resep, berkas | Dokter, Perawat |
| Kasir | Daftar tagihan, detail tagihan dan pembayaran, tutup kas | Kasir |
| Stok | Obat dan bahan, pergerakan stok, opname | Admin, Perawat |
| Laporan | LAP-01 sampai LAP-10 | Pemilik, Admin |
| Pengaturan | Klinik, dokter, kursi, tindakan dan tarif, obat, diagnosa, metode bayar, pengguna dan peran, audit log | Pemilik, Admin |

**Struktur folder frontend**

```
resources/js/
  app.tsx                inisialisasi Inertia dan QueryClientProvider
  Pages/                 satu berkas per halaman Inertia (Patients/Index.tsx, ...)
  features/              satu folder per konteks: patient, scheduling, clinical, billing, pharmacy, reporting
    patient/
      api.ts             fungsi fetch dan kunci query
      queries.ts         hook useQuery dan useMutation
      schemas.ts         skema Zod (form dan respons)
      components/        komponen khusus fitur
  components/ui/         komponen dasar (Button, Input, Dialog, DataTable)
  lib/                   http client, format uang dan tanggal, fungsi can() untuk izin
```

**Aturan Zod**

- Satu sumber skema per fitur di `schemas.ts`; tipe TypeScript diturunkan dengan `z.infer`, tidak ditulis ulang.
- Skema form memvalidasi sebelum kirim: kolom wajib, NIK 16 digit, format nomor HP, tanggal, uang tidak negatif. Pesan galat berbahasa Indonesia lewat error map global.
- Respons endpoint JSON diparse dengan skema Zod di `api.ts`; bila tidak cocok, galat dilaporkan ke pelacak galat dan UI menampilkan pesan umum, bukan layar kosong. Halaman kritis (pasien, tagihan) juga memberi tipe pada props Inertia dengan skema yang sama.
- Validasi server (FormRequest) tetap otoritatif; Zod hanya memberi umpan balik cepat. Uji kontrak di CI membandingkan contoh respons API Laravel dengan skema Zod agar keduanya tidak menyimpang.
- Uang diperlakukan sebagai bilangan bulat rupiah di seluruh frontend dan baru diformat saat ditampilkan.

**Aturan TanStack Query**

- Satu QueryClient; `staleTime` bawaan 30 detik, data master 10 menit; antrean di-poll setiap 10 detik hanya saat tab aktif.
- Kunci query terstruktur per fitur (contoh: `['patients', 'search', term]`); invalidasi setelah mutasi lewat helper fitur.
- Autocomplete memakai debounce 250 ms dan membatasi hasil 20 baris.
- Mutation odontogram memakai pembaruan optimistis dengan rollback bila gagal, plus penanda tersimpan per gigi.
- Galat 401 atau 419 mengarahkan ke login atau memuat ulang token; 403 menampilkan halaman tidak berwenang.
- Setelah mutasi yang mengubah props halaman Inertia, gunakan `router.reload({ only: [...] })`, bukan muat ulang penuh.

**Tailwind CSS 4**

- Konfigurasi berbasis CSS: token warna, jarak, dan font didefinisikan di blok `@theme` pada `app.css`; berkas `tailwind.config.js` hanya dibuat bila benar-benar diperlukan.
- Mode terang saja di Fase 1.
- Kontrol sentuh minimal 44 px. Warna status konsisten: hijau lunas, kuning sebagian, merah belum bayar, abu-abu dibatalkan; status selalu disertai teks atau ikon, bukan warna saja.
- Stylesheet cetak (`print:`) untuk kwitansi dan resep; PDF dibuat di server agar hasil cetak seragam di semua perangkat.

**Komponen odontogram**

- Komponen SVG React: tiap gigi digambar dengan lima area permukaan yang bisa diklik; kondisi ditandai warna dan pola atau ikon sehingga tetap terbaca bagi pengguna buta warna dan pada cetak hitam-putih.
- Tiga mode: dewasa (32 gigi), anak (20 gigi), campuran.
- Memilih gigi membuka panel samping berisi kondisi, tindakan, dan riwayat gigi itu; panah keyboard memindahkan fokus antar gigi.
- Satu query memuat odontogram terkini dan snapshot kunjungan aktif; perubahan disimpan per gigi.

**Pola UX penting**

- Banner alergi dan penyakit sistemik selalu terlihat di semua layar pemeriksaan.
- Pencarian pasien global dengan pintasan `/` di semua halaman.
- Aksi merusak (void, batal) meminta konfirmasi dan alasan.
- Setiap daftar punya keadaan kosong, skeleton pemuatan, dan pesan galat yang jelas.
- Draf pemeriksaan disimpan otomatis tiap 30 detik ke server (bukan ke penyimpanan peramban, agar data pasien tidak tertinggal di perangkat) supaya tidak hilang bila halaman tertutup.
- Peringatan sebelum meninggalkan formulir yang belum tersimpan.
