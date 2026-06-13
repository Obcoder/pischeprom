<script setup>
import { computed, reactive, ref, watch } from "vue";
import { useQuotationCalculator } from "@/Composables/useQuotationCalculator";
import { useGoodPriceCalculations } from "@/Composables/useGoodPriceCalculations";

const props = defineProps({
    goodId: {
        type: Number,
        required: true,
    },
    quotations: {
        type: Array,
        default: () => [],
    },
    defaultVatRate: {
        type: Number,
        default: 20,
    },
    defaultBoxWeightKg: {
        type: Number,
        default: 1,
    },
    currencyCode: {
        type: String,
        default: "RUB",
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["saved"]);

const {
    storeCalculation,
    saving,
} = useGoodPriceCalculations(props.goodId);

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

const form = ref({
    quotation_id: null,
    vatRate: props.defaultVatRate,
    priceIncludesVat: true,

    pricingMode: "markup", // markup | targetMargin

    markupType: "percent", // percent | absolute
    markupValue: 0,

    targetMarginPercent: 20,

    boxWeightKg: props.defaultBoxWeightKg ?? 1,
});

const saveDialog = ref(false);

const saveForm = reactive({
    name: "",
    comment: "",
});

watch(
    () => props.defaultBoxWeightKg,
    (value) => {
        const weight = Number(value);

        if (Number.isFinite(weight) && weight > 0) {
            form.value.boxWeightKg = weight;
        }
    },
    {
        immediate: true,
    }
);

watch(
    () => props.defaultVatRate,
    (value) => {
        const vat = Number(value);

        if (Number.isFinite(vat) && vat >= 0) {
            form.value.vatRate = vat;
        }
    },
    {
        immediate: true,
    }
);

const { result } = useQuotationCalculator({
    quotations: props.quotations,
    form,
});

const selectedQuotation = computed(() => {
    return props.quotations.find((item) => Number(item.id) === Number(form.value.quotation_id)) || null;
});

const canSaveCalculation = computed(() => {
    return !!result.value && !!form.value.quotation_id && !!props.goodId;
});

const resultRows = computed(() => {
    const r = result.value;

    if (!r) {
        return [];
    }

    return [
        {
            title: "Закупка / кг",
            purchaseNet: r.purchase.perKg.net,
            net: r.purchase.perKg.net,
            vat: r.purchase.perKg.vat,
            gross: r.purchase.perKg.gross,
            profit: null,
            margin: null,
            markup: null,
        },
        {
            title: "Продажа / кг",
            purchaseNet: r.sale.perKg.purchaseNet,
            net: r.sale.perKg.net,
            vat: r.sale.perKg.vat,
            gross: r.sale.perKg.gross,
            profit: r.sale.perKg.profit,
            margin: r.sale.perKg.marginPercent,
            markup: r.sale.perKg.markupPercent,
        },
        {
            title: "Продажа / коробка",
            purchaseNet: r.sale.perBox.purchaseNet,
            net: r.sale.perBox.net,
            vat: r.sale.perBox.vat,
            gross: r.sale.perBox.gross,
            profit: r.sale.perBox.profit,
            margin: r.sale.perBox.marginPercent,
            markup: r.sale.perBox.markupPercent,
        },
        {
            title: "Продажа / тонна",
            purchaseNet: r.sale.perTon.purchaseNet,
            net: r.sale.perTon.net,
            vat: r.sale.perTon.vat,
            gross: r.sale.perTon.gross,
            profit: r.sale.perTon.profit,
            margin: r.sale.perTon.marginPercent,
            markup: r.sale.perTon.markupPercent,
        },
    ];
});

function defaultCalculationName() {
    const quotation = selectedQuotation.value;
    const source = quotation?.unit?.name || "quotation";

    if (form.value.pricingMode === "targetMargin") {
        return `Расчёт от ${source}: маржа ${form.value.targetMarginPercent}%`;
    }

    if (form.value.markupType === "percent") {
        return `Расчёт от ${source}: наценка ${form.value.markupValue}%`;
    }

    return `Расчёт от ${source}: наценка ${form.value.markupValue} ${props.currencyCode}/кг`;
}

function openSaveDialog() {
    if (!canSaveCalculation.value) return;

    saveForm.name = defaultCalculationName();
    saveForm.comment = "";

    saveDialog.value = true;
}

function calculationPayload() {
    const r = result.value;

    return {
        quotation_id: form.value.quotation_id,
        purchase_id: null,
        price_type_id: null,
        formula_id: null,
        currency_id: null,

        name: saveForm.name || defaultCalculationName(),
        comment: saveForm.comment || null,

        input: {
            ...form.value,
            quotation: selectedQuotation.value
                ? {
                    id: selectedQuotation.value.id,
                    unit_id: selectedQuotation.value.unit_id,
                    unit_name: selectedQuotation.value.unit?.name || null,
                    price: selectedQuotation.value.price,
                    measure_id: selectedQuotation.value.measure_id,
                    measure_name: selectedQuotation.value.measure?.name || null,
                    denominator: selectedQuotation.value.denominator || 1,
                }
                : null,
        },

        result: r,

        purchase_net_per_kg: r?.purchase?.perKg?.net ?? null,

        sale_net_per_kg: r?.sale?.perKg?.net ?? null,
        sale_gross_per_kg: r?.sale?.perKg?.gross ?? null,

        sale_net_per_box: r?.sale?.perBox?.net ?? null,
        sale_gross_per_box: r?.sale?.perBox?.gross ?? null,

        profit_per_kg: r?.sale?.perKg?.profit ?? null,
        margin_percent: r?.sale?.perKg?.marginPercent ?? null,
        markup_percent: r?.sale?.perKg?.markupPercent ?? null,
    };
}

async function saveCalculation() {
    if (!canSaveCalculation.value) return;

    const saved = await storeCalculation(calculationPayload());

    saveDialog.value = false;

    emit("saved", saved);
}
</script>

<template>
    <v-card class="quotation-calculator" :class="{ 'quotation-calculator--compact': compact }">
        <v-card-title class="quotation-calculator__title">
            <span>Расчет продажной цены</span>

            <v-btn
                color="#47765a"
                variant="tonal"
                prepend-icon="mdi-content-save"
                size="small"
                :disabled="!canSaveCalculation"
                :loading="saving"
                @click="openSaveDialog"
            >
                Сохранить
            </v-btn>
        </v-card-title>

        <v-card-text class="quotation-calculator__body">
            <v-row dense>
                <v-col cols="12" md="5">
                    <v-select
                        v-model="form.quotation_id"
                        :items="quotations"
                        item-value="id"
                        label="Quotation"
                        variant="outlined"
                        density="compact"
                        hide-details
                        clearable
                    >
                        <template #item="{ props, item }">
                            <v-list-item
                                v-bind="props"
                                :title="`${item.raw.unit?.name || '-'} | ${formatMoney(item.raw.price)} ${currencyCode}`"
                                :subtitle="`${item.raw.measure?.name || '-'} | делитель: ${item.raw.denominator || 1}`"
                            />
                        </template>

                        <template #selection="{ item }">
                            <span>
                                {{ item.raw.unit?.name || "-" }} | {{ formatMoney(item.raw.price) }} {{ currencyCode }}
                            </span>
                        </template>
                    </v-select>
                </v-col>

                <v-col cols="6" md="2">
                    <v-text-field
                        v-model="form.vatRate"
                        label="НДС %"
                        type="number"
                        min="0"
                        step="0.01"
                        variant="outlined"
                        density="compact"
                        hide-details
                    />
                </v-col>

                <v-col cols="6" md="2">
                    <v-text-field
                        v-model="form.boxWeightKg"
                        label="Коробка, кг"
                        type="number"
                        min="0.001"
                        step="0.001"
                        variant="outlined"
                        density="compact"
                        hide-details
                    />
                </v-col>

                <v-col cols="12" md="3" class="d-flex align-center">
                    <v-switch
                        v-model="form.priceIncludesVat"
                        label="Quotation с НДС"
                        color="#47765a"
                        density="compact"
                        inset
                        hide-details
                    />
                </v-col>

                <v-col cols="12" md="4">
                    <v-select
                        v-model="form.pricingMode"
                        :items="[
                            { title: 'По наценке', value: 'markup' },
                            { title: 'По целевой марже', value: 'targetMargin' }
                        ]"
                        item-title="title"
                        item-value="value"
                        label="Режим"
                        variant="outlined"
                        density="compact"
                        hide-details
                    />
                </v-col>

                <template v-if="form.pricingMode === 'markup'">
                    <v-col cols="6" md="3">
                        <v-select
                            v-model="form.markupType"
                            :items="[
                                { title: 'Наценка %', value: 'percent' },
                                { title: 'Наценка суммой', value: 'absolute' }
                            ]"
                            item-title="title"
                            item-value="value"
                            label="Тип"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="6" md="2">
                        <v-text-field
                            v-model="form.markupValue"
                            :label="form.markupType === 'percent' ? 'Наценка %' : 'Наценка / кг'"
                            type="number"
                            step="0.01"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>
                </template>

                <template v-else>
                    <v-col cols="12" md="3">
                        <v-text-field
                            v-model="form.targetMarginPercent"
                            label="Маржа %"
                            type="number"
                            min="0"
                            max="99.99"
                            step="0.01"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>
                </template>
            </v-row>

            <div v-if="result" class="quotation-calculator__meta">
                <v-chip size="x-small" variant="tonal" color="blue-grey">
                    База: {{ formatMoney(result.meta.sourcePricePerKg) }} {{ currencyCode }}/кг
                </v-chip>

                <v-chip size="x-small" variant="tonal" color="blue-grey">
                    {{ form.pricingMode === "targetMargin" ? `Маржа ${result.meta.targetMarginPercent}%` : "Наценка" }}
                </v-chip>
            </div>

            <template v-if="result">
                <v-table density="compact" class="quotation-result-table">
                    <thead>
                        <tr>
                            <th>Позиция</th>
                            <th>Закупка без НДС</th>
                            <th>Продажа без НДС</th>
                            <th>НДС</th>
                            <th>С НДС</th>
                            <th>Прибыль</th>
                            <th>Маржа</th>
                            <th>Наценка</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="row in resultRows" :key="row.title">
                            <td class="font-weight-bold">{{ row.title }}</td>
                            <td>{{ formatMoney(row.purchaseNet) }}</td>
                            <td>{{ formatMoney(row.net) }}</td>
                            <td>{{ formatMoney(row.vat) }}</td>
                            <td class="font-weight-bold">{{ formatMoney(row.gross) }}</td>
                            <td>{{ row.profit === null ? "—" : formatMoney(row.profit) }}</td>
                            <td>{{ row.margin === null ? "—" : `${row.margin}%` }}</td>
                            <td>{{ row.markup === null ? "—" : `${row.markup}%` }}</td>
                        </tr>
                    </tbody>
                </v-table>
            </template>

            <v-alert
                v-else
                type="info"
                variant="tonal"
                density="compact"
                class="mt-2"
            >
                Выберите quotation для расчета.
            </v-alert>
        </v-card-text>

        <v-dialog
            v-model="saveDialog"
            width="620"
        >
            <v-card>
                <v-card-title>Сохранить расчёт продажной цены</v-card-title>

                <v-card-text>
                    <v-text-field
                        v-model="saveForm.name"
                        label="Название расчёта"
                        variant="outlined"
                        density="compact"
                    />

                    <v-textarea
                        v-model="saveForm.comment"
                        label="Комментарий"
                        variant="outlined"
                        density="compact"
                        rows="4"
                    />

                    <v-alert
                        v-if="result"
                        type="success"
                        variant="tonal"
                        class="mt-3"
                    >
                        <div>
                            Продажа / 1 кг с НДС:
                            <strong>{{ formatMoney(result.sale.perKg.gross) }} {{ currencyCode }}</strong>
                        </div>

                        <div>
                            Продажа / коробка с НДС:
                            <strong>{{ formatMoney(result.sale.perBox.gross) }} {{ currencyCode }}</strong>
                        </div>

                        <div>
                            Маржа:
                            <strong>{{ result.sale.perKg.marginPercent }}%</strong>
                        </div>
                    </v-alert>
                </v-card-text>

                <v-card-actions>
                    <v-btn
                        variant="text"
                        @click="saveDialog = false"
                    >
                        Отмена
                    </v-btn>

                    <v-btn
                        color="deep-purple-darken-1"
                        variant="tonal"
                        :loading="saving"
                        @click="saveCalculation"
                    >
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>

<style scoped>
.quotation-calculator__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 44px;
    padding-top: 8px;
    padding-bottom: 8px;
}

.quotation-calculator__body {
    padding-top: 10px;
}

.quotation-calculator__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 8px 0;
}

.quotation-result-table {
    font-size: 0.78rem;
}

.quotation-result-table :deep(th),
.quotation-result-table :deep(td) {
    height: 30px !important;
    padding: 0 8px !important;
    white-space: nowrap;
}

.quotation-result-table :deep(th) {
    font-size: 0.72rem;
}
</style>
