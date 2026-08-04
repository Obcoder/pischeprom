<script setup>
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { route } from 'ziggy-js'

defineOptions({ layout: VerwalterLayout })

const props = defineProps({ importUuid: { type: String, required: true } })
const baseUrl = computed(() => `/api/ai/price-lists/${props.importUuid}`)
const importData = ref(null)
const permissions = ref({})
const loading = ref(true)
const itemsLoading = ref(false)
const actionLoading = ref('')
const activeTab = ref('items')
const items = ref([])
const itemsTotal = ref(0)
const itemsPage = ref(1)
const itemsPerPage = ref(50)
const selectedItemIds = ref([])
const itemFilters = reactive({ search: '', match_class: null, decision_status: null })
const snackbar = reactive({ open: false, text: '', color: 'success' })
const supplierOptions = ref([])
const supplierId = ref(null)
const supplierSearch = ref('')
const bindSource = ref(false)
const editDialog = ref(false)
const editItem = ref(null)
const editForm = reactive({})
const matchDialog = ref(false)
const matchItem = ref(null)
const goodOptions = ref([])
const goodSearch = ref('')
const selectedGoodId = ref(null)
const saveAlias = ref(false)
const defaultsDialog = ref(false)
const defaultsForm = reactive({ currency_code: 'RUB', vat_mode: 'unknown', vat_rate: null })
const defaultsPreview = ref(null)
const applyDialog = ref(false)
const applyPreview = ref(null)
let itemSearchTimer = null
let supplierSearchTimer = null
let goodSearchTimer = null
let pollTimer = null

const itemHeaders = [
    { title: '#', key: 'position', width: 64 },
    { title: 'Исходная строка', key: 'raw_name', minWidth: 285, sortable: false },
    { title: 'Цена', key: 'price', width: 145, sortable: false },
    { title: 'НДС / упаковка', key: 'details', minWidth: 170, sortable: false },
    { title: 'Совпадение', key: 'match', minWidth: 270, sortable: false },
    { title: 'Решение', key: 'decision_status', width: 155, sortable: false },
    { title: '', key: 'actions', width: 160, sortable: false },
]

const matchFilters = [
    { title: 'Точное совпадение', value: 'exact_match' },
    { title: 'Вероятное', value: 'probable_match' },
    { title: 'Нет совпадения', value: 'no_match' },
    { title: 'Конфликт', value: 'conflict' },
    { title: 'Некорректная строка', value: 'invalid_row' },
    { title: 'Пропущено', value: 'ignored' },
]

const decisionFilters = [
    { title: 'Не проверено', value: 'unreviewed' },
    { title: 'Связано', value: 'matched' },
    { title: 'Создать черновик', value: 'create_draft' },
    { title: 'Пропущено', value: 'ignored' },
    { title: 'Применено', value: 'applied' },
]

const processingStatuses = ['received', 'queued', 'validating', 'extracting', 'ocr', 'normalizing', 'matching', 'applying']
const canRetry = computed(() => permissions.value.process && ['failed', 'unsupported_format', 'quarantined', 'cancelled'].includes(importData.value?.status))
const canCancel = computed(() => permissions.value.process && importData.value && !['applied', 'cancelled', 'not_a_price_list', 'applying'].includes(importData.value.status))
const canClassify = computed(() => permissions.value.process && importData.value?.status === 'awaiting_classification')
const canApply = computed(() => permissions.value.apply && ['ready_to_apply', 'partially_applied'].includes(importData.value?.status))
const showPolling = computed(() => processingStatuses.includes(importData.value?.status))
const sourceTitle = computed(() => importData.value?.source_channel === 'email' ? 'Входящее письмо' : 'Сообщение MAX')
const usageTotals = computed(() => (importData.value?.usage || []).reduce((totals, row) => ({
    tokens: totals.tokens + Number(row.total_tokens || 0),
    cost: totals.cost + Number(row.estimated_cost || 0),
    currency: row.cost_currency || totals.currency,
}), { tokens: 0, cost: 0, currency: '' }))

function notify(text, color = 'success') {
    snackbar.text = text
    snackbar.color = color
    snackbar.open = true
}

function requestMessage(error, fallback) {
    const errors = error.response?.data?.errors
    return Object.values(errors || {}).flat()[0] || error.response?.data?.message || fallback
}

async function loadImport({ quiet = false } = {}) {
    if (!quiet) loading.value = true
    try {
        const { data } = await axios.get(baseUrl.value)
        importData.value = data.data
        permissions.value = data.permissions || {}
        supplierId.value = data.data.supplier?.id || null
        configurePolling()
    } catch (error) {
        notify(requestMessage(error, 'Не удалось загрузить импорт.'), 'error')
    } finally {
        loading.value = false
    }
}

async function loadItems() {
    itemsLoading.value = true
    try {
        const { data } = await axios.get(`${baseUrl.value}/items`, {
            params: {
                page: itemsPage.value,
                per_page: itemsPerPage.value,
                search: itemFilters.search || undefined,
                match_class: itemFilters.match_class || undefined,
                decision_status: itemFilters.decision_status || undefined,
            },
        })
        items.value = (data.data || []).map((item) => ({
            ...item,
            selected_good_id: item.good_id || item.candidates?.[0]?.good_id || null,
            selectable: ['matched', 'create_draft'].includes(item.decision_status) && !item.applied_at,
        }))
        itemsTotal.value = data.total || 0
    } catch (error) {
        notify(requestMessage(error, 'Не удалось загрузить позиции.'), 'error')
    } finally {
        itemsLoading.value = false
    }
}

function configurePolling() {
    window.clearInterval(pollTimer)
    if (showPolling.value) {
        pollTimer = window.setInterval(async () => {
            await loadImport({ quiet: true })
            if (activeTab.value === 'items') await loadItems()
        }, 6000)
    }
}

