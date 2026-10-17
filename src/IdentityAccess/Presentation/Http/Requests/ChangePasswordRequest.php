<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ChangePasswordRequest extends FormRequest
{
    public const MIN_LENGTH = 10;

    public function authorize(): bool
    {
        return true; // gate account.password diperiksa di route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(self::MIN_LENGTH)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
            'password.min' => 'Kata sandi baru minimal '.self::MIN_LENGTH.' karakter.',
            'password.letters' => 'Kata sandi baru harus memuat huruf.',
            'password.numbers' => 'Kata sandi baru harus memuat angka.',
        ];
    }
}
