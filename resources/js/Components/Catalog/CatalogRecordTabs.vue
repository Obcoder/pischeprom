<script setup>
import { computed, nextTick, onMounted, onScopeDispose, ref, watch } from 'vue'
import { goodRecordTabs, productRecordTabs, catalogRecordTabs, moveTab, normalizeTabOrder } from './recordTabs.js'

const props = defineProps({ modelValue: { type: String, default: 'overview' }, good: { type: Boolean, default: true }, product: { type: Boolean, default: false }, disabled: { type: Boolean, default: false } })
const emit = defineEmits(['update:modelValue'])
const definitions = computed(() => props.good !== false ? goodRecordTabs : props.product ? productRecordTabs : catalogRecordTabs)
const storageKey = computed(() => props.good !== false ? 'ameise.catalog.good-tabs.v1' : props.product ? 'ameise.catalog.product-tabs.v1' : 'ameise.catalog.record-tabs.v1')
const order = ref(normalizeTabOrder(null, definitions.value))
const dragging = ref(null)
const announcement = ref('')
const row = ref(null)
let pointer = null
let resizeObserver = null
const tabs = computed(() => order.value.map(id => definitions.value.find(tab => tab.id === id)).filter(Boolean))

function restoreOrder() {
    try { order.value = normalizeTabOrder(JSON.parse(localStorage.getItem(storageKey.value) || 'null'), definitions.value) }
    catch { order.value = normalizeTabOrder(null, definitions.value) }
}
watch(storageKey, restoreOrder)

function revealActive() {
    row.value?.querySelector(`[data-record-tab="${props.modelValue}"]`)?.scrollIntoView?.({ block: 'nearest', inline: 'nearest' })
}
onMounted(async () => {
    restoreOrder()
    await nextTick()
    revealActive()
    if (row.value && typeof ResizeObserver !== 'undefined') { resizeObserver = new ResizeObserver(revealActive); resizeObserver.observe(row.value) }
})
watch(() => props.modelValue, revealActive, { flush: 'post' })
onScopeDispose(() => resizeObserver?.disconnect())
function reorder(from, to) {
    order.value = moveTab(order.value, from, to)
    try { localStorage.setItem(storageKey.value, JSON.stringify(order.value)) } catch { /* Keep the order for this session. */ }
    const tab = definitions.value.find(item => item.id === from)
    announcement.value = `${tab?.label}: позиция ${order.value.indexOf(from) + 1} из ${order.value.length}`
}
function dragStart(event, id) {
    if (props.disabled) return
    dragging.value = id
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move'
        event.dataTransfer.setData('text/plain', id)
    }
}
function drop(event, id) {
    event.preventDefault()
    if (dragging.value) reorder(dragging.value, id)
    dragging.value = null
}
function startPointer(event, id) {
    if (props.disabled) return
    if (event.button !== 0) return
    event.preventDefault()
    pointer = { id, pointerId: event.pointerId }
    dragging.value = id
    event.currentTarget.setPointerCapture?.(event.pointerId)
}
function movePointer(event) {
    if (!pointer || pointer.pointerId !== event.pointerId) return
    const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-record-tab]')
    if (target && row.value?.contains(target) && target.dataset.recordTab !== pointer.id) reorder(pointer.id, target.dataset.recordTab)
    const bounds = row.value?.getBoundingClientRect()
    if (bounds && event.clientX < bounds.left + 30) row.value.scrollLeft -= 16
    else if (bounds && event.clientX > bounds.right - 30) row.value.scrollLeft += 16
}
function endPointer() { pointer = null; dragging.value = null }
async function keydown(event, id, handle = false) {
    if (props.disabled) return
    const direction = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0
    if (handle && direction) {
        event.preventDefault()
        const target = order.value[order.value.indexOf(id) + direction]
        if (target) reorder(id, target)
        return
    }
    if (!direction && !['Home', 'End'].includes(event.key)) return
    event.preventDefault()
    const index = event.key === 'Home' ? 0 : event.key === 'End' ? order.value.length - 1
        : (order.value.indexOf(id) + direction + order.value.length) % order.value.length
    emit('update:modelValue', order.value[index])
    await nextTick()
    row.value?.querySelector(`[data-record-tab="${order.value[index]}"] [role="tab"]`)?.focus()
}
</script>

<template>
    <div class="catalog-record-tabs">
        <div ref="row" class="catalog-record-tabs__row" role="tablist" :aria-label="good !== false ? 'Разделы карточки товара' : product ? 'Разделы карточки продукта' : 'Разделы карточки записи'">
            <div v-for="tab in tabs" :key="tab.id" :data-record-tab="tab.id" class="catalog-record-tabs__item" :class="{ 'is-active': modelValue === tab.id, 'is-dragging': dragging === tab.id }" role="presentation" :draggable="!disabled" @dragstart="dragStart($event, tab.id)" @dragover.prevent @drop="drop($event, tab.id)" @dragend="dragging = null">
                <button type="button" :disabled="disabled" role="tab" :aria-selected="modelValue === tab.id" :tabindex="modelValue === tab.id ? 0 : -1" @click="emit('update:modelValue', tab.id)" @keydown="keydown($event, tab.id)"><v-icon :icon="tab.icon" size="16" /><span>{{ tab.label }}</span></button>
                <button type="button" :disabled="disabled" class="catalog-record-tabs__handle" :aria-label="`Переместить вкладку ${tab.label}`" title="Перетащите вкладку или используйте стрелки ← →" @pointerdown="startPointer($event, tab.id)" @pointermove="movePointer" @pointerup="endPointer" @pointercancel="endPointer" @lostpointercapture="endPointer" @keydown="keydown($event, tab.id, true)"><v-icon icon="mdi-drag-vertical" size="14" /></button>
            </div>
        </div>
        <span class="catalog-record-tabs__announcement" aria-live="polite">{{ announcement }}</span>
    </div>
</template>

<style scoped>
.catalog-record-tabs { flex: 0 0 auto; min-width: 0; padding: 6px 18px; border-bottom: 1px solid #e6e0eb; background: #faf8fc; }
.catalog-record-tabs__row { display: flex; align-items: center; gap: 4px; overflow-x: auto; scrollbar-width: thin; }
.catalog-record-tabs__item { display: flex; flex: 0 0 auto; align-items: stretch; border: 1px solid transparent; border-radius: 7px; color: #887892; }
.catalog-record-tabs__item > button { display: inline-flex; align-items: center; gap: 6px; padding: 7px 3px 7px 9px; font-size: 11px; font-weight: 550; white-space: nowrap; line-height: 18px; }
.catalog-record-tabs__item.is-active { color: #51355f; border-color: #e1d7e9; background: #eee6f4; }
.catalog-record-tabs__item:hover { background: #f0eaf5; }
.catalog-record-tabs__item.is-dragging { opacity: .6; }
.catalog-record-tabs__item > .catalog-record-tabs__handle { padding: 7px 2px; color: #b7a8c1; cursor: grab; touch-action: none; }
.catalog-record-tabs__item > button:focus-visible { outline: 2px solid #9479a5; outline-offset: -2px; border-radius: 5px; }
.catalog-record-tabs__announcement { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
@media (max-width: 700px) { .catalog-record-tabs { padding: 5px 10px; } }
</style>
