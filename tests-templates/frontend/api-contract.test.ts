// TEMPLAT: salin ke resources/js/features/patient/api-contract.test.ts
// Contoh respons dibuat dari Laravel (tests/Feature menyimpan contoh JSON ke tests/Contracts/*.json)
// agar skema Zod dan respons server tidak menyimpang.
import { describe, expect, it } from 'vitest';
import searchExample from '../../../../tests/Contracts/patients.search.json';
import { patientSearchResponseSchema } from './schemas';

describe('PAS-02 kontrak GET /internal-api/patients/search', () => {
  it('respons Laravel cocok dengan skema Zod', () => {
    const r = patientSearchResponseSchema.safeParse(searchExample);
    expect(r.success).toBe(true);
  });

  it('bentuk {data, meta}', () => {
    expect(searchExample).toHaveProperty('data');
    expect(searchExample).toHaveProperty('meta');
  });
});
