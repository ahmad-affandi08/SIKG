import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { EmptyState } from '@/components/ui/EmptyState';
import { formatDate, formatRupiah } from '@/lib/format';

interface Batch {
  id: string;
  name: string;
  unit: string;
  batch_no: string | null;
  expiry_date: string | null;
  received_quantity: number;
  remaining_quantity: number;
  unit_cost_amount: number | null;
  supplier_name: string | null;
  received_at: string;
}

/** P2-05: batch yang masih bersisa, kedaluwarsa terdekat lebih dulu (dipakai lebih dulu, FEFO). */
export default function Batches({ batches }: { batches: Batch[] }) {
  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Batch dan kedaluwarsa" />
        <PageHeader
          title="Batch dan kedaluwarsa"
          back={{ href: '/stock', label: 'stok obat' }}
          description="Stok keluar mengambil batch yang paling cepat kedaluwarsa lebih dulu."
        />
        {batches.length === 0 ? (
          <EmptyState
            kind="pill"
            size="page"
            title="Belum ada batch bersisa"
            description="Batch muncul setelah barang diterima."
          />
        ) : (
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Batch bersisa</caption>
            <thead>
              <tr>
                <th scope="col">Obat</th>
                <th scope="col">Batch</th>
                <th scope="col">Kedaluwarsa</th>
                <th scope="col" className="text-right">
                  Sisa
                </th>
                <th scope="col" className="text-right">
                  Diterima
                </th>
                <th scope="col">Tanggal masuk</th>
                <th scope="col" className="text-right">
                  Harga beli
                </th>
                <th scope="col">Pemasok</th>
              </tr>
            </thead>
            <tbody>
              {batches.map((batch) => (
                <tr key={batch.id}>
                  <td>{batch.name}</td>
                  <td>{batch.batch_no ?? '-'}</td>
                  <td>{batch.expiry_date ? formatDate(batch.expiry_date) : '-'}</td>
                  <td className="text-right">
                    {batch.remaining_quantity} {batch.unit}
                  </td>
                  <td className="text-right">{batch.received_quantity}</td>
                  <td>{formatDate(batch.received_at)}</td>
                  <td className="text-right">
                    {batch.unit_cost_amount === null ? '-' : formatRupiah(batch.unit_cost_amount)}
                  </td>
                  <td>{batch.supplier_name ?? '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </main>
    </AppLayout>
  );
}
