---
description: Audit perubahan saat ini terhadap aturan SIKG
---

Jalankan sub-agent `rules-reviewer` pada perubahan yang belum di-commit (`git diff` dan `git status`), dan sub-agent `security-reviewer` bila perubahan menyentuh data pasien, autentikasi, otorisasi, berkas, log, atau uang.

Laporkan temuan berurutan dari yang paling berat, masing-masing dengan: butir aturan (contoh M4, docs/rules/backend-ddd.md bagian 2), lokasi file:baris, dan perbaikan yang disarankan. Lalu perbaiki temuan yang jelas, dan jalankan `scripts/verify.sh`. Jangan menulis tanggal atau estimasi.
