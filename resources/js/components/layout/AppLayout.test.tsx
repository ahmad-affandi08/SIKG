import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { AppLayout } from '@/components/layout/AppLayout';
import type { NavItem } from '@/components/layout/navigation';

let permissions: string[] = [];
let currentUrl = '/dashboard';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({
    url: currentUrl,
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

  it('menampilkan jejak halaman di header dan menandai halaman sekarang', () => {
    currentUrl = '/patients';
    permissions = ['patient.view'];
    render(<AppLayout navItems={MENU}>isi</AppLayout>);

    const trail = screen.getByRole('navigation', { name: 'Jejak halaman' });
    expect(trail).toHaveTextContent('Beranda');
    expect(trail).toHaveTextContent('Pasien');
    expect(trail.querySelector('[aria-current="page"]')).toHaveTextContent('Pasien');
  });
});

describe('UI-06 sidebar dapat ditutup dan dibuka', () => {
  const sidebar = () => document.getElementById('sidebar');

  beforeEach(() => {
    currentUrl = '/dashboard';
    permissions = [];
    document.cookie = 'sikg_sidebar_collapsed=; path=/; max-age=0';
    vi.stubGlobal('matchMedia', (query: string) => ({
      matches: query.includes('min-width: 1024px'),
      addEventListener: () => undefined,
      removeEventListener: () => undefined,
    }));
  });
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('di desktop terbuka bawaannya; tombol header menutup lalu membukanya lagi', async () => {
    render(<AppLayout navItems={MENU}>isi</AppLayout>);
    const toggle = screen.getByRole('button', { name: 'Tutup menu samping' });
    expect(toggle).toHaveAttribute('aria-expanded', 'true');
    expect(sidebar()).not.toHaveAttribute('inert');

    await userEvent.click(toggle);
    expect(screen.getByRole('button', { name: 'Buka menu samping' })).toHaveAttribute(
      'aria-expanded',
      'false',
    );
    expect(sidebar()).toHaveAttribute('inert');
    expect(document.cookie).toContain('sikg_sidebar_collapsed=1');

    await userEvent.click(screen.getByRole('button', { name: 'Buka menu samping' }));
    expect(sidebar()).not.toHaveAttribute('inert');
    expect(document.cookie).toContain('sikg_sidebar_collapsed=0');
  });

  it('pilihan menutup diingat lewat cookie saat halaman dimuat ulang', () => {
    document.cookie = 'sikg_sidebar_collapsed=1; path=/';
    render(<AppLayout navItems={MENU}>isi</AppLayout>);

    expect(screen.getByRole('button', { name: 'Buka menu samping' })).toBeInTheDocument();
    expect(sidebar()).toHaveAttribute('inert');
  });
});
