import { Head, Link } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { stockLevel } from '@/features/pharmacy/schemas';

interface MedicineRow {
  id: string;
  name: string;
  unit: string;
  quantity: number;
  min_stock: number;
  is_active: boolean;
}

interface Props {
  medicines: MedicineRow[];
  can: { manage: boolean };
}

const LEVEL_LABEL = { empty: 'Habis', low: 'Di bawah minimum', ok: 'Cukup' } as const;
const LEVEL_TONE = { empty: 'danger', low: 'warning', ok: 'success' } as const;

export default function Index({ medicines, can }: Props) {
  return (
    <AppLayout>
      <main className="mx-auto flex max-w-5xl flex-col gap-8 p-6 lg:px-12 lg:py-10">
        <Head title="Stok obat" />
        <PageHeader
          title="Stok obat dan bahan"
          actions={
            can.manage ? (
              <>
                <Link href="/stock/adjustments/create" className="btn btn-secondary">
                  Penyesuaian stok
                </Link>
                <Link href="/stock/movements/create" className="btn btn-primary">
                  Stok masuk atau keluar
                </Link>
              </>
            ) : null
          }
        />

        {medicines.length === 0 ? (
          <p className="text-muted text-sm">Belum ada obat di katalog.</p>
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
