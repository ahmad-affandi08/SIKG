import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { AppLayout } from '@/components/layout/AppLayout';
import type { NavItem } from '@/components/layout/navigation';

let permissions: string[] = [];

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({
    url: '/dashboard',
    props: {
      auth: { user: { id: 'U1', name: 'Pengguna Uji', roles: ['kasir'] }, permissions },
      clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' },
      flash: { success: null, error: null },
    },
  }),
  Link: ({
    href,
    children,
    ...rest
  }: {
    href: string;
    children: ReactNode;
    method?: string;
    as?: string;
    className?: string;
    'aria-current'?: 'page';
  }) => (
    <a href={href} aria-current={rest['aria-current']}>
      {children}
    </a>
  ),
}));

const MENU: readonly NavItem[] = [
  { label: 'Beranda', href: '/dashboard' },
  { label: 'Pasien', href: '/patients', permission: 'patient.view' },
  { label: 'Pengaturan', href: '/settings', permission: 'setting.manage' },
];

describe('AUTH-02 tata letak aplikasi', () => {
  beforeEach(() => {
    permissions = [];
  });

  it('menampilkan nama klinik, pengguna, dan menu utama yang dapat diakses pembaca layar', () => {
    render(<AppLayout navItems={MENU}>isi</AppLayout>);

    expect(screen.getByText('Klinik Uji')).toBeInTheDocument();
    expect(screen.getByText('Pengguna Uji')).toBeInTheDocument();
    expect(screen.getByRole('navigation', { name: 'Menu utama' })).toBeInTheDocument();
    expect(screen.getByText('isi')).toBeInTheDocument();
  });

  it('hanya menampilkan menu yang izinnya dimiliki pengguna', () => {
    permissions = ['patient.view'];
    render(<AppLayout navItems={MENU}>isi</AppLayout>);

    const nav = screen.getByRole('navigation', { name: 'Menu utama' });
    expect(nav).toHaveTextContent('Beranda');
    expect(nav).toHaveTextContent('Pasien');
    expect(nav).not.toHaveTextContent('Pengaturan');
  });

  it('menandai halaman aktif untuk pembaca layar', () => {
    render(<AppLayout navItems={MENU}>isi</AppLayout>);

    expect(screen.getByRole('link', { name: 'Beranda' })).toHaveAttribute('aria-current', 'page');
  });

  it('menyediakan tombol keluar', () => {
    render(<AppLayout navItems={MENU}>isi</AppLayout>);

    expect(screen.getByRole('link', { name: 'Keluar' })).toBeInTheDocument();
  });
});
