import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { formatDateTime } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Props {
  entry: {
    id: string;
    actor_name: string | null;
    action: string;
    subject_type: string;
    subject_id: string;
    ip: string | null;
    user_agent: string | null;
    created_at: string;
    fields: { field: string; before: string | null; after: string | null; changed: boolean }[];
  };
}

export default function Show({ entry }: Props) {
  const timezone = useClinicTimezone();

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Detail audit" />
        <PageHeader
          title={entry.action}
          back={{ href: '/audit', label: 'audit log' }}
          description={`${entry.subject_type} ${entry.subject_id}`}
        />
        <dl className="grid max-w-3xl grid-cols-[auto_1fr] gap-x-4 gap-y-1">
          <dt className="text-muted">Waktu</dt>
          <dd>{formatDateTime(entry.created_at, timezone)}</dd>
          <dt className="text-muted">Pelaku</dt>
          <dd>{entry.actor_name ?? 'Sistem'}</dd>
          <dt className="text-muted">Alamat IP</dt>
          <dd>{entry.ip ?? '-'}</dd>
          <dt className="text-muted">Peramban</dt>
          <dd className="break-all">{entry.user_agent ?? '-'}</dd>
        </dl>

        <section aria-labelledby="perubahan" className="flex flex-col gap-2">
          <h2 id="perubahan">Perubahan nilai</h2>
          {entry.fields.length === 0 ? (
            <p className="text-muted">Aksi ini tidak menyimpan nilai sebelum atau sesudah.</p>
          ) : (
            <table>
              <caption className="sr-only">Nilai sebelum dan sesudah</caption>
              <thead>
                <tr>
                  <th scope="col">Kolom</th>
                  <th scope="col">Sebelum</th>
                  <th scope="col">Sesudah</th>
                </tr>
              </thead>
              <tbody>
                {entry.fields.map((f) => (
                  <tr key={f.field} className={f.changed ? 'font-bold' : ''}>
                    <td>{f.field}</td>
                    <td>{f.before ?? '-'}</td>
                    <td>{f.after ?? '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
      </main>
    </AppLayout>
  );
}
