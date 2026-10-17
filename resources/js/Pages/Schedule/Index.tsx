import { Head, Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { statusTone } from '@/features/scheduling/status';
import { formatDate } from '@/lib/format';

interface Item {
  id: string;
  date: string;
  start: string;
  end: string;
  minutes: number;
  status: string;
  status_label: string;
  queue_no: number | null;
  complaint: string | null;
  cancel_reason: string | null;
  patient_name: string | null;
  medical_record_no: string | null;
  doctor_id: string;
  doctor_name: string | null;
  chair_id: string | null;
  chair_name: string | null;
  next: string[];
}

interface Props {
  filters: { date: string; view: 'day' | 'week'; doctor: string | null; chair: string | null };
  today: string;
  calendar: { from: string; to: string; items: Item[] };
  doctors: SelectOption[];
  chairs: SelectOption[];
  canManage: boolean;
}

function days(from: string, to: string): string[] {
  const out: string[] = [];
  const cursor = new Date(`${from}T00:00:00Z`);
  const end = new Date(`${to}T00:00:00Z`);
  while (cursor <= end) {
    out.push(cursor.toISOString().slice(0, 10));
    cursor.setUTCDate(cursor.getUTCDate() + 1);
  }
  return out;
}

function shift(date: string, delta: number): string {
  const d = new Date(`${date}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + delta);
  return d.toISOString().slice(0, 10);
}

export default function Index({ filters, today, calendar, doctors, chairs, canManage }: Props) {
  function go(patch: Partial<{ date: string; view: string; doctor: string; chair: string }>) {
    const next = {
      date: filters.date,
      view: filters.view,
      doctor: filters.doctor ?? '',
      chair: filters.chair ?? '',
      ...patch,
    };
    router.get('/schedule', Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '')), {
      preserveScroll: true,
      preserveState: true,
    });
  }

  const step = filters.view === 'week' ? 7 : 1;

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-6xl flex-col gap-4 p-6 lg:px-12 lg:py-10">
        <Head title="Jadwal" />
        <PageHeader
          title="Jadwal dan janji"
          actions={
            canManage ? (
              <Link href={`/appointments/create?date=${filters.date}`} className="btn btn-primary">
                Buat janji
              </Link>
            ) : null
          }
        />

        <form
          className="flex flex-wrap items-end gap-3"
          aria-label="Penyaring kalender"
          onSubmit={(event) => {
            event.preventDefault();
          }}
        >
          <TextField
            label="Tanggal"
            type="date"
            value={filters.date}
            onChange={(event) => {
              if (event.target.value) {
                go({ date: event.target.value });
              }
            }}
          />
          <Select
            label="Tampilan"
            options={[
              { value: 'day', label: 'Harian' },
              { value: 'week', label: 'Mingguan' },
            ]}
            value={filters.view}
            onChange={(event) => {
              go({ view: event.target.value });
            }}
          />
          <Select
            label="Dokter"
            placeholder="Semua dokter"
            options={doctors}
            value={filters.doctor ?? ''}
            onChange={(event) => {
              go({ doctor: event.target.value });
            }}
          />
          <Select
            label="Kursi"
            placeholder="Semua kursi"
            options={chairs}
            value={filters.chair ?? ''}
            onChange={(event) => {
              go({ chair: event.target.value });
            }}
          />
          <Button
            variant="secondary"
            onClick={() => {
              go({ date: shift(filters.date, -step) });
            }}
          >
            Sebelumnya
          </Button>
          <Button
            variant="secondary"
            onClick={() => {
              go({ date: today });
            }}
          >
            Hari ini
          </Button>
          <Button
            variant="secondary"
            onClick={() => {
              go({ date: shift(filters.date, step) });
            }}
          >
            Berikutnya
          </Button>
        </form>

        {days(calendar.from, calendar.to).map((day) => {
          const items = calendar.items.filter((item) => item.date === day);
          return (
            <section
              key={day}
              aria-label={`Janji ${formatDate(day)}`}
              className="flex flex-col gap-2"
            >
              <h2>
                {formatDate(day)}
                {day === today ? ' (hari ini)' : ''}
              </h2>
              {items.length === 0 ? (
                <p className="text-muted text-sm">Tidak ada janji.</p>
              ) : (
                <ul className="flex flex-col gap-2">
                  {items.map((item) => (
                    <li key={item.id} className="card flex flex-col gap-2 p-3">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-semibold">
                          {item.start} - {item.end}
                        </span>
                        <span className="font-medium">{item.patient_name ?? '-'}</span>
                        <span className="text-muted text-sm">{item.medical_record_no}</span>
                        <Badge tone={statusTone(item.status)}>{item.status_label}</Badge>
                        {item.queue_no ? <Badge>{`Antrean ${String(item.queue_no)}`}</Badge> : null}
                      </div>
                      <div className="text-muted text-sm">
                        {item.doctor_name ?? '-'} | {item.chair_name ?? 'Kursi belum ditentukan'}
                        {item.complaint ? ` | ${item.complaint}` : ''}
                        {item.cancel_reason ? ` | Alasan batal: ${item.cancel_reason}` : ''}
                      </div>
                      {canManage ? <ItemActions item={item} /> : null}
                    </li>
                  ))}
                </ul>
              )}
            </section>
          );
        })}
      </main>
    </AppLayout>
  );
}

function ItemActions({ item }: { item: Item }) {
  const can = (status: string) => item.next.includes(status);
  const post = (path: string) => {
    router.post(`/appointments/${item.id}/${path}`, {}, { preserveScroll: true });
  };

  return (
    <div className="flex flex-wrap items-start gap-2">
      {can('confirmed') ? (
        <Button
          variant="secondary"
          onClick={() => {
            post('confirm');
          }}
        >
          Konfirmasi
        </Button>
      ) : null}
      {can('checked_in') ? (
        <Button
          onClick={() => {
            post('check-in');
          }}
        >
          Check-in
        </Button>
      ) : null}
      {item.status === 'scheduled' || item.status === 'confirmed' ? (
        <Link href={`/appointments/${item.id}/edit`} className="btn btn-secondary">
          Jadwal ulang
        </Link>
      ) : null}
      {can('no_show') ? (
        <ConfirmAction
          label="Tidak datang"
          message="Pasien dicatat tidak datang dan slot dilepas."
          confirmLabel="Ya, tidak datang"
          onConfirm={() => {
            post('no-show');
          }}
        />
      ) : null}
      {can('cancelled') ? (
        <Link href={`/appointments/${item.id}/cancel`} className="btn btn-secondary">
          Batalkan
        </Link>
      ) : null}
    </div>
  );
}
