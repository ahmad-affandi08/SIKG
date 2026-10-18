<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Master\Import;

use Illuminate\Support\Facades\Validator;
use Src\Shared\Application\Master\Import\MasterRowValidator;
use Src\Shared\Application\Master\ImportableMaster;

/**
 * Memvalidasi satu baris dengan `rules()` entitas (sama dengan formulir), pesan berbahasa Indonesia, nama kolom memakai label.
 */
final class LaravelMasterRowValidator implements MasterRowValidator
{
    private const MESSAGES = [
        'required' => ':attribute wajib diisi.',
        'string' => ':attribute harus berupa teks.',
        'integer' => ':attribute harus berupa bilangan bulat tanpa pecahan.',
        'min.numeric' => ':attribute minimal :min.',
        'max.numeric' => ':attribute maksimal :max.',
        'max.string' => ':attribute maksimal :max karakter.',
        'min.string' => ':attribute minimal :min karakter.',
        'regex' => ':attribute formatnya tidak sesuai.',
        'unique' => ':attribute sudah dipakai.',
        'in' => ':attribute tidak valid.',
        'enum' => ':attribute tidak valid.',
        'exists' => ':attribute tidak ditemukan atau tidak aktif.',
        'date_format' => ':attribute harus berformat TTTT-BB-HH atau HH/BB/TTTT.',
        'after_or_equal' => ':attribute tidak boleh sebelum tanggal awal.',
        'boolean' => ':attribute harus berupa ya atau tidak.',
        'size' => ':attribute panjangnya tidak sesuai.',
    ];

    public function validate(ImportableMaster $entity, array $values): array
    {
        $labels = [];
        $options = [];
        foreach ($entity->fields() as $field) {
            $labels[$field->key] = (string) preg_replace('/\s*\(Rp\)$/', '', $field->label);
            if ($field->type === 'select') {
                $options[$field->key] = implode(', ', array_column($field->options, 'label'));
            }
        }

        // Aturan enum Laravel memakai terjemahan bawaan (bahasa Inggris) dan mengabaikan pesan kustom: pilihan diperiksa di sini.
        $invalidChoice = [];
        foreach ($entity->fields() as $field) {
            $value = $values[$field->key] ?? null;
            if ($field->type === 'select' && $value !== null && ! in_array($value, array_column($field->options, 'value'), true)) {
                $invalidChoice[$field->key] = "{$labels[$field->key]} tidak valid. Pilihan: {$options[$field->key]}.";
            }
        }

        $validator = Validator::make($values, $entity->rules(null), self::MESSAGES, $labels);
        $errors = array_values($invalidChoice);
        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $attribute => $messages) {
                if (isset($invalidChoice[$attribute])) {
                    continue;
                }
                foreach ($messages as $message) {
                    $errors[] = $message;
                }
            }
        }
        if ($errors !== []) {
            return ['data' => [], 'errors' => array_values(array_unique($errors))];
        }

        $data = [];
        foreach (array_keys($entity->rules(null)) as $key) {
            $data[$key] = $values[$key] ?? null;
        }

        return ['data' => $data, 'errors' => []];
    }
}
