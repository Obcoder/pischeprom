<template>
    <v-container fluid>
        <v-card>
            <v-card-title class="d-flex align-center justify-space-between flex-wrap ga-3">
                <div>Telephones</div>

                <v-btn color="primary" @click="openCreate">
                    Добавить
                </v-btn>
            </v-card-title>

            <v-card-text>
                <v-row>
                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="filters.search"
                            label="Поиск"
                            clearable
                            density="comfortable"
                            variant="outlined"
                        />
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-autocomplete
                            v-model="filters.entity_ids"
                            :items="entities"
                            item-title="name"
                            item-value="id"
                            label="Фильтр по entities"
                            multiple
                            chips
                            closable-chips
                            clearable
                            density="comfortable"
                            variant="outlined"
                        />
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-autocomplete
                            v-model="filters.unit_ids"
                            :items="units"
                            item-title="name"
                            item-value="id"
                            label="Фильтр по units"
                            multiple
                            chips
                            closable-chips
                            clearable
                            density="comfortable"
                            variant="outlined"
                        />
                    </v-col>
                </v-row>

                <v-data-table-server
                    v-model:page="options.page"
                    v-model:items-per-page="options.itemsPerPage"
                    v-model:sort-by="options.sortBy"
                    :headers="headers"
                    :items="items"
                    :items-length="totalItems"
                    :loading="loading"
                    item-value="id"
                    class="mt-4"
                >
                    <template #item.entities="{ item }">
                        <div class="d-flex flex-wrap ga-1">
                            <v-chip
                                v-for="entity in item.entities"
                                :key="entity.id"
                                size="small"
                            >
                                {{ entity.name }}
                            </v-chip>
                        </div>
                    </template>

                    <template #item.units="{ item }">
                        <div class="d-flex flex-wrap ga-1">
                            <v-chip
                                v-for="unit in item.units"
                                :key="unit.id"
                                size="small"
                            >
                                {{ unit.name }}
                            </v-chip>
                        </div>
                    </template>

                    <template #item.created_at="{ item }">
                        <div>{{ formatDate(item.created_at) }}</div>
                        <div class="text-medium-emphasis text-caption">
                            {{ formatTime(item.created_at) }}
                        </div>
                    </template>

                    <template #item.updated_at="{ item }">
                        <div>{{ formatDate(item.updated_at) }}</div>
                        <div class="text-medium-emphasis text-caption">
                            {{ formatTime(item.updated_at) }}
                        </div>
                    </template>

                    <template #item.actions="{ item }">
                        <v-btn
                            size="small"
                            variant="text"
                            color="primary"
                            @click="openEdit(item)"
                        >
                            Редактировать
                        </v-btn>
                    </template>
                </v-data-table-server>
            </v-card-text>
        </v-card>

        <TelephoneFormDialog
            v-model="dialog"
            :form="form"
            :errors="errors"
            :saving="saving"
            :entities="entities"
            :units="units"
            @submit="submit"
            @close="close"
        />
    </v-container>
</template>

<script setup>
import { onMounted } from 'vue'
import { useTelephoneTable } from '@/Composables/telephones/useTelephoneTable'
import { useTelephoneForm } from '@/Composables/telephones/useTelephoneForm'
import { useTelephoneMeta } from '@/Composables/telephones/useTelephoneMeta'
import { formatDate, formatTime } from '@/utils/formatters/dateTime'
import TelephoneFormDialog from '@/Components/Dictionaries/telephones/TelephoneFormDialog.vue'

const headers = [
    { title: 'ID', key: 'id', sortable: true },
    { title: 'Номер', key: 'number', sortable: true },
    { title: 'Entities', key: 'entities', sortable: false },
    { title: 'Units', key: 'units', sortable: false },
    { title: 'Создано', key: 'created_at', sortable: true },
    { title: 'Обновлено', key: 'updated_at', sortable: true },
    { title: 'Действия', key: 'actions', sortable: false },
]

const {
    loading,
    items,
    totalItems,
    options,
    filters,
    fetchItems,
} = useTelephoneTable()

const {
    entities,
    units,
    fetchMeta,
} = useTelephoneMeta()

const {
    dialog,
    saving,
    errors,
    form,
    openCreate,
    openEdit,
    close,
    submit,
} = useTelephoneForm(async () => {
    await fetchItems()
})

onMounted(async () => {
    await fetchMeta()
    await fetchItems()
})
</script>
