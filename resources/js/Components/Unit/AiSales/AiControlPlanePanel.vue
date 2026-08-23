<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'

const props = defineProps({
    unitId: { type: Number, required: true },
    initialCapabilities: { type: Object, default: () => ({}) },
})

const loading = ref(false)
const saving = ref(false)
const error = ref(null)
const control = ref(null)
const definitions = ref([])
const runs = ref([])
const contexts = ref([])
const tooling = ref(null)
const runDialog = ref(false)
const capabilities = computed(() => props.initialCapabilities || {})
const runForm = reactive({ definition: null, contextId: null })

const activeDefinitions = computed(() => definitions.value.filter((definition) => definition.enabled))
const nonTerminalStatuses = new Set([
    'queued', 'preparing', 'policy_check', 'ready', 'sent', 'requires_action', 'processing',
])

async function load() {
    if (!capabilities.value.view_control_plane) return

    loading.value = true
    error.value = null

    try {
        const requests = [
            axios.get('/api/ai-sales/control-plane'),
            axios.get('/api/ai-sales/agent-definitions'),
            axios.get(`/api/ai-sales/runs?unit_id=${props.unitId}`),
            axios.get(`/api/ai-sales/units/${props.unitId}/dossier`),
            capabilities.value.view_ai_tooling
                ? axios.get('/api/ai-sales/tooling')
                : Promise.resolve({ data: { data: null } }),
        ]
        const [controlResponse, definitionsResponse, runsResponse, dossierResponse, toolingResponse] = await Promise.all(requests)
        control.value = controlResponse.data?.data || null
        definitions.value = definitionsResponse.data?.data || []
        runs.value = runsResponse.data?.data || []
        contexts.value = dossierResponse.data?.data?.contexts || []
        tooling.value = toolingResponse.data?.data || null
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Не удалось загрузить AI Control Plane.'
    } finally {
        loading.value = false
    }
}

async function setKillSwitch(scope, enabled) {
    saving.value = true
    error.value = null

    try {
        const { data } = await axios.patch(`/api/ai-sales/control-plane/kill-switches/${scope}`, { enabled })
        control.value.kill_switches = data.data.kill_switches
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Kill switch не изменён.'
    } finally {
        saving.value = false
    }
}

function openRunDialog() {
    runForm.definition = activeDefinitions.value[0] || null
    runForm.contextId = contexts.value[0]?.id || null
    runDialog.value = true
}

async function createRun() {
    if (!runForm.definition || !runForm.contextId) return

    saving.value = true
    error.value = null

    try {
        await axios.post('/api/ai-sales/runs', {
            definition_code: runForm.definition.code,
            definition_version: runForm.definition.version,
            unit_id: props.unitId,
            unit_business_context_id: runForm.contextId,
            idempotency_key: `ui-${crypto.randomUUID()}`,
        })
        runDialog.value = false
        await load()
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Synthetic run отклонён server-side policy.'
    } finally {
        saving.value = false
    }
}

async function cancelRun(run) {
    saving.value = true
    error.value = null

    try {
        await axios.post(`/api/ai-sales/runs/${run.id}/cancel`)
        await load()
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Run не отменён.'
    } finally {
        saving.value = false
    }
}

function contourColor(contour) {
    return contour === 'local_ru' ? 'teal' : contour === 'external_sanitized' ? 'deep-purple' : 'grey'
}

onMounted(load)
</script>

