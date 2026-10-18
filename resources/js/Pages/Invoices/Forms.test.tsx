import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import CashClosingForm from '@/Pages/CashClosings/Form';
import PaymentForm from '@/Pages/Invoices/PaymentForm';
import RefundForm from '@/Pages/Invoices/RefundForm';
import VoidForm from '@/Pages/Invoices/VoidForm';

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
    url: '/invoices/I1/payments/create',
    props: {
      auth: { user: { id: 'U1', name: 'Kasir Uji', roles: ['kasir'] }, permissions: [] },
      clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' },
      flash: { success: null, error: null },
      required: [],
    },
  }),
}));

const invoice = {
  id: 'I1',
  number: 'INV-2025-00001',
  lifecycle: 'issued',
  status: 'unpaid',
  patient_name: 'Budi Santoso',
  medical_record_no: 'RM-000001',
  total_amount: 500000,
  paid_amount: 0,
  balance_amount: 500000,
};

const methods = [
  { value: 'M1', label: 'Tunai', type: 'cash' },
  { value: 'M2', label: 'QRIS', type: 'qris' },
];

beforeEach(() => {
  post.mockReset();
});

describe('KSR-03 formulir pembayaran', () => {
  it('UI-08 jumlah selalu bilangan bulat berformat rupiah; metode kosong tidak dikirim ke server', async () => {
    render(
      <PaymentForm invoice={invoice} methods={methods} allowPay idempotencyKey="kunci-uji-0001" />,
    );
    await userEvent.clear(screen.getByRole('textbox', { name: /Jumlah/ }));
    await userEvent.type(screen.getByRole('textbox', { name: /Jumlah/ }), '1500.50');
    expect(screen.getByRole('textbox', { name: /Jumlah/ })).toHaveValue('Rp 150.050');
    await userEvent.click(screen.getByRole('button', { name: 'Simpan pembayaran' }));

    expect(post).not.toHaveBeenCalled();
    expect(screen.getByText('Pilih metode pembayaran.')).toBeInTheDocument();
  });

  it('mengirim bilangan bulat dengan Idempotency-Key dan sisa tagihan sebagai jumlah awal', async () => {
    render(
      <PaymentForm invoice={invoice} methods={methods} allowPay idempotencyKey="kunci-uji-0001" />,
    );
    expect(screen.getByRole('textbox', { name: /Jumlah/ })).toHaveValue('Rp 500.000');

    await userEvent.selectOptions(
      screen.getByRole('combobox', { name: /Metode pembayaran/ }),
      'M2',
    );
    await userEvent.click(screen.getByRole('button', { name: 'Simpan pembayaran' }));

    // UI-09 pembayaran meminta konfirmasi yang menyebut nominal dan metode; baru setelah itu terkirim.
    expect(post).not.toHaveBeenCalled();
    expect(screen.getByRole('alertdialog')).toHaveTextContent('Pembayaran Rp 500.000 lewat');
    await userEvent.click(screen.getByRole('button', { name: 'Ya, catat' }));

    expect(post).toHaveBeenCalledTimes(1);
    const [url, body, options] = post.mock.calls[0] as [
      string,
      Record<string, unknown>,
      { headers: Record<string, string> },
    ];
    expect(url).toBe('/invoices/I1/payments');
    expect(body).toMatchObject({ payment_method_id: 'M2', type: 'pay', amount: 500000 });
    expect(options.headers['Idempotency-Key']).toBe('kunci-uji-0001');
  });

  it('pada draft hanya uang muka yang dapat dicatat', () => {
    render(
      <PaymentForm
        invoice={{ ...invoice, lifecycle: 'draft' }}
        methods={methods}
        allowPay={false}
        idempotencyKey="kunci-uji-0002"
      />,
    );
    const jenis = screen.getByRole('combobox', { name: /Jenis/ });
    expect(jenis).toHaveValue('dp');
    expect(screen.queryByRole('option', { name: 'Pembayaran' })).not.toBeInTheDocument();
  });
});

describe('UI-09 konfirmasi aksi uang', () => {
  it('pengembalian dana meminta konfirmasi dengan nominal sebelum terkirim', async () => {
    render(<RefundForm invoice={invoice} methods={methods} idempotencyKey="kunci-refund-01" />);
    await userEvent.selectOptions(
      screen.getByRole('combobox', { name: /Dikembalikan lewat metode/ }),
      'M1',
    );
    await userEvent.type(screen.getByRole('textbox', { name: /Jumlah/ }), '75000');
    await userEvent.type(
      screen.getByRole('textbox', { name: /Alasan pengembalian/ }),
      'Salah input',
    );
    await userEvent.click(screen.getByRole('button', { name: 'Catat pengembalian' }));

    expect(post).not.toHaveBeenCalled();
    expect(screen.getByRole('alertdialog')).toHaveTextContent('Mengembalikan Rp 75.000');
    await userEvent.click(screen.getByRole('button', { name: 'Ya, kembalikan' }));

    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0]?.[1]).toMatchObject({ amount: 75000, payment_method_id: 'M1' });
  });
});

describe('KSR-07 pembatalan tagihan', () => {
  it('alasan wajib sebelum mengirim', async () => {
    render(<VoidForm invoice={invoice} />);
    await userEvent.click(screen.getByRole('button', { name: 'Batalkan tagihan' }));

    expect(post).not.toHaveBeenCalled();
    expect(screen.getByText('Alasan pembatalan wajib diisi.')).toBeInTheDocument();
  });
});

describe('KSR-08 tutup kas', () => {
  const preview = { date: '2025-06-16', system_amount: 200000, payments: 2 };

  it('selisih ditampilkan dan catatan wajib bila ada selisih', async () => {
    render(<CashClosingForm preview={preview} />);
    await userEvent.type(screen.getByRole('textbox', { name: /Uang tunai fisik/ }), '190000');
    expect(screen.getByRole('status')).toHaveTextContent('Selisih kurang Rp 10.000.');

    await userEvent.click(screen.getByRole('button', { name: 'Tutup kas' }));
    expect(post).not.toHaveBeenCalled();
    expect(screen.getByText('Ada selisih kas; isi catatan penjelasannya.')).toBeInTheDocument();
  });

  it('tanpa selisih langsung terkirim sebagai bilangan bulat', async () => {
    render(<CashClosingForm preview={preview} />);
    await userEvent.type(screen.getByRole('textbox', { name: /Uang tunai fisik/ }), '200000');
    expect(screen.getByRole('status')).toHaveTextContent('Tidak ada selisih.');

    await userEvent.click(screen.getByRole('button', { name: 'Tutup kas' }));
    expect(post).not.toHaveBeenCalled();
    expect(screen.getByRole('alertdialog')).toHaveTextContent('tidak ada selisih');
    await userEvent.click(screen.getByRole('button', { name: 'Ya, tutup kas' }));

    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0]?.[1]).toEqual({ counted_amount: 200000, note: null });
  });

  it('UI-09 batal pada konfirmasi tidak mengirim apa pun', async () => {
    render(<CashClosingForm preview={preview} />);
    await userEvent.type(screen.getByRole('textbox', { name: /Uang tunai fisik/ }), '200000');
    await userEvent.click(screen.getByRole('button', { name: 'Tutup kas' }));
    await userEvent.click(screen.getByRole('button', { name: 'Batal' }));

    expect(post).not.toHaveBeenCalled();
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
  });
});
