import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { OwnerDashboard, changeText } from '@/features/reporting/components/OwnerDashboard';
import type { Dashboard } from '@/features/reporting/schemas';

vi.mock('@inertiajs/react', () => ({
  Link: ({
    href,
    children,
    className,
  }: {
    href: string;
    children: React.ReactNode;
    className?: string;
  }) => (
    <a href={href} className={className}>
      {children}
    </a>
  ),
}));

const dashboard: Dashboard = {
  date: '2025-06-16',
  revenue_today: 350000,
  revenue_month: 1250000,
  revenue_prev_month: 2000000,
  revenue_prev_month_same_period: 1000000,
  revenue_change_basis_points: 2500,
  visits_today: 6,
  visits_month: 40,
  no_show_count: 3,
  no_show_basis_points: 750,
  receivables_total: 150000,
  receivables_count: 2,
  low_stock_count: 4,
  series: Array.from({ length: 30 }, (_, i) => ({
    date: `2025-05-${String(18 + (i % 10)).padStart(2, '0')}`,
    revenue: i * 1000,
  })),
};

describe('LAP dasbor Pemilik', () => {
  it('menampilkan pendapatan, kunjungan, tidak datang, piutang, dan stok menipis', () => {
    render(<OwnerDashboard dashboard={dashboard} />);

    expect(screen.getByText('Rp 350.000')).toBeInTheDocument();
    expect(screen.getByText('Rp 1.250.000')).toBeInTheDocument();
    expect(screen.getByText('6 hari ini')).toBeInTheDocument();
    expect(screen.getByText('7,5%')).toBeInTheDocument();
    expect(screen.getByText('Rp 150.000')).toBeInTheDocument();
    expect(screen.getByText('4 jenis')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Total piutang/ })).toHaveAttribute(
      'href',
      '/reports/receivables',
    );
  });

  it('grafik 30 hari punya padanan tabel untuk pembaca layar', () => {
    render(<OwnerDashboard dashboard={dashboard} />);

    expect(screen.getByRole('img', { name: /30 hari terakhir/ })).toBeInTheDocument();
    expect(
      screen.getByRole('table', { name: 'Pendapatan harian 30 hari terakhir' }),
    ).toBeInTheDocument();
  });

  it('perubahan dibanding bulan lalu: naik, turun, sama, atau tanpa pembanding', () => {
    expect(changeText(1250000, 1000000, 2500)).toContain('Naik 25%');
    expect(changeText(500000, 1000000, -5000)).toContain('Turun 50%');
    expect(changeText(1000000, 1000000, 0)).toContain('Sama dengan bulan lalu');
    expect(changeText(100, 0, null)).toContain('belum ada pendapatan');
  });
});
