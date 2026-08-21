<script setup>
import axios from 'axios'
import { computed, ref } from 'vue'

const props = defineProps({
    item: { type: Object, required: true },
    review: { type: Object, required: true },
    campaignName: { type: String, default: 'Campaign' },
})
const emit = defineEmits(['updated'])

const actionLoading = ref('')
const error = ref('')
const domains = computed(() => props.review?.domains || [])

const actionLabels = {
    fetch: 'Исследовать публичный сайт',
    research: 'Обработать сохранённую страницу',
    ingest_candidate: 'Создать Candidate для проверки',
}

function statusColor(status) {
    return ({ completed: 'success', blocked: 'warning', failed: 'error', not_requested: 'grey' })[status] || 'grey'
}

async function perform(domain) {
    const action = domain?.next_action
    const resultId = domain?.source?.result_id
    if (!resultId || !actionLabels[action]) return
    const prompts = {
        fetch: 'Выполнить один bounded public fetch для выбранного server-owned результата?',
        research: 'Обработать уже сохранённую публичную страницу безопасным research workflow?',
        ingest_candidate: 'Создать transient Candidate из проверенного публичного evidence? Unit и Entity созданы не будут.',
    }
    if (!window.confirm(prompts[action])) return

    actionLoading.value = `${action}:${resultId}`
    error.value = ''
    try {
        const base = `/api/ai-sales/prospecting/search-results/${encodeURIComponent(resultId)}`
        const endpoint = {
            fetch: `${base}/fetch`,
            research: `${base}/research`,
            ingest_candidate: `${base}/ingest-candidate`,
        }[action]
        await axios.post(endpoint, {})
        emit('updated')
    } catch (requestError) {
        const validation = requestError?.response?.data?.errors
        error.value = Object.values(validation || {}).flat()[0]
            || requestError?.response?.data?.message
            || 'Действие безопасно заблокировано policy.'
    } finally {
        actionLoading.value = ''
    }
}
</script>

<template>
    <v-card variant="outlined" color="deep-purple" data-testid="candidate-ingestion-review-panel">
        <v-card-title class="d-flex align-center ga-2 flex-wrap">
            <v-icon icon="mdi-clipboard-search-outline" />
            <span>Ручная проверка результатов · {{ campaignName }}</span>
            <v-chip size="small" color="warning" variant="tonal">human action required</v-chip>
        </v-card-title>
        <v-card-text>
            <v-alert type="info" variant="tonal" density="compact" class="mb-3">
                Поиск повторно не запускается. Выберите сохранённый server-owned результат: сначала bounded public research,
                затем ручное создание transient Candidate. Unit, Entity и email недоступны.
            </v-alert>
            <v-alert v-if="error" type="warning" variant="tonal" class="mb-3">{{ error }}</v-alert>

            <div class="d-flex ga-2 flex-wrap mb-3">
                <v-chip size="small" variant="outlined">Результаты: {{ review.counts?.results || 0 }}</v-chip>
                <v-chip size="small" variant="outlined">Домены: {{ review.counts?.unique_domains || 0 }}</v-chip>
                <v-chip size="small" color="success" variant="outlined">Buyer-like: {{ review.counts?.buyer_like_domains || 0 }}</v-chip>
                <v-chip size="small" color="success" variant="outlined">Готовы к Candidate: {{ review.counts?.candidate_ready_domains || 0 }}</v-chip>
                <v-chip size="small" variant="outlined">
                    Research attempts: {{ review.budget?.pages_used || 0 }}/{{ review.budget?.pages_limit || 0 }}
                </v-chip>
            </div>

            <v-expansion-panels multiple variant="accordion">
                <v-expansion-panel v-for="domain in domains" :key="domain.domain">
                    <v-expansion-panel-title>
                        <div class="d-flex align-center ga-2 flex-wrap w-100 pr-3">
                            <strong>{{ domain.identity?.working_name || domain.domain }}</strong>
                            <v-chip size="x-small" :color="domain.buyer_classification?.candidate_eligible ? 'success' : 'grey'">
                                {{ domain.buyer_classification?.role }} · {{ domain.buyer_classification?.confidence }}%
                            </v-chip>
                            <v-chip size="x-small" variant="outlined">{{ domain.reason_code }}</v-chip>
                            <v-spacer />
                            <v-btn
                                v-if="actionLabels[domain.next_action]"
                                size="small"
                                color="primary"
                                variant="tonal"
                                :loading="actionLoading === `${domain.next_action}:${domain.source?.result_id}`"
                                @click.stop="perform(domain)"
                            >{{ actionLabels[domain.next_action] }}</v-btn>
                            <v-btn
                                v-else-if="domain.next_action === 'open_candidate' && domain.candidate_id"
                                size="small"
                                variant="text"
                                :href="`/Ameise/ai-sales?tab=review&candidate=${encodeURIComponent(domain.candidate_id)}#candidate-review`"
                            >Открыть Candidate</v-btn>
                        </div>
                    </v-expansion-panel-title>
                    <v-expansion-panel-text>
                        <v-row dense>
                            <v-col cols="12" md="4">
                                <div class="text-caption">Identity</div>
                                <strong>{{ domain.identity?.status }}</strong>
                                <div>{{ domain.identity?.geography || 'География не установлена' }}</div>
                                <div class="text-medium-emphasis">Confidence: {{ domain.identity?.confidence }}%</div>
                            </v-col>
                            <v-col cols="12" md="4">
                                <div class="text-caption">Public fetch</div>
                                <v-chip size="x-small" :color="statusColor(domain.source?.fetch_status)">
                                    {{ domain.source?.fetch_status || 'not_requested' }}
                                </v-chip>
                                <div class="text-caption mt-1">{{ domain.source?.fetch_error_code }}</div>
                            </v-col>
                            <v-col cols="12" md="4">
                                <div class="text-caption">Research</div>
                                <v-chip size="x-small" :color="statusColor(domain.source?.research_status)">
                                    {{ domain.source?.research_status || 'not_requested' }}
                                </v-chip>
                                <div class="text-caption mt-1">{{ domain.source?.research_error_code }}</div>
                            </v-col>
                        </v-row>
                        <div v-if="domain.identity?.activity_summary" class="mt-2">
                            <div class="text-caption">Публичная деятельность</div>
                            {{ domain.identity.activity_summary }}
                        </div>
                        <div v-if="domain.source?.research_summary" class="mt-2">
                            <div class="text-caption">Safe research summary</div>
                            {{ domain.source.research_summary }}
                        </div>
                        <div class="mt-2">
                            <div class="text-caption">Источник</div>
                            <span>{{ domain.source?.title }}</span>
                            <v-btn
                                v-if="domain.source?.url"
                                size="x-small"
                                variant="text"
                                :href="domain.source.url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >Открыть источник</v-btn>
                        </div>
                    </v-expansion-panel-text>
                </v-expansion-panel>
            </v-expansion-panels>

            <v-alert v-if="!domains.length" type="info" variant="tonal" class="mt-3">
                Безопасные результаты для ручной проверки отсутствуют.
            </v-alert>
        </v-card-text>
    </v-card>
</template>
