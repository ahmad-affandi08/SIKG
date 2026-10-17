import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { adjustmentSchema, stockLevel, stockMovementSchema } from '@/features/pharmacy/schemas';
import { formatDateTime } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface MedicineRow {
  id: string;
  name: string;
  unit: string;
  quantity: number;
  min_stock: number;
  is_active: boolean;
}

interface MovementRow {
  id: string;
  type: string;
  type_label: string;
  quantity: number;
  balance_after: number;
  reason: string | null;
  batch_no: string | null;
  expiry_date: string | null;
  created_at: string;
}

interface Props {
  medicines: MedicineRow[];
  selected: string | null;
  movements: MovementRow[];
  can: { manage: boolean; override: boolean };
}

const LEVEL_LABEL = { empty: 'Habis', low: 'Di bawah minimum', ok: 'Cukup' } as const;
const LEVEL_TONE = { empty: 'danger', low: 'warning', ok: 'success' } as const;

const TYPE_OPTIONS: SelectOption[] = [
  { value: 'in', label: 'Stok masuk' },
  { value: 'out', label: 'Stok keluar (pemakaian atau penjualan)' },
];

function errorMap(issues: readonly { path: PropertyKey[]; message: string }[]) {
  const next: Record<string, string> = {};
  for (const issue of issues) {
    next[String(issue.path[0])] ??= issue.message;
  }
  return next;
}

