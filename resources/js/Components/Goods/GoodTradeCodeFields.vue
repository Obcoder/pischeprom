<script setup>
import { computed, ref, watch } from 'vue'
import { goodTradeCodeFields, goodTradeCodeValues } from '@/utils/goodTradeCodes.js'
import GoodTradeCodesRecommend from '@/Components/Goods/GoodTradeCodesRecommend.vue'
import GoodTradeCodesRegistry from '@/Components/Goods/GoodTradeCodesRegistry.vue'

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    context: { type: Object, default: null },
    active: { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue'])
const expanded = ref(false)
const primaryFields = goodTradeCodeFields.filter(field => field.primary)
const otherFields = goodTradeCodeFields.filter(field => !field.primary)
const populatedFields = computed(() => goodTradeCodeFields.filter(field => props.modelValue[field.key]))
const otherCount = computed(() => otherFields.filter(field => props.modelValue[field.key]).length)
const recommendationDraft = computed(() => ({
    ...(props.context || props.modelValue),
    ...goodTradeCodeValues(props.modelValue),
}))

watch(() => props.errors, errors => {
    if (otherFields.some(field => errors[field.key]?.length)) expanded.value = true
}, { deep: true })

function updateField(key, value) {
    emit('update:modelValue', { ...props.modelValue, [key]: value?.trim() || null })
}

function applyRecommendations(patch) {
    if (props.disabled || props.readonly || !props.active) return
    const values = Object.fromEntries(goodTradeCodeFields.filter(field => Object.hasOwn(patch, field.key))
        .map(field => [field.key, patch[field.key]]))
    if (!Object.keys(values).length) return
    if (otherFields.some(field => Object.hasOwn(values, field.key))) expanded.value = true
    emit('update:modelValue', { ...props.modelValue, ...values })
}
</script>

<template>
    <div v-if="readonly" class="good-trade-codes-readonly">
        <div v-for="field in populatedFields" :key="field.key" class="good-trade-code">
            <span>{{ field.label }}</span>
            <strong>{{ modelValue[field.key] }}</strong>
        </div>
        <span v-if="!populatedFields.length" class="text-caption text-medium-emphasis">Торговые коды не указаны</span>
    </div>
    <div v-else class="good-trade-code-fields">
        <div class="d-flex align-center flex-wrap ga-1 mb-2">
            <span class="text-subtitle-2">Торговые коды</span>
            <span class="text-caption text-medium-emphasis">· необязательные</span>
        </div>
        <GoodTradeCodesRecommend
            :draft="recommendationDraft"
            :disabled="disabled"
            :active="active"
            @apply="applyRecommendations"
        />
        <v-row dense>
            <v-col v-for="field in primaryFields" :key="field.key" cols="12" sm="4">
                <v-text-field
                    :model-value="modelValue[field.key]"
                    :label="field.label"
                    :hint="field.hint"
                    persistent-hint
                    :error-messages="errors[field.key]"
                    :disabled="disabled"
                    density="compact"
                    variant="outlined"
                    hide-details="auto"
                    autocomplete="off"
                    clearable
                    @update:model-value="updateField(field.key, $event)"
                />
            </v-col>
        </v-row>
        <v-btn
            class="mt-1"
            size="small"
            variant="text"
            :append-icon="expanded ? 'mdi-chevron-up' : 'mdi-chevron-down'"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            Другие коды{{ otherCount ? ` · ${otherCount}` : '' }}
        </v-btn>
        <v-row v-if="expanded" dense class="mt-1">
            <v-col v-for="field in otherFields" :key="field.key" cols="12" sm="6" md="4">
                <v-text-field
                    :model-value="modelValue[field.key]"
                    :label="field.label"
                    :hint="field.hint"
                    persistent-hint
                    :error-messages="errors[field.key]"
                    :disabled="disabled"
                    density="compact"
                    variant="outlined"
                    hide-details="auto"
                    autocomplete="off"
                    clearable
                    @update:model-value="updateField(field.key, $event)"
                />
            </v-col>
        </v-row>
        <GoodTradeCodesRegistry :codes="modelValue" :active="active" :disabled="disabled" />
    </div>
</template>

<style scoped>
.good-trade-codes-readonly {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(125px, 1fr));
    gap: 8px 12px;
}
.good-trade-code span,
.good-trade-code strong {
    display: block;
    font-size: 0.75rem;
    overflow-wrap: anywhere;
}
.good-trade-code span {
    color: rgba(var(--v-theme-on-surface), 0.6);
}
</style>
