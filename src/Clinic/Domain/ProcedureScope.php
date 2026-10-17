<?php

declare(strict_types=1);

namespace Src\Clinic\Domain;

/**
 * Cakupan tindakan (MST-04): menentukan apakah tindakan dicatat per gigi, per permukaan, per rahang, atau per kunjungan.
 */
enum ProcedureScope: string
{
    case Tooth = 'tooth';
    case Surface = 'surface';
    case Jaw = 'jaw';
    case Visit = 'visit';

    public function label(): string
    {
        return match ($this) {
            self::Tooth => 'Per gigi',
            self::Surface => 'Per permukaan gigi',
            self::Jaw => 'Per rahang',
            self::Visit => 'Per kunjungan',
        };
    }
}
