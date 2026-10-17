import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { NotificationBell } from '@/components/layout/NotificationBell';

vi.mock('@inertiajs/react', () => ({
  Link: ({
    href,
    children,
    ...rest
  }: {
    href: string;
    children: ReactNode;
    'aria-label'?: string;
    className?: string;
  }) => (
    <a href={href} aria-label={rest['aria-label']}>
      {children}
    </a>
  ),
}));

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('SYS-05 lonceng notifikasi', () => {
  it('tanpa penyedia query hanya berupa tautan ke kotak masuk', () => {
    render(<NotificationBell />);

    expect(screen.getByRole('link', { name: 'Notifikasi' })).toHaveAttribute(
      'href',
      '/notifications',
    );
  });

  it('menampilkan jumlah belum dibaca dari polling', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        Promise.resolve(
          new Response(JSON.stringify({ data: { unread: 3, items: [] } }), {
            status: 200,
            headers: { 'Content-Type': 'application/json' },
          }),
        ),
      ),
    );
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    render(
      <QueryClientProvider client={client}>
        <NotificationBell />
      </QueryClientProvider>,
    );

    expect(
      await screen.findByRole('link', { name: 'Notifikasi, 3 belum dibaca' }),
    ).toBeInTheDocument();
  });
});
