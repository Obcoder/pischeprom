export function avitoError(failure, fallback = 'Не удалось выполнить действие. Повторите попытку.') {
    return Object.values(failure?.response?.data?.errors || {}).flat().join(' ')
        || failure?.response?.data?.message || fallback
}

export function avitoMoney(value, currency = 'RUB') {
    if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) return '—'
    return `${Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 4 })} ${currency || ''}`.trim()
}

export function avitoDate(value) {
    if (!value) return '—'
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('ru-RU')
}

export function avitoListingUrl(value) {
    try {
        const url = new URL(String(value || ''))
        return url.protocol === 'https:' && (url.hostname === 'avito.ru' || url.hostname.endsWith('.avito.ru')) ? url.href : null
    } catch { return null }
}

export function avitoPriceBlockReason(row, data) {
    const type = data?.types?.find(item => Number(item.id) === Number(row.target_id))
    if (row.scope !== 'common' || !type?.is_public) return 'В Авито доступна только общедоступная цена.'
    if (!row.id || row.dirty) return 'Сначала сохраните цену.'
    if (row.busy) return 'Дождитесь сохранения цены.'
    if (!row.active) return 'Цена отключена.'
    const currency = data?.currencies?.find(item => Number(item.id) === Number(row.currency_id))?.code
    if (String(currency || '').toUpperCase() !== 'RUB') return 'Авито принимает цену в рублях.'
    const amount = row.saved?.price_gross ?? row.result?.price_gross
    if (amount == null || amount === '' || !Number.isFinite(Number(amount)) || Number(amount) <= 0) return 'Укажите положительную цену с НДС.'
    const measurement = row.measurement || data?.measurement
    if (!measurement?.measure_id || !measurement?.unit_label) return 'Сначала укажите единицу товара.'
    return ''
}
