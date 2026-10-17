<?php

declare(strict_types=1);

use Illuminate\Validation\Rule;
use Src\Shared\Application\RequiredFields;

it('UI-05 hanya aturan required polos yang dihitung wajib, dalam bentuk larik maupun string', function () {
    $rules = [
        'name' => ['required', 'string'],
        'sex' => ['required', Rule::in(['male', 'female'])],
        'nik' => ['nullable', 'string'],
        'phone' => 'required|string|max:30',
        'note' => 'nullable|string',
        'reason' => ['required_if:type,out', 'string'],
        'open' => ['required_with:operating_hours.*', 'date_format:H:i'],
        'consent' => ['sometimes', 'boolean'],
    ];

    expect(RequiredFields::from($rules))->toBe(['name', 'sex', 'phone']);
});

it('UI-05 kunci bertingkat dipertahankan apa adanya', function () {
    expect(RequiredFields::from([
        'items' => ['required', 'array'],
        'items.*.dose' => ['required', 'string'],
        'items.*.instructions' => ['nullable', 'string'],
    ]))->toBe(['items', 'items.*.dose']);
});

it('UI-05 tanpa aturan tidak ada isian wajib', function () {
    expect(RequiredFields::from([]))->toBe([]);
});
