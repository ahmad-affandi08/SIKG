import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { cashClosingSchema } from '@/features/billing/schemas';
import { collectErrors } from '@/features/scheduling/form';
import { formatDate, formatRupiah } from '@/lib/format';

interface Props {
  preview: { date: string; system_amount: number; payments: number };
}

/** Tutup kas (KSR-08): kasir mengisi uang fisik; selisih terhadap catatan sistem dihitung server dan wajib dijelaskan. */
export default function Form({ preview }: Props) {
  const [values, setValues] = useState({ counted_amount: '', note: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const counted = /^\d{1,12}$/.test(values.counted_amount) ? Number(values.counted_amount) : null;
  const difference = counted === null ? null : counted - preview.system_amount;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = cashClosingSchema.safeParse(values);
    if (!result.success) {
      setErrors(collectErrors(result.error.issues));
      return;
    }
    if (difference !== 0 && values.note.trim() === '') {
      setErrors({ note: 'Ada selisih kas; isi catatan penjelasannya.' });
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      '/cash-closings',
      { counted_amount: Number(values.counted_amount), note: values.note || null },
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
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Tutup kas sekarang" />
        <PageHeader
          title="Tutup kas sekarang"
          back={{ href: '/cash-closings', label: 'riwayat tutup kas' }}
          description={`Per ${formatDate(preview.date)}`}
        />
        <dl className="grid max-w-md grid-cols-[auto_1fr] gap-x-4 gap-y-0.5">
          <dt className="text-muted">Catatan sistem (tunai bersih)</dt>
          <dd className="text-right font-bold">{formatRupiah(preview.system_amount)}</dd>
          <dt className="text-muted">Jumlah transaksi tunai</dt>
          <dd className="text-right">{preview.payments}</dd>
        </dl>
        <form onSubmit={submit} noValidate aria-label="Tutup kas" className="form-grid">
          {errors.domain ? (
            <p role="alert" className="text-danger-600">
              {errors.domain}
            </p>
          ) : null}
          <TextField
            field="counted_amount"
            label="Uang tunai fisik di laci (rupiah)"
            inputMode="numeric"
            value={values.counted_amount}
            error={errors.counted_amount}
            onChange={(e) => {
              setValues({ ...values, counted_amount: e.target.value });
            }}
          />
          <TextField
            field="note"
            label="Catatan (wajib bila ada selisih)"
            value={values.note}
            error={errors.note}
            onChange={(e) => {
              setValues({ ...values, note: e.target.value });
            }}
          />
          {difference !== null ? (
            <p role="status" className={difference === 0 ? 'text-success-600' : 'text-danger-600'}>
              {difference === 0
                ? 'Tidak ada selisih.'
                : `Selisih ${difference > 0 ? 'lebih' : 'kurang'} ${formatRupiah(Math.abs(difference))}.`}
            </p>
          ) : null}
          <div className="flex flex-wrap gap-2">
            <Button type="submit" loading={saving}>
              Tutup kas
            </Button>
            <Link href="/cash-closings" className="btn btn-secondary">
              Batal
            </Link>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
