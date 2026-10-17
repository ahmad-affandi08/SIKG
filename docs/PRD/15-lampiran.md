# PRD SIKG: Lampiran

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 21. Lampiran

**Daftar status dan enum** (nilai awal; dipetakan ke PHP backed enum)

| Objek | Nilai |
| --- | --- |
| Status janji temu | scheduled, confirmed, checked_in, in_examination, completed, cancelled, no_show |
| Status kunjungan | in_progress, closed |
| Status tagihan | draft, issued, partially_paid, paid, void |
| Jenis pembayaran | payment, deposit, refund |
| Jenis pergerakan stok | in, out, adjustment |
| Jenis riwayat medis | allergy, systemic_disease, regular_medication, pregnancy |
| Dentisi odontogram | adult, child, mixed |
| Cakupan tindakan | tooth, surface, jaw, visit |

**Kode kondisi gigi awal** (usulan; Admin dapat menambah dan mengubah)

| Kode | Kondisi | Kode | Kondisi |
| --- | --- | --- | --- |
| SEH | Sehat | IMP | Impaksi |
| KAR | Karies | HLG | Hilang atau dicabut |
| TAM | Tambalan | SIS | Sisa akar |
| MHK | Mahkota | IMN | Implan |
| PSA | Perawatan saluran akar | JBT | Jembatan |
| FRK | Fraktur | GTL | Gigi tiruan lepasan |
| BER | Belum erupsi | | |

**Glosarium**

| Istilah | Arti |
| --- | --- |
| RME | Rekam medis elektronik |
| No. RM | Nomor rekam medis pasien, unik per klinik |
| FDI | Sistem penomoran gigi dua digit yang dipakai di odontogram |
| Odontogram | Diagram kondisi seluruh gigi pasien |
| SOAP | Subjective, Objective, Assessment, Plan: format catatan klinis |
| ICD-10 | Klasifikasi penyakit internasional; bab K00-K14 untuk rongga mulut |
| SATUSEHAT | Platform interoperabilitas data kesehatan Kemenkes |
| HL7 FHIR | Standar pertukaran data kesehatan yang dipakai SATUSEHAT |
| PSE | Penyelenggara sistem elektronik |
| RBAC | Role-based access control: hak akses berdasarkan peran |
| DDD | Domain-driven design |
| Bounded context | Batas model domain yang punya istilah dan aturan sendiri |
| Aggregate | Kelompok entity yang diubah sebagai satu kesatuan dengan satu akar |
| ADR | Architecture decision record: catatan keputusan arsitektur |
| RPO, RTO | Batas kehilangan data dan batas lama pemulihan yang dapat diterima |
| Idempotensi | Permintaan yang diulang tidak menghasilkan efek ganda |
| ULID | Pengenal unik yang dapat diurutkan menurut waktu |
| UAT | User acceptance test: uji penerimaan oleh pengguna sebenarnya |

**Sumber**

- [Peraturan Menteri Kesehatan Nomor 24 Tahun 2022 tentang Rekam Medis, teks lengkap](https://pasal.id/peraturan/permen/permenkes-no-24-tahun-2022)
- [Laravel 13 Released: PHP 8.3, Attributes, Laravel AI, and a Smoother Upgrade Path (Laravel News)](https://laravel-news.com/laravel-13-released)
