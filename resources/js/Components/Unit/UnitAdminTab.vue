<script setup>
import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    unit: { type: Object, required: true },
    dict: { type: Object, default: () => ({}) },
    action: { type: Object, default: null },
})

const emit = defineEmits(['refresh'])

const mode = ref('unit')
const savingUnit = ref(false)
const deletingUnit = ref(false)
const savingIndustry = ref(false)
const deletingIndustryId = ref(null)
const attachingIndustry = ref(false)
const selectedIndustryId = ref(null)
const industryErrors = ref({})
const unitErrors = ref({})
const feedback = ref('')

const unitForm = reactive({
    name: '',
    is_customer: false,
    is_supplier: false,
})

const industryForm = reactive({
    id: null,
    code: '',
    title: '',
})

const industries = computed(() => props.unit.industries || [])
const availableIndustries = computed(() => props.dict.industries || [])

watch(() => props.unit, fillUnitForm, { immediate: true })
watch(() => props.action, (value) => {
    if (!value?.action) return

    mode.value = value.action === 'industry-create' ? 'industry' : value.action

    if (value.action === 'industry-create') {
        resetIndustryForm()
    }
}, { deep: true })

function fillUnitForm() {
    unitForm.name = props.unit?.name || ''
    unitForm.is_customer = Boolean(props.unit?.is_customer)
    unitForm.is_supplier = Boolean(props.unit?.is_supplier)
}

function setMode(nextMode) {
    mode.value = nextMode
    feedback.value = ''

    if (nextMode === 'industry') {
        resetIndustryForm()
    }
}

async function saveUnit() {
    savingUnit.value = true
    unitErrors.value = {}
    feedback.value = ''

    try {
        await axios.put(`/api/units/${props.unit.id}`, unitForm)
        feedback.value = 'Unit сохранён'
        emit('refresh')
    } catch (error) {
        unitErrors.value = error.response?.data?.errors || {}
        feedback.value = error.response?.data?.message || 'Не удалось сохранить Unit'
        console.error('unit save error:', error.response?.data || error)
    } finally {
        savingUnit.value = false
    }
}

async function deleteUnit() {
    if (!window.confirm(`Удалить Unit "${props.unit.name}"?`)) return

    deletingUnit.value = true

    try {
        await axios.delete(`/api/units/${props.unit.id}`)
        router.visit('/Ameise/units')
    } finally {
        deletingUnit.value = false
    }
}

function resetIndustryForm(industry = null) {
    industryErrors.value = {}
    industryForm.id = industry?.id || null
    industryForm.code = industry?.code || ''
    industryForm.title = industry?.title || ''
}

function editIndustry(industry) {
    resetIndustryForm(industry)
    mode.value = 'industry'
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
    <div class="unit-admin-tab">
        <div class="unit-admin-tab__switcher">
            <button type="button" :class="{ active: mode === 'unit' }" @click="setMode('unit')">Unit CRUD</button>
            <button type="button" :class="{ active: mode === 'industry' }" @click="setMode('industry')">ОКВЭД CRUD</button>
            <button type="button" :class="{ active: mode === 'attach' }" @click="setMode('attach')">Привязка ОКВЭД</button>
            <span v-if="feedback">{{ feedback }}</span>
        </div>

        <div class="unit-admin-tab__layout">
            <section class="unit-admin-tab__sheet">
                <div class="unit-admin-tab__sheet-head">
                    <span>Присоединённые ОКВЭД</span>
                    <strong>{{ industries.length }}</strong>
                </div>

                <div class="unit-admin-table">
                    <div class="unit-admin-table__row unit-admin-table__row--head">
                        <span>Код</span>
                        <span>Название</span>
                        <span>Статус</span>
                        <span>Действия</span>
                    </div>

                    <div v-for="industry in industries" :key="industry.id" class="unit-admin-table__row">
                        <span class="unit-admin-table__code">{{ industry.code }}</span>
                        <span>{{ industry.title }}</span>
                        <span>{{ industry.pivot?.is_primary ? 'primary' : 'linked' }}</span>
                        <span class="unit-admin-table__actions">
                            <button type="button" @click="editIndustry(industry)">Edit</button>
                            <button type="button" class="danger" @click="detachIndustry(industry)">Detach</button>
                        </span>
                    </div>

                    <div v-if="!industries.length" class="unit-admin-table__empty">Нет привязанных ОКВЭД</div>
                </div>
            </section>

            <section class="unit-admin-tab__editor">
                <template v-if="mode === 'unit'">
                    <div class="unit-admin-tab__title">Редактировать Unit</div>
                    <v-text-field v-model="unitForm.name" label="Name" variant="solo-filled" density="compact" :error-messages="unitErrors.name || []" />
                    <div class="unit-admin-tab__checks">
                        <v-switch v-model="unitForm.is_customer" label="Customer" color="teal" hide-details density="compact" />
                        <v-switch v-model="unitForm.is_supplier" label="Supplier" color="teal" hide-details density="compact" />
                    </div>
                    <div class="unit-admin-tab__buttons">
                        <button type="button" :disabled="!unitForm.name || savingUnit" @click="saveUnit">Save Unit</button>
                        <button type="button" class="danger" :disabled="deletingUnit" @click="deleteUnit">Delete Unit</button>
                    </div>
                </template>

                <template v-else-if="mode === 'industry'">
                    <div class="unit-admin-tab__title">{{ industryForm.id ? 'Редактировать ОКВЭД' : 'Создать ОКВЭД' }}</div>
                    <div class="unit-admin-tab__fields-2">
                        <v-text-field v-model="industryForm.code" label="Code" variant="solo-filled" density="compact" :error-messages="industryErrors.code || []" />
                        <v-text-field v-model="industryForm.title" label="Title" variant="solo-filled" density="compact" :error-messages="industryErrors.title || []" />
                    </div>
                    <div class="unit-admin-tab__buttons">
                        <button type="button" :disabled="!industryForm.code || !industryForm.title || savingIndustry" @click="saveIndustry">Save ОКВЭД</button>
                        <button type="button" @click="resetIndustryForm()">New</button>
                        <button v-if="industryForm.id" type="button" class="danger" :disabled="deletingIndustryId === industryForm.id" @click="deleteIndustry({ id: industryForm.id, code: industryForm.code })">Delete</button>
                    </div>
                </template>

                <template v-else>
                    <div class="unit-admin-tab__title">Привязать ОКВЭД к Unit</div>
                    <v-autocomplete
                        v-model="selectedIndustryId"
                        :items="availableIndustries"
                        item-title="code"
                        item-value="id"
                        label="ОКВЭД"
                        variant="solo-filled"
                        density="compact"
                        hide-details
                    >
                        <template #item="{ props: itemProps, item }">
                            <v-list-item v-bind="itemProps" :subtitle="item.raw.title" />
                        </template>
                    </v-autocomplete>
                    <div class="unit-admin-tab__buttons">
                        <button type="button" :disabled="!selectedIndustryId || attachingIndustry" @click="attachIndustry(false)">Attach</button>
                        <button type="button" :disabled="!selectedIndustryId || attachingIndustry" @click="attachIndustry(true)">Attach primary</button>
                    </div>
                </template>
            </section>
        </div>
    </div>
</template>

<style scoped>
.unit-admin-tab {
    display: grid;
    gap: 8px;
    color: #153b3a;
}

.unit-admin-tab__switcher,
.unit-admin-tab__buttons,
.unit-admin-table__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    align-items: center;
}

