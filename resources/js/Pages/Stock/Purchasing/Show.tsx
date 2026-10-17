import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { DatePicker } from '@/components/ui/DatePicker';
import { TextField } from '@/components/ui/TextField';
import { hasReceivedLine, PURCHASE_TONE, receiveLinesError } from '@/features/pharmacy/purchasing';
import type { ReceiveLineValues } from '@/features/pharmacy/purchasing';
import { formatDate, formatRupiah } from '@/lib/format';

interface Item {
  id: string;
  name: string;
  unit: string;
  quantity_ordered: number;
  quantity_received: number;
  outstanding: number;
  unit_cost_amount: number;
  line_total: number;
}

interface Props {
  order: {
    id: string;
    po_no: string;
    supplier_name: string;
    status: string;
    status_label: string;
    expected_date: string | null;
    ordered_at: string | null;
    total_amount: number;
    note: string | null;
    items: Item[];
  };
  can: { manage: boolean };
}

/** P2-05: rincian pesanan, penerimaan barang bertahap dengan batch dan kedaluwarsa. */
export default function Show({ order, can }: Props) {
  const base = `/stock/purchase-orders/${order.id}`;
  const receivable = order.status === 'ordered' || order.status === 'partial';
  const form = useForm<{ lines: ReceiveLineValues[] }>({
    lines: order.items.map((item) => ({
      item_id: item.id,
      quantity: '',
      batch_no: '',
      expiry_date: '',
    })),
  });
  const [errors, setErrors] = useState<Record<number, string>>({});
  const outstanding = Object.fromEntries(order.items.map((item) => [item.id, item.outstanding]));

  function setLine(index: number, patch: Partial<ReceiveLineValues>) {
    form.setData(
      'lines',
      form.data.lines.map((line, i) => (i === index ? { ...line, ...patch } : line)),
    );
  }

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const found = receiveLinesError(form.data.lines, outstanding);
    if (Object.keys(found).length > 0 || !hasReceivedLine(form.data.lines)) {
      setErrors(
        Object.keys(found).length > 0
          ? found
          : { 0: 'Isi jumlah diterima pada minimal satu baris.' },
      );
      return;
    }
    setErrors({});
    form.transform((data) => ({
      lines: data.lines.map((line) => ({
        ...line,
        quantity: line.quantity.trim() || '0',
      })),
    }));
    form.post(`${base}/receive`, { preserveScroll: true });
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-4 p-3">
        <PageHead title={`Pesanan ${order.po_no}`} />
        <PageHeader
          title={`Pesanan ${order.po_no}`}
          back={{
            href: '/stock/purchase-orders',
            label: 'pembelian',
          }}
          description={order.supplier_name}
          actions={
            can.manage ? (
              <>
                {order.status === 'draft' ? (
                  <Button
                    onClick={() => {
                      router.post(`${base}/order`, {}, { preserveScroll: true });
                    }}
                  >
                    Tandai sudah dipesan
                  </Button>
                ) : null}
                {order.status === 'draft' || order.status === 'ordered' ? (
                  <ConfirmAction
                    label="Batalkan pesanan"
                    message="Pesanan dibatalkan dan tidak dapat diterima lagi. Lanjutkan?"
                    confirmLabel="Ya, batalkan"
                    onConfirm={() => {
                      router.post(`${base}/cancel`, {}, { preserveScroll: true });
                    }}
                  />
                ) : null}
              </>
            ) : null
          }
        />
        <dl className="text-sm">
          <div className="flex gap-3">
            <dt className="text-muted w-48">Status</dt>
            <dd>
              <Badge tone={PURCHASE_TONE[order.status] ?? 'neutral'}>{order.status_label}</Badge>
            </dd>
          </div>
          <div className="flex gap-3">
            <dt className="text-muted w-48">Perkiraan tiba</dt>
            <dd>{order.expected_date ? formatDate(order.expected_date) : '-'}</dd>
          </div>
          <div className="flex gap-3">
            <dt className="text-muted w-48">Total pesanan</dt>
            <dd>{formatRupiah(order.total_amount)}</dd>
          </div>
          {order.note ? (
            <div className="flex gap-3">
              <dt className="text-muted w-48">Catatan</dt>
              <dd>{order.note}</dd>
            </div>
          ) : null}
        </dl>

        <table className="w-full text-left text-sm">
          <caption className="sr-only">Baris pesanan</caption>
          <thead>
            <tr>
              <th scope="col">Obat</th>
              <th scope="col" className="text-right">
                Dipesan
              </th>
              <th scope="col" className="text-right">
                Diterima
              </th>
              <th scope="col" className="text-right">
                Harga beli
              </th>
              <th scope="col" className="text-right">
                Subtotal
              </th>
            </tr>
          </thead>
          <tbody>
            {order.items.map((item) => (
              <tr key={item.id}>
                <td>{item.name}</td>
                <td className="text-right">
                  {item.quantity_ordered} {item.unit}
                </td>
                <td className="text-right">{item.quantity_received}</td>
                <td className="text-right">{formatRupiah(item.unit_cost_amount)}</td>
                <td className="text-right">{formatRupiah(item.line_total)}</td>
              </tr>
            ))}
          </tbody>
        </table>

        {can.manage && receivable ? (
          <form onSubmit={submit} noValidate aria-label="Terima barang" className="form-grid">
            <h2>Terima barang</h2>
            <p className="text-muted text-sm">
              Isi jumlah yang datang. Baris dengan jumlah kosong atau 0 dilewati; sisanya dapat
              diterima pada pengiriman berikutnya.
            </p>
            {order.items.map((item, index) =>
              item.outstanding > 0 ? (
                <div key={item.id} className="grid grid-cols-1 items-start gap-2 sm:grid-cols-4">
                  <p className="text-sm sm:pt-7">
                    {item.name} (sisa {item.outstanding} {item.unit})
                  </p>
                  <TextField
                    label="Jumlah diterima"
                    inputMode="numeric"
                    value={form.data.lines[index]?.quantity ?? ''}
                    onChange={(event) => {
                      setLine(index, {
                        quantity: event.target.value,
                      });
                    }}
                    error={errors[index]}
                  />
                  <TextField
                    label="Nomor batch"
                    value={form.data.lines[index]?.batch_no ?? ''}
                    onChange={(event) => {
                      setLine(index, {
                        batch_no: event.target.value,
                      });
                    }}
                  />
                  <DatePicker
                    label="Kedaluwarsa"
                    value={form.data.lines[index]?.expiry_date ?? ''}
                    onChange={(value) => {
                      setLine(index, {
                        expiry_date: value,
                      });
                    }}
                    minYear={new Date().getFullYear()}
                  />
                </div>
              ) : null,
            )}
            <div className="flex gap-3">
              <Button type="submit" loading={form.processing}>
                Catat penerimaan
              </Button>
            </div>
          </form>
        ) : null}
      </main>
    </AppLayout>
  );
}
