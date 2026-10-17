<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure;

use Src\Clinical\Contracts\EncounterFileLookup;
use Src\Clinical\Contracts\EncounterFileSummary;
use Src\Clinical\Domain\ClinicalFileRepository;
use Src\Clinical\Domain\FileKind;

final readonly class EloquentEncounterFileLookup implements EncounterFileLookup
{
    public function __construct(private ClinicalFileRepository $files) {}

    public function imagesFor(array $encounterIds): array
    {
        $out = [];
        foreach ($this->files->forEncounters($encounterIds) as $encounterId => $files) {
            foreach ($files as $file) {
                if ($file->kind === FileKind::Document || ! str_starts_with($file->mime, 'image/')) {
                    continue;
                }
                $out[$encounterId][] = new EncounterFileSummary($file->id, $file->kind->value, $file->kind->label(), $file->caption, $file->mime);
            }
        }

        return $out;
    }
}
