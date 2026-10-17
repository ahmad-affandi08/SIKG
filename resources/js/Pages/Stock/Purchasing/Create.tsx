import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { DatePicker } from '@/components/ui/DatePicker';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { purchaseOrderSchema } from '@/features/pharmacy/purchasing';
import type { PurchaseOrderValues } from '@/features/pharmacy/purchasing';
import { formatRupiah } from '@/lib/format';

interface Props {
  suppliers: SelectOption[];
  medicines: SelectOption[];
}

const EMPTY_LINE = { medicine_id: '', quantity: '', unit_cost_amount: '' };

/** P2-05: membuat pesanan pembelian (draft) ke pemasok. */
export default function Create({ suppliers, medicines }: Props) {
  const form = useForm<PurchaseOrderValues>({
    supplier_id: '',
    expected_date: '',
    note: '',
    lines: [{ ...EMPTY_LINE }],
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const error = (key: string): string | undefined =>
    errors[key] ?? (form.errors as Record<string, string | undefined>)[key];

  const total = form.data.lines.reduce(
    (sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.unit_cost_amount) || 0),
    0,
  );

  function setLine(index: number, patch: Partial<(typeof form.data.lines)[number]>) {
    form.setData(
      'lines',
      form.data.lines.map((line, i) => (i === index ? { ...line, ...patch } : line)),
    );
  }

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = purchaseOrderSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[issue.path.map(String).join('.')] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post('/stock/purchase-orders');
  }

  return (
    <AppLayout>
      <main className="flex max-w-3xl flex-col gap-3 p-3">
        <PageHead title="Pesanan pembelian baru" />
        <PageHeader
          title="Pesanan pembelian baru"
          back={{
            href: '/stock/purchase-orders',
            label: 'pembelian',
          }}
        />
        {suppliers.length === 0 ? (
          <Alert tone="warning">
            Belum ada pemasok aktif. Tambahkan dulu di Pengaturan, Pemasok.
          </Alert>
        ) : null}
        <form
          onSubmit={submit}
          noValidate
          aria-label="Pesanan pembelian baru"
          className="form-grid"
        >
          <Select
            label="Pemasok"
            field="supplier_id"
            placeholder="Pilih pemasok"
            options={suppliers}
            value={form.data.supplier_id}
            onChange={(event) => {
              form.setData('supplier_id', event.target.value);
            }}
            error={error('supplier_id')}
          />
          <DatePicker
            label="Perkiraan tiba"
            value={form.data.expected_date}
            onChange={(value) => {
              form.setData('expected_date', value);
            }}
            error={error('expected_date')}
          />
          <TextField
            label="Catatan"
            value={form.data.note}
            onChange={(event) => {
              form.setData('note', event.target.value);
            }}
            error={error('note')}
          />

          <fieldset className="flex flex-col gap-2">
            <legend className="text-sm font-semibold">Obat dan bahan yang dipesan</legend>
            {form.data.lines.map((line, index) => (
              <div key={index} className="grid grid-cols-1 items-start gap-2 sm:grid-cols-4">
                <Select
                  label={`Obat ${String(index + 1)}`}
                  placeholder="Pilih obat"
                  options={medicines}
                  value={line.medicine_id}
                  onChange={(event) => {
                    setLine(index, {
                      medicine_id: event.target.value,
                    });
                  }}
                  error={error(`lines.${String(index)}.medicine_id`)}
                  required
                />
                <TextField
                  label="Jumlah"
                  inputMode="numeric"
                  value={line.quantity}
                  onChange={(event) => {
                    setLine(index, {
                      quantity: event.target.value,
                    });
                  }}
                  error={error(`lines.${String(index)}.quantity`)}
                  required
                />
                <TextField
                  label="Harga beli satuan (Rp)"
                  inputMode="numeric"
                  value={line.unit_cost_amount}
                  onChange={(event) => {
                    setLine(index, {
                      unit_cost_amount: event.target.value,
                    });
                  }}
                  error={error(`lines.${String(index)}.unit_cost_amount`)}
                  required
                />
                <div className="sm:pt-6">
                  <Button
                    variant="secondary"
                    disabled={form.data.lines.length === 1}
                    onClick={() => {
                      form.setData(
                        'lines',
                        form.data.lines.filter((_, i) => i !== index),
                      );
                    }}
                  >
                    Hapus baris
                  </Button>
                </div>
              </div>
            ))}
            {error('lines') ? (
              <p role="alert" className="text-danger-600 text-sm">
                {error('lines')}
              </p>
            ) : null}
            <div>
              <Button
                variant="secondary"
                onClick={() => {
                  form.setData('lines', [...form.data.lines, { ...EMPTY_LINE }]);
                }}
              >
                Tambah obat
              </Button>
            </div>
          </fieldset>

          <p className="text-sm font-semibold">Perkiraan total: {formatRupiah(total)}</p>
          <div className="flex gap-3">
            <Button type="submit" loading={form.processing} disabled={suppliers.length === 0}>
              Simpan draft
            </Button>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
