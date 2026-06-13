<script setup>
import { onMounted, ref } from "vue";
import { useGoodPriceTypeValues } from "@/Composables/useGoodPriceTypeValues";

const props = defineProps({
    goodId: {
        type: Number,
        required: true,
    },
    currencies: {
        type: Array,
        default: () => [],
    },
    tableHeight: {
        type: [Number, String],
        default: 560,
    },
    itemsPerPage: {
        type: Number,
        default: 50,
    },
    showIntro: {
        type: Boolean,
        default: true,
    },
});

const {
    values,
    loading,
    saving,
    deleting,
    fetchValues,
    updateValue,
    deleteValue,
} = useGoodPriceTypeValues(props.goodId);

const dialogEdit = ref(false);
const edited = ref(null);

const headers = [
    { key: "price_type.name", title: "Вид цены", sortable: true },
    { key: "price_gross", title: "Цена с НДС / кг", sortable: true, width: "170px" },
    { key: "price_net", title: "Цена без НДС / кг", sortable: true, width: "180px" },
    { key: "vat_rate", title: "НДС", sortable: true, width: "90px" },
    { key: "currency.code", title: "Валюта", sortable: true, width: "110px" },
    { key: "is_manual", title: "Manual", sortable: true, width: "110px" },
    { key: "is_published", title: "Public", sortable: true, width: "110px" },
    { key: "actions", title: "", sortable: false, width: "130px" },
];

function formatMoney(value) {
    if (value === null || value === undefined || value === "") return "—";

    return new Intl.NumberFormat("ru-RU", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

function openEdit(item) {
    edited.value = {
        id: item.id,
        currency_id: item.currency_id || null,
        price_net: item.price_net ?? null,
        price_gross: item.price_gross ?? null,
        vat_rate: item.vat_rate ?? 20,
        is_manual: !!item.is_manual,
        manual_comment: item.manual_comment || "",
        is_published: !!item.is_published,
        valid_from: item.valid_from || null,
        valid_to: item.valid_to || null,
    };

    dialogEdit.value = true;
}

async function saveEdit() {
    if (!edited.value?.id) return;

    await updateValue(edited.value.id, {
        currency_id: edited.value.currency_id,
        price_net: edited.value.price_net,
        price_gross: edited.value.price_gross,
        vat_rate: edited.value.vat_rate,
        is_manual: edited.value.is_manual,
        manual_comment: edited.value.manual_comment,
        is_published: edited.value.is_published,
        valid_from: edited.value.valid_from,
        valid_to: edited.value.valid_to,
    });

    dialogEdit.value = false;
}

onMounted(() => {
    fetchValues();
});
</script>

<template>
    <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
            <span>Актуальные цены товара по видам цен</span>

            <v-btn
                icon="mdi-refresh"
                variant="text"
                :loading="loading"
                @click="fetchValues"
            />
        </v-card-title>

        <v-card-text class="price-values-body">
            <v-alert
                v-if="showIntro"
                type="info"
                variant="tonal"
                class="mb-4"
            >
                Здесь хранятся цены конкретного товара для разных типов покупателей.
                Эти цены потом можно будет выводить в интернет-магазине.
            </v-alert>

            <v-data-table
                :items="values"
                :headers="headers"
                :loading="loading"
                :items-per-page="itemsPerPage"
                fixed-header
                :height="tableHeight"
                density="compact"
                class="border rounded"
                hover
            >
                <template #item.price_type.name="{ item }">
                    <div class="font-weight-medium">
                        {{ item.price_type?.name || "—" }}
                    </div>

                    <div class="text-caption text-medium-emphasis">
                        {{ item.price_type?.code || "—" }}
                    </div>
                </template>

                <template #item.price_gross="{ item }">
                    <strong>{{ formatMoney(item.price_gross) }}</strong>
                </template>

                <template #item.price_net="{ item }">
                    {{ formatMoney(item.price_net) }}
                </template>

                <template #item.vat_rate="{ item }">
                    {{ Number(item.vat_rate || 0).toFixed(2) }}%
                </template>

                <template #item.currency.code="{ item }">
                    {{ item.currency?.code || item.price_type?.currency?.code || "RUB" }}
                </template>

                <template #item.is_manual="{ item }">
                    <v-chip
                        size="small"
                        variant="tonal"
                        :color="item.is_manual ? 'orange' : 'grey'"
                    >
                        {{ item.is_manual ? "manual" : "auto" }}
                    </v-chip>
                </template>

                <template #item.is_published="{ item }">
                    <v-chip
                        size="small"
                        variant="tonal"
                        :color="item.is_published ? 'green' : 'grey'"
                    >
                        {{ item.is_published ? "public" : "hidden" }}
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
                        @click="deleteValue(item.id)"
                    />
                </template>

                <template #no-data>
                    <div class="pa-6 text-center text-medium-emphasis">
                        Цены по видам цен пока не назначены.
                        Перейдите во вкладку “Сохранённые расчёты” и примените расчёт к виду цены.
                    </div>
                </template>
            </v-data-table>
        </v-card-text>

        <v-dialog
            v-model="dialogEdit"
            width="720"
        >
            <v-card>
                <v-card-title>Редактировать цену товара</v-card-title>

                <v-card-text v-if="edited">
                    <v-row>
                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="edited.price_gross"
                                label="Цена с НДС / кг"
                                type="number"
                                step="0.01"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="edited.price_net"
                                label="Цена без НДС / кг"
                                type="number"
                                step="0.01"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="edited.vat_rate"
                                label="НДС %"
                                type="number"
                                step="0.01"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-select
                                v-model="edited.currency_id"
                                :items="currencies"
                                item-value="id"
                                item-title="code"
                                label="Валюта"
                                variant="outlined"
                                density="compact"
                                clearable
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-switch
                                v-model="edited.is_manual"
                                label="Ручная цена"
                                color="orange"
                                inset
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-switch
                                v-model="edited.is_published"
                                label="Публиковать"
                                color="green"
                                inset
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="edited.manual_comment"
                                label="Комментарий"
                                rows="3"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions>
                    <v-btn
                        variant="text"
                        @click="dialogEdit = false"
                    >
                        Закрыть
                    </v-btn>

                    <v-btn
                        color="deep-purple-darken-1"
                        variant="tonal"
                        :loading="saving"
                        @click="saveEdit"
                    >
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>
