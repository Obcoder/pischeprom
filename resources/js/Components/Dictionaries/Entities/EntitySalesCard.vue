<script setup>
import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'
import { route } from 'ziggy-js'

const props = defineProps({
    entity: {
        type: Object,
        default: null,
    },
})

const rows = ref([])
const months = ref([])
const goodsSummary = ref([])
const measures = ref([])
const totalItems = ref(0)
const totalAmount = ref(0)
const loading = ref(false)
const error = ref('')

const options = reactive({
    page: 1,
    itemsPerPage: 200,
    sortBy: [{ key: 'date', order: 'desc' }],
})

const headers = [
    { title: 'Дата', key: 'date', sortable: true, width: '86px' },
    { title: 'Сумма', key: 'total', sortable: true, width: '110px', align: 'end' },
    { title: 'Товары продажи', key: 'goods', sortable: false },
    { title: 'Предыдущая', key: 'previous_sale', sortable: false, width: '128px' },
]

const groupBy = [{ key: 'month', order: 'desc' }]
const entityId = computed(() => props.entity?.id || null)
const measuresById = computed(() => new Map(measures.value.map((measure) => [Number(measure.id), measure])))

function toNumber(value, fallback = 0) {
    const number = Number(value ?? fallback)
    return Number.isFinite(number) ? number : fallback
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(toNumber(value))
}

function formatQuantity(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    }).format(toNumber(value))
}

function formatDate(value) {
    if (!value) return '-'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
    }).format(new Date(value))
}

function monthLabel(value) {
    if (!value) return 'Без месяца'
    const [year, month] = String(value).split('-')
    return `${month}.${year}`
}

function saleGoods(sale) {
    return sale?.goods || []
}

function goodHref(good) {
    return good?.id ? route('Ameise.good.show', good.id) : null
}

function vatText(good) {
    if (!good?.vat_rate) return 'НДС -'
    return `${good.vat_rate.title || 'НДС'} ${good.vat_rate.rate}%`
}

function measureText(good) {
    const measureId = good?.pivot?.measure_id || good?.measure_id
    return good?.pivot?.measure_name || good?.measure_name || measuresById.value.get(Number(measureId))?.name || '-'
}

async function fetchSales() {
    if (!entityId.value) {
        rows.value = []
        months.value = []
        goodsSummary.value = []
        totalItems.value = 0
        totalAmount.value = 0
        return
    }

    loading.value = true
    error.value = ''

    try {
        const sort = options.sortBy?.[0] || { key: 'date', order: 'desc' }
        const [salesRes, measuresRes] = await Promise.all([
            axios.get('/api/sales', {
                params: {
                    server: 1,
                    entity_id: entityId.value,
                    goods_summary: 1,
                    page: options.page,
                    itemsPerPage: options.itemsPerPage,
                    sortBy: sort.key,
                    sortDesc: sort.order === 'desc',
                },
            }),
            measures.value.length ? Promise.resolve({ data: measures.value }) : axios.get('/api/measures'),
        ])
        const data = salesRes.data

        rows.value = data.data || []
        totalItems.value = data.meta?.total || 0
        totalAmount.value = data.meta?.total_amount || 0
        months.value = data.meta?.months || []
        goodsSummary.value = data.meta?.goods_summary || []
        measures.value = measuresRes.data.data || measuresRes.data || []
    } catch (err) {
        console.error('entity sales error:', err?.response?.data || err)
        error.value = err?.response?.data?.message || 'Не удалось загрузить продажи entity.'
    } finally {
        loading.value = false
    }
}

function handleOptionsUpdate(nextOptions) {
    options.page = nextOptions.page
    options.itemsPerPage = 200
    options.sortBy = nextOptions.sortBy?.length ? nextOptions.sortBy : [{ key: 'date', order: 'desc' }]
    fetchSales()
}

watch(entityId, () => {
    options.page = 1
    fetchSales()
}, { immediate: true })
</script>

