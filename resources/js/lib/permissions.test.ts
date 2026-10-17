import { describe, expect, it } from 'vitest';

import { can } from '@/lib/permissions';

describe('M9 can() hanya kenyamanan UI', () => {
  it('benar bila izin ada', () => {
    expect(can(['patient.view', 'appointment.view'], 'patient.view')).toBe(true);
  });

  it('salah bila izin tidak ada atau daftar kosong (bawaan aman: tolak)', () => {
    expect(can(['patient.view'], 'invoice.void')).toBe(false);
    expect(can([], 'patient.view')).toBe(false);
  });
});
