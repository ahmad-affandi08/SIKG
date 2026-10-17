# Aturan database MySQL dan model data

## 1. Konvensi

- MySQL 8.0 atau lebih baru, InnoDB, `utf8mb4`. Semua tabel bertransaksi; foreign key aktif.
- Kunci primer ULID (`CHAR(26)`) pada tabel domain. Nomor tampil (nomor RM, nomor invoice) di kolom terpisah.
- Kolom standar: `created_at`, `updated_at`, `created_by`, `updated_by`, `deleted_at`. Tabel transaksional memiliki `clinic_id` dan `branch_id`.
- Uang: `BIGINT UNSIGNED` rupiah, nama kolom berakhiran `_amount`. Persentase: `DECIMAL(5,2)`. Waktu: `DATETIME` UTC. Tanggal lahir: `DATE`.
- Enum disimpan sebagai `VARCHAR` dan dipetakan ke PHP backed enum, bukan tipe `ENUM` MySQL.
- Foreign key `ON DELETE RESTRICT`. Tidak ada cascade delete pada data klinis dan keuangan.
- Soft delete pada data klinis dan keuangan. Tidak ada hapus permanen lewat aplikasi (M5).
- Nomor berurut dari tabel `sequences` dengan kunci baris dalam transaksi, bukan `MAX()+1`.
- NIK dan nomor HP: kolom `*_encrypted` (enkripsi aplikasi) ditambah `*_hash` (HMAC dengan kunci aplikasi) untuk pencarian. Pencarian memakai hash, bukan dekripsi massal.

## 2. Tabel per konteks

| Konteks | Tabel | Kolom penting |
| --- | --- | --- |
| IdentityAccess | users, roles, permissions (dan pivot) | name, email, username, password, is_active, last_login_at, two_factor_secret |
| Clinic | clinics, branches | name, address, license_no, timezone, logo_path |
| Clinic | doctors | user_id, branch_id, sip_no, specialty, fee_scheme, is_active |
| Clinic | chairs, doctor_schedules, holidays | branch_id, doctor_id, weekday, start_time, end_time, date |
| Clinic | procedures | code, name, category, scope (tooth, surface, jaw, visit), default_price_amount, is_active |
| Clinic | diagnoses, payment_methods, tooth_conditions | icd10_code, name; type; code, name, color |
| Patient | patients | medical_record_no (unik), name, name_search, nik_encrypted, nik_hash, birth_date, sex, address, phone_encrypted, phone_hash, occupation, emergency_contact, data_consent_at |
| Patient | patient_guardians, patient_medical_histories, patient_consents | patient_id, relation; type (allergy, systemic_disease, regular_medication, pregnancy), name, severity; text_version, given_at |
| Scheduling | appointments | patient_id, doctor_id, chair_id, starts_at, ends_at, status, source (booking, walkin), queue_no, complaint, cancel_reason |
| Clinical | encounters | patient_id, appointment_id, doctor_id, chair_id, started_at, closed_at, status, chief_complaint, vitals (JSON) |
| Clinical | clinical_notes | encounter_id, type (S, O, A, P, addendum), body, author_id, authored_at, supersedes_id |
| Clinical | encounter_diagnoses, encounter_procedures | encounter_id, diagnosis_id atau procedure_id, tooth_fdi, surface, jaw, quantity, unit_price_amount (salinan tarif), performed_by |
| Clinical | odontogram_snapshots, odontogram_tooth_states | patient_id, encounter_id, dentition, taken_at; snapshot_id, tooth_fdi, surface, condition_code (unik per snapshot, gigi, permukaan) |
| Clinical | treatment_plans, treatment_plan_items | patient_id, status; procedure_id, tooth_fdi, estimate_amount, sequence, status |
| Clinical | clinical_files | patient_id, encounter_id, tooth_fdi, disk, path, mime, size, kind (xray, photo, document) |
| Pharmacy | medicines, stock_balances | name, unit, sell_price_amount, min_stock, drug_class; medicine_id, quantity |
| Pharmacy | stock_movements | medicine_id, type (in, out, adjustment), quantity, reference_type, reference_id, reason, batch_no, expiry_date |
| Pharmacy | prescriptions, prescription_items | encounter_id, doctor_id, issued_at; medicine_id atau free_text_name, dose, frequency, duration, instructions, quantity |
| Billing | invoices | number (unik), patient_id, encounter_id, status, subtotal_amount, discount_amount, total_amount, paid_amount, issued_at, voided_at, void_reason, voided_by |
| Billing | invoice_items | invoice_id, source_type, source_id, description, quantity, unit_price_amount, discount_amount, total_amount |
| Billing | payments | invoice_id, payment_method_id, type (payment, deposit, refund), amount, paid_at, received_by, reference_no, idempotency_key |
| Billing | installment_plans, installments, cash_closings | invoice_id, due_date, amount, paid_at; date, cashier_id, system_amount, counted_amount, difference_amount |
| Platform | audit_logs | actor_id, action, subject_type, subject_id, before (JSON), after (JSON), ip, user_agent, created_at |
| Platform | access_logs, import_batches, sequences, jobs | actor_id, patient_id, route, at; type, file, status, errors; name, scope, next_value |
| Platform | system_heartbeats | name (unik: `scheduler`, `queue`), beat_at, meta (JSON tanpa PHI); ditulis scheduler dan worker (SYS-07) |
| Platform (bawaan framework) | sessions, cache, cache_locks, job_batches, failed_jobs, password_reset_tokens | Dibutuhkan karena sesi, cache, dan antrean memakai driver `database` (`docs/rules/hosting.md`); tidak memakai kolom standar atau `clinic_id` |

