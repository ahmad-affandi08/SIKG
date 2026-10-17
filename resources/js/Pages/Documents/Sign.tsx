import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { SignaturePad } from '@/features/clinical/components/SignaturePad';
import { signSchema } from '@/features/clinical/signature';
import type { SignValues } from '@/features/clinical/signature';

type Block =
  | { kind: 'kv'; rows: [string, string][] }
  | { kind: 'list'; items: string[] }
  | { kind: 'text'; text: string };

interface Props {
  document: {
    id: string;
    patient_id: string;
    number: string;
    title: string;
    heading: string;
    blocks: Block[];
    doctor_name: string;
    patient_name: string;
    medical_record_no: string;
    signable: boolean;
    signed: boolean;
  };
  roles: SelectOption[];
}

/** P2-07: pasien atau wali menandatangani informed consent langsung di tablet; PDF tersimpan di rekam medis. */
export default function Sign({ document, roles }: Props) {
  const form = useForm<SignValues>({
    signer_name: document.patient_name,
    signer_role: 'patient',
    signature: '',
    witness_name: '',
    witness_signature: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const error = (key: keyof SignValues): string | undefined => errors[key] ?? form.errors[key];
  const back = `/patients/${document.patient_id}/documents`;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = signSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post(`/documents/${document.id}/sign`);
  }

  return (
    <AppLayout>
      <main className="flex max-w-3xl flex-col gap-3 p-3">
        <PageHead title={`Tanda tangan ${document.number}`} />
        <PageHeader
          title={document.heading}
          back={{ href: back, label: 'dokumen klinis' }}
          description={`${document.patient_name} (${document.medical_record_no}) - No. ${document.number}`}
        />

        <article className="pane flex flex-col gap-2 p-3 text-sm" aria-label="Isi dokumen">
          {document.blocks.map((block, index) => {
            if (block.kind === 'kv') {
              return (
                <dl key={index} className="grid grid-cols-[minmax(8rem,12rem)_1fr] gap-x-3 gap-y-1">
                  {block.rows.map(([label, value]) => (
                    <div key={label} className="contents">
                      <dt className="text-muted">{label}</dt>
                      <dd>{value}</dd>
                    </div>
                  ))}
                </dl>
              );
            }
            if (block.kind === 'list') {
              return (
                <ol key={index} className="ml-5 list-decimal">
                  {block.items.map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ol>
              );
            }
            return (
              <p key={index} className="whitespace-pre-line">
                {block.text}
              </p>
            );
          })}
          <p className="text-muted text-xs">Dokter yang menjelaskan: {document.doctor_name}</p>
        </article>

        {!document.signable ? (
          <Alert tone="warning">Hanya informed consent yang ditandatangani di layar.</Alert>
        ) : document.signed ? (
          <Alert tone="info">
            Dokumen ini sudah ditandatangani.{' '}
            <a
              href={`/documents/${document.id}/signed`}
              target="_blank"
              rel="noopener noreferrer"
              className="underline"
            >
              Buka PDF bertanda tangan
            </a>{' '}
            atau{' '}
            <Link href={back} className="underline">
              kembali ke daftar dokumen
            </Link>
            .
          </Alert>
        ) : (
          <form
            onSubmit={submit}
            noValidate
            aria-label="Tanda tangan digital"
            className="form-grid"
          >
            <p className="text-sm">
              Serahkan tablet kepada pasien atau wali. Dengan menandatangani, penanda tangan
              menyatakan telah membaca dan menyetujui isi dokumen di atas.
            </p>
            <Select
              label="Penanda tangan"
              options={roles}
              value={form.data.signer_role}
              onChange={(event) => {
                form.setData('signer_role', event.target.value as SignValues['signer_role']);
              }}
              error={error('signer_role')}
              required
            />
            <TextField
              label="Nama jelas penanda tangan"
              value={form.data.signer_name}
              onChange={(event) => {
                form.setData('signer_name', event.target.value);
              }}
              error={error('signer_name')}
              required
            />
            <SignaturePad
              label="Tanda tangan pasien atau wali"
              onChange={(value) => {
                form.setData('signature', value);
              }}
              error={error('signature')}
            />
            <details>
              <summary className="cursor-pointer text-sm font-semibold">
                Tambahkan saksi (opsional)
              </summary>
              <div className="mt-2 flex flex-col gap-2">
                <TextField
                  label="Nama saksi"
                  value={form.data.witness_name}
                  onChange={(event) => {
                    form.setData('witness_name', event.target.value);
                  }}
                  error={error('witness_name')}
                />
                <SignaturePad
                  label="Tanda tangan saksi"
                  onChange={(value) => {
                    form.setData('witness_signature', value);
                  }}
                  error={error('witness_signature')}
                />
              </div>
            </details>
            <div className="flex gap-3">
              <Button type="submit" loading={form.processing}>
                Simpan tanda tangan
              </Button>
            </div>
          </form>
        )}
      </main>
    </AppLayout>
  );
}
