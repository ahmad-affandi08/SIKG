<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

use Src\Shared\Domain\DomainException;

final class InvalidReminder extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct('reminder.invalid', $message);
    }
}
