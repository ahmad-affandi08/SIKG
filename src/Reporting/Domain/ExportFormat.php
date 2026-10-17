<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

enum ExportFormat: string
{
    case Pdf = 'pdf';
    case Xlsx = 'xlsx';

    public function extension(): string
    {
        return $this->value;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
