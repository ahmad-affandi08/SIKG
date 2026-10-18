import { useQuery } from '@tanstack/react-query';

import { fetchIntentStatus } from '@/features/onlinePayments/api';

/** Polling status QR tiap 5 detik selama menunggu (tanpa WebSocket, ADR-0002). */
export const INTENT_POLL_MS = 5000;

export function useIntentStatus(invoiceId: string, intentId: string, enabled: boolean) {
  return useQuery({
    queryKey: ['online-payments', 'status', invoiceId, intentId] as const,
    queryFn: ({ signal }) => fetchIntentStatus(invoiceId, intentId, signal),
    enabled,
    refetchInterval: enabled ? INTENT_POLL_MS : false,
    refetchIntervalInBackground: false,
    staleTime: 0,
  });
}
