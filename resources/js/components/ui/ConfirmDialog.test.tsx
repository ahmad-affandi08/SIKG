import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';

import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

function Harness({
  variant = 'primary',
  onConfirm,
}: {
  variant?: 'primary' | 'danger';
  onConfirm: () => void;
}) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <button
        type="button"
        onClick={() => {
          setOpen(true);
        }}
      >
        Buka
      </button>
      <ConfirmDialog
        open={open}
        title="Catat pembayaran?"
        message="Pembayaran Rp 500.000 lewat Tunai."
        confirmLabel="Ya, catat"
        variant={variant}
        onCancel={() => {
          setOpen(false);
        }}
        onConfirm={() => {
          setOpen(false);
          onConfirm();
        }}
      />
    </>
  );
}

describe('UI-09 jendela konfirmasi', () => {
  it('tampil sebagai dialog modal berjudul dan berisi pesan akibat', async () => {
    render(<Harness onConfirm={vi.fn()} />);
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Buka' }));

    const dialog = screen.getByRole('alertdialog', { name: 'Catat pembayaran?' });
    expect(dialog).toHaveAttribute('aria-modal', 'true');
    expect(dialog).toHaveAccessibleDescription('Pembayaran Rp 500.000 lewat Tunai.');
  });

  it('aksi biasa memfokuskan tombol setuju; aksi berbahaya memfokuskan Batal', async () => {
    const { unmount } = render(<Harness onConfirm={vi.fn()} />);
    await userEvent.click(screen.getByRole('button', { name: 'Buka' }));
    expect(screen.getByRole('button', { name: 'Ya, catat' })).toHaveFocus();
    unmount();

    render(<Harness variant="danger" onConfirm={vi.fn()} />);
    await userEvent.click(screen.getByRole('button', { name: 'Buka' }));
    expect(screen.getByRole('button', { name: 'Batal' })).toHaveFocus();
  });

  it('Esc membatalkan dan fokus kembali ke pemicu', async () => {
    const onConfirm = vi.fn();
    render(<Harness onConfirm={onConfirm} />);
    const trigger = screen.getByRole('button', { name: 'Buka' });
    await userEvent.click(trigger);

    await userEvent.keyboard('{Escape}');

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
    expect(onConfirm).not.toHaveBeenCalled();
    expect(trigger).toHaveFocus();
  });

  it('Tab berputar di dalam dialog', async () => {
    render(<Harness onConfirm={vi.fn()} />);
    await userEvent.click(screen.getByRole('button', { name: 'Buka' }));
    expect(screen.getByRole('button', { name: 'Ya, catat' })).toHaveFocus();

    await userEvent.tab();
    expect(screen.getByRole('button', { name: 'Batal' })).toHaveFocus();

    await userEvent.tab({ shift: true });
    expect(screen.getByRole('button', { name: 'Ya, catat' })).toHaveFocus();
  });

  it('klik latar membatalkan, tombol setuju menjalankan aksi', async () => {
    const onConfirm = vi.fn();
    render(<Harness onConfirm={onConfirm} />);
    await userEvent.click(screen.getByRole('button', { name: 'Buka' }));
    await userEvent.click(screen.getByRole('button', { name: 'Tutup jendela konfirmasi' }));
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Buka' }));
    await userEvent.click(screen.getByRole('button', { name: 'Ya, catat' }));
    expect(onConfirm).toHaveBeenCalledOnce();
  });
});
