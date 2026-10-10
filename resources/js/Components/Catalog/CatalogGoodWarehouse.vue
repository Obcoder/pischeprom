<script setup>
import axios from 'axios'
import { computed, onScopeDispose, reactive, ref, watch } from 'vue'
import { route } from 'ziggy-js'
import CatalogGoodMeasurement from './CatalogGoodMeasurement.vue'
import { useRealtimeResource } from '../../Composables/useRealtimeResource.js'
import RealtimeStatus from '../Realtime/RealtimeStatus.vue'

const props = defineProps({
    goodId: { type: [Number, String], default: null },
    modelValue: { type: Object, required: true },
    overview: { type: Object, default: null },
    options: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
    active: { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue', 'changed', 'state'])
const stockRows = ref([])
const movements = ref([])
const purchases = ref([])
const warehouses = ref([])
const currencies = ref([])
const loading = ref(false)
const loadError = ref('')
const formError = ref('')
const formErrors = ref({})
const saving = ref(false)
const deleting = ref(null)
const movementDialog = ref(false)
const movementPage = ref(1)
const purchasePage = ref(1)
const movementMeta = ref({ total: 0, last_page: 1 })
const purchaseMeta = ref({ total: 0, last_page: 1 })
const warehouseFilter = ref(null)
const movementTypeFilter = ref(null)
const formBaseline = ref('')
const form = reactive({})
let controller = null
let requestVersion = 0
let loadedGoodId = null
let currenciesLoaded = false
let disposed = false

const resource = useRealtimeResource({
    key: 'catalog-good-warehouse', initialValue: {}, topics: ['goods_stock', 'purchases', 'warehouses'],
    load: () => props.active && !saving.value && !deleting.value ? refresh({ background: true }) : Promise.resolve(),
})
const movementTypes = [
    { value: 'receipt', title: 'Приход', icon: 'mdi-arrow-bottom-left', color: 'success' },
    { value: 'write_off', title: 'Списание', icon: 'mdi-arrow-top-right', color: 'error' },
    { value: 'adjustment', title: 'Корректировка', icon: 'mdi-tune', color: 'warning' },
]
const savedMeasureId = computed(() => props.overview?.measurement?.measure_id ?? props.overview?.measure_id ?? null)
const unitChanged = computed(() => String(props.modelValue.measure_id || '') !== String(savedMeasureId.value || ''))
const mutationBusy = computed(() => saving.value || deleting.value !== null)
const canCreateMovement = computed(() => props.goodId && savedMeasureId.value && !unitChanged.value && !props.disabled && !mutationBusy.value)
const formDirty = computed(() => movementDialog.value && JSON.stringify(form) !== formBaseline.value)
const measureName = id => (props.options.measures || []).find(item => String(item.id) === String(id))?.name || 'без единицы'
const measureLabel = computed(() => measureName(form.measure_id))
const unitName = computed(() => props.overview?.measurement?.unit_label || measureName(savedMeasureId.value))
const warehouseOptions = computed(() => warehouses.value.map(item => ({ ...item, title: `${item.name}${item.is_active === false ? ' · неактивен' : ''}` })))
const visibleStock = computed(() => stockRows.value.filter(row => !warehouseFilter.value || String(row.warehouse_id) === String(warehouseFilter.value)))
const balances = computed(() => {
    const grouped = new Map()
    for (const row of stockRows.value) {
        const key = String(row.measure_id ?? '')
        const bucket = grouped.get(key) || { label: row.measure?.name || 'без единицы', quantity: 0 }
        bucket.quantity += Number(row.quantity || 0)
        grouped.set(key, bucket)
    }
    return [...grouped.values()]
})
const purchaseRows = computed(() => purchases.value.flatMap(purchase => (purchase.items || [])
    .filter(item => String(item.good_id) === String(props.goodId))
    .map(item => ({ ...item, purchase }))))

const number = value => new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 6 }).format(Number(value) || 0)
const money = value => new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value) || 0)
const currency = id => currencies.value.find(item => String(item.id) === String(id))?.code
    || currencies.value.find(item => String(item.id) === String(id))?.name || 'валюта не указана'
