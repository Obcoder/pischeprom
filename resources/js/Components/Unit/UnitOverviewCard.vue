<script setup>
import { computed, reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import axios from 'axios'

import UnitFilesTab from '@/Components/Unit/UnitFilesTab.vue'
import UnitBuildingsTab from '@/Components/Unit/UnitBuildingsTab.vue'
import UnitAdminTab from '@/Components/Unit/UnitAdminTab.vue'
import UnitRelationManagerDialog from '@/Components/Unit/UnitRelationManagerDialog.vue'
import UnitMailComposerDialog from '@/Components/Unit/Mail/UnitMailComposerDialog.vue'


const props = defineProps({
    section: { type: String, default: 'entities' },
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

const unitDialog = ref(false)
const savingUnit = ref(false)
const deletingUnit = ref(false)
const unitErrors = ref({})

const unitForm = reactive({
    name: '',
    is_customer: false,
    is_supplier: false,
})

function fillUnitForm() {
    unitForm.name = props.unit?.name || ''
    unitForm.is_customer = Boolean(props.unit?.is_customer)
    unitForm.is_supplier = Boolean(props.unit?.is_supplier)
}

function openUnitDialog() {
    unitErrors.value = {}
    fillUnitForm()
    unitDialog.value = true
}

async function saveUnit() {
    savingUnit.value = true
    unitErrors.value = {}

    try {
        await axios.put(`/api/units/${props.unit.id}`, unitForm)
        unitDialog.value = false
        emit('refresh')
    } catch (error) {
        unitErrors.value = error.response?.data?.errors || {}
        console.error('unit save error:', error.response?.data || error)
    } finally {
        savingUnit.value = false
    }
}

async function deleteUnit() {
    if (!window.confirm(`Удалить Unit "${props.unit.name}"?`)) return

    deletingUnit.value = true

    try {
        await axios.delete(`/api/units/${props.unit.id}`)
        router.visit('/Ameise/units')
    } finally {
        deletingUnit.value = false
    }
}

const dialogLabels = ref(false)
const dialogFields = ref(false)
const dialogCities = ref(false)
const dialogAttachEntity = ref(false)
const dialogEntityForm = ref(false)

const savingLabels = ref(false)
const savingFields = ref(false)
const savingCities = ref(false)
const savingEntity = ref(false)
const savingEntityRelation = ref(false)
const editingEntity = ref(null)
const attachEntityId = ref(null)
const entityErrors = ref({})

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

const unitEntities = computed(() => props.unit?.entities || [])

function entityHref(entity) {
    try {
        return route('Ameise.entity.show', entity.id)
    } catch (error) {
        return `/Ameise/entity/${entity.id}`
    }
}

function entityClassification(entity) {
    return entity?.classification?.name || 'Entity'
}

function openFileMail(file) {
    quickMailFiles.value = file?.path ? [file.path] : []
    quickMailRecipients.value = cloneRecipients(unitMailRecipients.value)
    quickMailDialog.value = true
}


defineExpose({ openUnitDialog })
</script>

<template>
    <div class="unit-details">
        <section v-if="section === 'entities'" class="unit-details__section">
            <div class="unit-details__toolbar">
                <p>Юридические лица и контакты, связанные с Unit</p>
                <div class="unit-details__actions">
                    <button type="button" @click="dialogAttachEntity = true">Привязать Entity</button>
                    <button type="button" class="is-primary" @click="openCreateEntity">Создать Entity</button>
                </div>
            </div>
            <div class="unit-details__entity-list">
                <article v-for="entity in unitEntities" :key="entity.id" class="unit-details__entity">
                    <div class="unit-details__entity-name">
                        <a :href="entityHref(entity)">{{ entity.name }}</a>
                        <span v-if="entity.full_name && entity.full_name !== entity.name">{{ entity.full_name }}</span>
                    </div>
                    <span class="unit-details__muted">{{ entityClassification(entity) }}</span>
                    <span class="unit-details__muted">{{ entity.INN ? `ИНН ${entity.INN}` : 'ИНН не указан' }}</span>
                    <div class="unit-details__actions">
                        <button type="button" @click="openEditEntity(entity)">Изменить</button>
                        <button type="button" :disabled="savingEntityRelation" @click="detachEntityFromUnit(entity)">Отвязать</button>
                        <button type="button" class="is-danger" :disabled="savingEntityRelation" @click="deleteEntity(entity)">Удалить</button>
                    </div>
                </article>
                <p v-if="!unitEntities.length" class="unit-details__empty">Связанных Entities пока нет. Привяжите существующую запись или создайте новую.</p>
            </div>
        </section>

        <section v-if="section === 'classification'" class="unit-details__section">
            <div class="unit-details__classification">
                <div class="unit-details__group">
                    <div class="unit-details__toolbar">
                        <h2>Метки <span>Labels</span></h2>
                        <button type="button" @click="dialogLabels = true">Изменить</button>
                    </div>
                    <div v-if="unit.labels?.length" class="unit-details__tags">
                        <span v-for="label in unit.labels" :key="label.id">{{ label.name }}</span>
                    </div>
                    <p v-else class="unit-details__empty">Метки не назначены</p>
                </div>
                <div class="unit-details__group">
                    <div class="unit-details__toolbar">
                        <h2>Сферы деятельности <span>Fields</span></h2>
                        <button type="button" @click="dialogFields = true">Изменить</button>
                    </div>
                    <div v-if="unit.fields?.length" class="unit-details__tags">
                        <span v-for="field in unit.fields" :key="field.id">{{ field.name || field.title }}</span>
                    </div>
                    <p v-else class="unit-details__empty">Сферы деятельности не назначены</p>
                </div>
            </div>
            <UnitAdminTab :unit="unit" :dict="dict" @refresh="emit('refresh')" />
        </section>

        <section v-if="section === 'logistics'" class="unit-details__section">
            <div class="unit-details__toolbar">
                <h2>География работы</h2>
                <button type="button" @click="dialogCities = true">Изменить города</button>
            </div>
            <div v-if="unit.cities?.length" class="unit-details__tags">
                <span v-for="city in unit.cities" :key="city.id">{{ city.name }}<small v-if="city.region?.name"> · {{ city.region.name }}</small></span>
            </div>
            <p v-else class="unit-details__empty">Города офисов, складов, производств и доставки не указаны</p>
            <h2 class="unit-details__subheading">Офисы, склады, производство и доставка</h2>
            <UnitBuildingsTab :unit="unit" :dict="dict" @refresh="emit('refresh')" />
        </section>

        <section v-if="section === 'files'" class="unit-details__section">
            <UnitFilesTab v-if="unit?.id" :unit-id="Number(unit.id)" @send-file="openFileMail" />
        </section>
        <v-dialog v-model="unitDialog" max-width="640">
            <v-card rounded="0">
                <v-card-title class="unit-overview__unit-dialog-title">
                    Редактировать Unit
                </v-card-title>

                <v-card-text>
                    <v-text-field
                        v-model="unitForm.name"
                        label="Название"
                        variant="outlined"
                        density="compact"
                        :error-messages="unitErrors.name || []"
                    />

                    <div class="unit-overview__unit-switches">
                        <v-switch
                            v-model="unitForm.is_customer"
                            label="Покупатель"
                            color="#352345"
                            hide-details
                            density="compact"
                        />
                        <v-switch
                            v-model="unitForm.is_supplier"
                            label="Поставщик"
                            color="#352345"
                            hide-details
                            density="compact"
                        />
                    </div>
                </v-card-text>

                <v-card-actions class="justify-space-between">
                    <v-btn
                        variant="text"
                        color="error"
                        :loading="deletingUnit"
                        @click="deleteUnit"
                    >
                        Удалить Unit
                    </v-btn>
                    <div>
                        <v-btn variant="text" @click="unitDialog = false">
                            Отмена
                        </v-btn>
                        <v-btn
                            color="#352345"
                            :disabled="!unitForm.name"
                            :loading="savingUnit"
                            @click="saveUnit"
                        >
                            Сохранить
                        </v-btn>
                    </div>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="dialogAttachEntity" max-width="720">
            <v-card rounded="0">
                <v-card-title>Привязать Entity</v-card-title>

                <v-card-text>
                    <v-autocomplete
                        v-model="attachEntityId"
                        :items="dict.entities || []"
                        item-title="name"
                        item-value="id"
                        label="Entity"
                        variant="outlined"
                        density="compact"
                        clearable
                    />
                </v-card-text>

                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogAttachEntity = false">
                        Отмена
                    </v-btn>

                    <v-btn
                        color="#352345"
                        :disabled="!attachEntityId"
                        :loading="savingEntityRelation"
                        @click="attachEntityToUnit"
                    >
                        Привязать
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="dialogEntityForm" max-width="820">
            <v-card rounded="0">
                <v-card-title>
                    {{ editingEntity ? 'Редактировать Entity' : 'Создать Entity' }}
                </v-card-title>

                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="entityForm.name"
                                label="Название"
                                variant="outlined"
                                density="compact"
                                :error-messages="entityErrors.name || []"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="entityForm.entity_classification_id"
                                :items="dict.entityClassifications || []"
                                item-title="name"
                                item-value="id"
                                label="Классификация"
                                variant="outlined"
                                density="compact"
                                clearable
                                :error-messages="entityErrors.entity_classification_id || []"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-text-field
                                v-model="entityForm.full_name"
                                label="Полное название"
                                variant="outlined"
                                density="compact"
                                :error-messages="entityErrors.full_name || []"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-autocomplete
                                v-model="entityForm.telephones"
                                :items="dict.telephones || []"
                                item-title="number"
                                item-value="id"
                                label="Телефоны"
                                variant="outlined"
                                density="compact"
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
                        Отмена
                    </v-btn>

                    <v-btn
                        color="#352345"
                        :disabled="!entityForm.name"
                        :loading="savingEntity"
                        @click="saveEntity"
                    >
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <UnitRelationManagerDialog
            v-model="dialogLabels"
            title="Метки · Labels"
            :items="unit.labels || []"
            :dict-items="dict.labels || []"
            item-title="name"
            item-value="id"
            :loading="savingLabels"
            @save="syncLabels"
        />

        <UnitRelationManagerDialog
            v-model="dialogFields"
            title="Сферы деятельности · Fields"
            :items="unit.fields || []"
            :dict-items="dict.fields || []"
            item-title="name"
            item-value="id"
            :loading="savingFields"
            @save="syncFields"
        />

        <UnitRelationManagerDialog
            v-model="dialogCities"
            title="География работы"
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
    </div>
</template>

<style scoped>
.unit-details { min-width: 0; }
.unit-details__section { padding: 14px; border: 1px solid #d9d7dc; background: #fff; }
.unit-details__toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.unit-details__toolbar p, .unit-details__muted { color: #77727b; font-size: 12px; }
.unit-details h2 { font-size: 13px; font-weight: 700; margin: 0; }
.unit-details h2 span { color: #77727b; font-size: 11px; font-weight: 400; margin-left: 5px; }
.unit-details__actions { display: flex; gap: 5px; flex-wrap: wrap; }
.unit-details button { padding: 5px 9px; border: 1px solid #d9d7dc; color: #352345; font-size: 11px; font-weight: 600; background: #fff; min-height: 30px; }
.unit-details button:hover { background: #f2f1f3; }
.unit-details button:focus-visible { outline: 2px solid #352345; outline-offset: 2px; }
.unit-details button:disabled { opacity: .45; cursor: default; }
.unit-details button.is-primary { background: #352345; border-color: #352345; color: #fff; }
.unit-details button.is-danger { color: #651c2e; }
.unit-details__entity { display: grid; grid-template-columns: minmax(180px, 1.5fr) minmax(100px, .6fr) minmax(130px, .6fr) auto; align-items: center; gap: 14px; padding: 12px 0; border-top: 1px solid #e8e6e9; }
.unit-details__entity-name { display: grid; gap: 3px; min-width: 0; }
.unit-details__entity-name a { color: #352345; text-decoration: none; font-size: 13px; font-weight: 650; overflow-wrap: anywhere; }
.unit-details__entity-name a:hover { text-decoration: underline; }
.unit-details__entity-name span { color: #77727b; font-size: 11px; }
.unit-details__classification { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid #d9d7dc; gap: 24px; padding-bottom: 16px; margin-bottom: 16px; }
.unit-details__tags { display: flex; flex-wrap: wrap; gap: 6px; }
.unit-details__tags > span { padding: 4px 8px; color: #352345; border: 1px solid #d9d7dc; background: #f7f6f8; font-size: 12px; }
.unit-details__empty { padding: 12px 0; color: #77727b; font-size: 12px; }
.unit-details h2.unit-details__subheading { margin: 20px 0 12px; padding-top: 14px; border-top: 1px solid #d9d7dc; }
.unit-overview__unit-switches { display: flex; gap: 24px; }
@media (max-width: 900px) {
    .unit-details__entity { grid-template-columns: 1fr 1fr; }
    .unit-details__classification { grid-template-columns: 1fr; gap: 16px; }
}
@media (max-width: 600px) {
    .unit-details__toolbar { align-items: flex-start; flex-direction: column; }
    .unit-details__entity { grid-template-columns: 1fr; gap: 8px; }
}
</style>