Tabel di luar daftar ini membutuhkan persetujuan pengguna (tulis ADR singkat).

## 3. Indeks utama

- patients: unik `medical_record_no`; indeks `nik_hash`, `phone_hash`; FULLTEXT `name_search`.
- appointments: (`doctor_id`, `starts_at`), (`chair_id`, `starts_at`), (`status`, `starts_at`).
- encounters: (`patient_id`, `started_at` menurun).
- invoices: unik `number`; (`status`, `issued_at`); (`patient_id`).
- payments: (`paid_at`, `payment_method_id`); unik (`invoice_id`, `idempotency_key`).
- stock_movements: (`medicine_id`, `created_at`).
- audit_logs: (`subject_type`, `subject_id`), (`actor_id`, `created_at`).

## 4. Aturan integritas (dijaga di domain dan diuji)

- Total tagihan = jumlah item dikurangi diskon; jumlah dibayar = jumlah pembayaran; status tagihan diturunkan dari keduanya.
- Bentrok jadwal dicegah dengan kunci baris dalam transaksi dan diuji dengan uji konkurensi.
- Kunjungan yang sudah ditutup tidak menerima perubahan catatan klinis selain addendum sah (RM-07).
- Void tidak menghapus tagihan; ia mengubah status dan membuat pembayaran berjenis `refund` bila ada uang masuk.
- Stok tidak boleh negatif; override hanya oleh Admin dengan alasan, tercatat sebagai penyesuaian.
- Odontogram terkini dihitung dari keadaan terbaru tiap pasangan gigi dan permukaan lintas snapshot.
- Tarif pada item tagihan dan tindakan adalah salinan saat dibuat; perubahan tarif master tidak mengubah data lama.

## 5. Aturan migrasi

- Satu migrasi per perubahan; nama deskriptif; berada di `src/<Context>/Infrastructure/Migrations`, dimuat oleh ServiceProvider konteks.
- Migrasi yang sudah digabung ke cabang utama tidak diedit. Buat migrasi baru.
- Pola expand-contract untuk perubahan yang merusak: tambah kolom baru, isi, ganti pemakaian, baru hapus kolom lama pada migrasi terpisah (dan hanya dengan persetujuan pengguna).
- Setiap migrasi harus bisa naik dan turun, dan aman dijalankan di data berukuran produksi (tidak mengunci tabel besar tanpa alasan).
- Dilarang `migrate:fresh`, `migrate:reset`, `db:wipe` di luar lingkungan lokal dan uji.
- Seeder hanya berisi data master dan data sintetis. Tidak ada data pasien nyata.
- Tes fitur memakai MySQL asli (bukan SQLite) agar perilaku kunci dan tipe data sama dengan produksi.
- Kompatibel hosting bersama (M21): tanpa trigger, stored procedure/function, event MySQL, dan `GRANT` per tabel; hanya hak `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES, LOCK TABLES`. Indeks FULLTEXT dan kunci baris (`SELECT ... FOR UPDATE`) dipakai sesuai MySQL 8; jangan memakai fitur yang hanya ada di versi server khusus.
- Migrasi besar dipecah (satu perubahan tabel per migrasi) dan selesai dalam batas waktu hosting; pengisian data ulang yang berat dilakukan lewat job berpotongan, bukan di dalam migrasi.
