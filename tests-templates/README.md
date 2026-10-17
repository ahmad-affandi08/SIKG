# Templat tes

Berkas di sini adalah **templat**, bukan tes yang berjalan. Salin ke `tests/` (atau `resources/js/`), sesuaikan nama kelas dengan kode nyata, dan beri nama tes dengan ID kebutuhan (contoh `PAS-03`). Jangan menghapus asersi agar tes lulus (CLAUDE.md M16).

| Templat | Salin ke | Menjaga |
| --- | --- | --- |
| Architecture/ArchitectureTest.php | tests/Architecture/ | Batas lapisan dan konteks, larangan fungsi |
| Unit/MoneyTest.php | tests/Unit/Shared/ | Aturan uang (M4), 100% |
| Feature/AuthorizationMatrixTest.php | tests/Feature/ | Tiap sel matriks peran x aksi (M9) |
| Feature/TenantScopeTest.php | tests/Feature/ | IDOR antar-klinik dan cabang |
| Feature/AuditLogTest.php | tests/Feature/ | Audit dan access log (M10) |
| Feature/ClosedEncounterTest.php | tests/Feature/Clinical/ | Kunci catatan klinis (RM-07, M6) |
| Feature/IdempotentPaymentTest.php | tests/Feature/Billing/ | Idempotensi pembayaran, void/refund (KSR) |
| Feature/NoPhiInLogsTest.php | tests/Feature/ | PHI tidak masuk log (M11) |
| Concurrency/AppointmentConflictTest.php | tests/Feature/Scheduling/ | Bentrok jadwal konkuren (JDW-02) |
| Concurrency/SequenceTest.php | tests/Feature/ | Nomor RM dan invoice unik (ADR-0004) |
| Architecture/HostingCompatibilityTest.php | tests/Architecture/ | Tanpa exec/Process/Redis/Horizon/broadcast/Chromium; driver bawaan aman untuk shared hosting (M21) |
| Feature/CronJobsTest.php | tests/Feature/ | Scheduler dan antrean digerakkan cron; job idempoten (SYS-07, NFR-16) |
| Feature/HealthEndpointTest.php | tests/Feature/ | `/up`, `/health` bertoken tanpa PHI, kesiapan lingkungan (SYS-06, SYS-07) |
| frontend/schemas.test.ts | resources/js/features/<konteks>/ | Skema Zod sah dan tidak sah |
| frontend/api-contract.test.ts | resources/js/features/<konteks>/ | Respons API cocok dengan skema Zod |
| frontend/OdontogramTooth.test.tsx | resources/js/features/clinical/components/ | Interaksi, aksesibilitas, rollback |
| e2e/alur-utama.spec.ts | tests/e2e/ | Alur utama dari daftar sampai tutup kas |

Aturan data uji: data sintetis saja; NIK fiktif berawalan `9999`; waktu lewat `Clock` palsu; MySQL asli.