const date = value => {
    if (!value) return '—'
    const result = new Date(value)
    return Number.isNaN(result.getTime()) ? '—' : result.toLocaleDateString('ru-RU')
}
const today = () => new Date().toLocaleDateString('en-CA')
const items = response => Array.isArray(response?.data) ? response.data : response?.data?.data || []
const movementType = type => movementTypes.find(item => item.value === type) || { title: type, color: 'default', icon: 'mdi-swap-vertical' }
const purchaseUrl = id => route('Ameise.commerce', { purchase_id: id })
const newPurchaseUrl = computed(() => props.goodId ? route('Ameise.commerce', { purchase_good_id: props.goodId }) : undefined)
const sourceUrl = movement => movement.purchase_id ? purchaseUrl(movement.purchase_id)
    : movement.sale_id ? route('Ameise.sales', { sale_id: movement.sale_id }) : undefined
const sourceTitle = movement => movement.purchase_id ? `Закупка № ${movement.purchase_id}`
    : movement.sale_id ? `Продажа № ${movement.sale_id}` : 'Ручная операция'
const sourceNote = movement => movement.source_type ? 'Изменяется через исходный документ' : ''
const received = item => (item.stock_movements || []).some(movement => Number(movement.quantity) > 0)
const purchaseWarehouse = item => [...new Set((item.stock_movements || []).map(movement => movement.warehouse_name).filter(Boolean))].join(', ')

