import { useQuery } from '@tanstack/react-query';

import { searchPatients } from '@/features/patient/api';

export const patientKeys = {
  search: (term: string) => ['patient', 'search', term] as const,
};

/** Hasil pencarian memuat data pasien, jadi tidak dipertahankan lama di cache (M11). */
export function usePatientSearch(term: string) {
  return useQuery({
    queryKey: patientKeys.search(term),
    queryFn: ({ signal }) => searchPatients(term, signal),
    enabled: term.trim().length > 0,
    staleTime: 0,
    gcTime: 0,
  });
}
