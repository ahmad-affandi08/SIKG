import { Head, Link } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { Alert } from '@/components/ui/Alert';

interface Props {
  name: string;
  username: string;
  temporaryPassword: string;
}

/**
 * Kata sandi sementara tampil sekali pada respons ini saja; tidak disimpan di sesi, log, atau peramban.
 */
export default function Credential({ name, username, temporaryPassword }: Props) {
  return (
    <AppLayout>
      <main className="mx-auto flex max-w-xl flex-col gap-4 p-6">
        <Head title="Kata sandi sementara" />
        <h1 className="text-2xl font-semibold">Kata sandi sementara</h1>
        <Alert tone="warning">
          Catat dan serahkan sekarang. Kata sandi ini tidak akan ditampilkan lagi; bila hilang,
          lakukan reset.
        </Alert>
        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
          <dt className="font-medium">Nama</dt>
          <dd>{name}</dd>
          <dt className="font-medium">Nama pengguna</dt>
          <dd>{username}</dd>
          <dt className="font-medium">Kata sandi sementara</dt>
          <dd>
            <code className="rounded-control bg-slate-100 px-2 py-1 text-base">
              {temporaryPassword}
            </code>
          </dd>
        </dl>
        <Link
          href="/settings/users"
          className="min-h-touch rounded-control bg-brand-600 inline-flex w-fit items-center px-4 text-sm font-medium text-white"
        >
          Selesai
        </Link>
      </main>
    </AppLayout>
  );
}
