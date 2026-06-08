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

const pipelineManagerMenu = ref(false)
const stageManagerMenu = ref(false)
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
const currentPipelineLabel = computed(() => currentPipeline.value?.name || 'Воронка не выбрана')

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

function unitEntities(card) {
    return card.unit?.entities || []
}

function mailFollowUp(card) {
    return card.unit?.mail_follow_up || null
}

function mailSubject(mail) {
    return shortText(mail?.subject || '(без темы)', 54)
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

function formatMailDate(value) {
    if (!value) {
        return null
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
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
            <v-row align="center" dense class="ga-2">
                <v-col cols="12" lg="5">
                    <div class="supplier-work__toolbar-context">
                        <span>Активная воронка</span>
                        <strong>{{ currentPipelineLabel }}</strong>
                    </div>
                </v-col>

                <v-col cols="12" lg="7" class="supplier-work__toolbar-actions">
                    <v-menu
                        v-model="pipelineManagerMenu"
                        :close-on-content-click="false"
                        location="bottom end"
                        max-width="760"
                    >
                        <template #activator="{ props: menuProps }">
                            <v-btn
                                v-bind="menuProps"
                                color="#800000"
                                prepend-icon="mdi-source-branch"
                                rounded="lg"
                                :loading="loading"
                            >
                                Воронки
                            </v-btn>
                        </template>

                        <v-card rounded="xl" elevation="10" class="supplier-pipeline-menu">
                            <v-card-title class="supplier-pipeline-menu__title">
                                <span>Управление воронками</span>
                                <v-btn
                                    icon="mdi-close"
                                    size="small"
                                    variant="text"
                                    @click="pipelineManagerMenu = false"
                                />
                            </v-card-title>

                            <v-card-text class="supplier-pipeline-menu__body">
                                <v-select
                                    :model-value="selectedPipelineId"
                                    :items="pipelineItems"
                                    item-title="name"
                                    item-value="id"
                                    label="Активная воронка"
                                    density="compact"
                                    variant="outlined"
                                    hide-details
                                    :loading="loading"
                                    @update:model-value="onPipelineSelected"
                                />

                                <div class="supplier-pipeline-list">
                                    <div
                                        v-for="pipeline in pipelineItems"
                                        :key="pipeline.id"
                                        class="supplier-pipeline-list__item"
                                        :class="{ 'supplier-pipeline-list__item--active': pipeline.id === selectedPipelineId }"
                                    >
                                        <div class="supplier-pipeline-list__content">
                                            <strong>{{ pipeline.name }}</strong>
                                            <span>{{ pipeline.description || 'Описание не заполнено' }}</span>
                                        </div>

                                        <div class="supplier-pipeline-list__actions">
                                            <v-chip
                                                v-if="pipeline.id === selectedPipelineId"
                                                size="x-small"
                                                color="#166534"
                                                variant="tonal"
                                            >
                                                текущая
                                            </v-chip>
                                            <v-btn
                                                v-else
                                                size="small"
                                                variant="tonal"
                                                color="#800000"
                                                @click="onPipelineSelected(pipeline.id)"
                                            >
                                                Открыть
                                            </v-btn>
                                            <v-btn
                                                icon="mdi-pencil"
                                                size="small"
                                                variant="text"
                                                @click="openPipelineDialog(pipeline)"
                                            />
                                            <v-btn
                                                icon="mdi-delete"
                                                size="small"
                                                variant="text"
                                                color="error"
                                                @click="deletePipeline(pipeline)"
                                            />
                                        </div>
                                    </div>

                                    <v-alert
                                        v-if="!pipelineItems.length"
                                        type="info"
                                        variant="tonal"
                                        rounded="lg"
                                    >
                                        Воронок пока нет. Создайте первую воронку для поставщиков.
                                    </v-alert>
                                </div>
                            </v-card-text>

                            <v-card-actions>
                                <v-btn
                                    color="#800000"
                                    prepend-icon="mdi-plus"
                                    rounded="lg"
                                    @click="openPipelineDialog()"
                                >
                                    Создать воронку
                                </v-btn>
                                <v-spacer />
                                <v-btn variant="text" @click="pipelineManagerMenu = false">Закрыть</v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-menu>

                    <v-menu
                        v-model="stageManagerMenu"
                        :close-on-content-click="false"
                        location="bottom end"
                        max-width="680"
                    >
                        <template #activator="{ props: menuProps }">
                            <v-btn
                                v-bind="menuProps"
                                color="#b45309"
                                variant="tonal"
                                prepend-icon="mdi-timeline-text"
                                rounded="lg"
                                :disabled="!hasPipeline"
                            >
                                Стадии
                            </v-btn>
                        </template>

                        <v-card rounded="xl" elevation="10" class="supplier-stage-menu">
                            <v-card-text>
                                <div class="supplier-stage-manager">
                                    <div
                                        v-for="stage in stages"
                                        :key="`manager-${stage.id}`"
                                        class="supplier-stage-manager__item"
                                    >
                                        <span
                                            class="supplier-board__stage-dot"
                                            :style="{ backgroundColor: stage.color || '#800000' }"
                                        />
                                        <div class="supplier-stage-manager__content">
                                            <strong>{{ stage.name }}</strong>
                                            <span>{{ stage.description || `${stage.cards?.length || 0} карточек` }}</span>
                                        </div>
                                        <v-chip size="x-small" color="#b45309" variant="tonal">
                                            {{ stage.cards?.length || 0 }}
                                        </v-chip>
                                    </div>

                                    <v-alert
                                        v-if="!stages.length"
                                        type="warning"
                                        variant="tonal"
                                        rounded="lg"
                                    >
                                        В этой воронке пока нет стадий.
                                    </v-alert>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-menu>

                    <v-btn
                        color="#b45309"
                        variant="tonal"
                        prepend-icon="mdi-timeline-plus"
                        rounded="lg"
                        :disabled="!hasPipeline"
                        @click="openStageDialog()"
                    >
                        Новая стадия
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
            <v-col cols="12">
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
                            <article
                                v-for="card in stage.cards"
                                :key="card.id"
                                class="supplier-card"
                                :class="{ 'supplier-card--mail-alert': mailFollowUp(card)?.is_overdue }"
                            >
                                <div class="supplier-card__identity">
                                    <div class="supplier-card__unit-heading">
                                        <Link
                                            v-if="card.unit?.id"
                                            :href="route('web.unit.show', card.unit.id)"
                                            class="supplier-card__unit-name"
                                        >
                                            {{ unitTitle(card.unit) }}
                                        </Link>
                                        <strong v-else class="supplier-card__unit-name">
                                            {{ unitTitle(card.unit) }}
                                        </strong>
                                        <span class="supplier-card__unit-id">#{{ card.unit_id }}</span>
                                    </div>
                                    <v-btn
                                        icon="mdi-pencil"
                                        size="x-small"
                                        variant="text"
                                        @click="openCardDialog(stage, card)"
                                    />
                                </div>

                                <div
                                    v-if="unitEntities(card).length"
                                    class="supplier-card__entities"
                                >
                                    <span
                                        v-for="entity in unitEntities(card)"
                                        :key="entity.id"
                                    >
                                        {{ entity.name }}
                                    </span>
                                </div>
                                <div v-else class="supplier-card__entities supplier-card__entities--empty">
                                    entities не связаны
                                </div>

                                <p v-if="card.notes" class="supplier-card__notes">
                                    {{ shortText(card.notes) }}
                                </p>

                                <div
                                    v-if="mailFollowUp(card)"
                                    class="supplier-card__mail"
                                    :class="{ 'supplier-card__mail--alert': mailFollowUp(card).is_overdue }"
                                >
                                    <div class="supplier-card__mail-head">
                                        <span>last mail</span>
                                        <time>{{ formatMailDate(mailFollowUp(card).sent_at) }}</time>
                                    </div>
                                    <div class="supplier-card__mail-subject">
                                        {{ mailSubject(mailFollowUp(card)) }}
                                    </div>
                                    <div v-if="mailFollowUp(card).preview" class="supplier-card__mail-preview">
                                        {{ shortText(mailFollowUp(card).preview, 72) }}
                                    </div>
                                </div>

                                <div v-if="card.next_contact_at" class="supplier-card__footer">
                                    <v-icon icon="mdi-calendar-clock" size="small" />
                                    <span>{{ formatDate(card.next_contact_at) }}</span>
                                </div>
                            </article>
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

.supplier-work__toolbar {
    border: 1px solid rgba(92, 0, 0, 0.1);
    background: rgba(255, 255, 255, 0.82);
}

.supplier-work__toolbar {
    padding: 14px;
}

.supplier-work__toolbar-context {
    display: grid;
    gap: 2px;
    padding: 4px 8px;
}

.supplier-work__toolbar-context span {
    color: #7c5b4b;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.supplier-work__toolbar-context strong {
    color: #35160d;
    font-size: 1rem;
    font-weight: 950;
}

.supplier-work__toolbar-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}

.supplier-pipeline-menu,
.supplier-stage-menu {
    width: min(760px, calc(100vw - 32px));
    border: 1px solid rgba(92, 0, 0, 0.12);
    background: rgba(255, 253, 248, 0.98);
}

.supplier-pipeline-menu__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: #35160d;
    font-weight: 950;
}

.supplier-pipeline-menu__body {
    display: grid;
    gap: 14px;
}

.supplier-pipeline-list {
    display: grid;
    gap: 8px;
    max-height: 420px;
    overflow: auto;
    padding-right: 2px;
}

.supplier-pipeline-list__item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 18px;
    background: #fffaf2;
}

