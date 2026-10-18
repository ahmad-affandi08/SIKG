import { Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Badge } from '@/components/ui/Badge';
import { ConfirmAction } from '@/components/ui/ConfirmAction';

interface Step {
  key: string;
  title: string;
  detail: string;
  done: boolean;
  required: boolean;
  summary: string;
  href: string;
  cta: string;
  import_href: string | null;
}

interface Props {
  steps: Step[];
  required_total: number;
  required_done: number;
}

/** Penyiapan awal (MST-10): daftar periksa yang mengikuti isi data klinik, plus tombol data awal. */
export default function Setup({ steps, required_total, required_done }: Props) {
  const ready = required_done === required_total;

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Penyiapan awal" />
        <PageHeader
          title="Penyiapan awal klinik"
          description={
            ready
              ? 'Semua langkah wajib sudah selesai. Klinik siap dipakai.'
              : `${String(required_done)} dari ${String(required_total)} langkah wajib selesai.`
          }
        />

        <section className="pane" aria-label="Data awal">
          <h2 className="pane-head">Data awal</h2>
          <div className="flex flex-col gap-2 p-3 text-sm">
            <p>
              Memuat metode pembayaran (tunai, transfer, QRIS, kartu), kategori biaya operasional,
              katalog tindakan gigi umum, katalog obat dan bahan umum, serta hari libur nasional
              bertanggal tetap. Tindakan dan obat dibuat <strong>nonaktif dengan harga Rp 0</strong>
              : isi harga lalu aktifkan. Kode ICD-9-CM dan kelas obat adalah usulan dan perlu
              diperiksa dokter. Libur yang tanggalnya berpindah tiap tahun (misalnya Idulfitri)
              ditambahkan sendiri di Pengaturan klinik.
            </p>
            <p>Aman dijalankan berulang: data yang sudah ada tidak diubah atau digandakan.</p>
            <div>
              <ConfirmAction
                label="Isi data awal"
                message="Menambahkan metode pembayaran, kategori biaya, katalog tindakan dan obat umum (nonaktif, harga Rp 0), dan hari libur nasional tetap. Data yang sudah ada tidak diubah."
                confirmLabel="Ya, isi data awal"
                variant="primary"
                onConfirm={() => {
                  router.post('/settings/setup/starter-data');
                }}
              />
            </div>
          </div>
        </section>

        <ol className="flex flex-col gap-2" aria-label="Langkah penyiapan">
          {steps.map((step, index) => (
            <li key={step.key} className="pane">
              <div className="pane-head">
                <span>{`${String(index + 1)}. ${step.title}`}</span>
                <span className="flex items-center gap-2">
                  {step.required ? null : <Badge tone="neutral">Anjuran</Badge>}
                  <Badge tone={step.done ? 'success' : 'warning'}>
                    {step.done ? 'Selesai' : 'Belum'}
                  </Badge>
                </span>
              </div>
              <div className="flex flex-col gap-2 p-3 text-sm">
                <p>{step.detail}</p>
                <p className="text-muted">{step.summary}</p>
                <div className="flex flex-wrap gap-2">
                  <Link href={step.href} className="btn btn-secondary">
                    {step.cta}
                  </Link>
                  {step.import_href !== null ? (
                    <Link href={step.import_href} className="btn btn-secondary">
                      Impor Excel
                    </Link>
                  ) : null}
                </div>
              </div>
            </li>
          ))}
        </ol>
      </main>
    </AppLayout>
  );
}
