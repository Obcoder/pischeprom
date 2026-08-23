<?php

namespace App\Domain\AiSales\Prospecting;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Models\ProspectingSearchResult;

final class ResultBusinessRoleClassifier
{
    public const VERSION = 'result-business-role-v1';

    public function classify(ProspectingSearchResult $result, BusinessLane|string|null $lane = null): ResultBusinessRoleDecision
    {
        $result->loadMissing(['publicFetch', 'research']);
        if ($lane === null) {
            $result->loadMissing('job:id,lane');
            $lane = $result->job->lane;
        }
        $text = implode(' ', array_filter([
            $result->title, $result->snippet, $result->registrable_domain,
            $result->publicFetch?->page_title, $result->publicFetch?->meta_description,
            $result->publicFetch?->text_excerpt,
            ...((array) ($result->publicFetch?->headings ?? [])),
            $result->research?->safe_summary,
            ...((array) ($result->research?->activity_mentions ?? [])),
        ]));

        return $this->classifyEvidence($text, (string) $result->registrable_domain, $lane);
    }

    public function classifyEvidence(string $text, string $domain, BusinessLane|string $lane = BusinessLane::Sales): ResultBusinessRoleDecision
    {
        $lane = $lane instanceof BusinessLane ? $lane : BusinessLane::from($lane);
        if ($lane !== BusinessLane::Sales) {
            return $this->decision(ResultBusinessRole::Unknown, ['buyer_classifier_not_authoritative_for_procurement_lane'], 0);
        }

        $haystack = mb_strtolower($domain.' '.$text);
        $rules = [
            ResultBusinessRole::Marketplace->value => ['marketplace', 'маркетплейс', 'wildberries', 'ozon.ru', 'avito.ru', 'aliexpress', 'товары от продавцов'],
            ResultBusinessRole::Directory->value => ['справочник', 'каталог компаний', 'directory', '2gis.', 'yell.ru', 'flamp.ru', 'list-org', 'orgpage', 'rusprofile'],
            ResultBusinessRole::Informational->value => ['рецепт', 'как приготовить', 'статья', 'новости', 'энциклопедия', 'wiki', 'блог'],
            ResultBusinessRole::Retailer->value => ['интернет-магазин', 'магазин продуктов', 'супермаркет', 'розничная сеть', 'retail'],
            ResultBusinessRole::SupplierOrCompetitor->value => ['купить с доставкой', 'купить оптом', 'продажа оптом', 'поставщик', 'оптовый продавец', 'прайс-лист', 'заказать товар'],
        ];
        foreach ($rules as $role => $needles) {
            $matches = $this->matches($haystack, $needles);
            if ($matches !== []) {
                return $this->decision(ResultBusinessRole::from($role), $matches, 95);
            }
        }
        if ($this->matches($haystack, ['вакансия', 'форум', 'реферат', 'скачать']) !== []) {
            return $this->decision(ResultBusinessRole::Irrelevant, ['non_business_page_signal'], 80);
        }

        $strong = $this->matches($haystack, [
            'производитель', 'производство', 'фабрика', 'комбинат питания', 'фабрика-кухня',
            'центральная производственная кухня', 'школьное питание',
            'переработка овощей', 'готовые блюда', 'замороженные продукты', 'общественное питание',
            'manufacturer', 'food factory', 'catering',
        ]);
        $weak = $this->matches($haystack, [
            'продукция', 'ассортимент', 'виды деятельности', 'корпоративное питание',
            'полуфабрикаты', 'ингредиенты', 'сырье', 'horeca', 'дистрибьютор', 'поставки ресторанам',
        ]);
        if (count($strong) >= 2 || ($strong !== [] && $weak !== [])) {
            return $this->decision(ResultBusinessRole::PotentialBuyer, [...$strong, ...$weak], 85);
        }
        if ($strong !== [] || count($weak) >= 2) {
            return $this->decision(ResultBusinessRole::PossibleBuyer, [...$strong, ...$weak], 65);
        }

        return $this->decision(ResultBusinessRole::Unknown, ['buyer_role_evidence_insufficient'], 20);
    }

    /** @param list<string> $needles
     * @return list<string>
     */
    private function matches(string $haystack, array $needles): array
    {
        return collect($needles)->filter(fn (string $needle): bool => str_contains($haystack, $needle))
            ->map(fn (string $needle): string => 'signal:'.str_replace([' ', '.'], '_', $needle))
            ->values()->all();
    }

    private function decision(ResultBusinessRole $role, array $reasons, int $confidence): ResultBusinessRoleDecision
    {
        $research = in_array($role, [
            ResultBusinessRole::PotentialBuyer,
            ResultBusinessRole::PossibleBuyer,
            ResultBusinessRole::Unknown,
            ResultBusinessRole::Retailer,
            ResultBusinessRole::Directory,
        ], true);
        $candidate = in_array($role, [ResultBusinessRole::PotentialBuyer, ResultBusinessRole::PossibleBuyer], true);

        return new ResultBusinessRoleDecision($role, array_values(array_unique($reasons)), $confidence, $research, $candidate);
    }
}
