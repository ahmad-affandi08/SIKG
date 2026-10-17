import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { PatientPicker } from '@/features/patient/components/PatientPicker';
import { useAvailability } from '@/features/scheduling/queries';
import {
  appointmentFormSchema,
  cancelSchema,
  DURATIONS,
  rescheduleSchema,
} from '@/features/scheduling/schemas';
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

function collectErrors(issues: readonly { path: PropertyKey[]; message: string }[]) {
  const next: Record<string, string> = {};
  for (const issue of issues) {
    next[String(issue.path[0])] ??= issue.message;
  }
  return next;
}

const DURATION_OPTIONS = DURATIONS.map((d) => ({ value: String(d), label: `${String(d)} menit` }));

export default function Index({ filters, today, calendar, doctors, chairs, canManage }: Props) {
  const [creating, setCreating] = useState(false);
  const [rescheduling, setRescheduling] = useState<Item | null>(null);

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
        <h1>Jadwal dan janji</h1>

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

        {canManage && !creating ? (
          <div>
            <Button
              onClick={() => {
                setCreating(true);
                setRescheduling(null);
              }}
            >
              Buat janji
            </Button>
          </div>
        ) : null}
        {creating ? (
          <AppointmentForm
            defaultDate={filters.date}
            doctors={doctors}
            chairs={chairs}
            onClose={() => {
              setCreating(false);
            }}
          />
        ) : null}
        {rescheduling ? (
          <RescheduleForm
            item={rescheduling}
            doctors={doctors}
            chairs={chairs}
            onClose={() => {
              setRescheduling(null);
            }}
          />
        ) : null}

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
                      {canManage ? (
                        <ItemActions
                          item={item}
                          onReschedule={() => {
                            setRescheduling(item);
                            setCreating(false);
                          }}
                        />
                      ) : null}
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

function ItemActions({ item, onReschedule }: { item: Item; onReschedule: () => void }) {
  const [reason, setReason] = useState('');
  const [cancelling, setCancelling] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const can = (status: string) => item.next.includes(status);
  const post = (path: string) => {
    router.post(`/appointments/${item.id}/${path}`, {}, { preserveScroll: true });
  };

  function cancel(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = cancelSchema.safeParse({ reason });
    if (!result.success) {
      setError(result.error.issues[0]?.message ?? 'Alasan wajib diisi.');
      return;
    }
    setError(null);
    router.post(
      `/appointments/${item.id}/cancel`,
      { reason },
      {
        preserveScroll: true,
        onSuccess: () => {
          setCancelling(false);
        },
      },
    );
  }

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
        <Button variant="secondary" onClick={onReschedule}>
          Jadwal ulang
        </Button>
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
      {can('cancelled') && !cancelling ? (
        <Button
          variant="secondary"
          onClick={() => {
            setCancelling(true);
          }}
        >
          Batalkan
        </Button>
      ) : null}
      {cancelling ? (
        <form
          onSubmit={cancel}
          noValidate
          className="flex flex-wrap items-end gap-2"
          aria-label="Pembatalan janji"
        >
          <TextField
            label="Alasan pembatalan"
            value={reason}
            onChange={(event) => {
              setReason(event.target.value);
            }}
            error={error ?? undefined}
          />
          <Button type="submit" variant="danger">
            Ya, batalkan
          </Button>
          <Button
            variant="secondary"
            onClick={() => {
              setCancelling(false);
            }}
          >
            Kembali
          </Button>
        </form>
      ) : null}
    </div>
  );
}

function SlotPicker({
  doctor,
  date,
  minutes,
  chair,
  time,
  onPick,
}: {
  doctor: string;
  date: string;
  minutes: number;
  chair: string;
  time: string;
  onPick: (time: string) => void;
}) {
  const ready = doctor !== '' && date !== '' && minutes >= 10;
  const slots = useAvailability({ doctor, date, minutes, chair }, ready);

  if (!ready) {
    return null;
  }
  return (
    <div className="flex flex-col gap-1">
      <span className="text-sm font-medium">Jam kosong</span>
      {slots.isSuccess && slots.data.length === 0 ? (
        <p className="text-muted text-sm">Tidak ada jam kosong pada tanggal itu.</p>
      ) : null}
      <div className="flex flex-wrap gap-2">
        {(slots.data ?? []).map((slot) => (
          <button
            key={slot}
            type="button"
            aria-pressed={slot === time}
            onClick={() => {
              onPick(slot);
            }}
            className="btn btn-secondary"
          >
            {slot}
          </button>
        ))}
      </div>
    </div>
  );
}

