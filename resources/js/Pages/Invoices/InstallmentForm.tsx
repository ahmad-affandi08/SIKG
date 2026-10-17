import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { DatePicker } from '@/components/ui/DatePicker';
import { TextField } from '@/components/ui/TextField';
import { InvoiceFormShell } from '@/features/billing/components/InvoiceFormShell';
import { installmentSchema } from '@/features/billing/schemas';
import type { InvoiceHead } from '@/features/billing/schemas';
import { collectErrors } from '@/features/scheduling/form';
import { formatRupiah } from '@/lib/format';
import { todayIso } from '@/lib/date';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

/** Jadwal cicilan sisa tagihan (KSR-05): dibagi rata, jatuh tempo tiap bulan pada tanggal yang sama. */
export default function InstallmentForm({ invoice }: { invoice: InvoiceHead }) {
  const timezone = useClinicTimezone();
  const [values, setValues] = useState({ count: '3', first_due_date: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const count = /^\d{1,2}$/.test(values.count) ? Number(values.count) : 0;
  const balance = invoice.balance_amount ?? 0;
  const preview =
    count >= 2 && count <= 36
      ? `Sekitar ${formatRupiah(Math.floor(balance / count))} per cicilan (sisa pembulatan masuk cicilan pertama).`
      : null;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = installmentSchema.safeParse(values);
    if (!result.success) {
      setErrors(collectErrors(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      `/invoices/${invoice.id}/payment-plan`,
      { count: Number(values.count), first_due_date: values.first_due_date },
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
    <InvoiceFormShell title="Jadwal cicilan" invoice={invoice} showAmounts>
      <form onSubmit={submit} noValidate aria-label="Jadwal cicilan" className="form-grid">
        {errors.domain ? (
          <p role="alert" className="text-danger-600">
            {errors.domain}
          </p>
        ) : null}
        <TextField
          field="count"
          label="Jumlah cicilan (2 sampai 36)"
          inputMode="numeric"
          value={values.count}
          error={errors.count}
          onChange={(e) => {
            setValues({ ...values, count: e.target.value });
          }}
        />
        <DatePicker
          field="first_due_date"
          label="Jatuh tempo cicilan pertama"
          value={values.first_due_date}
          min={todayIso(timezone)}
          error={errors.first_due_date}
          onChange={(value) => {
            setValues({ ...values, first_due_date: value });
          }}
        />
        {preview ? <p className="text-muted">{preview}</p> : null}
        <div className="flex flex-wrap gap-2">
          <Button type="submit" loading={saving}>
            Buat jadwal
          </Button>
          <Link href={`/invoices/${invoice.id}`} className="btn btn-secondary">
            Batal
          </Link>
        </div>
      </form>
    </InvoiceFormShell>
  );
}
