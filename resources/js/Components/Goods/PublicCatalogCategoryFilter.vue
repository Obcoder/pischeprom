<script setup>
import { computed, ref, watch } from 'vue'
import { catalogCategoryIndex, catalogCategoryAncestors, catalogCategorySearch, catalogCategoryRows } from '@/utils/catalogCategoryFilter'

const props = defineProps({ nodes: { type: Array, default: () => [] }, selectedId: { type: [Number, String], default: '' } })
defineEmits(['select'])
const query = ref('')
const expanded = ref(new Set())
const list = ref(null)
const index = computed(() => catalogCategoryIndex(props.nodes))
const search = computed(() => catalogCategorySearch(index.value, query.value))
const rows = computed(() => catalogCategoryRows(index.value, expanded.value, search.value))
const selectedKey = computed(() => String(props.selectedId ?? ''))
const selectedName = computed(() => index.value.byId.get(selectedKey.value)?.name || '')

watch(() => [props.nodes, props.selectedId], () => {
    const next = new Set([...expanded.value].filter(key => index.value.byId.has(key)))
    for (const key of catalogCategoryAncestors(index.value, props.selectedId)) next.add(key)
    expanded.value = next
}, { immediate: true })

watch(search, result => {
    if (!result) return
    const next = new Set(expanded.value)
    for (const match of result.matches) {
        for (const ancestor of catalogCategoryAncestors(index.value, match)) next.add(ancestor)
    }
    expanded.value = next
})

// Wait until Vue has rendered the selected branch, and scroll this panel only.
// A catalogue/count refresh must not move a list the customer is browsing.
watch(() => [props.selectedId, list.value], () => {
    const panel = list.value
    const selected = panel?.querySelector('.catalog-category-filter__row.is-selected')
    if (!selected) return
    const top = selected.offsetTop
    const bottom = top + selected.offsetHeight
    if (top < panel.scrollTop) panel.scrollTop = top
    else if (bottom > panel.scrollTop + panel.clientHeight) panel.scrollTop = bottom - panel.clientHeight
}, { flush: 'post' })

function toggle(key) {
    const next = new Set(expanded.value)
    if (next.has(key)) next.delete(key)
    else next.add(key)
    expanded.value = next
}

function countLabel(node) {
    const count = Number(node.goods_count)
    return Number.isFinite(count) ? Math.max(0, count).toLocaleString('ru-RU') : '0'
}
</script>

<template>
    <section class="catalog-category-filter" aria-label="Фильтр по категориям">
        <div class="catalog-category-filter__heading"><strong>Категории</strong><span v-if="search" aria-live="polite">Найдено: {{ search.matches.size }}</span></div>
        <div class="catalog-category-filter__search">
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5" /><path d="m12.5 12.5 4 4" /></svg>
            <input v-model="query" type="search" aria-label="Найти категорию" placeholder="Найти категорию" autocomplete="off" @keydown.esc="query = ''" />
            <button v-if="query" type="button" aria-label="Очистить поиск категорий" @click="query = ''"><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8" /></svg></button>
        </div>
        <button type="button" class="catalog-category-filter__all" :class="{ 'is-selected': !selectedKey }" :aria-pressed="!selectedKey" @click="$emit('select', '')">
            <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="2" y="2" width="4" height="4" rx=".6" /><rect x="10" y="2" width="4" height="4" rx=".6" /><rect x="2" y="10" width="4" height="4" rx=".6" /><rect x="10" y="10" width="4" height="4" rx=".6" /></svg>
            <span>Все категории</span><span v-if="!selectedKey" class="catalog-category-filter__check" aria-hidden="true">✓</span>
        </button>
        <div ref="list" class="catalog-category-filter__list" role="group" aria-label="Категории каталога">
            <div v-for="row in rows" :key="row.key" class="catalog-category-filter__row" :class="{ 'is-selected': selectedKey === row.key, 'is-match': row.matched }" :style="{ '--category-depth': Math.min(row.depth, 4) }">
                <button v-if="row.hasChildren" type="button" class="catalog-category-filter__expand" :aria-expanded="expanded.has(row.key)" :aria-label="`${expanded.has(row.key) ? 'Свернуть' : 'Раскрыть'} категорию ${row.node.name}`" @click="toggle(row.key)"><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m6 4 4 4-4 4" /></svg></button>
                <span v-else class="catalog-category-filter__leaf" aria-hidden="true"><span /></span>
                <button type="button" class="catalog-category-filter__select" :aria-pressed="selectedKey === row.key" :title="row.node.name" @click="$emit('select', row.node.id)"><span class="catalog-category-filter__name">{{ row.node.name }}</span><span class="catalog-category-filter__count" :aria-label="`Товаров: ${countLabel(row.node)}`">{{ countLabel(row.node) }}</span></button>
            </div>
            <p v-if="!rows.length" class="catalog-category-filter__empty" role="status">{{ search ? 'Категории не найдены' : 'Категорий пока нет' }}</p>
        </div>
        <p v-if="selectedName && search && !search.visible.has(selectedKey)" class="catalog-category-filter__current">Выбрано: <strong>{{ selectedName }}</strong></p>
    </section>
