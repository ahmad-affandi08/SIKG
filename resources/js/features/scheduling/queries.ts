import { useQuery } from '@tanstack/react-query';

import { fetchAvailability, fetchQueue } from '@/features/scheduling/api';

export const schedulingKeys = {
  queue: (date: string | undefined) => ['scheduling', 'queue', date ?? 'today'] as const,
  availability: (doctor: string, date: string, minutes: number, chair: string) =>
    ['scheduling', 'availability', doctor, date, minutes, chair] as const,
};

/** Polling antrean tiap 10 detik, hanya saat tab aktif (tanpa WebSocket, ADR-0002). */
export const QUEUE_POLL_MS = 10_000;

export function useQueue(date?: string) {
  return useQuery({
    queryKey: schedulingKeys.queue(date),
    queryFn: ({ signal }) => fetchQueue(date, signal),
    refetchInterval: QUEUE_POLL_MS,
    refetchIntervalInBackground: false,
    staleTime: 0,
  });
}

export function useAvailability(
  params: { doctor: string; date: string; minutes: number; chair: string },
  enabled: boolean,
) {
  return useQuery({
    queryKey: schedulingKeys.availability(params.doctor, params.date, params.minutes, params.chair),
    queryFn: ({ signal }) =>
      fetchAvailability(
        {
          doctor: params.doctor,
          date: params.date,
          minutes: params.minutes,
          ...(params.chair ? { chair: params.chair } : {}),
        },
        signal,
      ),
    enabled,
    staleTime: 0,
  });
}
