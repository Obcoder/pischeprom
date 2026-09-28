<script setup>
import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'
import { apartmentLabel } from '@/utils/buildingApartments'

const props = defineProps({
    building: { type: Object, required: true },
    modelValue: { type: [Number, String], default: null },
    selectable: { type: Boolean, default: true },
    disabled: Boolean,
    errorMessages: { type: [Array, String], default: () => [] },
})
const emit = defineEmits(['update:modelValue', 'changed'])
const apartments = ref([])
const dialog = ref(false)
const loading = ref(false)
const saving = ref(false)
const deletingId = ref(null)
const editingId = ref(null)
const error = ref('')
const errors = ref({})
const form = reactive({ number: '', type: 'apartment' })
const types = [
    { title: 'Квартира', value: 'apartment' },
    { title: 'Офис', value: 'office' },
    { title: 'Помещение', value: 'premise' },
]
const busy = computed(() => props.disabled || loading.value || saving.value || deletingId.value !== null)
const selectLabel = computed(() => `Квартира / офис / помещение${props.building.address ? ` · ${props.building.address}` : ''}`)
let requestId = 0

async function load() {
    if (!props.building?.id) return
    const currentRequest = ++requestId
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get(`/api/buildings/${props.building.id}/apartments`)
        if (currentRequest !== requestId) return
        apartments.value = Array.isArray(data) ? data : data.data || []
    } catch (exception) {
        if (currentRequest === requestId) error.value = exception.response?.data?.message || 'Не удалось загрузить помещения.'
    } finally {
        if (currentRequest === requestId) loading.value = false
    }
}

function resetForm(apartment = null) {
    editingId.value = apartment?.id || null
    form.number = apartment?.number || ''
    form.type = apartment?.type || 'apartment'
    errors.value = {}
}

function openManager() {
    resetForm()
    dialog.value = true
    load()
}

async function save() {
    if (busy.value || !form.number.trim()) return
    saving.value = true
    error.value = ''
    errors.value = {}
    try {
        const url = `/api/buildings/${props.building.id}/apartments`
        const payload = { number: form.number.trim(), type: form.type }
        const { data } = editingId.value
            ? await axios.put(`${url}/${editingId.value}`, payload)
            : await axios.post(url, payload)
        const apartment = data.data || data
        apartments.value = [...apartments.value.filter(item => item.id !== apartment.id), apartment]
            .sort((a, b) => a.number.localeCompare(b.number, 'ru', { numeric: true }))
        if (!editingId.value && props.selectable) emit('update:modelValue', apartment.id)
        emit('changed', apartments.value)
        resetForm()
    } catch (exception) {
        errors.value = exception.response?.data?.errors || {}
        error.value = exception.response?.data?.message || 'Не удалось сохранить помещение.'
    } finally {
        saving.value = false
    }
}

async function remove(apartment) {
    if (busy.value || !window.confirm(`Удалить ${apartmentLabel(apartment)}?`)) return
    deletingId.value = apartment.id
    error.value = ''
    try {
        await axios.delete(`/api/buildings/${props.building.id}/apartments/${apartment.id}`)
        apartments.value = apartments.value.filter(item => item.id !== apartment.id)
        if (Number(props.modelValue) === Number(apartment.id)) emit('update:modelValue', null)
        if (editingId.value === apartment.id) resetForm()
        emit('changed', apartments.value)
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Не удалось удалить помещение.'
    } finally {
        deletingId.value = null
    }
}

watch(() => props.building?.id, () => {
    requestId += 1
    apartments.value = props.building?.apartments || []
    if (props.building?.apartment && !apartments.value.some(item => item.id === props.building.apartment.id)) {
        apartments.value = [...apartments.value, props.building.apartment]
    }
    resetForm()
    if (props.selectable && !Array.isArray(props.building?.apartments)) load()
}, { immediate: true })
</script>

<template>
    <div class="apartment-selector">
        <div class="apartment-selector__control">
            <v-select
                v-if="selectable"
                :model-value="modelValue"
                :items="apartments"
                :item-title="apartmentLabel"
                item-value="id"
                :label="selectLabel"
                :disabled="busy || !building.id"
                :loading="loading"
                :error-messages="errorMessages"
                no-data-text="Помещений пока нет — добавьте в справочник"
                variant="outlined"
                density="compact"
                hide-details="auto"
                clearable
                @update:model-value="emit('update:modelValue', $event ?? null)"
            />
            <v-btn v-if="!disabled" size="small" variant="tonal" prepend-icon="mdi-door" :disabled="!building.id" @click="openManager">
                {{ selectable ? 'Помещения' : 'Квартиры и помещения' }}
            </v-btn>
        </div>
        <v-alert v-if="error && !dialog" type="error" variant="tonal" density="compact">{{ error }}</v-alert>

        <v-dialog v-model="dialog" max-width="660" :persistent="busy">
            <v-card>
                <v-card-title>Квартиры, офисы и помещения</v-card-title>
                <v-card-subtitle>{{ building.address }}</v-card-subtitle>
                <v-card-text>
                    <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
                    <v-progress-linear v-if="loading" indeterminate />
                    <v-list v-if="apartments.length" class="apartment-selector__list" density="compact">
                        <v-list-item v-for="apartment in apartments" :key="apartment.id" :title="apartmentLabel(apartment)">
                            <template #append>
                                <v-btn v-if="selectable" size="small" variant="text" :disabled="busy" @click="emit('update:modelValue', apartment.id); dialog = false">Выбрать</v-btn>
                                <v-btn icon="mdi-pencil-outline" size="small" variant="text" title="Изменить помещение" :disabled="busy" @click="resetForm(apartment)" />
                                <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" title="Удалить помещение" :disabled="busy" :loading="deletingId === apartment.id" @click="remove(apartment)" />
                            </template>
                        </v-list-item>
                    </v-list>
                    <p v-else-if="!loading" class="mb-3">Помещений пока нет.</p>
                    <v-divider class="my-3" />
                    <p class="mb-3">{{ editingId ? 'Изменить помещение' : 'Добавить помещение' }}</p>
                    <v-select v-model="form.type" :items="types" label="Тип" variant="outlined" density="compact" :disabled="busy" :error-messages="errors.type" />
                    <v-text-field v-model="form.number" label="Номер" placeholder="Например, 12А" maxlength="50" variant="outlined" density="compact" :disabled="busy" :error-messages="errors.number" @keydown.enter.prevent="save" />
                    <div class="d-flex justify-end ga-2">
                        <v-btn v-if="editingId" variant="text" :disabled="busy" @click="resetForm()">Отмена изменения</v-btn>
                        <v-btn color="primary" :loading="saving" :disabled="busy || !form.number.trim()" @click="save">{{ editingId ? 'Сохранить' : 'Добавить' }}</v-btn>
                    </div>
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn :disabled="busy" @click="dialog = false">Закрыть</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.apartment-selector { min-width: 0; display: grid; gap: 8px; }
.apartment-selector__control { display: flex; align-items: flex-start; flex-wrap: wrap; gap: 8px; }
.apartment-selector__control > .v-select { min-width: 220px; flex: 1; }
.apartment-selector__list { max-height: 300px; overflow-y: auto; }
</style>
