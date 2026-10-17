<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

/**
 * Jenis catatan klinis: format SOAP (RM-03) dan addendum (RM-07). Bagian objektif (O) dibagi ekstra oral dan intra oral.
 */
enum NoteType: string
{
    case Subjective = 'S';
    case Objective = 'O';
    case Assessment = 'A';
    case Plan = 'P';
    case Addendum = 'addendum';

    public function label(): string
    {
        return match ($this) {
            self::Subjective => 'Subjektif',
            self::Objective => 'Objektif',
            self::Assessment => 'Asesmen',
            self::Plan => 'Rencana',
            self::Addendum => 'Addendum',
        };
    }
}
