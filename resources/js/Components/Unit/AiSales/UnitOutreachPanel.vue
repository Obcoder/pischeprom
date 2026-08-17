<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'

const props = defineProps({ unitId: { type: Number, required: true } })
const data = ref({ contexts: [], drafts: [], permissions: [], suppressions: [], product_matches: [], good_matches: [], contacts: [], feature_state: {} })
const loading = ref(false)
const busy = ref('')
const error = ref('')
const notice = ref('')
const selectedDraftId = ref(null)
const draftForm = ref({ unit_business_context_id: null, unit_contact_context_link_id: null, unit_product_match_id: null, unit_good_match_id: null, purpose: 'advertising_outreach' })
const permissionForm = ref({ unit_business_context_id: null, unit_contact_context_link_id: null, product_id: null, evidence_type: 'other_reviewed', reference: '', content_hash: '', valid_until: '' })
const suppressionForm = ref({ unit_business_context_id: null, scope: 'endpoint', unit_contact_context_link_id: null, reason: 'manual_block', source: 'unit_outreach_ui', evidence_reference: '', evidence_hash: '' })
const revisionForm = ref(null)

const selectedDraft = computed(() => data.value.drafts.find(item => Number(item.id) === Number(selectedDraftId.value)) || null)
const contextProducts = computed(() => data.value.product_matches.filter(item => Number(item.context_id) === Number(draftForm.value.unit_business_context_id)))
const contextContacts = computed(() => data.value.contacts.filter(item => Number(item.context_id) === Number(draftForm.value.unit_business_context_id)))
const contextGoods = computed(() => data.value.good_matches.filter(item => Number(item.product_match_id) === Number(draftForm.value.unit_product_match_id)))
const permissionContacts = computed(() => data.value.contacts.filter(item => Number(item.context_id) === Number(permissionForm.value.unit_business_context_id)))
const permissionProducts = computed(() => data.value.product_matches.filter(item => Number(item.context_id) === Number(permissionForm.value.unit_business_context_id)))
const suppressionContacts = computed(() => data.value.contacts.filter(item => Number(item.context_id) === Number(suppressionForm.value.unit_business_context_id)))

function contextTitle(item) {
    return `${item.lane} · ${item.role_code} · ${item.stage}`
}

async function load() {
    loading.value = true
    error.value = ''
    try {
        const response = await axios.get(`/api/ai-sales/units/${props.unitId}/outreach`)
        data.value = response.data.data
        if (!selectedDraftId.value && data.value.drafts.length) selectedDraftId.value = data.value.drafts[0].id
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Stage 12 outreach недоступен.'
    } finally {
        loading.value = false
    }
}

async function action(key, callback, message) {
    busy.value = key
    error.value = ''
    notice.value = ''
    try {
        await callback()
        notice.value = message
        await load()
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Действие заблокировано политикой.'
    } finally {
        busy.value = ''
    }
}

async function createDraft() {
    await action('create-draft', async () => {
        const response = await axios.post(`/api/ai-sales/units/${props.unitId}/outreach/drafts`, draftForm.value)
        selectedDraftId.value = response.data.data.id
    }, 'Черновик создан без отправки.')
}

async function generateDraft() {
    if (!selectedDraft.value) return
    await action('generate', () => axios.post(`/api/ai-sales/units/${props.unitId}/outreach/drafts/${selectedDraft.value.id}/generate`), 'Детерминированная fake-ревизия создана.')
}

function beginRevision() {
    revisionForm.value = structuredClone(selectedDraft.value?.current_revision?.structured_content || {})
}

async function saveRevision() {
    await action('revision', () => axios.post(
        `/api/ai-sales/units/${props.unitId}/outreach/drafts/${selectedDraft.value.id}/revisions`,
        { structured_content: revisionForm.value },
    ), 'Новая append-only ревизия сохранена.')
    revisionForm.value = null
}

