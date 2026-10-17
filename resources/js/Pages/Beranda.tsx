import { Head, usePage } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';

/** Kerangka beranda. Isi per peran dikerjakan bersama kebutuhan terkait. */
export default function Beranda() {
  const { auth } = usePage().props;

  return (
    <AppLayout>
      <main className="mx-auto max-w-3xl p-6">
        <Head title="Beranda" />
        <h1 className="text-2xl font-semibold">Beranda</h1>
        <p className="mt-2 text-slate-600">
          Masuk sebagai {auth.user?.name}
          {auth.user?.roles.length ? ` (${auth.user.roles.join(', ')})` : ''}.
        </p>
      </main>
    </AppLayout>
  );
}
