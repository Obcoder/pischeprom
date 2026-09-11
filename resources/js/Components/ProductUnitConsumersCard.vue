<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
    consumers: { type: Array, default: () => [] },
})
const search = ref('')
const headers = [
    { title: 'Unit', key: 'unit.name' },
    { title: 'Объём', key: 'quantity', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end', width: 40 },
]

function unitName(consumer) {
    return consumer.unit?.name || consumer.unit?.rus || `Unit #${consumer.unit_id}`
}

function consumerSite(consumer) {
    const uris = consumer.unit?.uris || []
    const uri = uris.find(item => item.is_valid && item.address) || uris.find(item => item.address)
    if (!uri?.address) return null

    const address = String(uri.address).trim()
    try {
        const url = new URL(/^https?:\/\//i.test(address) ? address : `https://${address}`)
        return { href: url.href, label: address.replace(/^https?:\/\//i, '').replace(/\/$/, '') }
    } catch {
        return null
    }
}

function amountLabel(consumer) {
    if (consumer.quantity === null || consumer.quantity === undefined || consumer.quantity === '') return 'Не уточнён'
    const quantity = Number(consumer.quantity)
    return Number.isFinite(quantity)
        ? new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 20 }).format(quantity)
        : String(consumer.quantity)
}

const filteredConsumers = computed(() => {
    const query = String(search.value || '').trim().toLocaleLowerCase('ru-RU')
    return query ? props.consumers.filter(consumer => [unitName(consumer), consumerSite(consumer)?.label]
        .some(value => String(value || '').toLocaleLowerCase('ru-RU').includes(query))) : props.consumers
})
</script>

<template>
    <section class="unit-consumers-card" aria-label="Потребители Units">
        <header class="unit-consumers-header">
            <h2><v-icon icon="mdi-domain" size="18" /> Units <span class="unit-consumers-count">{{ consumers.length }}</span></h2>
        </header>

        <div v-if="!consumers.length" class="unit-consumers-empty">Потребности Units в этом продукте пока не зарегистрированы.</div>
        <template v-else>
            <div class="unit-consumers-search">
                <v-text-field
                    v-model="search"
                    label="Поиск Units"
                    placeholder="Название или сайт"
                    prepend-inner-icon="mdi-magnify"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                />
            </div>
            <v-data-table
                :headers="headers"
                :items="filteredConsumers"
                :items-per-page="10"
                :items-per-page-options="[10, 25, 50]"
                :hide-default-footer="filteredConsumers.length <= 10"
                :mobile="false"
                density="compact"
                no-data-text="Units не найдены"
                items-per-page-text="На странице"
                page-text="{0}–{1} из {2}"
                class="unit-consumers-table"
            >
                <template #item.unit.name="{ item }">
                    <div class="unit-consumers-info">
                        <Link v-if="item.unit?.id" :href="route('web.unit.show', item.unit.id)" class="unit-consumers-link">{{ unitName(item) }}</Link>
                        <span v-else class="unit-consumers-link">{{ unitName(item) }}</span>
                        <a v-if="consumerSite(item)" :href="consumerSite(item).href" target="_blank" rel="noopener noreferrer" class="unit-consumers-site">{{ consumerSite(item).label }} <v-icon icon="mdi-open-in-new" size="11" /></a>
                    </div>
                </template>
                <template #item.quantity="{ item }">
                    <div class="unit-consumers-quantity">{{ amountLabel(item) }}</div>
                    <div v-if="item.measure?.name" class="unit-consumers-measure">{{ item.measure.name }}</div>
                </template>
                <template #item.actions="{ item }">
                    <Link v-if="item.unit?.id" :href="route('web.unit.show', item.unit.id)" class="unit-consumers-open" :aria-label="`Открыть Unit: ${unitName(item)}`" title="Открыть Unit"><v-icon icon="mdi-arrow-top-right" size="17" /></Link>
                </template>
            </v-data-table>
        </template>
    </section>
</template>

<style scoped>
.unit-consumers-card { min-width: 0; overflow: hidden; border: 1px solid rgba(var(--v-theme-primary), 0.12); border-radius: 12px; background: rgb(var(--v-theme-surface)); }
.unit-consumers-header { display: flex; align-items: center; min-height: 48px; padding: 8px 12px; }
.unit-consumers-header h2 { display: flex; align-items: center; gap: 7px; margin: 0; font-size: 0.9rem; font-weight: 650; }
.unit-consumers-header .v-icon { color: rgb(var(--v-theme-primary)); }
.unit-consumers-count { padding: 1px 6px; border-radius: 5px; background: rgba(var(--v-theme-primary), 0.08); color: rgb(var(--v-theme-primary)); font-size: 0.7rem; }
.unit-consumers-search { padding: 0 12px 10px; }
.unit-consumers-search :deep(.v-field__input) { font-size: 0.8rem; }
.unit-consumers-empty { padding: 4px 12px 16px; color: rgba(var(--v-theme-on-surface), 0.6); font-size: 0.8rem; line-height: 1.5; }
.unit-consumers-info { display: grid; gap: 3px; padding: 7px 0; }
.unit-consumers-link { color: rgb(var(--v-theme-primary)); font-size: 0.8rem; font-weight: 600; text-decoration: none; overflow-wrap: anywhere; }
.unit-consumers-site { color: rgba(var(--v-theme-on-surface), 0.58); font-size: 0.7rem; text-decoration: none; overflow-wrap: anywhere; }
.unit-consumers-link:hover, .unit-consumers-site:hover { text-decoration: underline; }
.unit-consumers-quantity { font-size: 0.8rem; font-weight: 600; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.unit-consumers-measure { color: rgba(var(--v-theme-on-surface), 0.6); font-size: 0.7rem; }
.unit-consumers-open { display: inline-flex; justify-content: center; align-items: center; width: 28px; height: 28px; border-radius: 5px; color: rgb(var(--v-theme-primary)); }
.unit-consumers-open:hover { background: rgba(var(--v-theme-primary), 0.07); }
.unit-consumers-table :deep(table) { table-layout: fixed; }
.unit-consumers-table :deep(th) { height: 30px !important; font-size: 0.7rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), 0.65); background: rgba(var(--v-theme-primary), 0.035); }
.unit-consumers-table :deep(th), .unit-consumers-table :deep(td) { padding: 0 12px !important; }
.unit-consumers-table :deep(th:nth-child(2)) { width: 110px; }
.unit-consumers-table :deep(th:last-child), .unit-consumers-table :deep(td:last-child) { padding: 0 6px !important; }
.unit-consumers-table :deep(.v-data-table-footer) { padding: 4px 8px; gap: 4px; font-size: 0.7rem; }
.unit-consumers-table :deep(.v-data-table-footer__items-per-page) { margin-inline-end: 4px; }
.unit-consumers-table :deep(.v-data-table-footer__info) { justify-content: center; min-width: 70px; }
@media (max-width: 480px) {
    .unit-consumers-table :deep(th:nth-child(2)) { width: 110px; }
    .unit-consumers-table :deep(.v-data-table-footer) { justify-content: center; flex-wrap: wrap; }
}
</style>
