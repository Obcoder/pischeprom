<script setup>
import { computed } from 'vue'
import { massUnitFactor } from '../../utils/goodMeasurement.js'

const props = defineProps({
    modelValue: { type: Object, required: true },
    overview: { type: Object, default: null },
    options: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])
const measure = computed(() => (props.options.measures || []).find(item => String(item.id) === String(props.modelValue.measure_id)))
const massFactor = computed(() => massUnitFactor(measure.value?.name))
const unitWeight = computed(() => massFactor.value ?? (Number(props.modelValue.unit_weight_kg) > 0 ? Number(props.modelValue.unit_weight_kg) : null))
const legacyPrices = computed(() => props.overview?.id && !props.overview.measure_id && !props.overview.measurement?.measure_id && props.overview.counts?.prices > 0)
const example = computed(() => {
    if (!measure.value) return 'Выберите единицу количества и цены для заказов, продаж, закупок и склада.'
    const weight = unitWeight.value == null ? '' : ` = ${(10 * unitWeight.value).toLocaleString('ru-RU', { maximumFractionDigits: 6 })} кг`
    return `Количество 10 означает 10 ${measure.value.name}${weight}. Цена указывается за 1 ${measure.value.name}.`
})
function update(key, value) {
    if (props.disabled) return
    const next = { ...props.modelValue, [key]: value }
    if (key === 'measure_id' && String(value) !== String(props.modelValue.measure_id)) next.unit_weight_kg = null
    emit('update:modelValue', next)
}
</script>

<template>
    <section class="good-measurement" aria-label="Единицы складского учёта">
        <h3><v-icon icon="mdi-scale-balance" size="18" /> Единица учёта и упаковка</h3>
        <div class="good-measurement__fields">
            <v-select :model-value="modelValue.measure_id" :items="options.measures || []" item-title="name" item-value="id" label="Единица учёта товара" variant="outlined" density="compact" :disabled="disabled" :error-messages="errors.measure_id" @update:model-value="update('measure_id', $event)" />
            <v-text-field v-if="measure && massFactor === null" :model-value="modelValue.unit_weight_kg" :label="`Масса 1 ${measure.name}, кг`" hint="Для расчёта веса заказа, если масса известна." persistent-hint type="number" step="0.000001" min="0.000001" variant="outlined" density="compact" :disabled="disabled" :error-messages="errors.unit_weight_kg" @update:model-value="update('unit_weight_kg', $event)" />
            <v-text-field :model-value="modelValue.denominator" label="Масса упаковки, кг (справочно)" hint="Фасовка не меняет единицу количества и цены." persistent-hint type="number" step="0.001" min="0" variant="outlined" density="compact" :disabled="disabled" :error-messages="errors.denominator" @update:model-value="update('denominator', $event)" />
        </div>
        <p role="status">{{ example }}</p>
        <v-select v-if="legacyPrices" :model-value="modelValue.existing_price_basis || 'selected_unit'" :items="[{ title: 'За выбранную единицу — сохранить суммы', value: 'selected_unit' }, { title: 'За кг — пересчитать по массе', value: 'kg' }]" label="Единица ранее сохранённых цен" variant="outlined" density="compact" :disabled="disabled" :error-messages="errors.existing_price_basis" @update:model-value="update('existing_price_basis', $event)" />
        <p class="good-measurement__note">Изменения единицы и упаковки сохраняются кнопкой «Сохранить» в карточке. При смене единицы действующие цены пересчитываются по массе. Ненулевые складские остатки в другой единице нужно сначала скорректировать в исходных документах; автоматически они не пересчитываются.</p>
    </section>
</template>

<style scoped>
.good-measurement { padding: 16px; border: 1px solid #e7e0ed; border-radius: 10px; background: #faf8fc; }
.good-measurement h3 { display: flex; gap: 7px; align-items: center; margin-bottom: 16px; font-size: 13px; font-weight: 650; color: #584566; }
.good-measurement__fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
.good-measurement p { margin: 6px 0 10px; font-size: 12px; line-height: 1.5; color: #655574; }
.good-measurement p.good-measurement__note { margin-bottom: 0; color: #86788f; font-size: 11px; }
</style>
