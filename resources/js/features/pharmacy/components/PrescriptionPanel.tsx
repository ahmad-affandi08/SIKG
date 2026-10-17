import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { TextField } from '@/components/ui/TextField';
import { formatDateTime, formatRupiah } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

export interface PrescriptionItem {
  id: string;
  medicine_id: string | null;
  name: string;
  dose: string;
  frequency: string;
  duration: string;
  instructions: string | null;
  quantity: number;
  dispense_in_clinic: boolean;
  unit_price_amount: number;
}

export interface PrescriptionData {
  id: string;
  status: string;
  void_reason: string | null;
  issued_at: string;
  items: PrescriptionItem[];
}

interface Props {
  encounterId: string;
  prescriptions: PrescriptionData[];
  canCreate: boolean;
  canVoid: boolean;
  canPrint: boolean;
}

export function PrescriptionPanel({
  encounterId,
  prescriptions,
  canCreate,
  canVoid,
  canPrint,
}: Props) {
  const timezone = useClinicTimezone();
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [voidFor, setVoidFor] = useState<string | null>(null);
  const [voidReason, setVoidReason] = useState('');

  return (
    <div className="flex flex-col gap-6">
      {canCreate ? (
        <div>
          <Link
            href={`/encounters/${encounterId}/prescriptions/create`}
            className="btn btn-primary"
          >
            Resep baru
          </Link>
        </div>
      ) : null}
      {prescriptions.length === 0 ? <p className="text-muted text-sm">Belum ada resep.</p> : null}
      {prescriptions.map((prescription) => (
        <section key={prescription.id} aria-label="Resep" className="card flex flex-col gap-2 p-3">
          <div className="flex flex-wrap items-center gap-2">
            <span className="font-medium">{formatDateTime(prescription.issued_at, timezone)}</span>
            {prescription.status === 'void' ? (
              <Badge tone="danger">Dibatalkan</Badge>
            ) : (
              <Badge tone="success">Berlaku</Badge>
            )}
            {canPrint ? (
              <a
                href={`/prescriptions/${prescription.id}/print`}
                target="_blank"
                rel="noopener noreferrer"
                className="text-ink text-sm underline"
              >
                Cetak resep
              </a>
            ) : null}
          </div>
          {prescription.void_reason ? (
            <p className="text-muted text-sm">Alasan batal: {prescription.void_reason}</p>
          ) : null}
          <ul className="flex flex-col gap-1 text-sm">
            {prescription.items.map((item) => (
              <li key={item.id}>
                <span className="font-medium">{item.name}</span> x{item.quantity} - {item.dose},{' '}
                {item.frequency}, {item.duration}
                {item.instructions ? ` (${item.instructions})` : ''}
                {item.dispense_in_clinic
                  ? ` | dijual di klinik ${formatRupiah(item.unit_price_amount * item.quantity)}`
                  : ''}
              </li>
            ))}
          </ul>
          {canVoid && prescription.status === 'issued' ? (
            voidFor === prescription.id ? (
              <form
                className="flex flex-wrap items-end gap-2"
                aria-label="Batalkan resep"
                onSubmit={(event) => {
                  event.preventDefault();
                  if (voidReason.trim() === '') {
                    setErrors({ void: 'Alasan pembatalan resep wajib diisi.' });
                    return;
                  }
                  setErrors({});
                  router.post(
                    `/prescriptions/${prescription.id}/void`,
                    { reason: voidReason },
                    {
                      preserveScroll: true,
                      onSuccess: () => {
                        setVoidFor(null);
                        setVoidReason('');
                      },
                    },
                  );
                }}
              >
                <TextField
                  label="Alasan pembatalan"
                  required
                  value={voidReason}
                  onChange={(e) => {
                    setVoidReason(e.target.value);
                  }}
                  error={errors.void}
                />
                <Button type="submit" variant="danger">
                  Ya, batalkan resep
                </Button>
                <Button
                  variant="secondary"
                  onClick={() => {
                    setVoidFor(null);
                  }}
                >
                  Kembali
                </Button>
              </form>
            ) : (
              <div>
                <ConfirmAction
                  label="Batalkan resep"
                  message="Obat yang sudah dijual dikembalikan ke stok dan dilepas dari tagihan."
                  confirmLabel="Lanjut"
                  onConfirm={() => {
                    setVoidFor(prescription.id);
                  }}
                />
              </div>
            )
          ) : null}
        </section>
      ))}
    </div>
  );
}