<template>
    <v-card v-if="entity" class="entity-sales-card">
        <div class="entity-sales-card__head">
            <div>
                <div class="entity-sales-card__eyebrow">Продажи entity</div>
                <h2>{{ entity.name }}</h2>
            </div>

            <div class="entity-sales-card__stats">
                <span>{{ totalItems }} продаж</span>
                <strong>{{ formatMoney(totalAmount) }}</strong>
            </div>
        </div>

        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="ma-2">
            {{ error }}
        </v-alert>

        <div class="entity-sales-layout">
            <section class="entity-sales-panel entity-sales-panel--main">
                <div class="entity-sales-panel__title">
                    <span>Продажи по месяцам</span>
                    <small>server-side, 200 строк</small>
                </div>

                <v-data-table-server
                    :headers="headers"
                    :items="rows"
                    :items-length="totalItems"
                    :loading="loading"
                    :page="options.page"
                    :items-per-page="200"
                    :sort-by="options.sortBy"
                    :group-by="groupBy"
                    fixed-header
                    density="compact"
                    class="entity-sales-grid"
                    item-value="id"
                    height="430"
                    @update:options="handleOptionsUpdate"
                >
                    <template #group-header="{ item, columns, toggleGroup, isGroupOpen }">
                        <tr class="entity-sales-grid__month-row">
                            <td :colspan="columns.length">
                                <button type="button" @click="toggleGroup(item)">
                                    <v-icon :icon="isGroupOpen(item) ? 'mdi-chevron-down' : 'mdi-chevron-right'" size="15" />
                                    {{ monthLabel(item.value) }}
                                </button>
                            </td>
                        </tr>
                    </template>

                    <template #item.date="{ item }">
                        <span class="entity-sales-date">{{ formatDate(item.date) }}</span>
                    </template>

                    <template #item.total="{ item }">
                        <strong class="entity-sales-money">{{ formatMoney(item.total) }}</strong>
                    </template>

                    <template #item.goods="{ item }">
                        <div class="entity-sale-goods">
                            <a
                                v-for="good in saleGoods(item)"
                                :key="`${item.id}-${good.id}-${good.pivot.measure_id}`"
                                :href="goodHref(good)"
                                class="entity-sale-good"
                            >
                                <span>{{ good.name }}</span>
                                <small>{{ formatQuantity(good.pivot.quantity) }} {{ measureText(good) }}</small>
                                <b>{{ formatMoney(good.pivot.total) }}</b>
                            </a>
                            <span v-if="!saleGoods(item).length" class="entity-sales-empty">Товаров нет</span>
                        </div>
                    </template>

                    <template #item.previous_sale="{ item }">
                        <div v-if="item.previous_sale" class="entity-sales-prev">
                            <strong>{{ formatMoney(item.previous_sale.total) }}</strong>
                            <span>{{ item.previous_sale.days }} дн.</span>
                        </div>
                        <span v-else class="entity-sales-empty">Первая</span>
                    </template>
                </v-data-table-server>
            </section>

            <section class="entity-sales-panel entity-sales-panel--summary">
                <div class="entity-sales-panel__title">
                    <span>Покупал товары</span>
                    <small>агрегировано по good + ед.</small>
                </div>

                <div class="entity-goods-summary">
                    <div class="entity-goods-summary__head">
                        <span>Good</span>
                        <span>Кол-во</span>
                        <span>Ед.</span>
                        <span>Сумма</span>
                        <span>Продаж</span>
                    </div>

                    <div
                        v-for="good in goodsSummary"
                        :key="`${good.id}-${good.measure_id}`"
                        class="entity-goods-summary__row"
                    >
                        <a :href="goodHref(good)">
                            <strong>{{ good.name }}</strong>
                            <small>{{ vatText(good) }} / тарность {{ good.denominator || '-' }}</small>
                        </a>
                        <b>{{ formatQuantity(good.quantity) }}</b>
                        <span>{{ good.measure_name || '-' }}</span>
                        <b>{{ formatMoney(good.total) }}</b>
                        <span>{{ good.sales_count }}</span>
                    </div>

                    <div v-if="!goodsSummary.length && !loading" class="entity-goods-summary__empty">
                        У entity пока нет покупок товаров.
                    </div>
                </div>
            </section>
        </div>
    </v-card>
</template>

<style scoped>
.entity-sales-card {
    overflow: hidden;
    border: 1px solid rgba(92, 53, 150, 0.18);
    border-radius: 22px;
    background: #fbf9ff;
    box-shadow: 0 18px 38px rgba(48, 20, 10, 0.08);
}

.entity-sales-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 16px;
    background: linear-gradient(135deg, #3f1d1d 0%, #5d317d 100%);
    color: #fff;
}

.entity-sales-card__eyebrow {
    margin-bottom: 4px;
    color: rgba(255, 255, 255, 0.64);
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.entity-sales-card__head h2 {
    margin: 0;
    font-size: 1.12rem;
    font-weight: 950;
}

.entity-sales-card__stats {
    display: grid;
    justify-items: end;
    gap: 2px;
}

.entity-sales-card__stats span {
    color: rgba(255, 255, 255, 0.68);
    font-size: 0.72rem;
    font-weight: 800;
}

.entity-sales-card__stats strong {
    font-family: 'Courier New', monospace;
    font-size: 1.08rem;
}

.entity-sales-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(360px, 0.9fr);
    gap: 10px;
    padding: 10px;
}

