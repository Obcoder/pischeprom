<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    connections: { type: Array, default: () => [] },
    configured: { type: Boolean, default: false },
    enabled: { type: Boolean, default: true },
    mutationsEnabled: { type: Boolean, default: false },
    documentationUrl: { type: String, default: 'https://developers.avito.ru/api-catalog' },
})

const emit = defineEmits(['notice', 'error', 'open-catalog'])

const STATUS_OPTIONS = [
    { title: 'Активные', value: 'active', color: 'success' },
    { title: 'Архив / удалены', value: 'removed', color: 'grey' },
    { title: 'Завершены', value: 'old', color: 'blue-grey' },
    { title: 'Заблокированы', value: 'blocked', color: 'error' },
    { title: 'Отклонены', value: 'rejected', color: 'warning' },
]

const METRIC_OPTIONS = [
    { title: 'Просмотры', value: 'views', short: 'Просм.', icon: 'mdi-eye-outline' },
    { title: 'Контакты', value: 'contacts', short: 'Конт.', icon: 'mdi-account-arrow-right-outline' },
    { title: 'Показали телефон', value: 'contactsShowPhone', short: 'Телефон' },
    { title: 'Написали в чат', value: 'contactsMessenger', short: 'Чаты' },
    { title: 'Телефон + чат', value: 'contactsShowPhoneAndMessenger', short: 'Тел.+чат' },
    { title: 'Отклик на скидку', value: 'contactsSbcDiscount', short: 'Скидки' },
    { title: 'Конверсия просмотр → контакт', value: 'viewsToContactsConversion', short: 'CR', kind: 'percent' },
    { title: 'Добавили в избранное', value: 'favorites', short: 'Избр.', icon: 'mdi-heart-outline' },
    { title: 'Средняя цена просмотра', value: 'averageViewCost', short: 'C/V', kind: 'kopecks' },
    { title: 'Средняя цена контакта', value: 'averageContactCost', short: 'C/C', kind: 'kopecks' },
    { title: 'Показы', value: 'impressions', short: 'Показы' },
    { title: 'Конверсия показ → просмотр', value: 'impressionsToViewsConversion', short: 'CTR', kind: 'percent' },
    { title: 'Целевые просмотры', value: 'clickPackages', short: 'Цел. просм.' },
    { title: 'Отклики на вакансии', value: 'jobContacts', short: 'Отклики' },
    { title: 'Конверсия просмотр → заказ', value: 'viewsToOrderedItemsConversion', short: 'Заказ CR', kind: 'percent' },
    { title: 'Заказано товаров', value: 'orderedItems', short: 'Заказы' },
    { title: 'Стоимость заказанных товаров', value: 'orderedItemsPrice', short: 'Сумма заказов', kind: 'kopecks' },
    { title: 'Доставлено товаров', value: 'deliveredItems', short: 'Доставлено' },
    { title: 'Стоимость доставленных товаров', value: 'deliveredItemsPrice', short: 'Сумма доставок', kind: 'kopecks' },
    { title: 'Заявки на бронирование', value: 'bookingPlacedCount', short: 'Брони' },
    { title: 'Стоимость заявок', value: 'bookingPlacedPrice', short: 'Сумма броней', kind: 'kopecks' },
    { title: 'Подтверждённые заявки', value: 'bookingApprovedCount', short: 'Подтв. брони' },
    { title: 'Стоимость подтверждённых', value: 'bookingApprovedPrice', short: 'Подтв. сумма', kind: 'kopecks' },
    { title: 'Заявки с заселением', value: 'bookingAcceptedCount', short: 'Заселения' },
    { title: 'Стоимость заселений', value: 'bookingAcceptedPrice', short: 'Сумма засел.', kind: 'kopecks' },
    { title: 'Все расходы', value: 'allSpending', short: 'Расходы', kind: 'kopecks' },
    { title: 'Расходы деньгами', value: 'spending', short: 'Деньгами', kind: 'kopecks' },
    { title: 'Размещение и действия', value: 'presenceSpending', short: 'Размещение', kind: 'kopecks' },
    { title: 'Продвижение', value: 'promoSpending', short: 'Промо', kind: 'kopecks' },
    { title: 'Остальные расходы', value: 'restSpending', short: 'Прочее', kind: 'kopecks' },
    { title: 'Комиссия', value: 'commission', short: 'Комиссия', kind: 'kopecks' },
    { title: 'Списано бонусов', value: 'spendingBonus', short: 'Бонусы' },
    { title: 'Активные объявления', value: 'activeItems', short: 'Активные' },
    { title: 'Новые активные', value: 'newActiveItems', short: 'Новые' },
    { title: 'Активны с прошлого периода', value: 'oldActiveItems', short: 'Старые' },
]

const SERVICE_LABELS = {
    highlight: 'Выделение цветом',
    xl: 'XL-объявление',
    x2_1: '×2 просмотров · 1 день',
    x2_7: '×2 просмотров · 7 дней',
    x5_1: '×5 просмотров · 1 день',
    x5_7: '×5 просмотров · 7 дней',
    x10_1: '×10 просмотров · 1 день',
    x10_7: '×10 просмотров · 7 дней',
    x15_1: '×15 просмотров · 1 день',
    x15_7: '×15 просмотров · 7 дней',
    x20_1: '×20 просмотров · 1 день',
    x20_7: '×20 просмотров · 7 дней',
}

const loading = ref(false)
const contextLoading = ref(false)
const detailLoading = ref(false)
const analyticsLoading = ref(false)
const promotionsLoading = ref(false)
const actionLoading = ref(false)
const inlineError = ref('')
const statsWarning = ref('')
const accountProfile = ref(null)
const accountId = ref(null)
const agencyMode = ref(false)
const authConnectionId = ref(null)
const items = ref([])
const listMeta = ref({})
const listRemote = ref(null)
const pageStatsRaw = ref(null)
const statsByItem = ref({})
const selectedItemId = ref(null)
const selectedIds = ref([])
const detail = ref(null)
const detailRaw = ref(null)
const detailRemote = ref(null)
const detailTab = ref('card')
const itemTrendRaw = ref(null)
const spendingRaw = ref(null)
const promotionInsights = ref(null)
const bulkPromotionInsights = ref(null)
const confirmed = ref(false)
const actionResult = ref(null)
const advancedOpen = ref(false)

const filters = reactive({
    search: '',
    statuses: [],
    category: null,
    updated_from: '',
})
const page = ref(1)
const perPage = ref(50)
const dateFrom = ref(toIsoDate(daysAgo(29)))
const dateTo = ref(toIsoDate(new Date()))
const selectedMetrics = ref([
    'views', 'contacts', 'favorites', 'viewsToContactsConversion',
    'impressions', 'impressionsToViewsConversion', 'allSpending', 'promoSpending',
])
const trendGrouping = ref('day')

const actionForm = reactive({
    price: null,
    serviceSlug: null,
    combinedSlugs: [],
    stickerIds: '',
    duration: 7,
    oldPrice: 0,
    promoPrice: 0,
})

const accountOptions = computed(() => {
    const options = props.connections
        .filter((connection) => connection.external_user_id)
        .map((connection) => ({
            title: `${connection.name} · ${connection.external_user_id}`,
            value: Number(connection.external_user_id),
        }))

    if (accountProfile.value?.id && !options.some((item) => item.value === Number(accountProfile.value.id))) {
        options.unshift({
            title: `${accountProfile.value.name || 'Текущий профиль'} · ${accountProfile.value.id}`,
            value: Number(accountProfile.value.id),
        })
    }

    return options
})

const authOptions = computed(() => [
    { title: 'Серверные ключи', value: null },
    ...props.connections.filter((item) => item.is_active).map((item) => ({
        title: `OAuth · ${item.name}`,
        value: item.id,
    })),
])

const visibleItems = computed(() => {
    const needle = String(filters.search || '').trim().toLocaleLowerCase('ru')
    if (!needle) return items.value

    return items.value.filter((item) => [
        item.id,
        item.title,
        item.address,
        item.category?.name,
        item.category?.id,
        item.status,
    ].join(' ').toLocaleLowerCase('ru').includes(needle))
})

const selectedItem = computed(() => items.value.find((item) => String(item.id) === String(selectedItemId.value))
    || (selectedItemId.value ? { id: selectedItemId.value, title: `Объявление ${selectedItemId.value}` } : null))
const detailItem = computed(() => ({ ...(selectedItem.value || {}), ...(detail.value || {}) }))
const allVisibleSelected = computed(() => visibleItems.value.length > 0
    && visibleItems.value.every((item) => selectedIds.value.includes(String(item.id))))
