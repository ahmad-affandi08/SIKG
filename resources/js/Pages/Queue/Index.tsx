import { Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useCan } from '@/lib/permissions';
import { useQueue } from '@/features/scheduling/queries';
import { statusTone } from '@/features/scheduling/status';

interface Props {
  canManage: boolean;
}

export default function Index({ canManage }: Props) {
  const queue = useQueue();
  const can = useCan();

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Antrean" />
        <PageHeader
          title="Antrean hari ini"
          actions={
            canManage ? (
              <Link href="/queue/walk-in" className="btn btn-primary">
                Pasien tanpa janji
              </Link>
            ) : null
          }
        />

        {queue.isError ? (
          <p role="alert" className="text-danger-600 text-sm">
            Antrean tidak dapat dimuat. Mencoba lagi otomatis.
          </p>
        ) : null}

        <section aria-label="Daftar antrean" aria-live="polite">
          {queue.data && queue.data.length > 0 ? (
            <table className="w-full text-left text-sm">
              <caption className="sr-only">Antrean pasien hari ini</caption>
              <thead>
                <tr>
                  <th scope="col" className="py-2">
                    No.
                  </th>
                  <th scope="col">Pasien</th>
                  <th scope="col">Dokter</th>
                  <th scope="col">Kursi</th>
                  <th scope="col">Keluhan</th>
                  <th scope="col">Status</th>
                  <th scope="col">
                    <span className="sr-only">Aksi</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {queue.data.map((item) => (
                  <tr key={item.id} className="align-top">
                    <td className="py-2 pr-3 text-lg font-semibold">{item.queue_no}</td>
                    <td className="pr-3">
                      <div className="font-medium">{item.patient_name ?? '-'}</div>
                      <div className="text-muted">{item.medical_record_no}</div>
                    </td>
                    <td className="pr-3">{item.doctor_name ?? '-'}</td>
                    <td className="pr-3">{item.chair_name ?? '-'}</td>
                    <td className="pr-3">{item.complaint ?? '-'}</td>
                    <td className="pr-3">
                      <Badge tone={statusTone(item.status)}>{item.status_label}</Badge>
                    </td>
                    <td>
                      {can('encounter.create') &&
                      ['checked_in', 'in_progress', 'completed'].includes(item.status) ? (
                        <Button
                          variant="secondary"
                          onClick={() => {
                            router.post('/encounters', { appointment_id: item.id });
                          }}
                        >
                          Buka pemeriksaan
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : queue.isSuccess ? (
            <p className="text-muted text-sm">Belum ada pasien dalam antrean.</p>
          ) : null}
          <p className="text-muted mt-2 text-xs">Diperbarui otomatis setiap 10 detik.</p>
        </section>
      </main>
    </AppLayout>
  );
}
