export interface RoleInfo {
  value: string;
  label: string;
  permissions: string[];
  defaults: string[];
  customized: boolean;
  users: number;
  editable: boolean;
  locked: string[];
}

/** Izin yang berbeda dari matriks bawaan: ditambah (tidak ada di bawaan) dan dicabut (ada di bawaan). */
export function diffFromDefaults(
  current: readonly string[],
  defaults: readonly string[],
): { added: string[]; removed: string[] } {
  return {
    added: current.filter((name) => !defaults.includes(name)).sort(),
    removed: defaults.filter((name) => !current.includes(name)).sort(),
  };
}

/** Pilihan baru setelah satu kotak dicentang atau dicabut; izin terkunci tidak pernah dicabut. */
export function togglePermission(
  selected: readonly string[],
  name: string,
  checked: boolean,
  locked: readonly string[],
): string[] {
  if (checked) {
    return selected.includes(name) ? [...selected] : [...selected, name];
  }
  if (locked.includes(name)) {
    return [...selected];
  }
  return selected.filter((item) => item !== name);
}

/** Apakah pilihan berbeda dari yang tersimpan (urutan tidak berpengaruh). */
export function isDirty(selected: readonly string[], saved: readonly string[]): boolean {
  if (selected.length !== saved.length) {
    return true;
  }
  return selected.some((name) => !saved.includes(name));
}
