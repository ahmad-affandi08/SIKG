<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TwoFactorLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:32']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.required' => 'Kode verifikasi wajib diisi.'];
    }
}
