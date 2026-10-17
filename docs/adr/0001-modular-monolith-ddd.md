# ADR-0001: Modular monolith dengan DDD

- Status: Disetujui
- Konteks terkait: seluruh sistem

## Konteks

Klinik kecil (1 sampai 3 dokter) dengan tim pengembang kecil. Aturan bisnis penting ada di uang, jadwal, dan catatan klinis. Microservice menambah beban operasional tanpa manfaat pada skala ini.

## Keputusan

Satu aplikasi Laravel 13 dan satu database MySQL, dibagi menjadi bounded context di `src/` (namespace `Src\`): Shared, IdentityAccess, Clinic, Patient, Scheduling, Clinical, Pharmacy, Billing, Reporting. Konteks inti (Clinical, Billing, Scheduling, Pharmacy) memakai DDD penuh. Konteks master data sederhana boleh memakai pola CRUD terstruktur.

## Alternatif yang dipertimbangkan

- Laravel konvensional (`app/` berlapis MVC): cepat di awal, tetapi aturan bisnis tersebar di controller dan model, sulit dijaga oleh agen otomatis.
- Microservice: terlalu berat untuk skala klinik.

## Akibat

- Batas lapisan dan konteks ditegakkan `tooling/deptrac.yaml`, arch test Pest, dan `scripts/guard.sh`.
- Komunikasi antar-konteks lewat ID, `Contracts`, dan domain event setelah commit.
- Setiap pengecualian CRUD harus dicatat sebagai ADR baru.
