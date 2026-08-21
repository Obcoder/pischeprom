<script setup>
import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
    modelValue: Boolean,
    campaign: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const open = computed({
    get: () => props.modelValue,
    set: value => emit('update:modelValue', value),
})
const busy = ref(false)
const error = ref('')
const advanced = ref(false)
const hydrating = ref(false)
const segmentMode = ref('recommended')
const productOptions = reactive({ primary: [], additional: [], excluded: [] })
const productSearch = reactive({ primary: '', additional: '', excluded: '' })
const productPage = reactive({ primary: 1, additional: 1, excluded: 1 })
const productLastPage = reactive({ primary: 1, additional: 1, excluded: 1 })
const productLoading = reactive({ primary: false, additional: false, excluded: false })
const geography = reactive({ countries: [], regions: [], cities: [] })
const geoSearch = reactive({ country: '', region: '', city: '' })
const geoLoading = reactive({ country: false, region: false, city: false })
const segmentOptions = ref([])
const segmentSearch = ref('')
const segmentLoading = ref(false)
const timers = new Map()

const defaultLimits = () => ({
    max_active_runs: 1,
    max_runs_per_day: 2,
    max_runs_per_month: 20,
    max_search_requests_per_run: 10,
    max_search_requests_per_day: 20,
    max_search_requests_per_month: 200,
    max_research_pages_per_run: 30,
    max_candidates_per_run: 15,
    max_units_per_run: 0,
    max_units_per_day: 0,
    max_units_per_month: 0,
    max_drafts_per_run: 0,
    max_drafts_per_day: 0,
    max_drafts_per_month: 0,
    max_requests_per_run: 10,
    max_requests_per_day: 20,
    max_requests_per_month: 200,
    max_tokens_per_run: 4000,
    max_tokens_per_day: 8000,
    max_tokens_per_month: 80000,
    max_cost_rub_per_run: 10,
    max_cost_rub_per_day: 20,
    max_cost_rub_per_month: 200,
})

const freshForm = () => ({
    safe_name: '',
    safe_objective: '',
    primary_product_id: null,
    additional_product_ids: [],
    excluded_product_ids: [],
    automation_mode: 'manual',
    auto_unit_approved: false,
    auto_draft_approved: false,
    schedule_cadence: 'manual',
    schedule_timezone: 'Europe/Moscow',
    next_run_at: null,
    criteria: {
        country_id: null,
        region_id: null,
        city_id: null,
        segments: [],
        industries: [],
        categories: [],
        max_domains: 30,
        max_page_fetch_attempts: 30,
        max_results_per_query: 10,
    },
    limits: defaultLimits(),
})
const form = reactive(freshForm())

const automationOptions = [
    { title: 'Ручной', value: 'manual' },
    { title: 'С участием менеджера', value: 'assisted' },
    { title: 'Автономный после проверки', value: 'autonomous_reviewed' },
]
const cadenceOptions = [
    { title: 'Только вручную', value: 'manual' },
    { title: 'Ежедневно', value: 'daily' },
    { title: 'Еженедельно', value: 'weekly' },
    { title: 'Ежемесячно', value: 'monthly' },
]
const additionalOptions = computed(() => productOptions.additional.filter(item =>
    Number(item.id) !== Number(form.primary_product_id)
    && !form.excluded_product_ids.map(Number).includes(Number(item.id))))
const excludedOptions = computed(() => productOptions.excluded.filter(item =>
    Number(item.id) !== Number(form.primary_product_id)
    && !form.additional_product_ids.map(Number).includes(Number(item.id))))
const shownSegments = computed(() => segmentMode.value === 'recommended'
    ? segmentOptions.value.filter(item => item.recommended)
    : segmentOptions.value)

function debounce(key, callback) {
    clearTimeout(timers.get(key))
    timers.set(key, setTimeout(callback, 300))
}

function mergeOptions(current, rows) {
    const key = item => `${typeof item.id}:${String(item.id)}`
    const values = new Map(current.map(item => [key(item), item]))
    rows.forEach(item => values.set(key(item), item))
    return [...values.values()]
}

async function loadProducts(kind, reset = true) {
    productLoading[kind] = true
    if (reset) productPage[kind] = 1
    try {
        const selectedIds = [
            form.primary_product_id,
            ...form.additional_product_ids,
            ...form.excluded_product_ids,
        ].filter(Boolean)
        const response = await axios.get('/api/ai-sales/prospecting/catalog/products', {
            params: {
                search: productSearch[kind] || undefined,
                page: productPage[kind],
                per_page: 25,
                ids: selectedIds,
            },
        })
        const rows = [...(response.data.data || []), ...(response.data.selected || [])]
        productOptions[kind] = reset ? mergeOptions([], rows) : mergeOptions(productOptions[kind], rows)
        productLastPage[kind] = response.data.meta?.last_page || 1
    } catch {
        error.value = 'Справочник Products безопасно недоступен.'
    } finally {
        productLoading[kind] = false
    }
}

