<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import { entityContacts, entityMatches, entityRequisites } from '@/utils/orderEntityOptions.js'

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    entities: { type: Array, default: () => [] },
    disabled: Boolean,
    errorMessages: { type: [Array, String], default: () => [] },
})
const emit = defineEmits(['update:modelValue'])
const root = ref(null)
const trigger = ref(null)
const searchInput = ref(null)
const opened = ref(false)
const dropdownStyle = ref({})
const query = ref('')
const active = ref(0)
const id = useId()
const selected = computed(() => props.entities.find(entity => String(entity.id) === String(props.modelValue)))
const matching = computed(() => props.entities.filter(entity => entityMatches(entity, query.value)))
const errors = computed(() => Array.isArray(props.errorMessages) ? props.errorMessages : [props.errorMessages].filter(Boolean))

async function open() {
    if (props.disabled) return
    opened.value = true
    query.value = ''
    active.value = Math.max(0, matching.value.findIndex(entity => String(entity.id) === String(props.modelValue)))
    await nextTick()
    searchInput.value?.focus()
    updatePlacement()
    await nextTick()
    scrollActive()
}

function updatePlacement() {
    if (!opened.value || !root.value) return
    const anchor = root.value.getBoundingClientRect()
    const viewport = window.visualViewport
    let top = Math.max(8, viewport?.offsetTop || 0)
    let bottom = Math.min(window.innerHeight, viewport ? viewport.offsetTop + viewport.height : window.innerHeight) - 8
    for (let parent = root.value.parentElement; parent; parent = parent.parentElement) {
        if (!/(auto|scroll|hidden|clip)/.test(window.getComputedStyle(parent).overflowY)) continue
        const bounds = parent.getBoundingClientRect()
        top = Math.max(top, bounds.top + 4)
        bottom = Math.min(bottom, bounds.bottom - 4)
    }
    const footer = root.value.closest('form')?.querySelector('footer')?.getBoundingClientRect()
    if (footer && footer.top > anchor.bottom && footer.top < bottom) bottom = footer.top - 6
    const below = bottom - anchor.bottom - 5
    const above = anchor.top - top - 5
    const upwards = below < 180 && above > below
    dropdownStyle.value = {
        maxHeight: `${Math.max(0, Math.min(360, upwards ? above : below))}px`,
        top: upwards ? 'auto' : 'calc(100% + 5px)',
        bottom: upwards ? 'calc(100% + 5px)' : 'auto',
    }
}

function close(restoreFocus = false) {
    opened.value = false
    if (restoreFocus) trigger.value?.focus()
}

function select(entity) {
    emit('update:modelValue', entity.id)
    close(true)
}

function scrollActive() {
    root.value?.querySelector(`[data-option-index="${active.value}"]`)?.scrollIntoView({ block: 'nearest' })
}

async function move(direction) {
    if (!matching.value.length) return
    active.value = (active.value + direction + matching.value.length) % matching.value.length
    await nextTick()
    scrollActive()
}

function outside(event) {
    if (!root.value?.contains(event.target)) close()
}

watch(query, () => { active.value = 0 })
watch(() => props.disabled, disabled => { if (disabled) close() })
onMounted(() => {
    document.addEventListener('pointerdown', outside)
    document.addEventListener('scroll', updatePlacement, true)
    window.addEventListener('resize', updatePlacement)
    window.visualViewport?.addEventListener('resize', updatePlacement)
})
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', outside)
    document.removeEventListener('scroll', updatePlacement, true)
    window.removeEventListener('resize', updatePlacement)
    window.visualViewport?.removeEventListener('resize', updatePlacement)
})
</script>

