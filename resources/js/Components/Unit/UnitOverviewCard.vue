<script setup>
import { computed, reactive, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import axios from 'axios'

import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'
import UnitFilesTab from '@/Components/Unit/UnitFilesTab.vue'
import UnitUrisCard from '@/Components/Unit/UnitUrisCard.vue'
import UnitRelationManagerDialog from '@/Components/Unit/UnitRelationManagerDialog.vue'
import UnitMailComposerDialog from '@/Components/Unit/Mail/UnitMailComposerDialog.vue'

const MANAGER_PHONE = '79650160001'

const props = defineProps({
    unit: {
        type: Object,
        required: true,
    },
    files: {
        type: Array,
        default: () => [],
    },
    dict: {
        type: Object,
        default: () => ({}),
    },
    loading: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['refresh'])

const emojiDigitMap = {
    '0': '0️⃣',
    '1': '1️⃣',
    '2': '2️⃣',
    '3': '3️⃣',
    '4': '4️⃣',
    '5': '5️⃣',
    '6': '6️⃣',
    '7': '7️⃣',
    '8': '8️⃣',
    '9': '9️⃣',
}

function formatUnitIdToEmoji(id) {
    return String(id ?? '')
        .split('')
        .map(char => emojiDigitMap[char] ?? char)
        .join('')
}

const activeTab = ref('info')

const dialogAttachEmail = ref(false)
const dialogAddEmail = ref(false)

const dialogLabels = ref(false)
const dialogFields = ref(false)
const dialogTelephones = ref(false)
const dialogCities = ref(false)
const dialogAttachEntity = ref(false)
const dialogEntityForm = ref(false)

const savingLabels = ref(false)
const savingFields = ref(false)
const savingTelephones = ref(false)
const savingCities = ref(false)
const savingEntity = ref(false)
const savingEntityRelation = ref(false)
const editingEntity = ref(null)
const attachEntityId = ref(null)
const entityErrors = ref({})

const formAttachEmail = useForm({
    email_id: null,
    unit_id: props.unit.id,
})

const formAddEmail = useForm({
    address: null,
})

const entityForm = reactive({
    name: '',
    full_name: '',
    entity_classification_id: null,
    telephones: [],
})

function getIds(items = []) {
    return items
        .filter(item => typeof item === 'object' && item?.id)
        .map(item => item.id)
}

function getStrings(items = []) {
    return items
        .filter(item => typeof item === 'string' && item.trim() !== '')
        .map(item => item.trim())
}

function attachEmail() {
    formAttachEmail.unit_id = props.unit.id

    formAttachEmail.post(route('emailgood.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            formAttachEmail.reset()
            dialogAttachEmail.value = false
            emit('refresh')
        },
    })
}

function storeEmail() {
    formAddEmail.post(route('web.email.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            formAddEmail.reset()
            dialogAddEmail.value = false
            emit('refresh')
        },
    })
}

async function syncLabels(payload) {
    savingLabels.value = true

    try {
        const currentIds = (props.unit.labels || []).map(item => item.id)
        const nextIds = getIds(payload)
        const newNames = getStrings(payload)

        const idsToDetach = currentIds.filter(id => !nextIds.includes(id))

        await Promise.all(
            idsToDetach.map(id =>
                axios.delete(route('api.units.labels.detach', {
                    unit: props.unit.id,
                    label: id,
                }))
            )
        )

        await Promise.all(
            nextIds
                .filter(id => !currentIds.includes(id))
                .map(id =>
                    axios.post(route('api.units.labels.attach', props.unit.id), {
                        label_id: id,
                    })
                )
        )

        await Promise.all(
            newNames.map(name =>
                axios.post(route('api.units.labels.attach', props.unit.id), {
                    name,
                })
            )
        )

        dialogLabels.value = false
        emit('refresh')
    } catch (error) {
        console.error('Ошибка сохранения labels:', error)
    } finally {
        savingLabels.value = false
    }
}

async function syncFields(payload) {
    savingFields.value = true

    try {
        const currentIds = (props.unit.fields || []).map(item => item.id)
        const nextIds = getIds(payload)
        const newNames = getStrings(payload)

        const idsToDetach = currentIds.filter(id => !nextIds.includes(id))

        await Promise.all(
            idsToDetach.map(id =>
                axios.delete(route('api.units.fields.detach', {
                    unit: props.unit.id,
                    field: id,
                }))
            )
        )

        await Promise.all(
            nextIds
                .filter(id => !currentIds.includes(id))
                .map(id =>
                    axios.post(route('api.units.fields.attach', props.unit.id), {
                        field_id: id,
                    })
                )
        )

        await Promise.all(
            newNames.map(name =>
                axios.post(route('api.units.fields.attach', props.unit.id), {
                    name,
                })
            )
        )

        dialogFields.value = false
        emit('refresh')
    } catch (error) {
        console.error('Ошибка сохранения fields:', error)
    } finally {
        savingFields.value = false
    }
}

