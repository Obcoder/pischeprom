<script setup>
import { computed } from 'vue'
import { catalogBranchSlots } from './branchSlots.js'

const props = defineProps({
    nodes: { type: Array, default: () => [] },
    parentId: { type: [Number, String], default: null },
    nodeId: { type: [Number, String], default: null },
    modelValue: { type: [Number, String], default: null },
    disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])
const branch = computed(() => catalogBranchSlots(props.nodes, props.parentId, props.nodeId, props.modelValue))
const slotLabel = slot => `Слот ${slot.order}${slot.selected ? ', выбран' : ''}: ${slot.names.length ? `занят — ${slot.names.join(', ')}` : 'свободен'}`
const gapLabel = gap => `Слоты ${gap.from}–${gap.to}: занято ${gap.occupied}, свободно ${gap.free}`
function select(order) {
    if (!props.disabled) emit('update:modelValue', order)
}
</script>

<template>
    <div class="catalog-branch-slots" role="group" aria-label="Занятые и свободные слоты в ветке">
        <div class="catalog-branch-slots__heading">
            <span>Слоты в этой ветке</span>
            <span>Занято: {{ branch.occupiedCount }}</span>
        </div>
        <div class="catalog-branch-slots__legend" aria-hidden="true">
            <span><i class="catalog-branch-slots__key catalog-branch-slots__key--occupied">●</i> Занят</span>
            <span><i class="catalog-branch-slots__key">○</i> Свободен</span>
            <span><i class="catalog-branch-slots__key catalog-branch-slots__key--selected">✓</i> Выбран</span>
        </div>
        <div class="catalog-branch-slots__list">
            <template v-for="item in branch.items" :key="item.type === 'slot' ? `slot-${item.order}` : `gap-${item.from}`">
                <button v-if="item.type === 'slot'" type="button" class="catalog-branch-slots__slot"
                    :class="{ 'catalog-branch-slots__slot--occupied': item.names.length, 'catalog-branch-slots__slot--selected': item.selected }"
                    :disabled="disabled" :aria-pressed="item.selected" :aria-label="slotLabel(item)" :title="slotLabel(item)"
                    :data-order="item.order" @click="select(item.order)">
                    <span>{{ item.order }}</span><small aria-hidden="true">{{ item.selected ? '✓' : item.names.length ? '●' : '○' }}</small>
                </button>
                <span v-else class="catalog-branch-slots__gap" tabindex="0" :title="gapLabel(item)" :aria-label="gapLabel(item)">{{ item.from }}–{{ item.to }}</span>
            </template>
        </div>
        <p v-if="branch.selectedNames.length" class="catalog-branch-slots__notice" role="status">
            Слот {{ branch.selectedOrder }} уже используется: {{ branch.selectedNames.join(', ') }}.
            При одинаковом порядке записи сортируются по названию.
        </p>
        <p v-else class="catalog-branch-slots__hint">Нажмите на свободный слот, чтобы выбрать порядок.</p>
        <p v-if="branch.condensed" class="catalog-branch-slots__hint">Диапазоны свёрнуты. Любой номер можно ввести в поле выше.</p>
    </div>
</template>

<style scoped>
.catalog-branch-slots { margin: -4px 0 20px; padding: 12px; border: 1px solid #e6e0eb; border-radius: 9px; background: #faf8fc; color: #74677f; font-size: 11px; }
.catalog-branch-slots__heading { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 12px; margin-bottom: 8px; }
.catalog-branch-slots__heading > :first-child { color: #4d4058; font-weight: 600; }
.catalog-branch-slots__legend { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-bottom: 10px; }
.catalog-branch-slots__legend > span { display: inline-flex; align-items: center; gap: 5px; }
.catalog-branch-slots__key { display: inline-grid; place-items: center; width: 16px; height: 16px; border: 1px dashed #baadca; border-radius: 4px; background: white; font-style: normal; font-size: 10px; }
.catalog-branch-slots__key--occupied { border-style: solid; background: #e9e3ef; }
.catalog-branch-slots__key--selected { border-style: solid; border-color: #604074; background: #604074; color: white; }
.catalog-branch-slots__list { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.catalog-branch-slots__slot { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-width: 40px; min-height: 34px; padding: 5px 8px; border: 1px dashed #baadca; border-radius: 6px; background: white; color: #685775; font: inherit; font-variant-numeric: tabular-nums; cursor: pointer; }
.catalog-branch-slots__slot small { font-size: 9px; }
.catalog-branch-slots__slot--occupied { border-style: solid; background: #e9e3ef; }
.catalog-branch-slots__slot--selected { border-style: solid; border-color: #604074; background: #604074; color: white; font-weight: 600; }
.catalog-branch-slots__slot:hover:not(:disabled) { box-shadow: 0 0 0 2px #ddd1e7; }
.catalog-branch-slots__slot:focus-visible, .catalog-branch-slots__gap:focus-visible { outline: 2px solid #604074; outline-offset: 2px; }
.catalog-branch-slots__slot:disabled { cursor: default; opacity: .65; }
.catalog-branch-slots__gap { padding: 4px 2px; font-size: 10px; overflow-wrap: anywhere; }
.catalog-branch-slots__hint, .catalog-branch-slots__notice { margin: 9px 0 0; line-height: 1.5; overflow-wrap: anywhere; }
.catalog-branch-slots__notice { color: #77552f; }
</style>
