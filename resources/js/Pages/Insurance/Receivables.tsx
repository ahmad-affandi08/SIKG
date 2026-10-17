import { Link } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { formatRupiah } from '@/lib/format';

interface Props {
  total: number;
  count: number;
  buckets: { label: string; amount: number }[];
  insurers: {
    insurer_id: string;
    insurer_name: string;
    count: number;
    outstanding: number;
    buckets: { label: string; amount: number }[];
  }[];
}

/** P2-02: piutang penjamin per penjamin dengan umur piutang dari tanggal pengajuan. */
export default function Receivables({ total, count, buckets, insurers }: Props) {
  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Piutang penjamin" />
        <PageHeader
          title="Piutang penjamin"
          back={{ href: '/insurance/claims', label: 'klaim penjamin' }}
          description={`${String(count)} klaim belum lunas, total ${formatRupiah(total)}. Umur dihitung dari tanggal pengajuan.`}
        />
        {insurers.length === 0 ? (
          <p className="text-muted text-sm">Tidak ada piutang penjamin.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Piutang per penjamin</caption>
            <thead>
              <tr>
                <th scope="col">Penjamin</th>
                <th scope="col" className="text-right">
                  Klaim
                </th>
                {buckets.map((b) => (
                  <th key={b.label} scope="col" className="text-right">
                    {b.label}
                  </th>
                ))}
                <th scope="col" className="text-right">
                  Total
                </th>
              </tr>
            </thead>
            <tbody>
              {insurers.map((i) => (
                <tr key={i.insurer_id}>
                  <td>
                    <Link href={`/insurance/claims?insurer=${i.insurer_id}`} className="underline">
                      {i.insurer_name}
                    </Link>
                  </td>
                  <td className="text-right">{i.count}</td>
                  {i.buckets.map((b) => (
                    <td key={b.label} className="text-right">
                      {formatRupiah(b.amount)}
                    </td>
                  ))}
                  <td className="text-right font-bold">{formatRupiah(i.outstanding)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <th scope="row">Jumlah</th>
                <td className="text-right">{count}</td>
                {buckets.map((b) => (
                  <td key={b.label} className="text-right font-bold">
                    {formatRupiah(b.amount)}
                  </td>
                ))}
                <td className="text-right font-bold">{formatRupiah(total)}</td>
              </tr>
            </tfoot>
          </table>
        )}
      </main>
    </AppLayout>
  );
}
