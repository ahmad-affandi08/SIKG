import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from '@/features/notifications/api';
import type { NotificationOverview } from '@/features/notifications/schemas';

export const notificationKeys = { all: ['notifications'] as const };

/** Polling pemberitahuan tiap 30 detik, hanya saat tab aktif (tanpa WebSocket, ADR-0002). */
export const NOTIFICATION_POLL_MS = 30_000;

export function useNotifications(initial?: NotificationOverview) {
  return useQuery({
    queryKey: notificationKeys.all,
    queryFn: ({ signal }) => fetchNotifications(signal),
    refetchInterval: NOTIFICATION_POLL_MS,
    refetchIntervalInBackground: false,
    staleTime: 10_000,
    ...(initial ? { initialData: initial } : {}),
  });
}

export function useMarkRead() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: markNotificationRead,
    onSuccess: () => client.invalidateQueries({ queryKey: notificationKeys.all }),
  });
}

export function useMarkAllRead() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: () => client.invalidateQueries({ queryKey: notificationKeys.all }),
  });
}
