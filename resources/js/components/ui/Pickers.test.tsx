import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { DatePicker } from '@/components/ui/DatePicker';
import { DateRangePicker } from '@/components/ui/DateRangePicker';
import type { DateRange } from '@/components/ui/DateRangePicker';
import { TimePicker } from '@/components/ui/TimePicker';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' } } }),
}));

// Waktu dibekukan pada hari Senin di zona klinik (Asia/Jakarta).
beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] });
  vi.setSystemTime(new Date('2025-06-16T03:00:00Z'));
});
afterEach(() => {
  vi.useRealTimers();
});

function DateHarness({ initial = '', ...rest }: { initial?: string; min?: string; max?: string }) {
  const [value, setValue] = useState(initial);
  return (
    <>
      <DatePicker label="Tanggal lahir" value={value} onChange={setValue} {...rest} />
      <output data-testid="nilai">{value}</output>
    </>
  );
}

function RangeHarness() {
  const [value, setValue] = useState<DateRange>({ from: '', to: '' });
  return (
    <>
      <DateRangePicker label="Periode" value={value} onChange={setValue} />
      <output data-testid="nilai">{`${value.from}|${value.to}`}</output>
    </>
  );
}

function TimeHarness({ initial = '' }: { initial?: string }) {
  const [value, setValue] = useState(initial);
  return (
    <>
      <TimePicker label="Jam mulai" value={value} onChange={setValue} />
      <output data-testid="nilai">{value}</output>
    </>
  );
}

const nilai = () => screen.getByTestId('nilai').textContent;
const user = () => userEvent.setup({ advanceTimers: () => undefined });

