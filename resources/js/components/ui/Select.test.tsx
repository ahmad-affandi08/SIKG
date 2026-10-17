import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { Select } from '@/components/ui/Select';

const OPTIONS = [
  { value: 'admin', label: 'Admin' },
  { value: 'kasir', label: 'Kasir' },
];

describe('NFR-08 komponen Select', () => {
  it('berlabel, menyediakan pilihan kosong, dan memanggil onChange', async () => {
    const onChange = vi.fn();
    render(
      <Select
        label="Peran"
        options={OPTIONS}
        placeholder="Pilih peran"
        defaultValue=""
        onChange={onChange}
      />,
    );

    const select = screen.getByLabelText('Peran');
    expect(screen.getByRole('option', { name: 'Pilih peran' })).toBeInTheDocument();

    await userEvent.selectOptions(select, 'kasir');
    expect(onChange).toHaveBeenCalled();
    expect(select).toHaveValue('kasir');
  });

  it('galat dibacakan dan menandai input tidak valid', () => {
    render(<Select label="Peran" options={OPTIONS} error="Peran wajib dipilih." />);

    expect(screen.getByRole('alert')).toHaveTextContent('Peran wajib dipilih.');
    expect(screen.getByLabelText('Peran')).toHaveAttribute('aria-invalid', 'true');
  });
});
