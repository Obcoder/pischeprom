<script setup>
import { computed, ref } from 'vue'
import { mailDatePresets, mailDateRange, mailDateRangeLabel, validMailDateRange } from './mailDateFilters.js'

const filters = defineModel('filters', { type: Object, required: true })
const props = defineProps({ timezone: { type: String, default: 'Europe/Moscow' } })
const rangeMenu = ref(false)
const dateFrom = ref('')
const dateTo = ref('')
const dateError = computed(() => {
    if (!dateFrom.value || !dateTo.value) return ''
    return validMailDateRange(dateFrom.value, dateTo.value) ? '' : 'Проверьте даты: начало должно быть не позже окончания.'
})
const hasRange = computed(() => Boolean(filters.value.date_from || filters.value.date_to))
const rangeLabel = computed(() => mailDateRangeLabel(filters.value.date_from, filters.value.date_to))

function presetActive(value) {
    if (filters.value.today) return value === 'today'
    const range = mailDateRange(value, props.timezone)
    return range.date_from === filters.value.date_from && range.date_to === filters.value.date_to
}

function selectPreset(value) {
    const range = presetActive(value) ? mailDateRange(null, props.timezone) : mailDateRange(value, props.timezone)
    filters.value = { ...filters.value, today: false, ...range }
}

function openRange(open) {
    if (!open) return
    const today = mailDateRange('today', props.timezone)
    dateFrom.value = filters.value.date_from || today.date_from
    dateTo.value = filters.value.date_to || today.date_to
}

function applyRange() {
    if (!validMailDateRange(dateFrom.value, dateTo.value)) return
    filters.value = { ...filters.value, today: false, date_from: dateFrom.value, date_to: dateTo.value }
    rangeMenu.value = false
}

function clearRange() {
    filters.value = { ...filters.value, today: false, date_from: null, date_to: null }
    rangeMenu.value = false
}
</script>

<template>
    <div class="mail-date-filter" role="group" aria-label="Период писем">
        <v-btn
            v-for="preset in mailDatePresets"
            :key="preset.value"
            :color="presetActive(preset.value) ? 'amber-lighten-2' : 'blue-grey-lighten-3'"
            :variant="presetActive(preset.value) ? 'tonal' : 'text'"
            :aria-pressed="presetActive(preset.value)"
            :title="preset.hint || preset.title"
            size="small"
            @click="selectPreset(preset.value)"
        >{{ preset.title }}</v-btn>

        <v-menu v-model="rangeMenu" :close-on-content-click="false" location="bottom end" @update:model-value="openRange">
            <template #activator="{ props: activatorProps }">
                <v-btn
                    v-bind="activatorProps"
                    size="small"
                    :color="hasRange ? 'amber-lighten-2' : 'blue-grey-lighten-3'"
                    variant="tonal"
                    prepend-icon="mdi-calendar-range"
                    :title="hasRange ? rangeLabel : 'Выбрать промежуток дат'"
                    aria-label="Выбрать промежуток дат"
                >Период</v-btn>
            </template>
            <v-card class="mail-date-range" width="310" theme="dark">
                <form @submit.prevent="applyRange">
                    <div class="mail-date-range__title">Период писем <span>включительно</span></div>
                    <div class="mail-date-range__fields">
                        <v-text-field v-model="dateFrom" type="date" label="С" density="compact" variant="outlined" hide-details :max="dateTo || undefined" />
                        <v-text-field v-model="dateTo" type="date" label="По" density="compact" variant="outlined" hide-details :min="dateFrom || undefined" />
                    </div>
                    <p v-if="dateError" class="mail-date-range__error" role="alert">{{ dateError }}</p>
                    <div class="mail-date-range__actions">
                        <v-btn size="small" variant="text" @click="clearRange">За всё время</v-btn>
                        <v-btn size="small" type="submit" color="blue" variant="tonal" :disabled="!validMailDateRange(dateFrom, dateTo)">Применить</v-btn>
                    </div>
                </form>
            </v-card>
        </v-menu>
    </div>
</template>

<style scoped>
.mail-date-filter { display: flex; align-items: center; gap: 2px; flex-shrink: 0; }
.mail-date-filter :deep(.v-btn) { min-width: 0; height: 28px; padding: 0 7px; font-size: 11px; letter-spacing: 0; text-transform: none; }
.mail-date-range { padding: 14px 12px 10px; border: 1px solid rgba(147, 197, 253, .25); }
.mail-date-range__title { margin-bottom: 16px; font-size: 13px; color: #dbeafe; }
.mail-date-range__title span { margin-left: 4px; font-size: 10px; color: #94a3b8; }
.mail-date-range__fields { display: flex; gap: 8px; }
.mail-date-range__fields :deep(.v-field__input) { font-size: 12px; padding-inline: 8px; }
.mail-date-range__actions { display: flex; justify-content: space-between; margin-top: 12px; }
.mail-date-range__actions :deep(.v-btn) { letter-spacing: 0; text-transform: none; }
.mail-date-range__error { margin-top: 8px; font-size: 11px; color: #fca5a5; }
</style>
