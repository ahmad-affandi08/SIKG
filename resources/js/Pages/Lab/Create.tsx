import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { DatePicker } from '@/components/ui/DatePicker';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { labOrderSchema } from '@/features/lab/schemas';
import type { LabOrderValues } from '@/features/lab/schemas';

interface Props {
  patient: { id: string; name: string; medical_record_no: string };
  doctors: SelectOption[];
  laboratories: SelectOption[];
  work_types: SelectOption[];
  today: string;
}

/** P2-06: memesan pekerjaan ke laboratorium gigi untuk satu pasien. */
export default function Create({ patient, doctors, laboratories, work_types, today }: Props) {
  const form = useForm<LabOrderValues & { patient_id: string }>({
    patient_id: patient.id,
    doctor_id: '',
    laboratory_id: '',
    work_type: '',
    tooth_notes: '',
    description: '',
    shade: '',
    due_date: '',
    lab_cost_amount: '0',
    patient_charge_amount: '0',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const error = (key: keyof LabOrderValues): string | undefined => errors[key] ?? form.errors[key];

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = labOrderSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post('/lab-orders');
  }

  const text = (
    key: 'tooth_notes' | 'description' | 'shade' | 'lab_cost_amount' | 'patient_charge_amount',
  ) => ({
    value: form.data[key],
    onChange: (event: { target: { value: string } }) => {
      form.setData(key, event.target.value);
    },
    error: error(key),
  });

  return (
    <AppLayout>
      <main className="flex max-w-xl flex-col gap-3 p-3">
        <PageHead title="Pesanan lab baru" />
        <PageHeader
          title="Pesanan lab baru"
          back={{ href: `/patients/${patient.id}`, label: 'pasien' }}
          description={`${patient.name} (${patient.medical_record_no})`}
        />
        {laboratories.length === 0 ? (
          <Alert tone="warning">
            Belum ada laboratorium. Tambahkan dulu di Pengaturan, Laboratorium.
          </Alert>
        ) : null}
        <form onSubmit={submit} noValidate aria-label="Pesanan lab baru" className="form-grid">
          <Select
            label="Laboratorium"
            placeholder="Pilih laboratorium"
            options={laboratories}
            value={form.data.laboratory_id}
            onChange={(event) => {
              form.setData('laboratory_id', event.target.value);
            }}
            error={error('laboratory_id')}
            required
          />
          <Select
            label="Dokter"
            placeholder="Pilih dokter"
            options={doctors}
            value={form.data.doctor_id}
            onChange={(event) => {
              form.setData('doctor_id', event.target.value);
            }}
            error={error('doctor_id')}
            required
          />
          <Select
            label="Jenis pekerjaan"
            placeholder="Pilih jenis"
            options={work_types}
            value={form.data.work_type}
            onChange={(event) => {
              form.setData('work_type', event.target.value);
            }}
            error={error('work_type')}
            required
          />
          <TextField label="Gigi (FDI)" {...text('tooth_notes')} />
          <TextField label="Uraian pekerjaan" required {...text('description')} />
          <TextField label="Warna gigi (shade)" {...text('shade')} />
          <DatePicker
            label="Target selesai"
            value={form.data.due_date}
            min={today}
            onChange={(value) => {
              form.setData('due_date', value);
            }}
            error={error('due_date')}
          />
          <TextField
            label="Perkiraan biaya lab (Rp)"
            inputMode="numeric"
            required
            {...text('lab_cost_amount')}
          />
          <TextField
            label="Harga ke pasien (Rp)"
            inputMode="numeric"
            required
            {...text('patient_charge_amount')}
          />
          <div className="flex gap-3">
            <Button type="submit" loading={form.processing} disabled={laboratories.length === 0}>
              Buat pesanan
            </Button>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
