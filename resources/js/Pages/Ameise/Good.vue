<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { useHead } from "@unhead/vue";
import { route } from "ziggy-js";
import { useDate } from "vuetify";
import { useForm } from "@inertiajs/vue3";
import { usePage } from "@inertiajs/vue3";

import GoodQuotationCalculator from "@/Components/GoodQuotationCalculator.vue";
import GoodSeoTab from "@/Components/Goods/GoodSeoTab.vue";
import GoodPriceCalculationsTab from "@/Components/Goods/GoodPriceCalculationsTab.vue";
import GoodPriceTypesTab from "@/Components/Goods/GoodPriceTypesTab.vue";
import GoodPriceTypeValuesTab from "@/Components/Goods/GoodPriceTypeValuesTab.vue";
import GoodMediaTab from "@/Components/Goods/GoodMediaTab.vue";
import CatalogGoodRecordDialog from "@/Components/Catalog/CatalogGoodRecordDialog.vue";
import FindBuyersLauncher from "@/Components/AiSales/FindBuyersLauncher.vue";

defineOptions({
    layout: VerwalterLayout,
});

const props = defineProps({
    good: {
        type: Object,
        required: true,
    },
});

const date = useDate();

const page = usePage();

const currentUrl = computed(() => {
    const ziggyLocation = page.props.ziggy?.location;

    if (ziggyLocation) {
        return String(ziggyLocation);
    }

    const ziggyUrl = page.props.ziggy?.url;

    if (ziggyUrl) {
        return String(ziggyUrl);
    }

    return "https://пищепром-сервер.рф";
});

// --------------------------------------------------
// STATE
// --------------------------------------------------
const pageLoading = ref(true);
const pageError = ref(null);

const supportedTabs = ["quotations", "prices", "price-types", "recommendations", "collections", "media", "seo", "sales"];
function initialTab() {
    try {
        const tab = new URL(page.url || currentUrl.value, currentUrl.value).searchParams.get("tab");
        return supportedTabs.includes(tab) ? tab : "quotations";
    } catch { return "quotations"; }
}
const activeTab = ref(initialTab());
const recordOpen = ref(false);

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
        title: "Entity",
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
        title: "Unit",
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
        title: "Measure",
        align: "start",
        sortable: true,
    },
    {
        key: "denominator",
        title: "Делитель",
        align: "start",
        sortable: true,
    },
];

const headerPurchases = [
    {
        key: "date",
        title: "Дата",
        sortable: true,
        width: "120px",
    },
    {
        key: "entity.name",
        title: "Поставщик / Entity",
        sortable: true,
    },
    {
        key: "pivot.quantity",
        title: "Кол-во",
        sortable: true,
        width: "110px",
    },
    {
        key: "pivot.price",
        title: "Цена",
        sortable: true,
        width: "130px",
    },
    {
        key: "pivot.total",
        title: "Сумма",
        sortable: true,
        width: "130px",
    },
];

// --------------------------------------------------
// HELPERS
// --------------------------------------------------
function toNumber(value, fallback = 0) {
    const number = Number(value);

    return Number.isFinite(number) ? number : fallback;
}

