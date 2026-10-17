import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { ExportPanel } from '@/features/reporting/components/ExportPanel';

function job(status: string, extra: Record<string, unknown> = {}) {
  return {
    id: '01JOBJOBJOBJOBJOBJOBJOBJOB',
    report_key: 'revenue',
    format: 'pdf',
    status,
    error: null,
    ...extra,
  };
}

function respond(data: unknown, status = 200) {
  return Promise.resolve(
    new Response(JSON.stringify({ data, meta: {} }), {
      status,
      headers: { 'Content-Type': 'application/json' },
    }),
  );
}

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(
    <QueryClientProvider client={client}>
      <ExportPanel
        reportKey="revenue"
        filters={{ from: '2025-06-16', to: '2025-06-16', doctor_id: '' }}
      />
    </QueryClientProvider>,
  );
}

describe('LAP ekspor PDF dan Excel lewat antrean', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('meminta berkas, menampilkan status menunggu, lalu selesai dengan tautan unduh', async () => {
    const fetchMock = vi
      .fn()
      .mockImplementationOnce(() => respond(job('queued'), 202))
      .mockImplementation(() =>
        respond(
          job('done', { status_label: 'Selesai', download_url: '/report-jobs/01JOB/download' }),
        ),
      );
    vi.stubGlobal('fetch', fetchMock);
    renderPanel();

    await userEvent.click(screen.getByRole('button', { name: 'Unduh PDF' }));

    const link = await screen.findByRole('link', { name: 'Unduh PDF' });
    expect(link).toHaveAttribute('href', '/report-jobs/01JOB/download');
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/reports/revenue/export');
    expect(JSON.parse(init.body as string)).toEqual({
      format: 'pdf',
      from: '2025-06-16',
      to: '2025-06-16',
    });
  });

  it('status menunggu ditampilkan jujur dan tombol dikunci selama berkas belum final', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => respond(job('queued'), 202)),
    );
    renderPanel();

    await userEvent.click(screen.getByRole('button', { name: 'Unduh Excel' }));

    expect(await screen.findByText(/Excel: Menunggu/)).toBeInTheDocument();
    expect(screen.getByText(/interval cron/)).toBeInTheDocument();
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Unduh PDF' })).toBeDisabled();
    });
    expect(screen.getByRole('button', { name: 'Unduh Excel' })).toBeDisabled();
  });

  it('berkas gagal menampilkan pesan dan tombol dapat dipakai lagi', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        respond(job('failed', { error: 'Berkas laporan gagal dibuat. Coba minta ulang.' }), 202),
      ),
    );
    renderPanel();

    await userEvent.click(screen.getByRole('button', { name: 'Unduh PDF' }));

    expect(
      await screen.findByText('Berkas laporan gagal dibuat. Coba minta ulang.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Unduh PDF' })).toBeEnabled();
  });

  it('galat izin dari server ditampilkan dalam bahasa Indonesia', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => Promise.resolve(new Response('{}', { status: 403 }))),
    );
    renderPanel();

    await userEvent.click(screen.getByRole('button', { name: 'Unduh PDF' }));

    expect(await screen.findByText('Anda tidak memiliki izin untuk aksi ini.')).toBeInTheDocument();
  });
});