export default function Index({ medicines, selected, movements, can }: Props) {
  const { flash } = usePage().props;
  const timezone = useClinicTimezone();
  const options: SelectOption[] = medicines
    .filter((m) => m.is_active)
    .map((m) => ({ value: m.id, label: `${m.name} (${m.unit})` }));
  const selectedMedicine = medicines.find((m) => m.id === selected) ?? null;

  const [move, setMove] = useState({
    medicine_id: selected ?? '',
    type: 'in',
    quantity: '',
    batch_no: '',
    expiry_date: '',
    reason: '',
  });
  const [overrideReason, setOverrideReason] = useState('');
  const [moveErrors, setMoveErrors] = useState<Record<string, string>>({});
  const [adjust, setAdjust] = useState({
    medicine_id: selected ?? '',
    counted_quantity: '',
    reason: '',
  });
  const [adjustErrors, setAdjustErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const stockShort = flash.error_code === 'stock.insufficient';

  function submitMovement(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = stockMovementSchema.safeParse(move);
    if (!result.success) {
      setMoveErrors(errorMap(result.error.issues));
      return;
    }
    setMoveErrors({});
    setSaving(true);
    router.post(
      '/stock/movements',
      {
        medicine_id: move.medicine_id,
        type: move.type,
        quantity: Number(move.quantity),
        batch_no: move.batch_no || null,
        expiry_date: move.expiry_date || null,
        reason: move.reason || null,
        override_reason: overrideReason || null,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setMove((current) => ({
            ...current,
            quantity: '',
            batch_no: '',
            expiry_date: '',
            reason: '',
          }));
          setOverrideReason('');
        },
        onError: (errors) => {
          setMoveErrors(errors);
        },
        onFinish: () => {
          setSaving(false);
        },
      },
    );
  }

  function submitAdjustment(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = adjustmentSchema.safeParse(adjust);
    if (!result.success) {
      setAdjustErrors(errorMap(result.error.issues));
      return;
    }
    setAdjustErrors({});
    setSaving(true);
    router.post(
      '/stock/adjustments',
      {
        medicine_id: adjust.medicine_id,
        counted_quantity: Number(adjust.counted_quantity),
        reason: adjust.reason,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setAdjust((current) => ({ ...current, counted_quantity: '', reason: '' }));
        },
        onError: (errors) => {
          setAdjustErrors(errors);
        },
        onFinish: () => {
          setSaving(false);
        },
      },
    );
  }

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-6xl flex-col gap-6 p-6">
        <Head title="Stok obat" />
        <h1 className="text-2xl font-semibold">Stok obat dan bahan</h1>

        <table className="w-full text-left text-sm">
          <caption className="sr-only">Stok obat dan bahan</caption>
          <thead>
            <tr>
              <th scope="col" className="py-2">
                Obat
              </th>
              <th scope="col">Stok</th>
              <th scope="col">Minimum</th>
              <th scope="col">Status</th>
              <th scope="col">
                <span className="sr-only">Aksi</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {medicines.map((medicine) => {
              const level = stockLevel(medicine.quantity, medicine.min_stock);
              return (
                <tr key={medicine.id} className="border-t border-slate-200">
                  <td className="py-2 pr-3 font-medium">
                    {medicine.name}
                    {medicine.is_active ? '' : ' (nonaktif)'}
                  </td>
                  <td className="pr-3">
                    {medicine.quantity} {medicine.unit}
                  </td>
                  <td className="pr-3">{medicine.min_stock}</td>
                  <td className="pr-3">
                    <Badge tone={LEVEL_TONE[level]}>{LEVEL_LABEL[level]}</Badge>
                  </td>
                  <td>
                    <Link
                      href={`/stock?medicine=${medicine.id}`}
                      preserveScroll
                      className="min-h-touch rounded-control inline-flex items-center px-4 text-sm ring-1 ring-slate-300"
                      aria-label={`Riwayat mutasi ${medicine.name}`}
                    >
                      Riwayat
                    </Link>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {medicines.length === 0 ? (
          <p className="text-sm text-slate-600">Belum ada obat di katalog.</p>
        ) : null}

        {selectedMedicine ? (
          <section aria-label="Riwayat mutasi" className="flex flex-col gap-2">
            <h2 className="text-lg font-semibold">Riwayat mutasi {selectedMedicine.name}</h2>
            {movements.length === 0 ? (
              <p className="text-sm text-slate-600">Belum ada mutasi.</p>
            ) : (
              <table className="w-full text-left text-sm">
                <caption className="sr-only">Riwayat mutasi stok</caption>
                <thead>
                  <tr>
                    <th scope="col" className="py-2">
                      Waktu
                    </th>
                    <th scope="col">Jenis</th>
                    <th scope="col">Jumlah</th>
                    <th scope="col">Saldo</th>
                    <th scope="col">Batch</th>
                    <th scope="col">Alasan</th>
                  </tr>
                </thead>
                <tbody>
                  {movements.map((m) => (
                    <tr key={m.id} className="border-t border-slate-200">
                      <td className="py-2 pr-3">{formatDateTime(m.created_at, timezone)}</td>
                      <td className="pr-3">{m.type_label}</td>
                      <td className="pr-3">
                        {m.quantity > 0 ? `+${String(m.quantity)}` : m.quantity}
                      </td>
                      <td className="pr-3">{m.balance_after}</td>
                      <td className="pr-3">
                        {m.batch_no ?? '-'}
                        {m.expiry_date ? ` (exp ${m.expiry_date})` : ''}
                      </td>
                      <td>{m.reason ?? '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </section>
        ) : null}

        {can.manage ? (
          <div className="grid gap-6 md:grid-cols-2">
            <form
              onSubmit={submitMovement}
              noValidate
              aria-label="Mutasi stok"
              className="flex flex-col gap-3"
            >
              <h2 className="text-lg font-semibold">Stok masuk atau keluar</h2>
              {stockShort && flash.error ? <Alert tone="error">{flash.error}</Alert> : null}
              <Select
                label="Obat"
                placeholder="Pilih obat"
                options={options}
                value={move.medicine_id}
                error={moveErrors.medicine_id}
                onChange={(e) => {
                  setMove({ ...move, medicine_id: e.target.value });
                }}
              />
              <Select
                label="Jenis mutasi"
                options={TYPE_OPTIONS}
                value={move.type}
                onChange={(e) => {
                  setMove({ ...move, type: e.target.value });
                }}
              />
              <TextField
                label="Jumlah"
                inputMode="numeric"
                value={move.quantity}
                error={moveErrors.quantity}
                onChange={(e) => {
                  setMove({ ...move, quantity: e.target.value });
                }}
              />
              {move.type === 'in' ? (
                <>
                  <TextField
                    label="Nomor batch (opsional)"
                    value={move.batch_no}
                    error={moveErrors.batch_no}
                    onChange={(e) => {
                      setMove({ ...move, batch_no: e.target.value });
                    }}
                  />
                  <TextField
                    label="Tanggal kedaluwarsa (YYYY-MM-DD, opsional)"
                    placeholder="YYYY-MM-DD"
                    value={move.expiry_date}
                    error={moveErrors.expiry_date}
                    onChange={(e) => {
                      setMove({ ...move, expiry_date: e.target.value });
                    }}
                  />
                </>
              ) : null}
              <TextField
                label={move.type === 'out' ? 'Alasan (wajib)' : 'Keterangan (opsional)'}
                value={move.reason}
                error={moveErrors.reason}
                onChange={(e) => {
                  setMove({ ...move, reason: e.target.value });
                }}
              />
              {move.type === 'out' && can.override && stockShort ? (
                <TextField
                  label="Alasan melebihi stok (khusus Admin)"
                  value={overrideReason}
                  onChange={(e) => {
                    setOverrideReason(e.target.value);
                  }}
                />
              ) : null}
              <div>
                <Button type="submit" disabled={saving}>
                  Simpan mutasi
                </Button>
              </div>
            </form>

            <form
              onSubmit={submitAdjustment}
              noValidate
              aria-label="Penyesuaian stok"
              className="flex flex-col gap-3"
            >
              <h2 className="text-lg font-semibold">Penyesuaian stok (opname)</h2>
              <Select
                label="Obat"
                placeholder="Pilih obat"
                options={options}
                value={adjust.medicine_id}
                error={adjustErrors.medicine_id}
                onChange={(e) => {
                  setAdjust({ ...adjust, medicine_id: e.target.value });
                }}
              />
              <TextField
                label="Jumlah fisik terhitung"
                inputMode="numeric"
                value={adjust.counted_quantity}
                error={adjustErrors.counted_quantity}
                onChange={(e) => {
                  setAdjust({ ...adjust, counted_quantity: e.target.value });
                }}
              />
              <TextField
                label="Alasan penyesuaian"
                value={adjust.reason}
                error={adjustErrors.reason}
                onChange={(e) => {
                  setAdjust({ ...adjust, reason: e.target.value });
                }}
              />
              <div>
                <Button type="submit" disabled={saving}>
                  Simpan penyesuaian
                </Button>
              </div>
            </form>
          </div>
        ) : null}
      </main>
    </AppLayout>
  );
}
