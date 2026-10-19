import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { EmptyState } from '@/components/ui/EmptyState';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import type { ProcedureOption } from '@/features/clinical/api';
import { ProcedurePicker } from '@/features/clinical/components/ProcedurePicker';
import { materialSchema } from '@/features/pharmacy/purchasing';

interface Material {
  id: string;
  procedure_label: string;
  medicine_name: string;
  unit: string;
  quantity: number;
}

interface Props {
  materials: Material[];
  medicines: SelectOption[];
}

/** P2-05: bahan habis pakai standar per tindakan; stok berkurang otomatis saat tindakan dicatat. */
export default function ProcedureMaterials({ materials, medicines }: Props) {
  const form = useForm({ procedure_id: '', medicine_id: '', quantity: '1' });
  const [procedure, setProcedure] = useState<ProcedureOption | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const error = (key: 'procedure_id' | 'medicine_id' | 'quantity') =>
    errors[key] ?? form.errors[key];

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = materialSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post('/settings/procedure-materials', {
      preserveScroll: true,
      onSuccess: () => {
        form.reset('medicine_id');
      },
    });
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-4 p-3">
        <PageHead title="Bahan per tindakan" />
        <PageHeader
          title="Bahan habis pakai per tindakan"
          description="Setiap tindakan yang dicatat mengurangi stok bahan di bawah ini; dibatalkan, stok kembali."
        />
        <form
          onSubmit={submit}
          noValidate
          aria-label="Tambah bahan tindakan"
          className="form-grid max-w-xl"
        >
          <ProcedurePicker
            field="procedure_id"
            selected={procedure}
            onSelect={(option) => {
              setProcedure(option);
              form.setData('procedure_id', option?.id ?? '');
            }}
            error={error('procedure_id')}
          />
          <Select
            label="Obat atau bahan"
            field="medicine_id"
            placeholder="Pilih bahan"
            options={medicines}
            value={form.data.medicine_id}
            onChange={(event) => {
              form.setData('medicine_id', event.target.value);
            }}
            error={error('medicine_id')}
          />
          <TextField
            label="Jumlah per satuan tindakan"
            field="quantity"
            inputMode="numeric"
            value={form.data.quantity}
            onChange={(event) => {
              form.setData('quantity', event.target.value);
            }}
            error={error('quantity')}
          />
          <div>
            <Button type="submit" loading={form.processing}>
              Simpan bahan
            </Button>
          </div>
        </form>

        {materials.length === 0 ? (
          <EmptyState kind="pill" size="compact" title="Belum ada bahan yang diatur" />
        ) : (
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Bahan per tindakan</caption>
            <thead>
              <tr>
                <th scope="col">Tindakan</th>
                <th scope="col">Bahan</th>
                <th scope="col" className="text-right">
                  Jumlah
                </th>
                <th scope="col">
                  <span className="sr-only">Aksi</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {materials.map((material) => (
                <tr key={material.id}>
                  <td>{material.procedure_label}</td>
                  <td>{material.medicine_name}</td>
                  <td className="text-right">
                    {material.quantity} {material.unit}
                  </td>
                  <td className="text-right">
                    <ConfirmAction
                      label="Lepas"
                      message={`Bahan ${material.medicine_name} tidak lagi dikurangi untuk tindakan ini. Lanjutkan?`}
                      confirmLabel="Ya, lepas"
                      onConfirm={() => {
                        router.post(
                          `/settings/procedure-materials/${material.id}/remove`,
                          {},
                          { preserveScroll: true },
                        );
                      }}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </main>
    </AppLayout>
  );
}
