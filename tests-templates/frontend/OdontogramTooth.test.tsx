// TEMPLAT: salin ke resources/js/features/clinical/components/OdontogramTooth.test.tsx
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Odontogram } from './Odontogram';
import * as api from '../api';

const wrap = (ui: React.ReactElement) => (
  <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>{ui}</QueryClientProvider>
);

describe('ODO-01 sampai ODO-04 odontogram', () => {
  it('ODO-01 menampilkan 32 gigi dewasa dengan penomoran FDI', () => {
    render(wrap(<Odontogram encounterId="enc-uji" dentition="adult" />));
    expect(screen.getAllByRole('button', { name: /^Gigi \d{2}/ })).toHaveLength(32);
    expect(screen.getByRole('button', { name: /^Gigi 11/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^Gigi 48/ })).toBeInTheDocument();
  });

  it('ODO-02 setiap permukaan dapat dipilih dari klaviatur dan punya label', async () => {
    render(wrap(<Odontogram encounterId="enc-uji" dentition="adult" />));
    await userEvent.tab();
    await userEvent.keyboard('{Enter}');
    expect(await screen.findByRole('group', { name: /permukaan/i })).toBeInTheDocument();
  });

  it('ODO-04 perubahan optimistis dibatalkan (rollback) bila server menolak', async () => {
    vi.spyOn(api, 'saveToothState').mockRejectedValue(new Error('gagal'));
    render(wrap(<Odontogram encounterId="enc-uji" dentition="adult" />));
    const gigi = screen.getByRole('button', { name: /^Gigi 16/ });
    await userEvent.click(gigi);
    await userEvent.click(await screen.findByRole('option', { name: /KAR/ }));
    await waitFor(() => expect(gigi).not.toHaveAccessibleName(/karies/i));
    expect(await screen.findByText(/gagal menyimpan/i)).toBeInTheDocument();
  });

  it('NFR-08 kondisi tidak hanya dibedakan lewat warna: kode kondisi tampil', () => {
    render(wrap(<Odontogram encounterId="enc-uji" dentition="adult" initialStates={[{ fdi: 16, surface: 'O', code: 'KAR' }]} />));
    expect(screen.getByText('KAR')).toBeInTheDocument();
  });
});