</template>

<style scoped>
.catalog-category-filter { min-width: 0; color: #56614d; }
.catalog-category-filter__heading { display: flex; align-items: baseline; justify-content: space-between; gap: 5px; margin-bottom: 6px; }
.catalog-category-filter__heading strong { font-size: 11px; font-weight: 650; }
.catalog-category-filter__heading > span { color: #909783; font-size: 9px; }
.catalog-category-filter__search { display: flex; align-items: center; gap: 5px; min-height: 30px; padding: 0 7px; border: 1px solid #dedfd4; border-radius: 5px; background: white; }
.catalog-category-filter__search:focus-within { border-color: #8b9a75; box-shadow: 0 0 0 2px #65754b12; }
.catalog-category-filter__search > svg { flex: 0 0 13px; width: 13px; height: 13px; color: #929b86; stroke: currentColor; stroke-width: 1.5; }
.catalog-category-filter__search input { flex: 1; width: 100%; min-width: 0; padding: 6px 0; outline: none; color: #46553c; font-size: 11px; }
.catalog-category-filter__search input::placeholder { color: #9a9f90; }
.catalog-category-filter__search input::-webkit-search-cancel-button { display: none; }
.catalog-category-filter__search button { display: grid; flex: 0 0 22px; place-items: center; width: 22px; height: 24px; border-radius: 3px; color: #939a89; }
.catalog-category-filter__search button svg { width: 13px; height: 13px; stroke: currentColor; stroke-width: 1.6; }
.catalog-category-filter__all { display: flex; align-items: center; gap: 6px; width: 100%; min-height: 29px; margin: 5px 0 3px; padding: 5px 7px; border-radius: 5px; color: #687260; text-align: left; font-size: 11px; }
.catalog-category-filter__all > svg { width: 13px; height: 13px; flex: 0 0 13px; stroke: currentColor; stroke-width: 1.2; }
.catalog-category-filter__all.is-selected, .catalog-category-filter__row.is-selected { background: #e9eedf; color: #435b32; }
.catalog-category-filter__all.is-selected { font-weight: 650; }
.catalog-category-filter__check { margin-left: auto; font-size: 11px; }
.catalog-category-filter__list { position: relative; max-height: 224px; overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin; scrollbar-color: #d2d8c8 transparent; padding-right: 2px; }
.catalog-category-filter__row { display: flex; align-items: stretch; min-width: 0; min-height: 30px; margin: 1px 0; padding-left: calc(var(--category-depth) * 9px); border-radius: 4px; }
.catalog-category-filter__expand, .catalog-category-filter__leaf { display: grid; place-items: center; flex: 0 0 21px; width: 21px; min-height: 30px; color: #8b977d; }
.catalog-category-filter__expand { border-radius: 4px; }
.catalog-category-filter__expand svg { width: 13px; height: 13px; stroke: currentColor; stroke-width: 1.7; transition: transform .12s ease; }
.catalog-category-filter__expand[aria-expanded="true"] svg { transform: rotate(90deg); }
.catalog-category-filter__leaf > span { width: 3px; height: 3px; border-radius: 50%; background: #bec5b5; }
.catalog-category-filter__select { display: flex; flex: 1; align-items: center; gap: 5px; min-width: 0; padding: 5px 5px 5px 1px; border-radius: 4px; text-align: left; font-size: 11px; line-height: 1.3; }
.catalog-category-filter__name { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.catalog-category-filter__count { flex: none; min-width: 18px; padding: 2px 4px; border-radius: 4px; background: #f0f2eb; color: #8c9680; font-size: 9px; text-align: center; font-variant-numeric: tabular-nums; }
.catalog-category-filter__row.is-selected .catalog-category-filter__name, .catalog-category-filter__row.is-match .catalog-category-filter__name { font-weight: 650; }
.catalog-category-filter__row.is-selected .catalog-category-filter__count { background: #dbe5cf; color: #597445; }
.catalog-category-filter__expand:hover, .catalog-category-filter__select:hover, .catalog-category-filter__all:hover { background: #6573500d; color: #435b32; }
.catalog-category-filter__empty { margin: 0; padding: 12px 7px; color: #999e90; font-size: 11px; line-height: 1.4; }
.catalog-category-filter__current { margin: 5px 0 0; padding-top: 6px; border-top: 1px solid #e6e9df; color: #909880; font-size: 9px; line-height: 1.4; overflow-wrap: anywhere; }
.catalog-category-filter__current strong { color: #5b6c4b; font-weight: 500; }
.catalog-category-filter button:focus-visible { outline: 2px solid #800000; outline-offset: -2px; }
@media (prefers-reduced-motion: reduce) { .catalog-category-filter__expand svg { transition: none; } }
</style>
