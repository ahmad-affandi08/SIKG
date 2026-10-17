import { useQuery } from '@tanstack/react-query';

import { searchDiagnoses } from '@/features/clinical/api';
import { STALE_TIME } from '@/lib/queryClient';

export const clinicalKeys = {
  diagnosisSearch: (term: string) => ['clinical', 'diagnoses', 'search', term] as const,
};

/** Referensi ICD-10 jarang berubah, jadi hasil dianggap segar selama masa data master. */
export function useDiagnosisSearch(term: string) {
  return useQuery({
    queryKey: clinicalKeys.diagnosisSearch(term),
    queryFn: ({ signal }) => searchDiagnoses(term, signal),
    enabled: term.trim().length > 0,
    staleTime: STALE_TIME.master,
  });
}
