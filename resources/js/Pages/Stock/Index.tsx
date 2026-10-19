import { Link } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { EmptyState } from '@/components/ui/EmptyState';
import { expiryText, stockLevel } from '@/features/pharmacy/schemas';
import { formatDate } from '@/lib/format';

interface MedicineRow {
  id: string;
  name: string;
  unit: string;
  quantity: number;
  min_stock: number;
  is_active: boolean;
}

interface LowRow {
  id: string;
  name: string;
  unit: string;
  quantity: number;
  min_stock: number;
}

interface ExpiringRow {
  medicine_id: string;
  name: string;
  unit: string;
  batch_no: string | null;
  expiry_date: string;
  days_left: number;
  received: number;
  quantity: number;
}

interface Props {
  medicines: MedicineRow[];
  alerts: { low: LowRow[]; expiring: ExpiringRow[]; window_days: number };
  can: { manage: boolean };
}

const LEVEL_LABEL = {
  empty: 'Habis',
  low: 'Di bawah minimum',
  ok: 'Cukup',
} as const;
const LEVEL_TONE = { empty: 'danger', low: 'warning', ok: 'success' } as const;

export default function Index({ medicines, alerts, can }: Props) {
  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Stok obat" />
        <PageHeader
          title="Stok obat dan bahan"
          actions={
            <>
              <Link href="/stock/batches" className="btn btn-secondary">
                Batch
              </Link>
              <Link href="/stock/purchase-orders" className="btn btn-secondary">
                Pembelian
              </Link>
              <Link href="/stock/counts" className="btn btn-secondary">
                Opname
              </Link>
              {can.manage ? (
                <>
                  <Link href="/settings/master/stock-opening/import" className="btn btn-secondary">
                    Impor stok awal
                  </Link>
                  <Link href="/stock/adjustments/create" className="btn btn-secondary">
                    Penyesuaian stok
                  </Link>
                  <Link href="/stock/movements/create" className="btn btn-primary">
                    Stok masuk atau keluar
                  </Link>
                </>
              ) : null}
            </>
          }
        />

        {alerts.low.length > 0 || alerts.expiring.length > 0 ? (
          <section aria-labelledby="perhatian" className="pane flex flex-col gap-2 p-3">
            <h2 id="perhatian">Perlu perhatian</h2>
            {alerts.low.length > 0 ? (
              <div>
                <h3 className="text-sm font-bold">Di bawah stok minimum ({alerts.low.length})</h3>
                <ul className="ml-4 list-disc text-sm">
                  {alerts.low.map((item) => (
                    <li key={item.id}>
                      {item.name}: {item.quantity} {item.unit} dari minimum {item.min_stock}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
            {alerts.expiring.length > 0 ? (
              <div>
                <h3 className="text-sm font-bold">
                  Kedaluwarsa dalam {alerts.window_days} hari atau sudah lewat (
                  {alerts.expiring.length})
                </h3>
                <ul className="ml-4 list-disc text-sm">
                  {alerts.expiring.map((item) => (
                    <li key={`${item.medicine_id}-${item.batch_no ?? ''}-${item.expiry_date}`}>
                      {item.name}
                      {item.batch_no ? `, batch ${item.batch_no}` : ''}:{' '}
                      {formatDate(item.expiry_date)} ({expiryText(item.days_left)}), sisa batch{' '}
                      {item.quantity} {item.unit}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </section>
        ) : null}

        {medicines.length === 0 ? (
          <EmptyState
            kind="pill"
            size="page"
            title="Belum ada obat di katalog"
            description="Tambahkan obat dan bahan di Pengaturan, Data master, atau impor stok awal dari Excel."
          />
        ) : (
          <table>
            <caption className="sr-only">Stok obat dan bahan</caption>
            <thead>
              <tr>
                <th scope="col">Obat</th>
                <th scope="col">Stok</th>
                <th scope="col">Minimum</th>
                <th scope="col">Status</th>
                <th scope="col">
                  <span className="sr-only">Aksi</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {medicines.map((medicine) => {
                const level = stockLevel(medicine.quantity, medicine.min_stock);
                return (
                  <tr key={medicine.id}>
                    <td className="font-medium">
                      {medicine.name}
                      {medicine.is_active ? '' : ' (nonaktif)'}
                    </td>
                    <td>
                      {medicine.quantity} {medicine.unit}
                    </td>
                    <td>{medicine.min_stock}</td>
                    <td>
                      <Badge tone={LEVEL_TONE[level]}>{LEVEL_LABEL[level]}</Badge>
                    </td>
                    <td className="text-right">
                      <Link
                        href={`/stock/${medicine.id}/history`}
                        className="btn btn-secondary"
                        aria-label={`Riwayat mutasi ${medicine.name}`}
                      >
                        Riwayat
                      </Link>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </main>
    </AppLayout>
  );
}
