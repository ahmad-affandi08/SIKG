import { Head } from '@inertiajs/react';

/** Kerangka halaman awal. Isi beranda per peran dikerjakan bersama kebutuhan terkait. */
export default function Beranda() {
  return (
    <main className="mx-auto max-w-3xl p-6">
      <Head title="Beranda" />
      <h1 className="text-2xl font-semibold">SIKG</h1>
      <p className="mt-2 text-slate-600">Sistem Informasi Klinik Gigi.</p>
    </main>
  );
}
