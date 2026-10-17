import { useMutation, useQuery } from '@tanstack/react-query';

import { fetchReportJob, requestExport } from '@/features/reporting/api';
import { isFinalStatus } from '@/features/reporting/schemas';
import type { ExportFormat, ReportFilterValues } from '@/features/reporting/schemas';

export const reportKeys = {
  job: (id: string) => ['report', 'job', id] as const,
};

/** Selang polling status berkas; berhenti saat final dan saat tab tidak aktif (TanStack Query). */
export const JOB_POLL_MS = 2000;

export function useReportJob(id: string | null) {
  return useQuery({
    queryKey: reportKeys.job(id ?? 'none'),
    queryFn: ({ signal }) => fetchReportJob(id ?? '', signal),
    enabled: id !== null,
    staleTime: 0,
    gcTime: 0,
    refetchInterval: (query) => {
      const job = query.state.data;
      return job && isFinalStatus(job.status) ? false : JOB_POLL_MS;
    },
  });
}

export function useRequestExport(reportKey: string) {
  return useMutation({
    mutationFn: ({
      filters,
      format,
    }: {
      filters: Partial<ReportFilterValues>;
      format: ExportFormat;
    }) => requestExport(reportKey, filters, format),
  });
}
