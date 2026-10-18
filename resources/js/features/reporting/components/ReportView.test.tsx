import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ReportView } from '@/features/reporting/components/ReportView';
import type { Report } from '@/features/reporting/schemas';

const report: Report = {
  key: 'revenue',
  title: 'Pendapatan',
  from: '2025-06-16',
  to: '2025-06-16',
  metrics: [
    { label: 'Pendapatan (tagihan terbit)', value: 350000, kind: 'money' },
    { label: 'Rata-rata waktu tunggu', value: null, kind: 'minutes' },
  ],
  tables: [
    {
      title: 'Pendapatan per kategori tindakan',
      columns: [
        { key: 'category', label: 'Kategori', kind: 'text' },
        { key: 'total', label: 'Nilai', kind: 'money' },
      ],
      rows: [
        { category: 'Bedah', total: 150000 },
        { category: 'Diskon tagihan', total: -30000 },
      ],
      totals: { category: 'Total', total: 120000 },
      truncated: false,
      empty_text: null,
    },
    {
      title: 'Tagihan dibatalkan',
      columns: [{ key: 'number', label: 'Nomor', kind: 'text' }],
      rows: [],
      totals: null,
      truncated: true,
      empty_text: 'Tidak ada tagihan dibatalkan pada rentang ini.',
    },
  ],
  notes: ['Pendapatan = total tagihan final.'],
};

describe('LAP tampilan isi laporan', () => {
  it('menampilkan angka ringkasan dengan format rupiah dan strip untuk nilai kosong', () => {
    render(<ReportView report={report} />);

    expect(screen.getByText('Rp 350.000')).toBeInTheDocument();
    expect(screen.getByText('-')).toBeInTheDocument();
  });

  it('tabel memuat baris, angka negatif, dan baris total', () => {
    render(<ReportView report={report} />);

    const table = screen.getByRole('table', { name: 'Pendapatan per kategori tindakan' });
    expect(within(table).getByText('Rp 150.000')).toBeInTheDocument();
    expect(within(table).getByText('-Rp 30.000')).toBeInTheDocument();
    expect(within(table).getByText('Total')).toBeInTheDocument();
    expect(within(table).getByText('Rp 120.000')).toBeInTheDocument();
    expect(
      within(table)
        .getAllByRole('columnheader')
        .map((h) => h.textContent),
    ).toEqual(['Kategori', 'Nilai']);
  });

  it('tabel kosong menampilkan teks kosong dari server dan tanda terpotong', () => {
    render(<ReportView report={report} />);

    expect(screen.getByText('Tidak ada tagihan dibatalkan pada rentang ini.')).toBeInTheDocument();
    expect(screen.getByText(/Tabel dipotong/)).toBeInTheDocument();
  });

  it('catatan definisi ditampilkan agar angka dapat dipertanggungjawabkan', () => {
    render(<ReportView report={report} />);

    expect(screen.getByText('Pendapatan = total tagihan final.')).toBeInTheDocument();
  });
});
