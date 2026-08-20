<script setup>
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

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
const reviewedUnitName = ref('')
const nameConfirmed = ref(false)
const canReview = computed(() => Boolean(page.props.auth?.permissions?.ai_sales?.review))
const canResolve = computed(() => Boolean(page.props.auth?.permissions?.ai_sales?.resolve))
const investigation = computed(() => props.candidate.investigation || {})
const identity = computed(() => investigation.value.identity || {})
const candidateDetails = computed(() => investigation.value.candidate || {})
const productScopes = computed(() => investigation.value.product_scope || [])
const sources = computed(() => investigation.value.sources || [])
const facts = computed(() => investigation.value.facts || [])
const publicContacts = computed(() => investigation.value.public_contacts || [])
const duplicates = computed(() => investigation.value.duplicates || [])
const identityResolved = computed(() => identity.value.verification_status !== 'unresolved')
const displayIdentityName = computed(() => identity.value.inferred_company_name
    || identity.value.public_site_name
    || identity.value.registrable_domain
    || 'Компания не идентифицирована')
const canCreateUnit = computed(() => canResolve.value
    && props.candidate.status === 'new_unit_review'
    && reviewedUnitName.value.trim().length >= 2
    && nameConfirmed.value)
const unitDossierUrl = computed(() => props.candidate.resolved_unit
    ? `/Ameise/unit/${Number(props.candidate.resolved_unit.id)}?ai_sales=1#prospecting-dossier`
    : null)

watch(
    () => props.candidate.id,
    () => {
        reviewedUnitName.value = identity.value.inferred_company_name
            || identity.value.public_site_name
            || identity.value.registrable_domain
            || ''
        nameConfirmed.value = false
        error.value = ''
    },
    { immediate: true },
)

function confidenceLabel(value) {
    return value === null || value === undefined ? 'не установлена' : `${value}%`
}

function dateLabel(value) {
    if (!value) return 'дата не установлена'
    const date = new Date(value)

    return Number.isNaN(date.getTime()) ? 'дата не установлена' : date.toLocaleDateString('ru-RU')
}

function confirmHumanDecision(message) {
    return typeof window === 'undefined' || window.confirm(message)
}

