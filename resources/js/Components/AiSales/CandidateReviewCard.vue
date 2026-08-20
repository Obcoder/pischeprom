<script setup>
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
    candidate: {
        type: Object,
        required: true,
    },
    highlighted: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['updated'])
const page = usePage()
const busy = ref('')
const error = ref('')
const canReview = computed(() => Boolean(page.props.auth?.permissions?.ai_sales?.review))
const canResolve = computed(() => Boolean(page.props.auth?.permissions?.ai_sales?.resolve))

const unitDossierUrl = computed(() => props.candidate.resolved_unit
    ? `/Ameise/unit/${Number(props.candidate.resolved_unit.id)}?ai_sales=1#prospecting-dossier`
    : null)

function confirmHumanDecision(message) {
    return typeof window === 'undefined' || window.confirm(message)
}

function safeActionError(requestError) {
    if (requestError?.response?.status === 403) return 'Действие запрещено permission или lane policy.'
    if (requestError?.response?.status === 409) return 'Состояние изменилось; обновите Candidate и повторите human review.'
    if (requestError?.response?.status === 422) return 'Повторная policy/duplicate проверка заблокировала действие.'

    return 'Действие безопасно остановлено.'
}

async function perform(label, path, payload, confirmation) {
    if (!confirmHumanDecision(confirmation)) return
    busy.value = label
    error.value = ''
    try {
        await axios.post(path, payload)
        emit('updated')
    } catch (requestError) {
        error.value = safeActionError(requestError)
    } finally {
        busy.value = ''
    }
}

function evaluate() {
    return perform(
        'evaluate',
        `/api/ai-sales/prospecting/candidates/${encodeURIComponent(props.candidate.id)}/evaluate`,
        {},
        'Выполнить детерминированную проверку дублей? Unit и Entity созданы не будут.',
    )
}

function resolveExisting(match) {
    return perform(
        `resolve-${match.unit.id}`,
        `/api/ai-sales/prospecting/candidates/${encodeURIComponent(props.candidate.id)}/resolve-existing`,
        { unit_id: Number(match.unit.id) },
        'Связать Candidate с этим существующим Unit после повторной server-side duplicate проверки?',
    )
}

function createUnit() {
    return perform(
        'create-unit',
        `/api/ai-sales/prospecting/candidates/${encodeURIComponent(props.candidate.id)}/create-unit`,
        {},
        'Подтвердить создание нового Unit? Сервис повторно проверит дубли; Entity создан не будет.',
    )
}

function reject() {
    return perform(
        'reject',
        `/api/ai-sales/prospecting/candidates/${encodeURIComponent(props.candidate.id)}/reject`,
        { reason_code: 'irrelevant' },
        'Отклонить Candidate после human review?',
    )
}
</script>

<template>
    <v-card
        :id="`candidate-${candidate.id}`"
        variant="outlined"
        class="candidate-review-card"
        :class="{ 'candidate-review-card--highlighted': highlighted }"
    >
        <v-card-title class="d-flex align-center ga-2 flex-wrap">
            <v-icon icon="mdi-account-search-outline" />
            <span>{{ candidate.working_name }}</span>
            <v-chip size="small" color="warning" variant="tonal">{{ candidate.status }}</v-chip>
            <v-chip v-if="candidate.resolution_outcome" size="small" variant="outlined">
                {{ candidate.resolution_outcome }}
            </v-chip>
        </v-card-title>

        <v-card-subtitle>
            {{ candidate.location || 'География не указана' }} · {{ candidate.purpose }} · {{ candidate.lane }}
        </v-card-subtitle>

        <v-card-text>
            <v-alert v-if="error" type="warning" variant="tonal" density="compact" class="mb-3">
                {{ error }}
            </v-alert>

            <div class="candidate-review-grid">
                <section>
                    <div class="text-subtitle-2 mb-2">Product scope</div>
                    <v-list density="compact" class="pa-0">
                        <v-list-item
                            v-for="product in candidate.products || []"
                            :key="product.id"
                            :title="product.name || product.english_name"
                            :subtitle="`${product.status} · ${product.source} · ${product.safe_rationale || 'review required'}`"
                        />
                    </v-list>
                </section>

                <section>
                    <div class="text-subtitle-2 mb-2">Policy и review reason</div>
                    <div class="text-body-2">{{ candidate.resolution_reason_code || 'human_review_required' }}</div>
                    <div class="text-caption text-medium-emphasis mt-1">
                        {{ candidate.relevance_summary || 'Product relevance требует human review.' }}
                    </div>
                </section>
            </div>

            <v-divider class="my-3" />

            <div class="text-subtitle-2 mb-2">Источники и evidence</div>
            <v-list density="compact" class="pa-0">
                <v-list-item
                    v-for="source in candidate.sources || []"
                    :key="source.id"
                    :title="source.title || source.reference"
                    :subtitle="`${source.type} · ${source.source_quality || 'unrated'} · confidence ${source.confidence ?? 'unknown'} · ${source.reference}`"
                />
                <v-list-item v-if="!(candidate.sources || []).length" title="Evidence отсутствует" />
            </v-list>

            <v-divider class="my-3" />

            <div class="text-subtitle-2 mb-2">Детерминированные duplicate suggestions</div>
            <v-list density="compact" class="pa-0">
                <v-list-item
                    v-for="match in candidate.unit_matches || []"
                    :key="`${match.unit.id}-${match.signal_code}`"
                    :title="match.unit.name || `Unit #${match.unit.id}`"
                    :subtitle="`${match.signal_code} · strength ${match.strength} · ${match.review_status}`"
                >
                    <template #append>
                        <v-btn
                            v-if="canResolve"
                            size="small"
                            variant="tonal"
                            :loading="busy === `resolve-${match.unit.id}`"
                            @click="resolveExisting(match)"
                        >
                            Связать с существующим Unit
                        </v-btn>
                    </template>
                </v-list-item>
                <v-list-item
                    v-if="!(candidate.unit_matches || []).length"
                    title="Совпадений не найдено"
                    subtitle="Создание нового Unit остаётся отдельным human approval."
                />
            </v-list>
        </v-card-text>

        <v-card-actions class="flex-wrap ga-2">
            <v-btn
                v-if="canReview && candidate.status === 'pending_resolution'"
                variant="tonal"
                :loading="busy === 'evaluate'"
                @click="evaluate"
            >
                Проверить дубли
            </v-btn>
            <v-btn
                v-if="canResolve && candidate.status === 'new_unit_review'"
                color="success"
                variant="tonal"
                :loading="busy === 'create-unit'"
                @click="createUnit"
            >
                Подтвердить создание нового Unit
            </v-btn>
            <v-btn
                v-if="canReview && !candidate.resolved_unit"
                color="error"
                variant="text"
                :loading="busy === 'reject'"
                @click="reject"
            >
                Отклонить
            </v-btn>
            <v-btn v-if="unitDossierUrl" :href="unitDossierUrl" variant="text" prepend-icon="mdi-domain">
                Unit dossier → Product match → score history
            </v-btn>
        </v-card-actions>
    </v-card>
</template>

<style scoped>
.candidate-review-card {
    scroll-margin-top: 76px;
}

.candidate-review-card--highlighted {
    border-color: rgb(var(--v-theme-warning));
    box-shadow: 0 0 0 2px rgba(var(--v-theme-warning), 0.2);
}

.candidate-review-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

@media (max-width: 760px) {
    .candidate-review-grid {
        grid-template-columns: 1fr;
    }
}
</style>