.supplier-pipeline-list__item--active {
    border-color: rgba(22, 101, 52, 0.3);
    background: #f4fbf3;
}

.supplier-pipeline-list__content {
    min-width: 0;
}

.supplier-pipeline-list__content strong,
.supplier-pipeline-list__content span {
    display: block;
}

.supplier-pipeline-list__content strong {
    color: #35160d;
    font-size: 0.94rem;
    font-weight: 900;
}

.supplier-pipeline-list__content span {
    overflow: hidden;
    color: #7c5b4b;
    font-size: 0.8rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.supplier-pipeline-list__actions {
    display: flex;
    align-items: center;
    gap: 4px;
}

.supplier-stage-menu {
    width: min(680px, calc(100vw - 32px));
}

.supplier-stage-manager {
    display: grid;
    gap: 8px;
    max-height: 430px;
    overflow: auto;
    padding-right: 2px;
}

.supplier-stage-manager__item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    padding: 12px;
    border: 1px solid rgba(180, 83, 9, 0.12);
    border-radius: 18px;
    background: #fffaf2;
}

.supplier-stage-manager__content {
    min-width: 0;
}

.supplier-stage-manager__content strong,
.supplier-stage-manager__content span {
    display: block;
}

.supplier-stage-manager__content strong {
    color: #35160d;
    font-size: 0.94rem;
    font-weight: 900;
}

