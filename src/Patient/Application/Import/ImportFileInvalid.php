<?php

declare(strict_types=1);

namespace Src\Patient\Application\Import;

use Src\Shared\Domain\DomainException;

final class ImportFileInvalid extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct('patient.import_file_invalid', $message);
    }
}
