import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { Select } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { buildMasterSchema, emptyValues } from '@/features/master/schema';
import type { MasterFieldDef } from '@/features/master/schema';
import { formatRupiah } from '@/lib/format';

type Row = Record<string, string | number | boolean | null> & {
  id: string;
  is_active: boolean;
};

interface Props {
  entity: {
    key: string;
    title: string;
    fields: MasterFieldDef[];
    columns: string[];
  };
  rows: Row[];
  canManage: boolean;
  tabs: { key: string; title: string }[];
}

function display(field: MasterFieldDef, value: Row[string] | undefined): string {
  if (value === null || value === undefined || value === '') {
    return '-';
  }
  if (field.type === 'money') {
    return formatRupiah(Number(value));
  }
  if (field.type === 'select') {
    return field.options.find((option) => option.value === String(value))?.label ?? String(value);
  }
  return String(value);
}

export default function Index({ entity, rows, canManage, tabs }: Props) {
  const base = `/settings/master/${entity.key}`;
  const form = useForm<Record<string, string>>(emptyValues(entity.fields));
  const [editing, setEditing] = useState<string | null>(null);
  const [open, setOpen] = useState(false);
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});
  const schema = buildMasterSchema(entity.fields);
  const columns = entity.columns
    .map((key) => entity.fields.find((field) => field.key === key))
    .filter((field): field is MasterFieldDef => field !== undefined);

  function startCreate() {
    form.setData(emptyValues(entity.fields));
    form.clearErrors();
    setClientErrors({});
    setEditing(null);
    setOpen(true);
  }

  function startEdit(row: Row) {
    const values = emptyValues(entity.fields);
    for (const field of entity.fields) {
      const value = row[field.key];
      values[field.key] = value === null || value === undefined ? '' : String(value);
    }
    form.setData(values);
    form.clearErrors();
    setClientErrors({});
    setEditing(row.id);
    setOpen(true);
  }

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = schema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setClientErrors(next);
      return;
    }
    setClientErrors({});
    const options = {
      preserveScroll: true,
      onSuccess: () => {
        setOpen(false);
      },
    };
    if (editing === null) {
      form.post(base, options);
    } else {
      form.put(`${base}/${editing}`, options);
    }
  }

  const errorFor = (key: string): string | undefined =>
    clientErrors[key] ?? (form.errors as Record<string, string | undefined>)[key];

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-6xl flex-col gap-4 p-6">
        <Head title={entity.title} />
        <h1 className="text-2xl font-semibold">{entity.title}</h1>

        {tabs.length > 1 ? (
          <nav aria-label="Jenis master data" className="flex flex-wrap gap-2">
            {tabs.map((tab) => (
              <Link
                key={tab.key}
                href={`/settings/master/${tab.key}`}
                aria-current={tab.key === entity.key ? 'page' : undefined}
                className={`min-h-touch rounded-control inline-flex items-center px-4 text-sm ring-1 ring-slate-300 ${
                  tab.key === entity.key ? 'bg-brand-600 text-white' : ''
                }`}
              >
                {tab.title}
              </Link>
            ))}
          </nav>
        ) : null}

        {canManage && !open ? (
          <div>
            <Button onClick={startCreate}>Tambah</Button>
          </div>
        ) : null}

        {open ? (
          <form
            onSubmit={submit}
            noValidate
            aria-label={editing === null ? 'Tambah data' : 'Ubah data'}
            className="rounded-control flex max-w-xl flex-col gap-4 p-4 ring-1 ring-slate-200"
          >
            {entity.fields.map((field) =>
              field.type === 'select' ? (
                <Select
                  key={field.key}
                  label={field.label}
                  options={field.options}
                  placeholder={field.required ? 'Pilih' : 'Semua'}
                  value={form.data[field.key] ?? ''}
                  onChange={(event) => {
                    form.setData(field.key, event.target.value);
                  }}
                  error={errorFor(field.key)}
                />
              ) : (
                <div key={field.key} className="flex flex-col gap-1">
                  <TextField
                    label={field.label}
                    inputMode={field.type === 'text' ? undefined : 'numeric'}
                    value={form.data[field.key] ?? ''}
                    onChange={(event) => {
                      form.setData(field.key, event.target.value);
                    }}
                    error={errorFor(field.key)}
                  />
                  {field.help ? <p className="text-sm text-slate-600">{field.help}</p> : null}
                </div>
              ),
            )}
            <div className="flex gap-3">
              <Button type="submit" loading={form.processing}>
                Simpan
              </Button>
              <Button
                variant="secondary"
                onClick={() => {
                  setOpen(false);
                }}
              >
                Batal
              </Button>
            </div>
          </form>
        ) : null}

        <table className="w-full text-left text-sm">
          <caption className="sr-only">{entity.title}</caption>
          <thead>
            <tr>
              {columns.map((field) => (
                <th key={field.key} scope="col" className="py-2">
                  {field.label}
                </th>
              ))}
              <th scope="col">Status</th>
              <th scope="col">
                <span className="sr-only">Aksi</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="border-t border-slate-200 align-top">
                {columns.map((field) => (
                  <td key={field.key} className="py-2 pr-3">
                    {display(field, row[field.key])}
                  </td>
                ))}
                <td className="pr-3">
                  <Badge tone={row.is_active ? 'success' : 'neutral'}>
                    {row.is_active ? 'Aktif' : 'Nonaktif'}
                  </Badge>
                </td>
                <td>
                  {canManage ? (
                    <div className="flex flex-wrap gap-2">
                      <Button
                        variant="secondary"
                        onClick={() => {
                          startEdit(row);
                        }}
                      >
                        Ubah
                      </Button>
                      {row.is_active ? (
                        <ConfirmAction
                          label="Nonaktifkan"
                          message="Data tidak muncul lagi di pilihan baru. Riwayat tetap tersimpan."
                          confirmLabel="Ya, nonaktifkan"
                          onConfirm={() => {
                            router.post(
                              `${base}/${row.id}/deactivate`,
                              {},
                              {
                                preserveScroll: true,
                              },
                            );
                          }}
                        />
                      ) : (
                        <ConfirmAction
                          label="Aktifkan"
                          message="Data muncul kembali di pilihan baru."
                          confirmLabel="Ya, aktifkan"
                          variant="primary"
                          onConfirm={() => {
                            router.post(
                              `${base}/${row.id}/activate`,
                              {},
                              {
                                preserveScroll: true,
                              },
                            );
                          }}
                        />
                      )}
                    </div>
                  ) : null}
                </td>
              </tr>
            ))}
            {rows.length === 0 ? (
              <tr>
                <td colSpan={columns.length + 2} className="py-4 text-slate-600">
                  Belum ada data.
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </main>
    </AppLayout>
  );
}
