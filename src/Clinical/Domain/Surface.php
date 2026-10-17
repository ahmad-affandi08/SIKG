<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

/**
 * Permukaan gigi (ODO-02). `Whole` untuk kondisi seluruh gigi (hilang, impaksi, implan, dan sebagainya).
 */
enum Surface: string
{
    case Mesial = 'M';
    case Distal = 'D';
    case Occlusal = 'O';
    case Buccal = 'B';
    case Lingual = 'L';
    case Whole = 'W';

    public function label(): string
    {
        return match ($this) {
            self::Mesial => 'Mesial',
            self::Distal => 'Distal',
            self::Occlusal => 'Oklusal atau insisal',
            self::Buccal => 'Bukal atau labial',
            self::Lingual => 'Lingual atau palatal',
            self::Whole => 'Seluruh gigi',
        };
    }

    /**
     * @return list<self> lima permukaan
     */
    public static function faces(): array
    {
        return [self::Mesial, self::Distal, self::Occlusal, self::Buccal, self::Lingual];
    }
}
