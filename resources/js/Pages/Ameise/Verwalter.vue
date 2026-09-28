<script setup>
import { Link } from '@inertiajs/vue3'
import { useHead } from '@unhead/vue'
import { route } from 'ziggy-js'
import { computed, ref, watch } from 'vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import AvitoWaitingList from '@/Components/Avito/AvitoWaitingList.vue'
import OrderDetailsDialog from '@/Components/Orders/OrderDetailsDialog.vue'

defineOptions({
    layout: VerwalterLayout,
})

const props = defineProps({
    activeLeads: {
        type: Array,
        default: () => [],
    },
    canViewOrders: {
        type: Boolean,
        default: false,
    },
    ordersByStatus: {
        type: Object,
        default: () => ({}),
    },
    orderStatuses: {
        type: Array,
        default: () => [],
    },
})

const orderTab = ref(null)
const orderDetailsOpen = ref(false)
const selectedOrderId = ref(null)
const activeOrderStatuses = computed(() => props.orderStatuses.filter(status => !status.is_closed))
const visibleOrders = computed(() => props.ordersByStatus?.[orderTab.value] || [])

watch(() => activeOrderStatuses.value.map(status => status.code), codes => {
    if (!codes.includes(orderTab.value)) {
        orderTab.value = codes[0] ?? null
    }
}, { immediate: true })

function formatDateTime(value) {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return '—'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date)
}

function formatPhone(value) {
    if (!value) {
        return '—'
    }

    return String(value).startsWith('+') ? value : `+${value}`
}

function leadPhone(lead) {
    return lead.client_phone || lead.telephone?.number
}

function statusLabel(status) {
    return {
        open: 'Открыт',
        in_progress: 'В работе',
    }[status] || status || '—'
}

function entityUrl(entityId) {
    try {
        return route('Ameise.entity.show', entityId)
    } catch (error) {
        return `/Ameise/entity/${entityId}`
    }
}

function goodUrl(good) {
    if (!good?.id) {
        return '#'
    }

    try {
        return route('Ameise.good.show', good.id)
    } catch (error) {
        return `/Ameise/goods/${good.id}`
    }
}

function openOrder(order) {
    selectedOrderId.value = order.id
    orderDetailsOpen.value = true
}

function formatMoney(value, currencyCode = 'RUB') {
    const amount = Number(value)

    if (!Number.isFinite(amount)) {
        return '—'
    }

    return `${amount.toLocaleString('ru-RU', {
        maximumFractionDigits: 2,
    })} ${currencyCode === 'RUB' ? '₽' : currencyCode}`
}

useHead({
    title: 'Ameise — активные лиды',
    meta: [
        {
            name: 'description',
            content: 'Сводная страница Ameise',
        },
    ],
})
</script>

