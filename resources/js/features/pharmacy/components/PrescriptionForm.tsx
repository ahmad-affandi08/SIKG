import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { MedicinePicker } from '@/features/pharmacy/components/MedicinePicker';
import { emptyRow, prescriptionRowSchema } from '@/features/pharmacy/schemas';
import type { PrescriptionRow } from '@/features/pharmacy/schemas';

interface Props {
  encounterId: string;
  canOverrideStock: boolean;
}

type Row = PrescriptionRow & { medicineName: string };

/** Formulir resep baru: satu atau lebih obat, peringatan alergi, dan override stok oleh yang berhak. */
export function PrescriptionForm({ encounterId, canOverrideStock }: Props) {
  const { flash } = usePage().props;
  const [rows, setRows] = useState<Row[]>([{ ...emptyRow(), medicineName: '' }]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [allergyReason, setAllergyReason] = useState('');
  const [overrideReason, setOverrideReason] = useState('');
  const [saving, setSaving] = useState(false);
  const allergyWarning = flash.error_code === 'prescription.allergy_warning' ? flash.error : null;
  const stockShort = flash.error_code === 'stock.insufficient';
  const back = `/encounters/${encounterId}?tab=prescriptions`;

  const patch = (index: number, change: Partial<Row>) => {
    setRows((current) => current.map((row, i) => (i === index ? { ...row, ...change } : row)));
  };

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const next: Record<string, string> = {};
    rows.forEach((row, index) => {
      const result = prescriptionRowSchema.safeParse(row);
      if (!result.success) {
        for (const issue of result.error.issues) {
          next[`${String(index)}.${String(issue.path[0])}`] ??= issue.message;
        }
      }
    });
    setErrors(next);
    if (Object.keys(next).length > 0) {
      return;
    }
    setSaving(true);
    router.post(
      `/encounters/${encounterId}/prescriptions`,
      {
        items: rows.map((row) => ({
          medicine_id: row.medicine_id || null,
          free_text_name: row.free_text_name || null,
          dose: row.dose,
          frequency: row.frequency,
          duration: row.duration,
          instructions: row.instructions || null,
          quantity: Number(row.quantity),
          dispense_in_clinic: row.dispense_in_clinic,
        })),
        allergy_acknowledgement: allergyReason || null,
        stock_override_reason: overrideReason || null,
      },
      {
        onFinish: () => {
          setSaving(false);
        },
      },
    );
  }

  return (
    <form onSubmit={submit} noValidate aria-label="Buat resep" className="flex flex-col gap-5">
      <h3 className="text-base font-semibold">Resep baru</h3>
      {rows.map((row, index) => (
        <fieldset key={index} className="card flex flex-col gap-3 p-3">
          <legend className="px-1 text-sm font-medium">Obat {index + 1}</legend>
          <MedicinePicker
            selected={row.medicine_id ? { id: row.medicine_id, name: row.medicineName } : null}
            onSelect={(medicine) => {
              patch(
                index,
                medicine
                  ? {
                      medicine_id: medicine.id,
                      medicineName: medicine.name,
                      free_text_name: '',
                    }
                  : { medicine_id: '', medicineName: '', dispense_in_clinic: false },
              );
            }}
            error={errors[`${String(index)}.medicine_id`]}
          />
          {row.medicine_id === '' ? (
            <TextField
              label="Atau tulis nama obat (di luar katalog)"
              value={row.free_text_name}
              onChange={(e) => {
                patch(index, { free_text_name: e.target.value });
              }}
            />
          ) : null}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <TextField
              label="Dosis"
              value={row.dose}
              onChange={(e) => {
                patch(index, { dose: e.target.value });
              }}
              error={errors[`${String(index)}.dose`]}
            />
            <TextField
              label="Frekuensi"
              value={row.frequency}
              onChange={(e) => {
                patch(index, { frequency: e.target.value });
              }}
              error={errors[`${String(index)}.frequency`]}
            />
            <TextField
              label="Durasi"
              value={row.duration}
              onChange={(e) => {
                patch(index, { duration: e.target.value });
              }}
              error={errors[`${String(index)}.duration`]}
            />
          </div>
          <TextField
            label="Aturan pakai"
            value={row.instructions}
            onChange={(e) => {
              patch(index, { instructions: e.target.value });
            }}
            error={errors[`${String(index)}.instructions`]}
          />
          <TextField
            label="Jumlah"
            inputMode="numeric"
            value={row.quantity}
            onChange={(e) => {
              patch(index, { quantity: e.target.value });
            }}
            error={errors[`${String(index)}.quantity`]}
          />
          <label className="min-h-touch flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={row.dispense_in_clinic}
              disabled={row.medicine_id === ''}
              onChange={(e) => {
                patch(index, { dispense_in_clinic: e.target.checked });
              }}
            />
            Jual di klinik (masuk tagihan dan mengurangi stok)
          </label>
          {errors[`${String(index)}.dispense_in_clinic`] ? (
            <p role="alert" className="text-danger-600 text-sm">
              {errors[`${String(index)}.dispense_in_clinic`]}
            </p>
          ) : null}
          {rows.length > 1 ? (
            <div>
              <Button
                variant="secondary"
                onClick={() => {
                  setRows((current) => current.filter((_, i) => i !== index));
                }}
              >
                {`Hapus obat ${String(index + 1)}`}
              </Button>
            </div>
          ) : null}
        </fieldset>
      ))}
      <div>
        <Button
          variant="secondary"
          onClick={() => {
            setRows((current) => [...current, { ...emptyRow(), medicineName: '' }]);
          }}
        >
          Tambah obat
        </Button>
      </div>

      {allergyWarning ? (
        <div className="flex flex-col gap-2">
          <Alert tone="warning">{allergyWarning}</Alert>
          <TextField
            label="Alasan tetap meresepkan (konfirmasi dokter)"
            value={allergyReason}
            onChange={(e) => {
              setAllergyReason(e.target.value);
            }}
          />
        </div>
      ) : null}
      {stockShort && canOverrideStock ? (
        <TextField
          label="Alasan override stok (Admin)"
          value={overrideReason}
          onChange={(e) => {
            setOverrideReason(e.target.value);
          }}
        />
      ) : null}

      <div className="flex flex-wrap gap-3">
        <Button type="submit" loading={saving}>
          Simpan resep
        </Button>
        <Link href={back} className="btn btn-secondary">
          Batal
        </Link>
      </div>
    </form>
  );
}
