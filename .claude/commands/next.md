---
description: Pilih dan mulai kebutuhan berikutnya sesuai urutan prioritas
---

1. Jalankan `python3 scripts/status.py` dan `python3 scripts/status.py --next`.
2. Urutan: Fase 1 Must, Fase 1 Should, Fase 1 Could, baru Fase 2 (P2-01 lebih dulu), lalu Fase 3. Fase 2 dan 3 tidak dimulai sebelum seluruh Must Fase 1 selesai dan pengguna menyetujui gerbangnya (CLAUDE.md M14).
3. Sebutkan satu ID berikutnya beserta alasannya (satu kalimat, tanpa estimasi waktu), lalu kerjakan dengan alur `/implement <ID>`.
4. Bila ID berikutnya bergantung pada ID lain yang belum selesai, kerjakan prasyaratnya lebih dulu dan jelaskan.
