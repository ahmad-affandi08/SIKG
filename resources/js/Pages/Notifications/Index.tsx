import { Link } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { useMarkAllRead, useMarkRead, useNotifications } from '@/features/notifications/queries';
import type { NotificationOverview } from '@/features/notifications/schemas';
import { formatDateTime } from '@/lib/format';

/** SYS-05: kotak masuk pemberitahuan staf. Data awal dari server; selanjutnya diperbarui lewat polling. */
export default function Index(initial: NotificationOverview) {
  const { data = initial } = useNotifications(initial);
  const read = useMarkRead();
  const readAll = useMarkAllRead();

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Notifikasi" />
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h1>Notifikasi</h1>
          <Button
            variant="secondary"
            disabled={data.unread === 0}
            loading={readAll.isPending}
            onClick={() => {
              readAll.mutate();
            }}
          >
            Tandai semua dibaca
          </Button>
        </div>
        <p className="text-muted text-sm">
          Peringatan stok, obat hampir kedaluwarsa, dan janji baru. Isinya tidak memuat data pasien.
        </p>

        {data.items.length === 0 ? (
          <EmptyState
            kind="bell"
            size="page"
            title="Belum ada pemberitahuan"
            description="Peringatan stok, obat hampir kedaluwarsa, dan janji baru akan muncul di sini."
          />
        ) : (
          <ul className="flex flex-col border border-[#8f8f8f]">
            {data.items.map((item) => (
              <li
                key={item.id}
                className="flex flex-wrap items-start justify-between gap-3 border-b border-[#c8c8c8] px-3 py-2 last:border-b-0"
              >
                <div className="min-w-0 flex-1">
                  <p className={item.read ? '' : 'font-bold'}>
                    {item.read ? null : <span className="sr-only">Belum dibaca. </span>}
                    {item.title}
                  </p>
                  <p className="text-sm">{item.body}</p>
                  <p className="text-muted text-xs">{formatDateTime(new Date(item.created_at))}</p>
                </div>
                <div className="flex items-center gap-3 text-sm">
                  {item.link ? (
                    <Link
                      href={item.link}
                      className="text-brand-700 underline"
                      onClick={() => {
                        read.mutate(item.id);
                      }}
                    >
                      Buka
                    </Link>
                  ) : null}
                  {item.read ? null : (
                    <button
                      type="button"
                      className="text-brand-700 underline"
                      onClick={() => {
                        read.mutate(item.id);
                      }}
                    >
                      Tandai dibaca
                    </button>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </main>
    </AppLayout>
  );
}
