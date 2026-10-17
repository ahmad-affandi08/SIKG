import { formatDate, formatRupiah } from '@/lib/format';
import type { Cell, ColumnKind } from '@/features/reporting/schemas';

/** Basis poin ke persen dengan koma desimal: 8550 menjadi "85,5%", 10000 menjadi "100%". */
export function formatPercent(basisPoints: number): string {
  const sign = basisPoints < 0 ? '-' : '';
  const abs = Math.abs(basisPoints);
  const whole = Math.floor(abs / 100);
  const frac = abs % 100;
  const fraction = frac === 0 ? '' : `,${frac.toString().padStart(2, '0').replace(/0$/, '')}`;
  return `${sign}${String(whole)}${fraction}%`;
}

const DATE_TIME = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/;

/**
 * Sel laporan untuk ditampilkan. Tanggal dan waktu dari server sudah dalam zona klinik, jadi hanya ditata ulang
 * (DD/MM/YYYY HH:mm) tanpa digeser zona waktu.
 */
export function formatCell(kind: ColumnKind, value: Cell): string {
  if (value === null || value === '') {
    return '';
  }
  switch (kind) {
    case 'money':
      return formatRupiah(Number(value));
    case 'int':
      return new Intl.NumberFormat('id-ID').format(Number(value));
    case 'percent':
      return formatPercent(Number(value));
    case 'minutes':
      return `${new Intl.NumberFormat('id-ID').format(Number(value))} mnt`;
    case 'date':
      return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)
        ? formatDate(value.slice(0, 10))
        : String(value);
    case 'datetime': {
      const match = typeof value === 'string' ? DATE_TIME.exec(value) : null;
      return match
        ? `${match[3] ?? ''}/${match[2] ?? ''}/${match[1] ?? ''} ${match[4] ?? ''}:${match[5] ?? ''}`
        : String(value);
    }
    default:
      return String(value);
  }
}

export function isNumericKind(kind: ColumnKind): boolean {
  return kind === 'int' || kind === 'money' || kind === 'percent' || kind === 'minutes';
}