.unit-admin-tab button {
    border: 1px solid rgba(0, 128, 128, 0.24);
    border-radius: 8px;
    background: #00796b;
    color: #f1fffc;
    cursor: pointer;
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    padding: 5px 8px;
    text-transform: uppercase;
}

.unit-admin-tab button.active {
    background: #003f3c;
}

.unit-admin-tab button.danger,
.unit-admin-table__actions button.danger {
    background: #95133d;
}

.unit-admin-tab button:disabled {
    cursor: wait;
    opacity: 0.5;
}

.unit-admin-tab__switcher span {
    color: #00796b;
    font-size: 0.68rem;
    font-weight: 800;
}

.unit-admin-tab__layout {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(240px, 0.8fr);
    gap: 8px;
}

.unit-admin-tab__sheet,
.unit-admin-tab__editor {
    border: 1px solid rgba(0, 128, 128, 0.18);
    border-radius: 12px;
    background: #f7fffd;
    overflow: hidden;
}

.unit-admin-tab__editor {
    display: grid;
    gap: 7px;
    align-content: start;
    padding: 8px;
}

.unit-admin-tab__sheet-head {
    display: flex;
    justify-content: space-between;
    padding: 5px 7px;
    background: #00695c;
    color: #ecfffb;
    font-size: 0.68rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.unit-admin-table {
    display: grid;
    max-height: 260px;
    overflow: auto;
}

.unit-admin-table__row {
    display: grid;
    grid-template-columns: 74px minmax(0, 1fr) 62px 112px;
    min-height: 30px;
    border-bottom: 1px solid rgba(0, 128, 128, 0.14);
}

.unit-admin-table__row > span {
    overflow: hidden;
    padding: 5px 6px;
    border-right: 1px solid rgba(0, 128, 128, 0.12);
    font-size: 0.68rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unit-admin-table__row--head > span {
    background: #d9f5ef;
    color: #00524b;
    font-weight: 950;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.unit-admin-table__code {
    color: #004d46;
    font-weight: 950;
}

.unit-admin-table__actions button {
    border-radius: 6px;
    font-size: 0.54rem;
    padding: 3px 5px;
}

.unit-admin-table__empty {
    padding: 10px;
    color: #607d7a;
    font-size: 0.72rem;
}

.unit-admin-tab__title {
    color: #004d46;
    font-size: 0.72rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.unit-admin-tab__checks,
.unit-admin-tab__fields-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
}

@media (max-width: 980px) {
    .unit-admin-tab__layout,
    .unit-admin-tab__checks,
    .unit-admin-tab__fields-2 {
        grid-template-columns: 1fr;
    }
}
</style>
