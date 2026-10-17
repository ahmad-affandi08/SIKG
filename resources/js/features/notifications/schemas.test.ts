import { describe, expect, it } from 'vitest';

import { notificationOverviewSchema } from '@/features/notifications/schemas';

describe('SYS-05 kontrak pemberitahuan', () => {
  const item = {
    id: '01HZZZZZZZZZZZZZZZZZZZZZZZ',
    kind: 'stock.low',
    title: 'Stok menipis',
    body: 'Amoksisilin 500 mg tersisa 3 tablet (minimum 10).',
    link: '/stock',
    created_at: '2025-06-16T07:00:00+07:00',
    read: false,
  };

  it('menerima ringkasan sah dengan tautan kosong', () => {
    const parsed = notificationOverviewSchema.safeParse({
      unread: 1,
      items: [item, { ...item, link: null, read: true }],
    });
    expect(parsed.success).toBe(true);
  });

  it('menolak jumlah belum dibaca negatif', () => {
    expect(notificationOverviewSchema.safeParse({ unread: -1, items: [] }).success).toBe(false);
  });
});