async function syncTelephones(payload) {
    savingTelephones.value = true

    try {
        const currentIds = (props.unit.telephones || []).map(item => item.id)
        const nextIds = getIds(payload)
        const newNumbers = getStrings(payload)

        const idsToDetach = currentIds.filter(id => !nextIds.includes(id))

        await Promise.all(
            idsToDetach.map(id =>
                axios.delete(route('api.units.telephones.detach', {
                    unit: props.unit.id,
                    telephone: id,
                }))
            )
        )

        await Promise.all(
            nextIds
                .filter(id => !currentIds.includes(id))
                .map(id =>
                    axios.post(route('api.units.telephones.attach', props.unit.id), {
                        telephone_id: id,
                    })
                )
        )

        await Promise.all(
            newNumbers.map(number =>
                axios.post(route('api.units.telephones.attach', props.unit.id), {
                    number,
                })
            )
        )

        dialogTelephones.value = false
        emit('refresh')
    } catch (error) {
        console.error('Ошибка сохранения telephones:', error)
    } finally {
        savingTelephones.value = false
    }
}

async function syncCities(payload) {
    savingCities.value = true

    try {
        const currentIds = (props.unit.cities || []).map(item => item.id)
        const nextIds = getIds(payload)
        const newNames = getStrings(payload)

        const idsToDetach = currentIds.filter(id => !nextIds.includes(id))

        await Promise.all(
            idsToDetach.map(id =>
                axios.delete(route('api.units.cities.detach', {
                    unit: props.unit.id,
                    city: id,
                }))
            )
        )

        await Promise.all(
            nextIds
                .filter(id => !currentIds.includes(id))
                .map(id =>
                    axios.post(route('api.units.cities.attach', props.unit.id), {
                        city_id: id,
                    })
                )
        )

        await Promise.all(
            newNames.map(name =>
                axios.post(route('api.units.cities.attach', props.unit.id), {
                    name,
                })
            )
        )

        dialogCities.value = false
        emit('refresh')
    } catch (error) {
        console.error('Ошибка сохранения cities:', error)
    } finally {
        savingCities.value = false
    }
}

function entityTelephoneIds(entity) {
    return (entity?.telephones || [])
        .map((telephone) => telephone.id)
        .filter(Boolean)
}

function resetEntityForm(entity = null) {
    editingEntity.value = entity
    entityErrors.value = {}
    entityForm.name = entity?.name || ''
    entityForm.full_name = entity?.full_name || ''
    entityForm.entity_classification_id = entity?.entity_classification_id
        || entity?.classification?.id
        || null
    entityForm.telephones = entityTelephoneIds(entity)
}

function openCreateEntity() {
    resetEntityForm()
    dialogEntityForm.value = true
}

function openEditEntity(entity) {
    resetEntityForm(entity)
    dialogEntityForm.value = true
}

async function attachEntityToUnit() {
    if (!attachEntityId.value) {
        return
    }

    savingEntityRelation.value = true

    try {
        await axios.post(`/api/units/${props.unit.id}/entities/attach`, {
            entity_id: attachEntityId.value,
        })

        attachEntityId.value = null
        dialogAttachEntity.value = false
        emit('refresh')
    } catch (error) {
        console.error('Ошибка привязки entity:', error)
    } finally {
        savingEntityRelation.value = false
    }
}

async function detachEntityFromUnit(entity) {
    if (!entity?.id || !window.confirm(`Отвязать "${entity.name}" от Unit?`)) {
        return
    }

    savingEntityRelation.value = true

    try {
        await axios.delete(`/api/units/${props.unit.id}/entities/${entity.id}`)
        emit('refresh')
    } catch (error) {
        console.error('Ошибка отвязки entity:', error)
    } finally {
        savingEntityRelation.value = false
    }
}

function relationIds(items = []) {
    return (items || [])
        .map((item) => item?.id ?? item)
        .filter(Boolean)
}

