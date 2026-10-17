import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { OdontogramBoard } from './OdontogramBoard';

const conditions = [
  { code: 'SEH', name: 'Sehat', color: '#FFFFFF', scope: 'both', active: true },
  { code: 'KAR', name: 'Karies', color: '#DC2626', scope: 'surface', active: true },
  { code: 'HLG', name: 'Hilang', color: '#475569', scope: 'tooth', active: true },
];

const initial = {
  conditions,
  teeth: {
    '16': {
      whole: null,
      surfaces: {
        O: {
          condition_code: 'KAR',
          set_at: '2025-06-16T00:00:00Z',
          encounter_id: 'E1',
          in_this_encounter: false,
        },
      },
    },
  },
};

function json(data: unknown, status = 200) {
  return new Response(JSON.stringify(status === 200 ? { data, meta: {} } : data), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

function setup(putStatus = 200) {
  const calls: { method: string; url: string; body: string | null }[] = [];
  vi.stubGlobal(
    'fetch',
    vi.fn((url: string, init?: RequestInit) => {
      const method = init?.method ?? 'GET';
      calls.push({ method, url, body: typeof init?.body === 'string' ? init.body : null });
      if (method === 'PUT') {
        return Promise.resolve(
          putStatus === 200
            ? json({ tooth_fdi: 16, surface: 'M', condition_code: 'KAR' })
            : json({ message: 'Kunjungan sudah ditutup.', code: 'encounter.locked' }, putStatus),
        );
      }
      return Promise.resolve(json(initial));
    }),
  );
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(
    <QueryClientProvider client={client}>
      <OdontogramBoard patientId="P1" encounterId="E1" canWrite />
    </QueryClientProvider>,
  );
  return calls;
}

describe('ODO-01 sampai ODO-03 papan odontogram', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('menampilkan 32 gigi dewasa, tiap gigi dapat diakses dengan nama', async () => {
    setup();

    expect(
      await screen.findByRole('button', { name: 'Gigi 16, karies oklusal' }),
    ).toBeInTheDocument();
    const buttons = screen.getAllByRole('button', { name: /^Gigi \d{2}/ });
    expect(buttons).toHaveLength(32);
  });

  it('jenis gigi sulung menampilkan 20 gigi dan campuran 52', async () => {
    setup();
    await screen.findByRole('button', { name: 'Gigi 16, karies oklusal' });

    await userEvent.selectOptions(screen.getByLabelText('Jenis gigi'), 'primary');
    expect(screen.getAllByRole('button', { name: /^Gigi \d{2}/ })).toHaveLength(20);

    await userEvent.selectOptions(screen.getByLabelText('Jenis gigi'), 'mixed');
    expect(screen.getAllByRole('button', { name: /^Gigi \d{2}/ })).toHaveLength(52);
  });

  it('panah keyboard memindahkan fokus antar gigi dan Enter memilih', async () => {
    setup();
    const first = await screen.findByRole('button', { name: 'Gigi 18, belum dicatat' });
    first.focus();

    await userEvent.keyboard('{ArrowRight}');
    expect(screen.getByRole('button', { name: 'Gigi 17, belum dicatat' })).toHaveFocus();
    await userEvent.keyboard('{Enter}');

    expect(screen.getByRole('heading', { name: 'Gigi 17' })).toBeInTheDocument();
  });

  it('kondisi yang ditawarkan mengikuti cakupan permukaan atau seluruh gigi', async () => {
    setup();
    await userEvent.click(await screen.findByRole('button', { name: 'Gigi 16, karies oklusal' }));

    let options = screen.getAllByRole('option').map((o) => o.textContent);
    expect(options).toContain('KAR - Karies');
    expect(options).not.toContain('HLG - Hilang');

    await userEvent.selectOptions(screen.getByLabelText('Permukaan'), 'W');
    options = screen.getAllByRole('option').map((o) => o.textContent);
    expect(options).toContain('HLG - Hilang');
    expect(options).not.toContain('KAR - Karies');
  });

  it('menyimpan kondisi lewat PUT dengan permukaan dan kode kondisi', async () => {
    const calls = setup();
    await userEvent.click(await screen.findByRole('button', { name: 'Gigi 16, karies oklusal' }));
    await userEvent.selectOptions(screen.getByLabelText('Permukaan'), 'M');
    await userEvent.selectOptions(screen.getByLabelText('Kondisi baru'), 'KAR');
    await userEvent.click(screen.getByRole('button', { name: 'Simpan kondisi' }));

    await waitFor(() => {
      expect(calls.some((c) => c.method === 'PUT')).toBe(true);
    });
    const put = calls.find((c) => c.method === 'PUT');
    expect(put?.url).toBe('/internal-api/encounters/E1/odontogram/teeth/16');
    expect(JSON.parse(put?.body ?? '{}')).toEqual({ surface: 'M', condition_code: 'KAR' });
  });

  it('bila server menolak, tampilan kembali dan pesan galat ditampilkan', async () => {
    setup(409);
    await userEvent.click(await screen.findByRole('button', { name: 'Gigi 16, karies oklusal' }));
    await userEvent.selectOptions(screen.getByLabelText('Permukaan'), 'D');
    await userEvent.selectOptions(screen.getByLabelText('Kondisi baru'), 'KAR');
    await userEvent.click(screen.getByRole('button', { name: 'Simpan kondisi' }));

    expect(await screen.findByText('Kunjungan sudah ditutup.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Gigi 16, karies oklusal' })).toBeInTheDocument();
  });
});
