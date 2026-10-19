import { router } from '@inertiajs/react';
import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import {
  diffFromDefaults,
  isDirty,
  togglePermission,
} from '@/features/identityAccess/rolePermissions';
import type { RoleInfo } from '@/features/identityAccess/rolePermissions';

interface ModuleGroup {
  name: string;
  permissions: { value: string; label: string }[];
}

interface Props {
  roles: RoleInfo[];
  modules: ModuleGroup[];
}

/** AUTH-07: Pemilik dan Admin menyesuaikan izin tiap peran; semua perubahan tercatat di audit log. */
export default function Index({ roles, modules }: Props) {
  const [activeValue, setActiveValue] = useState(roles[0]?.value ?? '');
  const [drafts, setDrafts] = useState<Record<string, string[]>>({});
  const [saving, setSaving] = useState(false);

  const active = roles.find((role) => role.value === activeValue);
  if (!active) {
    return null;
  }
  const selected = drafts[active.value] ?? active.permissions;
  const dirty = isDirty(selected, active.permissions);
  const diff = diffFromDefaults(selected, active.defaults);
  const labelOf = (name: string) =>
    modules.flatMap((m) => m.permissions).find((p) => p.value === name)?.label ?? name;

  function change(name: string, checked: boolean) {
    if (!active) {
      return;
    }
    setDrafts((prev) => ({
      ...prev,
      [active.value]: togglePermission(selected, name, checked, active.locked),
    }));
  }

  function clearDraft() {
    setDrafts((prev) =>
      Object.fromEntries(Object.entries(prev).filter(([key]) => key !== activeValue)),
    );
  }

  function save() {
    setSaving(true);
    router.put(
      `/settings/roles/${active?.value ?? ''}`,
      { permissions: selected },
      {
        preserveScroll: true,
        onSuccess: clearDraft,
        onFinish: () => {
          setSaving(false);
        },
      },
    );
  }

  function reset() {
    router.post(
      `/settings/roles/${active?.value ?? ''}/reset`,
      {},
      { preserveScroll: true, onSuccess: clearDraft },
    );
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Peran dan izin" />
        <h1>Peran dan izin</h1>
        <p className="text-muted text-sm">
          Atur apa yang boleh dilakukan tiap peran. Perubahan berlaku pada permintaan berikutnya
          setiap pengguna peran itu dan tercatat di audit log.
        </p>

        <div className="grid gap-3 lg:grid-cols-[16rem_minmax(0,1fr)]">
          <nav aria-label="Daftar peran">
            <ul className="border-line-soft flex flex-col overflow-hidden rounded-xl border">
              {roles.map((role) => (
                <li key={role.value} className="border-line-soft border-b last:border-b-0">
                  <button
                    type="button"
                    aria-current={role.value === activeValue ? 'true' : undefined}
                    onClick={() => {
                      setActiveValue(role.value);
                    }}
                    className={`flex w-full items-center justify-between gap-2 px-2 py-1.5 text-left text-sm ${
                      role.value === activeValue ? 'bg-brand-700 text-white' : 'hover:bg-chrome'
                    }`}
                  >
                    <span className="font-medium">{role.label}</span>
                    <span className="text-xs">
                      {drafts[role.value] && isDirty(drafts[role.value] ?? [], role.permissions)
                        ? 'belum disimpan'
                        : `${String(role.users)} pengguna`}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          </nav>

          <section aria-labelledby="izin-peran" className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-3">
              <h2 id="izin-peran">{active.label}</h2>
              <Badge tone={active.customized ? 'warning' : 'neutral'}>
                {active.customized ? 'Disesuaikan' : 'Sesuai bawaan'}
              </Badge>
            </div>

            {!active.editable ? (
              <Alert tone="warning">Hanya Pemilik yang dapat mengubah izin peran Pemilik.</Alert>
            ) : null}
            {active.locked.length > 0 ? (
              <p className="text-muted text-sm">
                Izin bertanda 🔒 tidak dapat dicabut dari peran ini agar klinik tidak terkunci dari
                pengelolaan pengguna dan peran.
              </p>
            ) : null}

            <fieldset disabled={!active.editable} className="flex flex-col gap-4">
              {modules.map((module) => (
                <div key={module.name} className="flex flex-col gap-1">
                  <h3 className="text-sm font-bold">{module.name}</h3>
                  <ul className="grid gap-x-4 gap-y-0.5 sm:grid-cols-2">
                    {module.permissions.map((permission) => {
                      const locked = active.locked.includes(permission.value);
                      const isDefault = active.defaults.includes(permission.value);
                      const checked = selected.includes(permission.value);
                      return (
                        <li key={permission.value}>
                          <label className="flex min-h-7 items-start gap-2 text-sm">
                            <input
                              type="checkbox"
                              className="mt-0.5"
                              checked={checked}
                              disabled={locked}
                              onChange={(event) => {
                                change(permission.value, event.target.checked);
                              }}
                            />
                            <span>
                              {permission.label}
                              {locked ? <span aria-label="terkunci"> 🔒</span> : null}
                              {checked && !isDefault ? (
                                <span className="text-warning-700 text-xs"> (ditambah)</span>
                              ) : null}
                              {!checked && isDefault ? (
                                <span className="text-xs text-red-700"> (dicabut)</span>
                              ) : null}
                            </span>
                          </label>
                        </li>
                      );
                    })}
                  </ul>
                </div>
              ))}
            </fieldset>

            {diff.added.length + diff.removed.length > 0 ? (
              <p className="text-muted text-sm">
                Berbeda dari bawaan:{' '}
                {[
                  ...diff.added.map((n) => `+ ${labelOf(n)}`),
                  ...diff.removed.map((n) => `− ${labelOf(n)}`),
                ].join('; ')}
              </p>
            ) : null}

            {active.editable ? (
              <div className="flex flex-wrap items-center gap-3">
                <Button onClick={save} loading={saving} disabled={!dirty}>
                  Simpan perubahan
                </Button>
                {dirty ? (
                  <Button variant="secondary" onClick={clearDraft}>
                    Batalkan
                  </Button>
                ) : null}
                {active.customized || diff.added.length + diff.removed.length > 0 ? (
                  <ConfirmAction
                    label="Kembalikan ke bawaan"
                    message={`Izin peran ${active.label} dikembalikan ke matriks bawaan dan penyesuaian hilang.`}
                    confirmLabel="Ya, kembalikan"
                    onConfirm={reset}
                  />
                ) : null}
              </div>
            ) : null}
          </section>
        </div>
      </main>
    </AppLayout>
  );
}
