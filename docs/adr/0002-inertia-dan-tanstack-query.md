# ADR-0002: Pembagian Inertia dan TanStack Query

- Status: Disetujui
- Konteks terkait: frontend, PAS-02, JDW-06, ODO-04

## Konteks

Inertia menyediakan data halaman dan form dengan server sebagai sumber kebenaran. Sebagian interaksi butuh cache, debounce, polling, dan pembaruan optimistis tanpa navigasi.

## Keputusan

- Data halaman, daftar, detail, dan form memakai props dan form Inertia.
- TanStack Query hanya untuk autocomplete, polling antrean, odontogram (mutation optimistis), dan status pekerjaan laporan.
- Pembaruan layar memakai polling, bukan WebSocket, sehingga berjalan di shared hosting (ADR-0006).
- Endpoint JSON berada di `/internal-api` dengan sesi dan CSRF. Tidak ada token publik.
- Zod untuk skema form dan respons; server tetap otoritatif.
- Pembayaran dan penutupan kunjungan tidak optimistis.

## Alternatif yang dipertimbangkan

- Semua lewat TanStack Query (SPA penuh): dua sumber kebenaran dan duplikasi otorisasi.
- Semua lewat Inertia: kurang cocok untuk autocomplete dan polling.

## Akibat

Aturan di `docs/rules/frontend.md`. Dilarang memakai TanStack Query untuk data yang sudah ada di props halaman yang sama.
