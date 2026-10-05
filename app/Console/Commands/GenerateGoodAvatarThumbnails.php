<?php

namespace App\Console\Commands;

use App\Models\Good;
use App\Services\Goods\GoodAvatarImages;
use Illuminate\Console\Command;
use Throwable;

class GenerateGoodAvatarThumbnails extends Command
{
    protected $signature = 'goods:avatar-thumbnails
        {--good=* : Обработать только указанные ID товаров}
        {--force : Обновить существующие миниатюры: 160 px и длительное кеширование}';

    protected $description = 'Создать недостающие миниатюры аватаров вне запросов списка товаров';

    public function handle(GoodAvatarImages $images): int
    {
        $ids = $this->option('good');
        foreach ($ids as $id) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                $this->error('Параметр --good должен содержать положительный ID.');

                return self::INVALID;
            }
        }

        $query = Good::query()->whereNotNull('ava_image')->where('ava_image', '!=', '')
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->when(! $this->option('force'), fn ($query) => $query->where(function ($query) {
                $query->whereNull('ava_thumb')->orWhere('ava_thumb', '')->orWhereColumn('ava_thumb', 'ava_image');
            }));

        $created = 0;
        $failed = 0;
        foreach ($query->lazyById(100) as $good) {
            try {
                $thumbnail = $images->regenerate($good);
                // Do not overwrite a concurrently replaced avatar or selected media thumbnail.
                $created += Good::query()->whereKey($good->id)
                    ->where('ava_image', $good->ava_image)
                    ->where('ava_thumb', $good->ava_thumb)
                    ->update(['ava_thumb' => $thumbnail]);
            } catch (Throwable $exception) {
                $failed++;
                $this->warn("Good #{$good->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Миниатюр обновлено: {$created}; ошибок: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
