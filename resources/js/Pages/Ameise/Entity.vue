<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import { useHead } from '@unhead/vue'
import { route } from 'ziggy-js'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import EntityDetailCard from '@/Components/Dictionaries/Entities/EntityDetailCard.vue'
import EntityChecksTab from '@/Components/Dictionaries/Entities/EntityChecksTab.vue'
import EntityGeographyPanel from '@/Components/Dictionaries/Entities/EntityGeographyPanel.vue'
import EntityConsumptionsCard from '@/Components/Dictionaries/Entities/EntityConsumptionsCard.vue'
import EntityEmailsTab from '@/Components/Dictionaries/Entities/EntityEmailsTab.vue'
import EntityFormDialog from '@/Components/Dictionaries/Entities/EntityFormDialog.vue'
import EntitySalesCard from '@/Components/Dictionaries/Entities/EntitySalesCard.vue'
import { useEntityApi } from '@/Composables/entities/useEntityApi.js'
import { useEntityForm } from '@/Composables/entities/useEntityForm.js'

const props = defineProps({
    entityId: {
        type: Number,
        default: null,
    },
})

defineOptions({
    layout: VerwalterLayout,
})

const entity = ref(null)
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
const loading = ref(false)
const metaLoading = ref(false)
const metaLoaded = ref(false)
const saving = ref(false)
const deleting = ref(false)
const error = ref(null)
const dialog = ref(false)
const isEdit = ref(false)
const activeTab = ref('overview')
const relatedChecksLoaded = ref(false)
const relatedChecks = ref([])
const relatedChecksLoading = ref(false)
const relatedChecksError = ref(null)
const relatedChecksMeta = ref({
    total_amount: 0,
    items_count: 0,
    project_totals: [],
})

const { getMeta, createOne, updateOne, deleteOne } = useEntityApi()
const { form, resetForm, fillForm, toPayload } = useEntityForm()

const pageTitle = computed(() => entity.value?.name || (props.entityId ? `Entity #${props.entityId}` : 'Новая Entity'))
const emailsCount = computed(() => entity.value?.emails?.length || 0)
const heroEyebrow = computed(() => {
    if (entity.value?.id) {
        return `Entity #${entity.value.id}`
    }

    return props.entityId ? `Entity #${props.entityId}` : 'Новая Entity'
})
const heroSubtitle = computed(() => {
    if (entity.value?.full_name && entity.value.full_name !== entity.value.name) {
        return entity.value.full_name
    }

    if (entity.value) return ''
    return props.entityId ? 'Загрузка карточки контрагента' : 'Создание карточки контрагента'
})
const heroClassification = computed(() => entity.value?.classification?.name || 'Без классификации')

async function fetchEntity() {
    if (!props.entityId) {
        entity.value = null
        resetRelatedChecks()
        openCreate()
        return
    }

    loading.value = true
    error.value = null

    try {
        const { data } = await axios.get(`/api/entities/${props.entityId}`)
        entity.value = data.data || data
        if (activeTab.value === 'checks') await fetchRelatedChecks(entity.value?.id)
    } catch (err) {
        console.error(err)
        error.value = 'Не удалось загрузить Entity.'
    } finally {
        loading.value = false
    }
}

async function loadMeta() {
    metaLoading.value = true

    try {
        meta.value = await getMeta()
        metaLoaded.value = true
    } catch (err) {
        console.error(err)
        error.value = 'Не удалось загрузить справочники Entity.'
    } finally {
        metaLoading.value = false
    }
}

function resetRelatedChecks() {
    relatedChecksLoaded.value = false
    relatedChecks.value = []
    relatedChecksError.value = null
    relatedChecksMeta.value = {
        total_amount: 0,
        items_count: 0,
        project_totals: [],
    }
}

function unpackList(response) {
    return response?.data?.data || response?.data || []
}

