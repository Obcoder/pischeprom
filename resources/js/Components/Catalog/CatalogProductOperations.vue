<script setup>
import axios from 'axios'
import { computed, onScopeDispose, reactive, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import ProductUnitConsumersCard from '@/Components/ProductUnitConsumersCard.vue'
import ProductEntityConsumptionsCard from '@/Components/ProductEntityConsumptionsCard.vue'
import ProductMarketPanel from '@/Components/ProductMarketPanel.vue'
import { emptyProductTranslationForm, productTranslationFields } from '@/Pages/Helpers/productLanguages.js'

const props = defineProps({
    productId: { type: [Number, String], required: true },
    activeTab: { type: String, default: 'translations' },
    active: { type: Boolean, default: true },
})
const emit = defineEmits(['state', 'loaded', 'changed'])
const page = usePage()
const product = ref(null)
const loading = ref(false)
const error = ref('')
const saving = ref(false)
const editing = ref(false)
const saved = ref(false)
const fieldErrors = ref({})
const form = reactive(emptyProductTranslationForm())
const baseline = ref(JSON.stringify(form))
const search = ref('')
const visitedTabs = ref(new Set())
const dirty = computed(() => JSON.stringify(form) !== baseline.value)
const canViewAiSales = computed(() => Boolean(page.props.auth?.permissions?.ai_sales?.view))
const filledLanguages = computed(() => productTranslationFields.filter(field => String(form[field.key] || '').trim()).length)
const relationHeaders = computed(() => [
    { title: 'ID', key: 'id', width: 72 },
    { title: props.activeTab === 'components' ? 'Компонент' : 'Название', key: 'name' },
    ...(props.activeTab === 'units' ? [{ title: 'Деятельность', key: 'product_action.name' }] : []),
])
const relationRows = computed(() => (product.value?.[props.activeTab] || []).map(row => ({ ...row, name: title(row) })))
const goods = computed(() => {
    const query = String(search.value || '').trim().toLocaleLowerCase('ru-RU')
    return (product.value?.goods || []).filter(good => !query || `${good.id} ${title(good)}`.toLocaleLowerCase('ru-RU').includes(query))
})
const sales = computed(() => {
    const ids = new Set((product.value?.goods || []).map(good => String(good.id)))
    return (product.value?.sales || []).map(sale => {
        const productGoods = (sale.goods || []).filter(good => ids.has(String(good.id)))
        return {
            ...sale,
            productGoods,
            productTotal: productGoods.reduce((total, good) => total + Number(good.pivot?.total ?? Number(good.pivot?.quantity || 0) * Number(good.pivot?.price || 0)), 0),
        }
    })
})
const saleHeaders = [
    { title: 'Дата / документ', key: 'date', width: 150 },
    { title: 'Покупатель', key: 'entity.name' },
    { title: 'Товары продукта', key: 'productGoods', sortable: false },
    { title: 'Сумма по продукту', key: 'productTotal', align: 'end' },
    { title: 'Всего по документу', key: 'total', align: 'end' },
]

function title(value) { return value?.name || value?.rus || value?.title || value?.eng || `#${value?.id ?? '—'}` }
function money(value) { return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0)) }
function quantity(value) { return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 6 }).format(Number(value || 0)) }
function date(value) {
    if (!value) return '—'
    const parsed = new Date(value)
    return Number.isNaN(parsed.getTime()) ? value : new Intl.DateTimeFormat('ru-RU').format(parsed)
}
function hydrate(data) {
    productTranslationFields.forEach(field => { form[field.key] = data?.[field.key] ?? '' })
    baseline.value = JSON.stringify(form)
}
function syncName(name) {
    if (!product.value || typeof name !== 'string') return
    const clean = JSON.parse(baseline.value)
    if (form.rus === clean.rus) form.rus = name
    clean.rus = name
    baseline.value = JSON.stringify(clean)
    product.value = { ...product.value, rus: name }
}
function reset() {
    hydrate(product.value)
    editing.value = false
    saved.value = false
    fieldErrors.value = {}
    error.value = ''
}
let version = 0
let controller = null
let loadedId = null
let disposed = false
function cancelLoad() {
    version++
    controller?.abort()
    controller = null
    loading.value = false
}
async function refresh({ throwOnError = false } = {}) {
    cancelLoad()
    if (!props.active || !props.productId) return null
    const requestVersion = version
    const id = props.productId
    controller = new AbortController()
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get(`/api/products/${id}`, { signal: controller.signal })
        if (disposed || requestVersion !== version || String(id) !== String(props.productId)) return null
        if (!data?.id || String(data.id) !== String(id)) throw new Error('Invalid product response')
        product.value = data
        if (!dirty.value) hydrate(data)
        loadedId = String(id)
        emit('loaded', data)
        return data
    } catch (failure) {
        if (requestVersion !== version || disposed) return null
        error.value = failure.response?.data?.message || 'Не удалось загрузить данные продукта.'
        if (throwOnError) throw failure
        return null
    } finally {
        if (requestVersion === version) { loading.value = false; controller = null }
    }
}
async function saveTranslations() {
    if (!product.value || saving.value) return
    fieldErrors.value = {}
    error.value = ''
    saved.value = false
    if (!String(form.rus || '').trim()) {
        fieldErrors.value = { rus: ['Укажите название на русском языке.'] }
        return
    }
    const id = props.productId
    // The catalogue owns category and publication fields. Send only this editor's fields.
    const payload = Object.fromEntries(productTranslationFields.map(field => [field.key, String(form[field.key] || '').trim() || null]))
    cancelLoad()
    saving.value = true
    try {
        const { data } = await axios.put(`/api/products/${id}`, payload)
        if (disposed || String(id) !== String(props.productId)) return
        product.value = data
        hydrate(data)
        editing.value = false
        saved.value = true
        emit('changed', data)
    } catch (failure) {
        if (disposed || String(id) !== String(props.productId)) return
        fieldErrors.value = failure.response?.data?.errors || {}
        error.value = failure.response?.data?.message || 'Не удалось сохранить переводы продукта.'
    } finally { saving.value = false }
}
watch(() => props.productId, () => {
    cancelLoad()
    product.value = null
    loadedId = null
    search.value = ''
    visitedTabs.value = new Set([props.activeTab])
    reset()
    if (props.active) refresh()
}, { immediate: true, flush: 'sync' })
watch(() => props.active, active => {
    if (!active) cancelLoad()
    else if (loadedId !== String(props.productId)) refresh()
})
watch(() => props.activeTab, tab => {
    search.value = ''
    visitedTabs.value = new Set([...visitedTabs.value, tab])
}, { immediate: true })
watch([dirty, saving], ([hasDraft, busy]) => emit('state', { dirty: hasDraft, busy, dirtyTab: hasDraft ? 'translations' : null }), { immediate: true, flush: 'sync' })
onScopeDispose(() => { disposed = true; cancelLoad() })
defineExpose({ reset, refresh, syncName })
</script>

