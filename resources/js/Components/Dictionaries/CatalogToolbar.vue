<script setup>
import { ref } from 'vue'

defineProps({
    count: { type: Number, default: 0 },
    total: { type: Number, default: null },
    filtersCount: { type: Number, default: 0 },
})

const emit = defineEmits(['update:filtersOpen'])
const filtersOpen = ref(false)
function toggleFilters() {
    filtersOpen.value = !filtersOpen.value
    emit('update:filtersOpen', filtersOpen.value)
}
</script>

<template>
    <div class="catalog-toolbar-group">
        <div class="catalog-toolbar">
            <div class="catalog-toolbar__fields">
                <slot />
            </div>
            <div class="catalog-toolbar__actions">
                <span class="catalog-toolbar__count" aria-live="polite">
                    {{ count }}<template v-if="total !== null && total !== count"> / {{ total }}</template>
                    <span>записей</span>
                </span>
                <v-btn
                    v-if="$slots.filters"
                    size="small"
                    :variant="filtersOpen || filtersCount ? 'tonal' : 'text'"
                    color="#352345"
                    prepend-icon="mdi-filter-variant"
                    :aria-expanded="filtersOpen"
                    @click="toggleFilters"
                >
                    Фильтры<template v-if="filtersCount"> · {{ filtersCount }}</template>
                </v-btn>
                <slot name="actions" />
            </div>
        </div>
        <v-expand-transition>
            <div v-if="$slots.filters && filtersOpen" class="catalog-toolbar__filters">
                <slot name="filters" />
            </div>
        </v-expand-transition>
    </div>
</template>

<style scoped>
.catalog-toolbar-group { flex: 0 0 auto; min-width: 0; background: #fff; border-bottom: 1px solid #d9d7dc; }
.catalog-toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 12px; padding: 6px 10px; }
.catalog-toolbar__fields { display: flex; align-items: center; gap: 8px; flex: 1 1 360px; min-width: 0; }
.catalog-toolbar__fields > :deep(.v-input) { flex: 1 1 170px; min-width: 120px; max-width: 220px; }
.catalog-toolbar__fields > :deep(.catalog-toolbar__search) { flex-basis: 260px; max-width: 360px; }
.catalog-toolbar__actions { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-left: auto; }
.catalog-toolbar__count { margin-right: 6px; color: #352345; font-size: 11px; font-variant-numeric: tabular-nums; white-space: nowrap; }
.catalog-toolbar__count span { margin-left: 3px; color: #85808a; }
.catalog-toolbar__filters { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding: 8px 10px; border-top: 1px solid #e9e7eb; background: #faf9fb; }
.catalog-toolbar__filters > :deep(.v-input) { flex: 1 1 160px; min-width: 140px; max-width: 240px; }
.catalog-toolbar-group :deep(.v-input) { --v-input-control-height: 32px; --v-input-padding-top: 0px; }
.catalog-toolbar-group :deep(.v-field) { --v-field-padding-top: 0px; --v-field-padding-bottom: 0px; border-radius: 0; font-size: 12px; box-shadow: none; }
.catalog-toolbar-group :deep(.v-field__input) { min-height: 32px; padding-top: 5px; padding-bottom: 5px; }
.catalog-toolbar-group :deep(.v-field__prepend-inner),
.catalog-toolbar-group :deep(.v-field__append-inner),
.catalog-toolbar-group :deep(.v-field__clearable) { padding-top: 0; align-items: center; }
.catalog-toolbar-group :deep(.v-field-label) { font-size: 12px; }
.catalog-toolbar-group :deep(.v-btn) { height: 30px; border-radius: 0; box-shadow: none; letter-spacing: 0; font-size: 11px; text-transform: none; }
.catalog-toolbar-group :deep(.v-btn--icon) { width: 30px; }
@media (max-width: 680px) {
    .catalog-toolbar__fields { flex-wrap: wrap; flex-basis: 100%; }
    .catalog-toolbar__fields > :deep(.v-input), .catalog-toolbar__fields > :deep(.catalog-toolbar__search) { max-width: none; }
    .catalog-toolbar__actions { width: 100%; }
    .catalog-toolbar__count { margin-right: auto; }
    .catalog-toolbar__filters > :deep(.v-input) { max-width: none; }
}
</style>
