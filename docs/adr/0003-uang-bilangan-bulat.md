# ADR-0003: Uang sebagai bilangan bulat rupiah

- Status: Disetujui
- Konteks terkait: KSR, MST-08, NFR-05

## Konteks

Kesalahan pembulatan pada tagihan menimbulkan kerugian dan sengketa. Rupiah tidak memakai pecahan dalam transaksi klinik.

## Keputusan

Semua nilai uang adalah bilangan bulat rupiah: kolom `BIGINT UNSIGNED` berakhiran `_amount`, value object `Money` di `Src\Shared\Domain`, `number` bulat di frontend. Pembulatan persentase hanya di `Money::percent` (basis poin, bulatkan setengah ke atas). Tarif disalin ke item saat item dibuat.

## Akibat

- Dilarang `float`, `double`, `decimal` untuk uang (diperiksa `scripts/guard.sh`).
- Aturan uang diuji 100%.
