import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { loginSchema } from '@/features/identityAccess/schemas';
import type { LoginValues } from '@/features/identityAccess/schemas';

interface LoginFormProps {
  /** Galat dari server (422/429), mis. kata sandi salah atau terlalu banyak percobaan. */
  serverErrors?: Partial<Record<keyof LoginValues, string>>;
  processing?: boolean;
  onSubmit: (values: LoginValues) => void;
}

export function LoginForm({ serverErrors = {}, processing = false, onSubmit }: LoginFormProps) {
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [clientErrors, setClientErrors] = useState<Partial<Record<keyof LoginValues, string>>>({});

  function handleSubmit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = loginSchema.safeParse({ identifier, password });
    if (!result.success) {
      const next: Partial<Record<keyof LoginValues, string>> = {};
      for (const issue of result.error.issues) {
        const field = issue.path[0];
        if ((field === 'identifier' || field === 'password') && next[field] === undefined) {
          next[field] = issue.message;
        }
      }
      setClientErrors(next);
      return;
    }
    setClientErrors({});
    onSubmit(result.data);
  }

  const errors = { ...serverErrors, ...clientErrors };

  return (
    <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
      <TextField
        label="Email atau nama pengguna"
        name="identifier"
        autoComplete="username"
        value={identifier}
        onChange={(event) => {
          setIdentifier(event.target.value);
        }}
        error={errors.identifier}
      />
      <TextField
        label="Kata sandi"
        name="password"
        type="password"
        autoComplete="current-password"
        value={password}
        onChange={(event) => {
          setPassword(event.target.value);
        }}
        error={errors.password}
      />
      <Button type="submit" loading={processing}>
        Masuk
      </Button>
    </form>
  );
}