async function loadSuppliers(search = '') {
    try {
        const { data } = await axios.get('/api/ai/price-lists/meta/entities', { params: { search } })
        supplierOptions.value = data.data || []
        if (importData.value?.supplier && !supplierOptions.value.some((item) => item.id === importData.value.supplier.id)) {
            supplierOptions.value.unshift(importData.value.supplier)
        }
    } catch (error) {
        notify(requestMessage(error, 'Не удалось найти поставщиков.'), 'error')
    }
}

async function assignSupplier() {
    if (!supplierId.value) return
    actionLoading.value = 'supplier'
    try {
        await axios.patch(`${baseUrl.value}/supplier`, { entity_id: supplierId.value, bind_source: bindSource.value })
        notify(bindSource.value ? 'Поставщик выбран, источник привязан.' : 'Поставщик выбран.')
        await Promise.all([loadImport({ quiet: true }), loadItems()])
    } catch (error) {
        notify(requestMessage(error, 'Не удалось выбрать поставщика.'), 'error')
    } finally {
        actionLoading.value = ''
    }
}

async function classify(classification) {
    if (!window.confirm(classification === 'price_list' ? 'Запустить обработку как прайс-листа?' : 'Пометить файл как не прайс-лист?')) return
    actionLoading.value = 'classify'
    try {
        await axios.post(`${baseUrl.value}/classification`, { classification })
        notify(classification === 'price_list' ? 'Обработка поставлена в очередь.' : 'Файл исключён из обработки.')
        await loadImport({ quiet: true })
    } catch (error) {
        notify(requestMessage(error, 'Не удалось подтвердить тип документа.'), 'error')
    } finally {
        actionLoading.value = ''
    }
}

async function retryStage() {
    actionLoading.value = 'retry'
    try {
        await axios.post(`${baseUrl.value}/retry`)
        notify('Этап поставлен в очередь.')
        await loadImport({ quiet: true })
    } catch (error) {
        notify(requestMessage(error, 'Повтор этапа недоступен.'), 'error')
    } finally {
        actionLoading.value = ''
    }
}

async function cancelImport() {
    if (!window.confirm('Отменить дальнейшую обработку этого импорта? Исходный файл и журнал сохранятся.')) return
    actionLoading.value = 'cancel'
    try {
        await axios.post(`${baseUrl.value}/cancel`)
        notify('Обработка отменена.')
        await loadImport({ quiet: true })
    } catch (error) {
        notify(requestMessage(error, 'Не удалось отменить обработку.'), 'error')
    } finally {
        actionLoading.value = ''
    }
}

function openEdit(item) {
    editItem.value = item
    Object.assign(editForm, {
        raw_name: item.raw_name || '', supplier_sku: item.supplier_sku || '', manufacturer_sku: item.manufacturer_sku || '',
        barcode: item.barcode || '', manufacturer: item.manufacturer || '', brand: item.brand || '', country_of_origin: item.country_of_origin || '',
        package_description: item.package_description || '', units_per_package: item.units_per_package || null, net_quantity: item.net_quantity || null,
        net_quantity_unit: item.net_quantity_unit || null, price_basis_quantity: item.price_basis_quantity || null,
        price_basis_unit: item.price_basis_unit || null, minimum_order_quantity: item.minimum_order_quantity || null,
        price: item.price || null, currency_code: item.currency_code || null, vat_mode: item.vat_mode || 'unknown',
        vat_rate: item.vat_rate || null, availability: item.availability || '', valid_from: item.valid_from || null,
        valid_to: item.valid_to || null, notes: item.notes || '',
    })
    editDialog.value = true
}

async function saveEdit() {
    actionLoading.value = 'edit'
    try {
        await axios.patch(`${baseUrl.value}/items/${editItem.value.id}`, editForm)
        editDialog.value = false
        notify('Исправления сохранены.')
        await Promise.all([loadItems(), loadImport({ quiet: true })])
    } catch (error) {
        notify(requestMessage(error, 'Не удалось сохранить строку.'), 'error')
    } finally {
        actionLoading.value = ''
    }
}

function candidateOptions(item) {
    return (item.candidates || []).map((candidate) => ({
        value: candidate.good_id,
        title: `${candidate.good?.name || `Товар #${candidate.good_id}`} · ${(Number(candidate.score || 0) * 100).toFixed(0)}%`,
    }))
}

async function decide(item, decision, goodId = null, alias = false) {
    actionLoading.value = `item-${item.id}`
    try {
        await axios.post(`${baseUrl.value}/items/${item.id}/decision`, {
            decision,
            good_id: goodId,
            save_alias: alias,
        })
        notify(decision === 'matched' ? 'Товар связан.' : decision === 'create_draft' ? 'Будет создан непубличный черновик.' : decision === 'ignored' ? 'Строка пропущена.' : 'Решение сброшено.')
        await Promise.all([loadItems(), loadImport({ quiet: true })])
    } catch (error) {
        notify(requestMessage(error, 'Не удалось изменить решение.'), 'error')
    } finally {
        actionLoading.value = ''
    }
}

async function openGoodSearch(item) {
    matchItem.value = item
    selectedGoodId.value = item.good_id || item.selected_good_id || null
    saveAlias.value = false
    goodOptions.value = (item.candidates || []).map((candidate) => candidate.good).filter(Boolean)
    goodSearch.value = item.raw_name || ''
    matchDialog.value = true
    await loadGoods(goodSearch.value)
}

async function loadGoods(search = '') {
    if (search.trim().length < 2) return
    try {
        const { data } = await axios.get('/api/ai/price-lists/meta/goods', { params: { search } })
        const merged = [...goodOptions.value, ...(data.data || [])]
        goodOptions.value = Array.from(new Map(merged.map((item) => [item.id, item])).values())
    } catch (error) {
        notify(requestMessage(error, 'Не удалось найти товары.'), 'error')
    }
}

async function confirmGood() {
    if (!selectedGoodId.value) return
    await decide(matchItem.value, 'matched', selectedGoodId.value, saveAlias.value)
    matchDialog.value = false
}

