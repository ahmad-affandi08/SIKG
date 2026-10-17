<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

final readonly class NoteRecord
{
    public function __construct(
        public string $id,
        public string $encounterId,
        public NoteType $type,
        public ?NoteSection $section,
        public string $body,
        public string $authorId,
        public DateTimeImmutable $authoredAt,
        public ?string $supersedesId,
        public ?string $reason,
    ) {}
}
