import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import ServiceProfile from '@/Pages/Settings/ServiceProfile';

const put = vi.fn<(...args: unknown[]) => void>();

vi.mock('@inertiajs/react', async () => {
  const { useState } = await import('react');
  return {
    useForm: (initial: Record<string, string>) => {
      const [data, setDataState] = useState({ ...initial });
      return {
        data,
        errors: {},
        processing: false,
        setData: (key: string, value: string) => {
          setDataState((previous) => ({ ...previous, [key]: value }));
        },
        put: (...args: unknown[]) => {
          put(...args);
        },
      };
    },
    Head: () => null,
    Link: ({ href, children }: { href: string; children: ReactNode }) => (
      <a href={href}>{children}</a>
    ),
    usePage: () => ({
      url: '/settings/service-profile',
      props: {
        auth: {
          user: { id: 'U1', name: 'Admin Uji', roles: ['admin'] },
          permissions: ['setting.manage'],
        },
        clinic: {
          name: 'Klinik Uji',
          timezone: 'Asia/Jakarta',
          profile: 'dental',
          dental: true,
          doctor_title: 'Dokter gigi',
        },
        flash: { success: null, error: null },
        required: [],
      },
    }),
  };
});

const options = [
  { value: 'dental', label: 'Klinik gigi', description: 'Odontogram tersedia.' },
  { value: 'general', label: 'Klinik umum', description: 'Tanpa odontogram.' },
];

describe('KLU-01 halaman Profil layanan', () => {
  beforeEach(() => {
    put.mockClear();
  });

  it('menampilkan pilihan dengan profil saat ini terpilih dan tombol simpan nonaktif', () => {
    render(<ServiceProfile current="dental" options={options} />);

    expect(screen.getByRole('radio', { name: 'Klinik gigi' })).toBeChecked();
    expect(screen.getByRole('radio', { name: 'Klinik umum' })).not.toBeChecked();
    expect(screen.getByRole('button', { name: 'Simpan profil layanan' })).toBeDisabled();
  });

  it('mengganti profil menampilkan peringatan bahwa data tidak dihapus lalu mengirim pilihan', async () => {
    const user = userEvent.setup();
    render(<ServiceProfile current="dental" options={options} />);

    await user.click(screen.getByRole('radio', { name: 'Klinik umum' }));
    expect(screen.getByText(/tidak dihapus/)).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Simpan profil layanan' }));

    expect(put).toHaveBeenCalledWith('/settings/service-profile', expect.anything());
  });
});