<template>
    <div ref="root" class="entity-picker" @keydown.esc.stop.prevent="close(true)" @focusout="event => { if (!event.currentTarget.contains(event.relatedTarget)) close() }">
        <span :id="`${id}-label`" class="entity-picker__label">Покупатель · Entity</span>
        <button ref="trigger" type="button" class="entity-picker__trigger" :class="{ 'entity-picker__trigger--invalid': errors.length }" :disabled="disabled" aria-haspopup="listbox" :aria-expanded="opened" :aria-controls="`${id}-list`" :aria-labelledby="`${id}-label ${id}-value`" :aria-describedby="errors.length ? `${id}-error` : undefined" @click="opened ? close() : open()" @keydown.down.prevent="open()">
            <span class="entity-picker__avatar"><v-icon :icon="selected ? 'mdi-domain' : 'mdi-account-search-outline'" size="18" /></span>
            <span :id="`${id}-value`" class="entity-picker__summary">
                <strong>{{ selected?.name || (modelValue ? `Entity #${modelValue}` : 'Выберите покупателя') }}</strong>
                <small>{{ selected ? [entityRequisites(selected)[0], entityContacts(selected)[0]].filter(Boolean).join(' · ') || selected.full_name || `ID ${selected.id}` : 'Название, ИНН, телефон или e-mail' }}</small>
            </span>
            <span v-if="selected?.classification?.name" class="entity-picker__tag">{{ selected.classification.name }}</span>
            <v-icon :icon="opened ? 'mdi-chevron-up' : 'mdi-chevron-down'" size="18" />
        </button>
        <div v-if="opened" class="entity-picker__dropdown" :style="dropdownStyle">
            <div class="entity-picker__search">
                <v-icon icon="mdi-magnify" size="17" />
                <input ref="searchInput" v-model="query" role="combobox" :aria-expanded="opened" :aria-controls="`${id}-list`" :aria-activedescendant="matching.length ? `${id}-option-${active}` : undefined" aria-autocomplete="list" aria-label="Поиск покупателя по названию, реквизитам и контактам" placeholder="Название, ИНН, телефон, e-mail…" autocomplete="off" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="matching[active] && select(matching[active])" />
            </div>
            <div :id="`${id}-list`" class="entity-picker__list" role="listbox" :aria-labelledby="`${id}-label`">
                <div v-for="(entity, index) in matching" :id="`${id}-option-${index}`" :key="entity.id" :data-option-index="index" role="option" :aria-selected="String(entity.id) === String(modelValue)" class="entity-picker__option" :class="{ 'entity-picker__option--active': active === index }" @pointermove="active = index" @mousedown.prevent @click="select(entity)">
                    <div class="entity-picker__option-heading"><strong>{{ entity.name }}</strong><span>{{ entity.classification?.name || `ID ${entity.id}` }}</span><v-icon v-if="String(entity.id) === String(modelValue)" icon="mdi-check" size="15" /></div>
                    <div v-if="entity.full_name && entity.full_name !== entity.name" class="entity-picker__legal">{{ entity.full_name }}</div>
                    <div v-if="entityRequisites(entity).length" class="entity-picker__requisites">{{ entityRequisites(entity).join(' · ') }}</div>
                    <div v-if="entityContacts(entity).length" class="entity-picker__contacts">{{ entityContacts(entity).join(' · ') }}</div>
                    <div v-if="entity.legal_address" class="entity-picker__address">{{ entity.legal_address }}</div>
                </div>
                <div v-if="!matching.length" class="entity-picker__empty">Покупатель не найден. Попробуйте другой запрос.</div>
            </div>
            <div class="entity-picker__count">{{ matching.length }} из {{ entities.length }} покупателей</div>
        </div>
        <span v-if="errors.length" :id="`${id}-error`" class="entity-picker__error" role="alert">{{ errors.join(' ') }}</span>
    </div>
</template>

<style scoped>
.entity-picker { position: relative; min-width: 0; }
.entity-picker__label { display: block; margin-bottom: 5px; color: var(--details-muted, #6b7280); font-size: 10px; }
.entity-picker__trigger { display: flex; width: 100%; align-items: center; gap: 9px; padding: 8px 10px; border: 1px solid var(--details-border, #dce2ea); border-radius: 7px; background: var(--details-panel, #fff); color: var(--details-text, #253047); text-align: left; }
.entity-picker__trigger:focus-visible { outline: 2px solid var(--details-accent, #3674d9); outline-offset: 2px; }
.entity-picker__trigger--invalid { border-color: #d85e70; }
.entity-picker__avatar { display: grid; place-items: center; width: 30px; height: 30px; flex-shrink: 0; border-radius: 7px; background: color-mix(in srgb, var(--details-accent, #3674d9) 10%, transparent); color: var(--details-accent, #3674d9); }
.entity-picker__summary { flex: 1; display: grid; gap: 2px; min-width: 0; }
.entity-picker__summary strong { font-size: 12px; font-weight: 650; }
.entity-picker__summary small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 10px; color: var(--details-muted, #6b7280); }
.entity-picker__tag { max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 9px; color: var(--details-muted, #6b7280); }
.entity-picker__dropdown { display: flex; flex-direction: column; position: absolute; z-index: 20; left: 0; right: 0; top: calc(100% + 5px); overflow: hidden; border: 1px solid var(--details-border, #dce2ea); border-radius: 9px; background: var(--details-panel, #fff); color: var(--details-text, #253047); box-shadow: 0 12px 32px #142b4926; }
.entity-picker__search { display: flex; flex-shrink: 0; align-items: center; gap: 6px; padding: 9px 11px; border-bottom: 1px solid var(--details-border, #dce2ea); color: var(--details-muted, #6b7280); }
.entity-picker__search input { flex: 1; width: 0; min-width: 0; border: 0; outline: 0; background: transparent; color: var(--details-text, #253047); font-size: 12px; box-shadow: none; }
.entity-picker__list { min-height: 0; max-height: min(290px, 40dvh); overflow-y: auto; overscroll-behavior: contain; }
.entity-picker__option { display: grid; gap: 3px; padding: 9px 12px; cursor: pointer; border-bottom: 1px solid var(--details-border, #dce2ea); }
.entity-picker__option--active { background: color-mix(in srgb, var(--details-accent, #3674d9) 9%, transparent); }
.entity-picker__option[aria-selected="true"] { border-left: 3px solid var(--details-accent, #3674d9); padding-left: 9px; }
.entity-picker__option-heading { display: flex; align-items: center; gap: 7px; }
.entity-picker__option-heading strong { flex: 1; font-size: 12px; font-weight: 650; }
.entity-picker__option-heading > span, .entity-picker__legal, .entity-picker__address { color: var(--details-muted, #6b7280); font-size: 10px; }
.entity-picker__legal, .entity-picker__address, .entity-picker__contacts { overflow-wrap: anywhere; }
.entity-picker__requisites, .entity-picker__contacts { font-size: 10px; }
.entity-picker__count, .entity-picker__empty { flex-shrink: 0; padding: 8px 12px; color: var(--details-muted, #6b7280); font-size: 10px; }
.entity-picker__error { display: block; margin-top: 4px; color: #d85e70; font-size: 10px; }
@media (max-width: 480px) { .entity-picker__tag { display: none; } }
</style>
