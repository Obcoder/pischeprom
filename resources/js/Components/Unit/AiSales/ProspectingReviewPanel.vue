<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'

const tab = ref('jobs')
const loading = ref(false)
const error = ref('')
const jobs = ref([])
const candidates = ref([])
const dialog = ref(false)
const form = reactive({ purpose: 'buyer_discovery', safe_objective: '', criteria: { segments: [] } })

const reviewCandidates = computed(() => candidates.value.filter((item) => [
    'exact_existing_unit', 'probable_existing_review', 'new_unit_review',
].includes(item.status)))
const exceptions = computed(() => candidates.value.filter((item) => [
    'rejected', 'expired', 'anonymized',
].includes(item.status)))
const resolvedUnits = computed(() => candidates.value.filter((item) => item.resolved_unit))

async function load() {
    loading.value = true
    error.value = ''
    try {
        const [jobResponse, candidateResponse] = await Promise.all([
            axios.get('/api/ai-sales/prospecting/jobs?per_page=50'),
            axios.get('/api/ai-sales/prospecting/candidates?per_page=50'),
        ])
        jobs.value = jobResponse.data.data || []
        candidates.value = candidateResponse.data.data || []
    } catch (requestError) {
        error.value = requestError.response?.status === 404
            ? 'Stage 08 выключен feature flag.'
            : (requestError.response?.data?.message || 'Не удалось загрузить безопасный review queue.')
    } finally {
        loading.value = false
    }
}

async function createJob() {
    await axios.post('/api/ai-sales/prospecting/jobs', form)
    dialog.value = false
    form.safe_objective = ''
    await load()
}

async function jobAction(job, action) {
    await axios.post(`/api/ai-sales/prospecting/jobs/${job.id}/${action}`, {})
    await load()
}

async function evaluate(candidate) {
    await axios.post(`/api/ai-sales/prospecting/candidates/${candidate.id}/evaluate`, {})
    await load()
}

async function resolveExisting(candidate, unitId) {
    await axios.post(`/api/ai-sales/prospecting/candidates/${candidate.id}/resolve-existing`, { unit_id: unitId })
    await load()
}

async function createUnit(candidate) {
    await axios.post(`/api/ai-sales/prospecting/candidates/${candidate.id}/create-unit`, {})
    await load()
}

async function reject(candidate) {
    await axios.post(`/api/ai-sales/prospecting/candidates/${candidate.id}/reject`, { reason_code: 'irrelevant' })
    await load()
}

onMounted(load)
</script>

