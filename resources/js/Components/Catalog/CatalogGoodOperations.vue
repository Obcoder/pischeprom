<script setup>
import { ref, reactive, computed, watch, onScopeDispose } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import { useDate } from 'vuetify'
import GoodQuotationCalculator from '@/Components/GoodQuotationCalculator.vue'
import GoodPriceCalculationsTab from '@/Components/Goods/GoodPriceCalculationsTab.vue'
import GoodPriceTypesTab from '@/Components/Goods/GoodPriceTypesTab.vue'
import GoodPriceTypeValuesTab from '@/Components/Goods/GoodPriceTypeValuesTab.vue'
import GoodMediaTab from '@/Components/Goods/GoodMediaTab.vue'
import FindBuyersLauncher from '@/Components/AiSales/FindBuyersLauncher.vue'

const props = defineProps({
    goodId: { type: [Number, String], required: true },
    activeTab: { type: String, default: 'market' },
    active: { type: Boolean, default: true },
})
const emit = defineEmits(['request-basics', 'changed', 'loaded', 'state'])
const operationTabs = ['market', 'prices', 'price-types', 'collections', 'media', 'sales']
const date = useDate()
// --------------------------------------------------
// STATE
// --------------------------------------------------
const pageLoading = ref(true);
const pageError = ref(null);

const goodData = ref(null);
const currencies = ref([]);
const measures = ref([]);
const units = ref([]);
const industries = ref([]);
const savingRecommendationClassifications = ref(false);

const dialogFormQuotation = ref(false);
const recommendationIndustryIds = ref([]);
const priceCalculationsRefreshKey = ref(0);
const priceValuesRefreshKey = ref(0);

// --------------------------------------------------
// COMPUTED
// --------------------------------------------------
const currentVatRate = computed(() => {
    return goodData.value?.vat_rate || goodData.value?.vatRate || null;
});

const defaultVatRate = computed(() => {
    return Number(currentVatRate.value?.rate ?? 20);
});

const goodBoxWeight = computed(() => {
    return Number(goodData.value?.denominator || 1);
});

const currentRecommendationIndustries = computed(() => {
    return goodData.value?.industries || [];
});

const currentFields = computed(() => {
    return goodData.value?.fields || [];
});

const recommendationCards = computed(() => {
    return currentRecommendationIndustries.value.map((industry) => {
        const units = Array.isArray(industry.units) ? industry.units : [];
        const entities = units
            .flatMap((unit) => Array.isArray(unit.entities) ? unit.entities : [])
            .filter((entity, index, list) => {
                return entity?.id && list.findIndex((item) => item?.id === entity.id) === index;
            });

        return {
            ...industry,
            units,
            entities,
        };
    });
});

// --------------------------------------------------
// TABLE HEADERS
// --------------------------------------------------
const headerSales = [
    {
        key: "date",
        title: "Дата",
        sortable: true,
        align: "start",
        width: "25%",
    },
    {
        key: "entity.name",
        title: "Покупатель",
        sortable: true,
        align: "start",
        width: "38%",
    },
    {
        key: "pivot.quantity",
        title: "Кол-во",
        sortable: true,
        align: "center",
        width: "10%",
    },
    {
        key: "pivot.price",
        title: "Цена",
        sortable: true,
        align: "start",
    },
];

const headerQuotations = [
    {
        key: "unit.name",
        title: "Поставщик",
        align: "start",
        sortable: true,
    },
    {
        key: "created_at",
        title: "Дата",
        align: "start",
        sortable: true,
    },
    {
        key: "price",
        title: "Цена",
        align: "start",
        sortable: true,
    },
    {
        key: "measure.name",
        title: "Единица измерения",
        align: "start",
        sortable: true,
    },
    {
        key: "denominator",
        title: "Количество в упаковке",
        align: "start",
        sortable: true,
    },
];

// --------------------------------------------------
// HELPERS
// --------------------------------------------------
function toNumber(value, fallback = 0) {
    const number = Number(value);

    return Number.isFinite(number) ? number : fallback;
}

