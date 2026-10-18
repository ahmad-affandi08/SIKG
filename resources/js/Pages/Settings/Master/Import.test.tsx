import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { importFileIssue } from '@/features/master/schema';
import Import from '@/Pages/Settings/Master/Import';

const post = vi.fn<(...args: unknown[]) => void>();

vi.mock('@inertiajs/react', () => ({
  useForm: (initial: Record<string, unknown>) => {
    const state = { data: { ...initial } as Record<string, unknown> };
    return {
      get data() {
        return state.data;
      },
      setData: (key: string, value: unknown) => {
        state.data = { ...state.data, [key]: value };
      },
      post: (...args: unknown[]) => {
        post(...args);
      },
      errors: {},
      processing: false,
    };
  },
  Head: () => null,
  Link: ({ href, children }: { href: string; children: ReactNode }) => (
    <a href={href}>{children}</a>
  ),
  router: { post: vi.fn(), visit: vi.fn() },
  usePage: () => ({
    url: '/settings/master/procedures/import',
    props: {
      auth: { user: { id: 'U1', name: 'Admin', roles: ['admin'] }, permissions: [] },
      clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' },
      flash: { success: null, error: null },
      required: [],
    },
  }),
}));

const entity = {
  key: 'procedures',
  title: 'Katalog tindakan dan tarif',
  import_key: 'code',
  warning: null,
  home: '/settings/master/procedures',
  fields: [
    {
      key: 'code',
      label: 'Kode',
      type: 'text' as const,
      required: true,
      options: [],
      max: 30,
      help: null,
    },
  ],
};

const preview = {
  token: '01ABCDEFGHJKMNPQRSTVWXYZ12',
  total_rows: 5,
  valid_rows: 3,
  existing_rows: [4],
  existing_count: 1,
  error_rows: 1,
  errors: [{ row: 6, messages: ['Tarif standar harus berupa bilangan bulat tanpa pecahan.'] }],
};

beforeEach(() => {
  post.mockReset();
});

describe('MST-09 impor Excel master data', () => {
  it('menampilkan langkah, tautan templat, dan memeriksa berkas sebelum diunggah', async () => {
    render(<Import entity={entity} preview={null} />);

    expect(screen.getByRole('link', { name: 'Unduh templat Excel' })).toHaveAttribute(
      'href',
      '/settings/master/procedures/import/template',
    );

    await userEvent.click(screen.getByRole('button', { name: 'Unggah dan pratinjau' }));
    expect(screen.getByText('Pilih berkas Excel (.xlsx) atau CSV.')).toBeInTheDocument();
    expect(post).not.toHaveBeenCalled();
  });

  it('pratinjau menampilkan ringkasan dan galat per baris', () => {
    render(<Import entity={entity} preview={preview} />);

    expect(screen.getByText('Siap diimpor').nextSibling).toHaveTextContent('3');
    expect(screen.getByText(/dilewati karena kode-nya sudah ada \(baris 4\)/)).toBeInTheDocument();
    expect(screen.getByText(/Tarif standar harus berupa bilangan bulat/)).toBeInTheDocument();
  });

  it('konfirmasi impor meminta persetujuan sebelum mengirim', async () => {
    render(<Import entity={entity} preview={preview} />);

    await userEvent.click(screen.getByRole('button', { name: 'Impor 3 baris' }));
    expect(post).not.toHaveBeenCalled();
    expect(screen.getByRole('alertdialog')).toHaveTextContent('3 baris akan ditambahkan');

    await userEvent.click(screen.getByRole('button', { name: 'Ya, impor' }));
    expect(post).toHaveBeenCalledWith(`/settings/master/procedures/import/${preview.token}/commit`);
  });

  it('tombol impor mati dan ada peringatan bila tidak ada baris sah', () => {
    render(<Import entity={entity} preview={{ ...preview, valid_rows: 0, error_rows: 5 }} />);

    expect(screen.getByRole('button', { name: 'Impor 0 baris' })).toBeDisabled();
    expect(screen.getByText(/Tidak ada baris yang dapat diimpor/)).toBeInTheDocument();
  });

  it('importFileIssue menolak jenis dan ukuran yang salah', () => {
    expect(importFileIssue(null)).not.toBeNull();
    expect(importFileIssue(new File(['x'], 'a.pdf'))).toBe(
      'Berkas harus berformat .xlsx atau .csv.',
    );
    expect(importFileIssue(new File(['x'], 'a.xlsx'))).toBeNull();
    const besar = new File([new Uint8Array(2 * 1024 * 1024 + 1)], 'b.csv');
    expect(importFileIssue(besar)).toBe('Berkas maksimal 2 MB.');
  });
});
