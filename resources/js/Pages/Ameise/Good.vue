<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { useHead } from "@vueuse/head";
import { route } from "ziggy-js";
import { useDate } from "vuetify";
import { useForm } from "@inertiajs/vue3";

import GoodQuotationCalculator from "@/Components/GoodQuotationCalculator.vue";
import GoodSeoTab from "@/Components/Goods/GoodSeoTab.vue";
import GoodPriceCalculationsTab from "@/Components/Goods/GoodPriceCalculationsTab.vue";

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

const showFormAddPrice = ref(false);
const dialogFormQuotation = ref(false);

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

// --------------------------------------------------
// API
// --------------------------------------------------
async function fetchGood() {
    const response = await axios.get(route("good.fetch", props.good.id));

    goodData.value = response.data;
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

async function loadPageData() {
    pageLoading.value = true;
    pageError.value = null;

    try {
        await Promise.all([
            fetchGood(),
            fetchCurrencies(),
            fetchMeasures(),
            fetchUnits(),
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

// --------------------------------------------------
// FORMS
// --------------------------------------------------
const formAddPrice = useForm({
    good_id: props.good.id,
    price: null,
    currency_id: null,
});

function storePrice() {
    formAddPrice
        .transform((data) => ({
            ...data,
            price: toNumber(data.price, null),
            currency_id: data.currency_id,
        }))
        .post(route("web.price.store"), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: async () => {
                formAddPrice.reset();
                formAddPrice.good_id = props.good.id;
                showFormAddPrice.value = false;
                await fetchGood();
            },
            onError: (errors) => {
                console.error("storePrice errors:", errors);
            },
        });
}

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
            content: window.location.href,
        },
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
                    text="+ 💵"
                    @click="showFormAddPrice = !showFormAddPrice"
                    variant="elevated"
                    density="compact"
                    color="pink-darken-4"
                    block
                />
            </v-col>

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

            <v-col cols="12" sm="8" v-if="goodData">
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
                    <v-tab value="calculator">Расчёт цены</v-tab>
                    <v-tab value="calculations">Сохранённые расчёты</v-tab>
                    <v-tab value="price-types">Виды цен</v-tab>
                    <v-tab value="media">Media</v-tab>
                    <v-tab value="seo">SEO</v-tab>
                    <v-tab value="sales">Продажи</v-tab>
                    <v-tab value="price-history">История цен</v-tab>
                </v-tabs>
            </v-card>

            <v-window v-model="activeTab">
                <!-- OVERVIEW -->
                <v-window-item value="overview">
                    <v-row>
                        <v-col cols="12" lg="3">
                            <v-card class="mb-4">
                                <v-card-title class="text-wrap">
                                    {{ goodData.name }}
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

                            <v-card
                                v-if="showFormAddPrice"
                                class="mb-4"
                            >
                                <v-card-title>Добавить цену</v-card-title>

                                <v-card-text>
                                    <v-form @submit.prevent="storePrice">
                                        <v-row>
                                            <v-col cols="8">
                                                <v-text-field
                                                    v-model="formAddPrice.price"
                                                    label="Цена"
                                                    variant="solo"
                                                    density="comfortable"
                                                    type="number"
                                                    color="deep-purple"
                                                />
                                            </v-col>

                                            <v-col cols="4">
                                                <v-select
                                                    :items="currencies"
                                                    item-value="id"
                                                    item-title="code"
                                                    v-model="formAddPrice.currency_id"
                                                    label="Валюта"
                                                    variant="solo"
                                                    density="compact"
                                                    clearable
                                                />
                                            </v-col>
                                        </v-row>

                                        <v-row>
                                            <v-col cols="12">
                                                <v-btn
                                                    text="Store"
                                                    @click="storePrice"
                                                    variant="flat"
                                                    density="comfortable"
                                                    block
                                                />
                                            </v-col>
                                        </v-row>
                                    </v-form>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" lg="5">
                            <v-card class="mb-4">
                                <v-card-title>Кратко по товару</v-card-title>

                                <v-card-text>
                                    <v-list density="compact">
                                        <v-list-item>
                                            <template #prepend>
                                                <v-icon icon="mdi-pound" />
                                            </template>

                                            <v-list-item-title>ID</v-list-item-title>

                                            <v-list-item-subtitle>
                                                {{ goodData.id }}
                                            </v-list-item-subtitle>
                                        </v-list-item>

                                        <v-list-item>
                                            <template #prepend>
                                                <v-icon icon="mdi-link-variant" />
                                            </template>

                                            <v-list-item-title>Slug</v-list-item-title>

                                            <v-list-item-subtitle>
                                                {{ goodData.slug || "—" }}
                                            </v-list-item-subtitle>
                                        </v-list-item>

                                        <v-list-item>
                                            <template #prepend>
                                                <v-icon icon="mdi-package-variant" />
                                            </template>

                                            <v-list-item-title>Denominator</v-list-item-title>

                                            <v-list-item-subtitle>
                                                {{ goodData.denominator || "—" }}
                                            </v-list-item-subtitle>
                                        </v-list-item>

                                        <v-list-item>
                                            <template #prepend>
                                                <v-icon icon="mdi-percent" />
                                            </template>

                                            <v-list-item-title>VAT</v-list-item-title>

                                            <v-list-item-subtitle>
                                                <span v-if="currentVatRate">
                                                    {{ currentVatRate.title }} / {{ currentVatRate.rate }}%
                                                </span>

                                                <span v-else>
                                                    —
                                                </span>
                                            </v-list-item-subtitle>
                                        </v-list-item>
                                    </v-list>
                                </v-card-text>
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
                                                    {{ (goodData.prices || []).length }}
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

                <!-- CALCULATOR -->
                <v-window-item value="calculator">
                    <GoodQuotationCalculator
                        :quotations="goodData.quotations || []"
                        :default-vat-rate="defaultVatRate"
                        :default-box-weight-kg="goodBoxWeight"
                        currency-code="RUB"
                    />
                </v-window-item>

                <!-- SAVED CALCULATIONS -->
                <v-window-item value="calculations">
                    <GoodPriceCalculationsTab :good-id="goodData.id" />
                </v-window-item>

                <!-- PRICE TYPES -->
                <v-window-item value="price-types">
                    <v-card>
                        <v-card-title>Виды цен</v-card-title>

                        <v-card-text>
                            <v-alert
                                type="info"
                                variant="tonal"
                            >
                                Backend для <strong>price-types</strong> уже подключён.
                                На следующем этапе добавим отдельный компонент для управления видами цен:
                                розничная, оптовая, дилерская, VIP и другие.
                            </v-alert>
                        </v-card-text>
                    </v-card>
                </v-window-item>

                <!-- MEDIA -->
                <v-window-item value="media">
                    <v-card>
                        <v-card-title>Media</v-card-title>

                        <v-card-text>
                            <v-alert
                                type="info"
                                variant="tonal"
                                class="mb-4"
                            >
                                Таблицы <strong>good_media</strong> и
                                <strong>good_media_folders</strong> уже созданы.
                                Следующим этапом подключим загрузку фото, видео, папки, архив,
                                выбор ava и обработку видео через FFmpeg.
                            </v-alert>

                            <v-row v-if="(goodData.media || []).length">
                                <v-col
                                    v-for="media in goodData.media || []"
                                    :key="media.id"
                                    cols="12"
                                    sm="6"
                                    md="4"
                                    lg="3"
                                >
                                    <v-card variant="tonal">
                                        <v-img
                                            v-if="media.type === 'image'"
                                            :src="media.thumb_url || media.url"
                                            height="160"
                                            cover
                                        />

                                        <v-img
                                            v-else-if="media.type === 'video'"
                                            :src="media.poster_url || '/default-image.jpg'"
                                            height="160"
                                            cover
                                        />

                                        <v-card-text>
                                            <div class="font-weight-medium text-truncate">
                                                {{ media.title || media.file_name || media.original_name || `media #${media.id}` }}
                                            </div>

                                            <div class="text-caption text-medium-emphasis">
                                                {{ media.type }} / {{ media.processing_status || "—" }}
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>
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

                <!-- PRICE HISTORY -->
                <v-window-item value="price-history">
                    <v-card>
                        <v-card-title class="d-flex align-center justify-space-between">
                            <span>История цен</span>

                            <v-btn
                                text="+ 💵"
                                color="pink-darken-4"
                                variant="tonal"
                                density="compact"
                                @click="showFormAddPrice = !showFormAddPrice"
                            />
                        </v-card-title>

                        <v-card-text>
                            <v-card
                                v-if="showFormAddPrice"
                                variant="tonal"
                                class="mb-4"
                            >
                                <v-card-title>Добавить цену</v-card-title>

                                <v-card-text>
                                    <v-form @submit.prevent="storePrice">
                                        <v-row>
                                            <v-col cols="12" md="8">
                                                <v-text-field
                                                    v-model="formAddPrice.price"
                                                    label="Цена"
                                                    variant="solo"
                                                    density="comfortable"
                                                    type="number"
                                                    color="deep-purple"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-select
                                                    :items="currencies"
                                                    item-value="id"
                                                    item-title="code"
                                                    v-model="formAddPrice.currency_id"
                                                    label="Валюта"
                                                    variant="solo"
                                                    density="compact"
                                                    clearable
                                                />
                                            </v-col>

                                            <v-col cols="12">
                                                <v-btn
                                                    text="Store"
                                                    @click="storePrice"
                                                    variant="flat"
                                                    density="comfortable"
                                                />
                                            </v-col>
                                        </v-row>
                                    </v-form>
                                </v-card-text>
                            </v-card>

                            <v-list border rounded>
                                <v-list-item
                                    v-for="price in goodData.prices || []"
                                    :key="price.id"
                                >
                                    <v-row>
                                        <v-col cols="12" md="3">
                                            <span class="text-caption font-mono">
                                                {{ formatDateTime(price.created_at) }}
                                            </span>
                                        </v-col>

                                        <v-col cols="12" md="3">
                                            <strong>{{ formatMoney(price.price) }}</strong>
                                        </v-col>

                                        <v-col cols="12" md="2">
                                            <span>{{ price.currency?.code || "-" }}</span>
                                        </v-col>
                                    </v-row>
                                </v-list-item>

                                <v-list-item v-if="!(goodData.prices || []).length">
                                    <span class="text-medium-emphasis">
                                        Цен пока нет
                                    </span>
                                </v-list-item>
                            </v-list>
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

:deep(.v-window) {
    overflow: visible;
}
</style>
