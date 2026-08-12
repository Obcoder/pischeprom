<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { route } from 'ziggy-js'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import { logo } from '@/Pages/Helpers/consts.js'

defineOptions({
    layout: VerwalterLayout,
})

const page = usePage()
const canViewLogistics = computed(() => Boolean(page.props.auth?.permissions?.logistics?.view))
const checks = ref([])
const entities = ref([])
const commodities = ref([])
const services = ref([])
const warehouses = ref([])
const measures = ref([])
const expenseArticles = ref([])
const projects = ref([])

const loadingChecks = ref(false)
const savingCheck = ref(false)
const savingLine = ref(false)
const savingDictionary = ref(false)
const creatingEntity = ref(false)
const selectedCheckLoading = ref(false)

const checkDialog = ref(false)
const detailDialog = ref(false)
const dictionaryDialog = ref(false)
const dictionaryType = ref('articles')
const entityCreatorOpen = ref(false)
const entitySearch = ref('')
const checkErrors = ref({})
const checkSubmitError = ref('')
const newEntityErrors = ref({})
const newEntitySubmitError = ref('')
const draftLineKind = ref('commodity')
const draftCommodityError = ref('')
const draftCommodityRows = ref([])
const draftEditingIndex = ref(null)
const draftServiceError = ref('')
const draftServiceRows = ref([])
const draftServiceEditingIndex = ref(null)
let draftRowSequence = 0

const selectedCheck = ref(null)
const entityHeader = ref(null)
const entityColumnWidth = ref(null)

const ENTITY_COLUMN_WIDTH_STORAGE_KEY = 'ameise:checks:entity-column-width'
const ENTITY_COLUMN_MIN_WIDTH = 180
const ENTITY_COLUMN_MAX_WIDTH = 760

const entityColumnResize = reactive({
    active: false,
    startX: 0,
    startWidth: 0,
})

const filters = reactive({
    date_from: '',
    date_to: '',
    entity_id: null,
    project_id: null,
    sort_by: 'date',
    sort_desc: true,
})

const checksMeta = reactive({
    total_amount: 0,
    items_count: 0,
    project_totals: [],
})

const checkForm = reactive({
    id: null,
    date: today(),
    entity_id: null,
    amount: 0,
})

const newEntityForm = reactive({
    name: '',
    full_name: '',
    INN: '',
})

const draftCommodityForm = reactive({
    commodity_id: null,
    warehouse_id: null,
    quantity: 1,
    measure_id: null,
    expense_article_id: null,
    price: 0,
})

const draftServiceForm = reactive({
    service_id: null,
    quantity: 1,
    measure_id: null,
    expense_article_id: null,
    price: 0,
})

const lineForm = reactive({
    kind: 'commodity',
    id: null,
    commodity_id: null,
    service_id: null,
    warehouse_id: null,
    quantity: 1,
    measure_id: null,
    expense_article_id: null,
    price: 0,
})

const dictionaryForm = reactive({
    id: null,
    name: '',
    code: '',
    color: '#6fbf73',
    description: '',
    sort_order: 500,
    is_active: true,
    expense_article_id: null,
    project_id: null,
})

const selectedCommodity = computed(() => (
    commodities.value.find((item) => item.id === lineForm.commodity_id) || null
))

const selectedService = computed(() => (
    services.value.find((item) => item.id === lineForm.service_id) || null
))

const defaultWarehouseId = computed(() => (
    warehouses.value.find((item) => item.is_active)?.id || warehouses.value[0]?.id || null
))

const selectedCommodityItems = computed(() => selectedCheck.value?.items || [])

const selectedServiceItems = computed(() => selectedCheck.value?.service_items || [])

const registeredCommodityTotal = computed(() => {
    if (selectedCheck.value?.commodity_items_total !== null && selectedCheck.value?.commodity_items_total !== undefined) {
        return numeric(selectedCheck.value.commodity_items_total)
    }

    return selectedCommodityItems.value.reduce((sum, item) => (
        sum + numeric(item.total_price || numeric(item.quantity) * numeric(item.price))
    ), 0)
})

const registeredServiceTotal = computed(() => {
    if (selectedCheck.value?.service_items_total !== null && selectedCheck.value?.service_items_total !== undefined) {
        return numeric(selectedCheck.value.service_items_total)
    }

    return selectedServiceItems.value.reduce((sum, item) => (
        sum + numeric(item.total_price || numeric(item.quantity) * numeric(item.price))
    ), 0)
})

const registeredPositionsTotal = computed(() => (
    registeredCommodityTotal.value + registeredServiceTotal.value
))

const draftCommodityTotal = computed(() => draftCommodityRows.value.reduce(
    (sum, item) => sum + numeric(item.quantity) * numeric(item.price),
    0
))

const draftServiceTotal = computed(() => draftServiceRows.value.reduce(
    (sum, item) => sum + numeric(item.quantity) * numeric(item.price),
    0
))

const draftPositionsTotal = computed(() => (
    draftCommodityTotal.value + draftServiceTotal.value
))

const draftReceiptRows = computed(() => [
    ...draftCommodityRows.value.map((row, index) => ({
        kind: 'commodity',
        index,
        row,
    })),
    ...draftServiceRows.value.map((row, index) => ({
        kind: 'service',
        index,
        row,
    })),
].sort((left, right) => left.row._sequence - right.row._sequence))

const activeDraftForm = computed(() => (
    draftLineKind.value === 'service' ? draftServiceForm : draftCommodityForm
))

const activeDraftEditingIndex = computed(() => (
    draftLineKind.value === 'service' ? draftServiceEditingIndex.value : draftEditingIndex.value
))

const draftLineError = computed(() => (
    draftLineKind.value === 'service' ? draftServiceError.value : draftCommodityError.value
))

const draftAmountDifference = computed(() => (
    numeric(checkForm.amount) - draftPositionsTotal.value
))

const canSaveCheck = computed(() => (
    Boolean(checkForm.date)
    && Boolean(checkForm.entity_id)
    && numeric(checkForm.amount) >= 0
))

const receiptRows = computed(() => [
    ...selectedCommodityItems.value.map((item) => ({
        key: `commodity-${item.id}`,
        kind: 'commodity',
        item,
        created_at: item.created_at,
    })),
    ...selectedServiceItems.value.map((item) => ({
        key: `service-${item.id}`,
        kind: 'service',
        item,
        created_at: item.created_at,
    })),
].sort((a, b) => {
    const dateA = new Date(a.created_at || 0).getTime()
    const dateB = new Date(b.created_at || 0).getTime()

    return dateA === dateB ? a.item.id - b.item.id : dateA - dateB
}))

const stats = computed(() => {
    const localTotal = checks.value.reduce((sum, check) => sum + numeric(check.amount), 0)
    const localItems = checks.value.reduce((sum, check) => sum + Number(check.items_count || check.items?.length || 0), 0)
    const total = checksMeta.total_amount || localTotal
    const items = checksMeta.items_count || localItems

    return {
        total,
        items,
        average: checks.value.length ? total / checks.value.length : 0,
    }
})

const dictionaryItems = computed(() => {
    if (dictionaryType.value === 'articles') {
        return expenseArticles.value
    }

    if (dictionaryType.value === 'services') {
        return services.value
    }

    return projects.value
})

const dictionaryTitle = computed(() => {
    if (dictionaryType.value === 'articles') {
        return 'Статьи расходов'
    }

    if (dictionaryType.value === 'services') {
        return 'Услуги'
    }

    return 'Проекты'
})

const dictionaryResource = computed(() => {
    if (dictionaryType.value === 'articles') {
        return 'expense-articles'
    }

    if (dictionaryType.value === 'services') {
        return 'services'
    }

    return 'projects'
})

const dictionaryColspan = computed(() => {
    if (dictionaryType.value === 'articles') {
        return 6
    }

    if (dictionaryType.value === 'services') {
        return 7
    }

    return 5
})

const checkDialogTitle = computed(() => (
    checkForm.id ? `Редактировать Check #${checkForm.id}` : 'Новый Check'
))

const sortOptions = [
    { title: 'Дата', value: 'date' },
    { title: 'Сумма', value: 'amount' },
]

const datePresets = [
    {
        key: 'today',
        label: 'Сегодня',
        startOffset: 0,
        endOffset: 0,
        title: 'Только сегодня',
    },
    {
        key: 'yesterday',
        label: 'Вчера',
        startOffset: -1,
        endOffset: -1,
        title: 'Только вчера',
    },
    {
        key: 'day-before-yesterday',
        label: 'Позавчера',
        startOffset: -2,
        endOffset: -2,
        title: 'Только позавчера',
    },
    {
        key: 'previous-2-days',
        label: 'Прошлые 2 дня',
        startOffset: -2,
        endOffset: -1,
        title: 'Два предыдущих дня, без сегодня',
    },
    {
        key: 'previous-3-days',
        label: 'Прошлые 3 дня',
        startOffset: -3,
        endOffset: -1,
        title: 'Три предыдущих дня, без сегодня',
    },
    {
        key: 'previous-4-days',
        label: 'Прошлые 4 дня',
        startOffset: -4,
        endOffset: -1,
        title: 'Четыре предыдущих дня, без сегодня',
    },
    {
        key: 'week',
        label: 'Неделя',
        startOffset: -7,
        endOffset: -1,
        title: 'Предыдущие семь дней, без сегодня',
    },
]

const activeFiltersCount = computed(() => [
    filters.date_from,
    filters.date_to,
    filters.entity_id,
    filters.project_id,
].filter(Boolean).length)

const checksGridStyle = computed(() => (
    entityColumnWidth.value
        ? { '--checks-entity-col-width': `${entityColumnWidth.value}px` }
        : {}
))

watch(
    () => lineForm.commodity_id,
    () => {
        if (!lineForm.id && lineForm.kind === 'commodity') {
            lineForm.expense_article_id = selectedCommodity.value?.expense_article_id || null
        }
    }
)

watch(
    () => lineForm.service_id,
    () => {
        if (!lineForm.id && lineForm.kind === 'service') {
            lineForm.expense_article_id = selectedService.value?.expense_article_id || null
        }
    }
)

watch(
    () => lineForm.kind,
    () => {
        lineForm.id = null
        lineForm.commodity_id = null
        lineForm.service_id = null
        lineForm.warehouse_id = lineForm.kind === 'commodity' ? defaultWarehouseId.value : null
        lineForm.expense_article_id = null
    }
)

watch(dictionaryType, () => resetDictionaryForm())

let filtersTimer = null

watch(
    filters,
    () => {
        window.clearTimeout(filtersTimer)
        filtersTimer = window.setTimeout(() => loadChecks(), 250)
    },
    { deep: true }
)

function unpack(response) {
    return response?.data?.data || response?.data || []
}

function today() {
    return dateInputValue(new Date())
}

