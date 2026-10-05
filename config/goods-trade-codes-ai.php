<?php

return [
    // Null values reuse the existing catalog AI configuration.
    'enabled' => env('GOODS_TRADE_CODES_AI_ENABLED'),
    'timeweb' => [
        'api_key' => env('GOODS_TRADE_CODES_AI_API_KEY'),
        'model' => env('GOODS_TRADE_CODES_AI_MODEL'),
        'token_parameter' => env('GOODS_TRADE_CODES_AI_TOKEN_PARAMETER'),
        'timeout_seconds' => env('GOODS_TRADE_CODES_AI_TIMEOUT_SECONDS'),
    ],
    'default_fields' => ['tn_ved_code', 'okpd2_code', 'hs_code'],
    // Reference pages describe the systems; they do not verify individual recommendations.
    'reference_checked_at' => '2026-10-06',
    'sources' => [
        'hs_code' => [
            'id' => 'wco_hs', 'title' => 'WCO: Harmonized System',
            'url' => 'https://www.wcoomd.org/en/topics/nomenclature.aspx',
            'summary' => 'HS — международная товарная номенклатура; код субпозиции содержит 6 цифр. Классификация зависит от состава, назначения и обработки товара.',
        ],
        'tn_ved_code' => [
            'id' => 'eec_tn_ved', 'title' => 'ЕЭК: ТН ВЭД ЕАЭС',
            'url' => 'https://eec.eaeunion.org/comission/department/catr/ett/',
            'summary' => 'ТН ВЭД ЕАЭС — номенклатура ЕАЭС с 10-значными кодами. Детализация требует конкретных характеристик товара; нельзя дополнять HS произвольными нулями.',
        ],
        'okpd2_code' => [
            'id' => 'rosstat_okpd2', 'title' => 'Росстат: классификаторы, ОКПД2',
            'url' => 'https://www.rosstat.gov.ru/classification',
            'summary' => 'ОКПД2 — самостоятельный российский классификатор продукции по видам экономической деятельности. Код не выводится механически из HS или ТН ВЭД.',
        ],
        'cn_code' => [
            'id' => 'eu_cn', 'title' => 'Еврокомиссия: Combined Nomenclature',
            'url' => 'https://taxation-customs.ec.europa.eu/customs/common-customs-tariff-cct/tariff-classification-goods/combined-nomenclature_en',
            'summary' => 'CN содержит 8 цифр и детализирует HS для ЕС. Нужна проверка актуального года номенклатуры.',
        ],
        'taric_code' => [
            'id' => 'eu_taric', 'title' => 'Еврокомиссия: TARIC',
            'url' => 'https://taxation-customs.ec.europa.eu/online-services/online-services-and-databases-customs/eu-customs-tariff-taric_en',
            'summary' => '10-значный код TARIC расширяет 8-значный код CN. Дополнительные меры и отдельные дополнительные коды не входят в это поле.',
        ],
        'htsus_code' => [
            'id' => 'usitc_hts', 'title' => 'USITC: HTS, тарифные и статистические коды',
            'url' => 'https://www.usitc.gov/faq/question/what_do_all_columns_mean.htm',
            'summary' => 'HTSUS: 8 цифр определяют тарифную позицию США, 10 цифр — статистическую детализацию импорта.',
        ],
        'schedule_b_code' => [
            'id' => 'census_schedule_b', 'title' => 'U.S. Census Bureau: Schedule B',
            'url' => 'https://www.census.gov/foreign-trade/schedules/b/index.html',
            'summary' => 'Schedule B — 10-значные коды экспортной статистики США. Они не всегда совпадают с кодами HTSUS.',
        ],
        'gtin' => [
            'id' => 'gs1_gtin', 'title' => 'GS1: Global Trade Item Number',
            'url' => 'https://www.gs1.org/services/verified-by-gs1',
            'summary' => 'GTIN — присвоенный идентификатор конкретного товара и упаковки. Его нельзя вычислить или придумать по названию. Нужен штрихкод производителя или проверка GS1.',
        ],
        'unspsc_code' => [
            'id' => 'undp_unspsc', 'title' => 'UNDP: UNSPSC',
            'url' => 'https://www.undp.org/unspsc',
            'summary' => 'UNSPSC — самостоятельная 8-значная классификация товаров и услуг для закупок, не таможенный тариф.',
        ],
        'cas_number' => [
            'id' => 'cas_registry', 'title' => 'CAS: Registry',
            'url' => 'https://www.cas.org/cas-data/cas-registry',
            'summary' => 'CAS RN — регистрационный идентификатор определённого вещества. Для рекомендации нужно установить точную химическую идентичность и форму по SDS/паспорту производителя. Не присваивай номер отдельного ингредиента готовой смеси.',
        ],
        'eccn_code' => [
            'id' => 'bis_eccn', 'title' => 'BIS: классификация ECCN',
            'url' => 'https://www.bis.gov/licensing/classify-your-item',
            'summary' => 'ECCN требует проверки документированных технических характеристик по Commerce Control List и применимости EAR. Отсутствие данных не доказывает EAR99; ECCN не выводится из таможенного кода.',
        ],
    ],
];
