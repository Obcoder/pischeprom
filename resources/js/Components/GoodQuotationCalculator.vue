<script setup>
import { ref, watch } from "vue"
import { useQuotationCalculator } from "@/Composables/useQuotationCalculator"

const props = defineProps({
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
})

function toNumber(value, fallback = 0) {
    const number = Number(value)
    return Number.isFinite(number) ? number : fallback
}

function formatMoney(value) {
    return new Intl.NumberFormat("ru-RU", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(toNumber(value))
}

const form = ref({
    quotation_id: null,
    vatRate: props.defaultVatRate,
    priceIncludesVat: true,
    markupType: "percent",
    markupValue: 0,
    boxWeightKg: props.defaultBoxWeightKg ?? 1,
})

watch(
    () => props.defaultBoxWeightKg,
    (value) => {
        const weight = Number(value)

        if (Number.isFinite(weight) && weight > 0) {
            form.value.boxWeightKg = weight
        }
    },
    {
        immediate: true
    }
)

const { result } = useQuotationCalculator({
    quotations: props.quotations,
    form,
})
</script>

<template>
    <v-card>
        <v-card-title>Расчет продажной цены</v-card-title>

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
                                {{ item.raw.unit?.name || "-" }} |
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
                        variant="solo"
                    />
                </v-col>

                <v-col cols="12" md="3">
                    <v-text-field
                        v-model="form.boxWeightKg"
                        label="Вес коробки, кг"
                        type="number"
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
                        variant="solo"
                    />
                </v-col>
            </v-row>

            <v-divider class="my-4" />

            <template v-if="result">
                <v-row class="mb-2">
                    <v-col cols="12">
                        <v-alert type="info" variant="tonal">
                            Базовая цена quotation, приведенная к 1 кг:
                            <strong>{{ formatMoney(result.meta.sourcePricePerKg) }} {{ currencyCode }}</strong>
                        </v-alert>
                    </v-col>
                </v-row>

                <v-row>
                    <v-col cols="12" md="4">
                        <v-card variant="tonal">
                            <v-card-title>Закупка / 1 кг</v-card-title>
                            <v-card-text>
                                <div>Без НДС: <strong>{{ formatMoney(result.purchase.perKg.net) }} {{ currencyCode }}</strong></div>
                                <div>НДС: <strong>{{ formatMoney(result.purchase.perKg.vat) }} {{ currencyCode }}</strong></div>
                                <div>С НДС: <strong>{{ formatMoney(result.purchase.perKg.gross) }} {{ currencyCode }}</strong></div>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-card variant="tonal">
                            <v-card-title>Продажа / 1 кг</v-card-title>
                            <v-card-text>
                                <div>Без НДС: <strong>{{ formatMoney(result.sale.perKg.net) }} {{ currencyCode }}</strong></div>
                                <div>НДС: <strong>{{ formatMoney(result.sale.perKg.vat) }} {{ currencyCode }}</strong></div>
                                <div>С НДС: <strong>{{ formatMoney(result.sale.perKg.gross) }} {{ currencyCode }}</strong></div>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-card variant="tonal">
                            <v-card-title>Продажа / коробка</v-card-title>
                            <v-card-text>
                                <div>Без НДС: <strong>{{ formatMoney(result.sale.perBox.net) }} {{ currencyCode }}</strong></div>
                                <div>НДС: <strong>{{ formatMoney(result.sale.perBox.vat) }} {{ currencyCode }}</strong></div>
                                <div>С НДС: <strong>{{ formatMoney(result.sale.perBox.gross) }} {{ currencyCode }}</strong></div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>

                <v-row class="mt-2">
                    <v-col cols="12" md="4">
                        <v-card variant="tonal">
                            <v-card-title>Продажа / 1 тонна</v-card-title>
                            <v-card-text>
                                <div>Без НДС: <strong>{{ formatMoney(result.sale.perTon.net) }} {{ currencyCode }}</strong></div>
                                <div>НДС: <strong>{{ formatMoney(result.sale.perTon.vat) }} {{ currencyCode }}</strong></div>
                                <div>С НДС: <strong>{{ formatMoney(result.sale.perTon.gross) }} {{ currencyCode }}</strong></div>
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
    </v-card>
</template>
