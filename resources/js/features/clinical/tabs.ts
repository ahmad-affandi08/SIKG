export const TABS = [
  { key: 'anamnesis', label: 'Anamnesis' },
  { key: 'soap', label: 'Catatan SOAP' },
  { key: 'odontogram', label: 'Odontogram' },
  { key: 'diagnosis', label: 'Diagnosa' },
  { key: 'procedures', label: 'Tindakan' },
  { key: 'prescriptions', label: 'Resep' },
  { key: 'files', label: 'Berkas' },
  { key: 'addenda', label: 'Addendum dan riwayat' },
] as const;

export type TabKey = (typeof TABS)[number]['key'];

/** KLU-02: klinik umum tidak memakai odontogram, sehingga tab-nya tidak ditampilkan. */
export function visibleTabs(dental: boolean): readonly (typeof TABS)[number][] {
  return dental ? TABS : TABS.filter((item) => item.key !== 'odontogram');
}
