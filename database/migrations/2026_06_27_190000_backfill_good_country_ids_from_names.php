<?php

use App\Models\Country;
use App\Models\Good;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('goods', 'country_id')) {
            return;
        }

        $countries = Country::query()
            ->select(['id', 'name'])
            ->whereNotNull('name')
            ->orderByRaw('CHAR_LENGTH(name) DESC')
            ->get();

        if ($countries->isEmpty()) {
            return;
        }

        Good::query()
            ->whereNull('country_id')
            ->with('products:id,rus,eng')
            ->chunkById(100, function ($goods) use ($countries): void {
                foreach ($goods as $good) {
                    $haystack = trim(collect([
                        $good->name,
                        ...$good->products->flatMap(fn ($product) => [
                            $product->rus,
                            $product->eng,
                        ])->all(),
                    ])->filter()->implode(' '));

                    foreach ($countries as $country) {
                        if ($this->containsCountryName($haystack, (string) $country->name)) {
                            $good->forceFill(['country_id' => $country->id])->saveQuietly();
                            break;
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        // Data backfill is intentionally not reverted.
    }

    private function containsCountryName(string $haystack, string $countryName): bool
    {
        if ($haystack === '' || $countryName === '') {
            return false;
        }

        $pattern = '/(^|[^\p{L}\p{N}])' . preg_quote($countryName, '/') . '([^\p{L}\p{N}]|$)/iu';

        return preg_match($pattern, $haystack) === 1;
    }
};
