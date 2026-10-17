import { Link } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';

interface ReportItem {
  key: string;
  code: string;
  title: string;
  description: string;
  group: string;
}

/** Daftar laporan yang boleh dibuka pengguna, dikelompokkan (Keuangan, Klinis, Stok, Audit). */
export default function Index({ reports }: { reports: ReportItem[] }) {
  const groups = [...new Set(reports.map((report) => report.group))];

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Laporan" />
        <PageHeader title="Laporan" description={`${String(reports.length)} laporan tersedia`} />
        {reports.length === 0 ? (
          <p className="text-muted">Akun Anda belum memiliki akses ke laporan.</p>
        ) : (
          groups.map((group) => (
            <section key={group} aria-label={group} className="flex flex-col gap-1">
              <h2 className="text-base">{group}</h2>
              <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {reports
                  .filter((report) => report.group === group)
                  .map((report) => (
                    <li key={report.key}>
                      <Link
                        href={`/reports/${report.key}`}
                        className="pane hover:bg-brand-50 block h-full"
                      >
                        <span className="pane-head">
                          {report.code} {report.title}
                        </span>
                        <span className="text-muted block p-2">{report.description}</span>
                      </Link>
                    </li>
                  ))}
              </ul>
            </section>
          ))
        )}
      </main>
    </AppLayout>
  );
}
