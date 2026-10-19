import { Link } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { EmptyState } from '@/components/ui/EmptyState';
import { formatDate, formatDateTime, formatRupiah } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Props {
  history: {
    id: string;
    closing_date: string;
    cashier_name: string | null;
    system_amount: number;
    counted_amount: number;
    difference_amount: number;
    note: string | null;
    closed_at: string;
  }[];
  preview: { date: string; system_amount: number; payments: number };
}

/** Selisih bertanda: fisik dikurangi sistem. Uang tetap bilangan bulat rupiah. */
function differenceText(amount: number): string {
  if (amount === 0) {
    return 'Tidak ada selisih';
  }
  return `${amount > 0 ? 'Lebih ' : 'Kurang '}${formatRupiah(Math.abs(amount))}`;
}

export default function Index({ history, preview }: Props) {
  const timezone = useClinicTimezone();

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Tutup kas" />
        <PageHeader
          title="Tutup kas harian"
          description={`Kas tunai belum ditutup: ${formatRupiah(preview.system_amount)} dari ${String(preview.payments)} transaksi (per ${formatDate(preview.date)}).`}
          actions={
            <Link href="/cash-closings/create" className="btn btn-primary">
              Tutup kas sekarang
            </Link>
          }
        />
        {history.length === 0 ? (
          <EmptyState
            kind="invoice"
            size="page"
            title="Belum ada penutupan kas"
            description="Riwayat tutup kas harian akan tampil di sini."
          />
        ) : (
          <table>
            <caption className="sr-only">Riwayat tutup kas</caption>
            <thead>
              <tr>
                <th scope="col">Tanggal</th>
                <th scope="col">Kasir</th>
                <th scope="col" className="text-right">
                  Sistem
                </th>
                <th scope="col" className="text-right">
                  Fisik
                </th>
                <th scope="col">Selisih</th>
                <th scope="col">Catatan</th>
                <th scope="col">Ditutup</th>
              </tr>
            </thead>
            <tbody>
              {history.map((row) => (
                <tr key={row.id}>
                  <td>{formatDate(row.closing_date)}</td>
                  <td>{row.cashier_name ?? '-'}</td>
                  <td className="text-right">{formatRupiah(row.system_amount)}</td>
                  <td className="text-right">{formatRupiah(row.counted_amount)}</td>
                  <td>{differenceText(row.difference_amount)}</td>
                  <td>{row.note ?? '-'}</td>
                  <td>{formatDateTime(row.closed_at, timezone)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </main>
    </AppLayout>
  );
}