function dateInputValue(date) {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

function dateWithOffset(offset) {
    const date = new Date()

    date.setHours(12, 0, 0, 0)
    date.setDate(date.getDate() + offset)

    return dateInputValue(date)
}

function datePresetRange(preset) {
    return {
        from: dateWithOffset(preset.startOffset),
        to: dateWithOffset(preset.endOffset),
    }
}

function applyDatePreset(preset) {
    const range = datePresetRange(preset)

    filters.date_from = range.from
    filters.date_to = range.to
}

function isDatePresetActive(preset) {
    const range = datePresetRange(preset)

    return filters.date_from === range.from && filters.date_to === range.to
}

function numeric(value) {
    const number = Number(value)

    return Number.isFinite(number) ? number : 0
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(numeric(value))
}

function formatDate(value) {
    if (!value) {
        return '-'
    }

    const date = new Date(`${value}T00:00:00`)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    const month = new Intl.DateTimeFormat('ru-RU', {
        month: 'long',
    }).format(date)

    return `${date.getFullYear()} ${month} ${date.getDate()}`
}

function formatQty(value) {
    return new Intl.NumberFormat('ru-RU', {
        maximumFractionDigits: 3,
    }).format(numeric(value))
}

function validationMessage(errors, field) {
    const messages = errors?.[field]

    return Array.isArray(messages) ? messages[0] : messages || ''
}

function entityUnitNames(entity) {
    return (entity?.units || [])
        .map((unit) => unit?.name)
        .filter(Boolean)
}

function entitySearchLabel(entity) {
    const units = entityUnitNames(entity)

    return units.length
        ? `${entity?.name || 'Без названия'} — ${units.join(', ')}`
        : entity?.name || 'Без названия'
}

function entityUnitSubtitle(entity) {
    const units = entityUnitNames(entity)

    return units.length ? `Unit: ${units.join(', ')}` : 'Без присоединённого Unit'
}

function commodityById(id) {
    return commodities.value.find((item) => Number(item.id) === Number(id)) || null
}

function serviceById(id) {
    return services.value.find((item) => Number(item.id) === Number(id)) || null
}

function warehouseById(id) {
    return warehouses.value.find((item) => Number(item.id) === Number(id)) || null
}

function measureById(id) {
    return measures.value.find((item) => Number(item.id) === Number(id)) || null
}

function expenseArticleById(id) {
    return expenseArticles.value.find((item) => Number(item.id) === Number(id)) || null
}

function draftLineTotal(row) {
    return numeric(row.quantity) * numeric(row.price)
}

function articleColor(article) {
    return article?.color || '#8fbf5f'
}

function entityName(check) {
    return check.entity?.name || 'Без entity'
}

function entitySubtitle(check) {
    return check.entity?.classification?.name || `Entity #${check.entity_id || '-'}`
}

function entityHref(check) {
    const entityId = check.entity?.id || check.entity_id

    return entityId ? route('Ameise.entity.show', entityId) : null
}

function clampEntityColumnWidth(width) {
    return Math.min(
        Math.max(Math.round(width), ENTITY_COLUMN_MIN_WIDTH),
        ENTITY_COLUMN_MAX_WIDTH
    )
}

function readStoredEntityColumnWidth() {
    if (typeof window === 'undefined') {
        return
    }

    try {
        const storedWidth = Number(window.localStorage.getItem(ENTITY_COLUMN_WIDTH_STORAGE_KEY))

        if (Number.isFinite(storedWidth) && storedWidth > 0) {
            entityColumnWidth.value = clampEntityColumnWidth(storedWidth)
        }
    } catch (error) {
        console.warn('Unable to read checks entity column width:', error)
    }
}

function storeEntityColumnWidth() {
    if (typeof window === 'undefined' || !entityColumnWidth.value) {
        return
    }

    try {
        window.localStorage.setItem(
            ENTITY_COLUMN_WIDTH_STORAGE_KEY,
            String(clampEntityColumnWidth(entityColumnWidth.value))
        )
    } catch (error) {
        console.warn('Unable to store checks entity column width:', error)
    }
}

function startEntityColumnResize(event) {
    if (event.button !== 0) {
        return
    }

    const measuredWidth = entityHeader.value?.getBoundingClientRect().width

    entityColumnResize.active = true
    entityColumnResize.startX = event.clientX
    entityColumnResize.startWidth = measuredWidth || entityColumnWidth.value || 320
    entityColumnWidth.value = clampEntityColumnWidth(entityColumnResize.startWidth)

    document.body.classList.add('checks-resizing-column')
    window.addEventListener('mousemove', resizeEntityColumn)
    window.addEventListener('mouseup', stopEntityColumnResize)
    window.addEventListener('blur', stopEntityColumnResize)
}

function resizeEntityColumn(event) {
    if (!entityColumnResize.active) {
        return
    }

    entityColumnWidth.value = clampEntityColumnWidth(
        entityColumnResize.startWidth + event.clientX - entityColumnResize.startX
    )
}

function stopEntityColumnResize() {
    if (!entityColumnResize.active) {
        return
    }

    entityColumnResize.active = false
    storeEntityColumnWidth()
    document.body.classList.remove('checks-resizing-column')
    window.removeEventListener('mousemove', resizeEntityColumn)
    window.removeEventListener('mouseup', stopEntityColumnResize)
    window.removeEventListener('blur', stopEntityColumnResize)
}

function commodityHref(item) {
    const commodityId = item.commodity?.id || item.commodity_id

    return commodityId ? route('Ameise.commodity.show', commodityId) : null
}

function rowTitle(row) {
    if (row.kind === 'service') {
        return row.item.service?.name || `Услуга #${row.item.service_id}`
    }

    return row.item.commodity?.name || `Commodity #${row.item.commodity_id}`
}

function checksFilterParams() {
    return {
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
        entity_id: filters.entity_id || undefined,
        project_id: filters.project_id || undefined,
        sort_by: filters.sort_by,
        sort_desc: filters.sort_desc,
    }
}

function resetFilters() {
    filters.date_from = ''
    filters.date_to = ''
    filters.entity_id = null
    filters.project_id = null
    filters.sort_by = 'date'
    filters.sort_desc = true
}

async function loadChecks() {
    loadingChecks.value = true

    try {
        const response = await axios.get(route('checks.index'), {
            params: checksFilterParams(),
        })

        checks.value = unpack(response)
        checksMeta.total_amount = numeric(response.data?.meta?.total_amount)
        checksMeta.items_count = Number(response.data?.meta?.items_count || 0)
        checksMeta.project_totals = response.data?.meta?.project_totals || []
    } catch (error) {
        console.error('loadChecks error:', error)
    } finally {
        loadingChecks.value = false
    }
}

async function loadDictionaries() {
    const [
        entitiesResponse,
        commoditiesResponse,
        servicesResponse,
        warehousesResponse,
        measuresResponse,
        articlesResponse,
        projectsResponse,
    ] = await Promise.all([
        axios.get(route('entities.index'), { params: { itemsPerPage: 1000 } }),
        axios.get(route('commodities.index'), {
            params: {
                per_page: 500,
                sort_by: 'name',
                sort_desc: false,
            },
        }),
        axios.get(route('services.index')),
        axios.get(route('warehouses.index')),
        axios.get(route('measures.index')),
        axios.get(route('expense-articles.index')),
        axios.get(route('projects.index')),
    ])

    entities.value = unpack(entitiesResponse)
    commodities.value = unpack(commoditiesResponse)
    services.value = unpack(servicesResponse)
    warehouses.value = unpack(warehousesResponse)
    measures.value = unpack(measuresResponse)
    expenseArticles.value = unpack(articlesResponse)
    projects.value = unpack(projectsResponse)

    if (!lineForm.warehouse_id) {
        lineForm.warehouse_id = defaultWarehouseId.value
    }

    if (!draftCommodityForm.warehouse_id) {
        draftCommodityForm.warehouse_id = defaultWarehouseId.value
    }
}

function resetCheckForm() {
    checkForm.id = null
    checkForm.date = today()
    checkForm.entity_id = null
    checkForm.amount = 0
    entitySearch.value = ''
    entityCreatorOpen.value = false
    checkErrors.value = {}
    checkSubmitError.value = ''
    draftLineKind.value = 'commodity'
    draftCommodityRows.value = []
    draftServiceRows.value = []
    resetNewEntityForm()
    resetDraftCommodityForm()
    resetDraftServiceForm()
}

function resetNewEntityForm(name = '') {
    newEntityForm.name = name
    newEntityForm.full_name = ''
    newEntityForm.INN = ''
    newEntityErrors.value = {}
    newEntitySubmitError.value = ''
}

function showEntityCreator() {
    resetNewEntityForm(entitySearch.value.trim())
    entityCreatorOpen.value = true
}

function hideEntityCreator() {
    entityCreatorOpen.value = false
    resetNewEntityForm()
}

async function createEntity() {
    newEntityErrors.value = {}
    newEntitySubmitError.value = ''

    const name = newEntityForm.name.trim()

    if (!name) {
        newEntityErrors.value = { name: ['Укажите название Entity.'] }
        return
    }

    creatingEntity.value = true

    try {
        const response = await axios.post(route('entities.store'), {
            name,
            full_name: newEntityForm.full_name.trim() || null,
            INN: newEntityForm.INN.trim() || null,
        })
        const entity = unpack(response)

        entities.value = [
            ...entities.value.filter((item) => Number(item.id) !== Number(entity.id)),
            entity,
        ].sort((left, right) => left.name.localeCompare(right.name, 'ru'))
        checkForm.entity_id = entity.id
        entitySearch.value = ''
        hideEntityCreator()
    } catch (error) {
        newEntityErrors.value = error.response?.data?.errors || {}
        newEntitySubmitError.value = error.response?.data?.message || 'Не удалось создать Entity.'
        console.error('createEntity error:', error)
    } finally {
        creatingEntity.value = false
    }
}

function resetDraftCommodityForm() {
    draftEditingIndex.value = null
    draftCommodityForm.commodity_id = null
    draftCommodityForm.warehouse_id = defaultWarehouseId.value
    draftCommodityForm.quantity = 1
    draftCommodityForm.measure_id = null
    draftCommodityForm.expense_article_id = null
    draftCommodityForm.price = 0
    draftCommodityError.value = ''
}

function resetDraftServiceForm() {
    draftServiceEditingIndex.value = null
    draftServiceForm.service_id = null
    draftServiceForm.quantity = 1
    draftServiceForm.measure_id = null
    draftServiceForm.expense_article_id = null
    draftServiceForm.price = 0
    draftServiceError.value = ''
}

function switchDraftLineKind(kind) {
    if (!kind || kind === draftLineKind.value) {
        return
    }

    resetDraftCommodityForm()
    resetDraftServiceForm()
    draftLineKind.value = kind
}

function resetActiveDraftForm() {
    if (draftLineKind.value === 'service') {
        resetDraftServiceForm()
        return
    }

    resetDraftCommodityForm()
}

function selectDraftCommodity(commodityId) {
    draftCommodityForm.commodity_id = commodityId
    draftCommodityForm.expense_article_id = commodityById(commodityId)?.expense_article_id || null
    draftCommodityError.value = ''
}

function selectDraftService(serviceId) {
    draftServiceForm.service_id = serviceId
    draftServiceForm.expense_article_id = serviceById(serviceId)?.expense_article_id || null
    draftServiceError.value = ''
}

function saveDraftLine() {
    if (draftLineKind.value === 'service') {
        saveDraftService()
        return
    }

    saveDraftCommodity()
}

function saveDraftCommodity() {
    draftCommodityError.value = ''

    if (!draftCommodityForm.commodity_id) {
        draftCommodityError.value = 'Выберите Commodity.'
        return
    }

    if (numeric(draftCommodityForm.quantity) <= 0) {
        draftCommodityError.value = 'Количество должно быть больше нуля.'
        return
    }

    if (numeric(draftCommodityForm.price) < 0) {
        draftCommodityError.value = 'Цена не может быть отрицательной.'
        return
    }

    const existingRow = draftEditingIndex.value === null
        ? null
        : draftCommodityRows.value[draftEditingIndex.value]
    const sequence = existingRow?._sequence || ++draftRowSequence
    const row = {
        _key: existingRow?._key || `draft-commodity-${sequence}`,
        _sequence: sequence,
        commodity_id: draftCommodityForm.commodity_id,
        warehouse_id: draftCommodityForm.warehouse_id || null,
        quantity: numeric(draftCommodityForm.quantity),
        measure_id: draftCommodityForm.measure_id || null,
        expense_article_id: draftCommodityForm.expense_article_id || null,
        price: numeric(draftCommodityForm.price),
    }

    if (draftEditingIndex.value === null) {
        draftCommodityRows.value.push(row)
    } else {
        draftCommodityRows.value.splice(draftEditingIndex.value, 1, row)
    }

    resetDraftCommodityForm()
}

function saveDraftService() {
    draftServiceError.value = ''

    if (!draftServiceForm.service_id) {
        draftServiceError.value = 'Выберите услугу.'
        return
    }

    if (numeric(draftServiceForm.quantity) <= 0) {
        draftServiceError.value = 'Количество должно быть больше нуля.'
        return
    }

    if (numeric(draftServiceForm.price) < 0) {
        draftServiceError.value = 'Цена не может быть отрицательной.'
        return
    }

    const existingRow = draftServiceEditingIndex.value === null
        ? null
        : draftServiceRows.value[draftServiceEditingIndex.value]
    const sequence = existingRow?._sequence || ++draftRowSequence
    const row = {
        _key: existingRow?._key || `draft-service-${sequence}`,
        _sequence: sequence,
        service_id: draftServiceForm.service_id,
        quantity: numeric(draftServiceForm.quantity),
        measure_id: draftServiceForm.measure_id || null,
        expense_article_id: draftServiceForm.expense_article_id || null,
        price: numeric(draftServiceForm.price),
    }

    if (draftServiceEditingIndex.value === null) {
        draftServiceRows.value.push(row)
    } else {
        draftServiceRows.value.splice(draftServiceEditingIndex.value, 1, row)
    }

    resetDraftServiceForm()
}

function editDraftCommodity(index) {
    const row = draftCommodityRows.value[index]

    if (!row) {
        return
    }

    resetDraftServiceForm()
    draftLineKind.value = 'commodity'
    draftEditingIndex.value = index
    draftCommodityForm.commodity_id = row.commodity_id
    draftCommodityForm.warehouse_id = row.warehouse_id
    draftCommodityForm.quantity = row.quantity
    draftCommodityForm.measure_id = row.measure_id
    draftCommodityForm.expense_article_id = row.expense_article_id
    draftCommodityForm.price = row.price
    draftCommodityError.value = ''
}

function editDraftService(index) {
    const row = draftServiceRows.value[index]

    if (!row) {
        return
    }

    resetDraftCommodityForm()
    draftLineKind.value = 'service'
    draftServiceEditingIndex.value = index
    draftServiceForm.service_id = row.service_id
    draftServiceForm.quantity = row.quantity
    draftServiceForm.measure_id = row.measure_id
    draftServiceForm.expense_article_id = row.expense_article_id
    draftServiceForm.price = row.price
    draftServiceError.value = ''
}

function editDraftReceiptRow(entry) {
    if (entry.kind === 'service') {
        editDraftService(entry.index)
        return
    }

    editDraftCommodity(entry.index)
}

function removeDraftCommodity(index) {
    draftCommodityRows.value.splice(index, 1)

    if (draftEditingIndex.value === index) {
        resetDraftCommodityForm()
    } else if (draftEditingIndex.value !== null && draftEditingIndex.value > index) {
        draftEditingIndex.value -= 1
    }
}

function removeDraftService(index) {
    draftServiceRows.value.splice(index, 1)

    if (draftServiceEditingIndex.value === index) {
        resetDraftServiceForm()
    } else if (draftServiceEditingIndex.value !== null && draftServiceEditingIndex.value > index) {
        draftServiceEditingIndex.value -= 1
    }
}

function removeDraftReceiptRow(entry) {
    if (entry.kind === 'service') {
        removeDraftService(entry.index)
        return
    }

    removeDraftCommodity(entry.index)
}

function openCreateCheck() {
    resetCheckForm()
    checkDialog.value = true
}

function openEditCheck(check) {
    resetCheckForm()
    checkForm.id = check.id
    checkForm.date = check.date || today()
    checkForm.entity_id = check.entity_id || null
    checkForm.amount = numeric(check.amount)
    checkDialog.value = true
}

async function saveCheck() {
    checkErrors.value = {}
    checkSubmitError.value = ''

    if (!canSaveCheck.value) {
        checkSubmitError.value = 'Заполните дату и выберите Entity.'
        return
    }

    savingCheck.value = true

    const payload = {
        date: checkForm.date,
        entity_id: checkForm.entity_id,
        amount: numeric(checkForm.amount),
    }

    if (!checkForm.id) {
        payload.commodities = draftCommodityRows.value.map(({ _key, _sequence, ...row }) => row)
        payload.services = draftServiceRows.value.map(({ _key, _sequence, ...row }) => row)
    }

    try {
        if (checkForm.id) {
            await axios.patch(route('checks.update', checkForm.id), payload)
        } else {
            await axios.post(route('checks.store'), payload)
        }

        checkDialog.value = false
        await loadChecks()

        if (selectedCheck.value?.id === checkForm.id) {
            await loadCheck(checkForm.id)
        }
    } catch (error) {
        checkErrors.value = error.response?.data?.errors || {}
        checkSubmitError.value = error.response?.data?.message || 'Не удалось сохранить Check.'
        console.error('saveCheck error:', error)
    } finally {
        savingCheck.value = false
    }
}

async function deleteCheck(check) {
    if (!confirm(`Удалить Check #${check.id}?`)) {
        return
    }

    try {
        await axios.delete(route('checks.destroy', check.id))

        if (selectedCheck.value?.id === check.id) {
            selectedCheck.value = null
            detailDialog.value = false
        }

        await loadChecks()
    } catch (error) {
        console.error('deleteCheck error:', error)
    }
}

async function loadCheck(id) {
    selectedCheckLoading.value = true

    try {
        const response = await axios.get(route('checks.show', id))

        selectedCheck.value = response.data.data || response.data
    } catch (error) {
        console.error('loadCheck error:', error)
    } finally {
        selectedCheckLoading.value = false
    }
}

async function openCheck(check) {
    detailDialog.value = true
    resetLineForm()
    await loadCheck(check.id)
}

function resetLineForm() {
    lineForm.kind = 'commodity'
    lineForm.id = null
    lineForm.commodity_id = null
    lineForm.service_id = null
    lineForm.warehouse_id = defaultWarehouseId.value
    lineForm.quantity = 1
    lineForm.measure_id = null
    lineForm.expense_article_id = null
    lineForm.price = 0
}

function editCommodityLine(item) {
    lineForm.kind = 'commodity'
    lineForm.id = item.id
    lineForm.commodity_id = item.commodity_id
    lineForm.service_id = null
    lineForm.warehouse_id = item.warehouse_id || defaultWarehouseId.value
    lineForm.quantity = numeric(item.quantity)
    lineForm.measure_id = item.measure_id || null
    lineForm.expense_article_id = item.expense_article_id || item.commodity?.expense_article_id || null
    lineForm.price = numeric(item.price)
}

function editServiceLine(item) {
    lineForm.kind = 'service'
    lineForm.id = item.id
    lineForm.commodity_id = null
    lineForm.service_id = item.service_id
    lineForm.warehouse_id = null
    lineForm.quantity = numeric(item.quantity)
    lineForm.measure_id = item.measure_id || null
    lineForm.expense_article_id = item.expense_article_id || item.service?.expense_article_id || null
    lineForm.price = numeric(item.price)
}

function editReceiptRow(row) {
    if (row.kind === 'service') {
        editServiceLine(row.item)
        return
    }

    editCommodityLine(row.item)
}

async function saveLine() {
    if (!selectedCheck.value?.id) {
        return
    }

    savingLine.value = true

    const payload = {
        quantity: numeric(lineForm.quantity),
        measure_id: lineForm.measure_id,
        expense_article_id: lineForm.expense_article_id,
        price: numeric(lineForm.price),
    }

    try {
        if (lineForm.kind === 'service') {
            payload.service_id = lineForm.service_id

            if (lineForm.id) {
                await axios.patch(route('check-services.update', lineForm.id), payload)
            } else {
                await axios.post(route('checks.services.store', selectedCheck.value.id), payload)
            }
        } else {
            payload.commodity_id = lineForm.commodity_id
            payload.warehouse_id = lineForm.warehouse_id || defaultWarehouseId.value

            if (lineForm.id) {
                await axios.patch(route('check-commodities.update', lineForm.id), payload)
            } else {
                await axios.post(route('checks.commodities.store', selectedCheck.value.id), payload)
            }
        }

        resetLineForm()
        await Promise.all([
            loadCheck(selectedCheck.value.id),
            loadChecks(),
        ])
    } catch (error) {
        console.error('saveLine error:', error)
    } finally {
        savingLine.value = false
    }
}

async function deleteReceiptRow(row) {
    if (!confirm(`Удалить строку "${rowTitle(row)}" из чека?`)) {
        return
    }

    try {
        if (row.kind === 'service') {
            await axios.delete(route('check-services.destroy', row.item.id))
        } else {
            await axios.delete(route('check-commodities.destroy', row.item.id))
        }

        await Promise.all([
            loadCheck(selectedCheck.value.id),
            loadChecks(),
        ])
    } catch (error) {
        console.error('deleteLine error:', error)
    }
}

function openDictionary(type) {
    dictionaryType.value = type
    resetDictionaryForm()
    dictionaryDialog.value = true
}

function resetDictionaryForm() {
    dictionaryForm.id = null
    dictionaryForm.name = ''
    dictionaryForm.code = ''
    dictionaryForm.color = '#6fbf73'
    dictionaryForm.description = ''
    dictionaryForm.sort_order = 500
    dictionaryForm.is_active = true
    dictionaryForm.expense_article_id = null
    dictionaryForm.project_id = null
}

function editDictionary(item) {
    dictionaryForm.id = item.id
    dictionaryForm.name = item.name || ''
    dictionaryForm.code = item.code || ''
    dictionaryForm.color = item.color || '#6fbf73'
    dictionaryForm.description = item.description || ''
    dictionaryForm.sort_order = item.sort_order ?? 500
    dictionaryForm.is_active = item.is_active ?? true
    dictionaryForm.expense_article_id = item.expense_article_id || null
    dictionaryForm.project_id = item.project_id || null
}

async function saveDictionary() {
    savingDictionary.value = true

    const resource = dictionaryResource.value
    const payload = {
        name: dictionaryForm.name,
        code: dictionaryForm.code || null,
        description: dictionaryForm.description || null,
        is_active: Boolean(dictionaryForm.is_active),
    }

    if (dictionaryType.value === 'articles') {
        payload.color = dictionaryForm.color || null
        payload.sort_order = numeric(dictionaryForm.sort_order)
    }

    if (dictionaryType.value === 'services') {
        payload.expense_article_id = dictionaryForm.expense_article_id || null
        payload.project_id = dictionaryForm.project_id || null
    }

    try {
        if (dictionaryForm.id) {
            await axios.patch(route(`${resource}.update`, dictionaryForm.id), payload)
        } else {
            await axios.post(route(`${resource}.store`), payload)
        }

        resetDictionaryForm()
        await loadDictionaries()
    } catch (error) {
        console.error('saveDictionary error:', error)
    } finally {
        savingDictionary.value = false
    }
}

async function deleteDictionary(item) {
    if (!confirm(`Удалить "${item.name}"?`)) {
        return
    }

    const resource = dictionaryResource.value

    try {
        await axios.delete(route(`${resource}.destroy`, item.id))
        await loadDictionaries()
    } catch (error) {
        console.error('deleteDictionary error:', error)
    }
}

onMounted(async () => {
    readStoredEntityColumnWidth()

    await Promise.all([
        loadChecks(),
        loadDictionaries(),
    ])

    const checkId = new URL(window.location.href).searchParams.get('check')

    if (checkId) {
        await openCheck({ id: checkId })
    }
})

onBeforeUnmount(() => {
    stopEntityColumnResize()
})
</script>

<template>
    <v-container
        fluid
        class="checks-page pa-0"
        :class="{ 'checks-page--resizing-column': entityColumnResize.active }"
    >
        <Head title="Checks" />

        <div class="checks-shell">
            <header class="checks-toolbar">
                <div class="checks-toolbar__title">
                    <span>Checks</span>
                    <strong>{{ checks.length }}</strong>
                </div>

                <div class="checks-kpis">
                    <div>
                        <span>Сумма</span>
                        <strong>{{ formatMoney(stats.total) }}</strong>
                    </div>
                    <div>
                        <span>Строк</span>
                        <strong>{{ stats.items }}</strong>
                    </div>
                    <div>
                        <span>Средний</span>
                        <strong>{{ formatMoney(stats.average) }}</strong>
                    </div>
                </div>

                <div class="checks-actions">
                    <v-btn
                        icon="mdi-shape-outline"
                        variant="tonal"
                        density="compact"
                        title="Статьи расходов"
                        @click="openDictionary('articles')"
                    />
                    <v-btn
                        icon="mdi-briefcase-outline"
                        variant="tonal"
                        density="compact"
                        title="Проекты"
                        @click="openDictionary('projects')"
                    />
                    <v-btn
                        icon="mdi-handshake-outline"
                        variant="tonal"
                        density="compact"
                        title="Услуги"
                        @click="openDictionary('services')"
                    />
                    <v-btn
                        icon="mdi-refresh"
                        variant="text"
                        density="compact"
                        :loading="loadingChecks"
                        title="Обновить"
                        @click="loadChecks"
                    />
                    <v-btn
                        color="#7f5f00"
                        prepend-icon="mdi-receipt-text-plus-outline"
                        text="Check"
                        variant="flat"
                        density="compact"
                        @click="openCreateCheck"
                    />
                </div>
            </header>

            <section class="checks-filter-panel">
                <div class="checks-date-presets" aria-label="Быстрый фильтр по дате">
                    <button
                        v-for="preset in datePresets"
                        :key="preset.key"
                        type="button"
                        class="checks-date-preset"
                        :class="{ 'checks-date-preset--active': isDatePresetActive(preset) }"
                        :title="preset.title"
                        :aria-pressed="isDatePresetActive(preset)"
                        @click="applyDatePreset(preset)"
                    >
                        {{ preset.label }}
                    </button>
                </div>

                <div class="checks-filter-grid">
                    <v-text-field
                        v-model="filters.date_from"
                        type="date"
                        label="Дата от"
                        variant="solo-filled"
                        density="compact"
                        hide-details
                    />
                    <v-text-field
                        v-model="filters.date_to"
                        type="date"
                        label="Дата до"
                        variant="solo-filled"
                        density="compact"
                        hide-details
                    />
                    <v-autocomplete
                        v-model="filters.entity_id"
                        :items="entities"
                        :item-title="entitySearchLabel"
                        item-value="id"
                        label="Контрагент"
                        variant="solo-filled"
                        density="compact"
                        clearable
                        hide-details
                    >
                        <template #item="{ props, item }">
                            <v-list-item v-bind="props" :title="item.raw.name">
                                <template #prepend>
                                    <v-icon icon="mdi-domain" size="20" />
                                </template>
                                <template #subtitle>{{ entityUnitSubtitle(item.raw) }}</template>
                            </v-list-item>
                        </template>
                    </v-autocomplete>
                    <v-autocomplete
                        v-model="filters.project_id"
                        :items="projects"
                        item-title="name"
                        item-value="id"
                        label="Project"
                        variant="solo-filled"
                        density="compact"
                        clearable
                        hide-details
                    />
                    <v-select
                        v-model="filters.sort_by"
                        :items="sortOptions"
                        item-title="title"
                        item-value="value"
                        label="Сортировка"
                        variant="solo-filled"
                        density="compact"
                        hide-details
                    />
                    <v-btn
                        :icon="filters.sort_desc ? 'mdi-sort-descending' : 'mdi-sort-ascending'"
                        variant="tonal"
                        density="comfortable"
                        :title="filters.sort_desc ? 'По убыванию' : 'По возрастанию'"
                        @click="filters.sort_desc = !filters.sort_desc"
                    />
                    <v-btn
                        icon="mdi-filter-remove-outline"
                        variant="text"
                        density="comfortable"
                        :disabled="!activeFiltersCount && filters.sort_by === 'date' && filters.sort_desc"
                        title="Сбросить фильтры"
                        @click="resetFilters"
                    />
                </div>

                <div class="checks-project-totals">
                    <button
                        v-for="projectTotal in checksMeta.project_totals"
                        :key="projectTotal.project_id || 'without-project'"
                        type="button"
                        class="project-total"
                        :class="{ 'project-total--active': projectTotal.project_id && Number(filters.project_id) === Number(projectTotal.project_id) }"
                        @click="filters.project_id = projectTotal.project_id"
                    >
                        <span>{{ projectTotal.project_name }}</span>
                        <strong>{{ formatMoney(projectTotal.total) }}</strong>
                    </button>
                    <span v-if="!checksMeta.project_totals.length" class="muted">Нет сумм по проектам</span>
                </div>
            </section>

            <div class="checks-table-wrap">
                <table class="checks-grid" :style="checksGridStyle">
                    <colgroup>
                        <col class="checks-col-id">
                        <col class="checks-col-date">
                        <col class="checks-col-entity">
                        <col class="checks-col-money">
                        <col class="checks-col-count">
                        <col class="checks-col-article">
                        <col class="checks-col-project">
                        <col class="checks-col-actions">
                    </colgroup>
                    <thead>
                        <tr class="checks-grid__groups">
                            <th colspan="3" class="group-check">Check</th>
                            <th colspan="2" class="group-budget">Бюджет</th>
                            <th colspan="2" class="group-links">Связи</th>
                            <th class="group-actions">Действия</th>
                        </tr>
                        <tr>
                            <th class="col-id">ID</th>
                            <th class="col-date">Дата</th>
                            <th ref="entityHeader" class="col-entity">
                                <span class="col-entity__label">Контрагент</span>
                                <span
                                    class="column-resize-handle"
                                    role="separator"
                                    aria-orientation="vertical"
                                    aria-label="Изменить ширину столбца Контрагент"
                                    title="Потяните, чтобы изменить ширину"
                                    @mousedown.stop.prevent="startEntityColumnResize"
                                ></span>
                            </th>
                            <th class="col-money">Сумма</th>
                            <th class="col-count">Строк</th>
                            <th>Статья</th>
                            <th>Проект</th>
                            <th class="col-edit">CRUD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loadingChecks">
                            <td colspan="8" class="state-cell">Загрузка checks...</td>
                        </tr>
                        <tr v-else-if="!checks.length">
                            <td colspan="8" class="state-cell">Checks пока не созданы.</td>
                        </tr>
                        <template v-else>
                            <tr
                                v-for="check in checks"
                                :key="check.id"
                                class="checks-grid__row"
                                @click="openCheck(check)"
                            >
                                <td class="cell-id" :title="`Check #${check.id}`">{{ check.id }}</td>
                                <td class="cell-date">{{ formatDate(check.date) }}</td>
                                <td>
                                    <span class="entity-button">
                                        <a
                                            v-if="entityHref(check)"
                                            :href="entityHref(check)"
                                            class="entity-link"
                                            @click.stop
                                        >
                                            <strong>{{ entityName(check) }}</strong>
                                        </a>
                                        <strong v-else>{{ entityName(check) }}</strong>
                                        <span>{{ entitySubtitle(check) }}</span>
                                    </span>
                                </td>
                                <td class="cell-money">{{ formatMoney(check.amount) }}</td>
                                <td class="cell-count">{{ check.items_count || check.items?.length || 0 }}</td>
                                <td>
                                    <span class="muted">по строкам</span>
                                </td>
                                <td>
                                    <span class="muted">commodity</span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <v-btn
                                            icon="mdi-eye-outline"
                                            size="small"
                                            variant="text"
                                            title="Открыть чек"
                                            @click.stop="openCheck(check)"
                                        />
                                        <v-btn
                                            icon="mdi-pencil-outline"
                                            size="small"
                                            variant="text"
                                            title="Редактировать"
                                            @click.stop="openEditCheck(check)"
                                        />
                                        <v-btn
                                            icon="mdi-delete-outline"
                                            size="small"
                                            variant="text"
                                            color="error"
                                            title="Удалить"
                                            @click.stop="deleteCheck(check)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <v-dialog v-model="checkDialog" max-width="1180" scrollable>
            <v-card class="check-form-modal">
                <v-card-title class="check-form-header">
                    <div class="check-form-header__icon">
                        <v-icon :icon="checkForm.id ? 'mdi-receipt-text-edit-outline' : 'mdi-receipt-text-plus-outline'" />
                    </div>
                    <div>
                        <span>{{ checkDialogTitle }}</span>
                        <small>
                            {{ checkForm.id ? 'Измените основные реквизиты чека' : 'Заполните реквизиты и состав чека до сохранения' }}
                        </small>
                    </div>
                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        density="comfortable"
                        title="Закрыть"
                        @click="checkDialog = false"
                    />
                </v-card-title>

                <v-card-text class="check-form-body">
                    <div class="check-form-overview">
                        <section class="check-form-section check-form-section--details">
                            <div class="check-form-section__title">
                                <span class="check-form-step">1</span>
                                <div>
                                    <strong>Реквизиты</strong>
                                    <small>Дата и контрагент</small>
                                </div>
                            </div>

                            <div class="check-details-grid">
                                <v-text-field
                                    v-model="checkForm.date"
                                    type="date"
                                    label="Дата чека"
                                    variant="outlined"
                                    density="comfortable"
                                    prepend-inner-icon="mdi-calendar-blank-outline"
                                    :error-messages="validationMessage(checkErrors, 'date')"
                                />

                                <div class="entity-picker">
                                    <div class="entity-picker__control">
                                        <v-autocomplete
                                            v-model="checkForm.entity_id"
                                            v-model:search="entitySearch"
                                            :items="entities"
                                            :item-title="entitySearchLabel"
                                            item-value="id"
                                            label="Entity"
                                            placeholder="Название Entity или Unit"
                                            variant="outlined"
                                            density="comfortable"
                                            prepend-inner-icon="mdi-domain"
                                            clearable
                                            no-data-text="Entity не найден"
                                            :error-messages="validationMessage(checkErrors, 'entity_id')"
                                        >
                                            <template #item="{ props, item }">
                                                <v-list-item v-bind="props" :title="item.raw.name">
                                                    <template #prepend>
                                                        <v-avatar size="34" rounded="lg" color="#eef3e8">
                                                            <v-icon icon="mdi-domain" size="20" color="#386145" />
                                                        </v-avatar>
                                                    </template>
                                                    <template #subtitle>{{ entityUnitSubtitle(item.raw) }}</template>
                                                </v-list-item>
                                            </template>
                                            <template #selection="{ item }">
                                                <div class="entity-selection">
                                                    <strong>{{ item.raw.name }}</strong>
                                                    <span>{{ entityUnitSubtitle(item.raw) }}</span>
                                                </div>
                                            </template>
                                        </v-autocomplete>

                                        <v-btn
                                            class="entity-create-button"
                                            color="#386145"
                                            variant="tonal"
                                            prepend-icon="mdi-domain-plus"
                                            text="Создать Entity"
                                            :disabled="creatingEntity"
                                            @click="showEntityCreator"
                                        />
                                    </div>

                                    <div v-if="entityCreatorOpen" class="entity-creator">
                                        <div class="entity-creator__header">
                                            <div>
                                                <strong>Новый Entity</strong>
                                                <small>После создания он будет выбран в чеке</small>
                                            </div>
                                            <v-btn icon="mdi-close" variant="text" density="compact" @click="hideEntityCreator" />
                                        </div>
                                        <div class="entity-creator__fields">
                                            <v-text-field
                                                v-model="newEntityForm.name"
                                                label="Короткое название *"
                                                variant="outlined"
                                                density="compact"
                                                autofocus
                                                :error-messages="validationMessage(newEntityErrors, 'name')"
                                                @keyup.enter="createEntity"
                                            />
                                            <v-text-field
                                                v-model="newEntityForm.full_name"
                                                label="Полное название"
                                                variant="outlined"
                                                density="compact"
                                                :error-messages="validationMessage(newEntityErrors, 'full_name')"
                                            />
                                            <v-text-field
                                                v-model="newEntityForm.INN"
                                                label="ИНН"
                                                variant="outlined"
                                                density="compact"
                                                :error-messages="validationMessage(newEntityErrors, 'INN')"
                                                @keyup.enter="createEntity"
                                            />
                                            <v-btn
                                                color="#386145"
                                                variant="flat"
                                                prepend-icon="mdi-check"
                                                text="Создать и выбрать"
                                                :loading="creatingEntity"
                                                @click="createEntity"
                                            />
                                        </div>
                                        <div v-if="newEntitySubmitError" class="form-inline-error">
                                            {{ newEntitySubmitError }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="check-form-section check-form-section--totals">
                            <div class="check-form-section__title">
                                <span class="check-form-step">2</span>
                                <div>
                                    <strong>Суммы</strong>
                                    <small>Независимые значения</small>
                                </div>
                            </div>

                            <div class="check-total-card check-total-card--manual">
                                <div>
                                    <span>Сумма чека</span>
                                    <small>Вводится вручную</small>
                                </div>
                                <v-text-field
                                    v-model="checkForm.amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    :error="Boolean(validationMessage(checkErrors, 'amount'))"
                                />
                            </div>

                            <div class="check-total-card check-total-card--calculated">
                                <div>
                                    <span>Сумма позиций</span>
                                    <small>
                                        {{ checkForm.id ? 'Состав редактируется отдельно' : 'Commodities + услуги' }}
                                    </small>
                                </div>
                                <strong v-if="!checkForm.id">{{ formatMoney(draftPositionsTotal) }}</strong>
                                <v-icon v-else icon="mdi-open-in-new" color="#4f7356" />
                            </div>

                            <div v-if="!checkForm.id" class="check-total-breakdown">
                                <span>
                                    <v-icon icon="mdi-package-variant-closed" size="14" />
                                    Commodities
                                    <strong>{{ formatMoney(draftCommodityTotal) }}</strong>
                                </span>
                                <span>
                                    <v-icon icon="mdi-handshake-outline" size="14" />
                                    Услуги
                                    <strong>{{ formatMoney(draftServiceTotal) }}</strong>
                                </span>
                            </div>

                            <div v-if="!checkForm.id" class="check-total-difference">
                                <span>Разница с позициями</span>
                                <strong :class="{ 'is-negative': draftAmountDifference < 0 }">
                                    {{ formatMoney(draftAmountDifference) }}
                                </strong>
                            </div>
                            <small v-if="validationMessage(checkErrors, 'amount')" class="form-inline-error">
                                {{ validationMessage(checkErrors, 'amount') }}
                            </small>
                        </section>
                    </div>

                    <section v-if="!checkForm.id" class="check-form-section check-form-section--items">
                        <div class="items-section-header">
                            <div class="check-form-section__title">
                                <span class="check-form-step">3</span>
                                <div>
                                    <strong>Позиции чека</strong>
                                    <small>Добавьте Commodities и услуги перед сохранением</small>
                                </div>
                            </div>
                            <div class="items-section-summary">
                                <span>{{ draftReceiptRows.length }} поз.</span>
                                <strong>{{ formatMoney(draftPositionsTotal) }}</strong>
                            </div>
                        </div>

                        <div class="draft-kind-bar">
                            <v-btn-toggle
                                :model-value="draftLineKind"
                                mandatory
                                divided
                                density="compact"
                                color="#386145"
                                @update:model-value="switchDraftLineKind"
                            >
                                <v-btn
                                    value="commodity"
                                    prepend-icon="mdi-package-variant-closed"
                                    text="Commodity"
                                />
                                <v-btn
                                    value="service"
                                    prepend-icon="mdi-handshake-outline"
                                    text="Услуга"
                                />
                            </v-btn-toggle>
                            <span>
                                {{ draftLineKind === 'commodity'
                                    ? 'Товарная строка влияет на складской остаток'
                                    : 'Услуга сохраняется без складского движения' }}
                            </span>
                        </div>

                        <div
                            class="draft-line-editor"
                            :class="{ 'draft-line-editor--service': draftLineKind === 'service' }"
                        >
                            <v-autocomplete
                                v-if="draftLineKind === 'commodity'"
                                class="draft-line-editor__item"
                                :model-value="draftCommodityForm.commodity_id"
                                :items="commodities"
                                item-title="name"
                                item-value="id"
                                label="Commodity *"
                                variant="outlined"
                                density="compact"
                                hide-details
                                @update:model-value="selectDraftCommodity"
                            >
                                <template #item="{ props, item }">
                                    <v-list-item v-bind="props" :title="item.raw.name">
                                        <template #prepend>
                                            <v-avatar size="30" rounded="lg">
                                                <v-img :src="item.raw.ava_url || logo" cover />
                                            </v-avatar>
                                        </template>
                                        <template #subtitle>
                                            {{ item.raw.expense_article?.name || 'без статьи' }}
                                            <span v-if="item.raw.project"> · {{ item.raw.project.name }}</span>
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-autocomplete>

                            <v-autocomplete
                                v-else
                                class="draft-line-editor__item"
                                :model-value="draftServiceForm.service_id"
                                :items="services"
                                item-title="name"
                                item-value="id"
                                label="Услуга *"
                                variant="outlined"
                                density="compact"
                                hide-details
                                @update:model-value="selectDraftService"
                            >
                                <template #item="{ props, item }">
                                    <v-list-item v-bind="props" :title="item.raw.name">
                                        <template #prepend>
                                            <v-avatar size="30" rounded="lg" color="#e8f1ed">
                                                <v-icon icon="mdi-handshake-outline" size="19" color="#386145" />
                                            </v-avatar>
                                        </template>
                                        <template #subtitle>
                                            {{ item.raw.expense_article?.name || 'без статьи' }}
                                            <span v-if="item.raw.project"> · {{ item.raw.project.name }}</span>
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-autocomplete>

                            <v-select
                                v-if="draftLineKind === 'commodity'"
                                v-model="draftCommodityForm.warehouse_id"
                                :items="warehouses"
                                item-title="name"
                                item-value="id"
                                label="Склад"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                            <v-text-field
                                v-model="activeDraftForm.quantity"
                                type="number"
                                min="0.001"
                                step="any"
                                label="Количество *"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                            <v-select
                                v-model="activeDraftForm.measure_id"
                                :items="measures"
                                item-title="name"
                                item-value="id"
                                label="Мера"
                                variant="outlined"
                                density="compact"
                                clearable
                                hide-details
                            />
                            <v-text-field
                                v-model="activeDraftForm.price"
                                type="number"
                                min="0"
                                step="0.01"
                                label="Цена *"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                            <v-autocomplete
                                v-model="activeDraftForm.expense_article_id"
                                class="draft-line-editor__article"
                                :items="expenseArticles"
                                item-title="name"
                                item-value="id"
                                label="Статья расходов"
                                variant="outlined"
                                density="compact"
                                clearable
                                hide-details
                            />
                            <v-btn
                                class="draft-line-editor__submit"
                                color="#17845f"
                                variant="flat"
                                :prepend-icon="activeDraftEditingIndex === null ? 'mdi-plus' : 'mdi-check'"
                                :text="activeDraftEditingIndex === null ? 'Добавить' : 'Обновить'"
                                @click="saveDraftLine"
                            />
                            <v-btn
                                v-if="activeDraftEditingIndex !== null"
                                icon="mdi-close"
                                variant="text"
                                density="compact"
                                title="Отменить редактирование"
                                @click="resetActiveDraftForm"
                            />
                        </div>

                        <div v-if="draftLineError" class="form-inline-error draft-line-error">
                            <v-icon icon="mdi-alert-circle-outline" size="16" />
                            {{ draftLineError }}
                        </div>

                        <div class="draft-lines-table-wrap">
                            <table class="draft-lines-table">
                                <thead>
                                    <tr>
                                        <th>Позиция</th>
                                        <th>Тип / учёт</th>
                                        <th>Количество</th>
                                        <th>Цена</th>
                                        <th>Сумма строки</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!draftReceiptRows.length">
                                        <td colspan="6" class="draft-lines-empty">
                                            <div class="draft-lines-empty__content">
                                                <v-icon icon="mdi-receipt-text-plus-outline" size="24" />
                                                <span>Позиций пока нет</span>
                                                <small>Чек можно сохранить без Commodities и услуг</small>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-for="entry in draftReceiptRows" :key="entry.row._key">
                                        <td>
                                            <div class="draft-commodity-cell">
                                                <v-avatar
                                                    v-if="entry.kind === 'commodity'"
                                                    size="34"
                                                    rounded="lg"
                                                >
                                                    <v-img :src="commodityById(entry.row.commodity_id)?.ava_url || logo" cover />
                                                </v-avatar>
                                                <v-avatar v-else size="34" rounded="lg" color="#e8f1ed">
                                                    <v-icon icon="mdi-handshake-outline" size="19" color="#386145" />
                                                </v-avatar>
                                                <div>
                                                    <strong v-if="entry.kind === 'commodity'">
                                                        {{ commodityById(entry.row.commodity_id)?.name || `Commodity #${entry.row.commodity_id}` }}
                                                    </strong>
                                                    <strong v-else>
                                                        {{ serviceById(entry.row.service_id)?.name || `Услуга #${entry.row.service_id}` }}
                                                    </strong>
                                                    <small>
                                                        {{ (entry.kind === 'commodity'
                                                            ? commodityById(entry.row.commodity_id)?.project?.name
                                                            : serviceById(entry.row.service_id)?.project?.name) || 'Без проекта' }}
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong v-if="entry.kind === 'commodity'">
                                                {{ warehouseById(entry.row.warehouse_id)?.name || 'Склад по умолчанию' }}
                                            </strong>
                                            <span v-else class="draft-row-kind">
                                                <v-icon icon="mdi-handshake-outline" size="13" />
                                                Услуга
                                            </span>
                                            <small>{{ expenseArticleById(entry.row.expense_article_id)?.name || 'Без статьи' }}</small>
                                        </td>
                                        <td>
                                            {{ formatQty(entry.row.quantity) }}
                                            <small>{{ measureById(entry.row.measure_id)?.name || 'ед.' }}</small>
                                        </td>
                                        <td>{{ formatMoney(entry.row.price) }}</td>
                                        <td class="draft-line-total">{{ formatMoney(draftLineTotal(entry.row)) }}</td>
                                        <td>
                                            <div class="row-actions">
                                                <v-btn
                                                    icon="mdi-pencil-outline"
                                                    size="small"
                                                    variant="text"
                                                    title="Редактировать строку"
                                                    @click="editDraftReceiptRow(entry)"
                                                />
                                                <v-btn
                                                    icon="mdi-delete-outline"
                                                    size="small"
                                                    variant="text"
                                                    color="error"
                                                    title="Удалить строку"
                                                    @click="removeDraftReceiptRow(entry)"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <v-alert
                        v-else
                        type="info"
                        variant="tonal"
                        density="compact"
                        icon="mdi-information-outline"
                    >
                        Состав сохранённого чека редактируется в окне просмотра чека.
                    </v-alert>

                    <v-alert
                        v-if="checkSubmitError"
                        class="mt-4"
                        type="error"
                        variant="tonal"
                        density="compact"
                    >
                        {{ checkSubmitError }}
                    </v-alert>
                </v-card-text>

                <v-card-actions class="check-form-actions">
                    <div v-if="!checkForm.id" class="check-form-actions__summary">
                        <span>Сумма чека: <strong>{{ formatMoney(checkForm.amount) }}</strong></span>
                        <span>Commodities: <strong>{{ formatMoney(draftCommodityTotal) }}</strong></span>
                        <span>Услуги: <strong>{{ formatMoney(draftServiceTotal) }}</strong></span>
                        <span>Позиции: <strong>{{ formatMoney(draftPositionsTotal) }}</strong></span>
                    </div>
                    <v-spacer />
                    <v-btn text="Отмена" variant="text" @click="checkDialog = false" />
                    <v-btn
                        color="#7f5f00"
                        prepend-icon="mdi-content-save-outline"
                        text="Сохранить Check"
                        variant="flat"
                        :loading="savingCheck"
                        :disabled="!canSaveCheck"
                        @click="saveCheck"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="detailDialog" max-width="1280" scrollable>
            <v-card class="check-detail-modal">
                <v-card-title class="modal-title modal-title--receipt">
                    <div>
                        <span>Check #{{ selectedCheck?.id || '-' }}</span>
                        <small>{{ formatDate(selectedCheck?.date) }} · {{ selectedCheck?.entity?.name || 'Без entity' }}</small>
                    </div>
                    <div class="receipt-summary">
                        <div class="receipt-total">{{ formatMoney(selectedCheck?.amount) }}</div>
                        <small>
                            позиции: {{ formatMoney(registeredPositionsTotal) }} ·
                            товары: {{ formatMoney(registeredCommodityTotal) }} ·
                            услуги: {{ formatMoney(registeredServiceTotal) }}
                        </small>
                    </div>
                    <v-btn icon="mdi-close" variant="text" density="compact" @click="detailDialog = false" />
                </v-card-title>

                <v-card-text>
                    <v-progress-linear
                        v-if="selectedCheckLoading"
                        indeterminate
                        color="#7f5f00"
                        height="2"
                        class="mb-3"
                    />

                    <div class="line-editor">
                        <v-row dense align="center">
                            <v-col cols="12" md="2">
                                <v-btn-toggle
                                    v-model="lineForm.kind"
                                    mandatory
                                    density="compact"
                                    variant="outlined"
                                    class="line-kind-toggle"
                                >
                                    <v-btn value="commodity" icon="mdi-package-variant-closed" title="Commodity" />
                                    <v-btn value="service" icon="mdi-handshake-outline" title="Услуга" />
                                </v-btn-toggle>
                            </v-col>
                            <v-col v-if="lineForm.kind === 'commodity'" cols="12" md="3">
                                <v-autocomplete
                                    v-model="lineForm.commodity_id"
                                    :items="commodities"
                                    item-title="name"
                                    item-value="id"
                                    label="Commodity"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details
                                >
                                    <template #item="{ props, item }">
                                        <v-list-item v-bind="props" :title="item.raw.name">
                                            <template #prepend>
                                                <v-avatar size="30" rounded="lg">
                                                    <v-img :src="item.raw.ava_url || logo" cover />
                                                </v-avatar>
                                            </template>
                                            <template #subtitle>
                                                {{ item.raw.expense_article?.name || 'без статьи' }}
                                                <span v-if="item.raw.project"> · {{ item.raw.project.name }}</span>
                                            </template>
                                        </v-list-item>
                                    </template>
                                </v-autocomplete>
                            </v-col>
                            <v-col v-else cols="12" md="3">
                                <v-autocomplete
                                    v-model="lineForm.service_id"
                                    :items="services"
                                    item-title="name"
                                    item-value="id"
                                    label="Услуга"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details
                                >
                                    <template #item="{ props, item }">
                                        <v-list-item v-bind="props" :title="item.raw.name">
                                            <template #prepend>
                                                <v-icon icon="mdi-handshake-outline" size="22" />
                                            </template>
                                            <template #subtitle>
                                                {{ item.raw.expense_article?.name || 'без статьи' }}
                                                <span v-if="item.raw.project"> · {{ item.raw.project.name }}</span>
                                            </template>
                                        </v-list-item>
                                    </template>
                                </v-autocomplete>
                            </v-col>
                            <v-col v-if="lineForm.kind === 'commodity'" cols="6" md="2">
                                <v-select
                                    v-model="lineForm.warehouse_id"
                                    :items="warehouses"
                                    item-title="name"
                                    item-value="id"
                                    label="Склад"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col cols="6" md="1">
                                <v-text-field
                                    v-model="lineForm.quantity"
                                    label="Кол-во"
                                    type="number"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-select
                                    v-model="lineForm.measure_id"
                                    :items="measures"
                                    item-title="name"
                                    item-value="id"
                                    label="Мера"
                                    variant="solo-filled"
                                    density="compact"
                                    clearable
                                    hide-details
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model="lineForm.price"
                                    label="Цена"
                                    type="number"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-autocomplete
                                    v-model="lineForm.expense_article_id"
                                    :items="expenseArticles"
                                    item-title="name"
                                    item-value="id"
                                    label="Статья"
                                    variant="solo-filled"
                                    density="compact"
                                    clearable
                                    hide-details
                                />
                            </v-col>
                            <v-col cols="12" md="1">
                                <div class="line-editor__actions">
                                    <v-btn
                                        :icon="lineForm.id ? 'mdi-content-save-outline' : 'mdi-plus'"
                                        color="#17845f"
                                        variant="flat"
                                        density="compact"
                                        :loading="savingLine"
                                        title="Сохранить строку"
                                        @click="saveLine"
                                    />
                                    <v-btn
                                        v-if="lineForm.id"
                                        icon="mdi-close"
                                        variant="text"
                                        density="compact"
                                        title="Отменить редактирование"
                                        @click="resetLineForm"
                                    />
                                </div>
                            </v-col>
                        </v-row>
                    </div>

                    <div class="receipt-table-wrap">
                        <table class="receipt-table">
                            <thead>
                                <tr>
                                    <th class="avatar-col"></th>
                                    <th class="commodity-col">Commodity</th>
                                    <th>Статья расходов</th>
                                    <th>Склад / Project</th>
                                    <th>Кол-во</th>
                                    <th>Цена</th>
                                    <th>Итого</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!receiptRows.length">
                                    <td colspan="8" class="state-cell">В этом check пока нет строк.</td>
                                </tr>
                                <tr v-for="row in receiptRows" :key="row.key">
                                    <td>
                                        <v-avatar v-if="row.kind === 'commodity'" size="38" rounded="lg">
                                            <v-img :src="row.item.commodity?.ava_url || logo" cover />
                                        </v-avatar>
                                        <v-avatar v-else size="38" rounded="lg" color="#eef1e8">
                                            <v-icon icon="mdi-handshake-outline" size="21" />
                                        </v-avatar>
                                    </td>
                                    <td class="receipt-commodity-cell">
                                        <a
                                            v-if="row.kind === 'commodity' && commodityHref(row.item)"
                                            :href="commodityHref(row.item)"
                                            class="receipt-item-link"
                                        >
                                            {{ rowTitle(row) }}
                                        </a>
                                        <strong v-else>{{ rowTitle(row) }}</strong>
                                        <small>
                                            {{ row.kind === 'commodity' ? `Commodity #${row.item.commodity_id}` : `Услуга #${row.item.service_id}` }}
                                        </small>
                                    </td>
                                    <td>
                                        <span
                                            class="article-pill"
                                            :style="{ '--article-color': articleColor(row.item.expense_article || row.item.commodity?.expense_article || row.item.service?.expense_article) }"
                                        >
                                            {{ row.item.expense_article?.name || row.item.commodity?.expense_article?.name || row.item.service?.expense_article?.name || '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong v-if="row.kind === 'commodity'">{{ row.item.warehouse?.name || '-' }}</strong>
                                        <strong v-else>{{ row.item.service?.project?.name || '-' }}</strong>
                                        <small>{{ row.kind === 'commodity' ? row.item.commodity?.project?.name || 'без проекта' : 'проект услуги' }}</small>
                                    </td>
                                    <td>
                                        {{ formatQty(row.item.quantity) }}
                                        <span class="muted">{{ row.item.measure?.name || '' }}</span>
                                    </td>
                                    <td>{{ formatMoney(row.item.price) }}</td>
                                    <td class="cell-money">{{ formatMoney(row.item.total_price || numeric(row.item.quantity) * numeric(row.item.price)) }}</td>
                                    <td>
                                        <div class="row-actions">
                                            <v-btn
                                                icon="mdi-pencil-outline"
                                                size="small"
                                                variant="text"
                                                title="Редактировать строку"
                                                @click="editReceiptRow(row)"
                                            />
                                            <v-btn
                                                icon="mdi-delete-outline"
                                                size="small"
                                                variant="text"
                                                color="error"
                                                title="Удалить строку"
                                                @click="deleteReceiptRow(row)"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <v-card
                        v-if="selectedCheck?.logistics_trips?.length || selectedCheck?.logistics_expenses?.length"
                        class="mt-4"
                        variant="outlined"
                    >
                        <v-card-title class="d-flex align-center justify-space-between">
                            <span>Связи с логистикой</span>
                            <Link v-if="canViewLogistics" :href="route('Ameise.logistics')" class="text-decoration-none">
                                <v-btn size="small" variant="tonal" color="green-darken-2" prepend-icon="mdi-truck-fast-outline">Открыть логистику</v-btn>
                            </Link>
                        </v-card-title>
                        <v-card-text>
                            <div class="d-flex flex-wrap ga-2 mb-3">
                                <v-chip v-for="trip in selectedCheck.logistics_trips" :key="trip.id" color="green" variant="tonal">
                                    {{ trip.number }} · {{ trip.status }}
                                </v-chip>
                            </div>
                            <v-table density="compact">
                                <thead><tr><th>Рейс</th><th>Категория</th><th>Дата snapshot</th><th class="text-right">Распределено</th></tr></thead>
                                <tbody>
                                    <tr v-for="expense in selectedCheck.logistics_expenses" :key="expense.id">
                                        <td>{{ selectedCheck.logistics_trips?.find(trip => trip.id === expense.trip_id)?.number || `#${expense.trip_id}` }}</td>
                                        <td>{{ expense.category?.name || '—' }}</td>
                                        <td>{{ formatDate(expense.occurred_at) }}</td>
                                        <td class="text-right">{{ formatMoney(expense.allocated_amount) }} {{ expense.currency_code }}</td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </v-card-text>
                    </v-card>
                </v-card-text>
            </v-card>
        </v-dialog>

        <v-dialog v-model="dictionaryDialog" width="940" scrollable>
            <v-card class="dictionary-modal">
                <v-card-title class="modal-title">
                    <span>{{ dictionaryTitle }}</span>
                    <div class="dictionary-tabs">
                        <v-btn-toggle v-model="dictionaryType" mandatory density="compact" variant="outlined">
                            <v-btn value="articles" text="Статьи" />
                            <v-btn value="projects" text="Проекты" />
                            <v-btn value="services" text="Услуги" />
                        </v-btn-toggle>
                    </div>
                    <v-btn icon="mdi-close" variant="text" density="compact" @click="dictionaryDialog = false" />
                </v-card-title>

                <v-card-text>
                    <div class="dictionary-form">
                        <v-row dense>
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="dictionaryForm.name"
                                    label="Название"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model="dictionaryForm.code"
                                    label="Код"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col v-if="dictionaryType === 'articles'" cols="6" md="2">
                                <v-text-field
                                    v-model="dictionaryForm.color"
                                    label="Цвет"
                                    type="color"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col v-if="dictionaryType === 'articles'" cols="6" md="2">
                                <v-text-field
                                    v-model="dictionaryForm.sort_order"
                                    label="Порядок"
                                    type="number"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col v-if="dictionaryType === 'services'" cols="6" md="3">
                                <v-autocomplete
                                    v-model="dictionaryForm.expense_article_id"
                                    :items="expenseArticles"
                                    item-title="name"
                                    item-value="id"
                                    label="Статья"
                                    variant="outlined"
                                    density="compact"
                                    clearable
                                    hide-details
                                />
                            </v-col>
                            <v-col v-if="dictionaryType === 'services'" cols="6" md="3">
                                <v-autocomplete
                                    v-model="dictionaryForm.project_id"
                                    :items="projects"
                                    item-title="name"
                                    item-value="id"
                                    label="Project"
                                    variant="outlined"
                                    density="compact"
                                    clearable
                                    hide-details
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-switch
                                    v-model="dictionaryForm.is_active"
                                    color="#17845f"
                                    label="Active"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="dictionaryForm.description"
                                    label="Описание"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                        </v-row>
                        <div class="dictionary-form__actions">
                            <v-btn
                                v-if="dictionaryForm.id"
                                icon="mdi-close"
                                variant="text"
                                density="compact"
                                title="Сбросить"
                                @click="resetDictionaryForm"
                            />
                            <v-btn
                                :prepend-icon="dictionaryForm.id ? 'mdi-content-save-outline' : 'mdi-plus'"
                                :text="dictionaryForm.id ? 'Сохранить' : 'Добавить'"
                                color="#17845f"
                                variant="flat"
                                density="compact"
                                :loading="savingDictionary"
                                @click="saveDictionary"
                            />
                        </div>
                    </div>

                    <table class="dictionary-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Код</th>
                                <th v-if="dictionaryType === 'articles'">Цвет</th>
                                <th v-if="dictionaryType === 'services'">Статья</th>
                                <th v-if="dictionaryType === 'services'">Project</th>
                                <th>Active</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!dictionaryItems.length">
                                <td :colspan="dictionaryColspan" class="state-cell">
                                    Справочник пуст.
                                </td>
                            </tr>
                            <tr v-for="item in dictionaryItems" :key="item.id">
                                <td>#{{ item.id }}</td>
                                <td>{{ item.name }}</td>
                                <td>{{ item.code || '-' }}</td>
                                <td v-if="dictionaryType === 'articles'">
                                    <span class="color-swatch" :style="{ backgroundColor: item.color || '#cbd5e1' }"></span>
                                </td>
                                <td v-if="dictionaryType === 'services'">{{ item.expense_article?.name || '-' }}</td>
                                <td v-if="dictionaryType === 'services'">{{ item.project?.name || '-' }}</td>
                                <td>{{ item.is_active ? 'да' : 'нет' }}</td>
                                <td>
                                    <div class="row-actions">
                                        <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="editDictionary(item)" />
                                        <v-btn
                                            icon="mdi-delete-outline"
                                            size="small"
                                            variant="text"
                                            color="error"
                                            @click="deleteDictionary(item)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </v-card-text>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<style scoped>
.checks-page {
    align-self: stretch;
    width: 100%;
    min-height: calc(100vh - 48px);
    background: #f4f1e8;
    color: #231b12;
}

.checks-shell {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    padding: 12px;
}

.checks-toolbar {
    display: grid;
    grid-template-columns: minmax(170px, 1fr) auto auto;
    gap: 12px;
    align-items: center;
    padding: 8px 10px;
    border: 1px solid #c7b894;
    background: #fffaf0;
}

.checks-toolbar__title {
    display: flex;
    align-items: baseline;
    gap: 8px;
    font-size: 22px;
    font-weight: 900;
}

.checks-toolbar__title strong {
    color: #17845f;
    font-size: 16px;
}

.checks-kpis {
    display: grid;
    grid-template-columns: repeat(3, minmax(90px, 1fr));
    gap: 1px;
    overflow: hidden;
    border: 1px solid #c7b894;
}

.checks-kpis div {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 4px 8px;
    background: #e6ead0;
}

.checks-kpis span {
    color: #6a604d;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

.checks-kpis strong {
    color: #0f8d3c;
    font-size: 15px;
}

.checks-actions,
.row-actions,
.line-editor__actions,
.dictionary-form__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
}

.checks-filter-panel {
    display: grid;
    gap: 8px;
    padding: 8px;
    border: 1px solid #c7b894;
    background: #f7f0df;
}

.checks-date-presets {
    display: flex;
    align-items: center;
    gap: 3px;
    overflow-x: auto;
    scrollbar-width: thin;
}

.checks-date-preset {
    flex: 0 0 auto;
    height: 20px;
    padding: 0 6px;
    border: 1px solid #c7b894;
    border-radius: 2px;
    background: #fffdf7;
    color: #625743;
    cursor: pointer;
    font-size: 9px;
    font-weight: 850;
    line-height: 18px;
    white-space: nowrap;
}

.checks-date-preset:hover {
    border-color: #927326;
    background: #fff8df;
    color: #493913;
}

.checks-date-preset--active {
    border-color: #7f5f00;
    background: #7f5f00;
    color: #ffffff;
}

.checks-date-preset:focus-visible {
    outline: 2px solid #17845f;
    outline-offset: 1px;
}

.checks-filter-grid {
    display: grid;
    grid-template-columns: 130px 130px minmax(220px, 1fr) minmax(180px, 260px) 138px auto auto;
    gap: 8px;
    align-items: center;
}

.checks-project-totals {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 2px;
}

.project-total {
    display: inline-grid;
    grid-template-columns: minmax(90px, 1fr) auto;
    gap: 10px;
    align-items: baseline;
    min-width: 180px;
    padding: 4px 8px;
    border: 1px solid #c7b894;
    background: #ffffff;
    color: #24180f;
    line-height: 1.15;
    text-align: left;
}

.project-total span {
    overflow: hidden;
    font-size: 11px;
    font-weight: 900;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.project-total strong {
    color: #10913d;
    font-size: 12px;
    font-weight: 950;
}

.project-total--active {
    border-color: #7f5f00;
    background: #fff4cc;
}

.checks-table-wrap,
.receipt-table-wrap {
    width: 100%;
    overflow: auto;
    border: 1px solid #b9aa83;
    background: #ffffff;
}

.checks-grid,
.receipt-table,
.dictionary-table {
    width: 100%;
    min-width: 920px;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 12px;
}

.checks-grid th,
.checks-grid td,
.receipt-table th,
.receipt-table td,
.dictionary-table th,
.dictionary-table td {
    overflow: hidden;
    padding: 4px 6px;
    border: 1px solid #d8cfb8;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.checks-grid thead th,
.receipt-table thead th,
.dictionary-table thead th {
    background: #d9d9d9;
    color: #1f1b16;
    font-weight: 900;
    text-align: left;
}

.checks-grid__groups th {
    color: #24180f;
    text-align: center;
    text-transform: uppercase;
}

.group-check {
    background: #9a7600 !important;
    color: #ffffff !important;
}

.group-budget {
    background: #bcd8a8 !important;
}

.group-links {
    background: #9bc4df !important;
}

.group-actions {
    background: #e9b8ba !important;
}

.checks-grid tbody tr:nth-child(odd),
.receipt-table tbody tr:nth-child(odd),
.dictionary-table tbody tr:nth-child(odd) {
    background: #fff4cc;
}

.checks-grid tbody tr:nth-child(even),
.receipt-table tbody tr:nth-child(even),
.dictionary-table tbody tr:nth-child(even) {
    background: #ffffff;
}

.checks-grid tbody tr:hover,
.receipt-table tbody tr:hover,
.dictionary-table tbody tr:hover {
    background: #e8f1df;
}

.checks-grid__row {
    cursor: pointer;
}

.checks-col-id {
    width: 64px;
}

.checks-col-date {
    width: 148px;
}

.checks-col-money {
    width: 160px;
}

.checks-col-count {
    width: 72px;
}

.checks-col-article,
.checks-col-project {
    width: 136px;
}

.checks-col-actions {
    width: 118px;
}

.checks-col-entity {
    width: var(--checks-entity-col-width, auto);
}

.col-id {
    font-size: 10px;
}

.col-date {
    font-size: 11px;
}

.col-entity {
    position: relative;
    width: auto;
    padding-right: 12px !important;
}

.col-entity__label {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.column-resize-handle {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 3;
    width: 9px;
    cursor: col-resize;
    user-select: none;
}

.column-resize-handle::after {
    position: absolute;
    top: 5px;
    right: 3px;
    bottom: 5px;
    width: 2px;
    background: transparent;
    content: "";
}

.col-entity:hover .column-resize-handle::after,
.column-resize-handle:hover::after,
.checks-page--resizing-column .column-resize-handle::after {
    background: #7f5f00;
}

.checks-page--resizing-column {
    cursor: col-resize;
    user-select: none;
}

:global(body.checks-resizing-column) {
    cursor: col-resize;
    user-select: none;
}

.cell-id {
    color: #806100;
    font-family: "JetBrains Mono", monospace;
    font-size: 10px;
    font-weight: 900;
    padding-right: 2px !important;
    padding-left: 2px !important;
    text-align: center;
}

.cell-date {
    color: #5f574a;
    font-size: 11px;
    font-weight: 800;
}

.cell-money {
    background: #ecf8e7;
    color: #10913d;
    font-weight: 900;
    text-align: right;
}

.cell-count {
    color: #244a76;
    font-weight: 800;
    text-align: center;
}

.entity-button {
    display: flex;
    flex-direction: column;
    max-width: 100%;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: default;
    line-height: 1.15;
    text-align: left;
}

.entity-link {
    align-self: flex-start;
    color: inherit;
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    text-decoration: none;
    white-space: nowrap;
}

.entity-link:hover strong {
    text-decoration: underline;
}

.entity-button strong,
.entity-button span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-button span,
.muted {
    color: #756b59;
    font-size: 11px;
}

.state-cell {
    padding: 14px !important;
    color: #756b59;
    text-align: center;
}

.check-form-modal {
    overflow: hidden;
    border: 1px solid #d7ccb2;
    border-radius: 18px !important;
    background: #f8f6ef;
}

.check-form-header {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 12px;
    align-items: center;
    min-height: 74px;
    padding: 14px 18px !important;
    border-bottom: 1px solid #ded5c0;
    background: linear-gradient(135deg, #fffdf7 0%, #f2ead6 100%);
    white-space: normal;
}

.check-form-header__icon {
    display: grid;
    width: 44px;
    height: 44px;
    place-items: center;
    border-radius: 13px;
    background: #7f5f00;
    color: #ffffff;
}

.check-form-header > div:nth-child(2) {
    min-width: 0;
}

.check-form-header span {
    display: block;
    font-size: 22px;
    font-weight: 900;
    line-height: 1.15;
}

.check-form-header small {
    display: block;
    margin-top: 3px;
    color: #766b57;
    font-size: 12px;
    font-weight: 600;
}

.check-form-body {
    display: grid;
    grid-auto-rows: max-content;
    gap: 14px;
    align-content: start;
    padding: 16px !important;
    background:
        radial-gradient(circle at top right, rgb(127 95 0 / 6%), transparent 280px),
        #f8f6ef;
}

.check-form-overview {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 14px;
    align-items: stretch;
}

.check-form-section {
    min-width: 0;
    padding: 14px;
    border: 1px solid #ded5c0;
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 4px 18px rgb(57 45 21 / 5%);
}

.check-form-section__title {
    display: flex;
    gap: 10px;
    align-items: center;
    min-width: 0;
}

.check-form-section__title > div {
    min-width: 0;
}

.check-form-section__title strong,
.check-form-section__title small {
    display: block;
}

.check-form-section__title strong {
    color: #2d2519;
    font-size: 14px;
    font-weight: 900;
}

.check-form-section__title small {
    color: #887c67;
    font-size: 11px;
    font-weight: 600;
}

.check-form-step {
    display: grid;
    flex: 0 0 28px;
    width: 28px;
    height: 28px;
    place-items: center;
    border-radius: 9px;
    background: #f1e8cc;
    color: #795b00;
    font-size: 12px;
    font-weight: 950;
}

.check-details-grid {
    display: grid;
    grid-template-columns: 190px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
    margin-top: 14px;
}

.entity-picker,
.entity-creator {
    min-width: 0;
}

.entity-picker__control {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    align-items: start;
}

.entity-create-button {
    min-height: 48px;
}

.entity-selection {
    display: flex;
    min-width: 0;
    flex-direction: column;
    line-height: 1.15;
}

.entity-selection strong,
.entity-selection span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-selection strong {
    font-size: 13px;
}

.entity-selection span {
    color: #766b57;
    font-size: 10px;
}

.entity-creator {
    margin-top: 8px;
    padding: 12px;
    border: 1px solid #aebda8;
    border-radius: 12px;
    background: #f3f8f0;
}

.entity-creator__header {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    align-items: start;
    margin-bottom: 10px;
}

.entity-creator__header strong,
.entity-creator__header small {
    display: block;
}

.entity-creator__header strong {
    color: #284a34;
    font-size: 13px;
    font-weight: 900;
}

.entity-creator__header small {
    color: #657567;
    font-size: 10px;
}

.entity-creator__fields {
    display: grid;
    grid-template-columns: minmax(140px, 1fr) minmax(170px, 1.2fr) 120px;
    gap: 8px;
    align-items: start;
}

.entity-creator__fields > .v-btn {
    grid-column: 1 / -1;
    justify-self: end;
    min-height: 40px;
}

.check-form-section--totals {
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #fffdf8;
}

.check-total-card {
    display: grid;
    grid-template-columns: 1fr 116px;
    gap: 10px;
    align-items: center;
    padding: 10px 11px;
    border-radius: 11px;
}

.check-total-card span,
.check-total-card small {
    display: block;
}

.check-total-card span {
    font-size: 12px;
    font-weight: 900;
}

.check-total-card small {
    margin-top: 2px;
    color: #756b59;
    font-size: 9px;
    font-weight: 700;
}

.check-total-card--manual {
    border: 1px solid #dbc988;
    background: #fff8df;
}

.check-total-card--calculated {
    border: 1px solid #b9d5b2;
    background: #edf8e9;
}

.check-total-card--calculated > strong {
    color: #10823a;
    font-size: 19px;
    font-weight: 950;
    text-align: right;
}

.check-total-card--calculated > .v-icon {
    justify-self: end;
}

.check-total-breakdown {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
}

.check-total-breakdown > span {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 2px 5px;
    align-items: center;
    padding: 7px 8px;
    border: 1px solid #ded8c9;
    border-radius: 9px;
    background: #ffffff;
    color: #716754;
    font-size: 9px;
    font-weight: 800;
}

.check-total-breakdown strong {
    grid-column: 1 / -1;
    color: #3d493d;
    font-size: 12px;
    font-weight: 950;
}

.check-total-difference {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 4px;
    color: #756b59;
    font-size: 11px;
    font-weight: 800;
}

.check-total-difference strong {
    color: #385f86;
    font-size: 13px;
}

.check-total-difference strong.is-negative {
    color: #bd3e3e;
}

.check-form-section--items {
    padding: 0;
    overflow: hidden;
}

.items-section-header {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    padding: 14px;
    border-bottom: 1px solid #e3dccb;
    background: #fffdf8;
}

.items-section-summary {
    display: flex;
    gap: 10px;
    align-items: baseline;
    padding: 6px 10px;
    border-radius: 9px;
    background: #edf8e9;
}

.items-section-summary span {
    color: #657567;
    font-size: 10px;
    font-weight: 800;
}

.items-section-summary strong {
    color: #10823a;
    font-size: 15px;
    font-weight: 950;
}

.draft-kind-bar {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 10px 14px 0;
    background: #f5f1e7;
}

.draft-kind-bar > span {
    color: #756b59;
    font-size: 10px;
    font-weight: 700;
}

.draft-line-editor {
    display: grid;
    grid-template-columns: minmax(230px, 1.5fr) 140px 96px 110px 110px minmax(160px, 1fr) auto auto;
    gap: 8px;
    align-items: center;
    padding: 12px 14px;
    background: #f5f1e7;
}

.draft-line-editor--service {
    grid-template-columns: minmax(260px, 1.8fr) 96px 110px 110px minmax(160px, 1fr) auto auto;
}

.draft-line-editor__submit {
    min-height: 40px;
}

.form-inline-error {
    color: #b3261e;
    font-size: 11px;
    font-weight: 750;
}

.draft-line-error {
    display: flex;
    gap: 5px;
    align-items: center;
    padding: 0 14px 10px;
    background: #f5f1e7;
}

.draft-lines-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.draft-lines-table {
    width: 100%;
    min-width: 820px;
    border-collapse: collapse;
    table-layout: auto;
    font-size: 12px;
}

.draft-lines-table th,
.draft-lines-table td {
    padding: 8px 10px;
    border-top: 1px solid #e3dccb;
    text-align: left;
    vertical-align: middle;
}

.draft-lines-table th {
    background: #ebe7dc;
    color: #645a49;
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.draft-lines-table tbody tr:hover {
    background: #fbfaf5;
}

.draft-lines-table td strong,
.draft-lines-table td small {
    display: block;
}

.draft-lines-table td small {
    color: #7a705f;
    font-size: 10px;
}

.draft-lines-empty {
    padding: 22px !important;
    color: #756b59;
    text-align: center !important;
}

.draft-lines-empty__content {
    width: 100%;
}

.draft-lines-empty .v-icon,
.draft-lines-empty span,
.draft-lines-empty small {
    display: block;
    margin: 0 auto;
}

.draft-lines-empty span {
    margin-top: 5px;
    color: #4d4538;
    font-weight: 850;
}

.draft-commodity-cell {
    display: flex;
    gap: 9px;
    align-items: center;
    min-width: 220px;
}

.draft-commodity-cell > div {
    min-width: 0;
}

.draft-commodity-cell strong {
    overflow: hidden;
    max-width: 310px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.draft-row-kind {
    display: inline-flex;
    gap: 4px;
    align-items: center;
    padding: 3px 7px;
    border-radius: 999px;
    background: #e8f1ed;
    color: #386145;
    font-size: 10px;
    font-weight: 900;
}

.draft-line-total {
    color: #10823a;
    font-size: 13px;
    font-weight: 950;
}

.check-form-actions {
    min-height: 66px;
    padding: 10px 16px !important;
    border-top: 1px solid #ded5c0;
    background: #fffdf8;
}

.check-form-actions__summary {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    color: #756b59;
    font-size: 11px;
}

.check-form-actions__summary strong {
    color: #30271b;
}

.modal-title {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 10px;
    align-items: center;
    border-bottom: 1px solid #d8cfb8;
    background: #fffaf0;
}

.modal-title--receipt {
    grid-template-columns: 1fr auto auto;
}

.modal-title small {
    display: block;
    color: #756b59;
    font-size: 12px;
    font-weight: 500;
}

.receipt-total {
    padding: 4px 10px;
    background: #ecf8e7;
    color: #10913d;
    font-size: 22px;
    font-weight: 950;
}

.receipt-summary {
    display: grid;
    gap: 2px;
    justify-items: end;
}

.receipt-summary small {
    color: #756b59;
    font-size: 9px;
    font-weight: 800;
    line-height: 1;
}

.line-editor,
.dictionary-form {
    margin-bottom: 10px;
    padding: 8px;
    border: 1px solid #c7b894;
    background: #f7f0df;
}

.line-kind-toggle {
    width: 100%;
}

.receipt-table {
    min-width: 1320px;
    table-layout: auto;
}

.receipt-table .avatar-col {
    width: 52px;
}

.receipt-table .commodity-col {
    width: 520px;
    min-width: 520px;
}

.receipt-table td strong,
.receipt-table td small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
}

.receipt-table td small {
    color: #756b59;
}

.receipt-table .receipt-commodity-cell {
    min-width: 520px;
    overflow: visible;
    text-overflow: clip;
    white-space: nowrap;
}

.receipt-table .receipt-commodity-cell strong,
.receipt-table .receipt-commodity-cell small {
    overflow: visible;
    text-overflow: clip;
    white-space: nowrap;
}

.receipt-item-link {
    display: block;
    overflow: visible;
    color: #1b4c8f;
    font-weight: 900;
    text-decoration: none;
    text-overflow: clip;
    white-space: nowrap;
}

.receipt-item-link:hover {
    text-decoration: underline;
}

.article-pill {
    display: inline-flex;
    max-width: 100%;
    padding: 2px 8px;
    border-left: 5px solid var(--article-color);
    background: #ffffff;
    color: #2b2117;
    font-weight: 800;
}

.dictionary-tabs {
    justify-self: end;
}

.dictionary-form {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    align-items: end;
}

.dictionary-table {
    min-width: 760px;
}

.color-swatch {
    display: inline-block;
    width: 28px;
    height: 16px;
    border: 1px solid #948a76;
}

@media (max-width: 1100px) {
    .draft-line-editor,
    .draft-line-editor--service {
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    .draft-line-editor__item {
        grid-column: span 2;
    }

    .draft-line-editor__article {
        grid-column: span 3;
    }

    .draft-line-editor__submit {
        grid-column: span 2;
    }

    .entity-creator__fields {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .entity-creator__fields > .v-btn {
        grid-column: 1 / -1;
    }
}

@media (max-width: 980px) {
    .checks-toolbar {
        grid-template-columns: 1fr;
    }

    .checks-kpis {
        grid-template-columns: repeat(3, 1fr);
    }

    .checks-actions {
        justify-content: flex-start;
        flex-wrap: wrap;
    }

    .checks-filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dictionary-form {
        grid-template-columns: 1fr;
    }

    .check-form-overview {
        grid-template-columns: 1fr;
    }

    .check-form-section--totals {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .check-form-section--totals .check-form-section__title,
    .check-total-breakdown,
    .check-total-difference,
    .check-form-section--totals > .form-inline-error {
        grid-column: 1 / -1;
    }
}

@media (max-width: 680px) {
    .check-form-header {
        grid-template-columns: auto 1fr auto;
        padding: 11px 12px !important;
    }

    .check-form-header__icon {
        width: 38px;
        height: 38px;
    }

    .check-form-header span {
        font-size: 18px;
    }

    .check-form-body {
        padding: 10px !important;
    }

    .check-details-grid,
    .entity-picker__control,
    .entity-creator__fields,
    .check-form-section--totals,
    .draft-line-editor,
    .draft-line-editor--service {
        grid-template-columns: 1fr;
    }

    .draft-line-editor__item,
    .draft-line-editor__article,
    .draft-line-editor__submit,
    .entity-creator__fields > .v-btn {
        grid-column: auto;
        justify-self: stretch;
    }

    .items-section-header,
    .check-form-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .items-section-summary {
        justify-content: space-between;
    }

    .draft-kind-bar {
        align-items: stretch;
        flex-direction: column;
    }

    .draft-kind-bar .v-btn-toggle {
        width: 100%;
    }

    .draft-kind-bar .v-btn {
        flex: 1;
    }

    .draft-lines-empty__content {
        position: sticky;
        left: 0;
        width: calc(100vw - 114px);
    }

    .check-form-actions__summary {
        justify-content: space-between;
    }

    .check-form-actions .v-spacer {
        display: none;
    }
}
</style>
