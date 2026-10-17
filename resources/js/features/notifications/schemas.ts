import { z } from 'zod';

export const notificationSchema = z.object({
  id: z.string(),
  kind: z.string(),
  title: z.string(),
  body: z.string(),
  link: z.string().nullable(),
  created_at: z.string(),
  read: z.boolean(),
});

export const notificationOverviewSchema = z.object({
  unread: z.number().int().nonnegative(),
  items: z.array(notificationSchema),
});

export const unreadSchema = z.object({ unread: z.number().int().nonnegative() });

export type AppNotification = z.infer<typeof notificationSchema>;
export type NotificationOverview = z.infer<typeof notificationOverviewSchema>;
