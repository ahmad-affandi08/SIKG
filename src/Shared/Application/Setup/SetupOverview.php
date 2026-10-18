<?php

declare(strict_types=1);

namespace Src\Shared\Application\Setup;

/**
 * MST-10: kemajuan penyiapan awal klinik, dikumpulkan dari penyumbang tiap konteks dan dibaca dari isi data (tanpa status
 * tersimpan, sehingga selalu mengikuti keadaan sebenarnya). Langkah wajib menentukan "siap dipakai"; lainnya anjuran.
 */
final class SetupOverview
{
    public function __construct(private readonly SetupRegistry $registry) {}

    /**
     * @return list<SetupStep> urut menurut `order`
     */
    public function steps(string $clinicId): array
    {
        $steps = [];
        foreach ($this->registry->stepProviders() as $provider) {
            array_push($steps, ...$provider->steps($clinicId));
        }
        usort($steps, fn (SetupStep $a, SetupStep $b) => $a->order <=> $b->order);

        return $steps;
    }

    /**
     * @return array{done: int, total: int}
     */
    public function requiredProgress(string $clinicId): array
    {
        $required = array_filter($this->steps($clinicId), fn (SetupStep $s) => $s->required);

        return ['done' => count(array_filter($required, fn (SetupStep $s) => $s->done)), 'total' => count($required)];
    }
}
