# ADR-0004: ULID, urutan nomor, dan penguncian baris

- Status: Disetujui
- Konteks terkait: PAS-01, JDW-02, KSR-06, KSR-04

## Konteks

ID berurutan di URL mudah ditebak. MySQL tidak punya exclusion constraint untuk mencegah jadwal bentrok. `MAX()+1` menghasilkan nomor ganda saat konkurensi.

## Keputusan

- Kunci primer ULID `CHAR(26)`; nomor tampil di kolom terpisah.
- Nomor RM dan nomor invoice dari tabel `sequences` dengan kunci baris dalam transaksi.
- Bentrok jadwal dicegah dengan `SELECT ... FOR UPDATE` pada dokter dan kursi di hari yang sama, diuji dengan uji konkurensi.
- Pembayaran memakai `Idempotency-Key`; unik per (`invoice_id`, `idempotency_key`).
- Pembaruan entitas penting memakai kolom `version` (optimistic locking) dan membalas 409 bila konflik.

## Akibat

Template tes konkurensi ada di `tests-templates/Concurrency`.
