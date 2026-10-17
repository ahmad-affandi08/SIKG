import { Head, Link, usePage } from '@inertiajs/react';

/** Kerangka beranda. Isi per peran dikerjakan bersama kebutuhan terkait. */
export default function Beranda() {
  const { auth, clinic } = usePage().props;

  return (
    <main className="mx-auto max-w-3xl p-6">
      <Head title="Beranda" />
      <h1 className="text-2xl font-semibold">{clinic?.name ?? 'SIKG'}</h1>
      <p className="mt-2 text-slate-600">
        Masuk sebagai {auth.user?.name}
        {auth.user?.roles.length ? ` (${auth.user.roles.join(', ')})` : ''}.
      </p>
      <Link
        href="/logout"
        method="post"
        as="button"
        className="min-h-touch rounded-control mt-4 px-4 ring-1 ring-slate-300"
      >
        Keluar
      </Link>
    </main>
  );
}
