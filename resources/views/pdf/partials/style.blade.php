{{-- Gaya cetak bersama untuk semua dokumen (ADR-0008). Hitam putih: hanya format (ukuran, tebal, garis), tanpa warna teks.
     Satu keluarga huruf (Helvetica/Arial), satu kop, satu gaya judul, satu gaya tabel, satu blok tanda tangan. --}}
@page { margin: 20mm 18mm 20mm 18mm; }
* { box-sizing: border-box; }
body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; line-height: 1.45; color: #000000; margin: 0; }
h1, h2, h3, p { margin: 0; }
.muted { color: #444444; }
.small { font-size: 8pt; }
.r { text-align: right; }
.c { text-align: center; }
.b { font-weight: bold; }
.num { text-align: right; white-space: nowrap; }

/* Kop surat: logo di kiri, nama dan alamat di tengah, garis ganda sebagai pemisah (konvensi surat resmi Indonesia) */
table.kop { width: 100%; border-collapse: collapse; margin-bottom: 14pt; }
table.kop td { vertical-align: middle; padding: 0 0 6pt; }
table.kop td.logo { width: 62pt; }
table.kop td.logo img { max-height: 54pt; max-width: 58pt; }
table.kop td.body { text-align: center; }
table.kop .name { font-size: 14pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4pt; line-height: 1.2; }
table.kop .line { font-size: 8.5pt; line-height: 1.4; }
table.kop tr.rule td { padding: 0; height: 0; border-bottom: 3pt double #000000; }

/* Judul dokumen: sama di semua dokumen */
.title { text-align: center; font-size: 12pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6pt; }
.subtitle { text-align: center; font-size: 10pt; margin-top: 1pt; }
.title + .subtitle { margin-bottom: 0; }
.head-gap { height: 12pt; }

/* Pasangan label dan nilai */
table.kv { width: 100%; border-collapse: collapse; }
table.kv td { padding: 1.5pt 0; vertical-align: top; }
table.kv td.k { width: 27%; padding-right: 4pt; }
table.kv td.s { width: 3%; }

/* Blok surat: Nomor, Lampiran, Perihal di kiri; tempat dan tanggal di kanan */
table.letter-meta { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
table.letter-meta td { padding: 1pt 0; vertical-align: top; }
table.letter-meta td.k { width: 62pt; }
table.letter-meta td.s { width: 8pt; }
table.letter-meta td.date { text-align: right; width: 36%; }

/* Tabel data: garis atas dan bawah tajuk, garis tipis antarbaris, garis tebal sebelum total */
table.data { width: 100%; border-collapse: collapse; margin-top: 4pt; }
table.data th { text-align: left; font-size: 8.5pt; font-weight: bold; padding: 4.5pt 5pt; border-top: 1pt solid #000000; border-bottom: 1pt solid #000000; background: #f0f0f0; }
table.data td { padding: 4pt 5pt; border-bottom: 0.5pt solid #c8c8c8; vertical-align: top; }
table.data tr { page-break-inside: avoid; }
table.data th.num, table.data td.num { text-align: right; }
table.data th.no, table.data td.no { width: 22pt; text-align: center; }
table.data tr.total td { font-weight: bold; border-top: 1pt solid #000000; border-bottom: 1pt solid #000000; background: #f0f0f0; }
table.data td .note { font-size: 8pt; color: #444444; }

/* Ringkasan jumlah di kanan bawah tabel */
table.sum { width: 46%; margin-left: 54%; border-collapse: collapse; margin-top: 6pt; }
table.sum td { padding: 2.5pt 5pt; }
table.sum td.v { text-align: right; white-space: nowrap; }
table.sum tr.grand td { border-top: 1pt solid #000000; border-bottom: 1pt solid #000000; font-weight: bold; font-size: 11pt; padding: 4pt 5pt; }

.terbilang { margin-top: 8pt; font-style: italic; }
.terbilang b { font-style: normal; }

h2.section { font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt; margin: 14pt 0 3pt; }

.stamp { display: inline-block; padding: 2pt 8pt; border: 1.5pt solid #000000; font-weight: bold; font-size: 10pt; letter-spacing: 1pt; }
.box { border: 0.8pt solid #000000; padding: 6pt 9pt; page-break-inside: avoid; }

/* Isi surat */
p.par { margin: 0 0 7pt; text-align: justify; white-space: pre-line; }
ol.list { margin: 0 0 7pt 0; padding-left: 16pt; }
ol.list li { margin-bottom: 2pt; text-align: justify; }

/* Tanda tangan: sama di semua dokumen */
table.sign { width: 100%; border-collapse: collapse; margin-top: 14pt; page-break-inside: avoid; }
table.sign td { vertical-align: top; text-align: center; padding: 0 6pt; }
.sign .role { }
.sign .space { height: 54pt; }
.sign .name { font-weight: bold; text-decoration: underline; }
.sign .meta { font-size: 8.5pt; }
.sign .blank { border-top: 0.7pt solid #000000; margin: 0 10pt; padding-top: 2pt; font-size: 8pt; }

.page-footer { position: fixed; left: 0; right: 0; bottom: -13mm; height: 9mm; font-size: 7.5pt; color: #444444; border-top: 0.5pt solid #888888; padding-top: 3pt; }
.page-footer table { width: 100%; border-collapse: collapse; }
.page-footer td { vertical-align: top; }