const selectedRows = computed(() => items.value.filter((item) => selectedIds.value.includes(String(item.id))))
const lastPage = computed(() => Number(listMeta.value.last_page || listMeta.value.pages || listMeta.value.total_pages || 0))
const canGoNext = computed(() => lastPage.value > 0 ? page.value < lastPage.value : items.value.length >= perPage.value)
const canMutate = computed(() => props.enabled && props.mutationsEnabled && confirmed.value && !actionLoading.value)

const statusCounts = computed(() => Object.fromEntries(STATUS_OPTIONS.map((status) => [
    status.value,
    items.value.filter((item) => item.status === status.value).length,
])))

const summary = computed(() => ({
    shown: visibleItems.value.length,
    active: items.value.filter((item) => item.status === 'active').length,
    views: sumMetric(items.value, 'views'),
    contacts: sumMetric(items.value, 'contacts'),
    favorites: sumMetric(items.value, 'favorites'),
    spending: sumMetric(items.value, 'allSpending'),
}))

const selectionSummary = computed(() => ({
    views: sumMetric(selectedRows.value, 'views'),
    contacts: sumMetric(selectedRows.value, 'contacts'),
    favorites: sumMetric(selectedRows.value, 'favorites'),
    spending: sumMetric(selectedRows.value, 'allSpending'),
}))

const trendRows = computed(() => {
    const candidates = collectObjects(itemTrendRaw.value)
        .filter((row) => row.date && ['uniqViews', 'uniqContacts', 'uniqFavorites'].some((key) => row[key] !== undefined))
    const unique = new Map(candidates.map((row) => [String(row.date), row]))

    return [...unique.values()].sort((a, b) => String(a.date).localeCompare(String(b.date)))
})

const maxTrendViews = computed(() => Math.max(1, ...trendRows.value.map((row) => Number(row.uniqViews || 0))))
const trendTotals = computed(() => ({
    views: sum(trendRows.value.map((item) => item.uniqViews)),
    contacts: sum(trendRows.value.map((item) => item.uniqContacts)),
    favorites: sum(trendRows.value.map((item) => item.uniqFavorites)),
}))

const spendingGroups = computed(() => collectObjects(spendingRaw.value)
    .filter((row) => row.date && Array.isArray(row.spendings))
    .sort((a, b) => String(a.date).localeCompare(String(b.date))))
const spendingTotals = computed(() => {
    if (!spendingRaw.value) return { all: null, promotion: null, presence: null, commission: null, rest: null }
    const result = { all: 0, promotion: 0, presence: 0, commission: 0, rest: 0 }
    spendingGroups.value.forEach((group) => {
        group.spendings.forEach((entry) => {
            const slug = entry.slug || 'all'
            const value = Number(entry.value || 0)
            result[slug] = (result[slug] || 0) + value
            result.all += slug === 'all' ? 0 : value
        })
    })
    return result
})

const availableServices = computed(() => normalizeServices(promotionInsights.value?.available_services?.data, true))
const activeServices = computed(() => normalizeServices(promotionInsights.value?.active_services?.data, false))
const serviceOptions = computed(() => {
    const slugs = new Set([...Object.keys(SERVICE_LABELS), ...availableServices.value.map((item) => item.slug)])
    return [...slugs].map((slug) => {
        const price = availableServices.value.find((item) => item.slug === slug)?.price
        return {
            title: `${serviceLabel(slug)}${price != null ? ` · ${formatMoney(price)}` : ''}`,
            value: slug,
        }
    })
})
const bbipSuggestions = computed(() => collectObjects(promotionInsights.value?.bbip_suggestions?.data)
    .filter((row) => row.duration !== undefined && row.price !== undefined)
    .filter((row, index, list) => index === list.findIndex((item) => `${item.duration}:${item.price}:${item.oldPrice}` === `${row.duration}:${row.price}:${row.oldPrice}`)))
const partialPromotionErrors = computed(() => Object.entries(promotionInsights.value || {})
    .filter(([, section]) => section?.ok === false)
    .map(([key, section]) => `${promotionSectionLabel(key)}: ${section.message}`))

watch(detailTab, (value) => {
    if (value === 'analytics' && selectedItemId.value && !itemTrendRaw.value) loadAnalytics()
    if (value === 'promotion' && selectedItemId.value && !promotionInsights.value) loadPromotions()
})

watch(authConnectionId, (connectionId) => {
    const connection = props.connections.find((item) => item.id === connectionId)
    if (connection?.external_user_id) accountId.value = Number(connection.external_user_id)
    promotionInsights.value = null
})

watch(accountId, syncAccountMode)

onMounted(initialize)

async function initialize() {
    const knownConnection = props.connections.find((item) => item.is_active && item.external_user_id)
    if (knownConnection) accountId.value = Number(knownConnection.external_user_id)

    if (props.configured) {
        contextLoading.value = true
        try {
            const { data } = await axios.get('/api/avito/listings/context')
            accountProfile.value = data.account || null
            if (!accountId.value && data.account?.id) accountId.value = Number(data.account.id)
            syncAccountMode()
        } catch (exception) {
            if (!accountId.value) inlineError.value = errorMessage(exception, 'Не удалось определить ID кабинета Avito.')
        } finally {
            contextLoading.value = false
        }
    }

    if (accountId.value) await loadListings(true)
}

async function loadListings(resetPage = false) {
    const resolvedAccountId = positiveInteger(accountId.value)
    if (!resolvedAccountId) {
        inlineError.value = 'Укажите числовой ID кабинета Avito.'
        return
    }
    if (resetPage) page.value = 1

    loading.value = true
    inlineError.value = ''
    statsWarning.value = ''
    const useAgencyMode = agencyMode.value
    const listRequest = axios.get('/api/avito/listings', {
        params: {
            account_id: resolvedAccountId,
            agency_mode: useAgencyMode ? 1 : undefined,
            statuses: filters.statuses.length ? filters.statuses : undefined,
            category: positiveInteger(filters.category) || undefined,
            updated_from: filters.updated_from || undefined,
            page: page.value,
            per_page: perPage.value,
        },
    })
    const requests = [listRequest]
    if (useAgencyMode) {
        requests.push(axios.post('/api/avito/listings/statistics', {
            account_id: resolvedAccountId,
            agency_mode: 1,
            date_from: dateFrom.value,
            date_to: dateTo.value,
            grouping: 'totals',
            metrics: selectedMetrics.value,
            category_ids: positiveInteger(filters.category) ? [positiveInteger(filters.category)] : undefined,
            limit: 1000,
            offset: 0,
        }))
    }

    const [listResult, statsResult] = await Promise.allSettled(requests)

    if (listResult.status === 'fulfilled') {
        items.value = Array.isArray(listResult.value.data.items) ? listResult.value.data.items : []
        listMeta.value = listResult.value.data.meta || {}
        listRemote.value = listResult.value.data.remote || null
        selectedIds.value = selectedIds.value.filter((id) => items.value.some((item) => String(item.id) === id))

        if (!items.value.some((item) => String(item.id) === String(selectedItemId.value))) {
            selectedItemId.value = items.value[0]?.id || null
            resetInspector()
        }
        if (selectedItemId.value) loadDetail()
    } else {
        items.value = []
        showError(listResult.reason, 'Не удалось загрузить объявления Avito.')
    }

    if (!useAgencyMode && listResult.status === 'fulfilled') {
        await loadOwnAccountStatistics(resolvedAccountId)
    } else if (statsResult?.status === 'fulfilled') {
        pageStatsRaw.value = statsResult.value.data.statistics || {}
        statsByItem.value = indexStatistics(pageStatsRaw.value)
    } else if (useAgencyMode) {
        pageStatsRaw.value = null
        statsByItem.value = {}
        statsWarning.value = errorMessage(statsResult.reason, 'Статистика за период недоступна.')
    } else {
        pageStatsRaw.value = null
        statsByItem.value = {}
    }

    loading.value = false
}

async function loadOwnAccountStatistics(resolvedAccountId) {
    const itemIds = items.value.map((item) => positiveInteger(item.id)).filter(Boolean)
    if (!itemIds.length) {
        pageStatsRaw.value = null
        statsByItem.value = {}
        return
    }

    try {
        const { data } = await axios.post('/api/avito/listings/statistics/items', {
            account_id: resolvedAccountId,
            item_ids: itemIds,
            date_from: dateFrom.value,
            date_to: dateTo.value,
            fields: ['uniqViews', 'uniqContacts', 'uniqFavorites'],
            grouping: 'day',
        })
        pageStatsRaw.value = data.statistics || {}
        statsByItem.value = indexItemStatistics(pageStatsRaw.value)
    } catch (exception) {
        pageStatsRaw.value = null
        statsByItem.value = {}
        statsWarning.value = errorMessage(exception, 'Базовая статистика за период недоступна.')
    }
}

