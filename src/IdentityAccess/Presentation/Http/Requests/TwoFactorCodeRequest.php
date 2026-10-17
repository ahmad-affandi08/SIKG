<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TwoFactorCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate account.two-factor diperiksa di route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['password.required' => 'Kata sandi wajib diisi.', 'code.required' => 'Kode verifikasi wajib diisi.'];
    }
}
