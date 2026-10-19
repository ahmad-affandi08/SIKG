import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { LEDGER_LABEL, sellPackageSchema } from '@/features/loyalty/schemas';
import { formatDate, formatRupiah } from '@/lib/format';

interface Props {
  patient: { id: string; name: string; medical_record_no: string };
  loyalty: {
    enabled: boolean;
    balance: number;
    point_value_amount: number;
    ledger: {
      delta: number;
      balance_after: number;
      kind: string;
      note: string | null;
      created_at: string;
    }[];
  };
  packages: {
    id: string;
    name: string;
    sessions_total: number;
    sessions_left: number;
    expires_on: string | null;
    status: string;
  }[];
  catalog: (SelectOption & { price_amount: number })[];
  open_encounters: SelectOption[];
  can: { sell: boolean };
}

/** P3-06: saldo dan riwayat poin, paket kunjungan pasien, dan penjualan paket. */
export default function Loyalty({
  patient,
  loyalty,
  packages,
  catalog,
  open_encounters,
  can,
}: Props) {
  const form = useForm({ package_id: '', encounter_id: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const err = (key: 'package_id' | 'encounter_id') => errors[key] ?? form.errors[key];

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = sellPackageSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post(`/patients/${patient.id}/packages`, { preserveScroll: true });
  }

  return (
    <AppLayout>
      <main className="flex max-w-3xl flex-col gap-4 p-3">
        <PageHead title="Poin dan paket" />
        <PageHeader
          title="Poin dan paket kunjungan"
          back={{ href: `/patients/${patient.id}`, label: 'rekam pasien' }}
          description={`${patient.name} (${patient.medical_record_no})`}
        />

        <section aria-labelledby="poin" className="flex flex-col gap-2">
          <h2 id="poin">Poin loyalitas</h2>
          {loyalty.enabled ? (
            <p className="text-sm font-semibold">
              Saldo {loyalty.balance} poin (setara{' '}
              {formatRupiah(loyalty.balance * loyalty.point_value_amount)})
            </p>
          ) : (
            <p className="text-muted text-sm">Poin loyalitas belum diaktifkan di klinik ini.</p>
          )}
          {loyalty.ledger.length === 0 ? (
            <EmptyState kind="box" size="compact" title="Belum ada riwayat poin" />
          ) : (
            <table className="w-full text-left text-sm">
              <caption className="sr-only">Riwayat poin</caption>
              <thead>
                <tr>
                  <th scope="col">Waktu</th>
                  <th scope="col">Keterangan</th>
                  <th scope="col" className="text-right">
                    Poin
                  </th>
                  <th scope="col" className="text-right">
                    Saldo
                  </th>
                </tr>
              </thead>
              <tbody>
                {loyalty.ledger.map((row, index) => (
                  <tr key={index}>
                    <td>{`${formatDate(row.created_at.slice(0, 10))} ${row.created_at.slice(11, 16)}`}</td>
                    <td>{LEDGER_LABEL[row.kind] ?? row.kind}</td>
                    <td className="text-right">
                      {row.delta > 0 ? `+${String(row.delta)}` : row.delta}
                    </td>
                    <td className="text-right">{row.balance_after}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>

        <section aria-labelledby="paket" className="flex flex-col gap-2">
          <h2 id="paket">Paket kunjungan</h2>
          {packages.length === 0 ? (
            <p className="text-muted text-sm">Pasien belum memiliki paket.</p>
          ) : (
            <table className="w-full text-left text-sm">
              <caption className="sr-only">Paket pasien</caption>
              <thead>
                <tr>
                  <th scope="col">Paket</th>
                  <th scope="col" className="text-right">
                    Sisa sesi
                  </th>
                  <th scope="col">Berlaku sampai</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                {packages.map((p) => (
                  <tr key={p.id}>
                    <td>{p.name}</td>
                    <td className="text-right">
                      {p.sessions_left} dari {p.sessions_total}
                    </td>
                    <td>{p.expires_on ? formatDate(p.expires_on) : '-'}</td>
                    <td>
                      <Badge tone={p.status === 'active' ? 'success' : 'neutral'}>
                        {p.status === 'active' ? 'Aktif' : 'Dibatalkan'}
                      </Badge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          {can.sell ? (
            <form
              onSubmit={submit}
              noValidate
              aria-label="Jual paket"
              className="form-grid max-w-md"
            >
              <Select
                required
                label="Paket"
                placeholder="Pilih paket"
                options={catalog.map((c) => ({
                  value: c.value,
                  label: `${c.label} - ${formatRupiah(c.price_amount)}`,
                }))}
                value={form.data.package_id}
                onChange={(event) => {
                  form.setData('package_id', event.target.value);
                }}
                error={err('package_id')}
              />
              <Select
                required
                label="Tagihkan ke kunjungan"
                placeholder={
                  open_encounters.length === 0 ? 'Tidak ada kunjungan berjalan' : 'Pilih kunjungan'
                }
                options={open_encounters}
                value={form.data.encounter_id}
                onChange={(event) => {
                  form.setData('encounter_id', event.target.value);
                }}
                error={err('encounter_id')}
              />
              <div>
                <Button
                  type="submit"
                  loading={form.processing}
                  disabled={open_encounters.length === 0 || catalog.length === 0}
                >
                  Jual paket
                </Button>
              </div>
            </form>
          ) : null}
        </section>
      </main>
    </AppLayout>
  );
}
