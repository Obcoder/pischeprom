<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiControlSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AiKillSwitchService
{
    public const GLOBAL = 'kill_switch.global';

    public const LOCAL_RU = 'kill_switch.local_ru';

    public const EXTERNAL_SANITIZED = 'kill_switch.external_sanitized';

    public function all(): array
    {
        $settings = AiControlSetting::query()
            ->whereIn('key', [self::GLOBAL, self::LOCAL_RU, self::EXTERNAL_SANITIZED])
            ->pluck('boolean_value', 'key');

        return [
            'global' => (bool) ($settings[self::GLOBAL] ?? true),
            'local_ru' => (bool) ($settings[self::LOCAL_RU] ?? true),
            'external_sanitized' => (bool) ($settings[self::EXTERNAL_SANITIZED] ?? true),
        ];
    }

    public function assertOpen(AiProcessingContour $contour): void
    {
        $switches = $this->all();

        if ($switches['global'] || ($switches[$contour->value] ?? true)) {
            throw new PolicyViolation('ai_kill_switch_active', 'The AI processing contour is disabled by a server-side kill switch.');
        }
    }

    public function set(string $scope, bool $enabled, User $actor): array
    {
        $key = match ($scope) {
            'global' => self::GLOBAL,
            'local_ru' => self::LOCAL_RU,
            'external_sanitized' => self::EXTERNAL_SANITIZED,
            default => throw new PolicyViolation('unknown_kill_switch', 'Kill switch scope is not code-owned.'),
        };

        DB::transaction(function () use ($key, $enabled, $actor): void {
            AiControlSetting::query()->updateOrCreate(
                ['key' => $key],
                ['boolean_value' => $enabled, 'updated_by' => $actor->id],
            );
        }, 3);

        return $this->all();
    }
}
