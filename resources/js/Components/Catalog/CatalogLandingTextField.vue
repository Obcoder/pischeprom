<script setup>
import { computed, nextTick, ref } from 'vue'
import { encodeLandingEditorText, insertLandingEditorReference, landingEditorText } from './landingEditorText.js'

const props = defineProps({ modelValue: { type: String, default: '' }, label: { type: String, required: true }, disabled: { type: Boolean, default: false }, links: { type: Array, default: () => [] } })
const emit = defineEmits(['update:modelValue'])
const input = ref(null)
const selected = ref(null)
const selection = ref(null)
const clipboardType = 'application/x-pischeprom-landing-text'
const display = computed(() => landingEditorText(props.modelValue, props.links))
function update(value) { emit('update:modelValue', encodeLandingEditorText(value, display.value.references)) }
function rememberSelection() {
    const element = input.value?.$el?.querySelector('textarea')
    if (element) selection.value = { start: element.selectionStart, end: element.selectionEnd }
}
function copy(event, cut = false) {
    if (!event.clipboardData || (cut && props.disabled)) return
    const start = event.target.selectionStart, end = event.target.selectionEnd
    if (start == null || start === end) return
    const text = display.value.text.slice(start, end)
    const encoded = encodeLandingEditorText(text, display.value.references)
    const labels = new Map(display.value.references.map(reference => [reference.marker, reference.label]))
    event.clipboardData.setData('text/plain', text.replace(/⟦[^⟧]*⟧/g, marker => labels.get(marker) || marker))
    event.clipboardData.setData(clipboardType, encoded)
    event.preventDefault()
    if (cut) update(display.value.text.slice(0, start) + display.value.text.slice(end))
}
async function paste(event) {
    const encoded = event.clipboardData?.getData(clipboardType)
    if (!encoded || props.disabled) return
    event.preventDefault()
    const start = event.target.selectionStart ?? display.value.text.length, end = event.target.selectionEnd ?? start
    const before = display.value.text.slice(0, start), after = display.value.text.slice(end)
    const next = encodeLandingEditorText(before + encoded + after, display.value.references)
    emit('update:modelValue', next)
    const cursor = landingEditorText(encodeLandingEditorText(before, display.value.references) + encoded, props.links).text.length
    await nextTick()
    event.target.setSelectionRange?.(cursor, cursor)
    selection.value = { start: cursor, end: cursor }
}
async function insert(token) {
    const link = props.links.find(item => item.token === token)
    if (!link || props.disabled) return
    const start = selection.value?.start ?? display.value.text.length, end = selection.value?.end ?? start
    const result = insertLandingEditorReference(props.modelValue, start, end, link, props.links)
    emit('update:modelValue', result.value)
    selected.value = null
    await nextTick()
    const element = input.value?.$el?.querySelector('textarea')
    element?.focus()
    element?.setSelectionRange(result.cursor, result.cursor)
    selection.value = { start: result.cursor, end: result.cursor }
}
</script>

<template>
    <div class="landing-text-field">
        <v-textarea ref="input" :model-value="display.text" :label="label" variant="outlined" density="compact" rows="3" auto-grow :disabled="disabled" @update:model-value="update" @blur="rememberSelection" @keyup="rememberSelection" @mouseup="rememberSelection" @copy="copy($event)" @cut="copy($event, true)" @paste="paste" />
        <div v-if="display.references.length" class="landing-text-field__references"><span>Ссылки:</span><span v-for="reference in display.references" :key="reference.marker" :title="reference.name">{{ reference.label }}<small v-if="reference.name !== reference.label"> — {{ reference.name }}</small></span></div>
        <v-autocomplete v-if="links.length" v-model="selected" :items="links" item-title="name" item-value="token" label="Вставить ссылку на товар или источник" prepend-inner-icon="mdi-link-variant" variant="outlined" density="compact" hide-details clearable :disabled="disabled" @update:model-value="insert" />
        <p v-if="display.references.length || links.length" class="landing-text-field__hint">Ссылки обозначены ⟦скобками⟧. Выберите ссылку ниже текста, чтобы вставить её на место курсора. Сохраняйте подпись в скобках целиком; её изменение превращает ссылку в обычный текст.</p>
    </div>
</template>

<style scoped>
.landing-text-field { margin-bottom: 8px; }
.landing-text-field__references { display: flex; flex-wrap: wrap; gap: 4px 10px; margin: -10px 0 12px; font-size: 11px; color: #806592; }
.landing-text-field__references small { font-size: inherit; }
.landing-text-field__hint { font-size: 10px; color: #887892; line-height: 1.6; margin: 7px 0 12px; }
</style>
