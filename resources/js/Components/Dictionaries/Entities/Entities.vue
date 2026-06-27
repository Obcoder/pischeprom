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
const detailDrawerOpened = ref(false)

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

const mergeBuildingMeta = (building) => {
    if (!building?.id) {
        return
    }

    meta.value.buildings = [
        building,
        ...meta.value.buildings.filter(item => Number(item.id) !== Number(building.id)),
    ].sort((a, b) => {
        const cityComparison = (a.city?.name || '').localeCompare(b.city?.name || '', 'ru')

        if (cityComparison !== 0) {
            return cityComparison
        }

        return (a.address || '').localeCompare(b.address || '', 'ru')
    })
}

const mergeTelephoneMeta = (telephone) => {
    if (!telephone?.id) {
        return
    }

    meta.value.telephones = [
        telephone,
        ...meta.value.telephones.filter(item => Number(item.id) !== Number(telephone.id)),
    ].sort((a, b) => {
        return String(a.number || '').localeCompare(String(b.number || ''), 'ru')
    })
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
            detailDrawerOpened.value = false
        }

        await loadItems()
    } catch (error) {
        console.error('removeItem error:', error?.response?.data || error)
    }
}

const showEntity = async (item) => {
    try {
        selectedEntity.value = await getOne(item.id)
        detailDrawerOpened.value = true
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
    <v-container fluid class="pa-2 w-100" style="max-width: 100%">
        <v-row class="w-100 ma-0">
            <v-col cols="12" class="pa-1 d-flex">
                <EntityTable
                    class="w-100"
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
        </v-row>

        <transition name="entity-drawer-backdrop">
            <div
                v-if="detailDrawerOpened"
                class="entity-detail-overlay"
                @click.self="detailDrawerOpened = false"
            >
                <transition name="entity-drawer" appear>
                    <aside class="entity-detail-drawer">
                        <div class="entity-detail-drawer__toolbar">
                            <v-btn
                                icon="mdi-close"
                                variant="text"
                                @click="detailDrawerOpened = false"
                            />
                        </div>

                        <slot name="details" :entity="selectedEntity">
                            <EntityDetailCard
                                class="w-100"
                                :entity="selectedEntity"
                            />
                        </slot>
                    </aside>
                </transition>
            </div>
        </transition>

        <EntityFormDialog
            v-model="dialog"
            :loading="saving"
            :is-edit="isEdit"
            :form="form"
            :meta="meta"
            @submit="submit"
            @building-created="mergeBuildingMeta"
            @telephone-created="mergeTelephoneMeta"
        />
    </v-container>
</template>

<style scoped>
.w-100 {
    width: 100%;
    max-width: 100%;
}

.entity-detail-overlay {
    position: fixed;
    inset: 0;
    z-index: 2400;
    display: flex;
    justify-content: flex-end;
    background: rgba(20, 18, 15, 0.28);
    backdrop-filter: blur(2px);
}

.entity-detail-drawer {
    width: min(620px, 94vw);
    height: 100%;
    padding: 18px;
    overflow: auto;
    background:
        radial-gradient(circle at 10% 0%, rgba(128, 0, 0, 0.12), transparent 34%),
        linear-gradient(180deg, #fffdf8 0%, #f7efe2 100%);
    box-shadow: -28px 0 70px rgba(23, 16, 10, 0.26);
}

.entity-detail-drawer__toolbar {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 8px;
}

.entity-drawer-enter-active,
.entity-drawer-leave-active {
    transition: transform 0.58s cubic-bezier(0.19, 1, 0.22, 1), opacity 0.42s ease;
}

.entity-drawer-enter-from,
.entity-drawer-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.entity-drawer-backdrop-enter-active,
.entity-drawer-backdrop-leave-active {
    transition: opacity 0.42s ease;
}

.entity-drawer-backdrop-enter-from,
.entity-drawer-backdrop-leave-to {
    opacity: 0;
}
</style>
