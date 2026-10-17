<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

final readonly class DocumentTemplate
{
    /**
     * @param  'consent'|'certificate'|'referral'  $type
     */
    public function __construct(public string $id, public string $type, public string $name, public ?string $procedureId, public string $body) {}

    /**
     * @return array{id: string, type: string, name: string, procedure_id: string|null, body: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'name' => $this->name, 'procedure_id' => $this->procedureId, 'body' => $this->body];
    }
}
