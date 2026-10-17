import { z } from 'zod';

const TIME = /^([01]\d|2[0-3]):[0-5]\d$/;
const PERCENT = /^\d{1,3}(\.\d{1,2})?$/;
const RUPIAH = /^\d{1,10}$/;

export const FEE_MODES = [
  { value: 'percentage', label: 'Persentase dari tarif' },
  { value: 'fixed', label: 'Nominal tetap per tindakan' },
] as const;

function feeValueIssue(mode: string, value: string): string | null {
  const text = value.trim();
  if (mode === 'percentage') {
    if (!PERCENT.test(text) || Number(text) > 100) {
      return 'Persentase harus 0 sampai 100 (maksimal dua desimal).';
    }
    return null;
  }
  return RUPIAH.test(text) ? null : 'Nominal harus rupiah bulat tidak negatif.';
}

const feeModeSchema = z.enum(['percentage', 'fixed']);

const scheduleSlotSchema = z.object({
  weekday: z.number().int().min(1).max(7),
  start: z.string().regex(TIME, 'Jam mulai harus berformat JJ:MM.'),
  end: z.string().regex(TIME, 'Jam selesai harus berformat JJ:MM.'),
});

export const doctorFormSchema = z
  .object({
    name: z.string().trim().min(1, 'Nama dokter wajib diisi.').max(255, 'Terlalu panjang.'),
    sip_no: z.string().trim().max(100, 'Terlalu panjang.'),
    specialty: z.string().trim().max(100, 'Terlalu panjang.'),
    branch_id: z.string(),
    user_id: z.string(),
    fee_mode: feeModeSchema,
    fee_value: z.string(),
    fee_overrides: z.array(
      z.object({
        code: z.string().trim().min(1, 'Kode tindakan wajib diisi.').max(30, 'Terlalu panjang.'),
        mode: feeModeSchema,
        value: z.string(),
      }),
    ),
    fee_tiers: z.array(
      z.object({
        up_to: z.string(),
        rate: z.string(),
      }),
    ),
    fee_lab_share: z.string(),
    schedule: z.array(scheduleSlotSchema),
  })
  .superRefine((values, ctx) => {
    const base = feeValueIssue(values.fee_mode, values.fee_value);
    if (base) {
      ctx.addIssue({ code: 'custom', path: ['fee_value'], message: base });
    }

    const codes = new Set<string>();
    values.fee_overrides.forEach((row, index) => {
      const issue = feeValueIssue(row.mode, row.value);
      if (issue) {
        ctx.addIssue({ code: 'custom', path: ['fee_overrides', index, 'value'], message: issue });
      }
      const code = row.code.trim().toUpperCase();
      if (codes.has(code)) {
        ctx.addIssue({
          code: 'custom',
          path: ['fee_overrides', index, 'code'],
          message: 'Kode tindakan tidak boleh ganda.',
        });
      }
      codes.add(code);
    });

    let previous = 0;
    values.fee_tiers.forEach((tier, index) => {
      const last = index === values.fee_tiers.length - 1;
      const rate = tier.rate.trim();
      if (!PERCENT.test(rate) || Number(rate) > 100) {
        ctx.addIssue({
          code: 'custom',
          path: ['fee_tiers', index, 'rate'],
          message: 'Persentase harus 0 sampai 100 (maksimal dua desimal).',
        });
      }
      const upTo = tier.up_to.trim();
      if (last) {
        if (upTo !== '') {
          ctx.addIssue({
            code: 'custom',
            path: ['fee_tiers', index, 'up_to'],
            message: 'Tingkat terakhir tanpa batas; kosongkan isian ini.',
          });
        }
        return;
      }
      if (!/^\d{1,12}$/.test(upTo) || Number(upTo) < 1) {
        ctx.addIssue({
          code: 'custom',
          path: ['fee_tiers', index, 'up_to'],
          message: 'Batas berupa rupiah bulat lebih dari nol.',
        });
      } else if (Number(upTo) <= previous) {
        ctx.addIssue({
          code: 'custom',
          path: ['fee_tiers', index, 'up_to'],
          message: 'Batas harus lebih besar dari tingkat sebelumnya.',
        });
      } else {
        previous = Number(upTo);
      }
    });
    if (values.fee_tiers.length > 6) {
      ctx.addIssue({
        code: 'custom',
        path: ['fee_tiers'],
        message: 'Jasa bertingkat paling banyak 6 tingkat.',
      });
    }
    const share = values.fee_lab_share.trim();
    if (share !== '' && (!PERCENT.test(share) || Number(share) > 100)) {
      ctx.addIssue({
        code: 'custom',
        path: ['fee_lab_share'],
        message: 'Beban biaya lab harus 0 sampai 100 persen.',
      });
    }

    values.schedule.forEach((slot, index) => {
      if (slot.end <= slot.start) {
        ctx.addIssue({
          code: 'custom',
          path: ['schedule', index, 'end'],
          message: 'Jam selesai harus setelah jam mulai.',
        });
        return;
      }
      const clash = values.schedule.some(
        (other, otherIndex) =>
          otherIndex < index &&
          other.weekday === slot.weekday &&
          other.start < slot.end &&
          slot.start < other.end,
      );
      if (clash) {
        ctx.addIssue({
          code: 'custom',
          path: ['schedule', index, 'start'],
          message: 'Jam praktik pada hari yang sama tidak boleh tumpang tindih.',
        });
      }
    });
  });

export type DoctorFormValues = z.infer<typeof doctorFormSchema>;

/** Basis poin dari server (3250) menjadi teks persen untuk formulir ("32.5"), tanpa bilangan pecahan. */
export function basisPointsToPercent(basisPoints: number): string {
  const whole = Math.trunc(basisPoints / 100);
  const fraction = String(basisPoints % 100)
    .padStart(2, '0')
    .replace(/0+$/, '');
  return fraction === '' ? String(whole) : `${String(whole)}.${fraction}`;
}

export function feeValueToText(mode: string, value: number): string {
  return mode === 'percentage' ? basisPointsToPercent(value) : String(value);
}
