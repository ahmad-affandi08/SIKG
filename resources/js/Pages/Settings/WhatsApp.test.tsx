import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import WhatsApp from '@/Pages/Settings/WhatsApp';

const put = vi.fn<(...args: unknown[]) => void>();
const post = vi.fn<(...args: unknown[]) => void>();

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
        post: (...args: unknown[]) => {
          post(...args);
        },
      };
    },
    Head: () => null,
    Link: ({ href, children }: { href: string; children: ReactNode }) => (
      <a href={href}>{children}</a>
    ),
    usePage: () => ({
      url: '/settings/whatsapp',
      props: {
        auth: {
          user: { id: 'U1', name: 'Admin Uji', roles: ['admin'] },
          permissions: ['setting.manage'],
        },
        clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' },
        flash: { success: null, error: null },
        required: [],
      },
    }),
  };
});

const providers = [
  {
    value: 'link',
    label: 'Tautan WhatsApp (manual, gratis)',
    description: 'Manual.',
    official: false,
    fields: [],
    required: [],
  },
  {
    value: 'meta',
    label: 'WhatsApp Resmi (Meta Cloud API)',
    description: 'Resmi.',
    official: true,
    fields: ['phone_number_id', 'token', 'language', 'staff_template'],
    required: ['phone_number_id', 'token'],
  },
  {
    value: 'fonnte',
    label: 'Fonnte (tidak resmi)',
    description: 'Tidak resmi.',
    official: false,
    fields: ['token'],
    required: ['token'],
  },
  {
    value: 'wablas',
    label: 'Wablas (tidak resmi)',
    description: 'Tidak resmi.',
    official: false,
    fields: ['base_url', 'token'],
    required: ['token', 'base_url'],
  },
  {
    value: 'waha',
    label: 'WAHA (tidak resmi, server sendiri)',
    description: 'Tidak resmi.',
    official: false,
    fields: ['base_url', 'token', 'session'],
    required: ['base_url'],
  },
] as const;

const settings: {
  provider: 'link' | 'meta' | 'fonnte' | 'wablas' | 'waha';
  phone_number_id: string | null;
  base_url: string | null;
  session: string | null;
  language: string;
  staff_template: string | null;
  has_token: boolean;
  ready: boolean;
} = {
  provider: 'link',
  phone_number_id: null,
  base_url: null,
  session: null,
  language: 'id',
  staff_template: null,
  has_token: false,
  ready: false,
};

function renderPage(overrides: Partial<typeof settings> = {}) {
  render(<WhatsApp providers={[...providers] as never} settings={{ ...settings, ...overrides }} />);
}

beforeEach(() => {
  put.mockReset();
  post.mockReset();
});

describe('SYS-05 pengaturan saluran WhatsApp', () => {
  it('menampilkan WhatsApp Resmi dan penyedia tidak resmi sebagai pilihan', () => {
    renderPage();

    expect(
      screen.getByRole('radio', { name: 'WhatsApp Resmi (Meta Cloud API)' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: 'Fonnte (tidak resmi)' })).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: 'Wablas (tidak resmi)' })).toBeInTheDocument();
    expect(
      screen.getByRole('radio', { name: 'WAHA (tidak resmi, server sendiri)' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: 'Tautan WhatsApp (manual, gratis)' })).toBeChecked();
    expect(screen.queryByText('Kredensial')).not.toBeInTheDocument();
  });

  it('memilih penyedia tidak resmi menampilkan peringatan risiko dan isian token', async () => {
    renderPage();

    await userEvent.click(screen.getByRole('radio', { name: 'Fonnte (tidak resmi)' }));

    expect(screen.getByText(/melanggar ketentuan layanan WhatsApp/)).toBeInTheDocument();
    expect(screen.getByLabelText(/Token akun Fonnte/)).toBeInTheDocument();
    expect(screen.queryByLabelText(/Alamat server/)).not.toBeInTheDocument();
  });

  it('WhatsApp Resmi menampilkan ID nomor telepon dan templat staf tanpa peringatan tidak resmi', async () => {
    renderPage();

    await userEvent.click(screen.getByRole('radio', { name: 'WhatsApp Resmi (Meta Cloud API)' }));

    expect(screen.getByLabelText(/ID nomor telepon/)).toBeInTheDocument();
    expect(screen.getByLabelText(/Nama templat pemberitahuan staf/)).toBeInTheDocument();
    expect(screen.queryByText(/melanggar ketentuan layanan WhatsApp/)).not.toBeInTheDocument();
  });

  it('isian wajib kosong ditolak di klien tanpa mengirim ke server', async () => {
    renderPage();
    await userEvent.click(screen.getByRole('radio', { name: 'Wablas (tidak resmi)' }));

    await userEvent.click(screen.getByRole('button', { name: 'Simpan pengaturan' }));

    expect(put).not.toHaveBeenCalled();
    expect(screen.getByText('Token wajib diisi.')).toBeInTheDocument();
    expect(screen.getByText('Alamat server wajib diisi.')).toBeInTheDocument();
  });

  it('alamat server tanpa https ditolak dan isian sah dikirim lewat PUT', async () => {
    renderPage();
    await userEvent.click(screen.getByRole('radio', { name: 'Wablas (tidak resmi)' }));
    await userEvent.type(screen.getByLabelText(/Token Wablas/), 'tk');
    await userEvent.type(screen.getByLabelText(/Alamat server/), 'http://tegal.wablas.com');

    await userEvent.click(screen.getByRole('button', { name: 'Simpan pengaturan' }));
    expect(put).not.toHaveBeenCalled();
    expect(screen.getByText('Alamat server harus diawali https://.')).toBeInTheDocument();
  });

  it('formulir pesan uji hanya muncul bila saluran sudah lengkap', () => {
    renderPage();
    expect(screen.queryByRole('form', { name: 'Kirim pesan uji' })).not.toBeInTheDocument();
  });

  it('saluran lengkap menampilkan formulir pesan uji', async () => {
    renderPage({ provider: 'fonnte', has_token: true, ready: true });

    expect(screen.getByRole('form', { name: 'Kirim pesan uji' })).toBeInTheDocument();
    await userEvent.type(screen.getByLabelText(/Nomor WhatsApp tujuan/), '081234567890');
    await userEvent.click(screen.getByRole('button', { name: 'Kirim pesan uji' }));
    expect(post).toHaveBeenCalledWith('/settings/whatsapp/test', expect.anything());
  });

  it('token lama tidak dianggap cukup saat penyedia berganti, dan dianggap cukup bila penyedia dan alamat sama', async () => {
    renderPage({ provider: 'fonnte', has_token: true, ready: true });

    await userEvent.click(screen.getByRole('radio', { name: 'Wablas (tidak resmi)' }));
    await userEvent.type(screen.getByLabelText(/Alamat server/), 'https://tegal.wablas.com');
    await userEvent.click(screen.getByRole('button', { name: 'Simpan pengaturan' }));
    expect(put).not.toHaveBeenCalled();
    expect(screen.getByText('Token wajib diisi.')).toBeInTheDocument();
  });

  it('penyedia sama dengan token tersimpan boleh dikirim tanpa mengisi token', async () => {
    renderPage({ provider: 'fonnte', has_token: true, ready: true });

    expect(screen.getByLabelText(/kosongkan bila tidak diganti/)).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Simpan pengaturan' }));
    expect(put).toHaveBeenCalledTimes(1);
  });
});
