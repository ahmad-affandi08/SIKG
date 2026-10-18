import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';

import { MoneyField, digitsOnly, displayRupiah } from '@/components/ui/MoneyField';
import { RequiredProvider } from '@/lib/useRequired';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({
    props: { clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' }, required: ['amount'] },
  }),
}));

function Harness({ initial = '', onChange }: { initial?: string; onChange?: (v: string) => void }) {
  const [value, setValue] = useState(initial);
  return (
    <RequiredProvider>
      <MoneyField
        field="amount"
        label="Jumlah"
        value={value}
        onValueChange={(digits) => {
          setValue(digits);
          onChange?.(digits);
        }}
      />
    </RequiredProvider>
  );
}

describe('UI-08 isian uang berformat rupiah', () => {
  it('menampilkan Rp dan pemisah ribuan selama diketik, nilai tetap angka polos', async () => {
    const onChange = vi.fn();
    render(<Harness onChange={onChange} />);
    const input = screen.getByRole('textbox', { name: /Jumlah/ });

    await userEvent.type(input, '1250000');

    expect(input).toHaveValue('Rp 1.250.000');
    expect(onChange).toHaveBeenLastCalledWith('1250000');
  });

  it('menampilkan nilai awal terformat dan menolak huruf serta tanda lain', async () => {
    render(<Harness initial="500000" />);
    const input = screen.getByRole('textbox', { name: /Jumlah/ });
    expect(input).toHaveValue('Rp 500.000');

    await userEvent.type(input, 'abc-,.');

    expect(input).toHaveValue('Rp 500.000');
  });

  it('menghapus angka sampai kosong mengembalikan isian kosong', async () => {
    const onChange = vi.fn();
    render(<Harness initial="12" onChange={onChange} />);
    const input = screen.getByRole('textbox', { name: /Jumlah/ });

    await userEvent.type(input, '{backspace}{backspace}');

    expect(input).toHaveValue('');
    expect(onChange).toHaveBeenLastCalledWith('');
  });

  it('menempel teks berformat dan membuang nol di depan', async () => {
    const onChange = vi.fn();
    render(<Harness onChange={onChange} />);
    const input = screen.getByRole('textbox', { name: /Jumlah/ });

    await userEvent.click(input);
    await userEvent.paste('Rp 0.002.500');

    expect(input).toHaveValue('Rp 2.500');
    expect(onChange).toHaveBeenLastCalledWith('2500');
  });

  it('menyisipkan angka di tengah tanpa melompatkan kursor ke akhir', async () => {
    render(<Harness initial="1000" />);
    const input: HTMLInputElement = screen.getByRole('textbox', { name: /Jumlah/ });

    // "Rp 1.000": kursor di antara "1" dan ".000", lalu ketik 5 menjadi 15.000 dan 5 berikutnya sesudahnya.
    input.setSelectionRange(4, 4);
    await userEvent.type(input, '5', { initialSelectionStart: 4, initialSelectionEnd: 4 });
    expect(input).toHaveValue('Rp 15.000');
    expect(input.selectionStart).toBe(5);
  });

  it('membatasi panjang sehingga tetap bilangan bulat aman', async () => {
    render(<Harness />);
    const input = screen.getByRole('textbox', { name: /Jumlah/ });

    await userEvent.type(input, '12345678901234567890');

    expect(input).toHaveValue('Rp 123.456.789.012.345');
  });

  it('memberi tanda wajib dari server dan placeholder Rp 0', () => {
    render(<Harness />);
    const input = screen.getByRole('textbox', { name: /Jumlah/ });
    expect(input).toHaveAttribute('aria-required', 'true');
    expect(input).toHaveAttribute('placeholder', 'Rp 0');
    expect(input).toHaveAttribute('inputmode', 'numeric');
  });

  it('fungsi bantu: displayRupiah dan digitsOnly', () => {
    expect(displayRupiah('')).toBe('');
    expect(displayRupiah('0')).toBe('Rp 0');
    expect(displayRupiah('1250000')).toBe('Rp 1.250.000');
    expect(digitsOnly('Rp 1.250.000')).toBe('1250000');
    expect(digitsOnly('000')).toBe('0');
    expect(digitsOnly('')).toBe('');
  });
});
