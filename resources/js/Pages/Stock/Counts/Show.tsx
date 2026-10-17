import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { formatDate } from '@/lib/format';

interface Line {
  id: string;
  name: string;
  unit: string;
  system_quantity: number;
  counted_quantity: number | null;
  difference: number | null;
}

interface Props {
  session: {
    id: string;
    scheduled_for: string;
    status: string;
    status_label: string;
    source_label: string;
  };
  lines: Line[];
  can: { manage: boolean };
}

/** P2-05: memasukkan hasil hitung fisik; menyelesaikan sesi menyesuaikan stok sistem ke jumlah fisik. */
export default function Show({ session, lines, can }: Props) {
  const editable = can.manage && session.status === 'open';
  const base = `/stock/counts/${session.id}`;
  const form = useForm<{ counts: Record<string, string> }>({
    counts: Object.fromEntries(
      lines.map((line) => [
        line.id,
        line.counted_quantity === null ? '' : String(line.counted_quantity),
      ]),
    ),
  });
  const [error, setError] = useState<string | undefined>();

  function save(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const bad = Object.values(form.data.counts).some(
      (value) => value !== '' && !/^\d{1,7}$/.test(value),
    );
    if (bad) {
      setError('Jumlah fisik berupa bilangan bulat tidak negatif.');
      return;
    }
    setError(undefined);
    form.transform((data) => ({
      counts: Object.fromEntries(
        Object.entries(data.counts).map(([id, value]) => [id, value === '' ? null : Number(value)]),
      ),
    }));
    form.post(`${base}/save`, { preserveScroll: true });
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Hitung stok" />
        <PageHeader
          title={`Opname ${formatDate(session.scheduled_for)}`}
          back={{ href: '/stock/counts', label: 'opname' }}
          description={`${session.source_label}, ${session.status_label}`}
          actions={
            editable ? (
              <>
                <ConfirmAction
                  label="Selesaikan opname"
                  variant="primary"
                  message="Stok sistem disesuaikan ke jumlah fisik untuk setiap obat yang sudah dihitung. Simpan hasil hitung dulu. Lanjutkan?"
                  confirmLabel="Ya, selesaikan"
                  onConfirm={() => {
                    router.post(`${base}/complete`, {});
                  }}
                />
                <ConfirmAction
                  label="Batalkan sesi"
                  message="Sesi dibatalkan dan hasil hitung tidak dipakai. Lanjutkan?"
                  confirmLabel="Ya, batalkan"
                  onConfirm={() => {
                    router.post(`${base}/cancel`, {});
                  }}
                />
              </>
            ) : null
          }
        />
        <Badge tone={session.status === 'open' ? 'warning' : 'neutral'}>
          {session.status_label}
        </Badge>
        <form onSubmit={save} noValidate aria-label="Hasil hitung" className="flex flex-col gap-3">
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Hasil hitung fisik</caption>
            <thead>
              <tr>
                <th scope="col">Obat</th>
                <th scope="col" className="text-right">
                  Stok sistem
                </th>
                <th scope="col">Hitung fisik</th>
                <th scope="col" className="text-right">
                  Selisih
                </th>
              </tr>
            </thead>
            <tbody>
              {lines.map((line) => (
                <tr key={line.id}>
                  <td>{line.name}</td>
                  <td className="text-right">
                    {line.system_quantity} {line.unit}
                  </td>
                  <td>
                    {editable ? (
                      <input
                        className="field w-28"
                        inputMode="numeric"
                        aria-label={`Hitung fisik ${line.name}`}
                        value={form.data.counts[line.id] ?? ''}
                        onChange={(event) => {
                          form.setData('counts', {
                            ...form.data.counts,
                            [line.id]: event.target.value,
                          });
                        }}
                      />
                    ) : (
                      (line.counted_quantity ?? '-')
                    )}
                  </td>
                  <td className="text-right">
                    {line.difference === null
                      ? '-'
                      : line.difference > 0
                        ? `+${String(line.difference)}`
                        : line.difference}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {error ? (
            <p role="alert" className="text-danger-600 text-sm">
              {error}
            </p>
          ) : null}
          {editable ? (
            <div>
              <Button type="submit" loading={form.processing}>
                Simpan hasil hitung
              </Button>
            </div>
          ) : null}
        </form>
      </main>
    </AppLayout>
  );
}
