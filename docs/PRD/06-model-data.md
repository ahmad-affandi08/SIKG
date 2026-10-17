# PRD SIKG: Model Data MySQL

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 11. Model data MySQL

Model data memisahkan fakta klinis, jadwal, dan keuangan, dengan uang sebagai bilangan bulat rupiah dan semua data klinis atau keuangan memakai soft delete.

**Konvensi**

- Kunci primer ULID (CHAR(26)) pada tabel domain; nomor tampil (nomor RM, nomor invoice) disimpan di kolom terpisah.
- Kolom standar: `created_at`, `updated_at`, `created_by`, `updated_by`, `deleted_at`; tabel transaksional juga memiliki `clinic_id` dan `branch_id`.
- Uang: BIGINT UNSIGNED dalam rupiah, nama kolom berakhiran `_amount`. Persentase: DECIMAL(5,2). Waktu: DATETIME dalam UTC. Tanggal lahir: DATE.
- Enum disimpan sebagai VARCHAR yang dipetakan ke PHP backed enum, bukan tipe ENUM MySQL, agar perubahan nilai tidak butuh ALTER besar.
- Foreign key memakai ON DELETE RESTRICT; tidak ada cascade delete pada data klinis dan keuangan.
- Nomor berurut (nomor RM, invoice) dihasilkan dari tabel `sequences` dengan kunci baris dalam transaksi, bukan MAX()+1.

**Tabel per konteks**

| Konteks | Tabel | Kolom penting |
| --- | --- | --- |
| IdentityAccess | users, roles, permissions (dan tabel pivot) | name, email, username, password, is_active, last_login_at, two_factor_secret |
| Clinic | clinics, branches | name, address, license_no, timezone, logo_path |
| Clinic | doctors | user_id, branch_id, sip_no, specialty, fee_scheme, is_active |
| Clinic | chairs, doctor_schedules, holidays | branch_id, doctor_id, weekday, start_time, end_time, date |
| Clinic | procedures | code, name, category, scope (tooth, surface, jaw, visit), default_price_amount, is_active |
| Clinic | diagnoses, payment_methods, tooth_conditions | icd10_code, name; type; code, name, color |
| Patient | patients | medical_record_no (unik), name, name_search, nik_encrypted, nik_hash, birth_date, sex, address, phone_encrypted, phone_hash, occupation, emergency_contact, data_consent_at |
| Patient | patient_guardians, patient_medical_histories, patient_consents | patient_id, relation; type (alergi, penyakit sistemik, obat rutin, kehamilan), name, severity; text_version, given_at |
| Scheduling | appointments | patient_id, doctor_id, chair_id, starts_at, ends_at, status, source (booking, walkin), queue_no, complaint, cancel_reason |
| Clinical | encounters | patient_id, appointment_id, doctor_id, chair_id, started_at, closed_at, status, chief_complaint, vitals (JSON) |
| Clinical | clinical_notes | encounter_id, type (S, O, A, P, addendum), body, author_id, authored_at, supersedes_id |
| Clinical | encounter_diagnoses, encounter_procedures | encounter_id, diagnosis_id atau procedure_id, tooth_fdi, surface, jaw, quantity, unit_price_amount (salinan tarif), performed_by |
| Clinical | odontogram_snapshots, odontogram_tooth_states | patient_id, encounter_id, dentition, taken_at; snapshot_id, tooth_fdi, surface, condition_code (unik per snapshot, gigi, permukaan) |
| Clinical | treatment_plans, treatment_plan_items | patient_id, status; procedure_id, tooth_fdi, estimate_amount, sequence, status |
| Clinical | clinical_files | patient_id, encounter_id, tooth_fdi, disk, path, mime, size, kind (rontgen, foto, dokumen) |
| Pharmacy | medicines, stock_balances | name, unit, sell_price_amount, min_stock, drug_class; medicine_id, quantity |
| Pharmacy | stock_movements | medicine_id, type (masuk, keluar, penyesuaian), quantity, reference_type, reference_id, reason, batch_no, expiry_date |
| Pharmacy | prescriptions, prescription_items | encounter_id, doctor_id, issued_at; medicine_id atau free_text_name, dose, frequency, duration, instructions, quantity |
| Billing | invoices | number (unik), patient_id, encounter_id, status, subtotal_amount, discount_amount, total_amount, paid_amount, issued_at, voided_at, void_reason, voided_by |
| Billing | invoice_items | invoice_id, source_type, source_id, description, quantity, unit_price_amount, discount_amount, total_amount |
| Billing | payments | invoice_id, payment_method_id, type (bayar, dp, refund), amount, paid_at, received_by, reference_no |
| Billing | installment_plans, installments, cash_closings | invoice_id, due_date, amount, paid_at; date, cashier_id, system_amount, counted_amount, difference_amount |
| Platform | audit_logs | actor_id, action, subject_type, subject_id, before (JSON), after (JSON), ip, user_agent, created_at |
| Platform | access_logs, import_batches, sequences, jobs | actor_id, patient_id, route, at; type, file, status, errors; name, scope, next_value |

**Indeks utama**

- patients: unik pada `medical_record_no`; indeks pada `nik_hash` dan `phone_hash`; FULLTEXT pada `name_search`. Evaluasi mesin pencari terpisah bila pasien melewati 200.000.
- appointments: (`doctor_id`, `starts_at`), (`chair_id`, `starts_at`), (`status`, `starts_at`).
- encounters: (`patient_id`, `started_at` menurun). invoices: unik pada `number`; (`status`, `issued_at`); (`patient_id`).
- payments: (`paid_at`, `payment_method_id`). stock_movements: (`medicine_id`, `created_at`). audit_logs: (`subject_type`, `subject_id`) dan (`actor_id`, `created_at`).

**Aturan integritas yang dijaga di domain dan diuji**

- Total tagihan sama dengan jumlah item dikurangi diskon; jumlah dibayar sama dengan jumlah pembayaran; status tagihan diturunkan dari keduanya, bukan diisi manual.
- Bentrok jadwal dicegah di application service dengan kunci baris (SELECT ... FOR UPDATE) pada dokter dan kursi di hari yang sama, karena MySQL tidak memiliki exclusion constraint; diuji dengan uji konkurensi.
- Kunjungan yang sudah ditutup tidak menerima perubahan catatan klinis selain addendum yang sah (RM-07).
- Void tidak menghapus tagihan; ia mengubah status dan membuat pembayaran berjenis refund bila ada uang masuk.
- Stok tidak boleh negatif; override hanya oleh Admin dengan alasan, tercatat sebagai penyesuaian.
- Odontogram terkini dihitung dari keadaan terbaru tiap pasangan gigi dan permukaan lintas snapshot.
- NIK dan nomor HP dienkripsi di aplikasi; pencarian memakai kolom hash (HMAC dengan kunci aplikasi), bukan dekripsi massal.
