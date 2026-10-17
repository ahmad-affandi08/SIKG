import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import type { OdontogramData } from '@/features/clinical/odontogram/api';
import { OdontogramChart } from '@/features/clinical/odontogram/OdontogramChart';

const entry = (code: string) => ({
  condition_code: code,
  set_at: '2025-06-16T00:00:00+00:00',
  encounter_id: 'E1',
  in_this_encounter: false,
});

const data: OdontogramData = {
  teeth: { '16': { whole: null, surfaces: { O: entry('KAR') } } },
  conditions: [{ code: 'KAR', name: 'Karies', color: '#DC2626', scope: 'surface', active: true }],
};

describe('ODO-07 bagan odontogram hanya baca', () => {
  it('menandai gigi yang berubah pada label aksesibel dan tidak interaktif', () => {
    render(
      <OdontogramChart data={data} changed={new Set([16])} includePrimary={false} label="Kiri" />,
    );

    expect(screen.getByRole('img', { name: /Gigi 16.*karies oklusal.*berubah/i })).toBeTruthy();
    expect(screen.getByRole('img', { name: 'Gigi 11, belum dicatat' })).toBeTruthy();
    expect(screen.queryAllByRole('button')).toHaveLength(0);
  });

  it('menggambar gigi sulung hanya bila diminta', () => {
    const { rerender } = render(
      <OdontogramChart data={data} changed={new Set()} includePrimary={false} label="Kiri" />,
    );
    expect(screen.queryByRole('img', { name: /Gigi 55/ })).toBeNull();

    rerender(<OdontogramChart data={data} changed={new Set()} includePrimary label="Kiri" />);
    expect(screen.getByRole('img', { name: /Gigi 55/ })).toBeTruthy();
  });
});
