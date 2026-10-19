import { describe, expect, it } from 'vitest';

import { TABS, visibleTabs } from './tabs';

describe('KLU-02 tab pemeriksaan menurut profil layanan', () => {
  it('klinik gigi melihat semua tab termasuk Odontogram', () => {
    expect(visibleTabs(true)).toEqual(TABS);
    expect(visibleTabs(true).map((tab) => tab.key)).toContain('odontogram');
  });

  it('klinik umum tidak melihat tab Odontogram dan urutan tab lain tetap', () => {
    expect(visibleTabs(false).map((tab) => tab.key)).toEqual([
      'anamnesis',
      'soap',
      'diagnosis',
      'procedures',
      'prescriptions',
      'files',
      'addenda',
    ]);
  });
});
