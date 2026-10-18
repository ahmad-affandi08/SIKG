import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { MoneyField, digitsOnly } from '@/components/ui/MoneyField';
import { TextField } from '@/components/ui/TextField';
import { InvoiceFormShell } from '@/features/billing/components/InvoiceFormShell';
import { discountSchema, percentText } from '@/features/billing/schemas';
import type { InvoiceHead, InvoiceItemRow } from '@/features/billing/schemas';
import { collectErrors } from '@/features/scheduling/form';
import { formatRupiah } from '@/lib/format';

interface Props {
  invoice: InvoiceHead;
  /** Diskon per item bila diisi; kosong berarti diskon per tagihan. */
  item: InvoiceItemRow | null;
  current: {
    discount_type: 'percent' | 'amount' | null;
    discount_value: number | null;
    discount_reason: string | null;
  };
  limitPercent: number;
}

const KINDS = [
  { value: 'percent', label: 'Persen (%)' },
  { value: 'amount', label: 'Nominal (Rp)' },
  { value: 'none', label: 'Hapus diskon' },
];

function initialValue(current: Props['current']): string {
  if (current.discount_type === null || current.discount_value === null) {
    return '';
  }
  return current.discount_type === 'percent'
    ? percentText(current.discount_value).replace('%', '')
    : String(current.discount_value);
}

/** Diskon (KSR-02): persen atau nominal dengan alasan wajib, dibatasi per peran. */
export default function DiscountForm({ invoice, item, current, limitPercent }: Props) {
  const [values, setValues] = useState<{
    kind: 'percent' | 'amount' | 'none';
    value: string;
    reason: string;
  }>({
    kind: current.discount_type ?? 'percent',
    value: initialValue(current),
    reason: current.discount_reason ?? '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const url =
    item === null
      ? `/invoices/${invoice.id}/discount`
      : `/invoices/${invoice.id}/items/${item.id}/discount`;
  const none = values.kind === 'none';

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = discountSchema.safeParse(values);
    if (!result.success) {
      setErrors(collectErrors(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(url, values, {
      onError: (serverErrors) => {
        setErrors(serverErrors);
      },
      onFinish: () => {
        setSaving(false);
      },
    });
  }

  return (
    <InvoiceFormShell title={item === null ? 'Diskon tagihan' : 'Diskon item'} invoice={invoice}>
      <form onSubmit={submit} noValidate aria-label="Diskon" className="form-grid">
        {errors.domain ? (
          <p role="alert" className="text-danger-600">
            {errors.domain}
          </p>
        ) : null}
        <p className="text-muted">
          {item === null
            ? `Subtotal tagihan ${formatRupiah(invoice.subtotal_amount ?? 0)}.`
            : `${item.description}: ${formatRupiah(item.gross_amount)}.`}{' '}
          Batas diskon untuk peran Anda {String(limitPercent)}%.
        </p>
        <Select
          field="kind"
          label="Jenis diskon"
          options={KINDS}
          value={values.kind}
          onChange={(e) => {
            const kind = e.target.value as 'percent' | 'amount' | 'none';
            // Persen boleh berpecahan, rupiah tidak: buang pecahan saat pindah ke nominal.
            const value =
              kind === 'amount' ? digitsOnly(values.value.split('.')[0] ?? '') : values.value;
            setValues({ ...values, kind, value });
          }}
        />
        {none ? null : (
          <>
            {values.kind === 'percent' ? (
              <TextField
                field="value"
                label="Persen"
                inputMode="decimal"
                value={values.value}
                error={errors.value}
                onChange={(e) => {
                  setValues({ ...values, value: e.target.value });
                }}
              />
            ) : (
              <MoneyField
                field="value"
                label="Nominal"
                value={values.value}
                error={errors.value}
                onValueChange={(digits) => {
                  setValues({ ...values, value: digits });
                }}
              />
            )}
            <TextField
              field="reason"
              label="Alasan diskon"
              value={values.reason}
              error={errors.reason}
              onChange={(e) => {
                setValues({ ...values, reason: e.target.value });
              }}
            />
          </>
        )}
        <div className="flex flex-wrap gap-2">
          <Button type="submit" loading={saving}>
            {none ? 'Hapus diskon' : 'Terapkan diskon'}
          </Button>
          <Link href={`/invoices/${invoice.id}`} className="btn btn-secondary">
            Batal
          </Link>
        </div>
      </form>
    </InvoiceFormShell>
  );
}
