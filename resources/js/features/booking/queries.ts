import { useQuery } from '@tanstack/react-query';

import { fetchPublicSlots } from '@/features/booking/api';

export function usePublicSlots(slug: string, doctorId: string, date: string) {
  return useQuery({
    queryKey: ['booking', 'slots', slug, doctorId, date] as const,
    queryFn: ({ signal }) => fetchPublicSlots(slug, { doctor_id: doctorId, date }, signal),
    enabled: doctorId !== '' && date !== '',
    staleTime: 0,
  });
}
