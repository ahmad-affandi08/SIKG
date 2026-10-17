import { Link, usePage } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { NAV_ITEMS, visibleNavItems } from '@/components/layout/navigation';
import { formatDate } from '@/lib/format';

const QUICK_ACCESS_GROUPS = new Set(['Layanan', 'Farmasi', 'Kasir']);

const DESCRIPTIONS: Record<string, string> = {
  '/patients': 'Cari, daftarkan, dan buka rekam medis pasien.',
  '/schedule': 'Buat janji dan atur kalender dokter dan kursi.',
  '/queue': 'Pantau antrean hari ini dan masukkan pasien tanpa janji.',
  '/stock': 'Lihat stok, catat mutasi, dan lakukan penyesuaian.',
  '/invoices': 'Tagihan, pembayaran, diskon, dan cetak kwitansi.',
  '/cash-closings': 'Hitung uang fisik dan cocokkan dengan catatan kas.',
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
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Beranda" />
        <header>
          <p className="text-muted">{formatDate(now)}</p>
          <h1 className="mt-0.5">
            {greeting(now.getHours())}, {firstName}
          </h1>
          <p className="text-muted mt-1">Pilih pekerjaan yang ingin Anda mulai hari ini.</p>
        </header>

        {items.length > 0 ? (
          <section aria-label="Akses cepat">
            <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
              {items.map((item) => (
                <li key={item.href}>
                  <Link href={item.href} className="pane hover:bg-brand-50 block h-full">
                    <span className="pane-head">{item.label}</span>
                    <span className="text-muted block p-2">{DESCRIPTIONS[item.href] ?? ''}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </section>
        ) : (
          <p className="text-muted">Akun Anda belum memiliki akses ke modul pelayanan.</p>
        )}
      </main>
    </AppLayout>
  );
}