async function entityPayload() {
    let existing = null

    if (editingEntity.value?.id) {
        const { data } = await axios.get(`/api/entities/${editingEntity.value.id}`)
        existing = data?.data || data
    }

    const existingUnitIds = relationIds(existing?.units)

    return {
        name: entityForm.name,
        full_name: entityForm.full_name || null,
        entity_classification_id: entityForm.entity_classification_id || null,
        buildings: relationIds(existing?.buildings),
        cities: relationIds(existing?.cities),
        emails: relationIds(existing?.emails),
        telephones: entityForm.telephones || [],
        units: [...new Set([...existingUnitIds, props.unit.id])],
        chats: relationIds(existing?.chats),
    }
}

async function saveEntity() {
    savingEntity.value = true
    entityErrors.value = {}

    try {
        if (editingEntity.value?.id) {
            await axios.put(`/api/entities/${editingEntity.value.id}`, await entityPayload())
        } else {
            await axios.post('/api/entities', await entityPayload())
        }

        dialogEntityForm.value = false
        resetEntityForm()
        emit('refresh')
    } catch (error) {
        entityErrors.value = error.response?.data?.errors || {}
        console.error('Ошибка сохранения entity:', error)
    } finally {
        savingEntity.value = false
    }
}

async function deleteEntity(entity) {
    if (!entity?.id || !window.confirm(`Удалить Entity "${entity.name}" полностью? Это действие удалит связи Entity.`)) {
        return
    }

    savingEntityRelation.value = true

    try {
        await axios.delete(`/api/entities/${entity.id}`)
        emit('refresh')
    } catch (error) {
        console.error('Ошибка удаления entity:', error)
    } finally {
        savingEntityRelation.value = false
    }
}

const quickMailDialog = ref(false)
const quickMailFiles = ref([])
const quickMailRecipients = ref([])
const dialingPhone = ref(null)
const dialFeedback = ref(null)

function cloneRecipients(items = []) {
    return items.map((item) => ({ ...item }))
}

const unitMailRecipients = computed(() => {
    const result = []

    ;(props.unit?.emails || []).forEach((email) => {
        if (!email?.address) return

        result.push({
            id: email.id ?? email.address,
            address: email.address,
            name: email.name || null,
            source: 'unit',
            source_label: 'Unit',
        })
    })

    ;(props.unit?.entities || []).forEach((entity) => {
        ;(entity?.emails || []).forEach((email) => {
            if (!email?.address) return

            result.push({
                id: email.id ?? email.address,
                address: email.address,
                name: email.name || null,
                source: 'entity',
                source_label: `Entity: ${entity.name}`,
                entity_id: entity.id,
                entity_name: entity.name,
            })
        })
    })

    return result.filter((email, index, array) => {
        return array.findIndex((item) => item.address === email.address) === index
    })
})

const workboardCards = computed(() => {
    return props.unit?.supplier_pipeline_cards || props.unit?.supplierPipelineCards || []
})

const overviewStats = computed(() => [
    {
        label: 'Entities',
        value: props.unit?.entities?.length || 0,
    },
    {
        label: 'Emails',
        value: props.unit?.emails?.length || 0,
    },
    {
        label: 'Phones',
        value: props.unit?.telephones?.length || 0,
    },
    {
        label: 'Quotations',
        value: props.unit?.quotations?.length || 0,
    },
])

const unitFlags = computed(() => [
    props.unit?.is_supplier ? 'Supplier' : null,
    props.unit?.is_customer ? 'Customer' : null,
].filter(Boolean))

const unitEntities = computed(() => props.unit?.entities || [])

function stageLabel(card) {
    return card.stage?.name || 'Без стадии'
}

function entityHref(entity) {
    try {
        return route('Ameise.entity.show', entity.id)
    } catch (error) {
        return `/Ameise/entity/${entity.id}`
    }
}

