<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Master;

use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Master\MasterEntity;
use Src\Shared\Application\Transaction;
use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * Pola bersama master data: daftar, buat, ubah, aktif/nonaktif, semua tercatat di audit dengan sebelum dan sesudah.
 */
abstract class EloquentMasterEntity implements MasterEntity
{
    public function __construct(protected readonly Transaction $tx, protected readonly AuditLogger $audit) {}

    /** @return class-string<TenantMasterModel> */
    abstract protected function modelClass(): string;

    /**
     * Jenis subjek di audit, contoh `chair`.
     *
     * @return non-empty-string
     */
    abstract protected function subject(): string;

    protected function orderColumn(): string
    {
        return 'name';
    }

    public function list(): array
    {
        $keys = array_map(fn ($f) => $f->key, $this->fields());

        return array_values($this->modelClass()::query()->orderBy($this->orderColumn())->get()
            ->map(fn (TenantMasterModel $m) => ['id' => $m->id, ...$m->only($keys), 'is_active' => $m->is_active])
            ->all());
    }

    public function create(string $actorId, array $data): string
    {
        return $this->tx->run(function () use ($actorId, $data): string {
            $model = new ($this->modelClass());
            $model->forceFill([...$this->clean($data), 'is_active' => true, 'created_by' => $actorId])->save();

            $this->audit->record(new AuditEntry($actorId, $this->subject().'.created', $this->subject(), $model->id, null, $this->snapshot($model)));

            return $model->id;
        });
    }

    public function update(string $actorId, string $id, array $data): void
    {
        $this->tx->run(function () use ($actorId, $id, $data): void {
            $model = $this->modelClass()::query()->findOrFail($id);
            $before = $this->snapshot($model);
            $model->forceFill([...$this->clean($data), 'updated_by' => $actorId])->save();

            $this->audit->record(new AuditEntry($actorId, $this->subject().'.updated', $this->subject(), $model->id, $before, $this->snapshot($model)));
        });
    }

    public function setActive(string $actorId, string $id, bool $active): void
    {
        $this->tx->run(function () use ($actorId, $id, $active): void {
            $model = $this->modelClass()::query()->findOrFail($id);
            $model->forceFill(['is_active' => $active, 'updated_by' => $actorId])->save();

            $this->audit->record(new AuditEntry(
                $actorId,
                $this->subject().($active ? '.activated' : '.deactivated'),
                $this->subject(),
                $model->id,
                ['is_active' => ! $active],
                ['is_active' => $active],
            ));
        });
    }

    /**
     * Hanya kolom yang didefinisikan; string kosong pada kolom opsional menjadi null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function clean(array $data): array
    {
        $out = [];
        foreach ($this->fields() as $field) {
            if (! array_key_exists($field->key, $data)) {
                continue;
            }
            $value = $data[$field->key];
            $out[$field->key] = ($value === '' || $value === null) && ! $field->required ? null : $value;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(TenantMasterModel $model): array
    {
        return [...$model->only(array_map(fn ($f) => $f->key, $this->fields())), 'is_active' => $model->is_active];
    }
}
