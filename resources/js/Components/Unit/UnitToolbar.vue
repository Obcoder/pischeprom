<script setup>
import axios from 'axios'
import { reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    unit: { type: Object, required: true },
    dict: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['refresh'])

const unitDialog = ref(false)
const industryDialog = ref(false)
const savingUnit = ref(false)
const deletingUnit = ref(false)
const savingIndustry = ref(false)
const deletingIndustryId = ref(null)
const attachingIndustry = ref(false)
const selectedIndustryId = ref(null)
const industryErrors = ref({})
const unitErrors = ref({})

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

watch(() => props.unit, fillUnitForm, { immediate: true })

function fillUnitForm() {
    unitForm.name = props.unit?.name || ''
    unitForm.is_customer = Boolean(props.unit?.is_customer)
    unitForm.is_supplier = Boolean(props.unit?.is_supplier)
}

function openUnitDialog() {
    fillUnitForm()
    unitDialog.value = true
}

async function saveUnit() {
    savingUnit.value = true
    unitErrors.value = {}

    try {
        await axios.put(`/api/units/${props.unit.id}`, unitForm)
        unitDialog.value = false
        emit('refresh')
    } catch (error) {
        unitErrors.value = error.response?.data?.errors || {}
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

function openIndustryDialog(industry = null) {
    resetIndustryForm(industry)
    industryDialog.value = true
}

async function saveIndustry() {
    savingIndustry.value = true
    industryErrors.value = {}

    try {
        if (industryForm.id) {
            await axios.put(`/api/industries/${industryForm.id}`, {
                code: industryForm.code,
                title: industryForm.title,
            })
        } else {
            await axios.post('/api/industries', {
                code: industryForm.code,
                title: industryForm.title,
            })
        }

        industryDialog.value = false
        emit('refresh')
    } catch (error) {
        industryErrors.value = error.response?.data?.errors || {}
        console.error('industry save error:', error.response?.data || error)
    } finally {
        savingIndustry.value = false
    }
}

async function deleteIndustry(industry) {
    if (!industry?.id || !window.confirm(`Удалить ОКВЭД ${industry.code}?`)) return

    deletingIndustryId.value = industry.id

    try {
        await axios.delete(`/api/industries/${industry.id}`)
        emit('refresh')
    } finally {
        deletingIndustryId.value = null
    }
}

async function attachIndustry(isPrimary = false) {
    if (!selectedIndustryId.value) return

    attachingIndustry.value = true

    try {
        await axios.post(`/api/units/${props.unit.id}/industries`, {
            industry_id: selectedIndustryId.value,
            is_primary: isPrimary,
        })
        selectedIndustryId.value = null
        emit('refresh')
    } finally {
        attachingIndustry.value = false
    }
}

async function detachIndustry(industry) {
    if (!industry?.id) return

    await axios.delete(`/api/units/${props.unit.id}/industries/${industry.id}`)
    emit('refresh')
}
</script>

<template>
    <section class="unit-toolbar">
        <div class="unit-toolbar__main">
            <div class="unit-toolbar__eyebrow">Unit toolbar</div>
            <strong>{{ unit.name }}</strong>
        </div>

        <div class="unit-toolbar__industries">
            <span
                v-for="industry in (unit.industries || [])"
                :key="industry.id"
                class="unit-toolbar__industry"
                :class="{ 'is-primary': industry.pivot?.is_primary }"
            >
                <b>{{ industry.code }}</b>
                <small>{{ industry.title }}</small>
                <button type="button" @click="openIndustryDialog(industry)">edit</button>
                <button type="button" @click="detachIndustry(industry)">x</button>
            </span>
        </div>

        <div class="unit-toolbar__actions">
            <v-autocomplete
                v-model="selectedIndustryId"
                :items="dict.industries || []"
                item-title="code"
                item-value="id"
                label="ОКВЭД"
                variant="solo-filled"
                density="compact"
                hide-details
                class="unit-toolbar__industry-select"
            >
                <template #item="{ props: itemProps, item }">
                    <v-list-item v-bind="itemProps" :subtitle="item.raw.title" />
                </template>
            </v-autocomplete>

            <button type="button" :disabled="!selectedIndustryId || attachingIndustry" @click="attachIndustry(false)">Attach</button>
            <button type="button" :disabled="!selectedIndustryId || attachingIndustry" @click="attachIndustry(true)">Primary</button>
            <button type="button" @click="openIndustryDialog()">New ОКВЭД</button>
            <button type="button" @click="openUnitDialog">Edit Unit</button>
            <button type="button" class="is-danger" :disabled="deletingUnit" @click="deleteUnit">Delete</button>
        </div>

        <v-dialog v-model="unitDialog" max-width="620">
            <v-card rounded="xl">
                <v-card-title>Edit Unit</v-card-title>
                <v-card-text>
                    <v-text-field v-model="unitForm.name" label="Name" variant="outlined" density="compact" :error-messages="unitErrors.name || []" />
                    <div class="unit-toolbar__switches">
                        <v-switch v-model="unitForm.is_customer" label="Customer" color="teal" hide-details density="compact" />
                        <v-switch v-model="unitForm.is_supplier" label="Supplier" color="teal" hide-details density="compact" />
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="unitDialog = false">Cancel</v-btn>
                    <v-btn color="teal-darken-3" :disabled="!unitForm.name" :loading="savingUnit" @click="saveUnit">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="industryDialog" max-width="680">
            <v-card rounded="xl">
                <v-card-title>{{ industryForm.id ? 'Edit ОКВЭД' : 'New ОКВЭД' }}</v-card-title>
                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="4">
                            <v-text-field v-model="industryForm.code" label="Code" variant="outlined" density="compact" :error-messages="industryErrors.code || []" />
                        </v-col>
                        <v-col cols="12" md="8">
                            <v-text-field v-model="industryForm.title" label="Title" variant="outlined" density="compact" :error-messages="industryErrors.title || []" />
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions class="justify-space-between">
                    <v-btn
                        v-if="industryForm.id"
                        variant="text"
                        color="error"
                        :loading="deletingIndustryId === industryForm.id"
                        @click="deleteIndustry({ id: industryForm.id, code: industryForm.code })"
                    >
                        Delete
                    </v-btn>
                    <v-spacer />
                    <v-btn variant="text" @click="industryDialog = false">Cancel</v-btn>
                    <v-btn color="teal-darken-3" :disabled="!industryForm.code || !industryForm.title" :loading="savingIndustry" @click="saveIndustry">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </section>
</template>

<style scoped>
.unit-toolbar {
    display: grid;
    grid-template-columns: minmax(170px, 0.7fr) minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
    margin-bottom: 10px;
    padding: 8px 10px;
    border: 1px solid rgba(0, 128, 128, 0.28);
    border-radius: 16px;
    background: linear-gradient(135deg, #043f3d 0%, #008b8b 100%);
    color: #e8fffb;
    box-shadow: 0 12px 28px rgba(0, 76, 76, 0.18);
}

.unit-toolbar__eyebrow {
    color: rgba(232, 255, 251, 0.68);
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.unit-toolbar__main strong {
    display: block;
    overflow: hidden;
    font-size: 1rem;
    font-weight: 950;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unit-toolbar__industries,
.unit-toolbar__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    align-items: center;
}

.unit-toolbar__industry {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    max-width: 260px;
    padding: 3px 6px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
}

.unit-toolbar__industry.is-primary {
    background: #e8fffb;
    color: #034341;
}

.unit-toolbar__industry small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unit-toolbar__industry button,
.unit-toolbar__actions button {
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    color: inherit;
    cursor: pointer;
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    padding: 5px 7px;
    text-transform: uppercase;
}

.unit-toolbar__actions button.is-danger {
    background: rgba(136, 19, 55, 0.44);
}

.unit-toolbar__actions button:disabled {
    cursor: wait;
    opacity: 0.45;
}

.unit-toolbar__industry-select {
    width: 170px;
}

.unit-toolbar__switches {
    display: flex;
    gap: 12px;
}

@media (max-width: 1180px) {
    .unit-toolbar {
        grid-template-columns: 1fr;
    }
}
</style>
