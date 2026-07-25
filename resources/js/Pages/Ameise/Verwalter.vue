<script setup>
import { Link, router } from '@inertiajs/vue3'
import { useHead } from '@vueuse/head'
import { route } from 'ziggy-js'
import { computed, ref } from 'vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

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
        default: () => ({
            open: [],
            deferred: [],
        }),
    },
})

const orderTab = ref('open')
const visibleOrders = computed(() => props.ordersByStatus?.[orderTab.value] || [])

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

function orderUrl(orderId) {
    try {
        return route('Ameise.orders.show', orderId)
    } catch (error) {
        return `/Ameise/orders/${orderId}`
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
    router.visit(orderUrl(order.id))
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
    <main class="ameise-dashboard">
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

            <div class="order-summary__tabs" role="tablist" aria-label="Статусы заказов">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="orderTab === 'open'"
                    :class="{ 'is-active': orderTab === 'open' }"
                    @click="orderTab = 'open'"
                >
                    Открытые
                    <span>{{ ordersByStatus.open?.length || 0 }}</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="orderTab === 'deferred'"
                    :class="{ 'is-active': orderTab === 'deferred' }"
                    @click="orderTab = 'deferred'"
                >
                    Отложенные
                    <span>{{ ordersByStatus.deferred?.length || 0 }}</span>
                </button>
            </div>

            <div class="order-ledger">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Заказ / Entity</th>
                            <th scope="col">Товары</th>
                            <th scope="col">Сумма</th>
                        </tr>
                    </thead>
                    <tbody v-if="visibleOrders.length">
                        <tr
                            v-for="order in visibleOrders"
                            :key="order.id"
                            tabindex="0"
                            @click="openOrder(order)"
                            @keydown.enter="openOrder(order)"
                        >
                            <td>
                                <strong>{{ order.number }}</strong>
                                <small>{{ order.entity?.name || 'Без Entity' }}</small>
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
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="3" class="order-ledger__empty">
                                {{ orderTab === 'open' ? 'Открытых заказов нет' : 'Отложенных заказов нет' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>

<style scoped>
.ameise-dashboard {
    display: grid;
    align-self: stretch;
    align-content: start;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    width: 100%;
    min-height: calc(100vh - 48px);
    padding: 18px;
    background: #f6f7f9;
}

.summary-block {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    width: 100%;
    height: 50vh;
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
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    min-height: 36px;
    border-bottom: 1px solid #d7dce2;
}

.order-summary__tabs button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border-right: 1px solid #d7dce2;
    background: #f5f6f7;
    color: #69727c;
    font-size: 10px;
    font-weight: 850;
}

.order-summary__tabs button:last-child {
    border-right: 0;
}

.order-summary__tabs button.is-active {
    box-shadow: inset 0 -2px #7f1d1d;
    background: #fff;
    color: #7f1d1d;
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

.order-ledger th:last-child {
    width: 82px;
}

.order-ledger tbody tr {
    cursor: pointer;
}

.order-ledger tbody tr:hover,
.order-ledger tbody tr:focus {
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

@media (max-width: 700px) {
    .ameise-dashboard {
        grid-template-columns: minmax(0, 1fr);
        padding: 10px;
    }

    .summary-block__header {
        min-height: 54px;
        padding: 9px 10px;
    }
}
</style>
