<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useHead } from '@vueuse/head'
import { route } from 'ziggy-js'
import axios from 'axios'
import { VueDraggableNext as Draggable } from 'vue-draggable-next'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({
    layout: VerwalterLayout,
})

const loading = ref(false)
const saving = ref(false)
const selectedPipelineId = ref(null)
const unitOptions = ref([])
const unitSearch = ref('')

const board = reactive({
    pipelines: [],
    pipeline: null,
})

const snackbar = reactive({
    show: false,
    text: '',
    color: 'success',
})

const pipelineDialog = ref(false)
const stageDialog = ref(false)
const cardDialog = ref(false)
const editingPipeline = ref(null)
const editingStage = ref(null)
const editingCard = ref(null)

const pipelineForm = reactive({
    name: '',
    description: '',
    is_active: true,
    sort_order: null,
})

const stageForm = reactive({
    name: '',
    color: '#800000',
    description: '',
    sort_order: null,
})

const cardForm = reactive({
    unit_id: null,
    supplier_pipeline_stage_id: null,
    title: '',
    notes: '',
    next_contact_at: '',
})

const currentPipeline = computed(() => board.pipeline)
const stages = computed(() => currentPipeline.value?.stages || [])
const pipelineItems = computed(() => board.pipelines || [])
const hasPipeline = computed(() => Boolean(currentPipeline.value?.id))
const totalCards = computed(() => stages.value.reduce((sum, stage) => sum + (stage.cards?.length || 0), 0))

useHead({
    title: 'Supplier Work Board - Ameise',
    meta: [
        {
            name: 'description',
            content: 'Воронки и рабочая доска поставщиков Ameise',
        },
    ],
})

function notify(text, color = 'success') {
    snackbar.text = text
    snackbar.color = color
    snackbar.show = true
}

function normalizePipeline(pipeline) {
    if (!pipeline) {
        return null
    }

    return {
        ...pipeline,
        stages: (pipeline.stages || []).map((stage) => ({
            ...stage,
            cards: stage.cards || [],
        })),
    }
}

async function fetchBoard(pipelineId = selectedPipelineId.value) {
    loading.value = true

    try {
        const response = await axios.get('/api/supplier-work/board', {
            params: pipelineId ? { pipeline_id: pipelineId } : {},
        })

        board.pipelines = response.data.pipelines || []
        board.pipeline = normalizePipeline(response.data.pipeline)
        selectedPipelineId.value = board.pipeline?.id || board.pipelines[0]?.id || null
    } catch (error) {
        console.error(error)
        notify('Не удалось загрузить доску поставщиков.', 'error')
    } finally {
        loading.value = false
    }
}

async function fetchUnitOptions(search = unitSearch.value) {
    try {
        const response = await axios.get('/api/supplier-work/unit-options', {
            params: {
                search,
                limit: 60,
            },
        })

        unitOptions.value = response.data || []
    } catch (error) {
        console.error(error)
        notify('Не удалось загрузить Units.', 'error')
    }
}

function resetPipelineForm() {
    editingPipeline.value = null
    pipelineForm.name = ''
    pipelineForm.description = ''
    pipelineForm.is_active = true
    pipelineForm.sort_order = null
}

function openPipelineDialog(pipeline = null) {
    resetPipelineForm()

    if (pipeline) {
        editingPipeline.value = pipeline
        pipelineForm.name = pipeline.name || ''
        pipelineForm.description = pipeline.description || ''
        pipelineForm.is_active = Boolean(pipeline.is_active)
        pipelineForm.sort_order = pipeline.sort_order ?? null
    }

    pipelineDialog.value = true
}

async function savePipeline() {
    saving.value = true

    const payload = {
        name: pipelineForm.name,
        description: pipelineForm.description || null,
        is_active: pipelineForm.is_active,
        sort_order: pipelineForm.sort_order ?? undefined,
    }

    try {
        if (editingPipeline.value?.id) {
            await axios.patch(`/api/supplier-work/pipelines/${editingPipeline.value.id}`, payload)
            notify('Воронка обновлена.')
        } else {
            const response = await axios.post('/api/supplier-work/pipelines', payload)
            selectedPipelineId.value = response.data.id
            notify('Воронка создана.')
        }

        pipelineDialog.value = false
        await fetchBoard(selectedPipelineId.value)
    } catch (error) {
        console.error(error)
        notify(error.response?.data?.message || 'Не удалось сохранить воронку.', 'error')
    } finally {
        saving.value = false
    }
}

