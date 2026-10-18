import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { KeyboardEventHandler, ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { AppLayout } from '@/components/layout/AppLayout';
import type { NavItem } from '@/components/layout/navigation';

let permissions: string[] = [];
let currentUrl = '/dashboard';

vi.mock('@inertiajs/react', () => ({
  router: { post: vi.fn() },
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
    onClick?: () => void;
    onKeyDown?: KeyboardEventHandler;
  }) => (
    <a
      href={href}
      aria-current={rest['aria-current']}
      onClick={rest.onClick}
      onKeyDown={rest.onKeyDown}
    >
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

    expect(screen.getByRole('button', { name: 'Keluar' })).toBeInTheDocument();
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

describe('UI-07 menubar dengan menu turun', () => {
  const GROUPED: readonly NavItem[] = [
    { label: 'Beranda', href: '/dashboard' },
    { label: 'Pasien', href: '/patients', group: 'Layanan' },
    { label: 'Jadwal', href: '/schedule', group: 'Layanan' },
    { label: 'Pengguna', href: '/settings/users', group: 'Pengaturan' },
  ];

  beforeEach(() => {
    currentUrl = '/dashboard';
    permissions = [];
  });

  it('item berkelompok disembunyikan di menu turun sampai judul kelompok diklik', async () => {
    render(<AppLayout navItems={GROUPED}>isi</AppLayout>);
    expect(screen.queryByRole('link', { name: 'Pasien' })).not.toBeInTheDocument();

    const group = screen.getByRole('button', { name: /Layanan/ });
    expect(group).toHaveAttribute('aria-expanded', 'false');
    await userEvent.click(group);

    expect(group).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByRole('link', { name: 'Pasien' })).toHaveAttribute('href', '/patients');
    expect(screen.getByRole('link', { name: 'Jadwal' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Pengguna' })).not.toBeInTheDocument();
  });

  it('Escape menutup menu dan panah bawah membuka serta memindahkan fokus', async () => {
    render(<AppLayout navItems={GROUPED}>isi</AppLayout>);
    const group = screen.getByRole('button', { name: /Layanan/ });
    group.focus();
    await userEvent.keyboard('{ArrowDown}');

    expect(screen.getByRole('link', { name: 'Pasien' })).toHaveFocus();
    await userEvent.keyboard('{ArrowDown}');
    expect(screen.getByRole('link', { name: 'Jadwal' })).toHaveFocus();

    await userEvent.keyboard('{Escape}');
    expect(screen.queryByRole('link', { name: 'Jadwal' })).not.toBeInTheDocument();
  });

  it('menu terbuka berpindah saat kelompok lain disorot', async () => {
    render(<AppLayout navItems={GROUPED}>isi</AppLayout>);
    await userEvent.click(screen.getByRole('button', { name: /Layanan/ }));
    await userEvent.hover(screen.getByRole('button', { name: /Pengaturan/ }));

    expect(screen.getByRole('link', { name: 'Pengguna' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Pasien' })).not.toBeInTheDocument();
  });

  it('status bar memuat klinik, pengguna beserta peran, dan jam', () => {
    render(<AppLayout navItems={GROUPED}>isi</AppLayout>);
    const bar = screen.getByRole('contentinfo');

    expect(bar).toHaveTextContent('Klinik Uji');
    expect(bar).toHaveTextContent('Pengguna Uji (Kasir)');
    expect(bar.querySelector('time')).not.toBeNull();
  });
});
