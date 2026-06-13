<script setup>
import axios from 'axios'
import { reactive, ref } from 'vue'

const props = defineProps({
    unit: { type: Object, required: true },
    dict: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['refresh'])

const attachBuildingId = ref(null)
const dialog = ref(false)
const saving = ref(false)
const deletingId = ref(null)
const detachingId = ref(null)
const errors = ref({})
const editing = ref(null)

const form = reactive({
    city_id: null,
    building_type_id: null,
    address: '',
    postcode: '',
})

function buildingTitle(building) {
    return [
        building?.city?.name,
        building?.address,
        building?.building_type?.name || building?.buildingType?.name,
    ].filter(Boolean).join(' / ')
}

function resetForm(building = null) {
    editing.value = building
    errors.value = {}
    form.city_id = building?.city_id || building?.city?.id || null
    form.building_type_id = building?.building_type_id || building?.buildingType?.id || building?.building_type?.id || null
    form.address = building?.address || ''
    form.postcode = building?.postcode || ''
}

function openCreate() {
    resetForm()
    dialog.value = true
}

function openEdit(building) {
    resetForm(building)
    dialog.value = true
}

async function attachExisting() {
    if (!attachBuildingId.value) return

    await axios.post(`/api/units/${props.unit.id}/buildings`, {
        building_id: attachBuildingId.value,
    })

    attachBuildingId.value = null
    emit('refresh')
}

async function saveBuilding() {
    saving.value = true
    errors.value = {}

    const payload = {
        city_id: form.city_id,
        building_type_id: form.building_type_id || null,
        address: form.address,
        postcode: form.postcode || null,
    }

    try {
        let building

        if (editing.value?.id) {
            const { data } = await axios.put(`/api/buildings/${editing.value.id}`, payload)
            building = data
        } else {
            const { data } = await axios.post('/api/buildings', payload)
            building = data
        }

        if (building?.id) {
            await axios.post(`/api/units/${props.unit.id}/buildings`, {
                building_id: building.id,
            })
        }

        dialog.value = false
        emit('refresh')
    } catch (error) {
        errors.value = error.response?.data?.errors || {}
        console.error('building save error:', error.response?.data || error)
    } finally {
        saving.value = false
    }
}

async function detachBuilding(building) {
    if (!building?.id) return

    detachingId.value = building.id

    try {
        await axios.delete(`/api/units/${props.unit.id}/buildings/${building.id}`)
        emit('refresh')
    } finally {
        detachingId.value = null
    }
}

async function deleteBuilding(building) {
    if (!building?.id || !window.confirm(`Удалить building "${building.address}" полностью?`)) return

    deletingId.value = building.id

    try {
        await axios.delete(`/api/buildings/${building.id}`)
        emit('refresh')
    } finally {
        deletingId.value = null
    }
}
</script>

<template>
    <div class="unit-buildings-tab">
        <div class="unit-buildings-tab__toolbar">
            <v-autocomplete
                v-model="attachBuildingId"
                :items="dict.buildings || []"
                :item-title="buildingTitle"
                item-value="id"
                label="Attach building"
                variant="solo-filled"
                density="compact"
                hide-details
                clearable
            />
            <button type="button" :disabled="!attachBuildingId" @click="attachExisting">Attach</button>
            <button type="button" @click="openCreate">New building</button>
        </div>

        <div class="unit-buildings-tab__grid">
            <article v-for="building in (unit.buildings || [])" :key="building.id" class="unit-building-card">
                <div>
                    <strong>{{ building.address }}</strong>
                    <span>{{ building.city?.name || 'Город не указан' }}</span>
                </div>
                <small>{{ building.building_type?.name || building.buildingType?.name || 'вид не указан' }}</small>
                <div class="unit-building-card__actions">
                    <button type="button" @click="openEdit(building)">Edit</button>
                    <button type="button" :disabled="detachingId === building.id" @click="detachBuilding(building)">Detach</button>
                    <button type="button" class="is-danger" :disabled="deletingId === building.id" @click="deleteBuilding(building)">Delete</button>
                </div>
            </article>

            <div v-if="!unit.buildings?.length" class="unit-buildings-tab__empty">
                Buildings не привязаны.
            </div>
        </div>

        <v-dialog v-model="dialog" max-width="760">
            <v-card rounded="xl">
                <v-card-title>{{ editing ? 'Edit building' : 'New building' }}</v-card-title>
                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="5">
                            <v-autocomplete v-model="form.city_id" :items="dict.cities || []" item-title="name" item-value="id" label="City" variant="outlined" density="compact" :error-messages="errors.city_id || []" />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-select v-model="form.building_type_id" :items="dict.buildingTypes || []" item-title="name" item-value="id" label="Building type" variant="outlined" density="compact" clearable :error-messages="errors.building_type_id || []" />
                        </v-col>
                        <v-col cols="12" md="3">
                            <v-text-field v-model="form.postcode" label="Postcode" variant="outlined" density="compact" :error-messages="errors.postcode || []" />
                        </v-col>
                        <v-col cols="12">
                            <v-text-field v-model="form.address" label="Address" variant="outlined" density="compact" :error-messages="errors.address || []" />
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
                    <v-btn color="teal-darken-3" :disabled="!form.city_id || !form.address" :loading="saving" @click="saveBuilding">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.unit-buildings-tab {
    display: grid;
    gap: 8px;
}

.unit-buildings-tab__toolbar {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) auto auto;
    gap: 6px;
    align-items: center;
}

.unit-buildings-tab__toolbar button,
.unit-building-card__actions button {
    border: 1px solid rgba(0, 128, 128, 0.22);
    border-radius: 999px;
    background: #00796b;
    color: #effefa;
    cursor: pointer;
    font-size: 0.64rem;
    font-weight: 900;
    letter-spacing: 0.07em;
    padding: 6px 8px;
    text-transform: uppercase;
}

.unit-buildings-tab__toolbar button:disabled,
.unit-building-card__actions button:disabled {
    cursor: wait;
    opacity: 0.5;
}

.unit-buildings-tab__grid {
    display: grid;
    gap: 6px;
    max-height: 360px;
    overflow: auto;
}

.unit-building-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 110px auto;
    gap: 8px;
    align-items: center;
    padding: 7px 8px;
    border: 1px solid rgba(0, 128, 128, 0.14);
    border-radius: 10px;
    background: #fff;
}

.unit-building-card strong,
.unit-building-card span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unit-building-card strong {
    color: #263238;
    font-size: 0.78rem;
}

.unit-building-card span,
.unit-building-card small {
    color: #607d8b;
    font-size: 0.68rem;
}

.unit-building-card__actions {
    display: flex;
    gap: 4px;
}

.unit-building-card__actions .is-danger {
    background: #8a1238;
    border-color: rgba(138, 18, 56, 0.24);
}

.unit-buildings-tab__empty {
    padding: 10px;
    border: 1px dashed rgba(0, 128, 128, 0.28);
    border-radius: 10px;
    color: #607d8b;
    font-size: 0.74rem;
}

@media (max-width: 760px) {
    .unit-buildings-tab__toolbar,
    .unit-building-card {
        grid-template-columns: 1fr;
    }
}
</style>
