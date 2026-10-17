import { mesialOnRight } from '@/features/clinical/odontogram/model';
import type { SurfaceCode } from '@/features/clinical/odontogram/model';

export const SIZE = 40;

/** Poligon tiap permukaan pada kotak SIZE x SIZE: bukal di atas, lingual di bawah, oklusal di tengah. */
export function polygons(fdi: number): Record<SurfaceCode, string> {
  const s = SIZE;
  const a = 11;
  const b = s - 11;
  const mesialRight = mesialOnRight(fdi);
  const left = `0,0 0,${String(s)} ${String(a)},${String(b)} ${String(a)},${String(a)}`;
  const right = `${String(s)},0 ${String(s)},${String(s)} ${String(b)},${String(b)} ${String(b)},${String(a)}`;
  return {
    B: `0,0 ${String(s)},0 ${String(b)},${String(a)} ${String(a)},${String(a)}`,
    L: `0,${String(s)} ${String(s)},${String(s)} ${String(b)},${String(b)} ${String(a)},${String(b)}`,
    O: `${String(a)},${String(a)} ${String(b)},${String(a)} ${String(b)},${String(b)} ${String(a)},${String(b)}`,
    M: mesialRight ? right : left,
    D: mesialRight ? left : right,
  };
}

export function textColor(hex: string): string {
  const value = hex.replace('#', '');
  const r = parseInt(value.slice(0, 2), 16);
  const g = parseInt(value.slice(2, 4), 16);
  const b = parseInt(value.slice(4, 6), 16);
  return r * 0.299 + g * 0.587 + b * 0.114 > 150 ? '#0F172A' : '#FFFFFF';
}
