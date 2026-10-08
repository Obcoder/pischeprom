<script setup>
import { computed } from 'vue'

const props = defineProps({
    pricing: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'sales' },
})
const purchase = computed(() => props.pricing?.purchase)
const sales = computed(() => props.pricing?.sales || [])
const number = value => value === null || value === undefined ? '—' : Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })
const currency = label => ({ RUB: '₽', USD: '$', EUR: '€', CNY: '¥' }[label] || label || 'валюта не указана')
const date = value => value ? String(value).slice(0, 10).split('-').reverse().join('.') : ''
const purchaseTitle = computed(() => purchase.value
    ? `Последняя закупка № ${purchase.value.purchase_id} от ${date(purchase.value.date)}: ${number(purchase.value.price)} ${currency(purchase.value.currency_label)} / ${purchase.value.unit_label || 'единица не указана'}`
    : 'Закупок пока нет')
const markupTitle = row => row.markup_unavailable_reason || 'Наценка к последней закупке за кг: (цена продажи / цена закупки − 1) × 100%. НДС закупки отдельно не указан.'
</script>

<template>
    <div v-if="mode === 'purchase'" class="catalog-purchase-price" :title="purchaseTitle" :aria-label="purchaseTitle">
        <strong>{{ number(purchase?.price) }}</strong>
        <template v-if="purchase">
            <span>{{ currency(purchase.currency_label) }} / {{ purchase.unit_label || '?' }}</span>
            <small>{{ date(purchase.date) }}</small>
        </template>
        <small v-else>Нет закупок</small>
    </div>
    <table v-else-if="sales.length" class="catalog-sales-prices" aria-label="Текущие цены продажи и торговая наценка">
        <thead><tr><th>Тип</th><th>Цена / кг</th><th title="Торговая наценка к последней закупке">ТН</th></tr></thead>
        <tbody>
            <tr v-for="row in sales" :key="row.id">
                <th :title="row.name">{{ row.name }}</th>
                <td :title="`${row.includes_vat ? 'С НДС' : 'Без НДС'} · ${currency(row.currency_label)} / кг`">{{ number(row.price) }} <span>{{ currency(row.currency_label) }}</span></td>
                <td :title="markupTitle(row)" :class="{ 'is-negative': row.markup_percent !== null && row.markup_percent < 0 }">{{ number(row.markup_percent) }}<template v-if="row.markup_percent !== null && row.markup_percent !== undefined">%</template></td>
            </tr>
        </tbody>
    </table>
    <span v-else class="catalog-sales-prices-empty">Нет действующих цен</span>
</template>

<style scoped>
.catalog-purchase-price { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; width: 64px; min-width: 64px; max-width: 64px; height: 64px; min-height: 64px; max-height: 64px; aspect-ratio: 1; margin: 2px; padding: 3px; box-sizing: border-box; border: 1px solid #e5dfec; border-radius: 0; background: #f4f0f7; color: #6f557f; text-align: center; overflow: hidden; }
.catalog-purchase-price strong { max-width: 100%; font-size: 12px; font-weight: 650; line-height: 1.15; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
.catalog-purchase-price span { max-width: 100%; font-size: 9px; line-height: 1.1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.catalog-purchase-price small { color: #95849f; font-size: 8px; line-height: 1.1; }
.catalog-sales-prices { width: 100%; min-width: 205px; max-width: 260px; border-collapse: collapse; color: #6b5978; font-size: 10px; line-height: 1.35; font-variant-numeric: tabular-nums; }
.catalog-sales-prices th, .catalog-sales-prices td { padding: 1px 4px; border: 0; text-align: right; white-space: nowrap; }
.catalog-sales-prices th:first-child { max-width: 100px; text-align: left; overflow: hidden; text-overflow: ellipsis; font-weight: 400; }
.catalog-sales-prices thead th { color: #a090aa; font-size: 9px; font-weight: 400; }
.catalog-sales-prices tbody td:nth-child(2) { font-weight: 600; }.catalog-sales-prices tbody td span { font-weight: 400; }
.catalog-sales-prices tbody td:last-child { color: #798c6d; }.catalog-sales-prices tbody td.is-negative { color: #b26a5c; }
.catalog-sales-prices-empty { color: #a090aa; font-size: 10px; }
</style>
