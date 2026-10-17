import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import RolesIndex from '@/Pages/Settings/Roles/Index';

const put = vi.fn<(...args: unknown[]) => void>();
const post = vi.fn<(...args: unknown[]) => void>();

vi.mock('@inertiajs/react', () => ({
  router: {
    put: (...args: unknown[]) => {
      put(...args);
    },
    post: (...args: unknown[]) => {
      post(...args);
    },
  },
  Head: () => null,
  Link: ({ href, children }: { href: string; children: ReactNode }) => (
    <a href={href}>{children}</a>
  ),
  usePage: () => ({
    url: '/settings/roles',
    props: {
      auth: { user: { id: 'U1', name: 'Pemilik Uji', roles: ['pemilik'] }, permissions: [] },
      clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' },
      flash: { success: null, error: null },
    },
  }),
}));

const modules = [
  {
    name: 'Pasien',
    permissions: [
      { value: 'patient.view', label: 'Melihat data pasien' },
      { value: 'patient.create', label: 'Mendaftarkan pasien baru' },
    ],
  },
  {
    name: 'Pengguna dan sistem',
    permissions: [{ value: 'user.manage', label: 'Mengelola pengguna dan reset kata sandi' }],
  },
];

const roles = [
  {
    value: 'pemilik',
    label: 'Pemilik',
    permissions: ['patient.view', 'user.manage'],
    defaults: ['patient.view', 'user.manage'],
    customized: false,
    users: 1,
    editable: true,
    locked: ['user.manage'],
  },
  {
    value: 'kasir',
    label: 'Kasir',
    permissions: ['patient.view'],
    defaults: ['patient.view'],
    customized: false,
    users: 3,
    editable: true,
    locked: [],
  },
];

beforeEach(() => {
  put.mockReset();
  post.mockReset();
});

describe('AUTH-07 halaman peran dan izin', () => {
  it('menampilkan izin peran, tombol simpan nonaktif sampai ada perubahan', async () => {
    render(<RolesIndex roles={roles} modules={modules} />);

    expect(screen.getByRole('button', { name: 'Simpan perubahan' })).toBeDisabled();
    await userEvent.click(screen.getByRole('button', { name: /Kasir/ }));
    expect(screen.getByRole('checkbox', { name: /Melihat data pasien/ })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: /Mendaftarkan pasien baru/ })).not.toBeChecked();
  });

  it('mengirim daftar izin setelah dicentang dan menandai yang berbeda dari bawaan', async () => {
    render(<RolesIndex roles={roles} modules={modules} />);
    await userEvent.click(screen.getByRole('button', { name: /Kasir/ }));

    await userEvent.click(screen.getByRole('checkbox', { name: /Mendaftarkan pasien baru/ }));
    expect(screen.getByText(/\+ Mendaftarkan pasien baru/)).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Simpan perubahan' }));

    expect(put).toHaveBeenCalledTimes(1);
    expect(put.mock.calls[0]?.[0]).toBe('/settings/roles/kasir');
    expect(put.mock.calls[0]?.[1]).toEqual({ permissions: ['patient.view', 'patient.create'] });
  });

  it('izin terkunci pada peran Pemilik tidak dapat dicabut', () => {
    render(<RolesIndex roles={roles} modules={modules} />);

    expect(screen.getByRole('checkbox', { name: /Mengelola pengguna/ })).toBeDisabled();
    expect(screen.getByRole('checkbox', { name: /Mengelola pengguna/ })).toBeChecked();
  });

  it('peran yang tidak boleh diubah menampilkan peringatan dan tanpa tombol simpan', () => {
    render(<RolesIndex roles={[{ ...roles[0]!, editable: false }, roles[1]!]} modules={modules} />);

    expect(screen.getByText(/Hanya Pemilik yang dapat mengubah/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Simpan perubahan' })).not.toBeInTheDocument();
  });
});
