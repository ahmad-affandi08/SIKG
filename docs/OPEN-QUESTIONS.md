# Pertanyaan terbuka dan konflik

Claude Code menambahkan entri di sini bila menemukan kebutuhan ambigu, konflik antar dokumen, atau keputusan yang bukan wewenangnya. Pengguna menjawab, lalu entri dipindah ke bagian "Terjawab" beserta keputusannya. Jangan menulis tanggal atau estimasi.

Format entri:

```
### Q-<nomor>: <judul singkat>
- Kebutuhan terkait: <ID>
- Dokumen yang bertabrakan atau kurang: <path dan butir>
- Opsi: (a) ... akibatnya ...; (b) ... akibatnya ...
- Rekomendasi:
```

## Terbuka

Pertanyaan bisnis untuk pemilik klinik ada di `docs/PRD/09-risiko-pertanyaan-terbuka.md` (15 butir). Sampai dijawab, pakai asumsi A1 sampai A8 di `docs/PRD/00-ringkasan-lingkup-persona.md`, kecuali pertanyaan itu menentukan struktur data (misalnya skema jasa dokter, butir 8): berhenti dan tanya.

(belum ada entri tambahan)

## Terjawab

(belum ada)
