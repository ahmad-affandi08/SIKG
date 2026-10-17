import { describe, expect, it } from 'vitest';

import {
  addDays,
  addMonths,
  clampIso,
  daysInMonth,
  formatDisplay,
  maskDate,
  maskTime,
  monthGrid,
  parseDisplay,
  parseIso,
  parseTime,
  todayIso,
  weekdayIndex,
} from '@/lib/date';

describe('tanggal murni', () => {
  it('memparse hanya tanggal kalender yang sah', () => {
    expect(parseIso('2024-02-29')).toEqual({ y: 2024, m: 2, d: 29 });
    expect(parseIso('2023-02-29')).toBeNull();
    expect(parseIso('2024-13-01')).toBeNull();
    expect(parseIso('1/1/2024')).toBeNull();
  });

  it('menampilkan DD/MM/YYYY dan memparse kembali', () => {
    expect(formatDisplay('1990-01-05')).toBe('05/01/1990');
    expect(parseDisplay('05/01/1990')).toBe('1990-01-05');
    expect(parseDisplay('31/04/2024')).toBeNull();
    expect(parseDisplay('5/1/1990')).toBeNull();
    expect(formatDisplay('')).toBe('');
  });

  it('menyisipkan garis miring saat mengetik angka', () => {
    expect(maskDate('0')).toBe('0');
    expect(maskDate('050')).toBe('05/0');
    expect(maskDate('05011990')).toBe('05/01/1990');
    expect(maskDate('05/01/1990123')).toBe('05/01/1990');
    expect(maskDate('ab12cd')).toBe('12');
  });

  it('menggeser hari dan bulan dengan benar, termasuk akhir bulan dan tahun kabisat', () => {
    expect(addDays('2024-02-28', 2)).toBe('2024-03-01');
    expect(addDays('2024-01-01', -1)).toBe('2023-12-31');
    expect(addMonths('2024-01-31', 1)).toBe('2024-02-29');
    expect(addMonths('2023-01-31', 1)).toBe('2023-02-28');
    expect(addMonths('2024-12-15', 1)).toBe('2025-01-15');
    expect(addMonths('2024-01-15', -1)).toBe('2023-12-15');
    expect(daysInMonth(2024, 2)).toBe(29);
  });

  it('pekan dimulai Senin dan kisi bulan berisi enam pekan', () => {
    expect(weekdayIndex('2025-06-16')).toBe(0);
    expect(weekdayIndex('2025-06-22')).toBe(6);
    const grid = monthGrid(2025, 6);
    expect(grid).toHaveLength(6);
    expect(grid.every((week) => week.length === 7)).toBe(true);
    expect(grid[0]?.[0]?.iso).toBe('2025-05-26');
    expect(grid[0]?.[6]?.iso).toBe('2025-06-01');
    expect(grid[0]?.[0]?.inMonth).toBe(false);
    expect(grid[0]?.[6]?.inMonth).toBe(true);
  });

  it('membatasi tanggal ke rentang min dan max', () => {
    expect(clampIso('2024-01-01', '2024-02-01', '2024-03-01')).toBe('2024-02-01');
    expect(clampIso('2024-05-01', '2024-02-01', '2024-03-01')).toBe('2024-03-01');
    expect(clampIso('2024-02-15', '2024-02-01', '2024-03-01')).toBe('2024-02-15');
  });

  it('menghitung hari ini menurut zona waktu klinik, bukan zona peramban', () => {
    const now = new Date('2025-06-16T20:00:00Z');
    expect(todayIso('Asia/Jakarta', now)).toBe('2025-06-17');
    expect(todayIso('UTC', now)).toBe('2025-06-16');
  });
});

describe('jam', () => {
  it('memparse HH:MM 24 jam yang sah saja', () => {
    expect(parseTime('09:30')).toEqual({ h: 9, m: 30 });
    expect(parseTime('24:00')).toBeNull();
    expect(parseTime('9:30')).toBeNull();
    expect(parseTime('12:60')).toBeNull();
  });

  it('menyisipkan titik dua saat mengetik angka', () => {
    expect(maskTime('9')).toBe('9');
    expect(maskTime('093')).toBe('09:3');
    expect(maskTime('0930')).toBe('09:30');
    expect(maskTime('09301')).toBe('09:30');
  });
});
