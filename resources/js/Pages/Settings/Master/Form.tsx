import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { DatePicker } from '@/components/ui/DatePicker';
import { Select } from '@/components/ui/Select';
import { TextArea } from '@/components/ui/TextArea';
import { TextField } from '@/components/ui/TextField';
import { buildMasterSchema, emptyValues } from '@/features/master/schema';
import type { MasterFieldDef } from '@/features/master/schema';

type Row = Record<string, string | number | boolean | null> & { id: string };

interface Props {
  entity: { key: string; title: string; fields: MasterFieldDef[] };
  row: Row | null;
}

function initialValues(fields: MasterFieldDef[], row: Row | null): Record<string, string> {
  const values = emptyValues(fields);
  if (row) {
    for (const field of fields) {
      const value = row[field.key];
      values[field.key] = value === null || value === undefined ? '' : String(value);
    }
  }
  return values;
}

export default function Form({ entity, row }: Props) {
  const base = `/settings/master/${entity.key}`;
  const editing = row !== null;
  const form = useForm<Record<string, string>>(initialValues(entity.fields, row));
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});
  const schema = buildMasterSchema(entity.fields);

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
    if (row) {
      form.put(`${base}/${row.id}`);
    } else {
      form.post(base);
    }
  }

  const errorFor = (key: string): string | undefined =>
    clientErrors[key] ?? (form.errors as Record<string, string | undefined>)[key];

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title={`${editing ? 'Ubah' : 'Tambah'} ${entity.title}`} />
        <PageHeader
          title={
            editing ? `Ubah ${entity.title.toLowerCase()}` : `Tambah ${entity.title.toLowerCase()}`
          }
          back={{ href: base, label: entity.title.toLowerCase() }}
        />
        <form
          onSubmit={submit}
          noValidate
          aria-label={editing ? 'Ubah data' : 'Tambah data'}
          className="form-grid"
        >
          {entity.fields.map((field) =>
            field.type === 'select' ? (
              <Select
                field={field.key}
                key={field.key}
                label={field.label}
                options={field.options}
                placeholder={field.required ? 'Pilih' : 'Tidak dipilih'}
                value={form.data[field.key] ?? ''}
                onChange={(event) => {
                  form.setData(field.key, event.target.value);
                }}
                error={errorFor(field.key)}
              />
            ) : field.type === 'date' ? (
              <DatePicker
                key={field.key}
                field={field.key}
                label={field.label}
                value={form.data[field.key] ?? ''}
                onChange={(value) => {
                  form.setData(field.key, value);
                }}
                error={errorFor(field.key)}
              />
            ) : field.type === 'textarea' ? (
              <div key={field.key} className="flex flex-col gap-1">
                <TextArea
                  field={field.key}
                  label={field.label}
                  value={form.data[field.key] ?? ''}
                  onChange={(event) => {
                    form.setData(field.key, event.target.value);
                  }}
                  error={errorFor(field.key)}
                />
                {field.help ? <p className="text-muted text-sm">{field.help}</p> : null}
              </div>
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
                {field.help ? <p className="text-muted text-sm">{field.help}</p> : null}
              </div>
            ),
          )}
          <div className="flex flex-wrap gap-3">
            <Button type="submit" loading={form.processing}>
              Simpan
            </Button>
            <Link href={base} className="btn btn-secondary">
              Batal
            </Link>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
