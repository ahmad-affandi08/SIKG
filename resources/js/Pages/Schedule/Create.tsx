import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { DatePicker } from '@/components/ui/DatePicker';
import { TimePicker } from '@/components/ui/TimePicker';
import { PatientPicker } from '@/features/patient/components/PatientPicker';
import { SlotPicker } from '@/features/scheduling/components/SlotPicker';
import { collectErrors, DURATION_OPTIONS } from '@/features/scheduling/form';
import { appointmentFormSchema } from '@/features/scheduling/schemas';
import { todayIso } from '@/lib/date';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Props {
  defaultDate: string;
  doctors: SelectOption[];
  chairs: SelectOption[];
}

export default function Create({ defaultDate, doctors, chairs }: Props) {
  const today = todayIso(useClinicTimezone());
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
  const back = `/schedule?date=${defaultDate}`;

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
        <PageHead title="Buat janji" />
        <PageHeader title="Buat janji" back={{ href: back, label: 'jadwal' }} />
        <form onSubmit={submit} noValidate aria-label="Buat janji" className="flex flex-col gap-5">
          <PatientPicker
            field="patient_id"
            selected={patient}
            onSelect={setPatient}
            error={errors.patient_id}
          />
          <Select
            field="doctor_id"
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
            field="chair_id"
            label="Kursi (opsional)"
            placeholder="Belum ditentukan"
            options={chairs}
            value={values.chair_id}
            onChange={(event) => {
              set('chair_id', event.target.value);
            }}
          />
          <DatePicker
            field="date"
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
            field="minutes"
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
            field="time"
            label="Jam mulai"
            step={15}
            value={values.time}
            onChange={(value) => {
              set('time', value);
            }}
            error={errors.time}
          />
          <TextField
            field="complaint"
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
          <div className="flex flex-wrap gap-3">
            <Button type="submit" loading={saving}>
              Simpan janji
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
