import type { ReactNode } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';

export interface EncounterRef {
  id: string;
  patient_name: string | null;
  medical_record_no: string | null;
}

interface EncounterFormShellProps {
  title: string;
  encounter: EncounterRef;
  /** Tab kunjungan tujuan tautan kembali. */
  tab: string;
  children: ReactNode;
  maxWidth?: string;
}

/** Kerangka halaman formulir yang berasal dari satu kunjungan: kepala halaman, tautan kembali, dan isi. */
export function EncounterFormShell({
  title,
  encounter,
  tab,
  children,
  maxWidth = 'max-w-2xl',
}: EncounterFormShellProps) {
  return (
    <AppLayout>
      <main className={`flex ${maxWidth} flex-col gap-3 p-3`}>
        <PageHead title={title} />
        <PageHeader
          title={title}
          back={{ href: `/encounters/${encounter.id}?tab=${tab}`, label: 'pemeriksaan' }}
          description={`${encounter.patient_name ?? 'Pasien'} (${encounter.medical_record_no ?? '-'})`}
        />
        {children}
      </main>
    </AppLayout>
  );
}