async function loadMoreProducts(kind) {
    if (productPage[kind] >= productLastPage[kind]) return
    productPage[kind] += 1
    await loadProducts(kind, false)
}

async function loadCountries(selectedId = null) {
    geoLoading.country = true
    try {
        const response = await axios.get('/api/ai-sales/prospecting/catalog/countries', {
            params: { search: selectedId ? undefined : geoSearch.country || undefined, id: selectedId || undefined, per_page: 50 },
        })
        geography.countries = mergeOptions(geography.countries, response.data.data || [])
    } finally {
        geoLoading.country = false
    }
}

async function loadRegions(selectedId = null) {
    if (!form.criteria.country_id) {
        geography.regions = []
        return
    }
    geoLoading.region = true
    try {
        const response = await axios.get('/api/ai-sales/prospecting/catalog/regions', {
            params: {
                country_id: form.criteria.country_id,
                search: selectedId ? undefined : geoSearch.region || undefined,
                id: selectedId || undefined,
                per_page: 50,
            },
        })
        geography.regions = selectedId ? mergeOptions(geography.regions, response.data.data || []) : (response.data.data || [])
    } finally {
        geoLoading.region = false
    }
}

async function loadCities(selectedId = null) {
    if (!form.criteria.country_id || !form.criteria.region_id) {
        geography.cities = []
        return
    }
    geoLoading.city = true
    try {
        const response = await axios.get('/api/ai-sales/prospecting/catalog/cities', {
            params: {
                country_id: form.criteria.country_id,
                region_id: form.criteria.region_id,
                search: selectedId ? undefined : geoSearch.city || undefined,
                id: selectedId || undefined,
                per_page: 50,
            },
        })
        geography.cities = selectedId ? mergeOptions(geography.cities, response.data.data || []) : (response.data.data || [])
    } finally {
        geoLoading.city = false
    }
}

async function loadSegments() {
    segmentLoading.value = true
    try {
        const response = await axios.get('/api/ai-sales/prospecting/catalog/segments', {
            params: {
                product_id: form.primary_product_id || undefined,
                search: segmentSearch.value || undefined,
                per_page: 50,
                ids: form.criteria.segments,
            },
        })
        segmentOptions.value = mergeOptions(segmentOptions.value, [
            ...(response.data.data || []),
            ...(response.data.selected || []),
        ])
    } finally {
        segmentLoading.value = false
    }
}

async function hydrate() {
    hydrating.value = true
    error.value = ''
    Object.assign(form, freshForm())
    const campaign = props.campaign
    if (campaign) {
        const primary = (campaign.products || []).find(item => item.role === 'primary')
        Object.assign(form, {
            safe_name: campaign.safe_name || '',
            safe_objective: campaign.safe_objective || '',
            primary_product_id: primary?.id || null,
            additional_product_ids: (campaign.products || []).filter(item => item.role === 'additional').map(item => item.id),
            excluded_product_ids: (campaign.products || []).filter(item => item.role === 'exclude').map(item => item.id),
            automation_mode: campaign.automation_mode || 'manual',
            auto_unit_approved: Boolean(campaign.policies?.auto_unit?.approved),
            auto_draft_approved: Boolean(campaign.policies?.auto_draft?.approved),
            schedule_cadence: campaign.schedule?.cadence || 'manual',
            schedule_timezone: campaign.schedule?.timezone || 'Europe/Moscow',
            next_run_at: campaign.schedule?.next_run_at || null,
        })
        Object.assign(form.criteria, campaign.criteria || {})
        Object.assign(form.limits, campaign.limits || {})
        const productRows = (campaign.products || []).map(item => ({ id: item.id, name: item.name }))
        for (const kind of ['primary', 'additional', 'excluded']) productOptions[kind] = productRows
    }
    await Promise.all(['primary', 'additional', 'excluded'].map(kind => loadProducts(kind)))
    await loadCountries(form.criteria.country_id)
    await loadRegions(form.criteria.region_id)
    await loadCities(form.criteria.city_id)
    await loadSegments()
    hydrating.value = false
}

