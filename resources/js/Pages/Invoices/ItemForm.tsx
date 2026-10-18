import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { MoneyField } from '@/components/ui/MoneyField';
import { TextField } from '@/components/ui/TextField';
import { InvoiceFormShell } from '@/features/billing/components/InvoiceFormShell';
import { invoiceItemSchema, quantitySchema } from '@/features/billing/schemas';
import type { InvoiceHead, InvoiceItemRow } from '@/features/billing/schemas';
import { collectErrors } from '@/features/scheduling/form';
import { formatRupiah } from '@/lib/format';

interface Props {
  invoice: InvoiceHead;
  item: InvoiceItemRow | null;
}

/** Tambah item manual (kasir) atau ubah jumlah item manual (KSR-01). Harga satuan tidak diubah setelah item dibuat. */
export default function ItemForm({ invoice, item }: Props) {
  const editing = item !== null;
  const [values, setValues] = useState({
    description: item?.description ?? '',
    quantity: String(item?.quantity ?? 1),
    unit_price_amount: item === null ? '' : String(item.unit_price_amount),
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = (editing ? quantitySchema : invoiceItemSchema).safeParse(values);
    if (!result.success) {
      setErrors(collectErrors(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    const options = {
      onError: (serverErrors: Record<string, string>) => {
        setErrors(serverErrors);
      },
      onFinish: () => {
        setSaving(false);
      },
    };
    if (editing) {
      router.put(
        `/invoices/${invoice.id}/items/${item.id}`,
        { quantity: Number(values.quantity) },
        options,
      );
    } else {
      router.post(
        `/invoices/${invoice.id}/items`,
        {
          description: values.description,
          quantity: Number(values.quantity),
          unit_price_amount: Number(values.unit_price_amount),
        },
        options,
      );
    }
  }

  return (
    <InvoiceFormShell title={editing ? 'Ubah jumlah item' : 'Tambah item'} invoice={invoice}>
      <form
        onSubmit={submit}
        noValidate
        aria-label={editing ? 'Ubah jumlah item' : 'Tambah item'}
        className="form-grid"
      >
        {errors.domain ? (
          <p role="alert" className="text-danger-600">
            {errors.domain}
          </p>
        ) : null}
        <TextField
          field="description"
          label="Uraian"
          value={values.description}
          disabled={editing}
          error={errors.description}
          onChange={(e) => {
            setValues({ ...values, description: e.target.value });
          }}
        />
        <TextField
          field="quantity"
          label="Jumlah"
          inputMode="numeric"
          value={values.quantity}
          error={errors.quantity}
          onChange={(e) => {
            setValues({ ...values, quantity: e.target.value });
          }}
        />
        {editing ? (
          <p className="text-muted">
            Harga satuan {formatRupiah(item.unit_price_amount)} tidak dapat diubah.
          </p>
        ) : (
          <MoneyField
            field="unit_price_amount"
            label="Harga satuan"
            value={values.unit_price_amount}
            error={errors.unit_price_amount}
            onValueChange={(digits) => {
              setValues({ ...values, unit_price_amount: digits });
            }}
          />
        )}
        <div className="flex flex-wrap gap-2">
          <Button type="submit" loading={saving}>
            {editing ? 'Simpan jumlah' : 'Tambah item'}
          </Button>
          <Link href={`/invoices/${invoice.id}`} className="btn btn-secondary">
            Batal
          </Link>
        </div>
      </form>
    </InvoiceFormShell>
  );
}
