import { Link } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
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
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Kata sandi sementara" />
        <h1>Kata sandi sementara</h1>
        <Alert tone="warning">
          Catat dan serahkan sekarang. Kata sandi ini tidak akan ditampilkan lagi; bila hilang,
          lakukan reset. Pengguna akan diminta menggantinya saat masuk pertama kali.
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
        <Link href="/settings/users" className="btn btn-primary w-fit">
          Selesai
        </Link>
      </main>
    </AppLayout>
  );
}
