<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useEntityApi } from '@/Composables/entities/useEntityApi'
import { useEntityFilters } from '@/Composables/entities/useEntityFilters'
import { useEntityForm } from '@/Composables/entities/useEntityForm'
import { usePhoneFormatter } from '@/Composables/entities/usePhoneFormatter'

const props = defineProps({
    classifications: { type: Array, default: () => [] },
    countries: { type: Array, default: () => [] },
    citiesOptions: { type: Array, default: () => [] },
    buildingsOptions: { type: Array, default: () => [] },
    emailsOptions: { type: Array, default: () => [] },
    telephonesOptions: { type: Array, default: () => [] },
    unitsOptions: { type: Array, default: () => [] },
    chatsOptions: { type: Array, default: () => [] },
})

const { getList, getOne, createOne, updateOne, deleteOne } = useEntityApi()
const { filters, resetFilters } = useEntityFilters()
const { form, fillForm, resetForm, toPayload } = useEntityForm()
const { formatPhones } = usePhoneFormatter()

const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const isEdit = ref(false)
const selectedEntity = ref(null)
const groupByCities = ref(false)

const items = ref([])
const totalItems = ref(0)
const page = ref(1)
const itemsPerPage = ref(10)
const pageMarkers = ref([])
const sortBy = ref([{ key: 'sales_count', order: 'desc' }])

const headers = [
    { title: 'Название', key: 'name', sortable: true },
    { title: 'Классификация', key: 'classification_name', sortable: false },
    { title: 'Страна', key: 'country_name', sortable: false },
    { title: 'Города', key: 'city_names', sortable: false },
    { title: 'Телефоны', key: 'telephones', sortable: false },
    { title: 'Продаж', key: 'sales_count', sortable: true },
    { title: 'Последний purchase', key: 'last_purchase_date', sortable: true },
    { title: 'Действия', key: 'actions', sortable: false, width: 120 },
]

const loadItems = async () => {
    loading.value = true
    try {
        const sort = sortBy.value?.[0] ?? {}
        const response = await getList({
            page: page.value,
            itemsPerPage: itemsPerPage.value,
            search: filters.search,
            entity_classification_ids: filters.entity_classification_ids,
            country_ids: filters.country_ids,
            city_ids: filters.city_ids,
            building_ids: filters.building_ids,
            email_ids: filters.email_ids,
            telephone_ids: filters.telephone_ids,
            unit_ids: filters.unit_ids,
            chat_ids: filters.chat_ids,
            sortBy: sort.key ?? 'sales_count',
            sortDesc: sort.order === 'desc',
        })

        items.value = response.data.map(item => ({
            ...item,
            classification_name: item.classification?.name ?? '',
            country_name: item.country?.name ?? '',
            city_names: item.cities?.map(c => c.name).join(', ') ?? '',
            telephones_display: formatPhones(item.telephones ?? []),
            last_purchase_date: item.sales_max_date ?? null,
        }))

        totalItems.value = response.meta.total
        pageMarkers.value = response.meta.page_markers ?? []
    } finally {
        loading.value = false
    }
}

const groupedItems = computed(() => {
    if (!groupByCities.value) return items.value

    return [...items.value].sort((a, b) => {
        const aCity = a.city_names || 'Без города'
        const bCity = b.city_names || 'Без города'
        return aCity.localeCompare(bCity)
    })
})

const openCreate = () => {
    resetForm()
    isEdit.value = false
    dialog.value = true
}

const openEdit = async (item) => {
    const full = await getOne(item.id)
    fillForm(full)
    isEdit.value = true
    dialog.value = true
}

const submit = async () => {
    saving.value = true
    try {
        if (isEdit.value && form.id) {
            await updateOne(form.id, toPayload())
        } else {
            await createOne(toPayload())
        }

        dialog.value = false
        resetForm()
        await loadItems()
    } finally {
        saving.value = false
    }
}

const removeItem = async (item) => {
    if (!confirm(`Удалить "${item.name}"?`)) return
    await deleteOne(item.id)
    if (selectedEntity.value?.id === item.id) {
        selectedEntity.value = null
    }
    await loadItems()
}

const showEntity = async (item) => {
    selectedEntity.value = await getOne(item.id)
}

const onOptionsUpdate = async (options) => {
    page.value = options.page
    itemsPerPage.value = options.itemsPerPage
    sortBy.value = options.sortBy
    await loadItems()
}

