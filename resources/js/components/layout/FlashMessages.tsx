import { usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';

/** Pesan sekali tampil dari server (berhasil atau galat), dibagikan lewat props Inertia. */
export function FlashMessages() {
  const { flash } = usePage().props;

  if (!flash.success && !flash.error) {
    return null;
  }

  return (
    <div className="flex flex-col gap-1">
      {flash.success ? <Alert tone="success">{flash.success}</Alert> : null}
      {flash.error ? <Alert tone="error">{flash.error}</Alert> : null}
    </div>
  );
}
