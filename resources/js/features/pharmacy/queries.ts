import { useQuery } from '@tanstack/react-query';

import { searchMedicines } from '@/features/pharmacy/api';

export const pharmacyKeys = {
  search: (term: string) => ['pharmacy', 'medicines', 'search', term] as const,
};

/** Stok tersedia berubah cepat, jadi hasil pencarian obat tidak dianggap segar lama. */
export function useMedicineSearch(term: string) {
  return useQuery({
    queryKey: pharmacyKeys.search(term),
    queryFn: ({ signal }) => searchMedicines(term, signal),
    enabled: term.trim().length > 0,
    staleTime: 0,
  });
}
