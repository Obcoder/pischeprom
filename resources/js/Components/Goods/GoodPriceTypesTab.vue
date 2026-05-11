<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { usePriceTypes } from "@/Composables/usePriceTypes";

const props = defineProps({
    currencies: {
        type: Array,
        default: () => [],
    },
});

const {
    priceTypes,
    loading,
    saving,
    deleting,
    fetchPriceTypes,
    storePriceType,
    updatePriceType,
    deletePriceType,
} = usePriceTypes();

const search = ref("");
const activeFilter = ref("all"); // all | active | inactive
const publicFilter = ref("all"); // all | public | hidden

const dialogForm = ref(false);
const dialogDelete = ref(false);
const editedId = ref(null);
const selectedItem = ref(null);

const form = reactive({
    name: "",
    code: "",
    description: "",
    currency_id: null,
    markup_percent: null,
    target_margin_percent: null,
    rounding_step: null,
    is_active: true,
    is_public: false,
    sort_order: 100,
});

const headers = [
    {
        key: "sort_order",
        title: "#",
        sortable: true,
        width: "80px",
    },
    {
        key: "name",
        title: "Вид цены",
        sortable: true,
    },
    {
        key: "code",
        title: "Код",
        sortable: true,
        width: "170px",
    },
    {
        key: "currency.code",
        title: "Валюта",
        sortable: true,
        width: "110px",
    },
    {
        key: "markup_percent",
        title: "Наценка %",
        sortable: true,
        width: "130px",
    },
    {
        key: "target_margin_percent",
        title: "Маржа %",
        sortable: true,
        width: "120px",
    },
    {
        key: "rounding_step",
        title: "Округление",
        sortable: true,
        width: "130px",
    },
    {
        key: "is_public",
        title: "Public",
        sortable: true,
        width: "100px",
    },
    {
        key: "is_active",
        title: "Active",
        sortable: true,
        width: "100px",
    },
    {
        key: "actions",
        title: "",
        sortable: false,
        width: "130px",
    },
];

const filteredItems = computed(() => {
    let items = [...priceTypes.value];

    const query = search.value.trim().toLowerCase();

    if (query) {
        items = items.filter((item) => {
            return [
                item.name,
                item.code,
                item.description,
                item.currency?.code,
                item.currency?.name,
            ]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(query));
        });
    }

    if (activeFilter.value === "active") {
        items = items.filter((item) => !!item.is_active);
    }

    if (activeFilter.value === "inactive") {
        items = items.filter((item) => !item.is_active);
    }

    if (publicFilter.value === "public") {
        items = items.filter((item) => !!item.is_public);
    }

    if (publicFilter.value === "hidden") {
        items = items.filter((item) => !item.is_public);
    }

    return items;
});

function resetForm() {
    editedId.value = null;

    form.name = "";
    form.code = "";
    form.description = "";
    form.currency_id = null;
    form.markup_percent = null;
    form.target_margin_percent = null;
    form.rounding_step = null;
    form.is_active = true;
    form.is_public = false;
    form.sort_order = 100;
}

function openCreate() {
    resetForm();
    dialogForm.value = true;
}

function openEdit(item) {
    editedId.value = item.id;

    form.name = item.name || "";
    form.code = item.code || "";
    form.description = item.description || "";
    form.currency_id = item.currency_id || null;
    form.markup_percent = item.markup_percent ?? null;
    form.target_margin_percent = item.target_margin_percent ?? null;
    form.rounding_step = item.rounding_step ?? null;
    form.is_active = !!item.is_active;
    form.is_public = !!item.is_public;
    form.sort_order = item.sort_order ?? 100;

    dialogForm.value = true;
}

function askDelete(item) {
    selectedItem.value = item;
    dialogDelete.value = true;
}

function payload() {
    return {
        name: form.name,
        code: form.code || null,
        description: form.description || null,
        currency_id: form.currency_id || null,
        markup_percent: form.markup_percent === "" ? null : form.markup_percent,
        target_margin_percent:
            form.target_margin_percent === "" ? null : form.target_margin_percent,
        rounding_step: form.rounding_step === "" ? null : form.rounding_step,
        is_active: form.is_active,
        is_public: form.is_public,
        sort_order: form.sort_order || 100,
    };
}