async function deletePipeline(pipeline) {
    if (!pipeline?.id || !window.confirm(`Удалить воронку "${pipeline.name}" вместе со стадиями и карточками?`)) {
        return
    }

    saving.value = true

    try {
        await axios.delete(`/api/supplier-work/pipelines/${pipeline.id}`)
        selectedPipelineId.value = null
        notify('Воронка удалена.')
        await fetchBoard()
    } catch (error) {
        console.error(error)
        notify('Не удалось удалить воронку.', 'error')
    } finally {
        saving.value = false
    }
}

function resetStageForm() {
    editingStage.value = null
    stageForm.name = ''
    stageForm.color = '#800000'
    stageForm.description = ''
    stageForm.sort_order = null
}

function openStageDialog(stage = null) {
    resetStageForm()

    if (stage) {
        editingStage.value = stage
        stageForm.name = stage.name || ''
        stageForm.color = stage.color || '#800000'
        stageForm.description = stage.description || ''
        stageForm.sort_order = stage.sort_order ?? null
    }

    stageDialog.value = true
}

async function saveStage() {
    if (!currentPipeline.value?.id) {
        notify('Сначала создайте воронку.', 'error')
        return
    }

    saving.value = true

    const payload = {
        name: stageForm.name,
        color: stageForm.color || null,
        description: stageForm.description || null,
        sort_order: stageForm.sort_order ?? undefined,
    }

    try {
        if (editingStage.value?.id) {
            await axios.patch(`/api/supplier-work/stages/${editingStage.value.id}`, payload)
            notify('Стадия обновлена.')
        } else {
            await axios.post(`/api/supplier-work/pipelines/${currentPipeline.value.id}/stages`, payload)
            notify('Стадия создана.')
        }

        stageDialog.value = false
        await fetchBoard(currentPipeline.value.id)
    } catch (error) {
        console.error(error)
        notify(error.response?.data?.message || 'Не удалось сохранить стадию.', 'error')
    } finally {
        saving.value = false
    }
}

async function deleteStage(stage) {
    if (!stage?.id || !window.confirm(`Удалить стадию "${stage.name}"? Карточки будут перенесены в первую доступную стадию.`)) {
        return
    }

    saving.value = true

    try {
        await axios.delete(`/api/supplier-work/stages/${stage.id}`)
        notify('Стадия удалена.')
        await fetchBoard(currentPipeline.value.id)
    } catch (error) {
        console.error(error)
        notify('Не удалось удалить стадию.', 'error')
    } finally {
        saving.value = false
    }
}

async function reorderStages(stage, direction) {
    const index = stages.value.findIndex((item) => item.id === stage.id)
    const nextIndex = index + direction

    if (index < 0 || nextIndex < 0 || nextIndex >= stages.value.length) {
        return
    }

    const ids = stages.value.map((item) => item.id)
    const temp = ids[index]
    ids[index] = ids[nextIndex]
    ids[nextIndex] = temp

    saving.value = true

    try {
        await axios.patch(`/api/supplier-work/pipelines/${currentPipeline.value.id}/stages/reorder`, {
            stage_ids: ids,
        })

        await fetchBoard(currentPipeline.value.id)
    } catch (error) {
        console.error(error)
        notify('Не удалось изменить порядок стадий.', 'error')
    } finally {
        saving.value = false
    }
}

function resetCardForm() {
    editingCard.value = null
    cardForm.unit_id = null
    cardForm.supplier_pipeline_stage_id = stages.value[0]?.id || null
    cardForm.title = ''
    cardForm.notes = ''
    cardForm.next_contact_at = ''
}

async function openCardDialog(stage = null, card = null) {
    resetCardForm()
    await fetchUnitOptions()

    if (stage?.id) {
        cardForm.supplier_pipeline_stage_id = stage.id
    }

    if (card) {
        editingCard.value = card
        cardForm.unit_id = card.unit_id
        cardForm.supplier_pipeline_stage_id = card.supplier_pipeline_stage_id
        cardForm.title = card.title || ''
        cardForm.notes = card.notes || ''
        cardForm.next_contact_at = card.next_contact_at ? String(card.next_contact_at).slice(0, 16) : ''

        if (card.unit && !unitOptions.value.some((unit) => unit.id === card.unit.id)) {
            unitOptions.value = [card.unit, ...unitOptions.value]
        }
    }

    cardDialog.value = true
}

