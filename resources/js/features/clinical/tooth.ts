export type Dentition = 'permanent' | 'primary' | 'mixed';

export const SURFACES = [
  { value: 'M', label: 'Mesial' },
  { value: 'D', label: 'Distal' },
  { value: 'O', label: 'Oklusal atau insisal' },
  { value: 'B', label: 'Bukal atau labial' },
  { value: 'L', label: 'Lingual atau palatal' },
] as const;

function range(from: number, to: number): number[] {
  const step = from <= to ? 1 : -1;
  const out: number[] = [];
  for (let n = from; n !== to + step; n += step) {
    out.push(n);
  }
  return out;
}

/** Penomoran FDI (M8): dewasa 11-18, 21-28, 31-38, 41-48; sulung 51-55, 61-65, 71-75, 81-85. */
export function isValidFdi(fdi: number): boolean {
  if (!Number.isInteger(fdi)) {
    return false;
  }
  const quadrant = Math.floor(fdi / 10);
  const position = fdi % 10;
  return (
    (quadrant >= 1 && quadrant <= 4 && position >= 1 && position <= 8) ||
    (quadrant >= 5 && quadrant <= 8 && position >= 1 && position <= 5)
  );
}

export function dentitionOf(fdi: number): Dentition {
  return Math.floor(fdi / 10) <= 4 ? 'permanent' : 'primary';
}

/** Baris gigi atas dan bawah menurut tampilan odontogram (kanan pasien di kiri layar). */
export function toothRows(dentition: 'permanent' | 'primary'): {
  upper: number[];
  lower: number[];
} {
  return dentition === 'permanent'
    ? {
        upper: [...range(18, 11), ...range(21, 28)],
        lower: [...range(48, 41), ...range(31, 38)],
      }
    : {
        upper: [...range(55, 51), ...range(61, 65)],
        lower: [...range(85, 81), ...range(71, 75)],
      };
}

export function allTeeth(dentition: Dentition): number[] {
  if (dentition === 'mixed') {
    return [...allTeeth('permanent'), ...allTeeth('primary')];
  }
  const rows = toothRows(dentition);
  return [...rows.upper, ...rows.lower];
}

/** Gigi depan (insisivus dan kaninus) memakai tepi insisal. */
export function isAnterior(fdi: number): boolean {
  return fdi % 10 <= 3;
}
