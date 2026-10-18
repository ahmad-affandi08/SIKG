<?php

declare(strict_types=1);

namespace Src\Shared\Application\Setup;

/**
 * Daftar penyumbang langkah dan data awal yang didaftarkan ServiceProvider tiap konteks (pola yang sama dengan MasterEntityRegistry).
 */
final class SetupRegistry
{
    /** @var list<SetupStepProvider> */
    private array $stepProviders = [];

    /** @var list<StarterDataContributor> */
    private array $starters = [];

    public function registerSteps(SetupStepProvider $provider): void
    {
        $this->stepProviders[] = $provider;
    }

    public function registerStarter(StarterDataContributor $contributor): void
    {
        $this->starters[] = $contributor;
    }

    /** @return list<SetupStepProvider> */
    public function stepProviders(): array
    {
        return $this->stepProviders;
    }

    /** @return list<StarterDataContributor> */
    public function starters(): array
    {
        return $this->starters;
    }
}
