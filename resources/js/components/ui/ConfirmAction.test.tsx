import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { ConfirmAction } from '@/components/ui/ConfirmAction';

describe('M5/UX aksi merusak meminta konfirmasi yang menyebut akibatnya', () => {
  it('klik pertama hanya menampilkan konfirmasi, belum menjalankan aksi', async () => {
    const onConfirm = vi.fn();
    render(
      <ConfirmAction
        label="Nonaktifkan Budi"
        message="Budi tidak akan bisa masuk lagi."
        onConfirm={onConfirm}
      />,
    );

    await userEvent.click(screen.getByRole('button', { name: 'Nonaktifkan Budi' }));

    expect(screen.getByRole('alertdialog')).toHaveTextContent('Budi tidak akan bisa masuk lagi.');
    expect(onConfirm).not.toHaveBeenCalled();
  });

  it('menjalankan aksi hanya setelah konfirmasi', async () => {
    const onConfirm = vi.fn();
    render(
      <ConfirmAction
        label="Hapus"
        message="Akan dihapus."
        confirmLabel="Ya, hapus"
        onConfirm={onConfirm}
      />,
    );

    await userEvent.click(screen.getByRole('button', { name: 'Hapus' }));
    await userEvent.click(screen.getByRole('button', { name: 'Ya, hapus' }));

    expect(onConfirm).toHaveBeenCalledOnce();
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
  });

  it('batal menutup konfirmasi tanpa menjalankan aksi', async () => {
    const onConfirm = vi.fn();
    render(<ConfirmAction label="Hapus" message="Akan dihapus." onConfirm={onConfirm} />);

    await userEvent.click(screen.getByRole('button', { name: 'Hapus' }));
    await userEvent.click(screen.getByRole('button', { name: 'Batal' }));

    expect(onConfirm).not.toHaveBeenCalled();
    expect(screen.getByRole('button', { name: 'Hapus' })).toBeInTheDocument();
  });
});
