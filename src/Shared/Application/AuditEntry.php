<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * Satu baris jejak audit (M10): siapa, kapan, aksi apa, sebelum dan sesudah. Tidak boleh memuat PHI mentah
 * di luar yang memang diaudit; kolom terenkripsi dicatat dalam bentuk terenkripsi atau ditandai berubah saja.
 */
final readonly class AuditEntry
{
    /**
     * @param  non-empty-string  $action  contoh `patient.updated`
     * @param  non-empty-string  $subjectType  contoh `patient`
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function __construct(
        public ?string $actorId,
        public string $action,
        public string $subjectType,
        public string $subjectId,
        public ?array $before = null,
        public ?array $after = null,
    ) {}
}
