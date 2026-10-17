import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { cancelSchema } from '@/features/scheduling/schemas';
import { formatDate } from '@/lib/format';

interface Props {
  item: {
    id: string;
    date: string;
    start: string;
    end: string;
    patient_name: string | null;
    medical_record_no: string | null;
    doctor_name: string | null;
  };
}

export default function Cancel({ item }: Props) {
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | undefined>(undefined);
  const [saving, setSaving] = useState(false);
  const back = `/schedule?date=${item.date}`;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = cancelSchema.safeParse({ reason });
    if (!result.success) {
      setError(result.error.issues[0]?.message ?? 'Alasan wajib diisi.');
      return;
    }
    setError(undefined);
    setSaving(true);
    router.post(
      `/appointments/${item.id}/cancel`,
      { reason },
      {
        onError: (serverErrors) => {
          setError(serverErrors.reason ?? serverErrors.domain);
        },
        onFinish: () => {
          setSaving(false);
        },
      },
    );
  }

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-2xl flex-col gap-8 p-6 lg:px-12 lg:py-10">
        <PageHead title="Batalkan janji" />
        <PageHeader
          title="Batalkan janji"
          back={{ href: back, label: 'jadwal' }}
          description={`${item.patient_name ?? '-'} (${item.medical_record_no ?? '-'}), ${formatDate(item.date)} pukul ${item.start}-${item.end}, ${item.doctor_name ?? '-'}.`}
        />
        <form
          onSubmit={submit}
          noValidate
          aria-label="Pembatalan janji"
          className="flex flex-col gap-5"
        >
          <p className="text-sm">Slot janji dilepas dan alasan pembatalan tersimpan di riwayat.</p>
          <TextField
            field="reason"
            label="Alasan pembatalan"
            value={reason}
            onChange={(event) => {
              setReason(event.target.value);
            }}
            error={error}
          />
          <div className="flex flex-wrap gap-3">
            <Button type="submit" variant="danger" loading={saving}>
              Ya, batalkan janji
            </Button>
            <Link href={back} className="btn btn-secondary">
              Kembali
            </Link>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
