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
    <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
            <span>Расчет продажной цены</span>

            <v-btn
                color="deep-purple-darken-1"
                variant="tonal"
                prepend-icon="mdi-content-save"
                :disabled="!canSaveCalculation"
                :loading="saving"
                @click="openSaveDialog"
            >
                Сохранить расчёт
            </v-btn>
        </v-card-title>

        <v-card-text>
            <v-row>
                <v-col cols="12" md="6">
                    <v-select
                        v-model="form.quotation_id"
                        :items="quotations"
                        item-value="id"
                        label="Выберите quotation"
                        variant="solo"
                        clearable
                    >
                        <template #item="{ props, item }">
                            <v-list-item
                                v-bind="props"
                                :title="`${item.raw.unit?.name || '-'} | ${formatMoney(item.raw.price)} ${currencyCode}`"
                                :subtitle="`${item.raw.measure?.name || '-'} | делитель quotation: ${item.raw.denominator || 1}`"
                            />
                        </template>

                        <template #selection="{ item }">
                            <span>
                                {{ item.raw.unit?.name || "-" }}
                                |
                                {{ formatMoney(item.raw.price) }} {{ currencyCode }}
                            </span>
                        </template>
                    </v-select>
                </v-col>

                <v-col cols="12" md="3">
                    <v-text-field
                        v-model="form.vatRate"
                        label="НДС %"
                        type="number"
                        min="0"
                        step="0.01"
                        variant="solo"
                    />
                </v-col>

                <v-col cols="12" md="3">
                    <v-text-field
                        v-model="form.boxWeightKg"
                        label="Вес коробки, кг"
                        type="number"
                        min="0.001"
                        step="0.001"
                        variant="solo"
                        hint="По умолчанию берется из good.denominator"
                        persistent-hint
                    />
                </v-col>
            </v-row>

            <v-row>
                <v-col cols="12" md="4">
                    <v-switch
                        v-model="form.priceIncludesVat"
                        label="Quotation с НДС"
                        color="primary"
                        inset
                    />
                </v-col>

                <v-col cols="12" md="4">
                    <v-select
                        v-model="form.pricingMode"
                        :items="[
                            { title: 'Расчет по наценке', value: 'markup' },
                            { title: 'Расчет по целевой марже', value: 'targetMargin' }
                        ]"
                        item-title="title"
                        item-value="value"
                        label="Режим расчета"
                        variant="solo"
                    />
                </v-col>
            </v-row>

            <v-row>
                <template v-if="form.pricingMode === 'markup'">
                    <v-col cols="12" md="4">
                        <v-select
                            v-model="form.markupType"
                            :items="[
                                { title: 'Наценка %', value: 'percent' },
                                { title: 'Наценка суммой', value: 'absolute' }
                            ]"
                            item-title="title"
                            item-value="value"
                            label="Тип наценки"
                            variant="solo"
                        />
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.markupValue"
                            :label="form.markupType === 'percent' ? 'Наценка %' : 'Абсолютная наценка на 1 кг'"
                            type="number"
                            step="0.01"
                            variant="solo"
                        />
                    </v-col>
                </template>

                <template v-else>
                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.targetMarginPercent"
                            label="Целевая маржа %"
                            type="number"
                            min="0"
                            max="99.99"
                            step="0.01"
                            variant="solo"
                            hint="Маржа считается по цене без НДС"
                            persistent-hint
                        />
                    </v-col>
                </template>
            </v-row>

            <v-divider class="my-4" />

            <template v-if="result">
                <v-row class="mb-2">
                    <v-col cols="12">
                        <v-alert type="info" variant="tonal">
                            <div>
                                Базовая цена quotation, приведенная к 1 кг:
                                <strong>
                                    {{ formatMoney(result.meta.sourcePricePerKg) }} {{ currencyCode }}
                                </strong>
                            </div>

                            <div class="mt-1" v-if="form.pricingMode === 'targetMargin'">
                                Расчет выполнен по целевой марже:
                                <strong>{{ result.meta.targetMarginPercent }}%</strong>
                            </div>

                            <div class="mt-1" v-else>
                                Расчет выполнен по наценке.
                            </div>
                        </v-alert>
                    </v-col>
                </v-row>

                <v-row>
                    <v-col cols="12" md="4">
                        <v-card variant="tonal">
                            <v-card-title>Закупка / 1 кг</v-card-title>

                            <v-card-text>
                                <div>
                                    Без НДС:
                                    <strong>{{ formatMoney(result.purchase.perKg.net) }} {{ currencyCode }}</strong>
                                </div>

                                <div>
                                    НДС:
                                    <strong>{{ formatMoney(result.purchase.perKg.vat) }} {{ currencyCode }}</strong>
                                </div>

                                <div>
                                    С НДС:
                                    <strong>{{ formatMoney(result.purchase.perKg.gross) }} {{ currencyCode }}</strong>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-card variant="tonal">
                            <v-card-title>Продажа / 1 кг</v-card-title>

                            <v-card-text>
                                <div>
                                    Без НДС:
                                    <strong>{{ formatMoney(result.sale.perKg.net) }} {{ currencyCode }}</strong>
                                </div>

                                <div>
                                    НДС:
                                    <strong>{{ formatMoney(result.sale.perKg.vat) }} {{ currencyCode }}</strong>
                                </div>

                                <div>
                                    С НДС:
                                    <strong>{{ formatMoney(result.sale.perKg.gross) }} {{ currencyCode }}</strong>
                                </div>

                                <v-divider class="my-2" />

                                <div>
                                    Прибыль без НДС:
                                    <strong>{{ formatMoney(result.sale.perKg.profit) }} {{ currencyCode }}</strong>
                                </div>

                                <div>
                                    Маржа:
                                    <strong>{{ result.sale.perKg.marginPercent }}%</strong>
                                </div>

                                <div>
                                    Наценка к закупке:
                                    <strong>{{ result.sale.perKg.markupPercent }}%</strong>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-card variant="tonal">
                            <v-card-title>Продажа / коробка</v-card-title>

                            <v-card-text>
                                <div>
                                    Без НДС:
                                    <strong>{{ formatMoney(result.sale.perBox.net) }} {{ currencyCode }}</strong>
                                </div>

                                <div>
                                    НДС:
                                    <strong>{{ formatMoney(result.sale.perBox.vat) }} {{ currencyCode }}</strong>
                                </div>

                                <div>
                                    С НДС:
                                    <strong>{{ formatMoney(result.sale.perBox.gross) }} {{ currencyCode }}</strong>
                                </div>

                                <v-divider class="my-2" />

                                <div>
                                    Прибыль без НДС:
                                    <strong>{{ formatMoney(result.sale.perBox.profit) }} {{ currencyCode }}</strong>
                                </div>

                                <div>
                                    Маржа:
                                    <strong>{{ result.sale.perBox.marginPercent }}%</strong>
                                </div>

                                <div>
                                    Наценка к закупке:
                                    <strong>{{ result.sale.perBox.markupPercent }}%</strong>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>

                <v-row class="mt-2">
                    <v-col cols="12" md="4">
                        <v-card variant="tonal">
                            <v-card-title>Продажа / 1 тонна</v-card-title>

                            <v-card-text>
                                <div>
                                    Без НДС:
                                    <strong>{{ formatMoney(result.sale.perTon.net) }} {{ currencyCode }}</strong>
                                </div>

                                <div>
                                    НДС:
                                    <strong>{{ formatMoney(result.sale.perTon.vat) }} {{ currencyCode }}</strong>
                                </div>

                                <div>
                                    С НДС:
                                    <strong>{{ formatMoney(result.sale.perTon.gross) }} {{ currencyCode }}</strong>
                                </div>

                                <v-divider class="my-2" />

                                <div>
                                    Прибыль без НДС:
                                    <strong>{{ formatMoney(result.sale.perTon.profit) }} {{ currencyCode }}</strong>
                                </div>

                                <div>
                                    Маржа:
                                    <strong>{{ result.sale.perTon.marginPercent }}%</strong>
                                </div>

                                <div>
                                    Наценка к закупке:
                                    <strong>{{ result.sale.perTon.markupPercent }}%</strong>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </template>

            <v-alert
                v-else
                type="info"
                variant="tonal"
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
