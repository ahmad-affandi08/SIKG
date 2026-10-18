import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { MoneyField } from '@/components/ui/MoneyField';
import { TextField } from '@/components/ui/TextField';
import { InvoiceFormShell } from '@/features/billing/components/InvoiceFormShell';
import { paymentSchema } from '@/features/billing/schemas';
import type { InvoiceHead } from '@/features/billing/schemas';
import { collectErrors } from '@/features/scheduling/form';

interface Props {
  invoice: InvoiceHead;
  methods: { value: string; label: string; type: string }[];
  /** Pembayaran biasa hanya setelah tagihan terbit; pada draft hanya uang muka. */
  allowPay: boolean;
  idempotencyKey: string;
}

/** Pembayaran dan uang muka (KSR-03, KSR-04). Idempotency-Key tetap selama halaman terbuka sehingga kirim ganda tidak tercatat dua kali. */
export default function PaymentForm({ invoice, methods, allowPay, idempotencyKey }: Props) {
  const typeOptions: SelectOption[] = [
    ...(allowPay ? [{ value: 'pay', label: 'Pembayaran' }] : []),
    { value: 'dp', label: 'Uang muka (DP)' },
  ];
  const [values, setValues] = useState({
    payment_method_id: '',
    type: allowPay ? 'pay' : 'dp',
    amount: allowPay && (invoice.balance_amount ?? 0) > 0 ? String(invoice.balance_amount) : '',
    reference_no: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = paymentSchema.safeParse(values);
    if (!result.success) {
      setErrors(collectErrors(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      `/invoices/${invoice.id}/payments`,
      {
        payment_method_id: values.payment_method_id,
        type: values.type,
        amount: Number(values.amount),
        reference_no: values.reference_no || null,
      },
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
    <InvoiceFormShell title="Catat pembayaran" invoice={invoice} showAmounts>
      <form onSubmit={submit} noValidate aria-label="Pembayaran" className="form-grid">
        {errors.domain ? (
          <p role="alert" className="text-danger-600">
            {errors.domain}
          </p>
        ) : null}
        <Select
          field="payment_method_id"
          label="Metode pembayaran"
          placeholder="Pilih metode"
          options={methods}
          value={values.payment_method_id}
          error={errors.payment_method_id}
          onChange={(e) => {
            setValues({ ...values, payment_method_id: e.target.value });
          }}
        />
        <Select
          field="type"
          label="Jenis"
          options={typeOptions}
          value={values.type}
          onChange={(e) => {
            setValues({ ...values, type: e.target.value });
          }}
        />
        <MoneyField
          field="amount"
          label="Jumlah"
          value={values.amount}
          error={errors.amount}
          onValueChange={(digits) => {
            setValues({ ...values, amount: digits });
          }}
        />
        <TextField
          field="reference_no"
          label="Nomor referensi (opsional, mis. nomor transfer)"
          value={values.reference_no}
          error={errors.reference_no}
          onChange={(e) => {
            setValues({ ...values, reference_no: e.target.value });
          }}
        />
        <div className="flex flex-wrap gap-2">
          <Button type="submit" loading={saving}>
            Simpan pembayaran
          </Button>
          <Link href={`/invoices/${invoice.id}`} className="btn btn-secondary">
            Batal
          </Link>
        </div>
      </form>
    </InvoiceFormShell>
  );
}
