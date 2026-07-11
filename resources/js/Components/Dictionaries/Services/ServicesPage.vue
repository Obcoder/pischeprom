<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import { useServices } from '@/Composables/useServices.js'
import ServiceFormDialog from './ServiceFormDialog.vue'

const {
    services,
    loadingServices,
    savingService,
    deletingService,
    totalItems,
    options,
    filters,

    indexServices,
    storeService,
    updateService,
    destroyService,
    resetServiceFilters,
} = useServices()

const checks = ref([])
const expenseArticles = ref([])
const projects = ref([])

const dialog = ref(false)
const selectedService = ref(null)
const formError = ref('')
const snackbar = ref({
    show: false,
    text: '',
    color: 'success',
})

const activeFilterItems = [
    {
        title: 'Все',
        value: 'all',
    },
    {
        title: 'Активные',
        value: true,
    },
    {
        title: 'Неактивные',
        value: false,
    },
]

const headers = [
    {
        title: 'ID',
        key: 'id',
        sortable: true,
        width: 82,
    },
    {
        title: 'Услуга',
        key: 'name',
        sortable: true,
        minWidth: 260,
    },
    {
        title: 'Код',
        key: 'code',
        sortable: true,
        width: 140,
    },
    {
        title: 'Статья',
        key: 'expense_article_id',
        sortable: true,
        width: 190,
    },
    {
        title: 'Проект',
        key: 'project_id',
        sortable: true,
        width: 180,
    },
    {
        title: 'Checks',
        key: 'checks_count',
        sortable: true,
        width: 110,
    },
    {
        title: 'Активна',
        key: 'is_active',
        sortable: true,
        width: 118,
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
        width: 126,
    },
]

const tableHeight = computed(() => 'calc(100vh - 320px)')

let searchTimer = null