async function bulkConfirmExact() {
    if (!window.confirm('Подтвердить все однозначные строки без конфликтов?')) return
    actionLoading.value = 'bulk-exact'
    try {
        const { data } = await axios.post(`${baseUrl.value}/items/bulk-confirm-exact`)
        notify(`Подтверждено строк: ${data.confirmed}.`)
        await Promise.all([loadItems(), loadImport({ quiet: true })])
    } catch (error) {
        notify(requestMessage(error, 'Массовое подтверждение не выполнено.'), 'error')
    } finally {
        actionLoading.value = ''
    }
}

async function previewDefaults() {
    actionLoading.value = 'defaults-preview'
    try {
        const { data } = await axios.post(`${baseUrl.value}/items/bulk-defaults`, { ...defaultsForm, preview: true })
        defaultsPreview.value = data.data
    } catch (error) {
        notify(requestMessage(error, 'Не удалось рассчитать изменения.'), 'error')
    } finally {
        actionLoading.value = ''
    }
}

async function applyDefaults() {
    actionLoading.value = 'defaults-apply'
    try {
        const { data } = await axios.post(`${baseUrl.value}/items/bulk-defaults`, { ...defaultsForm, preview: false })
        defaultsDialog.value = false
        notify(`Defaults установлены в ${data.data.affected} строках.`)
        await Promise.all([loadItems(), loadImport({ quiet: true })])
    } catch (error) {
        notify(requestMessage(error, 'Не удалось установить defaults.'), 'error')
    } finally {
        actionLoading.value = ''
    }
}

async function openApplyDialog() {
    actionLoading.value = 'preview'
    try {
        const { data } = await axios.get(`${baseUrl.value}/apply-preview`, {
            params: selectedItemIds.value.length ? { item_ids: selectedItemIds.value } : {},
        })
        applyPreview.value = data.data
        applyDialog.value = true
    } catch (error) {
        notify(requestMessage(error, 'Не удалось подготовить сводку.'), 'error')
    } finally {
        actionLoading.value = ''
    }
}

async function applyImport() {
    actionLoading.value = 'apply'
    try {
        await axios.post(`${baseUrl.value}/apply`, {
            confirm: true,
            item_ids: selectedItemIds.value.length ? selectedItemIds.value : undefined,
        })
        applyDialog.value = false
        notify('Изменения поставлены в очередь. Повторная отправка заблокирована.')
        await loadImport({ quiet: true })
    } catch (error) {
        notify(requestMessage(error, 'Применение не запущено.'), 'error')
    } finally {
        actionLoading.value = ''
    }
}