async function loadDetail() {
    if (!selectedItemId.value || !positiveInteger(accountId.value)) return
    detailLoading.value = true
    detail.value = null
    detailRaw.value = null
    try {
        const { data } = await axios.get(`/api/avito/listings/${encodeURIComponent(selectedItemId.value)}`, {
            params: {
                account_id: positiveInteger(accountId.value),
                agency_mode: agencyMode.value ? 1 : undefined,
            },
        })
        detailRaw.value = data.item || {}
        detail.value = unwrapPayload(data.item)
        detailRemote.value = data.remote || null
        actionForm.price = detailItem.value.price ?? selectedItem.value?.price ?? null
    } catch (exception) {
        showError(exception, 'Не удалось загрузить карточку объявления.')
    } finally {
        detailLoading.value = false
    }
}

async function loadAnalytics() {
    if (!selectedItemId.value || !positiveInteger(accountId.value)) return
    analyticsLoading.value = true
    const payload = {
        account_id: positiveInteger(accountId.value),
        item_ids: [positiveInteger(selectedItemId.value)],
        date_from: dateFrom.value,
        date_to: dateTo.value,
    }
    const requests = [axios.post('/api/avito/listings/statistics/items', {
            ...payload,
            fields: ['uniqViews', 'uniqContacts', 'uniqFavorites'],
            grouping: trendGrouping.value,
        })]
    if (agencyMode.value) {
        requests.push(axios.post('/api/avito/listings/spendings', {
            ...payload,
            agency_mode: 1,
            spending_types: ['all'],
            grouping: trendGrouping.value === 'day' ? 'day' : trendGrouping.value,
        }))
    }
    const [trendResult, spendingResult] = await Promise.allSettled(requests)
    itemTrendRaw.value = trendResult.status === 'fulfilled' ? trendResult.value.data.statistics : null
    spendingRaw.value = spendingResult?.status === 'fulfilled' ? spendingResult.value.data.spendings : null

    const failures = [trendResult, spendingResult].filter((item) => item?.status === 'rejected')
    if (failures.length === requests.length) showError(failures[0].reason, 'Аналитика объявления недоступна.')
    else if (failures.length) statsWarning.value = errorMessage(failures[0].reason, 'Часть аналитики недоступна.')
    analyticsLoading.value = false
}

async function loadPromotions(itemIds = null, bulk = false) {
    const ids = (itemIds || [selectedItemId.value]).map(positiveInteger).filter(Boolean)
    if (!ids.length || !positiveInteger(accountId.value)) return
    promotionsLoading.value = true
    try {
        const { data } = await axios.post('/api/avito/listings/promotions', {
            account_id: positiveInteger(accountId.value),
            item_ids: ids,
            connection_id: authConnectionId.value || undefined,
        })
        if (bulk) {
            bulkPromotionInsights.value = data
            emit('notice', `Данные продвижения загружены для ${ids.length} объявлений.`)
        } else {
            promotionInsights.value = data
        }
    } catch (exception) {
        showError(exception, 'Не удалось загрузить данные продвижения.')
    } finally {
        promotionsLoading.value = false
    }
}

async function performAction(action, payload = {}) {
    if (!canMutate.value || !selectedItemId.value) return
    actionLoading.value = true
    actionResult.value = null
    try {
        const { data } = await axios.post(`/api/avito/listings/${encodeURIComponent(selectedItemId.value)}/action`, {
            account_id: positiveInteger(accountId.value),
            connection_id: authConnectionId.value || undefined,
            action,
            confirmed: true,
            ...payload,
        })
        actionResult.value = data
        confirmed.value = false
        emit('notice', actionSuccessMessage(action))

        if (action === 'update_price') await loadListings(false)
        else {
            promotionInsights.value = null
            await loadPromotions()
            await loadDetail()
        }
    } catch (exception) {
        showError(exception, 'Avito не выполнил изменение объявления.')
    } finally {
        actionLoading.value = false
    }
}

function selectItem(item) {
    if (String(selectedItemId.value) === String(item.id)) return
    selectedItemId.value = item.id
    resetInspector()
    loadDetail()
    if (detailTab.value === 'analytics') loadAnalytics()
    if (detailTab.value === 'promotion') loadPromotions()
}

function resetInspector() {
    detail.value = null
    detailRaw.value = null
    detailRemote.value = null
    itemTrendRaw.value = null
    spendingRaw.value = null
    promotionInsights.value = null
    actionResult.value = null
    confirmed.value = false
}

function handleSearchEnter() {
    const id = positiveInteger(filters.search)
    if (!id) return
    const existing = items.value.find((item) => Number(item.id) === id)
    selectItem(existing || { id, title: `Объявление ${id}` })
}

function previousPage() {
    if (page.value <= 1 || loading.value) return
    page.value--
    loadListings(false)
}

function nextPage() {
    if (!canGoNext.value || loading.value) return
    page.value++
    loadListings(false)
}

function changePerPage() {
    page.value = 1
    loadListings(false)
}

function setStatus(status) {
    filters.statuses = filters.statuses.length === 1 && filters.statuses[0] === status ? [] : [status]
    loadListings(true)
}

function toggleVisibleSelection() {
    const ids = visibleItems.value.map((item) => String(item.id))
    if (allVisibleSelected.value) selectedIds.value = selectedIds.value.filter((id) => !ids.includes(id))
    else selectedIds.value = [...new Set([...selectedIds.value, ...ids])]
}

function toggleRow(id) {
    const value = String(id)
    selectedIds.value = selectedIds.value.includes(value)
        ? selectedIds.value.filter((item) => item !== value)
        : [...selectedIds.value, value]
}

function metricValue(item, key) {
    const metrics = statsByItem.value[String(item?.id)]
    const value = metrics?.[key]
    if (value !== null && value !== undefined) return value
    if (key === 'viewsToContactsConversion' && Number(metrics?.views) > 0) {
        return Number(metrics?.contacts || 0) / Number(metrics.views) * 100
    }
    return null
}

function sumMetric(rows, key) {
    const values = rows.map((item) => metricValue(item, key)).filter((value) => value !== null && value !== undefined)
    return values.length ? sum(values) : null
}

function indexStatistics(payload) {
    const groupings = firstArrayAt(payload, [
        ['result', 'groupings'], ['groupings'], ['data', 'groupings'], ['result'],
    ])
    const result = {}
    groupings.forEach((group) => {
        const id = group.id ?? group.itemId ?? group.item_id
        if (id == null || !Array.isArray(group.metrics)) return
        result[String(id)] = Object.fromEntries(group.metrics.map((metric) => [
            metric.slug ?? metric.key ?? metric.name,
            metric.value,
        ]))
    })
    return result
}

function indexItemStatistics(payload) {
    const rows = firstArrayAt(payload, [
        ['result'], ['result', 'items'], ['data', 'result'], ['items'],
    ])
    const result = {}
    rows.forEach((row) => {
        const id = row.itemId ?? row.item_id ?? row.id
        if (id == null || !Array.isArray(row.stats)) return
        const views = sum(row.stats.map((item) => item.uniqViews))
        const contacts = sum(row.stats.map((item) => item.uniqContacts))
        const favorites = sum(row.stats.map((item) => item.uniqFavorites))
        result[String(id)] = {
            views,
            contacts,
            favorites,
            viewsToContactsConversion: views > 0 ? contacts / views * 100 : 0,
        }
    })
    return result
}

function syncAccountMode() {
    const profileId = positiveInteger(accountProfile.value?.id)
    const selectedAccountId = positiveInteger(accountId.value)
    if (profileId && selectedAccountId) agencyMode.value = profileId !== selectedAccountId
}

function normalizeServices(payload, requirePrice) {
    const rows = collectObjects(payload)
        .filter((row) => (row.slug || row.vas_id || row.vasId)
            && (!requirePrice || row.price !== undefined || row.priceOld !== undefined || row.oldPrice !== undefined))
        .map((row) => ({
            ...row,
            slug: row.slug || row.vas_id || row.vasId,
            price: row.price ?? null,
            oldPrice: row.priceOld ?? row.oldPrice ?? null,
            finishTime: row.finish_time ?? row.finishTime ?? null,
        }))
    return rows.filter((row, index, list) => index === list.findIndex((item) => item.slug === row.slug))
}

