<?php

declare(strict_types=1);

namespace Src\Shared\Application\Master;

/**
 * Definisi satu kolom formulir master data. Tipe: text, textarea, number, money (rupiah bulat), select, date (YYYY-MM-DD).
 */
final readonly class MasterField
{
    /**
     * @param  list<array{value: string, label: string}>  $options  untuk tipe select
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'text',
        public bool $required = false,
        public array $options = [],
        public ?int $max = null,
        public ?string $help = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'options' => $this->options,
            'max' => $this->max,
            'help' => $this->help,
        ];
    }
}
