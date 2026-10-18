import { Link } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { INTENT_TONE } from '@/features/onlinePayments/schemas';
import { formatRupiah } from '@/lib/format';

interface Row {
  id: string;
  invoice_id: string;
  invoice_number: string;
  order_id: string;
  amount: number;
  status: string;
  status_label: string;
  note: string | null;
  gateway_ref: string | null;
  created_at: string;
  paid_at: string | null;
}

/** P3-05: rekonsiliasi pembayaran online. Baris "Perlu diselesaikan" berisi uang yang diterima penyedia tetapi tidak dapat dicatat ke tagihan. */
export default function OnlinePayments({ intents }: { intents: Row[] }) {
  const mismatches = intents.filter((row) => row.status === 'mismatch');

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Pembayaran online" />
        <PageHeader
          title="Pembayaran online"
          description="Rekonsiliasi QRIS: pembayaran yang masuk dicocokkan ke tagihan secara otomatis."
        />
        {mismatches.length > 0 ? (
          <Alert tone="error">
            {String(mismatches.length)} pembayaran diterima penyedia tetapi tidak dapat dicatat ke
            tagihan (tagihan lunas, dibatalkan, atau jumlah berbeda). Kembalikan dana lewat dasbor
            penyedia, lalu catat di tagihan bila perlu.
          </Alert>
        ) : null}
        <div className="overflow-x-auto">
          <table className="data-table w-full text-sm">
            <thead>
              <tr>
                <th>Waktu</th>
                <th>Tagihan</th>
                <th>Jumlah</th>
                <th>Status</th>
                <th>Keterangan</th>
              </tr>
            </thead>
            <tbody>
              {intents.length === 0 ? (
                <tr>
                  <td colSpan={5} className="text-muted">
                    Belum ada pembayaran online.
                  </td>
                </tr>
              ) : null}
              {intents.map((row) => (
                <tr key={row.id}>
                  <td>{row.created_at}</td>
                  <td>
                    <Link href={`/invoices/${row.invoice_id}`} className="underline">
                      {row.invoice_number}
                    </Link>
                  </td>
                  <td>{formatRupiah(row.amount)}</td>
                  <td>
                    <Badge tone={INTENT_TONE[row.status] ?? 'neutral'}>{row.status_label}</Badge>
                  </td>
                  <td>{row.note ?? (row.paid_at ? `dibayar ${row.paid_at}` : '')}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </main>
    </AppLayout>
  );
}