function entityInitials(entity) {
    const words = String(entity?.name || '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)

    return words.map((word) => word.charAt(0).toUpperCase()).join('') || 'E'
}

function entityClassification(entity) {
    return entity?.classification?.name || 'Entity'
}

function entityTelephones(entity) {
    return entity?.telephones || []
}

function entityEmails(entity) {
    return entity?.emails || []
}

function normalizePhone(value) {
    return String(value || '').replace(/[^\d+]/g, '')
}

function formatPhone(value) {
    const normalized = normalizePhone(value)

    return normalized ? `+${normalized.replace(/^\+/, '')}` : 'нет номера'
}

async function dialEntityPhone(telephone, entity) {
    const phone = normalizePhone(telephone?.number)

    if (!phone) {
        return
    }

    dialingPhone.value = phone
    dialFeedback.value = null

    try {
        await axios.post('/api/phone-calls/dial', {
            client_phone: phone,
            employee_phone: MANAGER_PHONE,
        })

        dialFeedback.value = {
            tone: 'success',
            text: `Билайн: звонок ${formatPhone(phone)} для ${entity.name} запущен.`,
        }
    } catch (error) {
        console.error(error)
        dialFeedback.value = {
            tone: 'error',
            text: error.response?.data?.message || 'Не удалось запустить звонок через Билайн.',
        }
    } finally {
        dialingPhone.value = null
    }
}

function openFileMail(file) {
    quickMailFiles.value = file?.path ? [file.path] : []
    quickMailRecipients.value = cloneRecipients(unitMailRecipients.value)
    quickMailDialog.value = true
}

function openQuickMail() {
    quickMailFiles.value = []
    quickMailRecipients.value = cloneRecipients(unitMailRecipients.value)
    quickMailDialog.value = true
}
</script>

<template>
    <BaseSectionCard
        title="Unit Overview"
        icon="mdi-factory"
        compact
    >
        <template #actions>
            <v-menu>
                <template #activator="{ props: menuProps }">
                    <v-btn
                        v-bind="menuProps"
                        icon="mdi-dots-vertical"
                        variant="text"
                        size="small"
                    />
                </template>

                <v-list density="compact">
                    <v-list-item
                        title="Write email"
                        prepend-icon="mdi-email-plus-outline"
                        @click="openQuickMail"
                    />

                    <v-list-item
                        title="Attach email"
                        prepend-icon="mdi-email-link-outline"
                        @click="dialogAttachEmail = true"
                    />
                </v-list>
            </v-menu>
        </template>

        <div class="d-flex align-start justify-space-between mb-3">
            <div class="text-h4 font-weight-bold">
                {{ unit.name }}

                <div v-if="unitFlags.length" class="unit-overview__flags">
                    <span
                        v-for="flag in unitFlags"
                        :key="flag"
                    >
                        {{ flag }}
                    </span>
                </div>
            </div>

            <div class="text-right">
                <div class="text-caption text-medium-emphasis">
                    ID
                </div>
                <div class="text-h5 font-weight-bold">
                    {{ formatUnitIdToEmoji(unit.id) }}
                </div>
            </div>
        </div>

        <div class="unit-overview__top-grid">
            <div class="unit-overview__stats">
                <div
                    v-for="stat in overviewStats"
                    :key="stat.label"
                    class="unit-overview__stat"
                >
                    <strong>{{ stat.value }}</strong>
                    <span>{{ stat.label }}</span>
                </div>
            </div>

            <aside class="unit-overview__workboard-tile">
                <div class="unit-overview__workboard-label">Workboard</div>
                <strong>{{ workboardCards.length }}</strong>
                <span v-if="workboardCards[0]">
                    {{ stageLabel(workboardCards[0]) }}
                </span>
                <span v-else>not linked</span>
                <small v-if="unit.mail_follow_up" :class="{ 'is-alert': unit.mail_follow_up.is_overdue }">
                    {{ unit.mail_follow_up.is_overdue ? 'mail overdue' : 'mail ok' }}
                </small>
            </aside>
        </div>

        <div class="unit-overview__body-grid">
            <section class="unit-overview__entities">
                <div class="unit-overview__entities-head">
                    <div>
                        <span>Entities</span>
                        <strong>{{ unitEntities.length }}</strong>
                    </div>

                    <div class="unit-overview__entity-toolbar">
                        <button type="button" @click="dialogAttachEntity = true">Attach</button>
                        <button type="button" @click="openCreateEntity">Create</button>
                    </div>
                </div>

                <div
                    v-if="unitEntities.length"
                    class="unit-overview__entity-grid"
                >
                    <article
                        v-for="entity in unitEntities"
                        :key="entity.id"
                        class="unit-overview__entity"
                    >
                        <div class="unit-overview__entity-mark">
                            {{ entityInitials(entity) }}
                        </div>

                        <div class="unit-overview__entity-body">
                            <a
                                :href="entityHref(entity)"
                                class="unit-overview__entity-name"
                            >
                                {{ entity.name }}
                            </a>

                            <div class="unit-overview__entity-kind">
                                {{ entityClassification(entity) }}
                            </div>

                            <div
                                v-if="entityTelephones(entity).length"
                                class="unit-overview__entity-phones"
                            >
                                <button
                                    v-for="telephone in entityTelephones(entity)"
                                    :key="telephone.id"
                                    type="button"
                                    class="unit-overview__entity-phone"
                                    :disabled="dialingPhone === normalizePhone(telephone.number)"
                                    @click="dialEntityPhone(telephone, entity)"
                                >
                                    <span class="unit-overview__entity-phone-icon">call</span>
                                    <span>{{ formatPhone(telephone.number) }}</span>
                                    <small v-if="dialingPhone === normalizePhone(telephone.number)">
                                        dialing
                                    </small>
                                </button>
                            </div>

                            <div
                                v-if="entityEmails(entity).length"
                                class="unit-overview__entity-emails"
                            >
                                <a
                                    v-for="email in entityEmails(entity)"
                                    :key="email.id"
                                    :href="`mailto:${email.address}`"
                                >
                                    {{ email.address }}
                                </a>
                            </div>

                            <div class="unit-overview__entity-actions">
                                <button type="button" @click="openEditEntity(entity)">Edit</button>
                                <button
                                    type="button"
                                    :disabled="savingEntityRelation"
                                    @click="detachEntityFromUnit(entity)"
                                >
                                    Detach
                                </button>
                                <button
                                    type="button"
                                    class="is-danger"
                                    :disabled="savingEntityRelation"
                                    @click="deleteEntity(entity)"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="unit-overview__empty"
                >
                    Entities не связаны
                </div>

                <div
                    v-if="dialFeedback"
                    class="unit-overview__dial-feedback"
                    :class="`unit-overview__dial-feedback--${dialFeedback.tone}`"
                >
                    {{ dialFeedback.text }}
                </div>
            </section>

            <section class="unit-overview__tabs-panel">
                <v-tabs v-model="activeTab" color="primary" density="compact">
                    <v-tab value="info">Info</v-tab>
                    <v-tab value="contacts">Contacts</v-tab>
                    <v-tab value="files">Files</v-tab>
                    <v-tab value="buildings">Buildings</v-tab>
                </v-tabs>

                <v-window v-model="activeTab" class="mt-4">
                    <v-window-item value="info">
                        <div class="mb-4">
                            <div class="text-caption text-medium-emphasis mb-2">URI</div>
                            <UnitUrisCard
                                :unit="unit"
                                :dict="dict"
                                @refresh="emit('refresh')"
                            />
                        </div>

                        <div class="mb-4">
                            <div class="d-flex align-center justify-space-between mb-2">
                                <div class="text-caption text-medium-emphasis">Labels</div>
                                <v-btn size="x-small" variant="text" @click="dialogLabels = true">
                                    Manage
                                </v-btn>
                            </div>

                            <div v-if="unit.labels?.length" class="d-flex flex-wrap ga-2">
                                <v-chip
                                    v-for="label in unit.labels"
                                    :key="label.id"
                                    size="small"
                                    color="primary"
                                    variant="tonal"
                                >
                                    {{ label.name }}
                                </v-chip>
                            </div>

                            <div v-else class="text-caption text-disabled">
                                No labels
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex align-center justify-space-between mb-2">
                                <div class="text-caption text-medium-emphasis">Fields</div>
                                <v-btn size="x-small" variant="text" @click="dialogFields = true">
                                    Manage
                                </v-btn>
                            </div>

                            <div v-if="unit.fields?.length" class="d-flex flex-wrap ga-2">
                                <v-chip
                                    v-for="field in unit.fields"
                                    :key="field.id"
                                    size="small"
                                    color="deep-purple"
                                    variant="tonal"
                                >
                                    {{ field.name }}
                                </v-chip>
                            </div>

                            <div v-else class="text-caption text-disabled">
                                No fields
                            </div>
                        </div>

                        <div class="mb-2">
                            <div class="d-flex align-center justify-space-between mb-2">
                                <div class="text-caption text-medium-emphasis">
                                    Cities for offices / warehouses / production / delivery
                                </div>
                                <v-btn size="x-small" variant="text" @click="dialogCities = true">
                                    Manage
                                </v-btn>
                            </div>

                            <div v-if="unit.cities?.length" class="d-flex flex-wrap ga-2">
                                <v-chip
                                    v-for="city in unit.cities"
                                    :key="city.id"
                                    size="small"
                                    color="teal"
                                    variant="tonal"
                                >
                                    {{ city.name }}
                                </v-chip>
                            </div>

                            <div v-else class="text-caption text-disabled">
                                No cities
                            </div>
                        </div>
                    </v-window-item>

                    <v-window-item value="contacts">
                        <div class="mb-4">
                            <div class="d-flex align-center justify-space-between mb-2">
                                <div class="text-caption text-medium-emphasis">Telephones</div>
                                <v-btn size="x-small" variant="text" @click="dialogTelephones = true">
                                    Manage
                                </v-btn>
                            </div>

                            <div v-if="unit.telephones?.length" class="d-flex flex-wrap ga-2">
                                <v-chip
                                    v-for="telephone in unit.telephones"
                                    :key="telephone.id"
                                    size="small"
                                    color="indigo"
                                    variant="tonal"
                                >
                                    {{ telephone.number }}
                                </v-chip>
                            </div>

                            <div v-else class="text-caption text-disabled">
                                No telephones
                            </div>
                        </div>

                        <div>
                            <div class="text-caption text-medium-emphasis mb-2">Emails</div>

                            <v-list density="compact" lines="two">
                                <v-list-item
                                    v-for="email in (unit.emails || [])"
                                    :key="email.id"
                                    :title="email.address"
                                    subtitle="Email"
                                />
                            </v-list>
                        </div>
                    </v-window-item>

                    <v-window-item value="files">
                        <UnitFilesTab
                            v-if="unit?.id"
                            :unit-id="Number(unit.id)"
                            @send-file="openFileMail"
                        />
                    </v-window-item>

                    <v-window-item value="buildings">
                        <v-list density="compact">
                            <v-list-item
                                v-for="building in (unit.buildings || [])"
                                :key="building.id"
                                :title="building.address"
                                :subtitle="building.city?.name"
                            />
                        </v-list>
                    </v-window-item>
                </v-window>
            </section>
        </div>

        <v-dialog v-model="dialogAttachEmail" max-width="720">
            <v-card rounded="xl">
                <v-card-title>Attach Email</v-card-title>

                <v-card-text>
                    <v-autocomplete
                        v-model="formAttachEmail.email_id"
                        :items="dict.emails || []"
                        item-title="address"
                        item-value="id"
                        label="Emails"
                        variant="outlined"
                        density="comfortable"
                    />

                    <div class="mt-4">
                        <v-btn variant="text" @click="dialogAddEmail = true">
                            New email
                        </v-btn>
                    </div>
                </v-card-text>

                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogAttachEmail = false">
                        Cancel
                    </v-btn>

                    <v-btn
                        color="primary"
                        :loading="formAttachEmail.processing"
                        @click="attachEmail"
                    >
                        Attach
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="dialogAddEmail" max-width="640">
            <v-card rounded="xl">
                <v-card-title>New Email</v-card-title>

                <v-card-text>
                    <v-text-field
                        v-model="formAddEmail.address"
                        label="Email address"
                        variant="outlined"
                        density="comfortable"
                    />
                </v-card-text>

                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogAddEmail = false">
                        Cancel
                    </v-btn>

                    <v-btn
                        color="primary"
                        :loading="formAddEmail.processing"
                        @click="storeEmail"
                    >
                        Save
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="dialogAttachEntity" max-width="720">
            <v-card rounded="xl">
                <v-card-title>Attach Entity</v-card-title>

                <v-card-text>
                    <v-autocomplete
                        v-model="attachEntityId"
                        :items="dict.entities || []"
                        item-title="name"
                        item-value="id"
                        label="Entity"
                        variant="outlined"
                        density="comfortable"
                        clearable
                    />
                </v-card-text>

                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogAttachEntity = false">
                        Cancel
                    </v-btn>

                    <v-btn
                        color="primary"
                        :disabled="!attachEntityId"
                        :loading="savingEntityRelation"
                        @click="attachEntityToUnit"
                    >
                        Attach
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="dialogEntityForm" max-width="820">
            <v-card rounded="xl">
                <v-card-title>
                    {{ editingEntity ? 'Edit Entity' : 'Create Entity' }}
                </v-card-title>

                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="entityForm.name"
                                label="Name"
                                variant="outlined"
                                density="comfortable"
                                :error-messages="entityErrors.name || []"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="entityForm.entity_classification_id"
                                :items="dict.entityClassifications || []"
                                item-title="name"
                                item-value="id"
                                label="Classification"
                                variant="outlined"
                                density="comfortable"
                                clearable
                                :error-messages="entityErrors.entity_classification_id || []"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-text-field
                                v-model="entityForm.full_name"
                                label="Full name"
                                variant="outlined"
                                density="comfortable"
                                :error-messages="entityErrors.full_name || []"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-autocomplete
                                v-model="entityForm.telephones"
                                :items="dict.telephones || []"
                                item-title="number"
                                item-value="id"
                                label="Telephones"
                                variant="outlined"
                                density="comfortable"
                                multiple
                                chips
                                clearable
                                :error-messages="entityErrors.telephones || []"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogEntityForm = false">
                        Cancel
                    </v-btn>

                    <v-btn
                        color="primary"
                        :disabled="!entityForm.name"
                        :loading="savingEntity"
                        @click="saveEntity"
                    >
                        Save
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <UnitRelationManagerDialog
            v-model="dialogLabels"
            title="Manage Labels"
            :items="unit.labels || []"
            :dict-items="dict.labels || []"
            item-title="name"
            item-value="id"
            :loading="savingLabels"
            @save="syncLabels"
        />

        <UnitRelationManagerDialog
            v-model="dialogFields"
            title="Manage Fields"
            :items="unit.fields || []"
            :dict-items="dict.fields || []"
            item-title="name"
            item-value="id"
            :loading="savingFields"
            @save="syncFields"
        />

        <UnitRelationManagerDialog
            v-model="dialogTelephones"
            title="Manage Telephones"
            :items="unit.telephones || []"
            :dict-items="dict.telephones || []"
            item-title="number"
            item-value="id"
            hint="Можно выбрать номер из базы или ввести новый"
            :loading="savingTelephones"
            @save="syncTelephones"
        />

        <UnitRelationManagerDialog
            v-model="dialogCities"
            title="Manage Cities"
            :items="unit.cities || []"
            :dict-items="dict.cities || []"
            item-title="name"
            item-value="id"
            hint="Города офисов, складов, производств и доставки"
            :loading="savingCities"
            @save="syncCities"
        />

        <UnitMailComposerDialog
            v-model="quickMailDialog"
            :unit-id="unit.id"
            :recipients="quickMailRecipients"
            :initial-storage-files="quickMailFiles"
            @sent="emit('refresh')"
        />
    </BaseSectionCard>
</template>

<style scoped>
.unit-overview__flags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 4px;
}

