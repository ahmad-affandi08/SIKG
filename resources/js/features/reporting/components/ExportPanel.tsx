import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useReportJob, useRequestExport } from '@/features/reporting/queries';
import { JOB_LABEL, isFinalStatus } from '@/features/reporting/schemas';
import type { ExportFormat, ReportFilterValues } from '@/features/reporting/schemas';
import { HttpError } from '@/lib/http';

interface ExportPanelProps {
  reportKey: string;
  filters: Partial<ReportFilterValues>;
}

const FORMAT_LABEL: Record<ExportFormat, string> = { pdf: 'PDF', xlsx: 'Excel' };

/**
 * Ekspor PDF dan Excel lewat antrean: tombol meminta berkas, lalu status "menunggu", "berjalan", dan "selesai" tampil
 * jujur lewat polling (pada hosting bersama antrean digerakkan cron sehingga berkas dapat menunggu satu interval).
 */
export function ExportPanel({ reportKey, filters }: ExportPanelProps) {
  const [jobId, setJobId] = useState<string | null>(null);
  const [format, setFormat] = useState<ExportFormat | null>(null);
  const request = useRequestExport(reportKey);
  const job = useReportJob(jobId);
  const status = job.data?.status ?? (request.data ? request.data.status : null);
  const busy = request.isPending || (status !== null && !isFinalStatus(status));

  function start(next: ExportFormat) {
    setFormat(next);
    request.mutate(
      { filters, format: next },
      {
        onSuccess: (created) => {
          setJobId(created.id);
        },
      },
    );
  }

  const failure = request.error instanceof HttpError ? request.error.message : null;
  const downloadUrl = job.data?.status === 'done' ? (job.data.download_url ?? null) : null;

  return (
    <section aria-label="Unduh laporan" className="flex flex-wrap items-center gap-2">
      <Button
        variant="secondary"
        disabled={busy}
        onClick={() => {
          start('pdf');
        }}
      >
        Unduh PDF
      </Button>
      <Button
        variant="secondary"
        disabled={busy}
        onClick={() => {
          start('xlsx');
        }}
      >
        Unduh Excel
      </Button>
      <div role="status" aria-live="polite" className="flex items-center gap-2">
        {status !== null && format !== null ? (
          <>
            <Badge
              tone={status === 'done' ? 'success' : status === 'failed' ? 'danger' : 'warning'}
            >
              {FORMAT_LABEL[format]}: {JOB_LABEL[status]}
            </Badge>
            {status === 'queued' ? (
              <span className="text-muted text-xs">
                Menunggu antrean. Pada hosting bersama berkas dapat menunggu hingga satu interval
                cron.
              </span>
            ) : null}
            {downloadUrl ? (
              <a href={downloadUrl} className="btn btn-primary" download>
                Unduh {FORMAT_LABEL[format]}
              </a>
            ) : null}
          </>
        ) : null}
      </div>
      {status === 'failed' ? (
        <Alert tone="error">
          {job.data?.error ?? 'Berkas laporan gagal dibuat. Coba minta ulang.'}
        </Alert>
      ) : null}
      {failure ? <Alert tone="error">{failure}</Alert> : null}
    </section>
  );
}
