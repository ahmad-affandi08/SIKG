<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Clinic\Contracts\BranchDirectory;
use Src\IdentityAccess\Domain\Role;

final class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin dan Policy diperiksa di route dan controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $routeUser = $this->route('user');
        $ignoreId = is_string($routeUser) ? $routeUser : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'username' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]{3,50}$/', Rule::unique('users', 'username')->ignore($ignoreId)],
            'role' => ['required', Rule::enum(Role::class)],
            'branch_id' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null && $value !== '' && ! app(BranchDirectory::class)->exists((string) $value)) {
                    $fail('Cabang tidak ditemukan.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai pengguna lain.',
            'username.required' => 'Nama pengguna wajib diisi.',
            'username.regex' => 'Nama pengguna 3-50 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.',
            'username.unique' => 'Nama pengguna sudah dipakai.',
            'role.required' => 'Peran wajib dipilih.',
            'role.enum' => 'Peran tidak dikenal.',
        ];
    }
}
