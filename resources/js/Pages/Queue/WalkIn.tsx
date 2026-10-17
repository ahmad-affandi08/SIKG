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
import { PatientPicker } from '@/features/patient/components/PatientPicker';
import { walkInSchema } from '@/features/scheduling/schemas';

interface Props {
  doctors: SelectOption[];
  chairs: SelectOption[];
}

export default function WalkIn({ doctors, chairs }: Props) {
  const [patient, setPatient] = useState<{ id: string; name: string } | null>(null);
  const [doctorId, setDoctorId] = useState('');
  const [chairId, setChairId] = useState('');
  const [complaint, setComplaint] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = {
      patient_id: patient?.id ?? '',
      doctor_id: doctorId,
      chair_id: chairId,
      complaint,
    };
    const result = walkInSchema.safeParse(values);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    setSaving(true);
    router.post('/walk-ins', values, {
      onError: (serverErrors) => {
        setErrors(serverErrors);
      },
      onFinish: () => {
        setSaving(false);
      },
    });
  }

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-2xl flex-col gap-8 p-6 lg:px-12 lg:py-10">
        <PageHead title="Pasien tanpa janji" />
        <PageHeader
          title="Pasien tanpa janji"
          back={{ href: '/queue', label: 'antrean' }}
          description="Pasien langsung masuk antrean hari ini dengan nomor antrean berikutnya."
        />
        <form
          onSubmit={submit}
          noValidate
          aria-label="Pasien tanpa janji"
          className="flex flex-col gap-5"
        >
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
            value={doctorId}
            onChange={(event) => {
              setDoctorId(event.target.value);
            }}
            error={errors.doctor_id}
          />
          <Select
            field="chair_id"
            label="Kursi (opsional)"
            placeholder="Belum ditentukan"
            options={chairs}
            value={chairId}
            onChange={(event) => {
              setChairId(event.target.value);
            }}
          />
          <TextField
            field="complaint"
            label="Keluhan"
            value={complaint}
            onChange={(event) => {
              setComplaint(event.target.value);
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
              Masukkan ke antrean
            </Button>
            <Link href="/queue" className="btn btn-secondary">
              Batal
            </Link>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