function errorMessage(error, fallback) {
    return Object.values(error?.response?.data?.errors || {}).flat()[0] || error?.response?.data?.message || fallback
}
async function refresh({ background = false } = {}) {
    if (!props.goodId || !props.active || disposed) return
    controller?.abort()
    controller = new AbortController()
    const requestController = controller
    const signal = requestController.signal
    const ownerSignal = resource.signal
    const cancelForOwner = () => requestController.abort()
    if (ownerSignal?.aborted) return
    ownerSignal?.addEventListener('abort', cancelForOwner, { once: true })
    const version = ++requestVersion
    const id = props.goodId
    if (!background) loading.value = true
    try {
        const [stockResponse, movementResponse, purchaseResponse, warehouseResponse, currencyResponse] = await Promise.all([
            axios.get(route('good-warehouse-stock.index'), { signal, params: { good_id: id } }),
            axios.get(route('good-stock-movements.index'), { signal, params: {
                good_id: id, paginate: true, per_page: 15, page: movementPage.value,
                warehouse_id: warehouseFilter.value || undefined, type: movementTypeFilter.value || undefined,
            } }),
            axios.get(route('purchases.index'), { signal, params: { good_ids: [id], include_stock: true, per_page: 15, page: purchasePage.value } }),
            axios.get(route('warehouses.index'), { signal, params: { include_goods: true } }),
            !currenciesLoaded ? axios.get(route('currencies.index'), { signal }) : null,
        ])
        if (disposed || signal.aborted || version !== requestVersion) return
        stockRows.value = items(stockResponse)
        movements.value = items(movementResponse)
        purchases.value = items(purchaseResponse)
        movementMeta.value = movementResponse.data.meta
        purchaseMeta.value = purchaseResponse.data.meta
        warehouses.value = items(warehouseResponse)
        if (currencyResponse) currencies.value = items(currencyResponse)
        currenciesLoaded = true
        loadedGoodId = id
        loadError.value = ''
        if (movementPage.value > movementMeta.value.last_page || purchasePage.value > purchaseMeta.value.last_page) {
            movementPage.value = Math.min(movementPage.value, movementMeta.value.last_page)
            purchasePage.value = Math.min(purchasePage.value, purchaseMeta.value.last_page)
            await refresh({ background })
        }
    } catch (error) {
        if (version !== requestVersion || signal.aborted || axios.isCancel(error)) return
        loadError.value = errorMessage(error, 'Не удалось загрузить складской учёт товара.')
        if (background) throw error
    } finally {
        ownerSignal?.removeEventListener('abort', cancelForOwner)
        if (version === requestVersion) loading.value = false
    }
}
function reset() {
    if (mutationBusy.value) return
    movementDialog.value = false
    formError.value = ''
    formErrors.value = {}
    Object.keys(form).forEach(key => delete form[key])
    formBaseline.value = JSON.stringify(form)
}
function closeMovement() {
    if (mutationBusy.value || (formDirty.value && !window.confirm('Закрыть движение без сохранения?'))) return
    reset()
}
function openMovement(type = 'receipt') {
    if (!canCreateMovement.value) return
    reset()
    Object.assign(form, {
        id: null, warehouse_id: warehouseFilter.value || warehouses.value.find(item => item.code === 'goods')?.id || warehouses.value.find(item => item.is_active)?.id || null,
        measure_id: savedMeasureId.value, type, quantity: 1, unit_price: 0, moved_at: today(), note: '',
    })
    formBaseline.value = JSON.stringify(form)
    movementDialog.value = true
}
function editMovement(movement) {
    if (props.disabled || mutationBusy.value || movement.source_type) return
    reset()
    Object.assign(form, {
        id: movement.id, warehouse_id: movement.warehouse_id, measure_id: movement.measure_id,
        type: movement.type, quantity: movement.type === 'write_off' ? Math.abs(movement.quantity_delta) : movement.quantity_delta,
        unit_price: movement.unit_price, moved_at: movement.moved_at, note: movement.note || '',
    })
    formBaseline.value = JSON.stringify(form)
    movementDialog.value = true
}
async function saveMovement() {
    if (props.disabled || mutationBusy.value || !props.goodId || (!form.id && !canCreateMovement.value)) return
    formError.value = ''
    formErrors.value = {}
    const quantity = Number(form.quantity)
    const price = Number(form.unit_price)
    if (!form.warehouse_id || !form.moved_at || !Number.isFinite(quantity) || Math.abs(quantity) < 0.000001
        || (form.type !== 'adjustment' && quantity <= 0) || !Number.isFinite(price) || price < 0) {
        formError.value = 'Заполните склад, дату, ненулевое количество и неотрицательную цену. Для прихода и списания количество должно быть положительным.'
        return
    }
    const goodId = props.goodId
    saving.value = true
    try {
        const payload = { ...form, good_id: goodId, quantity, unit_price: price, note: form.note || null }
        delete payload.id
        if (form.id) await axios.patch(route('good-stock-movements.update', form.id), payload)
        else await axios.post(route('good-stock-movements.store'), payload)
        if (disposed || String(goodId) !== String(props.goodId)) return
        movementDialog.value = false
        movementPage.value = 1
        emit('changed')
        await refresh()
    } catch (error) {
        if (disposed || String(goodId) !== String(props.goodId)) return
        formErrors.value = error?.response?.data?.errors || {}
        formError.value = errorMessage(error, 'Не удалось сохранить движение.')
    } finally { saving.value = false }
}
async function removeMovement(movement) {
    if (props.disabled || mutationBusy.value || movement.source_type || !window.confirm(`Удалить движение № ${movement.id}? Остаток товара будет пересчитан.`)) return
    deleting.value = movement.id
    try {
        await axios.delete(route('good-stock-movements.destroy', movement.id))
        emit('changed')
        await refresh()
    } catch (error) { loadError.value = errorMessage(error, 'Не удалось удалить движение.') }
    finally { deleting.value = null }
}
function pageMovements(page) { movementPage.value = page; refresh() }
function pagePurchases(page) { purchasePage.value = page; refresh() }
function filterMovements() { movementPage.value = 1; refresh() }