function collectObjects(value, result = []) {
    if (Array.isArray(value)) {
        value.forEach((item) => collectObjects(item, result))
    } else if (value && typeof value === 'object') {
        result.push(value)
        Object.values(value).forEach((item) => collectObjects(item, result))
    }
    return result
}

function firstArrayAt(value, paths) {
    for (const path of paths) {
        let current = value
        for (const segment of path) current = current?.[segment]
        if (Array.isArray(current)) return current
    }
    return []
}

function unwrapPayload(payload) {
    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) return {}
    for (const key of ['result', 'resource', 'item', 'data']) {
        if (payload[key] && typeof payload[key] === 'object' && !Array.isArray(payload[key])) return payload[key]
    }
    return payload
}

function applySelectedService() {
    const slug = actionForm.serviceSlug
    if (!slug) return
    if (['highlight', 'xl'].includes(slug)) performAction('apply_vas', { slug })
    else if (/^x(?:2|5|10|15|20)_(?:1|7)$/.test(slug)) performAction('apply_package', { package_id: slug })
    else performAction('apply_services', { slugs: [slug] })
}

function applyCombinedServices() {
    const stickers = String(actionForm.stickerIds || '').split(',').map(positiveInteger).filter(Boolean)
    performAction('apply_services', {
        slugs: actionForm.combinedSlugs,
        stickers: stickers.length ? stickers : undefined,
    })
}

function useBbipSuggestion(suggestion) {
    actionForm.duration = Number(suggestion.duration || 7)
    actionForm.oldPrice = Number(suggestion.oldPrice ?? suggestion.priceOld ?? suggestion.price ?? 0)
    actionForm.promoPrice = Number(suggestion.price || 0)
}

function exportCsv(selectedOnly = false) {
    const rows = selectedOnly && selectedRows.value.length ? selectedRows.value : visibleItems.value
    const headers = ['ID', 'Название', 'Статус', 'Цена, ₽', 'Категория', 'Адрес', 'Просмотры', 'Контакты', 'Избранное', 'Конверсия, %', 'Расходы, коп.', 'URL']
    const data = rows.map((item) => [
        item.id,
        item.title,
        statusLabel(item.status),
        item.price,
        item.category?.name,
        item.address,
        metricValue(item, 'views'),
        metricValue(item, 'contacts'),
        metricValue(item, 'favorites'),
        metricValue(item, 'viewsToContactsConversion'),
        metricValue(item, 'allSpending'),
        item.url,
    ])
    const csv = `\uFEFF${[headers, ...data].map((row) => row.map(csvCell).join(';')).join('\n')}`
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
    link.download = `avito-items-${toIsoDate(new Date())}.csv`
    link.click()
    URL.revokeObjectURL(link.href)
}

async function copyText(value) {
    try {
        await navigator.clipboard.writeText(String(value))
        emit('notice', 'Скопировано.')
    } catch {
        emit('error', 'Браузер не разрешил копирование.')
    }
}

function showError(exception, fallback) {
    inlineError.value = errorMessage(exception, fallback)
    emit('error', inlineError.value)
}

function errorMessage(exception, fallback) {
    const errors = exception?.response?.data?.errors
    if (errors && typeof errors === 'object') return Object.values(errors).flat()[0] || fallback
    return exception?.response?.data?.message || fallback
}

function statusLabel(status) {
    return STATUS_OPTIONS.find((item) => item.value === status)?.title || status || 'Неизвестно'
}

function statusColor(status) {
    return STATUS_OPTIONS.find((item) => item.value === status)?.color || 'grey'
}

function serviceLabel(slug) {
    return SERVICE_LABELS[slug] || slug || 'Услуга'
}

function promotionSectionLabel(key) {
    return ({ active_services: 'Активные услуги', available_services: 'Стоимость услуг', cpx: 'CPX', bbip_suggestions: 'BBIP' })[key] || key
}

function actionSuccessMessage(action) {
    return ({
        update_price: 'Цена объявления обновлена.',
        apply_vas: 'Дополнительная услуга подключена.',
        apply_package: 'Пакет продвижения подключён.',
        apply_services: 'Набор услуг продвижения применён.',
        stop_cpx: 'CPX-продвижение остановлено.',
        create_bbip: 'Заявка BBIP создана.',
    })[action] || 'Изменение отправлено в Avito.'
}

function metricLabel(key) {
    return METRIC_OPTIONS.find((item) => item.value === key)?.short || key
}

function formatMetric(value, key) {
    if (value === null || value === undefined || value === '') return '—'
    const option = METRIC_OPTIONS.find((item) => item.value === key)
    if (option?.kind === 'percent') return `${formatNumber(value, 2)}%`
    if (option?.kind === 'kopecks') return formatMoney(Number(value) / 100)
    return formatNumber(value)
}

function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '—'
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 2 }).format(Number(value))
}

function formatNumber(value, digits = 0) {
    if (value === null || value === undefined || value === '') return '—'
    return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: digits }).format(Number(value || 0))
}

function formatDate(value, withTime = false) {
    if (!value) return '—'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return String(value)
    return new Intl.DateTimeFormat('ru-RU', withTime
        ? { dateStyle: 'short', timeStyle: 'short' }
        : { dateStyle: 'short' }).format(date)
}

function prettyJson(value) {
    return JSON.stringify(value ?? {}, null, 2)
}

function positiveInteger(value) {
    const parsed = Number.parseInt(value, 10)
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null
}

function sum(values) {
    return values.reduce((total, value) => total + (Number(value) || 0), 0)
}

function csvCell(value) {
    return `"${String(value ?? '').replaceAll('"', '""')}"`
}

function daysAgo(days) {
    const date = new Date()
    date.setDate(date.getDate() - days)
    return date
}

function toIsoDate(date) {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
    return local.toISOString().slice(0, 10)
}
</script>

