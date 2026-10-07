<script setup>
import { computed, ref } from 'vue'
import GoodTradeCodeFields from '../Goods/GoodTradeCodeFields.vue'
import GoodVatCheck from '../Goods/GoodVatCheck.vue'
import { safeGalleryUrl } from './gallery.js'

const props = defineProps({
    modelValue: { type: Object, required: true },
    overview: { type: Object, default: null },
    options: { type: Object, default: () => ({}) },
    context: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
    active: { type: Boolean, default: true },
    editUrl: { type: String, default: '' },
    inlineNavigation: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'navigate'])
const clipboardMessage = ref('')
const draft = computed(() => ({ ...props.context, ...props.modelValue, id: props.overview?.id, product_ids: props.modelValue.products || [] }))
const categories = computed(() => new Map((props.options.categories || []).map(category => [String(category.id), category.name || category.rus])))
const productOptions = computed(() => (props.options.products || []).map(product => ({
    ...product, label: product.rus || product.name || product.eng || `Продукт № ${product.id}`,
    category_name: product.category?.name || categories.value.get(String(product.category_id)) || '',
})))
const fieldOptions = computed(() => (props.options.fields || []).map(field => ({ ...field, label: field.title || field.name || `Подборка № ${field.id}` })))
const stats = computed(() => [
    { key: 'prices', label: 'Цены', icon: 'mdi-tag-outline' },
    { key: 'sales', label: 'Продажи', icon: 'mdi-cart-outline' },
    { key: 'purchases', label: 'Закупки', icon: 'mdi-truck-outline' },
    { key: 'media', label: 'Медиа', icon: 'mdi-image-multiple-outline' },
].map(stat => ({ ...stat, count: props.overview?.counts?.[stat.key] ?? 0 })))
const errorFor = key => props.errors[key] || []
const vatTitle = rate => `${rate.title || 'НДС'} · ${rate.rate}%`
const formatDate = value => {
    if (!value) return '—'
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function update(key, value) {
    if (!props.disabled) emit('update:modelValue', { ...props.modelValue, [key]: value })
}
function updateCodes(value) {
    if (!props.disabled) emit('update:modelValue', { ...props.modelValue, ...value })
}
async function copy(value, label) {
    if (!value) return
    try {
        if (!globalThis.navigator?.clipboard) throw new Error('unavailable')
        await navigator.clipboard.writeText(String(value))
        clipboardMessage.value = `${label}: ссылка скопирована`
    } catch { clipboardMessage.value = 'Не удалось скопировать ссылку' }
}
function statUrl(key) {
    const tab = key === 'purchases' ? 'quotations' : key
    return props.editUrl ? `${props.editUrl}${props.editUrl.includes('?') ? '&' : '?'}tab=${tab}` : undefined
}
function openStat(event, key) {
    if (!props.inlineNavigation || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button > 0) return
    event.preventDefault()
    emit('navigate', key === 'purchases' ? 'quotations' : key)
}
</script>

<template>
    <section class="catalog-good-overview">
        <div class="catalog-good-overview__heading"><h3><v-icon icon="mdi-package-variant-closed" size="18" /> Данные товара</h3><span v-if="overview?.id">Товар № {{ overview.id }}</span></div>
        <div class="catalog-good-overview__grid">
            <section class="catalog-good-overview__facts">
                <v-text-field :model-value="modelValue.denominator" label="Количество в упаковке" type="number" step="0.001" min="0" variant="outlined" density="compact" :disabled="disabled" :error-messages="errorFor('denominator')" @update:model-value="update('denominator', $event)" />
                <v-autocomplete :model-value="modelValue.country_id" :items="options.countries || []" item-title="name" item-value="id" label="Страна происхождения" variant="outlined" density="compact" clearable :disabled="disabled" :error-messages="errorFor('country_id')" @update:model-value="update('country_id', $event)">
                    <template #item="{ props: itemProps, item }"><v-list-item v-bind="itemProps"><template #prepend><v-avatar size="22" class="mr-2"><v-img v-if="item.raw.flag" :src="item.raw.flag" :alt="item.raw.name" cover /><span v-else>{{ item.raw.name?.slice(0, 1) }}</span></v-avatar></template></v-list-item></template>
                    <template #selection="{ item }"><span class="catalog-good-overview__country"><v-avatar size="20"><v-img v-if="item.raw.flag" :src="item.raw.flag" :alt="item.raw.name" cover /><span v-else>{{ item.raw.name?.slice(0, 1) }}</span></v-avatar>{{ item.raw.name }}</span></template>
                </v-autocomplete>
                <v-select :model-value="modelValue.vat_rate_id" :items="options.vat_rates || []" :item-title="vatTitle" item-value="id" label="НДС" variant="outlined" density="compact" clearable :disabled="disabled" :error-messages="errorFor('vat_rate_id')" @update:model-value="update('vat_rate_id', $event)" />
                <GoodVatCheck :draft="draft" :vat-rates="options.vat_rates || []" :disabled="disabled" :active="active" @apply="update('vat_rate_id', $event)" />
            </section>
            <section class="catalog-good-overview__relations">
                <v-autocomplete :model-value="modelValue.products" :items="productOptions" item-title="label" item-value="id" label="Связанные продукты" multiple chips closable-chips clearable variant="outlined" density="compact" :disabled="disabled" :error-messages="errorFor('products')" @update:model-value="update('products', $event || [])">
                    <template #item="{ props: itemProps, item }"><v-list-item v-bind="itemProps" :subtitle="item.raw.category_name" /></template>
                </v-autocomplete>
                <v-autocomplete :model-value="modelValue.fields" :items="fieldOptions" item-title="label" item-value="id" label="Подборки" multiple chips closable-chips clearable variant="outlined" density="compact" :disabled="disabled" :error-messages="errorFor('fields')" @update:model-value="update('fields', $event || [])" />
                <div class="catalog-good-overview__stats" aria-label="Быстрая статистика">
                    <a v-for="stat in stats" :key="stat.key" :href="statUrl(stat.key)" :target="editUrl && !inlineNavigation ? '_blank' : undefined" :rel="editUrl ? 'noopener noreferrer' : undefined" class="catalog-good-overview__stat" @click="openStat($event, stat.key)"><v-icon :icon="stat.icon" size="18" /><span>{{ stat.label }}</span><strong>{{ stat.count }}</strong></a>
                </div>
                <dl class="catalog-good-overview__metadata"><div><dt>Создан</dt><dd>{{ formatDate(overview?.created_at) }}</dd></div><div><dt>Обновлён</dt><dd>{{ formatDate(overview?.updated_at) }}</dd></div></dl>
                <div v-for="image in [{ key: 'ava_image', label: 'Оригинал', copyLabel: 'Скопировать оригинал' }, { key: 'ava_thumb', label: 'Миниатюра', copyLabel: 'Скопировать миниатюру' }]" :key="image.key" class="catalog-good-overview__file"><span>{{ image.label }}</span><a :href="safeGalleryUrl(overview?.[image.key]) || undefined" target="_blank" rel="noopener noreferrer" :title="overview?.[image.key] || ''">{{ overview?.[image.key] || '—' }}</a><v-btn icon="mdi-content-copy" size="x-small" variant="text" :disabled="!overview?.[image.key]" :aria-label="image.copyLabel" @click="copy(overview?.[image.key], image.label)" /></div>
                <p v-if="clipboardMessage" class="catalog-good-overview__copied" role="status">{{ clipboardMessage }}</p>
            </section>
            <section class="catalog-good-overview__codes"><GoodTradeCodeFields :model-value="modelValue" :context="draft" :errors="errors" :disabled="disabled" :active="active" @update:model-value="updateCodes" /></section>
        </div>
    </section>
</template>

<style scoped>
.catalog-good-overview { margin-top: 18px; padding-top: 16px; border-top: 1px solid #e6e0eb; }
.catalog-good-overview__heading { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 16px; }
.catalog-good-overview__heading h3 { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #4d4058; font-weight: 650; }
.catalog-good-overview__heading > span { color: #8c8294; font-size: 11px; }
.catalog-good-overview__grid { display: grid; grid-template-columns: minmax(0, .95fr) minmax(0, 1.15fr) minmax(0, 1.45fr); gap: 20px; }
.catalog-good-overview__grid > section { min-width: 0; }
.catalog-good-overview__country { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; }
.catalog-good-overview__stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 7px; margin: 0 0 12px; }
.catalog-good-overview__stat { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 5px; padding: 8px; background: #f6f2f9; border: 1px solid #e9e1ef; border-radius: 8px; color: #8d789d; text-decoration: none; font-size: 10px; }
.catalog-good-overview__stat span { grid-column: 1 / -1; grid-row: 2; }
.catalog-good-overview__stat strong { color: #644e74; font-size: 16px; font-weight: 600; }
.catalog-good-overview__metadata { display: flex; flex-wrap: wrap; gap: 6px 20px; margin-bottom: 8px; font-size: 10px; }
.catalog-good-overview__metadata dt { color: #9a8da4; }
.catalog-good-overview__metadata dd { margin: 2px 0 0; color: #75627f; }
.catalog-good-overview__file { display: grid; grid-template-columns: 62px minmax(0, 1fr) 24px; align-items: center; gap: 6px; font-size: 10px; color: #998aa4; }
.catalog-good-overview__file a { color: #75627f; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-decoration: none; }
.catalog-good-overview__file a[href]:hover { text-decoration: underline; }
.catalog-good-overview__copied { font-size: 10px; color: #806592; margin-top: 5px; }
.catalog-good-overview :deep(.v-chip) { max-width: 100%; }
.catalog-good-overview :deep(.v-chip__content) { overflow: hidden; text-overflow: ellipsis; }
.catalog-good-overview :deep(.good-code-registry .v-btn) { max-width: 100%; min-width: 0; height: auto; min-height: 30px; padding-block: 7px; }
.catalog-good-overview :deep(.good-code-registry .v-btn__content) { white-space: normal; overflow-wrap: anywhere; line-height: 1.4; }
@media (max-width: 1150px) { .catalog-good-overview__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .catalog-good-overview__codes { grid-column: 1 / -1; } }
@media (max-width: 700px) { .catalog-good-overview__grid { grid-template-columns: 1fr; gap: 16px; } .catalog-good-overview__codes { grid-column: auto; } }
</style>