async function saveCard() {
    if (!currentPipeline.value?.id) {
        notify('Сначала создайте воронку.', 'error')
        return
    }

    saving.value = true

    const payload = {
        unit_id: cardForm.unit_id,
        supplier_pipeline_stage_id: cardForm.supplier_pipeline_stage_id,
        title: cardForm.title || null,
        notes: cardForm.notes || null,
        next_contact_at: cardForm.next_contact_at || null,
    }

    try {
        if (editingCard.value?.id) {
            await axios.patch(`/api/supplier-work/cards/${editingCard.value.id}`, payload)
            notify('Карточка обновлена.')
        } else {
            await axios.post(`/api/supplier-work/pipelines/${currentPipeline.value.id}/cards`, payload)
            notify('Поставщик добавлен в воронку.')
        }

        cardDialog.value = false
        await fetchBoard(currentPipeline.value.id)
    } catch (error) {
        console.error(error)
        notify(error.response?.data?.message || 'Не удалось сохранить карточку.', 'error')
    } finally {
        saving.value = false
    }
}

async function deleteCard(card) {
    const title = card.title || card.unit?.name || 'карточку'

    if (!card?.id || !window.confirm(`Удалить "${title}" из воронки?`)) {
        return
    }

    saving.value = true

    try {
        await axios.delete(`/api/supplier-work/cards/${card.id}`)
        notify('Карточка удалена.')
        await fetchBoard(currentPipeline.value.id)
    } catch (error) {
        console.error(error)
        notify('Не удалось удалить карточку.', 'error')
    } finally {
        saving.value = false
    }
}

async function onCardChange(stage, event) {
    const changedCard = event.added?.element || event.moved?.element

    if (!changedCard?.id) {
        return
    }

    saving.value = true

    try {
        await axios.patch(`/api/supplier-work/cards/${changedCard.id}/move`, {
            supplier_pipeline_stage_id: stage.id,
            ordered_card_ids: (stage.cards || []).map((card) => card.id),
        })
    } catch (error) {
        console.error(error)
        notify('Не удалось переместить карточку. Доска будет восстановлена.', 'error')
        await fetchBoard(currentPipeline.value.id)
    } finally {
        saving.value = false
    }
}

function onPipelineSelected(pipelineId) {
    selectedPipelineId.value = pipelineId
    fetchBoard(pipelineId)
}

function unitTitle(unit) {
    if (!unit) {
        return 'Unit'
    }

    return unit.name || `Unit #${unit.id}`
}

function cardTitle(card) {
    return card.title || unitTitle(card.unit)
}

function shortText(text, limit = 90) {
    const value = String(text || '').trim()

    if (!value) {
        return ''
    }

    return value.length > limit ? `${value.slice(0, limit)}...` : value
}

