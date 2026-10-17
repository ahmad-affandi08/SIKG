import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { holidaySchema } from '@/features/clinic/schemas';

export default function HolidayForm() {
  const form = useForm({ date: '', name: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = holidaySchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post('/settings/holidays');
  }

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-2xl flex-col gap-8 p-6 lg:px-12 lg:py-10">
        <Head title="Tambah hari libur" />
        <PageHeader
          title="Tambah hari libur"
          back={{ href: '/settings/clinic', label: 'pengaturan klinik' }}
          description="Pada hari libur klinik tidak menerima janji baru."
        />
        <form
          onSubmit={submit}
          noValidate
          aria-label="Tambah hari libur"
          className="flex flex-col gap-5"
        >
          <TextField
            label="Tanggal"
            type="date"
            value={form.data.date}
            onChange={(event) => {
              form.setData('date', event.target.value);
            }}
            error={errors.date ?? form.errors.date}
          />
          <TextField
            label="Nama hari libur"
            value={form.data.name}
            onChange={(event) => {
              form.setData('name', event.target.value);
            }}
            error={errors.name ?? form.errors.name}
          />
          <div className="flex flex-wrap gap-3">
            <Button type="submit" loading={form.processing}>
              Simpan hari libur
            </Button>
            <Link href="/settings/clinic" className="btn btn-secondary">
              Batal
            </Link>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
