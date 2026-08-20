<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'

const props = defineProps({ unitId: { type: Number, required: true } })
const contexts = ref([])
const selectedContext = ref(null)
const dossier = ref(null)
const error = ref('')
const tab = ref('overview')
const scores = ref(null)
const scoreError = ref('')
const scoreBusy = ref('')
const overrideDialog = ref(false)
const overrideTarget = ref(null)
const overrideForm = ref({ effective_score: 0, reason_code: 'human_evidence_correction', safe_note: '', expires_at: null })

const currentPriority = computed(() => currentScore(scores.value?.prospect_priority || []))

function currentScore(items, subjectKey = null, subjectId = null) {
    return items.find(item => !item.superseded && (!subjectKey || Number(item[subjectKey]) === Number(subjectId))) || null
}

function productScore(matchId) {
    return currentScore(scores.value?.product_relevance || [], 'unit_product_match_id', matchId)
}

function goodScore(matchId) {
    return currentScore(scores.value?.good_fit || [], 'unit_good_match_id', matchId)
}

function scoreHistory(items, subjectKey, subjectId) {
    return (items || []).filter(item => Number(item[subjectKey]) === Number(subjectId))
}

async function loadContexts() {
    try {
        const { data } = await axios.get(`/api/ai-sales/units/${props.unitId}/dossier`)
        contexts.value = data.data.contexts || []
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Контексты недоступны.'
    }
}

async function loadDossier() {
    dossier.value = null
    scores.value = null
    error.value = ''
    if (!selectedContext.value) return
    try {
        const { data } = await axios.get(`/api/ai-sales/units/${props.unitId}/prospecting-dossier`, { params: { context_id: selectedContext.value } })
        dossier.value = data.data
        await loadScores()
    } catch (requestError) {
        error.value = requestError.response?.status === 404 ? 'Stage 08 dossier выключен.' : (requestError.response?.data?.message || 'Досье недоступно.')
    }
}

async function loadScores() {
    scoreError.value = ''
    try {
        const { data } = await axios.get(`/api/ai-sales/scoring/units/${props.unitId}/contexts/${selectedContext.value}`)
        scores.value = data.data
    } catch (requestError) {
        scoreError.value = requestError.response?.status === 404 ? 'Stage 10 scoring выключен.' : (requestError.response?.data?.message || 'Оценки недоступны.')
    }
}

async function recalculate(kind, id) {
    const paths = {
        product: `product-matches/${id}/recalculate`,
        good: `good-matches/${id}/recalculate`,
        priority: `contexts/${id}/priority/recalculate`,
    }
    scoreBusy.value = `${kind}-${id}`
    try {
        await axios.post(`/api/ai-sales/scoring/${paths[kind]}`)
        await loadScores()
    } catch (requestError) {
        scoreError.value = requestError.response?.data?.message || 'Пересчёт заблокирован.'
    } finally {
        scoreBusy.value = ''
    }
}

async function reviewScore(kind, snapshot) {
    scoreBusy.value = `review-${snapshot.id}`
    try {
        await axios.post(`/api/ai-sales/scoring/${kind}-snapshots/${snapshot.id}/review`, { status: 'reviewed' })
        await loadScores()
    } catch (requestError) {
        scoreError.value = requestError.response?.data?.message || 'Review заблокирован.'
    } finally {
        scoreBusy.value = ''
    }
}

function openOverride(kind, snapshot) {
    overrideTarget.value = { kind, snapshot }
    overrideForm.value = { effective_score: snapshot.effective_score, reason_code: 'human_evidence_correction', safe_note: '', expires_at: null }
    overrideDialog.value = true
}

async function submitOverride() {
    if (!overrideTarget.value) return
    const { kind, snapshot } = overrideTarget.value
    scoreBusy.value = `override-${snapshot.id}`
    try {
        await axios.post(`/api/ai-sales/scoring/${kind}-snapshots/${snapshot.id}/override`, overrideForm.value)
        overrideDialog.value = false
        await loadScores()
    } catch (requestError) {
        scoreError.value = requestError.response?.data?.message || 'Override заблокирован.'
    } finally {
        scoreBusy.value = ''
    }
}

onMounted(loadContexts)
</script>