function AppointmentForm({
  defaultDate,
  doctors,
  chairs,
  onClose,
}: {
  defaultDate: string;
  doctors: SelectOption[];
  chairs: SelectOption[];
  onClose: () => void;
}) {
  const [patient, setPatient] = useState<{ id: string; name: string } | null>(null);
  const [values, setValues] = useState({
    doctor_id: '',
    chair_id: '',
    date: defaultDate,
    time: '',
    minutes: '30',
    complaint: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const set = (key: keyof typeof values, value: string) => {
    setValues((v) => ({ ...v, [key]: value }));
  };

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const payload = { patient_id: patient?.id ?? '', ...values };
    const result = appointmentFormSchema.safeParse(payload);
    if (!result.success) {
      setErrors(collectErrors(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      '/appointments',
      { ...payload, minutes: result.data.minutes },
      {
        preserveScroll: true,
        onSuccess: onClose,
        onError: (serverErrors) => {
          setErrors(serverErrors);
        },
        onFinish: () => {
          setSaving(false);
        },
      },
    );
  }

  return (
    <form
      onSubmit={submit}
      noValidate
      aria-label="Buat janji"
      className="card flex max-w-xl flex-col gap-3 p-4"
    >
      <h2>Buat janji</h2>
      <PatientPicker selected={patient} onSelect={setPatient} error={errors.patient_id} />
      <Select
        label="Dokter"
        placeholder="Pilih dokter"
        options={doctors}
        value={values.doctor_id}
        onChange={(event) => {
          set('doctor_id', event.target.value);
        }}
        error={errors.doctor_id}
      />
      <Select
        label="Kursi (opsional)"
        placeholder="Belum ditentukan"
        options={chairs}
        value={values.chair_id}
        onChange={(event) => {
          set('chair_id', event.target.value);
        }}
      />
      <TextField
        label="Tanggal"
        type="date"
        value={values.date}
        onChange={(event) => {
          set('date', event.target.value);
        }}
        error={errors.date}
      />
      <Select
        label="Durasi"
        options={DURATION_OPTIONS}
        value={values.minutes}
        onChange={(event) => {
          set('minutes', event.target.value);
        }}
        error={errors.minutes}
      />
      <SlotPicker
        doctor={values.doctor_id}
        date={values.date}
        minutes={Number(values.minutes)}
        chair={values.chair_id}
        time={values.time}
        onPick={(t) => {
          set('time', t);
        }}
      />
      <TextField
        label="Jam mulai (JJ:MM)"
        value={values.time}
        onChange={(event) => {
          set('time', event.target.value);
        }}
        error={errors.time}
      />
      <TextField
        label="Keluhan"
        value={values.complaint}
        onChange={(event) => {
          set('complaint', event.target.value);
        }}
        error={errors.complaint}
      />
      {errors.domain ? (
        <p role="alert" className="text-danger-600 text-sm">
          {errors.domain}
        </p>
      ) : null}
      <div className="flex gap-3">
        <Button type="submit" loading={saving}>
          Simpan janji
        </Button>
        <Button variant="secondary" onClick={onClose}>
          Batal
        </Button>
      </div>
    </form>
  );
}

function RescheduleForm({
  item,
  doctors,
  chairs,
  onClose,
}: {
  item: Item;
  doctors: SelectOption[];
  chairs: SelectOption[];
  onClose: () => void;
}) {
  const [values, setValues] = useState({
    doctor_id: item.doctor_id,
    chair_id: item.chair_id ?? '',
    date: item.date,
    time: item.start,
    minutes: String(item.minutes),
    reason: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const set = (key: keyof typeof values, value: string) => {
    setValues((v) => ({ ...v, [key]: value }));
  };

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = rescheduleSchema.safeParse(values);
    if (!result.success) {
      setErrors(collectErrors(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.put(
      `/appointments/${item.id}`,
      { ...values, minutes: result.data.minutes },
      {
        preserveScroll: true,
        onSuccess: onClose,
        onError: (serverErrors) => {
          setErrors(serverErrors);
        },
        onFinish: () => {
          setSaving(false);
        },
      },
    );
  }

  return (
    <form
      onSubmit={submit}
      noValidate
      aria-label="Jadwal ulang"
      className="card flex max-w-xl flex-col gap-3 p-4"
    >
      <h2>Jadwal ulang {item.patient_name ?? ''}</h2>
      <Select
        label="Dokter"
        options={doctors}
        value={values.doctor_id}
        onChange={(event) => {
          set('doctor_id', event.target.value);
        }}
        error={errors.doctor_id}
      />
      <Select
        label="Kursi (opsional)"
        placeholder="Belum ditentukan"
        options={chairs}
        value={values.chair_id}
        onChange={(event) => {
          set('chair_id', event.target.value);
        }}
      />
      <TextField
        label="Tanggal"
        type="date"
        value={values.date}
        onChange={(event) => {
          set('date', event.target.value);
        }}
        error={errors.date}
      />
      <Select
        label="Durasi"
        options={DURATION_OPTIONS}
        value={values.minutes}
        onChange={(event) => {
          set('minutes', event.target.value);
        }}
        error={errors.minutes}
      />
      <SlotPicker
        doctor={values.doctor_id}
        date={values.date}
        minutes={Number(values.minutes)}
        chair={values.chair_id}
        time={values.time}
        onPick={(t) => {
          set('time', t);
        }}
      />
      <TextField
        label="Jam mulai (JJ:MM)"
        value={values.time}
        onChange={(event) => {
          set('time', event.target.value);
        }}
        error={errors.time}
      />
      <TextField
        label="Alasan jadwal ulang"
        value={values.reason}
        onChange={(event) => {
          set('reason', event.target.value);
        }}
        error={errors.reason}
      />
      {errors.domain ? (
        <p role="alert" className="text-danger-600 text-sm">
          {errors.domain}
        </p>
      ) : null}
      <div className="flex gap-3">
        <Button type="submit" loading={saving}>
          Simpan jadwal ulang
        </Button>
        <Button variant="secondary" onClick={onClose}>
          Batal
        </Button>
      </div>
    </form>
  );
}