function safeText(value, fallback = "") {
    return typeof value === "string" ? value : fallback;
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

function currencyCodeById(id) {
    if (!id) return "RUB";

    return currencies.value.find((currency) => Number(currency.id) === Number(id))?.code || "RUB";
}

function purchaseCurrencyCode(item) {
    return currencyCodeById(item?.pivot?.currency_id);
}

function entityTitle(entity) {
    return entity?.name || entity?.full_name || entity?.short_name || `Entity #${entity?.id}`;
}

// --------------------------------------------------
// API
// --------------------------------------------------
async function fetchGood() {
    const response = await axios.get(route("good.fetch", props.good.id));

    goodData.value = response.data;
    syncRecommendationClassificationForm();
}

async function refreshAfterRecord() {
    pageError.value = null;
    try { await fetchGood(); }
    catch (error) {
        if (error.response?.status === 404) window.location.href = route("Ameise.products");
        else pageError.value = error.response?.data?.message || "Не удалось обновить товар";
    }
}

async function fetchCurrencies() {
    const response = await axios.get(route("currencies.index"));

    currencies.value = Array.isArray(response.data)
        ? response.data
        : response.data.data || [];
}

async function fetchMeasures() {
    const response = await axios.get(route("measures.index"));

    measures.value = Array.isArray(response.data)
        ? response.data
        : response.data.data || [];
}

async function fetchUnits() {
    const response = await axios.get(route("units.index"));

    units.value = Array.isArray(response.data)
        ? response.data
        : response.data.data || [];
}

function industryTitle(industry) {
    return [industry.code, industry.title].filter(Boolean).join(" — ");
}

async function fetchIndustries() {
    const response = await axios.get("/api/industries", {
        params: {
            per_page: 1000,
        },
    });

    industries.value = Array.isArray(response.data)
        ? response.data
        : response.data.data || [];
}

async function loadPageData() {
    pageLoading.value = true;
    pageError.value = null;

    try {
        await Promise.all([
            fetchGood(),
            fetchCurrencies(),
            fetchMeasures(),
            fetchUnits(),
            fetchIndustries(),
        ]);
    } catch (error) {
        console.error(error);

        pageError.value =
            error?.response?.data?.message ||
            error?.message ||
            "Ошибка загрузки данных";
    } finally {
        pageLoading.value = false;
    }
}

function syncRecommendationClassificationForm() {
    recommendationIndustryIds.value = currentRecommendationIndustries.value.map((industry) => industry.id);
}

function handleCalculationSaved() {
    priceCalculationsRefreshKey.value += 1;
}

function handleCalculationApplied() {
    priceValuesRefreshKey.value += 1;
}

async function saveRecommendationClassifications() {
    if (!goodData.value?.id) {
        return;
    }

    savingRecommendationClassifications.value = true;

    try {
        await axios.patch(`/api/goods/${goodData.value.id}`, {
            industry_ids: recommendationIndustryIds.value,
        });

        await fetchGood();
    } catch (error) {
        console.error(error);
    } finally {
        savingRecommendationClassifications.value = false;
    }
}

// --------------------------------------------------
// FORMS
// --------------------------------------------------
const formQuotation = useForm({
    good_id: props.good.id,
    unit_id: null,
    price: null,
    measure_id: null,
    denominator: 1,
});

function storeQuotation() {
    formQuotation
        .transform((data) => ({
            ...data,
            price: toNumber(data.price, null),
            denominator: Math.max(toNumber(data.denominator, 1), 0.0001),
        }))
        .post(route("web.quotation.store"), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: async () => {
                formQuotation.reset();
                formQuotation.good_id = props.good.id;
                formQuotation.denominator = 1;
                dialogFormQuotation.value = false;
                await fetchGood();
            },
            onError: (errors) => {
                console.error("storeQuotation errors:", errors);
            },
        });
}

// --------------------------------------------------
// SEO HEAD FOR ADMIN PAGE
// --------------------------------------------------
const seoTitle = computed(() => {
    return goodData.value?.name
        ? `${goodData.value.name} - Ameise`
        : "Good - Ameise";
});

const seoDescription = computed(() => {
    const description = safeText(
        goodData.value?.description,
        "Страница товара в админке"
    );

    return description.slice(0, 160);
});

useHead({
    title: seoTitle,
    meta: [
        {
            name: "description",
            content: seoDescription,
        },
        {
            name: "keywords",
            content: computed(() =>
                goodData.value?.name
                    ? `${goodData.value.name}, товар, pischeprom`
                    : "товар"
            ),
        },
        {
            property: "og:title",
            content: computed(() => goodData.value?.name || "Good"),
        },
        {
            property: "og:description",
            content: seoDescription,
        },
        {
            property: "og:image",
            content: computed(() => goodData.value?.ava_image || "/default-image.jpg"),
        },
        {
            property: "og:url",
            content: currentUrl,
        }
    ],
});

// --------------------------------------------------
// LIFECYCLE
// --------------------------------------------------
onMounted(() => {
    loadPageData();
});
</script>