<template>
    <section class="listings-workspace">
        <div class="listings-toolbar">
            <v-combobox
                v-model="accountId"
                :items="accountOptions"
                item-title="title"
                item-value="value"
                :return-object="false"
                label="ID кабинета"
                prepend-inner-icon="mdi-account-box-outline"
                variant="outlined"
                density="compact"
                hide-details
                :loading="contextLoading"
            />
            <v-select
                v-model="authConnectionId"
                :items="authOptions"
                label="Авторизация действий"
                variant="outlined"
                density="compact"
                hide-details
            />
            <v-text-field
                v-model="filters.search"
                label="Название, адрес или ID"
                prepend-inner-icon="mdi-magnify"
                hint="Enter по ID откроет карточку"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @keyup.enter="handleSearchEnter"
            />
            <v-select
                v-model="filters.statuses"
                :items="STATUS_OPTIONS"
                label="Статусы"
                multiple
                clearable
                variant="outlined"
                density="compact"
                hide-details
            >
                <template #selection="{ index }"><span v-if="index === 0" class="select-summary">{{ filters.statuses.length }} выбрано</span></template>
            </v-select>
            <v-text-field v-model="filters.category" label="Категория ID" type="number" variant="outlined" density="compact" hide-details clearable />
            <v-text-field v-model="filters.updated_from" label="Обновлены с" type="date" variant="outlined" density="compact" hide-details clearable />
            <div class="toolbar-actions">
                <v-btn icon="mdi-tune-variant" size="small" variant="tonal" :color="advancedOpen ? 'deep-purple-lighten-1' : undefined" title="Период и метрики" @click="advancedOpen = !advancedOpen" />
                <v-btn icon="mdi-refresh" size="small" color="deep-purple-lighten-1" :loading="loading" title="Загрузить" @click="loadListings(true)" />
            </div>
        </div>

        <div v-if="advancedOpen" class="advanced-strip">
            <span class="strip-label">Период статистики</span>
            <v-text-field v-model="dateFrom" label="С" type="date" variant="outlined" density="compact" hide-details />
            <v-text-field v-model="dateTo" label="По" type="date" variant="outlined" density="compact" hide-details />
            <v-select
                v-model="selectedMetrics"
                :items="METRIC_OPTIONS"
                label="Метрики таблицы"
                multiple
                variant="outlined"
                density="compact"
                hide-details
            >
                <template #selection="{ index }"><span v-if="index === 0" class="select-summary">{{ selectedMetrics.length }} метрик</span></template>
            </v-select>
            <v-switch
                v-model="agencyMode"
                label="Агентский клиент"
                color="deep-purple-lighten-1"
                density="compact"
                hide-details
                inset
                title="Включайте только для кабинета клиента агентства"
            />
            <v-btn size="small" variant="tonal" prepend-icon="mdi-check" :disabled="!selectedMetrics.length" @click="loadListings(false)">Применить</v-btn>
            <small>{{ agencyMode ? 'Полная статистика клиента агентства' : 'Свой кабинет · базовая статистика до 270 дней' }}</small>
        </div>

        <v-alert v-if="!configured" type="warning" variant="tonal" density="compact" class="compact-alert">
            Серверные ключи Avito не настроены. Список объявлений требует Client Credentials; OAuth можно использовать для отдельных действий.
        </v-alert>
        <v-alert v-if="inlineError" type="error" variant="tonal" density="compact" closable class="compact-alert" @click:close="inlineError = ''">{{ inlineError }}</v-alert>
        <v-alert v-if="statsWarning" type="warning" variant="tonal" density="compact" closable class="compact-alert" @click:close="statsWarning = ''">{{ statsWarning }}</v-alert>

        <div class="listings-kpis">
            <button type="button" :class="{ active: !filters.statuses.length }" @click="filters.statuses = []; loadListings(true)">
                <span>На странице</span><strong>{{ summary.shown }}</strong><small>стр. {{ page }}</small>
            </button>
            <button type="button" :class="{ active: filters.statuses[0] === 'active' }" @click="setStatus('active')">
                <span>Активные</span><strong>{{ summary.active }}</strong><small>{{ statusCounts.active || 0 }} загружено</small>
            </button>
            <article><span>Просмотры</span><strong>{{ formatNumber(summary.views) }}</strong><small>{{ formatDate(dateFrom) }}—{{ formatDate(dateTo) }}</small></article>
            <article><span>Контакты</span><strong>{{ formatNumber(summary.contacts) }}</strong><small>{{ summary.views ? `${formatNumber(summary.contacts / summary.views * 100, 1)}%` : 'конверсия —' }}</small></article>
            <article><span>Избранное</span><strong>{{ formatNumber(summary.favorites) }}</strong><small>по загруженным ID</small></article>
            <article><span>Расходы</span><strong>{{ formatMoney(summary.spending == null ? null : summary.spending / 100) }}</strong><small>{{ agencyMode ? 'включая продвижение' : 'агентская метрика' }}</small></article>
        </div>

        <div v-if="selectedIds.length" class="bulk-strip">
            <strong>{{ selectedIds.length }} выбрано</strong>
            <span><v-icon icon="mdi-eye-outline" size="14" /> {{ formatNumber(selectionSummary.views) }}</span>
            <span><v-icon icon="mdi-account-arrow-right-outline" size="14" /> {{ formatNumber(selectionSummary.contacts) }}</span>
            <span><v-icon icon="mdi-heart-outline" size="14" /> {{ formatNumber(selectionSummary.favorites) }}</span>
            <span><v-icon icon="mdi-cash-minus" size="14" /> {{ formatMoney(selectionSummary.spending == null ? null : selectionSummary.spending / 100) }}</span>
            <v-spacer />
            <small v-if="bulkPromotionInsights">Данные продвижения получены</small>
            <v-btn size="x-small" variant="text" prepend-icon="mdi-rocket-launch-outline" :loading="promotionsLoading" @click="loadPromotions(selectedIds, true)">Продвижение</v-btn>
            <v-btn size="x-small" variant="text" prepend-icon="mdi-file-delimited-outline" @click="exportCsv(true)">CSV</v-btn>
            <v-btn icon="mdi-close" size="x-small" variant="text" @click="selectedIds = []; bulkPromotionInsights = null" />
        </div>

        <div class="listings-layout">
            <section class="list-panel">
                <div class="table-shell">
                    <table class="listings-table">
                        <thead>
                            <tr>
                                <th class="check-cell"><v-checkbox-btn :model-value="allVisibleSelected" density="compact" @click.stop="toggleVisibleSelection" /></th>
                                <th class="item-cell">Объявление</th>
                                <th>Статус</th>
                                <th class="number-cell">Цена</th>
                                <th class="metric-cell">Просм.</th>
                                <th class="metric-cell">Конт.</th>
                                <th class="metric-cell">Избр.</th>
                                <th class="metric-cell">CR</th>
                                <th class="metric-cell">Промо</th>
                                <th class="row-actions-cell"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in visibleItems"
                                :key="item.id"
                                :class="{ selected: String(item.id) === String(selectedItemId) }"
                                @click="selectItem(item)"
                            >
                                <td class="check-cell"><v-checkbox-btn :model-value="selectedIds.includes(String(item.id))" density="compact" @click.stop="toggleRow(item.id)" /></td>
                                <td class="item-cell">
                                    <strong>{{ item.title || `Объявление ${item.id}` }}</strong>
                                    <small><code>#{{ item.id }}</code> · {{ item.category?.name || 'Категория не указана' }}</small>
                                    <small>{{ item.address || 'Адрес не указан' }}</small>
                                </td>
                                <td><v-chip :color="statusColor(item.status)" size="x-small" variant="tonal">{{ statusLabel(item.status) }}</v-chip></td>
                                <td class="number-cell"><strong>{{ formatMoney(item.price) }}</strong></td>
                                <td class="metric-cell">{{ formatMetric(metricValue(item, 'views'), 'views') }}</td>
                                <td class="metric-cell">{{ formatMetric(metricValue(item, 'contacts'), 'contacts') }}</td>
                                <td class="metric-cell">{{ formatMetric(metricValue(item, 'favorites'), 'favorites') }}</td>
                                <td class="metric-cell">{{ formatMetric(metricValue(item, 'viewsToContactsConversion'), 'viewsToContactsConversion') }}</td>
                                <td class="metric-cell">{{ formatMetric(metricValue(item, 'promoSpending'), 'promoSpending') }}</td>
                                <td class="row-actions-cell">
                                    <v-btn v-if="item.url" :href="item.url" target="_blank" rel="noopener noreferrer" icon="mdi-open-in-new" size="x-small" variant="text" @click.stop />
                                    <v-btn icon="mdi-content-copy" size="x-small" variant="text" @click.stop="copyText(item.id)" />
                                </td>
                            </tr>
                            <tr v-if="loading"><td colspan="10" class="table-state"><v-progress-circular indeterminate size="28" color="deep-purple-lighten-2" /><span>Объявления и статистика…</span></td></tr>
                            <tr v-else-if="!visibleItems.length"><td colspan="10" class="table-state"><v-icon icon="mdi-view-grid-outline" size="36" /><strong>Объявлений нет</strong><span>Измените фильтры или проверьте ID кабинета.</span></td></tr>
                        </tbody>
                    </table>
                </div>

                <footer class="list-footer">
                    <span>Avito: {{ listMeta.page || page }} · {{ items.length }} записей <small v-if="listRemote">· {{ listRemote.duration_ms }} ms</small></span>
                    <v-btn size="x-small" variant="text" prepend-icon="mdi-file-delimited-outline" @click="exportCsv(false)">CSV</v-btn>
                    <v-select v-model="perPage" :items="[25, 50, 100]" label="Строк" variant="outlined" density="compact" hide-details @update:model-value="changePerPage" />
                    <div class="pager">
                        <v-btn icon="mdi-chevron-left" size="x-small" variant="tonal" :disabled="page <= 1" @click="previousPage" />
                        <strong>{{ page }}<template v-if="lastPage"> / {{ lastPage }}</template></strong>
                        <v-btn icon="mdi-chevron-right" size="x-small" variant="tonal" :disabled="!canGoNext" @click="nextPage" />
                    </div>
                </footer>
            </section>

            <aside class="inspector">
                <div v-if="!selectedItem" class="inspector-empty">
                    <v-icon icon="mdi-cursor-default-click-outline" size="42" />
                    <strong>Выберите объявление</strong>
                    <span>Карточка, аналитика и продвижение откроются здесь.</span>
                </div>

                <template v-else>
                    <header class="inspector-head">
                        <div>
                            <small>#{{ selectedItem.id }}</small>
                            <h2>{{ detailItem.title || `Объявление ${selectedItem.id}` }}</h2>
                            <span>{{ detailItem.category?.name || detailItem.address || 'Данные объявления' }}</span>
                        </div>
                        <div class="inspector-head__actions">
                            <v-btn v-if="detailItem.url" :href="detailItem.url" target="_blank" rel="noopener noreferrer" icon="mdi-open-in-new" size="x-small" variant="tonal" />
                            <v-btn icon="mdi-refresh" size="x-small" variant="text" :loading="detailLoading" @click="loadDetail" />
                        </div>
                    </header>

                    <v-tabs v-model="detailTab" class="inspector-tabs" density="compact" color="deep-purple-lighten-1" grow>
                        <v-tab value="card">Карточка</v-tab>
                        <v-tab value="analytics">Аналитика</v-tab>
                        <v-tab value="promotion">Промо</v-tab>
                        <v-tab value="raw">API</v-tab>
                    </v-tabs>

                    <div v-if="detailLoading && !detail" class="inspector-loading"><v-progress-circular indeterminate size="30" /></div>

                    <v-window v-else v-model="detailTab" class="inspector-window">
                        <v-window-item value="card">
                            <div class="inspector-scroll">
                                <div class="card-lead">
                                    <v-chip :color="statusColor(detailItem.status)" size="small" variant="tonal">{{ statusLabel(detailItem.status) }}</v-chip>
                                    <strong>{{ formatMoney(detailItem.price) }}</strong>
                                </div>
                                <dl class="facts-grid">
                                    <dt>ID</dt><dd><code>{{ selectedItem.id }}</code><v-btn icon="mdi-content-copy" size="x-small" variant="text" @click="copyText(selectedItem.id)" /></dd>
                                    <dt>Категория</dt><dd>{{ detailItem.category?.name || '—' }} <small v-if="detailItem.category?.id">#{{ detailItem.category.id }}</small></dd>
                                    <dt>Адрес</dt><dd>{{ detailItem.address || '—' }}</dd>
                                    <dt>Публикация</dt><dd>{{ formatDate(detailItem.start_time || detailItem.startTime, true) }}</dd>
                                    <dt>Завершение</dt><dd>{{ formatDate(detailItem.finish_time || detailItem.finishTime, true) }}</dd>
                                    <dt>Автозагрузка</dt><dd>{{ detailItem.autoload_item_id || detailItem.autoloadItemId || '—' }}</dd>
                                </dl>

                                <section class="compact-section">
                                    <div class="section-title"><strong>Цена объявления</strong><small>рубли</small></div>
                                    <div class="inline-action">
                                        <v-text-field v-model.number="actionForm.price" type="number" min="0" label="Новая цена" variant="outlined" density="compact" hide-details />
                                        <v-btn color="deep-purple-lighten-1" size="small" icon="mdi-content-save-outline" :loading="actionLoading" :disabled="!canMutate || actionForm.price == null" @click="performAction('update_price', { price: Number(actionForm.price) })" />
                                    </div>
                                </section>

                                <section v-if="Array.isArray(detailItem.vas) && detailItem.vas.length" class="compact-section">
                                    <div class="section-title"><strong>Подключённые услуги</strong><small>{{ detailItem.vas.length }}</small></div>
                                    <div class="service-chips">
                                        <v-chip v-for="service in detailItem.vas" :key="service.vas_id || service.slug" size="x-small" color="deep-purple-lighten-1" variant="tonal">
                                            {{ serviceLabel(service.vas_id || service.slug) }} · до {{ formatDate(service.finish_time || service.finishTime) }}
                                        </v-chip>
                                    </div>
                                </section>

                                <v-alert v-if="!mutationsEnabled" type="info" variant="tonal" density="compact" class="inner-alert">Изменения заблокированы серверным флагом AVITO_MUTATIONS_ENABLED.</v-alert>
                                <v-checkbox v-model="confirmed" density="compact" color="warning" hide-details label="Подтверждаю реальное изменение в Avito" />
                                <div v-if="actionResult" class="action-result"><v-icon icon="mdi-check-circle-outline" color="success" /><span>Avito принял изменение · {{ actionResult.remote?.request_id }}</span></div>
                            </div>
                        </v-window-item>

                        <v-window-item value="analytics">
                            <div class="inspector-scroll">
                                <div class="analytics-toolbar">
                                    <v-select v-model="trendGrouping" :items="[{ title: 'Дни', value: 'day' }, { title: 'Недели', value: 'week' }, { title: 'Месяцы', value: 'month' }]" label="Группировка" variant="outlined" density="compact" hide-details />
                                    <v-btn icon="mdi-refresh" size="small" variant="tonal" :loading="analyticsLoading" @click="loadAnalytics" />
                                </div>
                                <div v-if="analyticsLoading" class="inspector-loading"><v-progress-circular indeterminate size="28" /></div>
                                <template v-else>
                                    <div class="mini-kpis">
                                        <article><span>Уник. просмотры</span><strong>{{ formatNumber(trendTotals.views) }}</strong></article>
                                        <article><span>Контакты</span><strong>{{ formatNumber(trendTotals.contacts) }}</strong></article>
                                        <article><span>Избранное</span><strong>{{ formatNumber(trendTotals.favorites) }}</strong></article>
                                        <article><span>Расходы</span><strong>{{ formatMoney(spendingTotals.all) }}</strong></article>
                                    </div>
                                    <section class="compact-section">
                                        <div class="section-title"><strong>Динамика просмотров</strong><small>{{ formatDate(dateFrom) }}—{{ formatDate(dateTo) }}</small></div>
                                        <div v-if="trendRows.length" class="micro-chart">
                                            <div v-for="row in trendRows" :key="row.date" :title="`${row.date}: ${row.uniqViews || 0}`">
                                                <i :style="{ height: `${Math.max(3, Number(row.uniqViews || 0) / maxTrendViews * 100)}%` }"></i>
                                            </div>
                                        </div>
                                        <div v-else class="small-empty">Avito не вернул динамику за период.</div>
                                    </section>
                                    <section v-if="agencyMode" class="compact-section">
                                        <div class="section-title"><strong>Расходы</strong><small>рубли</small></div>
                                        <dl class="spending-list">
                                            <dt>Продвижение</dt><dd>{{ formatMoney(spendingTotals.promotion) }}</dd>
                                            <dt>Размещение / действия</dt><dd>{{ formatMoney(spendingTotals.presence) }}</dd>
                                            <dt>Комиссия</dt><dd>{{ formatMoney(spendingTotals.commission) }}</dd>
                                            <dt>Остальное</dt><dd>{{ formatMoney(spendingTotals.rest) }}</dd>
                                        </dl>
                                    </section>
                                    <section v-else class="compact-section">
                                        <div class="section-title"><strong>Расходы</strong><small>агентская статистика</small></div>
                                        <div class="small-empty">Детализация расходов доступна для кабинетов клиентов агентства.</div>
                                    </section>
                                </template>
                            </div>
                        </v-window-item>

                        <v-window-item value="promotion">
                            <div class="inspector-scroll">
                                <div class="promotion-toolbar">
                                    <span>Активные и доступные услуги, CPX и BBIP</span>
                                    <v-btn icon="mdi-refresh" size="small" variant="tonal" :loading="promotionsLoading" @click="loadPromotions" />
                                </div>
                                <div v-if="promotionsLoading && !promotionInsights" class="inspector-loading"><v-progress-circular indeterminate size="28" /></div>
                                <template v-else>
                                    <v-alert v-for="message in partialPromotionErrors" :key="message" type="warning" variant="tonal" density="compact" class="inner-alert">{{ message }}</v-alert>
                                    <section class="compact-section">
                                        <div class="section-title"><strong>Активные услуги</strong><small>{{ activeServices.length }}</small></div>
                                        <div v-if="activeServices.length" class="service-list">
                                            <div v-for="service in activeServices" :key="service.slug"><span>{{ serviceLabel(service.slug) }}</span><small>{{ service.finishTime ? `до ${formatDate(service.finishTime)}` : 'активна' }}</small></div>
                                        </div>
                                        <div v-else class="small-empty">Активные услуги не найдены.</div>
                                    </section>

                                    <section class="compact-section">
                                        <div class="section-title"><strong>Подключить услугу</strong><small>{{ availableServices.length }} цен</small></div>
                                        <div class="inline-action inline-action--wide">
                                            <v-select v-model="actionForm.serviceSlug" :items="serviceOptions" label="Услуга / пакет" variant="outlined" density="compact" hide-details />
                                            <v-btn icon="mdi-rocket-launch-outline" size="small" color="deep-purple-lighten-1" :disabled="!canMutate || !actionForm.serviceSlug" :loading="actionLoading" @click="applySelectedService" />
                                        </div>
                                        <div v-if="availableServices.length" class="price-list">
                                            <button v-for="service in availableServices" :key="service.slug" type="button" @click="actionForm.serviceSlug = service.slug">
                                                <span>{{ serviceLabel(service.slug) }}</span><strong>{{ formatMoney(service.price) }}</strong><s v-if="service.oldPrice && service.oldPrice !== service.price">{{ formatMoney(service.oldPrice) }}</s>
                                            </button>
                                        </div>
                                    </section>

                                    <section class="compact-section">
                                        <div class="section-title"><strong>Комбинация услуг</strong><small>расширенный режим</small></div>
                                        <v-combobox v-model="actionForm.combinedSlugs" :items="serviceOptions" :return-object="false" label="Slugs услуг" multiple chips closable-chips variant="outlined" density="compact" hide-details />
                                        <div class="inline-action mt-2">
                                            <v-text-field v-model="actionForm.stickerIds" label="ID значков через запятую" variant="outlined" density="compact" hide-details />
                                            <v-btn size="small" variant="tonal" :disabled="!canMutate || !actionForm.combinedSlugs.length" @click="applyCombinedServices">Применить</v-btn>
                                        </div>
                                    </section>

                                    <section class="compact-section">
                                        <div class="section-title"><strong>BBIP · прогнозное продвижение</strong><small>коп./день</small></div>
                                        <div v-if="bbipSuggestions.length" class="suggestions">
                                            <button v-for="(suggestion, index) in bbipSuggestions" :key="index" type="button" @click="useBbipSuggestion(suggestion)">
                                                <strong>{{ suggestion.duration }} дн.</strong><span>{{ formatMoney(Number(suggestion.price || 0) / 100) }}/день</span>
                                            </button>
                                        </div>
                                        <div class="bbip-form">
                                            <v-text-field v-model.number="actionForm.duration" label="Дней" type="number" variant="outlined" density="compact" hide-details />
                                            <v-text-field v-model.number="actionForm.promoPrice" label="Цена, коп." type="number" variant="outlined" density="compact" hide-details />
                                            <v-text-field v-model.number="actionForm.oldPrice" label="Ценность, коп." type="number" variant="outlined" density="compact" hide-details />
                                            <v-btn size="small" color="deep-purple-lighten-1" :disabled="!canMutate" :loading="actionLoading" @click="performAction('create_bbip', { duration: Number(actionForm.duration), promo_price: Number(actionForm.promoPrice), old_price: Number(actionForm.oldPrice) })">Создать заявку</v-btn>
                                        </div>
                                    </section>

                                    <section class="danger-row">
                                        <div><strong>CPX-продвижение</strong><small>Остановить и вернуться к ценам прайс-листа</small></div>
                                        <v-btn size="small" color="error" variant="tonal" :disabled="!canMutate" :loading="actionLoading" @click="performAction('stop_cpx')">Остановить</v-btn>
                                    </section>

                                    <v-checkbox v-model="confirmed" density="compact" color="warning" hide-details label="Подтверждаю реальное изменение в Avito" />
                                    <v-alert v-if="!mutationsEnabled" type="info" variant="tonal" density="compact" class="inner-alert">Подключение услуг отключено серверным флагом.</v-alert>
                                </template>
                            </div>
                        </v-window-item>

                        <v-window-item value="raw">
                            <div class="inspector-scroll raw-tab">
                                <div class="raw-actions">
                                    <v-btn size="x-small" variant="tonal" prepend-icon="mdi-api" @click="emit('open-catalog', 'item')">Все item API</v-btn>
                                    <v-btn size="x-small" variant="text" prepend-icon="mdi-open-in-new" :href="documentationUrl" target="_blank" rel="noopener noreferrer">Документация</v-btn>
                                </div>
                                <details open><summary>Карточка · {{ detailRemote?.request_id || 'без запроса' }}</summary><pre>{{ prettyJson(detailRaw || selectedItem) }}</pre></details>
                                <details><summary>Статистика страницы</summary><pre>{{ prettyJson(pageStatsRaw) }}</pre></details>
                                <details><summary>Динамика объявления</summary><pre>{{ prettyJson(itemTrendRaw) }}</pre></details>
                                <details><summary>Расходы</summary><pre>{{ prettyJson(spendingRaw) }}</pre></details>
                                <details><summary>Продвижение</summary><pre>{{ prettyJson(promotionInsights) }}</pre></details>
                            </div>
                        </v-window-item>
                    </v-window>
                </template>
            </aside>
        </div>
    </section>
