import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { PrescriptionForm } from '@/features/pharmacy/components/PrescriptionForm';
import { PrescriptionPanel } from '@/features/pharmacy/components/PrescriptionPanel';
import type { PrescriptionData } from '@/features/pharmacy/components/PrescriptionPanel';

const post = vi.fn<(...args: unknown[]) => void>();
let flash: Record<string, unknown> = {};

vi.mock('@inertiajs/react', () => ({
  router: {
    post: (...args: unknown[]) => {
      post(...args);
    },
  },
  Link: ({ href, children }: { href: string; children: ReactNode }) => (
    <a href={href}>{children}</a>
  ),
  usePage: () => ({
    props: { clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' }, flash },
  }),
}));

const issued: PrescriptionData = {
  id: 'R1',
  status: 'issued',
  void_reason: null,
  issued_at: '2026-01-01T03:00:00+00:00',
  items: [
    {
      id: 'I1',
      medicine_id: 'M1',
      name: 'Amoksisilin 500 mg',
      dose: '1 kapsul',
      frequency: '3 kali sehari',
      duration: '5 hari',
      instructions: 'Setelah makan',
      quantity: 15,
      dispense_in_clinic: true,
      unit_price_amount: 2000,
    },
  ],
};

function renderPanel(overrides: Partial<Parameters<typeof PrescriptionPanel>[0]> = {}) {
  render(
    <PrescriptionPanel
      encounterId="E1"
      prescriptions={[issued]}
      canCreate
      canVoid
      canPrint
      {...overrides}
    />,
  );
}

function renderForm(canOverrideStock = false) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(
    <QueryClientProvider client={client}>
      <PrescriptionForm encounterId="E1" canOverrideStock={canOverrideStock} />
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  post.mockReset();
  flash = {};
});

describe('OBT-01 daftar resep pada kunjungan', () => {
  it('menampilkan butir resep, harga jual di klinik sebagai rupiah, dan tautan cetak', () => {
    renderPanel();
    expect(screen.getByText('Amoksisilin 500 mg')).toBeInTheDocument();
    expect(screen.getByText(/dijual di klinik Rp\s?30\.000/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Cetak resep' })).toHaveAttribute(
      'href',
      '/prescriptions/R1/print',
    );
  });

  it('menautkan ke halaman resep baru, bukan menampilkan formulir di bawah daftar', () => {
    renderPanel();
    expect(screen.getByRole('link', { name: 'Resep baru' })).toHaveAttribute(
      'href',
      '/encounters/E1/prescriptions/create',
    );
    expect(screen.queryByRole('form', { name: 'Buat resep' })).not.toBeInTheDocument();
  });

  it('menyembunyikan tautan baru dan tombol batal bila tidak berhak', () => {
    renderPanel({ canCreate: false, canVoid: false, canPrint: false });
    expect(screen.queryByRole('link', { name: 'Resep baru' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Cetak resep' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Batalkan resep' })).not.toBeInTheDocument();
  });
});

describe('OBT-01 formulir resep baru', () => {
  it('menolak resep tanpa nama obat atau dosis dan tidak mengirim ke server', async () => {
    renderForm();
    await userEvent.click(screen.getByRole('button', { name: 'Simpan resep' }));
    expect(post).not.toHaveBeenCalled();
    expect(
      screen.getByText('Pilih obat dari katalog atau tulis nama obat, salah satu.'),
    ).toBeInTheDocument();
    expect(screen.getByText('Dosis wajib diisi.')).toBeInTheDocument();
  });

  it('mengirim obat di luar katalog dengan jumlah bilangan bulat', async () => {
    renderForm();
    await userEvent.type(screen.getByLabelText('Atau tulis nama obat (di luar katalog)'), 'Obat X');
    await userEvent.type(screen.getByLabelText('Dosis'), '1 tablet');
    await userEvent.type(screen.getByLabelText('Frekuensi'), '2 kali sehari');
    await userEvent.type(screen.getByLabelText('Durasi'), '3 hari');
    await userEvent.click(screen.getByRole('button', { name: 'Simpan resep' }));

    expect(post).toHaveBeenCalledTimes(1);
    const [url, body] = post.mock.calls[0] as [string, { items: Record<string, unknown>[] }];
    expect(url).toBe('/encounters/E1/prescriptions');
    expect(body.items[0]).toMatchObject({
      medicine_id: null,
      free_text_name: 'Obat X',
      quantity: 1,
      dispense_in_clinic: false,
    });
  });

  it('menyediakan tautan batal kembali ke tab resep kunjungan', () => {
    renderForm();
    expect(screen.getByRole('link', { name: 'Batal' })).toHaveAttribute(
      'href',
      '/encounters/E1?tab=prescriptions',
    );
  });
});

describe('OBT-02 peringatan alergi', () => {
  it('menampilkan peringatan dari server dan meminta alasan konfirmasi dokter', () => {
    flash = {
      error: 'Pasien memiliki alergi terhadap Penisilin.',
      error_code: 'prescription.allergy_warning',
    };
    renderForm();
    expect(screen.getByRole('status')).toHaveTextContent(
      'Pasien memiliki alergi terhadap Penisilin.',
    );
    expect(
      screen.getByLabelText('Alasan tetap meresepkan (konfirmasi dokter)'),
    ).toBeInTheDocument();
  });
});

describe('OBT-04 stok tidak cukup', () => {
  it('menampilkan kolom override hanya untuk yang berhak', () => {
    flash = { error: 'Stok tidak cukup.', error_code: 'stock.insufficient' };
    renderForm(false);
    expect(screen.queryByLabelText('Alasan override stok (Admin)')).not.toBeInTheDocument();
  });

  it('menampilkan kolom override untuk Admin', () => {
    flash = { error: 'Stok tidak cukup.', error_code: 'stock.insufficient' };
    renderForm(true);
    expect(screen.getByLabelText('Alasan override stok (Admin)')).toBeInTheDocument();
  });
});
