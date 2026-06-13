<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import { ref, onMounted, computed, reactive } from "vue";
import axios from "axios";
import { useHead } from "@vueuse/head";
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

const activeTab = ref("overview");

const goodData = ref(null);
const currencies = ref([]);
const measures = ref([]);
const units = ref([]);
const industries = ref([]);
const vatRates = ref([]);
const savingRecommendationClassifications = ref(false);

const dialogFormQuotation = ref(false);
const recommendationIndustryIds = ref([]);
const savingGood = ref(false);
const deletingGood = ref(false);
const deleteGoodDialog = ref(false);
const goodFormErrors = ref({});
const goodFormMessage = ref("");
const avatarFile = ref(null);
const priceCalculationsRefreshKey = ref(0);
const priceValuesRefreshKey = ref(0);

const goodForm = reactive({
    name: "",
    slug: "",
    denominator: null,
    description: "",
    vat_rate_id: null,
    is_published: false,
    remove_ava: false,
});

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

function formatDateTime(value) {
    if (!value) return "-";

    try {
        return new Intl.DateTimeFormat("ru-RU", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        }).format(new Date(value));
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

function vatRateTitle(item) {
    return item ? `${item.title} / ${item.rate}%` : "";
}

// --------------------------------------------------
// API
// --------------------------------------------------
async function fetchGood() {
    const response = await axios.get(route("good.fetch", props.good.id));

    goodData.value = response.data;
    syncRecommendationClassificationForm();
    syncGoodForm();
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

async function fetchVatRates() {
    const response = await axios.get("/api/vat-rates");

    vatRates.value = Array.isArray(response.data)
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
            fetchVatRates(),
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

function syncGoodForm() {
    const good = goodData.value;

    if (!good) {
        return;
    }

    goodForm.name = good.name || "";
    goodForm.slug = good.slug || "";
    goodForm.denominator = good.denominator ?? null;
    goodForm.description = good.description || "";
    goodForm.vat_rate_id = good.vat_rate_id || null;
    goodForm.is_published = !!good.is_published;
    goodForm.remove_ava = false;
    avatarFile.value = null;
    goodFormErrors.value = {};
    goodFormMessage.value = "";
}

async function saveGood() {
    if (!goodData.value?.id) {
        return;
    }

    savingGood.value = true;
    goodFormErrors.value = {};
    goodFormMessage.value = "";

    const payload = new FormData();
    payload.append("_method", "PATCH");
    payload.append("name", goodForm.name);
    payload.append("slug", goodForm.slug || "");
    payload.append("denominator", goodForm.denominator ?? "");
    payload.append("description", goodForm.description || "");
    payload.append("vat_rate_id", goodForm.vat_rate_id ?? "");
    payload.append("is_published", goodForm.is_published ? "1" : "0");
    payload.append("remove_ava", goodForm.remove_ava ? "1" : "0");

    const file = Array.isArray(avatarFile.value)
        ? avatarFile.value[0]
        : avatarFile.value;

    if (file) {
        payload.append("ava_image", file);
    }

    try {
        await axios.post(`/api/goods/${goodData.value.id}`, payload, {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });

        await fetchGood();
        goodFormMessage.value = "Товар сохранён";
    } catch (error) {
        goodFormErrors.value = error?.response?.data?.errors || {};
        goodFormMessage.value = error?.response?.data?.message || "Ошибка сохранения товара";
    } finally {
        savingGood.value = false;
    }
}

async function deleteGood() {
    if (!goodData.value?.id) {
        return;
    }

    deletingGood.value = true;

    try {
        await axios.delete(`/api/goods/${goodData.value.id}`);
        window.location.href = route("Ameise.goods");
    } catch (error) {
        goodFormMessage.value = error?.response?.data?.message || "Ошибка удаления товара";
    } finally {
        deletingGood.value = false;
        deleteGoodDialog.value = false;
    }
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
                <div class="d-flex align-center justify-end ga-2">
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

        <v-dialog
            v-model="deleteGoodDialog"
            width="520"
        >
            <v-card>
                <v-card-title>Удалить товар</v-card-title>

                <v-card-text>
                    Удалить <strong>{{ goodData?.name }}</strong>? Это действие удалит сам good и связанные каскадные данные.
                </v-card-text>

                <v-card-actions>
                    <v-spacer />

                    <v-btn
                        text="Отмена"
                        variant="text"
                        @click="deleteGoodDialog = false"
                    />

                    <v-btn
                        text="Удалить"
                        color="red"
                        variant="tonal"
                        :loading="deletingGood"
                        @click="deleteGood"
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
                    <v-tab value="overview">Обзор</v-tab>
                    <v-tab value="quotations">Quotations</v-tab>
                    <v-tab value="purchases">Закупки</v-tab>
                    <v-tab value="prices">Цены</v-tab>
                    <v-tab value="price-types">Виды цен</v-tab>
                    <v-tab value="recommendations">ОКВЭД-рекомендации</v-tab>
                    <v-tab value="media">Media</v-tab>
                    <v-tab value="seo">SEO</v-tab>
                    <v-tab value="sales">Продажи</v-tab>
                </v-tabs>
            </v-card>

            <v-window v-model="activeTab">
                <!-- OVERVIEW -->
                <v-window-item value="overview">
                    <v-row>
                        <v-col cols="12" lg="3">
                            <v-card class="mb-4">
                                <v-card-title class="text-wrap">
                                    Изображения
                                </v-card-title>

                                <v-card-text>
                                    <v-img
                                        :src="goodData.ava_thumb || '/default-image.jpg'"
                                        cover
                                        class="mb-3 rounded-lg"
                                        max-height="120"
                                    />

                                    <v-img
                                        :src="goodData.ava_image || '/default-image.jpg'"
                                        :alt="goodData.name"
                                        lazy-src="/placeholder.jpg"
                                        aspect-ratio="1"
                                        cover
                                        class="mb-4 rounded-lg"
                                    />

                                    <p class="mb-0">
                                        {{ goodData.description || "Описание отсутствует" }}
                                    </p>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" lg="5">
                            <v-card class="mb-4">
                                <v-card-title class="d-flex align-center justify-space-between">
                                    <span>Редактировать good</span>

                                    <v-chip
                                        size="small"
                                        variant="tonal"
                                        :color="goodForm.is_published ? 'green' : 'grey'"
                                    >
                                        {{ goodForm.is_published ? "published" : "hidden" }}
                                    </v-chip>
                                </v-card-title>

                                <v-card-text>
                                    <v-alert
                                        v-if="goodFormMessage"
                                        :type="Object.keys(goodFormErrors).length ? 'error' : 'success'"
                                        variant="tonal"
                                        class="mb-4"
                                    >
                                        {{ goodFormMessage }}
                                    </v-alert>

                                    <v-form @submit.prevent="saveGood">
                                        <v-row dense>
                                            <v-col cols="12">
                                                <v-text-field
                                                    v-model="goodForm.name"
                                                    label="Название"
                                                    variant="outlined"
                                                    density="compact"
                                                    :error-messages="goodFormErrors.name"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="7">
                                                <v-text-field
                                                    v-model="goodForm.slug"
                                                    label="Slug"
                                                    variant="outlined"
                                                    density="compact"
                                                    hint="Если пусто, будет сгенерирован из названия"
                                                    persistent-hint
                                                    :error-messages="goodFormErrors.slug"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="5">
                                                <v-text-field
                                                    v-model="goodForm.denominator"
                                                    label="Denominator / упаковка"
                                                    type="number"
                                                    step="0.001"
                                                    variant="outlined"
                                                    density="compact"
                                                    :error-messages="goodFormErrors.denominator"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="7">
                                                <v-select
                                                    v-model="goodForm.vat_rate_id"
                                                    :items="vatRates"
                                                    :item-title="vatRateTitle"
                                                    item-value="id"
                                                    label="НДС"
                                                    variant="outlined"
                                                    density="compact"
                                                    clearable
                                                    :error-messages="goodFormErrors.vat_rate_id"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="5">
                                                <v-switch
                                                    v-model="goodForm.is_published"
                                                    label="Публиковать"
                                                    color="green"
                                                    inset
                                                    hide-details
                                                />
                                            </v-col>

                                            <v-col cols="12">
                                                <v-textarea
                                                    v-model="goodForm.description"
                                                    label="Описание"
                                                    rows="5"
                                                    variant="outlined"
                                                    density="compact"
                                                    :error-messages="goodFormErrors.description"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="8">
                                                <v-file-input
                                                    v-model="avatarFile"
                                                    label="Заменить ava_image"
                                                    accept="image/png,image/jpeg,image/webp"
                                                    variant="outlined"
                                                    density="compact"
                                                    prepend-icon="mdi-image"
                                                    :error-messages="goodFormErrors.ava_image"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-checkbox
                                                    v-model="goodForm.remove_ava"
                                                    label="Удалить аватар"
                                                    color="red"
                                                    density="compact"
                                                    hide-details
                                                />
                                            </v-col>
                                        </v-row>
                                    </v-form>
                                </v-card-text>

                                <v-card-actions>
                                    <v-btn
                                        color="red"
                                        variant="text"
                                        @click="deleteGoodDialog = true"
                                    >
                                        Удалить
                                    </v-btn>

                                    <v-spacer />

                                    <v-btn
                                        variant="text"
                                        @click="syncGoodForm"
                                    >
                                        Сбросить
                                    </v-btn>

                                    <v-btn
                                        color="#47765a"
                                        variant="flat"
                                        :loading="savingGood"
                                        @click="saveGood"
                                    >
                                        Сохранить
                                    </v-btn>
                                </v-card-actions>
                            </v-card>

                            <v-card>
                                <v-card-title>Products</v-card-title>

                                <v-card-text>
                                    <template v-if="(goodData.products || []).length">
                                        <v-chip
                                            v-for="product in goodData.products || []"
                                            :key="product.id"
                                            class="ma-1"
                                            size="small"
                                            variant="tonal"
                                            color="teal"
                                        >
                                            {{ product.rus || product.name || product.id }}
                                        </v-chip>
                                    </template>

                                    <v-alert
                                        v-else
                                        type="info"
                                        variant="tonal"
                                    >
                                        Products пока не привязаны.
                                    </v-alert>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" lg="4">
                            <v-card class="mb-4">
                                <v-card-title>Системные поля</v-card-title>

                                <v-card-text>
                                    <v-list density="compact">
                                        <v-list-item title="ID" :subtitle="String(goodData.id)" />
                                        <v-list-item title="Slug сейчас" :subtitle="goodData.slug || '—'" />
                                        <v-list-item title="created_at" :subtitle="formatDateTime(goodData.created_at)" />
                                        <v-list-item title="updated_at" :subtitle="formatDateTime(goodData.updated_at)" />
                                        <v-list-item title="ava_image" :subtitle="goodData.ava_image || '—'" />
                                        <v-list-item title="ava_thumb" :subtitle="goodData.ava_thumb || '—'" />
                                    </v-list>
                                </v-card-text>
                            </v-card>

                            <v-card>
                                <v-card-title>Быстрая статистика</v-card-title>

                                <v-card-text>
                                    <v-row dense>
                                        <v-col cols="6">
                                            <v-card variant="tonal" class="pa-3">
                                                <div class="text-caption text-medium-emphasis">
                                                    Цены
                                                </div>

                                                <div class="text-h6">
                                                    {{ (goodData.price_type_values || goodData.priceTypeValues || []).length }}
                                                </div>
                                            </v-card>
                                        </v-col>

                                        <v-col cols="6">
                                            <v-card variant="tonal" class="pa-3">
                                                <div class="text-caption text-medium-emphasis">
                                                    Продажи
                                                </div>

                                                <div class="text-h6">
                                                    {{ (goodData.sales || []).length }}
                                                </div>
                                            </v-card>
                                        </v-col>

                                        <v-col cols="6">
                                            <v-card variant="tonal" class="pa-3">
                                                <div class="text-caption text-medium-emphasis">
                                                    Закупки
                                                </div>

                                                <div class="text-h6">
                                                    {{ (goodData.purchases || []).length }}
                                                </div>
                                            </v-card>
                                        </v-col>

                                        <v-col cols="6">
                                            <v-card variant="tonal" class="pa-3">
                                                <div class="text-caption text-medium-emphasis">
                                                    Media
                                                </div>

                                                <div class="text-h6">
                                                    {{ (goodData.media || []).length }}
                                                </div>
                                            </v-card>
                                        </v-col>
                                    </v-row>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-window-item>

                <!-- QUOTATIONS -->
                <v-window-item value="quotations">
                    <v-card>
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
                                height="620px"
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
                </v-window-item>

                <!-- PURCHASES -->
                <v-window-item value="purchases">
                    <v-card>
                        <v-card-title>Закупки данного товара</v-card-title>

                        <v-card-text>
                            <v-alert
                                type="info"
                                variant="tonal"
                                class="mb-4"
                            >
                                Здесь показываются закупки, связанные с этим good через pivot-таблицу
                                <strong>good_purchase</strong>.
                                На следующем этапе добавим автоматический расчёт продажной цены от закупки.
                            </v-alert>

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
                </v-window-item>

                <!-- PRICES -->
                <v-window-item value="prices">
                    <v-row dense class="prices-workspace">
                        <v-col cols="12" xl="5">
                            <GoodQuotationCalculator
                                class="prices-workspace__card"
                                :good-id="goodData.id"
                                :quotations="goodData.quotations || []"
                                :default-vat-rate="defaultVatRate"
                                :default-box-weight-kg="goodBoxWeight"
                                currency-code="RUB"
                                @saved="handleCalculationSaved"
                            />
                        </v-col>

                        <v-col cols="12" xl="7">
                            <GoodPriceCalculationsTab
                                :key="priceCalculationsRefreshKey"
                                class="prices-workspace__card"
                                :good-id="goodData.id"
                                @applied="handleCalculationApplied"
                            />
                        </v-col>

                        <v-col cols="12">
                            <GoodPriceTypeValuesTab
                                :key="priceValuesRefreshKey"
                                class="prices-workspace__card"
                                :good-id="goodData.id"
                                :currencies="currencies"
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
                            <v-alert type="info" variant="tonal" class="mb-4">
                                Товар будет рекомендоваться пользователям по ОКВЭДам из таблицы industries, связанным с их Unit.
                            </v-alert>

                            <v-autocomplete
                                v-model="recommendationIndustryIds"
                                :items="industries"
                                :item-title="industryTitle"
                                item-value="id"
                                label="ОКВЭДы / industries"
                                placeholder="Выберите ОКВЭДы"
                                variant="outlined"
                                density="comfortable"
                                multiple
                                chips
                                closable-chips
                            />

                            <div class="d-flex justify-end">
                                <v-btn
                                    color="#800000"
                                    rounded="lg"
                                    :loading="savingRecommendationClassifications"
                                    @click="saveRecommendationClassifications"
                                >
                                    Сохранить ОКВЭДы
                                </v-btn>
                            </div>
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
                    <GoodSeoTab :good="goodData" />
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

.prices-workspace {
    --price-header: #dc7a51;
    --price-body: #47765a;
    --price-text: #f7ecd7;
    --price-grid: rgba(255, 255, 255, 0.9);
}

.prices-workspace__card {
    border: 1px solid rgba(71, 118, 90, 0.24);
    box-shadow: 0 10px 30px rgba(35, 55, 42, 0.08);
}

.prices-workspace :deep(.v-card-title) {
    min-height: 48px;
    padding-top: 10px;
    padding-bottom: 10px;
}

.prices-workspace :deep(.v-card-text) {
    padding: 12px;
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
