<?php

namespace App\Services\Mail;

use App\Models\Good;
use App\Models\Product;
use App\Services\Goods\PublicGoodOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MailOfferCatalog
{
    public function __construct(private readonly PublicGoodOffer $offers) {}

    public function search(string $search, int $perPage = 20): LengthAwarePaginator
    {
        $search = trim($search);

        return $this->query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $like = '%'.$search.'%';
                    $query->where('name', 'like', $like)->orWhere('slug', 'like', $like);
                    if (ctype_digit($search)) {
                        $query->orWhere('id', (int) $search);
                    }
                    $query->orWhereHas('products', function (Builder $query) use ($like): void {
                        $query->where(function (Builder $query) use ($like): void {
                            foreach (Product::TRANSLATION_COLUMNS as $column) {
                                $query->orWhere($column, 'like', $like);
                            }
                            $query->orWhereHas('category', fn (Builder $query) => $query->where('name', 'like', $like));
                        });
                    });
                });
            })
            ->orderBy('name')->orderBy('id')
            ->paginate(min(20, max(1, $perPage)))
            ->through(fn (Good $good) => $this->snapshot($good));
    }

    /** Re-read published goods at preview/send time; client input never supplies catalog URLs or HTML. */
    public function resolve(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $goods = $this->query()->whereIn('id', array_column($items, 'good_id'))->get()->keyBy('id');

        return array_map(function (array $item, int $index) use ($goods): array {
            $good = $goods->get($item['good_id']);
            if (! $good) {
                throw ValidationException::withMessages([
                    'offer.items.'.$index.'.good_id' => 'Товар больше недоступен для предложения. Удалите его из письма или выберите другой.',
                ]);
            }

            $snapshot = $this->snapshot($good);
            if (isset($item['price_override'])) {
                $snapshot['price'] = (float) $item['price_override'];
            }
            if (isset($item['specifications'])) {
                $snapshot['specifications'] = collect($snapshot['specifications'])
                    ->concat($item['specifications'])->keyBy('label')->values()->all();
            }

            return [
                ...$snapshot,
                'quantity' => isset($item['quantity']) ? (float) $item['quantity'] : null,
                'include_description' => (bool) ($item['include_description'] ?? false),
                'include_specifications' => (bool) ($item['include_specifications'] ?? false),
                'include_image' => (bool) ($item['include_image'] ?? true),
            ];
        }, $items, array_keys($items));
    }

    private function query(): Builder
    {
        return Good::query()->where('is_published', true)->whereNotNull('slug')->where('slug', '!=', '')->with([
            'country:id,name',
            'products' => fn ($query) => $query->without('manufacturers')->with('category'),
            'publishedMedia' => fn ($query) => $query->where('type', 'image')->orderByDesc('is_ava'),
        ]);
    }

    private function snapshot(Good $good): array
    {
        $offer = $this->offers->for($good);
        $image = $good->publishedMedia->sortByDesc('is_ava')->first();
        $specifications = [];
        if ($offer['package_weight']) {
            $specifications[] = ['label' => 'Фасовка', 'value' => rtrim(rtrim(number_format($offer['package_weight'], 3, ',', ' '), '0'), ',').' кг'];
        }
        if ($good->country?->name) {
            $specifications[] = ['label' => 'Страна происхождения', 'value' => $good->country->name];
        }
        $categories = $good->products->pluck('category.name')->filter()->unique()->implode(', ');
        if ($categories !== '') {
            $specifications[] = ['label' => 'Категория', 'value' => Str::limit($categories, 500, '')];
        }

        return [
            'id' => $good->id,
            'name' => $good->name,
            'url' => route('public.goods.show', ['good' => $good->slug]),
            'image_url' => $this->imageUrl($image?->url ?: $image?->thumb_url ?: $good->ava_image ?: $good->ava_thumb),
            'description' => Str::limit(trim(html_entity_decode(strip_tags((string) $good->description), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 12000, ''),
            'specifications' => $specifications,
            'price' => $offer['price'],
            'currency_code' => $offer['currency_code'],
            'price_unit_label' => $offer['price_unit_label'],
            'includes_vat' => $offer['price'] !== null ? $offer['includes_vat'] : null,
            'package_weight' => $offer['package_weight'],
        ];
    }

    private function imageUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/[\x00-\x20\x7f]/', $value)) {
            return null;
        }
        if (str_starts_with($value, '//')) {
            $value = 'https:'.$value;
        } elseif (! preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) {
            $value = rtrim((string) config('app.url'), '/').'/'.ltrim($value, '/');
        }
        $parts = parse_url($value);
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || preg_match('/\.(?:mp4|m4v|mov|webm|avi|mkv|mpeg|mpg|ogv|m3u8|pdf|docx?|xlsx?|zip|rar)$/i', $parts['path'] ?? '')) {
            return null;
        }

        return $value;
    }
}
