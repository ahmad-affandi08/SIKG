<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

final readonly class TreatmentPlanRecord
{
    /**
     * @param  list<PlanItemRecord>  $items  urut sequence
     */
    public function __construct(
        public string $id,
        public string $patientId,
        public string $title,
        public PlanStatus $status,
        public ?string $notes,
        public DateTimeImmutable $createdAt,
        public array $items,
    ) {}

    /** Estimasi biaya seluruh langkah (rupiah bulat), termasuk yang sudah selesai. */
    public function estimateAmount(): int
    {
        return array_sum(array_map(fn (PlanItemRecord $i) => $i->estimateAmount, $this->items));
    }

    /** Estimasi biaya langkah yang belum selesai. */
    public function remainingAmount(): int
    {
        return array_sum(array_map(fn (PlanItemRecord $i) => $i->status === PlanItemStatus::Done ? 0 : $i->estimateAmount, $this->items));
    }
}
