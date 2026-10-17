import { Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
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
  const columns = entity.columns
    .map((key) => entity.fields.find((field) => field.key === key))
    .filter((field): field is MasterFieldDef => field !== undefined);

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title={entity.title} />
        <PageHeader
          title={entity.title}
          actions={
            canManage ? (
              <Link href={`${base}/create`} className="btn btn-primary">
                Tambah
              </Link>
            ) : null
          }
        />

        {tabs.length > 1 ? (
          <nav aria-label="Jenis master data" className="flex flex-wrap gap-2">
            {tabs.map((tab) => (
              <Link
                key={tab.key}
                href={`/settings/master/${tab.key}`}
                aria-current={tab.key === entity.key ? 'page' : undefined}
                className="btn btn-secondary"
              >
                {tab.title}
              </Link>
            ))}
          </nav>
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
              <tr key={row.id} className="align-top">
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
                      <Link href={`${base}/${row.id}/edit`} className="btn btn-secondary">
                        Ubah
                      </Link>
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
                <td colSpan={columns.length + 2} className="text-muted py-4">
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
