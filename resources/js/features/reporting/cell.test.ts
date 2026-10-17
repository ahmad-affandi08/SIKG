import { describe, expect, it } from 'vitest';

import { formatCell, formatPercent, isNumericKind } from '@/features/reporting/cell';

describe('LAP format sel laporan (M4, M18)', () => {
  it('uang tampil Rp dengan titik ribuan, termasuk negatif dan nol', () => {
    expect(formatCell('money', 1250000)).toBe('Rp 1.250.000');
    expect(formatCell('money', -30000)).toBe('-Rp 30.000');
    expect(formatCell('money', 0)).toBe('Rp 0');
  });

  it('persen berupa basis poin dengan koma desimal', () => {
    expect(formatPercent(10000)).toBe('100%');
    expect(formatPercent(8550)).toBe('85,5%');
    expect(formatPercent(1234)).toBe('12,34%');
    expect(formatPercent(-250)).toBe('-2,5%');
    expect(formatCell('percent', 5000)).toBe('50%');
  });

  it('tanggal dan waktu dari server (sudah zona klinik) hanya ditata ulang DD/MM/YYYY', () => {
    expect(formatCell('date', '2025-06-16')).toBe('16/06/2025');
    expect(formatCell('datetime', '2025-06-16 09:05')).toBe('16/06/2025 09:05');
    expect(formatCell('datetime', '2025-06-16T23:59:00')).toBe('16/06/2025 23:59');
  });

  it('angka bulat berpemisah ribuan dan menit diberi satuan', () => {
    expect(formatCell('int', 12500)).toBe('12.500');
    expect(formatCell('minutes', 15)).toBe('15 mnt');
  });

  it('nilai kosong tampil kosong dan teks apa adanya', () => {
    expect(formatCell('money', null)).toBe('');
    expect(formatCell('text', '')).toBe('');
    expect(formatCell('text', 'drg. Sari')).toBe('drg. Sari');
  });

  it('kolom angka rata kanan, teks dan tanggal tidak', () => {
    expect(['int', 'money', 'percent', 'minutes'].every((k) => isNumericKind(k as 'int'))).toBe(
      true,
    );
    expect(['text', 'date', 'datetime'].some((k) => isNumericKind(k as 'text'))).toBe(false);
  });
});
