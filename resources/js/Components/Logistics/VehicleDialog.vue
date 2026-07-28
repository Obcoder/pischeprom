<script setup>
import { inject, reactive, ref, watch } from 'vue'

const props = defineProps({ modelValue: Boolean, vehicle: { type: Object, default: null } })
const emit = defineEmits(['update:modelValue', 'saved'])
const api = inject('logisticsApi')
const entities = ref([])
const formRef = ref(null)
const form = reactive(emptyForm())

const types = [
    { title: 'Грузовик', value: 'truck' }, { title: 'Фургон', value: 'van' },
    { title: 'Тягач', value: 'tractor' }, { title: 'Рефрижератор', value: 'refrigerated' },
    { title: 'Другое', value: 'other' },
]
const statuses = [
    { title: 'Активен', value: 'active' }, { title: 'В ремонте', value: 'maintenance' },
    { title: 'Неактивен', value: 'inactive' },
]

function emptyForm() {
    return {
        name: '', registration_number: '', make: '', model: '', year: null, vin: '',
        vehicle_type: 'truck', owner_entity_id: null, status: 'active', payload_capacity_kg: null,
        cargo_volume_m3: null, curb_weight_kg: null, gross_weight_kg: null, length_m: null,
        width_m: null, height_m: null, axle_count: null, max_axle_load_t: null, fuel_type: '',
        fuel_tank_capacity_l: null, average_fuel_consumption_l_per_100km: null,
        is_active: true, notes: '',
    }
}

function fill() {
    const blank = emptyForm()
    for (const key of Object.keys(blank)) {
        form[key] = props.vehicle?.[key] ?? blank[key]
    }
    if (props.vehicle?.owner) mergeEntities([props.vehicle.owner])
}

function mergeEntities(items) {
    const map = new Map(entities.value.map((item) => [item.id, item]))
    items.filter(Boolean).forEach((item) => map.set(item.id, item))
    entities.value = [...map.values()]
}

async function searchEntities(search = '') {
    try {
        const response = await api.request(`vehicle-entities-${search}`, {
            method: 'get', url: '/api/logistics/references/entities', params: { search, limit: 50 },
        }, { error: 'Не удалось загрузить владельцев.' })
        mergeEntities(response?.data || [])
    } catch { /* shown globally */ }
}

async function save() {
    const validation = await formRef.value?.validate()
    if (validation && !validation.valid) return

    const editing = Boolean(props.vehicle?.id)
    try {
        const response = await api.request('vehicle-save', {
            method: editing ? 'put' : 'post',
            url: editing ? `/api/logistics/vehicles/${props.vehicle.id}` : '/api/logistics/vehicles',
            data: form,
        }, { success: editing ? 'Автомобиль обновлён.' : 'Автомобиль добавлен.' })
        emit('saved', response?.data || response)
        emit('update:modelValue', false)
    } catch { /* backend validation is rendered below */ }
}

watch(() => props.modelValue, (open) => { if (open) { fill(); searchEntities() } })
</script>

<template>
    <v-dialog :model-value="modelValue" max-width="1040" scrollable persistent @update:model-value="emit('update:modelValue', $event)">
        <v-card>
            <v-card-title class="d-flex align-center justify-space-between">
                <span>{{ vehicle?.id ? `Автомобиль #${vehicle.id}` : 'Новый автомобиль' }}</span>
                <v-btn icon="mdi-close" variant="text" @click="emit('update:modelValue', false)" />
            </v-card-title>
            <v-divider />
            <v-card-text>
                <v-form ref="formRef" @submit.prevent="save">
                    <v-row dense>
                        <v-col cols="12" md="6"><v-text-field v-model="form.name" label="Внутреннее название *" :rules="[v => !!v || 'Укажите название']" :error-messages="api.firstError('name')" /></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="form.registration_number" label="Госномер *" :rules="[v => !!v || 'Укажите госномер']" :error-messages="api.firstError('registration_number')" /></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="form.vin" label="VIN" :error-messages="api.firstError('vin')" /></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="form.make" label="Марка" /></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="form.model" label="Модель" /></v-col>
                        <v-col cols="6" md="2"><v-text-field v-model.number="form.year" label="Год" type="number" /></v-col>
                        <v-col cols="6" md="2"><v-select v-model="form.vehicle_type" label="Тип *" :items="types" /></v-col>
                        <v-col cols="12" md="2"><v-select v-model="form.status" label="Статус *" :items="statuses" /></v-col>
                        <v-col cols="12" md="6">
                            <v-autocomplete v-model="form.owner_entity_id" :items="entities" item-title="name" item-value="id" label="Владелец / перевозчик" clearable @update:search="searchEntities" />
                        </v-col>
                        <v-col cols="12"><v-divider class="my-2" /><div class="text-subtitle-2 mb-2">Груз и габариты для truck-профиля</div></v-col>
                        <v-col cols="6" md="3"><v-text-field v-model.number="form.payload_capacity_kg" label="Грузоподъёмность, кг" type="number" min="0" :error-messages="api.firstError('payload_capacity_kg')" /></v-col>
                        <v-col cols="6" md="3"><v-text-field v-model.number="form.cargo_volume_m3" label="Объём, м³" type="number" min="0" step="0.001" /></v-col>
                        <v-col cols="6" md="3"><v-text-field v-model.number="form.curb_weight_kg" label="Снаряжённая масса, кг" type="number" min="0" /></v-col>
                        <v-col cols="6" md="3"><v-text-field v-model.number="form.gross_weight_kg" label="Полная масса, кг" type="number" min="0" :error-messages="api.firstError('gross_weight_kg')" /></v-col>
                        <v-col cols="4" md="2"><v-text-field v-model.number="form.length_m" label="Длина, м" type="number" min="0" step="0.01" /></v-col>
                        <v-col cols="4" md="2"><v-text-field v-model.number="form.width_m" label="Ширина, м" type="number" min="0" step="0.01" /></v-col>
                        <v-col cols="4" md="2"><v-text-field v-model.number="form.height_m" label="Высота, м" type="number" min="0" step="0.01" /></v-col>
                        <v-col cols="6" md="2"><v-text-field v-model.number="form.axle_count" label="Осей" type="number" min="1" /></v-col>
                        <v-col cols="6" md="2"><v-text-field v-model.number="form.max_axle_load_t" label="На ось, т" type="number" min="0" step="0.01" /></v-col>
                        <v-col cols="12"><v-divider class="my-2" /><div class="text-subtitle-2 mb-2">Топливо</div></v-col>
                        <v-col cols="12" md="4"><v-text-field v-model="form.fuel_type" label="Тип топлива" /></v-col>
                        <v-col cols="6" md="4"><v-text-field v-model.number="form.fuel_tank_capacity_l" label="Бак, л" type="number" min="0" /></v-col>
                        <v-col cols="6" md="4"><v-text-field v-model.number="form.average_fuel_consumption_l_per_100km" label="Норма, л/100 км" type="number" min="0" /></v-col>
                        <v-col cols="12" md="4"><v-switch v-model="form.is_active" label="Доступен в справочнике" color="green-darken-2" hide-details /></v-col>
                        <v-col cols="12"><v-textarea v-model="form.notes" label="Примечание" rows="2" /></v-col>
                    </v-row>
                </v-form>
            </v-card-text>
            <v-divider />
            <v-card-actions class="justify-end">
                <v-btn variant="text" @click="emit('update:modelValue', false)">Отмена</v-btn>
                <v-btn color="green-darken-2" variant="flat" :loading="api.isPending('vehicle-save')" @click="save">Сохранить</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
