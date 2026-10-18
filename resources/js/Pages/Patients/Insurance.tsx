import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { DatePicker } from '@/components/ui/DatePicker';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { MoneyField } from '@/components/ui/MoneyField';
import { TextField } from '@/components/ui/TextField';
import { policySchema } from '@/features/insurance/schemas';
import type { PolicyValues } from '@/features/insurance/schemas';
import { formatDate, formatRupiah } from '@/lib/format';

interface Policy {
  id: string;
  insurer_id: string;
  insurer_name: string;
  policy_no: string;
  ceiling_amount: number | null;
  used_amount: number;
  remaining_amount: number | null;
  valid_from: string | null;
  valid_to: string | null;
  notes: string | null;
  active: boolean;
  valid_now: boolean;
}

interface Props {
  patient: { id: string; name: string; medical_record_no: string };
  policies: Policy[];
  insurers: SelectOption[];
  canManage: boolean;
}

/** P2-02: polis penjamin pasien dengan plafon dan masa berlaku. */
export default function Insurance({ patient, policies, insurers, canManage }: Props) {
  const form = useForm<PolicyValues>({
    insurer_id: '',
    policy_no: '',
    ceiling_amount: '',
    valid_from: '',
    valid_to: '',
    notes: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const base = `/patients/${patient.id}/insurance`;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = policySchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post(base, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
      },
    });
  }

  const error = (key: keyof PolicyValues): string | undefined => errors[key] ?? form.errors[key];

  return (
    <AppLayout>
      <main className="flex flex-col gap-4 p-3">
        <PageHead title="Penjamin pasien" />
        <PageHeader
          title="Penjamin pasien"
          back={{ href: `/patients/${patient.id}`, label: 'rekam pasien' }}
          description={`${patient.name} (${patient.medical_record_no})`}
        />

        {policies.length === 0 ? (
          <p className="text-muted text-sm">Pasien belum memiliki polis penjamin.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Polis penjamin pasien</caption>
            <thead>
              <tr>
                <th scope="col">Penjamin</th>
                <th scope="col">Nomor polis</th>
                <th scope="col">Masa berlaku</th>
                <th scope="col" className="text-right">
                  Plafon
                </th>
                <th scope="col" className="text-right">
                  Terpakai
                </th>
                <th scope="col" className="text-right">
                  Sisa
                </th>
                <th scope="col">Status</th>
                {canManage ? <th scope="col">Aksi</th> : null}
              </tr>
            </thead>
            <tbody>
              {policies.map((p) => (
                <tr key={p.id}>
                  <td>{p.insurer_name}</td>
                  <td>{p.policy_no}</td>
                  <td>
                    {p.valid_from ? formatDate(p.valid_from) : '-'} s.d.{' '}
                    {p.valid_to ? formatDate(p.valid_to) : '-'}
                  </td>
                  <td className="text-right">
                    {p.ceiling_amount === null ? 'Tanpa batas' : formatRupiah(p.ceiling_amount)}
                  </td>
                  <td className="text-right">{formatRupiah(p.used_amount)}</td>
                  <td className="text-right">
                    {p.remaining_amount === null ? '-' : formatRupiah(p.remaining_amount)}
                  </td>
                  <td>
                    <Badge tone={p.valid_now ? 'success' : 'neutral'}>
                      {!p.active ? 'Nonaktif' : p.valid_now ? 'Berlaku' : 'Di luar masa berlaku'}
                    </Badge>
                  </td>
                  {canManage ? (
                    <td>
                      <Button
                        variant="secondary"
                        onClick={() => {
                          router.post(
                            `${base}/${p.id}/${p.active ? 'deactivate' : 'activate'}`,
                            {},
                            { preserveScroll: true },
                          );
                        }}
                      >
                        {p.active ? 'Nonaktifkan' : 'Aktifkan'}
                      </Button>
                    </td>
                  ) : null}
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {canManage ? (
          <form
            onSubmit={submit}
            noValidate
            aria-label="Tambah polis"
            className="form-grid max-w-xl"
          >
            <h2>Tambah polis</h2>
            <Select
              label="Penjamin"
              placeholder="Pilih penjamin"
              options={insurers}
              value={form.data.insurer_id}
              onChange={(event) => {
                form.setData('insurer_id', event.target.value);
              }}
              error={error('insurer_id')}
            />
            <TextField
              label="Nomor polis atau kartu anggota"
              value={form.data.policy_no}
              onChange={(event) => {
                form.setData('policy_no', event.target.value);
              }}
              error={error('policy_no')}
            />
            <MoneyField
              label="Plafon (kosongkan bila tanpa batas)"
              value={form.data.ceiling_amount}
              onValueChange={(digits) => {
                form.setData('ceiling_amount', digits);
              }}
              error={error('ceiling_amount')}
            />
            <DatePicker
              label="Berlaku dari"
              value={form.data.valid_from}
              onChange={(value) => {
                form.setData('valid_from', value);
              }}
              error={error('valid_from')}
            />
            <DatePicker
              label="Berlaku sampai"
              value={form.data.valid_to}
              onChange={(value) => {
                form.setData('valid_to', value);
              }}
              error={error('valid_to')}
            />
            <TextField
              label="Catatan"
              value={form.data.notes}
              onChange={(event) => {
                form.setData('notes', event.target.value);
              }}
              error={error('notes')}
            />
            <div>
              <Button type="submit" loading={form.processing}>
                Simpan polis
              </Button>
            </div>
          </form>
        ) : null}
      </main>
    </AppLayout>
  );
}
