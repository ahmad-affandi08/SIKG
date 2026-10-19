import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { QuickPatientSearch } from '@/components/layout/QuickPatientSearch';
import type { QuickPatient } from '@/components/layout/patientSearchApi';

const visit = vi.fn();
const search = vi.fn<(term: string) => Promise<QuickPatient[]>>();

vi.mock('@inertiajs/react', () => ({
  router: {
    visit: (url: string) => {
      visit(url);
    },
  },
}));
vi.mock('@/components/layout/patientSearchApi', () => ({
  quickSearchPatients: (term: string) => search(term),
}));

function patient(overrides: Partial<QuickPatient> = {}): QuickPatient {
  return {
    id: '01HZZZZZZZZZZZZZZZZZZZZZZ1',
    medical_record_no: 'CTH-00001',
    name: 'Budi Santoso',
    birth_date: '1990-01-01',
    nik: '9999010101900001',
    ...overrides,
  };
}

beforeEach(() => {
  visit.mockReset();
  search.mockReset();
});

describe('PAS-02 pencarian pasien cepat', () => {
  it('PAS-02 mengetik nama menampilkan daftar dengan RM, tanggal lahir, dan NIK penuh', async () => {
    search.mockResolvedValue([
      patient(),
      patient({
        id: '01HZZZZZZZZZZZZZZZZZZZZZZ2',
        name: 'Budi Hartono',
        medical_record_no: 'CTH-00002',
        nik: null,
      }),
    ]);
    render(<QuickPatientSearch />);

    await userEvent.type(screen.getByRole('combobox', { name: 'Pencarian pasien cepat' }), 'budi');

    expect(await screen.findByText('Budi Santoso')).toBeInTheDocument();
    expect(screen.getByText(/CTH-00001 · 01\/01\/1990 · NIK 9999010101900001/)).toBeInTheDocument();
    expect(screen.getByText('Budi Hartono')).toBeInTheDocument();
    expect(visit).not.toHaveBeenCalled();
  });

  it('PAS-02 memilih pasien dari daftar membuka halaman pasien', async () => {
    search.mockResolvedValue([patient()]);
    render(<QuickPatientSearch />);

    await userEvent.type(screen.getByRole('combobox'), 'budi');
    await userEvent.click(await screen.findByRole('button', { name: /Budi Santoso/ }));

    expect(visit).toHaveBeenCalledWith('/patients/01HZZZZZZZZZZZZZZZZZZZZZZ1');
  });

  it('PAS-02 NIK langsung membuka pasien tanpa daftar', async () => {
    search.mockResolvedValue([patient()]);
    render(<QuickPatientSearch />);

    await userEvent.type(screen.getByRole('combobox'), '9999010101900001{Enter}');

    await waitFor(() => {
      expect(visit).toHaveBeenCalledWith('/patients/01HZZZZZZZZZZZZZZZZZZZZZZ1');
    });
    expect(search).toHaveBeenCalledTimes(1);
    expect(search).toHaveBeenCalledWith('9999010101900001');
  });

  it('PAS-02 nomor RM memilih kecocokan persis, bukan awalan yang mirip', async () => {
    search.mockResolvedValue([
      patient({ id: '01HZZZZZZZZZZZZZZZZZZZZZZ9', medical_record_no: 'CTH-000010' }),
      patient({ id: '01HZZZZZZZZZZZZZZZZZZZZZZ1', medical_record_no: 'CTH-00001' }),
    ]);
    render(<QuickPatientSearch />);

    await userEvent.type(screen.getByRole('combobox'), 'cth-00001{Enter}');

    await waitFor(() => {
      expect(visit).toHaveBeenCalledWith('/patients/01HZZZZZZZZZZZZZZZZZZZZZZ1');
    });
  });

  it('PAS-02 NIK yang tidak ada menampilkan pesan, bukan berpindah halaman', async () => {
    search.mockResolvedValue([]);
    render(<QuickPatientSearch />);

    await userEvent.type(screen.getByRole('combobox'), '9999010101900009{Enter}');

    expect(await screen.findByText('Pasien tidak ditemukan.')).toBeInTheDocument();
    expect(visit).not.toHaveBeenCalled();
  });

  it('PAS-02 panah bawah dan Enter membuka pasien yang disorot', async () => {
    search.mockResolvedValue([
      patient(),
      patient({ id: '01HZZZZZZZZZZZZZZZZZZZZZZ2', name: 'Budi Hartono' }),
    ]);
    render(<QuickPatientSearch />);

    await userEvent.type(screen.getByRole('combobox'), 'budi');
    await screen.findByText('Budi Hartono');
    await userEvent.keyboard('{ArrowDown}{ArrowDown}{Enter}');

    expect(visit).toHaveBeenCalledWith('/patients/01HZZZZZZZZZZZZZZZZZZZZZZ2');
  });

  it('PAS-02 tombol / dan Ctrl+K memindahkan fokus ke kotak cari', () => {
    render(<QuickPatientSearch />);
    const box = screen.getByRole('combobox');

    fireEvent.keyDown(document.body, { key: '/' });
    expect(box).toHaveFocus();

    box.blur();
    fireEvent.keyDown(document.body, { key: 'k', ctrlKey: true });
    expect(box).toHaveFocus();
  });

  it('PAS-02 kata kunci satu huruf tidak memicu pencarian', async () => {
    render(<QuickPatientSearch />);

    await userEvent.type(screen.getByRole('combobox'), 'b');
    await new Promise((resolve) => setTimeout(resolve, 400));

    expect(search).not.toHaveBeenCalled();
  });
});