async function save() {
    busy.value = true
    error.value = ''
    try {
        if (props.campaign) {
            await axios.patch(`/api/ai-sales/campaigns/${encodeURIComponent(props.campaign.id)}`, form)
        } else {
            await axios.post('/api/ai-sales/campaigns', form)
        }
        open.value = false
        emit('saved')
    } catch (requestError) {
        const errors = requestError?.response?.data?.errors
        error.value = errors ? Object.values(errors).flat().join(' ') : 'Campaign draft заблокирован server-side validation.'
    } finally {
        busy.value = false
    }
}

watch(open, value => { if (value) hydrate() })
for (const kind of ['primary', 'additional', 'excluded']) {
    watch(() => productSearch[kind], () => debounce(`product-${kind}`, () => loadProducts(kind)))
}
watch(() => form.primary_product_id, async value => {
    if (hydrating.value) return
    form.additional_product_ids = form.additional_product_ids.filter(id => Number(id) !== Number(value))
    form.excluded_product_ids = form.excluded_product_ids.filter(id => Number(id) !== Number(value))
    await loadSegments()
})
watch(() => form.additional_product_ids, value => {
    if (hydrating.value) return
    form.excluded_product_ids = form.excluded_product_ids.filter(id => !value.map(Number).includes(Number(id)))
}, { deep: true })
watch(() => form.criteria.country_id, async () => {
    if (hydrating.value) return
    form.criteria.region_id = null
    form.criteria.city_id = null
    geography.regions = []
    geography.cities = []
    await loadRegions()
})
watch(() => form.criteria.region_id, async () => {
    if (hydrating.value) return
    form.criteria.city_id = null
    geography.cities = []
    await loadCities()
})
watch(() => geoSearch.country, () => debounce('country', loadCountries))
watch(() => geoSearch.region, () => debounce('region', loadRegions))
watch(() => geoSearch.city, () => debounce('city', loadCities))
watch(segmentSearch, () => debounce('segments', loadSegments))
</script>

