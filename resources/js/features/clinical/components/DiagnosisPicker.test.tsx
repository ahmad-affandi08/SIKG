import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { DiagnosisPicker } from '@/features/clinical/components/DiagnosisPicker';

function respondWith(data: unknown) {
  const fetchMock = vi.fn(() =>
    Promise.resolve(
      new Response(JSON.stringify({ data, meta: {} }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    ),
  );
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

function renderPicker(onSelect = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(
    <QueryClientProvider client={client}>
      <DiagnosisPicker onSelect={onSelect} />
    </QueryClientProvider>,
  );
  return onSelect;
}

describe('MST-07 autocomplete diagnosa', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('mencari setelah mengetik dan memilih hasil', async () => {
    const fetchMock = respondWith([{ id: 'A', code: 'K04.0', name: 'Pulpitis' }]);
    const onSelect = renderPicker();

    await userEvent.type(screen.getByRole('combobox'), 'pulp');
    const option = await screen.findByRole('button', { name: /K04\.0/ });
    await userEvent.click(option);

    expect(fetchMock).toHaveBeenCalled();
    expect(onSelect).toHaveBeenCalledExactlyOnceWith({ id: 'A', code: 'K04.0', name: 'Pulpitis' });
    expect(screen.getByRole('combobox')).toHaveValue('');
  });

  it('memberi tahu bila tidak ada hasil', async () => {
    respondWith([]);
    renderPicker();

    await userEvent.type(screen.getByRole('combobox'), 'zzz');

    expect(await screen.findByText('Diagnosa tidak ditemukan.')).toBeInTheDocument();
  });

  it('tidak memanggil server saat kolom kosong', () => {
    const fetchMock = respondWith([]);
    renderPicker();

    expect(fetchMock).not.toHaveBeenCalled();
  });
});
