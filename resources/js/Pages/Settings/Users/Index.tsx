import { Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Badge } from '@/components/ui/Badge';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { roleLabel } from '@/features/identityAccess/roles';
import { formatDateTime } from '@/lib/format';

interface UserRow {
  id: string;
  name: string;
  email: string;
  username: string;
  role: string | null;
  branch: string | null;
  isActive: boolean;
  twoFactorEnabled: boolean;
  lastLoginAt: string | null;
  canManage: boolean;
}

interface Props {
  users: UserRow[];
  canCreate: boolean;
}

export default function Index({ users, canCreate }: Props) {
  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Pengguna" />
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h1>Pengguna</h1>
          {canCreate ? (
            <Link href="/settings/users/create" className="btn btn-primary">
              Tambah pengguna
            </Link>
          ) : null}
        </div>

        <table className="w-full text-left text-sm">
          <caption className="sr-only">Daftar pengguna klinik</caption>
          <thead>
            <tr>
              <th scope="col" className="py-2">
                Nama
              </th>
              <th scope="col">Nama pengguna</th>
              <th scope="col">Peran</th>
              <th scope="col">Cabang</th>
              <th scope="col">Status</th>
              <th scope="col">Masuk terakhir</th>
              <th scope="col">
                <span className="sr-only">Aksi</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {users.map((user) => (
              <tr key={user.id} className="align-top">
                <td className="py-2 pr-3">
                  <div className="font-medium">{user.name}</div>
                  <div className="text-muted">{user.email}</div>
                </td>
                <td className="pr-3">{user.username}</td>
                <td className="pr-3">{roleLabel(user.role)}</td>
                <td className="pr-3">{user.branch ?? 'Semua cabang'}</td>
                <td className="pr-3">
                  <Badge tone={user.isActive ? 'success' : 'neutral'}>
                    {user.isActive ? 'Aktif' : 'Nonaktif'}
                  </Badge>
                </td>
                <td className="pr-3">
                  {user.lastLoginAt ? formatDateTime(user.lastLoginAt) : '-'}
                </td>
                <td>
                  {user.canManage ? (
                    <div className="flex flex-wrap gap-2">
                      <Link href={`/settings/users/${user.id}/edit`} className="btn btn-secondary">
                        Ubah
                      </Link>
                      {user.isActive ? (
                        <ConfirmAction
                          label={`Nonaktifkan ${user.name}`}
                          message={`${user.name} tidak akan bisa masuk lagi. Riwayatnya tetap tersimpan.`}
                          confirmLabel="Ya, nonaktifkan"
                          onConfirm={() => {
                            router.post(
                              `/settings/users/${user.id}/deactivate`,
                              {},
                              { preserveScroll: true },
                            );
                          }}
                        />
                      ) : (
                        <ConfirmAction
                          label={`Aktifkan ${user.name}`}
                          message={`${user.name} dapat masuk kembali.`}
                          confirmLabel="Ya, aktifkan"
                          variant="primary"
                          onConfirm={() => {
                            router.post(
                              `/settings/users/${user.id}/activate`,
                              {},
                              { preserveScroll: true },
                            );
                          }}
                        />
                      )}
                      <ConfirmAction
                        label={`Reset kata sandi ${user.name}`}
                        message="Kata sandi lama langsung tidak berlaku dan kata sandi sementara baru dibuat."
                        confirmLabel="Ya, reset"
                        onConfirm={() => {
                          router.post(`/settings/users/${user.id}/reset-password`);
                        }}
                      />
                      {user.twoFactorEnabled ? (
                        <ConfirmAction
                          label={`Matikan verifikasi dua langkah ${user.name}`}
                          message={`${user.name} tidak lagi diminta kode saat masuk dan sesinya berakhir. Pakai ini hanya bila perangkat dan kode pemulihannya hilang.`}
                          confirmLabel="Ya, matikan"
                          onConfirm={() => {
                            router.post(`/settings/users/${user.id}/reset-two-factor`);
                          }}
                        />
                      ) : null}
                    </div>
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </main>
    </AppLayout>
  );
}
