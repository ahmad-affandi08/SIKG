import { Link } from '@inertiajs/react';
import { QueryClientContext, useQuery } from '@tanstack/react-query';
import { useContext } from 'react';
import { z } from 'zod';

import { api } from '@/lib/http';

/** Kunci dan jeda yang sama dengan features/notifications agar halaman Notifikasi dan lonceng berbagi data. */
const NOTIFICATIONS_KEY = ['notifications'] as const;
const POLL_MS = 30_000;

const overviewSchema = z.object({
  unread: z.number().int().nonnegative(),
  items: z.array(z.unknown()),
});

function BellWithCount({ onClick }: { onClick?: () => void }) {
  const { data } = useQuery({
    queryKey: NOTIFICATIONS_KEY,
    queryFn: async ({ signal }) =>
      (await api.get('/internal-api/notifications', overviewSchema, { signal })).data,
    refetchInterval: POLL_MS,
    refetchIntervalInBackground: false,
    staleTime: 10_000,
  });
  const unread = data?.unread ?? 0;
  return (
    <Link
      href="/notifications"
      onClick={onClick}
      className="menubar-item"
      aria-label={unread > 0 ? `Notifikasi, ${String(unread)} belum dibaca` : 'Notifikasi'}
    >
      Notifikasi
      {unread > 0 ? (
        <span className="ml-1 rounded-full bg-[#111] px-1.5 text-[11px] font-bold text-white tabular-nums">
          {unread > 99 ? '99+' : unread}
        </span>
      ) : null}
    </Link>
  );
}

/** Tautan ke kotak masuk dengan jumlah belum dibaca (polling, SYS-05). Tanpa penyedia query hanya tautan biasa. */
export function NotificationBell({ onClick }: { onClick?: () => void }) {
  const client = useContext(QueryClientContext);
  if (!client) {
    return (
      <Link href="/notifications" onClick={onClick} className="menubar-item">
        Notifikasi
      </Link>
    );
  }
  return <BellWithCount {...(onClick ? { onClick } : {})} />;
}