<template>
    <v-container fluid>
        <CatalogGoodRecordDialog v-model="recordOpen" :good-id="good.id" @saved="refreshAfterRecord" @deleted="refreshAfterRecord" />
        <!-- ACTIONS -->
        <v-row class="mb-3 align-center">
            <v-col cols="12" sm="2">
                <v-btn
                    text="+ Q"
                    @click="dialogFormQuotation = !dialogFormQuotation"
                    variant="elevated"
                    density="compact"
                    color="indigo"
                    block
                />
            </v-col>

            <v-col cols="12" sm="10" v-if="goodData">
                <div class="d-flex align-center justify-end flex-wrap ga-2">
                    <v-btn prepend-icon="mdi-card-text-outline" variant="tonal" density="compact" @click="recordOpen = true">Карточка записи</v-btn>
                    <FindBuyersLauncher source-type="good" :source-id="goodData.id" />
                    <v-chip
                        size="small"
                        variant="tonal"
                        color="deep-purple"
                    >
                        ID: {{ goodData.id }}
                    </v-chip>

                    <v-chip
                        size="small"
                        variant="tonal"
                        :color="goodData.is_published ? 'green' : 'grey'"
                    >
                        {{ goodData.is_published ? "published" : "hidden" }}
                    </v-chip>

                    <v-chip
                        v-if="currentVatRate"
                        size="small"
                        variant="tonal"
                        color="blue-grey"
                    >
                        НДС: {{ currentVatRate.title }} / {{ currentVatRate.rate }}%
                    </v-chip>
                </div>
            </v-col>
        </v-row>

        <!-- QUOTATION DIALOG -->
        <v-dialog
            v-model="dialogFormQuotation"
            width="771"
        >
            <v-card>
                <v-card-title>Form Quotation</v-card-title>

                <v-card-text>
                    <v-form @submit.prevent="storeQuotation">
                        <v-container fluid>
                            <v-row>
                                <v-col cols="12">
                                    <v-autocomplete
                                        :items="units"
                                        item-value="id"
                                        item-title="name"
                                        v-model="formQuotation.unit_id"
                                        label="Unit"
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
                                        label="Measure"
                                        variant="solo"
                                        density="compact"
                                        clearable
                                    />
                                </v-col>

                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="formQuotation.denominator"
                                        label="Делитель quotation"
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
                        text="Cancel"
                        variant="text"
                        @click="dialogFormQuotation = false"
                    />

                    <v-btn
                        text="Store"
                        @click="storeQuotation"
                        variant="elevated"
                        density="comfortable"
                        color="grey"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- LOADING -->
        <v-row v-if="pageLoading">
            <v-col cols="12" class="text-center py-10">
                <v-progress-circular
                    indeterminate
                    color="primary"
                />
            </v-col>
        </v-row>

        <!-- ERROR -->
        <v-row v-else-if="pageError">
            <v-col cols="12">
                <v-alert
                    type="error"
                    variant="tonal"
                >
                    {{ pageError }}
                </v-alert>
            </v-col>
        </v-row>

        <!-- CONTENT -->
        <template v-else-if="goodData">
            <!-- HEADER CARD -->
            <v-card class="mb-4 good-header-card">
                <v-card-text>
                    <v-row class="align-center">
                        <v-col cols="12" md="8">
                            <div class="text-caption text-medium-emphasis mb-1">
                                Good / товар
                            </div>

                            <h1 class="text-h5 font-weight-bold mb-1">
                                {{ goodData.name }}
                            </h1>

                            <div class="text-caption text-medium-emphasis">
                                slug: {{ goodData.slug || "—" }}
                            </div>
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-row dense>
                                <v-col cols="6">
                                    <v-card variant="tonal" class="pa-3">
                                        <div class="text-caption text-medium-emphasis">
                                            Упаковка / denominator
                                        </div>

                                        <div class="text-subtitle-1 font-weight-bold">
                                            {{ goodData.denominator || "—" }}
                                        </div>
                                    </v-card>
                                </v-col>

                                <v-col cols="6">
                                    <v-card variant="tonal" class="pa-3">
                                        <div class="text-caption text-medium-emphasis">
                                            Quotation
                                        </div>

                                        <div class="text-subtitle-1 font-weight-bold">
                                            {{ (goodData.quotations || []).length }}
                                        </div>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>

            <!-- TABS -->
            <v-card class="mb-4">
                <v-tabs
                    v-model="activeTab"
                    density="compact"
                    color="deep-purple-darken-1"
                    show-arrows
                >
                    <v-tab value="quotations">Quotations / Закупки</v-tab>
                    <v-tab value="prices">Цены</v-tab>
                    <v-tab value="price-types">Виды цен</v-tab>
                    <v-tab value="recommendations">ОКВЭД-рекомендации</v-tab>
                    <v-tab value="collections">Подборки</v-tab>
                    <v-tab value="media">Media</v-tab>
                    <v-tab value="seo">SEO</v-tab>
                    <v-tab value="sales">Продажи</v-tab>
                </v-tabs>
            </v-card>

            <v-window v-model="activeTab">
                <!-- QUOTATIONS / PURCHASES -->
                <v-window-item value="quotations">
                    <v-row dense>
                        <v-col cols="12" xl="6">
                            <v-card class="h-100">
                                <v-card-title class="d-flex align-center justify-space-between">
                                    <span>Quotations</span>

                                    <v-btn
                                        text="+ Q"
                                        color="indigo"
                                        variant="tonal"
                                        density="compact"
                                        @click="dialogFormQuotation = true"
                                    />
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
                                    </v-data-table>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" xl="6">
                            <v-card class="h-100">
                                <v-card-title>Закупки данного товара</v-card-title>

                                <v-card-text class="pa-0">
                                    <v-data-table
                                        :items="goodData.purchases || []"
                                        :headers="headerPurchases"
                                        items-per-page="50"
                                        fixed-header
                                        height="560px"
                                        density="compact"
                                        class="border rounded"
                                        hover
                                    >
                                        <template #item.date="{ item }">
                                            {{ item.date || "-" }}
                                        </template>

                                        <template #item.pivot.quantity="{ item }">
                                            {{ item.pivot?.quantity || "—" }}
                                        </template>

                                        <template #item.pivot.price="{ item }">
                                            <strong>{{ formatMoney(item.pivot?.price) }}</strong>

                                            <span class="text-caption ml-1">
                                                {{ purchaseCurrencyCode(item) }}
                                            </span>
                                        </template>

                                        <template #item.pivot.total="{ item }">
                                            <strong>{{ formatMoney(item.pivot?.total) }}</strong>

                                            <span class="text-caption ml-1">
                                                {{ purchaseCurrencyCode(item) }}
                                            </span>
                                        </template>

                                        <template #no-data>
                                            <div class="pa-6 text-center text-medium-emphasis">
                                                Закупок по этому товару пока нет.
                                            </div>
                                        </template>
                                    </v-data-table>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-window-item>

                <!-- PRICES -->
                <v-window-item value="prices">
                    <v-row dense class="prices-workspace">
                        <v-col cols="12" xl="5">
                            <GoodPriceTypeValuesTab
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
                <v-window-item value="price-types">
                    <GoodPriceTypesTab :currencies="currencies" />
                </v-window-item>

                <v-window-item value="recommendations">
                    <v-card>
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
                                                label="ОКВЭДы / industries"
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

                <v-window-item value="collections">
                    <v-card>
                        <v-card-title class="d-flex align-center justify-space-between">
                            <span>Подборки товара</span>

                            <v-btn
                                color="#47765a"
                                rounded="lg"
                                density="compact"
                                variant="tonal"
                                prepend-icon="mdi-pencil"
                                @click="recordOpen = true"
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
                <v-window-item value="media">
                    <GoodMediaTab
                        :good="goodData"
                        @changed="fetchGood"
                        @ava-updated="fetchGood"
                    />
                </v-window-item>

                <!-- SEO -->
                <v-window-item value="seo">
                    <GoodSeoTab :key="goodData.id" :good="goodData" />
                </v-window-item>

                <!-- SALES -->
                <v-window-item value="sales">
                    <v-card>
                        <v-card-title>Продажи</v-card-title>

                        <v-card-text class="pa-0">
                            <v-data-table
                                :items="goodData.sales || []"
                                :headers="headerSales"
                                items-per-page="100"
                                fixed-header
                                height="620px"
                                density="comfortable"
                                hover
                            >
                                <template #item.date="{ item }">
                                    <span class="text-xs">
                                        {{ item.date }}
                                    </span>
                                </template>

                                <template #item.pivot.price="{ item }">
                                    <span class="font-weight-bold">
                                        {{ formatMoney(item.pivot?.price) }}
                                    </span>
                                </template>
                            </v-data-table>
                        </v-card-text>
                    </v-card>
                </v-window-item>

            </v-window>
        </template>
    </v-container>
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

:deep(.v-window) {
    overflow: visible;
}
</style>
