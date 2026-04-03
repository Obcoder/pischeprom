<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import EntityTable from '@/Components/Dictionaries/Entities/EntityTable.vue'
import EntityFormDialog from '@/Components/Dictionaries/Entities/EntityFormDialog.vue'
import EntityDetailCard from '@/Components/Dictionaries/Entities/EntityDetailCard.vue'

import { useEntityApi } from '@/Composables/entities/useEntityApi.js'
import { useEntityFilters } from '@/Composables/entities/useEntityFilters.js'
import { useEntityForm } from '@/Composables/entities/useEntityForm.js'
import { usePhoneFormatter } from '@/Composables/entities/usePhoneFormatter.js'

const { getMeta, getList, getOne, createOne, updateOne, deleteOne } = useEntityApi()
const { filters, resetFilters } = useEntityFilters()
const { form, resetForm, fillForm, toPayload } = useEntityForm()
const { formatPhones } = usePhoneFormatter()

const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const isEdit = ref(false)
const selectedEntity = ref(null)
const groupByCities = ref(false)
const filtersOpened = ref(false)

const meta = ref({
    classifications: [],
    countries: [],
    cities: [],
    buildings: [],
    emails: [],
    telephones: [],
    units: [],
    chats: [],
})

const items = ref([])
const totalItems = ref(0)
const pageMarkers = ref([])

const page = ref(1)
const itemsPerPage = ref(60)
const sortBy = ref([{ key: 'sales_count', order: 'desc' }])

let debounceTimer = null

const tableItems = computed(() => {
    const mapped = items.value.map(item => ({
        ...item,
        classification_name: item.classification?.name ?? '',
        country_name: item.country?.name ?? '',
        city_names: item.cities?.map(c => c.name).join(', ') ?? '',
        telephones_display: formatPhones(item.telephones ?? []),
    }))

    if (!groupByCities.value) {
        return mapped
    }

    return [...mapped].sort((a, b) => {
        const aCity = a.city_names || 'Без города'
        const bCity = b.city_names || 'Без города'
        return aCity.localeCompare(bCity, 'ru')
    })
})

const loadMeta = async () => {
    try {
        meta.value = await getMeta()
    } catch (error) {
        console.error('loadMeta error:', error?.response?.data || error)
    }
}

const loadItems = async () => {
    loading.value = true

    try {
        const sort = sortBy.value?.[0] ?? { key: 'sales_count', order: 'desc' }

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

        items.value = Array.isArray(response.data) ? response.data : []
        totalItems.value = response.meta?.total ?? 0
        pageMarkers.value = response.meta?.page_markers ?? []
    } catch (error) {
        console.error('loadItems error:', error?.response?.data || error)
    } finally {
        loading.value = false
    }
}

const debouncedLoad = () => {
    clearTimeout(debounceTimer)

    debounceTimer = setTimeout(() => {
        page.value = 1
        loadItems()
    }, 350)
}

const openCreate = () => {
    resetForm()
    isEdit.value = false
    dialog.value = true
}

const openEdit = async (item) => {
    try {
        const entity = await getOne(item.id)
        fillForm(entity)
        isEdit.value = true
        dialog.value = true
    } catch (error) {
        console.error('openEdit error:', error?.response?.data || error)
    }
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
    } catch (error) {
        console.error('submit error:', error?.response?.data || error)
    } finally {
        saving.value = false
    }
}

const removeItem = async (item) => {
    if (!confirm(`Удалить "${item.name}"?`)) {
        return
    }

    try {
        await deleteOne(item.id)

        if (selectedEntity.value?.id === item.id) {
            selectedEntity.value = null
        }

        await loadItems()
    } catch (error) {
        console.error('removeItem error:', error?.response?.data || error)
    }
}

const showEntity = async (item) => {
    try {
        selectedEntity.value = await getOne(item.id)
    } catch (error) {
        console.error('showEntity error:', error?.response?.data || error)
    }
}

const handleResetFilters = async () => {
    resetFilters()
    page.value = 1
    await loadItems()
}

watch(
    () => ({
        search: filters.search,
        entity_classification_ids: [...filters.entity_classification_ids],
        country_ids: [...filters.country_ids],
        city_ids: [...filters.city_ids],
        building_ids: [...filters.building_ids],
        email_ids: [...filters.email_ids],
        telephone_ids: [...filters.telephone_ids],
        unit_ids: [...filters.unit_ids],
        chat_ids: [...filters.chat_ids],
    }),
    () => {
        debouncedLoad()
    },
    { deep: true }
)

watch(itemsPerPage, async () => {
    page.value = 1
    await loadItems()
})

watch(
    sortBy,
    async () => {
        page.value = 1
        await loadItems()
    },
    { deep: true }
)

watch(page, async () => {
    await loadItems()
})

onMounted(async () => {
    await loadMeta()
    await loadItems()
})
</script>

<template>
    <v-container fluid class="entities-page pa-2">
        <v-row class="fill-height ma-0">
            <v-col cols="12" md="9" class="pa-1 d-flex entity-table-col">
                <EntityTable
                    class="flex-grow-1"
                    :items="tableItems"
                    :loading="loading"
                    :total-items="totalItems"
                    :page="page"
                    :items-per-page="itemsPerPage"
                    :sort-by="sortBy"
                    :page-markers="pageMarkers"
                    :filters="filters"
                    :meta="meta"
                    :group-by-cities="groupByCities"
                    :filters-opened="filtersOpened"
                    @update:page="page = $event"
                    @update:itemsPerPage="itemsPerPage = $event"
                    @update:sortBy="sortBy = $event"
                    @update:groupByCities="groupByCities = $event"
                    @update:filtersOpened="filtersOpened = $event"
                    @show="showEntity"
                    @create="openCreate"
                    @edit="openEdit"
                    @delete="removeItem"
                    @resetFilters="handleResetFilters"
                    @reload="loadItems"
                />
            </v-col>

            <v-col cols="12" md="3" class="pa-1 d-flex entity-detail-col">
                <slot name="details" :entity="selectedEntity">
                    <EntityDetailCard
                        class="flex-grow-1"
                        :entity="selectedEntity"
                    />
                </slot>
            </v-col>
        </v-row>

        <EntityFormDialog
            v-model="dialog"
            :loading="saving"
            :is-edit="isEdit"
            :form="form"
            :meta="meta"
            @submit="submit"
        />
    </v-container>
</template>

<style scoped>
.entities-page {
    height: calc(100vh - 72px);
    overflow: hidden;
}

.entity-table-col,
.entity-detail-col {
    min-height: 0;
}

:deep(.v-col) {
    min-height: 0;
}
</style>
