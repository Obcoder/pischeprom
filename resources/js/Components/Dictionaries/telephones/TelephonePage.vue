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
                            @update:model-value="onFilterChanged"
                            @click:clear="onFilterChanged"
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
                            @update:model-value="onFilterChanged"
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
                            @update:model-value="onFilterChanged"
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

        <v-dialog
            v-model="dialog"
            max-width="700"
        >
            <v-card>
                <v-card-title>
                    {{ isEdit ? 'Редактирование телефона' : 'Новый телефон' }}
                </v-card-title>

                <v-card-text>
                    <v-row>
                        <v-col cols="12">
                            <v-text-field
                                v-model="form.number"
                                label="Номер"
                                variant="outlined"
                                density="comfortable"
                                :error-messages="errors.number || []"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-autocomplete
                                v-model="form.entity_ids"
                                :items="entities"
                                item-title="name"
                                item-value="id"
                                label="Entities"
                                multiple
                                chips
                                closable-chips
                                clearable
                                variant="outlined"
                                density="comfortable"
                                :error-messages="errors.entity_ids || []"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-autocomplete
                                v-model="form.unit_ids"
                                :items="units"
                                item-title="name"
                                item-value="id"
                                label="Units"
                                multiple
                                chips
                                closable-chips
                                clearable
                                variant="outlined"
                                density="comfortable"
                                :error-messages="errors.unit_ids || []"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="close">
                        Отмена
                    </v-btn>

                    <v-btn
                        color="primary"
                        :loading="saving"
                        @click="submit"
                    >
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script setup>
import { onMounted } from 'vue'
import { useTelephoneTable } from '@/Composables/telephones/useTelephoneTable'
import { useTelephoneForm } from '@/Composables/telephones/useTelephoneForm'
import { useTelephoneMeta } from '@/Composables/telephones/useTelephoneMeta'

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
    resetPage,
} = useTelephoneTable()

const {
    loadingMeta,
    entities,
    units,
    fetchMeta,
} = useTelephoneMeta()

const {
    dialog,
    saving,
    errors,
    form,
    isEdit,
    openCreate,
    openEdit,
    close,
    submit,
} = useTelephoneForm(async () => {
    await fetchItems()
})

const onFilterChanged = () => {
    resetPage()
    fetchItems()
}

onMounted(async () => {
    await fetchMeta()
    await fetchItems()
})
</script>
