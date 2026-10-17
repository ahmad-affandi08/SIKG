<?php

declare(strict_types=1);

namespace Src\Integration\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Integration\Domain\SatuSehatEnvironment;

final class SatuSehatSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin setting.manage dipasang di route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'environment' => ['required', Rule::enum(SatuSehatEnvironment::class)],
            'organization_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'location_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'location_name' => ['nullable', 'string', 'max:150'],
            'client_id' => ['nullable', 'string', 'max:128'],
            'client_secret' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'environment.required' => 'Lingkungan wajib dipilih.',
            'organization_id.regex' => 'ID organisasi hanya boleh huruf, angka, dan tanda hubung.',
            'location_id.regex' => 'ID lokasi hanya boleh huruf, angka, dan tanda hubung.',
        ];
    }
}
