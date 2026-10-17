<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * Daftar isian wajib yang diturunkan dari aturan validasi server, supaya tanda wajib di formulir tidak pernah
 * menyimpang dari yang benar-benar ditolak server. Hanya aturan `required` polos yang dihitung; aturan bersyarat
 * (`required_if`, `required_without`, dan sejenisnya) tidak ditandai karena kewajibannya bergantung pada isian lain.
 * Kunci bertingkat dipertahankan apa adanya, mis. `items.*.dose`.
 */
final class RequiredFields
{
    /**
     * @param  array<string, mixed>  $rules
     * @return list<string>
     */
    public static function from(array $rules): array
    {
        $required = [];
        foreach ($rules as $field => $rule) {
            $parts = is_string($rule) ? explode('|', $rule) : (is_array($rule) ? $rule : [$rule]);
            if (in_array('required', $parts, true)) {
                $required[] = (string) $field;
            }
        }

        return $required;
    }
}
