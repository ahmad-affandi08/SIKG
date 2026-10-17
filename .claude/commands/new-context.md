---
description: Buat kerangka bounded context baru yang sah
argument-hint: <NamaKonteks>
---

1. Pastikan `$ARGUMENTS` ada dalam daftar sah (Shared, IdentityAccess, Clinic, Patient, Scheduling, Clinical, Pharmacy, Billing, Reporting; Fase 2: Integration, Insurance, Lab, Orthodontics hanya saat fasenya dikerjakan).
2. Jalankan `scripts/make-context.sh $ARGUMENTS`. Jangan membuat folder konteks secara manual.
3. Tambahkan aturan baru di `tooling/deptrac.yaml` bila perlu: itu file terlindungi, jadi catat usulan di `docs/OPEN-QUESTIONS.md` dan minta pengguna menambahkannya.
4. Pastikan `bootstrap/providers.php` memuat ServiceProvider konteks, lalu jalankan `scripts/verify.sh`.
