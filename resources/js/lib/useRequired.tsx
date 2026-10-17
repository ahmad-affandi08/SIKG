import { usePage } from '@inertiajs/react';
import { createContext, useContext } from 'react';
import type { ReactNode } from 'react';

function escape(part: string): string {
  return part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Apakah isian `field` wajib menurut daftar dari server. Pola bertingkat memakai `*` untuk satu ruas, mis. pola
 * `schedule.*.start` cocok dengan isian `schedule.0.start`.
 */
export function isRequired(
  required: readonly string[] | undefined,
  field: string | undefined,
): boolean {
  if (!required || !field) {
    return false;
  }
  return required.some((pattern) => {
    if (pattern === field) {
      return true;
    }
    if (!pattern.includes('*')) {
      return false;
    }
    const source = pattern
      .split('.')
      .map((part) => (part === '*' ? '[^.]+' : escape(part)))
      .join('\\.');
    return new RegExp(`^${source}$`).test(field);
  });
}

const RequiredContext = createContext<readonly string[]>([]);

/**
 * Meneruskan daftar isian wajib dari prop `required` halaman (diturunkan server dari aturan validasinya) ke semua
 * isian di bawahnya. Komponen isian tetap dapat dipakai tanpa penyedia ini; hasilnya: tidak ada yang ditandai.
 */
export function RequiredProvider({ children }: { children: ReactNode }) {
  const { required } = usePage().props;
  return <RequiredContext.Provider value={required ?? []}>{children}</RequiredContext.Provider>;
}

/** Tanda wajib untuk satu isian. */
export function useRequired(field: string | undefined): boolean {
  return isRequired(useContext(RequiredContext), field);
}

/**
 * Seperti useRequired, tetapi membaca langsung prop halaman. Dipakai di komponen halaman itu sendiri, yang berada di luar
 * penyedia (penyedia dipasang di dalam AppLayout).
 */
export function usePageRequired(field: string): boolean {
  const { required } = usePage().props;
  return isRequired(required, field);
}