function formatDate(value) {
    if (!value) {
        return null
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

onMounted(async () => {
    await Promise.all([
        fetchBoard(),
        fetchUnitOptions(),
    ])
})
</script>

<template>
    <v-container fluid class="supplier-work pa-4">
        <div class="supplier-work__hero">
            <div>
                <div class="supplier-work__eyebrow">Ameise CRM</div>
                <h1 class="supplier-work__title">Поставщики: воронки и рабочая доска</h1>
                <p class="supplier-work__subtitle">
                    Переносите карточки поставщиков по стадиям drag-and-drop, ведите заметки и управляйте структурой воронок без ухода со страницы.
                </p>
            </div>

            <div class="supplier-work__metrics">
                <div class="supplier-work__metric">
                    <span>{{ pipelineItems.length }}</span>
                    <small>воронок</small>
                </div>
                <div class="supplier-work__metric">
                    <span>{{ stages.length }}</span>
                    <small>стадий</small>
                </div>
                <div class="supplier-work__metric">
                    <span>{{ totalCards }}</span>
                    <small>карточек</small>
                </div>
            </div>
        </div>

        <v-card rounded="xl" elevation="0" class="supplier-work__toolbar mb-4">
            <v-row align="center" dense>
                <v-col cols="12" lg="5">
                    <v-select
                        :model-value="selectedPipelineId"
                        :items="pipelineItems"
                        item-title="name"
                        item-value="id"
                        label="Воронка"
                        density="compact"
                        variant="outlined"
                        hide-details
                        :loading="loading"
                        @update:model-value="onPipelineSelected"
                    />
                </v-col>

                <v-col cols="12" lg="7" class="supplier-work__toolbar-actions">
                    <v-btn
                        color="#800000"
                        prepend-icon="mdi-plus"
                        rounded="lg"
                        @click="openPipelineDialog()"
                    >
                        Воронка
                    </v-btn>
                    <v-btn
                        color="#b45309"
                        variant="tonal"
                        prepend-icon="mdi-timeline-plus"
                        rounded="lg"
                        :disabled="!hasPipeline"
                        @click="openStageDialog()"
                    >
                        Стадия
                    </v-btn>
                    <v-btn
                        color="#166534"
                        variant="tonal"
                        prepend-icon="mdi-account-plus"
                        rounded="lg"
                        :disabled="!hasPipeline || !stages.length"
                        @click="openCardDialog(stages[0])"
                    >
                        Поставщик
                    </v-btn>
                    <v-btn
                        icon="mdi-refresh"
                        variant="text"
                        :loading="loading"
                        @click="fetchBoard(selectedPipelineId)"
                    />
                </v-col>
            </v-row>
        </v-card>

        <v-alert
            v-if="!hasPipeline"
            type="info"
            variant="tonal"
            rounded="xl"
            class="mb-4"
        >
            Воронок поставщиков пока нет. Создайте первую воронку, затем добавьте стадии и карточки Units-поставщиков.
        </v-alert>

        <v-row v-else align="start">
            <v-col cols="12" xl="9">
                <v-progress-linear
                    v-if="loading"
                    indeterminate
                    color="#800000"
                    class="mb-3"
                />

                <div v-if="stages.length" class="supplier-board">
                    <section
                        v-for="stage in stages"
                        :key="stage.id"
                        class="supplier-board__stage"
                    >
                        <header class="supplier-board__stage-header">
                            <div class="supplier-board__stage-main">
                                <span
                                    class="supplier-board__stage-dot"
                                    :style="{ backgroundColor: stage.color || '#800000' }"
                                />
                                <div>
                                    <h2>{{ stage.name }}</h2>
                                    <p>{{ stage.cards?.length || 0 }} карточек</p>
                                </div>
                            </div>

                            <v-menu>
                                <template #activator="{ props: menuProps }">
                                    <v-btn
                                        v-bind="menuProps"
                                        icon="mdi-dots-horizontal"
                                        size="small"
                                        variant="text"
                                    />
                                </template>
                                <v-list density="compact">
                                    <v-list-item @click="openCardDialog(stage)">
                                        <v-list-item-title>Добавить поставщика</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item @click="openStageDialog(stage)">
                                        <v-list-item-title>Редактировать стадию</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item @click="reorderStages(stage, -1)">
                                        <v-list-item-title>Поднять выше</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item @click="reorderStages(stage, 1)">
                                        <v-list-item-title>Опустить ниже</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item @click="deleteStage(stage)">
                                        <v-list-item-title class="text-error">Удалить стадию</v-list-item-title>
                                    </v-list-item>
                                </v-list>
                            </v-menu>
                        </header>

                        <Draggable
                            v-model="stage.cards"
                            group="supplier-cards"
                            item-key="id"
                            class="supplier-board__dropzone"
                            ghost-class="supplier-board__ghost"
                            chosen-class="supplier-board__chosen"
                            @change="(event) => onCardChange(stage, event)"
                        >
                            <template #item="{ element: card }">
                                <article class="supplier-card">
                                    <div class="supplier-card__topline">
                                        <v-chip size="x-small" color="#800000" variant="tonal">
                                            Unit #{{ card.unit_id }}
                                        </v-chip>
                                        <v-btn
                                            icon="mdi-pencil"
                                            size="x-small"
                                            variant="text"
                                            @click="openCardDialog(stage, card)"
                                        />
                                    </div>

                                    <h3>{{ cardTitle(card) }}</h3>

                                    <Link
                                        v-if="card.unit?.id"
                                        :href="route('web.unit.show', card.unit.id)"
                                        class="supplier-card__unit-link"
                                    >
                                        {{ card.unit.name }}
                                    </Link>

                                    <p v-if="card.notes" class="supplier-card__notes">
                                        {{ shortText(card.notes) }}
                                    </p>

                                    <div class="supplier-card__tags">
                                        <v-chip
                                            v-for="label in card.unit?.labels || []"
                                            :key="label.id"
                                            size="x-small"
                                            variant="outlined"
                                        >
                                            {{ label.name }}
                                        </v-chip>
                                        <v-chip
                                            v-for="city in card.unit?.cities || []"
                                            :key="`city-${city.id}`"
                                            size="x-small"
                                            color="#0f766e"
                                            variant="tonal"
                                        >
                                            {{ city.name }}
                                        </v-chip>
                                    </div>

                                    <div v-if="card.next_contact_at" class="supplier-card__footer">
                                        <v-icon icon="mdi-calendar-clock" size="small" />
                                        <span>{{ formatDate(card.next_contact_at) }}</span>
                                    </div>
                                </article>
                            </template>
                        </Draggable>

                        <v-btn
                            block
                            variant="tonal"
                            color="#800000"
                            rounded="lg"
                            prepend-icon="mdi-plus"
                            class="mt-3"
                            @click="openCardDialog(stage)"
                        >
                            Добавить
                        </v-btn>
                    </section>
                </div>

                <v-alert
                    v-else
                    type="warning"
                    variant="tonal"
                    rounded="xl"
                >
                    В этой воронке нет стадий. Добавьте хотя бы одну стадию, чтобы начать работу с карточками поставщиков.
                </v-alert>
            </v-col>

            <v-col cols="12" xl="3">
                <v-card rounded="xl" elevation="0" class="supplier-side-card mb-4">
                    <v-card-title class="text-subtitle-1 font-weight-bold">
                        Управление воронкой
                    </v-card-title>
                    <v-card-subtitle>
                        CRUD воронки и стадий
                    </v-card-subtitle>
                    <v-card-text>
                        <div class="supplier-side-card__current">
                            <strong>{{ currentPipeline.name }}</strong>
                            <span>{{ currentPipeline.description || 'Описание не заполнено' }}</span>
                        </div>

                        <div class="supplier-side-card__actions">
                            <v-btn
                                block
                                color="#800000"
                                variant="tonal"
                                prepend-icon="mdi-pencil"
                                @click="openPipelineDialog(currentPipeline)"
                            >
                                Редактировать воронку
                            </v-btn>
                            <v-btn
                                block
                                color="error"
                                variant="tonal"
                                prepend-icon="mdi-delete"
                                @click="deletePipeline(currentPipeline)"
                            >
                                Удалить воронку
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>

                <v-card rounded="xl" elevation="0" class="supplier-side-card">
                    <v-card-title class="text-subtitle-1 font-weight-bold">
                        Стадии
                    </v-card-title>
                    <v-card-text>
                        <div class="supplier-stage-list">
                            <div
                                v-for="stage in stages"
                                :key="`side-${stage.id}`"
                                class="supplier-stage-list__item"
                            >
                                <span
                                    class="supplier-board__stage-dot"
                                    :style="{ backgroundColor: stage.color || '#800000' }"
                                />
                                <div>
                                    <strong>{{ stage.name }}</strong>
                                    <small>{{ stage.cards?.length || 0 }} карточек</small>
                                </div>
                                <v-btn
                                    icon="mdi-pencil"
                                    size="x-small"
                                    variant="text"
                                    @click="openStageDialog(stage)"
                                />
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="pipelineDialog" max-width="620">
            <v-card rounded="xl">
                <v-card-title>
                    {{ editingPipeline ? 'Редактировать воронку' : 'Новая воронка' }}
                </v-card-title>
                <v-card-text class="d-grid ga-3">
                    <v-text-field
                        v-model="pipelineForm.name"
                        label="Название"
                        variant="outlined"
                        density="compact"
                    />
                    <v-textarea
                        v-model="pipelineForm.description"
                        label="Описание"
                        variant="outlined"
                        rows="3"
                    />
                    <v-text-field
                        v-model.number="pipelineForm.sort_order"
                        label="Порядок"
                        type="number"
                        variant="outlined"
                        density="compact"
                    />
                    <v-switch
                        v-model="pipelineForm.is_active"
                        color="#800000"
                        label="Активная воронка"
                        hide-details
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="pipelineDialog = false">Отмена</v-btn>
                    <v-btn color="#800000" :loading="saving" @click="savePipeline">Сохранить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="stageDialog" max-width="620">
            <v-card rounded="xl">
                <v-card-title>
                    {{ editingStage ? 'Редактировать стадию' : 'Новая стадия' }}
                </v-card-title>
                <v-card-text class="d-grid ga-3">
                    <v-text-field
                        v-model="stageForm.name"
                        label="Название"
                        variant="outlined"
                        density="compact"
                    />
                    <v-text-field
                        v-model="stageForm.color"
                        label="Цвет"
                        type="color"
                        variant="outlined"
                        density="compact"
                    />
                    <v-text-field
                        v-model.number="stageForm.sort_order"
                        label="Порядок"
                        type="number"
                        variant="outlined"
                        density="compact"
                    />
                    <v-textarea
                        v-model="stageForm.description"
                        label="Описание"
                        variant="outlined"
                        rows="3"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="stageDialog = false">Отмена</v-btn>
                    <v-btn color="#800000" :loading="saving" @click="saveStage">Сохранить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="cardDialog" max-width="720">
            <v-card rounded="xl">
                <v-card-title>
                    {{ editingCard ? 'Редактировать карточку поставщика' : 'Добавить поставщика' }}
                </v-card-title>
                <v-card-text class="d-grid ga-3">
                    <v-autocomplete
                        v-model="cardForm.unit_id"
                        v-model:search="unitSearch"
                        :items="unitOptions"
                        item-title="name"
                        item-value="id"
                        label="Unit"
                        variant="outlined"
                        density="compact"
                        :disabled="Boolean(editingCard)"
                        no-filter
                        clearable
                        @update:search="fetchUnitOptions"
                    >
                        <template #item="{ props: itemProps, item }">
                            <v-list-item v-bind="itemProps">
                                <template #append>
                                    <v-chip
                                        v-if="item.raw.is_supplier"
                                        size="x-small"
                                        color="#166534"
                                        variant="tonal"
                                    >
                                        supplier
                                    </v-chip>
                                </template>
                            </v-list-item>
                        </template>
                    </v-autocomplete>

                    <v-select
                        v-model="cardForm.supplier_pipeline_stage_id"
                        :items="stages"
                        item-title="name"
                        item-value="id"
                        label="Стадия"
                        variant="outlined"
                        density="compact"
                    />

                    <v-text-field
                        v-model="cardForm.title"
                        label="Заголовок карточки"
                        placeholder="Если пусто, будет использовано название Unit"
                        variant="outlined"
                        density="compact"
                    />

                    <v-textarea
                        v-model="cardForm.notes"
                        label="Заметки по работе"
                        variant="outlined"
                        rows="4"
                    />

                    <v-text-field
                        v-model="cardForm.next_contact_at"
                        label="Следующий контакт"
                        type="datetime-local"
                        variant="outlined"
                        density="compact"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-btn
                        v-if="editingCard"
                        color="error"
                        variant="tonal"
                        @click="deleteCard(editingCard); cardDialog = false"
                    >
                        Удалить
                    </v-btn>
                    <v-spacer />
                    <v-btn variant="text" @click="cardDialog = false">Отмена</v-btn>
                    <v-btn color="#800000" :loading="saving" @click="saveCard">Сохранить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3500">
            {{ snackbar.text }}
        </v-snackbar>
    </v-container>
</template>

<style scoped>
.supplier-work {
    width: 100%;
    min-height: calc(100vh - 64px);
    background:
        radial-gradient(circle at top left, rgba(128, 0, 0, 0.12), transparent 34rem),
        linear-gradient(135deg, #fff9f0 0%, #f7efe3 45%, #eef4ed 100%);
}

.supplier-work__hero {
    display: flex;
    justify-content: space-between;
    gap: 24px;
    padding: 26px;
    margin-bottom: 16px;
    border: 1px solid rgba(92, 0, 0, 0.12);
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.74);
    backdrop-filter: blur(14px);
    box-shadow: 0 22px 60px rgba(73, 32, 12, 0.12);
}

.supplier-work__eyebrow {
    color: #7c2d12;
    font-size: 0.74rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.supplier-work__title {
    margin: 4px 0 8px;
    color: #30130b;
    font-size: clamp(1.7rem, 3vw, 3.4rem);
    font-weight: 950;
    line-height: 0.98;
}

.supplier-work__subtitle {
    max-width: 760px;
    margin: 0;
    color: #6b4b3b;
    font-size: 1rem;
}

.supplier-work__metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(84px, 1fr));
    gap: 10px;
    align-self: end;
}

.supplier-work__metric {
    padding: 14px;
    border-radius: 20px;
    background: #30130b;
    color: #fff8ef;
    text-align: center;
}

.supplier-work__metric span {
    display: block;
    font-size: 1.35rem;
    font-weight: 900;
}

.supplier-work__metric small {
    color: #f9d8b7;
}

.supplier-work__toolbar,
.supplier-side-card {
    border: 1px solid rgba(92, 0, 0, 0.1);
    background: rgba(255, 255, 255, 0.82);
}

.supplier-work__toolbar {
    padding: 14px;
}

.supplier-work__toolbar-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}

