# Aturan frontend: Inertia + React + TypeScript + Tailwind 4 + Zod + TanStack Query

## 1. Pembagian tugas data

| Kebutuhan | Pakai | Alasan |
| --- | --- | --- |
| Data halaman awal, daftar, detail, navigasi, submit form biasa | **Inertia** (props dan `useForm` atau `router`) | Server tetap sumber kebenaran, tanpa lapisan cache kedua |
| Autocomplete (pasien, obat, tindakan, diagnosis) | **TanStack Query** | Cache, debounce, pembatalan |
| Antrean hari ini (penyegaran berkala) | **TanStack Query** `refetchInterval` 10 detik | Tidak perlu muat ulang halaman |
| Odontogram (ubah gigi, undo) | **TanStack Query** mutation dengan optimistic update dan rollback | Respons langsung |
| Tindakan pembayaran dan penutupan kunjungan | **Inertia** form, bukan optimistic | Harus pasti |

Dilarang memakai TanStack Query untuk data yang sudah tersedia lewat props Inertia pada halaman yang sama (dua sumber kebenaran). Dilarang `fetch` atau `axios` mentah di komponen; semua lewat modul `api.ts` fitur.

## 2. Struktur

```
resources/js/
  app.tsx                    boot Inertia, QueryClientProvider
  Pages/<Area>/<Page>.tsx    satu file per halaman Inertia (tipis, merakit fitur)
  features/<context>/
    api.ts                   fungsi pemanggil /internal-api, memparse respons dengan Zod
    queries.ts               query key factory dan hook useXxxQuery
    schemas.ts               skema Zod (form dan respons) + tipe z.infer
    components/              komponen khusus fitur
  components/ui/             komponen dasar (Button, Input, Dialog, Table, Toast, dst.)
  lib/                       format (rupiah, tanggal), http client, util
```

- Satu arah impor: `Pages` memakai `features` dan `components`; `features` memakai `components` dan `lib`; `features/A` tidak mengimpor dari `features/B` (gunakan komponen bersama bila perlu).
- Nama berkas: komponen `PascalCase.tsx`, selain itu `camelCase.ts`.

## 3. TypeScript dan lint

- `strict: true`, `noUncheckedIndexedAccess: true`. Dilarang `any`, `@ts-ignore`, `@ts-nocheck`, dan `as` untuk menyiasati tipe data dari server (parse dengan Zod).
- Tipe diturunkan dengan `z.infer<typeof schema>`; jangan menulis tipe ganda untuk bentuk yang sama.
- ESLint (config di `tooling/eslint.config.js`) lulus tanpa peringatan.

## 4. Zod

- Semua form punya skema Zod di `schemas.ts`, dipakai oleh React Hook Form (`zodResolver`) bila disetujui di ADR, atau `useForm` Inertia dengan validasi sebelum kirim.
- Semua respons dari `/internal-api` diparse dengan skema Zod di `api.ts` sebelum dipakai. Respons yang tidak cocok dilaporkan sebagai galat, bukan diabaikan.
- Skema Zod hanya umpan balik cepat. Server memvalidasi ulang dan berwenang (galat server 422 ditampilkan per bidang).
- Pesan galat Zod berbahasa Indonesia (peta galat global di `lib/zod-id.ts`).
- Uang di frontend adalah bilangan bulat rupiah (`z.number().int().nonnegative()`); format tampilan lewat `formatRupiah()`. Dilarang aritmatika desimal untuk uang.

## 5. TanStack Query v5

- Query key lewat factory per fitur, contoh `patientKeys.search(term)`. Dilarang string literal tersebar.
- `staleTime` dan `gcTime` ditetapkan per query; jangan mengandalkan default untuk data klinis.
- Mutation yang mengubah data harus meng-invalidate key yang terkait.
- Galat 409 (versi konflik) dan 422 ditangani eksplisit dengan pesan Indonesia dan opsi muat ulang.
- Optimistic update wajib `onError` rollback dan `onSettled` invalidate.
- Query key hidup di memori. Jangan mem-persist cache query (tanpa plugin persist ke localStorage atau IndexedDB), jangan mencatatnya ke log, dan pakai ID (bukan NIK atau nomor HP) sebagai bagian key untuk entitas. Istilah pencarian transien boleh ada di key.

## 6. Tailwind CSS 4

- Konfigurasi CSS-first: token di `resources/css/app.css` dengan `@import "tailwindcss";` dan `@theme { ... }`. Tidak ada `tailwind.config.js` baru kecuali dibutuhkan plugin yang tidak mendukung CSS-first.
- Warna, ukuran, dan radius dari token tema; dilarang nilai arbitrer berulang (`w-[317px]`) tanpa alasan.
- Dukung mode tablet (lebar 768 px ke atas) untuk dokter dan perawat, dengan target sentuh minimal 44 px. Halaman kasir dan pendaftaran dioptimalkan untuk desktop dan klaviatur.
- Komponen mengikuti `components/ui`; jangan membuat varian tombol atau input baru di halaman.

## 7. Odontogram

- Komponen SVG, penomoran FDI (11-18, 21-28, 31-38, 41-48; sulung 51-55, 61-65, 71-75, 81-85). Satu gigi terdiri dari lima permukaan (M, D, O/I, B/F, L/P) bila relevan.
- Warna kondisi dari tabel master `tooth_conditions`, bukan konstanta di komponen.
- Setiap gigi dapat diakses lewat klaviatur, dengan `aria-label` (contoh "Gigi 16, karies oklusal").
- Tidak mengandalkan warna saja: tambahkan kode kondisi (SEH, KAR, TAM, dst.) atau pola.
- Semua perubahan lewat mutation ke server; status lokal hanya sementara.

## 8. UX dan aksesibilitas

- Bahasa Indonesia untuk semua teks; format tanggal DD/MM/YYYY, waktu 24 jam, uang `Rp 1.250.000`.
- Setiap aksi destruktif atau keuangan (void, refund, tutup kunjungan, tutup kas) memakai dialog konfirmasi yang menyebut akibatnya; void dan refund meminta alasan.
- Tampilkan peringatan alergi dan kondisi sistemik pasien secara menonjol di halaman klinis (RM-03).
- Semua input berlabel; fokus terlihat; kontras minimal WCAG AA; status tidak hanya lewat warna.
- Daftar panjang memakai paginasi sisi server; keadaan kosong, memuat, dan galat selalu ada.
- Dilarang `console.log` data pasien. Dilarang menyimpan PHI di `localStorage` atau `sessionStorage`.
- Hilangkan tombol atau menu sesuai izin dari props `auth.permissions` hanya sebagai kenyamanan; server tetap menolak (M9).

## 9. Tes frontend

- Vitest + Testing Library untuk komponen dan hook; MSW atau mock `api.ts` untuk jaringan.
- Skema Zod diuji dengan kasus sah dan tidak sah.
- Komponen odontogram diuji untuk interaksi, rollback, dan aksesibilitas dasar (peran dan label).
- Playwright untuk alur utama (lihat `docs/rules/testing-dod.md`).
