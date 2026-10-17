import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import Password from '@/Pages/Account/Password';

const put = vi.fn<(...args: unknown[]) => void>();

vi.mock('@inertiajs/react', () => ({
  useForm: (initial: Record<string, string>) => {
    let data = { ...initial };
    return {
      get data() {
        return data;
      },
      errors: {},
      processing: false,
      setData: (key: string, value: string) => {
        data = { ...data, [key]: value };
      },
      put: (...args: unknown[]) => {
        put(...args);
      },
      reset: () => undefined,
    };
  },
  Head: () => null,
  Link: ({ href, children }: { href: string; children: ReactNode }) => (
    <a href={href}>{children}</a>
  ),
  usePage: () => ({
    url: '/account/password',
    props: {
      auth: { user: { id: 'U1', name: 'Pengguna Uji', roles: ['kasir'] }, permissions: [] },
      clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' },
      flash: { success: null, error: null },
      required: ['current_password', 'password'],
    },
  }),
}));

beforeEach(() => {
  put.mockReset();
});

describe('AUTH-06 halaman ganti kata sandi', () => {
  it('menampilkan peringatan bila kata sandi masih sementara', () => {
    render(<Password forced minLength={10} />);

    expect(screen.getByText(/Kata sandi Anda masih sementara/)).toBeInTheDocument();
  });

  it('menolak kata sandi lemah tanpa mengirim ke server dan menampilkan pesan Indonesia', async () => {
    render(<Password forced={false} minLength={10} />);

    await userEvent.click(screen.getByRole('button', { name: 'Ganti kata sandi' }));

    expect(put).not.toHaveBeenCalled();
    expect(screen.getByText('Kata sandi saat ini wajib diisi.')).toBeInTheDocument();
    expect(screen.getByText('Kata sandi baru wajib diisi.')).toBeInTheDocument();
  });
});
