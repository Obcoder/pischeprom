<script setup>
import { defaultFields } from './Landing/schema.js'
import CatalogLandingTextField from './CatalogLandingTextField.vue'
import { supportsLandingReferences } from './landingEditorText.js'

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
    fields: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    context: { type: String, default: '' },
    links: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue', 'upload'])
const value = key => props.modelValue?.[key]
const items = key => Array.isArray(value(key)) ? value(key) : []
function update(key, next) {
    if (!props.disabled) emit('update:modelValue', { ...props.modelValue, [key]: next })
}
function updateItem(field, index, next) {
    const result = [...items(field.key)]
    result[index] = next
    update(field.key, result)
}
function add(field) {
    const next = field.fields ? defaultFields(field.fields) : ['number', 'integer'].includes(field.itemType) ? 0 : ''
    if (field.fields?.some(item => item.key === 'id')) next.id = `${props.context === 'sources' ? 'source' : 'item'}-${globalThis.crypto?.randomUUID?.() || Math.random().toString(36).slice(2)}`
    update(field.key, [...items(field.key), next])
}
function remove(field, index) { update(field.key, items(field.key).filter((_, position) => position !== index)) }
function move(field, index, direction) {
    const result = [...items(field.key)], target = index + direction
    if (target < 0 || target >= result.length) return
    ;[result[index], result[target]] = [result[target], result[index]]
    update(field.key, result)
}
function upload(field, files) {
    const file = Array.isArray(files) ? files[0] : files
    if (!file || props.disabled) return
    // The upload locks the editor until this callback has applied the returned URL.
    emit('upload', { file, apply: url => emit('update:modelValue', { ...props.modelValue, [field.key]: url }) })
}
const numeric = (field, next) => next === '' || next == null ? 0 : Number(next)
const imageUrl = url => typeof url === 'string' && /^(https?:\/\/|\/(?!\/))/i.test(url) ? url : ''
</script>

<template>
    <div class="landing-fields">
        <template v-for="field in fields.filter(item => item.key !== 'id')" :key="field.key">
            <fieldset v-if="field.type === 'object'" class="landing-fields__group" :disabled="disabled">
                <legend>{{ field.label }}</legend>
                <CatalogLandingFields :model-value="value(field.key) || {}" :fields="field.fields" :context="`${context}.${field.key}`" :links="links" :disabled="disabled" @update:model-value="update(field.key, $event)" @upload="emit('upload', $event)" />
            </fieldset>
            <fieldset v-else-if="field.type === 'list'" class="landing-fields__group" :disabled="disabled">
                <legend>{{ field.label }}</legend>
                <div v-for="(item, index) in items(field.key)" :key="index" class="landing-fields__item">
                    <div class="landing-fields__item-actions">
                        <span>{{ field.label }} · {{ index + 1 }}</span>
                        <v-btn icon="mdi-arrow-up" variant="text" size="x-small" :disabled="disabled || index === 0" :aria-label="`Поднять элемент ${index + 1}`" @click="move(field, index, -1)" />
                        <v-btn icon="mdi-arrow-down" variant="text" size="x-small" :disabled="disabled || index === items(field.key).length - 1" :aria-label="`Опустить элемент ${index + 1}`" @click="move(field, index, 1)" />
                        <v-btn icon="mdi-close" color="error" variant="text" size="x-small" :disabled="disabled" :aria-label="`Удалить элемент ${index + 1}`" @click="remove(field, index)" />
                    </div>
                    <CatalogLandingFields v-if="field.fields" :model-value="item" :fields="field.fields" :context="`${context}.${field.key}.${index}`" :links="links" :disabled="disabled" @update:model-value="updateItem(field, index, $event)" @upload="emit('upload', $event)" />
                    <v-text-field v-else-if="['number', 'integer'].includes(field.itemType)" :model-value="item" :label="`${field.label} · ${index + 1}`" type="number" :min="field.min" variant="outlined" density="compact" :disabled="disabled" @update:model-value="updateItem(field, index, numeric(field, $event))" />
                    <CatalogLandingTextField v-else-if="supportsLandingReferences(context, field.key)" :model-value="item" :label="`${field.label} · ${index + 1}`" :links="links" :disabled="disabled" @update:model-value="updateItem(field, index, $event)" />
                    <v-textarea v-else :model-value="item" :label="`${field.label} · ${index + 1}`" variant="outlined" density="compact" auto-grow rows="2" :disabled="disabled" @update:model-value="updateItem(field, index, $event || '')" />
                </div>
                <v-btn prepend-icon="mdi-plus" variant="text" size="small" :disabled="disabled" @click="add(field)">Добавить: {{ field.label.toLowerCase() }}</v-btn>
            </fieldset>
            <v-switch v-else-if="field.type === 'boolean'" :model-value="value(field.key) ?? field.default ?? false" :label="field.label" color="#4d315e" hide-details density="compact" :disabled="disabled" @update:model-value="update(field.key, $event)" />
            <v-select v-else-if="field.type === 'select'" :model-value="value(field.key)" :items="field.options" :label="field.label" variant="outlined" density="compact" :disabled="disabled" @update:model-value="update(field.key, $event)" />
            <CatalogLandingTextField v-else-if="['textarea', 'text'].includes(field.type) && supportsLandingReferences(context, field.key)" :model-value="value(field.key) || ''" :label="field.label" :links="links" :disabled="disabled" @update:model-value="update(field.key, $event)" />
            <v-textarea v-else-if="field.type === 'textarea'" :model-value="value(field.key)" :label="field.label" variant="outlined" density="compact" rows="3" auto-grow :disabled="disabled" @update:model-value="update(field.key, $event || '')" />
            <div v-else>
                <v-text-field :model-value="value(field.key)" :label="field.label" :type="field.type === 'number' ? 'number' : 'text'" :min="field.min" :max="field.max" :step="field.type === 'number' ? 'any' : undefined" variant="outlined" density="compact" :disabled="disabled" @update:model-value="update(field.key, field.type === 'number' ? numeric(field, $event) : ($event || ''))" />
                <template v-if="field.type === 'url' && field.key === 'image'">
                    <v-img v-if="imageUrl(value(field.key))" :src="imageUrl(value(field.key))" max-width="240" height="140" cover rounded class="mb-3" />
                    <v-file-input :model-value="null" label="Загрузить изображение" accept="image/jpeg,image/png,image/webp,image/gif" variant="outlined" density="compact" hint="До 5 МБ. Загрузка добавляет изображение в черновик." :disabled="disabled" @update:model-value="upload(field, $event)" />
                </template>
            </div>
        </template>
    </div>
</template>

<style scoped>
.landing-fields { display: grid; gap: 8px; min-width: 0; }
.landing-fields__group { border: 1px solid #e6e0eb; border-radius: 8px; padding: 14px; min-width: 0; margin: 4px 0 10px; }
.landing-fields__group > legend { padding: 0 6px; color: #665570; font-size: 12px; }
.landing-fields__item { padding: 10px 0 0; border-bottom: 1px solid #ece7f0; margin-bottom: 10px; }
.landing-fields__item-actions { display: flex; align-items: center; gap: 3px; margin-bottom: 10px; }
.landing-fields__item-actions > span { flex: 1; font-size: 11px; color: #887892; }
</style>
