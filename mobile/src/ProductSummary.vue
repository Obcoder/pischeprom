<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { calendarDay, formatDeliveryDate } from './delivery-date.js'

const props = defineProps({
    api: { type: Object, required: true },
    date: { type: String, required: true },
    online: { type: Boolean, default: true },
})
const emit = defineEmits(['update:date'])
const products = ref([])
const meta = ref({ awaiting_orders_count: 0, ready_orders_count: 0 })
const loading = ref(false)
const error = ref('')
const search = ref('')
let requestId = 0
const days = [{ offset: -1, label: 'Вчера' }, { offset: 0, label: 'Сегодня' }, { offset: 1, label: 'Завтра' }]
const visibleProducts = computed(() => {
    const query = String(search.value || '').trim().toLocaleLowerCase('ru')
    return products.value.filter(product => product.name.toLocaleLowerCase('ru').includes(query))
})
const quantity = value => new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 6 }).format(Number(value || 0))
const orderLabel = count => ({ one: 'заказ', few: 'заказа', many: 'заказов', other: 'заказа' })[new Intl.PluralRules('ru').select(count)]

async function refresh() {
    const request = ++requestId
    loading.value = true
    error.value = ''
    products.value = []
    meta.value = { awaiting_orders_count: 0, ready_orders_count: 0 }
    try {
        const result = await props.api.products({ delivery_date: props.date })
        if (request !== requestId) return
        products.value = result.data
        meta.value = result.meta
    } catch (failure) {
        if (request === requestId) error.value = failure.message
    } finally {
        if (request === requestId) loading.value = false
    }
}
watch(() => props.date, refresh, { immediate: true })
onBeforeUnmount(() => { requestId += 1 })
</script>

<template>
    <section aria-label="Товары к сборке и отгрузке">
        <div class="delivery-filter">
            <div class="delivery-filter-heading"><v-icon icon="mdi-calendar-clock-outline" size="18" /><strong>День доставки</strong><span>{{ formatDeliveryDate(date) }}</span></div>
            <div class="delivery-day-buttons" role="group" aria-label="День сборки и отгрузки">
                <button v-for="day in days" :key="day.offset" :class="{ active: date === calendarDay(day.offset) }" :aria-pressed="date === calendarDay(day.offset)" @click="emit('update:date', calendarDay(day.offset))">{{ day.label }}</button>
            </div>
            <label class="delivery-calendar"><span>Выбрать дату</span><input :value="date" type="date" aria-label="Дата доставки для списка товаров" @input="emit('update:date', $event.target.value || calendarDay())" /></label>
        </div>

        <div class="summary-heading">
            <p class="section-hint">Общее количество товаров по всем открытым заказам на выбранный день доставки.</p>
            <v-btn icon="mdi-refresh" variant="text" aria-label="Обновить товары" :loading="loading" :disabled="!online" @click="refresh" />
        </div>
        <v-alert v-if="error" type="error" class="mb-4" role="alert">{{ error }}<v-btn variant="text" :disabled="!online" @click="refresh">Повторить</v-btn></v-alert>
        <div v-if="loading" aria-label="Загрузка товаров" aria-busy="true">
            <v-skeleton-loader v-for="n in 3" :key="n" type="list-item-two-line" class="mb-3 skeleton-card" />
        </div>
        <template v-else-if="!error">
            <div class="summary-counts" role="status">
                <div><span>К сборке</span><strong>{{ meta.awaiting_orders_count }} <small>{{ orderLabel(meta.awaiting_orders_count) }}</small></strong></div>
                <div><span>К отгрузке</span><strong>{{ meta.ready_orders_count }} <small>{{ orderLabel(meta.ready_orders_count) }}</small></strong></div>
            </div>
            <div v-if="!products.length" class="empty-state">
                <div class="hero-icon"><v-icon icon="mdi-package-variant-closed-check" size="36" /></div>
                <h2>Нет товаров на этот день</h2>
                <p class="muted">Выберите другую дату доставки. Отгруженные и закрытые заказы в список не входят.</p>
            </div>
            <template v-else>
                <v-text-field v-model="search" label="Найти товар" prepend-inner-icon="mdi-magnify" clearable hide-details density="comfortable" bg-color="surface" class="mb-4" />
                <div v-if="!visibleProducts.length" class="empty-state"><h2>Товар не найден</h2><p class="muted">Попробуйте другое название.</p></div>
                <div v-else class="summary-table-wrap">
                    <table class="summary-table">
                        <caption class="summary-caption">Количество по товарам · {{ formatDeliveryDate(date) }}</caption>
                        <thead><tr><th scope="col">Товар</th><th scope="col">К сборке</th><th scope="col">К отгрузке</th></tr></thead>
                        <tbody>
                            <tr v-for="product in visibleProducts" :key="product.key">
                                <th scope="row">{{ product.name }}<span class="summary-unit">{{ product.measure_name || 'Единица не задана' }}<template v-if="product.kilograms_per_unit && product.measure_name !== 'кг'"> · по {{ quantity(product.kilograms_per_unit) }} кг</template></span></th>
                                <td :class="{ 'has-quantity': Number(product.awaiting_quantity) > 0 }">{{ Number(product.awaiting_quantity) > 0 ? quantity(product.awaiting_quantity) : '—' }}</td>
                                <td :class="{ 'has-quantity': Number(product.ready_quantity) > 0 }">{{ Number(product.ready_quantity) > 0 ? quantity(product.ready_quantity) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="list-footnote">Товаров: {{ visibleProducts.length }} из {{ products.length }}. Разные единицы учитываются отдельно.</p>
            </template>
        </template>
    </section>
</template>

<style scoped>
.summary-heading { display: flex; align-items: flex-start; gap: 8px; }
.summary-heading .section-hint { flex: 1; }
.summary-counts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.summary-counts > div { display: flex; flex-direction: column; gap: 5px; padding: 14px; border-radius: 14px; background: #e9eee3; }
.summary-counts > div:first-child { background: #fff1da; }
.summary-counts span { font-size: 12px; color: #526653; }
.summary-counts strong { font-size: 22px; }
.summary-counts small { font-size: 12px; font-weight: 500; }
.summary-table-wrap { border: 1px solid #e1e7df; border-radius: 16px; overflow: hidden; background: #fff; }
.summary-table { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 13px; }
.summary-caption { padding: 12px; text-align: left; color: #6b786f; font-size: 12px; }
.summary-table th, .summary-table td { padding: 13px 10px; vertical-align: top; border-top: 1px solid #edf0e9; overflow-wrap: anywhere; }
.summary-table th { text-align: left; font-weight: 600; }
.summary-table thead th { background: #f8faf7; color: #65755e; font-size: 11px; }
.summary-table th:first-child { width: 48%; }
.summary-table td, .summary-table thead th:not(:first-child) { text-align: right; font-variant-numeric: tabular-nums; }
.summary-table td { color: #8a968b; }
.summary-table td.has-quantity { font-weight: 700; color: #245a40; }
.summary-unit { display: block; margin-top: 4px; font-size: 11px; color: #75846f; font-weight: 400; }
</style>