const pageLabel = (pageNumber) => {
    const marker = pageMarkers.value.find(p => p.page === pageNumber)
    return marker?.first_name || ''
}

watch(
    () => ({ ...filters }),
    async () => {
        page.value = 1
        await loadItems()
    },
    { deep: true }
)

onMounted(loadItems)
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col cols="8">
                <v-card>
                    <v-card-title class="d-flex align-center ga-3 flex-wrap">
                        <span>Entities</span>

                        <v-spacer />

                        <v-btn color="primary" @click="openCreate">
                            Добавить
                        </v-btn>

                        <v-btn
                            :variant="groupByCities ? 'flat' : 'outlined'"
                            color="secondary"
                            @click="groupByCities = !groupByCities"
                        >
                            {{ groupByCities ? 'Убрать группировку' : 'Группировать по городам' }}
                        </v-btn>
                    </v-card-title>

                    <v-card-text>
                        <v-row>
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="filters.search"
                                    label="Поиск"
                                    density="comfortable"
                                    clearable
                                    hide-details
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="filters.entity_classification_ids"
                                    :items="classifications"
                                    item-title="name"
                                    item-value="id"
                                    label="Классификации"
                                    multiple
                                    clearable
                                    chips
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="filters.country_ids"
                                    :items="countries"
                                    item-title="name"
                                    item-value="id"
                                    label="Страны"
                                    multiple
                                    clearable
                                    chips
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="filters.city_ids"
                                    :items="citiesOptions"
                                    item-title="name"
                                    item-value="id"
                                    label="Города"
                                    multiple
                                    clearable
                                    chips
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="filters.building_ids"
                                    :items="buildingsOptions"
                                    item-title="name"
                                    item-value="id"
                                    label="Здания"
                                    multiple
                                    clearable
                                    chips
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="filters.email_ids"
                                    :items="emailsOptions"
                                    item-title="email"
                                    item-value="id"
                                    label="Emails"
                                    multiple
                                    clearable
                                    chips
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="filters.telephone_ids"
                                    :items="telephonesOptions"
                                    item-title="number"
                                    item-value="id"
                                    label="Телефоны"
                                    multiple
                                    clearable
                                    chips
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="filters.unit_ids"
                                    :items="unitsOptions"
                                    item-title="name"
                                    item-value="id"
                                    label="Units"
                                    multiple
                                    clearable
                                    chips
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="filters.chat_ids"
                                    :items="chatsOptions"
                                    item-title="name"
                                    item-value="id"
                                    label="Chats"
                                    multiple
                                    clearable
                                    chips
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-btn variant="text" @click="resetFilters(); loadItems()">
                                    Сбросить фильтры
                                </v-btn>
                            </v-col>
                        </v-row>

                        <v-data-table-server
                            :headers="headers"
                            :items="groupedItems"
                            :items-length="totalItems"
                            :loading="loading"
                            :page="page"
                            :items-per-page="itemsPerPage"
                            :sort-by="sortBy"
                            item-value="id"
                            class="mt-4"
                            @update:options="onOptionsUpdate"
                        >
                            <template #item.name="{ item }">
                                <a
                                    href="#"
                                    class="text-decoration-none"
                                    @click.prevent="showEntity(item)"
                                >
                                    {{ item.name }}
                                </a>
                            </template>

                            <template #item.city_names="{ item }">
                                <div v-if="groupByCities" class="d-flex flex-column">
                                    <strong>{{ item.city_names || 'Без города' }}</strong>
                                    <span class="text-medium-emphasis">{{ item.name }}</span>
                                </div>
                                <span v-else>
                  {{ item.city_names || '—' }}
                </span>
                            </template>

                            <template #item.telephones="{ item }">
                                {{ item.telephones_display || '—' }}
                            </template>

                            <template #item.last_purchase_date="{ item }">
                                {{ item.last_purchase_date ? new Date(item.last_purchase_date).toLocaleDateString() : '—' }}
                            </template>

                            <template #item.actions="{ item }">
                                <div class="d-flex ga-2">
                                    <v-btn size="small" variant="text" @click="openEdit(item)">
                                        edit
                                    </v-btn>
                                    <v-btn size="small" variant="text" color="error" @click="removeItem(item)">
                                        del
                                    </v-btn>
                                </div>
                            </template>

                            <template #bottom>
                                <div class="d-flex align-center flex-wrap ga-2 pa-4">
                                    <v-btn
                                        v-for="p in pageMarkers"
                                        :key="p.page"
                                        :variant="page === p.page ? 'flat' : 'outlined'"
                                        size="small"
                                        min-width="44"
                                        @click="page = p.page; loadItems()"
                                    >
                                        <div class="d-flex flex-column align-center">
                                            <span>{{ p.page }}</span>
                                            <span style="font-size: 9px; line-height: 1.1; max-width: 60px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ p.first_name }}
                      </span>
                                        </div>
                                    </v-btn>
                                </div>
                            </template>
                        </v-data-table-server>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="4">
                <slot name="details" :entity="selectedEntity">
                    <v-card min-height="400">
                        <v-card-title>Entity details</v-card-title>
                        <v-card-text v-if="selectedEntity">
                            <div class="mb-2"><strong>ID:</strong> {{ selectedEntity.id }}</div>
                            <div class="mb-2"><strong>Название:</strong> {{ selectedEntity.name }}</div>
                            <div class="mb-2"><strong>INN:</strong> {{ selectedEntity.INN || '—' }}</div>
                            <div class="mb-2"><strong>OGRN:</strong> {{ selectedEntity.OGRN || '—' }}</div>
                            <div class="mb-2"><strong>Классификация:</strong> {{ selectedEntity.classification?.name || '—' }}</div>
                            <div class="mb-2"><strong>Страна:</strong> {{ selectedEntity.country?.name || '—' }}</div>
                            <div class="mb-2"><strong>Города:</strong> {{ selectedEntity.cities?.map(i => i.name).join(', ') || '—' }}</div>
                            <div class="mb-2"><strong>Здания:</strong> {{ selectedEntity.buildings?.map(i => i.name).join(', ') || '—' }}</div>
                            <div class="mb-2"><strong>Email:</strong> {{ selectedEntity.emails?.map(i => i.email).join(', ') || '—' }}</div>
                            <div class="mb-2"><strong>Телефоны:</strong> {{ formatPhones(selectedEntity.telephones || []) || '—' }}</div>
                            <div class="mb-2"><strong>Units:</strong> {{ selectedEntity.units?.map(i => i.name).join(', ') || '—' }}</div>
                            <div class="mb-2"><strong>Chats:</strong> {{ selectedEntity.chats?.map(i => i.name).join(', ') || '—' }}</div>
                            <div class="mb-2"><strong>Продаж:</strong> {{ selectedEntity.sales_count ?? 0 }}</div>
                            <div class="mb-2">
                                <strong>Последний purchase:</strong>
                                {{ selectedEntity.sales_max_date ? new Date(selectedEntity.sales_max_date).toLocaleString() : '—' }}
                            </div>
                        </v-card-text>

                        <v-card-text v-else class="text-medium-emphasis">
                            Выбери entity в таблице
                        </v-card-text>
                    </v-card>
                </slot>
            </v-col>
        </v-row>

        <v-dialog v-model="dialog" max-width="900">
            <v-card>
                <v-card-title>
                    {{ isEdit ? 'Редактирование Entity' : 'Новая Entity' }}
                </v-card-title>

                <v-card-text>
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.name" label="Название" />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-text-field v-model="form.INN" label="INN" />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-text-field v-model="form.OGRN" label="OGRN" />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.entity_classification_id"
                                :items="classifications"
                                item-title="name"
                                item-value="id"
                                label="Классификация"
                                clearable
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.country_id"
                                :items="countries"
                                item-title="name"
                                item-value="id"
                                label="Страна"
                                clearable
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.cities"
                                :items="citiesOptions"
                                item-title="name"
                                item-value="id"
                                label="Города"
                                multiple
                                chips
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.buildings"
                                :items="buildingsOptions"
                                item-title="name"
                                item-value="id"
                                label="Здания"
                                multiple
                                chips
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.emails"
                                :items="emailsOptions"
                                item-title="email"
                                item-value="id"
                                label="Emails"
                                multiple
                                chips
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.telephones"
                                :items="telephonesOptions"
                                item-title="number"
                                item-value="id"
                                label="Телефоны"
                                multiple
                                chips
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.units"
                                :items="unitsOptions"
                                item-title="name"
                                item-value="id"
                                label="Units"
                                multiple
                                chips
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.chats"
                                :items="chatsOptions"
                                item-title="name"
                                item-value="id"
                                label="Chats"
                                multiple
                                chips
                            />
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">Отмена</v-btn>
                    <v-btn color="primary" :loading="saving" @click="submit">
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>