<template>
    <v-dialog v-model="open" max-width="1040" persistent>
        <v-card>
            <v-card-title>{{ campaign ? 'Редактирование кампании' : 'Новая кампания покупателей' }}</v-card-title>
            <v-card-subtitle>Product-first campaign draft · сервер проверит справочники, лимиты и lane.</v-card-subtitle>
            <v-card-text>
                <v-alert v-if="error" type="warning" variant="tonal" class="mb-4">{{ error }}</v-alert>

                <v-text-field v-model="form.safe_name" label="Название кампании *" maxlength="160" />
                <v-textarea
                    v-model="form.safe_objective"
                    label="Цель кампании *"
                    hint="Опишите бизнес-цель без персональных или секретных данных."
                    persistent-hint
                    maxlength="512"
                    rows="2"
                    class="mb-3"
                />

                <v-autocomplete
                    v-model="form.primary_product_id"
                    v-model:search="productSearch.primary"
                    :items="productOptions.primary"
                    :loading="productLoading.primary"
                    item-title="name"
                    item-value="id"
                    label="Основной продукт *"
                    hint="Основной продукт определяет, покупателей какого товара будет искать система."
                    persistent-hint
                    clearable
                    no-data-text="Products не найдены"
                >
                    <template #item="{ props: itemProps, item }">
                        <v-list-item v-bind="itemProps" :subtitle="item.raw.category || item.raw.english_name" />
                    </template>
                </v-autocomplete>
                <v-btn
                    v-if="productPage.primary < productLastPage.primary"
                    size="x-small"
                    variant="text"
                    @click="loadMoreProducts('primary')"
                >Показать ещё Products</v-btn>

                <v-autocomplete
                    v-model="form.additional_product_ids"
                    v-model:search="productSearch.additional"
                    :items="additionalOptions"
                    :loading="productLoading.additional"
                    item-title="name"
                    item-value="id"
                    label="Дополнительные продукты"
                    hint="Они расширяют Product scope, но не заменяют основной продукт."
                    persistent-hint
                    multiple
                    chips
                    closable-chips
                    clearable
                    no-data-text="Products не найдены"
                />
                <v-btn
                    v-if="productPage.additional < productLastPage.additional"
                    size="x-small"
                    variant="text"
                    @click="loadMoreProducts('additional')"
                >Показать ещё Products</v-btn>

                <v-row dense class="mt-3">
                    <v-col cols="12" md="4">
                        <v-autocomplete
                            v-model="form.criteria.country_id"
                            v-model:search="geoSearch.country"
                            :items="geography.countries"
                            :loading="geoLoading.country"
                            item-title="name"
                            item-value="id"
                            label="Страна"
                            clearable
                            no-data-text="Страны не найдены"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-autocomplete
                            v-model="form.criteria.region_id"
                            v-model:search="geoSearch.region"
                            :items="geography.regions"
                            :loading="geoLoading.region"
                            :disabled="!form.criteria.country_id"
                            item-title="name"
                            item-value="id"
                            label="Регион"
                            clearable
                            no-data-text="Регионы не найдены"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-autocomplete
                            v-model="form.criteria.city_id"
                            v-model:search="geoSearch.city"
                            :items="geography.cities"
                            :loading="geoLoading.city"
                            :disabled="!form.criteria.region_id"
                            item-title="name"
                            item-value="id"
                            label="Город"
                            clearable
                            no-data-text="Города не найдены"
                        />
                    </v-col>
                </v-row>
                <div class="text-caption text-medium-emphasis mb-3">
                    Можно выбрать страну целиком или уточнить один регион и город — текущая Campaign schema хранит одну согласованную географию.
                </div>

                <v-btn-toggle v-model="segmentMode" mandatory density="compact" color="primary" class="mb-2">
                    <v-btn value="recommended">Рекомендуемые</v-btn>
                    <v-btn value="all">Все сегменты</v-btn>
                </v-btn-toggle>
                <v-autocomplete
                    v-model="form.criteria.segments"
                    v-model:search="segmentSearch"
                    :items="shownSegments"
                    :loading="segmentLoading"
                    item-title="name"
                    item-value="id"
                    label="Сегменты покупателей"
                    hint="Сегменты ограничивают типы предприятий, которые считаются потенциальными покупателями."
                    persistent-hint
                    multiple
                    chips
                    closable-chips
                    clearable
                    no-data-text="Для продукта пока нет рекомендаций. Откройте «Все сегменты» для ручного выбора."
                />

                <v-expansion-panels v-model="advanced" class="mt-4">
                    <v-expansion-panel :value="true">
                        <v-expansion-panel-title>Расширенные настройки</v-expansion-panel-title>
                        <v-expansion-panel-text>
                            <v-select v-model="form.automation_mode" :items="automationOptions" label="Режим автоматизации" />
                            <v-select v-model="form.schedule_cadence" :items="cadenceOptions" label="Режим запуска / Частота" />
                            <v-text-field
                                v-if="form.schedule_cadence !== 'manual'"
                                v-model="form.next_run_at"
                                type="datetime-local"
                                label="Следующий ограниченный запуск"
                            />
                            <v-autocomplete
                                v-model="form.excluded_product_ids"
                                v-model:search="productSearch.excluded"
                                :items="excludedOptions"
                                :loading="productLoading.excluded"
                                item-title="name"
                                item-value="id"
                                label="Исключённые продукты"
                                multiple
                                chips
                                closable-chips
                                clearable
                            />

                            <div class="text-subtitle-2 mb-1">Лимиты кампании</div>
                            <div class="text-caption text-medium-emphasis mb-3">
                                После утверждения кампании лимиты фиксируются до повторной проверки. Глобальные production ceilings остаются fail-closed.
                            </div>
                            <v-row dense>
                                <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_runs_per_day" type="number" min="0" label="Запусков в день" /></v-col>
                                <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_runs_per_month" type="number" min="0" label="Запусков в месяц" /></v-col>
                                <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_search_requests_per_run" type="number" min="0" label="Поисковых запросов за запуск" /></v-col>
                                <v-col cols="6" md="3"><v-text-field v-model.number="form.criteria.max_results_per_query" type="number" min="1" max="50" label="Результатов на запрос" /></v-col>
                                <v-col cols="6" md="3"><v-text-field v-model.number="form.criteria.max_domains" type="number" min="1" max="100" label="Доменов за запуск" /></v-col>
                                <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_research_pages_per_run" type="number" min="0" label="Страниц для исследования" /></v-col>
                                <v-col cols="6" md="3"><v-text-field v-model.number="form.criteria.max_page_fetch_attempts" type="number" min="1" max="100" label="Попыток исследования" /></v-col>
                                <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_candidates_per_run" type="number" min="0" label="Кандидатов за запуск" /></v-col>
                            </v-row>
                            <v-alert type="info" variant="tonal" density="compact">
                                Автоматическое создание Candidate/Unit, scoring, drafts, dispatch, Entity и email эта форма не включает.
                            </v-alert>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                </v-expansion-panels>
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="open = false">Отмена</v-btn>
                <v-btn color="primary" :loading="busy" :disabled="!form.safe_name || !form.safe_objective || !form.primary_product_id" @click="save">
                    Сохранить черновик
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
