import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { historyFormSchema } from '@/features/patient/schemas';
import type { HistoryFormValues } from '@/features/patient/schemas';

interface Props {
  patient: { id: string; name: string; medical_record_no: string };
  historyTypes: SelectOption[];
  severities: SelectOption[];
}

export default function HistoryForm({ patient, historyTypes, severities }: Props) {
  const form = useForm<HistoryFormValues>({ type: '', name: '', severity: '', notes: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const back = `/patients/${patient.id}`;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = historyFormSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post(`/patients/${patient.id}/history`);
  }

  const err = (key: keyof HistoryFormValues) => errors[key] ?? form.errors[key];

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Tambah riwayat medis" />
        <PageHeader
          title="Tambah riwayat medis"
          back={{ href: back, label: 'rekam pasien' }}
          description={`${patient.name} (${patient.medical_record_no})`}
        />
        <form onSubmit={submit} noValidate aria-label="Tambah riwayat medis" className="form-grid">
          <Select
            field="type"
            label="Jenis"
            placeholder="Pilih jenis"
            options={historyTypes}
            value={form.data.type}
            onChange={(event) => {
              form.setData('type', event.target.value);
            }}
            error={err('type')}
          />
          <TextField
            field="name"
            label="Nama (contoh Penisilin, Diabetes melitus)"
            value={form.data.name}
            onChange={(event) => {
              form.setData('name', event.target.value);
            }}
            error={err('name')}
          />
          <Select
            field="severity"
            label="Tingkat keparahan"
            placeholder="Tidak ditentukan"
            options={severities}
            value={form.data.severity}
            onChange={(event) => {
              form.setData('severity', event.target.value);
            }}
            error={err('severity')}
          />
          <TextField
            field="notes"
            label="Catatan"
            value={form.data.notes}
            onChange={(event) => {
              form.setData('notes', event.target.value);
            }}
            error={err('notes')}
          />
          <div className="flex flex-wrap gap-3">
            <Button type="submit" loading={form.processing}>
              Simpan riwayat
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
