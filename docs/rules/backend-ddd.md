# Aturan backend: Laravel 13, DDD modular monolith

Laravel 13 membutuhkan PHP 8.3 atau lebih baru. Atribut PHP bawaan Laravel boleh dipakai, tetapi tidak wajib.

## 1. Struktur

```
app/                         bootstrap Laravel saja (Providers, Console, Exceptions)
bootstrap/providers.php      daftar ServiceProvider tiap konteks
src/
  Shared/{Domain,Application,Infrastructure}
  IdentityAccess/ Clinic/ Patient/ Scheduling/ Clinical/ Pharmacy/ Billing/ Reporting/
    Contracts/               interface dan DTO yang boleh dipakai konteks lain
    Domain/                  Entity, ValueObject, Event, Repository (interface), Exception
    Application/             Command, Query, Handler, DTO
    Infrastructure/          Eloquent model, Repository impl, Migrations, integrasi
    Presentation/            Http/Controllers, Http/Requests, Http/Resources, routes.php
    <Context>ServiceProvider.php
tests/{Unit,Feature,Architecture}
```

- Autoload `Src\\` ke folder `src/` di `composer.json`.
- Buat konteks baru dengan `scripts/make-context.sh <Nama>`; jangan membuat folder manual.
- Konteks yang ada: IdentityAccess, Clinic, Patient, Scheduling, Clinical, Pharmacy, Billing, Reporting, Shared. Konteks Fase 2 (Integration, Insurance, Lab, Orthodontics) baru dibuat saat fasenya dikerjakan.

## 2. Aturan per lapisan

| Lapisan | Boleh | Dilarang |
| --- | --- | --- |
| Domain | PHP murni, Entity, ValueObject (`Money`, `PatientId`), DomainEvent, interface Repository, DomainException, `Shared\Domain` | `Illuminate\*`, Eloquent, `DB`, `Request`, `Auth`, facade, `now()`, `config()`, `app()`, I/O apa pun |
| Application | Handler command dan query, DTO, interface port, `Domain`, `Shared`, transaksi, dispatch event | Eloquent langsung (kecuali konteks CRUD, bagian 8), `Request`, `Response`, view, Inertia |
| Infrastructure | Eloquent model dan repository, penyimpanan berkas, klien integrasi, migrasi | Aturan bisnis, keputusan otorisasi |
| Presentation | Controller tipis, FormRequest, API Resource, halaman Inertia, route | Logika bisnis, query langsung, mengubah banyak agregat |

Arah dependensi: Presentation menuju Application menuju Domain; Infrastructure menuju Domain. Deptrac (`tooling/deptrac.yaml`) dan arch test menegakkan ini.

## 3. Aturan antar-konteks

- Referensi konteks lain **hanya lewat ID** (`PatientId`, `DoctorId`), bukan objek atau relasi Eloquent. Tipe ID dideklarasikan di `Src\<Context>\Contracts` agar konteks lain boleh memakainya.
- Konteks lain hanya boleh memakai `Src\<Lain>\Contracts\*` dan `Src\<Lain>\Domain\Events\*`. Selebihnya dilarang (diperiksa `scripts/guard.sh` dan arch test).
- Billing membuat item tagihan dengan mendengarkan `ProcedurePerformed` (Clinical) dan penjualan obat (Pharmacy). Clinical membaca alergi dari Patient lewat interface di `Patient\Contracts`.
- Reporting boleh membaca tabel konteks lain lewat query read-only terpusat di `Reporting/Infrastructure`. Reporting tidak pernah menulis.
- Domain event didispatch **setelah commit** (`DB::afterCommit` atau `ShouldDispatchAfterCommit`).

## 4. Pola wajib

**Command dan Handler.** Satu use case, satu handler, satu transaksi.

```php
<?php

declare(strict_types=1);

namespace Src\Billing\Application\RecordPayment;

use Src\Billing\Domain\InvoiceRepository;
use Src\Shared\Application\Transaction;
use Src\Shared\Application\Clock;

final readonly class RecordPaymentHandler
{
    public function __construct(
        private InvoiceRepository $invoices,
        private Transaction $tx,
        private Clock $clock,
    ) {}

    public function __invoke(RecordPayment $command): void
    {
        $this->tx->run(function () use ($command): void {
            $invoice = $this->invoices->lockById($command->invoiceId);
            $invoice->recordPayment($command->methodId, $command->amount, $command->idempotencyKey, $this->clock->now());
            $this->invoices->save($invoice);
        });
    }
}
```

**Controller tipis.**

```php
public function store(RecordPaymentRequest $request, string $invoiceId, RecordPaymentHandler $handler): RedirectResponse
{
    $this->authorize('payment.create');   // permission atau Policy; wajib
    $handler(RecordPayment::fromRequest($request, $invoiceId));
    return back();
}
```