.unit-overview__flags span {
    padding: 2px 7px;
    border-radius: 999px;
    background: rgba(128, 0, 32, 0.08);
    color: #6f1026;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.unit-overview__stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 6px;
}

.unit-overview__top-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 148px;
    gap: 10px;
    align-items: stretch;
    margin-bottom: 10px;
}

.unit-overview__stat {
    padding: 7px 8px;
    border: 1px solid rgba(128, 0, 32, 0.12);
    border-radius: 12px;
    background: linear-gradient(180deg, #fff, #fff8f8);
}

.unit-overview__stat strong,
.unit-overview__stat span {
    display: block;
}

.unit-overview__stat strong {
    color: #5f0f24;
    font-size: 1.05rem;
    line-height: 1;
}

.unit-overview__stat span {
    margin-top: 2px;
    color: #7a6770;
    font-size: 0.68rem;
}

.unit-overview__workboard-tile {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 118px;
    aspect-ratio: 1 / 1;
    padding: 12px;
    border: 1px solid rgba(95, 15, 36, 0.16);
    border-radius: 18px;
    background:
        radial-gradient(circle at 85% 20%, rgba(255, 255, 255, 0.54), transparent 30%),
        linear-gradient(145deg, #6f1026, #2a151b);
    color: #fff7ee;
    box-shadow: 0 14px 28px rgba(95, 15, 36, 0.16);
}

.unit-overview__workboard-label {
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    opacity: 0.82;
}

.unit-overview__workboard-tile strong {
    font-size: 2.15rem;
    line-height: 0.95;
}

.unit-overview__workboard-tile span,
.unit-overview__workboard-tile small {
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1.15;
}

.unit-overview__workboard-tile small {
    width: fit-content;
    padding: 3px 6px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
}

.unit-overview__workboard-tile small.is-alert {
    background: #fff;
    color: #9f1239;
}

.unit-overview__empty {
    padding: 8px 10px;
    border: 1px dashed rgba(128, 0, 32, 0.22);
    border-radius: 12px;
    color: #8d7f85;
    font-size: 0.75rem;
}

.unit-overview__entities {
    min-width: 0;
    padding: 10px;
    border: 1px solid rgba(95, 15, 36, 0.12);
    border-radius: 18px;
    background:
        linear-gradient(180deg, rgba(255, 248, 248, 0.9), rgba(255, 255, 255, 0.96));
}

.unit-overview__body-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 12px;
    align-items: start;
}

.unit-overview__tabs-panel {
    min-width: 0;
}

.unit-overview__entities-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
}