.supplier-board {
    display: grid;
    grid-auto-columns: minmax(290px, 360px);
    grid-auto-flow: column;
    gap: 16px;
    overflow-x: auto;
    padding-bottom: 14px;
}

.supplier-board__stage {
    display: flex;
    flex-direction: column;
    min-height: 560px;
    padding: 14px;
    border: 1px solid rgba(92, 0, 0, 0.1);
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 16px 36px rgba(73, 32, 12, 0.08);
}

.supplier-board__stage-header,
.supplier-board__stage-main {
    display: flex;
    align-items: center;
}

.supplier-board__stage-header {
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
}

.supplier-board__stage-main {
    gap: 10px;
    min-width: 0;
}

.supplier-board__stage-dot {
    flex: 0 0 auto;
    width: 12px;
    height: 32px;
    border-radius: 999px;
}

.supplier-board__stage h2 {
    margin: 0;
    color: #35160d;
    font-size: 1rem;
    font-weight: 900;
}

.supplier-board__stage p {
    margin: 2px 0 0;
    color: #8a6250;
    font-size: 0.78rem;
}

.supplier-board__dropzone {
    display: grid;
    align-content: start;
    gap: 10px;
    flex: 1 1 auto;
    min-height: 420px;
    padding: 2px;
}

.supplier-card {
    padding: 14px;
    border: 1px solid rgba(128, 0, 0, 0.1);
    border-radius: 18px;
    background: #fffdf8;
    box-shadow: 0 10px 24px rgba(73, 32, 12, 0.08);
    cursor: grab;
}

