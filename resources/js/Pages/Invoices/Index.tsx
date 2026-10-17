import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { DateRangePicker } from '@/components/ui/DateRangePicker';
import type { DateRange } from '@/components/ui/DateRangePicker';
import { Select } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { STATUS_TONE } from '@/features/billing/schemas';
import type { InvoiceStatus } from '@/features/billing/schemas';
import { formatDate, formatRupiah } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Row {
  id: string;
  number: string | null;
  patient_name: string | null;
  medical_record_no: string | null;
  status: InvoiceStatus;
  status_label: string;
  total_amount: number;
  paid_amount: number;
  balance_amount: number;
  created_at: string;
}

interface Props {
  rows: Row[];
  total: number;
  page: number;
  per_page: number;
  filters: { status: string; number: string; from: string; to: string };
  statuses: { value: string; label: string }[];
}

export default function Index({ rows, total, page, per_page: perPage, filters, statuses }: Props) {
  const timezone = useClinicTimezone();
  const [status, setStatus] = useState(filters.status);
  const [number, setNumber] = useState(filters.number);
  const [range, setRange] = useState<DateRange>({ from: filters.from, to: filters.to });
  const pages = Math.max(1, Math.ceil(total / perPage));

  function go(nextPage: number) {
    const query: Record<string, string> = {};
    if (status) query.status = status;
    if (number.trim()) query.number = number.trim();
    if (range.from) query.from = range.from;
    if (range.to) query.to = range.to;
    if (nextPage > 1) query.page = String(nextPage);
    router.get('/invoices', query, { preserveScroll: true, preserveState: true });
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Tagihan" />
        <PageHeader title="Tagihan" description={`${String(total)} tagihan`} />

        <form
          className="flex flex-wrap items-end gap-3"
          aria-label="Penyaring tagihan"
          onSubmit={(event) => {
            event.preventDefault();
            go(1);
          }}
        >
          <Select
            label="Status"
            placeholder="Semua status"
            options={statuses}
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
            }}
          />
          <TextField
            label="Nomor invoice"
            value={number}
            onChange={(e) => {
              setNumber(e.target.value);
            }}
          />
          <DateRangePicker label="Tanggal dibuat" value={range} onChange={setRange} />
          <Button type="submit" variant="secondary">
            Terapkan
          </Button>
        </form>

        {rows.length === 0 ? (
          <p className="text-muted">Belum ada tagihan yang cocok.</p>
        ) : (
          <table>
            <caption className="sr-only">Daftar tagihan</caption>
            <thead>
              <tr>
                <th scope="col">Nomor</th>
                <th scope="col">Pasien</th>
                <th scope="col">Tanggal</th>
                <th scope="col">Status</th>
                <th scope="col" className="text-right">
                  Total
                </th>
                <th scope="col" className="text-right">
                  Dibayar
                </th>
                <th scope="col" className="text-right">
                  Sisa
                </th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    <Link href={`/invoices/${row.id}`} className="text-brand-700 underline">
                      {row.number ?? 'Draft'}
                    </Link>
                  </td>
                  <td>
                    {row.patient_name ?? '-'}
                    <span className="text-muted"> {row.medical_record_no}</span>
                  </td>
                  <td>{formatDate(row.created_at, timezone)}</td>
                  <td>
                    <Badge tone={STATUS_TONE[row.status]}>{row.status_label}</Badge>
                  </td>
                  <td className="text-right">{formatRupiah(row.total_amount)}</td>
                  <td className="text-right">{formatRupiah(row.paid_amount)}</td>
                  <td className="text-right">{formatRupiah(row.balance_amount)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {pages > 1 ? (
          <nav aria-label="Halaman daftar" className="flex items-center gap-2">
            <Button
              variant="secondary"
              disabled={page <= 1}
              onClick={() => {
                go(page - 1);
              }}
            >
              Sebelumnya
            </Button>
            <span>
              Halaman {page} dari {pages}
            </span>
            <Button
              variant="secondary"
              disabled={page >= pages}
              onClick={() => {
                go(page + 1);
              }}
            >
              Berikutnya
            </Button>
          </nav>
        ) : null}
      </main>
    </AppLayout>
  );
}
