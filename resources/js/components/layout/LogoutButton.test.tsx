import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { LogoutButton } from '@/components/layout/LogoutButton';

const post = vi.fn<(...args: unknown[]) => void>();
const wipe = vi.fn<() => Promise<void>>();
const flush = vi.fn<(userId: string | null) => Promise<number>>();
let items: { id: string }[] = [];
let afterFlush: { id: string }[] = [];

vi.mock('@inertiajs/react', () => ({
  router: {
    post: (...args: unknown[]) => {
      post(...args);
    },
  },
  usePage: () => ({
    url: '/dashboard',
    props: { auth: { user: { id: 'U1', name: 'Dokter', roles: ['dokter'] }, permissions: [] } },
  }),
}));

vi.mock('@/lib/offline/useOfflineStatus', () => ({
  flushQueue: (userId: string | null) => flush(userId),
  useQueueItems: () => items,
}));

vi.mock('@/lib/offline/store', () => ({
  wipeQueue: () => wipe(),
  queueSnapshot: () => afterFlush,
}));

describe('P3-02 keluar akun membuang antrean offline dari perangkat', () => {
  beforeEach(() => {
    post.mockReset();
    wipe.mockReset().mockResolvedValue(undefined);
    flush.mockReset().mockResolvedValue(0);
    items = [];
    afterFlush = [];
  });

  it('P3-02 tanpa catatan tertunda: antrean dan kunci dibuang lalu keluar', async () => {
    render(<LogoutButton />);

    await userEvent.click(screen.getByRole('button', { name: 'Keluar' }));

    expect(wipe).toHaveBeenCalledTimes(1);
    expect(post).toHaveBeenCalledWith('/logout');
  });

  it('P3-02 catatan yang berhasil terkirim saat keluar tidak memunculkan peringatan', async () => {
    items = [{ id: 'A' }];
    afterFlush = [];
    render(<LogoutButton />);

    await userEvent.click(screen.getByRole('button', { name: 'Keluar' }));

    expect(flush).toHaveBeenCalledWith('U1');
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
    expect(post).toHaveBeenCalledWith('/logout');
  });

  it('P3-02 catatan yang belum terkirim meminta persetujuan dan Batal tidak membuang apa pun', async () => {
    items = [{ id: 'A' }, { id: 'B' }];
    afterFlush = [{ id: 'A' }, { id: 'B' }];
    render(<LogoutButton />);

    await userEvent.click(screen.getByRole('button', { name: 'Keluar' }));

    expect(screen.getByText(/2 catatan belum terkirim/)).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Batal' }));
    expect(wipe).not.toHaveBeenCalled();
    expect(post).not.toHaveBeenCalled();
  });

  it('P3-02 menyetujui membuang catatan lalu keluar', async () => {
    items = [{ id: 'A' }];
    afterFlush = [{ id: 'A' }];
    render(<LogoutButton />);

    await userEvent.click(screen.getByRole('button', { name: 'Keluar' }));
    await userEvent.click(screen.getByRole('button', { name: 'Buang dan keluar' }));

    expect(wipe).toHaveBeenCalledTimes(1);
    expect(post).toHaveBeenCalledWith('/logout');
  });
});
