<script setup>
import { computed, inject, onActivated, onMounted, reactive, ref, watch } from 'vue'
import VehicleDialog from './VehicleDialog.vue'

const api = inject('logisticsApi')
const permissions = inject('logisticsPermissions')
const items = ref([])
const total = ref(0)
const loaded = ref(false)
const dialog = ref(false)
const selected = ref(null)
const archiveTarget = ref(null)
const table = reactive({ page: 1, itemsPerPage: 25, sortBy: [{ key: 'name', order: 'asc' }] })
const filters = reactive({ search: '', status: null, vehicle_type: null, with_archived: false })
let timer = null

const headers = [
    { title: 'Автомобиль', key: 'name' }, { title: 'Госномер', key: 'registration_number' },
    { title: 'Тип', key: 'vehicle_type' }, { title: 'Статус', key: 'status' },
    { title: 'Грузоподъёмность', key: 'payload_capacity_kg', align: 'end' },
    { title: 'Полная масса', key: 'gross_weight_kg', align: 'end' },
    { title: 'Рейсов', key: 'trips_count', align: 'end', sortable: false },
    { title: '', key: 'actions', align: 'end', sortable: false },
]
const statuses = [{ title: 'Активен', value: 'active' }, { title: 'В ремонте', value: 'maintenance' }, { title: 'Неактивен', value: 'inactive' }]
const types = [{ title: 'Грузовик', value: 'truck' }, { title: 'Фургон', value: 'van' }, { title: 'Тягач', value: 'tractor' }, { title: 'Рефрижератор', value: 'refrigerated' }, { title: 'Другое', value: 'other' }]
const canManage = computed(() => Boolean(permissions.value?.vehicles_manage))

function formatKg(value) { return value == null ? '—' : `${new Intl.NumberFormat('ru-RU').format(value)} кг` }
function statusColor(value) { return ({ active: 'green', maintenance: 'orange', inactive: 'grey' })[value] || 'grey' }
function openCreate() { selected.value = null; dialog.value = true }
function openEdit(item) { selected.value = item; dialog.value = true }

async function load(options = null) {
    if (options?.page) Object.assign(table, options)
    const sort = table.sortBy?.[0] || { key: 'name', order: 'asc' }
    try {
        const response = await api.request('vehicles-load', {
            method: 'get', url: '/api/logistics/vehicles', params: {
                page: table.page, per_page: table.itemsPerPage, sort_by: sort.key,
                sort_direction: sort.order, search: filters.search || undefined,
                status: filters.status || undefined, vehicle_type: filters.vehicle_type || undefined,
                with_archived: filters.with_archived ? 1 : 0,
            },
        }, { error: 'Не удалось загрузить автопарк.' })
        items.value = response?.data || []
        total.value = response?.meta?.total || 0
        loaded.value = true
    } catch { /* global snackbar */ }
}

async function archive() {
    if (!archiveTarget.value) return
    try {
        await api.request('vehicle-archive', { method: 'delete', url: `/api/logistics/vehicles/${archiveTarget.value.id}` }, { success: 'Автомобиль перемещён в архив.' })
        archiveTarget.value = null
        await load()
    } catch { /* global snackbar */ }
}

async function restore(item) {
    try {
        await api.request(`vehicle-restore-${item.id}`, { method: 'post', url: `/api/logistics/vehicles/${item.id}/restore` }, { success: 'Автомобиль восстановлен.' })
        await load()
    } catch { /* global snackbar */ }
}

watch(filters, () => { clearTimeout(timer); timer = setTimeout(() => { table.page = 1; load() }, 300) }, { deep: true })
onMounted(load)
onActivated(() => { if (!loaded.value) load() })
</script>

<template>
    <section>
        <div class="logistics-toolbar">
            <div class="logistics-toolbar__filters">
                <v-text-field v-model="filters.search" prepend-inner-icon="mdi-magnify" label="Название, госномер, VIN" density="compact" variant="outlined" hide-details clearable style="min-width: 260px" />
                <v-select v-model="filters.status" :items="statuses" label="Статус" density="compact" variant="outlined" hide-details clearable style="max-width: 190px" />
                <v-select v-model="filters.vehicle_type" :items="types" label="Тип" density="compact" variant="outlined" hide-details clearable style="max-width: 200px" />
                <v-checkbox v-model="filters.with_archived" label="С архивом" density="compact" hide-details />
            </div>
            <div class="logistics-toolbar__actions">
                <v-btn variant="outlined" icon="mdi-refresh" :loading="api.isPending('vehicles-load')" @click="load" />
                <v-btn v-if="canManage" color="green-darken-2" variant="flat" prepend-icon="mdi-plus" @click="openCreate">Добавить авто</v-btn>
            </div>
        </div>

        <v-card variant="outlined">
            <v-data-table-server v-model:page="table.page" v-model:items-per-page="table.itemsPerPage" v-model:sort-by="table.sortBy"
                :headers="headers" :items="items" :items-length="total" :loading="api.isPending('vehicles-load')" item-value="id" @update:options="load">
                <template #item.name="{ item }"><strong>{{ item.name }}</strong><div class="text-caption text-medium-emphasis">{{ [item.make, item.model, item.year].filter(Boolean).join(' ') }}</div></template>
                <template #item.status="{ item }"><v-chip :color="statusColor(item.status)" variant="tonal" size="small">{{ statuses.find(x => x.value === item.status)?.title || item.status }}</v-chip></template>
                <template #item.vehicle_type="{ item }">{{ types.find(x => x.value === item.vehicle_type)?.title || item.vehicle_type }}</template>
                <template #item.payload_capacity_kg="{ item }">{{ formatKg(item.payload_capacity_kg) }}</template>
                <template #item.gross_weight_kg="{ item }">{{ formatKg(item.gross_weight_kg) }}</template>
                <template #item.actions="{ item }">
                    <v-btn v-if="canManage && !item.deleted_at" icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(item)" />
                    <v-btn v-if="canManage && !item.deleted_at" icon="mdi-archive-outline" size="small" variant="text" color="orange" @click="archiveTarget = item" />
                    <v-btn v-if="canManage && item.deleted_at" icon="mdi-restore" size="small" variant="text" color="green" :loading="api.isPending(`vehicle-restore-${item.id}`)" @click="restore(item)" />
                </template>
                <template #no-data><div class="logistics-empty">Автомобили не найдены.</div></template>
            </v-data-table-server>
        </v-card>

        <VehicleDialog v-model="dialog" :vehicle="selected" @saved="load" />
        <v-dialog :model-value="Boolean(archiveTarget)" max-width="470" @update:model-value="!$event && (archiveTarget = null)">
            <v-card title="Архивировать автомобиль?">
                <v-card-text>Автомобиль «{{ archiveTarget?.name }}» исчезнет из активного справочника, но останется в истории рейсов.</v-card-text>
                <v-card-actions class="justify-end"><v-btn variant="text" @click="archiveTarget = null">Отмена</v-btn><v-btn color="orange-darken-2" variant="flat" :loading="api.isPending('vehicle-archive')" @click="archive">В архив</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </section>
</template>
