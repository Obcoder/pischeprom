<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
    unit: { type: Object, required: true },
    canViewOrders: { type: Boolean, default: false },
    canCreateOrders: { type: Boolean, default: false },
})

const orders = computed(() => {
    if (!props.canViewOrders) return []

    const unique = new Map()
    for (const entity of props.unit.entities || []) {
        for (const order of entity.orders || []) {
            unique.set(order.id, {
                ...order,
                entity: order.entity || { id: entity.id, name: entity.name },
            })
        }
    }

    return [...unique.values()].sort((a, b) => new Date(b.submitted_at || b.created_at || 0) - new Date(a.submitted_at || a.created_at || 0))
})

function formatNumber(value, digits = 2) {
    if (value === null || value === undefined || value === '') return '—'
    const number = Number(value)
    return Number.isFinite(number) ? new Intl.NumberFormat('ru-RU', { maximumFractionDigits: digits }).format(number) : '—'
}

function formatDate(value) {
    const date = new Date(value)
    return value && !Number.isNaN(date.getTime()) ? date.toLocaleDateString('ru-RU') : '—'
}

function orderMoney(order) {
    return `${formatNumber(order.total_amount)} ${order.currency_code || ''}`.trim()
}
</script>

<template>
    <div class="unit-orders">
        <template v-if="canViewOrders">
            <div class="unit-orders__toolbar">
                <span>Заказы связанных юридических лиц</span>
                <Link v-if="canCreateOrders" :href="route('Ameise.orders.create')" class="unit-orders__create">
                    <v-icon icon="mdi-plus" size="15" />Создать заказ
                </Link>
            </div>
            <div v-if="orders.length" class="unit-orders__scroll">
                <article v-for="order in orders" :key="order.id" class="unit-orders__item">
                    <div class="unit-orders__summary">
                        <Link :href="route('Ameise.orders.show', order.id)" class="unit-orders__number">{{ order.number || `Заказ #${order.id}` }}</Link>
                        <span class="unit-orders__amount">{{ orderMoney(order) }}</span>
                    </div>
                    <div class="unit-orders__details">
                        <span>{{ order.entity?.name }}</span>
                        <time>{{ formatDate(order.submitted_at || order.created_at) }}</time>
                    </div>
                    <span class="unit-orders__status">{{ order.status?.name || order.status?.code || 'Без статуса' }}</span>
                    <div v-if="order.items?.length" class="unit-orders__goods">
                        <div v-for="item in order.items.slice(0, 3)" :key="item.id">
                            <Link v-if="item.good?.id || item.good_id" :href="route('Ameise.good.show', item.good?.id || item.good_id)">{{ item.good_name || item.good?.name || 'Товар' }}</Link>
                            <span v-else>{{ item.good_name || 'Товар' }}</span>
                            <span class="unit-orders__quantity"> × {{ formatNumber(item.quantity, 3) }}</span>
                        </div>
                        <Link v-if="order.items.length > 3" :href="route('Ameise.orders.show', order.id)" class="unit-orders__more">Ещё {{ order.items.length - 3 }}</Link>
                    </div>
                </article>
            </div>
            <p v-else class="unit-orders__empty">У связанных юридических лиц пока нет заказов.</p>
        </template>
        <p v-else class="unit-orders__empty">Просмотр заказов недоступен.</p>
    </div>
</template>

<style scoped>
.unit-orders { display: flex; flex-direction: column; height: 100%; min-width: 0; min-height: 0; color: #222; font-size: 12px; }
.unit-orders__toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; flex-shrink: 0; padding: 10px 12px; color: #666; }
.unit-orders__create { display: inline-flex; align-items: center; justify-content: center; gap: 4px; min-height: 28px; padding: 4px 7px; border: 1px solid #cfcbd2; background: #fff; color: #352345; font-size: 11px; text-decoration: none; white-space: nowrap; }
.unit-orders__create:hover { background: #f3f2f4; }
.unit-orders__scroll { flex: 1 1 auto; min-height: 0; min-width: 0; overflow: auto; overscroll-behavior: contain; }
.unit-orders__item { display: grid; gap: 5px; padding: 10px 12px; border-top: 1px solid #e7e7e7; }
.unit-orders__summary, .unit-orders__details { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; min-width: 0; }
.unit-orders__number { font-weight: 600; overflow-wrap: anywhere; }
.unit-orders__amount { white-space: nowrap; font-variant-numeric: tabular-nums; color: #651c2e; }
.unit-orders__details { color: #777; font-size: 11px; }
.unit-orders__details > span { overflow-wrap: anywhere; min-width: 0; }
.unit-orders__details time { flex-shrink: 0; white-space: nowrap; }
.unit-orders__status { border-left: 2px solid #352345; padding-left: 7px; font-size: 11px; }
.unit-orders__goods { display: grid; gap: 3px; margin-top: 2px; font-size: 11px; overflow-wrap: anywhere; }
.unit-orders__quantity { color: #666; white-space: nowrap; }
.unit-orders__item a { color: #352345; text-decoration: none; }
.unit-orders__item a:hover { text-decoration: underline; }
.unit-orders__item .unit-orders__more { color: #777; }
.unit-orders__empty { padding: 12px; color: #777; font-size: 12px; margin: 0; }
.unit-orders a:focus-visible { outline: 2px solid #352345; outline-offset: 2px; }
</style>
