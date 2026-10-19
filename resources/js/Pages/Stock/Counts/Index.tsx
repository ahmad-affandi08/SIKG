import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { TextField } from '@/components/ui/TextField';
import { scheduleSchema } from '@/features/pharmacy/purchasing';
import { formatDate } from '@/lib/format';

interface Session {
  id: string;
  scheduled_for: string;
  status: string;
  status_label: string;
  source_label: string;
  note: string | null;
}

interface Props {
  schedule: {
    enabled: boolean;
    interval_days: number;
    next_due_date: string | null;
  };
  open: Session | null;
  sessions: Session[];
  can: { manage: boolean };
}

const TONE: Record<string, 'neutral' | 'success' | 'warning'> = {
  open: 'warning',
  completed: 'success',
  cancelled: 'neutral',
};

/** P2-05: jadwal opname dan daftar sesi hitung stok. */
export default function Index({ schedule, open, sessions, can }: Props) {
  const form = useForm({
    enabled: schedule.enabled,
    interval_days: String(schedule.interval_days),
  });
  const [error, setError] = useState<string | undefined>();

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = scheduleSchema.safeParse(form.data);
    if (!result.success) {
      setError(result.error.issues[0]?.message);
      return;
    }
    setError(undefined);
    form.post('/stock/counts/schedule', { preserveScroll: true });
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-4 p-3">
        <PageHead title="Opname stok" />
        <PageHeader
          title="Opname stok"
          back={{ href: '/stock', label: 'stok obat' }}
          actions={
            can.manage && open === null ? (
              <Button
                onClick={() => {
                  router.post('/stock/counts', {});
                }}
              >
                Mulai opname
              </Button>
            ) : null
          }
        />
        {open ? (
          <p className="text-sm">
            Ada sesi opname terbuka sejak {formatDate(open.scheduled_for)}.{' '}
            <Link href={`/stock/counts/${open.id}`} className="underline">
              Lanjutkan penghitungan
            </Link>
          </p>
        ) : null}

        {can.manage ? (
          <form
            onSubmit={submit}
            noValidate
            aria-label="Jadwal opname"
            className="form-grid max-w-md"
          >
            <h2>Jadwal opname</h2>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.data.enabled}
                onChange={(event) => {
                  form.setData('enabled', event.target.checked);
                }}
              />
              Buka sesi opname otomatis sesuai interval
            </label>
            <TextField
              label="Interval (hari)"
              inputMode="numeric"
              value={form.data.interval_days}
              onChange={(event) => {
                form.setData('interval_days', event.target.value);
              }}
              error={error ?? form.errors.interval_days}
              required
            />
            <p className="text-muted text-xs">
              {schedule.enabled && schedule.next_due_date
                ? `Sesi berikutnya jatuh tempo ${formatDate(schedule.next_due_date)}. Staf stok diberi tahu saat sesi dibuka.`
                : 'Jadwal belum aktif.'}
            </p>
            <div>
              <Button type="submit" loading={form.processing}>
                Simpan jadwal
              </Button>
            </div>
          </form>
        ) : null}

        <section aria-labelledby="riwayat-opname">
          <h2 id="riwayat-opname">Riwayat sesi</h2>
          {sessions.length === 0 ? (
            <EmptyState
              kind="folder"
              size="page"
              title="Belum ada sesi opname"
              description="Mulai sesi opname untuk mencocokkan stok fisik dengan catatan."
            />
          ) : (
            <table className="w-full text-left text-sm">
              <caption className="sr-only">Sesi opname</caption>
              <thead>
                <tr>
                  <th scope="col">Tanggal</th>
                  <th scope="col">Sumber</th>
                  <th scope="col">Status</th>
                  <th scope="col">Catatan</th>
                </tr>
              </thead>
              <tbody>
                {sessions.map((session) => (
                  <tr key={session.id}>
                    <td>
                      <Link href={`/stock/counts/${session.id}`} className="underline">
                        {formatDate(session.scheduled_for)}
                      </Link>
                    </td>
                    <td>{session.source_label}</td>
                    <td>
                      <Badge tone={TONE[session.status] ?? 'neutral'}>{session.status_label}</Badge>
                    </td>
                    <td>{session.note ?? '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
      </main>
    </AppLayout>
  );
}
