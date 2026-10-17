import { Head, Link, usePage } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { Icon } from '@/components/layout/icons';
import { NAV_ITEMS, visibleNavItems } from '@/components/layout/navigation';
import { formatDate } from '@/lib/format';

const QUICK_ACCESS_GROUPS = new Set(['Layanan', 'Farmasi']);

const DESCRIPTIONS: Record<string, string> = {
  '/patients': 'Cari, daftarkan, dan buka rekam medis pasien.',
  '/schedule': 'Buat janji dan atur kalender dokter dan kursi.',
  '/queue': 'Pantau antrean hari ini dan masukkan pasien tanpa janji.',
  '/stock': 'Lihat stok, catat mutasi, dan lakukan penyesuaian.',
};

function greeting(hour: number): string {
  if (hour < 11) {
    return 'Selamat pagi';
  }
  if (hour < 15) {
    return 'Selamat siang';
  }
  if (hour < 18) {
    return 'Selamat sore';
  }
  return 'Selamat malam';
}

/** Beranda: sapaan dan akses cepat ke modul yang boleh dibuka pengguna. Isi per peran menyusul bersama kebutuhannya. */
export default function Beranda() {
  const { auth } = usePage().props;
  const now = new Date();
  const items = visibleNavItems(NAV_ITEMS, auth.permissions).filter(
    (item) => item.group !== undefined && QUICK_ACCESS_GROUPS.has(item.group),
  );
  const firstName = auth.user?.name.split(' ')[0] ?? '';

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-5xl flex-col gap-8 p-6 lg:p-10">
        <Head title="Beranda" />
        <header>
          <p className="text-sm font-medium text-slate-500">{formatDate(now)}</p>
          <h1 className="mt-1 text-3xl font-semibold">
            {greeting(now.getHours())}, {firstName}
          </h1>
          <p className="mt-2 text-slate-600">Pilih pekerjaan yang ingin Anda mulai hari ini.</p>
        </header>

        {items.length > 0 ? (
          <section aria-label="Akses cepat" className="grid gap-4 sm:grid-cols-2">
            {items.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="card group flex gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-md"
              >
                <span className="bg-brand-50 text-brand-700 group-hover:bg-brand-600 flex size-11 shrink-0 items-center justify-center rounded-xl transition-colors group-hover:text-white">
                  {item.icon ? <Icon name={item.icon} className="size-5" /> : null}
                </span>
                <span>
                  <span className="block font-semibold">{item.label}</span>
                  <span className="mt-0.5 block text-sm text-slate-600">
                    {DESCRIPTIONS[item.href] ?? ''}
                  </span>
                </span>
              </Link>
            ))}
          </section>
        ) : (
          <p className="text-slate-600">Akun Anda belum memiliki akses ke modul pelayanan.</p>
        )}
      </main>
    </AppLayout>
  );
}
