import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Badge } from '@/components/ui/Badge';
import { formatDateTime } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

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
  backup: {
    state: 'ok' | 'warn' | 'fail';
    reasons: string[];
    interval_hours: number;
    last_backup: {
      file: string | null;
      finished_at: string;
      details: { rows?: number; tables?: number };
    } | null;
    last_verify: {
      file: string | null;
      finished_at: string;
      details: { rows?: number; tables?: number };
    } | null;
    backups: number;
    recent: {
      kind: string;
      ok: boolean;
      file: string | null;
      message: string;
      finished_at: string;
    }[];
  };
  hostingProfile: string;
}

const STATUS = {
  pass: { label: 'Lulus', tone: 'success' },
  warn: { label: 'Peringatan', tone: 'warning' },
  fail: { label: 'Gagal', tone: 'danger' },
} as const;

const BACKUP_LABEL = { ok: 'Sehat', warn: 'Perlu perhatian', fail: 'Bermasalah' } as const;
const BACKUP_TONE = { ok: 'success', warn: 'warning', fail: 'danger' } as const;
const RUN_KIND: Record<string, string> = {
  backup: 'Cadangan data',
  verify: 'Uji pemulihan',
  mirror: 'Salinan berkas',
  prune: 'Pemangkasan',
};

function age(seconds: number | null): string {
  return seconds === null ? 'belum pernah berdenyut' : `${seconds} detik lalu`;
}

/** SYS-06 dan SYS-07: baca-saja. Tidak menampilkan nilai rahasia. */
export default function System({ checks, health, backup, hostingProfile }: Props) {
  const timezone = useClinicTimezone();
  const failed = checks.filter((check) => check.status === 'fail').length;

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Sistem" />
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

        <section aria-labelledby="cadangan" className="flex flex-col gap-2">
          <h2 id="cadangan">Cadangan data</h2>
          <p>
            Status: <Badge tone={BACKUP_TONE[backup.state]}>{BACKUP_LABEL[backup.state]}</Badge>
          </p>
          {backup.reasons.length > 0 ? (
            <ul className="list-disc pl-5 text-sm">
              {backup.reasons.map((reason) => (
                <li key={reason}>{reason}</li>
              ))}
            </ul>
          ) : null}
          <ul className="list-disc pl-5 text-sm">
            <li>
              Cadangan terakhir berhasil:{' '}
              {backup.last_backup
                ? formatDateTime(backup.last_backup.finished_at, timezone)
                : 'belum ada'}
              {backup.last_backup?.details.rows !== undefined
                ? ` (${String(backup.last_backup.details.tables)} tabel, ${String(backup.last_backup.details.rows)} baris)`
                : ''}
            </li>
            <li>
              Uji pemulihan terakhir berhasil:{' '}
              {backup.last_verify
                ? formatDateTime(backup.last_verify.finished_at, timezone)
                : 'belum ada'}
            </li>
            <li>Berkas cadangan tersimpan: {backup.backups}</li>
            <li>Interval cadangan: setiap {backup.interval_hours} jam.</li>
          </ul>
          {backup.recent.length > 0 ? (
            <table className="w-full text-left text-sm">
              <caption className="sr-only">Riwayat cadangan terbaru</caption>
              <thead>
                <tr>
                  <th scope="col">Waktu</th>
                  <th scope="col">Jenis</th>
                  <th scope="col">Hasil</th>
                  <th scope="col">Keterangan</th>
                </tr>
              </thead>
              <tbody>
                {backup.recent.map((run) => (
                  <tr key={`${run.kind}-${run.finished_at}`}>
                    <td>{formatDateTime(run.finished_at, timezone)}</td>
                    <td>{RUN_KIND[run.kind] ?? run.kind}</td>
                    <td>
                      <Badge tone={run.ok ? 'success' : 'danger'}>
                        {run.ok ? 'Berhasil' : 'Gagal'}
                      </Badge>
                    </td>
                    <td>{run.message}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : null}
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
