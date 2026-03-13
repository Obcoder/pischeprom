<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { useHead } from "@vueuse/head";
import { route } from "ziggy-js";
import { useDate } from "vuetify";
import { useForm } from "@inertiajs/vue3";
import GoodQuotationCalculator from "@/Components/GoodQuotationCalculator.vue";

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

const goodData = ref(null);
const currencies = ref([]);
const measures = ref([]);
const units = ref([]);

const showFormAddPrice = ref(false);
const dialogFormQuotation = ref(false);

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

// --------------------------------------------------
// HELPERS
// --------------------------------------------------
function toNumber(value, fallback = 0) {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
}

function round2(value) {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
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

// --------------------------------------------------
// API
// --------------------------------------------------
async function fetchGood() {
    const response = await axios.get(route("good.fetch", props.good.id));
    goodData.value = response.data;
}

async function fetchCurrencies() {
    const response = await axios.get(route("currencies.index"));
    currencies.value = response.data;
}

async function fetchMeasures() {
    const response = await axios.get(route("measures.index"));
    measures.value = response.data;
}

async function fetchUnits() {
    const response = await axios.get(route("units.index"));
    units.value = response.data;
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
    formAddPrice.transform((data) => ({
        ...data,
        price: toNumber(data.price, null),
        currency_id: data.currency_id,
    })).post(route("web.price.store"), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: async () => {
            formAddPrice.reset();
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
    formQuotation.transform((data) => ({
        ...data,
        price: toNumber(data.price, null),
        denominator: Math.max(toNumber(data.denominator, 1), 1),
    })).post(route("web.quotation.store"), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: async () => {
            formQuotation.reset();
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
// SEO
// --------------------------------------------------
const seoTitle = computed(() => {
    return goodData.value?.name
        ? `${goodData.value.name} - Your Store`
        : "Loading...";
});

const seoDescription = computed(() => {
    const description = safeText(goodData.value?.description, "Loading product details...");
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
                    ? `${goodData.value.name}, product, store`
                    : "product"
            ),
        },
        {
            property: "og:title",
            content: computed(() => goodData.value?.name || "Loading..."),
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
// QUOTATION CALCULATOR
// --------------------------------------------------

/**
 * Приведение quotation к цене за 1 кг.
 *
 * Предположение:
 * q.price = цена за denominator единиц measure
 *
 * measure:
 * - кг / kg => 1 кг
 * - т / тонна / ton => 1000 кг
 * - г / gram / g => 0.001 кг
 */

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
        <v-row class="mb-3">
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
                                                label="Делитель"
                                                type="number"
                                                min="1"
                                                variant="solo"
                                                density="compact"
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
            </v-col>
        </v-row>

        <!-- LOADING / ERROR -->
        <v-row v-if="pageLoading">
            <v-col cols="12" class="text-center">
                <v-progress-circular indeterminate color="primary" />
            </v-col>
        </v-row>

        <v-row v-else-if="pageError">
            <v-col cols="12">
                <v-alert type="error" variant="tonal">
                    {{ pageError }}
                </v-alert>
            </v-col>
        </v-row>

        <!-- CONTENT -->
        <template v-else-if="goodData">
            <v-row>
                <!-- LEFT COLUMN -->
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

                    <!-- FORM ADD PRICE -->
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

                    <!-- PRICE HISTORY -->
                    <v-card>
                        <v-card-title>История цен</v-card-title>

                        <v-card-text class="pa-0">
                            <v-list border rounded>
                                <v-list-item
                                    v-for="price in goodData.prices || []"
                                    :key="price.id"
                                >
                                    <v-row>
                                        <v-col cols="5">
                                            <span class="text-caption font-mono">
                                                {{ formatDate(price.created_at) }}
                                            </span>
                                        </v-col>
                                        <v-col cols="4">
                                            <span>{{ formatMoney(price.price) }}</span>
                                        </v-col>
                                        <v-col cols="3">
                                            <span>{{ price.currency?.code || "-" }}</span>
                                        </v-col>
                                    </v-row>
                                </v-list-item>

                                <v-list-item v-if="!(goodData.prices || []).length">
                                    <span class="text-medium-emphasis">Цен пока нет</span>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- QUOTATIONS -->
                <v-col cols="12" lg="5">
                    <v-card class="mb-4">
                        <v-card-title>Quotations</v-card-title>

                        <v-card-text class="pa-0">
                            <v-data-table
                                :items="goodData.quotations || []"
                                :headers="headerQuotations"
                                items-per-page="100"
                                fixed-header
                                height="405px"
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

                    <!-- CALCULATOR -->
                    <GoodQuotationCalculator
                        :quotations="goodData.quotations || []"
                        :default-vat-rate="20"
                        :default-box-weight-kg="goodData.denominator || 1"
                        currency-code="RUB"
                    />
                </v-col>

                <!-- SALES -->
                <v-col cols="12" lg="4">
                    <v-card>
                        <v-card-title>Продажи</v-card-title>

                        <v-card-text class="pa-0">
                            <v-data-table
                                :items="goodData.sales || []"
                                :headers="headerSales"
                                items-per-page="89"
                                fixed-header
                                height="510px"
                                density="comfortable"
                                hover
                            >
                                <template #item.date="{ item }">
                                    <span class="text-xs">{{ item.date }}</span>
                                </template>

                                <template #item.pivot.price="{ item }">
                                    <span class="font-weight-bold">
                                        {{ formatMoney(item.pivot?.price) }}
                                    </span>
                                </template>
                            </v-data-table>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </template>
    </v-container>
</template>

<style scoped>
.v-card {
    margin-top: 20px;
}

.rounded-lg {
    border-radius: 8px;
}
</style>