.supplier-card:active {
    cursor: grabbing;
}

.supplier-card__topline,
.supplier-card__footer,
.supplier-card__tags {
    display: flex;
    align-items: center;
}

.supplier-card__topline {
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}

.supplier-card h3 {
    margin: 0;
    color: #321208;
    font-size: 1rem;
    font-weight: 900;
    line-height: 1.2;
}

.supplier-card__unit-link {
    display: inline-block;
    margin-top: 4px;
    color: #800000;
    font-size: 0.82rem;
    font-weight: 800;
    text-decoration: none;
}

.supplier-card__notes {
    margin: 10px 0 0;
    color: #61483c;
    font-size: 0.84rem;
    line-height: 1.35;
}

.supplier-card__tags {
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 10px;
}

.supplier-card__footer {
    gap: 6px;
    margin-top: 12px;
    color: #854d0e;
    font-size: 0.8rem;
    font-weight: 800;
}

.supplier-board__ghost {
    opacity: 0.35;
}

.supplier-board__chosen {
    transform: rotate(-1deg);
}

.supplier-side-card__current {
    display: grid;
    gap: 4px;
    padding: 14px;
    border-radius: 18px;
    background: #fff7ed;
}

.supplier-side-card__current strong {
    color: #391509;
}

.supplier-side-card__current span {
    color: #7c5b4b;
    font-size: 0.85rem;
}

.supplier-side-card__actions {
    display: grid;
    gap: 8px;
    margin-top: 12px;
}

.supplier-stage-list {
    display: grid;
    gap: 8px;
}

.supplier-stage-list__item {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: 16px;
    background: #fffaf2;
}

.supplier-stage-list__item strong,
.supplier-stage-list__item small {
    display: block;
}

.supplier-stage-list__item strong {
    color: #35160d;
    font-size: 0.9rem;
}

.supplier-stage-list__item small {
    color: #8a6250;
    font-size: 0.74rem;
}

@media (max-width: 960px) {
    .supplier-work__hero {
        flex-direction: column;
    }

    .supplier-work__metrics {
        align-self: stretch;
    }

    .supplier-work__toolbar-actions {
        justify-content: flex-start;
    }
}
</style>
