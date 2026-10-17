import type { ReactNode } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import type { InvoiceHead } from '@/features/billing/schemas';
import { formatRupiah } from '@/lib/format';

interface InvoiceFormShellProps {
  title: string;
  invoice: InvoiceHead;
  children: ReactNode;
  /** Tampilkan ringkasan jumlah (total, dibayar, sisa) di bawah judul. */
  showAmounts?: boolean;
}

/** Kerangka halaman formulir yang berasal dari satu tagihan: kepala halaman, tautan kembali, ringkasan, dan isi. */
export function InvoiceFormShell({ title, invoice, children, showAmounts }: InvoiceFormShellProps) {
  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title={title} />
        <PageHeader
          title={title}
          back={{ href: `/invoices/${invoice.id}`, label: 'tagihan' }}
          description={`${invoice.number ?? 'Draft'} | ${invoice.patient_name ?? 'Pasien'} (${invoice.medical_record_no ?? '-'})`}
        />
        {showAmounts ? (
          <dl className="grid max-w-md grid-cols-[auto_1fr] gap-x-4 gap-y-0.5">
            <dt className="text-muted">Total tagihan</dt>
            <dd className="text-right">{formatRupiah(invoice.total_amount)}</dd>
            <dt className="text-muted">Sudah dibayar</dt>
            <dd className="text-right">{formatRupiah(invoice.paid_amount ?? 0)}</dd>
            <dt className="font-bold">Sisa tagihan</dt>
            <dd className="text-right font-bold">{formatRupiah(invoice.balance_amount ?? 0)}</dd>
          </dl>
        ) : null}
        {children}
      </main>
    </AppLayout>
  );
}