watch(
    () => ({ ...filters.value }),
    () => {
        clearTimeout(searchTimer)

        searchTimer = setTimeout(() => {
            options.value.page = 1
            indexServices()
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

async function loadExpenseArticles() {
    try {
        const response = await axios.get(route('expense-articles.index'))

        expenseArticles.value = response.data.data || response.data || []
    } catch (error) {
        console.error('loadExpenseArticles error:', error)
    }
}

async function loadProjects() {
    try {
        const response = await axios.get(route('projects.index'))

        projects.value = response.data.data || response.data || []
    } catch (error) {
        console.error('loadProjects error:', error)
    }
}

function openCreate() {
    formError.value = ''
    selectedService.value = null
    dialog.value = true
}

function openEdit(item) {
    formError.value = ''
    selectedService.value = item
    dialog.value = true
}

async function saveService(payload) {
    formError.value = ''

    try {
        if (selectedService.value?.id) {
            await updateService(selectedService.value.id, payload)
            notify('Услуга обновлена')
        } else {
            await storeService(payload)
            notify('Услуга создана')
        }

        dialog.value = false
        selectedService.value = null

        await indexServices()
    } catch (error) {
        formError.value = errorMessage(error, 'Не удалось сохранить услугу')
    }
}

async function removeService(item) {
    if (!confirm(`Удалить услугу "${item.name}"?`)) {
        return
    }

    try {
        await destroyService(item.id)
        notify('Услуга удалена')
        await indexServices()
    } catch (error) {
        notify(errorMessage(error, 'Не удалось удалить услугу'), 'error')
    }
}

function resetFilters() {
    resetServiceFilters()
    indexServices()
}

function notify(text, color = 'success') {
    snackbar.value = {
        show: true,
        text,
        color,
    }
}

function errorMessage(error, fallback) {
    return error?.response?.data?.message
        || Object.values(error?.response?.data?.errors || {}).flat().join('\n')
        || fallback
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

onMounted(async () => {
    await Promise.all([
        loadChecks(),
        loadExpenseArticles(),
        loadProjects(),
        indexServices(),
    ])
})
</script>

<template>
    <v-container fluid class="services-page pa-2 pa-md-3">
        <v-row align="center" dense class="mb-2">
            <v-col cols="12" md="3">
                <v-text-field
                    v-model="filters.search"
                    label="Поиск"
                    placeholder="Название, код, описание"
                    variant="outlined"
                    density="compact"
                    prepend-inner-icon="mdi-magnify"
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
                    label="Check"
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
                <v-autocomplete
                    v-model="filters.expense_article_id"
                    :items="expenseArticles"
                    item-title="name"
                    item-value="id"
                    label="Статья"
                    variant="outlined"
                    density="compact"
                    clearable
                    hide-details
                />
            </v-col>

            <v-col cols="12" md="2">
                <v-autocomplete
                    v-model="filters.project_id"
                    :items="projects"
                    item-title="name"
                    item-value="id"
                    label="Проект"
                    variant="outlined"
                    density="compact"
                    clearable
                    hide-details
                />
            </v-col>

            <v-col cols="12" md="2">
                <v-select
                    v-model="filters.is_active"
                    :items="activeFilterItems"
                    label="Статус"
                    variant="outlined"
                    density="compact"
                    hide-details
                />
            </v-col>
        </v-row>

        <v-row align="center" dense class="mb-2">
            <v-col cols="12" md="2">
                <v-text-field
                    v-model="filters.created_from"
                    label="Created from"
                    type="date"
                    variant="outlined"
                    density="compact"
                    clearable
                    hide-details
                />
            </v-col>

            <v-col cols="12" md="2">
                <v-text-field
                    v-model="filters.created_to"
                    label="Created to"
                    type="date"
                    variant="outlined"
                    density="compact"
                    clearable
                    hide-details
                />
            </v-col>

            <v-col>
                <div class="text-caption text-medium-emphasis">
                    Всего: {{ totalItems }}
                </div>
            </v-col>

            <v-col cols="auto">
                <v-btn
                    text="Сбросить"
                    prepend-icon="mdi-filter-off"
                    variant="tonal"
                    density="compact"
                    @click="resetFilters"
                />
            </v-col>

            <v-col cols="auto">
                <v-btn
                    text="+ Услуга"
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
            :items="services"
            :items-length="totalItems"
            :loading="loadingServices"
            :height="tableHeight"
            fixed-header
            hover
            density="compact"
            class="services-table border rounded"
            :items-per-page-options="[50, 100, 200, 500]"
            @update:options="indexServices"
        >
            <template #item.name="{ item }">
                <div class="service-name-cell">
                    <button type="button" class="service-name" @click="openEdit(item)">
                        {{ item.name }}
                    </button>

                    <span v-if="item.description" class="text-caption text-medium-emphasis text-truncate">
                        {{ item.description }}
                    </span>
                </div>
            </template>

            <template #item.code="{ item }">
                <code v-if="item.code" class="service-code">{{ item.code }}</code>
                <span v-else class="text-medium-emphasis">—</span>
            </template>

            <template #item.expense_article_id="{ item }">
                <v-chip
                    v-if="item.expense_article"
                    :color="item.expense_article.color || undefined"
                    size="x-small"
                    variant="tonal"
                >
                    {{ item.expense_article.name }}
                </v-chip>
                <span v-else class="text-medium-emphasis">—</span>
            </template>

            <template #item.project_id="{ item }">
                <span class="text-caption">
                    {{ item.project?.name || '—' }}
                </span>
            </template>

            <template #item.checks_count="{ item }">
                {{ item.checks_count ?? 0 }}
            </template>

            <template #item.is_active="{ item }">
                <v-chip
                    :color="item.is_active ? 'success' : 'grey'"
                    size="x-small"
                    variant="tonal"
                >
                    {{ item.is_active ? 'Да' : 'Нет' }}
                </v-chip>
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
                    :loading="deletingService"
                    @click="removeService(item)"
                />
            </template>
        </v-data-table-server>

        <ServiceFormDialog
            v-model="dialog"
            :service="selectedService"
            :saving="savingService"
            :expense-articles="expenseArticles"
            :projects="projects"
            :error="formError"
            @save="saveService"
        />

        <v-snackbar
            v-model="snackbar.show"
            :color="snackbar.color"
            :timeout="2600"
        >
            {{ snackbar.text }}
        </v-snackbar>
    </v-container>
</template>

<style scoped>
.services-page {
    min-height: calc(100vh - 150px);
}

.services-table :deep(thead th) {
    font-weight: 800;
    white-space: nowrap;
}

.services-table :deep(td),
.services-table :deep(th) {
    height: 34px;
}

.service-name-cell {
    display: grid;
    max-width: 460px;
    line-height: 1.15;
}

.service-name {
    min-width: 0;
    overflow: hidden;
    border: 0;
    padding: 0;
    background: transparent;
    color: rgb(var(--v-theme-primary));
    font: inherit;
    font-weight: 700;
    text-align: left;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
}

.service-code {
    font-size: 0.75rem;
}
</style>
