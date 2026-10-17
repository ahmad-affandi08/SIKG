import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import type { ProcedureOption } from '@/features/clinical/api';
import { ProcedurePicker } from '@/features/clinical/components/ProcedurePicker';
import { planItemFormSchema } from '@/features/clinical/plan';
import { formatRupiah } from '@/lib/format';

interface Props {
  patient: { id: string; name: string; medical_record_no: string };
  plan: { id: string; title: string };
}

export default function ItemForm({ patient, plan }: Props) {
  const [procedure, setProcedure] = useState<ProcedureOption | null>(null);
  const [tooth, setTooth] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [notes, setNotes] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const back = `/patients/${patient.id}/treatment-plans`;
  const needsTooth = procedure?.scope === 'tooth' || procedure?.scope === 'surface';

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = { procedure_id: procedure?.id ?? '', tooth_fdi: tooth, quantity, notes };
    const result = planItemFormSchema.safeParse(values);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    if (needsTooth && tooth === '') {
      setErrors({ tooth_fdi: 'Tindakan ini dikerjakan per gigi; isi nomor gigi (FDI).' });
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      `/treatment-plans/${plan.id}/items`,
      {
        procedure_id: values.procedure_id,
        tooth_fdi: tooth === '' ? null : Number(tooth),
        quantity: Number(quantity),
        notes,
      },
      {
        onError: (serverErrors) => {
          setErrors(serverErrors);
        },
        onFinish: () => {
          setSaving(false);
        },
      },
    );
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Tambah langkah rencana" />
        <PageHeader
          title="Tambah langkah rencana"
          back={{ href: back, label: 'rencana perawatan' }}
          description={`${plan.title} | ${patient.name} (${patient.medical_record_no})`}
        />
        <form
          onSubmit={submit}
          noValidate
          aria-label="Tambah langkah rencana"
          className="form-grid"
        >
          <ProcedurePicker
            field="procedure_id"
            selected={procedure}
            onSelect={setProcedure}
            error={errors.procedure_id}
          />
          {needsTooth ? (
            <TextField
              field="tooth_fdi"
              label="Nomor gigi (FDI)"
              inputMode="numeric"
              value={tooth}
              error={errors.tooth_fdi}
              onChange={(event) => {
                setTooth(event.target.value);
              }}
            />
          ) : null}
          <TextField
            field="quantity"
            label="Jumlah"
            inputMode="numeric"
            value={quantity}
            error={errors.quantity}
            onChange={(event) => {
              setQuantity(event.target.value);
            }}
          />
          {procedure && /^\d{1,2}$/.test(quantity) ? (
            <p className="text-muted text-sm">
              Estimasi biaya {formatRupiah(procedure.price_amount * Number(quantity))} (tarif saat
              ini disalin ke langkah).
            </p>
          ) : null}
          <TextField
            field="notes"
            label="Catatan"
            value={notes}
            error={errors.notes}
            onChange={(event) => {
              setNotes(event.target.value);
            }}
          />
          {errors.domain ? (
            <p role="alert" className="text-danger-600 text-sm">
              {errors.domain}
            </p>
          ) : null}
          <div className="flex flex-wrap gap-3">
            <Button type="submit" loading={saving}>
              Simpan langkah
            </Button>
            <Link href={back} className="btn btn-secondary">
              Batal
            </Link>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
