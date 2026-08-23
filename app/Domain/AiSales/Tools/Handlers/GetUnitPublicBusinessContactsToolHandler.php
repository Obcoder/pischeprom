<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\PublicBusinessContactSummary;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use Illuminate\Support\Facades\DB;

class GetUnitPublicBusinessContactsToolHandler implements AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        $links = DB::table('unit_contact_context_links')
            ->where('unit_id', $context->unitId)
            ->whereNull('unit_business_context_id')
            ->whereNull('archived_at')
            ->where('contact_role', 'business')
            ->where('verification_status', ObservationVerificationStatus::Verified->value)
            ->where('data_classification', DataClassification::Public->value)
            ->where('visibility_scope', UnitVisibilityScope::SharedPublic->value)
            ->select(['id', 'channel_type', 'email_id', 'telephone_id', 'uri_id', 'unit_source_id'])
            ->orderBy('id')
            ->limit(20)
            ->get();

        $emails = DB::table('emails')
            ->whereIn('id', $links->pluck('email_id')->filter()->all())
            ->whereNull('deleted_at')
            ->select(['id', 'address'])
            ->pluck('address', 'id');
        $telephones = DB::table('telephones')
            ->whereIn('id', $links->pluck('telephone_id')->filter()->all())
            ->select(['id', 'number'])
            ->pluck('number', 'id');
        $uris = DB::table('uris')
            ->whereIn('id', $links->pluck('uri_id')->filter()->all())
            ->select(['id', 'address'])
            ->pluck('address', 'id');
        $sourceLabels = DB::table('unit_sources')
            ->whereIn('id', $links->pluck('unit_source_id')->filter()->all())
            ->select(['id', 'source_label'])
            ->pluck('source_label', 'id');

        $items = $links->map(function (object $link) use ($emails, $telephones, $uris, $sourceLabels): ?PublicBusinessContactSummary {
            $value = match ($link->channel_type) {
                'email' => $emails[$link->email_id] ?? null,
                'telephone' => $telephones[$link->telephone_id] ?? null,
                'uri' => $uris[$link->uri_id] ?? null,
                default => null,
            };

            return is_string($value) && $value !== ''
                ? new PublicBusinessContactSummary(
                    $link->channel_type,
                    $value,
                    $sourceLabels[$link->unit_source_id] ?? null,
                    true,
                )
                : null;
        })->filter()->values()->all();

        return new AiToolHandlerResult($items, 'unit_public_business_contacts', $context->unitId);
    }
}
