import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { DateRangePicker } from '@/components/ui/DateRangePicker';
import type { DateRange } from '@/components/ui/DateRangePicker';
import { EmptyState } from '@/components/ui/EmptyState';
import { Select } from '@/components/ui/Select';
import { formatDateTime } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Row {
  id: string;
  actor_name: string | null;
  patient_name: string | null;
  medical_record_no: string | null;
  route: string;
  ip: string | null;
  at: string;
}

interface Props {
  rows: Row[];
  total: number;
  page: number;
  per_page: number;
  actors: { value: string; label: string }[];
  filters: { actor_id: string; from: string; to: string };
}

/** Jejak pembukaan rekam medis pasien (UU PDP dan Permenkes 24/2022): siapa membuka rekam siapa dan kapan. */
export default function Accesses({ rows, total, page, per_page: perPage, actors, filters }: Props) {
  const timezone = useClinicTimezone();
  const [actor, setActor] = useState(filters.actor_id);
  const [range, setRange] = useState<DateRange>({ from: filters.from, to: filters.to });
  const pages = Math.max(1, Math.ceil(total / perPage));

  function go(nextPage: number) {
    const query: Record<string, string> = {};
    if (actor) query.actor_id = actor;
    if (range.from) query.from = range.from;
    if (range.to) query.to = range.to;
    if (nextPage > 1) query.page = String(nextPage);
    router.get('/audit/access', query, { preserveScroll: true, preserveState: true });
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Jejak akses" />
        <PageHeader
          title="Jejak akses rekam medis"
          description={`${String(total)} pembukaan`}
          actions={
            <Link href="/audit" className="btn btn-secondary">
              Audit log
            </Link>
          }
        />
        <form
          className="flex flex-wrap items-end gap-3"
          aria-label="Penyaring jejak akses"
          onSubmit={(event) => {
            event.preventDefault();
            go(1);
          }}
        >
          <Select
            label="Pengguna"
            placeholder="Semua"
            options={actors}
            value={actor}
            onChange={(e) => {
              setActor(e.target.value);
            }}
          />
          <DateRangePicker label="Tanggal" value={range} onChange={setRange} />
          <Button type="submit" variant="secondary">
            Terapkan
          </Button>
        </form>

        {rows.length === 0 ? (
          <EmptyState
            kind="search"
            size="page"
            title="Tidak ada catatan yang cocok"
            description="Ubah rentang tanggal atau filter pengguna."
          />
        ) : (
          <table>
            <caption className="sr-only">Jejak akses rekam medis</caption>
            <thead>
              <tr>
                <th scope="col">Waktu</th>
                <th scope="col">Pengguna</th>
                <th scope="col">Pasien</th>
                <th scope="col">Halaman</th>
                <th scope="col">IP</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>{formatDateTime(row.at, timezone)}</td>
                  <td>{row.actor_name ?? '-'}</td>
                  <td>
                    {row.patient_name ?? '-'}
                    <span className="text-muted"> {row.medical_record_no}</span>
                  </td>
                  <td>{row.route}</td>
                  <td>{row.ip ?? '-'}</td>
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
