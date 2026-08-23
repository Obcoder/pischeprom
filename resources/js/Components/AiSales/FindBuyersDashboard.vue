<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'

const visible = ref(false)
const loading = ref(false)
const error = ref('')
const dashboard = ref({ sections: [], jobs: [], runtime: {} })
const expanded = ref([])

const sectionLabels = {
    my_jobs: 'Мои задания',
    review_required: 'На проверке',
    in_progress: 'Выполняются',
    candidates: 'Кандидаты',
    high_priority: 'Высокий приоритет',
    blocked: 'Заблокировано',
    completed: 'Завершено',
}

const jobs = computed(() => dashboard.value.jobs || [])

async function load() {
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get('/api/ai-sales/find-buyers/dashboard?limit=25')
        dashboard.value = data.data
        visible.value = true
    } catch (requestError) {
        if ([401, 403, 404].includes(requestError?.response?.status)) {
            visible.value = false
        } else {
            visible.value = true
            error.value = requestError?.response?.data?.message || 'Не удалось загрузить Find Buyers dashboard.'
        }
    } finally {
        loading.value = false
    }
}

async function cancel(job) {
    try {
        await axios.post(`/api/ai-sales/find-buyers/jobs/${job.job.id}/cancel`, {})
        await load()
    } catch (requestError) {
        error.value = requestError?.response?.data?.message || 'Отмена заблокирована policy.'
    }
}

function scoreColor(band) {
    return ({ low: 'grey', medium: 'blue-grey', promising: 'teal', high: 'orange', very_high: 'deep-orange' })[band] || 'grey'
}

onMounted(load)
</script>

<template>
    <v-card v-if="visible" variant="tonal" color="deep-purple" class="ma-3">
        <v-card-title class="d-flex align-center ga-2 flex-wrap">
            <v-icon>mdi-robot-outline</v-icon>
            <span>Product-first «Найти покупателей»</span>
            <v-chip size="small" color="warning" variant="flat">code-only · live off</v-chip>
            <v-spacer />
            <v-btn size="small" variant="text" :loading="loading" @click="load">Обновить</v-btn>
        </v-card-title>
        <v-card-text>
            <v-alert v-if="error" type="warning" variant="tonal" class="mb-3">{{ error }}</v-alert>
            <div class="d-flex ga-2 flex-wrap mb-4">
                <v-chip v-for="section in dashboard.sections" :key="section.code" size="small" variant="outlined">
                    {{ sectionLabels[section.code] || section.code }}: {{ section.count }}
                </v-chip>
            </div>
            <v-expansion-panels v-model="expanded" multiple variant="accordion">
                <v-expansion-panel v-for="job in jobs" :key="job.job.id" :value="job.job.id">
                    <v-expansion-panel-title>
                        <div class="d-flex align-center ga-2 flex-wrap w-100 pr-3">
                            <strong>{{ job.job.products.find(item => item.role === 'primary')?.name || job.job.safe_objective }}</strong>
                            <v-chip size="x-small">{{ job.stage }}</v-chip>
                            <v-progress-linear :model-value="job.progress_percent" width="120" height="6" rounded />
                            <v-spacer />
                            <v-chip size="x-small" variant="outlined">Candidates: {{ job.counts.candidates.total }}</v-chip>
                        </div>
                    </v-expansion-panel-title>
                    <v-expansion-panel-text>
                        <v-row dense>
                            <v-col cols="12" md="4"><div class="text-caption">География</div><strong>{{ job.job.geography || 'не выбрана' }}</strong></v-col>
                            <v-col cols="12" md="4"><div class="text-caption">Query plan</div><strong>{{ job.counts.queries.approved }}/{{ job.counts.queries.planned }} approved</strong></v-col>
                            <v-col cols="12" md="4"><div class="text-caption">Fetch</div><strong>{{ job.counts.fetches.completed }} completed · {{ job.counts.fetches.partial_or_fail_closed }} partial/blocked</strong></v-col>
                        </v-row>
                        <v-alert v-if="job.fetch_outcomes.length" type="info" variant="tonal" density="compact" class="my-3">
                            <span v-for="outcome in job.fetch_outcomes" :key="`${outcome.status}-${outcome.error_code}`" class="mr-3">
                                {{ outcome.status }}{{ outcome.error_code ? ` / ${outcome.error_code}` : '' }}: {{ outcome.count }}
                            </span>
                        </v-alert>
                        <div v-if="job.scoring.visible" class="d-flex ga-2 flex-wrap my-3">
                            <v-chip
                                v-for="score in job.scoring.prospect_priority"
                                :key="score.snapshot_id"
                                :color="scoreColor(score.band)"
                                size="small"
                            >
                                {{ score.unit.name }} · {{ score.band }} · {{ score.effective_score }} · confidence {{ score.confidence_band }} · {{ score.eligibility }}
                            </v-chip>
                        </div>
                        <div class="d-flex ga-2 flex-wrap">
                            <v-btn
                                v-for="candidate in job.candidates"
                                :key="candidate.id"
                                size="small"
                                variant="text"
                                :href="candidate.resolved_unit?.url || candidate.review_url"
                            >
                                {{ candidate.resolved_unit ? `Unit: ${candidate.resolved_unit.name}` : `Candidate: ${candidate.status}` }}
                            </v-btn>
                            <v-btn
                                v-if="['draft', 'query_plan_ready', 'review_required', 'search_pending'].includes(job.stage)"
                                size="small" color="error" variant="text" @click.stop="cancel(job)"
                            >Отменить</v-btn>
                        </div>
                        <div class="text-caption mt-2">{{ job.next_action.label }}</div>
                    </v-expansion-panel-text>
                </v-expansion-panel>
            </v-expansion-panels>
            <div v-if="!jobs.length" class="text-medium-emphasis">Заданий пока нет.</div>
        </v-card-text>
    </v-card>
</template>
