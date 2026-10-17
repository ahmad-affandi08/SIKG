import { z } from 'zod';

export interface MasterFieldDef {
  key: string;
  label: string;
  type: 'text' | 'textarea' | 'number' | 'money' | 'select';
  required: boolean;
  options: { value: string; label: string }[];
  max: number | null;
  help: string | null;
}

/**
 * Skema klien dari definisi kolom milik server (umpan balik cepat; server memvalidasi ulang dan berwenang).
 * Nilai formulir berupa string; uang dan angka harus bilangan bulat tidak negatif (M4).
 */
export function buildMasterSchema(fields: readonly MasterFieldDef[]) {
  const shape: Record<string, z.ZodType<string>> = {};
  for (const field of fields) {
    shape[field.key] = fieldSchema(field);
  }
  return z.object(shape);
}

function fieldSchema(field: MasterFieldDef): z.ZodType<string> {
  const required = field.required ? `${field.label} wajib diisi.` : null;

  if (field.type === 'money' || field.type === 'number') {
    const unit = field.type === 'money' ? 'rupiah bulat' : 'bilangan bulat';
    return z.string().refine(
      (value) => {
        const text = value.trim();
        if (text === '') {
          return !field.required;
        }
        return /^\d{1,12}$/.test(text);
      },
      { message: `${field.label} harus berupa ${unit} tidak negatif.` },
    );
  }

  if (field.type === 'select') {
    return z.string().refine((value) => !field.required || value !== '', {
      message: `${field.label} wajib dipilih.`,
    });
  }

  return z
    .string()
    .trim()
    .refine((value) => !field.required || value !== '', {
      message: required ?? '',
    })
    .refine((value) => field.max === null || value.length <= field.max, {
      message: `${field.label} maksimal ${String(field.max)} karakter.`,
    });
}

export function emptyValues(fields: readonly MasterFieldDef[]): Record<string, string> {
  return Object.fromEntries(fields.map((field) => [field.key, '']));
}
