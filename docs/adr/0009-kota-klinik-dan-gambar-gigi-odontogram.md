# ADR-0009: Kota pada profil klinik dan gambar gigi odontogram cetak

- Status: Disetujui (permintaan pengguna); melengkapi ADR-0008
- Konteks terkait: MST-01, RM-09, RM-10, OBT-03, KSR-06, ODO-06; Q-26

## Konteks

ADR-0008 menunda "Kota, tanggal" karena profil klinik belum menyimpan kota, dan odontogram cetak masih berupa kotak 3x3 dengan sel sudut kosong yang tampak kasar dan berbeda dari bagan di layar.

## Keputusan

- Profil klinik menyimpan kota (`clinics.city`, opsional, maksimal 100 karakter). Surat keterangan, surat rujukan, kwitansi, invoice, dan resep mencetak "Kota, tanggal"; bila kota kosong hanya tanggal yang tercetak. Format tanggal tetap DD/MM/YYYY (M18).
- Odontogram cetak menggambar tiap gigi sebagai persegi dengan persegi dalam: bidang atas bukal atau labial, bawah lingual atau palatal, kiri dan kanan mesial dan distal menurut letak gigi, dan persegi tengah oklusal atau insisal. Gambar dibuat sebagai SVG murni oleh `ToothGlyph` (tanpa pustaka tambahan) dan disematkan sebagai data URI. Kode kondisi dicetak di dalam bidangnya agar terbaca pada cetakan hitam putih.

## Akibat

- Bagan cetak dan bagan di layar memakai bentuk gigi yang sama.
- Kop dan "Kota, tanggal" bergantung pada profil klinik; Admin perlu mengisi kota agar tercetak.