.unit-overview__entities-head > div:first-child {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #6b5f64;
    font-size: 0.74rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.unit-overview__entities-head strong {
    min-width: 24px;
    padding: 2px 7px;
    border-radius: 999px;
    background: #5f0f24;
    color: #fff;
    font-size: 0.68rem;
    line-height: 1.2;
    text-align: center;
}

.unit-overview__entity-toolbar,
.unit-overview__entity-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.unit-overview__entity-toolbar button,
.unit-overview__entity-actions button {
    border: 1px solid rgba(95, 15, 36, 0.18);
    border-radius: 999px;
    background: #fff;
    color: #5f0f24;
    cursor: pointer;
    font-size: 0.64rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    line-height: 1;
    padding: 6px 8px;
    text-transform: uppercase;
}

.unit-overview__entity-toolbar button:hover,
.unit-overview__entity-actions button:hover {
    background: rgba(95, 15, 36, 0.08);
}

.unit-overview__entity-toolbar button:disabled,
.unit-overview__entity-actions button:disabled {
    cursor: wait;
    opacity: 0.5;
}

.unit-overview__entity-actions .is-danger {
    border-color: rgba(190, 18, 60, 0.2);
    color: #9f1239;
}

.unit-overview__entity-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
    max-height: 520px;
    overflow: auto;
    padding-right: 2px;
}

