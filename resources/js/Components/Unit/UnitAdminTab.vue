<script setup>
import axios from 'axios'
import { computed, reactive, ref } from 'vue'

const props = defineProps({
    unit: { type: Object, required: true },
    dict: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['refresh'])

const industryDialog = ref(false)
const attachDialog = ref(false)
const savingIndustry = ref(false)
const deletingIndustryId = ref(null)
const attachingIndustry = ref(false)
const selectedIndustryId = ref(null)
const industryErrors = ref({})
const feedback = ref('')

const industryForm = reactive({
    id: null,
    code: '',
    title: '',
})

const industries = computed(() => props.unit.industries || [])
const availableIndustries = computed(() => props.dict.industries || [])

function resetIndustryForm(industry = null) {
    industryErrors.value = {}
    industryForm.id = industry?.id || null
    industryForm.code = industry?.code || ''
    industryForm.title = industry?.title || ''
}

function openIndustryDialog(industry = null) {
    feedback.value = ''
    resetIndustryForm(industry)
    industryDialog.value = true
}

function openAttachDialog() {
    feedback.value = ''
    selectedIndustryId.value = null
    attachDialog.value = true
}

async function saveIndustry() {
    savingIndustry.value = true
    industryErrors.value = {}
    feedback.value = ''

    try {
        if (industryForm.id) {
            await axios.put(`/api/industries/${industryForm.id}`, {
                code: industryForm.code,
                title: industryForm.title,
            })
            feedback.value = 'ОКВЭД обновлён'
        } else {
            const { data } = await axios.post('/api/industries', {
                code: industryForm.code,
                title: industryForm.title,
            })
            selectedIndustryId.value = data.id
            feedback.value = 'ОКВЭД создан'
        }

        industryDialog.value = false
        emit('refresh')
    } catch (error) {
        industryErrors.value = error.response?.data?.errors || {}
        feedback.value = error.response?.data?.message || 'Не удалось сохранить ОКВЭД'
        console.error('industry save error:', error.response?.data || error)
    } finally {
        savingIndustry.value = false
    }
}

async function deleteIndustry(industry) {
    if (!industry?.id || !window.confirm(`Удалить ОКВЭД ${industry.code}?`)) return

    deletingIndustryId.value = industry.id
    feedback.value = ''

    try {
        await axios.delete(`/api/industries/${industry.id}`)
        industryDialog.value = false
        resetIndustryForm()
        feedback.value = 'ОКВЭД удалён'
        emit('refresh')
    } catch (error) {
        feedback.value = error.response?.data?.message || 'Не удалось удалить ОКВЭД'
    } finally {
        deletingIndustryId.value = null
    }
}

async function attachIndustry(isPrimary = false) {
    if (!selectedIndustryId.value) return

    attachingIndustry.value = true
    feedback.value = ''

    try {
        await axios.post(`/api/units/${props.unit.id}/industries`, {
            industry_id: selectedIndustryId.value,
            is_primary: isPrimary,
        })
        selectedIndustryId.value = null
        attachDialog.value = false
        feedback.value = 'ОКВЭД привязан к Unit'
        emit('refresh')
    } catch (error) {
        feedback.value = error.response?.data?.message || 'Не удалось привязать ОКВЭД'
    } finally {
        attachingIndustry.value = false
    }
}

async function detachIndustry(industry) {
    if (!industry?.id) return

    feedback.value = ''

    try {
        await axios.delete(`/api/units/${props.unit.id}/industries/${industry.id}`)
        feedback.value = 'ОКВЭД отвязан от Unit'
        emit('refresh')
    } catch (error) {
        feedback.value = error.response?.data?.message || 'Не удалось отвязать ОКВЭД'
    }
}
</script>

<template>
    <div class="unit-okved-tab">
        <div class="unit-okved-tab__toolbar">
            <div class="unit-okved-tab__title">
                <span>ОКВЭД</span>
                <strong>{{ industries.length }}</strong>
            </div>

            <div class="unit-okved-tab__actions">
                <button type="button" @click="openIndustryDialog()">Создать ОКВЭД</button>
                <button type="button" @click="openAttachDialog">Привязать ОКВЭД</button>
            </div>
        </div>

        <div v-if="feedback" class="unit-okved-tab__feedback">
            {{ feedback }}
        </div>

        <section class="unit-okved-tab__sheet">
            <div class="unit-okved-table">
                <div class="unit-okved-table__row unit-okved-table__row--head">
                    <span>Код</span>
                    <span>Название</span>
                    <span>Статус</span>
                    <span>Действия</span>
                </div>

                <div v-for="industry in industries" :key="industry.id" class="unit-okved-table__row">
                    <span class="unit-okved-table__code">{{ industry.code }}</span>
                    <span>{{ industry.title }}</span>
                    <span>{{ industry.pivot?.is_primary ? 'Основной' : 'Дополнительный' }}</span>
                    <span class="unit-okved-table__actions">
                        <button type="button" @click="openIndustryDialog(industry)">Изменить</button>
                        <button type="button" class="danger" @click="detachIndustry(industry)">Отвязать</button>
                    </span>
                </div>

                <div v-if="!industries.length" class="unit-okved-table__empty">
                    Нет привязанных ОКВЭД
                </div>
            </div>
        </section>

        <v-dialog v-model="industryDialog" max-width="680">
            <v-card rounded="0">
                <v-card-title>{{ industryForm.id ? 'Редактировать ОКВЭД' : 'Создать ОКВЭД' }}</v-card-title>
                <v-card-text>
                    <div class="unit-okved-tab__fields-2">
                        <v-text-field v-model="industryForm.code" label="Code" variant="outlined" density="compact" :error-messages="industryErrors.code || []" />
                        <v-text-field v-model="industryForm.title" label="Title" variant="outlined" density="compact" :error-messages="industryErrors.title || []" />
                    </div>
                </v-card-text>
                <v-card-actions class="justify-space-between">
                    <v-btn
                        v-if="industryForm.id"
                        variant="text"
                        color="error"
                        :loading="deletingIndustryId === industryForm.id"
                        @click="deleteIndustry({ id: industryForm.id, code: industryForm.code })"
                    >
                        Удалить
                    </v-btn>
                    <v-spacer />
                    <v-btn variant="text" @click="industryDialog = false">Отмена</v-btn>
                    <v-btn color="#352345" :disabled="!industryForm.code || !industryForm.title" :loading="savingIndustry" @click="saveIndustry">Сохранить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="attachDialog" max-width="680">
            <v-card rounded="0">
                <v-card-title>Привязать ОКВЭД</v-card-title>
                <v-card-text>
                    <v-autocomplete
                        v-model="selectedIndustryId"
                        :items="availableIndustries"
                        item-title="code"
                        item-value="id"
                        label="ОКВЭД"
                        variant="outlined"
                        density="compact"
                        hide-details
                    >
                        <template #item="{ props: itemProps, item }">
                            <v-list-item v-bind="itemProps" :subtitle="item.raw.title" />
                        </template>
                    </v-autocomplete>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="attachDialog = false">Отмена</v-btn>
                    <v-btn color="#352345" :disabled="!selectedIndustryId || attachingIndustry" :loading="attachingIndustry" @click="attachIndustry(false)">Привязать</v-btn>
                    <v-btn color="#352345" :disabled="!selectedIndustryId || attachingIndustry" :loading="attachingIndustry" @click="attachIndustry(true)">Привязать как основной</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.unit-okved-tab {
    display: grid;
    gap: 8px;
    color: #242127;
}

.unit-okved-tab__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.unit-okved-tab__title,
.unit-okved-tab__actions,
.unit-okved-table__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    align-items: center;
}