.supplier-stage-manager__content span {
    overflow: hidden;
    color: #7c5b4b;
    font-size: 0.8rem;
    text-overflow: ellipsis;
    white-space: nowrap;
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
    transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
}

.supplier-card--mail-alert {
    border-color: rgba(185, 28, 28, 0.44);
    background:
        linear-gradient(135deg, rgba(254, 226, 226, 0.98), rgba(255, 241, 242, 0.94));
    box-shadow: 0 14px 28px rgba(185, 28, 28, 0.16);
}

.supplier-card:active {
    cursor: grabbing;
}

.supplier-card__identity,
.supplier-card__unit-heading,
.supplier-card__footer {
    display: flex;
    align-items: center;
}

.supplier-card__identity {
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
}

.supplier-card__unit-heading {
    min-width: 0;
    gap: 6px;
}

.supplier-card__unit-name {
    overflow: hidden;
    color: #321208;
    font-size: 1.13rem;
    font-weight: 950;
    line-height: 1.12;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.supplier-card__unit-id {
    flex: 0 0 auto;
    color: #9ca3af;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.04em;
}

.supplier-card__entities {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 7px;
    color: #050505;
    font-family: "Courier New", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1.2;
}

.supplier-card__entities span {
    min-width: 0;
}

.supplier-card__entities span:not(:last-child)::after {
    content: " /";
    color: #737373;
}

.supplier-card__entities--empty {
    color: #8a8a8a;
    font-style: italic;
    font-weight: 600;
}

.supplier-card__notes {
    margin: 10px 0 0;
    color: #61483c;
    font-size: 0.84rem;
    line-height: 1.35;
}

.supplier-card__mail {
    display: grid;
    gap: 3px;
    margin-top: 11px;
    padding: 8px 9px;
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 12px;
    background:
        linear-gradient(180deg, rgba(15, 23, 42, 0.035), rgba(15, 23, 42, 0.015));
    color: #1f2937;
    font-family: "Courier New", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.supplier-card__mail--alert {
    border-color: rgba(153, 27, 27, 0.28);
    background: rgba(127, 29, 29, 0.08);
}

.supplier-card__mail-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    color: #64748b;
    font-size: 0.58rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    line-height: 1;
    text-transform: uppercase;
}

.supplier-card__mail-subject {
    overflow: hidden;
    color: #111827;
    font-size: 0.7rem;
    font-weight: 900;
    line-height: 1.18;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.supplier-card__mail-preview {
    display: -webkit-box;
    overflow: hidden;
    color: #475569;
    font-size: 0.66rem;
    line-height: 1.22;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
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

    .supplier-pipeline-list__item {
        grid-template-columns: 1fr;
    }

    .supplier-pipeline-list__actions {
        justify-content: flex-start;
    }

    .supplier-stage-manager__item {
        grid-template-columns: auto minmax(0, 1fr) auto;
    }
}
</style>
