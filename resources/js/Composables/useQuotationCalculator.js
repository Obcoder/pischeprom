import { computed, unref } from "vue";

export function useQuotationCalculator(options) {
    const { quotations, purchases, measures, form } = options;

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

    function kgFactorByMeasureName(value) {
        const measureName = normalizeMeasureName(value);
        let kgFactor = 1;

        if (["кг", "килограмм", "килограммы", "kg", "kilogram", "kilograms"].includes(measureName)) {
            kgFactor = 1;
        } else if (["т", "тн", "тонна", "тонны", "тонн", "ton", "tons", "tonne", "tonnes"].includes(measureName)) {
            kgFactor = 1000;
        } else if (["г", "гр", "грамм", "граммы", "gram", "grams", "g"].includes(measureName)) {
            kgFactor = 0.001;
        } else {
            // fallback: считаем, что measure уже в кг
            kgFactor = 1;
        }

        return kgFactor;
    }

    function measureNameById(id) {
        if (!id) return "";

        const list = unref(measures) || [];
        const measure = list.find((item) => Number(item.id) === Number(id));

        return measure?.name || "";
    }

    function sourcePricePerKg(rawPriceValue, denominatorValue, measureNameValue) {
        const rawPrice = toNumber(rawPriceValue, 0);
        const denominator = Math.max(toNumber(denominatorValue, 1), 0.000001);
        const kgFactor = kgFactorByMeasureName(measureNameValue);
        const totalKg = denominator * kgFactor;

        if (totalKg <= 0) return 0;

        return rawPrice / totalKg;
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

        return sourcePricePerKg(
            quotation.price,
            quotation.denominator || 1,
            quotation.measure?.name || measureNameById(quotation.measure_id)
        );
    }

    function purchasePricePerKg(purchase) {
        if (!purchase) return 0;

        return sourcePricePerKg(
            purchase.pivot?.price,
            1,
            purchase.pivot?.measure?.name || measureNameById(purchase.pivot?.measure_id)
        );
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
            (quotation) => Number(quotation.id) === Number(formValue.quotation_id)
        ) || null;
    });

    const selectedPurchase = computed(() => {
        const list = unref(purchases) || [];
        const formValue = unref(form);

        if (!formValue?.purchase_id) return null;

        return list.find(
            (purchase) => Number(purchase.id) === Number(formValue.purchase_id)
        ) || null;
    });

    const selectedSource = computed(() => {
        const formValue = unref(form);
        const sourceType = formValue?.sourceType === "purchase" ? "purchase" : "quotation";
        const record = sourceType === "purchase" ? selectedPurchase.value : selectedQuotation.value;

        if (!record) return null;

        return {
            type: sourceType,
            record,
        };
    });

    const result = computed(() => {
        const source = selectedSource.value;
        const formValue = unref(form);

        if (!source || !formValue) return null;

        const vatRate = Math.max(toNumber(formValue.vatRate, 0), 0);
        const boxWeightKg = Math.max(toNumber(formValue.boxWeightKg, 1), 0.001);

        const sourcePricePerKgValue = source.type === "purchase"
            ? purchasePricePerKg(source.record)
            : quotationPricePerKg(source.record);

        let purchasePerKg;

        if (formValue.priceIncludesVat) {
            purchasePerKg = extractVatFromGross(sourcePricePerKgValue, vatRate);
        } else {
            purchasePerKg = addVatToNet(sourcePricePerKgValue, vatRate);
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
            source,
            quotation: source.type === "quotation" ? source.record : null,
            sourcePurchase: source.type === "purchase" ? source.record : null,
            purchase: {
                perKg: purchasePerKg,
            },
            sale: {
                perKg: buildScaledPrice(1),
                perBox: buildScaledPrice(boxWeightKg),
                perTon: buildScaledPrice(1000),
            },
            meta: {
                sourceType: source.type,
                sourcePricePerKg: round2(sourcePricePerKgValue),
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
        selectedPurchase,
        selectedSource,
        result,
        quotationPricePerKg,
        purchasePricePerKg,
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
