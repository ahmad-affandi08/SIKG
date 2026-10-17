import { EncounterFormShell } from '@/features/clinical/components/EncounterFormShell';
import type { EncounterRef } from '@/features/clinical/components/EncounterFormShell';
import { PrescriptionForm } from '@/features/pharmacy/components/PrescriptionForm';

interface Props {
  encounter: EncounterRef;
  can: { overrideStock: boolean };
}

export default function PrescriptionFormPage({ encounter, can }: Props) {
  return (
    <EncounterFormShell
      title="Resep baru"
      encounter={encounter}
      tab="prescriptions"
      maxWidth="max-w-3xl"
    >
      <PrescriptionForm encounterId={encounter.id} canOverrideStock={can.overrideStock} />
    </EncounterFormShell>
  );
}
