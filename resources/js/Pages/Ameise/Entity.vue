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

const { getMeta, createOne, updateOne, deleteOne } = useEntityApi()
const { form, resetForm, fillForm, toPayload } = useEntityForm()

const pageTitle = computed(() => entity.value?.name || (props.entityId ? `Entity #${props.entityId}` : 'Новая Entity'))
const emailsCount = computed(() => entity.value?.emails?.length || 0)

async function fetchEntity() {
    if (!props.entityId) {
        entity.value = null
        openCreate()
        return
    }

    loading.value = true
    error.value = null

    try {
        const { data } = await axios.get(`/api/entities/${props.entityId}`)
        entity.value = data.data || data
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
        <header class="entity-page__header mb-4">
            <div class="entity-page__heading">
                <div v-if="entity" class="entity-page__kicker">
                    Entity #{{ entity.id }}
                </div>
                <h1>{{ pageTitle }}</h1>

                <div class="entity-page__subtitle">
                    {{ entity?.classification?.name || 'Карточка контрагента' }}
                </div>
            </div>

            <div class="entity-page__actions">
                <v-btn color="#800000" rounded="lg" prepend-icon="mdi-plus" @click="openCreate">
                    Новая
                </v-btn>

                <v-btn
                    variant="tonal"
                    color="#800000"
                    rounded="lg"
                    prepend-icon="mdi-pencil"
                    :disabled="!entity"
                    @click="openEdit"
                >
                    Редактировать
                </v-btn>

                <v-btn
                    variant="tonal"
                    color="error"
                    rounded="lg"
                    prepend-icon="mdi-delete"
                    :loading="deleting"
                    :disabled="!entity"
                    @click="removeEntity"
                >
                    Удалить
                </v-btn>

                <Link :href="route('Ameise.großbuch')" class="entity-page__back">
                    <v-btn variant="text" color="#800000" rounded="lg" prepend-icon="mdi-arrow-left">
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
                <EntityDetailCard :entity="entity" />
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

.entity-page__header {
    display: flex;
    align-items: flex-start;
    flex-direction: column;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 18px;
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.82);
    box-shadow: 0 16px 36px rgba(48, 20, 10, 0.08);
    backdrop-filter: blur(8px);
}

.entity-page__heading {
    min-width: 0;
}

.entity-page__kicker {
    margin-bottom: 4px;
    color: #8b6b61;
    font-size: 0.72rem;
    font-weight: 950;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.entity-page__header h1 {
    margin: 0;
    color: #35160d;
    font-size: clamp(1.4rem, 2vw, 2.4rem);
    font-weight: 950;
    line-height: 1.06;
}

.entity-page__subtitle {
    margin-top: 4px;
    color: #7c5148;
    font-size: 0.86rem;
    font-weight: 800;
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
    .entity-page__actions {
        justify-content: flex-start;
    }
}
</style>