async function review(type, decision = 'approved') {
    const revision = selectedDraft.value?.current_revision
    if (!revision) return
    await action(`review-${type}`, () => axios.post(
        `/api/ai-sales/units/${props.unitId}/outreach/drafts/${selectedDraft.value.id}/reviews`,
        { revision_id: revision.id, review_type: type, decision, reason_code: `human_${decision}` },
    ), `Review ${type}: ${decision}. Это не разрешение на отправку.`)
}

async function previewEligibility() {
    await action('eligibility', () => axios.post(
        `/api/ai-sales/units/${props.unitId}/outreach/drafts/${selectedDraft.value.id}/eligibility-preview`,
    ), 'Eligibility пересчитана; dispatch остаётся выключен.')
}

async function createPermission() {
    const productMatch = data.value.product_matches.find(item => Number(item.id) === Number(permissionForm.value.product_id))
    await action('permission', () => axios.post(`/api/ai-sales/units/${props.unitId}/outreach/permissions`, {
        unit_business_context_id: permissionForm.value.unit_business_context_id,
        unit_contact_context_link_id: permissionForm.value.unit_contact_context_link_id,
        purpose: 'advertising_outreach',
        product_id: productMatch?.product_id,
        valid_from: new Date().toISOString(),
        valid_until: permissionForm.value.valid_until || null,
        evidence: [{
            type: permissionForm.value.evidence_type, reference: permissionForm.value.reference,
            content_hash: permissionForm.value.content_hash, captured_at: new Date().toISOString(),
            source_controller: 'unit_outreach_ui',
        }],
    }), 'Permission evidence записано со статусом pending_review.')
}

async function reviewPermission(permission, decision) {
    await action(`permission-${permission.id}`, () => axios.post(
        `/api/ai-sales/units/${props.unitId}/outreach/permissions/${permission.id}/review`,
        { decision, reason_code: `human_${decision}` },
    ), `Permission: ${decision}. Dispatch остаётся выключен.`)
}

async function revokePermission(permission) {
    await action(`permission-${permission.id}`, () => axios.post(
        `/api/ai-sales/units/${props.unitId}/outreach/permissions/${permission.id}/revoke`,
        { reason_code: 'human_revocation' },
    ), 'Permission отозвано.')
}

async function createSuppression() {
    await action('suppression', () => axios.post(`/api/ai-sales/units/${props.unitId}/outreach/suppressions`, suppressionForm.value), 'Suppression записан и имеет приоритет над permission.')
}

onMounted(load)
</script>

