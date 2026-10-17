import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { DateRangePicker } from '@/components/ui/DateRangePicker';
import type { DateRange } from '@/components/ui/DateRangePicker';
import { Select } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { formatDateTime } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Row {
  id: string;
  actor_name: string | null;
  action: string;
  subject_type: string;
  subject_id: string;
  ip: string | null;
  created_at: string;
}

interface Props {
  rows: Row[];
  total: number;
  page: number;
  per_page: number;
  subject_types: string[];
  actors: { value: string; label: string }[];
  filters: {
    action: string;
    subject_type: string;
    subject_id: string;
    actor_id: string;
    from: string;
    to: string;
  };
}

/** SYS-01: jejak perubahan data (siapa, kapan, aksi apa). Hanya-baca; tidak ada aksi ubah atau hapus. */
export default function Index({
  rows,
  total,
  page,
  per_page: perPage,
  subject_types: subjectTypes,
  actors,
  filters,
}: Props) {
  const timezone = useClinicTimezone();
  const [action, setAction] = useState(filters.action);
  const [subjectType, setSubjectType] = useState(filters.subject_type);
  const [subjectId, setSubjectId] = useState(filters.subject_id);
  const [actor, setActor] = useState(filters.actor_id);
  const [range, setRange] = useState<DateRange>({ from: filters.from, to: filters.to });
  const pages = Math.max(1, Math.ceil(total / perPage));

  function go(nextPage: number) {
    const query: Record<string, string> = {};
    if (action.trim()) query.action = action.trim();
    if (subjectType) query.subject_type = subjectType;
    if (subjectId.trim()) query.subject_id = subjectId.trim();
    if (actor) query.actor_id = actor;
    if (range.from) query.from = range.from;
    if (range.to) query.to = range.to;
    if (nextPage > 1) query.page = String(nextPage);
    router.get('/audit', query, { preserveScroll: true, preserveState: true });
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Audit log" />
        <PageHeader
          title="Audit log"
          description={`${String(total)} catatan`}
          actions={
            <Link href="/audit/access" className="btn btn-secondary">
              Jejak akses rekam medis
            </Link>
          }
        />

        <form
          className="flex flex-wrap items-end gap-3"
          aria-label="Penyaring audit"
          onSubmit={(event) => {
            event.preventDefault();
            go(1);
          }}
        >
          <TextField
            label="Aksi (awalan)"
            placeholder="mis. invoice."
            value={action}
            onChange={(e) => {
              setAction(e.target.value);
            }}
          />
          <Select
            label="Jenis subjek"
            placeholder="Semua"
            options={subjectTypes.map((t) => ({ value: t, label: t }))}
            value={subjectType}
            onChange={(e) => {
              setSubjectType(e.target.value);
            }}
          />
          <TextField
            label="ID subjek"
            value={subjectId}
            onChange={(e) => {
              setSubjectId(e.target.value);
            }}
          />
          <Select
            label="Pelaku"
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
          <p className="text-muted">Tidak ada catatan yang cocok.</p>
        ) : (
          <table>
            <caption className="sr-only">Catatan audit</caption>
            <thead>
              <tr>
                <th scope="col">Waktu</th>
                <th scope="col">Pelaku</th>
                <th scope="col">Aksi</th>
                <th scope="col">Subjek</th>
                <th scope="col">IP</th>
                <th scope="col">
                  <span className="sr-only">Detail</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>{formatDateTime(row.created_at, timezone)}</td>
                  <td>{row.actor_name ?? 'Sistem'}</td>
                  <td>{row.action}</td>
                  <td>
                    {row.subject_type}
                    <span className="text-muted block">{row.subject_id}</span>
                  </td>
                  <td>{row.ip ?? '-'}</td>
                  <td>
                    <Link
                      href={`/audit/${row.id}`}
                      className="btn btn-secondary"
                      aria-label={`Detail ${row.action}`}
                    >
                      Detail
                    </Link>
                  </td>
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