describe('UI-04 DatePicker', () => {
  it('mengetik angka disusun jadi DD/MM/YYYY dan menghasilkan YYYY-MM-DD', async () => {
    render(<DateHarness />);
    const input = screen.getByLabelText('Tanggal lahir');
    await user().type(input, '05011990');

    expect(input).toHaveValue('05/01/1990');
    expect(nilai()).toBe('1990-01-05');
  });

  it('tanggal yang tidak ada di kalender ditolak dengan pesan dan nilai kosong', async () => {
    render(<DateHarness />);
    await user().type(screen.getByLabelText('Tanggal lahir'), '31042024');

    expect(screen.getByRole('alert')).toHaveTextContent('Tanggal tidak valid.');
    expect(nilai()).toBe('');
  });

  it('isian setengah jadi saat ditinggalkan meminta format lengkap', async () => {
    render(<DateHarness />);
    const u = user();
    await u.type(screen.getByLabelText('Tanggal lahir'), '0501');
    await u.tab();

    expect(screen.getByRole('alert')).toHaveTextContent('Isi tanggal dengan format DD/MM/YYYY.');
  });

  it('memilih dari kalender mengisi isian dan menutup panel', async () => {
    render(<DateHarness initial="2025-06-10" />);
    const u = user();
    expect(screen.getByLabelText('Tanggal lahir')).toHaveValue('10/06/2025');

    await u.click(screen.getByRole('button', { name: 'Pilih tanggal: Tanggal lahir' }));
    const dialog = screen.getByRole('dialog', { name: 'Kalender: Tanggal lahir' });
    await u.click(within(dialog).getByRole('button', { name: '20 Juni 2025' }));

    expect(nilai()).toBe('2025-06-20');
    expect(screen.getByLabelText('Tanggal lahir')).toHaveValue('20/06/2025');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('pekan dimulai Senin dan hari ini diberi penanda', async () => {
    render(<DateHarness />);
    await user().click(screen.getByRole('button', { name: 'Pilih tanggal: Tanggal lahir' }));

    const headers = screen.getAllByRole('columnheader').map((h) => h.getAttribute('aria-label'));
    expect(headers).toEqual(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']);
    expect(screen.getByRole('button', { name: '16 Juni 2025' })).toHaveAttribute(
      'aria-current',
      'date',
    );
  });

  it('batas min dan max mematikan tanggal di luarnya dan menolak ketikan di luar batas', async () => {
    render(<DateHarness max="2025-06-16" />);
    const u = user();
    await u.click(screen.getByRole('button', { name: 'Pilih tanggal: Tanggal lahir' }));
    expect(screen.getByRole('button', { name: '17 Juni 2025' })).toBeDisabled();
    expect(screen.getByRole('button', { name: '16 Juni 2025' })).toBeEnabled();
    await u.keyboard('{Escape}');

    await u.type(screen.getByLabelText('Tanggal lahir'), '17062025');
    expect(screen.getByRole('alert')).toHaveTextContent('Tanggal di luar batas yang diizinkan.');
    expect(nilai()).toBe('');
  });

  it('papan ketik: panah memindahkan fokus, Enter memilih, Escape menutup', async () => {
    render(<DateHarness initial="2025-06-16" />);
    const u = user();
    await u.click(screen.getByRole('button', { name: 'Pilih tanggal: Tanggal lahir' }));
    expect(screen.getByRole('button', { name: '16 Juni 2025' })).toHaveFocus();

    await u.keyboard('{ArrowRight}');
    expect(screen.getByRole('button', { name: '17 Juni 2025' })).toHaveFocus();
    await u.keyboard('{ArrowDown}');
    expect(screen.getByRole('button', { name: '24 Juni 2025' })).toHaveFocus();
    await u.keyboard('{PageDown}');
    expect(screen.getByRole('button', { name: '24 Juli 2025' })).toHaveFocus();
    await u.keyboard('{Enter}');
    expect(nilai()).toBe('2025-07-24');

    await u.click(screen.getByRole('button', { name: 'Pilih tanggal: Tanggal lahir' }));
    await u.keyboard('{Escape}');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('tombol Hari ini dan Hapus', async () => {
    render(<DateHarness initial="2020-01-01" />);
    const u = user();
    await u.click(screen.getByRole('button', { name: 'Pilih tanggal: Tanggal lahir' }));
    await u.click(screen.getByRole('button', { name: 'Hari ini' }));
    expect(nilai()).toBe('2025-06-16');

    await u.click(screen.getByRole('button', { name: 'Pilih tanggal: Tanggal lahir' }));
    await u.click(screen.getByRole('button', { name: 'Hapus' }));
    expect(nilai()).toBe('');
    expect(screen.getByLabelText('Tanggal lahir')).toHaveValue('');
  });

  it('isian wajib diberi tanda bintang dan aria-required', () => {
    render(<DatePicker label="Tanggal" value="" onChange={() => undefined} required />);
    expect(screen.getByRole('textbox', { name: /Tanggal/ })).toHaveAttribute(
      'aria-required',
      'true',
    );
    expect(screen.getByText('*')).toHaveAttribute('aria-hidden', 'true');
  });
});

describe('UI-04 DateRangePicker', () => {
  it('dua klik menentukan awal dan akhir lalu menutup panel', async () => {
    render(<RangeHarness />);
    const u = user();
    await u.click(screen.getByRole('button', { name: 'Pilih rentang tanggal: Periode' }));
    await u.click(screen.getByRole('button', { name: '10 Juni 2025' }));
    expect(nilai()).toBe('2025-06-10|');
    await u.click(screen.getByRole('button', { name: '14 Juni 2025' }));

    expect(nilai()).toBe('2025-06-10|2025-06-14');
    expect(screen.getByLabelText('Dari')).toHaveValue('10/06/2025');
    expect(screen.getByLabelText('Sampai')).toHaveValue('14/06/2025');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('klik akhir yang lebih awal menukar awal dan akhir', async () => {
    render(<RangeHarness />);
    const u = user();
    await u.click(screen.getByRole('button', { name: 'Pilih rentang tanggal: Periode' }));
    await u.click(screen.getByRole('button', { name: '20 Juni 2025' }));
    await u.click(screen.getByRole('button', { name: '12 Juni 2025' }));

    expect(nilai()).toBe('2025-06-12|2025-06-20');
  });

  it('pintasan 7 hari terakhir dan bulan ini memakai tanggal klinik', async () => {
    render(<RangeHarness />);
    const u = user();
    await u.click(screen.getByRole('button', { name: 'Pilih rentang tanggal: Periode' }));
    await u.click(screen.getByRole('button', { name: '7 hari terakhir' }));
    expect(nilai()).toBe('2025-06-10|2025-06-16');

    await u.click(screen.getByRole('button', { name: 'Pilih rentang tanggal: Periode' }));
    await u.click(screen.getByRole('button', { name: 'Bulan lalu' }));
    expect(nilai()).toBe('2025-05-01|2025-05-31');
  });

  it('mengetik akhir sebelum awal menampilkan pesan', async () => {
    render(<RangeHarness />);
    const u = user();
    await u.type(screen.getByLabelText('Dari'), '20062025');
    await u.type(screen.getByLabelText('Sampai'), '10062025');

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Tanggal akhir tidak boleh sebelum tanggal awal.',
    );
  });
});

describe('UI-04 TimePicker', () => {
  it('mengetik angka disusun jadi JJ:MM dan jam tidak sah ditolak', async () => {
    render(<TimeHarness />);
    const u = user();
    const input = screen.getByLabelText('Jam mulai');
    await u.type(input, '0930');
    expect(input).toHaveValue('09:30');
    expect(nilai()).toBe('09:30');

    await u.clear(input);
    await u.type(input, '2575');
    expect(screen.getByRole('alert')).toHaveTextContent('Jam tidak valid (00:00 sampai 23:59).');
    expect(nilai()).toBe('');
  });

  it('memilih jam lalu menit dari panel dan menutupnya', async () => {
    render(<TimeHarness />);
    const u = user();
    await u.click(screen.getByRole('button', { name: 'Pilih jam: Jam mulai' }));
    await u.click(
      within(screen.getByRole('group', { name: 'Jam' })).getByRole('button', { name: '14' }),
    );
    expect(nilai()).toBe('14:00');
    await u.click(
      within(screen.getByRole('group', { name: 'Menit' })).getByRole('button', { name: '45' }),
    );

    expect(nilai()).toBe('14:45');
    expect(screen.getByLabelText('Jam mulai')).toHaveValue('14:45');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('nilai awal ditandai di panel dan Escape menutup', async () => {
    render(<TimeHarness initial="08:15" />);
    const u = user();
    await u.click(screen.getByRole('button', { name: 'Pilih jam: Jam mulai' }));

    expect(
      within(screen.getByRole('group', { name: 'Jam' })).getByRole('button', { name: '08' }),
    ).toHaveAttribute('aria-pressed', 'true');
    expect(
      within(screen.getByRole('group', { name: 'Menit' })).getByRole('button', { name: '15' }),
    ).toHaveAttribute('aria-pressed', 'true');
    await u.keyboard('{Escape}');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});
