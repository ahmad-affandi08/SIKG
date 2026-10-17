/**
 * Tanggal murni (YYYY-MM-DD) dan jam (HH:MM) untuk komponen pemilih. Semua fungsi murni dan tidak bergantung pada
 * zona waktu peramban: tanggal dihitung dengan UTC supaya pergantian zona tidak menggeser hari.
 */

export const MONTHS_ID = [
  'Januari',
  'Februari',
  'Maret',
  'April',
  'Mei',
  'Juni',
  'Juli',
  'Agustus',
  'September',
  'Oktober',
  'November',
  'Desember',
] as const;

/** Pekan dimulai Senin. */
export const WEEKDAYS_ID = [
  { short: 'Sen', long: 'Senin' },
  { short: 'Sel', long: 'Selasa' },
  { short: 'Rab', long: 'Rabu' },
  { short: 'Kam', long: 'Kamis' },
  { short: 'Jum', long: 'Jumat' },
  { short: 'Sab', long: 'Sabtu' },
  { short: 'Min', long: 'Minggu' },
] as const;

export interface Ymd {
  y: number;
  m: number; // 1-12
  d: number;
}

const ISO = /^(\d{4})-(\d{2})-(\d{2})$/;
const DISPLAY = /^(\d{2})\/(\d{2})\/(\d{4})$/;
const TIME = /^([01]\d|2[0-3]):([0-5]\d)$/;

export function daysInMonth(y: number, m: number): number {
  return new Date(Date.UTC(y, m, 0)).getUTCDate();
}

function valid(y: number, m: number, d: number): boolean {
  return y >= 1 && y <= 9999 && m >= 1 && m <= 12 && d >= 1 && d <= daysInMonth(y, m);
}

export function parseIso(value: string): Ymd | null {
  const match = ISO.exec(value);
  if (!match) {
    return null;
  }
  const [y, m, d] = [Number(match[1]), Number(match[2]), Number(match[3])];
  return valid(y, m, d) ? { y, m, d } : null;
}

export function toIso({ y, m, d }: Ymd): string {
  return `${String(y).padStart(4, '0')}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

/** DD/MM/YYYY dari YYYY-MM-DD; kosong bila tidak sah. */
export function formatDisplay(iso: string): string {
  const p = parseIso(iso);
  return p
    ? `${String(p.d).padStart(2, '0')}/${String(p.m).padStart(2, '0')}/${String(p.y).padStart(4, '0')}`
    : '';
}

/** YYYY-MM-DD dari DD/MM/YYYY; null bila bentuk atau tanggalnya tidak sah. */
export function parseDisplay(text: string): string | null {
  const match = DISPLAY.exec(text);
  if (!match) {
    return null;
  }
  const [d, m, y] = [Number(match[1]), Number(match[2]), Number(match[3])];
  return valid(y, m, d) ? toIso({ y, m, d }) : null;
}

/** Ketikan angka bebas menjadi DD/MM/YYYY secara bertahap (garis miring disisipkan otomatis, maksimal 8 angka). */
export function maskDate(raw: string): string {
  const digits = raw.replace(/\D/g, '').slice(0, 8);
  const parts = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)].filter(
    (p) => p !== '',
  );
  return parts.join('/');
}

export function compareIso(a: string, b: string): number {
  return a < b ? -1 : a > b ? 1 : 0;
}

export function addDays(iso: string, delta: number): string {
  const p = parseIso(iso);
  if (!p) {
    return iso;
  }
  const date = new Date(Date.UTC(p.y, p.m - 1, p.d + delta));
  return toIso({ y: date.getUTCFullYear(), m: date.getUTCMonth() + 1, d: date.getUTCDate() });
}

/** Geser bulan; tanggal dipotong ke hari terakhir bulan tujuan (31 Jan + 1 bulan = 28 atau 29 Feb). */
export function addMonths(iso: string, delta: number): string {
  const p = parseIso(iso);
  if (!p) {
    return iso;
  }
  const index = p.y * 12 + (p.m - 1) + delta;
  const y = Math.floor(index / 12);
  const m = (index % 12) + 1;
  return toIso({ y, m, d: Math.min(p.d, daysInMonth(y, m)) });
}

/** 0 = Senin sampai 6 = Minggu. */
export function weekdayIndex(iso: string): number {
  const p = parseIso(iso);
  if (!p) {
    return 0;
  }
  return (new Date(Date.UTC(p.y, p.m - 1, p.d)).getUTCDay() + 6) % 7;
}

/** Enam pekan penuh (42 hari) untuk satu bulan, mulai Senin; hari di luar bulan ikut dengan inMonth false. */
export function monthGrid(y: number, m: number): { iso: string; inMonth: boolean }[][] {
  const first = toIso({ y, m, d: 1 });
  const start = addDays(first, -weekdayIndex(first));
  const weeks: { iso: string; inMonth: boolean }[][] = [];
  for (let w = 0; w < 6; w += 1) {
    const week: { iso: string; inMonth: boolean }[] = [];
    for (let i = 0; i < 7; i += 1) {
      const iso = addDays(start, w * 7 + i);
      week.push({ iso, inMonth: parseIso(iso)?.m === m });
    }
    weeks.push(week);
  }
  return weeks;
}

export function clampIso(iso: string, min?: string, max?: string): string {
  if (min && compareIso(iso, min) < 0) {
    return min;
  }
  if (max && compareIso(iso, max) > 0) {
    return max;
  }
  return iso;
}

export function inRange(iso: string, min?: string, max?: string): boolean {
  return clampIso(iso, min, max) === iso;
}

/** Tanggal hari ini (YYYY-MM-DD) di zona waktu yang diberikan, biasanya zona klinik. */
export function todayIso(timeZone: string, now: Date = new Date()): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(now);
  return parseIso(parts)
    ? parts
    : toIso({ y: now.getUTCFullYear(), m: now.getUTCMonth() + 1, d: now.getUTCDate() });
}

export interface Hm {
  h: number;
  m: number;
}

export function parseTime(value: string): Hm | null {
  const match = TIME.exec(value);
  return match ? { h: Number(match[1]), m: Number(match[2]) } : null;
}

export function toTime({ h, m }: Hm): string {
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

/** Ketikan angka bebas menjadi HH:MM secara bertahap. */
export function maskTime(raw: string): string {
  const digits = raw.replace(/\D/g, '').slice(0, 4);
  return digits.length <= 2 ? digits : `${digits.slice(0, 2)}:${digits.slice(2)}`;
}
