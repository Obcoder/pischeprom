<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

trait RejectsDeprecatedProviderPayloadWrites
{
    public static function bootRejectsDeprecatedProviderPayloadWrites(): void
    {
        static::saving(function (Model $model): void {
            foreach ($model->deprecatedProviderPayloadColumns() as $column) {
                if ($model->isDirty($column) && $model->getAttribute($column) !== null) {
                    throw new LogicException('Deprecated provider payload columns are read-only.');
                }
            }
        });
    }

    /**
     * @return list<string>
     */
    abstract protected function deprecatedProviderPayloadColumns(): array;
}
