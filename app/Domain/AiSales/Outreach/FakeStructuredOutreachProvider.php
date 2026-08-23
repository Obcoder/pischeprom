<?php

namespace App\Domain\AiSales\Outreach;

final class FakeStructuredOutreachProvider
{
    public function generate(OutreachSafeDto $dto): array
    {
        $input = $dto->toArray();
        $product = $input['product']['name'];
        $offer = $input['offer']['name'] ?? null;

        return [
            'subject' => 'Возможности сотрудничества по продукту «'.$product.'»',
            'greeting' => 'Здравствуйте!',
            'introduction' => 'Предлагаем обсудить возможное сотрудничество по выбранному направлению.',
            'value_proposition' => $offer
                ? 'Для обсуждения подготовлено предложение «'.$offer.'», связанное с продуктом «'.$product.'».'
                : 'Для обсуждения выбран продукт «'.$product.'».',
            'evidence_points' => array_values(array_filter([
                $input['product']['match_rationale'] ?? null,
                $input['offer']['fit_rationale'] ?? null,
            ])),
            'call_to_action' => 'Если тема актуальна, предлагаем согласовать удобный формат дальнейшего обсуждения.',
            'closing' => 'С уважением, команда ПИЩЕПРОМ-СЕРВЕР',
            'claims' => array_values(array_filter([
                [
                    'type' => 'product_relevance',
                    'text' => 'Выбран продукт «'.$product.'».',
                    'evidence_type' => 'unit_product_match',
                    'evidence_reference' => $input['product']['evidence_reference'],
                    'evidence_hash' => $input['product']['evidence_hash'],
                ],
                $offer ? [
                    'type' => 'good_offer_fit',
                    'text' => 'Подготовлено предложение «'.$offer.'».',
                    'evidence_type' => 'unit_good_match',
                    'evidence_reference' => $input['offer']['evidence_reference'],
                    'evidence_hash' => $input['offer']['evidence_hash'],
                ] : null,
            ])),
        ];
    }
}
