# PRD SIKG: Route, Endpoint, dan Kontrak Data

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 13. Route, endpoint, dan kontrak data

Halaman dilayani lewat route web Inertia, sedangkan data yang berubah tanpa navigasi dilayani endpoint JSON di prefix `/internal-api`; keduanya berada di balik login, pembatasan laju, dan pemeriksaan izin.

**Route halaman (Inertia)**

| Metode dan route | Fungsi | Izin |
| --- | --- | --- |
| GET /login, POST /login, POST /logout | Masuk dan keluar | Publik |
| GET /dashboard | Beranda sesuai peran | Login |
| GET /patients, GET /patients/create, POST /patients | Daftar, form baru, simpan pasien | patient.view, patient.create |
| GET /patients/{id}, PUT /patients/{id} | Detail dan ubah pasien | patient.view, patient.update |
| POST /patients/import, POST /patients/import/{batch}/commit | Pratinjau dan konfirmasi impor Excel | patient.import |
| POST /patients/{id}/merge | Gabung data ganda | patient.merge |
| GET /schedule, GET /queue | Kalender dan antrean | appointment.view |
| POST /appointments, PUT /appointments/{id} | Buat dan ubah janji | appointment.manage |
| POST /appointments/{id}/check-in, /cancel, /no-show | Ubah status janji | appointment.manage |
| POST /walk-ins | Pasien tanpa janji | appointment.manage |
| GET /encounters/{id} | Layar pemeriksaan | encounter.view |
| POST /encounters | Mulai kunjungan dari janji atau walk-in | encounter.create |
| PUT /encounters/{id}/notes | Simpan SOAP dan anamnesis | encounter.write |
| POST /encounters/{id}/diagnoses, /procedures | Tambah diagnosa dan tindakan | encounter.write |
| POST /encounters/{id}/files | Unggah foto atau rontgen | encounter.write |
| POST /encounters/{id}/prescriptions | Buat resep | prescription.create |
| POST /encounters/{id}/close, /addenda | Tutup kunjungan, tambah addendum | encounter.close, encounter.addendum |
| GET /invoices, GET /invoices/{id} | Daftar dan detail tagihan | invoice.view |
| POST /invoices/{id}/items, /discount | Ubah item dan diskon | invoice.edit, invoice.discount |
| POST /invoices/{id}/payments | Catat pembayaran | payment.create |
| POST /invoices/{id}/void | Batalkan tagihan | invoice.void |
| GET /invoices/{id}/print | Cetak invoice dan kwitansi | invoice.view |
| GET /cash-closings, POST /cash-closings | Tutup kas harian | cash.close |
| GET /stock, POST /stock/movements, /stock/adjustments | Stok dan penyesuaian | stock.view, stock.manage |
| GET /reports/{key}, POST /reports/{key}/export | Laporan dan ekspor | report.view |
| GET dan POST /settings/... | Pengaturan master data, pengguna, peran | setting.manage, user.manage |

**Endpoint JSON (untuk TanStack Query)**

| Metode dan endpoint | Fungsi | Catatan |
| --- | --- | --- |
| GET /internal-api/patients/search?q= | Autocomplete pasien | Maksimal 20 hasil, throttle 60 per menit |
| GET /internal-api/procedures/search?q= | Autocomplete tindakan | Mengembalikan tarif saat ini |
| GET /internal-api/medicines/search?q= | Autocomplete obat | Termasuk stok tersedia |
| GET /internal-api/diagnoses/search?q= | Autocomplete ICD-10 | Hanya kode yang aktif |
| GET /internal-api/appointments/availability?doctor=&date= | Slot kosong dokter | Menghormati jadwal praktik dan hari libur |
| GET /internal-api/queue?date= | Antrean hari ini | Di-poll tiap 10 detik |
| GET /internal-api/patients/{id}/odontogram | Odontogram terkini | Gabungan snapshot terakhir |
| PUT /internal-api/encounters/{id}/odontogram/teeth/{fdi} | Simpan kondisi gigi | Menyertakan permukaan dan kode kondisi |
| GET /internal-api/invoices/{id}/summary | Ringkasan tagihan | Total, dibayar, sisa |
| GET /internal-api/report-jobs/{id} | Status laporan berat | queued, running, done, failed |

**Konvensi kontrak**

- Respons sukses: `{ "data": ..., "meta": ... }`. Galat validasi: HTTP 422 dengan `{ "message": ..., "errors": { "kolom": ["pesan"] } }`.
- Galat aturan bisnis memakai kode mesin yang stabil agar UI dapat bereaksi, contoh: `appointment.conflict`, `stock.insufficient`, `invoice.already_paid`.
- Pagination: cursor untuk daftar panjang (kunjungan, audit log), offset dengan total untuk daftar kecil.
- Pembayaran memakai header `Idempotency-Key` agar klik ganda atau percobaan ulang tidak membuat dua pembayaran.
- Pembaruan entitas penting menyertakan `version`; bila data sudah berubah oleh pengguna lain, server membalas 409 dan UI menawarkan muat ulang.
- Laju: login 5 percobaan per menit, pencarian 60 per menit per pengguna.
- Endpoint `/internal-api` hanya dipakai frontend sendiri sehingga tidak diberi nomor versi; perubahan yang memecah kontrak dirilis bersama frontend dan dijaga uji kontrak di CI.
