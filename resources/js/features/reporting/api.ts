import { api } from '@/lib/http';
import { reportJobSchema } from '@/features/reporting/schemas';
import type { ExportFormat, ReportFilterValues, ReportJob } from '@/features/reporting/schemas';

/** Meminta berkas laporan; dibuat lewat antrean sehingga balasan langsung berstatus menunggu atau lebih lanjut. */
export async function requestExport(
  reportKey: string,
  filters: Partial<ReportFilterValues>,
  format: ExportFormat,
): Promise<ReportJob> {
  const body: Record<string, string> = { format };
  for (const [key, value] of Object.entries(filters)) {
    if (value) {
      body[key] = value;
    }
  }
  const result = await api.post(`/internal-api/reports/${reportKey}/export`, reportJobSchema, {
    body,
  });
  return result.data;
}

export async function fetchReportJob(id: string, signal?: AbortSignal): Promise<ReportJob> {
  const result = await api.get(
    `/internal-api/report-jobs/${id}`,
    reportJobSchema,
    signal ? { signal } : {},
  );
  return result.data;
}