<template>
    <v-card rounded="xl" variant="outlined">
        <v-card-title class="d-flex justify-space-between align-center ga-3">
            <div>
                <div class="text-overline">Stage 07 · workflows default-off</div>
                <div class="text-h6">AI Control Plane, контуры и safe tools</div>
            </div>
            <div class="d-flex ga-2">
                <v-btn
                    v-if="capabilities.create_ai_run"
                    size="small"
                    color="primary"
                    prepend-icon="mdi-play-circle-outline"
                    :disabled="!activeDefinitions.length || !contexts.length"
                    @click="openRunDialog"
                >
                    Synthetic run
                </v-btn>
                <v-btn v-if="capabilities.view_control_plane" icon="mdi-refresh" size="small" variant="text" :loading="loading" @click="load" />
            </div>
        </v-card-title>

        <v-card-text>
            <v-alert v-if="!capabilities.view_control_plane" type="info" density="compact" variant="tonal">
                Control Plane скрыт server-side permission.
            </v-alert>

            <template v-else>
                <v-progress-linear v-if="loading" indeterminate class="mb-3" />
                <v-alert v-if="error" type="error" density="compact" variant="tonal" class="mb-3">{{ error }}</v-alert>
                <v-alert type="info" density="compact" variant="tonal" class="mb-4">
                    Unit runtime остаётся fake-only. Timeweb доступен только guarded CLI с repository-owned synthetic fixtures; raw bodies и ключи не показываются и не сохраняются, fallback отсутствует.
                </v-alert>

                <v-row v-if="control">
                    <v-col cols="12" lg="5">
                        <strong>Feature flags и kill switches</strong>
                        <v-list density="compact" class="border rounded-lg mt-2">
                            <v-list-item title="External HTTP egress">
                                <template #append><v-chip size="x-small" :color="control.features.external_http_enabled ? 'error' : 'success'">{{ control.features.external_http_enabled ? 'ON' : 'OFF' }}</v-chip></template>
                            </v-list-item>
                            <v-list-item title="Provider failover">
                                <template #append><v-chip size="x-small" :color="control.features.failover_enabled ? 'error' : 'success'">{{ control.features.failover_enabled ? 'ON' : 'OFF' }}</v-chip></template>
                            </v-list-item>
                            <v-list-item title="Timeweb AI Gateway">
                                <template #append><v-chip size="x-small" :color="control.features.timeweb_enabled ? 'warning' : 'success'">{{ control.features.timeweb_enabled ? 'STAGING ONLY' : 'OFF' }}</v-chip></template>
                            </v-list-item>
                            <v-list-item title="Timeweb synthetic probes">
                                <template #append><v-chip size="x-small" :color="control.features.timeweb_probe_enabled ? 'warning' : 'success'">{{ control.features.timeweb_probe_enabled ? 'ON' : 'OFF' }}</v-chip></template>
                            </v-list-item>
                            <v-list-item title="Server-owned tools">
                                <template #append><v-chip size="x-small" :color="control.features.tools_enabled ? 'warning' : 'success'">{{ control.features.tools_enabled ? 'DEV/TEST' : 'OFF' }}</v-chip></template>
                            </v-list-item>
                            <v-list-item title="Server-owned workflows">
                                <template #append><v-chip size="x-small" :color="control.features.workflows_enabled ? 'warning' : 'success'">{{ control.features.workflows_enabled ? 'DEV/TEST' : 'OFF' }}</v-chip></template>
                            </v-list-item>
                            <v-list-item title="Provider-native tools">
                                <template #append><v-chip size="x-small" :color="control.features.provider_native_tools_enabled ? 'error' : 'success'">{{ control.features.provider_native_tools_enabled ? 'BLOCK REQUIRED' : 'OFF' }}</v-chip></template>
                            </v-list-item>
                            <v-list-item title="Live business workflows">
                                <template #append><v-chip size="x-small" :color="control.features.live_business_workflows_enabled ? 'error' : 'success'">{{ control.features.live_business_workflows_enabled ? 'BLOCK REQUIRED' : 'OFF' }}</v-chip></template>
                            </v-list-item>
                            <v-list-item v-for="(enabled, scope) in control.kill_switches" :key="scope" :title="`Kill switch: ${scope}`">
                                <template #append>
                                    <v-switch
                                        :model-value="enabled"
                                        color="error"
                                        density="compact"
                                        hide-details
                                        :disabled="!capabilities.manage_ai_kill_switches || saving"
                                        @update:model-value="value => setKillSwitch(scope, value)"
                                    />
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-col>

                    <v-col cols="12" lg="7">
                        <strong>Definitions и verification state</strong>
                        <v-list density="compact" class="border rounded-lg mt-2">
                            <v-list-item v-for="definition in definitions" :key="`${definition.code}:${definition.version}`" :title="definition.display_name">
                                <v-list-item-subtitle>{{ definition.code }}:{{ definition.version }} · {{ definition.task_profile }}</v-list-item-subtitle>
                                <template #append>
                                    <v-chip size="x-small" :color="definition.enabled ? 'success' : 'grey'">{{ definition.enabled ? 'enabled' : 'disabled' }}</v-chip>
                                </template>
                            </v-list-item>
                        </v-list>
                        <div class="text-caption text-medium-emphasis mt-2">
                            Capability rows: {{ control.capabilities.length }} · Inventory models: {{ control.provider_models.length }} · Residency attestations: {{ control.residency_verifications.length }}
                        </div>
                        <template v-if="control.permissions.view_capabilities">
                            <div class="text-caption font-weight-medium mt-3">Verified capability matrix</div>
                            <v-list density="compact" class="border rounded-lg mt-1">
                                <v-list-item
                                    v-for="item in control.capabilities.slice(0, 12)"
                                    :key="`${item.provider}:${item.route}:${item.model}:${item.capability}`"
                                    :title="`${item.model} · ${item.capability}`"
                                    :subtitle="`${item.provider}/${item.route} · ${item.contour}`"
                                >
                                    <template #append><v-chip size="x-small">{{ item.support }} / {{ item.status }}</v-chip></template>
                                </v-list-item>
                                <v-list-item v-if="!control.capabilities.length" title="Capability evidence отсутствует" />
                            </v-list>
                            <div class="text-caption font-weight-medium mt-3">Timeweb safe model inventory</div>
                            <v-list density="compact" class="border rounded-lg mt-1">
                                <v-list-item
                                    v-for="item in control.provider_models.slice(0, 12)"
                                    :key="`${item.provider}:${item.route}:${item.model}`"
                                    :title="item.model"
                                    :subtitle="`${item.route} · ${item.endpoint_profile} · last seen ${item.last_seen_at || 'never'}`"
                                >
                                    <template #append><v-chip size="x-small" :color="item.active_in_inventory ? 'success' : 'grey'">{{ item.active_in_inventory ? 'active' : 'inactive' }}</v-chip></template>
                                </v-list-item>
                                <v-list-item v-if="!control.provider_models.length" title="Timeweb inventory ещё не синхронизирован" />
                            </v-list>
                            <div v-if="control.timeweb" class="text-caption text-medium-emphasis mt-2">
                                Keys: local {{ control.timeweb.local_ru.key_configured ? `configured …${control.timeweb.local_ru.key_fingerprint_suffix}` : 'not configured' }};
                                external {{ control.timeweb.external_sanitized.key_configured ? `configured …${control.timeweb.external_sanitized.key_fingerprint_suffix}` : 'not configured' }}.
                            </div>
                            <div class="text-caption font-weight-medium mt-3">Local residency verification</div>
                            <v-list density="compact" class="border rounded-lg mt-1">
                                <v-list-item
                                    v-for="item in control.residency_verifications.slice(0, 8)"
                                    :key="`${item.provider}:${item.route}:${item.model}`"
                                    :title="`${item.model} · ${item.status}`"
                                    :subtitle="`${item.declared_contour}/${item.declared_country} · expires ${item.expires_at || 'not set'}`"
                                />
                                <v-list-item v-if="!control.residency_verifications.length" title="Human-verified residency отсутствует" />
                            </v-list>
                        </template>
                    </v-col>
                </v-row>

                <template v-if="tooling && capabilities.view_ai_tooling">
                    <v-divider class="my-5" />
                    <div class="d-flex align-center justify-space-between mb-2">
                        <strong>Code-owned Tool / Workflow Registry</strong>
                        <span class="text-caption text-medium-emphasis">Read-only diagnostics · no browser execution</span>
                    </div>
                    <v-alert type="info" density="compact" variant="tonal" class="mb-3">
                        Browser не передаёт tool code, arguments, workflow, prompt, provider, model, URL или contour. Live business eligibility отсутствует.
                    </v-alert>
                    <v-row>
                        <v-col cols="12" lg="7">
                            <div class="text-caption font-weight-medium mb-1">Tools</div>
                            <v-list density="compact" class="border rounded-lg">
                                <v-list-item
                                    v-for="tool in tooling.tools"
                                    :key="`${tool.code}:${tool.version}`"
                                    :title="`${tool.code}:${tool.version}`"
                                    :subtitle="`${tool.allowed_contours.join(', ')} · ${tool.allowed_lanes.join(', ')} · ceiling ${tool.maximum_classification}`"
                                >
                                    <template #append>
                                        <v-chip size="x-small" :color="tool.enabled ? 'success' : 'grey'">{{ tool.block_reason || 'eligible' }}</v-chip>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-col>
                        <v-col cols="12" lg="5">
                            <div class="text-caption font-weight-medium mb-1">Server-owned workflows</div>
                            <v-list density="compact" class="border rounded-lg">
                                <v-list-item
                                    v-for="workflow in tooling.workflows"
                                    :key="`${workflow.code}:${workflow.version}`"
                                    :title="`${workflow.code}:${workflow.version}`"
                                    :subtitle="`${workflow.required_contour} · ${workflow.ordered_steps.map(step => step.tool_code).join(' → ')}`"
                                >
                                    <template #append><v-chip size="x-small" color="grey">{{ workflow.block_reason }}</v-chip></template>
                                </v-list-item>
                            </v-list>
                        </v-col>
                    </v-row>

                    <div class="text-caption font-weight-medium mt-4 mb-1">Последние safe execution records</div>
                    <v-list density="compact" class="border rounded-lg">
                        <v-list-item
                            v-for="execution in tooling.executions.slice(0, 12)"
                            :key="`${execution.run_id}:${execution.tool_code}:${execution.created_at}`"
                            :title="`${execution.tool_code}:${execution.tool_version} · ${execution.status}`"
                            :subtitle="`${execution.contour} · ${execution.row_count} rows · ${execution.byte_count} bytes · ${execution.query_count} queries · ${execution.duration_ms ?? 0} ms · ${execution.block_reason || 'allow'}`"
                        />
                        <v-list-item v-if="!tooling.executions.length" title="Safe tool executions пока отсутствуют" />
                    </v-list>
                </template>

                <v-divider class="my-5" />
                <div class="d-flex align-center justify-space-between mb-2">
                    <strong>Runs этого Unit</strong>
                    <span class="text-caption text-medium-emphasis">Только разрешённые lanes</span>
                </div>
                <v-list density="compact" class="border rounded-lg">
                    <v-list-item v-for="run in runs" :key="run.id" :title="`${run.definition.code} · ${run.status}`">
                        <v-list-item-subtitle>
                            {{ run.lane }}/{{ run.role_code }} · {{ run.selected_contour || run.requested_contour }} · {{ run.reason_code || 'policy pass' }}
                            · {{ run.budgets.used_tokens }}/{{ run.budgets.max_tokens }} tokens · {{ run.budgets.used_cost_rub }}/{{ run.budgets.max_cost_rub }} RUB
                        </v-list-item-subtitle>
                        <template #append>
                            <div class="d-flex ga-2 align-center">
                                <v-chip size="x-small" :color="contourColor(run.selected_contour || run.requested_contour)">{{ run.selected_contour || run.requested_contour }}</v-chip>
                                <v-btn
                                    v-if="capabilities.cancel_ai_run && nonTerminalStatuses.has(run.status)"
                                    size="x-small"
                                    variant="text"
                                    :disabled="saving"
                                    @click="cancelRun(run)"
                                >Cancel</v-btn>
                            </div>
                        </template>
                    </v-list-item>
                    <v-list-item v-if="!runs.length" title="AI runs пока отсутствуют" />
                </v-list>
            </template>
        </v-card-text>

        <v-dialog v-model="runDialog" max-width="640">
            <v-card title="Synthetic fake-only run">
                <v-card-text>
                    <v-select
                        v-model="runForm.definition"
                        :items="activeDefinitions"
                        item-title="display_name"
                        return-object
                        label="Code-owned definition"
                    />
                    <v-select
                        v-model="runForm.contextId"
                        :items="contexts"
                        item-title="role_label"
                        item-value="id"
                        label="Unit business context"
                    />
                    <v-alert type="warning" density="compact" variant="tonal">
                        Контур выбирается сервером из task profile; изменить его в UI нельзя.
                    </v-alert>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="runDialog = false">Отмена</v-btn>
                    <v-btn color="primary" :loading="saving" :disabled="!runForm.definition || !runForm.contextId" @click="createRun">Создать</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>