.entity-sales-panel {
    min-width: 0;
    overflow: hidden;
    border: 1px solid rgba(92, 53, 150, 0.18);
    border-radius: 12px;
    background: #fff;
}

.entity-sales-panel__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-height: 32px;
    padding: 6px 8px;
    border-bottom: 1px solid #ddd5f5;
    background: #f1ecff;
    color: #321c63;
}

.entity-sales-panel__title span {
    font-size: 0.78rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.entity-sales-panel__title small {
    color: #7c6b9c;
    font-size: 0.68rem;
}

.entity-sales-grid {
    font-size: 10.5px;
}

.entity-sales-grid :deep(thead th) {
    height: 26px !important;
    background: #5b3bb0 !important;
    color: #fff !important;
    border-right: 1px solid rgba(255, 255, 255, 0.24);
    font-size: 10px;
    font-weight: 900 !important;
}

.entity-sales-grid :deep(tbody td) {
    height: 28px !important;
    padding: 2px 5px !important;
    border-right: 1px solid #ddd5f5;
    border-bottom: 1px solid #ddd5f5;
}

.entity-sales-grid__month-row td {
    height: 23px !important;
    background: #eee7ff !important;
    color: #38216e;
    font-weight: 900;
}

.entity-sales-grid__month-row button {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10.5px;
    font-weight: 900;
}

.entity-sales-date,
.entity-sales-money,
.entity-sales-prev strong,
.entity-sale-good b,
.entity-goods-summary__row b {
    color: #2f1678;
    font-family: 'Courier New', monospace;
}

.entity-sales-money {
    display: block;
    text-align: right;
}

.entity-sale-goods {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}

.entity-sale-good {
    display: inline-grid;
    grid-template-columns: minmax(110px, 1fr) auto auto;
    gap: 5px;
    align-items: center;
    max-width: 360px;
    padding: 1px 5px;
    border: 1px solid #d9cff8;
    background: #fbf9ff;
    color: #2e2058;
    text-decoration: none;
}

.entity-sale-good span {
    overflow: hidden;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-sale-good small {
    color: #806fa5;
    font-size: 9px;
}

.entity-sales-prev {
    display: grid;
    gap: 1px;
    line-height: 1.05;
    text-align: right;
}

.entity-sales-prev span,
.entity-sales-empty {
    color: #806fa5;
    font-size: 9px;
}

.entity-goods-summary {
    max-height: 430px;
    overflow: auto;
    font-size: 10.5px;
}

.entity-goods-summary__head,
.entity-goods-summary__row {
    display: grid;
    grid-template-columns: minmax(190px, 1fr) 86px 54px 92px 50px;
    align-items: center;
}

.entity-goods-summary__head {
    position: sticky;
    top: 0;
    z-index: 1;
    min-height: 26px;
    background: #5b3bb0;
    color: #fff;
    font-size: 9.5px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.entity-goods-summary__head span,
.entity-goods-summary__row > * {
    min-width: 0;
    padding: 3px 5px;
    border-right: 1px solid #ddd5f5;
}

.entity-goods-summary__row {
    min-height: 30px;
    border-bottom: 1px solid #ddd5f5;
}

.entity-goods-summary__row:hover {
    background: #f6f2ff;
}

.entity-goods-summary__row a {
    display: grid;
    gap: 1px;
    color: #2f1678;
    text-decoration: none;
}

.entity-goods-summary__row a strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-goods-summary__row a small {
    color: #806fa5;
    font-size: 9px;
}

.entity-goods-summary__row b {
    text-align: right;
}

.entity-goods-summary__row span {
    color: #6c5b93;
}

.entity-goods-summary__empty {
    padding: 14px;
    color: #806fa5;
    font-size: 0.78rem;
}

@media (max-width: 1180px) {
    .entity-sales-layout {
        grid-template-columns: 1fr;
    }

    .entity-goods-summary__head,
    .entity-goods-summary__row {
        grid-template-columns: minmax(180px, 1fr) 80px 50px 86px 46px;
    }
}

@media (max-width: 760px) {
    .entity-sales-card__head {
        align-items: flex-start;
        flex-direction: column;
    }

    .entity-sales-card__stats {
        justify-items: start;
    }
}
</style>
