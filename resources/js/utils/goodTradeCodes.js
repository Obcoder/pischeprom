export const goodTradeCodeFields = [
    { key: 'tn_ved_code', label: 'ТН ВЭД ЕАЭС', hint: '10 цифр · ввоз и вывоз в ЕАЭС', primary: true },
    { key: 'okpd2_code', label: 'ОКПД 2', hint: 'Российский классификатор продукции', primary: true },
    { key: 'hs_code', label: 'HS', hint: '6 цифр · международная товарная группа', primary: true },
    { key: 'cn_code', label: 'CN', hint: '8 цифр · товарная номенклатура ЕС' },
    { key: 'taric_code', label: 'TARIC', hint: '10 цифр · интегрированный тариф ЕС' },
    { key: 'htsus_code', label: 'HTSUS', hint: '8 или 10 цифр · импорт США' },
    { key: 'schedule_b_code', label: 'Schedule B', hint: '10 цифр · экспортная статистика США' },
    { key: 'gtin', label: 'GTIN / EAN / UPC', hint: '8, 12, 13 или 14 цифр · идентификатор товара' },
    { key: 'unspsc_code', label: 'UNSPSC', hint: '8 цифр · классификатор товаров и услуг' },
    { key: 'cas_number', label: 'CAS', hint: 'Регистрационный номер химического вещества' },
    { key: 'eccn_code', label: 'ECCN', hint: 'Экспортная классификация США' },
]

export function goodTradeCodeValues(source = {}) {
    return Object.fromEntries(goodTradeCodeFields.map(({ key }) => [key, source?.[key] || null]))
}
