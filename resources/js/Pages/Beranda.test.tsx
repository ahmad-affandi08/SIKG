import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import Beranda from '@/Pages/Beranda';

let setup: { done: number; total: number } | null = null;

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ href, children }: { href: string; children: ReactNode }) => (
    <a href={href}>{children}</a>
  ),
  usePage: () => ({
    url: '/dashboard',
    props: {
      auth: { user: { id: 'U1', name: 'Pemilik', roles: ['pemilik'] }, permissions: [] },
      clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' },
      flash: { success: null, error: null },
      required: [],
      setup,
    },
  }),
}));

describe('Beranda banner Penyiapan awal (MST-11)', () => {
  beforeEach(() => {
    setup = null;
  });

  it('MST-11 menampilkan banner dengan tautan ke Penyiapan awal selama langkah wajib belum selesai', () => {
    setup = { done: 2, total: 5 };
    render(<Beranda />);

    expect(screen.getByText(/2 dari 5 langkah wajib/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Buka Penyiapan awal' })).toHaveAttribute(
      'href',
      '/settings/setup',
    );
  });

  it('MST-11 tidak menampilkan banner bila semua langkah wajib selesai', () => {
    render(<Beranda />);

    expect(screen.queryByText(/Penyiapan awal klinik belum selesai/)).not.toBeInTheDocument();
  });
});