<template>
    <v-card id="prospecting-dossier" variant="outlined">
        <v-card-title>Prospecting dossier Unit</v-card-title>
        <v-card-text>
            <v-alert type="info" variant="tonal" density="compact" class="mb-3">Контекст обязателен; sales и procurement никогда не объединяются по умолчанию.</v-alert>
            <v-select v-model="selectedContext" :items="contexts" item-value="id" :item-title="item => `${item.lane_label}: ${item.role_label}`" label="Выберите бизнес-контекст" clearable @update:model-value="loadDossier" />
            <v-alert v-if="dossier?.dual_role_warning" type="warning" variant="tonal">Unit одновременно участвует в sales и procurement. Показан только выбранный lane.</v-alert>
            <v-alert v-if="error" type="info" variant="tonal">{{ error }}</v-alert>
            <v-alert v-if="scoreError" type="info" variant="tonal" class="mt-2">{{ scoreError }}</v-alert>
        </v-card-text>
        <template v-if="dossier">
            <v-tabs v-model="tab" show-arrows>
                <v-tab value="overview">Обзор</v-tab><v-tab value="contexts">Контексты</v-tab><v-tab value="sources">Источники</v-tab>
                <v-tab value="observations">Наблюдения</v-tab><v-tab value="contacts">Контакты</v-tab><v-tab value="scores">Оценки</v-tab><v-tab value="products">Продукты</v-tab><v-tab value="goods">Goods-предложения</v-tab>
                <v-tab value="communications">Переписка</v-tab><v-tab value="entities">Entities и сделки</v-tab><v-tab value="runs">AI-запуски</v-tab><v-tab value="timeline">История</v-tab>
            </v-tabs>
            <v-tabs-window v-model="tab">
                <v-tabs-window-item value="overview"><v-card-text>{{ dossier.unit.name }} · {{ dossier.context.lane }} · {{ dossier.context.stage }}</v-card-text></v-tabs-window-item>
                <v-tabs-window-item value="contexts"><v-card-text>Контекст #{{ dossier.context.id }} · {{ dossier.context.role_code }}</v-card-text></v-tabs-window-item>
                <v-tabs-window-item value="sources"><v-list><v-list-item v-for="source in dossier.sources" :key="source.id" :title="source.label || source.type" :subtitle="source.reference" /></v-list></v-tabs-window-item>
                <v-tabs-window-item value="observations"><v-list><v-list-item v-for="observation in dossier.observations" :key="observation.id" :title="observation.key" :subtitle="observation.summary" /></v-list></v-tabs-window-item>
                <v-tabs-window-item value="contacts"><v-list><v-list-item v-for="link in dossier.contact_links" :key="link.id" :title="link.channel_type" :subtitle="`${link.contact_role} · ${link.verification_status} · ${link.communication_state}`" /></v-list></v-tabs-window-item>
                <v-tabs-window-item value="scores">
                    <v-card-text v-if="currentPriority">
                        <div class="text-h6">Prospect priority: {{ currentPriority.effective_score }}/100</div>
                        <div>Computed {{ currentPriority.computed_score }} · effective {{ currentPriority.effective_score }} · confidence {{ currentPriority.confidence }}</div>
                        <div>{{ currentPriority.band }} · {{ currentPriority.eligibility }} · {{ currentPriority.review_status }}</div>
                        <div>Definition {{ currentPriority.definition.code }} / {{ currentPriority.definition.version }} · {{ currentPriority.created_at }}</div>
                        <v-alert v-if="currentPriority.stale" type="warning" density="compact" variant="tonal">Stale: {{ currentPriority.stale_reason_code }}</v-alert>
                        <div class="mt-2">Next best action: {{ currentPriority.next_best_action }}</div>
                        <div v-if="currentPriority.manual_override" class="mt-2">Manual override: {{ currentPriority.manual_override.reason_code }} · base #{{ currentPriority.manual_override.base_snapshot_id }} · {{ currentPriority.manual_override.safe_note }}</div>
                        <v-btn v-if="scores.capabilities.recalculate" size="small" class="mr-2 mt-2" :loading="scoreBusy === `priority-${dossier.context.id}`" @click="recalculate('priority', dossier.context.id)">Пересчитать</v-btn>
                        <v-btn v-if="scores.capabilities.review" size="small" class="mr-2 mt-2" @click="reviewScore('prospect-priority', currentPriority)">Проверено</v-btn>
                        <v-btn v-if="scores.capabilities.override" size="small" class="mt-2" @click="openOverride('prospect-priority', currentPriority)">Override</v-btn>
                        <v-expansion-panels class="mt-3">
                            <v-expansion-panel title="Факторы и evidence"><v-expansion-panel-text><v-list density="compact"><v-list-item v-for="factor in currentPriority.factors" :key="factor.factor_code" :title="`${factor.factor_code}: ${factor.contribution}`" :subtitle="`${factor.status} · ${factor.safe_rationale}${factor.evidence ? ` · ${factor.evidence.reference}` : ''}`" /></v-list></v-expansion-panel-text></v-expansion-panel>
                            <v-expansion-panel title="История snapshots"><v-expansion-panel-text><v-list density="compact"><v-list-item v-for="snapshot in scores.prospect_priority" :key="snapshot.id" :title="`#${snapshot.id}: ${snapshot.computed_score} → ${snapshot.effective_score}`" :subtitle="`${snapshot.origin} · ${snapshot.review_status} · ${snapshot.created_at}`" /></v-list></v-expansion-panel-text></v-expansion-panel>
                        </v-expansion-panels>
                    </v-card-text>
                    <v-card-text v-else>Prospect priority ещё не вычислен. Scoring не означает разрешение на контакт.</v-card-text>
                </v-tabs-window-item>
                <v-tabs-window-item value="products">
                    <v-card-text class="font-weight-medium">Продукты и потенциальные потребности/предложения</v-card-text>
                    <v-list><v-list-item v-for="match in dossier.product_matches" :key="match.id" :title="match.product?.name" :subtitle="`${match.match_type} · ${match.status} · relevance ${productScore(match.id)?.effective_score ?? 'not evaluated'} · confidence ${productScore(match.id)?.confidence ?? 'unknown'}`"><template #append><v-btn v-if="scores?.capabilities.recalculate" size="x-small" class="mr-1" :loading="scoreBusy === `product-${match.id}`" @click="recalculate('product', match.id)">Пересчитать</v-btn><v-btn v-if="scores?.capabilities.review && productScore(match.id)" size="x-small" class="mr-1" @click="reviewScore('product-relevance', productScore(match.id))">Проверено</v-btn><v-btn v-if="scores?.capabilities.override && productScore(match.id)" size="x-small" @click="openOverride('product-relevance', productScore(match.id))">Override</v-btn></template><template #default>
                        <div v-if="productScore(match.id)" class="text-caption">Computed {{ productScore(match.id).computed_score }} · effective {{ productScore(match.id).effective_score }} · {{ productScore(match.id).band }} · {{ productScore(match.id).eligibility }} · {{ productScore(match.id).review_status }} · definition {{ productScore(match.id).definition.version }} · {{ productScore(match.id).next_best_action }}<span v-if="productScore(match.id).stale"> · stale: {{ productScore(match.id).stale_reason_code }}</span></div>
                        <div v-if="productScore(match.id)?.manual_override" class="text-caption">Override {{ productScore(match.id).manual_override.reason_code }} · base #{{ productScore(match.id).manual_override.base_snapshot_id }} · {{ productScore(match.id).manual_override.safe_note }}</div>
                        <v-expansion-panels v-if="productScore(match.id)" class="mt-1">
                            <v-expansion-panel title="Факторы Product relevance"><v-expansion-panel-text><v-list density="compact"><v-list-item v-for="factor in productScore(match.id).factors" :key="factor.factor_code" :title="`${factor.factor_code}: ${factor.contribution}`" :subtitle="`${factor.status} · ${factor.safe_rationale}${factor.evidence ? ` · ${factor.evidence.reference}` : ''}`" /></v-list></v-expansion-panel-text></v-expansion-panel>
                            <v-expansion-panel title="История snapshots"><v-expansion-panel-text><v-list density="compact"><v-list-item v-for="snapshot in scoreHistory(scores.product_relevance, 'unit_product_match_id', match.id)" :key="snapshot.id" :title="`#${snapshot.id}: ${snapshot.computed_score} → ${snapshot.effective_score}`" :subtitle="`${snapshot.origin} · ${snapshot.review_status} · ${snapshot.created_at}`" /></v-list></v-expansion-panel-text></v-expansion-panel>
                        </v-expansion-panels>
                    </template></v-list-item></v-list>
                </v-tabs-window-item>
                <v-tabs-window-item value="goods">
                    <v-card-text class="font-weight-medium">Конкретные Goods и коммерческие предложения</v-card-text>
                    <v-list><v-list-item v-for="fit in dossier.good_offer_fits" :key="fit.id" :title="fit.good?.name" :subtitle="`${fit.offer_direction} · ${fit.fit_status} · ${fit.compatibility_state} · Good fit ${goodScore(fit.id)?.effective_score ?? 'not evaluated'}`"><template #append><v-btn v-if="scores?.capabilities.recalculate" size="x-small" class="mr-1" :loading="scoreBusy === `good-${fit.id}`" @click="recalculate('good', fit.id)">Пересчитать</v-btn><v-btn v-if="scores?.capabilities.review && goodScore(fit.id)" size="x-small" class="mr-1" @click="reviewScore('good-fit', goodScore(fit.id))">Проверено</v-btn><v-btn v-if="scores?.capabilities.override && goodScore(fit.id)" size="x-small" @click="openOverride('good-fit', goodScore(fit.id))">Override</v-btn></template><template #default>
                        <div v-if="goodScore(fit.id)" class="text-caption">Computed {{ goodScore(fit.id).computed_score }} · effective {{ goodScore(fit.id).effective_score }} · confidence {{ goodScore(fit.id).confidence }} · {{ goodScore(fit.id).band }} · {{ goodScore(fit.id).eligibility }} · {{ goodScore(fit.id).review_status }} · definition {{ goodScore(fit.id).definition.version }} · {{ goodScore(fit.id).next_best_action }}<span v-if="goodScore(fit.id).stale"> · stale: {{ goodScore(fit.id).stale_reason_code }}</span></div>
                        <div v-if="goodScore(fit.id)?.manual_override" class="text-caption">Override {{ goodScore(fit.id).manual_override.reason_code }} · base #{{ goodScore(fit.id).manual_override.base_snapshot_id }} · {{ goodScore(fit.id).manual_override.safe_note }}</div>
                        <v-expansion-panels v-if="goodScore(fit.id)" class="mt-1">
                            <v-expansion-panel title="Known/unknown Good-fit factors"><v-expansion-panel-text><v-list density="compact"><v-list-item v-for="factor in goodScore(fit.id).factors" :key="factor.factor_code" :title="`${factor.factor_code}: ${factor.contribution}`" :subtitle="`${factor.status} · ${factor.safe_rationale}`" /></v-list></v-expansion-panel-text></v-expansion-panel>
                            <v-expansion-panel title="История snapshots"><v-expansion-panel-text><v-list density="compact"><v-list-item v-for="snapshot in scoreHistory(scores.good_fit, 'unit_good_match_id', fit.id)" :key="snapshot.id" :title="`#${snapshot.id}: ${snapshot.computed_score} → ${snapshot.effective_score}`" :subtitle="`${snapshot.origin} · ${snapshot.review_status} · ${snapshot.created_at}`" /></v-list></v-expansion-panel-text></v-expansion-panel>
                        </v-expansion-panels>
                    </template></v-list-item></v-list>
                    <v-alert v-if="dossier.legacy_good_match_diagnostics?.length" type="warning" density="compact" variant="tonal">Legacy Good-first rows доступны только как внутренние diagnostics и требуют reconciliation.</v-alert>
                </v-tabs-window-item>
                <v-tabs-window-item value="communications"><v-card-text>Сообщений: {{ dossier.communications.message_count }} · вложений: {{ dossier.communications.attachment_count }}. Raw correspondence не отображается и не экспортируется.</v-card-text></v-tabs-window-item>
                <v-tabs-window-item value="entities"><v-card-text>Транзакций: {{ dossier.transaction_count }}</v-card-text><v-list><v-list-item v-for="entity in dossier.linked_entities" :key="entity.id" :title="entity.name" /></v-list></v-tabs-window-item>
                <v-tabs-window-item value="runs">
                    <v-list><v-list-item v-for="run in dossier.ai_runs" :key="run.id" :title="run.definition_code" :subtitle="`${run.status} · ${run.created_at}`" /></v-list>
                    <v-list><v-list-item v-for="call in dossier.tool_calls" :key="call.id" :title="call.tool_code" :subtitle="`${call.status} · rows ${call.row_count || 0} · queries ${call.query_count || 0}`" /></v-list>
                    <v-card-text v-if="!dossier.ai_runs.length && !dossier.tool_calls.length">Контекстных AI-запусков и server-owned tool calls нет или они скрыты permissions.</v-card-text>
                </v-tabs-window-item>
                <v-tabs-window-item value="timeline"><v-list><v-list-item v-for="item in dossier.timeline.data" :key="`${item.reference.type}-${item.reference.id}`" :title="item.summary" :subtitle="`${item.type} · ${item.occurred_at}`" /></v-list></v-tabs-window-item>
            </v-tabs-window>
        </template>
        <v-dialog v-model="overrideDialog" max-width="560">
            <v-card><v-card-title>Новый append-only override</v-card-title><v-card-text>
                <v-alert type="warning" density="compact" variant="tonal" class="mb-3">Override не меняет computed score и не снимает DNC/suppression/policy block.</v-alert>
                <v-text-field v-model.number="overrideForm.effective_score" type="number" :min="0" :max="100" label="Effective score" />
                <v-select v-model="overrideForm.reason_code" :items="['human_evidence_correction', 'data_quality_correction', 'temporary_priority_review', 'review_disagreement']" label="Reason code" />
                <v-textarea v-model="overrideForm.safe_note" label="Безопасное обоснование" :counter="500" />
                <v-text-field v-model="overrideForm.expires_at" type="datetime-local" label="Истекает (необязательно)" />
            </v-card-text><v-card-actions><v-spacer /><v-btn @click="overrideDialog = false">Отмена</v-btn><v-btn color="primary" :loading="scoreBusy.startsWith('override-')" @click="submitOverride">Создать snapshot</v-btn></v-card-actions></v-card>
        </v-dialog>
    </v-card>
</template>
