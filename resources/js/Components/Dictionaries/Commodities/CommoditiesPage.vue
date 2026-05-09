<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { route } from 'ziggy-js'
import { logo } from '@/Pages/Helpers/consts.js'
import { useCommodities } from '@/Composables/useCommodities.js'
import CommodityFormDialog from './CommodityFormDialog.vue'

const {
    commodities,
    loadingCommodities,
    savingCommodity,
    deletingCommodity,
    totalItems,
    options,
    filters,

    indexCommodities,
    storeCommodity,
    updateCommodity,
    destroyCommodity,
    resetCommodityFilters,
} = useCommodities()

const checks = ref([])

const dialog = ref(false)
const selectedCommodity = ref(null)

const avaFilterItems = [
    {
        title: 'Все',
        value: 'all',
    },
    {
        title: 'С ava',
        value: true,
    },
    {
        title: 'Без ava',
        value: false,
    },
]

const headers = [
    {
        title: 'Ava',
        key: 'ava',
        sortable: true,
        width: 80,
    },
    {
        title: 'ID',
        key: 'id',
        sortable: true,
        width: 80,
    },
    {
        title: 'Наименование',
        key: 'name',
        sortable: true,
    },
    {
        title: 'Checks',
        key: 'checks_count',
        sortable: true,
        width: 110,
    },
    {
        title: 'Media',
        key: 'media_count',
        sortable: true,
        width: 110,
    },
    {
        title: 'Created',
        key: 'created_at',
        sortable: true,
        width: 170,
    },
    {
        title: 'Updated',
        key: 'updated_at',
        sortable: true,
        width: 170,
    },
    {
        title: '',
        key: 'actions',
        sortable: false,
        align: 'end',
        width: 150,
    },
]

const tableHeight = computed(() => '760px')

let searchTimer = null

watch(
    () => ({ ...filters.value }),
    () => {
        clearTimeout(searchTimer)

        searchTimer = setTimeout(() => {
            options.value.page = 1
            indexCommodities()
        }, 350)
    },
    {
        deep: true,
    }
)

async function loadChecks() {
    try {
        const response = await axios.get(route('checks.index'))

        checks.value = response.data.data || response.data || []
    } catch (error) {
        console.error('loadChecks error:', error)
    }
}

function openCreate() {
    selectedCommodity.value = null
    dialog.value = true
}

function openEdit(item) {
    selectedCommodity.value = item
    dialog.value = true
}

async function saveCommodity(payload) {
    if (selectedCommodity.value?.id) {
        await updateCommodity(selectedCommodity.value.id, payload)
    } else {
        await storeCommodity(payload)
    }

    dialog.value = false
    selectedCommodity.value = null

    await indexCommodities()
}

async function removeCommodity(item) {
    if (!confirm(`Удалить Commodity "${item.name}"?`)) {
        return
    }

    await destroyCommodity(item.id)
    await indexCommodities()
}

function formatDate(value) {
    if (!value) {
        return '—'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

function resetFilters() {
    resetCommodityFilters()
    indexCommodities()
}

onMounted(async () => {
    await Promise.all([
        loadChecks(),
        indexCommodities(),
    ])
})
</script>

<template>
    <v-container fluid>
        <v-row align="center" class="mb-2">
            <v-col cols="12" md="3">
                <v-text-field
                    v-model="filters.search"
                    label="Поиск по имени"
                    variant="outlined"
                    density="compact"
                    clearable
                    hide-details
                />
            </v-col>

            <v-col cols="12" md="3">
                <v-autocomplete
                    v-model="filters.check_id"
                    :items="checks"
                    item-value="id"
                    item-title="id"
                    label="Фильтр по Check"
                    variant="outlined"
                    density="compact"
                    clearable
                    hide-details
                >
                    <template #item="{ props, item }">
                        <v-list-item v-bind="props">
                            <template #title>
                                Check #{{ item.raw.id }}
                            </template>

                            <template #subtitle>
                                {{ item.raw.date }} — {{ item.raw.entity?.name || 'без entity' }}
                            </template>
                        </v-list-item>
                    </template>

                    <template #selection="{ item }">
                        Check #{{ item.raw.id }}
                    </template>
                </v-autocomplete>
            </v-col>

            <v-col cols="12" md="2">
                <v-select
                    v-model="filters.has_ava"
                    :items="avaFilterItems"
                    label="Ava"
                    variant="outlined"
                    density="compact"
                    hide-details
                />
            </v-col>

            <v-col cols="12" md="2">
                <v-text-field
                    v-model="filters.created_from"
                    label="Created from"
                    type="date"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                />
            </v-col>

            <v-col cols="12" md="2">
                <v-text-field
                    v-model="filters.created_to"
                    label="Created to"
                    type="date"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                />
            </v-col>
        </v-row>

        <v-row align="center" class="mb-2">
            <v-col>
                <div class="text-caption text-grey">
                    Всего: {{ totalItems }}
                </div>
            </v-col>

            <v-col cols="auto">
                <v-btn
                    text="Сбросить"
                    variant="tonal"
                    density="compact"
                    @click="resetFilters"
                />
            </v-col>

            <v-col cols="auto">
                <v-btn
                    text="+ Commodity"
                    color="primary"
                    variant="elevated"
                    density="compact"
                    @click="openCreate"
                />
            </v-col>
        </v-row>

        <v-data-table-server
            v-model:page="options.page"
            v-model:items-per-page="options.itemsPerPage"
            v-model:sort-by="options.sortBy"
            :headers="headers"
            :items="commodities"
            :items-length="totalItems"
            :loading="loadingCommodities"
            :height="tableHeight"
            fixed-header
            hover
            density="compact"
            class="border rounded"
            :items-per-page-options="[50, 100, 200, 500]"
            @update:options="indexCommodities"
        >
            <template #item.ava="{ item }">
                <v-avatar size="42" rounded="lg">
                    <v-img :src="item.ava_url || logo" cover />
                </v-avatar>
            </template>

            <template #item.name="{ item }">
                <Link
                    :href="route('Ameise.commodity.show', item.id)"
                    class="text-primary font-weight-medium"
                >
                    {{ item.name }}
                </Link>
            </template>

            <template #item.created_at="{ item }">
                <span class="text-caption">
                    {{ formatDate(item.created_at) }}
                </span>
            </template>

            <template #item.updated_at="{ item }">
                <span class="text-caption">
                    {{ formatDate(item.updated_at) }}
                </span>
            </template>

            <template #item.actions="{ item }">
                <v-btn
                    icon="mdi-pencil"
                    variant="text"
                    density="compact"
                    @click="openEdit(item)"
                />

                <v-btn
                    icon="mdi-delete"
                    variant="text"
                    density="compact"
                    color="error"
                    :loading="deletingCommodity"
                    @click="removeCommodity(item)"
                />
            </template>
        </v-data-table-server>

        <CommodityFormDialog
            v-model="dialog"
            :commodity="selectedCommodity"
            :saving="savingCommodity"
            @save="saveCommodity"
        />
    </v-container>
</template>
