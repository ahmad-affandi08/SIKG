import { notificationOverviewSchema, unreadSchema } from '@/features/notifications/schemas';
import { api } from '@/lib/http';

export async function fetchNotifications(signal?: AbortSignal) {
  const result = await api.get(
    '/internal-api/notifications',
    notificationOverviewSchema,
    signal ? { signal } : {},
  );
  return result.data;
}

export async function markNotificationRead(id: string) {
  const result = await api.post(`/internal-api/notifications/${id}/read`, unreadSchema);
  return result.data;
}

export async function markAllNotificationsRead() {
  const result = await api.post('/internal-api/notifications/read-all', unreadSchema);
  return result.data;
}
