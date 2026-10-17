<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * Satu butir pemeriksaan kesiapan lingkungan. Detail tidak pernah memuat nilai rahasia (kunci, kata sandi, token).
 */
final readonly class PreflightCheck
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    /**
     * @param  non-empty-string  $key
     * @param  self::PASS|self::WARN|self::FAIL  $status
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $status,
        public string $detail,
    ) {}
}
