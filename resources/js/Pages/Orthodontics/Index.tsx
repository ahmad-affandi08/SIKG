import { Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { EmptyState } from '@/components/ui/EmptyState';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { formatDate, formatRupiah } from '@/lib/format';

interface Row {
  id: string;
  patient_name: string;
  medical_record_no: string;
  doctor_name: string;
  title: string;
  appliance_label: string;
  status: string;
  status_label: string;
  start_date: string;
  last_control_date: string | null;
  next_control_date: string | null;
  control_overdue: boolean;
  unbilled_amount: number;
  total_fee_amount: number;
}

interface Props {
  cases: Row[];
  statuses: SelectOption[];
  filters: { status: string };
}

const TONE: Record<string, 'success' | 'neutral' | 'warning'> = {
  active: 'warning',
  completed: 'success',
  stopped: 'neutral',
};

/** P2-04: daftar paket ortodonti dengan jadwal kontrol berikutnya. */
export default function Index({ cases, statuses, filters }: Props) {
  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Ortodonti" />
        <PageHeader
          title="Ortodonti"
          description="Paket perawatan behel dan aligner, kontrol bulanan, dan cicilan. Buat paket baru dari halaman pasien."
        />
        <div className="flex flex-wrap items-end gap-3">
          <Select
            label="Status"
            placeholder="Semua status"
            options={statuses}
            value={filters.status}
            onChange={(event) => {
              router.get(
                '/orthodontics',
                event.target.value ? { status: event.target.value } : {},
                { preserveState: true },
              );
            }}
          />
        </div>

        {cases.length === 0 ? (
          <EmptyState
            kind="tooth"
            size="page"
            title="Belum ada paket ortodonti"
            description="Paket perawatan ortodonti pasien akan tampil di sini."
          />
        ) : (
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Daftar paket ortodonti</caption>
            <thead>
              <tr>
                <th scope="col">Pasien</th>
                <th scope="col">Paket</th>
                <th scope="col">Dokter</th>
                <th scope="col">Status</th>
                <th scope="col">Mulai</th>
                <th scope="col">Kontrol berikutnya</th>
                <th scope="col" className="text-right">
                  Cicilan belum ditagih
                </th>
              </tr>
            </thead>
            <tbody>
              {cases.map((c) => (
                <tr key={c.id}>
                  <td>
                    {c.patient_name} ({c.medical_record_no})
                  </td>
                  <td>
                    <Link href={`/orthodontics/cases/${c.id}`} className="underline">
                      {c.title}
                    </Link>
                    <div className="text-muted">{c.appliance_label}</div>
                  </td>
                  <td>{c.doctor_name}</td>
                  <td>
                    <Badge tone={TONE[c.status] ?? 'neutral'}>{c.status_label}</Badge>
                  </td>
                  <td>{formatDate(c.start_date)}</td>
                  <td>
                    {c.next_control_date ? formatDate(c.next_control_date) : '-'}
                    {c.control_overdue ? <div className="text-danger-600">Terlambat</div> : null}
                  </td>
                  <td className="text-right">{formatRupiah(c.unbilled_amount)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </main>
    </AppLayout>
  );
}