watch(() => props.goodId, () => {
    controller?.abort()
    requestVersion++
    loadedGoodId = null
    stockRows.value = []; movements.value = []; purchases.value = []
    movementMeta.value = { total: 0, last_page: 1 }; purchaseMeta.value = { total: 0, last_page: 1 }
    movementPage.value = 1; purchasePage.value = 1
    warehouseFilter.value = null; movementTypeFilter.value = null
    reset()
})
watch(() => [props.active, props.goodId], ([active, id]) => {
    if (!active) { controller?.abort(); loading.value = false; return }
    if (id && (String(loadedGoodId) !== String(id) || !loading.value)) refresh()
}, { immediate: true })
watch(() => props.overview?.measurement?.measure_id ?? props.overview?.measure_id, () => {
    if (props.active && props.goodId) refresh()
})
watch(() => ({ dirty: formDirty.value, busy: mutationBusy.value }), state => emit('state', state), { immediate: true })
onScopeDispose(() => { disposed = true; requestVersion++; controller?.abort() })
defineExpose({ reset, refresh })
</script>

<template>
    <div class="good-warehouse">
        <slot name="measurement"><CatalogGoodMeasurement :model-value="modelValue" :overview="overview" :options="options" :errors="errors" :disabled="disabled || mutationBusy" @update:model-value="emit('update:modelValue', $event)" /></slot>
        <v-alert v-if="!goodId" type="info" variant="tonal" density="compact">Сохраните товар, чтобы вести остатки, движения и закупки.</v-alert>
        <template v-else>
            <header class="good-warehouse__heading">
                <div><h3>Складской учёт <RealtimeStatus :failed="resource.refreshFailed.value" /></h3><p>Остатки и документы только этого товара</p></div>
                <v-btn icon="mdi-refresh" variant="text" size="small" :loading="loading" :disabled="mutationBusy" aria-label="Обновить склад товара" @click="refresh()" />
            </header>
            <v-alert v-if="loadError" type="error" variant="tonal" density="compact">{{ loadError }}</v-alert>
            <v-alert v-if="!savedMeasureId || unitChanged" type="warning" variant="tonal" density="compact">{{ unitChanged ? 'Сохраните выбранную единицу в карточке перед созданием движений и закупок.' : 'Укажите и сохраните единицу учёта, чтобы создавать движения и закупки.' }}</v-alert>
            <div class="good-warehouse__summary">
                <div><span>Остаток по всем складам</span><strong v-if="!balances.length">0 {{ unitName }}</strong><strong v-for="balance in balances" :key="balance.label" :class="{ 'is-negative': balance.quantity < 0 }">{{ number(balance.quantity) }} {{ balance.label }}</strong></div>
                <div><span>Складов с остатками</span><strong>{{ new Set(stockRows.map(row => row.warehouse_id)).size }}</strong></div>
                <div><span>Движений{{ warehouseFilter || movementTypeFilter ? ' по фильтру' : '' }}</span><strong>{{ movementMeta.total }}</strong></div>
                <div><span>Документов закупки</span><strong>{{ purchaseMeta.total }}</strong></div>
            </div>
            <div class="good-warehouse__actions">
                <v-btn v-for="operation in movementTypes" :key="operation.value" :prepend-icon="operation.icon" variant="tonal" size="small" :disabled="!canCreateMovement || loading" @click="openMovement(operation.value)">{{ operation.title }}</v-btn>
                <v-spacer />
                <v-btn :href="newPurchaseUrl" target="_blank" rel="noopener noreferrer" prepend-icon="mdi-cart-plus" color="primary" variant="tonal" size="small" :disabled="!canCreateMovement">Новая закупка</v-btn>
            </div>
            <section class="good-warehouse__section">
                <div class="good-warehouse__section-heading"><h3>Остатки по складам</h3><v-select v-model="warehouseFilter" :items="warehouseOptions" item-title="title" item-value="id" label="Все склады" clearable hide-details variant="outlined" density="compact" @update:model-value="filterMovements" /></div>
                <v-table density="compact" class="good-warehouse__table">
                    <thead><tr><th>Склад</th><th class="text-right">Количество</th><th>Единица</th><th>Последнее движение</th></tr></thead>
                    <tbody>
                        <tr v-for="row in visibleStock" :key="`${row.warehouse_id}:${row.measure_id}`"><td>{{ row.warehouse?.name || `Склад № ${row.warehouse_id}` }}</td><td class="text-right" :class="{ 'is-negative': row.quantity < 0 }"><strong>{{ number(row.quantity) }}</strong></td><td>{{ row.measure?.name || 'Без единицы' }}</td><td>{{ date(row.last_moved_at) }}</td></tr>
                        <tr v-if="!visibleStock.length"><td colspan="4" class="good-warehouse__empty">{{ loading ? 'Загрузка остатков…' : 'Ненулевых остатков нет' }}</td></tr>
                    </tbody>
                </v-table>
            </section>
            <section class="good-warehouse__section">
                <div class="good-warehouse__section-heading"><h3>Движения товара <span>{{ movementMeta.total }}</span></h3><v-select v-model="movementTypeFilter" :items="movementTypes" item-title="title" item-value="value" label="Все операции" clearable hide-details variant="outlined" density="compact" @update:model-value="filterMovements" /></div>
                <v-table density="compact" class="good-warehouse__table">
                    <thead><tr><th>Дата / операция</th><th>Склад</th><th class="text-right">Количество</th><th class="text-right">Цена учёта</th><th>Документ / примечание</th><th><span class="sr-only">Действия</span></th></tr></thead>
                    <tbody>
                        <tr v-for="movement in movements" :key="movement.id">
                            <td class="good-warehouse__nowrap">{{ date(movement.moved_at) }}<small><v-icon :icon="movementType(movement.type).icon" :color="movementType(movement.type).color" size="14" /> {{ movementType(movement.type).title }} · № {{ movement.id }}</small></td>
                            <td>{{ movement.warehouse?.name || '—' }}</td>
                            <td class="text-right good-warehouse__nowrap" :class="{ 'is-negative': movement.quantity_delta < 0 }"><strong>{{ movement.quantity_delta > 0 ? '+' : '' }}{{ number(movement.quantity_delta) }}</strong><small>{{ movement.measure?.name || 'Без единицы' }}</small></td>
                            <td class="text-right">{{ money(movement.unit_price) }}</td>
                            <td><a v-if="sourceUrl(movement)" :href="sourceUrl(movement)" target="_blank" rel="noopener noreferrer" :title="sourceNote(movement)">{{ sourceTitle(movement) }} <v-icon icon="mdi-open-in-new" size="12" /></a><span v-else>{{ sourceTitle(movement) }}</span><small v-if="movement.note" class="good-warehouse__note" :title="movement.note">{{ movement.note }}</small></td>
                            <td class="good-warehouse__nowrap"><template v-if="!movement.source_type"><v-btn icon="mdi-pencil-outline" variant="text" size="x-small" :disabled="disabled || mutationBusy" :aria-label="`Изменить движение № ${movement.id}`" @click="editMovement(movement)" /><v-btn icon="mdi-delete-outline" variant="text" size="x-small" color="error" :loading="deleting === movement.id" :disabled="disabled || mutationBusy" :aria-label="`Удалить движение № ${movement.id}`" @click="removeMovement(movement)" /></template><v-icon v-else icon="mdi-link-lock" size="17" :title="sourceNote(movement)" color="grey" /></td>
                        </tr>
                        <tr v-if="!movements.length"><td colspan="6" class="good-warehouse__empty">{{ loading ? 'Загрузка движений…' : 'Движений по выбранным условиям нет' }}</td></tr>
                    </tbody>
                </v-table>
                <v-pagination v-if="movementMeta.last_page > 1" :model-value="movementPage" :length="movementMeta.last_page" :total-visible="5" density="compact" size="small" :disabled="loading" @update:model-value="pageMovements" />
                <p class="good-warehouse__hint">Приходы из закупок и списания по продажам изменяются в исходных документах. Цена учёта хранится в значении исходного движения.</p>
            </section>
            <section class="good-warehouse__section">
                <div class="good-warehouse__section-heading"><h3>Закупки товара <span>{{ purchaseMeta.total }}</span></h3><a :href="route('Ameise.commerce', { good_id: goodId })" target="_blank" rel="noopener noreferrer">Журнал закупок <v-icon icon="mdi-open-in-new" size="13" /></a></div>
                <v-table density="compact" class="good-warehouse__table">
                    <thead><tr><th>Документ</th><th>Поставщик</th><th class="text-right">Количество</th><th class="text-right">Цена / сумма</th><th>Склад / проведение</th></tr></thead>
                    <tbody>
                        <tr v-for="item in purchaseRows" :key="item.pivot_id || `${item.purchase.id}:${item.good_id}`">
                            <td class="good-warehouse__nowrap"><a :href="purchaseUrl(item.purchase.id)" target="_blank" rel="noopener noreferrer">№ {{ item.purchase.id }} <v-icon icon="mdi-open-in-new" size="12" /></a><small>{{ date(item.purchase.date) }}</small></td>
                            <td>{{ item.purchase.entity?.name || item.purchase.entity?.full_name || 'Поставщик не указан' }}<small v-if="item.purchase.entity?.INN">ИНН {{ item.purchase.entity.INN }}</small></td>
                            <td class="text-right good-warehouse__nowrap"><strong>{{ number(item.quantity) }}</strong><small>{{ measureName(item.measure_id) }}</small></td>
                            <td class="text-right good-warehouse__nowrap">{{ money(item.price) }} {{ currency(item.currency_id) }}<small><strong>{{ money(item.total) }} {{ currency(item.currency_id) }}</strong> за позицию</small></td>
                            <td><span class="good-warehouse__status" :class="{ 'is-received': received(item) }">{{ received(item) ? 'Оприходовано' : 'Без складского движения' }}</span><small>{{ purchaseWarehouse(item) || '—' }}</small></td>
                        </tr>
                        <tr v-if="!purchaseRows.length"><td colspan="5" class="good-warehouse__empty">{{ loading ? 'Загрузка закупок…' : 'Этот товар ещё не закупался' }}</td></tr>
                    </tbody>
                </v-table>
                <v-pagination v-if="purchaseMeta.last_page > 1" :model-value="purchasePage" :length="purchaseMeta.last_page" :total-visible="5" density="compact" size="small" :disabled="loading" @update:model-value="pagePurchases" />
                <p class="good-warehouse__hint">Количество, цена и сумма относятся к позиции этого товара. Документ открывается со всеми позициями для редактирования и оплаты.</p>
            </section>
        </template>
        <v-dialog :model-value="movementDialog" max-width="620" :persistent="saving" @update:model-value="value => { if (!value) closeMovement() }">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center"><span>{{ form.id ? `Движение № ${form.id}` : 'Новое движение товара' }}</span><v-spacer /><v-btn icon="mdi-close" variant="text" size="small" :disabled="saving" aria-label="Закрыть движение" @click="closeMovement" /></v-card-title>
                <v-card-text>
                    <p class="good-warehouse__hint mb-4">{{ overview?.name }} · {{ measureLabel }}</p>
                    <v-alert v-if="formError" type="error" density="compact" variant="tonal" class="mb-4">{{ formError }}</v-alert>
                    <form id="catalog-stock-movement" @submit.prevent="saveMovement">
                        <div class="good-warehouse__form-grid">
                            <v-select v-model="form.type" :items="movementTypes" item-title="title" item-value="value" label="Операция" variant="outlined" density="compact" :disabled="saving" :error-messages="formErrors.type" />
                            <v-select v-model="form.warehouse_id" :items="warehouseOptions" item-title="title" item-value="id" label="Склад" variant="outlined" density="compact" :disabled="saving" :error-messages="formErrors.warehouse_id" />
                            <v-text-field v-model="form.quantity" :label="`Количество, ${measureLabel}`" type="number" step="0.000001" :min="form.type === 'adjustment' ? undefined : 0.000001" :hint="form.type === 'adjustment' ? 'Изменение остатка: плюс — добавить, минус — уменьшить.' : 'Введите положительное количество.'" persistent-hint variant="outlined" density="compact" :disabled="saving" :error-messages="formErrors.quantity" />
                            <v-text-field v-model="form.unit_price" label="Учётная цена за единицу" type="number" step="0.01" min="0" variant="outlined" density="compact" :disabled="saving" :error-messages="formErrors.unit_price" />
                            <v-text-field v-model="form.moved_at" label="Дата движения" type="date" variant="outlined" density="compact" :disabled="saving" :error-messages="formErrors.moved_at" />
                            <v-text-field :model-value="measureLabel" label="Единица движения" readonly variant="outlined" density="compact" :error-messages="formErrors.measure_id" />
                        </div>
                        <v-textarea v-model="form.note" label="Основание / примечание" rows="2" variant="outlined" density="compact" :disabled="saving" :error-messages="formErrors.note" />
                    </form>
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn :disabled="saving" @click="closeMovement">Отмена</v-btn><v-btn color="primary" variant="flat" type="submit" form="catalog-stock-movement" :loading="saving" :disabled="disabled || (!form.id && !canCreateMovement)">Провести движение</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.good-warehouse { display: grid; gap: 16px; min-width: 0; padding: 4px 0 12px; }
.good-warehouse__heading, .good-warehouse__section-heading, .good-warehouse__actions { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
.good-warehouse h3 { display: flex; gap: 8px; align-items: center; font-size: 13px; font-weight: 650; color: #554360; }
.good-warehouse__heading p, .good-warehouse__hint { margin: 4px 0 0; color: #877c91; font-size: 11px; line-height: 1.5; }
.good-warehouse__summary { display: grid; grid-template-columns: 1.7fr repeat(3, 1fr); gap: 8px; }
.good-warehouse__summary > div { display: flex; flex-direction: column; gap: 5px; padding: 12px; border: 1px solid #e8e1ee; border-radius: 9px; background: #fbf9fd; }
.good-warehouse__summary span { color: #8a7b95; font-size: 10px; }
.good-warehouse__summary strong { font-size: 17px; color: #584568; font-weight: 600; }
.good-warehouse__section { min-width: 0; overflow: hidden; border: 1px solid #e8e1ed; border-radius: 9px; }
.good-warehouse__section-heading { padding: 10px 12px; background: #faf8fc; border-bottom: 1px solid #ece6f1; }
.good-warehouse__section-heading h3 span { border-radius: 5px; background: #eee8f3; padding: 2px 6px; font-size: 10px; color: #897797; }
.good-warehouse__section-heading :deep(.v-input) { flex: 0 1 225px; min-width: 160px; }
.good-warehouse__section-heading :deep(.v-field__input) { min-height: 34px; padding-block: 5px; font-size: 12px; }
.good-warehouse__section-heading a { font-size: 11px; }
.good-warehouse__table :deep(table) { font-size: 12px; }
.good-warehouse__table :deep(th) { font-size: 10px; color: #90809d !important; background: #fdfcfe; white-space: nowrap; }
.good-warehouse__table :deep(td) { padding: 8px 12px !important; color: #63556d; }
.good-warehouse__table small { display: block; margin-top: 3px; font-size: 10px; color: #918397; }
.good-warehouse__table strong { font-weight: 600; }
.good-warehouse a { color: #77548e; text-decoration: none; }
.good-warehouse a:hover { text-decoration: underline; }
.good-warehouse__nowrap { white-space: nowrap; }
.good-warehouse__note { max-width: 210px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.good-warehouse__table :deep(td.good-warehouse__empty) { padding: 22px 12px !important; text-align: center; color: #a294ac; font-size: 12px; }
.good-warehouse__section > .good-warehouse__hint { padding: 9px 12px; margin: 0; border-top: 1px solid #f0eaf5; }
.good-warehouse__status { font-size: 10px; color: #9a7e46; }
.good-warehouse__status.is-received { color: #398171; }
.good-warehouse .is-negative { color: #b44e62 !important; }
.good-warehouse__form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
@media (max-width: 680px) { .good-warehouse__summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } .good-warehouse__form-grid { grid-template-columns: minmax(0, 1fr); } }
</style>