.unit-overview__entity {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    gap: 9px;
    padding: 10px;
    border: 1px solid rgba(95, 15, 36, 0.13);
    border-radius: 16px;
    background:
        linear-gradient(135deg, rgba(95, 15, 36, 0.055), transparent 48%),
        #fff;
    box-shadow: 0 8px 22px rgba(58, 31, 38, 0.05);
}

.unit-overview__entity-mark {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border-radius: 14px;
    background:
        radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.38), transparent 34%),
        linear-gradient(145deg, #6f1026, #2c1d20);
    color: #fff7ee;
    font-family: 'OrelegaOne-Regular', Georgia, serif;
    font-size: 1.02rem;
    letter-spacing: 0.03em;
}

.unit-overview__entity-body {
    min-width: 0;
}

.unit-overview__entity-name {
    display: inline-block;
    max-width: 100%;
    color: #35171f;
    font-family: 'OrelegaOne-Regular', Georgia, serif;
    font-size: 1.13rem;
    line-height: 1.05;
    text-decoration: none;
}

.unit-overview__entity-name:hover {
    color: #8a1631;
    text-decoration: underline;
    text-decoration-thickness: 1px;
    text-underline-offset: 3px;
}

.unit-overview__entity-kind {
    margin-top: 2px;
    color: #8a7880;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.unit-overview__entity-phones,
.unit-overview__entity-emails {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 7px;
}

.unit-overview__entity-phone {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    max-width: 100%;
    padding: 4px 8px 4px 5px;
    border: 1px solid rgba(13, 148, 136, 0.22);
    border-radius: 999px;
    background: rgba(13, 148, 136, 0.075);
    color: #135e55;
    cursor: pointer;
    font-size: 0.76rem;
    font-weight: 800;
    line-height: 1.1;
}

.unit-overview__entity-phone:hover:not(:disabled) {
    border-color: rgba(13, 148, 136, 0.48);
    background: rgba(13, 148, 136, 0.13);
    transform: translateY(-1px);
}

.unit-overview__entity-phone:disabled {
    cursor: wait;
    opacity: 0.72;
}

.unit-overview__entity-phone-icon {
    padding: 2px 5px;
    border-radius: 999px;
    background: #0f766e;
    color: #fff;
    font-size: 0.58rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.unit-overview__entity-phone small {
    color: #0f766e;
    font-size: 0.62rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.unit-overview__entity-emails a {
    color: #365486;
    font-size: 0.72rem;
    font-weight: 700;
    text-decoration: none;
}

.unit-overview__entity-emails a:hover {
    text-decoration: underline;
    text-underline-offset: 2px;
}

.unit-overview__entity-actions {
    margin-top: 8px;
}

.unit-overview__dial-feedback {
    margin-top: 8px;
    padding: 8px 10px;
    border-radius: 12px;
    font-size: 0.76rem;
    font-weight: 800;
}

.unit-overview__dial-feedback--success {
    background: rgba(13, 148, 136, 0.1);
    color: #0f766e;
}

.unit-overview__dial-feedback--error {
    background: rgba(190, 18, 60, 0.1);
    color: #9f1239;
}

@media (max-width: 700px) {
    .unit-overview__stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .unit-overview__top-grid,
    .unit-overview__body-grid,
    .unit-overview__entity-grid {
        grid-template-columns: 1fr;
    }

    .unit-overview__workboard-tile {
        aspect-ratio: auto;
        min-height: 96px;
    }
}

@media (max-width: 1100px) {
    .unit-overview__body-grid {
        grid-template-columns: 1fr;
    }
}
</style>
