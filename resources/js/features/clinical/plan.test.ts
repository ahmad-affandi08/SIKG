import { describe, expect, it } from 'vitest';

import {
  nextStatus,
  planFormSchema,
  planItemFormSchema,
  progressText,
} from '@/features/clinical/plan';
import type { PlanItem } from '@/features/clinical/plan';
import { suggestionText } from '@/features/clinical/components/EncounterPanels';

const item = (status: PlanItem['status']): PlanItem => ({
  id: status,
  sequence: 1,
  code: 'X',
  name: 'X',
  tooth_fdi: null,
  quantity: 1,
  unit_price_amount: 0,
  estimate_amount: 0,
  status,
  status_label: status,
  notes: null,
  encounter_id: null,
  completed_at: null,
});

describe('RM-06 rencana perawatan', () => {
  it('judul rencana wajib', () => {
    expect(planFormSchema.safeParse({ title: ' ', notes: '' }).success).toBe(false);
    expect(planFormSchema.safeParse({ title: 'Perawatan gigi belakang', notes: '' }).success).toBe(
      true,
    );
  });

  it('langkah: tindakan wajib, gigi dua angka, jumlah 1 sampai 99', () => {
    const ok = { procedure_id: 'A', tooth_fdi: '46', quantity: '2', notes: '' };
    expect(planItemFormSchema.safeParse(ok).success).toBe(true);
    expect(planItemFormSchema.safeParse({ ...ok, procedure_id: '' }).success).toBe(false);
    expect(planItemFormSchema.safeParse({ ...ok, tooth_fdi: '4' }).success).toBe(false);
    expect(planItemFormSchema.safeParse({ ...ok, tooth_fdi: '' }).success).toBe(true);
    expect(planItemFormSchema.safeParse({ ...ok, quantity: '0' }).success).toBe(false);
    expect(planItemFormSchema.safeParse({ ...ok, quantity: '100' }).success).toBe(false);
  });

  it('kemajuan dan status berikutnya', () => {
    expect(progressText([])).toBe('Belum ada langkah');
    expect(progressText([item('done'), item('planned'), item('in_progress')])).toBe(
      '1 dari 3 langkah selesai',
    );
    expect(nextStatus('planned')).toBe('in_progress');
    expect(nextStatus('in_progress')).toBe('done');
    expect(nextStatus('done')).toBeNull();
  });
});

describe('ODO-05 usulan pembaruan odontogram', () => {
  it('kalimat usulan menyebut gigi, sasaran, kondisi, dan tindakan', () => {
    const base = {
      entry_id: 'E',
      procedure_name: 'Tambal',
      tooth_fdi: 46,
      condition_code: 'TAM',
      condition_name: 'Tambalan',
    };

    expect(suggestionText({ ...base, surfaces: ['O', 'M'] })).toBe(
      'Tandai gigi 46 (permukaan OM) sebagai Tambalan setelah Tambal?',
    );
    expect(suggestionText({ ...base, surfaces: ['W'] })).toBe(
      'Tandai gigi 46 (seluruh gigi) sebagai Tambalan setelah Tambal?',
    );
  });
});
