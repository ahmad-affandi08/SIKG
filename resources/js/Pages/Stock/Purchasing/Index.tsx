import { Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { PURCHASE_TONE } from '@/features/pharmacy/purchasing';
import { formatDate, formatRupiah } from '@/lib/format';

interface Row {
  id: string;
  po_no: string;
  supplier_name: string;
  status: string;
  status_label: string;
  expected_date: string | null;
  total_amount: number;
  item_count: number;
}

interface Props {
  orders: Row[];
  statuses: SelectOption[];
  filters: { status: string };
  can: { manage: boolean };
}

/** P2-05: daftar pesanan pembelian ke pemasok. */
export default function Index({ orders, statuses, filters, can }: Props) {
  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Pembelian" />
        <PageHeader
          title="Pembelian ke pemasok"
          back={{ href: '/stock', label: 'stok obat' }}
          actions={
            can.manage ? (
              <Link href="/stock/purchase-orders/create" className="btn btn-primary">
                Pesanan baru
              </Link>
            ) : null
          }
        />
        <div className="flex flex-wrap items-end gap-3">
          <Select
            label="Status"
            placeholder="Semua status"
            options={statuses}
            value={filters.status}
            onChange={(event) => {
              router.get(
                '/stock/purchase-orders',
                event.target.value === '' ? {} : { status: event.target.value },
                { preserveScroll: true, preserveState: true },
              );
            }}
          />
          <Link href="/settings/master/suppliers" className="btn btn-secondary">
            Pemasok
          </Link>
        </div>
        {orders.length === 0 ? (
          <p className="text-muted text-sm">Belum ada pesanan pembelian.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Daftar pesanan pembelian</caption>
            <thead>
              <tr>
                <th scope="col">Nomor</th>
                <th scope="col">Pemasok</th>
                <th scope="col">Status</th>
                <th scope="col">Perkiraan tiba</th>
                <th scope="col" className="text-right">
                  Jenis obat
                </th>
                <th scope="col" className="text-right">
                  Total
                </th>
              </tr>
            </thead>
            <tbody>
              {orders.map((order) => (
                <tr key={order.id}>
                  <td>
                    <Link href={`/stock/purchase-orders/${order.id}`} className="underline">
                      {order.po_no}
                    </Link>
                  </td>
                  <td>{order.supplier_name}</td>
                  <td>
                    <Badge tone={PURCHASE_TONE[order.status] ?? 'neutral'}>
                      {order.status_label}
                    </Badge>
                  </td>
                  <td>{order.expected_date ? formatDate(order.expected_date) : '-'}</td>
                  <td className="text-right">{order.item_count}</td>
                  <td className="text-right">{formatRupiah(order.total_amount)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </main>
    </AppLayout>
  );
}
