export const goodRecordTabs = [
    { id: 'overview', label: 'Основное', icon: 'mdi-package-variant-closed' },
    { id: 'seo', label: 'SEO', icon: 'mdi-magnify' },
    { id: 'quotations', label: 'Закупки', icon: 'mdi-truck-outline' },
    { id: 'prices', label: 'Цены', icon: 'mdi-tag-outline' },
    { id: 'price-types', label: 'Типы цен', icon: 'mdi-format-list-bulleted-type' },
    { id: 'recommendations', label: 'Рекомендации', icon: 'mdi-lightbulb-outline' },
    { id: 'collections', label: 'Подборки', icon: 'mdi-folder-multiple-outline' },
    { id: 'media', label: 'Медиа', icon: 'mdi-image-multiple-outline' },
    { id: 'sales', label: 'Продажи', icon: 'mdi-cart-outline' },
]

export function normalizeTabOrder(value, tabs = goodRecordTabs) {
    const allowed = tabs.map(tab => tab.id)
    return [...new Set([...(Array.isArray(value) ? value : []).filter(id => allowed.includes(id)), ...allowed])]
}

export function moveTab(order, from, to) {
    if (from === to || !order.includes(from) || !order.includes(to)) return [...order]
    const result = order.filter(id => id !== from)
    result.splice(order.indexOf(to), 0, from)
    return result
}
