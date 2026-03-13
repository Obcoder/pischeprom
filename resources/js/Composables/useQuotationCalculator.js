import { computed, unref } from "vue";

export function useQuotationCalculator(options) {
    const { quotations, form } = options;

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

    /**
     * Приведение quotation к цене за 1 кг.
     *
     * Ожидается:
     * - quotation.price = цена за denominator единиц measure
     * - quotation.measure.name = кг / т / г
     */
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
            // fallback: считаем, что measure уже в кг
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

    /**
     * Расчет цены продажи без НДС по целевой марже.
     *
     * Формула:
     * margin = (sale - purchase) / sale
     * sale = purchase / (1 - margin)
     */
    function calcSaleNetByTargetMargin(purchaseNet, targetMarginPercent) {
        const purchase = toNumber(purchaseNet, 0);
        const margin = toNumber(targetMarginPercent, 0);

        if (purchase <= 0) return 0;
        if (margin <= 0) return round2(purchase);
        if (margin >= 100) return 0;

        return round2(purchase / (1 - margin / 100));
    }

    function calcProfit(purchaseNet, saleNet) {
        return round2(toNumber(saleNet, 0) - toNumber(purchaseNet, 0));
    }

    function calcMarginPercent(purchaseNet, saleNet) {
        const sale = toNumber(saleNet, 0);
        const profit = calcProfit(purchaseNet, saleNet);

        if (sale <= 0) return 0;

        return round2((profit / sale) * 100);
    }

    function calcMarkupPercent(purchaseNet, saleNet) {
        const purchase = toNumber(purchaseNet, 0);
        const profit = calcProfit(purchaseNet, saleNet);

        if (purchase <= 0) return 0;

        return round2((profit / purchase) * 100);
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

        let saleNetPerKg = purchasePerKg.net;

        if (formValue.pricingMode === "targetMargin") {
            saleNetPerKg = calcSaleNetByTargetMargin(
                purchasePerKg.net,
                formValue.targetMarginPercent
            );
        } else {
            saleNetPerKg = applyMarkup(
                purchasePerKg.net,
                formValue.markupType,
                formValue.markupValue
            );
        }

        const salePerKg = addVatToNet(saleNetPerKg, vatRate);

        function buildScaledPrice(multiplier) {
            const purchaseNet = round2(purchasePerKg.net * multiplier);
            const saleNet = round2(salePerKg.net * multiplier);
            const saleVat = round2(salePerKg.vat * multiplier);
            const saleGross = round2(salePerKg.gross * multiplier);

            const profit = calcProfit(purchaseNet, saleNet);
            const marginPercent = calcMarginPercent(purchaseNet, saleNet);
            const markupPercent = calcMarkupPercent(purchaseNet, saleNet);

            return {
                purchaseNet,
                net: saleNet,
                vat: saleVat,
                gross: saleGross,
                profit,
                marginPercent,
                markupPercent,
            };
        }

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
                pricingMode: formValue.pricingMode,
                markupType: formValue.markupType,
                markupValue: toNumber(formValue.markupValue, 0),
                targetMarginPercent: toNumber(formValue.targetMarginPercent, 0),
                priceIncludesVat: Boolean(formValue.priceIncludesVat),
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
        calcSaleNetByTargetMargin,
        calcProfit,
        calcMarginPercent,
        calcMarkupPercent,
        toNumber,
        round2,
    };
}
