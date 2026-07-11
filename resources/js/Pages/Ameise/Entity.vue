<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import { useHead } from '@vueuse/head'
import { route } from 'ziggy-js'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import EntityDetailCard from '@/Components/Dictionaries/Entities/EntityDetailCard.vue'
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
const saving = ref(false)
const deleting = ref(false)
const error = ref(null)
const dialog = ref(false)
const isEdit = ref(false)
const activeTab = ref('overview')
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
    if (entity.value?.full_name) {
        return entity.value.full_name
    }

    if (entity.value?.classification?.name) {
        return entity.value.classification.name
    }

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
        await fetchRelatedChecks(entity.value?.id)
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
    } catch (err) {
        console.error(err)
        error.value = 'Не удалось загрузить справочники Entity.'
    } finally {
        metaLoading.value = false
    }
}

function resetRelatedChecks() {
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
    resetForm()
    isEdit.value = false
    dialog.value = true
}

function openEdit() {
    if (!entity.value) {
        return
    }

    fillForm(entity.value)
    isEdit.value = true
    dialog.value = true
}

async function submit() {
    saving.value = true
    error.value = null

    try {
        const saved = isEdit.value && form.id
            ? await updateOne(form.id, toPayload())
            : await createOne(toPayload())

        entity.value = saved
        await fetchRelatedChecks(saved?.id)
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

onMounted(async () => {
    await loadMeta()
    await fetchEntity()
})

useHead({
    title: pageTitle,
})
</script>

<template>
    <v-container fluid class="entity-page pa-4">
        <header class="entity-page__maroon-header mb-4">
            <div class="entity-page__maroon-main">
                <div class="entity-page__heading">
                    <div class="entity-page__eyebrow">
                        {{ heroEyebrow }}
                    </div>

                    <h1>{{ pageTitle }}</h1>

                    <p class="entity-page__subtitle">
                        {{ heroSubtitle }}
                    </p>
                </div>

                <v-chip
                    v-if="entity"
                    color="#800000"
                    variant="flat"
                    size="small"
                    class="entity-page__classification"
                >
                    {{ heroClassification }}
                </v-chip>
            </div>

            <div class="entity-page__actions">
                <v-btn
                    class="entity-page__action entity-page__action--primary"
                    color="#fff7ed"
                    rounded="lg"
                    prepend-icon="mdi-plus"
                    @click="openCreate"
                >
                    Новая
                </v-btn>

                <v-btn
                    class="entity-page__action entity-page__action--ghost"
                    variant="tonal"
                    color="white"
                    rounded="lg"
                    prepend-icon="mdi-pencil"
                    :disabled="!entity"
                    @click="openEdit"
                >
                    Редактировать
                </v-btn>

                <v-btn
                    class="entity-page__action entity-page__action--ghost"
                    variant="tonal"
                    color="white"
                    rounded="lg"
                    prepend-icon="mdi-delete"
                    :loading="deleting"
                    :disabled="!entity"
                    @click="removeEntity"
                >
                    Удалить
                </v-btn>

                <Link :href="route('Ameise.großbuch')" class="entity-page__back">
                    <v-btn
                        class="entity-page__action entity-page__action--text"
                        variant="text"
                        color="white"
                        rounded="lg"
                        prepend-icon="mdi-arrow-left"
                    >
                        Grossbuch
                    </v-btn>
                </Link>
            </div>
        </header>

        <v-progress-linear v-if="loading || metaLoading" indeterminate color="#800000" class="mb-3" />

        <v-alert v-if="error" type="error" variant="tonal" class="mb-3">
            {{ error }}
        </v-alert>

        <v-card class="entity-page__tabs mb-4">
            <v-tabs v-model="activeTab" color="#800000" density="compact" class="entity-page__tabbar">
                <v-tab value="overview">Overview</v-tab>
                <v-tab value="emails">Emails {{ emailsCount }}</v-tab>
                <v-tab value="sales">Sales</v-tab>
            </v-tabs>
        </v-card>

        <v-tabs-window v-model="activeTab" class="entity-page__window">
            <v-tabs-window-item value="overview">
                <EntityDetailCard
                    :entity="entity"
                    :show-hero="false"
                    show-checks
                    :checks="relatedChecks"
                    :checks-loading="relatedChecksLoading"
                    :checks-error="relatedChecksError"
                    :checks-meta="relatedChecksMeta"
                />
            </v-tabs-window-item>

            <v-tabs-window-item value="emails">
                <EntityEmailsTab :entity="entity" />
            </v-tabs-window-item>

            <v-tabs-window-item value="sales">
                <EntitySalesCard :entity="entity" />
            </v-tabs-window-item>
        </v-tabs-window>

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
.entity-page {
    min-height: 100vh;
    background:
        radial-gradient(circle at 8% 0%, rgba(128, 0, 0, 0.10), transparent 28%),
        linear-gradient(135deg, #fffaf4 0%, #f7f2eb 48%, #fff 100%);
}

.entity-page__maroon-header {
    display: grid;
    gap: 18px;
    padding: 24px;
    border: 1px solid rgba(128, 0, 0, 0.12);
    border-radius: 22px;
    background:
        radial-gradient(circle at 92% 0%, rgba(128, 0, 0, 0.22), transparent 30%),
        linear-gradient(135deg, #3f1d1d 0%, #7f1d1d 100%);
    box-shadow: 0 18px 44px rgba(48, 20, 10, 0.12);
    color: #fff;
}

.entity-page__maroon-main {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
}

.entity-page__heading {
    min-width: 0;
}

.entity-page__eyebrow {
    margin-bottom: 8px;
    color: rgba(255, 255, 255, 0.68);
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.entity-page__maroon-header h1 {
    margin: 0;
    color: #fff;
    font-size: 2.25rem;
    font-weight: 950;
    line-height: 1.06;
    overflow-wrap: anywhere;
}

.entity-page__subtitle {
    max-width: 860px;
    margin: 10px 0 0;
    color: rgba(255, 255, 255, 0.78);
    font-size: 0.86rem;
    font-weight: 800;
    line-height: 1.55;
}

.entity-page__classification {
    flex: 0 0 auto;
    background: rgba(255, 255, 255, 0.16) !important;
    color: #fff !important;
}

.entity-page__back {
    text-decoration: none;
}

.entity-page__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: 8px;
}

.entity-page__action {
    font-weight: 900;
    letter-spacing: 0.04em;
}

.entity-page__action--primary {
    color: #800000 !important;
}

.entity-page__action--ghost {
    background: rgba(255, 255, 255, 0.14) !important;
    color: #fff !important;
}

.entity-page__action--text {
    color: #fff !important;
}

.entity-page__tabs {
    overflow: hidden;
    border: 1px solid rgba(128, 0, 0, 0.10);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.86);
    box-shadow: 0 12px 28px rgba(48, 20, 10, 0.06);
}

.entity-page__tabbar {
    color: #3f1d1d;
}

.entity-page__window {
    overflow: visible;
}

@media (max-width: 760px) {
    .entity-page__maroon-header {
        padding: 18px;
    }

    .entity-page__maroon-main {
        display: grid;
    }

    .entity-page__maroon-header h1 {
        font-size: 1.7rem;
    }

    .entity-page__actions {
        justify-content: flex-start;
    }
}
</style>
