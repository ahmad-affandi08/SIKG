import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { DatePicker } from '@/components/ui/DatePicker';
import { TimePicker } from '@/components/ui/TimePicker';
import { SlotPicker } from '@/features/scheduling/components/SlotPicker';
import { collectErrors, DURATION_OPTIONS } from '@/features/scheduling/form';
import { rescheduleSchema } from '@/features/scheduling/schemas';
import { todayIso } from '@/lib/date';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Item {
  id: string;
  date: string;
  start: string;
  minutes: number;
  doctor_id: string;
  chair_id: string | null;
  patient_name: string | null;
  medical_record_no: string | null;
}

interface Props {
  item: Item;
  doctors: SelectOption[];
  chairs: SelectOption[];
}

export default function Reschedule({ item, doctors, chairs }: Props) {
  const today = todayIso(useClinicTimezone());
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
  const back = `/schedule?date=${item.date}`;

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
    <AppLayout>
      <main className="mx-auto flex max-w-2xl flex-col gap-8 p-6 lg:px-12 lg:py-10">
        <Head title="Jadwal ulang" />
        <PageHeader
          title="Jadwal ulang"
          back={{ href: back, label: 'jadwal' }}
          description={`${item.patient_name ?? '-'} (${item.medical_record_no ?? '-'})`}
        />
        <form
          onSubmit={submit}
          noValidate
          aria-label="Jadwal ulang"
          className="flex flex-col gap-5"
        >
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
          <DatePicker
            label="Tanggal"
            value={values.date}
            min={today}
            clearable={false}
            onChange={(value) => {
              set('date', value);
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
          <TimePicker
            label="Jam mulai"
            step={15}
            value={values.time}
            onChange={(value) => {
              set('time', value);
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
          <div className="flex flex-wrap gap-3">
            <Button type="submit" loading={saving}>
              Simpan jadwal ulang
            </Button>
            <Link href={back} className="btn btn-secondary">
              Batal
            </Link>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
