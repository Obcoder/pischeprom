<?php

use App\Models\Telephone;
use App\Services\Telephones\TelephoneIdentityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telephones')) {
            return;
        }

        $identity = app(TelephoneIdentityService::class);

        Telephone::query()
            ->select(['id', 'number'])
            ->orderBy('id')
            ->chunkById(250, function ($telephones) use ($identity): void {
                foreach ($telephones as $telephone) {
                    if ($identity->normalize($telephone->number)) {
                        $identity->resolve($telephone->number);
                    }
                }
            });
    }

    public function down(): void
    {
        // Canonical phone formatting and duplicate folding are intentionally irreversible.
    }
};
