<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    checks: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
    meta: { type: Object, default: () => ({}) },
})
defineEmits(['refresh'])

function numeric(value) {
    const result = Number(value)
    return Number.isFinite(result) ? result : 0
}
function itemCount(check) {
    return check.items_count ?? numeric(check.commodity_items_count) + numeric(check.service_items_count)
}
const total = computed(() => props.meta.total_amount ?? props.checks.reduce((sum, check) => sum + numeric(check.amount), 0))
const items = computed(() => props.meta.items_count ?? props.checks.reduce((sum, check) => sum + numeric(itemCount(check)), 0))
const money = value => new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 }).format(numeric(value))
function dateLabel(value) {
    if (!value) return '—'
    const date = new Date(value.length === 10 ? `${value}T00:00:00` : value)
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('ru-RU').format(date)
}
</script>

<template>
    <section class="entity-checks" aria-label="Checks">
        <header class="entity-checks__header">
            <h2><v-icon icon="mdi-receipt-text-outline" size="19" /> Checks <span>{{ checks.length }}</span></h2>
            <div class="entity-checks__summary">
                <span>Сумма <strong>{{ money(total) }}</strong></span>
                <span>Строк <strong>{{ items }}</strong></span>
                <v-btn variant="text" size="small" icon="mdi-refresh" aria-label="Обновить Checks" :loading="loading" @click="$emit('refresh')" />
            </div>
        </header>
        <v-progress-linear v-if="loading" indeterminate color="#800000" />
        <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
        <div v-else-if="!checks.length && !loading" class="entity-checks__empty">Связанных checks пока нет.</div>
        <div v-else class="entity-checks__table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Дата</th><th class="entity-checks__money">Сумма</th><th>Строк</th><th>Товары</th><th>Услуги</th><th><span class="sr-only">Открыть</span></th></tr></thead>
                <tbody>
                    <tr v-for="check in checks" :key="check.id">
                        <td>#{{ check.id }}</td>
                        <td>{{ dateLabel(check.date) }}</td>
                        <td class="entity-checks__money">{{ money(check.amount) }}</td>
                        <td>{{ itemCount(check) }}</td>
                        <td>{{ check.commodity_items_count ?? '—' }}</td>
                        <td>{{ check.service_items_count ?? '—' }}</td>
                        <td><Link :href="`/Ameise/checks?check=${encodeURIComponent(check.id)}`" :aria-label="`Открыть Check #${check.id}`">Открыть <v-icon icon="mdi-arrow-top-right" size="14" /></Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<style scoped>
.entity-checks { padding: 16px; border: 1px solid #eaddd6; border-radius: 14px; background: #fff; color: #3f1d1d; }
.entity-checks__header, .entity-checks__summary, .entity-checks h2 { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.entity-checks__header { justify-content: space-between; margin-bottom: 10px; }
.entity-checks h2 { margin: 0; font-size: 1rem; font-weight: 700; }
.entity-checks h2 > span { border-radius: 6px; padding: 2px 7px; background: #f5ebe5; font-size: 0.75rem; }
.entity-checks__summary { color: #806960; font-size: 0.78rem; }
.entity-checks__summary strong { margin-left: 5px; color: #3f1d1d; }
.entity-checks__table-wrap { overflow-x: auto; }
.entity-checks table { width: 100%; min-width: 610px; border-collapse: collapse; font-size: 0.8rem; }
.entity-checks th, .entity-checks td { padding: 9px 10px; border-bottom: 1px solid #f0e7e0; text-align: left; white-space: nowrap; }
.entity-checks th { background: #fbf7f3; color: #806960; font-weight: 600; }
.entity-checks tbody tr:hover { background: #fffaf6; }
.entity-checks .entity-checks__money { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
.entity-checks a { color: #800000; text-decoration: none; }
.entity-checks a:hover { text-decoration: underline; }
.entity-checks__empty { padding: 24px 8px; color: #806960; text-align: center; font-size: 0.85rem; }
</style>
