import { describe, expect, it } from 'vitest';

import { odontogramSchema } from '@/features/clinical/odontogram/api';

const whole = {
  condition_code: 'HLG',
  set_at: '2025-06-16T07:00:00+00:00',
  encounter_id: 'E1',
  in_this_encounter: true,
};

describe('ODO-01 skema odontogram', () => {
  it('menerima gigi dengan kondisi seluruh gigi dan peta permukaan kosong berupa objek', () => {
    const result = odontogramSchema.safeParse({
      teeth: { '16': { whole, surfaces: {} } },
      conditions: [],
    });
    expect(result.success).toBe(true);
  });

  it('menerima peta permukaan kosong yang terkirim sebagai array kosong', () => {
    const result = odontogramSchema.safeParse({
      teeth: { '16': { whole, surfaces: [] } },
      conditions: [],
    });
    expect(result.success).toBe(true);
    expect(result.data?.teeth['16']?.surfaces).toEqual({});
  });
});
