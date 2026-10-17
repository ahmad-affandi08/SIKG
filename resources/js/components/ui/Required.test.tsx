import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { DatePicker } from '@/components/ui/DatePicker';
import { Select } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { TimePicker } from '@/components/ui/TimePicker';
import { RequiredProvider } from '@/lib/useRequired';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({
    props: {
      clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' },
      required: ['name', 'birth_date', 'schedule.*.start', 'sex'],
    },
  }),
}));

function stars(container: HTMLElement) {
  return container.querySelectorAll('[aria-hidden="true"].text-signal-700');
}

describe('UI-05 tanda wajib dari server', () => {
  it('isian yang disebut server diberi bintang merah dan aria-required', () => {
    const { container } = render(
      <RequiredProvider>
        <TextField label="Nama lengkap" field="name" />
        <TextField label="Alamat" field="address" />
      </RequiredProvider>,
    );

    expect(screen.getByRole('textbox', { name: /Nama lengkap/ })).toHaveAttribute(
      'aria-required',
      'true',
    );
    expect(screen.getByRole('textbox', { name: /Alamat/ })).not.toHaveAttribute('aria-required');
    expect(stars(container)).toHaveLength(1);
    expect(stars(container)[0]).toHaveTextContent('*');
  });

  it('bintang hanya hiasan dan tidak ikut nama yang dibacakan pembaca layar', () => {
    render(
      <RequiredProvider>
        <TextField label="Nama lengkap" field="name" />
      </RequiredProvider>,
    );

    expect(screen.getByRole('textbox', { name: 'Nama lengkap' })).toBeInTheDocument();
  });

  it('berlaku untuk Select, pemilih tanggal, dan pemilih jam, termasuk pola bertingkat', () => {
    const { container } = render(
      <RequiredProvider>
        <Select
          label="Jenis kelamin"
          field="sex"
          options={[{ value: 'male', label: 'Laki-laki' }]}
        />
        <DatePicker label="Tanggal lahir" field="birth_date" value="" onChange={() => undefined} />
        <TimePicker label="Mulai 1" field="schedule.0.start" value="" onChange={() => undefined} />
        <TimePicker label="Selesai 1" field="schedule.0.end" value="" onChange={() => undefined} />
      </RequiredProvider>,
    );

    expect(stars(container)).toHaveLength(3);
    expect(screen.getByRole('combobox', { name: /Jenis kelamin/ })).toHaveAttribute(
      'aria-required',
      'true',
    );
    expect(screen.getByRole('textbox', { name: /Mulai 1/ })).toHaveAttribute(
      'aria-required',
      'true',
    );
    expect(screen.getByRole('textbox', { name: /Selesai 1/ })).not.toHaveAttribute('aria-required');
  });

  it('prop required eksplisit menimpa daftar server (kewajiban bersyarat di layar)', () => {
    const { container } = render(
      <RequiredProvider>
        <TextField label="Alasan" field="reason" required />
        <TextField label="Nama" field="name" required={false} />
      </RequiredProvider>,
    );

    expect(stars(container)).toHaveLength(1);
    expect(screen.getByRole('textbox', { name: /Alasan/ })).toHaveAttribute(
      'aria-required',
      'true',
    );
  });

  it('tanpa penyedia, tidak ada isian yang ditandai', () => {
    const { container } = render(<TextField label="Nama lengkap" field="name" />);
    expect(stars(container)).toHaveLength(0);
  });
});
