<script setup>
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { route } from 'ziggy-js'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import { logo } from '@/Pages/Helpers/consts.js'

defineOptions({
    layout: VerwalterLayout,
})

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
const selectedCheckLoading = ref(false)

const checkDialog = ref(false)
const detailDialog = ref(false)
const dictionaryDialog = ref(false)
const dictionaryType = ref('articles')

const selectedCheck = ref(null)

const checkForm = reactive({
    id: null,
    date: today(),
    entity_id: null,
    amount: 0,
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
    const total = checks.value.reduce((sum, check) => sum + numeric(check.amount), 0)
    const items = checks.value.reduce((sum, check) => sum + Number(check.items_count || check.items?.length || 0), 0)

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

function unpack(response) {
    return response?.data?.data || response?.data || []
}

function today() {
    return new Date().toISOString().slice(0, 10)
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

async function loadChecks() {
    loadingChecks.value = true

    try {
        const response = await axios.get(route('checks.index'))

        checks.value = unpack(response)
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
}

function resetCheckForm() {
    checkForm.id = null
    checkForm.date = today()
    checkForm.entity_id = null
    checkForm.amount = 0
}

function openCreateCheck() {
    resetCheckForm()
    checkDialog.value = true
}

function openEditCheck(check) {
    checkForm.id = check.id
    checkForm.date = check.date || today()
    checkForm.entity_id = check.entity_id || null
    checkForm.amount = numeric(check.amount)
    checkDialog.value = true
}

async function saveCheck() {
    savingCheck.value = true

    const payload = {
        date: checkForm.date,
        entity_id: checkForm.entity_id,
        amount: numeric(checkForm.amount),
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
    await Promise.all([
        loadChecks(),
        loadDictionaries(),
    ])

    const checkId = new URL(window.location.href).searchParams.get('check')

    if (checkId) {
        await openCheck({ id: checkId })
    }
})
</script>

<template>
    <v-container fluid class="checks-page pa-0">
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

            <div class="checks-table-wrap">
                <table class="checks-grid">
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
                            <th class="col-entity">Контрагент</th>
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
                                    <a
                                        v-if="entityHref(check)"
                                        :href="entityHref(check)"
                                        class="entity-button entity-link"
                                        @click.stop
                                    >
                                        <strong>{{ entityName(check) }}</strong>
                                        <span>{{ entitySubtitle(check) }}</span>
                                    </a>
                                    <span v-else class="entity-button entity-button--static">
                                        <strong>{{ entityName(check) }}</strong>
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

        <v-dialog v-model="checkDialog" width="720">
            <v-card class="check-form-modal">
                <v-card-title class="modal-title">
                    <span>{{ checkDialogTitle }}</span>
                    <v-btn icon="mdi-close" variant="text" density="compact" @click="checkDialog = false" />
                </v-card-title>
                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="checkForm.date"
                                type="date"
                                label="Дата"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                        </v-col>
                        <v-col cols="12" md="8">
                            <v-autocomplete
                                v-model="checkForm.entity_id"
                                :items="entities"
                                item-title="name"
                                item-value="id"
                                label="Entity"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="checkForm.amount"
                                label="Сумма"
                                type="number"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn text="Отмена" variant="text" @click="checkDialog = false" />
                    <v-btn
                        color="#7f5f00"
                        text="Сохранить"
                        variant="flat"
                        :loading="savingCheck"
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
                    <div class="receipt-total">{{ formatMoney(selectedCheck?.amount) }}</div>
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

.col-id {
    width: 24px;
    font-size: 10px;
}

.col-date {
    width: 112px;
    font-size: 11px;
}

.col-entity {
    width: 38%;
}

.col-money {
    width: 116px;
}

.col-count {
    width: 64px;
}

.col-edit {
    width: 118px;
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
    cursor: pointer;
    line-height: 1.15;
    text-align: left;
}

.entity-link {
    text-decoration: none;
}

.entity-link:hover strong {
    text-decoration: underline;
}

.entity-button--static {
    cursor: default;
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

    .dictionary-form {
        grid-template-columns: 1fr;
    }
}
</style>
