import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { InvoiceFormShell } from '@/features/billing/components/InvoiceFormShell';
import { refundSchema } from '@/features/billing/schemas';
import type { InvoiceHead } from '@/features/billing/schemas';
import { collectErrors } from '@/features/scheduling/form';

interface Props {
  invoice: InvoiceHead;
  methods: { value: string; label: string; type: string }[];
  idempotencyKey: string;
}

/** Retur atau refund sebagian (KSR-07): transaksi pembalik, pembayaran asal tidak dihapus. */
export default function RefundForm({ invoice, methods, idempotencyKey }: Props) {
  const [values, setValues] = useState({ payment_method_id: '', amount: '', reason: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = refundSchema.safeParse(values);
    if (!result.success) {
      setErrors(collectErrors(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      `/invoices/${invoice.id}/refunds`,
      { ...values, amount: Number(values.amount) },
      {
        headers: { 'Idempotency-Key': idempotencyKey },
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
    <InvoiceFormShell title="Pengembalian dana" invoice={invoice} showAmounts>
      <form onSubmit={submit} noValidate aria-label="Pengembalian dana" className="form-grid">
        {errors.domain ? (
          <p role="alert" className="text-danger-600">
            {errors.domain}
          </p>
        ) : null}
        <Select
          field="payment_method_id"
          label="Dikembalikan lewat metode"
          placeholder="Pilih metode"
          options={methods}
          value={values.payment_method_id}
          error={errors.payment_method_id}
          onChange={(e) => {
            setValues({ ...values, payment_method_id: e.target.value });
          }}
        />
        <TextField
          field="amount"
          label="Jumlah (rupiah)"
          inputMode="numeric"
          value={values.amount}
          error={errors.amount}
          onChange={(e) => {
            setValues({ ...values, amount: e.target.value });
          }}
        />
        <TextField
          field="reason"
          label="Alasan pengembalian"
          value={values.reason}
          error={errors.reason}
          onChange={(e) => {
            setValues({ ...values, reason: e.target.value });
          }}
        />
        <div className="flex flex-wrap gap-2">
          <Button type="submit" variant="danger" loading={saving}>
            Catat pengembalian
          </Button>
          <Link href={`/invoices/${invoice.id}`} className="btn btn-secondary">
            Batal
          </Link>
        </div>
      </form>
    </InvoiceFormShell>
  );
}
