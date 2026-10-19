import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Select } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { CENTRAL, transferSchema } from '@/features/branches/schemas';

interface Option {
  value: string;
  label: string;
}

interface Props {
  branches: Option[];
  bound_branch_id: string | null;
  balances: { medicine_id: string; name: string; unit: string; branch: string; quantity: number }[];
  medicines: Option[];
  transfers: {
    id: string;
    name: string;
    unit: string;
    quantity: number;
    reason: string | null;
    from: string;
    to: string;
    at: string;
  }[];
  can: { manage: boolean };
}

/** P3-04: transfer stok antar cabang. Stok milik satu cabang; "Gudang pusat" adalah stok tanpa cabang. */
export default function Transfers({
  branches,
  bound_branch_id: boundBranchId,
  balances,
  medicines,
  transfers,
  can,
}: Props) {
  const form = useForm({
    medicine_id: '',
    from_branch_id: boundBranchId ?? CENTRAL,
    to_branch_id: '',
    quantity: '',
    reason: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const err = (key: string) =>
    errors[key] ?? (form.errors as Record<string, string | undefined>)[key];
  const places: Option[] = [{ value: CENTRAL, label: 'Gudang pusat' }, ...branches];
  const fromOptions = boundBranchId ? places.filter((p) => p.value === boundBranchId) : places;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = transferSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post('/stock/transfers', {
      preserveScroll: true,
      onSuccess: () => {
        form.reset('medicine_id', 'quantity', 'reason');
      },
    });
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-4 p-3">
        <PageHead title="Transfer stok" />
        <PageHeader
          title="Transfer stok antar cabang"
          description="Stok berpindah beserta batch, kedaluwarsa, dan harga belinya. Tercatat sebagai stok keluar di asal dan stok masuk di tujuan."
        />
        {boundBranchId ? (
          <Alert tone="info">Anda hanya dapat mengirim stok dari cabang Anda sendiri.</Alert>
        ) : null}

        {can.manage ? (
          <form
            onSubmit={submit}
            noValidate
            aria-label="Transfer stok"
            className="form-grid max-w-xl"
          >
            <Select
              label="Obat atau bahan"
              required
              placeholder="Pilih obat"
              value={form.data.medicine_id}
              options={medicines}
              error={err('medicine_id')}
              onChange={(event) => {
                form.setData('medicine_id', event.target.value);
              }}
            />
            <Select
              label="Dari"
              required
              value={form.data.from_branch_id}
              options={fromOptions}
              error={err('from_branch_id')}
              onChange={(event) => {
                form.setData('from_branch_id', event.target.value);
              }}
            />
            <Select
              label="Ke"
              required
              placeholder="Pilih tujuan"
              value={form.data.to_branch_id}
              options={places.filter((p) => p.value !== form.data.from_branch_id)}
              error={err('to_branch_id')}
              onChange={(event) => {
                form.setData('to_branch_id', event.target.value);
              }}
            />
            <TextField
              label="Jumlah"
              inputMode="numeric"
              required
              value={form.data.quantity}
              error={err('quantity')}
              onChange={(event) => {
                form.setData('quantity', event.target.value);
              }}
            />
            <TextField
              label="Alasan (opsional)"
              required={false}
              value={form.data.reason}
              error={err('reason')}
              onChange={(event) => {
                form.setData('reason', event.target.value);
              }}
            />
            <div>
              <Button type="submit" loading={form.processing}>
                Pindahkan stok
              </Button>
            </div>
          </form>
        ) : null}

        <section aria-label="Stok per cabang" className="flex flex-col gap-1">
          <h2>Stok per cabang</h2>
          <div className="overflow-x-auto">
            <table className="data-table w-full text-sm">
              <thead>
                <tr>
                  <th>Obat atau bahan</th>
                  <th>Lokasi</th>
                  <th>Jumlah</th>
                </tr>
              </thead>
              <tbody>
                {balances.length === 0 ? (
                  <tr>
                    <td colSpan={3}>
                      <EmptyState kind="box" size="compact" title="Belum ada saldo stok" />
                    </td>
                  </tr>
                ) : null}
                {balances.map((row) => (
                  <tr key={`${row.medicine_id}-${row.branch}`}>
                    <td>{row.name}</td>
                    <td>{row.branch}</td>
                    <td>
                      {String(row.quantity)} {row.unit}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section aria-label="Riwayat transfer" className="flex flex-col gap-1">
          <h2>Riwayat transfer</h2>
          <div className="overflow-x-auto">
            <table className="data-table w-full text-sm">
              <thead>
                <tr>
                  <th>Waktu</th>
                  <th>Obat atau bahan</th>
                  <th>Jumlah</th>
                  <th>Dari</th>
                  <th>Ke</th>
                  <th>Alasan</th>
                </tr>
              </thead>
              <tbody>
                {transfers.length === 0 ? (
                  <tr>
                    <td colSpan={6}>
                      <EmptyState kind="box" size="compact" title="Belum ada transfer" />
                    </td>
                  </tr>
                ) : null}
                {transfers.map((row) => (
                  <tr key={row.id}>
                    <td>{row.at}</td>
                    <td>{row.name}</td>
                    <td>
                      {String(row.quantity)} {row.unit}
                    </td>
                    <td>{row.from}</td>
                    <td>{row.to}</td>
                    <td>{row.reason ?? ''}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      </main>
    </AppLayout>
  );
}
