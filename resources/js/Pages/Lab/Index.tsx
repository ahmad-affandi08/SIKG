import { Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { LAB_TONE } from '@/features/lab/schemas';
import { formatDate, formatRupiah } from '@/lib/format';

interface Row {
  id: string;
  order_no: string;
  patient_name: string;
  medical_record_no: string;
  doctor_name: string;
  laboratory_name: string;
  work_type_label: string;
  status: string;
  status_label: string;
  due_date: string | null;
  overdue: boolean;
  lab_cost_amount: number;
  patient_charge_amount: number;
  billed: boolean;
}

interface Props {
  orders: Row[];
  statuses: SelectOption[];
  filters: { status: string };
}

/** P2-06: daftar pesanan pekerjaan ke laboratorium gigi. */
export default function Index({ orders, statuses, filters }: Props) {
  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Pesanan lab" />
        <PageHeader
          title="Pesanan lab gigi"
          description="Buat pesanan dari halaman pasien. Pekerjaan yang sudah diterima dapat ditagihkan ke pasien."
        />
        <div className="flex flex-wrap items-end gap-3">
          <Select
            label="Status"
            placeholder="Semua status"
            options={statuses}
            value={filters.status}
            onChange={(event) => {
              router.get(
                '/lab-orders',
                event.target.value === '' ? {} : { status: event.target.value },
                { preserveScroll: true, preserveState: true },
              );
            }}
          />
          <Link href="/settings/master/laboratories" className="btn btn-secondary">
            Laboratorium
          </Link>
        </div>
        {orders.length === 0 ? (
          <p className="text-muted text-sm">Belum ada pesanan lab.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Daftar pesanan lab</caption>
            <thead>
              <tr>
                <th scope="col">Nomor</th>
                <th scope="col">Pasien</th>
                <th scope="col">Pekerjaan</th>
                <th scope="col">Laboratorium</th>
                <th scope="col">Dokter</th>
                <th scope="col">Status</th>
                <th scope="col">Target</th>
                <th scope="col" className="text-right">
                  Biaya lab
                </th>
                <th scope="col" className="text-right">
                  Harga pasien
                </th>
              </tr>
            </thead>
            <tbody>
              {orders.map((order) => (
                <tr key={order.id}>
                  <td>
                    <Link href={`/lab-orders/${order.id}`} className="underline">
                      {order.order_no}
                    </Link>
                  </td>
                  <td>
                    {order.patient_name} ({order.medical_record_no})
                  </td>
                  <td>{order.work_type_label}</td>
                  <td>{order.laboratory_name}</td>
                  <td>{order.doctor_name}</td>
                  <td>
                    <Badge tone={LAB_TONE[order.status] ?? 'neutral'}>{order.status_label}</Badge>
                    {order.billed ? ' (sudah ditagih)' : ''}
                  </td>
                  <td>
                    {order.due_date ? formatDate(order.due_date) : '-'}
                    {order.overdue ? ' (lewat)' : ''}
                  </td>
                  <td className="text-right">{formatRupiah(order.lab_cost_amount)}</td>
                  <td className="text-right">{formatRupiah(order.patient_charge_amount)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </main>
    </AppLayout>
  );
}