<template>
    <v-card variant="outlined" :loading="loading">
        <v-card-title class="d-flex align-center justify-space-between">
            <span>Outreach drafts · Stage 12</span>
            <v-chip color="error" variant="tonal" size="small">dispatch OFF</v-chip>
        </v-card-title>
        <v-card-text>
            <v-alert type="warning" variant="tonal" density="compact" class="mb-3">
                Только черновик и human review. Публичный email не означает consent; approval не отправляет письмо.
            </v-alert>
            <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
            <v-alert v-if="notice" type="success" variant="tonal" density="compact" class="mb-3">{{ notice }}</v-alert>

            <v-expansion-panels multiple>
                <v-expansion-panel title="Новый product-first черновик">
                    <v-expansion-panel-text>
                        <v-row dense>
                            <v-col cols="12" md="4"><v-select v-model="draftForm.unit_business_context_id" :items="data.contexts" item-value="id" :item-title="contextTitle" label="Sales context" /></v-col>
                            <v-col cols="12" md="4"><v-select v-model="draftForm.unit_product_match_id" :items="contextProducts" item-value="id" item-title="product_name" label="Approved Product match" /></v-col>
                            <v-col cols="12" md="4"><v-select v-model="draftForm.unit_good_match_id" :items="contextGoods" item-value="id" item-title="good_name" label="Good offer fit (optional)" clearable /></v-col>
                            <v-col cols="12" md="6"><v-select v-model="draftForm.unit_contact_context_link_id" :items="contextContacts" item-value="id" item-title="display_label" label="Existing Email link (optional until review)" clearable /></v-col>
                            <v-col cols="12" md="6" class="d-flex align-center"><v-btn color="primary" :loading="busy === 'create-draft'" @click="createDraft">Создать черновик</v-btn></v-col>
                        </v-row>
                    </v-expansion-panel-text>
                </v-expansion-panel>

                <v-expansion-panel title="Черновики, ревизии и независимые reviews">
                    <v-expansion-panel-text>
                        <v-select v-model="selectedDraftId" :items="data.drafts" item-value="id" :item-title="item => `#${item.id} · ${item.product.name} · ${item.status}`" label="Черновик" />
                        <template v-if="selectedDraft">
                            <div class="d-flex flex-wrap ga-2 mb-3">
                                <v-chip>{{ selectedDraft.status }}</v-chip><v-chip>{{ selectedDraft.purpose }}</v-chip>
                                <v-chip :color="selectedDraft.current_revision?.dlp_status === 'passed' ? 'success' : 'error'">DLP: {{ selectedDraft.current_revision?.dlp_status || 'нет ревизии' }}</v-chip>
                            </div>
                            <v-alert type="info" variant="tonal" density="compact" class="mb-3">
                                eligible={{ selectedDraft.eligibility.eligible }} · content_ready={{ selectedDraft.eligibility.content_ready }} · {{ selectedDraft.eligibility.block_reasons.join(', ') }}
                            </v-alert>
                            <div class="d-flex flex-wrap ga-2 mb-3">
                                <v-btn size="small" :loading="busy === 'generate'" @click="generateDraft">Fake structured generate</v-btn>
                                <v-btn size="small" :disabled="!selectedDraft.current_revision" @click="beginRevision">Новая ручная ревизия</v-btn>
                                <v-btn size="small" :disabled="!selectedDraft.current_revision" :loading="busy === 'eligibility'" @click="previewEligibility">Eligibility preview</v-btn>
                            </div>
                            <template v-if="selectedDraft.current_revision">
                                <h4>{{ selectedDraft.current_revision.subject }}</h4>
                                <pre class="outreach-preview">{{ selectedDraft.current_revision.plaintext }}</pre>
                                <v-alert v-if="selectedDraft.current_revision.dlp_findings.length" type="error" variant="tonal">{{ selectedDraft.current_revision.dlp_findings.join(', ') }}</v-alert>
                                <div class="d-flex flex-wrap ga-2 mt-3">
                                    <v-btn v-for="type in ['content', 'claims', 'permission', 'recipient']" :key="type" size="x-small" variant="tonal" @click="review(type)">Approve {{ type }}</v-btn>
                                </div>
                            </template>
                            <v-card v-if="revisionForm" variant="tonal" class="mt-3"><v-card-text>
                                <v-text-field v-model="revisionForm.subject" label="Subject" />
                                <v-textarea v-model="revisionForm.introduction" label="Introduction" rows="2" />
                                <v-textarea v-model="revisionForm.value_proposition" label="Value proposition" rows="3" />
                                <v-textarea v-model="revisionForm.call_to_action" label="Call to action" rows="2" />
                                <v-btn color="primary" :loading="busy === 'revision'" @click="saveRevision">Сохранить append-only ревизию</v-btn>
                            </v-card-text></v-card>
                        </template>
                    </v-expansion-panel-text>
                </v-expansion-panel>

                <v-expansion-panel v-if="data.capabilities?.can_view_permissions" title="Communication permission ledger">
                    <v-expansion-panel-text>
                        <v-row dense>
                            <v-col cols="12" md="4"><v-select v-model="permissionForm.unit_business_context_id" :items="data.contexts" item-value="id" :item-title="contextTitle" label="Sales context" /></v-col>
                            <v-col cols="12" md="4"><v-select v-model="permissionForm.unit_contact_context_link_id" :items="permissionContacts" item-value="id" item-title="display_label" label="Recipient Email link" /></v-col>
                            <v-col cols="12" md="4"><v-select v-model="permissionForm.product_id" :items="permissionProducts" item-value="id" item-title="product_name" label="Product scope" /></v-col>
                            <v-col cols="12" md="6"><v-select v-model="permissionForm.evidence_type" :items="['signed_documented_consent','web_form_consent','written_response','contract_relationship_evidence','import_manual_evidence','other_reviewed']" label="Evidence type" /></v-col>
                            <v-col cols="12" md="6"><v-text-field v-model="permissionForm.reference" label="Safe evidence reference" /></v-col>
                            <v-col cols="12" md="6"><v-text-field v-model="permissionForm.content_hash" label="Evidence SHA-256" /></v-col>
                            <v-col cols="12" md="4"><v-text-field v-model="permissionForm.valid_until" type="datetime-local" label="Valid until" /></v-col>
                            <v-col cols="12" md="8" class="d-flex align-center"><v-btn :loading="busy === 'permission'" @click="createPermission">Записать pending evidence</v-btn></v-col>
                        </v-row>
                        <v-table density="compact" class="mt-3"><thead><tr><th>ID</th><th>Purpose/product</th><th>Status</th><th>Evidence</th><th>Actions</th></tr></thead><tbody>
                            <tr v-for="item in data.permissions" :key="item.id"><td>{{ item.id }}</td><td>{{ item.purpose }} / {{ item.product_id }}</td><td>{{ item.status }}</td><td>{{ item.evidence_count }}</td><td class="d-flex ga-1">
                                <v-btn v-if="item.status === 'pending_review'" size="x-small" @click="reviewPermission(item, 'granted')">grant</v-btn>
                                <v-btn v-if="item.status === 'pending_review'" size="x-small" @click="reviewPermission(item, 'rejected')">reject</v-btn>
                                <v-btn v-if="item.status === 'granted'" size="x-small" color="error" @click="revokePermission(item)">revoke</v-btn>
                            </td></tr>
                        </tbody></v-table>
                    </v-expansion-panel-text>
                </v-expansion-panel>

                <v-expansion-panel v-if="data.capabilities?.can_manage_suppressions" title="Suppression / DNC (всегда сильнее permission)">
                    <v-expansion-panel-text>
                        <v-row dense>
                            <v-col cols="12" md="4"><v-select v-model="suppressionForm.unit_business_context_id" :items="data.contexts" item-value="id" :item-title="contextTitle" label="Sales context" /></v-col>
                            <v-col cols="12" md="4"><v-select v-model="suppressionForm.unit_contact_context_link_id" :items="suppressionContacts" item-value="id" item-title="display_label" label="Email link" /></v-col>
                            <v-col cols="12" md="4"><v-select v-model="suppressionForm.reason" :items="['do_not_contact','unsubscribed','complaint','hard_bounce','invalid_address','legal_hold','manual_block','suppressed']" label="Reason" /></v-col>
                            <v-col cols="12" md="6"><v-text-field v-model="suppressionForm.evidence_reference" label="Safe evidence reference" /></v-col>
                            <v-col cols="12" md="6"><v-text-field v-model="suppressionForm.evidence_hash" label="Evidence SHA-256" /></v-col>
                            <v-col cols="12"><v-btn color="error" variant="tonal" :loading="busy === 'suppression'" @click="createSuppression">Добавить suppression</v-btn></v-col>
                        </v-row>
                        <v-chip v-for="item in data.suppressions" :key="item.id" class="mr-2 mt-2" color="error" variant="tonal">#{{ item.id }} {{ item.reason }} {{ item.cleared_at ? '(cleared)' : '' }}</v-chip>
                    </v-expansion-panel-text>
                </v-expansion-panel>
            </v-expansion-panels>
        </v-card-text>
    </v-card>
</template>

<style scoped>
.outreach-preview { white-space: pre-wrap; max-height: 280px; overflow: auto; padding: 12px; background: rgba(20, 30, 45, .06); border-radius: 8px; }
</style>