async function fetchRelatedChecks(entityId = entity.value?.id) {
    if (relatedChecksLoading.value) return
    if (!entityId) {
        resetRelatedChecks()
        return
    }

    relatedChecksLoading.value = true
    relatedChecksError.value = null

    try {
        const response = await axios.get(route('checks.index'), {
            params: {
                entity_id: entityId,
                sort_by: 'date',
                sort_desc: true,
            },
        })

        relatedChecks.value = unpackList(response)
        relatedChecksLoaded.value = true
        relatedChecksMeta.value = {
            total_amount: Number(response.data?.meta?.total_amount || 0),
            items_count: Number(response.data?.meta?.items_count || 0),
            project_totals: response.data?.meta?.project_totals || [],
        }
    } catch (err) {
        console.error(err)
        relatedChecksError.value = 'Не удалось загрузить связанные checks.'
    } finally {
        relatedChecksLoading.value = false
    }
}

function mergeBuildingMeta(building) {
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

function mergeTelephoneMeta(telephone) {
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

function openCreate() {
    if (!metaLoaded.value && !metaLoading.value) loadMeta()
    resetForm()
    isEdit.value = false
    dialog.value = true
}

function openEdit() {
    if (!entity.value) {
        return
    }

    if (!metaLoaded.value && !metaLoading.value) loadMeta()
    fillForm(entity.value)
    isEdit.value = true
    dialog.value = true
}

async function submit() {
    if (saving.value || metaLoading.value || !metaLoaded.value) return
    saving.value = true
    error.value = null

    try {
        const saved = isEdit.value && form.id
            ? await updateOne(form.id, toPayload())
            : await createOne(toPayload())

        entity.value = saved
        if (activeTab.value === 'checks') await fetchRelatedChecks(saved?.id)
        dialog.value = false
        resetForm()

        if (!isEdit.value && saved?.id) {
            router.visit(route('Ameise.entity.show', saved.id), {
                preserveScroll: true,
            })
        }
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message || 'Не удалось сохранить Entity.'
    } finally {
        saving.value = false
    }
}

async function removeEntity() {
    if (!entity.value?.id || !confirm(`Удалить Entity "${entity.value.name}"?`)) {
        return
    }

    deleting.value = true
    error.value = null

    try {
        await deleteOne(entity.value.id)
        router.visit(route('Ameise.großbuch'))
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message || 'Не удалось удалить Entity.'
    } finally {
        deleting.value = false
    }
}

watch(activeTab, tab => {
    if (tab === 'checks' && !relatedChecksLoaded.value) fetchRelatedChecks()
})

onMounted(() => {
    fetchEntity()
})

useHead({
    title: pageTitle,
})
</script>

<template>
    <v-container fluid class="entity-page pa-4">
        <header class="entity-page__maroon-header mb-3">
            <div class="entity-page__identity">
                <div class="entity-page__heading-meta">
                    <span class="entity-page__eyebrow">{{ heroEyebrow }}</span>
                    <span v-if="entity" class="entity-page__classification">{{ heroClassification }}</span>
                </div>
                <h1>{{ pageTitle }}</h1>
                <p v-if="heroSubtitle" class="entity-page__subtitle">{{ heroSubtitle }}</p>
                <div class="entity-page__actions">
                    <v-btn class="entity-page__action entity-page__action--primary" color="#fff7ed" size="small" rounded="lg" prepend-icon="mdi-plus" @click="openCreate">Новая</v-btn>
                    <v-btn class="entity-page__action entity-page__action--ghost" variant="tonal" color="white" size="small" rounded="lg" prepend-icon="mdi-pencil-outline" :disabled="!entity" @click="openEdit">Редактировать</v-btn>
                    <v-btn class="entity-page__action entity-page__action--ghost" variant="tonal" color="white" size="small" rounded="lg" prepend-icon="mdi-delete-outline" :loading="deleting" :disabled="!entity" @click="removeEntity">Удалить</v-btn>
                    <Link :href="route('Ameise.großbuch')" class="entity-page__back"><v-icon icon="mdi-arrow-left" size="16" /> Grossbuch</Link>
                </div>
            </div>
            <EntityGeographyPanel :entity="entity" />
        </header>

        <v-progress-linear v-if="loading || metaLoading" indeterminate color="#800000" class="mb-3" />

        <v-alert v-if="error" type="error" variant="tonal" class="mb-3">
            {{ error }}
        </v-alert>

        <v-card class="entity-page__tabs mb-3">
            <v-tabs v-model="activeTab" color="#800000" density="compact" class="entity-page__tabbar">
                <v-tab value="overview">Overview</v-tab>
                <v-tab value="emails">Emails {{ emailsCount }}</v-tab>
                <v-tab value="sales">Sales</v-tab>
                <v-tab value="checks">Checks<span v-if="relatedChecksLoaded" class="entity-page__tab-count">{{ relatedChecks.length }}</span></v-tab>
            </v-tabs>
        </v-card>

        <v-tabs-window v-model="activeTab" class="entity-page__window">
            <v-tabs-window-item value="overview">
                <EntityDetailCard :entity="entity" :show-hero="false" :show-geography="false">
                    <template #needs>
                        <EntityConsumptionsCard v-if="entity?.id" :key="entity.id" :entity-id="entity.id" />
                    </template>
                </EntityDetailCard>
            </v-tabs-window-item>

            <v-tabs-window-item value="emails">
                <EntityEmailsTab :entity="entity" />
            </v-tabs-window-item>

            <v-tabs-window-item value="sales">
                <EntitySalesCard :entity="entity" />
            </v-tabs-window-item>
            <v-tabs-window-item value="checks">
                <EntityChecksTab :checks="relatedChecks" :loading="relatedChecksLoading" :error="relatedChecksError" :meta="relatedChecksMeta" @refresh="fetchRelatedChecks()" />
            </v-tabs-window-item>
        </v-tabs-window>

        <EntityFormDialog
            v-model="dialog"
            :loading="saving"
            :preparing="metaLoading"
            :error="dialog ? error || '' : ''"
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
.entity-page { min-height: 100vh; background: #f8f5f1; }
.entity-page__maroon-header { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: start; gap: 20px; padding: 18px 20px; border-radius: 16px; background: linear-gradient(115deg, #3f1d1d, #771c1c); color: #fff; }
.entity-page__identity { min-width: 0; }
.entity-page__heading-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 6px; }
.entity-page__eyebrow { color: #e3c9c5; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; }
.entity-page__classification { padding: 2px 7px; border: 1px solid rgba(255,255,255,0.18); border-radius: 5px; color: #f1dedb; font-size: 0.68rem; }
.entity-page__maroon-header h1 { margin: 0; font-size: clamp(1.35rem, 2.1vw, 1.9rem); font-weight: 750; line-height: 1.2; overflow-wrap: anywhere; }
.entity-page__subtitle { margin: 5px 0 0; color: #efd9d7; font-size: 0.8rem; line-height: 1.4; overflow-wrap: anywhere; }
.entity-page__actions { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-top: 13px; }
.entity-page__action { font-weight: 600; letter-spacing: 0; text-transform: none; }
.entity-page__action--primary { color: #800000 !important; }
.entity-page__action--ghost { background: rgba(255,255,255,0.11) !important; }
.entity-page__back { display: inline-flex; align-items: center; gap: 5px; min-height: 28px; padding: 0 6px; color: #efd9d7; font-size: 0.75rem; text-decoration: none; }
.entity-page__back:hover { color: #fff; text-decoration: underline; }
.entity-page__tabs { overflow: hidden; border: 1px solid #eaddd6; border-radius: 10px; background: #fff; box-shadow: none; }
.entity-page__tabbar { color: #60443c; }
.entity-page__tabbar :deep(.v-tab) { font-size: 0.78rem; text-transform: none; letter-spacing: 0; }
.entity-page__tab-count { margin-left: 6px; padding: 1px 5px; background: #f5ebe5; border-radius: 4px; font-size: 0.65rem; }
.entity-page__window { overflow: visible; }
@media (max-width: 800px) {
    .entity-page__maroon-header { grid-template-columns: 1fr; gap: 14px; padding: 16px; }
}
@media (max-width: 480px) {
    .entity-page { padding: 10px !important; }
    .entity-page__maroon-header { padding: 13px; border-radius: 12px; }
}
</style>
