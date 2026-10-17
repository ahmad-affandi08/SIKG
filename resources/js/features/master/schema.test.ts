import { describe, expect, it } from 'vitest';

import { buildMasterSchema, emptyValues } from './schema';
import type { MasterFieldDef } from './schema';

const fields: MasterFieldDef[] = [
  {
    key: 'code',
    label: 'Kode',
    type: 'text',
    required: true,
    options: [],
    max: 5,
    help: null,
  },
  {
    key: 'note',
    label: 'Catatan',
    type: 'text',
    required: false,
    options: [],
    max: null,
    help: null,
  },
  {
    key: 'price',
    label: 'Tarif',
    type: 'money',
    required: true,
    options: [],
    max: null,
    help: null,
  },
  {
    key: 'scope',
    label: 'Cakupan',
    type: 'select',
    required: true,
    options: [{ value: 'tooth', label: 'Gigi' }],
    max: null,
    help: null,
  },
];

describe('MST skema master data', () => {
  const schema = buildMasterSchema(fields);

  it('menerima isian yang benar', () => {
    expect(
      schema.safeParse({
        code: 'A1',
        note: '',
        price: '150000',
        scope: 'tooth',
      }).success,
    ).toBe(true);
  });

  it('menolak kolom wajib kosong', () => {
    const result = schema.safeParse({
      code: ' ',
      note: '',
      price: '',
      scope: '',
    });
    expect(result.success).toBe(false);
    if (!result.success) {
      const keys = result.error.issues.map((issue) => String(issue.path[0]));
      expect(keys).toEqual(expect.arrayContaining(['code', 'price', 'scope']));
    }
  });

  it('menolak uang desimal atau negatif', () => {
    expect(
      schema.safeParse({
        code: 'A1',
        note: '',
        price: '10.5',
        scope: 'tooth',
      }).success,
    ).toBe(false);
    expect(
      schema.safeParse({
        code: 'A1',
        note: '',
        price: '-5',
        scope: 'tooth',
      }).success,
    ).toBe(false);
  });

  it('menolak teks melebihi panjang maksimal', () => {
    expect(
      schema.safeParse({
        code: 'ABCDEF',
        note: '',
        price: '1',
        scope: 'tooth',
      }).success,
    ).toBe(false);
  });

  it('nilai awal kosong untuk tiap kolom', () => {
    expect(emptyValues(fields)).toEqual({
      code: '',
      note: '',
      price: '',
      scope: '',
    });
  });
});
