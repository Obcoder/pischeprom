<script setup>
import { computed, reactive, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({ unit: { type: Object, required: true } })
const emit = defineEmits(['refresh'])
const dialog = ref(false)
const editingLead = ref(null)
const saving = ref(false)
const errors = ref({})
const feedback = ref('')
const form = reactive({ title: '', description: '', status: 'open' })
const statuses = [
    { value: 'open', title: 'Открыт' },
    { value: 'in_progress', title: 'В работе' },
    { value: 'won', title: 'Успешно' },
    { value: 'lost', title: 'Потерян' },
    { value: 'archived', title: 'В архиве' },
]
const leads = computed(() => {
    const unique = new Map()
    for (const entity of props.unit.entities || []) {
        for (const lead of entity.leads || []) unique.set(lead.id, { ...lead, entity: lead.entity || { id: entity.id, name: entity.name } })
    }
    for (const lead of props.unit.leads || []) unique.set(lead.id, { ...unique.get(lead.id), ...lead })
    return [...unique.values()].sort((a, b) => new Date(b.last_activity_at || b.created_at || 0) - new Date(a.last_activity_at || a.created_at || 0))
})
watch(() => props.unit.id, () => { dialog.value = false })
function statusLabel(value) { return statuses.find((status) => status.value === value)?.title || value || '—' }
function sourceLabel(value) { return ({ email: 'Почта', phone: 'Телефон', phone_call: 'Звонок', call: 'Звонок', manual: 'Вручную' })[value] || value || '—' }
function formatDate(value) {
    const date = new Date(value)
    return value && !Number.isNaN(date.getTime()) ? date.toLocaleDateString('ru-RU') : '—'
}
function editLead(lead) {
    editingLead.value = lead
    form.title = lead.title || ''
    form.description = lead.description || ''
    form.status = lead.status || 'open'
    feedback.value = ''
    errors.value = {}
    dialog.value = true
}
async function saveLead() {
    if (!editingLead.value?.id || saving.value) return
    saving.value = true
    feedback.value = ''
    errors.value = {}
    try {
        await axios.patch(`/api/leads/${editingLead.value.id}`, { title: form.title, description: form.description || null, status: form.status })
        dialog.value = false
        emit('refresh')
    } catch (error) {
        errors.value = error.response?.data?.errors || {}
        feedback.value = error.response?.data?.message || 'Не удалось сохранить лид.'
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="unit-leads">
        <p class="unit-leads__caption">Лиды Unit и связанных юридических лиц</p>
        <div v-if="leads.length" class="unit-leads__scroll">
            <article v-for="lead in leads" :key="lead.id" class="unit-leads__item">
                <div class="unit-leads__summary">
                    <button type="button" class="unit-leads__title" @click="editLead(lead)">{{ lead.title || `Лид #${lead.id}` }}</button>
                    <button
                        type="button"
                        class="unit-leads__status"
                        :class="{ 'is-closed': ['lost', 'archived'].includes(lead.status) }"
                        :aria-label="`Изменить статус лида «${lead.title || lead.id}»: ${statusLabel(lead.status)}`"
                        @click="editLead(lead)"
                    >{{ statusLabel(lead.status) }}</button>
                </div>
                <div class="unit-leads__entity">{{ lead.entity?.name || 'Unit' }}</div>
                <div class="unit-leads__details">
                    <span>{{ sourceLabel(lead.source) }}</span>
                    <time class="unit-leads__date" :datetime="lead.last_activity_at || lead.created_at || undefined">{{ formatDate(lead.last_activity_at || lead.created_at) }}</time>
                </div>
            </article>
        </div>
        <p v-else class="unit-leads__empty">Связанных лидов пока нет. Лид можно создать из письма или входящего звонка.</p>
        <v-dialog v-model="dialog" max-width="600" :persistent="saving">
            <v-card class="unit-lead-dialog" rounded="0" elevation="0" border>
                <v-card-title>Лид</v-card-title>
                <v-card-text>
                    <p v-if="feedback" role="alert" class="unit-leads__error">{{ feedback }}</p>
                    <form id="unit-lead-form" class="unit-lead-dialog__form" @submit.prevent="saveLead">
                        <v-text-field v-model="form.title" label="Название" variant="outlined" density="compact" hide-details="auto" :error-messages="errors.title || []" />
                        <v-select v-model="form.status" :items="statuses" label="Статус" variant="outlined" density="compact" hide-details="auto" :error-messages="errors.status || []" />
                        <v-textarea v-model="form.description" label="Описание" rows="3" variant="outlined" density="compact" hide-details="auto" :error-messages="errors.description || []" />
                    </form>
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn :disabled="saving" @click="dialog = false">Отмена</v-btn><v-btn form="unit-lead-form" type="submit" variant="flat" color="#352345" rounded="0" :loading="saving" :disabled="!form.title.trim()">Сохранить</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.unit-leads { display: flex; flex-direction: column; height: 100%; min-width: 0; min-height: 0; color: #222; font-size: 12px; }
.unit-leads__caption { flex-shrink: 0; padding: 10px 12px; color: #666; margin: 0; }
.unit-leads__scroll { flex: 1 1 auto; min-width: 0; min-height: 0; overflow: auto; overscroll-behavior: contain; }
.unit-leads__item { display: grid; gap: 5px; min-width: 0; padding: 10px 12px; border-top: 1px solid #e7e7e7; }
.unit-leads__summary { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 8px; }
.unit-leads__title { min-width: 0; color: #352345; text-align: left; font-weight: 600; overflow-wrap: anywhere; }
.unit-leads__title:hover { text-decoration: underline; }
.unit-leads__status { padding: 2px 5px; border: 1px solid #cfcbd2; color: #352345; font-size: 10px; white-space: nowrap; }
.unit-leads__status.is-closed { color: #651c2e; }
.unit-leads__entity { color: #777; font-size: 11px; overflow-wrap: anywhere; }
.unit-leads__details { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; color: #777; font-size: 11px; }
.unit-leads__details > span { min-width: 0; overflow-wrap: anywhere; }
.unit-leads__date { flex-shrink: 0; white-space: nowrap; }
.unit-leads button:focus-visible { outline: 2px solid #352345; outline-offset: 2px; }
.unit-leads__empty { padding: 12px; margin: 0; color: #777; }
.unit-lead-dialog__form { display: grid; gap: 14px; }
.unit-leads__error { color: #651c2e; margin-bottom: 12px; }
.unit-lead-dialog :deep(.v-field) { border-radius: 0; }
</style>
