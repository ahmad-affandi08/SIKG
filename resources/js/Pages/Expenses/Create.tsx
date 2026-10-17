import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { DatePicker } from '@/components/ui/DatePicker';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { expenseSchema } from '@/features/expenses/schemas';
import type { ExpenseValues } from '@/features/expenses/schemas';

interface Props {
  categories: SelectOption[];
  today: string;
}

/** P2-08: mencatat satu biaya operasional klinik. */
export default function Create({ categories, today }: Props) {
  const form = useForm<ExpenseValues>({
    expense_date: today,
    category_id: '',
    amount: '',
    payee: '',
    description: '',
    reference_no: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const error = (key: keyof ExpenseValues): string | undefined => errors[key] ?? form.errors[key];

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = expenseSchema(today).safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post('/expenses');
  }

  const text = (key: 'amount' | 'payee' | 'description' | 'reference_no') => ({
    value: form.data[key],
    onChange: (event: { target: { value: string } }) => {
      form.setData(key, event.target.value);
    },
    error: error(key),
  });

  return (
    <AppLayout>
      <main className="flex max-w-xl flex-col gap-3 p-3">
        <PageHead title="Catat biaya" />
        <PageHeader title="Catat biaya operasional" back={{ href: '/expenses', label: 'biaya' }} />
        {categories.length === 0 ? (
          <Alert tone="warning">
            Belum ada kategori biaya. Tambahkan dulu di Pengaturan, Kategori biaya.
          </Alert>
        ) : null}
        <form onSubmit={submit} noValidate aria-label="Catat biaya" className="form-grid">
          <DatePicker
            label="Tanggal biaya"
            value={form.data.expense_date}
            max={today}
            clearable={false}
            onChange={(value) => {
              form.setData('expense_date', value);
            }}
            error={error('expense_date')}
            required
          />
          <Select
            label="Kategori"
            placeholder="Pilih kategori"
            options={categories}
            value={form.data.category_id}
            onChange={(event) => {
              form.setData('category_id', event.target.value);
            }}
            error={error('category_id')}
            required
          />
          <TextField label="Jumlah (Rp)" inputMode="numeric" required {...text('amount')} />
          <TextField label="Keterangan" required {...text('description')} />
          <TextField label="Dibayarkan kepada" {...text('payee')} />
          <TextField label="Nomor bukti" {...text('reference_no')} />
          <div className="flex gap-3">
            <Button type="submit" loading={form.processing} disabled={categories.length === 0}>
              Simpan biaya
            </Button>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
