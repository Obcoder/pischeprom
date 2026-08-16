<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectingSearchJobService
{
    public function __construct(
        private readonly ProspectingFeatureGuard $features,
        private readonly ProspectingAuthorizationService $authorization,
    ) {}

    public function createDraft(array $attributes, User $actor): ProspectingSearchJob
    {
        $this->features->jobs();
        $this->features->assertNoLiveSearch();
        $purpose = ProspectingPurpose::from($attributes['purpose']);
        $this->authorization->authorize($actor, ProspectingAuthorizationService::MANAGE_JOBS, $purpose->lane());
        $criteria = $this->criteria($attributes['criteria'] ?? []);
        $safeObjective = $this->safeObjective((string) ($attributes['safe_objective'] ?? ''));

        return DB::transaction(function () use ($attributes, $actor, $purpose, $criteria, $safeObjective): ProspectingSearchJob {
            $job = ProspectingSearchJob::query()->create([
                'created_by' => $actor->id,
                'owner_user_id' => $actor->id,
                'purpose' => $purpose,
                'lane' => $purpose->lane(),
                'default_role_code' => $purpose->role(),
                'primary_good_id' => $attributes['primary_good_id'] ?? null,
                'country_id' => $attributes['country_id'] ?? null,
                'region_id' => $attributes['region_id'] ?? null,
                'city_id' => $attributes['city_id'] ?? null,
                'locale' => mb_substr((string) ($attributes['locale'] ?? 'ru-RU'), 0, 12),
                'max_queries' => $this->boundedInteger($attributes['max_queries'] ?? 10, 1, (int) config('ai-sales.prospecting.limits.max_queries', 20)),
                'max_candidates' => $this->boundedInteger($attributes['max_candidates'] ?? 100, 1, (int) config('ai-sales.prospecting.limits.max_candidates', 250)),
                'max_results_per_query' => $this->boundedInteger($attributes['max_results_per_query'] ?? 20, 1, 50),
                'max_rows' => $this->boundedInteger($attributes['max_rows'] ?? 500, 1, 1000),
                'max_bytes' => $this->boundedInteger($attributes['max_bytes'] ?? 1048576, 1024, 2097152),
                'max_searches' => 0,
                'max_cost_rub' => 0,
                'safe_objective' => $safeObjective,
                'criteria_snapshot' => $criteria,
                'policy_version' => 'stage08-v1',
                'workflow_version' => 'stage08-no-execution',
                'schema_hash' => hash('sha256', json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'status' => ProspectingJobStatus::Draft,
                'auto_create_unit' => false,
                'retention_profile' => 'prospecting-transient-v1',
            ]);
            $this->syncGoods($job, $attributes['additional_good_ids'] ?? [], $job->primary_good_id);

            return $job->fresh(['owner:id,name', 'primaryGood:id,name', 'goods:id,name']);
        }, 3);
    }

    public function updateDraft(ProspectingSearchJob $job, array $attributes, User $actor): ProspectingSearchJob
    {
        $this->features->jobs();
        if ($job->status !== ProspectingJobStatus::Draft || (int) $job->owner_user_id !== (int) $actor->id) {
            throw ValidationException::withMessages(['job' => 'Only the owner may update a draft job.']);
        }
        $purpose = ProspectingPurpose::from($attributes['purpose'] ?? $job->purpose->value);
        $this->authorization->authorize($actor, ProspectingAuthorizationService::MANAGE_JOBS, $purpose->lane());
        $criteria = array_key_exists('criteria', $attributes) ? $this->criteria($attributes['criteria']) : $job->criteria_snapshot;
        $safeObjective = $this->safeObjective((string) ($attributes['safe_objective'] ?? $job->safe_objective));

        return DB::transaction(function () use ($job, $attributes, $purpose, $criteria, $safeObjective): ProspectingSearchJob {
            $primaryGoodId = array_key_exists('primary_good_id', $attributes)
                ? $attributes['primary_good_id']
                : $job->primary_good_id;
            $job->fill([
                'purpose' => $purpose,
                'lane' => $purpose->lane(),
                'default_role_code' => $purpose->role(),
                'primary_good_id' => $primaryGoodId,
                'country_id' => array_key_exists('country_id', $attributes) ? $attributes['country_id'] : $job->country_id,
                'region_id' => array_key_exists('region_id', $attributes) ? $attributes['region_id'] : $job->region_id,
                'city_id' => array_key_exists('city_id', $attributes) ? $attributes['city_id'] : $job->city_id,
                'locale' => array_key_exists('locale', $attributes)
                    ? mb_substr((string) $attributes['locale'], 0, 12)
                    : $job->locale,
                'max_queries' => $this->boundedInteger($attributes['max_queries'] ?? $job->max_queries, 1, (int) config('ai-sales.prospecting.limits.max_queries', 20)),
                'max_candidates' => $this->boundedInteger($attributes['max_candidates'] ?? $job->max_candidates, 1, (int) config('ai-sales.prospecting.limits.max_candidates', 250)),
                'max_results_per_query' => $this->boundedInteger($attributes['max_results_per_query'] ?? $job->max_results_per_query, 1, 50),
                'max_rows' => $this->boundedInteger($attributes['max_rows'] ?? $job->max_rows, 1, 1000),
                'max_bytes' => $this->boundedInteger($attributes['max_bytes'] ?? $job->max_bytes, 1024, 2097152),
                'safe_objective' => $safeObjective,
                'criteria_snapshot' => $criteria,
                'schema_hash' => hash('sha256', json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'auto_create_unit' => false,
            ])->save();

            if (array_key_exists('primary_good_id', $attributes) || array_key_exists('additional_good_ids', $attributes)) {
                $additionalGoodIds = array_key_exists('additional_good_ids', $attributes)
                    ? $attributes['additional_good_ids']
                    : $job->goods()->wherePivot('role', 'additional')->pluck('goods.id')->all();
                $this->syncGoods($job, $additionalGoodIds, $primaryGoodId);
            }

            return $job->fresh(['owner:id,name', 'primaryGood:id,name', 'goods:id,name']);
        }, 3);
    }

    public function submit(ProspectingSearchJob $job, User $actor): ProspectingSearchJob
    {
        $this->authorization->authorize($actor, ProspectingAuthorizationService::MANAGE_JOBS, $job->lane);
        if ((int) $job->owner_user_id !== (int) $actor->id) {
            throw ValidationException::withMessages(['job' => 'Only the job owner may submit its draft.']);
        }
        $this->transition($job, ProspectingJobStatus::Draft, ProspectingJobStatus::ReviewRequired, $actor, false);

        return $job->fresh();
    }

    public function approve(ProspectingSearchJob $job, User $actor): ProspectingSearchJob
    {
        $this->authorization->authorize($actor, ProspectingAuthorizationService::REVIEW, $job->lane);
        $this->transition($job, ProspectingJobStatus::ReviewRequired, ProspectingJobStatus::Approved, $actor, true);

        return $job->fresh();
    }

    public function cancel(ProspectingSearchJob $job, User $actor): ProspectingSearchJob
    {
        $this->features->jobs();
        $this->authorization->authorize($actor, ProspectingAuthorizationService::MANAGE_JOBS, $job->lane);
        if (in_array($job->status, [ProspectingJobStatus::Cancelled, ProspectingJobStatus::Archived], true)) {
            return $job;
        }
        $job->update(['status' => ProspectingJobStatus::Cancelled, 'cancelled_at' => now(), 'reviewer_user_id' => $actor->id]);

        return $job->fresh();
    }

    public function archive(ProspectingSearchJob $job, User $actor): ProspectingSearchJob
    {
        $this->features->jobs();
        $this->authorization->authorize($actor, ProspectingAuthorizationService::REVIEW, $job->lane);
        if (! in_array($job->status, [ProspectingJobStatus::Cancelled, ProspectingJobStatus::Approved], true)) {
            throw ValidationException::withMessages(['status' => 'Only an approved or cancelled job can be archived.']);
        }
        $job->update(['status' => ProspectingJobStatus::Archived, 'reviewer_user_id' => $actor->id]);

        return $job->fresh();
    }

    private function transition(
        ProspectingSearchJob $job,
        ProspectingJobStatus $from,
        ProspectingJobStatus $to,
        User $actor,
        bool $approved,
    ): void {
        $this->features->jobs();
        $updated = ProspectingSearchJob::query()->whereKey($job->id)->where('status', $from->value)->update([
            'status' => $to->value,
            'reviewer_user_id' => $approved ? $actor->id : null,
            'approved_by' => $approved ? $actor->id : null,
            'approved_at' => $approved ? now() : null,
            'updated_at' => now(),
        ]);
        if ($updated !== 1) {
            throw ValidationException::withMessages(['status' => "Job must be {$from->value}."]);
        }
    }

    private function criteria(array $criteria): array
    {
        $allowed = collect($criteria)->only(['segments', 'industries', 'categories', 'notes']);

        return $allowed->map(function ($value, $key) {
            if ($key === 'notes') {
                return mb_substr(trim((string) $value), 0, 500);
            }

            return collect((array) $value)->take(25)->map(fn ($item) => mb_substr(trim((string) $item), 0, 120))->filter()->values()->all();
        })->all();
    }

    private function safeObjective(string $objective): string
    {
        $objective = mb_substr(trim($objective), 0, 512);
        if ($objective === '') {
            throw ValidationException::withMessages(['safe_objective' => 'A bounded safe objective is required.']);
        }

        return $objective;
    }

    private function boundedInteger(mixed $value, int $minimum, int $maximum): int
    {
        return max($minimum, min((int) $value, max($minimum, $maximum)));
    }

    private function syncGoods(ProspectingSearchJob $job, iterable $additionalGoodIds, ?int $primaryGoodId): void
    {
        $goods = collect($additionalGoodIds)->take(25)->mapWithKeys(fn ($id) => [
            (int) $id => ['role' => 'additional'],
        ])->all();
        if ($primaryGoodId) {
            $goods[$primaryGoodId] = ['role' => 'primary'];
        }
        $job->goods()->sync($goods);
    }
}
