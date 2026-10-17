import { useQuery } from '@tanstack/react-query';

import { searchDiagnoses, searchProcedures } from '@/features/clinical/api';
import { STALE_TIME } from '@/lib/queryClient';

export const clinicalKeys = {
  procedureSearch: (term: string) => ['clinical', 'procedures', 'search', term] as const,
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

export function useProcedureSearch(term: string) {
  return useQuery({
    queryKey: clinicalKeys.procedureSearch(term),
    queryFn: ({ signal }) => searchProcedures(term, signal),
    enabled: term.trim().length > 0,
    staleTime: STALE_TIME.master,
  });
}
