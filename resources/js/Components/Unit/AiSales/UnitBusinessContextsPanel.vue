<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'

const props = defineProps({
    unitId: {
        type: Number,
        required: true,
    },
    initialCapabilities: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['unit-updated'])

const loading = ref(false)
const saving = ref(false)
const error = ref(null)
const dossier = ref(null)
const contextDialog = ref(false)
const roleDialog = ref(false)
const aliasDialog = ref(false)
const observationDialog = ref(false)
const proposalDialog = ref(false)

const capabilities = computed(() => dossier.value?.capabilities || props.initialCapabilities || {})
const options = computed(() => dossier.value?.options || {})
const contexts = computed(() => dossier.value?.contexts || [])
const activeContexts = computed(() => contexts.value.filter(item => !item.archived_at))
const linkedEntities = computed(() => dossier.value?.linked_entities || [])

const contextForm = reactive({
    lane: null,
    role_code: null,
    stage: 'new',
    status: 'active',
    confidence: null,
    primary_segment: null,
    source: 'manual',
})

const roleForm = reactive({
    role_code: null,
    source: 'manual',
})

const aliasForm = reactive({
    unit_business_context_id: null,
    alias: '',
    alias_type: 'trade_name',
    confidence: null,
    data_classification: 'public',
    visibility_scope: 'shared_public',
})

const observationForm = reactive({
    unit_business_context_id: null,
    observation_key: 'unit.profile_fact',
    normalized_value: '',
    summary: '',
    source_reference: '',
    confidence: null,
    data_classification: 'public',
    visibility_scope: 'shared_public',
})

const proposalForm = reactive({
    unit_business_context_id: null,
    action: 'create',
    existing_entity_id: null,
    proposed_name: '',
    evidence_summary: '',
    proposed_attributes: {},
})

async function loadDossier() {
    if (!capabilities.value.view || !props.unitId) return

    loading.value = true
    error.value = null

    try {
        const { data } = await axios.get(`/api/ai-sales/units/${props.unitId}/dossier`)
        dossier.value = data?.data || data
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Не удалось загрузить защищённый контекст Unit.'
    } finally {
        loading.value = false
    }
}

async function persist(url, payload, closeDialog = null) {
    saving.value = true
    error.value = null

    try {
        await axios.post(url, payload)
        if (closeDialog) closeDialog.value = false
        await loadDossier()
    } catch (exception) {
        const validation = exception.response?.data?.errors
        error.value = validation
            ? Object.values(validation).flat().join(' ')
            : exception.response?.data?.message || 'Операция отклонена policy.'
    } finally {
        saving.value = false
    }
}

function createContext() {
    return persist(`/api/ai-sales/units/${props.unitId}/contexts`, contextForm, contextDialog)
}

function assignRole() {
    return persist(`/api/ai-sales/units/${props.unitId}/roles`, roleForm, roleDialog)
}

async function archiveRole(role) {
    if (!window.confirm(`Архивировать роль «${role.display_name}»?`)) return

    saving.value = true
    error.value = null
    try {
        await axios.delete(`/api/ai-sales/units/${props.unitId}/roles/${role.id}`)
        await loadDossier()
    } catch (exception) {
        error.value = exception.response?.data?.message || Object.values(exception.response?.data?.errors || {}).flat().join(' ') || 'Роль не архивирована.'
    } finally {
        saving.value = false
    }
}

async function updateContext(context, attributes) {
    saving.value = true
    error.value = null
    try {
        await axios.patch(`/api/ai-sales/units/${props.unitId}/contexts/${context.id}`, attributes)
        await loadDossier()
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Context не обновлён.'
    } finally {
        saving.value = false
    }
}

function createAlias() {
    const payload = { ...aliasForm }
    if (payload.visibility_scope === 'shared_public') payload.unit_business_context_id = null

    return persist(`/api/ai-sales/units/${props.unitId}/aliases`, payload, aliasDialog)
}

function createObservation() {
    const payload = { ...observationForm }
    if (payload.visibility_scope === 'shared_public') payload.unit_business_context_id = null

    return persist(`/api/ai-sales/units/${props.unitId}/observations`, payload, observationDialog)
}

function reviewObservation(observation, verificationStatus) {
    return persist(
        `/api/ai-sales/units/${props.unitId}/observations/${observation.id}/review`,
        { verification_status: verificationStatus },
    )
}

async function promoteObservation(observation) {
    if (!window.confirm('Явно перенести проверенное значение в canonical Unit name?')) return

    await persist(`/api/ai-sales/units/${props.unitId}/observations/${observation.id}/promote`, {})
    emit('unit-updated')
}

function createEntityProposal() {
    const payload = {
        ...proposalForm,
        existing_entity_id: proposalForm.action === 'link_existing' ? proposalForm.existing_entity_id : null,
        proposed_name: proposalForm.action === 'create' ? proposalForm.proposed_name : null,
    }

    return persist(`/api/ai-sales/units/${props.unitId}/entity-proposals`, payload, proposalDialog)
}

function visibilityColor(scope) {
    return {
        shared_public: 'green',
        sales_lane: 'blue',
        procurement_lane: 'orange',
        internal_only: 'grey-darken-1',
    }[scope] || 'grey'
}

onMounted(loadDossier)
</script>

<template>
    <v-card class="unit-contexts" rounded="xl" variant="outlined">
        <v-card-title class="unit-contexts__header">
            <div>
                <div class="text-overline">Unit dossier boundary</div>
                <div class="text-h6">Роли, business contexts и provenance</div>
            </div>

            <v-btn
                v-if="capabilities.view"
                icon="mdi-refresh"
                size="small"
                variant="text"
                :loading="loading"
                @click="loadDossier"
            />
        </v-card-title>

        <v-card-text>
            <v-alert
                v-if="!capabilities.view"
                type="info"
                variant="tonal"
                density="compact"
            >
                Защищённое досье скрыто: требуется server-side permission <code>ai_sales.view</code>.
            </v-alert>

            <template v-else>
                <v-progress-linear v-if="loading" indeterminate class="mb-3" />
                <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>

                <v-alert
                    v-if="dossier?.dual_role_warning"
                    type="warning"
                    variant="tonal"
                    icon="mdi-shield-alert-outline"
                    class="mb-4"
                >
                    Unit одновременно работает в sales и procurement lanes. Данные направлений разделяются policy на сервере.
                </v-alert>

                <div class="unit-contexts__toolbar">
                    <div class="d-flex flex-wrap ga-2 align-center">
                        <span class="text-caption text-medium-emphasis">Роли</span>
                        <v-chip
                            v-for="role in (dossier?.roles || [])"
                            :key="role.code"
                            size="small"
                            color="indigo"
                            variant="tonal"
                            :closable="capabilities.manage_roles"
                            @click:close="archiveRole(role)"
                        >
                            {{ role.display_name }}
                        </v-chip>
                        <v-btn v-if="capabilities.manage_roles" size="x-small" variant="text" @click="roleDialog = true">
                            Добавить роль
                        </v-btn>
                    </div>

                    <v-btn
                        v-if="capabilities.manage_contexts"
                        size="small"
                        color="primary"
                        prepend-icon="mdi-plus"
                        @click="contextDialog = true"
                    >
                        Context
                    </v-btn>
                </div>

                <div class="unit-contexts__grid mb-5">
                    <v-card
                        v-for="context in contexts"
                        :key="context.id"
                        variant="tonal"
                        :class="{ 'opacity-60': context.archived_at }"
                    >
                        <v-card-text>
                            <div class="d-flex justify-space-between ga-2">
                                <div>
                                    <strong>{{ context.role_label }}</strong>
                                    <div class="text-caption">{{ context.lane_label }} · {{ context.stage_label }}</div>
                                </div>
                                <v-chip size="x-small" :color="context.lane === 'sales' ? 'blue' : context.lane === 'procurement' ? 'orange' : 'grey'">
                                    {{ context.status_label }}
                                </v-chip>
                            </div>

                            <div class="text-caption mt-3">
                                Owner: {{ context.owner?.name || '—' }} · Reviewer: {{ context.reviewer?.name || '—' }}
                            </div>
                            <div class="text-caption">Confidence: {{ context.confidence ?? '—' }} · Source: {{ context.source }}</div>

                            <v-select
                                v-if="capabilities.manage_contexts && !context.archived_at"
                                class="mt-2"
                                :model-value="context.stage"
                                :items="options.stages || []"
                                item-title="label"
                                item-value="code"
                                label="Stage"
                                density="compact"
                                hide-details
                                @update:model-value="value => updateContext(context, { stage: value })"
                            />
                        </v-card-text>
                    </v-card>

                    <div v-if="!contexts.length" class="text-medium-emphasis text-caption">Business contexts пока не созданы.</div>
                </div>

                <v-row>
                    <v-col cols="12" lg="5">
                        <div class="d-flex align-center justify-space-between mb-2">
                            <strong>Aliases</strong>
                            <v-btn v-if="capabilities.manage_aliases" size="x-small" variant="text" @click="aliasDialog = true">Добавить</v-btn>
                        </div>
                        <v-list density="compact" class="border rounded-lg">
                            <v-list-item v-for="alias in (dossier?.aliases || [])" :key="alias.id" :title="alias.alias">
                                <template #subtitle>
                                    {{ alias.alias_type }} · {{ alias.verification_status }} · confidence {{ alias.confidence ?? '—' }}
                                </template>
                                <template v-if="capabilities.view_internal_classifications" #append>
                                    <v-chip size="x-small" :color="visibilityColor(alias.visibility_scope)">{{ alias.visibility_scope }}</v-chip>
                                </template>
                            </v-list-item>
                            <v-list-item v-if="!dossier?.aliases?.length" title="Нет aliases" />
                        </v-list>
                    </v-col>

                    <v-col cols="12" lg="7">
                        <div class="d-flex align-center justify-space-between mb-2">
                            <strong>Observations и provenance</strong>
                            <v-btn v-if="capabilities.manage_observations" size="x-small" variant="text" @click="observationDialog = true">Добавить</v-btn>
                        </div>
                        <v-list density="compact" class="border rounded-lg unit-contexts__observations">
                            <v-list-item v-for="observation in (dossier?.observations || [])" :key="observation.id">
                                <v-list-item-title>{{ observation.summary }}</v-list-item-title>
                                <v-list-item-subtitle>
                                    {{ observation.observation_key }} · {{ observation.verification_status }} · confidence {{ observation.confidence ?? '—' }}
                                    <span v-if="observation.source?.label || observation.source_reference">
                                        · source: {{ observation.source?.label || observation.source_reference }}
                                    </span>
                                </v-list-item-subtitle>
                                <template #append>
                                    <div class="d-flex ga-1 align-center">
                                        <v-chip
                                            v-if="capabilities.view_internal_classifications"
                                            size="x-small"
                                            :color="visibilityColor(observation.visibility_scope)"
                                        >
                                            {{ observation.data_classification }} / {{ observation.visibility_scope }}
                                        </v-chip>
                                        <v-btn
                                            v-if="capabilities.verify_observations && observation.verification_status === 'unverified'"
                                            size="x-small"
                                            variant="text"
                                            @click="reviewObservation(observation, 'verified')"
                                        >
                                            Verify
                                        </v-btn>
                                        <v-btn
                                            v-if="capabilities.verify_observations"
                                            size="x-small"
                                            variant="text"
                                            @click="reviewObservation(observation, 'contradicted')"
                                        >
                                            Contradict
                                        </v-btn>
                                        <v-btn
                                            v-if="capabilities.promote_observations && observation.verification_status === 'verified' && observation.observation_key === 'unit.name'"
                                            size="x-small"
                                            variant="text"
                                            @click="promoteObservation(observation)"
                                        >
                                            Promote
                                        </v-btn>
                                    </div>
                                </template>
                            </v-list-item>
                            <v-list-item v-if="!dossier?.observations?.length" title="Нет observations" />
                        </v-list>
                    </v-col>
                </v-row>

                <v-divider class="my-5" />

                <v-row>
                    <v-col cols="12" lg="6">
                        <div class="d-flex justify-space-between align-center mb-2">
                            <strong>Связанные Entity</strong>
                            <v-btn v-if="capabilities.propose_entity" size="x-small" variant="text" @click="proposalDialog = true">
                                Создать proposal
                            </v-btn>
                        </div>
                        <v-alert type="info" variant="tonal" density="compact" class="mb-2">
                            Proposal не создаёт и не привязывает Entity. Финальное действие требует отдельного human permission, duplicate check и audit.
                        </v-alert>
                        <v-chip v-for="entity in linkedEntities" :key="entity.id" class="mr-2 mb-2" size="small">
                            {{ entity.name }}
                        </v-chip>
                        <div v-if="!linkedEntities.length" class="text-caption text-medium-emphasis">
                            Entity identities скрыты или ещё не привязаны.
                        </div>
                        <v-list v-if="dossier?.entity_proposals?.length" density="compact" class="mt-2 border rounded-lg">
                            <v-list-item
                                v-for="proposal in dossier.entity_proposals"
                                :key="proposal.id"
                                :title="proposal.proposed_name"
                                :subtitle="`${proposal.action} · ${proposal.status} · duplicates: ${proposal.duplicate_candidate_ids.length}`"
                            />
                        </v-list>
                    </v-col>

                    <v-col cols="12" lg="6">
                        <strong>Audit / activity</strong>
                        <v-timeline v-if="capabilities.view_audit && dossier?.audit?.length" density="compact" side="end" class="mt-2">
                            <v-timeline-item v-for="event in dossier.audit" :key="event.id" size="x-small">
                                <div class="text-caption font-weight-bold">{{ event.event_type }}</div>
                                <div class="text-caption">{{ event.summary }}</div>
                                <div class="text-caption text-medium-emphasis">{{ event.actor?.name || event.actor?.type }} · {{ event.created_at }}</div>
                            </v-timeline-item>
                        </v-timeline>
                        <div v-else class="text-caption text-medium-emphasis mt-2">Audit скрыт permission или пока пуст.</div>
                    </v-col>
                </v-row>
            </template>
        </v-card-text>

        <v-dialog v-model="roleDialog" max-width="520">
            <v-card title="Назначить market role">
                <v-card-text>
                    <v-select v-model="roleForm.role_code" :items="options.roles || []" item-title="label" item-value="code" label="Role" />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="roleDialog = false">Отмена</v-btn>
                    <v-btn color="primary" :disabled="!roleForm.role_code" :loading="saving" @click="assignRole">Сохранить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="contextDialog" max-width="680">
            <v-card title="Unit business context">
                <v-card-text>
                    <v-row>
                        <v-col cols="12" sm="6"><v-select v-model="contextForm.lane" :items="options.lanes || []" item-title="label" item-value="code" label="Lane" /></v-col>
                        <v-col cols="12" sm="6"><v-select v-model="contextForm.role_code" :items="options.roles || []" item-title="label" item-value="code" label="Role" /></v-col>
                        <v-col cols="12" sm="6"><v-select v-model="contextForm.stage" :items="options.stages || []" item-title="label" item-value="code" label="Stage" /></v-col>
                        <v-col cols="12" sm="6"><v-text-field v-model.number="contextForm.confidence" type="number" min="0" max="100" label="Confidence" /></v-col>
                        <v-col cols="12"><v-text-field v-model="contextForm.primary_segment" label="Primary segment" /></v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="contextDialog = false">Отмена</v-btn>
                    <v-btn color="primary" :disabled="!contextForm.lane || !contextForm.role_code" :loading="saving" @click="createContext">Сохранить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="aliasDialog" max-width="620">
            <v-card title="Добавить alias">
                <v-card-text>
                    <v-select v-model="aliasForm.unit_business_context_id" :items="activeContexts" item-title="role_label" item-value="id" clearable label="Context (обязателен для lane scope)" />
                    <v-text-field v-model="aliasForm.alias" label="Alias" />
                    <v-select v-model="aliasForm.alias_type" :items="options.alias_types || []" label="Type" />
                    <v-text-field v-model.number="aliasForm.confidence" type="number" min="0" max="100" label="Confidence" />
                    <v-row>
                        <v-col cols="12" sm="6"><v-select v-model="aliasForm.data_classification" :items="options.data_classifications || []" label="Classification" /></v-col>
                        <v-col cols="12" sm="6"><v-select v-model="aliasForm.visibility_scope" :items="options.visibility_scopes || []" label="Visibility" /></v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="aliasDialog = false">Отмена</v-btn>
                    <v-btn color="primary" :disabled="!aliasForm.alias" :loading="saving" @click="createAlias">Сохранить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="observationDialog" max-width="760">
            <v-card title="Добавить observation">
                <v-card-text>
                    <v-select v-model="observationForm.unit_business_context_id" :items="activeContexts" item-title="role_label" item-value="id" clearable label="Context (пусто только для shared public)" />
                    <v-text-field v-model="observationForm.observation_key" label="Observation key" />
                    <v-text-field v-model="observationForm.normalized_value" label="Normalized value" />
                    <v-textarea v-model="observationForm.summary" label="Summary" rows="3" />
                    <v-text-field v-model="observationForm.source_reference" label="Source reference" />
                    <v-row>
                        <v-col cols="12" sm="4"><v-text-field v-model.number="observationForm.confidence" type="number" min="0" max="100" label="Confidence" /></v-col>
                        <v-col cols="12" sm="4"><v-select v-model="observationForm.data_classification" :items="options.data_classifications || []" label="Classification" /></v-col>
                        <v-col cols="12" sm="4"><v-select v-model="observationForm.visibility_scope" :items="options.visibility_scopes || []" label="Visibility" /></v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="observationDialog = false">Отмена</v-btn>
                    <v-btn color="primary" :disabled="!observationForm.summary" :loading="saving" @click="createObservation">Сохранить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="proposalDialog" max-width="720">
            <v-card title="Entity candidate proposal">
                <v-card-text>
                    <v-select v-model="proposalForm.unit_business_context_id" :items="activeContexts" item-title="role_label" item-value="id" label="Context" />
                    <v-select v-model="proposalForm.action" :items="options.entity_proposal_actions || []" label="Action" />
                    <v-text-field
                        v-if="proposalForm.action === 'link_existing'"
                        v-model.number="proposalForm.existing_entity_id"
                        type="number"
                        min="1"
                        label="Existing Entity ID"
                    />
                    <v-text-field v-else v-model="proposalForm.proposed_name" label="Proposed Entity name" />
                    <v-textarea v-model="proposalForm.evidence_summary" label="Evidence summary" rows="3" />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="proposalDialog = false">Отмена</v-btn>
                    <v-btn color="primary" :disabled="!proposalForm.unit_business_context_id || !proposalForm.evidence_summary" :loading="saving" @click="createEntityProposal">
                        Только proposal
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>

<style scoped>
.unit-contexts {
    border-color: rgba(63, 81, 181, 0.18);
    background: linear-gradient(135deg, rgba(245, 247, 255, 0.96), rgba(255, 255, 255, 0.98));
}

.unit-contexts__header,
.unit-contexts__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.unit-contexts__toolbar {
    margin-bottom: 16px;
}

.unit-contexts__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 10px;
}

.unit-contexts__observations {
    max-height: 420px;
    overflow: auto;
}

@media (max-width: 720px) {
    .unit-contexts__header,
    .unit-contexts__toolbar {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
