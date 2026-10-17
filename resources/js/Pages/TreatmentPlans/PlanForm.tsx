import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { planFormSchema } from '@/features/clinical/plan';
import type { PlanFormValues } from '@/features/clinical/plan';

interface Props {
  patient: { id: string; name: string; medical_record_no: string };
}

export default function PlanForm({ patient }: Props) {
  const form = useForm<PlanFormValues>({ title: '', notes: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const back = `/patients/${patient.id}/treatment-plans`;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = planFormSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post(back);
  }

  const err = (key: keyof PlanFormValues) => errors[key] ?? form.errors[key];

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Buat rencana perawatan" />
        <PageHeader
          title="Buat rencana perawatan"
          back={{ href: back, label: 'rencana perawatan' }}
          description={`${patient.name} (${patient.medical_record_no})`}
        />
        <form
          onSubmit={submit}
          noValidate
          aria-label="Buat rencana perawatan"
          className="form-grid"
        >
          <TextField
            field="title"
            label="Judul rencana"
            value={form.data.title}
            error={err('title')}
            onChange={(event) => {
              form.setData('title', event.target.value);
            }}
          />
          <TextField
            field="notes"
            label="Catatan"
            value={form.data.notes}
            error={err('notes')}
            onChange={(event) => {
              form.setData('notes', event.target.value);
            }}
          />
          <div className="flex flex-wrap gap-3">
            <Button type="submit" loading={form.processing}>
              Simpan rencana
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
