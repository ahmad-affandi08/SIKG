import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
  AddendumPanel,
  AnamnesisPanel,
  MedicalWarnings,
  ProcedurePanel,
  SoapPanel,
} from '@/features/clinical/components/EncounterPanels';

const put = vi.fn<(...args: unknown[]) => void>();
const post = vi.fn<(...args: unknown[]) => void>();

vi.mock('@inertiajs/react', () => ({
  router: {
    put: (...args: unknown[]) => {
      put(...args);
    },
    post: (...args: unknown[]) => {
      post(...args);
    },
    delete: vi.fn(),
  },
  usePage: () => ({ props: { clinic: { name: 'Klinik Uji', timezone: 'Asia/Jakarta' } } }),
}));

const encounter = {
  id: 'E1',
  is_closed: false,
  chief_complaint: 'Nyeri',
  complaint_history: null,
  vitals: { systolic: 120, diastolic: 80, pulse: 70, temperature_tenths: 368 },
};

beforeEach(() => {
  put.mockReset();
  post.mockReset();
});

describe('PAS-04 banner peringatan medis', () => {
  it('menampilkan alergi dan penyakit sistemik dengan teks, bukan warna saja', () => {
    render(
      <MedicalWarnings
        warnings={[
          { type: 'allergy', type_label: 'Alergi', name: 'Penisilin', severity: 'severe' },
          {
            type: 'systemic_disease',
            type_label: 'Penyakit sistemik',
            name: 'Diabetes',
            severity: null,
          },
        ]}
      />,
    );
    const banner = screen.getByRole('status');
    expect(banner).toHaveTextContent('Peringatan medis');
    expect(banner).toHaveTextContent('Alergi Penisilin (berat)');
    expect(banner).toHaveTextContent('Penyakit sistemik Diabetes');
  });

  it('tidak menampilkan apa pun bila tidak ada peringatan', () => {
    const { container } = render(<MedicalWarnings warnings={[]} />);
    expect(container).toBeEmptyDOMElement();
  });
});

describe('RM-02 anamnesis', () => {
  it('mengirim tanda vital sebagai bilangan bulat dan suhu dalam persepuluh derajat', async () => {
    render(<AnamnesisPanel encounter={encounter} canWrite />);

    await userEvent.click(screen.getByRole('button', { name: 'Simpan anamnesis' }));

    expect(put).toHaveBeenCalledOnce();
    expect(put.mock.calls[0]?.[0]).toBe('/encounters/E1/anamnesis');
    expect(put.mock.calls[0]?.[1]).toMatchObject({
      systolic: 120,
      diastolic: 80,
      pulse: 70,
      temperature_tenths: 368,
    });
  });

  it('tidak mengirim bila tekanan darah tidak berpasangan', async () => {
    render(<AnamnesisPanel encounter={encounter} canWrite />);

    await userEvent.clear(screen.getByLabelText('Diastolik (mmHg)'));
    await userEvent.click(screen.getByRole('button', { name: 'Simpan anamnesis' }));

    expect(put).not.toHaveBeenCalled();
    expect(screen.getAllByRole('alert')[0]).toHaveTextContent(
      'Tekanan darah diisi sistolik dan diastolik sekaligus.',
    );
  });

  it('tanpa izin menulis, isian dinonaktifkan dan tombol simpan tidak ada', () => {
    render(<AnamnesisPanel encounter={encounter} canWrite={false} />);

    expect(screen.getByLabelText('Keluhan utama')).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Simpan anamnesis' })).not.toBeInTheDocument();
  });
});

describe('RM-03 catatan SOAP', () => {
  it('menampilkan lima bagian berlabel dan mengirim perubahan', async () => {
    render(<SoapPanel encounter={encounter} notes={[]} canWrite />);

    expect(screen.getByLabelText('Subjektif (S)')).toBeInTheDocument();
    expect(screen.getByLabelText('Objektif: pemeriksaan ekstra oral (O)')).toBeInTheDocument();
    expect(screen.getByLabelText('Objektif: pemeriksaan intra oral (O)')).toBeInTheDocument();
    expect(screen.getByLabelText('Asesmen (A)')).toBeInTheDocument();
    expect(screen.getByLabelText('Rencana (P)')).toBeInTheDocument();

    await userEvent.type(screen.getByLabelText('Rencana (P)'), 'Scaling');
    await userEvent.click(screen.getByRole('button', { name: 'Simpan catatan' }));

    expect(put).toHaveBeenCalledWith(
      '/encounters/E1/notes',
      expect.objectContaining({ plan: 'Scaling' }),
      expect.anything(),
    );
  });
});

describe('RM-05 tindakan', () => {
  const entries = [
    {
      id: 'T1',
      code: 'TMB',
      name: 'Tambal',
      tooth_fdi: 16,
      surfaces: 'OM',
      jaw: null,
      quantity: 2,
      unit_price_amount: 250000,
      subtotal_amount: 500000,
      notes: null,
    },
    {
      id: 'T2',
      code: 'SCL',
      name: 'Scaling',
      tooth_fdi: null,
      surfaces: null,
      jaw: 'upper',
      quantity: 1,
      unit_price_amount: 150000,
      subtotal_amount: 150000,
      notes: null,
    },
  ];

  it('menampilkan sasaran, tarif salinan, dan total dengan format rupiah', () => {
    render(
      <ProcedurePanel
        encounterId="E1"
        entries={entries}
        jaws={[{ value: 'upper', label: 'Rahang atas' }]}
        canEdit={false}
      />,
    );

    const table = screen.getByRole('table');
    expect(within(table).getByText('gigi 16, permukaan OM')).toBeInTheDocument();
    expect(within(table).getByText('Rahang atas')).toBeInTheDocument();
    expect(within(table).getByText('Rp 650.000')).toBeInTheDocument();
    expect(screen.queryByRole('form', { name: 'Tambah tindakan' })).not.toBeInTheDocument();
  });
});

describe('RM-07 addendum', () => {
  it('formulir addendum hanya untuk yang berwenang dan wajib diisi', async () => {
    const { rerender } = render(<AddendumPanel encounterId="E1" addenda={[]} canAdd={false} />);
    expect(screen.queryByRole('form', { name: 'Tambah addendum' })).not.toBeInTheDocument();

    rerender(<AddendumPanel encounterId="E1" addenda={[]} canAdd />);
    await userEvent.click(screen.getByRole('button', { name: 'Tambah addendum' }));

    expect(post).not.toHaveBeenCalled();
    expect(screen.getAllByRole('alert').map((a) => a.textContent)).toEqual(
      expect.arrayContaining(['Isi addendum wajib diisi.', 'Alasan addendum wajib diisi.']),
    );
  });
});
