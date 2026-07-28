<script setup>
import { computed, inject, onActivated, onMounted, reactive, ref } from 'vue'

const api = inject('logisticsApi')
const data = ref(null)
const loaded = ref(false)
const filters = reactive({ date_from: monthStart(), date_to: today() })

const cards = computed(() => {
    const value = data.value
    if (!value) return []

    return [
        { label: 'Рейсы за период', value: formatNumber(value.trips.total), hint: `завершено: ${value.trips.completed}` },
        { label: 'Плановый километраж', value: formatKm(value.planned_distance_m), hint: 'по рассчитанным маршрутам' },
        { label: 'Фактический километраж', value: formatKm(value.actual_distance_m), hint: 'одометр или ручной факт' },
        { label: 'Перевезённый вес', value: formatWeight(value.cargo_weight_kg), hint: 'сумма указанного груза' },
        { label: 'Расходы', value: formatCurrencyMap(value.expenses_by_currency), hint: `без чека: ${value.expenses_without_check}` },
        { label: 'Топливо', value: formatCurrencyMap(value.expenses_by_category?.fuel), hint: 'по категории расходов' },
        { label: 'Платные дороги', value: formatCurrencyMap(value.expenses_by_category?.toll_road), hint: 'по категории расходов' },
        { label: 'Проживание', value: formatCurrencyMap(value.expenses_by_category?.accommodation), hint: 'по категории расходов' },
        { label: 'Стоимость километра', value: formatCurrencyRate(value.cost_per_km_by_currency, '/км'), hint: `база: ${value.distance_basis === 'actual' ? 'факт' : 'план'}` },
        { label: 'Стоимость килограмма', value: formatCurrencyRate(value.cost_per_kg_by_currency, '/кг'), hint: 'без смешения валют' },
        { label: 'Средняя загрузка', value: value.average_vehicle_load_factor == null ? '—' : `${formatNumber(value.average_vehicle_load_factor * 100, 1)}%`, hint: 'к грузоподъёмности авто' },
        { label: 'Требуют внимания', value: formatNumber(value.trips.without_route + value.trips.without_vehicle), hint: `без маршрута: ${value.trips.without_route} · без авто: ${value.trips.without_vehicle}` },
    ]
})

function localDate(date) { return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 10) }
function today() { return localDate(new Date()) }
function monthStart() { const date = new Date(); date.setDate(1); return localDate(date) }
function formatNumber(value, digits = 0) { return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: digits }).format(Number(value || 0)) }
function formatKm(value) { return `${formatNumber(Number(value || 0) / 1000, 1)} км` }
function formatWeight(value) { return Number(value || 0) >= 1000 ? `${formatNumber(value / 1000, 2)} т` : `${formatNumber(value, 0)} кг` }
function formatMoney(value, currency) { return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: currency || 'RUB', maximumFractionDigits: 2 }).format(Number(value || 0)) }
function formatCurrencyMap(values) {
    const entries = Object.entries(values || {})
    return entries.length ? entries.map(([currency, value]) => formatMoney(value, currency)).join(' · ') : '—'
}
function formatCurrencyRate(values, suffix) {
    const entries = Object.entries(values || {}).filter(([, value]) => value != null)
    return entries.length ? entries.map(([currency, value]) => `${formatMoney(value, currency)}${suffix}`).join(' · ') : '—'
}
function formatDate(value) { return value ? new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—' }

async function load() {
    try {
        const response = await api.request('dashboard', {
            method: 'get',
            url: '/api/logistics/dashboard',
            params: filters,
        }, { error: 'Не удалось загрузить сводку логистики.' })
        data.value = response?.data || null
        loaded.value = true
    } catch { /* snackbar already contains the backend error */ }
}

onMounted(load)
onActivated(() => { if (!loaded.value) load() })
</script>

<template>
    <section>
        <div class="logistics-toolbar">
            <div class="logistics-toolbar__filters">
                <v-text-field v-model="filters.date_from" label="Период с" type="date" density="compact" variant="outlined" hide-details style="max-width: 190px" />
                <v-text-field v-model="filters.date_to" label="по" type="date" density="compact" variant="outlined" hide-details style="max-width: 190px" />
            </div>
            <div class="logistics-toolbar__actions">
                <v-btn color="green-darken-2" variant="flat" prepend-icon="mdi-refresh" :loading="api.isPending('dashboard')" @click="load">
                    Обновить
                </v-btn>
            </div>
        </div>

        <v-skeleton-loader v-if="!loaded && api.isPending('dashboard')" type="card, card, card" />
        <template v-else-if="data">
            <v-row dense>
                <v-col v-for="card in cards" :key="card.label" cols="12" sm="6" lg="3">
                    <v-card class="logistics-metric" variant="outlined">
                        <v-card-text>
                            <div class="logistics-metric__label">{{ card.label }}</div>
                            <div class="logistics-metric__value">{{ card.value }}</div>
                            <div class="logistics-metric__hint">{{ card.hint }}</div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-card class="mt-4" variant="outlined">
                <v-card-title>Последние рейсы</v-card-title>
                <v-table density="compact">
                    <thead><tr><th>Номер</th><th>Маршрут</th><th>Авто</th><th>Статус</th><th>Отправление</th></tr></thead>
                    <tbody>
                        <tr v-for="trip in data.recent_trips" :key="trip.id">
                            <td>{{ trip.number }}</td><td>{{ trip.route || '—' }}</td><td>{{ trip.vehicle || '—' }}</td>
                            <td><v-chip size="x-small" variant="tonal">{{ trip.status }}</v-chip></td><td>{{ formatDate(trip.planned_departure_at) }}</td>
                        </tr>
                        <tr v-if="!data.recent_trips?.length"><td colspan="5" class="logistics-empty">Рейсов пока нет.</td></tr>
                    </tbody>
                </v-table>
            </v-card>
        </template>
    </section>
</template>
