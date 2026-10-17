import { usePage } from '@inertiajs/react';

/**
 * Hanya kenyamanan UI (menyembunyikan tombol atau menu). Server tetap memeriksa izin pada setiap aksi (M9).
 */
export function can(permissions: readonly string[], permission: string): boolean {
  return permissions.includes(permission);
}

export function useCan(): (permission: string) => boolean {
  const { auth } = usePage().props;
  return (permission) => can(auth.permissions, permission);
}