</template>

<style scoped>
.listings-workspace { display: grid; gap: 5px; min-width: 0; color: #e9ecff; }
.listings-toolbar { display: grid; grid-template-columns: minmax(155px, .85fr) minmax(170px, .9fr) minmax(220px, 1.35fr) minmax(150px, .8fr) 115px 135px auto; gap: 5px; padding: 6px; border: 1px solid rgba(147, 154, 201, .16); border-radius: 8px; background: #1a1d33; }
.toolbar-actions { display: flex; align-items: center; gap: 4px; }
.select-summary { overflow: hidden; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
.advanced-strip { display: grid; grid-template-columns: auto 145px 145px minmax(240px, 1fr) auto auto minmax(180px, auto); align-items: center; gap: 6px; padding: 5px 8px; color: #aab0ce; font-size: 10px; border: 1px solid rgba(130, 112, 235, .25); border-radius: 8px; background: rgba(67, 48, 133, .16); }
.strip-label { font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.compact-alert { margin: 0; font-size: 11px; }
.listings-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 5px; }
.listings-kpis article, .listings-kpis button { display: grid; min-width: 0; min-height: 50px; align-content: center; gap: 1px; padding: 5px 9px; color: #e9ecff; text-align: left; border: 1px solid rgba(146, 153, 196, .14); border-radius: 8px; background: #181b30; }
.listings-kpis button { cursor: pointer; }.listings-kpis button.active { border-color: rgba(154, 130, 255, .65); background: rgba(91, 66, 177, .22); }
.listings-kpis span, .listings-kpis small { overflow: hidden; color: #9299ba; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }.listings-kpis strong { overflow: hidden; font-size: 17px; line-height: 1.05; text-overflow: ellipsis; white-space: nowrap; }
.bulk-strip { display: flex; min-height: 34px; align-items: center; gap: 10px; padding: 3px 8px; color: #cdd1e8; font-size: 10px; border: 1px solid rgba(143, 119, 255, .35); border-radius: 7px; background: rgba(82, 59, 160, .2); }.bulk-strip span { display: inline-flex; align-items: center; gap: 3px; }.bulk-strip small { color: #8ed7b4; }
.listings-layout { display: grid; grid-template-columns: minmax(0, 1fr) 390px; gap: 5px; min-height: calc(100vh - 335px); }
.list-panel, .inspector { min-width: 0; overflow: hidden; border: 1px solid rgba(143, 150, 194, .16); border-radius: 8px; background: #171a2e; }
.list-panel { display: grid; grid-template-rows: minmax(0, 1fr) auto; }
.table-shell { overflow: auto; min-height: 420px; max-height: calc(100vh - 382px); background: #111427; }
.listings-table { width: 100%; min-width: 1010px; border-spacing: 0; border-collapse: separate; font-size: 10px; }
.listings-table th { position: sticky; z-index: 2; top: 0; height: 31px; padding: 3px 6px; color: #aeb5d4; text-align: left; white-space: nowrap; border-right: 1px solid #32364d; border-bottom: 1px solid #3a3f59; background: #24283e; }
.listings-table td { height: 48px; padding: 3px 6px; vertical-align: middle; border-right: 1px solid #292d44; border-bottom: 1px solid #292d44; background: #171a2e; transition: background .12s ease; }
.listings-table tbody tr { cursor: pointer; }.listings-table tbody tr:nth-child(even) td { background: #15182b; }.listings-table tbody tr:hover td { background: #20243c; }.listings-table tbody tr.selected td { background: rgba(89, 67, 168, .28); box-shadow: inset 0 1px rgba(157, 137, 255, .12); }
.check-cell { width: 30px; padding: 0 2px !important; text-align: center !important; }.check-cell :deep(.v-selection-control) { min-height: 26px; }
.item-cell { width: 310px; min-width: 240px; max-width: 340px; }.item-cell strong, .item-cell small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.item-cell strong { font-size: 11px; }.item-cell small { margin-top: 2px; color: #858dac; font-size: 9px; }.item-cell code { color: #b8aaff; }
.number-cell { width: 92px; text-align: right !important; white-space: nowrap; }.metric-cell { width: 64px; text-align: right !important; white-space: nowrap; }.row-actions-cell { width: 55px; padding: 1px !important; white-space: nowrap; }
.table-state { height: 230px !important; color: #9198b7; text-align: center !important; cursor: default; }.table-state > * { display: block; margin: 5px auto; }.table-state strong { color: #d8dcf2; }
.list-footer { display: flex; min-height: 38px; align-items: center; gap: 6px; padding: 3px 7px; color: #9299b9; font-size: 10px; border-top: 1px solid #30344b; }.list-footer > span { flex: 1; }.list-footer .v-select { max-width: 82px; }.pager { display: flex; align-items: center; gap: 6px; }.pager strong { min-width: 34px; color: #d4d8ed; text-align: center; }
.inspector { display: grid; grid-template-rows: auto auto minmax(0, 1fr); min-height: 470px; }
.inspector-empty { display: grid; height: 100%; min-height: 460px; place-content: center; justify-items: center; gap: 7px; padding: 20px; color: #858dac; text-align: center; }.inspector-empty strong { color: #dce0f3; }.inspector-empty span { max-width: 260px; font-size: 11px; }
.inspector-head { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 6px; min-height: 66px; align-items: center; padding: 7px 9px; border-bottom: 1px solid #30344b; background: linear-gradient(120deg, rgba(72, 49, 143, .45), rgba(25, 28, 49, .75)); }.inspector-head small, .inspector-head span { display: block; overflow: hidden; color: #979fbe; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }.inspector-head h2 { overflow: hidden; margin: 2px 0; font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }.inspector-head__actions { display: flex; gap: 2px; }
.inspector-tabs { min-height: 34px; border-bottom: 1px solid #30344b; background: #1b1e34; }.inspector-tabs :deep(.v-tab) { min-width: 0; min-height: 34px; padding: 0 5px; font-size: 9px; }
.inspector-window { min-height: 0; }.inspector-window :deep(.v-window__container), .inspector-window :deep(.v-window-item) { height: 100%; }.inspector-scroll { height: calc(100vh - 485px); min-height: 384px; overflow: auto; padding: 7px; }.inspector-loading { display: grid; min-height: 230px; place-items: center; }
.card-lead { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 5px 2px 8px; }.card-lead > strong { font-size: 18px; }
.facts-grid { display: grid; grid-template-columns: 90px minmax(0, 1fr); margin: 0; font-size: 10px; border: 1px solid #30344b; border-radius: 7px; overflow: hidden; }.facts-grid dt, .facts-grid dd { min-height: 29px; padding: 6px 7px; border-bottom: 1px solid #2b2f46; }.facts-grid dt { color: #8991b1; background: #15182a; }.facts-grid dd { display: flex; min-width: 0; align-items: center; justify-content: space-between; gap: 3px; margin: 0; overflow-wrap: anywhere; background: #1b1e33; }.facts-grid small { color: #848cac; }
.compact-section { margin-top: 7px; padding: 7px; border: 1px solid #30344b; border-radius: 7px; background: #15182b; }.section-title { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; font-size: 10px; }.section-title small { color: #858dac; font-size: 9px; }
.inline-action { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 5px; }.inline-action--wide { grid-template-columns: minmax(0, 1fr) 34px; }
.service-chips { display: flex; flex-wrap: wrap; gap: 3px; }.inner-alert { margin: 7px 0 0; font-size: 10px; }.action-result { display: flex; align-items: center; gap: 5px; padding: 6px; color: #a9d8bf; font-size: 9px; border-radius: 6px; background: rgba(43, 142, 94, .12); }
.analytics-toolbar, .promotion-toolbar { display: flex; min-height: 34px; align-items: center; justify-content: space-between; gap: 5px; margin-bottom: 5px; }.analytics-toolbar .v-select { max-width: 160px; }.promotion-toolbar span { color: #949bbb; font-size: 9px; }
.mini-kpis { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; }.mini-kpis article { display: grid; gap: 1px; padding: 6px 7px; border: 1px solid #30344b; border-radius: 6px; background: #15182a; }.mini-kpis span { color: #858dac; font-size: 8px; }.mini-kpis strong { font-size: 14px; }
.micro-chart { display: flex; height: 82px; align-items: flex-end; gap: 2px; padding: 5px 1px 1px; border-bottom: 1px solid #454b67; }.micro-chart > div { display: flex; height: 100%; min-width: 2px; flex: 1; align-items: flex-end; }.micro-chart i { display: block; width: 100%; min-height: 3px; border-radius: 2px 2px 0 0; background: linear-gradient(#9a84ff, #5e47bd); }
.small-empty { padding: 16px 5px; color: #858dac; font-size: 9px; text-align: center; }.spending-list { display: grid; grid-template-columns: 1fr auto; gap: 5px; margin: 0; font-size: 10px; }.spending-list dt { color: #9299b8; }.spending-list dd { margin: 0; font-weight: 700; }
.service-list { display: grid; gap: 3px; }.service-list > div { display: flex; align-items: center; justify-content: space-between; gap: 5px; padding: 4px 6px; font-size: 9px; border-radius: 5px; background: #1e2239; }.service-list small { color: #91bfa7; }
.price-list { display: grid; grid-template-columns: 1fr 1fr; gap: 3px; margin-top: 5px; }.price-list button { display: grid; grid-template-columns: 1fr auto; gap: 2px 4px; padding: 4px 5px; color: #dce0f4; text-align: left; border: 1px solid #30354d; border-radius: 5px; background: #1d2036; }.price-list button:hover { border-color: #8069e7; }.price-list span { overflow: hidden; font-size: 8px; text-overflow: ellipsis; white-space: nowrap; }.price-list strong { font-size: 8px; }.price-list s { grid-column: 2; color: #7f87a7; font-size: 7px; }
.suggestions { display: flex; gap: 3px; overflow: auto; margin-bottom: 5px; }.suggestions button { display: grid; min-width: 95px; gap: 1px; padding: 4px 6px; color: #dfe2f6; text-align: left; border: 1px solid #353a52; border-radius: 5px; background: #1d2036; }.suggestions span { color: #989fbd; font-size: 8px; }.bbip-form { display: grid; grid-template-columns: 60px 1fr 1fr; gap: 4px; }.bbip-form .v-btn { grid-column: 1 / -1; }
.danger-row { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-top: 7px; padding: 7px; border: 1px solid rgba(220, 72, 95, .28); border-radius: 7px; background: rgba(123, 31, 51, .1); }.danger-row strong, .danger-row small { display: block; }.danger-row strong { font-size: 10px; }.danger-row small { color: #979eba; font-size: 8px; }
.raw-actions { display: flex; gap: 4px; margin-bottom: 6px; }.raw-tab details { margin-bottom: 5px; border: 1px solid #30344b; border-radius: 6px; background: #121527; }.raw-tab summary { padding: 6px 7px; color: #bfc4df; font-size: 9px; cursor: pointer; }.raw-tab pre { max-height: 300px; overflow: auto; margin: 0; padding: 7px; color: #cfd5f3; font: 8px/1.45 ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre-wrap; border-top: 1px solid #2b2f45; }
code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
:deep(.v-field) { font-size: 11px; }.listings-toolbar :deep(.v-field), .advanced-strip :deep(.v-field) { --v-field-input-padding-top: 6px; --v-field-input-padding-bottom: 6px; }.listings-workspace :deep(.v-label) { font-size: 10px; }.listings-workspace :deep(.v-input--density-compact) { --v-input-control-height: 34px; }.listings-workspace :deep(.v-checkbox .v-label) { font-size: 9px; }.mt-2 { margin-top: 5px; }
@media (max-width: 1350px) { .listings-toolbar { grid-template-columns: 1fr 1fr 1.4fr 1fr 110px auto; }.listings-toolbar > :nth-child(6) { display: none; }.listings-layout { grid-template-columns: minmax(0, 1fr) 355px; }.advanced-strip { grid-template-columns: auto 135px 135px 1fr auto auto; }.advanced-strip small { display: none; } }
@media (max-width: 1050px) { .listings-toolbar { grid-template-columns: 1fr 1fr 1.5fr auto; }.listings-toolbar > :nth-child(4), .listings-toolbar > :nth-child(5), .listings-toolbar > :nth-child(6) { display: none; }.listings-kpis { grid-template-columns: repeat(3, 1fr); }.listings-layout { grid-template-columns: 1fr; }.table-shell { max-height: 520px; }.inspector-scroll { height: auto; max-height: 620px; }.inspector { min-height: 500px; } }
@media (max-width: 650px) { .listings-toolbar { grid-template-columns: 1fr auto; }.listings-toolbar > :nth-child(2) { display: none; }.listings-toolbar > :nth-child(3) { grid-column: 1; }.advanced-strip { grid-template-columns: 1fr 1fr; }.advanced-strip .strip-label { grid-column: 1 / -1; }.advanced-strip > :nth-child(4) { grid-column: 1 / -1; }.listings-kpis { grid-template-columns: 1fr 1fr; }.listings-kpis article, .listings-kpis button { min-height: 46px; }.bulk-strip span { display: none; }.listings-layout { min-height: 0; }.table-shell { min-height: 390px; }.inspector { min-height: 480px; }.price-list { grid-template-columns: 1fr; } }
</style>