function formatDate(value, dateOnly = false) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('ru-RU', dateOnly ? { dateStyle: 'medium' } : { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function formatBytes(value) {
    const bytes = Number(value || 0)
    if (bytes < 1024) return `${bytes} Б`
    if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} КБ`
    return `${(bytes / 1024 ** 2).toFixed(1)} МБ`
}

function statusColor(status) {
    if (status === 'applied') return 'green'
    if (['failed', 'quarantined', 'unsupported_format'].includes(status)) return 'red'
    if (['review_required', 'supplier_unresolved', 'awaiting_classification', 'ready_to_apply'].includes(status)) return 'amber-darken-2'
    if (processingStatuses.includes(status)) return 'blue'
    return 'grey'
}

function matchColor(value) {
    return { exact_match: 'green', probable_match: 'amber-darken-2', no_match: 'blue-grey', conflict: 'red', invalid_row: 'red', ignored: 'grey' }[value] || 'grey'
}

function matchLabel(value) {
    return { exact_match: 'Точное', probable_match: 'Вероятное', no_match: 'Нет совпадения', conflict: 'Конфликт', invalid_row: 'Некорректно', ignored: 'Пропущено' }[value] || value
}

function decisionLabel(value) {
    return { unreviewed: 'Не проверено', matched: 'Связано', create_draft: 'Создать черновик', ignored: 'Пропущено', invalid: 'Некорректно', applied: 'Применено' }[value] || value
}

function locator(item) {
    return [item.source_sheet && `лист «${item.source_sheet}»`, item.source_page && `стр. ${item.source_page}`, item.source_table && `табл. ${item.source_table}`, item.source_row && `строка ${item.source_row}`, item.source_range].filter(Boolean).join(' · ') || 'Локатор не указан'
}

function goodUrl(good) {
    return good?.id ? route('Ameise.good.show', { id: good.id, slug: good.slug || undefined }) : '#'
}

watch([itemsPage, itemsPerPage], loadItems)
watch(() => [itemFilters.match_class, itemFilters.decision_status], () => { itemsPage.value = 1; loadItems() })
watch(() => itemFilters.search, () => {
    window.clearTimeout(itemSearchTimer)
    itemSearchTimer = window.setTimeout(() => { itemsPage.value = 1; loadItems() }, 350)
})
watch(supplierSearch, (value) => {
    window.clearTimeout(supplierSearchTimer)
    supplierSearchTimer = window.setTimeout(() => loadSuppliers(value || ''), 300)
})
watch(goodSearch, (value) => {
    window.clearTimeout(goodSearchTimer)
    goodSearchTimer = window.setTimeout(() => loadGoods(value || ''), 300)
})
watch(defaultsDialog, (open) => { if (open) { defaultsPreview.value = null; Object.assign(defaultsForm, { currency_code: importData.value?.document_defaults?.currency || 'RUB', vat_mode: ['included', 'excluded'].includes(importData.value?.document_defaults?.vat_mode) ? importData.value.document_defaults.vat_mode : null, vat_rate: importData.value?.document_defaults?.vat_rate || null }) } })

onMounted(async () => {
    await Promise.all([loadImport(), loadItems(), loadSuppliers()])
})

onBeforeUnmount(() => {
    window.clearInterval(pollTimer)
    window.clearTimeout(itemSearchTimer)
    window.clearTimeout(supplierSearchTimer)
    window.clearTimeout(goodSearchTimer)
})
</script>

<template>
    <Head :title="`AI · ${importData?.file?.name || 'Прайс-лист'}`" />

    <main class="price-list-show">
        <div class="price-list-shell">
            <nav class="breadcrumbs">
                <Link :href="route('Ameise.ai.price-lists.index')"><v-icon icon="mdi-arrow-left" size="18" /> Все прайс-листы</Link>
            </nav>

            <v-skeleton-loader v-if="loading && !importData" type="heading, paragraph, card, table" color="transparent" />

            <template v-else-if="importData">
                <header class="show-hero">
                    <div>
                        <div class="show-eyebrow">Ameise · AI · {{ importData.source_label }}</div>
                        <h1>{{ importData.file.name }}</h1>
                        <p>{{ importData.supplier?.name || 'Поставщик пока не определён' }} · получен {{ formatDate(importData.received_at) }}</p>
                    </div>
                    <div class="hero-actions">
                        <v-chip :color="statusColor(importData.status)" variant="flat">{{ importData.status_label }}</v-chip>
                        <v-btn icon="mdi-refresh" variant="tonal" color="white" :loading="loading" title="Обновить" @click="loadImport(); loadItems()" />
                    </div>
                </header>

                <v-progress-linear v-if="importData.progress < 100" :model-value="importData.progress" color="light-blue-accent-2" height="5" rounded class="mb-4" />

                <v-alert v-if="importData.error" type="error" variant="tonal" class="mb-4" border="start">
                    <strong>{{ importData.error.message }}</strong>
                    <div class="text-caption mt-1">Код: {{ importData.error.code }} · {{ importData.error.retryable ? 'этап можно повторить' : 'нужно исправить причину вручную' }}</div>
                </v-alert>

                <section class="overview-grid">
                    <v-card class="overview-card" variant="flat">
                        <div class="card-kicker"><v-icon icon="mdi-file-document-outline" /> Исходник</div>
                        <strong>{{ importData.file.original_name }}</strong>
                        <span>{{ (importData.file.extension || '—').toUpperCase() }} · {{ importData.file.mime_type || 'MIME не определён' }} · {{ formatBytes(importData.file.size_bytes) }}</span>
                        <span class="hash">SHA-256 · {{ importData.sha256 || 'ожидается' }}</span>
                        <a v-if="permissions.download" :href="`${baseUrl}/download`" class="download-link"><v-icon icon="mdi-download" size="18" /> Скачать через защищённый маршрут</a>
                        <span v-else class="download-blocked"><v-icon icon="mdi-shield-lock-outline" size="18" /> Скачивание карантинного файла доступно только техническому администратору</span>
                    </v-card>

                    <v-card class="overview-card" variant="flat">
                        <div class="card-kicker"><v-icon :icon="importData.source_channel === 'email' ? 'mdi-email-outline' : 'mdi-message-text-outline'" /> {{ sourceTitle }}</div>
                        <strong>{{ importData.source_channel === 'email' ? (importData.source.mail_message?.subject || 'Без темы') : (importData.source.max?.sender_name || 'Отправитель MAX') }}</strong>
                        <span v-if="importData.source_channel === 'email'">{{ importData.source.mail_message?.from_name }} · {{ importData.source.mail_message?.from_address }}</span>
                        <span v-else>chat {{ importData.source.max?.chat_id || '—' }} · message {{ importData.source.max?.message_id || '—' }}</span>
                        <Link v-if="importData.source.mail_message" :href="`${route('Ameise.mail')}?mail_message_id=${importData.source.mail_message.id}`" class="download-link"><v-icon icon="mdi-open-in-new" size="18" /> Открыть Почту</Link>
                    </v-card>

                    <v-card class="overview-card" variant="flat">
                        <div class="card-kicker"><v-icon icon="mdi-robot-outline" /> Обработка</div>
                        <strong>{{ importData.current_stage || 'Этап завершён' }} · {{ importData.progress }}%</strong>
                        <span>{{ importData.model_id || 'Локальные парсеры без AI' }}</span>
                        <span>{{ importData.parser_type || 'parser ожидается' }} · OCR: {{ importData.ocr_pages || 0 }} стр.</span>
                        <span v-if="permissions.view_technical">{{ usageTotals.tokens }} tokens<template v-if="usageTotals.cost"> · ≈ {{ usageTotals.cost.toFixed(4) }} {{ usageTotals.currency }}</template> · schema {{ importData.versions?.schema || '—' }}</span>
                    </v-card>

                    <v-card class="overview-card" variant="flat">
                        <div class="card-kicker"><v-icon icon="mdi-format-list-checks" /> Строки</div>
                        <strong>Всего {{ importData.counts.total }} · применено {{ importData.counts.applied }}</strong>
                        <span>Точных {{ importData.counts.exact }} · вероятных {{ importData.counts.probable }}</span>
                        <span>Без совпадения {{ importData.counts.unmatched }} · ошибок {{ importData.counts.invalid }}</span>
                    </v-card>
                </section>

                <section class="control-panel">
                    <div class="supplier-control">
                        <v-autocomplete
                            v-model="supplierId"
                            v-model:search="supplierSearch"
                            :items="supplierOptions"
                            item-title="name"
                            item-value="id"
                            label="Поставщик (Entity)"
                            variant="outlined"
                            density="compact"
                            hide-details
                            :disabled="!permissions.assign_supplier"
                            no-filter
                        >
                            <template #item="{ props: optionProps, item }">
                                <v-list-item v-bind="optionProps" :subtitle="item.raw.INN ? `ИНН ${item.raw.INN}` : null" />
                            </template>
                        </v-autocomplete>
                        <v-checkbox v-if="permissions.assign_supplier" v-model="bindSource" label="Запомнить связь отправителя" density="compact" hide-details />
                        <v-btn v-if="permissions.assign_supplier" color="blue-darken-2" variant="tonal" :disabled="!supplierId" :loading="actionLoading === 'supplier'" @click="assignSupplier">Сохранить</v-btn>
                    </div>
                    <div class="control-actions">
                        <v-btn v-if="canClassify" color="green-darken-2" prepend-icon="mdi-check" :loading="actionLoading === 'classify'" @click="classify('price_list')">Это прайс</v-btn>
                        <v-btn v-if="canClassify" color="grey" variant="tonal" prepend-icon="mdi-file-remove-outline" @click="classify('not_price_list')">Не прайс</v-btn>
                        <v-btn v-if="canRetry" color="orange-darken-2" variant="tonal" prepend-icon="mdi-reload" :loading="actionLoading === 'retry'" @click="retryStage">Повторить этап</v-btn>
                        <v-btn v-if="canCancel" color="red-darken-2" variant="text" prepend-icon="mdi-cancel" :loading="actionLoading === 'cancel'" @click="cancelImport">Отменить</v-btn>
                        <v-btn v-if="canApply" color="green-darken-2" prepend-icon="mdi-database-check-outline" :loading="actionLoading === 'preview'" @click="openApplyDialog">Применить…</v-btn>
                    </div>
                </section>

                <section class="content-panel">
                    <v-tabs v-model="activeTab" color="blue-darken-2" show-arrows>
                        <v-tab value="items" prepend-icon="mdi-format-list-bulleted">Позиции</v-tab>
                        <v-tab value="source" prepend-icon="mdi-file-eye-outline">Исходник / фрагменты</v-tab>
                        <v-tab value="history" prepend-icon="mdi-timeline-clock-outline">История обработки</v-tab>
                        <v-tab value="ai" prepend-icon="mdi-robot-outline">AI / OCR</v-tab>
                        <v-tab value="result" prepend-icon="mdi-check-decagram-outline">Результат применения</v-tab>
                    </v-tabs>

                    <v-window v-model="activeTab">
                        <v-window-item value="items">
                            <div class="items-toolbar">
                                <v-text-field v-model="itemFilters.search" label="Название, артикул или barcode" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable />
                                <v-select v-model="itemFilters.match_class" :items="matchFilters" label="Совпадение" variant="outlined" density="compact" hide-details clearable />
                                <v-select v-model="itemFilters.decision_status" :items="decisionFilters" label="Решение" variant="outlined" density="compact" hide-details clearable />
                                <v-btn v-if="permissions.review" variant="tonal" color="green-darken-2" prepend-icon="mdi-check-all" :loading="actionLoading === 'bulk-exact'" @click="bulkConfirmExact">Подтвердить точные</v-btn>
                                <v-btn v-if="permissions.review" variant="tonal" prepend-icon="mdi-tune-variant" @click="defaultsDialog = true">Defaults</v-btn>
                            </div>

                            <v-data-table-server
                                v-model="selectedItemIds"
                                v-model:page="itemsPage"
                                v-model:items-per-page="itemsPerPage"
                                :headers="itemHeaders"
                                :items="items"
                                :items-length="itemsTotal"
                                :loading="itemsLoading"
                                item-value="id"
                                item-selectable="selectable"
                                :show-select="permissions.apply"
                                class="items-table"
                            >
                                <template #item.position="{ item }"><span class="position">{{ item.position }}</span></template>
                                <template #item.raw_name="{ item }">
                                    <div class="raw-cell">
                                        <strong>{{ item.raw_name || item.raw_text || 'Пустая строка' }}</strong>
                                        <span>{{ locator(item) }}</span>
                                        <span v-if="item.supplier_sku || item.manufacturer_sku || item.barcode">Код: {{ item.supplier_sku || item.manufacturer_sku || '—' }} · EAN {{ item.barcode || '—' }}</span>
                                        <v-chip v-if="Object.keys(item.user_corrections || {}).length" size="x-small" color="purple" variant="tonal">исправлено пользователем</v-chip>
                                    </div>
                                </template>
                                <template #item.price="{ item }">
                                    <strong class="price-value">{{ item.price || '—' }} {{ item.currency_code || '' }}</strong>
                                    <div class="cell-note">за {{ item.price_basis_quantity || 1 }} {{ item.price_basis_unit || 'ед.' }}</div>
                                </template>
                                <template #item.details="{ item }">
                                    <div>{{ item.vat_mode === 'included' ? 'с НДС' : item.vat_mode === 'excluded' ? 'без НДС' : 'НДС неизвестен' }}<span v-if="item.vat_rate"> · {{ item.vat_rate }}%</span></div>
                                    <div class="cell-note">{{ item.package_description || 'Упаковка не указана' }}</div>
                                </template>
                                <template #item.match="{ item }">
                                    <v-chip :color="matchColor(item.match_class)" size="x-small" variant="tonal">{{ matchLabel(item.match_class) }}<span v-if="item.match_score"> · {{ (Number(item.match_score) * 100).toFixed(0) }}%</span></v-chip>
                                    <Link v-if="item.good" :href="goodUrl(item.good)" class="good-link" target="_blank">{{ item.good.name }} <v-icon icon="mdi-open-in-new" size="13" /></Link>
                                    <v-select v-else-if="candidateOptions(item).length" v-model="item.selected_good_id" :items="candidateOptions(item)" density="compact" variant="outlined" hide-details class="candidate-select" />
                                    <div v-if="item.review_reason" class="cell-note" :title="item.review_reason">{{ item.review_reason }}</div>
                                </template>
                                <template #item.decision_status="{ item }">
                                    <v-chip size="small" variant="tonal">{{ decisionLabel(item.decision_status) }}</v-chip>
                                </template>
                                <template #item.actions="{ item }">
                                    <div v-if="permissions.review" class="row-actions">
                                        <v-btn icon="mdi-pencil-outline" size="x-small" variant="text" title="Исправить" @click="openEdit(item)" />
                                        <v-btn icon="mdi-link-variant" size="x-small" variant="text" color="green-darken-2" title="Связать с товаром" :loading="actionLoading === `item-${item.id}`" @click="item.selected_good_id ? decide(item, 'matched', item.selected_good_id) : openGoodSearch(item)" />
                                        <v-menu>
                                            <template #activator="{ props: menuProps }"><v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="x-small" variant="text" /></template>
                                            <v-list density="compact">
                                                <v-list-item prepend-icon="mdi-magnify" title="Найти другой товар" @click="openGoodSearch(item)" />
                                                <v-list-item prepend-icon="mdi-package-variant-plus" title="Создать черновик" @click="decide(item, 'create_draft')" />
                                                <v-list-item prepend-icon="mdi-eye-off-outline" title="Пропустить" @click="decide(item, 'ignored')" />
                                                <v-list-item prepend-icon="mdi-backup-restore" title="Сбросить решение" @click="decide(item, 'unreviewed')" />
                                            </v-list>
                                        </v-menu>
                                    </div>
                                </template>
                            </v-data-table-server>
                        </v-window-item>

                        <v-window-item value="source">
                            <div class="tab-content">
                                <v-alert type="info" variant="tonal" density="compact" class="mb-4">Показаны безопасные текстовые фрагменты текущей страницы. Оригинал доступен только через защищённое скачивание.</v-alert>
                                <article v-for="item in items" :key="item.id" class="source-fragment">
                                    <span>{{ locator(item) }}</span>
                                    <pre>{{ item.raw_text || JSON.stringify(item.raw_cells || {}, null, 2) }}</pre>
                                </article>
                                <div v-if="!items.length" class="empty-tab">Извлечённых фрагментов пока нет.</div>
                            </div>
                        </v-window-item>

                        <v-window-item value="history">
                            <div class="tab-content timeline">
                                <div v-for="event in importData.events" :key="event.id" class="timeline-event">
                                    <div class="timeline-dot" />
                                    <div><strong>{{ event.event_type }}</strong><span>{{ formatDate(event.created_at) }} · {{ event.user?.name || 'система' }}<template v-if="event.duration_ms"> · {{ event.duration_ms }} мс</template></span><p v-if="event.status_from || event.status_to">{{ event.status_from || '—' }} → {{ event.status_to || '—' }}</p></div>
                                </div>
                                <div v-if="!importData.events?.length" class="empty-tab">Событий пока нет.</div>
                            </div>
                        </v-window-item>

                        <v-window-item value="ai">
                            <div class="tab-content">
                                <v-alert v-if="!permissions.view_technical" type="info" variant="tonal">Технический журнал доступен пользователям с отдельным правом. Ключи и полный текст документов здесь не отображаются.</v-alert>
                                <v-table v-else density="compact">
                                    <thead><tr><th>Операция</th><th>Модель</th><th>Расход</th><th>Время</th><th>Статус</th></tr></thead>
                                    <tbody><tr v-for="usage in importData.usage || []" :key="usage.id"><td>{{ usage.provider }} · {{ usage.operation }}</td><td>{{ usage.model || '—' }}</td><td>{{ usage.total_tokens ? `${usage.total_tokens} tokens` : usage.pages ? `${usage.pages} стр.` : `${usage.units || 0} units` }}<div v-if="usage.estimated_cost" class="cell-note">≈ {{ usage.estimated_cost }} {{ usage.cost_currency }}</div></td><td>{{ usage.latency_ms || 0 }} мс</td><td>{{ usage.status }}</td></tr></tbody>
                                </v-table>
                                <div v-if="permissions.view_technical && !importData.usage?.length" class="empty-tab">AI/OCR не использовались или расход ещё не записан.</div>
                            </div>
                        </v-window-item>

                        <v-window-item value="result">
                            <div class="tab-content">
                                <v-table v-if="importData.applied_prices?.length" density="compact">
                                    <thead><tr><th>Товар</th><th>Закупочная цена</th><th>НДС</th><th>Создано</th></tr></thead>
                                    <tbody><tr v-for="price in importData.applied_prices" :key="price.id"><td><Link :href="goodUrl(price.good)" class="good-link">{{ price.good?.name || `#${price.good_id}` }}</Link></td><td>{{ price.price }} {{ price.currency_code }}</td><td>{{ price.vat_mode }} {{ price.vat_rate || '' }}</td><td>{{ formatDate(price.created_at) }}</td></tr></tbody>
                                </v-table>
                                <div v-else class="empty-tab"><v-icon icon="mdi-database-clock-outline" size="34" /><strong>Цены ещё не применялись</strong><span>До явного подтверждения каталог и закупочные цены не изменяются.</span></div>
                            </div>
                        </v-window-item>
                    </v-window>
                </section>
            </template>
        </div>

        <v-dialog v-model="editDialog" max-width="900" persistent>
            <v-card title="Исправить распознанную строку">
                <v-card-text class="edit-grid">
                    <v-text-field v-model="editForm.raw_name" label="Название" variant="outlined" class="span-2" />
                    <v-text-field v-model="editForm.supplier_sku" label="Код поставщика" variant="outlined" />
                    <v-text-field v-model="editForm.manufacturer_sku" label="Артикул производителя" variant="outlined" />
                    <v-text-field v-model="editForm.barcode" label="Barcode / EAN" variant="outlined" />
                    <v-text-field v-model="editForm.brand" label="Бренд" variant="outlined" />
                    <v-text-field v-model="editForm.manufacturer" label="Производитель" variant="outlined" />
                    <v-text-field v-model="editForm.country_of_origin" label="Страна (текст из документа)" variant="outlined" />
                    <v-text-field v-model="editForm.package_description" label="Упаковка" variant="outlined" class="span-2" />
                    <v-text-field v-model="editForm.price" label="Цена" variant="outlined" />
                    <v-text-field v-model="editForm.currency_code" label="Валюта ISO" variant="outlined" />
                    <v-select v-model="editForm.vat_mode" :items="[{ title: 'С НДС', value: 'included' }, { title: 'Без НДС', value: 'excluded' }, { title: 'Неизвестно', value: 'unknown' }]" label="НДС" variant="outlined" />
                    <v-text-field v-model="editForm.vat_rate" label="Ставка НДС, %" variant="outlined" />
                    <v-text-field v-model="editForm.price_basis_quantity" label="Количество в цене" variant="outlined" />
                    <v-select v-model="editForm.price_basis_unit" :items="['kg', 'g', 'l', 'ml', 'pcs', 'box']" label="Единица цены" variant="outlined" clearable />
                    <v-text-field v-model="editForm.minimum_order_quantity" label="Минимальная партия" variant="outlined" />
                    <v-text-field v-model="editForm.availability" label="Наличие" variant="outlined" />
                    <v-textarea v-model="editForm.notes" label="Примечание" variant="outlined" rows="2" class="span-2" />
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn variant="text" @click="editDialog = false">Отмена</v-btn><v-btn color="blue-darken-2" :loading="actionLoading === 'edit'" @click="saveEdit">Сохранить</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="matchDialog" max-width="650">
            <v-card title="Связать с существующим товаром">
                <v-card-text>
                    <v-autocomplete v-model="selectedGoodId" v-model:search="goodSearch" :items="goodOptions" item-title="name" item-value="id" label="Найдите Good" variant="outlined" no-filter clearable />
                    <v-checkbox v-model="saveAlias" label="Запомнить подтверждённый alias для этого поставщика" color="green-darken-2" :disabled="!importData?.supplier" />
                    <v-alert type="info" variant="tonal" density="compact">Alias создаётся только после ручного подтверждения и не позволяет AI записывать цены автоматически.</v-alert>
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn variant="text" @click="matchDialog = false">Отмена</v-btn><v-btn color="green-darken-2" :disabled="!selectedGoodId" :loading="actionLoading.startsWith('item-')" @click="confirmGood">Связать</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="defaultsDialog" max-width="650">
            <v-card title="Заполнить отсутствующие defaults">
                <v-card-text>
                    <p class="dialog-note">Существующие значения из документа и ручные исправления не будут перезаписаны.</p>
                    <div class="defaults-grid">
                        <v-text-field v-model="defaultsForm.currency_code" label="Валюта ISO" variant="outlined" clearable />
                        <v-select v-model="defaultsForm.vat_mode" :items="[{ title: 'С НДС', value: 'included' }, { title: 'Без НДС', value: 'excluded' }, { title: 'Неизвестно', value: 'unknown' }]" label="Режим НДС" variant="outlined" clearable />
                        <v-text-field v-model="defaultsForm.vat_rate" label="Ставка НДС, %" variant="outlined" clearable />
                    </div>
                    <v-alert v-if="defaultsPreview" type="info" variant="tonal">Будет затронуто строк: <strong>{{ defaultsPreview.affected }}</strong>.</v-alert>
                </v-card-text>
                <v-card-actions><v-btn variant="tonal" :loading="actionLoading === 'defaults-preview'" @click="previewDefaults">Предварительный просмотр</v-btn><v-spacer /><v-btn variant="text" @click="defaultsDialog = false">Отмена</v-btn><v-btn color="blue-darken-2" :disabled="!defaultsPreview?.affected" :loading="actionLoading === 'defaults-apply'" @click="applyDefaults">Применить к пустым</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="applyDialog" max-width="680" persistent>
            <v-card title="Подтверждение применения">
                <v-card-text v-if="applyPreview">
                    <v-alert type="warning" variant="tonal" class="mb-4">Операция создаст новые исторические закупочные цены. Существующие продажные цены не изменяются.</v-alert>
                    <dl class="apply-summary">
                        <div v-if="applyPreview.selected"><dt>Выбранные строки</dt><dd>{{ applyPreview.selected_count }}</dd></div>
                        <div><dt>Поставщик</dt><dd>{{ applyPreview.supplier?.name || 'Не выбран' }}</dd></div>
                        <div><dt>Новых цен</dt><dd>{{ applyPreview.prices }}</dd></div>
                        <div><dt>Новых товаров-черновиков</dt><dd>{{ applyPreview.drafts }}</dd></div>
                        <div><dt>Пропущено</dt><dd>{{ applyPreview.ignored }}</dd></div>
                        <div><dt>Требуют решения</dt><dd :class="{ danger: applyPreview.unreviewed }">{{ applyPreview.unreviewed }}</dd></div>
                        <div><dt>Defaults</dt><dd>{{ applyPreview.currency || '—' }} · {{ applyPreview.vat_mode || 'НДС неизвестен' }}</dd></div>
                        <div><dt>Период действия</dt><dd>{{ applyPreview.valid_from || '—' }} — {{ applyPreview.valid_to || '—' }}</dd></div>
                    </dl>
                    <v-alert v-if="applyPreview.price_change_warnings" type="warning" variant="tonal" class="mt-4">Резкое изменение относительно последней сопоставимой цены: {{ applyPreview.price_change_warnings }} строк. Проверьте валюту, фасовку и единицу цены.</v-alert>
                    <v-alert v-if="applyPreview.draft_duplicate_warnings" type="warning" variant="tonal" class="mt-3">Для {{ applyPreview.draft_duplicate_warnings }} черновиков уже существует товар с таким названием.</v-alert>
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn variant="text" :disabled="actionLoading === 'apply'" @click="applyDialog = false">Отмена</v-btn><v-btn color="green-darken-2" prepend-icon="mdi-database-check-outline" :disabled="!applyPreview || applyPreview.unreviewed > 0 || (applyPreview.prices + applyPreview.drafts) === 0" :loading="actionLoading === 'apply'" @click="applyImport">Подтверждаю изменения</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar.open" :color="snackbar.color" location="bottom right" :timeout="5500">{{ snackbar.text }}<template #actions><v-btn variant="text" @click="snackbar.open = false">Закрыть</v-btn></template></v-snackbar>
    </main>
</template>

<style scoped>
.price-list-show { min-height: calc(100vh - 58px); padding: 18px 20px 30px; background: radial-gradient(circle at 8% 0%, #394a5b 0, #17212b 42%, #10171e 100%); color: #f6f8fa; }
.price-list-shell { width: min(1560px, 100%); margin: 0 auto; }
.breadcrumbs { margin-bottom: 12px; }.breadcrumbs a { color: #c8d6e0; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.show-hero { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 14px; }.show-eyebrow { color: #9bc4d8; font-size: .73rem; letter-spacing: .17em; text-transform: uppercase; font-weight: 800; }.show-hero h1 { margin: 4px 0; font-size: clamp(1.45rem, 3vw, 2.2rem); overflow-wrap: anywhere; }.show-hero p { margin: 0; color: #c8d4de; }.hero-actions { display: flex; align-items: center; gap: 9px; }
.overview-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 12px; }.overview-card { padding: 15px; min-height: 145px; display: flex; flex-direction: column; gap: 8px; color: #1b2732; }.overview-card > span { color: #657480; font-size: .82rem; overflow-wrap: anywhere; }.card-kicker { display: flex; align-items: center; gap: 7px; color: #597080; font-size: .74rem; font-weight: 800; text-transform: uppercase; letter-spacing: .09em; }.hash { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }.download-link, .good-link { color: #176aa6; text-decoration: none; }.download-link { margin-top: auto; display: inline-flex; gap: 6px; align-items: center; font-size: .84rem; font-weight: 700; }
.download-blocked { margin-top: auto; display: inline-flex; gap: 6px; align-items: center; color: #9f2d20 !important; }
.control-panel { display: flex; justify-content: space-between; align-items: center; gap: 16px; background: #f7f9fb; color: #1b2732; padding: 12px 14px; border-radius: 13px 13px 0 0; border-bottom: 1px solid #dce4e9; }.supplier-control { flex: 1; display: grid; grid-template-columns: minmax(240px, 480px) auto auto; align-items: center; gap: 10px; }.control-actions { display: flex; justify-content: flex-end; gap: 7px; flex-wrap: wrap; }
.content-panel { overflow: hidden; background: #fff; color: #1b2732; border-radius: 0 0 14px 14px; box-shadow: 0 20px 60px rgba(0,0,0,.24); }.content-panel > :deep(.v-tabs) { border-bottom: 1px solid #e0e6ea; }.items-toolbar { padding: 12px; display: grid; grid-template-columns: minmax(240px, 2fr) minmax(180px, 1fr) minmax(180px, 1fr) auto auto; gap: 9px; align-items: center; background: #f8fafb; }.items-table :deep(th) { white-space: nowrap; }.items-table :deep(td) { padding-top: 8px !important; padding-bottom: 8px !important; }.position { color: #788792; font-variant-numeric: tabular-nums; }.raw-cell { max-width: 390px; display: flex; flex-direction: column; gap: 3px; }.raw-cell > span, .cell-note { color: #71808c; font-size: .73rem; }.price-value { white-space: nowrap; font-variant-numeric: tabular-nums; }.candidate-select { max-width: 290px; margin-top: 5px; }.good-link { display: block; margin-top: 5px; font-size: .84rem; font-weight: 700; }.row-actions { display: flex; align-items: center; }
.tab-content { padding: 18px; min-height: 300px; }.source-fragment { border: 1px solid #e0e6ea; border-radius: 10px; margin-bottom: 10px; overflow: hidden; }.source-fragment > span { display: block; padding: 7px 10px; background: #f4f7f9; color: #687884; font-size: .72rem; }.source-fragment pre { margin: 0; padding: 11px; white-space: pre-wrap; overflow-wrap: anywhere; font: .8rem/1.45 ui-monospace, SFMono-Regular, Menlo, monospace; }.empty-tab { min-height: 230px; color: #71808c; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 8px; text-align: center; }
.timeline { position: relative; }.timeline-event { display: grid; grid-template-columns: 16px 1fr; gap: 10px; padding: 0 0 17px; }.timeline-dot { width: 10px; height: 10px; margin-top: 5px; border-radius: 50%; background: #2485c5; box-shadow: 0 0 0 4px #e0f1fb; }.timeline-event strong, .timeline-event span { display: block; }.timeline-event span { color: #75838e; font-size: .75rem; }.timeline-event p { margin: 4px 0 0; font-family: ui-monospace, monospace; font-size: .78rem; }
.edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1px 12px; }.span-2 { grid-column: 1 / -1; }.defaults-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }.dialog-note { margin: 0 0 16px; color: #60717d; }.apply-summary { margin: 0; }.apply-summary div { padding: 8px 0; display: flex; justify-content: space-between; border-bottom: 1px solid #e6ebee; }.apply-summary dt { color: #62727d; }.apply-summary dd { margin: 0; font-weight: 800; }.danger { color: #b42318; }
@media (max-width: 1200px) { .overview-grid { grid-template-columns: 1fr 1fr; }.control-panel { align-items: stretch; flex-direction: column; }.control-actions { justify-content: flex-start; }.items-toolbar { grid-template-columns: 1fr 1fr 1fr; } }
@media (max-width: 760px) { .price-list-show { padding: 12px 7px 24px; }.show-hero { flex-direction: column; }.overview-grid { grid-template-columns: 1fr; }.supplier-control { grid-template-columns: 1fr; }.items-toolbar, .edit-grid, .defaults-grid { grid-template-columns: 1fr; }.span-2 { grid-column: auto; }.control-actions :deep(.v-btn) { flex: 1 1 auto; }.overview-card { min-height: 120px; } }
</style>
