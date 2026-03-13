import { computed, unref } from "vue";

export function useQuotationCalculator(options) {
    const {
        quotations,
        form,
    } = options;

    function toNumber(value, fallback = 0) {
        const number = Number(value);
        return Number.isFinite(number) ? number : fallback;
    }

    function round2(value) {
        return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
    }

    function normalizeMeasureName(value) {
        return String(value || "").trim().toLowerCase();
    }

    function quotationPricePerKg(quotation) {
        if (!quotation) return 0;

        const rawPrice = toNumber(quotation.price, 0);
        const denominator = Math.max(toNumber(quotation.denominator, 1), 1);
        const measureName = normalizeMeasureName(quotation.measure?.name);

        let kgFactor = 1;

        if (["кг", "kg", "kilogram"].includes(measureName)) {
            kgFactor = 1;
        } else if (["т", "тонна", "тонн", "ton", "tonne"].includes(measureName)) {
            kgFactor = 1000;
        } else if (["г", "гр", "gram", "g"].includes(measureName)) {
            kgFactor = 0.001;
        } else {
            kgFactor = 1;
        }

        const totalKg = denominator * kgFactor;

        if (totalKg <= 0) return 0;

        return rawPrice / totalKg;
    }

    function extractVatFromGross(grossPrice, vatRate) {
        const gross = toNumber(grossPrice, 0);
        const rate = Math.max(toNumber(vatRate, 0), 0);

        if (rate <= 0) {
            return {
                net: round2(gross),
                vat: 0,
                gross: round2(gross),
            };
        }

        const net = gross / (1 + rate / 100);
        const vat = gross - net;

        return {
            net: round2(net),
            vat: round2(vat),
            gross: round2(gross),
        };
    }

    function addVatToNet(netPrice, vatRate) {
        const net = toNumber(netPrice, 0);
        const rate = Math.max(toNumber(vatRate, 0), 0);

        if (rate <= 0) {
            return {
                net: round2(net),
                vat: 0,
                gross: round2(net),
            };
        }

        const gross = net * (1 + rate / 100);
        const vat = gross - net;

        return {
            net: round2(net),
            vat: round2(vat),
            gross: round2(gross),
        };
    }

    function applyMarkup(baseNetPrice, markupType, markupValue) {
        const base = toNumber(baseNetPrice, 0);
        const value = toNumber(markupValue, 0);

        if (markupType === "absolute") {
            return round2(base + value);
        }

        return round2(base * (1 + value / 100));
    }

    const selectedQuotation = computed(() => {
        const list = unref(quotations) || [];
        const formValue = unref(form);

        if (!formValue?.quotation_id) return null;

        return list.find(
            (quotation) => quotation.id === formValue.quotation_id
        ) || null;
    });

    const result = computed(() => {
        const quotation = selectedQuotation.value;
        const formValue = unref(form);

        if (!quotation || !formValue) return null;

        const vatRate = Math.max(toNumber(formValue.vatRate, 0), 0);
        const boxWeightKg = Math.max(toNumber(formValue.boxWeightKg, 1), 0.001);

        const sourcePricePerKg = quotationPricePerKg(quotation);

        let purchasePerKg;

        if (formValue.priceIncludesVat) {
            purchasePerKg = extractVatFromGross(sourcePricePerKg, vatRate);
        } else {
            purchasePerKg = addVatToNet(sourcePricePerKg, vatRate);
        }

        const saleNetPerKg = applyMarkup(
            purchasePerKg.net,
            formValue.markupType,
            formValue.markupValue
        );

        const salePerKg = addVatToNet(saleNetPerKg, vatRate);

        const buildScaledPrice = (multiplier) => ({
            net: round2(salePerKg.net * multiplier),
            vat: round2(salePerKg.vat * multiplier),
            gross: round2(salePerKg.gross * multiplier),
        });

        return {
            quotation,
            purchase: {
                perKg: purchasePerKg,
            },
            sale: {
                perKg: buildScaledPrice(1),
                perBox: buildScaledPrice(boxWeightKg),
                perTon: buildScaledPrice(1000),
            },
            meta: {
                sourcePricePerKg: round2(sourcePricePerKg),
                vatRate,
                boxWeightKg,
            },
        };
    });

    return {
        selectedQuotation,
        result,
        quotationPricePerKg,
        extractVatFromGross,
        addVatToNet,
        applyMarkup,
        toNumber,
        round2,
    };
}
