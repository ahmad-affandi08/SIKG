import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { userFormSchema } from '@/features/identityAccess/schemas';
import type { UserFormValues } from '@/features/identityAccess/schemas';

interface Props {
  user: {
    id: string;
    name: string;
    email: string;
    username: string;
    role: string | null;
    branch_id: string | null;
    isActive: boolean;
  } | null;
  roles: SelectOption[];
  branches: SelectOption[];
  canManage?: boolean;
}

export default function Form({ user, roles, branches, canManage = true }: Props) {
  const form = useForm<UserFormValues>({
    name: user?.name ?? '',
    email: user?.email ?? '',
    username: user?.username ?? '',
    role: user?.role ?? '',
    branch_id: user?.branch_id ?? '',
  });
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});
  const editing = user !== null;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = userFormSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setClientErrors(next);
      return;
    }
    setClientErrors({});
    if (editing) {
      form.put(`/settings/users/${user.id}`);
    } else {
      form.post('/settings/users');
    }
  }

  const error = (key: keyof UserFormValues): string | undefined =>
    clientErrors[key] ?? form.errors[key];

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title={editing ? 'Ubah pengguna' : 'Tambah pengguna'} />
        <h1>{editing ? 'Ubah pengguna' : 'Tambah pengguna'}</h1>
        {!canManage ? (
          <p className="text-muted text-sm">Anda hanya dapat melihat pengguna ini.</p>
        ) : null}

        <form onSubmit={submit} noValidate className="form-grid">
          <fieldset disabled={!canManage} className="form-grid">
            <TextField
              field="name"
              label="Nama lengkap"
              value={form.data.name}
              onChange={(event) => {
                form.setData('name', event.target.value);
              }}
              error={error('name')}
            />
            <TextField
              field="email"
              label="Email"
              type="email"
              autoComplete="off"
              value={form.data.email}
              onChange={(event) => {
                form.setData('email', event.target.value);
              }}
              error={error('email')}
            />
            <TextField
              field="username"
              label="Nama pengguna"
              autoComplete="off"
              value={form.data.username}
              onChange={(event) => {
                form.setData('username', event.target.value);
              }}
              error={error('username')}
            />
            <Select
              field="role"
              label="Peran"
              placeholder="Pilih peran"
              options={roles}
              value={form.data.role}
              onChange={(event) => {
                form.setData('role', event.target.value);
              }}
              error={error('role')}
            />
            <Select
              field="branch_id"
              label="Cabang"
              placeholder="Semua cabang"
              options={branches}
              value={form.data.branch_id}
              onChange={(event) => {
                form.setData('branch_id', event.target.value);
              }}
              error={error('branch_id')}
            />
          </fieldset>

          {!editing ? (
            <p className="text-muted text-sm">
              Kata sandi sementara dibuat otomatis dan ditampilkan satu kali setelah pengguna
              dibuat.
            </p>
          ) : null}

          <div className="flex gap-3">
            {canManage ? (
              <Button type="submit" loading={form.processing}>
                {editing ? 'Simpan perubahan' : 'Buat pengguna'}
              </Button>
            ) : null}
            <Link href="/settings/users" className="btn btn-secondary">
              Kembali
            </Link>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