**Repository.** Interface di Domain, implementasi Eloquent di Infrastructure. Repository memetakan model Eloquent menjadi entity dan sebaliknya. Metode pembaca yang butuh kunci (`lockById`) memakai `SELECT ... FOR UPDATE`.

**Value object uang.**

```php
final readonly class Money
{
    private function __construct(public int $amount) {}   // rupiah bulat

    public static function rupiah(int $amount): self
    {
        if ($amount < 0) { throw new \InvalidArgumentException('Nilai uang tidak boleh negatif'); }
        return new self($amount);
    }

    public function add(self $o): self { return new self($this->amount + $o->amount); }
    public function subtract(self $o): self { return self::rupiah($this->amount - $o->amount); }
    public function percent(int $basisPoints): self { return new self(intdiv($this->amount * $basisPoints + 5000, 10000)); } // satu aturan pembulatan
}
```

**Kesalahan domain.** Turunkan dari `DomainException` dengan kode mesin yang stabil (`appointment.conflict`, `stock.insufficient`, `invoice.already_paid`). Presentation memetakannya ke HTTP 409 atau 422 dengan format `{ message, code }`.

**Waktu dan ID.** Domain menerima waktu dari `Clock` (interface di Shared), tidak memanggil `now()`. Simpan UTC, tampilkan sesuai zona waktu klinik. ID memakai ULID, dibuat di Domain lewat `IdGenerator` atau factory ID.

**Idempotensi.** Command yang membuat pembayaran menyimpan `idempotency_key` unik per invoice; pengulangan mengembalikan hasil yang sama tanpa efek ganda.

**Penguncian.** Bentrok jadwal dicegah dengan kunci baris pada dokter dan kursi di hari yang sama dalam transaksi. MySQL tidak punya exclusion constraint; jangan mengandalkan pemeriksaan tanpa kunci.

**Nomor berurut** (nomor RM, invoice) dari tabel `sequences` dengan kunci baris dalam transaksi. Dilarang `MAX()+1`.

## 5. Otorisasi

- Izin disimpan sebagai permission granular (`patient.update`, `invoice.void`), dikelompokkan ke peran. Gunakan `spatie/laravel-permission` bila disetujui di ADR; jangan membuat dua sistem izin.
- Setiap route di balik middleware `auth`, `throttle`, dan pemeriksaan izin (`can:` atau Policy).
- Semua query ter-scope `clinic_id` dan `branch_id` lewat global scope yang diuji (tes IDOR antar-klinik).
- Matriks default ada di `docs/PRD/00-ringkasan-lingkup-persona.md`; tes otorisasi menguji setiap sel.

## 6. Audit

- Perubahan data pasien, rekam medis, tagihan, pembayaran, pengguna, dan peran menulis `audit_logs` (aktor, aksi, subjek, sebelum, sesudah, IP) dalam transaksi yang sama.
- Pembukaan rekam medis pasien menulis `access_logs`.
- Tabel log hanya di-`INSERT` oleh aplikasi; tidak ada UPDATE atau DELETE.

## 7. Validasi

- `FormRequest` adalah validasi otoritatif; pesan Indonesia.
- Validasi aturan bisnis ada di Domain, bukan di FormRequest.
- Skema Zod di frontend hanya umpan balik cepat.

## 8. Pragmatisme

DDD penuh untuk konteks inti: Clinical, Billing, Scheduling, Pharmacy (stok). Konteks Clinic (master data), IdentityAccess, dan Reporting boleh memakai pola CRUD terstruktur (Eloquent di Application atau Infrastructure) dengan alasan dicatat di ADR. Aturan keamanan, audit, dan scope tetap berlaku penuh.

## 9. Larangan (diperiksa `scripts/guard.sh`)

- `use Illuminate\...` atau `DB::`, `Auth::`, `Cache::`, `now(`, `config(` di `src/*/Domain`
- `float` atau `double` pada properti atau parameter bernama `*amount*`, `*price*`, `*total*`, `*fee*`
- `forceDelete(`, `->truncate(`, `DB::table(...)->delete(` pada tabel klinis dan keuangan
- `Model::unguard(`, `$guarded = []` pada model
- `dd(`, `dump(`, `var_dump(`, `ray(` yang tertinggal
- `env(` di luar folder `config/`
- Raw query dengan interpolasi variabel (`DB::raw("... $var")`, `whereRaw("... $var")`)
- Referensi lintas konteks di luar `Contracts` dan `Domain\Events`
- Controller yang memuat logika bisnis atau mengubah lebih dari satu agregat
