import { usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';

/** Pesan sekali tampil dari server (berhasil atau galat), dibagikan lewat props Inertia. */
export function FlashMessages() {
  const { flash } = usePage().props;

  return (
    <div className="mx-auto flex max-w-5xl flex-col gap-2 px-6 pt-4">
      {flash.success ? <Alert tone="success">{flash.success}</Alert> : null}
      {flash.error ? <Alert tone="error">{flash.error}</Alert> : null}
    </div>
  );
}