<template>
    <main class="ameise-dashboard" :class="{ 'ameise-dashboard--without-orders': !canViewOrders }">
        <section class="summary-block" aria-labelledby="active-leads-title">
            <header class="summary-block__header">
                <div>
                    <div class="summary-block__eyebrow">Сводная таблица</div>
                    <h1 id="active-leads-title">Активные лиды</h1>
                </div>
                <span class="summary-block__count" :aria-label="`Всего активных лидов: ${activeLeads.length}`">
                    {{ activeLeads.length }}
                </span>
            </header>

            <div class="lead-ledger">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Статус</th>
                            <th scope="col">Лид</th>
                            <th scope="col">Активность</th>
                        </tr>
                    </thead>
                    <tbody v-if="activeLeads.length">
                        <tr v-for="lead in activeLeads" :key="lead.id">
                            <td>
                                <span class="lead-status" :class="`lead-status--${lead.status}`">
                                    {{ statusLabel(lead.status) }}
                                </span>
                            </td>
                            <td>
                                <div class="lead-ledger__title" :title="lead.title || 'Лид'">
                                    {{ lead.title || 'Лид' }}
                                </div>
                                <div class="lead-ledger__meta">
                                    <span>#{{ lead.id }}</span>
                                    <Link
                                        v-if="lead.entity"
                                        :href="entityUrl(lead.entity.id)"
                                        class="lead-ledger__link"
                                    >
                                        {{ lead.entity.name }}
                                    </Link>
                                    <Link
                                        v-else-if="lead.unit"
                                        :href="route('web.unit.show', lead.unit.id)"
                                        class="lead-ledger__link"
                                    >
                                        {{ lead.unit.name }}
                                    </Link>
                                    <span v-else>{{ lead.source || 'Без CRM-связи' }}</span>
                                    <span v-if="leadPhone(lead)" class="lead-ledger__phone">
                                        {{ formatPhone(leadPhone(lead)) }}
                                    </span>
                                </div>
                            </td>
                            <td class="lead-ledger__date">
                                {{ formatDateTime(lead.last_activity_at || lead.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="3" class="lead-ledger__empty">Активных лидов нет</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            v-if="canViewOrders"
            class="summary-block order-summary"
            aria-labelledby="orders-title"
        >
            <header class="summary-block__header order-summary__header">
                <div>
                    <div class="summary-block__eyebrow">Работа с заказами</div>
                    <h1 id="orders-title">Заказы</h1>
                </div>
                <span class="summary-block__count">
                    {{ visibleOrders.length }}
                </span>
            </header>

            <div v-if="activeOrderStatuses.length" class="order-summary__tabs" role="tablist" aria-label="Статусы заказов">
                <button
                    v-for="status in activeOrderStatuses"
                    :key="status.id"
                    type="button"
                    role="tab"
                    :aria-selected="orderTab === status.code"
                    :class="{ 'is-active': orderTab === status.code }"
                    :style="{ '--status-color': status.color || '#64748b' }"
                    @click="orderTab = status.code"
                >
                    <i class="order-summary__status-dot" />
                    {{ status.name }}
                    <span>{{ ordersByStatus[status.code]?.length || 0 }}</span>
                </button>
            </div>

            <div class="order-ledger">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Entity / Заказ</th>
                            <th scope="col">Товары</th>
                            <th scope="col">Сумма</th>
                            <th scope="col" class="order-ledger__action"><span class="sr-only">Детали заказа</span></th>
                        </tr>
                    </thead>
                    <tbody v-if="visibleOrders.length">
                        <tr
                            v-for="order in visibleOrders"
                            :key="order.id"
                            @click="openOrder(order)"
                        >
                            <td>
                                <strong class="order-ledger__entity">
                                    {{ order.entity?.name || 'Без Entity' }}
                                </strong>
                                <button
                                    type="button"
                                    class="order-ledger__number"
                                    aria-haspopup="dialog"
                                    :aria-label="`Детали заказа ${order.number || `#${order.id}`}`"
                                    @click.stop="openOrder(order)"
                                >{{ order.number || `#${order.id}` }}</button>
                            </td>
                            <td>
                                <div class="order-ledger__goods">
                                    <Link
                                        v-for="item in (order.items || []).slice(0, 2)"
                                        :key="item.id"
                                        :href="goodUrl(item.good)"
                                        @click.stop
                                    >
                                        {{ item.good_name }} × {{ item.quantity }}
                                    </Link>
                                    <small v-if="(order.items || []).length > 2">
                                        +{{ order.items.length - 2 }}
                                    </small>
                                </div>
                            </td>
                            <td class="order-ledger__amount">
                                {{ formatMoney(order.total_amount, order.currency_code) }}
                            </td>
                            <td class="order-ledger__action">
                                <button
                                    type="button"
                                    class="order-ledger__details-button"
                                    aria-haspopup="dialog"
                                    :aria-label="`Детали заказа ${order.number || `#${order.id}`}`"
                                    title="Детали заказа"
                                    @click.stop="openOrder(order)"
                                ><v-icon icon="mdi-eye-outline" size="14" /></button>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="4" class="order-ledger__empty">
                                {{ activeOrderStatuses.length ? 'В этом статусе заказов нет' : 'Активных статусов заказов нет' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <AvitoWaitingList class="avito-waiting-summary" />
        <OrderDetailsDialog v-if="canViewOrders" v-model="orderDetailsOpen" :order-id="selectedOrderId" />
    </main>
</template>

<style scoped>
.ameise-dashboard {
    display: grid;
    align-self: stretch;
    align-content: start;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr) minmax(400px, 1.7fr);
    gap: 18px;
    width: 100%;
    min-height: calc(100vh - 48px);
    padding: 18px;
    background: #f6f7f9;
}

.ameise-dashboard--without-orders {
    grid-template-columns: minmax(0, 1fr) minmax(400px, 1.3fr);
}

.avito-waiting-summary {
    grid-column: -2 / -1;
    grid-row: 1;
}

.summary-block {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    width: 100%;
    height: 50vh;
    min-height: 380px;
    border: 1px solid #d7dce2;
    border-radius: 8px;
    background: #ffffff;
}

.summary-block__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    min-height: 58px;
    padding: 10px 14px;
    border-bottom: 1px solid #d7dce2;
}

.summary-block__eyebrow {
    margin-bottom: 2px;
    color: #7b8490;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.summary-block h1 {
    margin: 0;
    color: #20252b;
    font-size: 18px;
    font-weight: 800;
    line-height: 1.2;
}

.summary-block__count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 24px;
    padding: 0 8px;
    border: 1px solid #c8ced6;
    border-radius: 4px;
    background: #f5f6f8;
    color: #333941;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 11px;
    font-weight: 800;
}

.lead-ledger {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}

.lead-ledger table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    color: #252a31;
    font-size: 12px;
}

.lead-ledger th:first-child {
    width: 78px;
}

.lead-ledger th:last-child {
    width: 82px;
}

.lead-ledger th,
.lead-ledger td {
    height: 34px;
    padding: 5px 10px;
    border-right: 1px solid #e6e9ed;
    border-bottom: 1px solid #e1e5e9;
    text-align: left;
    vertical-align: middle;
    white-space: nowrap;
}

.lead-ledger th:last-child,
.lead-ledger td:last-child {
    border-right: 0;
}

.lead-ledger tbody tr:last-child td {
    border-bottom: 0;
}

.lead-ledger th {
    position: sticky;
    top: 0;
    z-index: 1;
    height: 30px;
    background: #f0f2f4;
    color: #626b76;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.lead-ledger tbody tr:hover {
    background: #faf4ee;
}

.lead-ledger__date,
.lead-ledger__phone {
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 11px;
}

.lead-ledger__date {
    color: #6f7781;
    white-space: normal !important;
}

.lead-ledger__title {
    overflow: hidden;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-ledger__meta {
    display: flex;
    gap: 5px;
    min-width: 0;
    overflow: hidden;
    margin-top: 2px;
    color: #8a929c;
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-ledger__phone {
    color: #7f1d1d;
}

.lead-ledger__link {
    overflow: hidden;
    color: #7f1d1d;
    font-weight: 700;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-ledger__link:hover {
    text-decoration: underline;
}

.lead-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
}

.lead-status::before {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #64748b;
    content: "";
}

.lead-status--open::before {
    background: #2563eb;
}

.lead-status--in_progress::before {
    background: #d97706;
}

.lead-ledger__empty {
    height: 72px !important;
    color: #737b85;
    text-align: center !important;
}

.order-summary__header {
    min-height: 52px;
}

.order-summary__tabs {
    display: flex;
    flex-shrink: 0;
    min-height: 36px;
    overflow-x: auto;
    border-bottom: 1px solid #d7dce2;
}

.order-summary__tabs button {
    display: inline-flex;
    flex: 1 0 auto;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    border-right: 1px solid #d7dce2;
    background: #f5f6f7;
    color: #69727c;
    font-size: 10px;
    font-weight: 850;
    white-space: nowrap;
}

.order-summary__tabs button:last-child {
    border-right: 0;
}

.order-summary__tabs button.is-active {
    box-shadow: inset 0 -2px var(--status-color);
    background: #fff;
    color: #252b33;
}

.order-summary__status-dot {
    flex: 0 0 6px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--status-color);
}

.order-summary__tabs span {
    min-width: 17px;
    padding: 1px 4px;
    border-radius: 8px;
    background: #e4e7ea;
    color: #4f5862;
    font-family: "JetBrains Mono", monospace;
    font-size: 8px;
}

.order-ledger {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}

.order-ledger table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.order-ledger th,
.order-ledger td {
    height: 38px;
    padding: 5px 8px;
    border-right: 1px solid #e6e9ed;
    border-bottom: 1px solid #e1e5e9;
    text-align: left;
    vertical-align: middle;
}

.order-ledger th:last-child,
.order-ledger td:last-child {
    border-right: 0;
}

.order-ledger th {
    position: sticky;
    top: 0;
    z-index: 1;
    height: 29px;
    background: #f0f2f4;
    color: #626b76;
    font-size: 9px;
    font-weight: 850;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.order-ledger th:nth-child(2) {
    width: 40%;
}

.order-ledger th:nth-child(3) {
    width: 82px;
}

.order-ledger .order-ledger__action {
    width: 30px;
    padding: 3px;
    text-align: center;
}

.order-ledger tbody tr {
    cursor: pointer;
}

.order-ledger tbody tr:hover,
.order-ledger tbody tr:focus-within {
    outline: none;
    background: #faf4ee;
}

.order-ledger td > strong,
.order-ledger td > small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.order-ledger td > strong {
    font-size: 10px;
    font-weight: 900;
}

.order-ledger__entity {
    color: #20262d;
}

.order-ledger__number {
    display: block;
    max-width: 100%;
    overflow: hidden;
    margin-top: 2px;
    color: #68727d;
    font-family: "JetBrains Mono", monospace;
    font-size: 9px;
    font-weight: 650;
    letter-spacing: 0.015em;
    text-align: left;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.order-ledger__number:hover {
    color: #7f1d1d;
    text-decoration: underline;
}

.order-ledger__details-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 26px;
    border-radius: 4px;
    color: #68727d;
}

.order-ledger__details-button:hover {
    background: #e7ebef;
    color: #7f1d1d;
}

.order-ledger__number:focus-visible,
.order-ledger__details-button:focus-visible {
    outline: 2px solid #7f1d1d;
    outline-offset: 2px;
}

.order-ledger td > small,
.order-ledger__goods small {
    margin-top: 1px;
    color: #89919a;
    font-size: 8px;
}

.order-ledger__goods {
    display: grid;
    gap: 1px;
}

.order-ledger__goods a {
    overflow: hidden;
    color: #7f1d1d;
    font-size: 9px;
    font-weight: 750;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.order-ledger__goods a:hover {
    text-decoration: underline;
}

.order-ledger__amount {
    color: #185c4b;
    font-family: "JetBrains Mono", monospace;
    font-size: 9px;
    font-weight: 900;
    white-space: nowrap;
}

.order-ledger__empty {
    height: 72px !important;
    color: #737b85;
    font-size: 10px;
    text-align: center !important;
}

@media (min-width: 701px) and (max-width: 1179px) {
    .ameise-dashboard,
    .ameise-dashboard--without-orders {
        grid-template-columns: minmax(0, 1fr) minmax(350px, 1.2fr);
        gap: 12px;
        padding: 12px;
    }

    .order-summary {
        grid-column: 1;
        grid-row: 2;
    }
}

@media (max-width: 700px) {
    .ameise-dashboard {
        grid-template-columns: minmax(0, 1fr);
        padding: 10px;
    }

    .avito-waiting-summary {
        grid-column: 1;
        grid-row: 1;
    }

    .summary-block__header {
        min-height: 54px;
        padding: 9px 10px;
    }
}
</style>
