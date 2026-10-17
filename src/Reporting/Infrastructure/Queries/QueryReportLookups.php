<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Queries;

use Src\Reporting\Application\ReportLookups;

final readonly class QueryReportLookups implements ReportLookups
{
    public function __construct(private ReportSource $source) {}

    public function doctors(): array
    {
        return $this->named('doctors');
    }

    public function paymentMethods(): array
    {
        return $this->named('payment_methods');
    }

    public function doctorIdForUser(string $userId): ?string
    {
        $id = $this->source->from('doctors', 'd')->where('d.user_id', $userId)->orderBy('d.id')->value('d.id');

        return is_string($id) ? $id : null;
    }

    public function today(): string
    {
        return $this->source->today();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function named(string $table): array
    {
        $list = [];
        foreach ($this->source->from($table, 't')->orderBy('t.name')->get(['t.id', 't.name']) as $row) {
            $list[] = ['id' => (string) $row->id, 'name' => (string) $row->name];
        }

        return $list;
    }
}
