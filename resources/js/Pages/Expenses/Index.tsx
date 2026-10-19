import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { DatePicker } from '@/components/ui/DatePicker';
import { EmptyState } from '@/components/ui/EmptyState';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { voidSchema } from '@/features/expenses/schemas';
import { formatDate, formatRupiah } from '@/lib/format';

interface Row {
  id: string;
  expense_date: string;
  category_name: string;
  amount: number;
  payee: string | null;
  description: string;
  reference_no: string | null;
  voided: boolean;
  void_reason: string | null;
}

interface Props {
  expenses: Row[];
  total_amount: number;
  categories: SelectOption[];
  filters: { from: string; to: string; category: string };
  can: { manage: boolean };
}

function VoidForm({ id }: { id: string }) {
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | undefined>();

  if (!open) {
    return (
      <Button
        variant="secondary"
        onClick={() => {
          setOpen(true);
        }}
      >
        Batalkan
      </Button>
    );
  }
  return (
    <form
      noValidate
      aria-label="Batalkan biaya"
      className="flex flex-col gap-1"
      onSubmit={(event) => {
        event.preventDefault();
        const result = voidSchema.safeParse({ reason });
        if (!result.success) {
          setError(result.error.issues[0]?.message);
          return;
        }
        setError(undefined);
        router.post(`/expenses/${id}/void`, { reason }, { preserveScroll: true });
      }}
    >
      <TextField
        label="Alasan pembatalan"
        value={reason}
        onChange={(event) => {
          setReason(event.target.value);
        }}
        error={error}
        required
      />
      <div className="flex gap-2">
        <Button type="submit" variant="danger">
          Ya, batalkan biaya
        </Button>
        <Button
          variant="secondary"
          onClick={() => {
            setOpen(false);
          }}
        >
          Tutup
        </Button>
      </div>
    </form>
  );
}

/** P2-08: daftar biaya operasional dalam rentang tanggal, dengan total biaya yang tidak dibatalkan. */
export default function Index({ expenses, total_amount, categories, filters, can }: Props) {
  function filter(next: Partial<Props['filters']>) {
    const query = { ...filters, ...next };
    router.get(
      '/expenses',
      Object.fromEntries(Object.entries(query).filter(([, value]) => value !== '')),
      { preserveScroll: true, preserveState: true },
    );
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Biaya operasional" />
        <PageHeader
          title="Biaya operasional"
          description="Dasar laporan laba rugi sederhana. Biaya yang salah dibatalkan, bukan dihapus."
          actions={
            can.manage ? (
              <Link href="/expenses/create" className="btn btn-primary">
                Catat biaya
              </Link>
            ) : null
          }
        />
        <div className="flex flex-wrap items-end gap-3">
          <DatePicker
            label="Dari"
            value={filters.from}
            clearable={false}
            onChange={(value) => {
              if (value !== '') filter({ from: value });
            }}
          />
          <DatePicker
            label="Sampai"
            value={filters.to}
            clearable={false}
            onChange={(value) => {
              if (value !== '') filter({ to: value });
            }}
          />
          <Select
            label="Kategori"
            placeholder="Semua kategori"
            options={categories}
            value={filters.category}
            onChange={(event) => {
              filter({ category: event.target.value });
            }}
          />
          <Link href="/settings/master/expense-categories" className="btn btn-secondary">
            Kategori biaya
          </Link>
          <Link href="/reports/profit-loss" className="btn btn-secondary">
            Laporan laba rugi
          </Link>
        </div>
        <p className="text-sm font-semibold">
          Total biaya (tidak dibatalkan): {formatRupiah(total_amount)}
        </p>

        {expenses.length === 0 ? (
          <EmptyState
            kind="invoice"
            size="page"
            title="Belum ada biaya pada rentang ini"
            description="Ubah rentang tanggal, atau catat biaya operasional baru."
          />
        ) : (
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Daftar biaya operasional</caption>
            <thead>
              <tr>
                <th scope="col">Tanggal</th>
                <th scope="col">Kategori</th>
                <th scope="col">Keterangan</th>
                <th scope="col">Dibayarkan kepada</th>
                <th scope="col" className="text-right">
                  Jumlah
                </th>
                <th scope="col">
                  <span className="sr-only">Aksi</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {expenses.map((expense) => (
                <tr key={expense.id} className={expense.voided ? 'text-muted line-through' : ''}>
                  <td>{formatDate(expense.expense_date)}</td>
                  <td>{expense.category_name}</td>
                  <td>
                    {expense.description}
                    {expense.reference_no ? ` (${expense.reference_no})` : ''}
                  </td>
                  <td>{expense.payee ?? '-'}</td>
                  <td className="text-right">{formatRupiah(expense.amount)}</td>
                  <td>
                    {expense.voided ? (
                      <Badge tone="neutral">Dibatalkan: {expense.void_reason}</Badge>
                    ) : can.manage ? (
                      <VoidForm id={expense.id} />
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </main>
    </AppLayout>
  );
}
