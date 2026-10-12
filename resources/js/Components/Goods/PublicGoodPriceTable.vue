<script setup>
import { computed } from 'vue'
import { unitLabel } from '@/utils/goodMeasurement'

const props = defineProps({
    prices: { type: Array, default: () => [] },
    purchase: { type: Object, default: () => ({}) },
})
const unit = computed(() => unitLabel(props.purchase.measurement))
const rows = computed(() => props.prices.length ? props.prices : [{
    price_type_id: 'retail',
    price_type_name: 'Розничная',
    price: props.purchase.price ?? null,
    currency_code: props.purchase.currency_code || 'RUB',
    includes_vat: props.purchase.includes_vat,
    is_retail: true,
}])
function money(row) {
    return `${Number(row.price).toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ${row.currency_code === 'RUB' ? '₽' : row.currency_code}`
}
</script>

<template>
    <table class="good-price-table" aria-label="Цены товара">
        <thead>
            <tr><th scope="col">Тип цены</th><th scope="col">Цена / {{ unit }}</th></tr>
        </thead>
        <tbody>
            <tr v-for="row in rows" :key="row.price_type_id" :class="{ 'good-price-table__retail': row.is_retail }">
                <th scope="row">{{ row.price_type_name }}</th>
                <td>
                    <span v-if="row.requires_auth" class="good-price-table__locked">Доступна зарегистрированным пользователям</span>
                    <template v-else>
                        <strong>{{ row.price > 0 ? money(row) : 'По запросу' }}</strong>
                        <small v-if="row.price > 0">{{ row.includes_vat ? 'С НДС' : 'НДС уточняется' }}</small>
                    </template>
                </td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped>
.good-price-table { width: 100%; border-collapse: collapse; table-layout: fixed; color: #493131; font-size: 13px; line-height: 1.45; }
.good-price-table th, .good-price-table td { padding: 10px 12px; border-bottom: 1px solid #f1e8e5; overflow-wrap: anywhere; }
.good-price-table thead th { color: #897271; font-size: 11px; font-weight: 400; text-align: left; }
.good-price-table thead th:first-child { width: 40%; }
.good-price-table thead th:last-child, .good-price-table td { text-align: right; }
.good-price-table tbody th { text-align: left; font-weight: 500; }
.good-price-table strong { font-size: 16px; font-weight: 650; font-variant-numeric: tabular-nums; }
.good-price-table small { display: block; margin-top: 2px; color: #897271; font-size: 10px; }
.good-price-table__locked { display: inline-block; padding: 5px 8px; border-radius: 6px; background: #f5efec; color: #795b56; font-size: 11px; line-height: 1.45; text-align: left; }
.good-price-table__retail { background: #fff5f2; }
.good-price-table__retail th, .good-price-table__retail strong { color: #800000; }
.good-price-table tbody tr:last-child > * { border-bottom: 0; }
@media (max-width: 359px) { .good-price-table th, .good-price-table td { padding-inline: 8px; } }
</style>