function safeActionError(requestError) {
    if (requestError?.response?.status === 403) return 'Действие запрещено permission или lane policy.'
    if (requestError?.response?.status === 409) return 'Состояние изменилось; обновите Candidate и повторите human review.'
    if (requestError?.response?.status === 422) return 'Повторная policy/duplicate проверка или подтверждение имени заблокировали действие.'

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
    if (!canCreateUnit.value) {
        error.value = 'Введите и подтвердите рабочее имя нового Unit.'

        return
    }

    return perform(
        'create-unit',
        `/api/ai-sales/prospecting/candidates/${encodeURIComponent(props.candidate.id)}/create-unit`,
        {
            reviewed_working_name: reviewedUnitName.value.trim(),
            name_confirmed: true,
        },
        'Подтвердить создание нового Unit с указанным рабочим именем? Сервис повторно проверит дубли; Entity создан не будет.',
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
            <span>{{ displayIdentityName }}</span>
            <v-chip size="small" color="warning" variant="tonal">{{ candidate.status }}</v-chip>
            <v-chip v-if="candidate.resolution_outcome" size="small" variant="outlined">
                {{ candidate.resolution_outcome }}
            </v-chip>
        </v-card-title>

        <v-card-subtitle>
            {{ candidateDetails.location?.display || 'География не установлена' }} ·
            {{ candidateDetails.lane || candidate.lane }} · {{ candidateDetails.role || candidate.role_code }}
        </v-card-subtitle>

        <v-card-text>
            <v-alert v-if="error" type="warning" variant="tonal" density="compact" class="mb-4">
                {{ error }}
            </v-alert>

            <section class="investigation-section">
                <h3 class="text-subtitle-1 mb-2">Кто найден</h3>
                <v-alert v-if="!identityResolved" type="info" variant="tonal" density="compact" class="mb-3">
                    Компания пока не идентифицирована.<br>
                    Найден релевантный публичный источник, но требуется human review.
                </v-alert>
                <dl class="investigation-facts">
                    <div><dt>Рабочее/site имя</dt><dd>{{ displayIdentityName }}</dd></div>
                    <div><dt>Домен</dt><dd>{{ identity.registrable_domain || 'не установлен' }}</dd></div>
                    <div><dt>Город/регион</dt><dd>{{ candidateDetails.location?.display || 'не установлены' }}</dd></div>
                    <div><dt>Identity confidence</dt><dd>{{ confidenceLabel(identity.confidence) }}</dd></div>
                    <div><dt>Identity status</dt><dd>{{ identity.verification_status || 'unresolved' }}</dd></div>
                    <div><dt>Evidence status</dt><dd>{{ identity.evidence_status || 'missing' }}</dd></div>
                </dl>
                <v-alert
                    v-if="candidateDetails.working_name_origin === 'source_page_title'"
                    type="warning"
                    variant="tonal"
                    density="compact"
                    class="mt-3"
                >
                    Заголовок найденной страницы «{{ candidateDetails.suggested_working_name }}» — только исходная подсказка,
                    а не подтверждённое название компании или Unit.
                </v-alert>
            </section>

            <v-divider class="my-4" />

            <section class="investigation-section">
                <h3 class="text-subtitle-1 mb-2">Почему подходит</h3>
                <v-card v-for="scope in productScopes" :key="scope.product.id" variant="tonal" class="mb-2">
                    <v-card-text>
                        <div class="font-weight-medium">Product «{{ scope.product.name || scope.product.english_name }}»</div>
                        <div class="text-body-2 mt-1">{{ scope.rationale || 'Причина требует human review.' }}</div>
                        <ul v-if="scope.evidence_statements?.length" class="mt-2 mb-0">
                            <li v-for="statement in scope.evidence_statements" :key="statement">{{ statement }}</li>
                        </ul>
                        <div class="text-caption text-medium-emphasis mt-2">
                            Confidence: {{ confidenceLabel(scope.confidence) }} ·
                            Product relevance score:
                            <strong v-if="scope.score_status === 'calculated'">{{ scope.score?.effective_score }}</strong>
                            <strong v-else>не рассчитан</strong>
                        </div>
                    </v-card-text>
                </v-card>
                <div v-if="!productScopes.length" class="text-body-2 text-medium-emphasis">
                    Product scope отсутствует — решение заблокировано до проверки.
                </div>
            </section>

            <v-divider class="my-4" />

            <section class="investigation-section">
                <h3 class="text-subtitle-1 mb-2">Источники</h3>
                <v-list density="compact" class="pa-0">
                    <v-list-item v-for="source in sources" :key="source.id" class="source-item">
                        <v-list-item-title>{{ source.title || source.reference }}</v-list-item-title>
                        <v-list-item-subtitle class="source-details">
                            {{ source.registrable_domain || 'домен не установлен' }} · {{ source.source_kind }} ·
                            fetch: {{ source.fetch_status }} · research: {{ source.research_status }} ·
                            {{ dateLabel(source.freshness?.observed_at) }}
                        </v-list-item-subtitle>
                        <div v-if="source.safe_claim_summary" class="text-body-2 mt-1">{{ source.safe_claim_summary }}</div>
                        <div v-if="source.safe_excerpt" class="text-caption text-medium-emphasis mt-1">
                            {{ source.safe_excerpt }}
                        </div>
                        <div class="text-caption text-medium-emphasis mt-1">
                            Evidence: {{ source.evidence_reference || source.evidence_hash || 'не записан' }}
                        </div>
                        <template #append>
                            <v-btn
                                v-if="source.safe_url"
                                :href="source.safe_url"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                size="small"
                                variant="text"
                                prepend-icon="mdi-open-in-new"
                            >
                                Открыть источник
                            </v-btn>
                        </template>
                    </v-list-item>
                    <v-list-item v-if="!sources.length" title="Безопасные источники отсутствуют" />
                </v-list>
            </section>

            <v-divider class="my-4" />

            <section class="investigation-section">
                <h3 class="text-subtitle-1 mb-2">Публичные сведения</h3>
                <v-list density="compact" class="pa-0">
                    <v-list-item
                        v-for="(fact, index) in facts"
                        :key="`${fact.type}-${index}`"
                        :title="fact.summary"
                        :subtitle="`${fact.type} · ${fact.verification_status} · confidence ${confidenceLabel(fact.confidence)}`"
                    />
                    <v-list-item
                        v-for="(contact, index) in publicContacts"
                        :key="`contact-${contact.kind}-${index}`"
                        :title="contact.display"
                        :subtitle="`Проверенный общий корпоративный контакт · ${contact.kind}`"
                        prepend-icon="mdi-domain"
                    />
                    <v-list-item
                        v-if="!facts.length && !publicContacts.length"
                        title="Подтверждённые публичные сведения пока отсутствуют"
                    />
                </v-list>
            </section>

            <v-divider class="my-4" />

            <section class="investigation-section">
                <h3 class="text-subtitle-1 mb-2">Проверка дублей</h3>
                <v-list density="compact" class="pa-0">
                    <v-list-item v-for="match in duplicates" :key="`${match.unit.id}-${match.reason_code}`">
                        <v-list-item-title>{{ match.unit.name || `Unit #${match.unit.id}` }}</v-list-item-title>
                        <v-list-item-subtitle>
                            {{ match.match_type }} · {{ match.reason }} · confidence {{ confidenceLabel(match.confidence) }}
                        </v-list-item-subtitle>
                        <div class="text-caption text-medium-emphasis">
                            Город: {{ match.unit.city || 'не установлен' }} · Домен: {{ match.unit.domain || 'не установлен' }}
                        </div>
                        <template #append>
                            <div class="d-flex ga-1 flex-wrap justify-end">
                                <v-btn :href="match.unit.url" size="small" variant="text">Открыть Unit</v-btn>
                                <v-btn
                                    v-if="canResolve"
                                    size="small"
                                    variant="tonal"
                                    :loading="busy === `resolve-${match.unit.id}`"
                                    @click="resolveExisting(match)"
                                >
                                    Связать с существующим Unit
                                </v-btn>
                            </div>
                        </template>
                    </v-list-item>
                    <v-list-item
                        v-if="!duplicates.length"
                        title="Совпадений не найдено"
                        subtitle="Создание нового Unit остаётся отдельным human approval с повторной duplicate проверкой."
                    />
                </v-list>
            </section>

            <v-divider class="my-4" />

            <section class="investigation-section">
                <h3 class="text-subtitle-1 mb-2">Решение</h3>
                <div v-if="canResolve && candidate.status === 'new_unit_review'" class="unit-name-confirmation">
                    <v-text-field
                        v-model="reviewedUnitName"
                        label="Рабочее имя нового Unit"
                        hint="Введите подтверждённое человеком имя. SEO title не подставляется автоматически."
                        persistent-hint
                        maxlength="255"
                        counter
                    />
                    <v-checkbox
                        v-model="nameConfirmed"
                        label="Я проверил(а) имя и подтверждаю его как рабочее имя Unit"
                        hide-details
                    />
                </div>
                <div class="d-flex flex-wrap ga-2 mt-3">
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
                        :disabled="!canCreateUnit"
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
                </div>
            </section>
        </v-card-text>
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

.investigation-section {
    min-width: 0;
}

.investigation-facts {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 20px;
    margin: 0;
}

.investigation-facts div {
    min-width: 0;
}

.investigation-facts dt {
    color: rgba(var(--v-theme-on-surface), 0.66);
    font-size: 0.75rem;
}

.investigation-facts dd {
    margin: 2px 0 0;
    overflow-wrap: anywhere;
}

.source-item {
    align-items: flex-start;
}

.source-details {
    white-space: normal;
}

.unit-name-confirmation {
    max-width: 720px;
}

@media (max-width: 760px) {
    .investigation-facts {
        grid-template-columns: 1fr;
    }
}
</style>
