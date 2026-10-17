import { Head } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { Badge } from '@/components/ui/Badge';

interface Check {
  key: string;
  label: string;
  status: 'pass' | 'warn' | 'fail';
  detail: string;
}

interface Props {
  checks: Check[];
  health: {
    status: string;
    scheduler_age_seconds: number | null;
    queue_age_seconds: number | null;
    pending_jobs: number;
    failed_jobs: number;
    stale_after_seconds: number;
  };
  hostingProfile: string;
}

const STATUS = {
  pass: { label: 'Lulus', tone: 'success' },
  warn: { label: 'Peringatan', tone: 'warning' },
  fail: { label: 'Gagal', tone: 'danger' },
} as const;

function age(seconds: number | null): string {
  return seconds === null ? 'belum pernah berdenyut' : `${seconds} detik lalu`;
}

/** SYS-06 dan SYS-07: baca-saja. Tidak menampilkan nilai rahasia. */
export default function System({ checks, health, hostingProfile }: Props) {
  const failed = checks.filter((check) => check.status === 'fail').length;

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-5xl flex-col gap-8 p-6 lg:px-12 lg:py-10">
        <Head title="Sistem" />
        <h1>Kesiapan sistem</h1>
        <p className="text-muted text-sm">
          Profil hosting: <strong>{hostingProfile}</strong>.{' '}
          {failed === 0
            ? 'Semua butir wajib lulus.'
            : `${failed} butir wajib gagal; perbaiki sebelum dipakai.`}
        </p>

        <section aria-labelledby="denyut" className="flex flex-col gap-2">
          <h2 id="denyut">Denyut cron dan antrean</h2>
          <p>
            Status:{' '}
            <Badge tone={health.status === 'ok' ? 'success' : 'warning'}>
              {health.status === 'ok' ? 'Sehat' : 'Menurun'}
            </Badge>
          </p>
          <ul className="list-disc pl-5 text-sm">
            <li>Scheduler: {age(health.scheduler_age_seconds)}</li>
            <li>Worker antrean: {age(health.queue_age_seconds)}</li>
            <li>Job tertunda: {health.pending_jobs}</li>
            <li>Job gagal: {health.failed_jobs}</li>
            <li>Dianggap terlambat setelah {health.stale_after_seconds} detik.</li>
          </ul>
        </section>

        <section aria-labelledby="pemeriksaan" className="flex flex-col gap-2">
          <h2 id="pemeriksaan">Pemeriksaan lingkungan</h2>
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Hasil pemeriksaan kesiapan lingkungan</caption>
            <thead>
              <tr>
                <th scope="col" className="py-2">
                  Butir
                </th>
                <th scope="col">Hasil</th>
                <th scope="col">Keterangan</th>
              </tr>
            </thead>
            <tbody>
              {checks.map((check) => (
                <tr key={check.key} className="align-top">
                  <td className="py-2 pr-3">{check.label}</td>
                  <td className="pr-3">
                    <Badge tone={STATUS[check.status].tone}>{STATUS[check.status].label}</Badge>
                  </td>
                  <td>{check.detail}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      </main>
    </AppLayout>
  );
}