<template>
    <v-card variant="outlined" class="prospecting-review">
        <v-card-title class="d-flex align-center ga-3">
            <span>AI-поиск покупателей</span>
            <v-chip size="small" color="amber">Stage 08 · review only</v-chip>
            <v-spacer />
            <v-btn size="small" variant="tonal" :disabled="loading" @click="load">Обновить</v-btn>
            <v-btn size="small" color="primary" @click="dialog = true">Новое задание</v-btn>
        </v-card-title>
        <v-alert v-if="error" type="info" variant="tonal" class="ma-3">{{ error }}</v-alert>
        <v-alert type="warning" variant="tonal" density="compact" class="mx-3">
            Live search, AI execution и автоматическое создание Unit/Entity выключены. Lane и роль выводятся сервером.
        </v-alert>
        <v-tabs v-model="tab" class="mt-2">
            <v-tab value="jobs">Задания</v-tab>
            <v-tab value="candidates">Кандидаты</v-tab>
            <v-tab value="units">Units</v-tab>
            <v-tab value="review">На проверке</v-tab>
            <v-tab value="exceptions">Исключения</v-tab>
        </v-tabs>
        <v-tabs-window v-model="tab">
            <v-tabs-window-item value="jobs">
                <v-list lines="two">
                    <v-list-item v-for="job in jobs" :key="job.id" :title="job.safe_objective" :subtitle="`${job.purpose} · ${job.lane} · ${job.status}`">
                        <template #append>
                            <v-btn v-if="job.status === 'draft'" size="small" variant="text" @click="jobAction(job, 'submit')">На проверку</v-btn>
                            <v-btn v-if="job.status === 'review_required'" size="small" variant="text" color="success" @click="jobAction(job, 'approve')">Одобрить</v-btn>
                            <v-btn v-if="['draft', 'review_required', 'approved'].includes(job.status)" size="small" variant="text" color="error" @click="jobAction(job, 'cancel')">Отменить</v-btn>
                            <v-btn v-if="['approved', 'cancelled'].includes(job.status)" size="small" variant="text" @click="jobAction(job, 'archive')">В архив</v-btn>
                        </template>
                    </v-list-item>
                    <v-list-item v-if="!jobs.length" title="Заданий нет" />
                </v-list>
            </v-tabs-window-item>
            <v-tabs-window-item value="candidates">
                <v-list lines="three">
                    <v-list-item v-for="candidate in candidates" :key="candidate.id" :title="candidate.working_name" :subtitle="`${candidate.domain || 'домен неизвестен'} · ${candidate.location || 'география неизвестна'} · ${candidate.status}`">
                        <template #append><v-btn size="small" variant="text" @click="evaluate(candidate)">Проверить</v-btn></template>
                    </v-list-item>
                </v-list>
            </v-tabs-window-item>
            <v-tabs-window-item value="units">
                <v-list><v-list-item v-for="candidate in resolvedUnits" :key="candidate.id" :title="candidate.resolved_unit.name" :subtitle="`Unit #${candidate.resolved_unit.id}; Candidate ${candidate.id}`" /></v-list>
            </v-tabs-window-item>
            <v-tabs-window-item value="review">
                <v-card v-for="candidate in reviewCandidates" :key="candidate.id" variant="flat" class="ma-3 pa-2 border">
                    <v-card-title>{{ candidate.working_name }}</v-card-title>
                    <v-card-subtitle>{{ candidate.relevance_summary || 'Обоснование не задано' }}</v-card-subtitle>
                    <v-card-text>
                        <div v-for="source in candidate.sources" :key="source.evidence_hash">{{ source.title || source.domain || source.reference }}</div>
                        <div v-for="match in candidate.unit_matches" :key="`${match.unit.id}-${match.signal_code}`">
                            {{ match.unit.name || `Unit #${match.unit.id}` }} · {{ match.signal_code }}
                            <v-btn size="x-small" variant="text" @click="resolveExisting(candidate, match.unit.id)">Связать с Unit</v-btn>
                        </div>
                    </v-card-text>
                    <v-card-actions>
                        <v-btn v-if="candidate.status === 'new_unit_review'" color="success" variant="tonal" @click="createUnit(candidate)">Создать рабочее Unit</v-btn>
                        <v-btn color="error" variant="text" @click="reject(candidate)">Отклонить</v-btn>
                    </v-card-actions>
                </v-card>
            </v-tabs-window-item>
            <v-tabs-window-item value="exceptions">
                <v-list><v-list-item v-for="candidate in exceptions" :key="candidate.id" :title="candidate.working_name" :subtitle="candidate.resolution_reason_code || candidate.status" /></v-list>
            </v-tabs-window-item>
        </v-tabs-window>

        <v-dialog v-model="dialog" max-width="680">
            <v-card>
                <v-card-title>Черновик prospecting job</v-card-title>
                <v-card-text>
                    <v-select v-model="form.purpose" label="Назначение" :items="[
                        { title: 'Покупатели', value: 'buyer_discovery' },
                        { title: 'Поставщики', value: 'supplier_discovery' },
                    ]" />
                    <v-textarea v-model="form.safe_objective" label="Безопасная цель" maxlength="512" counter />
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn @click="dialog = false">Отмена</v-btn><v-btn color="primary" :disabled="!form.safe_objective" @click="createJob">Создать черновик</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>
