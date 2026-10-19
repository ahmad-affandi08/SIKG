import { Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Badge } from '@/components/ui/Badge';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { EmptyState } from '@/components/ui/EmptyState';
import { nextStatus, progressText } from '@/features/clinical/plan';
import type { Plan } from '@/features/clinical/plan';
import { formatDate, formatRupiah } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Props {
  patient: { id: string; name: string; medical_record_no: string };
  plans: Plan[];
  can: { manage: boolean };
  statuses: { value: string; label: string }[];
}

const TONE = {
  planned: 'neutral',
  in_progress: 'warning',
  done: 'success',
  cancelled: 'danger',
} as const;

/** RM-06: rencana perawatan pasien beserta estimasi biaya dan status tiap langkah. */
export default function Index({ patient, plans, can, statuses }: Props) {
  const timezone = useClinicTimezone();
  const label = (value: string) => statuses.find((s) => s.value === value)?.label ?? value;

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Rencana perawatan" />
        <PageHeader
          title="Rencana perawatan"
          back={{ href: `/patients/${patient.id}`, label: 'rekam pasien' }}
          description={`${patient.name} (${patient.medical_record_no})`}
          actions={
            can.manage ? (
              <Link
                href={`/patients/${patient.id}/treatment-plans/create`}
                className="btn btn-primary"
              >
                Buat rencana
              </Link>
            ) : null
          }
        />

        {plans.length === 0 ? (
          <EmptyState
            kind="tooth"
            size="page"
            title="Belum ada rencana perawatan"
            description="Rencana multi-kunjungan untuk pasien ini akan tampil di sini."
          />
        ) : null}

        {plans.map((plan) => (
          <section key={plan.id} className="pane" aria-label={plan.title}>
            <h2 className="pane-head flex flex-wrap items-center justify-between gap-2">
              <span>{plan.title}</span>
              <Badge tone={TONE[plan.status]}>{plan.status_label}</Badge>
            </h2>
            <div className="flex flex-col gap-2 p-2">
              <p className="text-muted text-sm">
                Dibuat {formatDate(plan.created_at, timezone)} | {progressText(plan.items)} |
                Estimasi {formatRupiah(plan.estimate_amount)} | Sisa{' '}
                {formatRupiah(plan.remaining_amount)}
              </p>
              {plan.notes ? <p className="text-sm">{plan.notes}</p> : null}
              {plan.items.length === 0 ? (
                <EmptyState kind="tooth" size="compact" title="Belum ada langkah" />
              ) : (
                <table>
                  <caption className="sr-only">Langkah rencana {plan.title}</caption>
                  <thead>
                    <tr>
                      <th scope="col">No</th>
                      <th scope="col">Tindakan</th>
                      <th scope="col">Gigi</th>
                      <th scope="col">Jml</th>
                      <th scope="col" className="text-right">
                        Estimasi
                      </th>
                      <th scope="col">Status</th>
                      <th scope="col">
                        <span className="sr-only">Aksi</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {plan.items.map((item) => {
                      const next = nextStatus(item.status);
                      return (
                        <tr key={item.id}>
                          <td>{item.sequence}</td>
                          <td>
                            <div className="font-medium">{item.code}</div>
                            <div className="text-muted">{item.name}</div>
                            {item.notes ? <div className="text-muted">{item.notes}</div> : null}
                          </td>
                          <td>{item.tooth_fdi ?? '-'}</td>
                          <td>{item.quantity}</td>
                          <td className="text-right tabular-nums">
                            {formatRupiah(item.estimate_amount)}
                          </td>
                          <td>
                            <Badge
                              tone={
                                item.status === 'done'
                                  ? 'success'
                                  : item.status === 'in_progress'
                                    ? 'warning'
                                    : 'neutral'
                              }
                            >
                              {label(item.status)}
                            </Badge>
                            {item.completed_at ? (
                              <div className="text-muted text-xs">
                                {formatDate(item.completed_at, timezone)}
                              </div>
                            ) : null}
                          </td>
                          <td>
                            {can.manage && plan.status !== 'cancelled' ? (
                              <div className="flex flex-wrap gap-2">
                                {next ? (
                                  <button
                                    type="button"
                                    className="btn btn-secondary"
                                    onClick={() => {
                                      router.post(
                                        `/treatment-plans/${plan.id}/items/${item.id}/status`,
                                        { status: next },
                                        { preserveScroll: true },
                                      );
                                    }}
                                  >
                                    {next === 'in_progress' ? 'Mulai' : 'Tandai selesai'}
                                  </button>
                                ) : null}
                                {item.status === 'planned' ? (
                                  <ConfirmAction
                                    label={`Hapus ${item.code}`}
                                    message="Langkah dihapus dari rencana. Estimasi biaya rencana ikut berkurang."
                                    confirmLabel="Ya, hapus"
                                    onConfirm={() => {
                                      router.delete(
                                        `/treatment-plans/${plan.id}/items/${item.id}`,
                                        { preserveScroll: true },
                                      );
                                    }}
                                  />
                                ) : null}
                              </div>
                            ) : null}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              )}
              {can.manage && plan.status !== 'cancelled' ? (
                <div className="flex flex-wrap gap-2">
                  <Link
                    href={`/treatment-plans/${plan.id}/items/create`}
                    className="btn btn-secondary"
                  >
                    Tambah langkah
                  </Link>
                  {plan.status !== 'done' ? (
                    <ConfirmAction
                      label="Batalkan rencana"
                      message="Rencana ditandai Dibatalkan dan tidak muncul lagi di layar pemeriksaan. Riwayatnya tetap tersimpan."
                      confirmLabel="Ya, batalkan"
                      onConfirm={() => {
                        router.post(
                          `/treatment-plans/${plan.id}/cancel`,
                          {},
                          { preserveScroll: true },
                        );
                      }}
                    />
                  ) : null}
                </div>
              ) : null}
            </div>
          </section>
        ))}
      </main>
    </AppLayout>
  );
}
