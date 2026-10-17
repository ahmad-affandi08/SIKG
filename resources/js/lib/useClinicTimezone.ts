import { usePage } from '@inertiajs/react';

const FALLBACK = 'Asia/Jakarta';

/** Zona waktu klinik dari props bersama Inertia (waktu server dalam UTC ditampilkan menurut zona ini). */
export function useClinicTimezone(): string {
  const { clinic } = usePage().props;
  return clinic?.timezone ?? FALLBACK;
}