async function submit() {
    if (editedId.value) {
        await updatePriceType(editedId.value, payload());
    } else {
        await storePriceType(payload());
    }

    dialogForm.value = false;
}

async function confirmDelete() {
    if (!selectedItem.value?.id) return;

    await deletePriceType(selectedItem.value.id);

    dialogDelete.value = false;
    selectedItem.value = null;
}

function formatPercent(value) {
    if (value === null || value === undefined || value === "") return "—";

    return `${Number(value).toFixed(2)}%`;
}

function formatNumber(value) {
    if (value === null || value === undefined || value === "") return "—";

    return new Intl.NumberFormat("ru-RU", {
        maximumFractionDigits: 4,
    }).format(Number(value));
}

onMounted(() => {
    fetchPriceTypes();
});
</script>

<template>
    <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
            <span>Виды цен</span>

            <div class="d-flex align-center ga-2">
                <v-btn
                    icon="mdi-refresh"
                    variant="text"
                    :loading="loading"
                    @click="fetchPriceTypes"
                />

                <v-btn
                    color="deep-purple-darken-1"
                    variant="tonal"
                    prepend-icon="mdi-plus"
                    @click="openCreate"
                >
                    Новый вид цены
                </v-btn>
            </div>
        </v-card-title>

        <v-card-text>
            <v-alert
                type="info"
                variant="tonal"
                class="mb-4"
            >
                Здесь создаются глобальные виды цен: розничная, оптовая, дилерская,
                VIP, цена для сайта и другие. На следующем шаге свяжем их с сохранёнными
                расчётами конкретного товара.
            </v-alert>

            <v-row class="mb-2">
                <v-col cols="12" md="5">
                    <v-text-field
                        v-model="search"
                        label="Поиск по видам цен"
                        variant="solo-inverted"
                        density="compact"
                        clearable
                        hide-details
                    />
                </v-col>

                <v-col cols="12" md="3">
                    <v-select
                        v-model="activeFilter"
                        :items="[
                            { title: 'Все', value: 'all' },
                            { title: 'Только активные', value: 'active' },
                            { title: 'Только выключенные', value: 'inactive' },
                        ]"
                        label="Активность"
                        variant="solo-inverted"
                        density="compact"
                        hide-details
                    />
                </v-col>

                <v-col cols="12" md="3">
                    <v-select
                        v-model="publicFilter"
                        :items="[
                            { title: 'Все', value: 'all' },
                            { title: 'Публичные', value: 'public' },
                            { title: 'Скрытые', value: 'hidden' },
                        ]"
                        label="Публичность"
                        variant="solo-inverted"
                        density="compact"
                        hide-details
                    />
                </v-col>
            </v-row>

            <v-data-table
                :items="filteredItems"
                :headers="headers"
                :loading="loading"
                items-per-page="50"
                fixed-header
                height="560px"
                density="compact"
                class="border rounded"
                hover
            >
                <template #item.sort_order="{ item }">
                    <span class="text-caption">
                        {{ item.sort_order }}
                    </span>
                </template>

                <template #item.name="{ item }">
                    <div class="font-weight-medium">
                        {{ item.name }}
                    </div>

                    <div
                        v-if="item.description"
                        class="text-caption text-medium-emphasis"
                    >
                        {{ item.description }}
                    </div>
                </template>

                <template #item.code="{ item }">
                    <v-chip
                        size="small"
                        variant="tonal"
                    >
                        {{ item.code }}
                    </v-chip>
                </template>

                <template #item.currency.code="{ item }">
                    <span>{{ item.currency?.code || "—" }}</span>
                </template>

                <template #item.markup_percent="{ item }">
                    {{ formatPercent(item.markup_percent) }}
                </template>

                <template #item.target_margin_percent="{ item }">
                    {{ formatPercent(item.target_margin_percent) }}
                </template>

                <template #item.rounding_step="{ item }">
                    {{ formatNumber(item.rounding_step) }}
                </template>

                <template #item.is_public="{ item }">
                    <v-chip
                        size="small"
                        :color="item.is_public ? 'green' : 'grey'"
                        variant="tonal"
                    >
                        {{ item.is_public ? "public" : "hidden" }}
                    </v-chip>
                </template>

                <template #item.is_active="{ item }">
                    <v-chip
                        size="small"
                        :color="item.is_active ? 'green' : 'grey'"
                        variant="tonal"
                    >
                        {{ item.is_active ? "active" : "off" }}
                    </v-chip>
                </template>

                <template #item.actions="{ item }">
                    <v-btn
                        icon="mdi-pencil"
                        size="small"
                        variant="text"
                        @click="openEdit(item)"
                    />

                    <v-btn
                        icon="mdi-delete"
                        size="small"
                        variant="text"
                        color="red"
                        :loading="!!deleting[item.id]"
                        @click="askDelete(item)"
                    />
                </template>

                <template #no-data>
                    <div class="pa-6 text-center text-medium-emphasis">
                        Виды цен пока не созданы.
                    </div>
                </template>
            </v-data-table>
        </v-card-text>

        <!-- CREATE / EDIT -->
        <v-dialog
            v-model="dialogForm"
            width="820"
        >
            <v-card>
                <v-card-title>
                    {{ editedId ? "Редактировать вид цены" : "Новый вид цены" }}
                </v-card-title>

                <v-card-text>
                    <v-row>
                        <v-col cols="12" md="7">
                            <v-text-field
                                v-model="form.name"
                                label="Название"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12" md="5">
                            <v-text-field
                                v-model="form.code"
                                label="Код"
                                variant="outlined"
                                density="compact"
                                hint="Можно оставить пустым — сервер создаст из названия"
                                persistent-hint
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="form.description"
                                label="Описание"
                                variant="outlined"
                                density="compact"
                                rows="3"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-select
                                v-model="form.currency_id"
                                :items="currencies"
                                item-title="code"
                                item-value="id"
                                label="Валюта"
                                variant="outlined"
                                density="compact"
                                clearable
                            >
                                <template #item="{ props, item }">
                                    <v-list-item
                                        v-bind="props"
                                        :title="item.raw.code"
                                        :subtitle="item.raw.name"
                                    />
                                </template>
                            </v-select>
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.markup_percent"
                                label="Наценка %"
                                type="number"
                                step="0.01"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.target_margin_percent"
                                label="Целевая маржа %"
                                type="number"
                                min="0"
                                max="99.99"
                                step="0.01"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.rounding_step"
                                label="Шаг округления"
                                type="number"
                                step="0.01"
                                variant="outlined"
                                density="compact"
                                hint="Например: 1, 10, 50, 100"
                                persistent-hint
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.sort_order"
                                label="Сортировка"
                                type="number"
                                min="0"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-switch
                                v-model="form.is_active"
                                label="Active"
                                color="green"
                                inset
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-switch
                                v-model="form.is_public"
                                label="Public"
                                color="green"
                                inset
                                hide-details
                            />
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions>
                    <v-btn
                        variant="text"
                        @click="dialogForm = false"
                    >
                        Закрыть
                    </v-btn>

                    <v-btn
                        color="deep-purple-darken-1"
                        variant="tonal"
                        :loading="saving"
                        @click="submit"
                    >
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- DELETE CONFIRM -->
        <v-dialog
            v-model="dialogDelete"
            width="520"
        >
            <v-card>
                <v-card-title>Удалить вид цены?</v-card-title>

                <v-card-text>
                    Удалить:
                    <strong>{{ selectedItem?.name }}</strong>
                    ?
                </v-card-text>

                <v-card-actions>
                    <v-btn
                        variant="text"
                        @click="dialogDelete = false"
                    >
                        Отмена
                    </v-btn>

                    <v-btn
                        color="red"
                        variant="tonal"
                        :loading="selectedItem?.id ? !!deleting[selectedItem.id] : false"
                        @click="confirmDelete"
                    >
                        Удалить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>
