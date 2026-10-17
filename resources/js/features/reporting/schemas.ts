import { z } from 'zod';

/** Jenis nilai kolom; sama dengan `ColumnKind` di backend. Uang rupiah bulat, persen basis poin, durasi menit. */
export const columnKindSchema = z.enum([
  'text',
  'int',
  'money',
  'percent',
  'minutes',
  'date',
  'datetime',
]);
export type ColumnKind = z.infer<typeof columnKindSchema>;

const cellSchema = z.union([z.string(), z.number(), z.null()]);
export type Cell = z.infer<typeof cellSchema>;

export const reportSchema = z.object({
  key: z.string(),
  code: z.string(),
  title: z.string(),
  from: z.string(),
  to: z.string(),
  metrics: z.array(z.object({ label: z.string(), value: cellSchema, kind: columnKindSchema })),
  tables: z.array(
    z.object({
      title: z.string(),
      columns: z.array(z.object({ key: z.string(), label: z.string(), kind: columnKindSchema })),
      rows: z.array(z.record(z.string(), cellSchema)),
      totals: z.record(z.string(), cellSchema).nullable(),
      truncated: z.boolean(),
      empty_text: z.string().nullable(),
    }),
  ),
  notes: z.array(z.string()),
});
export type Report = z.infer<typeof reportSchema>;
export type ReportTable = Report['tables'][number];

export const exportFormatSchema = z.enum(['pdf', 'xlsx']);
export type ExportFormat = z.infer<typeof exportFormatSchema>;

/** Status berkas laporan yang dibuat lewat antrean (SYS-07). */
export const jobStatusSchema = z.enum(['queued', 'running', 'done', 'failed']);
export type JobStatus = z.infer<typeof jobStatusSchema>;

export const reportJobSchema = z.object({
  id: z.string(),
  report_key: z.string(),
  format: exportFormatSchema,
  status: jobStatusSchema,
  status_label: z.string().optional(),
  error: z.string().nullable(),
  download_url: z.string().nullable().optional(),
});
export type ReportJob = z.infer<typeof reportJobSchema>;

export const JOB_LABEL: Record<JobStatus, string> = {
  queued: 'Menunggu',
  running: 'Berjalan',
  done: 'Selesai',
  failed: 'Gagal',
};

export function isFinalStatus(status: JobStatus): boolean {
  return status === 'done' || status === 'failed';
}

export const dashboardSchema = z.object({
  date: z.string(),
  revenue_today: z.number(),
  revenue_month: z.number(),
  revenue_prev_month: z.number(),
  revenue_prev_month_same_period: z.number(),
  revenue_change_basis_points: z.number().nullable(),
  visits_today: z.number(),
  visits_month: z.number(),
  no_show_count: z.number(),
  no_show_basis_points: z.number(),
  receivables_total: z.number(),
  receivables_count: z.number(),
  low_stock_count: z.number(),
  series: z.array(z.object({ date: z.string(), revenue: z.number() })),
});
export type Dashboard = z.infer<typeof dashboardSchema>;

export interface ReportFilterValues {
  from: string;
  to: string;
  doctor_id: string;
  method_id: string;
  grain: string;
}