.unit-okved-tab__title span {
    color: #352345;
    font-size: 0.72rem;
    font-weight: 650;
    letter-spacing: 0.08em;
    text-transform: none;
}

.unit-okved-tab__title strong {
    min-width: 24px;
    padding: 2px 7px;
    border-radius: 0;
    background: #352345;
    color: #ffffff;
    font-size: 0.68rem;
    line-height: 1.2;
    text-align: center;
}

.unit-okved-tab button {
    border: 1px solid #d9d7dc;
    border-radius: 0;
    background: #352345;
    color: #ffffff;
    cursor: pointer;
    font-size: 0.62rem;
    font-weight: 650;
    letter-spacing: 0.06em;
    padding: 5px 8px;
    text-transform: none;
}

.unit-okved-tab button.danger,
.unit-okved-table__actions button.danger {
    background: #651c2e;
}

.unit-okved-tab button:disabled {
    cursor: wait;
    opacity: 0.5;
}

.unit-okved-tab__feedback {
    padding: 6px 8px;
    border: 1px solid #d9d7dc;
    border-radius: 0;
    background: #f5f4f6;
    color: #352345;
    font-size: 0.68rem;
    font-weight: 800;
}

.unit-okved-tab__sheet {
    width: 100%;
    border: 1px solid #d9d7dc;
    border-radius: 0;
    background: #ffffff;
    overflow: hidden;
}

.unit-okved-table {
    display: grid;
    width: 100%;
    max-height: 320px;
    overflow: auto;
}

.unit-okved-table__row {
    display: grid;
    grid-template-columns: 92px minmax(0, 1fr) 86px 142px;
    min-height: 30px;
    border-bottom: 1px solid #d9d7dc;
}

.unit-okved-table__row > span {
    overflow: hidden;
    padding: 5px 7px;
    border-right: 1px solid #d9d7dc;
    font-size: 0.68rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unit-okved-table__row--head > span {
    background: #f3f2f4;
    color: #352345;
    font-weight: 650;
    letter-spacing: 0.06em;
    text-transform: none;
}

.unit-okved-table__code {
    color: #352345;
    font-weight: 650;
}

.unit-okved-table__actions button {
    border-radius: 0;
    font-size: 0.54rem;
    padding: 3px 5px;
}

.unit-okved-table__empty {
    padding: 12px;
    color: #77727b;
    font-size: 0.72rem;
}

.unit-okved-tab__fields-2 {
    display: grid;
    grid-template-columns: 120px minmax(0, 1fr);
    gap: 8px;
}

@media (max-width: 980px) {
    .unit-okved-tab__toolbar,
    .unit-okved-tab__fields-2 {
        align-items: stretch;
        flex-direction: column;
        grid-template-columns: 1fr;
    }

    .unit-okved-table__row {
        grid-template-columns: 74px minmax(0, 1fr) 74px 112px;
    }
}
</style>
