<script setup>
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import { onBeforeUnmount, ref, watch } from 'vue'
import { route } from 'ziggy-js'

const props = defineProps({
    modelValue: Boolean,
    entity: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])
const orders = ref([])
const loading = ref(false)
const error = ref('')
const page = ref(1)
const requestedPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
let controller = null
let requestId = 0
let disposed = false

function cancelRequest() {
    requestId += 1
    controller?.abort()
    controller = null
    loading.value = false
}

function updateDialog(value) {
    if (!value) cancelRequest()
    emit('update:modelValue', value)
}

async function loadOrders(targetPage = page.value) {
    if (disposed || !props.modelValue || !props.entity?.id) return

    cancelRequest()
    const currentRequest = requestId
    const entityId = props.entity.id
    const requestController = new AbortController()
    controller = requestController
    requestedPage.value = targetPage
    loading.value = true
    error.value = ''
    orders.value = []

    const isCurrent = () => !disposed
        && currentRequest === requestId
        && !requestController.signal.aborted
        && props.modelValue
        && props.entity?.id === entityId

    try {
        const { data } = await axios.get('/api/orders', {
            params: {
                entity_id: entityId,
                page: targetPage,
                per_page: 25,
                sort_by: 'submitted_at',
                sort_direction: 'desc',
            },
            signal: requestController.signal,
        })

        if (!isCurrent()) return

        orders.value = Array.isArray(data.data) ? data.data : []
        page.value = data.meta?.current_page ?? targetPage
        lastPage.value = data.meta?.last_page ?? 1
        total.value = data.meta?.total ?? orders.value.length
    } catch (failure) {
        if (!isCurrent() || axios.isCancel(failure)) return
        error.value = 'Не удалось загрузить заказы. Попробуйте ещё раз.'
    } finally {
        if (isCurrent()) {
            loading.value = false
            controller = null
        }
    }
}

function orderUrl(order) {
    try {
        return route('Ameise.orders.show', order.id)
    } catch {
        return `/Ameise/orders/${order.id}`
    }
}

function formatDate(value) {
    if (!value) return '—'
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('ru-RU')
}

function formatMoney(value, currency = 'RUB') {
    const amount = Number(value)
    if (value == null || !Number.isFinite(amount)) return '—'
    return `${amount.toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ${!currency || currency === 'RUB' ? '₽' : currency}`
}

watch(
    () => [props.modelValue, props.entity?.id],
    () => {
        cancelRequest()
        orders.value = []
        error.value = ''
        page.value = 1
        requestedPage.value = 1
        lastPage.value = 1
        total.value = 0
        loadOrders()
    },
    { immediate: true },
)

onBeforeUnmount(() => {
    disposed = true
    cancelRequest()
})
</script>

<template>
    <v-dialog :model-value="modelValue" max-width="760" scrollable @update:model-value="updateDialog">
        <v-card class="entity-orders-dialog" theme="dark">
            <div class="entity-orders-dialog__header">
                <div class="entity-orders-dialog__heading">
                    <h2>Заказы · {{ entity?.name || 'Entity' }}</h2>
                    <span>Все статусы<span v-if="!loading && !error"> · {{ total }}</span></span>
                </div>
                <v-btn icon="mdi-close" size="small" variant="text" aria-label="Закрыть заказы" @click="updateDialog(false)" />
            </div>

            <v-progress-linear v-if="loading" indeterminate color="blue-grey-lighten-3" height="2" />

            <div class="entity-orders-dialog__body" :aria-busy="loading">
                <div v-if="error" class="entity-orders-dialog__message" role="alert">
                    <span>{{ error }}</span>
                    <v-btn size="small" variant="tonal" @click="loadOrders(requestedPage)">Повторить</v-btn>
                </div>
                <div v-else-if="loading" class="entity-orders-dialog__message" role="status">Загрузка заказов…</div>
                <div v-else-if="!orders.length" class="entity-orders-dialog__message">У этого Entity пока нет заказов.</div>
                <table v-else aria-label="Заказы Entity">
                    <thead>
                        <tr>
                            <th scope="col">Заказ</th>
                            <th scope="col">Статус</th>
                            <th scope="col">Дата</th>
                            <th scope="col" class="entity-orders-dialog__amount">Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="order in orders" :key="order.id">
                            <td><Link :href="orderUrl(order)">{{ order.number || `#${order.id}` }}</Link></td>
                            <td>
                                <span class="entity-orders-dialog__status">
                                    <span class="entity-orders-dialog__status-dot" :style="{ backgroundColor: order.status?.color || '#94a3b8' }" />
                                    {{ order.status?.name || '—' }}
                                </span>
                            </td>
                            <td class="entity-orders-dialog__date">{{ formatDate(order.submitted_at || order.created_at) }}</td>
                            <td class="entity-orders-dialog__amount">{{ formatMoney(order.total_amount, order.currency_code) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="lastPage > 1" class="entity-orders-dialog__pagination">
                <v-btn icon="mdi-chevron-left" size="x-small" variant="text" :disabled="loading || page <= 1" aria-label="Предыдущая страница заказов" @click="loadOrders(page - 1)" />
                <span>{{ page }} / {{ lastPage }}</span>
                <v-btn icon="mdi-chevron-right" size="x-small" variant="text" :disabled="loading || page >= lastPage" aria-label="Следующая страница заказов" @click="loadOrders(page + 1)" />
            </div>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.entity-orders-dialog {
    max-height: 80vh;
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 14px;
    background: linear-gradient(135deg, #414748 0%, #535958 100%);
    color: #f5f1e9;
}

.entity-orders-dialog__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 12px;
}

.entity-orders-dialog__heading {
    min-width: 0;
}

.entity-orders-dialog__heading h2 {
    overflow-wrap: anywhere;
    font-size: 14px;
    line-height: 1.3;
}

.entity-orders-dialog__heading > span,
.entity-orders-dialog__pagination {
    color: rgba(255, 255, 255, 0.7);
    font-size: 11px;
}

.entity-orders-dialog__body {
    min-height: 90px;
    overflow: auto;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.entity-orders-dialog__body table {
    width: 100%;
    border-collapse: collapse;
}

.entity-orders-dialog__body th,
.entity-orders-dialog__body td {
    padding: 7px 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    text-align: left;
    font-size: 11px;
}

.entity-orders-dialog__body th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #414748;
    color: rgba(255, 255, 255, 0.7);
}

.entity-orders-dialog__body a {
    color: #eaf4ff;
    font-weight: 700;
    text-decoration: none;
}

.entity-orders-dialog__body a:hover {
    text-decoration: underline;
}

.entity-orders-dialog__status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.entity-orders-dialog__status-dot {
    width: 6px;
    height: 6px;
    flex: 0 0 6px;
    border-radius: 50%;
}

.entity-orders-dialog__date,
.entity-orders-dialog__amount {
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.entity-orders-dialog__body .entity-orders-dialog__amount {
    text-align: right;
}

.entity-orders-dialog__message {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
    min-height: 90px;
    padding: 12px;
    font-size: 12px;
    text-align: center;
}

.entity-orders-dialog__pagination {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 5px 8px;
}
</style>
