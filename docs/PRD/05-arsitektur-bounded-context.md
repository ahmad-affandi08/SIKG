# PRD SIKG: Arsitektur dan Bounded Context

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 9. Arsitektur teknis

SIKG dibangun sebagai modular monolith: satu aplikasi Laravel 13, satu database MySQL, dibagi menjadi bounded context DDD, dengan antarmuka React yang dilayani lewat Inertia.js.

| Lapisan | Pilihan | Catatan |
| --- | --- | --- |
| Runtime | PHP 8.3 sampai 8.5 | Laravel 13 mensyaratkan PHP 8.3 atau lebih baru ([Laravel News](https://laravel-news.com/laravel-13-released)) |
| Backend | Laravel 13 | Routing, auth, queue, scheduler; Eloquent sebagai lapisan persistensi |
| Database | MySQL 8.0 atau lebih baru, InnoDB, utf8mb4 | Semua tabel bertransaksi, foreign key aktif |
| Cache, sesi, antrean | Redis (usulan); driver database cukup untuk Fase 1 | Satu server dapat menjalankan semuanya |
| Frontend | React + TypeScript strict lewat Inertia.js (seri 3.x), build dengan Vite | Cek kompatibilitas versi Inertia dengan starter kit Laravel 13 saat inisialisasi |
| Styling | Tailwind CSS 4 | Token desain di blok @theme; pustaka komponen dasar usulan: shadcn/ui |
| Validasi klien | Zod | Skema form dan skema respons API |
| Data asinkron | TanStack Query | Pencarian, polling antrean, simpan otomatis odontogram |
| Form | React Hook Form dengan zodResolver (usulan) | Satu skema Zod per form |
| Hak akses | spatie/laravel-permission (usulan) | Izin granular, peran dapat diubah pemilik |
| PDF dan Excel | spatie/laravel-pdf atau dompdf; maatwebsite/excel (usulan) | Kwitansi, resep, laporan |
| Pengujian | Pest, Vitest, Playwright | Unit, komponen, uji alur end-to-end |
| Mutu kode | Larastan, Laravel Pint, ESLint, Deptrac | Deptrac menjaga batas antar-konteks |

**Pembagian peran Inertia dan TanStack Query**

| Kebutuhan | Dipakai | Alasan |
| --- | --- | --- |
| Halaman daftar dan detail (pasien, kunjungan, tagihan) | Props Inertia | Data sudah terotorisasi dari server; navigasi sederhana |
| Simpan dan ubah lewat form | Form Inertia, validasi Zod di klien, FormRequest di server | Galat validasi server kembali ke kolom yang sama |
| Autocomplete pasien, tindakan, obat, ICD-10 | TanStack Query | Cache dan debounce |
| Data yang berubah tanpa navigasi (antrean, status bayar) | TanStack Query dengan polling | Tanpa memuat ulang halaman |
| Odontogram | Mutation TanStack Query dengan pembaruan optimistis | Perubahan per gigi tersimpan otomatis |
| Laporan berat | TanStack Query memantau status pekerjaan antrean | Hasil diberi notifikasi saat siap |

Endpoint JSON untuk TanStack Query berada di prefix `/internal-api` dan memakai sesi login yang sama (cookie dan CSRF), bukan token publik.

**Prinsip arsitektur**

1. Lapisan domain bebas dari Eloquent, HTTP, dan framework; berisi entity, value object, domain service, dan domain event.
2. Lapisan aplikasi berisi use case (command dan query handler); controller hanya memanggil satu use case.
3. Lapisan infrastruktur berisi repository Eloquent, penyimpanan berkas, dan integrasi eksternal (WhatsApp, SATUSEHAT).
4. Lapisan presentasi berisi controller tipis, FormRequest, halaman Inertia, dan API Resource.
5. Antar-konteks berkomunikasi lewat domain event dan interface, bukan lewat tabel konteks lain; dijaga Deptrac di CI.
6. Uang adalah value object Money (rupiah bulat, BIGINT) dengan satu aturan pembulatan di satu tempat.
7. Use case yang mengubah data berjalan dalam satu transaksi dan menulis audit.
8. Waktu disimpan dalam UTC dan ditampilkan sesuai zona waktu klinik.
9. Pendekatan pragmatis: DDD penuh untuk konteks inti (Clinical, Billing, Scheduling, Pharmacy); konteks master data sederhana memakai pola CRUD terstruktur dengan alasan dicatat dalam ADR.

## 10. Bounded context DDD dan struktur kode

Sembilan bounded context menjaga setiap bagian klinik bisa berubah tanpa merusak bagian lain; empat di antaranya (Clinical, Billing, Scheduling, Pharmacy) adalah konteks inti dan memakai DDD penuh.

| Context | Tanggung jawab | Aggregate utama | Event penting |
| --- | --- | --- | --- |
| IdentityAccess | Pengguna, peran, izin, sesi | User, Role | UserDeactivated |
| Clinic | Profil klinik, dokter, kursi, katalog tindakan dan tarif, diagnosa, metode bayar, hari libur | Clinic, Doctor, Chair, Procedure | TariffChanged |
| Patient | Data pasien, riwayat medis, wali, persetujuan data | Patient | PatientRegistered, PatientMerged |
| Scheduling | Jadwal praktik, janji temu, antrean | Appointment, DoctorSchedule | AppointmentBooked, PatientCheckedIn, AppointmentMissed |
| Clinical | Kunjungan, SOAP, diagnosa, tindakan, odontogram, rencana perawatan, berkas klinis, addendum | Encounter, Odontogram, TreatmentPlan | EncounterStarted, ProcedurePerformed, EncounterClosed |
| Pharmacy | Resep, katalog obat, stok dan pergerakan stok | Prescription, StockItem | PrescriptionIssued, StockBelowMinimum |
| Billing | Tagihan, pembayaran, DP, cicilan, retur, tutup kas | Invoice, CashClosing | InvoiceIssued, PaymentReceived, InvoiceVoided |
| Reporting | Read model dan query laporan; tidak pernah mengubah data | Proyeksi | - |
| Shared | Money, DateRange, base class, bus, audit | - | - |

Fase 2 menambah konteks Integration (SATUSEHAT dan WhatsApp sebagai anti-corruption layer), Insurance, Lab, dan Orthodontics.

**Aturan antar-konteks**

- Referensi lintas konteks hanya lewat ID (PatientId, DoctorId), bukan objek atau relasi Eloquent.
- Billing membuat item tagihan dengan mendengarkan event ProcedurePerformed (Clinical) dan penjualan obat (Pharmacy); tarif disalin saat item dibuat (MST-08).
- Clinical membaca alergi dan penyakit sistemik dari Patient lewat interface khusus, bukan query langsung ke tabel Patient.
- Reporting boleh membaca tabel konteks lain lewat query read-only yang terpusat; ini satu-satunya pengecualian, dan Reporting tidak pernah menulis.
- Event didispatch setelah transaksi commit agar listener tidak melihat data setengah jadi.

**Struktur folder backend**

```
app/                       bootstrap Laravel saja (Providers, Console)
src/
  Shared/{Domain,Application,Infrastructure}
  Patient/
    Domain/                Entity, ValueObject, Event, Repository (interface)
    Application/           Command, Query, Handler, DTO
    Infrastructure/        EloquentModel, EloquentRepository, Migrations
    Presentation/          Controller, FormRequest, Resource, routes.php
    PatientServiceProvider.php
  Scheduling/  Clinical/  Pharmacy/  Billing/
  Clinic/  IdentityAccess/  Reporting/
tests/{Unit,Feature,Architecture}
```

**Aturan teknis DDD**

- Autoload namespace `Src\` ke folder `src/` di composer.json; tiap konteks punya ServiceProvider untuk binding, route, dan migrasi.
- Domain tidak boleh memakai Illuminate, Application, Infrastructure, atau Presentation. Application hanya bergantung pada Domain dan Shared. Presentation hanya memanggil Application.
- Model Eloquent berada di Infrastructure sebagai model persistensi; repository memetakannya ke entity domain. Konteks Reporting dan master data sederhana boleh memakai Eloquent langsung.
- Command dan query dipanggil lewat bus sederhana di Shared; tidak memerlukan paket CQRS pihak ketiga.
- Uji arsitektur (Pest arch test) dan Deptrac memeriksa aturan di atas pada setiap build, sehingga pelanggaran batas gagal di CI.