<template>
    <section class="catalog-product-operations">
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">
            {{ error }}
            <template v-if="!product" #append><v-btn variant="text" size="small" @click="refresh">Повторить</v-btn></template>
        </v-alert>
        <v-skeleton-loader v-if="loading && !product" type="article, table" />
        <template v-if="product">
            <div class="d-flex align-center ga-2 mb-3 flex-wrap">
                <v-chip size="small" variant="tonal">Продукт № {{ product.id }}</v-chip>
                <span class="text-body-2">{{ product.rus }}</span>
                <v-spacer />
                <v-btn icon="mdi-refresh" size="small" variant="text" :loading="loading" :disabled="saving" aria-label="Обновить данные продукта" @click="refresh" />
            </div>

            <v-card v-show="activeTab === 'translations'" variant="outlined">
                <v-card-title class="d-flex align-center ga-2 flex-wrap">
                    Переводы
                    <v-chip size="small" variant="tonal">{{ filledLanguages }} / {{ productTranslationFields.length }}</v-chip>
                    <v-chip v-if="saved" size="small" color="success">Сохранено</v-chip>
                    <v-spacer />
                    <v-btn v-if="!editing" size="small" variant="tonal" prepend-icon="mdi-pencil-outline" @click="editing = true; saved = false">Редактировать</v-btn>
                </v-card-title>
                <v-card-text>
                    <v-form @submit.prevent="saveTranslations">
                        <div class="product-translations">
                            <div v-for="field in productTranslationFields" :key="field.key" class="product-translations__field">
                                <v-text-field v-if="editing" v-model="form[field.key]" :label="`${field.label} (${field.code})`" :error-messages="fieldErrors[field.key]" :disabled="saving" variant="outlined" density="compact" hide-details="auto" maxlength="255" />
                                <template v-else>
                                    <div class="text-caption text-medium-emphasis">{{ field.label }} · {{ field.code }}</div>
                                    <div class="text-body-2">{{ form[field.key] || '—' }}</div>
                                </template>
                            </div>
                        </div>
                        <div v-if="editing" class="d-flex justify-end ga-2 mt-4">
                            <v-btn variant="text" :disabled="saving" @click="reset">Отмена</v-btn>
                            <v-btn type="submit" color="primary" :loading="saving" :disabled="!dirty">Сохранить переводы</v-btn>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>

            <v-card v-if="['manufacturers', 'components', 'units'].includes(activeTab)" variant="outlined">
                <v-card-text class="pb-2">
                    <v-text-field v-model="search" label="Поиск по названию или ID" prepend-inner-icon="mdi-magnify" density="compact" variant="outlined" hide-details clearable />
                </v-card-text>
                <v-data-table :items="relationRows" :headers="relationHeaders" :search="search" :items-per-page="15" :items-per-page-options="[15, 30, 60]" :hide-default-footer="relationRows.length <= 15" :mobile="false" density="compact" no-data-text="Связанных записей пока нет" items-per-page-text="На странице">
                    <template #item.name="{ item }">
                        <a v-if="activeTab !== 'components'" :href="route('web.unit.show', item.id)">{{ item.name }}</a>
                        <span v-else>{{ item.name }}</span>
                    </template>
                    <template #item.product_action.name="{ item }">{{ item.product_action?.name || '—' }}</template>
                </v-data-table>
            </v-card>

            <div v-if="activeTab === 'goods'">
                <v-text-field v-model="search" label="Поиск товаров продукта" prepend-inner-icon="mdi-magnify" density="compact" variant="outlined" hide-details clearable class="mb-3" />
                <v-expansion-panels v-if="goods.length" variant="accordion">
                    <v-expansion-panel v-for="good in goods" :key="good.id">
                        <v-expansion-panel-title>
                            <div class="d-flex align-center ga-2 flex-wrap">
                                <span>{{ title(good) }}</span>
                                <v-chip size="x-small" variant="tonal">№ {{ good.id }}</v-chip>
                                <v-chip size="x-small" variant="outlined">Предложения: {{ good.quotations?.length || 0 }}</v-chip>
                            </div>
                        </v-expansion-panel-title>
                        <v-expansion-panel-text>
                            <v-btn :href="route('Ameise.good.show', good.id)" size="small" variant="tonal" prepend-icon="mdi-open-in-new" class="mb-3">Открыть карточку товара</v-btn>
                            <v-table v-if="good.quotations?.length" density="compact">
                                <thead><tr><th>Дата</th><th>Поставщик</th><th class="text-right">Цена</th><th>Ед. изм.</th><th class="text-right">Делитель</th></tr></thead>
                                <tbody>
                                    <tr v-for="quotation in good.quotations" :key="quotation.id">
                                        <td>{{ date(quotation.created_at) }}</td>
                                        <td><a v-if="quotation.unit?.id" :href="route('web.unit.show', quotation.unit.id)">{{ title(quotation.unit) }}</a><span v-else>—</span></td>
                                        <td class="text-right text-no-wrap">{{ money(quotation.price) }} {{ quotation.currency?.code || '' }}</td>
                                        <td>{{ quotation.measure?.name || '—' }}</td>
                                        <td class="text-right">{{ quantity(quotation.denominator || 1) }}</td>
                                    </tr>
                                </tbody>
                            </v-table>
                            <p v-else class="text-body-2 text-medium-emphasis">Предложений поставщиков пока нет.</p>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                </v-expansion-panels>
                <v-alert v-else type="info" variant="tonal" density="compact">Товары не найдены.</v-alert>
            </div>

            <div v-if="visitedTabs.has('consumers')" v-show="activeTab === 'consumers'" class="product-consumers">
                <ProductUnitConsumersCard :consumers="product.consumers || []" />
                <ProductEntityConsumptionsCard :key="product.id" :product-id="product.id" />
            </div>

            <div v-if="visitedTabs.has('sales')" v-show="activeTab === 'sales'" class="product-sales">
                <v-card variant="outlined">
                    <v-card-title>Продажи товаров продукта</v-card-title>
                    <v-data-table :headers="saleHeaders" :items="sales" :items-per-page="15" :items-per-page-options="[15, 30, 60]" :hide-default-footer="sales.length <= 15" :mobile="false" density="compact" no-data-text="Продаж пока нет" items-per-page-text="На странице">
                        <template #item.date="{ item }"><div>{{ date(item.date) }}</div><a class="text-caption" :href="route('Ameise.sales', { sale_id: item.id })">Продажа № {{ item.id }}</a></template>
                        <template #item.entity.name="{ item }"><a v-if="item.entity?.id" :href="route('Ameise.entity.show', item.entity.id)">{{ title(item.entity) }}</a><span v-else>—</span></template>
                        <template #item.productGoods="{ item }"><div v-for="good in item.productGoods" :key="good.pivot?.id || good.id" class="py-1"><a :href="route('Ameise.good.show', good.id)">{{ title(good) }}</a><div class="text-caption text-medium-emphasis">{{ quantity(good.pivot?.quantity) }} × {{ money(good.pivot?.price) }}</div></div></template>
                        <template #item.productTotal="{ item }"><span class="text-no-wrap font-weight-medium">{{ money(item.productTotal) }}</span></template>
                        <template #item.total="{ item }"><span class="text-no-wrap">{{ money(item.total) }}</span></template>
                    </v-data-table>
                </v-card>
            </div>

            <ProductMarketPanel
                v-if="visitedTabs.has('market')"
                v-show="activeTab === 'market'"
                :key="product.id"
                :product-id="product.id"
                :product-name="product.rus || ''"
                :can-view-ai-sales="canViewAiSales"
                :active="active && activeTab === 'market'"
            />
        </template>
    </section>
</template>

<style scoped>
.catalog-product-operations { min-width: 0; }
.catalog-product-operations :deep(.v-card-title) { white-space: normal; font-size: 1rem; }
.catalog-product-operations :deep(.v-data-table-footer) { flex-wrap: wrap; }
.catalog-product-operations a { color: rgb(var(--v-theme-primary)); text-decoration: none; }
.catalog-product-operations a:hover { text-decoration: underline; }
.product-translations { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.product-translations__field { min-width: 0; overflow-wrap: anywhere; }
.product-consumers { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; align-items: start; }
.product-sales { display: grid; gap: 16px; }
@media (max-width: 960px) { .product-translations { grid-template-columns: repeat(2, minmax(0, 1fr)); } .product-consumers { grid-template-columns: 1fr; } }
@media (max-width: 600px) { .product-translations { grid-template-columns: 1fr; } }
</style>
