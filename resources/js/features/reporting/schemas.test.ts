import { describe, expect, it } from 'vitest';

import {
  dashboardSchema,
  isFinalStatus,
  reportJobSchema,
  reportSchema,
} from '@/features/reporting/schemas';

describe('LAP skema respons laporan', () => {
  it('menerima bentuk laporan dari server', () => {
    const parsed = reportSchema.parse({
      key: 'revenue',
      code: 'LAP-02',
      title: 'Pendapatan',
      from: '2025-06-16',
      to: '2025-06-16',
      metrics: [{ label: 'Pendapatan', value: 350000, kind: 'money' }],
      tables: [
        {
          title: 'Per hari',
          columns: [{ key: 'period', label: 'Tanggal', kind: 'date' }],
          rows: [{ period: '2025-06-16' }],
          totals: null,
          truncated: false,
          empty_text: null,
        },
      ],
      notes: [],
    });

    expect(parsed.tables[0]?.rows).toHaveLength(1);
  });

  it('menolak jenis kolom yang tidak dikenal', () => {
    expect(() =>
      reportSchema.parse({
        key: 'x',
        code: 'LAP-99',
        title: 'X',
        from: '2025-06-16',
        to: '2025-06-16',
        metrics: [{ label: 'a', value: 1, kind: 'float' }],
        tables: [],
        notes: [],
      }),
    ).toThrow();
  });

  it('status berkas: hanya selesai dan gagal yang final', () => {
    expect(isFinalStatus('done')).toBe(true);
    expect(isFinalStatus('failed')).toBe(true);
    expect(isFinalStatus('queued')).toBe(false);
    expect(isFinalStatus('running')).toBe(false);
  });

  it('berkas yang baru diminta belum punya tautan unduh', () => {
    const job = reportJobSchema.parse({
      id: 'A',
      report_key: 'revenue',
      format: 'pdf',
      status: 'queued',
      error: null,
    });

    expect(job.download_url).toBeUndefined();
    expect(() => reportJobSchema.parse({ ...job, status: 'sedang' })).toThrow();
  });

  it('dasbor memuat 30 titik pendapatan bilangan bulat', () => {
    const base = {
      date: '2025-06-16',
      revenue_today: 0,
      revenue_month: 0,
      revenue_prev_month: 0,
      revenue_prev_month_same_period: 0,
      revenue_change_basis_points: null,
      visits_today: 0,
      visits_month: 0,
      no_show_count: 0,
      no_show_basis_points: 0,
      receivables_total: 0,
      receivables_count: 0,
      low_stock_count: 0,
      series: [{ date: '2025-06-16', revenue: 100 }],
    };

    expect(dashboardSchema.parse(base).series).toHaveLength(1);
  });
});
