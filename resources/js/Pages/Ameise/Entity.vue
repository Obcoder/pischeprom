<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import { useHead } from '@vueuse/head'
import { route } from 'ziggy-js'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import EntityDetailCard from '@/Components/Dictionaries/Entities/EntityDetailCard.vue'
import EntityFormDialog from '@/Components/Dictionaries/Entities/EntityFormDialog.vue'
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

const { getMeta, createOne, updateOne, deleteOne } = useEntityApi()
const { form, resetForm, fillForm, toPayload } = useEntityForm()

const pageTitle = computed(() => entity.value?.name || (props.entityId ? `Entity #${props.entityId}` : 'Новая Entity'))

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
        <div class="entity-page__toolbar mb-4">
            <div>
                <div class="text-caption text-medium-emphasis">CRM Entity</div>
                <h1>{{ pageTitle }}</h1>
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
        </div>

        <v-progress-linear v-if="loading || metaLoading" indeterminate color="#800000" class="mb-3" />

        <v-alert v-if="error" type="error" variant="tonal" class="mb-3">
            {{ error }}
        </v-alert>

        <EntityDetailCard :entity="entity" />

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
.entity-page {
    min-height: 100vh;
    background:
        radial-gradient(circle at 8% 0%, rgba(128, 0, 0, 0.10), transparent 28%),
        linear-gradient(135deg, #fffaf4 0%, #f7f2eb 48%, #fff 100%);
}

.entity-page__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 18px;
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.82);
    box-shadow: 0 16px 36px rgba(48, 20, 10, 0.08);
    backdrop-filter: blur(8px);
}

.entity-page__toolbar h1 {
    margin: 0;
    color: #35160d;
    font-size: clamp(1.4rem, 2vw, 2.4rem);
    font-weight: 950;
}

.entity-page__back {
    text-decoration: none;
}

.entity-page__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

@media (max-width: 760px) {
    .entity-page__toolbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .entity-page__actions {
        justify-content: flex-start;
    }
}
</style>
