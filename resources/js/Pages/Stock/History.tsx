import { Link } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { formatDateTime } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface MovementRow {
  id: string;
  type: string;
  type_label: string;
  quantity: number;
  balance_after: number;
  reason: string | null;
  batch_no: string | null;
  expiry_date: string | null;
  created_at: string;
}

interface Props {
  medicine: { id: string; name: string; unit: string; quantity: number };
  movements: MovementRow[];
  can: { manage: boolean };
}

export default function History({ medicine, movements, can }: Props) {
  const timezone = useClinicTimezone();

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title={`Riwayat stok ${medicine.name}`} />
        <PageHeader
          title={medicine.name}
          back={{ href: '/stock', label: 'stok obat' }}
          description={`Stok saat ini ${String(medicine.quantity)} ${medicine.unit}. Riwayat mutasi terbaru.`}
          actions={
            can.manage ? (
              <>
                <Link
                  href={`/stock/adjustments/create?medicine=${medicine.id}`}
                  className="btn btn-secondary"
                >
                  Penyesuaian stok
                </Link>
                <Link
                  href={`/stock/movements/create?medicine=${medicine.id}`}
                  className="btn btn-primary"
                >
                  Stok masuk atau keluar
                </Link>
              </>
            ) : null
          }
        />

        {movements.length === 0 ? (
          <p className="text-muted text-sm">Belum ada mutasi.</p>
        ) : (
          <table>
            <caption className="sr-only">Riwayat mutasi stok {medicine.name}</caption>
            <thead>
              <tr>
                <th scope="col">Waktu</th>
                <th scope="col">Jenis</th>
                <th scope="col">Jumlah</th>
                <th scope="col">Saldo</th>
                <th scope="col">Batch</th>
                <th scope="col">Alasan</th>
              </tr>
            </thead>
            <tbody>
              {movements.map((m) => (
                <tr key={m.id}>
                  <td>{formatDateTime(m.created_at, timezone)}</td>
                  <td>{m.type_label}</td>
                  <td>{m.quantity > 0 ? `+${String(m.quantity)}` : m.quantity}</td>
                  <td>{m.balance_after}</td>
                  <td>
                    {m.batch_no ?? '-'}
                    {m.expiry_date ? ` (exp ${m.expiry_date})` : ''}
                  </td>
                  <td>{m.reason ?? '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </main>
    </AppLayout>
  );
}
