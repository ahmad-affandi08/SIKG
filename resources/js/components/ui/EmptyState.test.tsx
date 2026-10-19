import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { EmptyState } from '@/components/ui/EmptyState';
import type { EmptyKind } from '@/components/ui/EmptyState';

const KINDS: EmptyKind[] = [
  'tooth',
  'calendar',
  'queue',
  'invoice',
  'pill',
  'chart',
  'bell',
  'folder',
  'search',
  'box',
];

describe('UI-08 keadaan kosong', () => {
  it('UI-08 menampilkan judul dan penjelasan sebagai teks yang dibaca pembaca layar', () => {
    render(
      <EmptyState
        kind="queue"
        title="Belum ada pasien"
        description="Pasien akan tampil di sini."
      />,
    );

    const status = screen.getByRole('status');
    expect(status).toHaveTextContent('Belum ada pasien');
    expect(status).toHaveTextContent('Pasien akan tampil di sini.');
  });

  it('UI-08 selalu berada di tengah, rata kiri-kanan dan atas-bawah, di semua ukuran', () => {
    for (const size of ['page', 'section', 'compact'] as const) {
      const { unmount } = render(<EmptyState title="Kosong" size={size} />);
      const root = screen.getByRole('status');
      for (const token of [
        'flex',
        'flex-col',
        'items-center',
        'justify-center',
        'text-center',
        'mx-auto',
      ]) {
        expect(root.className.split(' ')).toContain(token);
      }
      unmount();
    }
  });

  it('UI-08 setiap ilustrasi digambar sebagai SVG dekoratif tanpa mengganggu pembaca layar', () => {
    for (const kind of KINDS) {
      const { container, unmount } = render(<EmptyState kind={kind} title={`Kosong ${kind}`} />);
      const svg = container.querySelector('svg');
      expect(svg, kind).not.toBeNull();
      expect(svg?.getAttribute('aria-hidden')).toBe('true');
      expect(svg?.getAttribute('focusable')).toBe('false');
      expect(svg?.querySelectorAll('path, rect, circle').length, kind).toBeGreaterThan(5);
      expect(container.querySelector('[data-empty-kind]')?.getAttribute('data-empty-kind')).toBe(
        kind,
      );
      unmount();
    }
  });

  it('UI-08 menampilkan tindakan bila diberikan dan tidak membuat blok kosong bila tidak', () => {
    const { rerender } = render(
      <EmptyState title="Kosong" action={<a href="/patients/create">Daftarkan pasien</a>} />,
    );
    expect(screen.getByRole('link', { name: 'Daftarkan pasien' })).toBeInTheDocument();

    rerender(<EmptyState title="Kosong" />);
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });
});
