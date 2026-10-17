import { Head, router } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { PatientPicker } from '@/features/patient/components/PatientPicker';
import { useQueue } from '@/features/scheduling/queries';
import { walkInSchema } from '@/features/scheduling/schemas';
import { statusTone } from '@/features/scheduling/status';

interface Props {
  doctors: SelectOption[];
  chairs: SelectOption[];
  canManage: boolean;
}

export default function Index({ doctors, chairs, canManage }: Props) {
  const queue = useQueue();
  const client = useQueryClient();
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
      preserveScroll: true,
      onSuccess: () => {
        setPatient(null);
        setComplaint('');
        void client.invalidateQueries({ queryKey: ['scheduling', 'queue'] });
      },
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
      <main className="mx-auto flex max-w-5xl flex-col gap-6 p-6">
        <Head title="Antrean" />
        <h1 className="text-2xl font-semibold">Antrean hari ini</h1>

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
                </tr>
              </thead>
              <tbody>
                {queue.data.map((item) => (
                  <tr key={item.id} className="border-t border-slate-200 align-top">
                    <td className="py-2 pr-3 text-lg font-semibold">{item.queue_no}</td>
                    <td className="pr-3">
                      <div className="font-medium">{item.patient_name ?? '-'}</div>
                      <div className="text-slate-600">{item.medical_record_no}</div>
                    </td>
                    <td className="pr-3">{item.doctor_name ?? '-'}</td>
                    <td className="pr-3">{item.chair_name ?? '-'}</td>
                    <td className="pr-3">{item.complaint ?? '-'}</td>
                    <td>
                      <Badge tone={statusTone(item.status)}>{item.status_label}</Badge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : queue.isSuccess ? (
            <p className="text-sm text-slate-600">Belum ada pasien dalam antrean.</p>
          ) : null}
          <p className="mt-2 text-xs text-slate-600">Diperbarui otomatis setiap 10 detik.</p>
        </section>

        {canManage ? (
          <form
            onSubmit={submit}
            noValidate
            aria-label="Pasien tanpa janji"
            className="rounded-control flex max-w-xl flex-col gap-3 p-4 ring-1 ring-slate-200"
          >
            <h2 className="text-lg font-semibold">Pasien tanpa janji (walk-in)</h2>
            <PatientPicker selected={patient} onSelect={setPatient} error={errors.patient_id} />
            <Select
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
              label="Kursi (opsional)"
              placeholder="Belum ditentukan"
              options={chairs}
              value={chairId}
              onChange={(event) => {
                setChairId(event.target.value);
              }}
            />
            <TextField
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
            <div>
              <Button type="submit" loading={saving}>
                Masukkan ke antrean
              </Button>
            </div>
          </form>
        ) : null}
      </main>
    </AppLayout>
  );
}
