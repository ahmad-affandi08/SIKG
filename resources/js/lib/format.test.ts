import { describe, expect, it } from 'vitest';

import { formatDate, formatDateTime, formatRupiah, formatTime } from '@/lib/format';

describe('NFR-12 format uang', () => {
  it('menulis rupiah dengan pemisah ribuan titik', () => {
    expect(formatRupiah(1250000)).toBe('Rp 1.250.000');
    expect(formatRupiah(0)).toBe('Rp 0');
    expect(formatRupiah(999)).toBe('Rp 999');
    expect(formatRupiah(1000)).toBe('Rp 1.000');
  });

  it('mendukung selisih negatif pada tutup kas', () => {
    expect(formatRupiah(-50000)).toBe('-Rp 50.000');
  });

  it('menolak desimal agar uang tetap bilangan bulat (M4)', () => {
    expect(() => formatRupiah(10.5)).toThrow(RangeError);
    expect(() => formatRupiah(Number.NaN)).toThrow(RangeError);
  });
});

describe('NFR-12 format tanggal dan waktu', () => {
  it('menulis tanggal DD/MM/YYYY di zona waktu klinik', () => {
    // 20:30 UTC adalah 03:30 WIB hari berikutnya
    expect(formatDate('2026-03-04T20:30:00Z')).toBe('05/03/2026');
    expect(formatTime('2026-03-04T20:30:00Z')).toBe('03:30');
    expect(formatDateTime('2026-03-04T20:30:00Z')).toBe('05/03/2026 03:30');
  });

  it('mengikuti zona waktu klinik yang diatur (WITA)', () => {
    expect(formatTime('2026-03-04T20:30:00Z', 'Asia/Makassar')).toBe('04:30');
  });

  it('tanggal murni seperti tanggal lahir tidak digeser zona waktu', () => {
    expect(formatDate('1990-12-31')).toBe('31/12/1990');
  });

  it('memakai format 24 jam', () => {
    expect(formatTime('2026-03-04T05:05:00Z')).toBe('12:05');
    expect(formatTime('2026-03-04T17:05:00Z')).toBe('00:05');
  });

  it('menolak tanggal tidak valid', () => {
    expect(() => formatDate('bukan-tanggal')).toThrow(RangeError);
  });
});
