import type { OdontogramData } from '@/features/clinical/odontogram/api';
import { findCondition, SURFACE_LABEL } from '@/features/clinical/odontogram/model';
import type { TargetSurface } from '@/features/clinical/odontogram/model';

/** Perbedaan satu gigi dan satu bagian (permukaan atau seluruh gigi) antara dua kunjungan. */
export interface OdontogramChange {
  fdi: number;
  surface: string;
  from: string | null;
  to: string | null;
}

/** Nomor gigi yang berubah, untuk disorot di bagan. */
export function changedTeeth(changes: readonly OdontogramChange[]): Set<number> {
  return new Set(changes.map((c) => c.fdi));
}

/** Nama kondisi; kode yang tidak dikenal ditampilkan apa adanya, kosong berarti belum dicatat. */
export function conditionName(data: OdontogramData | null, code: string | null): string {
  if (code === null) {
    return 'Belum dicatat';
  }
  return findCondition(data?.conditions ?? [], code)?.name ?? code;
}

export function surfaceName(surface: string): string {
  const name = SURFACE_LABEL[surface as TargetSurface] as string | undefined;
  return name ?? surface;
}

/** Ringkasan satu baris perubahan, contoh "Gigi 16, oklusal: Karies menjadi Tambalan". */
export function describeChange(change: OdontogramChange, data: OdontogramData | null): string {
  const from = conditionName(data, change.from);
  const to = change.to === null ? 'Belum dicatat' : conditionName(data, change.to);
  return `Gigi ${String(change.fdi)}, ${surfaceName(change.surface)}: ${from} menjadi ${to}`;
}

/** Dentisi yang perlu digambar: gigi sulung hanya bila tercatat pada salah satu sisi. */
export function hasPrimaryTeeth(...sides: (OdontogramData | null)[]): boolean {
  return sides.some(
    (side) => side !== null && Object.keys(side.teeth).some((fdi) => Number(fdi) >= 50),
  );
}