function formatMoney(value) {
    return new Intl.NumberFormat("ru-RU", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(toNumber(value));
}

function formatDate(value) {
    if (!value) return "-";

    try {
        return date.format(value, "fullDate");
    } catch {
        return value;
    }
}

function entityTitle(entity) {
    return entity?.name || entity?.full_name || entity?.short_name || `Entity #${entity?.id}`;
}

// --------------------------------------------------
// API
// --------------------------------------------------
let requestVersion = 0
let controller = null
let loadedGoodId = null
let disposed = false
let sourceVersion = 0
const asItems = data => Array.isArray(data) ? data : data?.data || []
function cancelLoad() {
    requestVersion++
    controller?.abort()
    controller = null
    pageLoading.value = false
}
function industryTitle(industry) { return [industry.code, industry.title].filter(Boolean).join(' — ') }
function applyGood(good, preserveDraft = true) {
    const keepRecommendations = preserveDraft && recommendationDirty.value
    goodData.value = good
    if (!keepRecommendations) syncRecommendationClassificationForm()
    emit('loaded', good)
}
async function fetchGood({ preserveDraft = true } = {}) {
    const id = props.goodId
    const version = ++sourceVersion
    const response = await axios.get(route('good.fetch', id))
    if (disposed || version !== sourceVersion || String(id) !== String(props.goodId)) return null
    applyGood(response.data, preserveDraft)
    return response.data
}
async function refresh({ throwOnError = false } = {}) {
    try {
        const good = await fetchGood()
        priceCalculationsRefreshKey.value++
        priceValuesRefreshKey.value++
        return good
    } catch (failure) {
        pageError.value = failure.response?.data?.message || 'Не удалось обновить данные товара.'
        if (throwOnError) throw failure
        return null
    }
}
async function changed() {
    try {
        const good = await fetchGood()
        if (good) emit('changed', good)
    } catch (failure) { pageError.value = failure.response?.data?.message || 'Не удалось обновить данные товара.' }
}
async function loadPageData() {
    cancelLoad()
    if (!props.active || !props.goodId) return
    const version = requestVersion
    const id = props.goodId
    controller = new AbortController()
    const options = { signal: controller.signal }
    pageLoading.value = true
    pageError.value = null
    try {
        const [good, currencyRows, measureRows, unitRows, industryRows] = await Promise.all([
            axios.get(route('good.fetch', id), options),
            axios.get(route('currencies.index'), options),
            axios.get(route('measures.index'), options),
            axios.get(route('units.index'), options),
            axios.get('/api/industries', { ...options, params: { per_page: 1000 } }),
        ])
        if (version !== requestVersion || String(id) !== String(props.goodId)) return
        applyGood(good.data)
        currencies.value = asItems(currencyRows.data)
        measures.value = asItems(measureRows.data)
        units.value = asItems(unitRows.data)
        industries.value = asItems(industryRows.data)
        loadedGoodId = String(id)
    } catch (failure) {
        if (version === requestVersion) pageError.value = failure.response?.data?.message || 'Не удалось загрузить данные товара.'
    } finally {
        if (version === requestVersion) { pageLoading.value = false; controller = null }
    }
}

function syncRecommendationClassificationForm() {
    recommendationIndustryIds.value = currentRecommendationIndustries.value.map((industry) => industry.id);
}

function handleCalculationSaved() {
    priceCalculationsRefreshKey.value += 1;
    changed();
}

function handleCalculationApplied() {
    priceValuesRefreshKey.value += 1;
    changed();
}

async function saveRecommendationClassifications() {
    if (!goodData.value?.id || savingRecommendationClassifications.value) return
    const id = goodData.value.id
    savingRecommendationClassifications.value = true
    pageError.value = null
    try {
        await axios.patch(`/api/goods/${id}`, { industry_ids: recommendationIndustryIds.value })
        if (String(id) !== String(props.goodId)) return
        const good = await fetchGood({ preserveDraft: false })
        if (good) emit('changed', good)
    } catch (failure) {
        if (String(id) === String(props.goodId)) pageError.value = failure.response?.data?.message || 'Не удалось сохранить ОКВЭДы.'
    } finally { savingRecommendationClassifications.value = false }
}

// --------------------------------------------------
// FORMS
// --------------------------------------------------
const formQuotation = reactive({ good_id: props.goodId, unit_id: null, price: null, measure_id: null, denominator: 1, errors: {}, processing: false })
const quotationError = ref('')
function resetQuotation() {
    Object.assign(formQuotation, { good_id: props.goodId, unit_id: null, price: null, measure_id: null, denominator: 1, errors: {} })
    quotationError.value = ''
}
async function storeQuotation() {
    if (formQuotation.processing || !props.goodId) return
    const id = props.goodId
    formQuotation.processing = true
    formQuotation.errors = {}
    quotationError.value = ''
    try {
        await axios.post(route('web.quotation.store'), {
            good_id: id, unit_id: formQuotation.unit_id, measure_id: formQuotation.measure_id,
            price: formQuotation.price === '' || formQuotation.price == null ? null : toNumber(formQuotation.price, null),
            denominator: Math.max(toNumber(formQuotation.denominator, 1), 0.0001),
        })
        if (String(id) !== String(props.goodId)) return
        resetQuotation()
        dialogFormQuotation.value = false
        await changed()
    } catch (failure) {
        if (String(id) !== String(props.goodId)) return
        formQuotation.errors = failure.response?.data?.errors || {}
        quotationError.value = failure.response?.data?.message || 'Не удалось сохранить предложение.'
    } finally { formQuotation.processing = false }
}

const normalizedIds = values => [...values].map(String).sort().join(',')
const recommendationDirty = computed(() => normalizedIds(recommendationIndustryIds.value) !== normalizedIds(currentRecommendationIndustries.value.map(item => item.id)))
const quotationDirty = computed(() => formQuotation.unit_id != null || formQuotation.price != null || formQuotation.measure_id != null || Number(formQuotation.denominator) !== 1)
const dirty = computed(() => recommendationDirty.value || quotationDirty.value)
const busy = computed(() => savingRecommendationClassifications.value || Boolean(formQuotation.processing))
const dirtyTab = computed(() => quotationDirty.value ? 'market' : recommendationDirty.value ? 'sales' : null)
const visitedTabs = ref(new Set())
watch(() => props.activeTab, tab => { if (operationTabs.includes(tab)) visitedTabs.value = new Set([...visitedTabs.value, tab]) }, { immediate: true })
watch([dirty, busy, dirtyTab], ([dirty, busy, dirtyTab]) => emit('state', { dirty, busy, dirtyTab }), { immediate: true })
function reset() {
    syncRecommendationClassificationForm()
    resetQuotation()
    dialogFormQuotation.value = false
    pageError.value = null
}
watch([() => props.goodId, () => props.active], ([id, active], previous = []) => {
    if (String(id) !== String(previous[0])) {
        cancelLoad()
        sourceVersion++
        loadedGoodId = null
        goodData.value = null
        reset()
        visitedTabs.value = new Set(operationTabs.includes(props.activeTab) ? [props.activeTab] : [])
    }
    if (active && String(id) !== loadedGoodId) loadPageData()
    else if (!active) cancelLoad()
}, { immediate: true })
onScopeDispose(() => { disposed = true; sourceVersion++; cancelLoad() })
defineExpose({ refresh, reset })
</script>

<template>
    <section class="catalog-good-operations">
        <!-- QUOTATION DIALOG -->
        <v-dialog
            v-model="dialogFormQuotation"
            :persistent="formQuotation.processing"
            width="771"
        >
            <v-card>
                <v-card-title>Новое предложение поставщика</v-card-title>

                <v-card-text>
                    <v-alert v-if="quotationError" type="error" variant="tonal" density="compact" class="mb-3">{{ quotationError }}</v-alert>
                    <v-form @submit.prevent="storeQuotation">
                        <v-container fluid>
                            <v-row>
                                <v-col cols="12">
                                    <v-autocomplete
                                        :items="units"
                                        item-value="id"
                                        item-title="name"
                                        v-model="formQuotation.unit_id"
                                        label="Поставщик"
                                        :error-messages="formQuotation.errors.unit_id"
                                        variant="solo"
                                        density="comfortable"
                                        base-color="yellow"
                                        clearable
                                    />
                                </v-col>
                            </v-row>

                            <v-row>
                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="formQuotation.price"
                                        label="Цена"
                                        :error-messages="formQuotation.errors.price"
                                        variant="solo"
                                        density="default"
                                        type="number"
                                    />
                                </v-col>

                                <v-col cols="12" md="4">
                                    <v-autocomplete
                                        :items="measures"
                                        item-value="id"
                                        item-title="name"
                                        v-model="formQuotation.measure_id"
                                        label="Единица измерения"
                                        :error-messages="formQuotation.errors.measure_id"
                                        variant="solo"
                                        density="compact"
                                        clearable
                                    />
                                </v-col>

                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="formQuotation.denominator"
                                        label="Количество в упаковке"
                                        :error-messages="formQuotation.errors.denominator"
                                        type="number"
                                        min="0.0001"
                                        step="0.0001"
                                        variant="solo"
                                        density="compact"
                                        hint="Например: цена дана за 25 кг"
                                        persistent-hint
                                    />
                                </v-col>
                            </v-row>
                        </v-container>
                    </v-form>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />

                    <v-btn
                        text="Отмена"
                        variant="text"
                        @click="dialogFormQuotation = false"
                    />

                    <v-btn
                        text="Сохранить"
                        @click="storeQuotation"
                        :loading="formQuotation.processing"
                        variant="elevated"
                        density="comfortable"
                        color="grey"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <div v-if="pageLoading && !goodData" class="text-center pa-8"><v-progress-circular indeterminate size="28" /></div>
        <v-alert v-if="pageError" type="error" variant="tonal" density="compact" class="mb-3">{{ pageError }}<v-btn v-if="!goodData" variant="text" size="small" @click="loadPageData">Повторить</v-btn></v-alert>
        <template v-if="goodData">
            <v-window :model-value="activeTab" :touch="false">
                <!-- MARKET -->
                <v-window-item value="market" :eager="visitedTabs.has('market')">
                    <v-card>
                        <v-card-title class="d-flex align-center justify-space-between flex-wrap ga-3">
                            <span>Предложения поставщиков</span>
                            <v-btn
                                prepend-icon="mdi-plus"
                                size="small"
                                variant="tonal"
                                @click="dialogFormQuotation = true"
                            >
                                Предложение поставщика
                            </v-btn>
                        </v-card-title>
                        <v-card-text class="pa-0">
                            <v-data-table
                                :items="goodData.quotations || []"
                                :headers="headerQuotations"
                                items-per-page="100"
                                fixed-header
                                height="560px"
                                density="compact"
                                class="border rounded"
                                hover
                            >
                                <template #item.denominator="{ item }">
                                    <span>{{ item.denominator || 1 }}</span>
                                </template>
                                <template #item.created_at="{ item }">
                                    <span>{{ formatDate(item.created_at) }}</span>
                                </template>
                                <template #item.price="{ item }">
                                    <span>{{ formatMoney(item.price) }}</span>
                                </template>
                                <template #no-data>
                                    <div class="pa-6 text-center text-medium-emphasis">Предложений поставщиков пока нет.</div>
                                </template>
                            </v-data-table>
                        </v-card-text>
                    </v-card>
                </v-window-item>

                <!-- PRICES -->
                <v-window-item value="prices" :eager="visitedTabs.has('prices')">
                    <v-row dense class="prices-workspace">
                        <v-col cols="12" xl="5">
                            <GoodPriceTypeValuesTab
                                :measurement="goodData.measurement"
                                :key="priceValuesRefreshKey"
                                class="prices-workspace__card"
                                :good-id="goodData.id"
                                :currencies="currencies"
                                :table-height="260"
                                :items-per-page="5"
                                :show-intro="false"
                            />
                        </v-col>

                        <v-col cols="12" xl="7">
                            <GoodQuotationCalculator
                                class="prices-workspace__card"
                                :good-id="goodData.id"
                                :quotations="goodData.quotations || []"
                                :purchases="goodData.purchases || []"
                                :measures="measures"
                                :currencies="currencies"
                                :default-vat-rate="defaultVatRate"
                                :default-box-weight-kg="goodBoxWeight"
                                currency-code="RUB"
                                compact
                                @saved="handleCalculationSaved"
                            />
                        </v-col>

                        <v-col cols="12">
                            <GoodPriceCalculationsTab
                                :measurement="goodData.measurement"
                                :key="priceCalculationsRefreshKey"
                                class="prices-workspace__card"
                                :good-id="goodData.id"
                                :table-height="360"
                                :show-intro="false"
                                @applied="handleCalculationApplied"
                            />
                        </v-col>
                    </v-row>
                </v-window-item>

                <!-- PRICE TYPES -->
                <v-window-item value="price-types" :eager="visitedTabs.has('price-types')">
                    <GoodPriceTypesTab :currencies="currencies" />
                </v-window-item>



                <v-window-item value="collections" :eager="visitedTabs.has('collections')">
                    <v-card>
                        <v-card-title class="d-flex align-center justify-space-between">
                            <span>Подборки товара</span>

                            <v-btn
                                color="#47765a"
                                rounded="lg"
                                density="compact"
                                variant="tonal"
                                prepend-icon="mdi-pencil"
                                @click="emit('request-basics')"
                            >
                                Редактировать
                            </v-btn>
                        </v-card-title>

                        <v-card-text>
                            <v-row dense>
                                <v-col
                                    v-for="field in currentFields"
                                    :key="field.id"
                                    cols="12"
                                    md="6"
                                    xl="4"
                                >
                                    <v-card class="field-card h-100">
                                        <v-card-text>
                                            <div class="d-flex align-start justify-space-between ga-3">
                                                <div class="min-width-0">
                                                    <div class="text-caption text-medium-emphasis">
                                                        Field
                                                    </div>

                                                    <div class="text-subtitle-1 font-weight-bold">
                                                        {{ field.title || field.name }}
                                                    </div>

                                                    <div class="text-caption text-medium-emphasis">
                                                        /подборки/{{ field.slug || field.id }}
                                                    </div>
                                                </div>

                                                <v-chip
                                                    size="small"
                                                    variant="tonal"
                                                    :color="field.is_published ? 'green' : 'grey'"
                                                >
                                                    {{ field.is_published ? "published" : "hidden" }}
                                                </v-chip>
                                            </div>

                                            <p v-if="field.description" class="text-body-2 mt-3 mb-0">
                                                {{ field.description }}
                                            </p>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>

                            <v-alert
                                v-if="!currentFields.length"
                                type="info"
                                variant="tonal"
                                density="compact"
                                class="mb-0"
                            >
                                Этот товар пока не входит ни в одну подборку.
                            </v-alert>
                        </v-card-text>
                    </v-card>
                </v-window-item>

                <!-- MEDIA -->
                <v-window-item value="media" :eager="visitedTabs.has('media')">
                    <GoodMediaTab
                        :good="goodData"
                        @changed="changed"
                        @ava-updated="changed"
                    />
                </v-window-item>

                <!-- SALES -->
                <v-window-item value="sales" :eager="visitedTabs.has('sales')">
                    <div class="catalog-good-operations__sales-header">
                        <div>
                            <h3 class="text-subtitle-1 font-weight-bold">Продажи и покупатели</h3>
                            <p class="text-body-2 text-medium-emphasis mb-0">История продаж и подбор покупателей по ОКВЭДам.</p>
                        </div>
                        <FindBuyersLauncher v-if="active && activeTab === 'sales'" source-type="good" :source-id="goodData.id" />
                    </div>
                    <v-card class="mb-4">
                        <v-card-title>История продаж</v-card-title>

                        <v-card-text class="pa-0">
                            <v-data-table
                                :items="goodData.sales || []"
                                :headers="headerSales"
                                items-per-page="100"
                                fixed-header
                                height="360px"
                                density="comfortable"
                                hover
                            >
                                <template #item.date="{ item }">
                                    <span class="text-xs">{{ formatDate(item.date) }}</span>
                                </template>

                                <template #item.pivot.price="{ item }">
                                    <span class="font-weight-bold">
                                        {{ formatMoney(item.pivot?.price) }}
                                    </span>
                                </template>
                            </v-data-table>
                        </v-card-text>
                    </v-card>

                    <v-card class="sales-recommendations">
                        <v-card-title class="d-flex align-center justify-space-between">
                            <span>ОКВЭДы для рекомендаций</span>

                            <v-chip size="small" color="#800000" variant="tonal">
                                {{ currentRecommendationIndustries.length }}
                            </v-chip>
                        </v-card-title>

                        <v-card-text>
                            <v-card variant="tonal" class="mb-4">
                                <v-card-text class="py-3">
                                    <v-row dense class="align-center">
                                        <v-col cols="12" md="9">
                                            <v-autocomplete
                                                v-model="recommendationIndustryIds"
                                                :items="industries"
                                                :item-title="industryTitle"
                                                item-value="id"
                                                label="ОКВЭДы покупателей"
                                                placeholder="Выберите ОКВЭДы"
                                                variant="outlined"
                                                density="compact"
                                                multiple
                                                chips
                                                closable-chips
                                                hide-details
                                            />
                                        </v-col>

                                        <v-col cols="12" md="3">
                                            <v-btn
                                                color="#800000"
                                                rounded="lg"
                                                block
                                                :loading="savingRecommendationClassifications"
                                                @click="saveRecommendationClassifications"
                                            >
                                                Сохранить ОКВЭДы
                                            </v-btn>
                                        </v-col>
                                    </v-row>
                                </v-card-text>
                            </v-card>

                            <v-row dense>
                                <v-col
                                    v-for="industry in recommendationCards"
                                    :key="industry.id"
                                    cols="12"
                                    md="6"
                                    xl="4"
                                >
                                    <v-card class="okved-card h-100">
                                        <v-card-text>
                                            <div class="d-flex align-start justify-space-between ga-3">
                                                <div class="min-width-0">
                                                    <div class="text-caption text-medium-emphasis">
                                                        ОКВЭД
                                                    </div>

                                                    <div class="text-h6 font-weight-bold">
                                                        {{ industry.code || "—" }}
                                                    </div>

                                                    <div class="text-body-2 text-medium-emphasis">
                                                        {{ industry.title || "Без названия" }}
                                                    </div>
                                                </div>

                                                <v-chip size="small" variant="tonal" color="#800000">
                                                    {{ industry.units.length + industry.entities.length }}
                                                </v-chip>
                                            </div>

                                            <v-divider class="my-3" />

                                            <div class="text-caption text-medium-emphasis mb-2">
                                                Кому будет рекомендоваться
                                            </div>

                                            <div
                                                v-if="industry.units.length || industry.entities.length"
                                                class="recommendation-targets"
                                            >
                                                <v-btn
                                                    v-for="unit in industry.units"
                                                    :key="`unit-${industry.id}-${unit.id}`"
                                                    :href="route('web.unit.show', unit.id)"
                                                    size="x-small"
                                                    variant="tonal"
                                                    color="blue-grey"
                                                    prepend-icon="mdi-domain"
                                                >
                                                    {{ unit.name || `Unit #${unit.id}` }}
                                                </v-btn>

                                                <v-btn
                                                    v-for="entity in industry.entities"
                                                    :key="`entity-${industry.id}-${entity.id}`"
                                                    :href="route('Ameise.entity.show', entity.id)"
                                                    size="x-small"
                                                    variant="text"
                                                    color="teal-darken-2"
                                                    prepend-icon="mdi-office-building"
                                                >
                                                    {{ entityTitle(entity) }}
                                                </v-btn>
                                            </div>

                                            <v-alert
                                                v-else
                                                type="info"
                                                variant="tonal"
                                                density="compact"
                                                class="mb-0"
                                            >
                                                Получатели по этому ОКВЭД пока не найдены.
                                            </v-alert>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>

                            <v-alert
                                v-if="!recommendationCards.length"
                                type="info"
                                variant="tonal"
                                density="compact"
                                class="mb-0"
                            >
                                ОКВЭДы для рекомендаций пока не присоединены.
                            </v-alert>
                        </v-card-text>
                    </v-card>
                </v-window-item>

            </v-window>
        </template>
    </section>
</template>

<style scoped>
.good-header-card {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.rounded-lg {
    border-radius: 8px;
}

.okved-card {
    border: 1px solid rgba(128, 0, 0, 0.16);
}

.field-card {
    border: 1px solid rgba(71, 118, 90, 0.22);
    background:
        linear-gradient(135deg, rgba(71, 118, 90, 0.08), transparent 58%),
        rgb(var(--v-theme-surface));
}

.recommendation-targets {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    max-height: 118px;
    overflow: auto;
}

.prices-workspace {
    --price-header: #dc7a51;
    --price-body: #47765a;
    --price-text: #f7ecd7;
    --price-grid: rgba(255, 255, 255, 0.9);
    align-items: stretch;
}

.prices-workspace__card {
    border: 1px solid rgba(71, 118, 90, 0.24);
    box-shadow: 0 10px 30px rgba(35, 55, 42, 0.08);
}

.prices-workspace :deep(.v-card-title) {
    min-height: 42px;
    padding-top: 8px;
    padding-bottom: 8px;
    font-size: 0.98rem;
}

.prices-workspace :deep(.v-card-text) {
    padding: 10px;
}

.prices-workspace :deep(.v-table) {
    border: 4px solid var(--price-grid);
    border-radius: 0;
    overflow: hidden;
}

.prices-workspace :deep(.v-table thead th) {
    background: var(--price-header) !important;
    color: #2f3129 !important;
    font-weight: 800 !important;
    border: 3px solid var(--price-grid);
}

.prices-workspace :deep(.v-table tbody td) {
    background: var(--price-body);
    color: var(--price-text);
    border: 3px solid var(--price-grid);
    height: 30px !important;
}

.prices-workspace :deep(.v-table tbody tr:hover td) {
    background: #3f6d52 !important;
}

.prices-workspace :deep(.v-data-table-footer) {
    padding: 6px 10px;
}

.catalog-good-operations { min-width: 0; }
.catalog-good-operations__sales-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.catalog-good-operations :deep(.v-card-title) { white-space: normal; }
.catalog-good-operations :deep(.v-data-table-footer) { flex-wrap: wrap; }
.catalog-good-operations :deep(.v-window) { overflow: visible; }
</style>
