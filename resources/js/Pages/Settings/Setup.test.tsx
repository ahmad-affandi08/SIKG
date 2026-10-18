import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import Setup from '@/Pages/Settings/Setup';

const post = vi.fn<(...args: unknown[]) => void>();

vi.mock('@inertiajs/react', () => ({
  router: {
    post: (...args: unknown[]) => {
      post(...args);
    },
  },
  Head: () => null,
  Link: ({ href, children }: { href: string; children: ReactNode }) => (
    <a href={href}>{children}</a>
  ),
  usePage: () => ({
    url: '/settings/setup',
    props: {
      auth: { user: { id: 'U1', name: 'Pemilik', roles: ['pemilik'] }, permissions: [] },
      clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' },
      flash: { success: null, error: null },
      required: [],
    },
  }),
}));

const steps = [
  {
    key: 'profile',
    title: 'Profil klinik',
    detail: 'Nama, alamat, dan nomor izin.',
    done: true,
    required: true,
    summary: 'Alamat dan nomor izin sudah diisi.',
    href: '/settings/profile',
    cta: 'Isi profil',
    import_href: null,
  },
  {
    key: 'procedures',
    title: 'Tarif tindakan',
    detail: 'Isi tarif lalu aktifkan.',
    done: false,
    required: true,
    summary: '0 aktif dari 22 tindakan.',
    href: '/settings/master/procedures',
    cta: 'Atur tarif',
    import_href: '/settings/master/procedures/import',
  },
  {
    key: 'users',
    title: 'Pengguna',
    detail: 'Tambahkan pengguna.',
    done: false,
    required: false,
    summary: '1 pengguna aktif.',
    href: '/settings/users',
    cta: 'Tambah pengguna',
    import_href: null,
  },
];

beforeEach(() => {
  post.mockReset();
});

describe('MST-10 penyiapan awal', () => {
  it('menampilkan kemajuan, status tiap langkah, dan tautan impor hanya bila ada', () => {
    render(<Setup steps={steps} required_total={2} required_done={1} />);

    expect(screen.getByText('1 dari 2 langkah wajib selesai.')).toBeInTheDocument();
    expect(screen.getByText('1. Profil klinik')).toBeInTheDocument();
    expect(screen.getAllByText('Selesai')).toHaveLength(1);
    expect(screen.getByText('Anjuran')).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: 'Impor Excel' })).toHaveLength(1);
    expect(screen.getByRole('link', { name: 'Impor Excel' })).toHaveAttribute(
      'href',
      '/settings/master/procedures/import',
    );
  });

  it('menyatakan klinik siap bila semua langkah wajib selesai', () => {
    render(<Setup steps={steps} required_total={2} required_done={2} />);

    expect(
      screen.getByText('Semua langkah wajib sudah selesai. Klinik siap dipakai.'),
    ).toBeInTheDocument();
  });

  it('data awal baru dimuat setelah konfirmasi', async () => {
    render(<Setup steps={steps} required_total={2} required_done={1} />);

    await userEvent.click(screen.getByRole('button', { name: 'Isi data awal' }));
    expect(post).not.toHaveBeenCalled();
    expect(screen.getByRole('alertdialog')).toHaveTextContent('nonaktif, harga Rp 0');

    await userEvent.click(screen.getByRole('button', { name: 'Ya, isi data awal' }));
    expect(post).toHaveBeenCalledWith('/settings/setup/starter-data');
  });
});
