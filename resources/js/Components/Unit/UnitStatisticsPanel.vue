<script setup>
import { computed } from 'vue'

const props = defineProps({
    unit: { type: Object, required: true },
    canViewOrders: { type: Boolean, default: false },
})

function transactions(relation) {
    const rows = new Map()
    for (const entity of props.unit.entities || []) {
        for (const item of entity[relation] || []) {
            if (!rows.has(item.id)) rows.set(item.id, { ...item, entity_name: entity.name, entity_id: entity.id })
        }
    }
    return [...rows.values()].sort((a, b) => String(b.date || '').localeCompare(String(a.date || '')))
}

const purchases = computed(() => transactions('purchases'))
const sales = computed(() => transactions('sales'))
const workboard = computed(() => props.unit.supplier_pipeline_cards || props.unit.supplierPipelineCards || [])
const orders = computed(() => new Set((props.unit.entities || []).flatMap(entity => (entity.orders || []).map(order => order.id))).size)
const leads = computed(() => new Set([
    ...(props.unit.leads || []),
    ...(props.unit.entities || []).flatMap(entity => entity.leads || []),
].map(lead => lead.id)).size)
const consumedProducts = computed(() => [...new Map((props.unit.consumptions || [])
    .filter(item => item.product?.id)
    .map(item => [item.product.id, item.product])).values()])
const productRequests = computed(() => consumedProducts.value.reduce((sum, product) => sum + Number(product.search_requests_count || 0), 0))
const metrics = computed(() => [
    { label: 'ID Unit', value: props.unit.id },
    { label: 'Закупки', value: purchases.value.length },
    { label: 'Sales', value: sales.value.length },
    { label: 'Заказы', value: props.canViewOrders ? orders.value : '—' },
    { label: 'Лиды', value: leads.value },
    { label: 'Прайс-лист', value: props.unit.quotations?.length || 0 },
])
const tables = computed(() => [
    { key: 'purchases', title: 'Закупки', rows: purchases.value, amount: 'amount' },
    { key: 'sales', title: 'Sales', rows: sales.value, amount: 'total' },
])

function dateLabel(value) {
    if (!value) return '—'
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('ru-RU').format(date)
}

function amountLabel(value) {
    if (value === null || value === undefined || value === '') return '—'
    const amount = Number(value)
    return Number.isFinite(amount) ? new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) : '—'
}
</script>

<template>
    <section class="unit-statistics" aria-label="Статистика Unit">
        <dl class="unit-statistics__metrics">
            <div v-for="metric in metrics" :key="metric.label">
                <dt>{{ metric.label }}</dt><dd>{{ metric.value }}</dd>
            </div>
        </dl>
        <div class="unit-statistics__documents">
            <section v-for="table in tables" :key="table.key" class="unit-statistics__section">
                <header><h2>{{ table.title }}</h2><span>Документы связанных Entities</span></header>
                <div class="unit-statistics__scroll">
                    <table v-if="table.rows.length">
                        <thead><tr><th>Документ</th><th>Дата</th><th>Entity</th><th class="is-number">Сумма</th></tr></thead>
                        <tbody>
                            <tr v-for="row in table.rows" :key="row.id">
                                <td>#{{ row.id }}</td>
                                <td class="is-date">{{ dateLabel(row.date) }}</td>
                                <td><a :href="`/Ameise/entity/${row.entity_id}`">{{ row.entity_name }}</a></td>
                                <td class="is-number">{{ amountLabel(row[table.amount]) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="unit-statistics__empty">Документов пока нет</p>
                </div>
            </section>
        </div>
        <div class="unit-statistics__footer">
            <dl>
                <div><dt>Entities</dt><dd>{{ unit.entities?.length || 0 }}</dd></div>
                <div><dt>Закупает продуктов</dt><dd>{{ consumedProducts.length }}</dd></div>
                <div><dt>Производит продуктов</dt><dd>{{ unit.manufactures?.length || 0 }}</dd></div>
                <div><dt>Заявки по потребляемым продуктам</dt><dd>{{ productRequests }}</dd></div>
            </dl>
        </div>
        <div class="unit-statistics__footer">
            <dl><div><dt>Создан</dt><dd>{{ dateLabel(unit.created_at) }}</dd></div><div><dt>Обновлён</dt><dd>{{ dateLabel(unit.updated_at) }}</dd></div></dl>
            <div v-if="workboard.length" class="unit-statistics__stages">
                <span v-for="card in workboard" :key="card.id">{{ card.pipeline?.name || 'Workboard' }} · {{ card.stage?.name || 'Без стадии' }}</span>
            </div>
            <span v-if="unit.mail_follow_up" :class="{ 'is-overdue': unit.mail_follow_up.is_overdue }">{{ unit.mail_follow_up.answered ? 'Получен ответ на последнее письмо' : unit.mail_follow_up.is_overdue ? 'Unit не ответил на исходящее письмо в срок' : 'Ожидаем ответ Unit на исходящее письмо' }}</span>
        </div>
    </section>
</template>

<style scoped>
.unit-statistics { display: grid; gap: 14px; }
.unit-statistics__metrics { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); border: 1px solid #d9d7dc; background: #fff; }
.unit-statistics__metrics > div { padding: 14px 16px; border-right: 1px solid #e8e6e9; }
.unit-statistics__metrics > div:last-child { border-right: 0; }
.unit-statistics dt { color: #77727b; font-size: 11px; }
.unit-statistics__metrics dd { margin-top: 6px; font-size: 24px; font-weight: 600; font-variant-numeric: tabular-nums; color: #352345; }
.unit-statistics__documents { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; }
.unit-statistics__section { border: 1px solid #d9d7dc; background: #fff; min-width: 0; }
.unit-statistics header { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px; border-bottom: 1px solid #d9d7dc; }
.unit-statistics h2 { font-size: 13px; font-weight: 650; }
.unit-statistics header span { color: #77727b; font-size: 10px; }
.unit-statistics__scroll { overflow: auto; max-height: 480px; }
.unit-statistics table { width: 100%; border-collapse: collapse; font-size: 12px; }
.unit-statistics th, .unit-statistics td { padding: 9px 12px; text-align: left; border-bottom: 1px solid #e8e6e9; }
.unit-statistics th { font-size: 10px; font-weight: 600; color: #77727b; background: #f8f8f9; position: sticky; top: 0; }
.unit-statistics .is-number { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.unit-statistics .is-date { white-space: nowrap; }
.unit-statistics a { color: #352345; text-decoration: none; }
.unit-statistics a:hover { text-decoration: underline; }
.unit-statistics__empty { padding: 24px 12px; color: #77727b; font-size: 12px; }
.unit-statistics__footer, .unit-statistics__footer dl, .unit-statistics__stages { display: flex; flex-wrap: wrap; align-items: center; gap: 18px; font-size: 11px; color: #77727b; }
.unit-statistics__footer dl > div { display: flex; gap: 7px; }
.unit-statistics__footer dd { color: #242127; }
.unit-statistics .is-overdue { color: #651c2e; }
@media (max-width: 1050px) { .unit-statistics__documents { grid-template-columns: 1fr; } }
@media (max-width: 650px) { .unit-statistics__metrics { grid-template-columns: repeat(3, 1fr); } .unit-statistics__metrics > div { padding: 10px; border-bottom: 1px solid #e8e6e9; } }
</style>
