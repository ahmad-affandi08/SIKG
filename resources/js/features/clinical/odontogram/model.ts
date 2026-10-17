import type {
  OdontogramData,
  ToothCondition,
  ToothEntry,
} from '@/features/clinical/odontogram/api';

export type SurfaceCode = 'M' | 'D' | 'O' | 'B' | 'L';
export type TargetSurface = SurfaceCode | 'W';

export const SURFACE_LABEL: Record<TargetSurface, string> = {
  M: 'mesial',
  D: 'distal',
  O: 'oklusal atau insisal',
  B: 'bukal atau labial',
  L: 'lingual atau palatal',
  W: 'seluruh gigi',
};

/** Nama singkat untuk label aksesibel (aria-label) agar mudah dibacakan. */
const SURFACE_SHORT: Record<TargetSurface, string> = {
  M: 'mesial',
  D: 'distal',
  O: 'oklusal',
  B: 'bukal',
  L: 'lingual',
  W: 'seluruh gigi',
};

export const SURFACE_ORDER: readonly SurfaceCode[] = ['B', 'M', 'O', 'D', 'L'];

/** Kondisi efektif satu permukaan: kondisi seluruh gigi bila ada, selain itu kondisi permukaan itu. */
export function conditionAt(
  data: OdontogramData,
  fdi: number,
  surface: TargetSurface,
): ToothEntry | null {
  const tooth = data.teeth[String(fdi)];
  if (!tooth) {
    return null;
  }
  if (surface === 'W') {
    return tooth.whole;
  }
  return tooth.surfaces[surface] ?? tooth.whole;
}

export function findCondition(
  conditions: readonly ToothCondition[],
  code: string | null | undefined,
): ToothCondition | null {
  return conditions.find((c) => c.code === code) ?? null;
}

/** Label aksesibel gigi, contoh "Gigi 16, karies oklusal, tambalan mesial". */
export function toothLabel(data: OdontogramData, fdi: number): string {
  const tooth = data.teeth[String(fdi)];
  const name = (code: string) =>
    findCondition(data.conditions, code)?.name.toLowerCase() ?? code.toLowerCase();
  if (!tooth) {
    return `Gigi ${String(fdi)}, belum dicatat`;
  }
  const parts: string[] = [];
  if (tooth.whole) {
    parts.push(`${name(tooth.whole.condition_code)} seluruh gigi`);
  }
  for (const surface of SURFACE_ORDER) {
    const entry = tooth.surfaces[surface];
    if (entry) {
      parts.push(`${name(entry.condition_code)} ${SURFACE_SHORT[surface]}`);
    }
  }
  return parts.length === 0
    ? `Gigi ${String(fdi)}, belum dicatat`
    : `Gigi ${String(fdi)}, ${parts.join(', ')}`;
}

/** Mesial menghadap garis tengah: di kuadran 1 dan 4 (kiri layar) mesial ada di sisi kanan gigi. */
export function mesialOnRight(fdi: number): boolean {
  const quadrant = Math.floor(fdi / 10);
  return quadrant === 1 || quadrant === 4 || quadrant === 5 || quadrant === 8;
}

/** Penerapan optimistis satu perubahan pada cache odontogram (dikembalikan bila server menolak). */
export function applyCondition(
  data: OdontogramData,
  fdi: number,
  surface: TargetSurface,
  conditionCode: string,
  now: string,
  encounterId: string,
): OdontogramData {
  const key = String(fdi);
  const current = data.teeth[key] ?? { whole: null, surfaces: {} };
  const entry: ToothEntry = {
    condition_code: conditionCode,
    set_at: now,
    encounter_id: encounterId,
    in_this_encounter: true,
  };
  const next =
    surface === 'W'
      ? { whole: entry, surfaces: {} }
      : { whole: current.whole, surfaces: { ...current.surfaces, [surface]: entry } };
  return { ...data, teeth: { ...data.teeth, [key]: next } };
}
