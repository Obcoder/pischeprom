import { C as VRow, D as VDataTable, F as VCardText, G as VList, H as VSelect, I as VCardTitle, J as VListItemTitle, K as VDivider, M as VWindowItem, N as VWindow, P as VCard, R as VCardActions, S as VSpacer, T as VContainer, U as VTextField, V as VAutocomplete, W as VMenu, X as VChip, Y as VListItemSubtitle, a as VTabs, at as VProgressCircular, c as VTab, dt as useDate, et as VAlert, g as VFileInput, h as VForm, i as VTextarea, it as VProgressLinear, l as VSwitch, lt as VImg, ot as VIcon, q as VListItem, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { t as _sfc_main$7 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { useForm } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, mergeProps, onBeforeUnmount, onMounted, openBlock, reactive, ref, renderList, toDisplayString, unref, useSSRContext, watch, withCtx, withModifiers } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Composables/useQuotationCalculator.js
function useQuotationCalculator(options) {
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
		if ([
			"кг",
			"kg",
			"kilogram"
		].includes(measureName)) kgFactor = 1;
		else if ([
			"т",
			"тонна",
			"тонн",
			"ton",
			"tonne"
		].includes(measureName)) kgFactor = 1e3;
		else if ([
			"г",
			"гр",
			"gram",
			"g"
		].includes(measureName)) kgFactor = .001;
		else kgFactor = 1;
		const totalKg = denominator * kgFactor;
		if (totalKg <= 0) return 0;
		return rawPrice / totalKg;
	}
	function extractVatFromGross(grossPrice, vatRate) {
		const gross = toNumber(grossPrice, 0);
		const rate = Math.max(toNumber(vatRate, 0), 0);
		if (rate <= 0) return {
			net: round2(gross),
			vat: 0,
			gross: round2(gross)
		};
		const net = gross / (1 + rate / 100);
		const vat = gross - net;
		return {
			net: round2(net),
			vat: round2(vat),
			gross: round2(gross)
		};
	}
	function addVatToNet(netPrice, vatRate) {
		const net = toNumber(netPrice, 0);
		const rate = Math.max(toNumber(vatRate, 0), 0);
		if (rate <= 0) return {
			net: round2(net),
			vat: 0,
			gross: round2(net)
		};
		const gross = net * (1 + rate / 100);
		const vat = gross - net;
		return {
			net: round2(net),
			vat: round2(vat),
			gross: round2(gross)
		};
	}
	function applyMarkup(baseNetPrice, markupType, markupValue) {
		const base = toNumber(baseNetPrice, 0);
		const value = toNumber(markupValue, 0);
		if (markupType === "absolute") return round2(base + value);
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
		return round2(profit / sale * 100);
	}
	function calcMarkupPercent(purchaseNet, saleNet) {
		const purchase = toNumber(purchaseNet, 0);
		const profit = calcProfit(purchaseNet, saleNet);
		if (purchase <= 0) return 0;
		return round2(profit / purchase * 100);
	}
	const selectedQuotation = computed(() => {
		const list = unref(quotations) || [];
		const formValue = unref(form);
		if (!formValue?.quotation_id) return null;
		return list.find((quotation) => quotation.id === formValue.quotation_id) || null;
	});
	return {
		selectedQuotation,
		result: computed(() => {
			const quotation = selectedQuotation.value;
			const formValue = unref(form);
			if (!quotation || !formValue) return null;
			const vatRate = Math.max(toNumber(formValue.vatRate, 0), 0);
			const boxWeightKg = Math.max(toNumber(formValue.boxWeightKg, 1), .001);
			const sourcePricePerKg = quotationPricePerKg(quotation);
			let purchasePerKg;
			if (formValue.priceIncludesVat) purchasePerKg = extractVatFromGross(sourcePricePerKg, vatRate);
			else purchasePerKg = addVatToNet(sourcePricePerKg, vatRate);
			let saleNetPerKg = purchasePerKg.net;
			if (formValue.pricingMode === "targetMargin") saleNetPerKg = calcSaleNetByTargetMargin(purchasePerKg.net, formValue.targetMarginPercent);
			else saleNetPerKg = applyMarkup(purchasePerKg.net, formValue.markupType, formValue.markupValue);
			const salePerKg = addVatToNet(saleNetPerKg, vatRate);
			function buildScaledPrice(multiplier) {
				const purchaseNet = round2(purchasePerKg.net * multiplier);
				const saleNet = round2(salePerKg.net * multiplier);
				return {
					purchaseNet,
					net: saleNet,
					vat: round2(salePerKg.vat * multiplier),
					gross: round2(salePerKg.gross * multiplier),
					profit: calcProfit(purchaseNet, saleNet),
					marginPercent: calcMarginPercent(purchaseNet, saleNet),
					markupPercent: calcMarkupPercent(purchaseNet, saleNet)
				};
			}
			return {
				quotation,
				purchase: { perKg: purchasePerKg },
				sale: {
					perKg: buildScaledPrice(1),
					perBox: buildScaledPrice(boxWeightKg),
					perTon: buildScaledPrice(1e3)
				},
				meta: {
					sourcePricePerKg: round2(sourcePricePerKg),
					vatRate,
					boxWeightKg,
					pricingMode: formValue.pricingMode,
					markupType: formValue.markupType,
					markupValue: toNumber(formValue.markupValue, 0),
					targetMarginPercent: toNumber(formValue.targetMarginPercent, 0),
					priceIncludesVat: Boolean(formValue.priceIncludesVat)
				}
			};
		}),
		quotationPricePerKg,
		extractVatFromGross,
		addVatToNet,
		applyMarkup,
		calcSaleNetByTargetMargin,
		calcProfit,
		calcMarginPercent,
		calcMarkupPercent,
		toNumber,
		round2
	};
}
//#endregion
//#region resources/js/Composables/useGoodPriceCalculations.js
function useGoodPriceCalculations(goodId) {
	const calculations = ref([]);
	const loading = ref(false);
	const saving = ref(false);
	const deleting = ref({});
	async function fetchCalculations() {
		loading.value = true;
		try {
			const { data } = await axios.get(route("api.goods.price-calculations.index", goodId));
			calculations.value = Array.isArray(data) ? data : [];
			return calculations.value;
		} finally {
			loading.value = false;
		}
	}
	async function storeCalculation(payload) {
		saving.value = true;
		try {
			const { data } = await axios.post(route("api.goods.price-calculations.store", goodId), payload);
			calculations.value.unshift(data);
			return data;
		} finally {
			saving.value = false;
		}
	}
	async function updateCalculation(calculationId, payload) {
		saving.value = true;
		try {
			const { data } = await axios.patch(route("api.goods.price-calculations.update", {
				good: goodId,
				calculation: calculationId
			}), payload);
			calculations.value = calculations.value.map((item) => item.id === data.id ? data : item);
			return data;
		} finally {
			saving.value = false;
		}
	}
	async function deleteCalculation(calculationId) {
		deleting.value[calculationId] = true;
		try {
			await axios.delete(route("api.goods.price-calculations.destroy", {
				good: goodId,
				calculation: calculationId
			}));
			calculations.value = calculations.value.filter((item) => item.id !== calculationId);
		} finally {
			deleting.value[calculationId] = false;
		}
	}
	return {
		calculations,
		loading,
		saving,
		deleting,
		fetchCalculations,
		storeCalculation,
		updateCalculation,
		deleteCalculation
	};
}
//#endregion
//#region resources/js/Components/GoodQuotationCalculator.vue
var _sfc_main$6 = {
	__name: "GoodQuotationCalculator",
	__ssrInlineRender: true,
	props: {
		goodId: {
			type: Number,
			required: true
		},
		quotations: {
			type: Array,
			default: () => []
		},
		defaultVatRate: {
			type: Number,
			default: 20
		},
		defaultBoxWeightKg: {
			type: Number,
			default: 1
		},
		currencyCode: {
			type: String,
			default: "RUB"
		}
	},
	emits: ["saved"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const { storeCalculation, saving } = useGoodPriceCalculations(props.goodId);
		function toNumber(value, fallback = 0) {
			const number = Number(value);
			return Number.isFinite(number) ? number : fallback;
		}
		function formatMoney(value) {
			return new Intl.NumberFormat("ru-RU", {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			}).format(toNumber(value));
		}
		const form = ref({
			quotation_id: null,
			vatRate: props.defaultVatRate,
			priceIncludesVat: true,
			pricingMode: "markup",
			markupType: "percent",
			markupValue: 0,
			targetMarginPercent: 20,
			boxWeightKg: props.defaultBoxWeightKg ?? 1
		});
		const saveDialog = ref(false);
		const saveForm = reactive({
			name: "",
			comment: ""
		});
		watch(() => props.defaultBoxWeightKg, (value) => {
			const weight = Number(value);
			if (Number.isFinite(weight) && weight > 0) form.value.boxWeightKg = weight;
		}, { immediate: true });
		watch(() => props.defaultVatRate, (value) => {
			const vat = Number(value);
			if (Number.isFinite(vat) && vat >= 0) form.value.vatRate = vat;
		}, { immediate: true });
		const { result } = useQuotationCalculator({
			quotations: props.quotations,
			form
		});
		const selectedQuotation = computed(() => {
			return props.quotations.find((item) => Number(item.id) === Number(form.value.quotation_id)) || null;
		});
		const canSaveCalculation = computed(() => {
			return !!result.value && !!form.value.quotation_id && !!props.goodId;
		});
		function defaultCalculationName() {
			const source = selectedQuotation.value?.unit?.name || "quotation";
			if (form.value.pricingMode === "targetMargin") return `Расчёт от ${source}: маржа ${form.value.targetMarginPercent}%`;
			if (form.value.markupType === "percent") return `Расчёт от ${source}: наценка ${form.value.markupValue}%`;
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
					quotation: selectedQuotation.value ? {
						id: selectedQuotation.value.id,
						unit_id: selectedQuotation.value.unit_id,
						unit_name: selectedQuotation.value.unit?.name || null,
						price: selectedQuotation.value.price,
						measure_id: selectedQuotation.value.measure_id,
						measure_name: selectedQuotation.value.measure?.name || null,
						denominator: selectedQuotation.value.denominator || 1
					} : null
				},
				result: r,
				purchase_net_per_kg: r?.purchase?.perKg?.net ?? null,
				sale_net_per_kg: r?.sale?.perKg?.net ?? null,
				sale_gross_per_kg: r?.sale?.perKg?.gross ?? null,
				sale_net_per_box: r?.sale?.perBox?.net ?? null,
				sale_gross_per_box: r?.sale?.perBox?.gross ?? null,
				profit_per_kg: r?.sale?.perKg?.profit ?? null,
				margin_percent: r?.sale?.perKg?.marginPercent ?? null,
				markup_percent: r?.sale?.perKg?.markupPercent ?? null
			};
		}
		async function saveCalculation() {
			if (!canSaveCalculation.value) return;
			const saved = await storeCalculation(calculationPayload());
			saveDialog.value = false;
			emit("saved", saved);
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<span${_scopeId}>Расчет продажной цены</span>`);
									_push(ssrRenderComponent(VBtn, {
										color: "deep-purple-darken-1",
										variant: "tonal",
										"prepend-icon": "mdi-content-save",
										disabled: !canSaveCalculation.value,
										loading: unref(saving),
										onClick: openSaveDialog
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Сохранить расчёт `);
											else return [createTextVNode(" Сохранить расчёт ")];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode("span", null, "Расчет продажной цены"), createVNode(VBtn, {
									color: "deep-purple-darken-1",
									variant: "tonal",
									"prepend-icon": "mdi-content-save",
									disabled: !canSaveCalculation.value,
									loading: unref(saving),
									onClick: openSaveDialog
								}, {
									default: withCtx(() => [createTextVNode(" Сохранить расчёт ")]),
									_: 1
								}, 8, ["disabled", "loading"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VRow, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSelect, {
															modelValue: form.value.quotation_id,
															"onUpdate:modelValue": ($event) => form.value.quotation_id = $event,
															items: __props.quotations,
															"item-value": "id",
															label: "Выберите quotation",
															variant: "solo",
															clearable: ""
														}, {
															item: withCtx(({ props, item }, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VListItem, mergeProps(props, {
																	title: `${item.raw.unit?.name || "-"} | ${formatMoney(item.raw.price)} ${__props.currencyCode}`,
																	subtitle: `${item.raw.measure?.name || "-"} | делитель quotation: ${item.raw.denominator || 1}`
																}), null, _parent, _scopeId));
																else return [createVNode(VListItem, mergeProps(props, {
																	title: `${item.raw.unit?.name || "-"} | ${formatMoney(item.raw.price)} ${__props.currencyCode}`,
																	subtitle: `${item.raw.measure?.name || "-"} | делитель quotation: ${item.raw.denominator || 1}`
																}), null, 16, ["title", "subtitle"])];
															}),
															selection: withCtx(({ item }, _push, _parent, _scopeId) => {
																if (_push) _push(`<span${_scopeId}>${ssrInterpolate(item.raw.unit?.name || "-")} | ${ssrInterpolate(formatMoney(item.raw.price))} ${ssrInterpolate(__props.currencyCode)}</span>`);
																else return [createVNode("span", null, toDisplayString(item.raw.unit?.name || "-") + " | " + toDisplayString(formatMoney(item.raw.price)) + " " + toDisplayString(__props.currencyCode), 1)];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VSelect, {
															modelValue: form.value.quotation_id,
															"onUpdate:modelValue": ($event) => form.value.quotation_id = $event,
															items: __props.quotations,
															"item-value": "id",
															label: "Выберите quotation",
															variant: "solo",
															clearable: ""
														}, {
															item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
																title: `${item.raw.unit?.name || "-"} | ${formatMoney(item.raw.price)} ${__props.currencyCode}`,
																subtitle: `${item.raw.measure?.name || "-"} | делитель quotation: ${item.raw.denominator || 1}`
															}), null, 16, ["title", "subtitle"])]),
															selection: withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.raw.unit?.name || "-") + " | " + toDisplayString(formatMoney(item.raw.price)) + " " + toDisplayString(__props.currencyCode), 1)]),
															_: 1
														}, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.value.vatRate,
															"onUpdate:modelValue": ($event) => form.value.vatRate = $event,
															label: "НДС %",
															type: "number",
															min: "0",
															step: "0.01",
															variant: "solo"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.value.vatRate,
															"onUpdate:modelValue": ($event) => form.value.vatRate = $event,
															label: "НДС %",
															type: "number",
															min: "0",
															step: "0.01",
															variant: "solo"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.value.boxWeightKg,
															"onUpdate:modelValue": ($event) => form.value.boxWeightKg = $event,
															label: "Вес коробки, кг",
															type: "number",
															min: "0.001",
															step: "0.001",
															variant: "solo",
															hint: "По умолчанию берется из good.denominator",
															"persistent-hint": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.value.boxWeightKg,
															"onUpdate:modelValue": ($event) => form.value.boxWeightKg = $event,
															label: "Вес коробки, кг",
															type: "number",
															min: "0.001",
															step: "0.001",
															variant: "solo",
															hint: "По умолчанию берется из good.denominator",
															"persistent-hint": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: form.value.quotation_id,
														"onUpdate:modelValue": ($event) => form.value.quotation_id = $event,
														items: __props.quotations,
														"item-value": "id",
														label: "Выберите quotation",
														variant: "solo",
														clearable: ""
													}, {
														item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
															title: `${item.raw.unit?.name || "-"} | ${formatMoney(item.raw.price)} ${__props.currencyCode}`,
															subtitle: `${item.raw.measure?.name || "-"} | делитель quotation: ${item.raw.denominator || 1}`
														}), null, 16, ["title", "subtitle"])]),
														selection: withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.raw.unit?.name || "-") + " | " + toDisplayString(formatMoney(item.raw.price)) + " " + toDisplayString(__props.currencyCode), 1)]),
														_: 1
													}, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.value.vatRate,
														"onUpdate:modelValue": ($event) => form.value.vatRate = $event,
														label: "НДС %",
														type: "number",
														min: "0",
														step: "0.01",
														variant: "solo"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.value.boxWeightKg,
														"onUpdate:modelValue": ($event) => form.value.boxWeightKg = $event,
														label: "Вес коробки, кг",
														type: "number",
														min: "0.001",
														step: "0.001",
														variant: "solo",
														hint: "По умолчанию берется из good.denominator",
														"persistent-hint": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VRow, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSwitch, {
															modelValue: form.value.priceIncludesVat,
															"onUpdate:modelValue": ($event) => form.value.priceIncludesVat = $event,
															label: "Quotation с НДС",
															color: "primary",
															inset: ""
														}, null, _parent, _scopeId));
														else return [createVNode(VSwitch, {
															modelValue: form.value.priceIncludesVat,
															"onUpdate:modelValue": ($event) => form.value.priceIncludesVat = $event,
															label: "Quotation с НДС",
															color: "primary",
															inset: ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSelect, {
															modelValue: form.value.pricingMode,
															"onUpdate:modelValue": ($event) => form.value.pricingMode = $event,
															items: [{
																title: "Расчет по наценке",
																value: "markup"
															}, {
																title: "Расчет по целевой марже",
																value: "targetMargin"
															}],
															"item-title": "title",
															"item-value": "value",
															label: "Режим расчета",
															variant: "solo"
														}, null, _parent, _scopeId));
														else return [createVNode(VSelect, {
															modelValue: form.value.pricingMode,
															"onUpdate:modelValue": ($event) => form.value.pricingMode = $event,
															items: [{
																title: "Расчет по наценке",
																value: "markup"
															}, {
																title: "Расчет по целевой марже",
																value: "targetMargin"
															}],
															"item-title": "title",
															"item-value": "value",
															label: "Режим расчета",
															variant: "solo"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VSwitch, {
													modelValue: form.value.priceIncludesVat,
													"onUpdate:modelValue": ($event) => form.value.priceIncludesVat = $event,
													label: "Quotation с НДС",
													color: "primary",
													inset: ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}), createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: form.value.pricingMode,
													"onUpdate:modelValue": ($event) => form.value.pricingMode = $event,
													items: [{
														title: "Расчет по наценке",
														value: "markup"
													}, {
														title: "Расчет по целевой марже",
														value: "targetMargin"
													}],
													"item-title": "title",
													"item-value": "value",
													label: "Режим расчета",
													variant: "solo"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VRow, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) if (form.value.pricingMode === "markup") {
												_push(`<!--[-->`);
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSelect, {
															modelValue: form.value.markupType,
															"onUpdate:modelValue": ($event) => form.value.markupType = $event,
															items: [{
																title: "Наценка %",
																value: "percent"
															}, {
																title: "Наценка суммой",
																value: "absolute"
															}],
															"item-title": "title",
															"item-value": "value",
															label: "Тип наценки",
															variant: "solo"
														}, null, _parent, _scopeId));
														else return [createVNode(VSelect, {
															modelValue: form.value.markupType,
															"onUpdate:modelValue": ($event) => form.value.markupType = $event,
															items: [{
																title: "Наценка %",
																value: "percent"
															}, {
																title: "Наценка суммой",
																value: "absolute"
															}],
															"item-title": "title",
															"item-value": "value",
															label: "Тип наценки",
															variant: "solo"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.value.markupValue,
															"onUpdate:modelValue": ($event) => form.value.markupValue = $event,
															label: form.value.markupType === "percent" ? "Наценка %" : "Абсолютная наценка на 1 кг",
															type: "number",
															step: "0.01",
															variant: "solo"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.value.markupValue,
															"onUpdate:modelValue": ($event) => form.value.markupValue = $event,
															label: form.value.markupType === "percent" ? "Наценка %" : "Абсолютная наценка на 1 кг",
															type: "number",
															step: "0.01",
															variant: "solo"
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"label"
														])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`<!--]-->`);
											} else _push(ssrRenderComponent(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VTextField, {
														modelValue: form.value.targetMarginPercent,
														"onUpdate:modelValue": ($event) => form.value.targetMarginPercent = $event,
														label: "Целевая маржа %",
														type: "number",
														min: "0",
														max: "99.99",
														step: "0.01",
														variant: "solo",
														hint: "Маржа считается по цене без НДС",
														"persistent-hint": ""
													}, null, _parent, _scopeId));
													else return [createVNode(VTextField, {
														modelValue: form.value.targetMarginPercent,
														"onUpdate:modelValue": ($event) => form.value.targetMarginPercent = $event,
														label: "Целевая маржа %",
														type: "number",
														min: "0",
														max: "99.99",
														step: "0.01",
														variant: "solo",
														hint: "Маржа считается по цене без НДС",
														"persistent-hint": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [form.value.pricingMode === "markup" ? (openBlock(), createBlock(Fragment, { key: 0 }, [createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: form.value.markupType,
													"onUpdate:modelValue": ($event) => form.value.markupType = $event,
													items: [{
														title: "Наценка %",
														value: "percent"
													}, {
														title: "Наценка суммой",
														value: "absolute"
													}],
													"item-title": "title",
													"item-value": "value",
													label: "Тип наценки",
													variant: "solo"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}), createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.value.markupValue,
													"onUpdate:modelValue": ($event) => form.value.markupValue = $event,
													label: form.value.markupType === "percent" ? "Наценка %" : "Абсолютная наценка на 1 кг",
													type: "number",
													step: "0.01",
													variant: "solo"
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"label"
												])]),
												_: 1
											})], 64)) : (openBlock(), createBlock(VCol, {
												key: 1,
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.value.targetMarginPercent,
													"onUpdate:modelValue": ($event) => form.value.targetMarginPercent = $event,
													label: "Целевая маржа %",
													type: "number",
													min: "0",
													max: "99.99",
													step: "0.01",
													variant: "solo",
													hint: "Маржа считается по цене без НДС",
													"persistent-hint": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}))];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VDivider, { class: "my-4" }, null, _parent, _scopeId));
									if (unref(result)) {
										_push(`<!--[-->`);
										_push(ssrRenderComponent(VRow, { class: "mb-2" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VAlert, {
															type: "info",
															variant: "tonal"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	_push(`<div${_scopeId}> Базовая цена quotation, приведенная к 1 кг: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).meta.sourcePricePerKg))} ${ssrInterpolate(__props.currencyCode)}</strong></div>`);
																	if (form.value.pricingMode === "targetMargin") _push(`<div class="mt-1"${_scopeId}> Расчет выполнен по целевой марже: <strong${_scopeId}>${ssrInterpolate(unref(result).meta.targetMarginPercent)}%</strong></div>`);
																	else _push(`<div class="mt-1"${_scopeId}> Расчет выполнен по наценке. </div>`);
																} else return [createVNode("div", null, [createTextVNode(" Базовая цена quotation, приведенная к 1 кг: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).meta.sourcePricePerKg)) + " " + toDisplayString(__props.currencyCode), 1)]), form.value.pricingMode === "targetMargin" ? (openBlock(), createBlock("div", {
																	key: 0,
																	class: "mt-1"
																}, [createTextVNode(" Расчет выполнен по целевой марже: "), createVNode("strong", null, toDisplayString(unref(result).meta.targetMarginPercent) + "%", 1)])) : (openBlock(), createBlock("div", {
																	key: 1,
																	class: "mt-1"
																}, " Расчет выполнен по наценке. "))];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VAlert, {
															type: "info",
															variant: "tonal"
														}, {
															default: withCtx(() => [createVNode("div", null, [createTextVNode(" Базовая цена quotation, приведенная к 1 кг: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).meta.sourcePricePerKg)) + " " + toDisplayString(__props.currencyCode), 1)]), form.value.pricingMode === "targetMargin" ? (openBlock(), createBlock("div", {
																key: 0,
																class: "mt-1"
															}, [createTextVNode(" Расчет выполнен по целевой марже: "), createVNode("strong", null, toDisplayString(unref(result).meta.targetMarginPercent) + "%", 1)])) : (openBlock(), createBlock("div", {
																key: 1,
																class: "mt-1"
															}, " Расчет выполнен по наценке. "))]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												else return [createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VAlert, {
														type: "info",
														variant: "tonal"
													}, {
														default: withCtx(() => [createVNode("div", null, [createTextVNode(" Базовая цена quotation, приведенная к 1 кг: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).meta.sourcePricePerKg)) + " " + toDisplayString(__props.currencyCode), 1)]), form.value.pricingMode === "targetMargin" ? (openBlock(), createBlock("div", {
															key: 0,
															class: "mt-1"
														}, [createTextVNode(" Расчет выполнен по целевой марже: "), createVNode("strong", null, toDisplayString(unref(result).meta.targetMarginPercent) + "%", 1)])) : (openBlock(), createBlock("div", {
															key: 1,
															class: "mt-1"
														}, " Расчет выполнен по наценке. "))]),
														_: 1
													})]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(ssrRenderComponent(VRow, null, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(ssrRenderComponent(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VCard, { variant: "tonal" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VCardTitle, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`Закупка / 1 кг`);
																				else return [createTextVNode("Закупка / 1 кг")];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCardText, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`<div${_scopeId}> Без НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).purchase.perKg.net))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).purchase.perKg.vat))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> С НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).purchase.perKg.gross))} ${ssrInterpolate(__props.currencyCode)}</strong></div>`);
																				else return [
																					createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																					createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																					createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)])
																				];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																	} else return [createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Закупка / 1 кг")]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [
																			createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																			createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																			createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)])
																		]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VCard, { variant: "tonal" }, {
																default: withCtx(() => [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Закупка / 1 кг")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [
																		createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)])
																	]),
																	_: 1
																})]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VCard, { variant: "tonal" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VCardTitle, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`Продажа / 1 кг`);
																				else return [createTextVNode("Продажа / 1 кг")];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCardText, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(`<div${_scopeId}> Без НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perKg.net))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perKg.vat))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> С НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perKg.gross))} ${ssrInterpolate(__props.currencyCode)}</strong></div>`);
																					_push(ssrRenderComponent(VDivider, { class: "my-2" }, null, _parent, _scopeId));
																					_push(`<div${_scopeId}> Прибыль без НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perKg.profit))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> Маржа: <strong${_scopeId}>${ssrInterpolate(unref(result).sale.perKg.marginPercent)}%</strong></div><div${_scopeId}> Наценка к закупке: <strong${_scopeId}>${ssrInterpolate(unref(result).sale.perKg.markupPercent)}%</strong></div>`);
																				} else return [
																					createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																					createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																					createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																					createVNode(VDivider, { class: "my-2" }),
																					createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																					createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.marginPercent) + "%", 1)]),
																					createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.markupPercent) + "%", 1)])
																				];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																	} else return [createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Продажа / 1 кг")]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [
																			createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																			createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																			createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																			createVNode(VDivider, { class: "my-2" }),
																			createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																			createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.marginPercent) + "%", 1)]),
																			createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.markupPercent) + "%", 1)])
																		]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VCard, { variant: "tonal" }, {
																default: withCtx(() => [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Продажа / 1 кг")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [
																		createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode(VDivider, { class: "my-2" }),
																		createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.marginPercent) + "%", 1)]),
																		createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.markupPercent) + "%", 1)])
																	]),
																	_: 1
																})]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VCard, { variant: "tonal" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VCardTitle, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`Продажа / коробка`);
																				else return [createTextVNode("Продажа / коробка")];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCardText, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(`<div${_scopeId}> Без НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perBox.net))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perBox.vat))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> С НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perBox.gross))} ${ssrInterpolate(__props.currencyCode)}</strong></div>`);
																					_push(ssrRenderComponent(VDivider, { class: "my-2" }, null, _parent, _scopeId));
																					_push(`<div${_scopeId}> Прибыль без НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perBox.profit))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> Маржа: <strong${_scopeId}>${ssrInterpolate(unref(result).sale.perBox.marginPercent)}%</strong></div><div${_scopeId}> Наценка к закупке: <strong${_scopeId}>${ssrInterpolate(unref(result).sale.perBox.markupPercent)}%</strong></div>`);
																				} else return [
																					createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																					createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																					createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																					createVNode(VDivider, { class: "my-2" }),
																					createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																					createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perBox.marginPercent) + "%", 1)]),
																					createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perBox.markupPercent) + "%", 1)])
																				];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																	} else return [createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Продажа / коробка")]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [
																			createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																			createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																			createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																			createVNode(VDivider, { class: "my-2" }),
																			createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																			createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perBox.marginPercent) + "%", 1)]),
																			createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perBox.markupPercent) + "%", 1)])
																		]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VCard, { variant: "tonal" }, {
																default: withCtx(() => [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Продажа / коробка")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [
																		createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode(VDivider, { class: "my-2" }),
																		createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perBox.marginPercent) + "%", 1)]),
																		createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perBox.markupPercent) + "%", 1)])
																	]),
																	_: 1
																})]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
												} else return [
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Закупка / 1 кг")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [
																	createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)])
																]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Продажа / 1 кг")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [
																	createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode(VDivider, { class: "my-2" }),
																	createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.marginPercent) + "%", 1)]),
																	createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.markupPercent) + "%", 1)])
																]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Продажа / коробка")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [
																	createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode(VDivider, { class: "my-2" }),
																	createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perBox.marginPercent) + "%", 1)]),
																	createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perBox.markupPercent) + "%", 1)])
																]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})
												];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(ssrRenderComponent(VRow, { class: "mt-2" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VCard, { variant: "tonal" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	_push(ssrRenderComponent(VCardTitle, null, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(`Продажа / 1 тонна`);
																			else return [createTextVNode("Продажа / 1 тонна")];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VCardText, null, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) {
																				_push(`<div${_scopeId}> Без НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perTon.net))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perTon.vat))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> С НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perTon.gross))} ${ssrInterpolate(__props.currencyCode)}</strong></div>`);
																				_push(ssrRenderComponent(VDivider, { class: "my-2" }, null, _parent, _scopeId));
																				_push(`<div${_scopeId}> Прибыль без НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perTon.profit))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> Маржа: <strong${_scopeId}>${ssrInterpolate(unref(result).sale.perTon.marginPercent)}%</strong></div><div${_scopeId}> Наценка к закупке: <strong${_scopeId}>${ssrInterpolate(unref(result).sale.perTon.markupPercent)}%</strong></div>`);
																			} else return [
																				createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																				createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																				createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																				createVNode(VDivider, { class: "my-2" }),
																				createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																				createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perTon.marginPercent) + "%", 1)]),
																				createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perTon.markupPercent) + "%", 1)])
																			];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																} else return [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Продажа / 1 тонна")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [
																		createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode(VDivider, { class: "my-2" }),
																		createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																		createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perTon.marginPercent) + "%", 1)]),
																		createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perTon.markupPercent) + "%", 1)])
																	]),
																	_: 1
																})];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VCard, { variant: "tonal" }, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Продажа / 1 тонна")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [
																	createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode(VDivider, { class: "my-2" }),
																	createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perTon.marginPercent) + "%", 1)]),
																	createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perTon.markupPercent) + "%", 1)])
																]),
																_: 1
															})]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												else return [createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Продажа / 1 тонна")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [
																createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode(VDivider, { class: "my-2" }),
																createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perTon.marginPercent) + "%", 1)]),
																createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perTon.markupPercent) + "%", 1)])
															]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(`<!--]-->`);
									} else _push(ssrRenderComponent(VAlert, {
										type: "info",
										variant: "tonal"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Выберите quotation для расчета. `);
											else return [createTextVNode(" Выберите quotation для расчета. ")];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VRow, null, {
										default: withCtx(() => [
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: form.value.quotation_id,
													"onUpdate:modelValue": ($event) => form.value.quotation_id = $event,
													items: __props.quotations,
													"item-value": "id",
													label: "Выберите quotation",
													variant: "solo",
													clearable: ""
												}, {
													item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
														title: `${item.raw.unit?.name || "-"} | ${formatMoney(item.raw.price)} ${__props.currencyCode}`,
														subtitle: `${item.raw.measure?.name || "-"} | делитель quotation: ${item.raw.denominator || 1}`
													}), null, 16, ["title", "subtitle"])]),
													selection: withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.raw.unit?.name || "-") + " | " + toDisplayString(formatMoney(item.raw.price)) + " " + toDisplayString(__props.currencyCode), 1)]),
													_: 1
												}, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.value.vatRate,
													"onUpdate:modelValue": ($event) => form.value.vatRate = $event,
													label: "НДС %",
													type: "number",
													min: "0",
													step: "0.01",
													variant: "solo"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.value.boxWeightKg,
													"onUpdate:modelValue": ($event) => form.value.boxWeightKg = $event,
													label: "Вес коробки, кг",
													type: "number",
													min: "0.001",
													step: "0.001",
													variant: "solo",
													hint: "По умолчанию берется из good.denominator",
													"persistent-hint": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})
										]),
										_: 1
									}),
									createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VSwitch, {
												modelValue: form.value.priceIncludesVat,
												"onUpdate:modelValue": ($event) => form.value.priceIncludesVat = $event,
												label: "Quotation с НДС",
												color: "primary",
												inset: ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}), createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: form.value.pricingMode,
												"onUpdate:modelValue": ($event) => form.value.pricingMode = $event,
												items: [{
													title: "Расчет по наценке",
													value: "markup"
												}, {
													title: "Расчет по целевой марже",
													value: "targetMargin"
												}],
												"item-title": "title",
												"item-value": "value",
												label: "Режим расчета",
												variant: "solo"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VRow, null, {
										default: withCtx(() => [form.value.pricingMode === "markup" ? (openBlock(), createBlock(Fragment, { key: 0 }, [createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: form.value.markupType,
												"onUpdate:modelValue": ($event) => form.value.markupType = $event,
												items: [{
													title: "Наценка %",
													value: "percent"
												}, {
													title: "Наценка суммой",
													value: "absolute"
												}],
												"item-title": "title",
												"item-value": "value",
												label: "Тип наценки",
												variant: "solo"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}), createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.value.markupValue,
												"onUpdate:modelValue": ($event) => form.value.markupValue = $event,
												label: form.value.markupType === "percent" ? "Наценка %" : "Абсолютная наценка на 1 кг",
												type: "number",
												step: "0.01",
												variant: "solo"
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"label"
											])]),
											_: 1
										})], 64)) : (openBlock(), createBlock(VCol, {
											key: 1,
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.value.targetMarginPercent,
												"onUpdate:modelValue": ($event) => form.value.targetMarginPercent = $event,
												label: "Целевая маржа %",
												type: "number",
												min: "0",
												max: "99.99",
												step: "0.01",
												variant: "solo",
												hint: "Маржа считается по цене без НДС",
												"persistent-hint": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}))]),
										_: 1
									}),
									createVNode(VDivider, { class: "my-4" }),
									unref(result) ? (openBlock(), createBlock(Fragment, { key: 0 }, [
										createVNode(VRow, { class: "mb-2" }, {
											default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VAlert, {
													type: "info",
													variant: "tonal"
												}, {
													default: withCtx(() => [createVNode("div", null, [createTextVNode(" Базовая цена quotation, приведенная к 1 кг: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).meta.sourcePricePerKg)) + " " + toDisplayString(__props.currencyCode), 1)]), form.value.pricingMode === "targetMargin" ? (openBlock(), createBlock("div", {
														key: 0,
														class: "mt-1"
													}, [createTextVNode(" Расчет выполнен по целевой марже: "), createVNode("strong", null, toDisplayString(unref(result).meta.targetMarginPercent) + "%", 1)])) : (openBlock(), createBlock("div", {
														key: 1,
														class: "mt-1"
													}, " Расчет выполнен по наценке. "))]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VRow, null, {
											default: withCtx(() => [
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Закупка / 1 кг")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [
																createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)])
															]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Продажа / 1 кг")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [
																createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode(VDivider, { class: "my-2" }),
																createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.marginPercent) + "%", 1)]),
																createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.markupPercent) + "%", 1)])
															]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Продажа / коробка")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [
																createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode(VDivider, { class: "my-2" }),
																createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perBox.marginPercent) + "%", 1)]),
																createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perBox.markupPercent) + "%", 1)])
															]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})
											]),
											_: 1
										}),
										createVNode(VRow, { class: "mt-2" }, {
											default: withCtx(() => [createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
													default: withCtx(() => [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Продажа / 1 тонна")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [
															createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode(VDivider, { class: "my-2" }),
															createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perTon.marginPercent) + "%", 1)]),
															createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perTon.markupPercent) + "%", 1)])
														]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})
									], 64)) : (openBlock(), createBlock(VAlert, {
										key: 1,
										type: "info",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(" Выберите quotation для расчета. ")]),
										_: 1
									}))
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: saveDialog.value,
							"onUpdate:modelValue": ($event) => saveDialog.value = $event,
							width: "620"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Сохранить расчёт продажной цены`);
													else return [createTextVNode("Сохранить расчёт продажной цены")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VTextField, {
															modelValue: saveForm.name,
															"onUpdate:modelValue": ($event) => saveForm.name = $event,
															label: "Название расчёта",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextarea, {
															modelValue: saveForm.comment,
															"onUpdate:modelValue": ($event) => saveForm.comment = $event,
															label: "Комментарий",
															variant: "outlined",
															density: "compact",
															rows: "4"
														}, null, _parent, _scopeId));
														if (unref(result)) _push(ssrRenderComponent(VAlert, {
															type: "success",
															variant: "tonal",
															class: "mt-3"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`<div${_scopeId}> Продажа / 1 кг с НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perKg.gross))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> Продажа / коробка с НДС: <strong${_scopeId}>${ssrInterpolate(formatMoney(unref(result).sale.perBox.gross))} ${ssrInterpolate(__props.currencyCode)}</strong></div><div${_scopeId}> Маржа: <strong${_scopeId}>${ssrInterpolate(unref(result).sale.perKg.marginPercent)}%</strong></div>`);
																else return [
																	createVNode("div", null, [createTextVNode(" Продажа / 1 кг с НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" Продажа / коробка с НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																	createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.marginPercent) + "%", 1)])
																];
															}),
															_: 1
														}, _parent, _scopeId));
														else _push(`<!---->`);
													} else return [
														createVNode(VTextField, {
															modelValue: saveForm.name,
															"onUpdate:modelValue": ($event) => saveForm.name = $event,
															label: "Название расчёта",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextarea, {
															modelValue: saveForm.comment,
															"onUpdate:modelValue": ($event) => saveForm.comment = $event,
															label: "Комментарий",
															variant: "outlined",
															density: "compact",
															rows: "4"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														unref(result) ? (openBlock(), createBlock(VAlert, {
															key: 0,
															type: "success",
															variant: "tonal",
															class: "mt-3"
														}, {
															default: withCtx(() => [
																createVNode("div", null, [createTextVNode(" Продажа / 1 кг с НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" Продажа / коробка с НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
																createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.marginPercent) + "%", 1)])
															]),
															_: 1
														})) : createCommentVNode("", true)
													];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => saveDialog.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Отмена `);
																else return [createTextVNode(" Отмена ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "deep-purple-darken-1",
															variant: "tonal",
															loading: unref(saving),
															onClick: saveCalculation
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Сохранить `);
																else return [createTextVNode(" Сохранить ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => saveDialog.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Отмена ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "deep-purple-darken-1",
														variant: "tonal",
														loading: unref(saving),
														onClick: saveCalculation
													}, {
														default: withCtx(() => [createTextVNode(" Сохранить ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Сохранить расчёт продажной цены")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [
													createVNode(VTextField, {
														modelValue: saveForm.name,
														"onUpdate:modelValue": ($event) => saveForm.name = $event,
														label: "Название расчёта",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextarea, {
														modelValue: saveForm.comment,
														"onUpdate:modelValue": ($event) => saveForm.comment = $event,
														label: "Комментарий",
														variant: "outlined",
														density: "compact",
														rows: "4"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													unref(result) ? (openBlock(), createBlock(VAlert, {
														key: 0,
														type: "success",
														variant: "tonal",
														class: "mt-3"
													}, {
														default: withCtx(() => [
															createVNode("div", null, [createTextVNode(" Продажа / 1 кг с НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" Продажа / коробка с НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.marginPercent) + "%", 1)])
														]),
														_: 1
													})) : createCommentVNode("", true)
												]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => saveDialog.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Отмена ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "deep-purple-darken-1",
													variant: "tonal",
													loading: unref(saving),
													onClick: saveCalculation
												}, {
													default: withCtx(() => [createTextVNode(" Сохранить ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, null, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Сохранить расчёт продажной цены")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [
												createVNode(VTextField, {
													modelValue: saveForm.name,
													"onUpdate:modelValue": ($event) => saveForm.name = $event,
													label: "Название расчёта",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextarea, {
													modelValue: saveForm.comment,
													"onUpdate:modelValue": ($event) => saveForm.comment = $event,
													label: "Комментарий",
													variant: "outlined",
													density: "compact",
													rows: "4"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												unref(result) ? (openBlock(), createBlock(VAlert, {
													key: 0,
													type: "success",
													variant: "tonal",
													class: "mt-3"
												}, {
													default: withCtx(() => [
														createVNode("div", null, [createTextVNode(" Продажа / 1 кг с НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
														createVNode("div", null, [createTextVNode(" Продажа / коробка с НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
														createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.marginPercent) + "%", 1)])
													]),
													_: 1
												})) : createCommentVNode("", true)
											]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => saveDialog.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Отмена ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "deep-purple-darken-1",
												variant: "tonal",
												loading: unref(saving),
												onClick: saveCalculation
											}, {
												default: withCtx(() => [createTextVNode(" Сохранить ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx(() => [createVNode("span", null, "Расчет продажной цены"), createVNode(VBtn, {
								color: "deep-purple-darken-1",
								variant: "tonal",
								"prepend-icon": "mdi-content-save",
								disabled: !canSaveCalculation.value,
								loading: unref(saving),
								onClick: openSaveDialog
							}, {
								default: withCtx(() => [createTextVNode(" Сохранить расчёт ")]),
								_: 1
							}, 8, ["disabled", "loading"])]),
							_: 1
						}),
						createVNode(VCardText, null, {
							default: withCtx(() => [
								createVNode(VRow, null, {
									default: withCtx(() => [
										createVNode(VCol, {
											cols: "12",
											md: "6"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: form.value.quotation_id,
												"onUpdate:modelValue": ($event) => form.value.quotation_id = $event,
												items: __props.quotations,
												"item-value": "id",
												label: "Выберите quotation",
												variant: "solo",
												clearable: ""
											}, {
												item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
													title: `${item.raw.unit?.name || "-"} | ${formatMoney(item.raw.price)} ${__props.currencyCode}`,
													subtitle: `${item.raw.measure?.name || "-"} | делитель quotation: ${item.raw.denominator || 1}`
												}), null, 16, ["title", "subtitle"])]),
												selection: withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.raw.unit?.name || "-") + " | " + toDisplayString(formatMoney(item.raw.price)) + " " + toDisplayString(__props.currencyCode), 1)]),
												_: 1
											}, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items"
											])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "3"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.value.vatRate,
												"onUpdate:modelValue": ($event) => form.value.vatRate = $event,
												label: "НДС %",
												type: "number",
												min: "0",
												step: "0.01",
												variant: "solo"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "3"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.value.boxWeightKg,
												"onUpdate:modelValue": ($event) => form.value.boxWeightKg = $event,
												label: "Вес коробки, кг",
												type: "number",
												min: "0.001",
												step: "0.001",
												variant: "solo",
												hint: "По умолчанию берется из good.denominator",
												"persistent-hint": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})
									]),
									_: 1
								}),
								createVNode(VRow, null, {
									default: withCtx(() => [createVNode(VCol, {
										cols: "12",
										md: "4"
									}, {
										default: withCtx(() => [createVNode(VSwitch, {
											modelValue: form.value.priceIncludesVat,
											"onUpdate:modelValue": ($event) => form.value.priceIncludesVat = $event,
											label: "Quotation с НДС",
											color: "primary",
											inset: ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}), createVNode(VCol, {
										cols: "12",
										md: "4"
									}, {
										default: withCtx(() => [createVNode(VSelect, {
											modelValue: form.value.pricingMode,
											"onUpdate:modelValue": ($event) => form.value.pricingMode = $event,
											items: [{
												title: "Расчет по наценке",
												value: "markup"
											}, {
												title: "Расчет по целевой марже",
												value: "targetMargin"
											}],
											"item-title": "title",
											"item-value": "value",
											label: "Режим расчета",
											variant: "solo"
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									})]),
									_: 1
								}),
								createVNode(VRow, null, {
									default: withCtx(() => [form.value.pricingMode === "markup" ? (openBlock(), createBlock(Fragment, { key: 0 }, [createVNode(VCol, {
										cols: "12",
										md: "4"
									}, {
										default: withCtx(() => [createVNode(VSelect, {
											modelValue: form.value.markupType,
											"onUpdate:modelValue": ($event) => form.value.markupType = $event,
											items: [{
												title: "Наценка %",
												value: "percent"
											}, {
												title: "Наценка суммой",
												value: "absolute"
											}],
											"item-title": "title",
											"item-value": "value",
											label: "Тип наценки",
											variant: "solo"
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}), createVNode(VCol, {
										cols: "12",
										md: "4"
									}, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: form.value.markupValue,
											"onUpdate:modelValue": ($event) => form.value.markupValue = $event,
											label: form.value.markupType === "percent" ? "Наценка %" : "Абсолютная наценка на 1 кг",
											type: "number",
											step: "0.01",
											variant: "solo"
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"label"
										])]),
										_: 1
									})], 64)) : (openBlock(), createBlock(VCol, {
										key: 1,
										cols: "12",
										md: "4"
									}, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: form.value.targetMarginPercent,
											"onUpdate:modelValue": ($event) => form.value.targetMarginPercent = $event,
											label: "Целевая маржа %",
											type: "number",
											min: "0",
											max: "99.99",
											step: "0.01",
											variant: "solo",
											hint: "Маржа считается по цене без НДС",
											"persistent-hint": ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}))]),
									_: 1
								}),
								createVNode(VDivider, { class: "my-4" }),
								unref(result) ? (openBlock(), createBlock(Fragment, { key: 0 }, [
									createVNode(VRow, { class: "mb-2" }, {
										default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VAlert, {
												type: "info",
												variant: "tonal"
											}, {
												default: withCtx(() => [createVNode("div", null, [createTextVNode(" Базовая цена quotation, приведенная к 1 кг: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).meta.sourcePricePerKg)) + " " + toDisplayString(__props.currencyCode), 1)]), form.value.pricingMode === "targetMargin" ? (openBlock(), createBlock("div", {
													key: 0,
													class: "mt-1"
												}, [createTextVNode(" Расчет выполнен по целевой марже: "), createVNode("strong", null, toDisplayString(unref(result).meta.targetMarginPercent) + "%", 1)])) : (openBlock(), createBlock("div", {
													key: 1,
													class: "mt-1"
												}, " Расчет выполнен по наценке. "))]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VRow, null, {
										default: withCtx(() => [
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
													default: withCtx(() => [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Закупка / 1 кг")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [
															createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).purchase.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)])
														]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
													default: withCtx(() => [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Продажа / 1 кг")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [
															createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode(VDivider, { class: "my-2" }),
															createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.marginPercent) + "%", 1)]),
															createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.markupPercent) + "%", 1)])
														]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
													default: withCtx(() => [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Продажа / коробка")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [
															createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode(VDivider, { class: "my-2" }),
															createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
															createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perBox.marginPercent) + "%", 1)]),
															createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perBox.markupPercent) + "%", 1)])
														]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})
										]),
										_: 1
									}),
									createVNode(VRow, { class: "mt-2" }, {
										default: withCtx(() => [createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
												default: withCtx(() => [createVNode(VCardTitle, null, {
													default: withCtx(() => [createTextVNode("Продажа / 1 тонна")]),
													_: 1
												}), createVNode(VCardText, null, {
													default: withCtx(() => [
														createVNode("div", null, [createTextVNode(" Без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.net)) + " " + toDisplayString(__props.currencyCode), 1)]),
														createVNode("div", null, [createTextVNode(" НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.vat)) + " " + toDisplayString(__props.currencyCode), 1)]),
														createVNode("div", null, [createTextVNode(" С НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
														createVNode(VDivider, { class: "my-2" }),
														createVNode("div", null, [createTextVNode(" Прибыль без НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perTon.profit)) + " " + toDisplayString(__props.currencyCode), 1)]),
														createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perTon.marginPercent) + "%", 1)]),
														createVNode("div", null, [createTextVNode(" Наценка к закупке: "), createVNode("strong", null, toDisplayString(unref(result).sale.perTon.markupPercent) + "%", 1)])
													]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									})
								], 64)) : (openBlock(), createBlock(VAlert, {
									key: 1,
									type: "info",
									variant: "tonal"
								}, {
									default: withCtx(() => [createTextVNode(" Выберите quotation для расчета. ")]),
									_: 1
								}))
							]),
							_: 1
						}),
						createVNode(VDialog, {
							modelValue: saveDialog.value,
							"onUpdate:modelValue": ($event) => saveDialog.value = $event,
							width: "620"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Сохранить расчёт продажной цены")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [
											createVNode(VTextField, {
												modelValue: saveForm.name,
												"onUpdate:modelValue": ($event) => saveForm.name = $event,
												label: "Название расчёта",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VTextarea, {
												modelValue: saveForm.comment,
												"onUpdate:modelValue": ($event) => saveForm.comment = $event,
												label: "Комментарий",
												variant: "outlined",
												density: "compact",
												rows: "4"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											unref(result) ? (openBlock(), createBlock(VAlert, {
												key: 0,
												type: "success",
												variant: "tonal",
												class: "mt-3"
											}, {
												default: withCtx(() => [
													createVNode("div", null, [createTextVNode(" Продажа / 1 кг с НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perKg.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
													createVNode("div", null, [createTextVNode(" Продажа / коробка с НДС: "), createVNode("strong", null, toDisplayString(formatMoney(unref(result).sale.perBox.gross)) + " " + toDisplayString(__props.currencyCode), 1)]),
													createVNode("div", null, [createTextVNode(" Маржа: "), createVNode("strong", null, toDisplayString(unref(result).sale.perKg.marginPercent) + "%", 1)])
												]),
												_: 1
											})) : createCommentVNode("", true)
										]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => saveDialog.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Отмена ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "deep-purple-darken-1",
											variant: "tonal",
											loading: unref(saving),
											onClick: saveCalculation
										}, {
											default: withCtx(() => [createTextVNode(" Сохранить ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"])
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$6 = _sfc_main$6.setup;
_sfc_main$6.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/GoodQuotationCalculator.vue");
	return _sfc_setup$6 ? _sfc_setup$6(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Composables/useGoodSeo.js
function useGoodSeo(goodId) {
	const seo = ref(null);
	const loading = ref(false);
	const saving = ref(false);
	const generating = ref(false);
	async function fetchSeo() {
		loading.value = true;
		try {
			const { data } = await axios.get(route("api.goods.seo.show", goodId));
			seo.value = data;
			return data;
		} finally {
			loading.value = false;
		}
	}
	async function saveSeo(payload) {
		saving.value = true;
		try {
			const { data } = await axios.put(route("api.goods.seo.upsert", goodId), payload);
			seo.value = data;
			return data;
		} finally {
			saving.value = false;
		}
	}
	async function generateStructuredData() {
		generating.value = true;
		try {
			const { data } = await axios.post(route("api.goods.seo.generate-structured-data", goodId));
			seo.value = data;
			return data;
		} finally {
			generating.value = false;
		}
	}
	return {
		seo,
		loading,
		saving,
		generating,
		fetchSeo,
		saveSeo,
		generateStructuredData
	};
}
//#endregion
//#region resources/js/Components/Goods/GoodSeoTab.vue
var _sfc_main$5 = {
	__name: "GoodSeoTab",
	__ssrInlineRender: true,
	props: { good: {
		type: Object,
		required: true
	} },
	setup(__props) {
		const props = __props;
		const { seo, loading, saving, generating, fetchSeo, saveSeo, generateStructuredData } = useGoodSeo(computed(() => props.good.id).value);
		const form = reactive({
			meta_title: "",
			meta_description: "",
			h1: "",
			slug_override: "",
			canonical_url: "",
			robots: "index,follow",
			og_title: "",
			og_description: "",
			og_image: "",
			twitter_title: "",
			twitter_description: "",
			twitter_image: "",
			short_seo_text: "",
			seo_text: "",
			semantic_core_text: "",
			keywords_text: "",
			search_queries_text: "",
			structured_data_text: "",
			focus_keyword: "",
			breadcrumbs_title: "",
			is_active: true,
			include_in_sitemap: true,
			include_in_yandex_feed: true,
			yandex_direct_title_1: "",
			yandex_direct_title_2: "",
			yandex_direct_text: "",
			utm_template: "",
			availability_status: "on_request",
			min_order: "",
			delivery_note: "",
			payment_note: "",
			faq_text: ""
		});
		function linesToArray(value) {
			return String(value || "").split("\n").map((item) => item.trim()).filter(Boolean);
		}
		function arrayToLines(value) {
			if (!Array.isArray(value)) return "";
			return value.join("\n");
		}
		function fillForm(data) {
			form.meta_title = data?.meta_title || "";
			form.meta_description = data?.meta_description || "";
			form.h1 = data?.h1 || "";
			form.slug_override = data?.slug_override || "";
			form.canonical_url = data?.canonical_url || "";
			form.robots = data?.robots || "index,follow";
			form.og_title = data?.og_title || "";
			form.og_description = data?.og_description || "";
			form.og_image = data?.og_image || "";
			form.twitter_title = data?.twitter_title || "";
			form.twitter_description = data?.twitter_description || "";
			form.twitter_image = data?.twitter_image || "";
			form.short_seo_text = data?.short_seo_text || "";
			form.seo_text = data?.seo_text || "";
			form.semantic_core_text = arrayToLines(data?.semantic_core);
			form.keywords_text = arrayToLines(data?.keywords);
			form.search_queries_text = arrayToLines(data?.search_queries);
			form.structured_data_text = data?.structured_data ? JSON.stringify(data.structured_data, null, 2) : "";
			form.focus_keyword = data?.focus_keyword || "";
			form.breadcrumbs_title = data?.breadcrumbs_title || "";
			form.is_active = data?.is_active ?? true;
			form.include_in_sitemap = data?.include_in_sitemap ?? true;
			form.include_in_yandex_feed = data?.include_in_yandex_feed ?? true;
			form.yandex_direct_title_1 = data?.yandex_direct_title_1 || "";
			form.yandex_direct_title_2 = data?.yandex_direct_title_2 || "";
			form.yandex_direct_text = data?.yandex_direct_text || "";
			form.utm_template = data?.utm_template || "";
			form.availability_status = data?.availability_status || "on_request";
			form.min_order = data?.min_order || "";
			form.delivery_note = data?.delivery_note || "";
			form.payment_note = data?.payment_note || "";
			form.faq_text = arrayToLines((data?.faq || []).map((item) => `${item.question} | ${item.answer}`));
		}
		function payload() {
			let structuredData = null;
			if (form.structured_data_text.trim()) try {
				structuredData = JSON.parse(form.structured_data_text);
			} catch {
				structuredData = null;
			}
			return {
				meta_title: form.meta_title,
				meta_description: form.meta_description,
				h1: form.h1,
				slug_override: form.slug_override,
				canonical_url: form.canonical_url,
				robots: form.robots,
				og_title: form.og_title,
				og_description: form.og_description,
				og_image: form.og_image,
				twitter_title: form.twitter_title,
				twitter_description: form.twitter_description,
				twitter_image: form.twitter_image,
				short_seo_text: form.short_seo_text,
				seo_text: form.seo_text,
				semantic_core: linesToArray(form.semantic_core_text),
				keywords: linesToArray(form.keywords_text),
				search_queries: linesToArray(form.search_queries_text),
				structured_data: structuredData,
				focus_keyword: form.focus_keyword,
				breadcrumbs_title: form.breadcrumbs_title,
				is_active: form.is_active,
				include_in_sitemap: form.include_in_sitemap,
				include_in_yandex_feed: form.include_in_yandex_feed,
				yandex_direct_title_1: form.yandex_direct_title_1,
				yandex_direct_title_2: form.yandex_direct_title_2,
				yandex_direct_text: form.yandex_direct_text,
				utm_template: form.utm_template,
				availability_status: form.availability_status,
				min_order: form.min_order,
				delivery_note: form.delivery_note,
				payment_note: form.payment_note,
				faq: faqToArray(form.faq_text)
			};
		}
		async function generateJsonLd() {
			fillForm(await generateStructuredData());
		}
		function faqToArray(value) {
			return String(value || "").split("\n").map((line) => line.trim()).filter(Boolean).map((line) => {
				const [question, answer] = line.split("|").map((item) => item?.trim());
				return {
					question: question || "",
					answer: answer || ""
				};
			}).filter((item) => item.question && item.answer);
		}
		async function submit() {
			fillForm(await saveSeo(payload()));
		}
		watch(seo, (value) => {
			if (value) fillForm(value);
		});
		onMounted(async () => {
			fillForm(await fetchSeo());
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<span${_scopeId}>SEO товара</span>`);
									_push(ssrRenderComponent(VSwitch, {
										modelValue: form.is_active,
										"onUpdate:modelValue": ($event) => form.is_active = $event,
										label: "SEO активно",
										color: "green",
										"hide-details": "",
										inset: "",
										density: "compact"
									}, null, _parent, _scopeId));
								} else return [createVNode("span", null, "SEO товара"), createVNode(VSwitch, {
									modelValue: form.is_active,
									"onUpdate:modelValue": ($event) => form.is_active = $event,
									label: "SEO активно",
									color: "green",
									"hide-details": "",
									inset: "",
									density: "compact"
								}, null, 8, ["modelValue", "onUpdate:modelValue"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VAlert, {
										type: "info",
										variant: "tonal",
										class: "mb-4"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Здесь храним SEO-данные для будущей публичной карточки товара в интернет-магазине. `);
											else return [createTextVNode(" Здесь храним SEO-данные для будущей публичной карточки товара в интернет-магазине. ")];
										}),
										_: 1
									}, _parent, _scopeId));
									if (unref(loading)) _push(ssrRenderComponent(VProgressLinear, {
										indeterminate: "",
										class: "mb-4"
									}, null, _parent, _scopeId));
									else _push(`<!---->`);
									_push(ssrRenderComponent(VRow, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "8"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.meta_title,
															"onUpdate:modelValue": ($event) => form.meta_title = $event,
															label: "Meta title",
															variant: "outlined",
															density: "compact",
															counter: "255"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.meta_title,
															"onUpdate:modelValue": ($event) => form.meta_title = $event,
															label: "Meta title",
															variant: "outlined",
															density: "compact",
															counter: "255"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.focus_keyword,
															"onUpdate:modelValue": ($event) => form.focus_keyword = $event,
															label: "Фокусный ключ",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.focus_keyword,
															"onUpdate:modelValue": ($event) => form.focus_keyword = $event,
															label: "Фокусный ключ",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.meta_description,
															"onUpdate:modelValue": ($event) => form.meta_description = $event,
															label: "Meta description",
															variant: "outlined",
															density: "compact",
															rows: "3",
															counter: ""
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.meta_description,
															"onUpdate:modelValue": ($event) => form.meta_description = $event,
															label: "Meta description",
															variant: "outlined",
															density: "compact",
															rows: "3",
															counter: ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.h1,
															"onUpdate:modelValue": ($event) => form.h1 = $event,
															label: "H1",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.h1,
															"onUpdate:modelValue": ($event) => form.h1 = $event,
															label: "H1",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.breadcrumbs_title,
															"onUpdate:modelValue": ($event) => form.breadcrumbs_title = $event,
															label: "Название в хлебных крошках",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.breadcrumbs_title,
															"onUpdate:modelValue": ($event) => form.breadcrumbs_title = $event,
															label: "Название в хлебных крошках",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSelect, {
															modelValue: form.robots,
															"onUpdate:modelValue": ($event) => form.robots = $event,
															items: [
																"index,follow",
																"noindex,follow",
																"index,nofollow",
																"noindex,nofollow"
															],
															label: "Robots",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														else return [createVNode(VSelect, {
															modelValue: form.robots,
															"onUpdate:modelValue": ($event) => form.robots = $event,
															items: [
																"index,follow",
																"noindex,follow",
																"index,nofollow",
																"noindex,nofollow"
															],
															label: "Robots",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.slug_override,
															"onUpdate:modelValue": ($event) => form.slug_override = $event,
															label: "SEO slug override",
															variant: "outlined",
															density: "compact",
															hint: "Пока не меняет основной slug товара, только хранится для SEO",
															"persistent-hint": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.slug_override,
															"onUpdate:modelValue": ($event) => form.slug_override = $event,
															label: "SEO slug override",
															variant: "outlined",
															density: "compact",
															hint: "Пока не меняет основной slug товара, только хранится для SEO",
															"persistent-hint": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.canonical_url,
															"onUpdate:modelValue": ($event) => form.canonical_url = $event,
															label: "Canonical URL",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.canonical_url,
															"onUpdate:modelValue": ($event) => form.canonical_url = $event,
															label: "Canonical URL",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VCol, {
													cols: "12",
													md: "8"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.meta_title,
														"onUpdate:modelValue": ($event) => form.meta_title = $event,
														label: "Meta title",
														variant: "outlined",
														density: "compact",
														counter: "255"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.focus_keyword,
														"onUpdate:modelValue": ($event) => form.focus_keyword = $event,
														label: "Фокусный ключ",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.meta_description,
														"onUpdate:modelValue": ($event) => form.meta_description = $event,
														label: "Meta description",
														variant: "outlined",
														density: "compact",
														rows: "3",
														counter: ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.h1,
														"onUpdate:modelValue": ($event) => form.h1 = $event,
														label: "H1",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.breadcrumbs_title,
														"onUpdate:modelValue": ($event) => form.breadcrumbs_title = $event,
														label: "Название в хлебных крошках",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: form.robots,
														"onUpdate:modelValue": ($event) => form.robots = $event,
														items: [
															"index,follow",
															"noindex,follow",
															"index,nofollow",
															"noindex,nofollow"
														],
														label: "Robots",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.slug_override,
														"onUpdate:modelValue": ($event) => form.slug_override = $event,
														label: "SEO slug override",
														variant: "outlined",
														density: "compact",
														hint: "Пока не меняет основной slug товара, только хранится для SEO",
														"persistent-hint": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.canonical_url,
														"onUpdate:modelValue": ($event) => form.canonical_url = $event,
														label: "Canonical URL",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VDivider, { class: "my-4" }, null, _parent, _scopeId));
									_push(ssrRenderComponent(VRow, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VAlert, {
															type: "success",
															variant: "tonal"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Публикация и продвижение `);
																else return [createTextVNode(" Публикация и продвижение ")];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VAlert, {
															type: "success",
															variant: "tonal"
														}, {
															default: withCtx(() => [createTextVNode(" Публикация и продвижение ")]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSwitch, {
															modelValue: form.include_in_sitemap,
															"onUpdate:modelValue": ($event) => form.include_in_sitemap = $event,
															label: "Включить в sitemap",
															color: "green",
															"hide-details": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VSwitch, {
															modelValue: form.include_in_sitemap,
															"onUpdate:modelValue": ($event) => form.include_in_sitemap = $event,
															label: "Включить в sitemap",
															color: "green",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSwitch, {
															modelValue: form.include_in_yandex_feed,
															"onUpdate:modelValue": ($event) => form.include_in_yandex_feed = $event,
															label: "Включить в Yandex Direct feed",
															color: "green",
															"hide-details": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VSwitch, {
															modelValue: form.include_in_yandex_feed,
															"onUpdate:modelValue": ($event) => form.include_in_yandex_feed = $event,
															label: "Включить в Yandex Direct feed",
															color: "green",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSelect, {
															modelValue: form.availability_status,
															"onUpdate:modelValue": ($event) => form.availability_status = $event,
															items: [
																{
																	title: "В наличии",
																	value: "in_stock"
																},
																{
																	title: "По запросу",
																	value: "on_request"
																},
																{
																	title: "Под заказ",
																	value: "preorder"
																},
																{
																	title: "Нет в наличии",
																	value: "out_of_stock"
																}
															],
															"item-title": "title",
															"item-value": "value",
															label: "Наличие",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														else return [createVNode(VSelect, {
															modelValue: form.availability_status,
															"onUpdate:modelValue": ($event) => form.availability_status = $event,
															items: [
																{
																	title: "В наличии",
																	value: "in_stock"
																},
																{
																	title: "По запросу",
																	value: "on_request"
																},
																{
																	title: "Под заказ",
																	value: "preorder"
																},
																{
																	title: "Нет в наличии",
																	value: "out_of_stock"
																}
															],
															"item-title": "title",
															"item-value": "value",
															label: "Наличие",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.min_order,
															"onUpdate:modelValue": ($event) => form.min_order = $event,
															label: "Минимальная партия",
															variant: "outlined",
															density: "compact",
															placeholder: "Например: от 1 паллеты / от 100 кг"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.min_order,
															"onUpdate:modelValue": ($event) => form.min_order = $event,
															label: "Минимальная партия",
															variant: "outlined",
															density: "compact",
															placeholder: "Например: от 1 паллеты / от 100 кг"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.delivery_note,
															"onUpdate:modelValue": ($event) => form.delivery_note = $event,
															label: "Доставка",
															variant: "outlined",
															density: "compact",
															rows: "2"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.delivery_note,
															"onUpdate:modelValue": ($event) => form.delivery_note = $event,
															label: "Доставка",
															variant: "outlined",
															density: "compact",
															rows: "2"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.payment_note,
															"onUpdate:modelValue": ($event) => form.payment_note = $event,
															label: "Оплата",
															variant: "outlined",
															density: "compact",
															rows: "2"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.payment_note,
															"onUpdate:modelValue": ($event) => form.payment_note = $event,
															label: "Оплата",
															variant: "outlined",
															density: "compact",
															rows: "2"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VAlert, {
														type: "success",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(" Публикация и продвижение ")]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VSwitch, {
														modelValue: form.include_in_sitemap,
														"onUpdate:modelValue": ($event) => form.include_in_sitemap = $event,
														label: "Включить в sitemap",
														color: "green",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VSwitch, {
														modelValue: form.include_in_yandex_feed,
														"onUpdate:modelValue": ($event) => form.include_in_yandex_feed = $event,
														label: "Включить в Yandex Direct feed",
														color: "green",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: form.availability_status,
														"onUpdate:modelValue": ($event) => form.availability_status = $event,
														items: [
															{
																title: "В наличии",
																value: "in_stock"
															},
															{
																title: "По запросу",
																value: "on_request"
															},
															{
																title: "Под заказ",
																value: "preorder"
															},
															{
																title: "Нет в наличии",
																value: "out_of_stock"
															}
														],
														"item-title": "title",
														"item-value": "value",
														label: "Наличие",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.min_order,
														"onUpdate:modelValue": ($event) => form.min_order = $event,
														label: "Минимальная партия",
														variant: "outlined",
														density: "compact",
														placeholder: "Например: от 1 паллеты / от 100 кг"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.delivery_note,
														"onUpdate:modelValue": ($event) => form.delivery_note = $event,
														label: "Доставка",
														variant: "outlined",
														density: "compact",
														rows: "2"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.payment_note,
														"onUpdate:modelValue": ($event) => form.payment_note = $event,
														label: "Оплата",
														variant: "outlined",
														density: "compact",
														rows: "2"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VDivider, { class: "my-4" }, null, _parent, _scopeId));
									_push(ssrRenderComponent(VRow, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VAlert, {
															type: "info",
															variant: "tonal"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Данные для Яндекс.Директа `);
																else return [createTextVNode(" Данные для Яндекс.Директа ")];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VAlert, {
															type: "info",
															variant: "tonal"
														}, {
															default: withCtx(() => [createTextVNode(" Данные для Яндекс.Директа ")]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.yandex_direct_title_1,
															"onUpdate:modelValue": ($event) => form.yandex_direct_title_1 = $event,
															label: "Заголовок Директ 1",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.yandex_direct_title_1,
															"onUpdate:modelValue": ($event) => form.yandex_direct_title_1 = $event,
															label: "Заголовок Директ 1",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.yandex_direct_title_2,
															"onUpdate:modelValue": ($event) => form.yandex_direct_title_2 = $event,
															label: "Заголовок Директ 2",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.yandex_direct_title_2,
															"onUpdate:modelValue": ($event) => form.yandex_direct_title_2 = $event,
															label: "Заголовок Директ 2",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.yandex_direct_text,
															"onUpdate:modelValue": ($event) => form.yandex_direct_text = $event,
															label: "Текст объявления",
															variant: "outlined",
															density: "compact",
															rows: "3"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.yandex_direct_text,
															"onUpdate:modelValue": ($event) => form.yandex_direct_text = $event,
															label: "Текст объявления",
															variant: "outlined",
															density: "compact",
															rows: "3"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.utm_template,
															"onUpdate:modelValue": ($event) => form.utm_template = $event,
															label: "UTM-шаблон",
															variant: "outlined",
															density: "compact",
															rows: "2",
															placeholder: "utm_source=yandex&utm_medium=cpc&utm_campaign={campaign_id}&utm_content={ad_id}&utm_term={keyword}"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.utm_template,
															"onUpdate:modelValue": ($event) => form.utm_template = $event,
															label: "UTM-шаблон",
															variant: "outlined",
															density: "compact",
															rows: "2",
															placeholder: "utm_source=yandex&utm_medium=cpc&utm_campaign={campaign_id}&utm_content={ad_id}&utm_term={keyword}"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VAlert, {
														type: "info",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(" Данные для Яндекс.Директа ")]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.yandex_direct_title_1,
														"onUpdate:modelValue": ($event) => form.yandex_direct_title_1 = $event,
														label: "Заголовок Директ 1",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.yandex_direct_title_2,
														"onUpdate:modelValue": ($event) => form.yandex_direct_title_2 = $event,
														label: "Заголовок Директ 2",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.yandex_direct_text,
														"onUpdate:modelValue": ($event) => form.yandex_direct_text = $event,
														label: "Текст объявления",
														variant: "outlined",
														density: "compact",
														rows: "3"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.utm_template,
														"onUpdate:modelValue": ($event) => form.utm_template = $event,
														label: "UTM-шаблон",
														variant: "outlined",
														density: "compact",
														rows: "2",
														placeholder: "utm_source=yandex&utm_medium=cpc&utm_campaign={campaign_id}&utm_content={ad_id}&utm_term={keyword}"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VDivider, { class: "my-4" }, null, _parent, _scopeId));
									_push(ssrRenderComponent(VRow, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.faq_text,
															"onUpdate:modelValue": ($event) => form.faq_text = $event,
															label: "FAQ",
															variant: "outlined",
															density: "compact",
															rows: "5",
															hint: "Формат: вопрос | ответ. Каждый FAQ с новой строки.",
															"persistent-hint": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.faq_text,
															"onUpdate:modelValue": ($event) => form.faq_text = $event,
															label: "FAQ",
															variant: "outlined",
															density: "compact",
															rows: "5",
															hint: "Формат: вопрос | ответ. Каждый FAQ с новой строки.",
															"persistent-hint": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VBtn, {
															color: "teal",
															variant: "tonal",
															loading: unref(generating),
															onClick: generateJsonLd
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Сгенерировать JSON-LD Product `);
																else return [createTextVNode(" Сгенерировать JSON-LD Product ")];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VBtn, {
															color: "teal",
															variant: "tonal",
															loading: unref(generating),
															onClick: generateJsonLd
														}, {
															default: withCtx(() => [createTextVNode(" Сгенерировать JSON-LD Product ")]),
															_: 1
														}, 8, ["loading"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.faq_text,
													"onUpdate:modelValue": ($event) => form.faq_text = $event,
													label: "FAQ",
													variant: "outlined",
													density: "compact",
													rows: "5",
													hint: "Формат: вопрос | ответ. Каждый FAQ с новой строки.",
													"persistent-hint": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}), createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VBtn, {
													color: "teal",
													variant: "tonal",
													loading: unref(generating),
													onClick: generateJsonLd
												}, {
													default: withCtx(() => [createTextVNode(" Сгенерировать JSON-LD Product ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VRow, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.semantic_core_text,
															"onUpdate:modelValue": ($event) => form.semantic_core_text = $event,
															label: "Семантическое ядро",
															variant: "outlined",
															density: "compact",
															rows: "7",
															hint: "Каждый ключ с новой строки",
															"persistent-hint": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.semantic_core_text,
															"onUpdate:modelValue": ($event) => form.semantic_core_text = $event,
															label: "Семантическое ядро",
															variant: "outlined",
															density: "compact",
															rows: "7",
															hint: "Каждый ключ с новой строки",
															"persistent-hint": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.keywords_text,
															"onUpdate:modelValue": ($event) => form.keywords_text = $event,
															label: "Keywords",
															variant: "outlined",
															density: "compact",
															rows: "7",
															hint: "Каждое слово/фраза с новой строки",
															"persistent-hint": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.keywords_text,
															"onUpdate:modelValue": ($event) => form.keywords_text = $event,
															label: "Keywords",
															variant: "outlined",
															density: "compact",
															rows: "7",
															hint: "Каждое слово/фраза с новой строки",
															"persistent-hint": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.search_queries_text,
															"onUpdate:modelValue": ($event) => form.search_queries_text = $event,
															label: "Поисковые запросы",
															variant: "outlined",
															density: "compact",
															rows: "7",
															hint: "Каждый запрос с новой строки",
															"persistent-hint": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.search_queries_text,
															"onUpdate:modelValue": ($event) => form.search_queries_text = $event,
															label: "Поисковые запросы",
															variant: "outlined",
															density: "compact",
															rows: "7",
															hint: "Каждый запрос с новой строки",
															"persistent-hint": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.semantic_core_text,
														"onUpdate:modelValue": ($event) => form.semantic_core_text = $event,
														label: "Семантическое ядро",
														variant: "outlined",
														density: "compact",
														rows: "7",
														hint: "Каждый ключ с новой строки",
														"persistent-hint": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.keywords_text,
														"onUpdate:modelValue": ($event) => form.keywords_text = $event,
														label: "Keywords",
														variant: "outlined",
														density: "compact",
														rows: "7",
														hint: "Каждое слово/фраза с новой строки",
														"persistent-hint": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.search_queries_text,
														"onUpdate:modelValue": ($event) => form.search_queries_text = $event,
														label: "Поисковые запросы",
														variant: "outlined",
														density: "compact",
														rows: "7",
														hint: "Каждый запрос с новой строки",
														"persistent-hint": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VDivider, { class: "my-4" }, null, _parent, _scopeId));
									_push(ssrRenderComponent(VRow, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.og_title,
															"onUpdate:modelValue": ($event) => form.og_title = $event,
															label: "OG title",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.og_title,
															"onUpdate:modelValue": ($event) => form.og_title = $event,
															label: "OG title",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.og_image,
															"onUpdate:modelValue": ($event) => form.og_image = $event,
															label: "OG image URL",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.og_image,
															"onUpdate:modelValue": ($event) => form.og_image = $event,
															label: "OG image URL",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.og_description,
															"onUpdate:modelValue": ($event) => form.og_description = $event,
															label: "OG description",
															variant: "outlined",
															density: "compact",
															rows: "3"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.og_description,
															"onUpdate:modelValue": ($event) => form.og_description = $event,
															label: "OG description",
															variant: "outlined",
															density: "compact",
															rows: "3"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.twitter_title,
															"onUpdate:modelValue": ($event) => form.twitter_title = $event,
															label: "Twitter title",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.twitter_title,
															"onUpdate:modelValue": ($event) => form.twitter_title = $event,
															label: "Twitter title",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: form.twitter_image,
															"onUpdate:modelValue": ($event) => form.twitter_image = $event,
															label: "Twitter image URL",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: form.twitter_image,
															"onUpdate:modelValue": ($event) => form.twitter_image = $event,
															label: "Twitter image URL",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.twitter_description,
															"onUpdate:modelValue": ($event) => form.twitter_description = $event,
															label: "Twitter description",
															variant: "outlined",
															density: "compact",
															rows: "3"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.twitter_description,
															"onUpdate:modelValue": ($event) => form.twitter_description = $event,
															label: "Twitter description",
															variant: "outlined",
															density: "compact",
															rows: "3"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.og_title,
														"onUpdate:modelValue": ($event) => form.og_title = $event,
														label: "OG title",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.og_image,
														"onUpdate:modelValue": ($event) => form.og_image = $event,
														label: "OG image URL",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.og_description,
														"onUpdate:modelValue": ($event) => form.og_description = $event,
														label: "OG description",
														variant: "outlined",
														density: "compact",
														rows: "3"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.twitter_title,
														"onUpdate:modelValue": ($event) => form.twitter_title = $event,
														label: "Twitter title",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.twitter_image,
														"onUpdate:modelValue": ($event) => form.twitter_image = $event,
														label: "Twitter image URL",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.twitter_description,
														"onUpdate:modelValue": ($event) => form.twitter_description = $event,
														label: "Twitter description",
														variant: "outlined",
														density: "compact",
														rows: "3"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VDivider, { class: "my-4" }, null, _parent, _scopeId));
									_push(ssrRenderComponent(VRow, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.short_seo_text,
															"onUpdate:modelValue": ($event) => form.short_seo_text = $event,
															label: "Короткий SEO-текст",
															variant: "outlined",
															density: "compact",
															rows: "3"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.short_seo_text,
															"onUpdate:modelValue": ($event) => form.short_seo_text = $event,
															label: "Короткий SEO-текст",
															variant: "outlined",
															density: "compact",
															rows: "3"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.seo_text,
															"onUpdate:modelValue": ($event) => form.seo_text = $event,
															label: "Большой SEO-текст",
															variant: "outlined",
															density: "compact",
															rows: "8"
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.seo_text,
															"onUpdate:modelValue": ($event) => form.seo_text = $event,
															label: "Большой SEO-текст",
															variant: "outlined",
															density: "compact",
															rows: "8"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextarea, {
															modelValue: form.structured_data_text,
															"onUpdate:modelValue": ($event) => form.structured_data_text = $event,
															label: "Structured data JSON-LD",
															variant: "outlined",
															density: "compact",
															rows: "8",
															hint: "Можно оставить пустым. Позже сделаем автогенерацию Product schema.",
															"persistent-hint": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VTextarea, {
															modelValue: form.structured_data_text,
															"onUpdate:modelValue": ($event) => form.structured_data_text = $event,
															label: "Structured data JSON-LD",
															variant: "outlined",
															density: "compact",
															rows: "8",
															hint: "Можно оставить пустым. Позже сделаем автогенерацию Product schema.",
															"persistent-hint": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.short_seo_text,
														"onUpdate:modelValue": ($event) => form.short_seo_text = $event,
														label: "Короткий SEO-текст",
														variant: "outlined",
														density: "compact",
														rows: "3"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.seo_text,
														"onUpdate:modelValue": ($event) => form.seo_text = $event,
														label: "Большой SEO-текст",
														variant: "outlined",
														density: "compact",
														rows: "8"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.structured_data_text,
														"onUpdate:modelValue": ($event) => form.structured_data_text = $event,
														label: "Structured data JSON-LD",
														variant: "outlined",
														density: "compact",
														rows: "8",
														hint: "Можно оставить пустым. Позже сделаем автогенерацию Product schema.",
														"persistent-hint": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VAlert, {
										type: "info",
										variant: "tonal",
										class: "mb-4"
									}, {
										default: withCtx(() => [createTextVNode(" Здесь храним SEO-данные для будущей публичной карточки товара в интернет-магазине. ")]),
										_: 1
									}),
									unref(loading) ? (openBlock(), createBlock(VProgressLinear, {
										key: 0,
										indeterminate: "",
										class: "mb-4"
									})) : createCommentVNode("", true),
									createVNode(VRow, null, {
										default: withCtx(() => [
											createVNode(VCol, {
												cols: "12",
												md: "8"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.meta_title,
													"onUpdate:modelValue": ($event) => form.meta_title = $event,
													label: "Meta title",
													variant: "outlined",
													density: "compact",
													counter: "255"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.focus_keyword,
													"onUpdate:modelValue": ($event) => form.focus_keyword = $event,
													label: "Фокусный ключ",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.meta_description,
													"onUpdate:modelValue": ($event) => form.meta_description = $event,
													label: "Meta description",
													variant: "outlined",
													density: "compact",
													rows: "3",
													counter: ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.h1,
													"onUpdate:modelValue": ($event) => form.h1 = $event,
													label: "H1",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.breadcrumbs_title,
													"onUpdate:modelValue": ($event) => form.breadcrumbs_title = $event,
													label: "Название в хлебных крошках",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: form.robots,
													"onUpdate:modelValue": ($event) => form.robots = $event,
													items: [
														"index,follow",
														"noindex,follow",
														"index,nofollow",
														"noindex,nofollow"
													],
													label: "Robots",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.slug_override,
													"onUpdate:modelValue": ($event) => form.slug_override = $event,
													label: "SEO slug override",
													variant: "outlined",
													density: "compact",
													hint: "Пока не меняет основной slug товара, только хранится для SEO",
													"persistent-hint": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.canonical_url,
													"onUpdate:modelValue": ($event) => form.canonical_url = $event,
													label: "Canonical URL",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})
										]),
										_: 1
									}),
									createVNode(VDivider, { class: "my-4" }),
									createVNode(VRow, null, {
										default: withCtx(() => [
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VAlert, {
													type: "success",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(" Публикация и продвижение ")]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VSwitch, {
													modelValue: form.include_in_sitemap,
													"onUpdate:modelValue": ($event) => form.include_in_sitemap = $event,
													label: "Включить в sitemap",
													color: "green",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VSwitch, {
													modelValue: form.include_in_yandex_feed,
													"onUpdate:modelValue": ($event) => form.include_in_yandex_feed = $event,
													label: "Включить в Yandex Direct feed",
													color: "green",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: form.availability_status,
													"onUpdate:modelValue": ($event) => form.availability_status = $event,
													items: [
														{
															title: "В наличии",
															value: "in_stock"
														},
														{
															title: "По запросу",
															value: "on_request"
														},
														{
															title: "Под заказ",
															value: "preorder"
														},
														{
															title: "Нет в наличии",
															value: "out_of_stock"
														}
													],
													"item-title": "title",
													"item-value": "value",
													label: "Наличие",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.min_order,
													"onUpdate:modelValue": ($event) => form.min_order = $event,
													label: "Минимальная партия",
													variant: "outlined",
													density: "compact",
													placeholder: "Например: от 1 паллеты / от 100 кг"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.delivery_note,
													"onUpdate:modelValue": ($event) => form.delivery_note = $event,
													label: "Доставка",
													variant: "outlined",
													density: "compact",
													rows: "2"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.payment_note,
													"onUpdate:modelValue": ($event) => form.payment_note = $event,
													label: "Оплата",
													variant: "outlined",
													density: "compact",
													rows: "2"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})
										]),
										_: 1
									}),
									createVNode(VDivider, { class: "my-4" }),
									createVNode(VRow, null, {
										default: withCtx(() => [
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VAlert, {
													type: "info",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(" Данные для Яндекс.Директа ")]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.yandex_direct_title_1,
													"onUpdate:modelValue": ($event) => form.yandex_direct_title_1 = $event,
													label: "Заголовок Директ 1",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.yandex_direct_title_2,
													"onUpdate:modelValue": ($event) => form.yandex_direct_title_2 = $event,
													label: "Заголовок Директ 2",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.yandex_direct_text,
													"onUpdate:modelValue": ($event) => form.yandex_direct_text = $event,
													label: "Текст объявления",
													variant: "outlined",
													density: "compact",
													rows: "3"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.utm_template,
													"onUpdate:modelValue": ($event) => form.utm_template = $event,
													label: "UTM-шаблон",
													variant: "outlined",
													density: "compact",
													rows: "2",
													placeholder: "utm_source=yandex&utm_medium=cpc&utm_campaign={campaign_id}&utm_content={ad_id}&utm_term={keyword}"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})
										]),
										_: 1
									}),
									createVNode(VDivider, { class: "my-4" }),
									createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.faq_text,
												"onUpdate:modelValue": ($event) => form.faq_text = $event,
												label: "FAQ",
												variant: "outlined",
												density: "compact",
												rows: "5",
												hint: "Формат: вопрос | ответ. Каждый FAQ с новой строки.",
												"persistent-hint": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}), createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VBtn, {
												color: "teal",
												variant: "tonal",
												loading: unref(generating),
												onClick: generateJsonLd
											}, {
												default: withCtx(() => [createTextVNode(" Сгенерировать JSON-LD Product ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VRow, null, {
										default: withCtx(() => [
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.semantic_core_text,
													"onUpdate:modelValue": ($event) => form.semantic_core_text = $event,
													label: "Семантическое ядро",
													variant: "outlined",
													density: "compact",
													rows: "7",
													hint: "Каждый ключ с новой строки",
													"persistent-hint": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.keywords_text,
													"onUpdate:modelValue": ($event) => form.keywords_text = $event,
													label: "Keywords",
													variant: "outlined",
													density: "compact",
													rows: "7",
													hint: "Каждое слово/фраза с новой строки",
													"persistent-hint": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.search_queries_text,
													"onUpdate:modelValue": ($event) => form.search_queries_text = $event,
													label: "Поисковые запросы",
													variant: "outlined",
													density: "compact",
													rows: "7",
													hint: "Каждый запрос с новой строки",
													"persistent-hint": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})
										]),
										_: 1
									}),
									createVNode(VDivider, { class: "my-4" }),
									createVNode(VRow, null, {
										default: withCtx(() => [
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.og_title,
													"onUpdate:modelValue": ($event) => form.og_title = $event,
													label: "OG title",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.og_image,
													"onUpdate:modelValue": ($event) => form.og_image = $event,
													label: "OG image URL",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.og_description,
													"onUpdate:modelValue": ($event) => form.og_description = $event,
													label: "OG description",
													variant: "outlined",
													density: "compact",
													rows: "3"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.twitter_title,
													"onUpdate:modelValue": ($event) => form.twitter_title = $event,
													label: "Twitter title",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.twitter_image,
													"onUpdate:modelValue": ($event) => form.twitter_image = $event,
													label: "Twitter image URL",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.twitter_description,
													"onUpdate:modelValue": ($event) => form.twitter_description = $event,
													label: "Twitter description",
													variant: "outlined",
													density: "compact",
													rows: "3"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})
										]),
										_: 1
									}),
									createVNode(VDivider, { class: "my-4" }),
									createVNode(VRow, null, {
										default: withCtx(() => [
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.short_seo_text,
													"onUpdate:modelValue": ($event) => form.short_seo_text = $event,
													label: "Короткий SEO-текст",
													variant: "outlined",
													density: "compact",
													rows: "3"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.seo_text,
													"onUpdate:modelValue": ($event) => form.seo_text = $event,
													label: "Большой SEO-текст",
													variant: "outlined",
													density: "compact",
													rows: "8"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: form.structured_data_text,
													"onUpdate:modelValue": ($event) => form.structured_data_text = $event,
													label: "Structured data JSON-LD",
													variant: "outlined",
													density: "compact",
													rows: "8",
													hint: "Можно оставить пустым. Позже сделаем автогенерацию Product schema.",
													"persistent-hint": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})
										]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardActions, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VBtn, {
									color: "deep-purple-darken-1",
									variant: "tonal",
									loading: unref(saving),
									onClick: submit
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(` Сохранить SEO `);
										else return [createTextVNode(" Сохранить SEO ")];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VBtn, {
									color: "deep-purple-darken-1",
									variant: "tonal",
									loading: unref(saving),
									onClick: submit
								}, {
									default: withCtx(() => [createTextVNode(" Сохранить SEO ")]),
									_: 1
								}, 8, ["loading"])];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx(() => [createVNode("span", null, "SEO товара"), createVNode(VSwitch, {
								modelValue: form.is_active,
								"onUpdate:modelValue": ($event) => form.is_active = $event,
								label: "SEO активно",
								color: "green",
								"hide-details": "",
								inset: "",
								density: "compact"
							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
							_: 1
						}),
						createVNode(VCardText, null, {
							default: withCtx(() => [
								createVNode(VAlert, {
									type: "info",
									variant: "tonal",
									class: "mb-4"
								}, {
									default: withCtx(() => [createTextVNode(" Здесь храним SEO-данные для будущей публичной карточки товара в интернет-магазине. ")]),
									_: 1
								}),
								unref(loading) ? (openBlock(), createBlock(VProgressLinear, {
									key: 0,
									indeterminate: "",
									class: "mb-4"
								})) : createCommentVNode("", true),
								createVNode(VRow, null, {
									default: withCtx(() => [
										createVNode(VCol, {
											cols: "12",
											md: "8"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.meta_title,
												"onUpdate:modelValue": ($event) => form.meta_title = $event,
												label: "Meta title",
												variant: "outlined",
												density: "compact",
												counter: "255"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.focus_keyword,
												"onUpdate:modelValue": ($event) => form.focus_keyword = $event,
												label: "Фокусный ключ",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.meta_description,
												"onUpdate:modelValue": ($event) => form.meta_description = $event,
												label: "Meta description",
												variant: "outlined",
												density: "compact",
												rows: "3",
												counter: ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "6"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.h1,
												"onUpdate:modelValue": ($event) => form.h1 = $event,
												label: "H1",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "3"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.breadcrumbs_title,
												"onUpdate:modelValue": ($event) => form.breadcrumbs_title = $event,
												label: "Название в хлебных крошках",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "3"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: form.robots,
												"onUpdate:modelValue": ($event) => form.robots = $event,
												items: [
													"index,follow",
													"noindex,follow",
													"index,nofollow",
													"noindex,nofollow"
												],
												label: "Robots",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "6"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.slug_override,
												"onUpdate:modelValue": ($event) => form.slug_override = $event,
												label: "SEO slug override",
												variant: "outlined",
												density: "compact",
												hint: "Пока не меняет основной slug товара, только хранится для SEO",
												"persistent-hint": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "6"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.canonical_url,
												"onUpdate:modelValue": ($event) => form.canonical_url = $event,
												label: "Canonical URL",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})
									]),
									_: 1
								}),
								createVNode(VDivider, { class: "my-4" }),
								createVNode(VRow, null, {
									default: withCtx(() => [
										createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VAlert, {
												type: "success",
												variant: "tonal"
											}, {
												default: withCtx(() => [createTextVNode(" Публикация и продвижение ")]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VSwitch, {
												modelValue: form.include_in_sitemap,
												"onUpdate:modelValue": ($event) => form.include_in_sitemap = $event,
												label: "Включить в sitemap",
												color: "green",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VSwitch, {
												modelValue: form.include_in_yandex_feed,
												"onUpdate:modelValue": ($event) => form.include_in_yandex_feed = $event,
												label: "Включить в Yandex Direct feed",
												color: "green",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: form.availability_status,
												"onUpdate:modelValue": ($event) => form.availability_status = $event,
												items: [
													{
														title: "В наличии",
														value: "in_stock"
													},
													{
														title: "По запросу",
														value: "on_request"
													},
													{
														title: "Под заказ",
														value: "preorder"
													},
													{
														title: "Нет в наличии",
														value: "out_of_stock"
													}
												],
												"item-title": "title",
												"item-value": "value",
												label: "Наличие",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.min_order,
												"onUpdate:modelValue": ($event) => form.min_order = $event,
												label: "Минимальная партия",
												variant: "outlined",
												density: "compact",
												placeholder: "Например: от 1 паллеты / от 100 кг"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.delivery_note,
												"onUpdate:modelValue": ($event) => form.delivery_note = $event,
												label: "Доставка",
												variant: "outlined",
												density: "compact",
												rows: "2"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.payment_note,
												"onUpdate:modelValue": ($event) => form.payment_note = $event,
												label: "Оплата",
												variant: "outlined",
												density: "compact",
												rows: "2"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})
									]),
									_: 1
								}),
								createVNode(VDivider, { class: "my-4" }),
								createVNode(VRow, null, {
									default: withCtx(() => [
										createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VAlert, {
												type: "info",
												variant: "tonal"
											}, {
												default: withCtx(() => [createTextVNode(" Данные для Яндекс.Директа ")]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "6"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.yandex_direct_title_1,
												"onUpdate:modelValue": ($event) => form.yandex_direct_title_1 = $event,
												label: "Заголовок Директ 1",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "6"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.yandex_direct_title_2,
												"onUpdate:modelValue": ($event) => form.yandex_direct_title_2 = $event,
												label: "Заголовок Директ 2",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.yandex_direct_text,
												"onUpdate:modelValue": ($event) => form.yandex_direct_text = $event,
												label: "Текст объявления",
												variant: "outlined",
												density: "compact",
												rows: "3"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.utm_template,
												"onUpdate:modelValue": ($event) => form.utm_template = $event,
												label: "UTM-шаблон",
												variant: "outlined",
												density: "compact",
												rows: "2",
												placeholder: "utm_source=yandex&utm_medium=cpc&utm_campaign={campaign_id}&utm_content={ad_id}&utm_term={keyword}"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})
									]),
									_: 1
								}),
								createVNode(VDivider, { class: "my-4" }),
								createVNode(VRow, null, {
									default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
										default: withCtx(() => [createVNode(VTextarea, {
											modelValue: form.faq_text,
											"onUpdate:modelValue": ($event) => form.faq_text = $event,
											label: "FAQ",
											variant: "outlined",
											density: "compact",
											rows: "5",
											hint: "Формат: вопрос | ответ. Каждый FAQ с новой строки.",
											"persistent-hint": ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}), createVNode(VCol, { cols: "12" }, {
										default: withCtx(() => [createVNode(VBtn, {
											color: "teal",
											variant: "tonal",
											loading: unref(generating),
											onClick: generateJsonLd
										}, {
											default: withCtx(() => [createTextVNode(" Сгенерировать JSON-LD Product ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									})]),
									_: 1
								}),
								createVNode(VRow, null, {
									default: withCtx(() => [
										createVNode(VCol, {
											cols: "12",
											md: "6"
										}, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.semantic_core_text,
												"onUpdate:modelValue": ($event) => form.semantic_core_text = $event,
												label: "Семантическое ядро",
												variant: "outlined",
												density: "compact",
												rows: "7",
												hint: "Каждый ключ с новой строки",
												"persistent-hint": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "3"
										}, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.keywords_text,
												"onUpdate:modelValue": ($event) => form.keywords_text = $event,
												label: "Keywords",
												variant: "outlined",
												density: "compact",
												rows: "7",
												hint: "Каждое слово/фраза с новой строки",
												"persistent-hint": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "3"
										}, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.search_queries_text,
												"onUpdate:modelValue": ($event) => form.search_queries_text = $event,
												label: "Поисковые запросы",
												variant: "outlined",
												density: "compact",
												rows: "7",
												hint: "Каждый запрос с новой строки",
												"persistent-hint": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})
									]),
									_: 1
								}),
								createVNode(VDivider, { class: "my-4" }),
								createVNode(VRow, null, {
									default: withCtx(() => [
										createVNode(VCol, {
											cols: "12",
											md: "6"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.og_title,
												"onUpdate:modelValue": ($event) => form.og_title = $event,
												label: "OG title",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "6"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.og_image,
												"onUpdate:modelValue": ($event) => form.og_image = $event,
												label: "OG image URL",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.og_description,
												"onUpdate:modelValue": ($event) => form.og_description = $event,
												label: "OG description",
												variant: "outlined",
												density: "compact",
												rows: "3"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "6"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.twitter_title,
												"onUpdate:modelValue": ($event) => form.twitter_title = $event,
												label: "Twitter title",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "6"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.twitter_image,
												"onUpdate:modelValue": ($event) => form.twitter_image = $event,
												label: "Twitter image URL",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.twitter_description,
												"onUpdate:modelValue": ($event) => form.twitter_description = $event,
												label: "Twitter description",
												variant: "outlined",
												density: "compact",
												rows: "3"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})
									]),
									_: 1
								}),
								createVNode(VDivider, { class: "my-4" }),
								createVNode(VRow, null, {
									default: withCtx(() => [
										createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.short_seo_text,
												"onUpdate:modelValue": ($event) => form.short_seo_text = $event,
												label: "Короткий SEO-текст",
												variant: "outlined",
												density: "compact",
												rows: "3"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.seo_text,
												"onUpdate:modelValue": ($event) => form.seo_text = $event,
												label: "Большой SEO-текст",
												variant: "outlined",
												density: "compact",
												rows: "8"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: form.structured_data_text,
												"onUpdate:modelValue": ($event) => form.structured_data_text = $event,
												label: "Structured data JSON-LD",
												variant: "outlined",
												density: "compact",
												rows: "8",
												hint: "Можно оставить пустым. Позже сделаем автогенерацию Product schema.",
												"persistent-hint": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})
									]),
									_: 1
								})
							]),
							_: 1
						}),
						createVNode(VCardActions, null, {
							default: withCtx(() => [createVNode(VBtn, {
								color: "deep-purple-darken-1",
								variant: "tonal",
								loading: unref(saving),
								onClick: submit
							}, {
								default: withCtx(() => [createTextVNode(" Сохранить SEO ")]),
								_: 1
							}, 8, ["loading"])]),
							_: 1
						})
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$5 = _sfc_main$5.setup;
_sfc_main$5.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Goods/GoodSeoTab.vue");
	return _sfc_setup$5 ? _sfc_setup$5(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Composables/usePriceTypes.js
function usePriceTypes() {
	const priceTypes = ref([]);
	const loading = ref(false);
	const saving = ref(false);
	const deleting = ref({});
	async function fetchPriceTypes(params = {}) {
		loading.value = true;
		try {
			const { data } = await axios.get(route("price-types.index"), { params });
			priceTypes.value = Array.isArray(data) ? data : data.data || [];
			return priceTypes.value;
		} finally {
			loading.value = false;
		}
	}
	async function storePriceType(payload) {
		saving.value = true;
		try {
			const { data } = await axios.post(route("price-types.store"), payload);
			priceTypes.value.push(data);
			priceTypes.value.sort((a, b) => {
				const orderA = Number(a.sort_order || 100);
				const orderB = Number(b.sort_order || 100);
				if (orderA !== orderB) return orderA - orderB;
				return String(a.name || "").localeCompare(String(b.name || ""));
			});
			return data;
		} finally {
			saving.value = false;
		}
	}
	async function updatePriceType(priceTypeId, payload) {
		saving.value = true;
		try {
			const { data } = await axios.patch(route("price-types.update", priceTypeId), payload);
			priceTypes.value = priceTypes.value.map((item) => item.id === data.id ? data : item);
			return data;
		} finally {
			saving.value = false;
		}
	}
	async function deletePriceType(priceTypeId) {
		deleting.value[priceTypeId] = true;
		try {
			await axios.delete(route("price-types.destroy", priceTypeId));
			priceTypes.value = priceTypes.value.filter((item) => item.id !== priceTypeId);
		} finally {
			deleting.value[priceTypeId] = false;
		}
	}
	return {
		priceTypes,
		loading,
		saving,
		deleting,
		fetchPriceTypes,
		storePriceType,
		updatePriceType,
		deletePriceType
	};
}
//#endregion
//#region resources/js/Composables/useGoodPriceTypeValues.js
function useGoodPriceTypeValues(goodId) {
	const values = ref([]);
	const loading = ref(false);
	const saving = ref(false);
	const deleting = ref({});
	async function fetchValues() {
		loading.value = true;
		try {
			const { data } = await axios.get(route("api.goods.price-type-values.index", goodId));
			values.value = Array.isArray(data) ? data : [];
			return values.value;
		} finally {
			loading.value = false;
		}
	}
	async function storeValue(payload) {
		saving.value = true;
		try {
			const { data } = await axios.post(route("api.goods.price-type-values.store", goodId), payload);
			if (values.value.some((item) => item.id === data.id)) values.value = values.value.map((item) => item.id === data.id ? data : item);
			else values.value.unshift(data);
			return data;
		} finally {
			saving.value = false;
		}
	}
	async function updateValue(valueId, payload) {
		saving.value = true;
		try {
			const { data } = await axios.patch(route("api.goods.price-type-values.update", {
				good: goodId,
				value: valueId
			}), payload);
			values.value = values.value.map((item) => item.id === data.id ? data : item);
			return data;
		} finally {
			saving.value = false;
		}
	}
	async function deleteValue(valueId) {
		deleting.value[valueId] = true;
		try {
			await axios.delete(route("api.goods.price-type-values.destroy", {
				good: goodId,
				value: valueId
			}));
			values.value = values.value.filter((item) => item.id !== valueId);
		} finally {
			deleting.value[valueId] = false;
		}
	}
	return {
		values,
		loading,
		saving,
		deleting,
		fetchValues,
		storeValue,
		updateValue,
		deleteValue
	};
}
//#endregion
//#region resources/js/Components/Goods/GoodPriceCalculationsTab.vue
var _sfc_main$4 = {
	__name: "GoodPriceCalculationsTab",
	__ssrInlineRender: true,
	props: { goodId: {
		type: Number,
		required: true
	} },
	emits: ["applied"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const { calculations, loading, saving, deleting, fetchCalculations, updateCalculation, deleteCalculation } = useGoodPriceCalculations(props.goodId);
		const emit = __emit;
		const { priceTypes, fetchPriceTypes } = usePriceTypes();
		const { storeValue, saving: applying } = useGoodPriceTypeValues(props.goodId);
		const dialogApply = ref(false);
		const applySource = ref(null);
		const applyForm = reactive({
			price_type_id: null,
			price_net: null,
			price_gross: null,
			vat_rate: 20,
			is_manual: false,
			is_published: false,
			manual_comment: ""
		});
		const dialogEdit = ref(false);
		const edited = ref(null);
		const headers = [
			{
				key: "created_at",
				title: "Дата",
				sortable: true,
				width: "150px"
			},
			{
				key: "name",
				title: "Название",
				sortable: true
			},
			{
				key: "source",
				title: "Источник",
				sortable: false
			},
			{
				key: "sale_gross_per_kg",
				title: "Продажа / кг с НДС",
				sortable: true
			},
			{
				key: "sale_gross_per_box",
				title: "Продажа / коробка с НДС",
				sortable: true
			},
			{
				key: "margin_percent",
				title: "Маржа %",
				sortable: true
			},
			{
				key: "markup_percent",
				title: "Наценка %",
				sortable: true
			},
			{
				key: "actions",
				title: "",
				sortable: false,
				width: "120px"
			}
		];
		function formatMoney(value) {
			const number = Number(value || 0);
			return new Intl.NumberFormat("ru-RU", {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			}).format(number);
		}
		function formatDate(value) {
			if (!value) return "-";
			return new Intl.DateTimeFormat("ru-RU", {
				day: "2-digit",
				month: "short",
				year: "numeric",
				hour: "2-digit",
				minute: "2-digit"
			}).format(new Date(value));
		}
		function sourceTitle(item) {
			if (item.quotation) return `Quotation: ${item.quotation.unit?.name || "-"}`;
			if (item.purchase) return `Закупка: ${item.purchase.entity?.name || "-"}`;
			return "Ручной расчёт";
		}
		function openEdit(item) {
			edited.value = {
				id: item.id,
				name: item.name || "",
				comment: item.comment || ""
			};
			dialogEdit.value = true;
		}
		async function saveEdit() {
			if (!edited.value?.id) return;
			await updateCalculation(edited.value.id, {
				name: edited.value.name,
				comment: edited.value.comment
			});
			dialogEdit.value = false;
		}
		const selectedPriceType = computed(() => {
			return priceTypes.value.find((item) => Number(item.id) === Number(applyForm.price_type_id)) || null;
		});
		watch(() => applyForm.price_type_id, () => {
			if (selectedPriceType.value) applyForm.is_published = !!selectedPriceType.value.is_public;
		});
		function openApply(item) {
			applySource.value = item;
			applyForm.price_type_id = null;
			applyForm.price_net = item.sale_net_per_kg ?? null;
			applyForm.price_gross = item.sale_gross_per_kg ?? null;
			applyForm.vat_rate = item.result?.input?.vatRate ?? item.input?.vatRate ?? 20;
			applyForm.is_manual = false;
			applyForm.is_published = false;
			applyForm.manual_comment = item.name || `Расчёт #${item.id}`;
			dialogApply.value = true;
		}
		async function applyToPriceType() {
			if (!applySource.value?.id || !applyForm.price_type_id) return;
			await storeValue({
				calculation_id: applySource.value.id,
				price_type_id: applyForm.price_type_id,
				price_net: applyForm.price_net,
				price_gross: applyForm.price_gross,
				vat_rate: applyForm.vat_rate,
				is_manual: applyForm.is_manual,
				manual_comment: applyForm.manual_comment,
				is_published: applyForm.is_published
			});
			dialogApply.value = false;
			emit("applied");
		}
		onMounted(() => {
			fetchCalculations();
			fetchPriceTypes();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<span${_scopeId}>Сохранённые расчёты продажной цены</span>`);
									_push(ssrRenderComponent(VBtn, {
										icon: "mdi-refresh",
										variant: "text",
										loading: unref(loading),
										onClick: unref(fetchCalculations)
									}, null, _parent, _scopeId));
								} else return [createVNode("span", null, "Сохранённые расчёты продажной цены"), createVNode(VBtn, {
									icon: "mdi-refresh",
									variant: "text",
									loading: unref(loading),
									onClick: unref(fetchCalculations)
								}, null, 8, ["loading", "onClick"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VAlert, {
										type: "info",
										variant: "tonal",
										class: "mb-4"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Здесь хранятся расчёты, которые были сохранены из калькулятора продажной цены. `);
											else return [createTextVNode(" Здесь хранятся расчёты, которые были сохранены из калькулятора продажной цены. ")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VDataTable, {
										items: unref(calculations),
										headers,
										loading: unref(loading),
										"items-per-page": "50",
										"fixed-header": "",
										height: "520",
										density: "compact",
										class: "border rounded",
										hover: ""
									}, {
										"item.created_at": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`<span class="text-caption"${_scopeId}>${ssrInterpolate(formatDate(item.created_at))}</span>`);
											else return [createVNode("span", { class: "text-caption" }, toDisplayString(formatDate(item.created_at)), 1)];
										}),
										"item.name": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) {
												_push(`<div class="font-weight-medium"${_scopeId}>${ssrInterpolate(item.name || `Расчёт #${item.id}`)}</div>`);
												if (item.comment) _push(`<div class="text-caption text-medium-emphasis"${_scopeId}>${ssrInterpolate(item.comment)}</div>`);
												else _push(`<!---->`);
											} else return [createVNode("div", { class: "font-weight-medium" }, toDisplayString(item.name || `Расчёт #${item.id}`), 1), item.comment ? (openBlock(), createBlock("div", {
												key: 0,
												class: "text-caption text-medium-emphasis"
											}, toDisplayString(item.comment), 1)) : createCommentVNode("", true)];
										}),
										"item.source": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VChip, {
												size: "small",
												variant: "tonal"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(sourceTitle(item))}`);
													else return [createTextVNode(toDisplayString(sourceTitle(item)), 1)];
												}),
												_: 2
											}, _parent, _scopeId));
											else return [createVNode(VChip, {
												size: "small",
												variant: "tonal"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(sourceTitle(item)), 1)]),
												_: 2
											}, 1024)];
										}),
										"item.sale_gross_per_kg": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`<strong${_scopeId}>${ssrInterpolate(formatMoney(item.sale_gross_per_kg))}</strong><span class="text-caption ml-1"${_scopeId}>${ssrInterpolate(item.currency?.code || "RUB")}</span>`);
											else return [createVNode("strong", null, toDisplayString(formatMoney(item.sale_gross_per_kg)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(item.currency?.code || "RUB"), 1)];
										}),
										"item.sale_gross_per_box": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`<strong${_scopeId}>${ssrInterpolate(formatMoney(item.sale_gross_per_box))}</strong><span class="text-caption ml-1"${_scopeId}>${ssrInterpolate(item.currency?.code || "RUB")}</span>`);
											else return [createVNode("strong", null, toDisplayString(formatMoney(item.sale_gross_per_box)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(item.currency?.code || "RUB"), 1)];
										}),
										"item.margin_percent": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(Number(item.margin_percent || 0).toFixed(2))}% `);
											else return [createTextVNode(toDisplayString(Number(item.margin_percent || 0).toFixed(2)) + "% ", 1)];
										}),
										"item.markup_percent": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(Number(item.markup_percent || 0).toFixed(2))}% `);
											else return [createTextVNode(toDisplayString(Number(item.markup_percent || 0).toFixed(2)) + "% ", 1)];
										}),
										"item.actions": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VBtn, {
													icon: "mdi-pencil",
													size: "small",
													variant: "text",
													onClick: ($event) => openEdit(item)
												}, null, _parent, _scopeId));
												_push(ssrRenderComponent(VBtn, {
													icon: "mdi-tag-plus",
													size: "small",
													variant: "text",
													color: "deep-purple",
													onClick: ($event) => openApply(item)
												}, null, _parent, _scopeId));
												_push(ssrRenderComponent(VBtn, {
													icon: "mdi-delete",
													size: "small",
													variant: "text",
													color: "red",
													loading: !!unref(deleting)[item.id],
													onClick: ($event) => unref(deleteCalculation)(item.id)
												}, null, _parent, _scopeId));
											} else return [
												createVNode(VBtn, {
													icon: "mdi-pencil",
													size: "small",
													variant: "text",
													onClick: ($event) => openEdit(item)
												}, null, 8, ["onClick"]),
												createVNode(VBtn, {
													icon: "mdi-tag-plus",
													size: "small",
													variant: "text",
													color: "deep-purple",
													onClick: ($event) => openApply(item)
												}, null, 8, ["onClick"]),
												createVNode(VBtn, {
													icon: "mdi-delete",
													size: "small",
													variant: "text",
													color: "red",
													loading: !!unref(deleting)[item.id],
													onClick: ($event) => unref(deleteCalculation)(item.id)
												}, null, 8, ["loading", "onClick"])
											];
										}),
										"no-data": withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`<div class="pa-6 text-center text-medium-emphasis"${_scopeId}> Сохранённых расчётов пока нет. </div>`);
											else return [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Сохранённых расчётов пока нет. ")];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VAlert, {
									type: "info",
									variant: "tonal",
									class: "mb-4"
								}, {
									default: withCtx(() => [createTextVNode(" Здесь хранятся расчёты, которые были сохранены из калькулятора продажной цены. ")]),
									_: 1
								}), createVNode(VDataTable, {
									items: unref(calculations),
									headers,
									loading: unref(loading),
									"items-per-page": "50",
									"fixed-header": "",
									height: "520",
									density: "compact",
									class: "border rounded",
									hover: ""
								}, {
									"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-caption" }, toDisplayString(formatDate(item.created_at)), 1)]),
									"item.name": withCtx(({ item }) => [createVNode("div", { class: "font-weight-medium" }, toDisplayString(item.name || `Расчёт #${item.id}`), 1), item.comment ? (openBlock(), createBlock("div", {
										key: 0,
										class: "text-caption text-medium-emphasis"
									}, toDisplayString(item.comment), 1)) : createCommentVNode("", true)]),
									"item.source": withCtx(({ item }) => [createVNode(VChip, {
										size: "small",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(sourceTitle(item)), 1)]),
										_: 2
									}, 1024)]),
									"item.sale_gross_per_kg": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.sale_gross_per_kg)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(item.currency?.code || "RUB"), 1)]),
									"item.sale_gross_per_box": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.sale_gross_per_box)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(item.currency?.code || "RUB"), 1)]),
									"item.margin_percent": withCtx(({ item }) => [createTextVNode(toDisplayString(Number(item.margin_percent || 0).toFixed(2)) + "% ", 1)]),
									"item.markup_percent": withCtx(({ item }) => [createTextVNode(toDisplayString(Number(item.markup_percent || 0).toFixed(2)) + "% ", 1)]),
									"item.actions": withCtx(({ item }) => [
										createVNode(VBtn, {
											icon: "mdi-pencil",
											size: "small",
											variant: "text",
											onClick: ($event) => openEdit(item)
										}, null, 8, ["onClick"]),
										createVNode(VBtn, {
											icon: "mdi-tag-plus",
											size: "small",
											variant: "text",
											color: "deep-purple",
											onClick: ($event) => openApply(item)
										}, null, 8, ["onClick"]),
										createVNode(VBtn, {
											icon: "mdi-delete",
											size: "small",
											variant: "text",
											color: "red",
											loading: !!unref(deleting)[item.id],
											onClick: ($event) => unref(deleteCalculation)(item.id)
										}, null, 8, ["loading", "onClick"])
									]),
									"no-data": withCtx(() => [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Сохранённых расчётов пока нет. ")]),
									_: 1
								}, 8, ["items", "loading"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogEdit.value,
							"onUpdate:modelValue": ($event) => dialogEdit.value = $event,
							width: "560"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Редактировать расчёт`);
													else return [createTextVNode("Редактировать расчёт")];
												}),
												_: 1
											}, _parent, _scopeId));
											if (edited.value) _push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VTextField, {
															modelValue: edited.value.name,
															"onUpdate:modelValue": ($event) => edited.value.name = $event,
															label: "Название",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextarea, {
															modelValue: edited.value.comment,
															"onUpdate:modelValue": ($event) => edited.value.comment = $event,
															label: "Комментарий",
															variant: "outlined",
															density: "compact",
															rows: "4"
														}, null, _parent, _scopeId));
													} else return [createVNode(VTextField, {
														modelValue: edited.value.name,
														"onUpdate:modelValue": ($event) => edited.value.name = $event,
														label: "Название",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VTextarea, {
														modelValue: edited.value.comment,
														"onUpdate:modelValue": ($event) => edited.value.comment = $event,
														label: "Комментарий",
														variant: "outlined",
														density: "compact",
														rows: "4"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])];
												}),
												_: 1
											}, _parent, _scopeId));
											else _push(`<!---->`);
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogEdit.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Закрыть `);
																else return [createTextVNode(" Закрыть ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "deep-purple-darken-1",
															variant: "tonal",
															loading: unref(saving),
															onClick: saveEdit
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Сохранить `);
																else return [createTextVNode(" Сохранить ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogEdit.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Закрыть ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "deep-purple-darken-1",
														variant: "tonal",
														loading: unref(saving),
														onClick: saveEdit
													}, {
														default: withCtx(() => [createTextVNode(" Сохранить ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VDialog, {
												modelValue: dialogApply.value,
												"onUpdate:modelValue": ($event) => dialogApply.value = $event,
												width: "640"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VCard, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(ssrRenderComponent(VCardTitle, null, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(`Применить расчёт к виду цены`);
																		else return [createTextVNode("Применить расчёт к виду цены")];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCardText, null, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(ssrRenderComponent(VSelect, {
																				modelValue: applyForm.price_type_id,
																				"onUpdate:modelValue": ($event) => applyForm.price_type_id = $event,
																				items: unref(priceTypes),
																				"item-title": "name",
																				"item-value": "id",
																				label: "Вид цены",
																				variant: "outlined",
																				density: "compact"
																			}, {
																				item: withCtx(({ props, item }, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VListItem, mergeProps(props, {
																						title: item.raw.name,
																						subtitle: item.raw.code
																					}), null, _parent, _scopeId));
																					else return [createVNode(VListItem, mergeProps(props, {
																						title: item.raw.name,
																						subtitle: item.raw.code
																					}), null, 16, ["title", "subtitle"])];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VRow, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) {
																						_push(ssrRenderComponent(VCol, {
																							cols: "12",
																							md: "4"
																						}, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VTextField, {
																									modelValue: applyForm.price_gross,
																									"onUpdate:modelValue": ($event) => applyForm.price_gross = $event,
																									label: "Цена с НДС / кг",
																									type: "number",
																									step: "0.01",
																									variant: "outlined",
																									density: "compact"
																								}, null, _parent, _scopeId));
																								else return [createVNode(VTextField, {
																									modelValue: applyForm.price_gross,
																									"onUpdate:modelValue": ($event) => applyForm.price_gross = $event,
																									label: "Цена с НДС / кг",
																									type: "number",
																									step: "0.01",
																									variant: "outlined",
																									density: "compact"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																							}),
																							_: 1
																						}, _parent, _scopeId));
																						_push(ssrRenderComponent(VCol, {
																							cols: "12",
																							md: "4"
																						}, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VTextField, {
																									modelValue: applyForm.price_net,
																									"onUpdate:modelValue": ($event) => applyForm.price_net = $event,
																									label: "Цена без НДС / кг",
																									type: "number",
																									step: "0.01",
																									variant: "outlined",
																									density: "compact"
																								}, null, _parent, _scopeId));
																								else return [createVNode(VTextField, {
																									modelValue: applyForm.price_net,
																									"onUpdate:modelValue": ($event) => applyForm.price_net = $event,
																									label: "Цена без НДС / кг",
																									type: "number",
																									step: "0.01",
																									variant: "outlined",
																									density: "compact"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																							}),
																							_: 1
																						}, _parent, _scopeId));
																						_push(ssrRenderComponent(VCol, {
																							cols: "12",
																							md: "4"
																						}, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VTextField, {
																									modelValue: applyForm.vat_rate,
																									"onUpdate:modelValue": ($event) => applyForm.vat_rate = $event,
																									label: "НДС %",
																									type: "number",
																									step: "0.01",
																									variant: "outlined",
																									density: "compact"
																								}, null, _parent, _scopeId));
																								else return [createVNode(VTextField, {
																									modelValue: applyForm.vat_rate,
																									"onUpdate:modelValue": ($event) => applyForm.vat_rate = $event,
																									label: "НДС %",
																									type: "number",
																									step: "0.01",
																									variant: "outlined",
																									density: "compact"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																							}),
																							_: 1
																						}, _parent, _scopeId));
																						_push(ssrRenderComponent(VCol, {
																							cols: "12",
																							md: "6"
																						}, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VSwitch, {
																									modelValue: applyForm.is_manual,
																									"onUpdate:modelValue": ($event) => applyForm.is_manual = $event,
																									label: "Считать ручной ценой",
																									color: "orange",
																									inset: "",
																									"hide-details": ""
																								}, null, _parent, _scopeId));
																								else return [createVNode(VSwitch, {
																									modelValue: applyForm.is_manual,
																									"onUpdate:modelValue": ($event) => applyForm.is_manual = $event,
																									label: "Считать ручной ценой",
																									color: "orange",
																									inset: "",
																									"hide-details": ""
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																							}),
																							_: 1
																						}, _parent, _scopeId));
																						_push(ssrRenderComponent(VCol, {
																							cols: "12",
																							md: "6"
																						}, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VSwitch, {
																									modelValue: applyForm.is_published,
																									"onUpdate:modelValue": ($event) => applyForm.is_published = $event,
																									label: "Публиковать",
																									color: "green",
																									inset: "",
																									"hide-details": ""
																								}, null, _parent, _scopeId));
																								else return [createVNode(VSwitch, {
																									modelValue: applyForm.is_published,
																									"onUpdate:modelValue": ($event) => applyForm.is_published = $event,
																									label: "Публиковать",
																									color: "green",
																									inset: "",
																									"hide-details": ""
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																							}),
																							_: 1
																						}, _parent, _scopeId));
																						_push(ssrRenderComponent(VCol, { cols: "12" }, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VTextarea, {
																									modelValue: applyForm.manual_comment,
																									"onUpdate:modelValue": ($event) => applyForm.manual_comment = $event,
																									label: "Комментарий",
																									rows: "3",
																									variant: "outlined",
																									density: "compact"
																								}, null, _parent, _scopeId));
																								else return [createVNode(VTextarea, {
																									modelValue: applyForm.manual_comment,
																									"onUpdate:modelValue": ($event) => applyForm.manual_comment = $event,
																									label: "Комментарий",
																									rows: "3",
																									variant: "outlined",
																									density: "compact"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																							}),
																							_: 1
																						}, _parent, _scopeId));
																					} else return [
																						createVNode(VCol, {
																							cols: "12",
																							md: "4"
																						}, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: applyForm.price_gross,
																								"onUpdate:modelValue": ($event) => applyForm.price_gross = $event,
																								label: "Цена с НДС / кг",
																								type: "number",
																								step: "0.01",
																								variant: "outlined",
																								density: "compact"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}),
																						createVNode(VCol, {
																							cols: "12",
																							md: "4"
																						}, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: applyForm.price_net,
																								"onUpdate:modelValue": ($event) => applyForm.price_net = $event,
																								label: "Цена без НДС / кг",
																								type: "number",
																								step: "0.01",
																								variant: "outlined",
																								density: "compact"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}),
																						createVNode(VCol, {
																							cols: "12",
																							md: "4"
																						}, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: applyForm.vat_rate,
																								"onUpdate:modelValue": ($event) => applyForm.vat_rate = $event,
																								label: "НДС %",
																								type: "number",
																								step: "0.01",
																								variant: "outlined",
																								density: "compact"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}),
																						createVNode(VCol, {
																							cols: "12",
																							md: "6"
																						}, {
																							default: withCtx(() => [createVNode(VSwitch, {
																								modelValue: applyForm.is_manual,
																								"onUpdate:modelValue": ($event) => applyForm.is_manual = $event,
																								label: "Считать ручной ценой",
																								color: "orange",
																								inset: "",
																								"hide-details": ""
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}),
																						createVNode(VCol, {
																							cols: "12",
																							md: "6"
																						}, {
																							default: withCtx(() => [createVNode(VSwitch, {
																								modelValue: applyForm.is_published,
																								"onUpdate:modelValue": ($event) => applyForm.is_published = $event,
																								label: "Публиковать",
																								color: "green",
																								inset: "",
																								"hide-details": ""
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}),
																						createVNode(VCol, { cols: "12" }, {
																							default: withCtx(() => [createVNode(VTextarea, {
																								modelValue: applyForm.manual_comment,
																								"onUpdate:modelValue": ($event) => applyForm.manual_comment = $event,
																								label: "Комментарий",
																								rows: "3",
																								variant: "outlined",
																								density: "compact"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						})
																					];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																		} else return [createVNode(VSelect, {
																			modelValue: applyForm.price_type_id,
																			"onUpdate:modelValue": ($event) => applyForm.price_type_id = $event,
																			items: unref(priceTypes),
																			"item-title": "name",
																			"item-value": "id",
																			label: "Вид цены",
																			variant: "outlined",
																			density: "compact"
																		}, {
																			item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
																				title: item.raw.name,
																				subtitle: item.raw.code
																			}), null, 16, ["title", "subtitle"])]),
																			_: 1
																		}, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"items"
																		]), createVNode(VRow, null, {
																			default: withCtx(() => [
																				createVNode(VCol, {
																					cols: "12",
																					md: "4"
																				}, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: applyForm.price_gross,
																						"onUpdate:modelValue": ($event) => applyForm.price_gross = $event,
																						label: "Цена с НДС / кг",
																						type: "number",
																						step: "0.01",
																						variant: "outlined",
																						density: "compact"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VCol, {
																					cols: "12",
																					md: "4"
																				}, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: applyForm.price_net,
																						"onUpdate:modelValue": ($event) => applyForm.price_net = $event,
																						label: "Цена без НДС / кг",
																						type: "number",
																						step: "0.01",
																						variant: "outlined",
																						density: "compact"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VCol, {
																					cols: "12",
																					md: "4"
																				}, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: applyForm.vat_rate,
																						"onUpdate:modelValue": ($event) => applyForm.vat_rate = $event,
																						label: "НДС %",
																						type: "number",
																						step: "0.01",
																						variant: "outlined",
																						density: "compact"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VCol, {
																					cols: "12",
																					md: "6"
																				}, {
																					default: withCtx(() => [createVNode(VSwitch, {
																						modelValue: applyForm.is_manual,
																						"onUpdate:modelValue": ($event) => applyForm.is_manual = $event,
																						label: "Считать ручной ценой",
																						color: "orange",
																						inset: "",
																						"hide-details": ""
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VCol, {
																					cols: "12",
																					md: "6"
																				}, {
																					default: withCtx(() => [createVNode(VSwitch, {
																						modelValue: applyForm.is_published,
																						"onUpdate:modelValue": ($event) => applyForm.is_published = $event,
																						label: "Публиковать",
																						color: "green",
																						inset: "",
																						"hide-details": ""
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VCol, { cols: "12" }, {
																					default: withCtx(() => [createVNode(VTextarea, {
																						modelValue: applyForm.manual_comment,
																						"onUpdate:modelValue": ($event) => applyForm.manual_comment = $event,
																						label: "Комментарий",
																						rows: "3",
																						variant: "outlined",
																						density: "compact"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				})
																			]),
																			_: 1
																		})];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCardActions, null, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(ssrRenderComponent(VBtn, {
																				variant: "text",
																				onClick: ($event) => dialogApply.value = false
																			}, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(` Закрыть `);
																					else return [createTextVNode(" Закрыть ")];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VBtn, {
																				color: "deep-purple-darken-1",
																				variant: "tonal",
																				loading: unref(applying),
																				disabled: !applyForm.price_type_id,
																				onClick: applyToPriceType
																			}, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(` Применить `);
																					else return [createTextVNode(" Применить ")];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																		} else return [createVNode(VBtn, {
																			variant: "text",
																			onClick: ($event) => dialogApply.value = false
																		}, {
																			default: withCtx(() => [createTextVNode(" Закрыть ")]),
																			_: 1
																		}, 8, ["onClick"]), createVNode(VBtn, {
																			color: "deep-purple-darken-1",
																			variant: "tonal",
																			loading: unref(applying),
																			disabled: !applyForm.price_type_id,
																			onClick: applyToPriceType
																		}, {
																			default: withCtx(() => [createTextVNode(" Применить ")]),
																			_: 1
																		}, 8, ["loading", "disabled"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
															} else return [
																createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Применить расчёт к виду цены")]),
																	_: 1
																}),
																createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VSelect, {
																		modelValue: applyForm.price_type_id,
																		"onUpdate:modelValue": ($event) => applyForm.price_type_id = $event,
																		items: unref(priceTypes),
																		"item-title": "name",
																		"item-value": "id",
																		label: "Вид цены",
																		variant: "outlined",
																		density: "compact"
																	}, {
																		item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
																			title: item.raw.name,
																			subtitle: item.raw.code
																		}), null, 16, ["title", "subtitle"])]),
																		_: 1
																	}, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items"
																	]), createVNode(VRow, null, {
																		default: withCtx(() => [
																			createVNode(VCol, {
																				cols: "12",
																				md: "4"
																			}, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: applyForm.price_gross,
																					"onUpdate:modelValue": ($event) => applyForm.price_gross = $event,
																					label: "Цена с НДС / кг",
																					type: "number",
																					step: "0.01",
																					variant: "outlined",
																					density: "compact"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VCol, {
																				cols: "12",
																				md: "4"
																			}, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: applyForm.price_net,
																					"onUpdate:modelValue": ($event) => applyForm.price_net = $event,
																					label: "Цена без НДС / кг",
																					type: "number",
																					step: "0.01",
																					variant: "outlined",
																					density: "compact"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VCol, {
																				cols: "12",
																				md: "4"
																			}, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: applyForm.vat_rate,
																					"onUpdate:modelValue": ($event) => applyForm.vat_rate = $event,
																					label: "НДС %",
																					type: "number",
																					step: "0.01",
																					variant: "outlined",
																					density: "compact"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VCol, {
																				cols: "12",
																				md: "6"
																			}, {
																				default: withCtx(() => [createVNode(VSwitch, {
																					modelValue: applyForm.is_manual,
																					"onUpdate:modelValue": ($event) => applyForm.is_manual = $event,
																					label: "Считать ручной ценой",
																					color: "orange",
																					inset: "",
																					"hide-details": ""
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VCol, {
																				cols: "12",
																				md: "6"
																			}, {
																				default: withCtx(() => [createVNode(VSwitch, {
																					modelValue: applyForm.is_published,
																					"onUpdate:modelValue": ($event) => applyForm.is_published = $event,
																					label: "Публиковать",
																					color: "green",
																					inset: "",
																					"hide-details": ""
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VCol, { cols: "12" }, {
																				default: withCtx(() => [createVNode(VTextarea, {
																					modelValue: applyForm.manual_comment,
																					"onUpdate:modelValue": ($event) => applyForm.manual_comment = $event,
																					label: "Комментарий",
																					rows: "3",
																					variant: "outlined",
																					density: "compact"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			})
																		]),
																		_: 1
																	})]),
																	_: 1
																}),
																createVNode(VCardActions, null, {
																	default: withCtx(() => [createVNode(VBtn, {
																		variant: "text",
																		onClick: ($event) => dialogApply.value = false
																	}, {
																		default: withCtx(() => [createTextVNode(" Закрыть ")]),
																		_: 1
																	}, 8, ["onClick"]), createVNode(VBtn, {
																		color: "deep-purple-darken-1",
																		variant: "tonal",
																		loading: unref(applying),
																		disabled: !applyForm.price_type_id,
																		onClick: applyToPriceType
																	}, {
																		default: withCtx(() => [createTextVNode(" Применить ")]),
																		_: 1
																	}, 8, ["loading", "disabled"])]),
																	_: 1
																})
															];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(VCard, null, {
														default: withCtx(() => [
															createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Применить расчёт к виду цены")]),
																_: 1
															}),
															createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VSelect, {
																	modelValue: applyForm.price_type_id,
																	"onUpdate:modelValue": ($event) => applyForm.price_type_id = $event,
																	items: unref(priceTypes),
																	"item-title": "name",
																	"item-value": "id",
																	label: "Вид цены",
																	variant: "outlined",
																	density: "compact"
																}, {
																	item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
																		title: item.raw.name,
																		subtitle: item.raw.code
																	}), null, 16, ["title", "subtitle"])]),
																	_: 1
																}, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items"
																]), createVNode(VRow, null, {
																	default: withCtx(() => [
																		createVNode(VCol, {
																			cols: "12",
																			md: "4"
																		}, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: applyForm.price_gross,
																				"onUpdate:modelValue": ($event) => applyForm.price_gross = $event,
																				label: "Цена с НДС / кг",
																				type: "number",
																				step: "0.01",
																				variant: "outlined",
																				density: "compact"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VCol, {
																			cols: "12",
																			md: "4"
																		}, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: applyForm.price_net,
																				"onUpdate:modelValue": ($event) => applyForm.price_net = $event,
																				label: "Цена без НДС / кг",
																				type: "number",
																				step: "0.01",
																				variant: "outlined",
																				density: "compact"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VCol, {
																			cols: "12",
																			md: "4"
																		}, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: applyForm.vat_rate,
																				"onUpdate:modelValue": ($event) => applyForm.vat_rate = $event,
																				label: "НДС %",
																				type: "number",
																				step: "0.01",
																				variant: "outlined",
																				density: "compact"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VCol, {
																			cols: "12",
																			md: "6"
																		}, {
																			default: withCtx(() => [createVNode(VSwitch, {
																				modelValue: applyForm.is_manual,
																				"onUpdate:modelValue": ($event) => applyForm.is_manual = $event,
																				label: "Считать ручной ценой",
																				color: "orange",
																				inset: "",
																				"hide-details": ""
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VCol, {
																			cols: "12",
																			md: "6"
																		}, {
																			default: withCtx(() => [createVNode(VSwitch, {
																				modelValue: applyForm.is_published,
																				"onUpdate:modelValue": ($event) => applyForm.is_published = $event,
																				label: "Публиковать",
																				color: "green",
																				inset: "",
																				"hide-details": ""
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VCol, { cols: "12" }, {
																			default: withCtx(() => [createVNode(VTextarea, {
																				modelValue: applyForm.manual_comment,
																				"onUpdate:modelValue": ($event) => applyForm.manual_comment = $event,
																				label: "Комментарий",
																				rows: "3",
																				variant: "outlined",
																				density: "compact"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		})
																	]),
																	_: 1
																})]),
																_: 1
															}),
															createVNode(VCardActions, null, {
																default: withCtx(() => [createVNode(VBtn, {
																	variant: "text",
																	onClick: ($event) => dialogApply.value = false
																}, {
																	default: withCtx(() => [createTextVNode(" Закрыть ")]),
																	_: 1
																}, 8, ["onClick"]), createVNode(VBtn, {
																	color: "deep-purple-darken-1",
																	variant: "tonal",
																	loading: unref(applying),
																	disabled: !applyForm.price_type_id,
																	onClick: applyToPriceType
																}, {
																	default: withCtx(() => [createTextVNode(" Применить ")]),
																	_: 1
																}, 8, ["loading", "disabled"])]),
																_: 1
															})
														]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Редактировать расчёт")]),
												_: 1
											}),
											edited.value ? (openBlock(), createBlock(VCardText, { key: 0 }, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: edited.value.name,
													"onUpdate:modelValue": ($event) => edited.value.name = $event,
													label: "Название",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VTextarea, {
													modelValue: edited.value.comment,
													"onUpdate:modelValue": ($event) => edited.value.comment = $event,
													label: "Комментарий",
													variant: "outlined",
													density: "compact",
													rows: "4"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})) : createCommentVNode("", true),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogEdit.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Закрыть ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "deep-purple-darken-1",
													variant: "tonal",
													loading: unref(saving),
													onClick: saveEdit
												}, {
													default: withCtx(() => [createTextVNode(" Сохранить ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											}),
											createVNode(VDialog, {
												modelValue: dialogApply.value,
												"onUpdate:modelValue": ($event) => dialogApply.value = $event,
												width: "640"
											}, {
												default: withCtx(() => [createVNode(VCard, null, {
													default: withCtx(() => [
														createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Применить расчёт к виду цены")]),
															_: 1
														}),
														createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VSelect, {
																modelValue: applyForm.price_type_id,
																"onUpdate:modelValue": ($event) => applyForm.price_type_id = $event,
																items: unref(priceTypes),
																"item-title": "name",
																"item-value": "id",
																label: "Вид цены",
																variant: "outlined",
																density: "compact"
															}, {
																item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
																	title: item.raw.name,
																	subtitle: item.raw.code
																}), null, 16, ["title", "subtitle"])]),
																_: 1
															}, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items"
															]), createVNode(VRow, null, {
																default: withCtx(() => [
																	createVNode(VCol, {
																		cols: "12",
																		md: "4"
																	}, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: applyForm.price_gross,
																			"onUpdate:modelValue": ($event) => applyForm.price_gross = $event,
																			label: "Цена с НДС / кг",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VCol, {
																		cols: "12",
																		md: "4"
																	}, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: applyForm.price_net,
																			"onUpdate:modelValue": ($event) => applyForm.price_net = $event,
																			label: "Цена без НДС / кг",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VCol, {
																		cols: "12",
																		md: "4"
																	}, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: applyForm.vat_rate,
																			"onUpdate:modelValue": ($event) => applyForm.vat_rate = $event,
																			label: "НДС %",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VCol, {
																		cols: "12",
																		md: "6"
																	}, {
																		default: withCtx(() => [createVNode(VSwitch, {
																			modelValue: applyForm.is_manual,
																			"onUpdate:modelValue": ($event) => applyForm.is_manual = $event,
																			label: "Считать ручной ценой",
																			color: "orange",
																			inset: "",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VCol, {
																		cols: "12",
																		md: "6"
																	}, {
																		default: withCtx(() => [createVNode(VSwitch, {
																			modelValue: applyForm.is_published,
																			"onUpdate:modelValue": ($event) => applyForm.is_published = $event,
																			label: "Публиковать",
																			color: "green",
																			inset: "",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VCol, { cols: "12" }, {
																		default: withCtx(() => [createVNode(VTextarea, {
																			modelValue: applyForm.manual_comment,
																			"onUpdate:modelValue": ($event) => applyForm.manual_comment = $event,
																			label: "Комментарий",
																			rows: "3",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})
																]),
																_: 1
															})]),
															_: 1
														}),
														createVNode(VCardActions, null, {
															default: withCtx(() => [createVNode(VBtn, {
																variant: "text",
																onClick: ($event) => dialogApply.value = false
															}, {
																default: withCtx(() => [createTextVNode(" Закрыть ")]),
																_: 1
															}, 8, ["onClick"]), createVNode(VBtn, {
																color: "deep-purple-darken-1",
																variant: "tonal",
																loading: unref(applying),
																disabled: !applyForm.price_type_id,
																onClick: applyToPriceType
															}, {
																default: withCtx(() => [createTextVNode(" Применить ")]),
																_: 1
															}, 8, ["loading", "disabled"])]),
															_: 1
														})
													]),
													_: 1
												})]),
												_: 1
											}, 8, ["modelValue", "onUpdate:modelValue"])
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, null, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Редактировать расчёт")]),
											_: 1
										}),
										edited.value ? (openBlock(), createBlock(VCardText, { key: 0 }, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: edited.value.name,
												"onUpdate:modelValue": ($event) => edited.value.name = $event,
												label: "Название",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VTextarea, {
												modelValue: edited.value.comment,
												"onUpdate:modelValue": ($event) => edited.value.comment = $event,
												label: "Комментарий",
												variant: "outlined",
												density: "compact",
												rows: "4"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})) : createCommentVNode("", true),
										createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogEdit.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Закрыть ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "deep-purple-darken-1",
												variant: "tonal",
												loading: unref(saving),
												onClick: saveEdit
											}, {
												default: withCtx(() => [createTextVNode(" Сохранить ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										}),
										createVNode(VDialog, {
											modelValue: dialogApply.value,
											"onUpdate:modelValue": ($event) => dialogApply.value = $event,
											width: "640"
										}, {
											default: withCtx(() => [createVNode(VCard, null, {
												default: withCtx(() => [
													createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Применить расчёт к виду цены")]),
														_: 1
													}),
													createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VSelect, {
															modelValue: applyForm.price_type_id,
															"onUpdate:modelValue": ($event) => applyForm.price_type_id = $event,
															items: unref(priceTypes),
															"item-title": "name",
															"item-value": "id",
															label: "Вид цены",
															variant: "outlined",
															density: "compact"
														}, {
															item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
																title: item.raw.name,
																subtitle: item.raw.code
															}), null, 16, ["title", "subtitle"])]),
															_: 1
														}, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														]), createVNode(VRow, null, {
															default: withCtx(() => [
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: applyForm.price_gross,
																		"onUpdate:modelValue": ($event) => applyForm.price_gross = $event,
																		label: "Цена с НДС / кг",
																		type: "number",
																		step: "0.01",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: applyForm.price_net,
																		"onUpdate:modelValue": ($event) => applyForm.price_net = $event,
																		label: "Цена без НДС / кг",
																		type: "number",
																		step: "0.01",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: applyForm.vat_rate,
																		"onUpdate:modelValue": ($event) => applyForm.vat_rate = $event,
																		label: "НДС %",
																		type: "number",
																		step: "0.01",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "6"
																}, {
																	default: withCtx(() => [createVNode(VSwitch, {
																		modelValue: applyForm.is_manual,
																		"onUpdate:modelValue": ($event) => applyForm.is_manual = $event,
																		label: "Считать ручной ценой",
																		color: "orange",
																		inset: "",
																		"hide-details": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "6"
																}, {
																	default: withCtx(() => [createVNode(VSwitch, {
																		modelValue: applyForm.is_published,
																		"onUpdate:modelValue": ($event) => applyForm.is_published = $event,
																		label: "Публиковать",
																		color: "green",
																		inset: "",
																		"hide-details": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, { cols: "12" }, {
																	default: withCtx(() => [createVNode(VTextarea, {
																		modelValue: applyForm.manual_comment,
																		"onUpdate:modelValue": ($event) => applyForm.manual_comment = $event,
																		label: "Комментарий",
																		rows: "3",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																})
															]),
															_: 1
														})]),
														_: 1
													}),
													createVNode(VCardActions, null, {
														default: withCtx(() => [createVNode(VBtn, {
															variant: "text",
															onClick: ($event) => dialogApply.value = false
														}, {
															default: withCtx(() => [createTextVNode(" Закрыть ")]),
															_: 1
														}, 8, ["onClick"]), createVNode(VBtn, {
															color: "deep-purple-darken-1",
															variant: "tonal",
															loading: unref(applying),
															disabled: !applyForm.price_type_id,
															onClick: applyToPriceType
														}, {
															default: withCtx(() => [createTextVNode(" Применить ")]),
															_: 1
														}, 8, ["loading", "disabled"])]),
														_: 1
													})
												]),
												_: 1
											})]),
											_: 1
										}, 8, ["modelValue", "onUpdate:modelValue"])
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx(() => [createVNode("span", null, "Сохранённые расчёты продажной цены"), createVNode(VBtn, {
								icon: "mdi-refresh",
								variant: "text",
								loading: unref(loading),
								onClick: unref(fetchCalculations)
							}, null, 8, ["loading", "onClick"])]),
							_: 1
						}),
						createVNode(VCardText, null, {
							default: withCtx(() => [createVNode(VAlert, {
								type: "info",
								variant: "tonal",
								class: "mb-4"
							}, {
								default: withCtx(() => [createTextVNode(" Здесь хранятся расчёты, которые были сохранены из калькулятора продажной цены. ")]),
								_: 1
							}), createVNode(VDataTable, {
								items: unref(calculations),
								headers,
								loading: unref(loading),
								"items-per-page": "50",
								"fixed-header": "",
								height: "520",
								density: "compact",
								class: "border rounded",
								hover: ""
							}, {
								"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-caption" }, toDisplayString(formatDate(item.created_at)), 1)]),
								"item.name": withCtx(({ item }) => [createVNode("div", { class: "font-weight-medium" }, toDisplayString(item.name || `Расчёт #${item.id}`), 1), item.comment ? (openBlock(), createBlock("div", {
									key: 0,
									class: "text-caption text-medium-emphasis"
								}, toDisplayString(item.comment), 1)) : createCommentVNode("", true)]),
								"item.source": withCtx(({ item }) => [createVNode(VChip, {
									size: "small",
									variant: "tonal"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(sourceTitle(item)), 1)]),
									_: 2
								}, 1024)]),
								"item.sale_gross_per_kg": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.sale_gross_per_kg)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(item.currency?.code || "RUB"), 1)]),
								"item.sale_gross_per_box": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.sale_gross_per_box)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(item.currency?.code || "RUB"), 1)]),
								"item.margin_percent": withCtx(({ item }) => [createTextVNode(toDisplayString(Number(item.margin_percent || 0).toFixed(2)) + "% ", 1)]),
								"item.markup_percent": withCtx(({ item }) => [createTextVNode(toDisplayString(Number(item.markup_percent || 0).toFixed(2)) + "% ", 1)]),
								"item.actions": withCtx(({ item }) => [
									createVNode(VBtn, {
										icon: "mdi-pencil",
										size: "small",
										variant: "text",
										onClick: ($event) => openEdit(item)
									}, null, 8, ["onClick"]),
									createVNode(VBtn, {
										icon: "mdi-tag-plus",
										size: "small",
										variant: "text",
										color: "deep-purple",
										onClick: ($event) => openApply(item)
									}, null, 8, ["onClick"]),
									createVNode(VBtn, {
										icon: "mdi-delete",
										size: "small",
										variant: "text",
										color: "red",
										loading: !!unref(deleting)[item.id],
										onClick: ($event) => unref(deleteCalculation)(item.id)
									}, null, 8, ["loading", "onClick"])
								]),
								"no-data": withCtx(() => [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Сохранённых расчётов пока нет. ")]),
								_: 1
							}, 8, ["items", "loading"])]),
							_: 1
						}),
						createVNode(VDialog, {
							modelValue: dialogEdit.value,
							"onUpdate:modelValue": ($event) => dialogEdit.value = $event,
							width: "560"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Редактировать расчёт")]),
										_: 1
									}),
									edited.value ? (openBlock(), createBlock(VCardText, { key: 0 }, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: edited.value.name,
											"onUpdate:modelValue": ($event) => edited.value.name = $event,
											label: "Название",
											variant: "outlined",
											density: "compact"
										}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VTextarea, {
											modelValue: edited.value.comment,
											"onUpdate:modelValue": ($event) => edited.value.comment = $event,
											label: "Комментарий",
											variant: "outlined",
											density: "compact",
											rows: "4"
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									})) : createCommentVNode("", true),
									createVNode(VCardActions, null, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogEdit.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Закрыть ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "deep-purple-darken-1",
											variant: "tonal",
											loading: unref(saving),
											onClick: saveEdit
										}, {
											default: withCtx(() => [createTextVNode(" Сохранить ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									}),
									createVNode(VDialog, {
										modelValue: dialogApply.value,
										"onUpdate:modelValue": ($event) => dialogApply.value = $event,
										width: "640"
									}, {
										default: withCtx(() => [createVNode(VCard, null, {
											default: withCtx(() => [
												createVNode(VCardTitle, null, {
													default: withCtx(() => [createTextVNode("Применить расчёт к виду цены")]),
													_: 1
												}),
												createVNode(VCardText, null, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: applyForm.price_type_id,
														"onUpdate:modelValue": ($event) => applyForm.price_type_id = $event,
														items: unref(priceTypes),
														"item-title": "name",
														"item-value": "id",
														label: "Вид цены",
														variant: "outlined",
														density: "compact"
													}, {
														item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
															title: item.raw.name,
															subtitle: item.raw.code
														}), null, 16, ["title", "subtitle"])]),
														_: 1
													}, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													]), createVNode(VRow, null, {
														default: withCtx(() => [
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: applyForm.price_gross,
																	"onUpdate:modelValue": ($event) => applyForm.price_gross = $event,
																	label: "Цена с НДС / кг",
																	type: "number",
																	step: "0.01",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: applyForm.price_net,
																	"onUpdate:modelValue": ($event) => applyForm.price_net = $event,
																	label: "Цена без НДС / кг",
																	type: "number",
																	step: "0.01",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: applyForm.vat_rate,
																	"onUpdate:modelValue": ($event) => applyForm.vat_rate = $event,
																	label: "НДС %",
																	type: "number",
																	step: "0.01",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "6"
															}, {
																default: withCtx(() => [createVNode(VSwitch, {
																	modelValue: applyForm.is_manual,
																	"onUpdate:modelValue": ($event) => applyForm.is_manual = $event,
																	label: "Считать ручной ценой",
																	color: "orange",
																	inset: "",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "6"
															}, {
																default: withCtx(() => [createVNode(VSwitch, {
																	modelValue: applyForm.is_published,
																	"onUpdate:modelValue": ($event) => applyForm.is_published = $event,
																	label: "Публиковать",
																	color: "green",
																	inset: "",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, { cols: "12" }, {
																default: withCtx(() => [createVNode(VTextarea, {
																	modelValue: applyForm.manual_comment,
																	"onUpdate:modelValue": ($event) => applyForm.manual_comment = $event,
																	label: "Комментарий",
																	rows: "3",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})
														]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VCardActions, null, {
													default: withCtx(() => [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogApply.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Закрыть ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "deep-purple-darken-1",
														variant: "tonal",
														loading: unref(applying),
														disabled: !applyForm.price_type_id,
														onClick: applyToPriceType
													}, {
														default: withCtx(() => [createTextVNode(" Применить ")]),
														_: 1
													}, 8, ["loading", "disabled"])]),
													_: 1
												})
											]),
											_: 1
										})]),
										_: 1
									}, 8, ["modelValue", "onUpdate:modelValue"])
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"])
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$4 = _sfc_main$4.setup;
_sfc_main$4.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Goods/GoodPriceCalculationsTab.vue");
	return _sfc_setup$4 ? _sfc_setup$4(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Goods/GoodPriceTypesTab.vue
var _sfc_main$3 = {
	__name: "GoodPriceTypesTab",
	__ssrInlineRender: true,
	props: { currencies: {
		type: Array,
		default: () => []
	} },
	setup(__props) {
		const { priceTypes, loading, saving, deleting, fetchPriceTypes, storePriceType, updatePriceType, deletePriceType } = usePriceTypes();
		const search = ref("");
		const activeFilter = ref("all");
		const publicFilter = ref("all");
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
			sort_order: 100
		});
		const headers = [
			{
				key: "sort_order",
				title: "#",
				sortable: true,
				width: "80px"
			},
			{
				key: "name",
				title: "Вид цены",
				sortable: true
			},
			{
				key: "code",
				title: "Код",
				sortable: true,
				width: "170px"
			},
			{
				key: "currency.code",
				title: "Валюта",
				sortable: true,
				width: "110px"
			},
			{
				key: "markup_percent",
				title: "Наценка %",
				sortable: true,
				width: "130px"
			},
			{
				key: "target_margin_percent",
				title: "Маржа %",
				sortable: true,
				width: "120px"
			},
			{
				key: "rounding_step",
				title: "Округление",
				sortable: true,
				width: "130px"
			},
			{
				key: "is_public",
				title: "Public",
				sortable: true,
				width: "100px"
			},
			{
				key: "is_active",
				title: "Active",
				sortable: true,
				width: "100px"
			},
			{
				key: "actions",
				title: "",
				sortable: false,
				width: "130px"
			}
		];
		const filteredItems = computed(() => {
			let items = [...priceTypes.value];
			const query = search.value.trim().toLowerCase();
			if (query) items = items.filter((item) => {
				return [
					item.name,
					item.code,
					item.description,
					item.currency?.code,
					item.currency?.name
				].filter(Boolean).some((value) => String(value).toLowerCase().includes(query));
			});
			if (activeFilter.value === "active") items = items.filter((item) => !!item.is_active);
			if (activeFilter.value === "inactive") items = items.filter((item) => !item.is_active);
			if (publicFilter.value === "public") items = items.filter((item) => !!item.is_public);
			if (publicFilter.value === "hidden") items = items.filter((item) => !item.is_public);
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
				target_margin_percent: form.target_margin_percent === "" ? null : form.target_margin_percent,
				rounding_step: form.rounding_step === "" ? null : form.rounding_step,
				is_active: form.is_active,
				is_public: form.is_public,
				sort_order: form.sort_order || 100
			};
		}
		async function submit() {
			if (editedId.value) await updatePriceType(editedId.value, payload());
			else await storePriceType(payload());
			dialogForm.value = false;
		}
		async function confirmDelete() {
			if (!selectedItem.value?.id) return;
			await deletePriceType(selectedItem.value.id);
			dialogDelete.value = false;
			selectedItem.value = null;
		}
		function formatPercent(value) {
			if (value === null || value === void 0 || value === "") return "—";
			return `${Number(value).toFixed(2)}%`;
		}
		function formatNumber(value) {
			if (value === null || value === void 0 || value === "") return "—";
			return new Intl.NumberFormat("ru-RU", { maximumFractionDigits: 4 }).format(Number(value));
		}
		onMounted(() => {
			fetchPriceTypes();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<span${_scopeId}>Виды цен</span><div class="d-flex align-center ga-2"${_scopeId}>`);
									_push(ssrRenderComponent(VBtn, {
										icon: "mdi-refresh",
										variant: "text",
										loading: unref(loading),
										onClick: unref(fetchPriceTypes)
									}, null, _parent, _scopeId));
									_push(ssrRenderComponent(VBtn, {
										color: "deep-purple-darken-1",
										variant: "tonal",
										"prepend-icon": "mdi-plus",
										onClick: openCreate
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Новый вид цены `);
											else return [createTextVNode(" Новый вид цены ")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(`</div>`);
								} else return [createVNode("span", null, "Виды цен"), createVNode("div", { class: "d-flex align-center ga-2" }, [createVNode(VBtn, {
									icon: "mdi-refresh",
									variant: "text",
									loading: unref(loading),
									onClick: unref(fetchPriceTypes)
								}, null, 8, ["loading", "onClick"]), createVNode(VBtn, {
									color: "deep-purple-darken-1",
									variant: "tonal",
									"prepend-icon": "mdi-plus",
									onClick: openCreate
								}, {
									default: withCtx(() => [createTextVNode(" Новый вид цены ")]),
									_: 1
								})])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VAlert, {
										type: "info",
										variant: "tonal",
										class: "mb-4"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Здесь создаются глобальные виды цен: розничная, оптовая, дилерская, VIP, цена для сайта и другие. На следующем шаге свяжем их с сохранёнными расчётами конкретного товара. `);
											else return [createTextVNode(" Здесь создаются глобальные виды цен: розничная, оптовая, дилерская, VIP, цена для сайта и другие. На следующем шаге свяжем их с сохранёнными расчётами конкретного товара. ")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VRow, { class: "mb-2" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "5"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: search.value,
															"onUpdate:modelValue": ($event) => search.value = $event,
															label: "Поиск по видам цен",
															variant: "solo-inverted",
															density: "compact",
															clearable: "",
															"hide-details": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: search.value,
															"onUpdate:modelValue": ($event) => search.value = $event,
															label: "Поиск по видам цен",
															variant: "solo-inverted",
															density: "compact",
															clearable: "",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSelect, {
															modelValue: activeFilter.value,
															"onUpdate:modelValue": ($event) => activeFilter.value = $event,
															items: [
																{
																	title: "Все",
																	value: "all"
																},
																{
																	title: "Только активные",
																	value: "active"
																},
																{
																	title: "Только выключенные",
																	value: "inactive"
																}
															],
															label: "Активность",
															variant: "solo-inverted",
															density: "compact",
															"hide-details": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VSelect, {
															modelValue: activeFilter.value,
															"onUpdate:modelValue": ($event) => activeFilter.value = $event,
															items: [
																{
																	title: "Все",
																	value: "all"
																},
																{
																	title: "Только активные",
																	value: "active"
																},
																{
																	title: "Только выключенные",
																	value: "inactive"
																}
															],
															label: "Активность",
															variant: "solo-inverted",
															density: "compact",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSelect, {
															modelValue: publicFilter.value,
															"onUpdate:modelValue": ($event) => publicFilter.value = $event,
															items: [
																{
																	title: "Все",
																	value: "all"
																},
																{
																	title: "Публичные",
																	value: "public"
																},
																{
																	title: "Скрытые",
																	value: "hidden"
																}
															],
															label: "Публичность",
															variant: "solo-inverted",
															density: "compact",
															"hide-details": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VSelect, {
															modelValue: publicFilter.value,
															"onUpdate:modelValue": ($event) => publicFilter.value = $event,
															items: [
																{
																	title: "Все",
																	value: "all"
																},
																{
																	title: "Публичные",
																	value: "public"
																},
																{
																	title: "Скрытые",
																	value: "hidden"
																}
															],
															label: "Публичность",
															variant: "solo-inverted",
															density: "compact",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VCol, {
													cols: "12",
													md: "5"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: search.value,
														"onUpdate:modelValue": ($event) => search.value = $event,
														label: "Поиск по видам цен",
														variant: "solo-inverted",
														density: "compact",
														clearable: "",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: activeFilter.value,
														"onUpdate:modelValue": ($event) => activeFilter.value = $event,
														items: [
															{
																title: "Все",
																value: "all"
															},
															{
																title: "Только активные",
																value: "active"
															},
															{
																title: "Только выключенные",
																value: "inactive"
															}
														],
														label: "Активность",
														variant: "solo-inverted",
														density: "compact",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: publicFilter.value,
														"onUpdate:modelValue": ($event) => publicFilter.value = $event,
														items: [
															{
																title: "Все",
																value: "all"
															},
															{
																title: "Публичные",
																value: "public"
															},
															{
																title: "Скрытые",
																value: "hidden"
															}
														],
														label: "Публичность",
														variant: "solo-inverted",
														density: "compact",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VDataTable, {
										items: filteredItems.value,
										headers,
										loading: unref(loading),
										"items-per-page": "50",
										"fixed-header": "",
										height: "560px",
										density: "compact",
										class: "border rounded",
										hover: ""
									}, {
										"item.sort_order": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`<span class="text-caption"${_scopeId}>${ssrInterpolate(item.sort_order)}</span>`);
											else return [createVNode("span", { class: "text-caption" }, toDisplayString(item.sort_order), 1)];
										}),
										"item.name": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) {
												_push(`<div class="font-weight-medium"${_scopeId}>${ssrInterpolate(item.name)}</div>`);
												if (item.description) _push(`<div class="text-caption text-medium-emphasis"${_scopeId}>${ssrInterpolate(item.description)}</div>`);
												else _push(`<!---->`);
											} else return [createVNode("div", { class: "font-weight-medium" }, toDisplayString(item.name), 1), item.description ? (openBlock(), createBlock("div", {
												key: 0,
												class: "text-caption text-medium-emphasis"
											}, toDisplayString(item.description), 1)) : createCommentVNode("", true)];
										}),
										"item.code": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VChip, {
												size: "small",
												variant: "tonal"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(item.code)}`);
													else return [createTextVNode(toDisplayString(item.code), 1)];
												}),
												_: 2
											}, _parent, _scopeId));
											else return [createVNode(VChip, {
												size: "small",
												variant: "tonal"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(item.code), 1)]),
												_: 2
											}, 1024)];
										}),
										"item.currency.code": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`<span${_scopeId}>${ssrInterpolate(item.currency?.code || "—")}</span>`);
											else return [createVNode("span", null, toDisplayString(item.currency?.code || "—"), 1)];
										}),
										"item.markup_percent": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(formatPercent(item.markup_percent))}`);
											else return [createTextVNode(toDisplayString(formatPercent(item.markup_percent)), 1)];
										}),
										"item.target_margin_percent": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(formatPercent(item.target_margin_percent))}`);
											else return [createTextVNode(toDisplayString(formatPercent(item.target_margin_percent)), 1)];
										}),
										"item.rounding_step": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(formatNumber(item.rounding_step))}`);
											else return [createTextVNode(toDisplayString(formatNumber(item.rounding_step)), 1)];
										}),
										"item.is_public": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VChip, {
												size: "small",
												color: item.is_public ? "green" : "grey",
												variant: "tonal"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(item.is_public ? "public" : "hidden")}`);
													else return [createTextVNode(toDisplayString(item.is_public ? "public" : "hidden"), 1)];
												}),
												_: 2
											}, _parent, _scopeId));
											else return [createVNode(VChip, {
												size: "small",
												color: item.is_public ? "green" : "grey",
												variant: "tonal"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(item.is_public ? "public" : "hidden"), 1)]),
												_: 2
											}, 1032, ["color"])];
										}),
										"item.is_active": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VChip, {
												size: "small",
												color: item.is_active ? "green" : "grey",
												variant: "tonal"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(item.is_active ? "active" : "off")}`);
													else return [createTextVNode(toDisplayString(item.is_active ? "active" : "off"), 1)];
												}),
												_: 2
											}, _parent, _scopeId));
											else return [createVNode(VChip, {
												size: "small",
												color: item.is_active ? "green" : "grey",
												variant: "tonal"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(item.is_active ? "active" : "off"), 1)]),
												_: 2
											}, 1032, ["color"])];
										}),
										"item.actions": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VBtn, {
													icon: "mdi-pencil",
													size: "small",
													variant: "text",
													onClick: ($event) => openEdit(item)
												}, null, _parent, _scopeId));
												_push(ssrRenderComponent(VBtn, {
													icon: "mdi-delete",
													size: "small",
													variant: "text",
													color: "red",
													loading: !!unref(deleting)[item.id],
													onClick: ($event) => askDelete(item)
												}, null, _parent, _scopeId));
											} else return [createVNode(VBtn, {
												icon: "mdi-pencil",
												size: "small",
												variant: "text",
												onClick: ($event) => openEdit(item)
											}, null, 8, ["onClick"]), createVNode(VBtn, {
												icon: "mdi-delete",
												size: "small",
												variant: "text",
												color: "red",
												loading: !!unref(deleting)[item.id],
												onClick: ($event) => askDelete(item)
											}, null, 8, ["loading", "onClick"])];
										}),
										"no-data": withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`<div class="pa-6 text-center text-medium-emphasis"${_scopeId}> Виды цен пока не созданы. </div>`);
											else return [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Виды цен пока не созданы. ")];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VAlert, {
										type: "info",
										variant: "tonal",
										class: "mb-4"
									}, {
										default: withCtx(() => [createTextVNode(" Здесь создаются глобальные виды цен: розничная, оптовая, дилерская, VIP, цена для сайта и другие. На следующем шаге свяжем их с сохранёнными расчётами конкретного товара. ")]),
										_: 1
									}),
									createVNode(VRow, { class: "mb-2" }, {
										default: withCtx(() => [
											createVNode(VCol, {
												cols: "12",
												md: "5"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: search.value,
													"onUpdate:modelValue": ($event) => search.value = $event,
													label: "Поиск по видам цен",
													variant: "solo-inverted",
													density: "compact",
													clearable: "",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: activeFilter.value,
													"onUpdate:modelValue": ($event) => activeFilter.value = $event,
													items: [
														{
															title: "Все",
															value: "all"
														},
														{
															title: "Только активные",
															value: "active"
														},
														{
															title: "Только выключенные",
															value: "inactive"
														}
													],
													label: "Активность",
													variant: "solo-inverted",
													density: "compact",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: publicFilter.value,
													"onUpdate:modelValue": ($event) => publicFilter.value = $event,
													items: [
														{
															title: "Все",
															value: "all"
														},
														{
															title: "Публичные",
															value: "public"
														},
														{
															title: "Скрытые",
															value: "hidden"
														}
													],
													label: "Публичность",
													variant: "solo-inverted",
													density: "compact",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})
										]),
										_: 1
									}),
									createVNode(VDataTable, {
										items: filteredItems.value,
										headers,
										loading: unref(loading),
										"items-per-page": "50",
										"fixed-header": "",
										height: "560px",
										density: "compact",
										class: "border rounded",
										hover: ""
									}, {
										"item.sort_order": withCtx(({ item }) => [createVNode("span", { class: "text-caption" }, toDisplayString(item.sort_order), 1)]),
										"item.name": withCtx(({ item }) => [createVNode("div", { class: "font-weight-medium" }, toDisplayString(item.name), 1), item.description ? (openBlock(), createBlock("div", {
											key: 0,
											class: "text-caption text-medium-emphasis"
										}, toDisplayString(item.description), 1)) : createCommentVNode("", true)]),
										"item.code": withCtx(({ item }) => [createVNode(VChip, {
											size: "small",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(item.code), 1)]),
											_: 2
										}, 1024)]),
										"item.currency.code": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.currency?.code || "—"), 1)]),
										"item.markup_percent": withCtx(({ item }) => [createTextVNode(toDisplayString(formatPercent(item.markup_percent)), 1)]),
										"item.target_margin_percent": withCtx(({ item }) => [createTextVNode(toDisplayString(formatPercent(item.target_margin_percent)), 1)]),
										"item.rounding_step": withCtx(({ item }) => [createTextVNode(toDisplayString(formatNumber(item.rounding_step)), 1)]),
										"item.is_public": withCtx(({ item }) => [createVNode(VChip, {
											size: "small",
											color: item.is_public ? "green" : "grey",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(item.is_public ? "public" : "hidden"), 1)]),
											_: 2
										}, 1032, ["color"])]),
										"item.is_active": withCtx(({ item }) => [createVNode(VChip, {
											size: "small",
											color: item.is_active ? "green" : "grey",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(item.is_active ? "active" : "off"), 1)]),
											_: 2
										}, 1032, ["color"])]),
										"item.actions": withCtx(({ item }) => [createVNode(VBtn, {
											icon: "mdi-pencil",
											size: "small",
											variant: "text",
											onClick: ($event) => openEdit(item)
										}, null, 8, ["onClick"]), createVNode(VBtn, {
											icon: "mdi-delete",
											size: "small",
											variant: "text",
											color: "red",
											loading: !!unref(deleting)[item.id],
											onClick: ($event) => askDelete(item)
										}, null, 8, ["loading", "onClick"])]),
										"no-data": withCtx(() => [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Виды цен пока не созданы. ")]),
										_: 1
									}, 8, ["items", "loading"])
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogForm.value,
							"onUpdate:modelValue": ($event) => dialogForm.value = $event,
							width: "820"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(editedId.value ? "Редактировать вид цены" : "Новый вид цены")}`);
													else return [createTextVNode(toDisplayString(editedId.value ? "Редактировать вид цены" : "Новый вид цены"), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VRow, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "7"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: form.name,
																			"onUpdate:modelValue": ($event) => form.name = $event,
																			label: "Название",
																			variant: "outlined",
																			density: "compact"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: form.name,
																			"onUpdate:modelValue": ($event) => form.name = $event,
																			label: "Название",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "5"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: form.code,
																			"onUpdate:modelValue": ($event) => form.code = $event,
																			label: "Код",
																			variant: "outlined",
																			density: "compact",
																			hint: "Можно оставить пустым — сервер создаст из названия",
																			"persistent-hint": ""
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: form.code,
																			"onUpdate:modelValue": ($event) => form.code = $event,
																			label: "Код",
																			variant: "outlined",
																			density: "compact",
																			hint: "Можно оставить пустым — сервер создаст из названия",
																			"persistent-hint": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, { cols: "12" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextarea, {
																			modelValue: form.description,
																			"onUpdate:modelValue": ($event) => form.description = $event,
																			label: "Описание",
																			variant: "outlined",
																			density: "compact",
																			rows: "3"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextarea, {
																			modelValue: form.description,
																			"onUpdate:modelValue": ($event) => form.description = $event,
																			label: "Описание",
																			variant: "outlined",
																			density: "compact",
																			rows: "3"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VSelect, {
																			modelValue: form.currency_id,
																			"onUpdate:modelValue": ($event) => form.currency_id = $event,
																			items: __props.currencies,
																			"item-title": "code",
																			"item-value": "id",
																			label: "Валюта",
																			variant: "outlined",
																			density: "compact",
																			clearable: ""
																		}, {
																			item: withCtx(({ props, item }, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VListItem, mergeProps(props, {
																					title: item.raw.code,
																					subtitle: item.raw.name
																				}), null, _parent, _scopeId));
																				else return [createVNode(VListItem, mergeProps(props, {
																					title: item.raw.code,
																					subtitle: item.raw.name
																				}), null, 16, ["title", "subtitle"])];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		else return [createVNode(VSelect, {
																			modelValue: form.currency_id,
																			"onUpdate:modelValue": ($event) => form.currency_id = $event,
																			items: __props.currencies,
																			"item-title": "code",
																			"item-value": "id",
																			label: "Валюта",
																			variant: "outlined",
																			density: "compact",
																			clearable: ""
																		}, {
																			item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
																				title: item.raw.code,
																				subtitle: item.raw.name
																			}), null, 16, ["title", "subtitle"])]),
																			_: 1
																		}, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"items"
																		])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: form.markup_percent,
																			"onUpdate:modelValue": ($event) => form.markup_percent = $event,
																			label: "Наценка %",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: form.markup_percent,
																			"onUpdate:modelValue": ($event) => form.markup_percent = $event,
																			label: "Наценка %",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: form.target_margin_percent,
																			"onUpdate:modelValue": ($event) => form.target_margin_percent = $event,
																			label: "Целевая маржа %",
																			type: "number",
																			min: "0",
																			max: "99.99",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: form.target_margin_percent,
																			"onUpdate:modelValue": ($event) => form.target_margin_percent = $event,
																			label: "Целевая маржа %",
																			type: "number",
																			min: "0",
																			max: "99.99",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: form.rounding_step,
																			"onUpdate:modelValue": ($event) => form.rounding_step = $event,
																			label: "Шаг округления",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact",
																			hint: "Например: 1, 10, 50, 100",
																			"persistent-hint": ""
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: form.rounding_step,
																			"onUpdate:modelValue": ($event) => form.rounding_step = $event,
																			label: "Шаг округления",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact",
																			hint: "Например: 1, 10, 50, 100",
																			"persistent-hint": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: form.sort_order,
																			"onUpdate:modelValue": ($event) => form.sort_order = $event,
																			label: "Сортировка",
																			type: "number",
																			min: "0",
																			variant: "outlined",
																			density: "compact"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: form.sort_order,
																			"onUpdate:modelValue": ($event) => form.sort_order = $event,
																			label: "Сортировка",
																			type: "number",
																			min: "0",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "2"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VSwitch, {
																			modelValue: form.is_active,
																			"onUpdate:modelValue": ($event) => form.is_active = $event,
																			label: "Active",
																			color: "green",
																			inset: "",
																			"hide-details": ""
																		}, null, _parent, _scopeId));
																		else return [createVNode(VSwitch, {
																			modelValue: form.is_active,
																			"onUpdate:modelValue": ($event) => form.is_active = $event,
																			label: "Active",
																			color: "green",
																			inset: "",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "2"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VSwitch, {
																			modelValue: form.is_public,
																			"onUpdate:modelValue": ($event) => form.is_public = $event,
																			label: "Public",
																			color: "green",
																			inset: "",
																			"hide-details": ""
																		}, null, _parent, _scopeId));
																		else return [createVNode(VSwitch, {
																			modelValue: form.is_public,
																			"onUpdate:modelValue": ($event) => form.is_public = $event,
																			label: "Public",
																			color: "green",
																			inset: "",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
															} else return [
																createVNode(VCol, {
																	cols: "12",
																	md: "7"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: form.name,
																		"onUpdate:modelValue": ($event) => form.name = $event,
																		label: "Название",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "5"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: form.code,
																		"onUpdate:modelValue": ($event) => form.code = $event,
																		label: "Код",
																		variant: "outlined",
																		density: "compact",
																		hint: "Можно оставить пустым — сервер создаст из названия",
																		"persistent-hint": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, { cols: "12" }, {
																	default: withCtx(() => [createVNode(VTextarea, {
																		modelValue: form.description,
																		"onUpdate:modelValue": ($event) => form.description = $event,
																		label: "Описание",
																		variant: "outlined",
																		density: "compact",
																		rows: "3"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VSelect, {
																		modelValue: form.currency_id,
																		"onUpdate:modelValue": ($event) => form.currency_id = $event,
																		items: __props.currencies,
																		"item-title": "code",
																		"item-value": "id",
																		label: "Валюта",
																		variant: "outlined",
																		density: "compact",
																		clearable: ""
																	}, {
																		item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
																			title: item.raw.code,
																			subtitle: item.raw.name
																		}), null, 16, ["title", "subtitle"])]),
																		_: 1
																	}, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items"
																	])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: form.markup_percent,
																		"onUpdate:modelValue": ($event) => form.markup_percent = $event,
																		label: "Наценка %",
																		type: "number",
																		step: "0.01",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: form.target_margin_percent,
																		"onUpdate:modelValue": ($event) => form.target_margin_percent = $event,
																		label: "Целевая маржа %",
																		type: "number",
																		min: "0",
																		max: "99.99",
																		step: "0.01",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: form.rounding_step,
																		"onUpdate:modelValue": ($event) => form.rounding_step = $event,
																		label: "Шаг округления",
																		type: "number",
																		step: "0.01",
																		variant: "outlined",
																		density: "compact",
																		hint: "Например: 1, 10, 50, 100",
																		"persistent-hint": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: form.sort_order,
																		"onUpdate:modelValue": ($event) => form.sort_order = $event,
																		label: "Сортировка",
																		type: "number",
																		min: "0",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "2"
																}, {
																	default: withCtx(() => [createVNode(VSwitch, {
																		modelValue: form.is_active,
																		"onUpdate:modelValue": ($event) => form.is_active = $event,
																		label: "Active",
																		color: "green",
																		inset: "",
																		"hide-details": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "2"
																}, {
																	default: withCtx(() => [createVNode(VSwitch, {
																		modelValue: form.is_public,
																		"onUpdate:modelValue": ($event) => form.is_public = $event,
																		label: "Public",
																		color: "green",
																		inset: "",
																		"hide-details": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																})
															];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(VRow, null, {
														default: withCtx(() => [
															createVNode(VCol, {
																cols: "12",
																md: "7"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.name,
																	"onUpdate:modelValue": ($event) => form.name = $event,
																	label: "Название",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "5"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.code,
																	"onUpdate:modelValue": ($event) => form.code = $event,
																	label: "Код",
																	variant: "outlined",
																	density: "compact",
																	hint: "Можно оставить пустым — сервер создаст из названия",
																	"persistent-hint": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, { cols: "12" }, {
																default: withCtx(() => [createVNode(VTextarea, {
																	modelValue: form.description,
																	"onUpdate:modelValue": ($event) => form.description = $event,
																	label: "Описание",
																	variant: "outlined",
																	density: "compact",
																	rows: "3"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VSelect, {
																	modelValue: form.currency_id,
																	"onUpdate:modelValue": ($event) => form.currency_id = $event,
																	items: __props.currencies,
																	"item-title": "code",
																	"item-value": "id",
																	label: "Валюта",
																	variant: "outlined",
																	density: "compact",
																	clearable: ""
																}, {
																	item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
																		title: item.raw.code,
																		subtitle: item.raw.name
																	}), null, 16, ["title", "subtitle"])]),
																	_: 1
																}, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items"
																])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.markup_percent,
																	"onUpdate:modelValue": ($event) => form.markup_percent = $event,
																	label: "Наценка %",
																	type: "number",
																	step: "0.01",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.target_margin_percent,
																	"onUpdate:modelValue": ($event) => form.target_margin_percent = $event,
																	label: "Целевая маржа %",
																	type: "number",
																	min: "0",
																	max: "99.99",
																	step: "0.01",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.rounding_step,
																	"onUpdate:modelValue": ($event) => form.rounding_step = $event,
																	label: "Шаг округления",
																	type: "number",
																	step: "0.01",
																	variant: "outlined",
																	density: "compact",
																	hint: "Например: 1, 10, 50, 100",
																	"persistent-hint": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.sort_order,
																	"onUpdate:modelValue": ($event) => form.sort_order = $event,
																	label: "Сортировка",
																	type: "number",
																	min: "0",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "2"
															}, {
																default: withCtx(() => [createVNode(VSwitch, {
																	modelValue: form.is_active,
																	"onUpdate:modelValue": ($event) => form.is_active = $event,
																	label: "Active",
																	color: "green",
																	inset: "",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "2"
															}, {
																default: withCtx(() => [createVNode(VSwitch, {
																	modelValue: form.is_public,
																	"onUpdate:modelValue": ($event) => form.is_public = $event,
																	label: "Public",
																	color: "green",
																	inset: "",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})
														]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogForm.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Закрыть `);
																else return [createTextVNode(" Закрыть ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "deep-purple-darken-1",
															variant: "tonal",
															loading: unref(saving),
															onClick: submit
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Сохранить `);
																else return [createTextVNode(" Сохранить ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogForm.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Закрыть ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "deep-purple-darken-1",
														variant: "tonal",
														loading: unref(saving),
														onClick: submit
													}, {
														default: withCtx(() => [createTextVNode(" Сохранить ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode(toDisplayString(editedId.value ? "Редактировать вид цены" : "Новый вид цены"), 1)]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [
														createVNode(VCol, {
															cols: "12",
															md: "7"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.name,
																"onUpdate:modelValue": ($event) => form.name = $event,
																label: "Название",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "5"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.code,
																"onUpdate:modelValue": ($event) => form.code = $event,
																label: "Код",
																variant: "outlined",
																density: "compact",
																hint: "Можно оставить пустым — сервер создаст из названия",
																"persistent-hint": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(VTextarea, {
																modelValue: form.description,
																"onUpdate:modelValue": ($event) => form.description = $event,
																label: "Описание",
																variant: "outlined",
																density: "compact",
																rows: "3"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VSelect, {
																modelValue: form.currency_id,
																"onUpdate:modelValue": ($event) => form.currency_id = $event,
																items: __props.currencies,
																"item-title": "code",
																"item-value": "id",
																label: "Валюта",
																variant: "outlined",
																density: "compact",
																clearable: ""
															}, {
																item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
																	title: item.raw.code,
																	subtitle: item.raw.name
																}), null, 16, ["title", "subtitle"])]),
																_: 1
															}, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items"
															])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.markup_percent,
																"onUpdate:modelValue": ($event) => form.markup_percent = $event,
																label: "Наценка %",
																type: "number",
																step: "0.01",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.target_margin_percent,
																"onUpdate:modelValue": ($event) => form.target_margin_percent = $event,
																label: "Целевая маржа %",
																type: "number",
																min: "0",
																max: "99.99",
																step: "0.01",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.rounding_step,
																"onUpdate:modelValue": ($event) => form.rounding_step = $event,
																label: "Шаг округления",
																type: "number",
																step: "0.01",
																variant: "outlined",
																density: "compact",
																hint: "Например: 1, 10, 50, 100",
																"persistent-hint": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.sort_order,
																"onUpdate:modelValue": ($event) => form.sort_order = $event,
																label: "Сортировка",
																type: "number",
																min: "0",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "2"
														}, {
															default: withCtx(() => [createVNode(VSwitch, {
																modelValue: form.is_active,
																"onUpdate:modelValue": ($event) => form.is_active = $event,
																label: "Active",
																color: "green",
																inset: "",
																"hide-details": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "2"
														}, {
															default: withCtx(() => [createVNode(VSwitch, {
																modelValue: form.is_public,
																"onUpdate:modelValue": ($event) => form.is_public = $event,
																label: "Public",
																color: "green",
																inset: "",
																"hide-details": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})
													]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogForm.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Закрыть ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "deep-purple-darken-1",
													variant: "tonal",
													loading: unref(saving),
													onClick: submit
												}, {
													default: withCtx(() => [createTextVNode(" Сохранить ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, null, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode(toDisplayString(editedId.value ? "Редактировать вид цены" : "Новый вид цены"), 1)]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [
													createVNode(VCol, {
														cols: "12",
														md: "7"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.name,
															"onUpdate:modelValue": ($event) => form.name = $event,
															label: "Название",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "5"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.code,
															"onUpdate:modelValue": ($event) => form.code = $event,
															label: "Код",
															variant: "outlined",
															density: "compact",
															hint: "Можно оставить пустым — сервер создаст из названия",
															"persistent-hint": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VTextarea, {
															modelValue: form.description,
															"onUpdate:modelValue": ($event) => form.description = $event,
															label: "Описание",
															variant: "outlined",
															density: "compact",
															rows: "3"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VSelect, {
															modelValue: form.currency_id,
															"onUpdate:modelValue": ($event) => form.currency_id = $event,
															items: __props.currencies,
															"item-title": "code",
															"item-value": "id",
															label: "Валюта",
															variant: "outlined",
															density: "compact",
															clearable: ""
														}, {
															item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
																title: item.raw.code,
																subtitle: item.raw.name
															}), null, 16, ["title", "subtitle"])]),
															_: 1
														}, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.markup_percent,
															"onUpdate:modelValue": ($event) => form.markup_percent = $event,
															label: "Наценка %",
															type: "number",
															step: "0.01",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.target_margin_percent,
															"onUpdate:modelValue": ($event) => form.target_margin_percent = $event,
															label: "Целевая маржа %",
															type: "number",
															min: "0",
															max: "99.99",
															step: "0.01",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.rounding_step,
															"onUpdate:modelValue": ($event) => form.rounding_step = $event,
															label: "Шаг округления",
															type: "number",
															step: "0.01",
															variant: "outlined",
															density: "compact",
															hint: "Например: 1, 10, 50, 100",
															"persistent-hint": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.sort_order,
															"onUpdate:modelValue": ($event) => form.sort_order = $event,
															label: "Сортировка",
															type: "number",
															min: "0",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "2"
													}, {
														default: withCtx(() => [createVNode(VSwitch, {
															modelValue: form.is_active,
															"onUpdate:modelValue": ($event) => form.is_active = $event,
															label: "Active",
															color: "green",
															inset: "",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "2"
													}, {
														default: withCtx(() => [createVNode(VSwitch, {
															modelValue: form.is_public,
															"onUpdate:modelValue": ($event) => form.is_public = $event,
															label: "Public",
															color: "green",
															inset: "",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})
												]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogForm.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Закрыть ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "deep-purple-darken-1",
												variant: "tonal",
												loading: unref(saving),
												onClick: submit
											}, {
												default: withCtx(() => [createTextVNode(" Сохранить ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogDelete.value,
							"onUpdate:modelValue": ($event) => dialogDelete.value = $event,
							width: "520"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Удалить вид цены?`);
													else return [createTextVNode("Удалить вид цены?")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Удалить: <strong${_scopeId}>${ssrInterpolate(selectedItem.value?.name)}</strong> ? `);
													else return [
														createTextVNode(" Удалить: "),
														createVNode("strong", null, toDisplayString(selectedItem.value?.name), 1),
														createTextVNode(" ? ")
													];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogDelete.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Отмена `);
																else return [createTextVNode(" Отмена ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "red",
															variant: "tonal",
															loading: selectedItem.value?.id ? !!unref(deleting)[selectedItem.value.id] : false,
															onClick: confirmDelete
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Удалить `);
																else return [createTextVNode(" Удалить ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogDelete.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Отмена ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "red",
														variant: "tonal",
														loading: selectedItem.value?.id ? !!unref(deleting)[selectedItem.value.id] : false,
														onClick: confirmDelete
													}, {
														default: withCtx(() => [createTextVNode(" Удалить ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Удалить вид цены?")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [
													createTextVNode(" Удалить: "),
													createVNode("strong", null, toDisplayString(selectedItem.value?.name), 1),
													createTextVNode(" ? ")
												]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogDelete.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Отмена ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "red",
													variant: "tonal",
													loading: selectedItem.value?.id ? !!unref(deleting)[selectedItem.value.id] : false,
													onClick: confirmDelete
												}, {
													default: withCtx(() => [createTextVNode(" Удалить ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, null, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Удалить вид цены?")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [
												createTextVNode(" Удалить: "),
												createVNode("strong", null, toDisplayString(selectedItem.value?.name), 1),
												createTextVNode(" ? ")
											]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogDelete.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Отмена ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "red",
												variant: "tonal",
												loading: selectedItem.value?.id ? !!unref(deleting)[selectedItem.value.id] : false,
												onClick: confirmDelete
											}, {
												default: withCtx(() => [createTextVNode(" Удалить ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx(() => [createVNode("span", null, "Виды цен"), createVNode("div", { class: "d-flex align-center ga-2" }, [createVNode(VBtn, {
								icon: "mdi-refresh",
								variant: "text",
								loading: unref(loading),
								onClick: unref(fetchPriceTypes)
							}, null, 8, ["loading", "onClick"]), createVNode(VBtn, {
								color: "deep-purple-darken-1",
								variant: "tonal",
								"prepend-icon": "mdi-plus",
								onClick: openCreate
							}, {
								default: withCtx(() => [createTextVNode(" Новый вид цены ")]),
								_: 1
							})])]),
							_: 1
						}),
						createVNode(VCardText, null, {
							default: withCtx(() => [
								createVNode(VAlert, {
									type: "info",
									variant: "tonal",
									class: "mb-4"
								}, {
									default: withCtx(() => [createTextVNode(" Здесь создаются глобальные виды цен: розничная, оптовая, дилерская, VIP, цена для сайта и другие. На следующем шаге свяжем их с сохранёнными расчётами конкретного товара. ")]),
									_: 1
								}),
								createVNode(VRow, { class: "mb-2" }, {
									default: withCtx(() => [
										createVNode(VCol, {
											cols: "12",
											md: "5"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: search.value,
												"onUpdate:modelValue": ($event) => search.value = $event,
												label: "Поиск по видам цен",
												variant: "solo-inverted",
												density: "compact",
												clearable: "",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "3"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: activeFilter.value,
												"onUpdate:modelValue": ($event) => activeFilter.value = $event,
												items: [
													{
														title: "Все",
														value: "all"
													},
													{
														title: "Только активные",
														value: "active"
													},
													{
														title: "Только выключенные",
														value: "inactive"
													}
												],
												label: "Активность",
												variant: "solo-inverted",
												density: "compact",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "3"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: publicFilter.value,
												"onUpdate:modelValue": ($event) => publicFilter.value = $event,
												items: [
													{
														title: "Все",
														value: "all"
													},
													{
														title: "Публичные",
														value: "public"
													},
													{
														title: "Скрытые",
														value: "hidden"
													}
												],
												label: "Публичность",
												variant: "solo-inverted",
												density: "compact",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})
									]),
									_: 1
								}),
								createVNode(VDataTable, {
									items: filteredItems.value,
									headers,
									loading: unref(loading),
									"items-per-page": "50",
									"fixed-header": "",
									height: "560px",
									density: "compact",
									class: "border rounded",
									hover: ""
								}, {
									"item.sort_order": withCtx(({ item }) => [createVNode("span", { class: "text-caption" }, toDisplayString(item.sort_order), 1)]),
									"item.name": withCtx(({ item }) => [createVNode("div", { class: "font-weight-medium" }, toDisplayString(item.name), 1), item.description ? (openBlock(), createBlock("div", {
										key: 0,
										class: "text-caption text-medium-emphasis"
									}, toDisplayString(item.description), 1)) : createCommentVNode("", true)]),
									"item.code": withCtx(({ item }) => [createVNode(VChip, {
										size: "small",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(item.code), 1)]),
										_: 2
									}, 1024)]),
									"item.currency.code": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.currency?.code || "—"), 1)]),
									"item.markup_percent": withCtx(({ item }) => [createTextVNode(toDisplayString(formatPercent(item.markup_percent)), 1)]),
									"item.target_margin_percent": withCtx(({ item }) => [createTextVNode(toDisplayString(formatPercent(item.target_margin_percent)), 1)]),
									"item.rounding_step": withCtx(({ item }) => [createTextVNode(toDisplayString(formatNumber(item.rounding_step)), 1)]),
									"item.is_public": withCtx(({ item }) => [createVNode(VChip, {
										size: "small",
										color: item.is_public ? "green" : "grey",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(item.is_public ? "public" : "hidden"), 1)]),
										_: 2
									}, 1032, ["color"])]),
									"item.is_active": withCtx(({ item }) => [createVNode(VChip, {
										size: "small",
										color: item.is_active ? "green" : "grey",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(item.is_active ? "active" : "off"), 1)]),
										_: 2
									}, 1032, ["color"])]),
									"item.actions": withCtx(({ item }) => [createVNode(VBtn, {
										icon: "mdi-pencil",
										size: "small",
										variant: "text",
										onClick: ($event) => openEdit(item)
									}, null, 8, ["onClick"]), createVNode(VBtn, {
										icon: "mdi-delete",
										size: "small",
										variant: "text",
										color: "red",
										loading: !!unref(deleting)[item.id],
										onClick: ($event) => askDelete(item)
									}, null, 8, ["loading", "onClick"])]),
									"no-data": withCtx(() => [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Виды цен пока не созданы. ")]),
									_: 1
								}, 8, ["items", "loading"])
							]),
							_: 1
						}),
						createVNode(VDialog, {
							modelValue: dialogForm.value,
							"onUpdate:modelValue": ($event) => dialogForm.value = $event,
							width: "820"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode(toDisplayString(editedId.value ? "Редактировать вид цены" : "Новый вид цены"), 1)]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VRow, null, {
											default: withCtx(() => [
												createVNode(VCol, {
													cols: "12",
													md: "7"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.name,
														"onUpdate:modelValue": ($event) => form.name = $event,
														label: "Название",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "5"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.code,
														"onUpdate:modelValue": ($event) => form.code = $event,
														label: "Код",
														variant: "outlined",
														density: "compact",
														hint: "Можно оставить пустым — сервер создаст из названия",
														"persistent-hint": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: form.description,
														"onUpdate:modelValue": ($event) => form.description = $event,
														label: "Описание",
														variant: "outlined",
														density: "compact",
														rows: "3"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: form.currency_id,
														"onUpdate:modelValue": ($event) => form.currency_id = $event,
														items: __props.currencies,
														"item-title": "code",
														"item-value": "id",
														label: "Валюта",
														variant: "outlined",
														density: "compact",
														clearable: ""
													}, {
														item: withCtx(({ props, item }) => [createVNode(VListItem, mergeProps(props, {
															title: item.raw.code,
															subtitle: item.raw.name
														}), null, 16, ["title", "subtitle"])]),
														_: 1
													}, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.markup_percent,
														"onUpdate:modelValue": ($event) => form.markup_percent = $event,
														label: "Наценка %",
														type: "number",
														step: "0.01",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.target_margin_percent,
														"onUpdate:modelValue": ($event) => form.target_margin_percent = $event,
														label: "Целевая маржа %",
														type: "number",
														min: "0",
														max: "99.99",
														step: "0.01",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.rounding_step,
														"onUpdate:modelValue": ($event) => form.rounding_step = $event,
														label: "Шаг округления",
														type: "number",
														step: "0.01",
														variant: "outlined",
														density: "compact",
														hint: "Например: 1, 10, 50, 100",
														"persistent-hint": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.sort_order,
														"onUpdate:modelValue": ($event) => form.sort_order = $event,
														label: "Сортировка",
														type: "number",
														min: "0",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "2"
												}, {
													default: withCtx(() => [createVNode(VSwitch, {
														modelValue: form.is_active,
														"onUpdate:modelValue": ($event) => form.is_active = $event,
														label: "Active",
														color: "green",
														inset: "",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "2"
												}, {
													default: withCtx(() => [createVNode(VSwitch, {
														modelValue: form.is_public,
														"onUpdate:modelValue": ($event) => form.is_public = $event,
														label: "Public",
														color: "green",
														inset: "",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})
											]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogForm.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Закрыть ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "deep-purple-darken-1",
											variant: "tonal",
											loading: unref(saving),
											onClick: submit
										}, {
											default: withCtx(() => [createTextVNode(" Сохранить ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VDialog, {
							modelValue: dialogDelete.value,
							"onUpdate:modelValue": ($event) => dialogDelete.value = $event,
							width: "520"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Удалить вид цены?")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [
											createTextVNode(" Удалить: "),
											createVNode("strong", null, toDisplayString(selectedItem.value?.name), 1),
											createTextVNode(" ? ")
										]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogDelete.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Отмена ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "red",
											variant: "tonal",
											loading: selectedItem.value?.id ? !!unref(deleting)[selectedItem.value.id] : false,
											onClick: confirmDelete
										}, {
											default: withCtx(() => [createTextVNode(" Удалить ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"])
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$3 = _sfc_main$3.setup;
_sfc_main$3.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Goods/GoodPriceTypesTab.vue");
	return _sfc_setup$3 ? _sfc_setup$3(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Goods/GoodPriceTypeValuesTab.vue
var _sfc_main$2 = {
	__name: "GoodPriceTypeValuesTab",
	__ssrInlineRender: true,
	props: {
		goodId: {
			type: Number,
			required: true
		},
		currencies: {
			type: Array,
			default: () => []
		}
	},
	setup(__props) {
		const { values, loading, saving, deleting, fetchValues, updateValue, deleteValue } = useGoodPriceTypeValues(__props.goodId);
		const dialogEdit = ref(false);
		const edited = ref(null);
		const headers = [
			{
				key: "price_type.name",
				title: "Вид цены",
				sortable: true
			},
			{
				key: "price_gross",
				title: "Цена с НДС / кг",
				sortable: true,
				width: "170px"
			},
			{
				key: "price_net",
				title: "Цена без НДС / кг",
				sortable: true,
				width: "180px"
			},
			{
				key: "vat_rate",
				title: "НДС",
				sortable: true,
				width: "90px"
			},
			{
				key: "currency.code",
				title: "Валюта",
				sortable: true,
				width: "110px"
			},
			{
				key: "is_manual",
				title: "Manual",
				sortable: true,
				width: "110px"
			},
			{
				key: "is_published",
				title: "Public",
				sortable: true,
				width: "110px"
			},
			{
				key: "actions",
				title: "",
				sortable: false,
				width: "130px"
			}
		];
		function formatMoney(value) {
			if (value === null || value === void 0 || value === "") return "—";
			return new Intl.NumberFormat("ru-RU", {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
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
				valid_to: item.valid_to || null
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
				valid_to: edited.value.valid_to
			});
			dialogEdit.value = false;
		}
		onMounted(() => {
			fetchValues();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<span${_scopeId}>Актуальные цены товара по видам цен</span>`);
									_push(ssrRenderComponent(VBtn, {
										icon: "mdi-refresh",
										variant: "text",
										loading: unref(loading),
										onClick: unref(fetchValues)
									}, null, _parent, _scopeId));
								} else return [createVNode("span", null, "Актуальные цены товара по видам цен"), createVNode(VBtn, {
									icon: "mdi-refresh",
									variant: "text",
									loading: unref(loading),
									onClick: unref(fetchValues)
								}, null, 8, ["loading", "onClick"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VAlert, {
										type: "info",
										variant: "tonal",
										class: "mb-4"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Здесь хранятся цены конкретного товара для разных типов покупателей. Эти цены потом можно будет выводить в интернет-магазине. `);
											else return [createTextVNode(" Здесь хранятся цены конкретного товара для разных типов покупателей. Эти цены потом можно будет выводить в интернет-магазине. ")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VDataTable, {
										items: unref(values),
										headers,
										loading: unref(loading),
										"items-per-page": "50",
										"fixed-header": "",
										height: "560px",
										density: "compact",
										class: "border rounded",
										hover: ""
									}, {
										"item.price_type.name": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`<div class="font-weight-medium"${_scopeId}>${ssrInterpolate(item.price_type?.name || "—")}</div><div class="text-caption text-medium-emphasis"${_scopeId}>${ssrInterpolate(item.price_type?.code || "—")}</div>`);
											else return [createVNode("div", { class: "font-weight-medium" }, toDisplayString(item.price_type?.name || "—"), 1), createVNode("div", { class: "text-caption text-medium-emphasis" }, toDisplayString(item.price_type?.code || "—"), 1)];
										}),
										"item.price_gross": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`<strong${_scopeId}>${ssrInterpolate(formatMoney(item.price_gross))}</strong>`);
											else return [createVNode("strong", null, toDisplayString(formatMoney(item.price_gross)), 1)];
										}),
										"item.price_net": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(formatMoney(item.price_net))}`);
											else return [createTextVNode(toDisplayString(formatMoney(item.price_net)), 1)];
										}),
										"item.vat_rate": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(Number(item.vat_rate || 0).toFixed(2))}% `);
											else return [createTextVNode(toDisplayString(Number(item.vat_rate || 0).toFixed(2)) + "% ", 1)];
										}),
										"item.currency.code": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(item.currency?.code || item.price_type?.currency?.code || "RUB")}`);
											else return [createTextVNode(toDisplayString(item.currency?.code || item.price_type?.currency?.code || "RUB"), 1)];
										}),
										"item.is_manual": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VChip, {
												size: "small",
												variant: "tonal",
												color: item.is_manual ? "orange" : "grey"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(item.is_manual ? "manual" : "auto")}`);
													else return [createTextVNode(toDisplayString(item.is_manual ? "manual" : "auto"), 1)];
												}),
												_: 2
											}, _parent, _scopeId));
											else return [createVNode(VChip, {
												size: "small",
												variant: "tonal",
												color: item.is_manual ? "orange" : "grey"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(item.is_manual ? "manual" : "auto"), 1)]),
												_: 2
											}, 1032, ["color"])];
										}),
										"item.is_published": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VChip, {
												size: "small",
												variant: "tonal",
												color: item.is_published ? "green" : "grey"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(item.is_published ? "public" : "hidden")}`);
													else return [createTextVNode(toDisplayString(item.is_published ? "public" : "hidden"), 1)];
												}),
												_: 2
											}, _parent, _scopeId));
											else return [createVNode(VChip, {
												size: "small",
												variant: "tonal",
												color: item.is_published ? "green" : "grey"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(item.is_published ? "public" : "hidden"), 1)]),
												_: 2
											}, 1032, ["color"])];
										}),
										"item.actions": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VBtn, {
													icon: "mdi-pencil",
													size: "small",
													variant: "text",
													onClick: ($event) => openEdit(item)
												}, null, _parent, _scopeId));
												_push(ssrRenderComponent(VBtn, {
													icon: "mdi-delete",
													size: "small",
													variant: "text",
													color: "red",
													loading: !!unref(deleting)[item.id],
													onClick: ($event) => unref(deleteValue)(item.id)
												}, null, _parent, _scopeId));
											} else return [createVNode(VBtn, {
												icon: "mdi-pencil",
												size: "small",
												variant: "text",
												onClick: ($event) => openEdit(item)
											}, null, 8, ["onClick"]), createVNode(VBtn, {
												icon: "mdi-delete",
												size: "small",
												variant: "text",
												color: "red",
												loading: !!unref(deleting)[item.id],
												onClick: ($event) => unref(deleteValue)(item.id)
											}, null, 8, ["loading", "onClick"])];
										}),
										"no-data": withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`<div class="pa-6 text-center text-medium-emphasis"${_scopeId}> Цены по видам цен пока не назначены. Перейдите во вкладку “Сохранённые расчёты” и примените расчёт к виду цены. </div>`);
											else return [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Цены по видам цен пока не назначены. Перейдите во вкладку “Сохранённые расчёты” и примените расчёт к виду цены. ")];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VAlert, {
									type: "info",
									variant: "tonal",
									class: "mb-4"
								}, {
									default: withCtx(() => [createTextVNode(" Здесь хранятся цены конкретного товара для разных типов покупателей. Эти цены потом можно будет выводить в интернет-магазине. ")]),
									_: 1
								}), createVNode(VDataTable, {
									items: unref(values),
									headers,
									loading: unref(loading),
									"items-per-page": "50",
									"fixed-header": "",
									height: "560px",
									density: "compact",
									class: "border rounded",
									hover: ""
								}, {
									"item.price_type.name": withCtx(({ item }) => [createVNode("div", { class: "font-weight-medium" }, toDisplayString(item.price_type?.name || "—"), 1), createVNode("div", { class: "text-caption text-medium-emphasis" }, toDisplayString(item.price_type?.code || "—"), 1)]),
									"item.price_gross": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.price_gross)), 1)]),
									"item.price_net": withCtx(({ item }) => [createTextVNode(toDisplayString(formatMoney(item.price_net)), 1)]),
									"item.vat_rate": withCtx(({ item }) => [createTextVNode(toDisplayString(Number(item.vat_rate || 0).toFixed(2)) + "% ", 1)]),
									"item.currency.code": withCtx(({ item }) => [createTextVNode(toDisplayString(item.currency?.code || item.price_type?.currency?.code || "RUB"), 1)]),
									"item.is_manual": withCtx(({ item }) => [createVNode(VChip, {
										size: "small",
										variant: "tonal",
										color: item.is_manual ? "orange" : "grey"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(item.is_manual ? "manual" : "auto"), 1)]),
										_: 2
									}, 1032, ["color"])]),
									"item.is_published": withCtx(({ item }) => [createVNode(VChip, {
										size: "small",
										variant: "tonal",
										color: item.is_published ? "green" : "grey"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(item.is_published ? "public" : "hidden"), 1)]),
										_: 2
									}, 1032, ["color"])]),
									"item.actions": withCtx(({ item }) => [createVNode(VBtn, {
										icon: "mdi-pencil",
										size: "small",
										variant: "text",
										onClick: ($event) => openEdit(item)
									}, null, 8, ["onClick"]), createVNode(VBtn, {
										icon: "mdi-delete",
										size: "small",
										variant: "text",
										color: "red",
										loading: !!unref(deleting)[item.id],
										onClick: ($event) => unref(deleteValue)(item.id)
									}, null, 8, ["loading", "onClick"])]),
									"no-data": withCtx(() => [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Цены по видам цен пока не назначены. Перейдите во вкладку “Сохранённые расчёты” и примените расчёт к виду цены. ")]),
									_: 1
								}, 8, ["items", "loading"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogEdit.value,
							"onUpdate:modelValue": ($event) => dialogEdit.value = $event,
							width: "720"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Редактировать цену товара`);
													else return [createTextVNode("Редактировать цену товара")];
												}),
												_: 1
											}, _parent, _scopeId));
											if (edited.value) _push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VRow, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: edited.value.price_gross,
																			"onUpdate:modelValue": ($event) => edited.value.price_gross = $event,
																			label: "Цена с НДС / кг",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: edited.value.price_gross,
																			"onUpdate:modelValue": ($event) => edited.value.price_gross = $event,
																			label: "Цена с НДС / кг",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: edited.value.price_net,
																			"onUpdate:modelValue": ($event) => edited.value.price_net = $event,
																			label: "Цена без НДС / кг",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: edited.value.price_net,
																			"onUpdate:modelValue": ($event) => edited.value.price_net = $event,
																			label: "Цена без НДС / кг",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: edited.value.vat_rate,
																			"onUpdate:modelValue": ($event) => edited.value.vat_rate = $event,
																			label: "НДС %",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: edited.value.vat_rate,
																			"onUpdate:modelValue": ($event) => edited.value.vat_rate = $event,
																			label: "НДС %",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VSelect, {
																			modelValue: edited.value.currency_id,
																			"onUpdate:modelValue": ($event) => edited.value.currency_id = $event,
																			items: __props.currencies,
																			"item-value": "id",
																			"item-title": "code",
																			label: "Валюта",
																			variant: "outlined",
																			density: "compact",
																			clearable: ""
																		}, null, _parent, _scopeId));
																		else return [createVNode(VSelect, {
																			modelValue: edited.value.currency_id,
																			"onUpdate:modelValue": ($event) => edited.value.currency_id = $event,
																			items: __props.currencies,
																			"item-value": "id",
																			"item-title": "code",
																			label: "Валюта",
																			variant: "outlined",
																			density: "compact",
																			clearable: ""
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"items"
																		])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VSwitch, {
																			modelValue: edited.value.is_manual,
																			"onUpdate:modelValue": ($event) => edited.value.is_manual = $event,
																			label: "Ручная цена",
																			color: "orange",
																			inset: "",
																			"hide-details": ""
																		}, null, _parent, _scopeId));
																		else return [createVNode(VSwitch, {
																			modelValue: edited.value.is_manual,
																			"onUpdate:modelValue": ($event) => edited.value.is_manual = $event,
																			label: "Ручная цена",
																			color: "orange",
																			inset: "",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VSwitch, {
																			modelValue: edited.value.is_published,
																			"onUpdate:modelValue": ($event) => edited.value.is_published = $event,
																			label: "Публиковать",
																			color: "green",
																			inset: "",
																			"hide-details": ""
																		}, null, _parent, _scopeId));
																		else return [createVNode(VSwitch, {
																			modelValue: edited.value.is_published,
																			"onUpdate:modelValue": ($event) => edited.value.is_published = $event,
																			label: "Публиковать",
																			color: "green",
																			inset: "",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, { cols: "12" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextarea, {
																			modelValue: edited.value.manual_comment,
																			"onUpdate:modelValue": ($event) => edited.value.manual_comment = $event,
																			label: "Комментарий",
																			rows: "3",
																			variant: "outlined",
																			density: "compact"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextarea, {
																			modelValue: edited.value.manual_comment,
																			"onUpdate:modelValue": ($event) => edited.value.manual_comment = $event,
																			label: "Комментарий",
																			rows: "3",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
															} else return [
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: edited.value.price_gross,
																		"onUpdate:modelValue": ($event) => edited.value.price_gross = $event,
																		label: "Цена с НДС / кг",
																		type: "number",
																		step: "0.01",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: edited.value.price_net,
																		"onUpdate:modelValue": ($event) => edited.value.price_net = $event,
																		label: "Цена без НДС / кг",
																		type: "number",
																		step: "0.01",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: edited.value.vat_rate,
																		"onUpdate:modelValue": ($event) => edited.value.vat_rate = $event,
																		label: "НДС %",
																		type: "number",
																		step: "0.01",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VSelect, {
																		modelValue: edited.value.currency_id,
																		"onUpdate:modelValue": ($event) => edited.value.currency_id = $event,
																		items: __props.currencies,
																		"item-value": "id",
																		"item-title": "code",
																		label: "Валюта",
																		variant: "outlined",
																		density: "compact",
																		clearable: ""
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items"
																	])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VSwitch, {
																		modelValue: edited.value.is_manual,
																		"onUpdate:modelValue": ($event) => edited.value.is_manual = $event,
																		label: "Ручная цена",
																		color: "orange",
																		inset: "",
																		"hide-details": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VSwitch, {
																		modelValue: edited.value.is_published,
																		"onUpdate:modelValue": ($event) => edited.value.is_published = $event,
																		label: "Публиковать",
																		color: "green",
																		inset: "",
																		"hide-details": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, { cols: "12" }, {
																	default: withCtx(() => [createVNode(VTextarea, {
																		modelValue: edited.value.manual_comment,
																		"onUpdate:modelValue": ($event) => edited.value.manual_comment = $event,
																		label: "Комментарий",
																		rows: "3",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																})
															];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(VRow, null, {
														default: withCtx(() => [
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: edited.value.price_gross,
																	"onUpdate:modelValue": ($event) => edited.value.price_gross = $event,
																	label: "Цена с НДС / кг",
																	type: "number",
																	step: "0.01",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: edited.value.price_net,
																	"onUpdate:modelValue": ($event) => edited.value.price_net = $event,
																	label: "Цена без НДС / кг",
																	type: "number",
																	step: "0.01",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: edited.value.vat_rate,
																	"onUpdate:modelValue": ($event) => edited.value.vat_rate = $event,
																	label: "НДС %",
																	type: "number",
																	step: "0.01",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VSelect, {
																	modelValue: edited.value.currency_id,
																	"onUpdate:modelValue": ($event) => edited.value.currency_id = $event,
																	items: __props.currencies,
																	"item-value": "id",
																	"item-title": "code",
																	label: "Валюта",
																	variant: "outlined",
																	density: "compact",
																	clearable: ""
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items"
																])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VSwitch, {
																	modelValue: edited.value.is_manual,
																	"onUpdate:modelValue": ($event) => edited.value.is_manual = $event,
																	label: "Ручная цена",
																	color: "orange",
																	inset: "",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VSwitch, {
																	modelValue: edited.value.is_published,
																	"onUpdate:modelValue": ($event) => edited.value.is_published = $event,
																	label: "Публиковать",
																	color: "green",
																	inset: "",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, { cols: "12" }, {
																default: withCtx(() => [createVNode(VTextarea, {
																	modelValue: edited.value.manual_comment,
																	"onUpdate:modelValue": ($event) => edited.value.manual_comment = $event,
																	label: "Комментарий",
																	rows: "3",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})
														]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											else _push(`<!---->`);
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogEdit.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Закрыть `);
																else return [createTextVNode(" Закрыть ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "deep-purple-darken-1",
															variant: "tonal",
															loading: unref(saving),
															onClick: saveEdit
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Сохранить `);
																else return [createTextVNode(" Сохранить ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogEdit.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Закрыть ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "deep-purple-darken-1",
														variant: "tonal",
														loading: unref(saving),
														onClick: saveEdit
													}, {
														default: withCtx(() => [createTextVNode(" Сохранить ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Редактировать цену товара")]),
												_: 1
											}),
											edited.value ? (openBlock(), createBlock(VCardText, { key: 0 }, {
												default: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: edited.value.price_gross,
																"onUpdate:modelValue": ($event) => edited.value.price_gross = $event,
																label: "Цена с НДС / кг",
																type: "number",
																step: "0.01",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: edited.value.price_net,
																"onUpdate:modelValue": ($event) => edited.value.price_net = $event,
																label: "Цена без НДС / кг",
																type: "number",
																step: "0.01",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: edited.value.vat_rate,
																"onUpdate:modelValue": ($event) => edited.value.vat_rate = $event,
																label: "НДС %",
																type: "number",
																step: "0.01",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VSelect, {
																modelValue: edited.value.currency_id,
																"onUpdate:modelValue": ($event) => edited.value.currency_id = $event,
																items: __props.currencies,
																"item-value": "id",
																"item-title": "code",
																label: "Валюта",
																variant: "outlined",
																density: "compact",
																clearable: ""
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items"
															])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VSwitch, {
																modelValue: edited.value.is_manual,
																"onUpdate:modelValue": ($event) => edited.value.is_manual = $event,
																label: "Ручная цена",
																color: "orange",
																inset: "",
																"hide-details": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VSwitch, {
																modelValue: edited.value.is_published,
																"onUpdate:modelValue": ($event) => edited.value.is_published = $event,
																label: "Публиковать",
																color: "green",
																inset: "",
																"hide-details": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(VTextarea, {
																modelValue: edited.value.manual_comment,
																"onUpdate:modelValue": ($event) => edited.value.manual_comment = $event,
																label: "Комментарий",
																rows: "3",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})
													]),
													_: 1
												})]),
												_: 1
											})) : createCommentVNode("", true),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogEdit.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Закрыть ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "deep-purple-darken-1",
													variant: "tonal",
													loading: unref(saving),
													onClick: saveEdit
												}, {
													default: withCtx(() => [createTextVNode(" Сохранить ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, null, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Редактировать цену товара")]),
											_: 1
										}),
										edited.value ? (openBlock(), createBlock(VCardText, { key: 0 }, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: edited.value.price_gross,
															"onUpdate:modelValue": ($event) => edited.value.price_gross = $event,
															label: "Цена с НДС / кг",
															type: "number",
															step: "0.01",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: edited.value.price_net,
															"onUpdate:modelValue": ($event) => edited.value.price_net = $event,
															label: "Цена без НДС / кг",
															type: "number",
															step: "0.01",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: edited.value.vat_rate,
															"onUpdate:modelValue": ($event) => edited.value.vat_rate = $event,
															label: "НДС %",
															type: "number",
															step: "0.01",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VSelect, {
															modelValue: edited.value.currency_id,
															"onUpdate:modelValue": ($event) => edited.value.currency_id = $event,
															items: __props.currencies,
															"item-value": "id",
															"item-title": "code",
															label: "Валюта",
															variant: "outlined",
															density: "compact",
															clearable: ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VSwitch, {
															modelValue: edited.value.is_manual,
															"onUpdate:modelValue": ($event) => edited.value.is_manual = $event,
															label: "Ручная цена",
															color: "orange",
															inset: "",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VSwitch, {
															modelValue: edited.value.is_published,
															"onUpdate:modelValue": ($event) => edited.value.is_published = $event,
															label: "Публиковать",
															color: "green",
															inset: "",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VTextarea, {
															modelValue: edited.value.manual_comment,
															"onUpdate:modelValue": ($event) => edited.value.manual_comment = $event,
															label: "Комментарий",
															rows: "3",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})
												]),
												_: 1
											})]),
											_: 1
										})) : createCommentVNode("", true),
										createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogEdit.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Закрыть ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "deep-purple-darken-1",
												variant: "tonal",
												loading: unref(saving),
												onClick: saveEdit
											}, {
												default: withCtx(() => [createTextVNode(" Сохранить ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx(() => [createVNode("span", null, "Актуальные цены товара по видам цен"), createVNode(VBtn, {
								icon: "mdi-refresh",
								variant: "text",
								loading: unref(loading),
								onClick: unref(fetchValues)
							}, null, 8, ["loading", "onClick"])]),
							_: 1
						}),
						createVNode(VCardText, null, {
							default: withCtx(() => [createVNode(VAlert, {
								type: "info",
								variant: "tonal",
								class: "mb-4"
							}, {
								default: withCtx(() => [createTextVNode(" Здесь хранятся цены конкретного товара для разных типов покупателей. Эти цены потом можно будет выводить в интернет-магазине. ")]),
								_: 1
							}), createVNode(VDataTable, {
								items: unref(values),
								headers,
								loading: unref(loading),
								"items-per-page": "50",
								"fixed-header": "",
								height: "560px",
								density: "compact",
								class: "border rounded",
								hover: ""
							}, {
								"item.price_type.name": withCtx(({ item }) => [createVNode("div", { class: "font-weight-medium" }, toDisplayString(item.price_type?.name || "—"), 1), createVNode("div", { class: "text-caption text-medium-emphasis" }, toDisplayString(item.price_type?.code || "—"), 1)]),
								"item.price_gross": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.price_gross)), 1)]),
								"item.price_net": withCtx(({ item }) => [createTextVNode(toDisplayString(formatMoney(item.price_net)), 1)]),
								"item.vat_rate": withCtx(({ item }) => [createTextVNode(toDisplayString(Number(item.vat_rate || 0).toFixed(2)) + "% ", 1)]),
								"item.currency.code": withCtx(({ item }) => [createTextVNode(toDisplayString(item.currency?.code || item.price_type?.currency?.code || "RUB"), 1)]),
								"item.is_manual": withCtx(({ item }) => [createVNode(VChip, {
									size: "small",
									variant: "tonal",
									color: item.is_manual ? "orange" : "grey"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(item.is_manual ? "manual" : "auto"), 1)]),
									_: 2
								}, 1032, ["color"])]),
								"item.is_published": withCtx(({ item }) => [createVNode(VChip, {
									size: "small",
									variant: "tonal",
									color: item.is_published ? "green" : "grey"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(item.is_published ? "public" : "hidden"), 1)]),
									_: 2
								}, 1032, ["color"])]),
								"item.actions": withCtx(({ item }) => [createVNode(VBtn, {
									icon: "mdi-pencil",
									size: "small",
									variant: "text",
									onClick: ($event) => openEdit(item)
								}, null, 8, ["onClick"]), createVNode(VBtn, {
									icon: "mdi-delete",
									size: "small",
									variant: "text",
									color: "red",
									loading: !!unref(deleting)[item.id],
									onClick: ($event) => unref(deleteValue)(item.id)
								}, null, 8, ["loading", "onClick"])]),
								"no-data": withCtx(() => [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Цены по видам цен пока не назначены. Перейдите во вкладку “Сохранённые расчёты” и примените расчёт к виду цены. ")]),
								_: 1
							}, 8, ["items", "loading"])]),
							_: 1
						}),
						createVNode(VDialog, {
							modelValue: dialogEdit.value,
							"onUpdate:modelValue": ($event) => dialogEdit.value = $event,
							width: "720"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Редактировать цену товара")]),
										_: 1
									}),
									edited.value ? (openBlock(), createBlock(VCardText, { key: 0 }, {
										default: withCtx(() => [createVNode(VRow, null, {
											default: withCtx(() => [
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: edited.value.price_gross,
														"onUpdate:modelValue": ($event) => edited.value.price_gross = $event,
														label: "Цена с НДС / кг",
														type: "number",
														step: "0.01",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: edited.value.price_net,
														"onUpdate:modelValue": ($event) => edited.value.price_net = $event,
														label: "Цена без НДС / кг",
														type: "number",
														step: "0.01",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: edited.value.vat_rate,
														"onUpdate:modelValue": ($event) => edited.value.vat_rate = $event,
														label: "НДС %",
														type: "number",
														step: "0.01",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: edited.value.currency_id,
														"onUpdate:modelValue": ($event) => edited.value.currency_id = $event,
														items: __props.currencies,
														"item-value": "id",
														"item-title": "code",
														label: "Валюта",
														variant: "outlined",
														density: "compact",
														clearable: ""
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VSwitch, {
														modelValue: edited.value.is_manual,
														"onUpdate:modelValue": ($event) => edited.value.is_manual = $event,
														label: "Ручная цена",
														color: "orange",
														inset: "",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VSwitch, {
														modelValue: edited.value.is_published,
														"onUpdate:modelValue": ($event) => edited.value.is_published = $event,
														label: "Публиковать",
														color: "green",
														inset: "",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: edited.value.manual_comment,
														"onUpdate:modelValue": ($event) => edited.value.manual_comment = $event,
														label: "Комментарий",
														rows: "3",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})
											]),
											_: 1
										})]),
										_: 1
									})) : createCommentVNode("", true),
									createVNode(VCardActions, null, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogEdit.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Закрыть ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "deep-purple-darken-1",
											variant: "tonal",
											loading: unref(saving),
											onClick: saveEdit
										}, {
											default: withCtx(() => [createTextVNode(" Сохранить ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"])
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Goods/GoodPriceTypeValuesTab.vue");
	return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Composables/useGoodMedia.js
function useGoodMedia(goodId) {
	const media = ref([]);
	const folders = ref([]);
	const loading = ref(false);
	const loadingFolders = ref(false);
	const uploading = ref(false);
	const saving = ref(false);
	const deleting = ref({});
	const publishing = ref({});
	const settingAva = ref({});
	const processing = ref({});
	const settingMainVideo = ref({});
	async function fetchMedia() {
		loading.value = true;
		try {
			const { data } = await axios.get(route("api.goods.media.index", { good: goodId }));
			media.value = Array.isArray(data) ? data : [];
			return media.value;
		} finally {
			loading.value = false;
		}
	}
	async function fetchFolders() {
		loadingFolders.value = true;
		try {
			const { data } = await axios.get(route("api.goods.media-folders.index", { good: goodId }));
			folders.value = Array.isArray(data) ? data : [];
			return folders.value;
		} finally {
			loadingFolders.value = false;
		}
	}
	async function createFolder(payload) {
		saving.value = true;
		try {
			const { data } = await axios.post(route("api.goods.media-folders.store", { good: goodId }), payload);
			folders.value.push(data);
			folders.value.sort((a, b) => {
				if (Number(a.is_archive) !== Number(b.is_archive)) return Number(a.is_archive) - Number(b.is_archive);
				const orderA = Number(a.sort_order || 100);
				const orderB = Number(b.sort_order || 100);
				if (orderA !== orderB) return orderA - orderB;
				return String(a.name || "").localeCompare(String(b.name || ""));
			});
			return data;
		} finally {
			saving.value = false;
		}
	}
	async function updateFolder(folderId, payload) {
		saving.value = true;
		try {
			const { data } = await axios.patch(route("api.goods.media-folders.update", {
				good: goodId,
				folder: folderId
			}), payload);
			folders.value = folders.value.map((item) => item.id === data.id ? data : item);
			return data;
		} finally {
			saving.value = false;
		}
	}
	async function deleteFolder(folderId) {
		deleting.value[`folder-${folderId}`] = true;
		try {
			await axios.delete(route("api.goods.media-folders.destroy", {
				good: goodId,
				folder: folderId
			}));
			folders.value = folders.value.filter((item) => item.id !== folderId);
		} finally {
			deleting.value[`folder-${folderId}`] = false;
		}
	}
	async function uploadMedia(payload) {
		uploading.value = true;
		try {
			const fd = new FormData();
			fd.append("file", payload.file);
			if (payload.folder_id) fd.append("folder_id", String(payload.folder_id));
			fd.append("title", payload.title || "");
			fd.append("alt", payload.alt || "");
			fd.append("caption", payload.caption || "");
			fd.append("is_published", payload.is_published ? "1" : "0");
			const { data } = await axios.post(route("api.goods.media.store", { good: goodId }), fd, { headers: { "Content-Type": "multipart/form-data" } });
			media.value.unshift(data);
			return data;
		} finally {
			uploading.value = false;
		}
	}
	async function updateMedia(mediaId, payload) {
		saving.value = true;
		try {
			const { data } = await axios.patch(route("api.goods.media.update", {
				good: goodId,
				media: mediaId
			}), payload);
			media.value = media.value.map((item) => item.id === data.id ? data : item);
			return data;
		} finally {
			saving.value = false;
		}
	}
	async function renameMedia(mediaId, fileName) {
		saving.value = true;
		try {
			const { data } = await axios.patch(route("api.goods.media.rename", {
				good: goodId,
				media: mediaId
			}), { file_name: fileName });
			media.value = media.value.map((item) => item.id === data.id ? data : item);
			return data;
		} finally {
			saving.value = false;
		}
	}
	async function toggleMediaPublish(item) {
		publishing.value[item.id] = true;
		const previous = !!item.is_published;
		item.is_published = !previous;
		try {
			const { data } = await axios.patch(route("api.goods.media.publish", {
				good: goodId,
				media: item.id
			}), { is_published: item.is_published });
			media.value = media.value.map((row) => row.id === data.id ? data : row);
			return data;
		} catch (error) {
			item.is_published = previous;
			throw error;
		} finally {
			publishing.value[item.id] = false;
		}
	}
	async function setMediaAva(item) {
		settingAva.value[item.id] = true;
		try {
			const { data } = await axios.patch(route("api.goods.media.ava", {
				good: goodId,
				media: item.id
			}));
			media.value = media.value.map((row) => ({
				...row,
				is_ava: row.id === data.id,
				is_published: row.id === data.id ? data.is_published : row.is_published
			}));
			return data;
		} finally {
			settingAva.value[item.id] = false;
		}
	}
	async function setMainVideoMedia(item) {
		settingMainVideo.value[item.id] = true;
		try {
			const { data } = await axios.patch(route("api.goods.media.main-video", {
				good: goodId,
				media: item.id
			}));
			media.value = media.value.map((row) => ({
				...row,
				is_main_video: row.id === data.id,
				is_published: row.id === data.id ? data.is_published : row.is_published
			}));
			return data;
		} finally {
			settingMainVideo.value[item.id] = false;
		}
	}
	async function processVideoMedia(item) {
		processing.value[item.id] = true;
		const previousStatus = item.processing_status;
		item.processing_status = "queued";
		try {
			const { data } = await axios.patch(route("api.goods.media.process", {
				good: goodId,
				media: item.id
			}));
			media.value = media.value.map((row) => row.id === data.id ? data : row);
			return data;
		} catch (error) {
			item.processing_status = previousStatus;
			throw error;
		} finally {
			processing.value[item.id] = false;
		}
	}
	async function deleteMedia(mediaId) {
		deleting.value[`media-${mediaId}`] = true;
		try {
			await axios.delete(route("api.goods.media.destroy", {
				good: goodId,
				media: mediaId
			}));
			media.value = media.value.filter((item) => item.id !== mediaId);
		} finally {
			deleting.value[`media-${mediaId}`] = false;
		}
	}
	return {
		media,
		folders,
		loading,
		loadingFolders,
		uploading,
		saving,
		deleting,
		publishing,
		settingAva,
		processing,
		settingMainVideo,
		fetchMedia,
		fetchFolders,
		createFolder,
		updateFolder,
		deleteFolder,
		uploadMedia,
		updateMedia,
		renameMedia,
		toggleMediaPublish,
		setMediaAva,
		processVideoMedia,
		deleteMedia,
		setMainVideoMedia
	};
}
//#endregion
//#region resources/js/Components/Goods/GoodMediaTab.vue
var _sfc_main$1 = {
	__name: "GoodMediaTab",
	__ssrInlineRender: true,
	props: { good: {
		type: Object,
		required: true
	} },
	emits: ["changed", "ava-updated"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const { media, folders, loading, loadingFolders, uploading, saving, deleting, publishing, settingAva, settingMainVideo, processing, fetchMedia, fetchFolders, createFolder, updateFolder, deleteFolder, uploadMedia, updateMedia, renameMedia, toggleMediaPublish, setMediaAva, setMainVideoMedia, processVideoMedia, deleteMedia } = useGoodMedia(props.good.id);
		const filterType = ref("all");
		const filterFolderId = ref("all");
		const filterPublished = ref("all");
		const search = ref("");
		const errorMessage = ref("");
		const dialogUpload = ref(false);
		const dialogFolder = ref(false);
		const dialogEdit = ref(false);
		const dialogRename = ref(false);
		const dialogDeleteMedia = ref(false);
		const dialogDeleteFolder = ref(false);
		const selectedMedia = ref(null);
		const selectedFolder = ref(null);
		const sortingMedia = ref({});
		const uploadForm = reactive({
			file: null,
			folder_id: null,
			title: "",
			alt: "",
			caption: "",
			is_published: false
		});
		const folderForm = reactive({
			id: null,
			parent_id: null,
			name: "",
			sort_order: 100,
			is_archive: false
		});
		const editForm = reactive({
			id: null,
			folder_id: null,
			title: "",
			alt: "",
			caption: "",
			sort_order: 100,
			is_published: false
		});
		const renameForm = reactive({
			id: null,
			file_name: ""
		});
		const folderItems = computed(() => {
			return [{
				title: "Без папки",
				value: null
			}, ...folders.value.map((folder) => ({
				title: folder.is_archive ? `🗄️ ${folder.name}` : `📁 ${folder.name}`,
				value: folder.id
			}))];
		});
		const filterFolderItems = computed(() => {
			return [
				{
					title: "Все папки",
					value: "all"
				},
				{
					title: "Без папки",
					value: "none"
				},
				...folders.value.map((folder) => ({
					title: folder.is_archive ? `🗄️ ${folder.name}` : `📁 ${folder.name}`,
					value: folder.id
				}))
			];
		});
		const filteredMedia = computed(() => {
			let items = [...media.value];
			if (filterType.value !== "all") items = items.filter((item) => item.type === filterType.value);
			if (filterFolderId.value === "none") items = items.filter((item) => !item.folder_id);
			else if (filterFolderId.value !== "all") items = items.filter((item) => {
				return Number(item.folder_id) === Number(filterFolderId.value);
			});
			if (filterPublished.value === "published") items = items.filter((item) => !!item.is_published);
			if (filterPublished.value === "hidden") items = items.filter((item) => !item.is_published);
			const q = search.value.trim().toLowerCase();
			if (q) items = items.filter((item) => {
				return [
					item.title,
					item.alt,
					item.caption,
					item.file_name,
					item.original_name,
					item.path,
					item.folder?.name
				].filter(Boolean).some((value) => String(value).toLowerCase().includes(q));
			});
			return items.sort((a, b) => {
				const typeA = String(a.type || "");
				const typeB = String(b.type || "");
				if (typeA !== typeB) return typeA.localeCompare(typeB);
				const orderA = Number(a.sort_order ?? 100);
				const orderB = Number(b.sort_order ?? 100);
				if (orderA !== orderB) return orderA - orderB;
				return Number(a.id) - Number(b.id);
			});
		});
		const hasActiveVideoProcessing = computed(() => {
			return media.value.some((item) => {
				return item.type === "video" && [
					"pending",
					"queued",
					"processing"
				].includes(item.processing_status);
			});
		});
		function extractFile(value) {
			if (value instanceof File) return value;
			if (Array.isArray(value) && value[0] instanceof File) return value[0];
			return null;
		}
		function formatSize(bytes) {
			const value = Number(bytes || 0);
			if (!value) return "—";
			if (value < 1024) return `${value} B`;
			if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
			return `${(value / 1024 / 1024).toFixed(1)} MB`;
		}
		function formatDate(value) {
			if (!value) return "—";
			try {
				return new Intl.DateTimeFormat("ru-RU", {
					day: "2-digit",
					month: "short",
					year: "numeric",
					hour: "2-digit",
					minute: "2-digit"
				}).format(new Date(value));
			} catch {
				return value;
			}
		}
		function mediaPreview(item) {
			if (item.type === "image") return item.thumb_url || item.url || null;
			if (item.type === "video") return item.poster_url || item.thumb_url || null;
			return null;
		}
		function mediaTypeIcon(item) {
			if (item.type === "image") return "mdi-image";
			if (item.type === "video") return "mdi-video";
			if (item.type === "document") return "mdi-file-document";
			return "mdi-file";
		}
		function mediaOpenUrl(item) {
			return item.video_mp4_url || item.video_hls_url || item.url;
		}
		function handleError(error) {
			console.error(error);
			const errors = error?.response?.data?.errors || null;
			if (errors) {
				errorMessage.value = Object.values(errors).flat().join("\n");
				return;
			}
			errorMessage.value = error?.response?.data?.message || error?.message || "Произошла ошибка";
		}
		async function reload() {
			errorMessage.value = "";
			try {
				await Promise.all([fetchFolders(), fetchMedia()]);
			} catch (error) {
				handleError(error);
			}
		}
		function formatDuration(seconds) {
			const value = Number(seconds || 0);
			if (!value) return "—";
			const minutes = Math.floor(value / 60);
			const restSeconds = Math.round(value % 60);
			if (minutes <= 0) return `${restSeconds} сек.`;
			return `${minutes} мин. ${String(restSeconds).padStart(2, "0")} сек.`;
		}
		function mediaResolution(item) {
			if (!item.width || !item.height) return "—";
			return `${item.width}×${item.height}`;
		}
		function mediaPosition(item) {
			return filteredMedia.value.findIndex((row) => row.id === item.id);
		}
		function canMoveMediaUp(item) {
			return mediaPosition(item) > 0;
		}
		function canMoveMediaDown(item) {
			const index = mediaPosition(item);
			return index >= 0 && index < filteredMedia.value.length - 1;
		}
		async function moveMedia(item, direction) {
			const items = filteredMedia.value;
			const currentIndex = items.findIndex((row) => row.id === item.id);
			if (currentIndex < 0) return;
			const targetIndex = currentIndex + direction;
			const targetItem = items[targetIndex];
			if (!targetItem) return;
			sortingMedia.value[item.id] = true;
			sortingMedia.value[targetItem.id] = true;
			errorMessage.value = "";
			const currentOrder = Number(item.sort_order ?? (currentIndex + 1) * 10);
			const targetOrder = Number(targetItem.sort_order ?? (targetIndex + 1) * 10);
			try {
				await updateMedia(item.id, { sort_order: targetOrder });
				await updateMedia(targetItem.id, { sort_order: currentOrder });
				await fetchMedia();
				emit("changed");
			} catch (error) {
				handleError(error);
			} finally {
				sortingMedia.value[item.id] = false;
				sortingMedia.value[targetItem.id] = false;
			}
		}
		function resetUploadForm() {
			uploadForm.file = null;
			uploadForm.folder_id = null;
			uploadForm.title = "";
			uploadForm.alt = props.good.name || "";
			uploadForm.caption = "";
			uploadForm.is_published = false;
		}
		function openUpload() {
			resetUploadForm();
			dialogUpload.value = true;
		}
		async function submitUpload() {
			const file = extractFile(uploadForm.file);
			if (!file) return;
			errorMessage.value = "";
			try {
				const saved = await uploadMedia({
					file,
					folder_id: uploadForm.folder_id,
					title: uploadForm.title,
					alt: uploadForm.alt,
					caption: uploadForm.caption,
					is_published: uploadForm.is_published
				});
				dialogUpload.value = false;
				emit("changed", saved);
			} catch (error) {
				handleError(error);
			}
		}
		function resetFolderForm() {
			folderForm.id = null;
			folderForm.parent_id = null;
			folderForm.name = "";
			folderForm.sort_order = 100;
			folderForm.is_archive = false;
		}
		function openCreateFolder(isArchive = false) {
			resetFolderForm();
			folderForm.is_archive = isArchive;
			folderForm.name = isArchive ? "Архив" : "";
			dialogFolder.value = true;
		}
		function openEditFolder(folder) {
			folderForm.id = folder.id;
			folderForm.parent_id = folder.parent_id || null;
			folderForm.name = folder.name || "";
			folderForm.sort_order = folder.sort_order ?? 100;
			folderForm.is_archive = !!folder.is_archive;
			dialogFolder.value = true;
		}
		async function submitFolder() {
			errorMessage.value = "";
			const payload = {
				parent_id: folderForm.parent_id,
				name: folderForm.name,
				sort_order: folderForm.sort_order || 100,
				is_archive: folderForm.is_archive
			};
			try {
				if (folderForm.id) await updateFolder(folderForm.id, payload);
				else await createFolder(payload);
				dialogFolder.value = false;
			} catch (error) {
				handleError(error);
			}
		}
		function askDeleteFolder(folder) {
			selectedFolder.value = folder;
			dialogDeleteFolder.value = true;
		}
		async function confirmDeleteFolder() {
			if (!selectedFolder.value?.id) return;
			errorMessage.value = "";
			try {
				await deleteFolder(selectedFolder.value.id);
				dialogDeleteFolder.value = false;
				selectedFolder.value = null;
			} catch (error) {
				handleError(error);
			}
		}
		function openEditMedia(item) {
			selectedMedia.value = item;
			editForm.id = item.id;
			editForm.folder_id = item.folder_id || null;
			editForm.title = item.title || "";
			editForm.alt = item.alt || "";
			editForm.caption = item.caption || "";
			editForm.sort_order = item.sort_order ?? 100;
			editForm.is_published = !!item.is_published;
			dialogEdit.value = true;
		}
		async function submitEditMedia() {
			if (!editForm.id) return;
			errorMessage.value = "";
			try {
				const saved = await updateMedia(editForm.id, {
					folder_id: editForm.folder_id,
					title: editForm.title,
					alt: editForm.alt,
					caption: editForm.caption,
					sort_order: editForm.sort_order || 100,
					is_published: editForm.is_published
				});
				dialogEdit.value = false;
				emit("changed", saved);
			} catch (error) {
				handleError(error);
			}
		}
		function openRenameMedia(item) {
			selectedMedia.value = item;
			renameForm.id = item.id;
			renameForm.file_name = item.file_name || item.original_name || "";
			dialogRename.value = true;
		}
		async function submitRenameMedia() {
			if (!renameForm.id || !renameForm.file_name) return;
			errorMessage.value = "";
			try {
				const saved = await renameMedia(renameForm.id, renameForm.file_name);
				dialogRename.value = false;
				emit("changed", saved);
			} catch (error) {
				handleError(error);
			}
		}
		async function handleTogglePublish(item) {
			errorMessage.value = "";
			try {
				emit("changed", await toggleMediaPublish(item));
			} catch (error) {
				handleError(error);
			}
		}
		async function handleSetAva(item) {
			errorMessage.value = "";
			try {
				const saved = await setMediaAva(item);
				emit("ava-updated", saved);
				emit("changed", saved);
			} catch (error) {
				handleError(error);
			}
		}
		async function handleSetMainVideo(item) {
			errorMessage.value = "";
			try {
				emit("changed", await setMainVideoMedia(item));
			} catch (error) {
				handleError(error);
			}
		}
		async function handleProcessVideo(item) {
			errorMessage.value = "";
			try {
				const saved = await processVideoMedia(item);
				startAutoRefresh();
				emit("changed", saved);
			} catch (error) {
				handleError(error);
			}
		}
		function askDeleteMedia(item) {
			selectedMedia.value = item;
			dialogDeleteMedia.value = true;
		}
		async function confirmDeleteMedia() {
			if (!selectedMedia.value?.id) return;
			errorMessage.value = "";
			try {
				await deleteMedia(selectedMedia.value.id);
				dialogDeleteMedia.value = false;
				selectedMedia.value = null;
				emit("changed");
			} catch (error) {
				handleError(error);
			}
		}
		let refreshTimer = null;
		function startAutoRefresh() {
			if (refreshTimer) return;
			refreshTimer = window.setInterval(async () => {
				if (!hasActiveVideoProcessing.value) {
					stopAutoRefresh();
					return;
				}
				await fetchMedia();
			}, 5e3);
		}
		function stopAutoRefresh() {
			if (!refreshTimer) return;
			window.clearInterval(refreshTimer);
			refreshTimer = null;
		}
		onMounted(async () => {
			await reload();
			if (hasActiveVideoProcessing.value) startAutoRefresh();
		});
		onBeforeUnmount(() => {
			stopAutoRefresh();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<span data-v-c44fce58${_scopeId}>Media товара</span><div class="d-flex align-center ga-2" data-v-c44fce58${_scopeId}>`);
									_push(ssrRenderComponent(VBtn, {
										icon: "mdi-refresh",
										variant: "text",
										loading: unref(loading) || unref(loadingFolders),
										onClick: reload
									}, null, _parent, _scopeId));
									_push(ssrRenderComponent(VBtn, {
										color: "blue-grey",
										variant: "tonal",
										"prepend-icon": "mdi-folder-plus",
										onClick: ($event) => openCreateFolder(false)
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Папка `);
											else return [createTextVNode(" Папка ")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VBtn, {
										color: "brown",
										variant: "tonal",
										"prepend-icon": "mdi-archive-plus",
										onClick: ($event) => openCreateFolder(true)
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Архив `);
											else return [createTextVNode(" Архив ")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VBtn, {
										color: "deep-purple-darken-1",
										variant: "tonal",
										"prepend-icon": "mdi-cloud-upload",
										onClick: openUpload
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Загрузить `);
											else return [createTextVNode(" Загрузить ")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(`</div>`);
								} else return [createVNode("span", null, "Media товара"), createVNode("div", { class: "d-flex align-center ga-2" }, [
									createVNode(VBtn, {
										icon: "mdi-refresh",
										variant: "text",
										loading: unref(loading) || unref(loadingFolders),
										onClick: reload
									}, null, 8, ["loading"]),
									createVNode(VBtn, {
										color: "blue-grey",
										variant: "tonal",
										"prepend-icon": "mdi-folder-plus",
										onClick: ($event) => openCreateFolder(false)
									}, {
										default: withCtx(() => [createTextVNode(" Папка ")]),
										_: 1
									}, 8, ["onClick"]),
									createVNode(VBtn, {
										color: "brown",
										variant: "tonal",
										"prepend-icon": "mdi-archive-plus",
										onClick: ($event) => openCreateFolder(true)
									}, {
										default: withCtx(() => [createTextVNode(" Архив ")]),
										_: 1
									}, 8, ["onClick"]),
									createVNode(VBtn, {
										color: "deep-purple-darken-1",
										variant: "tonal",
										"prepend-icon": "mdi-cloud-upload",
										onClick: openUpload
									}, {
										default: withCtx(() => [createTextVNode(" Загрузить ")]),
										_: 1
									})
								])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									if (errorMessage.value) _push(ssrRenderComponent(VAlert, {
										type: "error",
										variant: "tonal",
										class: "mb-4",
										closable: "",
										"onClick:close": ($event) => errorMessage.value = ""
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(errorMessage.value)}`);
											else return [createTextVNode(toDisplayString(errorMessage.value), 1)];
										}),
										_: 1
									}, _parent, _scopeId));
									else _push(`<!---->`);
									_push(ssrRenderComponent(VAlert, {
										type: "info",
										variant: "tonal",
										class: "mb-4"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Фото сразу создают thumbnail. Видео пока загружается как original со статусом <strong data-v-c44fce58${_scopeId}>pending</strong>. Обработку видео через FFmpeg подключим следующим шагом. `);
											else return [
												createTextVNode(" Фото сразу создают thumbnail. Видео пока загружается как original со статусом "),
												createVNode("strong", null, "pending"),
												createTextVNode(". Обработку видео через FFmpeg подключим следующим шагом. ")
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VRow, { class: "mb-2" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: search.value,
															"onUpdate:modelValue": ($event) => search.value = $event,
															label: "Поиск media",
															variant: "solo-inverted",
															density: "compact",
															clearable: "",
															"hide-details": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: search.value,
															"onUpdate:modelValue": ($event) => search.value = $event,
															label: "Поиск media",
															variant: "solo-inverted",
															density: "compact",
															clearable: "",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "2"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSelect, {
															modelValue: filterType.value,
															"onUpdate:modelValue": ($event) => filterType.value = $event,
															items: [
																{
																	title: "Все типы",
																	value: "all"
																},
																{
																	title: "Фото",
																	value: "image"
																},
																{
																	title: "Видео",
																	value: "video"
																},
																{
																	title: "Документы",
																	value: "document"
																}
															],
															label: "Тип",
															variant: "solo-inverted",
															density: "compact",
															"hide-details": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VSelect, {
															modelValue: filterType.value,
															"onUpdate:modelValue": ($event) => filterType.value = $event,
															items: [
																{
																	title: "Все типы",
																	value: "all"
																},
																{
																	title: "Фото",
																	value: "image"
																},
																{
																	title: "Видео",
																	value: "video"
																},
																{
																	title: "Документы",
																	value: "document"
																}
															],
															label: "Тип",
															variant: "solo-inverted",
															density: "compact",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSelect, {
															modelValue: filterFolderId.value,
															"onUpdate:modelValue": ($event) => filterFolderId.value = $event,
															items: filterFolderItems.value,
															label: "Папка",
															variant: "solo-inverted",
															density: "compact",
															"hide-details": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VSelect, {
															modelValue: filterFolderId.value,
															"onUpdate:modelValue": ($event) => filterFolderId.value = $event,
															items: filterFolderItems.value,
															label: "Папка",
															variant: "solo-inverted",
															density: "compact",
															"hide-details": ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSelect, {
															modelValue: filterPublished.value,
															"onUpdate:modelValue": ($event) => filterPublished.value = $event,
															items: [
																{
																	title: "Все",
																	value: "all"
																},
																{
																	title: "Опубликованные",
																	value: "published"
																},
																{
																	title: "Скрытые",
																	value: "hidden"
																}
															],
															label: "Публикация",
															variant: "solo-inverted",
															density: "compact",
															"hide-details": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VSelect, {
															modelValue: filterPublished.value,
															"onUpdate:modelValue": ($event) => filterPublished.value = $event,
															items: [
																{
																	title: "Все",
																	value: "all"
																},
																{
																	title: "Опубликованные",
																	value: "published"
																},
																{
																	title: "Скрытые",
																	value: "hidden"
																}
															],
															label: "Публикация",
															variant: "solo-inverted",
															density: "compact",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: search.value,
														"onUpdate:modelValue": ($event) => search.value = $event,
														label: "Поиск media",
														variant: "solo-inverted",
														density: "compact",
														clearable: "",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "2"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: filterType.value,
														"onUpdate:modelValue": ($event) => filterType.value = $event,
														items: [
															{
																title: "Все типы",
																value: "all"
															},
															{
																title: "Фото",
																value: "image"
															},
															{
																title: "Видео",
																value: "video"
															},
															{
																title: "Документы",
																value: "document"
															}
														],
														label: "Тип",
														variant: "solo-inverted",
														density: "compact",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: filterFolderId.value,
														"onUpdate:modelValue": ($event) => filterFolderId.value = $event,
														items: filterFolderItems.value,
														label: "Папка",
														variant: "solo-inverted",
														density: "compact",
														"hide-details": ""
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: filterPublished.value,
														"onUpdate:modelValue": ($event) => filterPublished.value = $event,
														items: [
															{
																title: "Все",
																value: "all"
															},
															{
																title: "Опубликованные",
																value: "published"
															},
															{
																title: "Скрытые",
																value: "hidden"
															}
														],
														label: "Публикация",
														variant: "solo-inverted",
														density: "compact",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VRow, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													lg: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VCard, { variant: "tonal" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	_push(ssrRenderComponent(VCardTitle, { class: "text-subtitle-1" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(` Папки `);
																			else return [createTextVNode(" Папки ")];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VCardText, null, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VList, {
																				density: "compact",
																				lines: "two"
																			}, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) {
																						_push(`<!--[-->`);
																						ssrRenderList(unref(folders), (folder) => {
																							_push(ssrRenderComponent(VListItem, { key: folder.id }, {
																								prepend: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(ssrRenderComponent(VIcon, { icon: folder.is_archive ? "mdi-archive" : "mdi-folder" }, null, _parent, _scopeId));
																									else return [createVNode(VIcon, { icon: folder.is_archive ? "mdi-archive" : "mdi-folder" }, null, 8, ["icon"])];
																								}),
																								append: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) {
																										_push(ssrRenderComponent(VBtn, {
																											icon: "mdi-pencil",
																											size: "x-small",
																											variant: "text",
																											onClick: ($event) => openEditFolder(folder)
																										}, null, _parent, _scopeId));
																										_push(ssrRenderComponent(VBtn, {
																											icon: "mdi-delete",
																											size: "x-small",
																											variant: "text",
																											color: "red",
																											loading: !!unref(deleting)[`folder-${folder.id}`],
																											onClick: ($event) => askDeleteFolder(folder)
																										}, null, _parent, _scopeId));
																									} else return [createVNode(VBtn, {
																										icon: "mdi-pencil",
																										size: "x-small",
																										variant: "text",
																										onClick: ($event) => openEditFolder(folder)
																									}, null, 8, ["onClick"]), createVNode(VBtn, {
																										icon: "mdi-delete",
																										size: "x-small",
																										variant: "text",
																										color: "red",
																										loading: !!unref(deleting)[`folder-${folder.id}`],
																										onClick: ($event) => askDeleteFolder(folder)
																									}, null, 8, ["loading", "onClick"])];
																								}),
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) {
																										_push(ssrRenderComponent(VListItemTitle, null, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(`${ssrInterpolate(folder.name)}`);
																												else return [createTextVNode(toDisplayString(folder.name), 1)];
																											}),
																											_: 2
																										}, _parent, _scopeId));
																										_push(ssrRenderComponent(VListItemSubtitle, null, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(`${ssrInterpolate(folder.path)}`);
																												else return [createTextVNode(toDisplayString(folder.path), 1)];
																											}),
																											_: 2
																										}, _parent, _scopeId));
																									} else return [createVNode(VListItemTitle, null, {
																										default: withCtx(() => [createTextVNode(toDisplayString(folder.name), 1)]),
																										_: 2
																									}, 1024), createVNode(VListItemSubtitle, null, {
																										default: withCtx(() => [createTextVNode(toDisplayString(folder.path), 1)]),
																										_: 2
																									}, 1024)];
																								}),
																								_: 2
																							}, _parent, _scopeId));
																						});
																						_push(`<!--]-->`);
																						if (!unref(folders).length) _push(ssrRenderComponent(VListItem, null, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VListItemTitle, { class: "text-medium-emphasis" }, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(` Папок пока нет. `);
																										else return [createTextVNode(" Папок пока нет. ")];
																									}),
																									_: 1
																								}, _parent, _scopeId));
																								else return [createVNode(VListItemTitle, { class: "text-medium-emphasis" }, {
																									default: withCtx(() => [createTextVNode(" Папок пока нет. ")]),
																									_: 1
																								})];
																							}),
																							_: 1
																						}, _parent, _scopeId));
																						else _push(`<!---->`);
																					} else return [(openBlock(true), createBlock(Fragment, null, renderList(unref(folders), (folder) => {
																						return openBlock(), createBlock(VListItem, { key: folder.id }, {
																							prepend: withCtx(() => [createVNode(VIcon, { icon: folder.is_archive ? "mdi-archive" : "mdi-folder" }, null, 8, ["icon"])]),
																							append: withCtx(() => [createVNode(VBtn, {
																								icon: "mdi-pencil",
																								size: "x-small",
																								variant: "text",
																								onClick: ($event) => openEditFolder(folder)
																							}, null, 8, ["onClick"]), createVNode(VBtn, {
																								icon: "mdi-delete",
																								size: "x-small",
																								variant: "text",
																								color: "red",
																								loading: !!unref(deleting)[`folder-${folder.id}`],
																								onClick: ($event) => askDeleteFolder(folder)
																							}, null, 8, ["loading", "onClick"])]),
																							default: withCtx(() => [createVNode(VListItemTitle, null, {
																								default: withCtx(() => [createTextVNode(toDisplayString(folder.name), 1)]),
																								_: 2
																							}, 1024), createVNode(VListItemSubtitle, null, {
																								default: withCtx(() => [createTextVNode(toDisplayString(folder.path), 1)]),
																								_: 2
																							}, 1024)]),
																							_: 2
																						}, 1024);
																					}), 128)), !unref(folders).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
																						default: withCtx(() => [createVNode(VListItemTitle, { class: "text-medium-emphasis" }, {
																							default: withCtx(() => [createTextVNode(" Папок пока нет. ")]),
																							_: 1
																						})]),
																						_: 1
																					})) : createCommentVNode("", true)];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			else return [createVNode(VList, {
																				density: "compact",
																				lines: "two"
																			}, {
																				default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(folders), (folder) => {
																					return openBlock(), createBlock(VListItem, { key: folder.id }, {
																						prepend: withCtx(() => [createVNode(VIcon, { icon: folder.is_archive ? "mdi-archive" : "mdi-folder" }, null, 8, ["icon"])]),
																						append: withCtx(() => [createVNode(VBtn, {
																							icon: "mdi-pencil",
																							size: "x-small",
																							variant: "text",
																							onClick: ($event) => openEditFolder(folder)
																						}, null, 8, ["onClick"]), createVNode(VBtn, {
																							icon: "mdi-delete",
																							size: "x-small",
																							variant: "text",
																							color: "red",
																							loading: !!unref(deleting)[`folder-${folder.id}`],
																							onClick: ($event) => askDeleteFolder(folder)
																						}, null, 8, ["loading", "onClick"])]),
																						default: withCtx(() => [createVNode(VListItemTitle, null, {
																							default: withCtx(() => [createTextVNode(toDisplayString(folder.name), 1)]),
																							_: 2
																						}, 1024), createVNode(VListItemSubtitle, null, {
																							default: withCtx(() => [createTextVNode(toDisplayString(folder.path), 1)]),
																							_: 2
																						}, 1024)]),
																						_: 2
																					}, 1024);
																				}), 128)), !unref(folders).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
																					default: withCtx(() => [createVNode(VListItemTitle, { class: "text-medium-emphasis" }, {
																						default: withCtx(() => [createTextVNode(" Папок пока нет. ")]),
																						_: 1
																					})]),
																					_: 1
																				})) : createCommentVNode("", true)]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																} else return [createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																	default: withCtx(() => [createTextVNode(" Папки ")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VList, {
																		density: "compact",
																		lines: "two"
																	}, {
																		default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(folders), (folder) => {
																			return openBlock(), createBlock(VListItem, { key: folder.id }, {
																				prepend: withCtx(() => [createVNode(VIcon, { icon: folder.is_archive ? "mdi-archive" : "mdi-folder" }, null, 8, ["icon"])]),
																				append: withCtx(() => [createVNode(VBtn, {
																					icon: "mdi-pencil",
																					size: "x-small",
																					variant: "text",
																					onClick: ($event) => openEditFolder(folder)
																				}, null, 8, ["onClick"]), createVNode(VBtn, {
																					icon: "mdi-delete",
																					size: "x-small",
																					variant: "text",
																					color: "red",
																					loading: !!unref(deleting)[`folder-${folder.id}`],
																					onClick: ($event) => askDeleteFolder(folder)
																				}, null, 8, ["loading", "onClick"])]),
																				default: withCtx(() => [createVNode(VListItemTitle, null, {
																					default: withCtx(() => [createTextVNode(toDisplayString(folder.name), 1)]),
																					_: 2
																				}, 1024), createVNode(VListItemSubtitle, null, {
																					default: withCtx(() => [createTextVNode(toDisplayString(folder.path), 1)]),
																					_: 2
																				}, 1024)]),
																				_: 2
																			}, 1024);
																		}), 128)), !unref(folders).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
																			default: withCtx(() => [createVNode(VListItemTitle, { class: "text-medium-emphasis" }, {
																				default: withCtx(() => [createTextVNode(" Папок пока нет. ")]),
																				_: 1
																			})]),
																			_: 1
																		})) : createCommentVNode("", true)]),
																		_: 1
																	})]),
																	_: 1
																})];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VCard, { variant: "tonal" }, {
															default: withCtx(() => [createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																default: withCtx(() => [createTextVNode(" Папки ")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VList, {
																	density: "compact",
																	lines: "two"
																}, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(folders), (folder) => {
																		return openBlock(), createBlock(VListItem, { key: folder.id }, {
																			prepend: withCtx(() => [createVNode(VIcon, { icon: folder.is_archive ? "mdi-archive" : "mdi-folder" }, null, 8, ["icon"])]),
																			append: withCtx(() => [createVNode(VBtn, {
																				icon: "mdi-pencil",
																				size: "x-small",
																				variant: "text",
																				onClick: ($event) => openEditFolder(folder)
																			}, null, 8, ["onClick"]), createVNode(VBtn, {
																				icon: "mdi-delete",
																				size: "x-small",
																				variant: "text",
																				color: "red",
																				loading: !!unref(deleting)[`folder-${folder.id}`],
																				onClick: ($event) => askDeleteFolder(folder)
																			}, null, 8, ["loading", "onClick"])]),
																			default: withCtx(() => [createVNode(VListItemTitle, null, {
																				default: withCtx(() => [createTextVNode(toDisplayString(folder.name), 1)]),
																				_: 2
																			}, 1024), createVNode(VListItemSubtitle, null, {
																				default: withCtx(() => [createTextVNode(toDisplayString(folder.path), 1)]),
																				_: 2
																			}, 1024)]),
																			_: 2
																		}, 1024);
																	}), 128)), !unref(folders).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
																		default: withCtx(() => [createVNode(VListItemTitle, { class: "text-medium-emphasis" }, {
																			default: withCtx(() => [createTextVNode(" Папок пока нет. ")]),
																			_: 1
																		})]),
																		_: 1
																	})) : createCommentVNode("", true)]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													lg: "9"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															if (unref(loading)) _push(ssrRenderComponent(VProgressLinear, {
																indeterminate: "",
																class: "mb-3"
															}, null, _parent, _scopeId));
															else _push(`<!---->`);
															if (filteredMedia.value.length) _push(ssrRenderComponent(VRow, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(`<!--[-->`);
																		ssrRenderList(filteredMedia.value, (item) => {
																			_push(ssrRenderComponent(VCol, {
																				key: item.id,
																				cols: "12",
																				sm: "6",
																				md: "4",
																				xl: "3"
																			}, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VCard, { class: ["h-100 media-card", { "media-card--ava": item.is_ava }] }, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) {
																								_push(`<div class="media-preview" data-v-c44fce58${_scopeId}>`);
																								if (mediaPreview(item)) _push(ssrRenderComponent(VImg, {
																									src: mediaPreview(item),
																									height: "180",
																									cover: ""
																								}, {
																									placeholder: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) {
																											_push(`<div class="d-flex align-center justify-center fill-height" data-v-c44fce58${_scopeId}>`);
																											_push(ssrRenderComponent(VProgressCircular, { indeterminate: "" }, null, _parent, _scopeId));
																											_push(`</div>`);
																										} else return [createVNode("div", { class: "d-flex align-center justify-center fill-height" }, [createVNode(VProgressCircular, { indeterminate: "" })])];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								else {
																									_push(`<div class="media-empty-preview" data-v-c44fce58${_scopeId}>`);
																									_push(ssrRenderComponent(VIcon, {
																										icon: mediaTypeIcon(item),
																										size: "56",
																										class: "mb-2"
																									}, null, _parent, _scopeId));
																									_push(`<div class="text-subtitle-2" data-v-c44fce58${_scopeId}>${ssrInterpolate(item.type === "video" ? "Видео ожидает обработки" : "Нет preview")}</div><div class="text-caption text-medium-emphasis mt-1" data-v-c44fce58${_scopeId}>${ssrInterpolate(item.processing_status || "pending")}</div></div>`);
																								}
																								_push(`<div class="media-preview__badges" data-v-c44fce58${_scopeId}>`);
																								_push(ssrRenderComponent(VChip, {
																									size: "x-small",
																									variant: "flat"
																								}, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) {
																											_push(ssrRenderComponent(VIcon, {
																												icon: mediaTypeIcon(item),
																												size: "14",
																												class: "mr-1"
																											}, null, _parent, _scopeId));
																											_push(` ${ssrInterpolate(item.type)}`);
																										} else return [createVNode(VIcon, {
																											icon: mediaTypeIcon(item),
																											size: "14",
																											class: "mr-1"
																										}, null, 8, ["icon"]), createTextVNode(" " + toDisplayString(item.type), 1)];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								if (item.is_ava) _push(ssrRenderComponent(VChip, {
																									size: "x-small",
																									color: "amber",
																									variant: "flat"
																								}, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(` ava `);
																										else return [createTextVNode(" ava ")];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								else _push(`<!---->`);
																								if (item.type === "video") _push(ssrRenderComponent(VChip, {
																									size: "x-small",
																									color: item.processing_status === "done" ? "green" : "orange",
																									variant: "flat"
																								}, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(`${ssrInterpolate(item.processing_status || "pending")}`);
																										else return [createTextVNode(toDisplayString(item.processing_status || "pending"), 1)];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								else _push(`<!---->`);
																								if (item.is_main_video) _push(ssrRenderComponent(VChip, {
																									size: "x-small",
																									color: "deep-purple",
																									variant: "flat"
																								}, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(` main video `);
																										else return [createTextVNode(" main video ")];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								else _push(`<!---->`);
																								_push(`</div></div>`);
																								_push(ssrRenderComponent(VCardText, null, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) {
																											_push(`<div class="font-weight-medium text-truncate" data-v-c44fce58${_scopeId}>${ssrInterpolate(item.title || item.file_name || item.original_name || `media #${item.id}`)}</div><div class="text-caption text-medium-emphasis text-truncate" data-v-c44fce58${_scopeId}>${ssrInterpolate(item.folder?.name || "Без папки")}</div><div class="text-caption text-medium-emphasis" data-v-c44fce58${_scopeId}>${ssrInterpolate(formatSize(item.size))} · ${ssrInterpolate(formatDate(item.created_at))}</div>`);
																											if (item.type === "video") _push(`<div class="text-caption text-medium-emphasis" data-v-c44fce58${_scopeId}>${ssrInterpolate(formatDuration(item.duration_seconds))} · ${ssrInterpolate(mediaResolution(item))}</div>`);
																											else _push(`<!---->`);
																											if (item.alt) _push(`<div class="text-caption text-medium-emphasis text-truncate mt-1" data-v-c44fce58${_scopeId}> alt: ${ssrInterpolate(item.alt)}</div>`);
																											else _push(`<!---->`);
																											_push(`<div class="d-flex align-center justify-space-between mt-3" data-v-c44fce58${_scopeId}>`);
																											_push(ssrRenderComponent(VSwitch, {
																												"model-value": !!item.is_published,
																												loading: !!unref(publishing)[item.id],
																												density: "compact",
																												color: "green",
																												inset: "",
																												"hide-details": "",
																												"onUpdate:modelValue": () => handleTogglePublish(item)
																											}, null, _parent, _scopeId));
																											_push(`<div class="d-flex align-center" data-v-c44fce58${_scopeId}>`);
																											_push(ssrRenderComponent(VBtn, {
																												icon: "mdi-arrow-up",
																												size: "small",
																												variant: "text",
																												disabled: !canMoveMediaUp(item),
																												loading: !!sortingMedia.value[item.id],
																												onClick: ($event) => moveMedia(item, -1)
																											}, null, _parent, _scopeId));
																											_push(ssrRenderComponent(VBtn, {
																												icon: "mdi-arrow-down",
																												size: "small",
																												variant: "text",
																												disabled: !canMoveMediaDown(item),
																												loading: !!sortingMedia.value[item.id],
																												onClick: ($event) => moveMedia(item, 1)
																											}, null, _parent, _scopeId));
																											if (item.type === "image") _push(ssrRenderComponent(VBtn, {
																												icon: "mdi-star",
																												size: "small",
																												variant: "text",
																												color: "amber-darken-2",
																												loading: !!unref(settingAva)[item.id],
																												onClick: ($event) => handleSetAva(item)
																											}, null, _parent, _scopeId));
																											else _push(`<!---->`);
																											if (item.type === "video") _push(ssrRenderComponent(VBtn, {
																												icon: "mdi-cog-play",
																												size: "small",
																												variant: "text",
																												color: "deep-purple",
																												loading: !!unref(processing)[item.id],
																												disabled: ["queued", "processing"].includes(item.processing_status),
																												onClick: ($event) => handleProcessVideo(item)
																											}, null, _parent, _scopeId));
																											else _push(`<!---->`);
																											if (item.type === "video") _push(ssrRenderComponent(VBtn, {
																												size: "small",
																												variant: "text",
																												color: "deep-purple-darken-2",
																												loading: !!unref(settingMainVideo)[item.id],
																												disabled: item.processing_status !== "done" || !item.video_mp4_url,
																												onClick: ($event) => handleSetMainVideo(item)
																											}, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) {
																														_push(`<span class="main-video-icon" data-v-c44fce58${_scopeId}>`);
																														_push(ssrRenderComponent(VIcon, {
																															icon: "mdi-video",
																															size: "22"
																														}, null, _parent, _scopeId));
																														_push(ssrRenderComponent(VIcon, {
																															icon: "mdi-star",
																															size: "11",
																															class: "main-video-icon__star"
																														}, null, _parent, _scopeId));
																														_push(`</span>`);
																													} else return [createVNode("span", { class: "main-video-icon" }, [createVNode(VIcon, {
																														icon: "mdi-video",
																														size: "22"
																													}), createVNode(VIcon, {
																														icon: "mdi-star",
																														size: "11",
																														class: "main-video-icon__star"
																													})])];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																											else _push(`<!---->`);
																											_push(ssrRenderComponent(VBtn, {
																												icon: "mdi-open-in-new",
																												size: "small",
																												variant: "text",
																												href: mediaOpenUrl(item),
																												target: "_blank"
																											}, null, _parent, _scopeId));
																											_push(ssrRenderComponent(VMenu, null, {
																												activator: withCtx(({ props: menuProps }, _push, _parent, _scopeId) => {
																													if (_push) _push(ssrRenderComponent(VBtn, mergeProps({ ref_for: true }, menuProps, {
																														icon: "mdi-dots-vertical",
																														size: "small",
																														variant: "text"
																													}), null, _parent, _scopeId));
																													else return [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
																														icon: "mdi-dots-vertical",
																														size: "small",
																														variant: "text"
																													}), null, 16)];
																												}),
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(ssrRenderComponent(VList, { density: "compact" }, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) {
																																_push(ssrRenderComponent(VListItem, {
																																	"prepend-icon": "mdi-pencil",
																																	title: "Редактировать",
																																	onClick: ($event) => openEditMedia(item)
																																}, null, _parent, _scopeId));
																																_push(ssrRenderComponent(VListItem, {
																																	"prepend-icon": "mdi-rename-box",
																																	title: "Переименовать файл",
																																	onClick: ($event) => openRenameMedia(item)
																																}, null, _parent, _scopeId));
																																_push(ssrRenderComponent(VListItem, {
																																	"prepend-icon": "mdi-delete",
																																	title: "Удалить",
																																	"base-color": "red",
																																	onClick: ($event) => askDeleteMedia(item)
																																}, null, _parent, _scopeId));
																															} else return [
																																createVNode(VListItem, {
																																	"prepend-icon": "mdi-pencil",
																																	title: "Редактировать",
																																	onClick: ($event) => openEditMedia(item)
																																}, null, 8, ["onClick"]),
																																createVNode(VListItem, {
																																	"prepend-icon": "mdi-rename-box",
																																	title: "Переименовать файл",
																																	onClick: ($event) => openRenameMedia(item)
																																}, null, 8, ["onClick"]),
																																createVNode(VListItem, {
																																	"prepend-icon": "mdi-delete",
																																	title: "Удалить",
																																	"base-color": "red",
																																	onClick: ($event) => askDeleteMedia(item)
																																}, null, 8, ["onClick"])
																															];
																														}),
																														_: 2
																													}, _parent, _scopeId));
																													else return [createVNode(VList, { density: "compact" }, {
																														default: withCtx(() => [
																															createVNode(VListItem, {
																																"prepend-icon": "mdi-pencil",
																																title: "Редактировать",
																																onClick: ($event) => openEditMedia(item)
																															}, null, 8, ["onClick"]),
																															createVNode(VListItem, {
																																"prepend-icon": "mdi-rename-box",
																																title: "Переименовать файл",
																																onClick: ($event) => openRenameMedia(item)
																															}, null, 8, ["onClick"]),
																															createVNode(VListItem, {
																																"prepend-icon": "mdi-delete",
																																title: "Удалить",
																																"base-color": "red",
																																onClick: ($event) => askDeleteMedia(item)
																															}, null, 8, ["onClick"])
																														]),
																														_: 2
																													}, 1024)];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																											_push(`</div></div>`);
																										} else return [
																											createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(item.title || item.file_name || item.original_name || `media #${item.id}`), 1),
																											createVNode("div", { class: "text-caption text-medium-emphasis text-truncate" }, toDisplayString(item.folder?.name || "Без папки"), 1),
																											createVNode("div", { class: "text-caption text-medium-emphasis" }, toDisplayString(formatSize(item.size)) + " · " + toDisplayString(formatDate(item.created_at)), 1),
																											item.type === "video" ? (openBlock(), createBlock("div", {
																												key: 0,
																												class: "text-caption text-medium-emphasis"
																											}, toDisplayString(formatDuration(item.duration_seconds)) + " · " + toDisplayString(mediaResolution(item)), 1)) : createCommentVNode("", true),
																											item.alt ? (openBlock(), createBlock("div", {
																												key: 1,
																												class: "text-caption text-medium-emphasis text-truncate mt-1"
																											}, " alt: " + toDisplayString(item.alt), 1)) : createCommentVNode("", true),
																											createVNode("div", { class: "d-flex align-center justify-space-between mt-3" }, [createVNode(VSwitch, {
																												"model-value": !!item.is_published,
																												loading: !!unref(publishing)[item.id],
																												density: "compact",
																												color: "green",
																												inset: "",
																												"hide-details": "",
																												"onUpdate:modelValue": () => handleTogglePublish(item)
																											}, null, 8, [
																												"model-value",
																												"loading",
																												"onUpdate:modelValue"
																											]), createVNode("div", { class: "d-flex align-center" }, [
																												createVNode(VBtn, {
																													icon: "mdi-arrow-up",
																													size: "small",
																													variant: "text",
																													disabled: !canMoveMediaUp(item),
																													loading: !!sortingMedia.value[item.id],
																													onClick: ($event) => moveMedia(item, -1)
																												}, null, 8, [
																													"disabled",
																													"loading",
																													"onClick"
																												]),
																												createVNode(VBtn, {
																													icon: "mdi-arrow-down",
																													size: "small",
																													variant: "text",
																													disabled: !canMoveMediaDown(item),
																													loading: !!sortingMedia.value[item.id],
																													onClick: ($event) => moveMedia(item, 1)
																												}, null, 8, [
																													"disabled",
																													"loading",
																													"onClick"
																												]),
																												item.type === "image" ? (openBlock(), createBlock(VBtn, {
																													key: 0,
																													icon: "mdi-star",
																													size: "small",
																													variant: "text",
																													color: "amber-darken-2",
																													loading: !!unref(settingAva)[item.id],
																													onClick: ($event) => handleSetAva(item)
																												}, null, 8, ["loading", "onClick"])) : createCommentVNode("", true),
																												item.type === "video" ? (openBlock(), createBlock(VBtn, {
																													key: 1,
																													icon: "mdi-cog-play",
																													size: "small",
																													variant: "text",
																													color: "deep-purple",
																													loading: !!unref(processing)[item.id],
																													disabled: ["queued", "processing"].includes(item.processing_status),
																													onClick: ($event) => handleProcessVideo(item)
																												}, null, 8, [
																													"loading",
																													"disabled",
																													"onClick"
																												])) : createCommentVNode("", true),
																												item.type === "video" ? (openBlock(), createBlock(VBtn, {
																													key: 2,
																													size: "small",
																													variant: "text",
																													color: "deep-purple-darken-2",
																													loading: !!unref(settingMainVideo)[item.id],
																													disabled: item.processing_status !== "done" || !item.video_mp4_url,
																													onClick: ($event) => handleSetMainVideo(item)
																												}, {
																													default: withCtx(() => [createVNode("span", { class: "main-video-icon" }, [createVNode(VIcon, {
																														icon: "mdi-video",
																														size: "22"
																													}), createVNode(VIcon, {
																														icon: "mdi-star",
																														size: "11",
																														class: "main-video-icon__star"
																													})])]),
																													_: 1
																												}, 8, [
																													"loading",
																													"disabled",
																													"onClick"
																												])) : createCommentVNode("", true),
																												createVNode(VBtn, {
																													icon: "mdi-open-in-new",
																													size: "small",
																													variant: "text",
																													href: mediaOpenUrl(item),
																													target: "_blank"
																												}, null, 8, ["href"]),
																												createVNode(VMenu, null, {
																													activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
																														icon: "mdi-dots-vertical",
																														size: "small",
																														variant: "text"
																													}), null, 16)]),
																													default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																														default: withCtx(() => [
																															createVNode(VListItem, {
																																"prepend-icon": "mdi-pencil",
																																title: "Редактировать",
																																onClick: ($event) => openEditMedia(item)
																															}, null, 8, ["onClick"]),
																															createVNode(VListItem, {
																																"prepend-icon": "mdi-rename-box",
																																title: "Переименовать файл",
																																onClick: ($event) => openRenameMedia(item)
																															}, null, 8, ["onClick"]),
																															createVNode(VListItem, {
																																"prepend-icon": "mdi-delete",
																																title: "Удалить",
																																"base-color": "red",
																																onClick: ($event) => askDeleteMedia(item)
																															}, null, 8, ["onClick"])
																														]),
																														_: 2
																													}, 1024)]),
																													_: 2
																												}, 1024)
																											])])
																										];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																							} else return [createVNode("div", { class: "media-preview" }, [mediaPreview(item) ? (openBlock(), createBlock(VImg, {
																								key: 0,
																								src: mediaPreview(item),
																								height: "180",
																								cover: ""
																							}, {
																								placeholder: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-center fill-height" }, [createVNode(VProgressCircular, { indeterminate: "" })])]),
																								_: 1
																							}, 8, ["src"])) : (openBlock(), createBlock("div", {
																								key: 1,
																								class: "media-empty-preview"
																							}, [
																								createVNode(VIcon, {
																									icon: mediaTypeIcon(item),
																									size: "56",
																									class: "mb-2"
																								}, null, 8, ["icon"]),
																								createVNode("div", { class: "text-subtitle-2" }, toDisplayString(item.type === "video" ? "Видео ожидает обработки" : "Нет preview"), 1),
																								createVNode("div", { class: "text-caption text-medium-emphasis mt-1" }, toDisplayString(item.processing_status || "pending"), 1)
																							])), createVNode("div", { class: "media-preview__badges" }, [
																								createVNode(VChip, {
																									size: "x-small",
																									variant: "flat"
																								}, {
																									default: withCtx(() => [createVNode(VIcon, {
																										icon: mediaTypeIcon(item),
																										size: "14",
																										class: "mr-1"
																									}, null, 8, ["icon"]), createTextVNode(" " + toDisplayString(item.type), 1)]),
																									_: 2
																								}, 1024),
																								item.is_ava ? (openBlock(), createBlock(VChip, {
																									key: 0,
																									size: "x-small",
																									color: "amber",
																									variant: "flat"
																								}, {
																									default: withCtx(() => [createTextVNode(" ava ")]),
																									_: 1
																								})) : createCommentVNode("", true),
																								item.type === "video" ? (openBlock(), createBlock(VChip, {
																									key: 1,
																									size: "x-small",
																									color: item.processing_status === "done" ? "green" : "orange",
																									variant: "flat"
																								}, {
																									default: withCtx(() => [createTextVNode(toDisplayString(item.processing_status || "pending"), 1)]),
																									_: 2
																								}, 1032, ["color"])) : createCommentVNode("", true),
																								item.is_main_video ? (openBlock(), createBlock(VChip, {
																									key: 2,
																									size: "x-small",
																									color: "deep-purple",
																									variant: "flat"
																								}, {
																									default: withCtx(() => [createTextVNode(" main video ")]),
																									_: 1
																								})) : createCommentVNode("", true)
																							])]), createVNode(VCardText, null, {
																								default: withCtx(() => [
																									createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(item.title || item.file_name || item.original_name || `media #${item.id}`), 1),
																									createVNode("div", { class: "text-caption text-medium-emphasis text-truncate" }, toDisplayString(item.folder?.name || "Без папки"), 1),
																									createVNode("div", { class: "text-caption text-medium-emphasis" }, toDisplayString(formatSize(item.size)) + " · " + toDisplayString(formatDate(item.created_at)), 1),
																									item.type === "video" ? (openBlock(), createBlock("div", {
																										key: 0,
																										class: "text-caption text-medium-emphasis"
																									}, toDisplayString(formatDuration(item.duration_seconds)) + " · " + toDisplayString(mediaResolution(item)), 1)) : createCommentVNode("", true),
																									item.alt ? (openBlock(), createBlock("div", {
																										key: 1,
																										class: "text-caption text-medium-emphasis text-truncate mt-1"
																									}, " alt: " + toDisplayString(item.alt), 1)) : createCommentVNode("", true),
																									createVNode("div", { class: "d-flex align-center justify-space-between mt-3" }, [createVNode(VSwitch, {
																										"model-value": !!item.is_published,
																										loading: !!unref(publishing)[item.id],
																										density: "compact",
																										color: "green",
																										inset: "",
																										"hide-details": "",
																										"onUpdate:modelValue": () => handleTogglePublish(item)
																									}, null, 8, [
																										"model-value",
																										"loading",
																										"onUpdate:modelValue"
																									]), createVNode("div", { class: "d-flex align-center" }, [
																										createVNode(VBtn, {
																											icon: "mdi-arrow-up",
																											size: "small",
																											variant: "text",
																											disabled: !canMoveMediaUp(item),
																											loading: !!sortingMedia.value[item.id],
																											onClick: ($event) => moveMedia(item, -1)
																										}, null, 8, [
																											"disabled",
																											"loading",
																											"onClick"
																										]),
																										createVNode(VBtn, {
																											icon: "mdi-arrow-down",
																											size: "small",
																											variant: "text",
																											disabled: !canMoveMediaDown(item),
																											loading: !!sortingMedia.value[item.id],
																											onClick: ($event) => moveMedia(item, 1)
																										}, null, 8, [
																											"disabled",
																											"loading",
																											"onClick"
																										]),
																										item.type === "image" ? (openBlock(), createBlock(VBtn, {
																											key: 0,
																											icon: "mdi-star",
																											size: "small",
																											variant: "text",
																											color: "amber-darken-2",
																											loading: !!unref(settingAva)[item.id],
																											onClick: ($event) => handleSetAva(item)
																										}, null, 8, ["loading", "onClick"])) : createCommentVNode("", true),
																										item.type === "video" ? (openBlock(), createBlock(VBtn, {
																											key: 1,
																											icon: "mdi-cog-play",
																											size: "small",
																											variant: "text",
																											color: "deep-purple",
																											loading: !!unref(processing)[item.id],
																											disabled: ["queued", "processing"].includes(item.processing_status),
																											onClick: ($event) => handleProcessVideo(item)
																										}, null, 8, [
																											"loading",
																											"disabled",
																											"onClick"
																										])) : createCommentVNode("", true),
																										item.type === "video" ? (openBlock(), createBlock(VBtn, {
																											key: 2,
																											size: "small",
																											variant: "text",
																											color: "deep-purple-darken-2",
																											loading: !!unref(settingMainVideo)[item.id],
																											disabled: item.processing_status !== "done" || !item.video_mp4_url,
																											onClick: ($event) => handleSetMainVideo(item)
																										}, {
																											default: withCtx(() => [createVNode("span", { class: "main-video-icon" }, [createVNode(VIcon, {
																												icon: "mdi-video",
																												size: "22"
																											}), createVNode(VIcon, {
																												icon: "mdi-star",
																												size: "11",
																												class: "main-video-icon__star"
																											})])]),
																											_: 1
																										}, 8, [
																											"loading",
																											"disabled",
																											"onClick"
																										])) : createCommentVNode("", true),
																										createVNode(VBtn, {
																											icon: "mdi-open-in-new",
																											size: "small",
																											variant: "text",
																											href: mediaOpenUrl(item),
																											target: "_blank"
																										}, null, 8, ["href"]),
																										createVNode(VMenu, null, {
																											activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
																												icon: "mdi-dots-vertical",
																												size: "small",
																												variant: "text"
																											}), null, 16)]),
																											default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																												default: withCtx(() => [
																													createVNode(VListItem, {
																														"prepend-icon": "mdi-pencil",
																														title: "Редактировать",
																														onClick: ($event) => openEditMedia(item)
																													}, null, 8, ["onClick"]),
																													createVNode(VListItem, {
																														"prepend-icon": "mdi-rename-box",
																														title: "Переименовать файл",
																														onClick: ($event) => openRenameMedia(item)
																													}, null, 8, ["onClick"]),
																													createVNode(VListItem, {
																														"prepend-icon": "mdi-delete",
																														title: "Удалить",
																														"base-color": "red",
																														onClick: ($event) => askDeleteMedia(item)
																													}, null, 8, ["onClick"])
																												]),
																												_: 2
																											}, 1024)]),
																											_: 2
																										}, 1024)
																									])])
																								]),
																								_: 2
																							}, 1024)];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					else return [createVNode(VCard, { class: ["h-100 media-card", { "media-card--ava": item.is_ava }] }, {
																						default: withCtx(() => [createVNode("div", { class: "media-preview" }, [mediaPreview(item) ? (openBlock(), createBlock(VImg, {
																							key: 0,
																							src: mediaPreview(item),
																							height: "180",
																							cover: ""
																						}, {
																							placeholder: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-center fill-height" }, [createVNode(VProgressCircular, { indeterminate: "" })])]),
																							_: 1
																						}, 8, ["src"])) : (openBlock(), createBlock("div", {
																							key: 1,
																							class: "media-empty-preview"
																						}, [
																							createVNode(VIcon, {
																								icon: mediaTypeIcon(item),
																								size: "56",
																								class: "mb-2"
																							}, null, 8, ["icon"]),
																							createVNode("div", { class: "text-subtitle-2" }, toDisplayString(item.type === "video" ? "Видео ожидает обработки" : "Нет preview"), 1),
																							createVNode("div", { class: "text-caption text-medium-emphasis mt-1" }, toDisplayString(item.processing_status || "pending"), 1)
																						])), createVNode("div", { class: "media-preview__badges" }, [
																							createVNode(VChip, {
																								size: "x-small",
																								variant: "flat"
																							}, {
																								default: withCtx(() => [createVNode(VIcon, {
																									icon: mediaTypeIcon(item),
																									size: "14",
																									class: "mr-1"
																								}, null, 8, ["icon"]), createTextVNode(" " + toDisplayString(item.type), 1)]),
																								_: 2
																							}, 1024),
																							item.is_ava ? (openBlock(), createBlock(VChip, {
																								key: 0,
																								size: "x-small",
																								color: "amber",
																								variant: "flat"
																							}, {
																								default: withCtx(() => [createTextVNode(" ava ")]),
																								_: 1
																							})) : createCommentVNode("", true),
																							item.type === "video" ? (openBlock(), createBlock(VChip, {
																								key: 1,
																								size: "x-small",
																								color: item.processing_status === "done" ? "green" : "orange",
																								variant: "flat"
																							}, {
																								default: withCtx(() => [createTextVNode(toDisplayString(item.processing_status || "pending"), 1)]),
																								_: 2
																							}, 1032, ["color"])) : createCommentVNode("", true),
																							item.is_main_video ? (openBlock(), createBlock(VChip, {
																								key: 2,
																								size: "x-small",
																								color: "deep-purple",
																								variant: "flat"
																							}, {
																								default: withCtx(() => [createTextVNode(" main video ")]),
																								_: 1
																							})) : createCommentVNode("", true)
																						])]), createVNode(VCardText, null, {
																							default: withCtx(() => [
																								createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(item.title || item.file_name || item.original_name || `media #${item.id}`), 1),
																								createVNode("div", { class: "text-caption text-medium-emphasis text-truncate" }, toDisplayString(item.folder?.name || "Без папки"), 1),
																								createVNode("div", { class: "text-caption text-medium-emphasis" }, toDisplayString(formatSize(item.size)) + " · " + toDisplayString(formatDate(item.created_at)), 1),
																								item.type === "video" ? (openBlock(), createBlock("div", {
																									key: 0,
																									class: "text-caption text-medium-emphasis"
																								}, toDisplayString(formatDuration(item.duration_seconds)) + " · " + toDisplayString(mediaResolution(item)), 1)) : createCommentVNode("", true),
																								item.alt ? (openBlock(), createBlock("div", {
																									key: 1,
																									class: "text-caption text-medium-emphasis text-truncate mt-1"
																								}, " alt: " + toDisplayString(item.alt), 1)) : createCommentVNode("", true),
																								createVNode("div", { class: "d-flex align-center justify-space-between mt-3" }, [createVNode(VSwitch, {
																									"model-value": !!item.is_published,
																									loading: !!unref(publishing)[item.id],
																									density: "compact",
																									color: "green",
																									inset: "",
																									"hide-details": "",
																									"onUpdate:modelValue": () => handleTogglePublish(item)
																								}, null, 8, [
																									"model-value",
																									"loading",
																									"onUpdate:modelValue"
																								]), createVNode("div", { class: "d-flex align-center" }, [
																									createVNode(VBtn, {
																										icon: "mdi-arrow-up",
																										size: "small",
																										variant: "text",
																										disabled: !canMoveMediaUp(item),
																										loading: !!sortingMedia.value[item.id],
																										onClick: ($event) => moveMedia(item, -1)
																									}, null, 8, [
																										"disabled",
																										"loading",
																										"onClick"
																									]),
																									createVNode(VBtn, {
																										icon: "mdi-arrow-down",
																										size: "small",
																										variant: "text",
																										disabled: !canMoveMediaDown(item),
																										loading: !!sortingMedia.value[item.id],
																										onClick: ($event) => moveMedia(item, 1)
																									}, null, 8, [
																										"disabled",
																										"loading",
																										"onClick"
																									]),
																									item.type === "image" ? (openBlock(), createBlock(VBtn, {
																										key: 0,
																										icon: "mdi-star",
																										size: "small",
																										variant: "text",
																										color: "amber-darken-2",
																										loading: !!unref(settingAva)[item.id],
																										onClick: ($event) => handleSetAva(item)
																									}, null, 8, ["loading", "onClick"])) : createCommentVNode("", true),
																									item.type === "video" ? (openBlock(), createBlock(VBtn, {
																										key: 1,
																										icon: "mdi-cog-play",
																										size: "small",
																										variant: "text",
																										color: "deep-purple",
																										loading: !!unref(processing)[item.id],
																										disabled: ["queued", "processing"].includes(item.processing_status),
																										onClick: ($event) => handleProcessVideo(item)
																									}, null, 8, [
																										"loading",
																										"disabled",
																										"onClick"
																									])) : createCommentVNode("", true),
																									item.type === "video" ? (openBlock(), createBlock(VBtn, {
																										key: 2,
																										size: "small",
																										variant: "text",
																										color: "deep-purple-darken-2",
																										loading: !!unref(settingMainVideo)[item.id],
																										disabled: item.processing_status !== "done" || !item.video_mp4_url,
																										onClick: ($event) => handleSetMainVideo(item)
																									}, {
																										default: withCtx(() => [createVNode("span", { class: "main-video-icon" }, [createVNode(VIcon, {
																											icon: "mdi-video",
																											size: "22"
																										}), createVNode(VIcon, {
																											icon: "mdi-star",
																											size: "11",
																											class: "main-video-icon__star"
																										})])]),
																										_: 1
																									}, 8, [
																										"loading",
																										"disabled",
																										"onClick"
																									])) : createCommentVNode("", true),
																									createVNode(VBtn, {
																										icon: "mdi-open-in-new",
																										size: "small",
																										variant: "text",
																										href: mediaOpenUrl(item),
																										target: "_blank"
																									}, null, 8, ["href"]),
																									createVNode(VMenu, null, {
																										activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
																											icon: "mdi-dots-vertical",
																											size: "small",
																											variant: "text"
																										}), null, 16)]),
																										default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																											default: withCtx(() => [
																												createVNode(VListItem, {
																													"prepend-icon": "mdi-pencil",
																													title: "Редактировать",
																													onClick: ($event) => openEditMedia(item)
																												}, null, 8, ["onClick"]),
																												createVNode(VListItem, {
																													"prepend-icon": "mdi-rename-box",
																													title: "Переименовать файл",
																													onClick: ($event) => openRenameMedia(item)
																												}, null, 8, ["onClick"]),
																												createVNode(VListItem, {
																													"prepend-icon": "mdi-delete",
																													title: "Удалить",
																													"base-color": "red",
																													onClick: ($event) => askDeleteMedia(item)
																												}, null, 8, ["onClick"])
																											]),
																											_: 2
																										}, 1024)]),
																										_: 2
																									}, 1024)
																								])])
																							]),
																							_: 2
																						}, 1024)]),
																						_: 2
																					}, 1032, ["class"])];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																		});
																		_push(`<!--]-->`);
																	} else return [(openBlock(true), createBlock(Fragment, null, renderList(filteredMedia.value, (item) => {
																		return openBlock(), createBlock(VCol, {
																			key: item.id,
																			cols: "12",
																			sm: "6",
																			md: "4",
																			xl: "3"
																		}, {
																			default: withCtx(() => [createVNode(VCard, { class: ["h-100 media-card", { "media-card--ava": item.is_ava }] }, {
																				default: withCtx(() => [createVNode("div", { class: "media-preview" }, [mediaPreview(item) ? (openBlock(), createBlock(VImg, {
																					key: 0,
																					src: mediaPreview(item),
																					height: "180",
																					cover: ""
																				}, {
																					placeholder: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-center fill-height" }, [createVNode(VProgressCircular, { indeterminate: "" })])]),
																					_: 1
																				}, 8, ["src"])) : (openBlock(), createBlock("div", {
																					key: 1,
																					class: "media-empty-preview"
																				}, [
																					createVNode(VIcon, {
																						icon: mediaTypeIcon(item),
																						size: "56",
																						class: "mb-2"
																					}, null, 8, ["icon"]),
																					createVNode("div", { class: "text-subtitle-2" }, toDisplayString(item.type === "video" ? "Видео ожидает обработки" : "Нет preview"), 1),
																					createVNode("div", { class: "text-caption text-medium-emphasis mt-1" }, toDisplayString(item.processing_status || "pending"), 1)
																				])), createVNode("div", { class: "media-preview__badges" }, [
																					createVNode(VChip, {
																						size: "x-small",
																						variant: "flat"
																					}, {
																						default: withCtx(() => [createVNode(VIcon, {
																							icon: mediaTypeIcon(item),
																							size: "14",
																							class: "mr-1"
																						}, null, 8, ["icon"]), createTextVNode(" " + toDisplayString(item.type), 1)]),
																						_: 2
																					}, 1024),
																					item.is_ava ? (openBlock(), createBlock(VChip, {
																						key: 0,
																						size: "x-small",
																						color: "amber",
																						variant: "flat"
																					}, {
																						default: withCtx(() => [createTextVNode(" ava ")]),
																						_: 1
																					})) : createCommentVNode("", true),
																					item.type === "video" ? (openBlock(), createBlock(VChip, {
																						key: 1,
																						size: "x-small",
																						color: item.processing_status === "done" ? "green" : "orange",
																						variant: "flat"
																					}, {
																						default: withCtx(() => [createTextVNode(toDisplayString(item.processing_status || "pending"), 1)]),
																						_: 2
																					}, 1032, ["color"])) : createCommentVNode("", true),
																					item.is_main_video ? (openBlock(), createBlock(VChip, {
																						key: 2,
																						size: "x-small",
																						color: "deep-purple",
																						variant: "flat"
																					}, {
																						default: withCtx(() => [createTextVNode(" main video ")]),
																						_: 1
																					})) : createCommentVNode("", true)
																				])]), createVNode(VCardText, null, {
																					default: withCtx(() => [
																						createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(item.title || item.file_name || item.original_name || `media #${item.id}`), 1),
																						createVNode("div", { class: "text-caption text-medium-emphasis text-truncate" }, toDisplayString(item.folder?.name || "Без папки"), 1),
																						createVNode("div", { class: "text-caption text-medium-emphasis" }, toDisplayString(formatSize(item.size)) + " · " + toDisplayString(formatDate(item.created_at)), 1),
																						item.type === "video" ? (openBlock(), createBlock("div", {
																							key: 0,
																							class: "text-caption text-medium-emphasis"
																						}, toDisplayString(formatDuration(item.duration_seconds)) + " · " + toDisplayString(mediaResolution(item)), 1)) : createCommentVNode("", true),
																						item.alt ? (openBlock(), createBlock("div", {
																							key: 1,
																							class: "text-caption text-medium-emphasis text-truncate mt-1"
																						}, " alt: " + toDisplayString(item.alt), 1)) : createCommentVNode("", true),
																						createVNode("div", { class: "d-flex align-center justify-space-between mt-3" }, [createVNode(VSwitch, {
																							"model-value": !!item.is_published,
																							loading: !!unref(publishing)[item.id],
																							density: "compact",
																							color: "green",
																							inset: "",
																							"hide-details": "",
																							"onUpdate:modelValue": () => handleTogglePublish(item)
																						}, null, 8, [
																							"model-value",
																							"loading",
																							"onUpdate:modelValue"
																						]), createVNode("div", { class: "d-flex align-center" }, [
																							createVNode(VBtn, {
																								icon: "mdi-arrow-up",
																								size: "small",
																								variant: "text",
																								disabled: !canMoveMediaUp(item),
																								loading: !!sortingMedia.value[item.id],
																								onClick: ($event) => moveMedia(item, -1)
																							}, null, 8, [
																								"disabled",
																								"loading",
																								"onClick"
																							]),
																							createVNode(VBtn, {
																								icon: "mdi-arrow-down",
																								size: "small",
																								variant: "text",
																								disabled: !canMoveMediaDown(item),
																								loading: !!sortingMedia.value[item.id],
																								onClick: ($event) => moveMedia(item, 1)
																							}, null, 8, [
																								"disabled",
																								"loading",
																								"onClick"
																							]),
																							item.type === "image" ? (openBlock(), createBlock(VBtn, {
																								key: 0,
																								icon: "mdi-star",
																								size: "small",
																								variant: "text",
																								color: "amber-darken-2",
																								loading: !!unref(settingAva)[item.id],
																								onClick: ($event) => handleSetAva(item)
																							}, null, 8, ["loading", "onClick"])) : createCommentVNode("", true),
																							item.type === "video" ? (openBlock(), createBlock(VBtn, {
																								key: 1,
																								icon: "mdi-cog-play",
																								size: "small",
																								variant: "text",
																								color: "deep-purple",
																								loading: !!unref(processing)[item.id],
																								disabled: ["queued", "processing"].includes(item.processing_status),
																								onClick: ($event) => handleProcessVideo(item)
																							}, null, 8, [
																								"loading",
																								"disabled",
																								"onClick"
																							])) : createCommentVNode("", true),
																							item.type === "video" ? (openBlock(), createBlock(VBtn, {
																								key: 2,
																								size: "small",
																								variant: "text",
																								color: "deep-purple-darken-2",
																								loading: !!unref(settingMainVideo)[item.id],
																								disabled: item.processing_status !== "done" || !item.video_mp4_url,
																								onClick: ($event) => handleSetMainVideo(item)
																							}, {
																								default: withCtx(() => [createVNode("span", { class: "main-video-icon" }, [createVNode(VIcon, {
																									icon: "mdi-video",
																									size: "22"
																								}), createVNode(VIcon, {
																									icon: "mdi-star",
																									size: "11",
																									class: "main-video-icon__star"
																								})])]),
																								_: 1
																							}, 8, [
																								"loading",
																								"disabled",
																								"onClick"
																							])) : createCommentVNode("", true),
																							createVNode(VBtn, {
																								icon: "mdi-open-in-new",
																								size: "small",
																								variant: "text",
																								href: mediaOpenUrl(item),
																								target: "_blank"
																							}, null, 8, ["href"]),
																							createVNode(VMenu, null, {
																								activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
																									icon: "mdi-dots-vertical",
																									size: "small",
																									variant: "text"
																								}), null, 16)]),
																								default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																									default: withCtx(() => [
																										createVNode(VListItem, {
																											"prepend-icon": "mdi-pencil",
																											title: "Редактировать",
																											onClick: ($event) => openEditMedia(item)
																										}, null, 8, ["onClick"]),
																										createVNode(VListItem, {
																											"prepend-icon": "mdi-rename-box",
																											title: "Переименовать файл",
																											onClick: ($event) => openRenameMedia(item)
																										}, null, 8, ["onClick"]),
																										createVNode(VListItem, {
																											"prepend-icon": "mdi-delete",
																											title: "Удалить",
																											"base-color": "red",
																											onClick: ($event) => askDeleteMedia(item)
																										}, null, 8, ["onClick"])
																									]),
																									_: 2
																								}, 1024)]),
																								_: 2
																							}, 1024)
																						])])
																					]),
																					_: 2
																				}, 1024)]),
																				_: 2
																			}, 1032, ["class"])]),
																			_: 2
																		}, 1024);
																	}), 128))];
																}),
																_: 1
															}, _parent, _scopeId));
															else _push(ssrRenderComponent(VAlert, {
																type: "info",
																variant: "tonal"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` Media-файлов пока нет. `);
																	else return [createTextVNode(" Media-файлов пока нет. ")];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [unref(loading) ? (openBlock(), createBlock(VProgressLinear, {
															key: 0,
															indeterminate: "",
															class: "mb-3"
														})) : createCommentVNode("", true), filteredMedia.value.length ? (openBlock(), createBlock(VRow, { key: 1 }, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(filteredMedia.value, (item) => {
																return openBlock(), createBlock(VCol, {
																	key: item.id,
																	cols: "12",
																	sm: "6",
																	md: "4",
																	xl: "3"
																}, {
																	default: withCtx(() => [createVNode(VCard, { class: ["h-100 media-card", { "media-card--ava": item.is_ava }] }, {
																		default: withCtx(() => [createVNode("div", { class: "media-preview" }, [mediaPreview(item) ? (openBlock(), createBlock(VImg, {
																			key: 0,
																			src: mediaPreview(item),
																			height: "180",
																			cover: ""
																		}, {
																			placeholder: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-center fill-height" }, [createVNode(VProgressCircular, { indeterminate: "" })])]),
																			_: 1
																		}, 8, ["src"])) : (openBlock(), createBlock("div", {
																			key: 1,
																			class: "media-empty-preview"
																		}, [
																			createVNode(VIcon, {
																				icon: mediaTypeIcon(item),
																				size: "56",
																				class: "mb-2"
																			}, null, 8, ["icon"]),
																			createVNode("div", { class: "text-subtitle-2" }, toDisplayString(item.type === "video" ? "Видео ожидает обработки" : "Нет preview"), 1),
																			createVNode("div", { class: "text-caption text-medium-emphasis mt-1" }, toDisplayString(item.processing_status || "pending"), 1)
																		])), createVNode("div", { class: "media-preview__badges" }, [
																			createVNode(VChip, {
																				size: "x-small",
																				variant: "flat"
																			}, {
																				default: withCtx(() => [createVNode(VIcon, {
																					icon: mediaTypeIcon(item),
																					size: "14",
																					class: "mr-1"
																				}, null, 8, ["icon"]), createTextVNode(" " + toDisplayString(item.type), 1)]),
																				_: 2
																			}, 1024),
																			item.is_ava ? (openBlock(), createBlock(VChip, {
																				key: 0,
																				size: "x-small",
																				color: "amber",
																				variant: "flat"
																			}, {
																				default: withCtx(() => [createTextVNode(" ava ")]),
																				_: 1
																			})) : createCommentVNode("", true),
																			item.type === "video" ? (openBlock(), createBlock(VChip, {
																				key: 1,
																				size: "x-small",
																				color: item.processing_status === "done" ? "green" : "orange",
																				variant: "flat"
																			}, {
																				default: withCtx(() => [createTextVNode(toDisplayString(item.processing_status || "pending"), 1)]),
																				_: 2
																			}, 1032, ["color"])) : createCommentVNode("", true),
																			item.is_main_video ? (openBlock(), createBlock(VChip, {
																				key: 2,
																				size: "x-small",
																				color: "deep-purple",
																				variant: "flat"
																			}, {
																				default: withCtx(() => [createTextVNode(" main video ")]),
																				_: 1
																			})) : createCommentVNode("", true)
																		])]), createVNode(VCardText, null, {
																			default: withCtx(() => [
																				createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(item.title || item.file_name || item.original_name || `media #${item.id}`), 1),
																				createVNode("div", { class: "text-caption text-medium-emphasis text-truncate" }, toDisplayString(item.folder?.name || "Без папки"), 1),
																				createVNode("div", { class: "text-caption text-medium-emphasis" }, toDisplayString(formatSize(item.size)) + " · " + toDisplayString(formatDate(item.created_at)), 1),
																				item.type === "video" ? (openBlock(), createBlock("div", {
																					key: 0,
																					class: "text-caption text-medium-emphasis"
																				}, toDisplayString(formatDuration(item.duration_seconds)) + " · " + toDisplayString(mediaResolution(item)), 1)) : createCommentVNode("", true),
																				item.alt ? (openBlock(), createBlock("div", {
																					key: 1,
																					class: "text-caption text-medium-emphasis text-truncate mt-1"
																				}, " alt: " + toDisplayString(item.alt), 1)) : createCommentVNode("", true),
																				createVNode("div", { class: "d-flex align-center justify-space-between mt-3" }, [createVNode(VSwitch, {
																					"model-value": !!item.is_published,
																					loading: !!unref(publishing)[item.id],
																					density: "compact",
																					color: "green",
																					inset: "",
																					"hide-details": "",
																					"onUpdate:modelValue": () => handleTogglePublish(item)
																				}, null, 8, [
																					"model-value",
																					"loading",
																					"onUpdate:modelValue"
																				]), createVNode("div", { class: "d-flex align-center" }, [
																					createVNode(VBtn, {
																						icon: "mdi-arrow-up",
																						size: "small",
																						variant: "text",
																						disabled: !canMoveMediaUp(item),
																						loading: !!sortingMedia.value[item.id],
																						onClick: ($event) => moveMedia(item, -1)
																					}, null, 8, [
																						"disabled",
																						"loading",
																						"onClick"
																					]),
																					createVNode(VBtn, {
																						icon: "mdi-arrow-down",
																						size: "small",
																						variant: "text",
																						disabled: !canMoveMediaDown(item),
																						loading: !!sortingMedia.value[item.id],
																						onClick: ($event) => moveMedia(item, 1)
																					}, null, 8, [
																						"disabled",
																						"loading",
																						"onClick"
																					]),
																					item.type === "image" ? (openBlock(), createBlock(VBtn, {
																						key: 0,
																						icon: "mdi-star",
																						size: "small",
																						variant: "text",
																						color: "amber-darken-2",
																						loading: !!unref(settingAva)[item.id],
																						onClick: ($event) => handleSetAva(item)
																					}, null, 8, ["loading", "onClick"])) : createCommentVNode("", true),
																					item.type === "video" ? (openBlock(), createBlock(VBtn, {
																						key: 1,
																						icon: "mdi-cog-play",
																						size: "small",
																						variant: "text",
																						color: "deep-purple",
																						loading: !!unref(processing)[item.id],
																						disabled: ["queued", "processing"].includes(item.processing_status),
																						onClick: ($event) => handleProcessVideo(item)
																					}, null, 8, [
																						"loading",
																						"disabled",
																						"onClick"
																					])) : createCommentVNode("", true),
																					item.type === "video" ? (openBlock(), createBlock(VBtn, {
																						key: 2,
																						size: "small",
																						variant: "text",
																						color: "deep-purple-darken-2",
																						loading: !!unref(settingMainVideo)[item.id],
																						disabled: item.processing_status !== "done" || !item.video_mp4_url,
																						onClick: ($event) => handleSetMainVideo(item)
																					}, {
																						default: withCtx(() => [createVNode("span", { class: "main-video-icon" }, [createVNode(VIcon, {
																							icon: "mdi-video",
																							size: "22"
																						}), createVNode(VIcon, {
																							icon: "mdi-star",
																							size: "11",
																							class: "main-video-icon__star"
																						})])]),
																						_: 1
																					}, 8, [
																						"loading",
																						"disabled",
																						"onClick"
																					])) : createCommentVNode("", true),
																					createVNode(VBtn, {
																						icon: "mdi-open-in-new",
																						size: "small",
																						variant: "text",
																						href: mediaOpenUrl(item),
																						target: "_blank"
																					}, null, 8, ["href"]),
																					createVNode(VMenu, null, {
																						activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
																							icon: "mdi-dots-vertical",
																							size: "small",
																							variant: "text"
																						}), null, 16)]),
																						default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																							default: withCtx(() => [
																								createVNode(VListItem, {
																									"prepend-icon": "mdi-pencil",
																									title: "Редактировать",
																									onClick: ($event) => openEditMedia(item)
																								}, null, 8, ["onClick"]),
																								createVNode(VListItem, {
																									"prepend-icon": "mdi-rename-box",
																									title: "Переименовать файл",
																									onClick: ($event) => openRenameMedia(item)
																								}, null, 8, ["onClick"]),
																								createVNode(VListItem, {
																									"prepend-icon": "mdi-delete",
																									title: "Удалить",
																									"base-color": "red",
																									onClick: ($event) => askDeleteMedia(item)
																								}, null, 8, ["onClick"])
																							]),
																							_: 2
																						}, 1024)]),
																						_: 2
																					}, 1024)
																				])])
																			]),
																			_: 2
																		}, 1024)]),
																		_: 2
																	}, 1032, ["class"])]),
																	_: 2
																}, 1024);
															}), 128))]),
															_: 1
														})) : (openBlock(), createBlock(VAlert, {
															key: 2,
															type: "info",
															variant: "tonal"
														}, {
															default: withCtx(() => [createTextVNode(" Media-файлов пока нет. ")]),
															_: 1
														}))];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [createVNode(VCol, {
												cols: "12",
												lg: "3"
											}, {
												default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
													default: withCtx(() => [createVNode(VCardTitle, { class: "text-subtitle-1" }, {
														default: withCtx(() => [createTextVNode(" Папки ")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VList, {
															density: "compact",
															lines: "two"
														}, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(folders), (folder) => {
																return openBlock(), createBlock(VListItem, { key: folder.id }, {
																	prepend: withCtx(() => [createVNode(VIcon, { icon: folder.is_archive ? "mdi-archive" : "mdi-folder" }, null, 8, ["icon"])]),
																	append: withCtx(() => [createVNode(VBtn, {
																		icon: "mdi-pencil",
																		size: "x-small",
																		variant: "text",
																		onClick: ($event) => openEditFolder(folder)
																	}, null, 8, ["onClick"]), createVNode(VBtn, {
																		icon: "mdi-delete",
																		size: "x-small",
																		variant: "text",
																		color: "red",
																		loading: !!unref(deleting)[`folder-${folder.id}`],
																		onClick: ($event) => askDeleteFolder(folder)
																	}, null, 8, ["loading", "onClick"])]),
																	default: withCtx(() => [createVNode(VListItemTitle, null, {
																		default: withCtx(() => [createTextVNode(toDisplayString(folder.name), 1)]),
																		_: 2
																	}, 1024), createVNode(VListItemSubtitle, null, {
																		default: withCtx(() => [createTextVNode(toDisplayString(folder.path), 1)]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1024);
															}), 128)), !unref(folders).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
																default: withCtx(() => [createVNode(VListItemTitle, { class: "text-medium-emphasis" }, {
																	default: withCtx(() => [createTextVNode(" Папок пока нет. ")]),
																	_: 1
																})]),
																_: 1
															})) : createCommentVNode("", true)]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											}), createVNode(VCol, {
												cols: "12",
												lg: "9"
											}, {
												default: withCtx(() => [unref(loading) ? (openBlock(), createBlock(VProgressLinear, {
													key: 0,
													indeterminate: "",
													class: "mb-3"
												})) : createCommentVNode("", true), filteredMedia.value.length ? (openBlock(), createBlock(VRow, { key: 1 }, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(filteredMedia.value, (item) => {
														return openBlock(), createBlock(VCol, {
															key: item.id,
															cols: "12",
															sm: "6",
															md: "4",
															xl: "3"
														}, {
															default: withCtx(() => [createVNode(VCard, { class: ["h-100 media-card", { "media-card--ava": item.is_ava }] }, {
																default: withCtx(() => [createVNode("div", { class: "media-preview" }, [mediaPreview(item) ? (openBlock(), createBlock(VImg, {
																	key: 0,
																	src: mediaPreview(item),
																	height: "180",
																	cover: ""
																}, {
																	placeholder: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-center fill-height" }, [createVNode(VProgressCircular, { indeterminate: "" })])]),
																	_: 1
																}, 8, ["src"])) : (openBlock(), createBlock("div", {
																	key: 1,
																	class: "media-empty-preview"
																}, [
																	createVNode(VIcon, {
																		icon: mediaTypeIcon(item),
																		size: "56",
																		class: "mb-2"
																	}, null, 8, ["icon"]),
																	createVNode("div", { class: "text-subtitle-2" }, toDisplayString(item.type === "video" ? "Видео ожидает обработки" : "Нет preview"), 1),
																	createVNode("div", { class: "text-caption text-medium-emphasis mt-1" }, toDisplayString(item.processing_status || "pending"), 1)
																])), createVNode("div", { class: "media-preview__badges" }, [
																	createVNode(VChip, {
																		size: "x-small",
																		variant: "flat"
																	}, {
																		default: withCtx(() => [createVNode(VIcon, {
																			icon: mediaTypeIcon(item),
																			size: "14",
																			class: "mr-1"
																		}, null, 8, ["icon"]), createTextVNode(" " + toDisplayString(item.type), 1)]),
																		_: 2
																	}, 1024),
																	item.is_ava ? (openBlock(), createBlock(VChip, {
																		key: 0,
																		size: "x-small",
																		color: "amber",
																		variant: "flat"
																	}, {
																		default: withCtx(() => [createTextVNode(" ava ")]),
																		_: 1
																	})) : createCommentVNode("", true),
																	item.type === "video" ? (openBlock(), createBlock(VChip, {
																		key: 1,
																		size: "x-small",
																		color: item.processing_status === "done" ? "green" : "orange",
																		variant: "flat"
																	}, {
																		default: withCtx(() => [createTextVNode(toDisplayString(item.processing_status || "pending"), 1)]),
																		_: 2
																	}, 1032, ["color"])) : createCommentVNode("", true),
																	item.is_main_video ? (openBlock(), createBlock(VChip, {
																		key: 2,
																		size: "x-small",
																		color: "deep-purple",
																		variant: "flat"
																	}, {
																		default: withCtx(() => [createTextVNode(" main video ")]),
																		_: 1
																	})) : createCommentVNode("", true)
																])]), createVNode(VCardText, null, {
																	default: withCtx(() => [
																		createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(item.title || item.file_name || item.original_name || `media #${item.id}`), 1),
																		createVNode("div", { class: "text-caption text-medium-emphasis text-truncate" }, toDisplayString(item.folder?.name || "Без папки"), 1),
																		createVNode("div", { class: "text-caption text-medium-emphasis" }, toDisplayString(formatSize(item.size)) + " · " + toDisplayString(formatDate(item.created_at)), 1),
																		item.type === "video" ? (openBlock(), createBlock("div", {
																			key: 0,
																			class: "text-caption text-medium-emphasis"
																		}, toDisplayString(formatDuration(item.duration_seconds)) + " · " + toDisplayString(mediaResolution(item)), 1)) : createCommentVNode("", true),
																		item.alt ? (openBlock(), createBlock("div", {
																			key: 1,
																			class: "text-caption text-medium-emphasis text-truncate mt-1"
																		}, " alt: " + toDisplayString(item.alt), 1)) : createCommentVNode("", true),
																		createVNode("div", { class: "d-flex align-center justify-space-between mt-3" }, [createVNode(VSwitch, {
																			"model-value": !!item.is_published,
																			loading: !!unref(publishing)[item.id],
																			density: "compact",
																			color: "green",
																			inset: "",
																			"hide-details": "",
																			"onUpdate:modelValue": () => handleTogglePublish(item)
																		}, null, 8, [
																			"model-value",
																			"loading",
																			"onUpdate:modelValue"
																		]), createVNode("div", { class: "d-flex align-center" }, [
																			createVNode(VBtn, {
																				icon: "mdi-arrow-up",
																				size: "small",
																				variant: "text",
																				disabled: !canMoveMediaUp(item),
																				loading: !!sortingMedia.value[item.id],
																				onClick: ($event) => moveMedia(item, -1)
																			}, null, 8, [
																				"disabled",
																				"loading",
																				"onClick"
																			]),
																			createVNode(VBtn, {
																				icon: "mdi-arrow-down",
																				size: "small",
																				variant: "text",
																				disabled: !canMoveMediaDown(item),
																				loading: !!sortingMedia.value[item.id],
																				onClick: ($event) => moveMedia(item, 1)
																			}, null, 8, [
																				"disabled",
																				"loading",
																				"onClick"
																			]),
																			item.type === "image" ? (openBlock(), createBlock(VBtn, {
																				key: 0,
																				icon: "mdi-star",
																				size: "small",
																				variant: "text",
																				color: "amber-darken-2",
																				loading: !!unref(settingAva)[item.id],
																				onClick: ($event) => handleSetAva(item)
																			}, null, 8, ["loading", "onClick"])) : createCommentVNode("", true),
																			item.type === "video" ? (openBlock(), createBlock(VBtn, {
																				key: 1,
																				icon: "mdi-cog-play",
																				size: "small",
																				variant: "text",
																				color: "deep-purple",
																				loading: !!unref(processing)[item.id],
																				disabled: ["queued", "processing"].includes(item.processing_status),
																				onClick: ($event) => handleProcessVideo(item)
																			}, null, 8, [
																				"loading",
																				"disabled",
																				"onClick"
																			])) : createCommentVNode("", true),
																			item.type === "video" ? (openBlock(), createBlock(VBtn, {
																				key: 2,
																				size: "small",
																				variant: "text",
																				color: "deep-purple-darken-2",
																				loading: !!unref(settingMainVideo)[item.id],
																				disabled: item.processing_status !== "done" || !item.video_mp4_url,
																				onClick: ($event) => handleSetMainVideo(item)
																			}, {
																				default: withCtx(() => [createVNode("span", { class: "main-video-icon" }, [createVNode(VIcon, {
																					icon: "mdi-video",
																					size: "22"
																				}), createVNode(VIcon, {
																					icon: "mdi-star",
																					size: "11",
																					class: "main-video-icon__star"
																				})])]),
																				_: 1
																			}, 8, [
																				"loading",
																				"disabled",
																				"onClick"
																			])) : createCommentVNode("", true),
																			createVNode(VBtn, {
																				icon: "mdi-open-in-new",
																				size: "small",
																				variant: "text",
																				href: mediaOpenUrl(item),
																				target: "_blank"
																			}, null, 8, ["href"]),
																			createVNode(VMenu, null, {
																				activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
																					icon: "mdi-dots-vertical",
																					size: "small",
																					variant: "text"
																				}), null, 16)]),
																				default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																					default: withCtx(() => [
																						createVNode(VListItem, {
																							"prepend-icon": "mdi-pencil",
																							title: "Редактировать",
																							onClick: ($event) => openEditMedia(item)
																						}, null, 8, ["onClick"]),
																						createVNode(VListItem, {
																							"prepend-icon": "mdi-rename-box",
																							title: "Переименовать файл",
																							onClick: ($event) => openRenameMedia(item)
																						}, null, 8, ["onClick"]),
																						createVNode(VListItem, {
																							"prepend-icon": "mdi-delete",
																							title: "Удалить",
																							"base-color": "red",
																							onClick: ($event) => askDeleteMedia(item)
																						}, null, 8, ["onClick"])
																					]),
																					_: 2
																				}, 1024)]),
																				_: 2
																			}, 1024)
																		])])
																	]),
																	_: 2
																}, 1024)]),
																_: 2
															}, 1032, ["class"])]),
															_: 2
														}, 1024);
													}), 128))]),
													_: 1
												})) : (openBlock(), createBlock(VAlert, {
													key: 2,
													type: "info",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(" Media-файлов пока нет. ")]),
													_: 1
												}))]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									errorMessage.value ? (openBlock(), createBlock(VAlert, {
										key: 0,
										type: "error",
										variant: "tonal",
										class: "mb-4",
										closable: "",
										"onClick:close": ($event) => errorMessage.value = ""
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(errorMessage.value), 1)]),
										_: 1
									}, 8, ["onClick:close"])) : createCommentVNode("", true),
									createVNode(VAlert, {
										type: "info",
										variant: "tonal",
										class: "mb-4"
									}, {
										default: withCtx(() => [
											createTextVNode(" Фото сразу создают thumbnail. Видео пока загружается как original со статусом "),
											createVNode("strong", null, "pending"),
											createTextVNode(". Обработку видео через FFmpeg подключим следующим шагом. ")
										]),
										_: 1
									}),
									createVNode(VRow, { class: "mb-2" }, {
										default: withCtx(() => [
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: search.value,
													"onUpdate:modelValue": ($event) => search.value = $event,
													label: "Поиск media",
													variant: "solo-inverted",
													density: "compact",
													clearable: "",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "2"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: filterType.value,
													"onUpdate:modelValue": ($event) => filterType.value = $event,
													items: [
														{
															title: "Все типы",
															value: "all"
														},
														{
															title: "Фото",
															value: "image"
														},
														{
															title: "Видео",
															value: "video"
														},
														{
															title: "Документы",
															value: "document"
														}
													],
													label: "Тип",
													variant: "solo-inverted",
													density: "compact",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: filterFolderId.value,
													"onUpdate:modelValue": ($event) => filterFolderId.value = $event,
													items: filterFolderItems.value,
													label: "Папка",
													variant: "solo-inverted",
													density: "compact",
													"hide-details": ""
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: filterPublished.value,
													"onUpdate:modelValue": ($event) => filterPublished.value = $event,
													items: [
														{
															title: "Все",
															value: "all"
														},
														{
															title: "Опубликованные",
															value: "published"
														},
														{
															title: "Скрытые",
															value: "hidden"
														}
													],
													label: "Публикация",
													variant: "solo-inverted",
													density: "compact",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})
										]),
										_: 1
									}),
									createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, {
											cols: "12",
											lg: "3"
										}, {
											default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
												default: withCtx(() => [createVNode(VCardTitle, { class: "text-subtitle-1" }, {
													default: withCtx(() => [createTextVNode(" Папки ")]),
													_: 1
												}), createVNode(VCardText, null, {
													default: withCtx(() => [createVNode(VList, {
														density: "compact",
														lines: "two"
													}, {
														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(folders), (folder) => {
															return openBlock(), createBlock(VListItem, { key: folder.id }, {
																prepend: withCtx(() => [createVNode(VIcon, { icon: folder.is_archive ? "mdi-archive" : "mdi-folder" }, null, 8, ["icon"])]),
																append: withCtx(() => [createVNode(VBtn, {
																	icon: "mdi-pencil",
																	size: "x-small",
																	variant: "text",
																	onClick: ($event) => openEditFolder(folder)
																}, null, 8, ["onClick"]), createVNode(VBtn, {
																	icon: "mdi-delete",
																	size: "x-small",
																	variant: "text",
																	color: "red",
																	loading: !!unref(deleting)[`folder-${folder.id}`],
																	onClick: ($event) => askDeleteFolder(folder)
																}, null, 8, ["loading", "onClick"])]),
																default: withCtx(() => [createVNode(VListItemTitle, null, {
																	default: withCtx(() => [createTextVNode(toDisplayString(folder.name), 1)]),
																	_: 2
																}, 1024), createVNode(VListItemSubtitle, null, {
																	default: withCtx(() => [createTextVNode(toDisplayString(folder.path), 1)]),
																	_: 2
																}, 1024)]),
																_: 2
															}, 1024);
														}), 128)), !unref(folders).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
															default: withCtx(() => [createVNode(VListItemTitle, { class: "text-medium-emphasis" }, {
																default: withCtx(() => [createTextVNode(" Папок пока нет. ")]),
																_: 1
															})]),
															_: 1
														})) : createCommentVNode("", true)]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										}), createVNode(VCol, {
											cols: "12",
											lg: "9"
										}, {
											default: withCtx(() => [unref(loading) ? (openBlock(), createBlock(VProgressLinear, {
												key: 0,
												indeterminate: "",
												class: "mb-3"
											})) : createCommentVNode("", true), filteredMedia.value.length ? (openBlock(), createBlock(VRow, { key: 1 }, {
												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(filteredMedia.value, (item) => {
													return openBlock(), createBlock(VCol, {
														key: item.id,
														cols: "12",
														sm: "6",
														md: "4",
														xl: "3"
													}, {
														default: withCtx(() => [createVNode(VCard, { class: ["h-100 media-card", { "media-card--ava": item.is_ava }] }, {
															default: withCtx(() => [createVNode("div", { class: "media-preview" }, [mediaPreview(item) ? (openBlock(), createBlock(VImg, {
																key: 0,
																src: mediaPreview(item),
																height: "180",
																cover: ""
															}, {
																placeholder: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-center fill-height" }, [createVNode(VProgressCircular, { indeterminate: "" })])]),
																_: 1
															}, 8, ["src"])) : (openBlock(), createBlock("div", {
																key: 1,
																class: "media-empty-preview"
															}, [
																createVNode(VIcon, {
																	icon: mediaTypeIcon(item),
																	size: "56",
																	class: "mb-2"
																}, null, 8, ["icon"]),
																createVNode("div", { class: "text-subtitle-2" }, toDisplayString(item.type === "video" ? "Видео ожидает обработки" : "Нет preview"), 1),
																createVNode("div", { class: "text-caption text-medium-emphasis mt-1" }, toDisplayString(item.processing_status || "pending"), 1)
															])), createVNode("div", { class: "media-preview__badges" }, [
																createVNode(VChip, {
																	size: "x-small",
																	variant: "flat"
																}, {
																	default: withCtx(() => [createVNode(VIcon, {
																		icon: mediaTypeIcon(item),
																		size: "14",
																		class: "mr-1"
																	}, null, 8, ["icon"]), createTextVNode(" " + toDisplayString(item.type), 1)]),
																	_: 2
																}, 1024),
																item.is_ava ? (openBlock(), createBlock(VChip, {
																	key: 0,
																	size: "x-small",
																	color: "amber",
																	variant: "flat"
																}, {
																	default: withCtx(() => [createTextVNode(" ava ")]),
																	_: 1
																})) : createCommentVNode("", true),
																item.type === "video" ? (openBlock(), createBlock(VChip, {
																	key: 1,
																	size: "x-small",
																	color: item.processing_status === "done" ? "green" : "orange",
																	variant: "flat"
																}, {
																	default: withCtx(() => [createTextVNode(toDisplayString(item.processing_status || "pending"), 1)]),
																	_: 2
																}, 1032, ["color"])) : createCommentVNode("", true),
																item.is_main_video ? (openBlock(), createBlock(VChip, {
																	key: 2,
																	size: "x-small",
																	color: "deep-purple",
																	variant: "flat"
																}, {
																	default: withCtx(() => [createTextVNode(" main video ")]),
																	_: 1
																})) : createCommentVNode("", true)
															])]), createVNode(VCardText, null, {
																default: withCtx(() => [
																	createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(item.title || item.file_name || item.original_name || `media #${item.id}`), 1),
																	createVNode("div", { class: "text-caption text-medium-emphasis text-truncate" }, toDisplayString(item.folder?.name || "Без папки"), 1),
																	createVNode("div", { class: "text-caption text-medium-emphasis" }, toDisplayString(formatSize(item.size)) + " · " + toDisplayString(formatDate(item.created_at)), 1),
																	item.type === "video" ? (openBlock(), createBlock("div", {
																		key: 0,
																		class: "text-caption text-medium-emphasis"
																	}, toDisplayString(formatDuration(item.duration_seconds)) + " · " + toDisplayString(mediaResolution(item)), 1)) : createCommentVNode("", true),
																	item.alt ? (openBlock(), createBlock("div", {
																		key: 1,
																		class: "text-caption text-medium-emphasis text-truncate mt-1"
																	}, " alt: " + toDisplayString(item.alt), 1)) : createCommentVNode("", true),
																	createVNode("div", { class: "d-flex align-center justify-space-between mt-3" }, [createVNode(VSwitch, {
																		"model-value": !!item.is_published,
																		loading: !!unref(publishing)[item.id],
																		density: "compact",
																		color: "green",
																		inset: "",
																		"hide-details": "",
																		"onUpdate:modelValue": () => handleTogglePublish(item)
																	}, null, 8, [
																		"model-value",
																		"loading",
																		"onUpdate:modelValue"
																	]), createVNode("div", { class: "d-flex align-center" }, [
																		createVNode(VBtn, {
																			icon: "mdi-arrow-up",
																			size: "small",
																			variant: "text",
																			disabled: !canMoveMediaUp(item),
																			loading: !!sortingMedia.value[item.id],
																			onClick: ($event) => moveMedia(item, -1)
																		}, null, 8, [
																			"disabled",
																			"loading",
																			"onClick"
																		]),
																		createVNode(VBtn, {
																			icon: "mdi-arrow-down",
																			size: "small",
																			variant: "text",
																			disabled: !canMoveMediaDown(item),
																			loading: !!sortingMedia.value[item.id],
																			onClick: ($event) => moveMedia(item, 1)
																		}, null, 8, [
																			"disabled",
																			"loading",
																			"onClick"
																		]),
																		item.type === "image" ? (openBlock(), createBlock(VBtn, {
																			key: 0,
																			icon: "mdi-star",
																			size: "small",
																			variant: "text",
																			color: "amber-darken-2",
																			loading: !!unref(settingAva)[item.id],
																			onClick: ($event) => handleSetAva(item)
																		}, null, 8, ["loading", "onClick"])) : createCommentVNode("", true),
																		item.type === "video" ? (openBlock(), createBlock(VBtn, {
																			key: 1,
																			icon: "mdi-cog-play",
																			size: "small",
																			variant: "text",
																			color: "deep-purple",
																			loading: !!unref(processing)[item.id],
																			disabled: ["queued", "processing"].includes(item.processing_status),
																			onClick: ($event) => handleProcessVideo(item)
																		}, null, 8, [
																			"loading",
																			"disabled",
																			"onClick"
																		])) : createCommentVNode("", true),
																		item.type === "video" ? (openBlock(), createBlock(VBtn, {
																			key: 2,
																			size: "small",
																			variant: "text",
																			color: "deep-purple-darken-2",
																			loading: !!unref(settingMainVideo)[item.id],
																			disabled: item.processing_status !== "done" || !item.video_mp4_url,
																			onClick: ($event) => handleSetMainVideo(item)
																		}, {
																			default: withCtx(() => [createVNode("span", { class: "main-video-icon" }, [createVNode(VIcon, {
																				icon: "mdi-video",
																				size: "22"
																			}), createVNode(VIcon, {
																				icon: "mdi-star",
																				size: "11",
																				class: "main-video-icon__star"
																			})])]),
																			_: 1
																		}, 8, [
																			"loading",
																			"disabled",
																			"onClick"
																		])) : createCommentVNode("", true),
																		createVNode(VBtn, {
																			icon: "mdi-open-in-new",
																			size: "small",
																			variant: "text",
																			href: mediaOpenUrl(item),
																			target: "_blank"
																		}, null, 8, ["href"]),
																		createVNode(VMenu, null, {
																			activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
																				icon: "mdi-dots-vertical",
																				size: "small",
																				variant: "text"
																			}), null, 16)]),
																			default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																				default: withCtx(() => [
																					createVNode(VListItem, {
																						"prepend-icon": "mdi-pencil",
																						title: "Редактировать",
																						onClick: ($event) => openEditMedia(item)
																					}, null, 8, ["onClick"]),
																					createVNode(VListItem, {
																						"prepend-icon": "mdi-rename-box",
																						title: "Переименовать файл",
																						onClick: ($event) => openRenameMedia(item)
																					}, null, 8, ["onClick"]),
																					createVNode(VListItem, {
																						"prepend-icon": "mdi-delete",
																						title: "Удалить",
																						"base-color": "red",
																						onClick: ($event) => askDeleteMedia(item)
																					}, null, 8, ["onClick"])
																				]),
																				_: 2
																			}, 1024)]),
																			_: 2
																		}, 1024)
																	])])
																]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1032, ["class"])]),
														_: 2
													}, 1024);
												}), 128))]),
												_: 1
											})) : (openBlock(), createBlock(VAlert, {
												key: 2,
												type: "info",
												variant: "tonal"
											}, {
												default: withCtx(() => [createTextVNode(" Media-файлов пока нет. ")]),
												_: 1
											}))]),
											_: 1
										})]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogUpload.value,
							"onUpdate:modelValue": ($event) => dialogUpload.value = $event,
							width: "760"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Загрузить media`);
													else return [createTextVNode("Загрузить media")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VFileInput, {
															modelValue: uploadForm.file,
															"onUpdate:modelValue": ($event) => uploadForm.file = $event,
															label: "Файл",
															variant: "outlined",
															density: "compact",
															accept: "image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx",
															"prepend-icon": "mdi-paperclip",
															"show-size": "",
															clearable: ""
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VRow, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	_push(ssrRenderComponent(VCol, {
																		cols: "12",
																		md: "6"
																	}, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VSelect, {
																				modelValue: uploadForm.folder_id,
																				"onUpdate:modelValue": ($event) => uploadForm.folder_id = $event,
																				items: folderItems.value,
																				"item-title": "title",
																				"item-value": "value",
																				label: "Папка",
																				variant: "outlined",
																				density: "compact",
																				clearable: ""
																			}, null, _parent, _scopeId));
																			else return [createVNode(VSelect, {
																				modelValue: uploadForm.folder_id,
																				"onUpdate:modelValue": ($event) => uploadForm.folder_id = $event,
																				items: folderItems.value,
																				"item-title": "title",
																				"item-value": "value",
																				label: "Папка",
																				variant: "outlined",
																				density: "compact",
																				clearable: ""
																			}, null, 8, [
																				"modelValue",
																				"onUpdate:modelValue",
																				"items"
																			])];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VCol, {
																		cols: "12",
																		md: "6"
																	}, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VSwitch, {
																				modelValue: uploadForm.is_published,
																				"onUpdate:modelValue": ($event) => uploadForm.is_published = $event,
																				label: "Опубликовать",
																				color: "green",
																				inset: "",
																				"hide-details": ""
																			}, null, _parent, _scopeId));
																			else return [createVNode(VSwitch, {
																				modelValue: uploadForm.is_published,
																				"onUpdate:modelValue": ($event) => uploadForm.is_published = $event,
																				label: "Опубликовать",
																				color: "green",
																				inset: "",
																				"hide-details": ""
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VCol, { cols: "12" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VTextField, {
																				modelValue: uploadForm.title,
																				"onUpdate:modelValue": ($event) => uploadForm.title = $event,
																				label: "Title",
																				variant: "outlined",
																				density: "compact"
																			}, null, _parent, _scopeId));
																			else return [createVNode(VTextField, {
																				modelValue: uploadForm.title,
																				"onUpdate:modelValue": ($event) => uploadForm.title = $event,
																				label: "Title",
																				variant: "outlined",
																				density: "compact"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VCol, { cols: "12" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VTextField, {
																				modelValue: uploadForm.alt,
																				"onUpdate:modelValue": ($event) => uploadForm.alt = $event,
																				label: "Alt",
																				variant: "outlined",
																				density: "compact"
																			}, null, _parent, _scopeId));
																			else return [createVNode(VTextField, {
																				modelValue: uploadForm.alt,
																				"onUpdate:modelValue": ($event) => uploadForm.alt = $event,
																				label: "Alt",
																				variant: "outlined",
																				density: "compact"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VCol, { cols: "12" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VTextarea, {
																				modelValue: uploadForm.caption,
																				"onUpdate:modelValue": ($event) => uploadForm.caption = $event,
																				label: "Caption",
																				variant: "outlined",
																				density: "compact",
																				rows: "3"
																			}, null, _parent, _scopeId));
																			else return [createVNode(VTextarea, {
																				modelValue: uploadForm.caption,
																				"onUpdate:modelValue": ($event) => uploadForm.caption = $event,
																				label: "Caption",
																				variant: "outlined",
																				density: "compact",
																				rows: "3"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																} else return [
																	createVNode(VCol, {
																		cols: "12",
																		md: "6"
																	}, {
																		default: withCtx(() => [createVNode(VSelect, {
																			modelValue: uploadForm.folder_id,
																			"onUpdate:modelValue": ($event) => uploadForm.folder_id = $event,
																			items: folderItems.value,
																			"item-title": "title",
																			"item-value": "value",
																			label: "Папка",
																			variant: "outlined",
																			density: "compact",
																			clearable: ""
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"items"
																		])]),
																		_: 1
																	}),
																	createVNode(VCol, {
																		cols: "12",
																		md: "6"
																	}, {
																		default: withCtx(() => [createVNode(VSwitch, {
																			modelValue: uploadForm.is_published,
																			"onUpdate:modelValue": ($event) => uploadForm.is_published = $event,
																			label: "Опубликовать",
																			color: "green",
																			inset: "",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VCol, { cols: "12" }, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: uploadForm.title,
																			"onUpdate:modelValue": ($event) => uploadForm.title = $event,
																			label: "Title",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VCol, { cols: "12" }, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: uploadForm.alt,
																			"onUpdate:modelValue": ($event) => uploadForm.alt = $event,
																			label: "Alt",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VCol, { cols: "12" }, {
																		default: withCtx(() => [createVNode(VTextarea, {
																			modelValue: uploadForm.caption,
																			"onUpdate:modelValue": ($event) => uploadForm.caption = $event,
																			label: "Caption",
																			variant: "outlined",
																			density: "compact",
																			rows: "3"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})
																];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VFileInput, {
														modelValue: uploadForm.file,
														"onUpdate:modelValue": ($event) => uploadForm.file = $event,
														label: "Файл",
														variant: "outlined",
														density: "compact",
														accept: "image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx",
														"prepend-icon": "mdi-paperclip",
														"show-size": "",
														clearable: ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VRow, null, {
														default: withCtx(() => [
															createVNode(VCol, {
																cols: "12",
																md: "6"
															}, {
																default: withCtx(() => [createVNode(VSelect, {
																	modelValue: uploadForm.folder_id,
																	"onUpdate:modelValue": ($event) => uploadForm.folder_id = $event,
																	items: folderItems.value,
																	"item-title": "title",
																	"item-value": "value",
																	label: "Папка",
																	variant: "outlined",
																	density: "compact",
																	clearable: ""
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items"
																])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "6"
															}, {
																default: withCtx(() => [createVNode(VSwitch, {
																	modelValue: uploadForm.is_published,
																	"onUpdate:modelValue": ($event) => uploadForm.is_published = $event,
																	label: "Опубликовать",
																	color: "green",
																	inset: "",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, { cols: "12" }, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: uploadForm.title,
																	"onUpdate:modelValue": ($event) => uploadForm.title = $event,
																	label: "Title",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, { cols: "12" }, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: uploadForm.alt,
																	"onUpdate:modelValue": ($event) => uploadForm.alt = $event,
																	label: "Alt",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, { cols: "12" }, {
																default: withCtx(() => [createVNode(VTextarea, {
																	modelValue: uploadForm.caption,
																	"onUpdate:modelValue": ($event) => uploadForm.caption = $event,
																	label: "Caption",
																	variant: "outlined",
																	density: "compact",
																	rows: "3"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})
														]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogUpload.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Закрыть `);
																else return [createTextVNode(" Закрыть ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "deep-purple-darken-1",
															variant: "tonal",
															loading: unref(uploading),
															disabled: !extractFile(uploadForm.file),
															onClick: submitUpload
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Загрузить `);
																else return [createTextVNode(" Загрузить ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogUpload.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Закрыть ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "deep-purple-darken-1",
														variant: "tonal",
														loading: unref(uploading),
														disabled: !extractFile(uploadForm.file),
														onClick: submitUpload
													}, {
														default: withCtx(() => [createTextVNode(" Загрузить ")]),
														_: 1
													}, 8, ["loading", "disabled"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Загрузить media")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VFileInput, {
													modelValue: uploadForm.file,
													"onUpdate:modelValue": ($event) => uploadForm.file = $event,
													label: "Файл",
													variant: "outlined",
													density: "compact",
													accept: "image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx",
													"prepend-icon": "mdi-paperclip",
													"show-size": "",
													clearable: ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VRow, null, {
													default: withCtx(() => [
														createVNode(VCol, {
															cols: "12",
															md: "6"
														}, {
															default: withCtx(() => [createVNode(VSelect, {
																modelValue: uploadForm.folder_id,
																"onUpdate:modelValue": ($event) => uploadForm.folder_id = $event,
																items: folderItems.value,
																"item-title": "title",
																"item-value": "value",
																label: "Папка",
																variant: "outlined",
																density: "compact",
																clearable: ""
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items"
															])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "6"
														}, {
															default: withCtx(() => [createVNode(VSwitch, {
																modelValue: uploadForm.is_published,
																"onUpdate:modelValue": ($event) => uploadForm.is_published = $event,
																label: "Опубликовать",
																color: "green",
																inset: "",
																"hide-details": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: uploadForm.title,
																"onUpdate:modelValue": ($event) => uploadForm.title = $event,
																label: "Title",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: uploadForm.alt,
																"onUpdate:modelValue": ($event) => uploadForm.alt = $event,
																label: "Alt",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(VTextarea, {
																modelValue: uploadForm.caption,
																"onUpdate:modelValue": ($event) => uploadForm.caption = $event,
																label: "Caption",
																variant: "outlined",
																density: "compact",
																rows: "3"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})
													]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogUpload.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Закрыть ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "deep-purple-darken-1",
													variant: "tonal",
													loading: unref(uploading),
													disabled: !extractFile(uploadForm.file),
													onClick: submitUpload
												}, {
													default: withCtx(() => [createTextVNode(" Загрузить ")]),
													_: 1
												}, 8, ["loading", "disabled"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, null, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Загрузить media")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [createVNode(VFileInput, {
												modelValue: uploadForm.file,
												"onUpdate:modelValue": ($event) => uploadForm.file = $event,
												label: "Файл",
												variant: "outlined",
												density: "compact",
												accept: "image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx",
												"prepend-icon": "mdi-paperclip",
												"show-size": "",
												clearable: ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VRow, null, {
												default: withCtx(() => [
													createVNode(VCol, {
														cols: "12",
														md: "6"
													}, {
														default: withCtx(() => [createVNode(VSelect, {
															modelValue: uploadForm.folder_id,
															"onUpdate:modelValue": ($event) => uploadForm.folder_id = $event,
															items: folderItems.value,
															"item-title": "title",
															"item-value": "value",
															label: "Папка",
															variant: "outlined",
															density: "compact",
															clearable: ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "6"
													}, {
														default: withCtx(() => [createVNode(VSwitch, {
															modelValue: uploadForm.is_published,
															"onUpdate:modelValue": ($event) => uploadForm.is_published = $event,
															label: "Опубликовать",
															color: "green",
															inset: "",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: uploadForm.title,
															"onUpdate:modelValue": ($event) => uploadForm.title = $event,
															label: "Title",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: uploadForm.alt,
															"onUpdate:modelValue": ($event) => uploadForm.alt = $event,
															label: "Alt",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VTextarea, {
															modelValue: uploadForm.caption,
															"onUpdate:modelValue": ($event) => uploadForm.caption = $event,
															label: "Caption",
															variant: "outlined",
															density: "compact",
															rows: "3"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})
												]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogUpload.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Закрыть ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "deep-purple-darken-1",
												variant: "tonal",
												loading: unref(uploading),
												disabled: !extractFile(uploadForm.file),
												onClick: submitUpload
											}, {
												default: withCtx(() => [createTextVNode(" Загрузить ")]),
												_: 1
											}, 8, ["loading", "disabled"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogFolder.value,
							"onUpdate:modelValue": ($event) => dialogFolder.value = $event,
							width: "620"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(folderForm.id ? "Редактировать папку" : "Создать папку")}`);
													else return [createTextVNode(toDisplayString(folderForm.id ? "Редактировать папку" : "Создать папку"), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VTextField, {
															modelValue: folderForm.name,
															"onUpdate:modelValue": ($event) => folderForm.name = $event,
															label: "Название папки",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VSelect, {
															modelValue: folderForm.parent_id,
															"onUpdate:modelValue": ($event) => folderForm.parent_id = $event,
															items: folderItems.value,
															"item-title": "title",
															"item-value": "value",
															label: "Родительская папка",
															variant: "outlined",
															density: "compact",
															clearable: ""
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextField, {
															modelValue: folderForm.sort_order,
															"onUpdate:modelValue": ($event) => folderForm.sort_order = $event,
															label: "Сортировка",
															type: "number",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VSwitch, {
															modelValue: folderForm.is_archive,
															"onUpdate:modelValue": ($event) => folderForm.is_archive = $event,
															label: "Это архив",
															color: "brown",
															inset: ""
														}, null, _parent, _scopeId));
													} else return [
														createVNode(VTextField, {
															modelValue: folderForm.name,
															"onUpdate:modelValue": ($event) => folderForm.name = $event,
															label: "Название папки",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VSelect, {
															modelValue: folderForm.parent_id,
															"onUpdate:modelValue": ($event) => folderForm.parent_id = $event,
															items: folderItems.value,
															"item-title": "title",
															"item-value": "value",
															label: "Родительская папка",
															variant: "outlined",
															density: "compact",
															clearable: ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														]),
														createVNode(VTextField, {
															modelValue: folderForm.sort_order,
															"onUpdate:modelValue": ($event) => folderForm.sort_order = $event,
															label: "Сортировка",
															type: "number",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VSwitch, {
															modelValue: folderForm.is_archive,
															"onUpdate:modelValue": ($event) => folderForm.is_archive = $event,
															label: "Это архив",
															color: "brown",
															inset: ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])
													];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogFolder.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Закрыть `);
																else return [createTextVNode(" Закрыть ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "deep-purple-darken-1",
															variant: "tonal",
															loading: unref(saving),
															disabled: !folderForm.name,
															onClick: submitFolder
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Сохранить `);
																else return [createTextVNode(" Сохранить ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogFolder.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Закрыть ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "deep-purple-darken-1",
														variant: "tonal",
														loading: unref(saving),
														disabled: !folderForm.name,
														onClick: submitFolder
													}, {
														default: withCtx(() => [createTextVNode(" Сохранить ")]),
														_: 1
													}, 8, ["loading", "disabled"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode(toDisplayString(folderForm.id ? "Редактировать папку" : "Создать папку"), 1)]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [
													createVNode(VTextField, {
														modelValue: folderForm.name,
														"onUpdate:modelValue": ($event) => folderForm.name = $event,
														label: "Название папки",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VSelect, {
														modelValue: folderForm.parent_id,
														"onUpdate:modelValue": ($event) => folderForm.parent_id = $event,
														items: folderItems.value,
														"item-title": "title",
														"item-value": "value",
														label: "Родительская папка",
														variant: "outlined",
														density: "compact",
														clearable: ""
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													]),
													createVNode(VTextField, {
														modelValue: folderForm.sort_order,
														"onUpdate:modelValue": ($event) => folderForm.sort_order = $event,
														label: "Сортировка",
														type: "number",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VSwitch, {
														modelValue: folderForm.is_archive,
														"onUpdate:modelValue": ($event) => folderForm.is_archive = $event,
														label: "Это архив",
														color: "brown",
														inset: ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])
												]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogFolder.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Закрыть ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "deep-purple-darken-1",
													variant: "tonal",
													loading: unref(saving),
													disabled: !folderForm.name,
													onClick: submitFolder
												}, {
													default: withCtx(() => [createTextVNode(" Сохранить ")]),
													_: 1
												}, 8, ["loading", "disabled"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, null, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode(toDisplayString(folderForm.id ? "Редактировать папку" : "Создать папку"), 1)]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [
												createVNode(VTextField, {
													modelValue: folderForm.name,
													"onUpdate:modelValue": ($event) => folderForm.name = $event,
													label: "Название папки",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VSelect, {
													modelValue: folderForm.parent_id,
													"onUpdate:modelValue": ($event) => folderForm.parent_id = $event,
													items: folderItems.value,
													"item-title": "title",
													"item-value": "value",
													label: "Родительская папка",
													variant: "outlined",
													density: "compact",
													clearable: ""
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												]),
												createVNode(VTextField, {
													modelValue: folderForm.sort_order,
													"onUpdate:modelValue": ($event) => folderForm.sort_order = $event,
													label: "Сортировка",
													type: "number",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VSwitch, {
													modelValue: folderForm.is_archive,
													"onUpdate:modelValue": ($event) => folderForm.is_archive = $event,
													label: "Это архив",
													color: "brown",
													inset: ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])
											]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogFolder.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Закрыть ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "deep-purple-darken-1",
												variant: "tonal",
												loading: unref(saving),
												disabled: !folderForm.name,
												onClick: submitFolder
											}, {
												default: withCtx(() => [createTextVNode(" Сохранить ")]),
												_: 1
											}, 8, ["loading", "disabled"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogEdit.value,
							"onUpdate:modelValue": ($event) => dialogEdit.value = $event,
							width: "760"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Редактировать media`);
													else return [createTextVNode("Редактировать media")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VSelect, {
															modelValue: editForm.folder_id,
															"onUpdate:modelValue": ($event) => editForm.folder_id = $event,
															items: folderItems.value,
															"item-title": "title",
															"item-value": "value",
															label: "Папка",
															variant: "outlined",
															density: "compact",
															clearable: ""
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextField, {
															modelValue: editForm.title,
															"onUpdate:modelValue": ($event) => editForm.title = $event,
															label: "Title",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextField, {
															modelValue: editForm.alt,
															"onUpdate:modelValue": ($event) => editForm.alt = $event,
															label: "Alt",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextarea, {
															modelValue: editForm.caption,
															"onUpdate:modelValue": ($event) => editForm.caption = $event,
															label: "Caption",
															variant: "outlined",
															density: "compact",
															rows: "3"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextField, {
															modelValue: editForm.sort_order,
															"onUpdate:modelValue": ($event) => editForm.sort_order = $event,
															label: "Сортировка",
															type: "number",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VSwitch, {
															modelValue: editForm.is_published,
															"onUpdate:modelValue": ($event) => editForm.is_published = $event,
															label: "Опубликовано",
															color: "green",
															inset: ""
														}, null, _parent, _scopeId));
													} else return [
														createVNode(VSelect, {
															modelValue: editForm.folder_id,
															"onUpdate:modelValue": ($event) => editForm.folder_id = $event,
															items: folderItems.value,
															"item-title": "title",
															"item-value": "value",
															label: "Папка",
															variant: "outlined",
															density: "compact",
															clearable: ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														]),
														createVNode(VTextField, {
															modelValue: editForm.title,
															"onUpdate:modelValue": ($event) => editForm.title = $event,
															label: "Title",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextField, {
															modelValue: editForm.alt,
															"onUpdate:modelValue": ($event) => editForm.alt = $event,
															label: "Alt",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextarea, {
															modelValue: editForm.caption,
															"onUpdate:modelValue": ($event) => editForm.caption = $event,
															label: "Caption",
															variant: "outlined",
															density: "compact",
															rows: "3"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextField, {
															modelValue: editForm.sort_order,
															"onUpdate:modelValue": ($event) => editForm.sort_order = $event,
															label: "Сортировка",
															type: "number",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VSwitch, {
															modelValue: editForm.is_published,
															"onUpdate:modelValue": ($event) => editForm.is_published = $event,
															label: "Опубликовано",
															color: "green",
															inset: ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])
													];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogEdit.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Закрыть `);
																else return [createTextVNode(" Закрыть ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "deep-purple-darken-1",
															variant: "tonal",
															loading: unref(saving),
															onClick: submitEditMedia
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Сохранить `);
																else return [createTextVNode(" Сохранить ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogEdit.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Закрыть ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "deep-purple-darken-1",
														variant: "tonal",
														loading: unref(saving),
														onClick: submitEditMedia
													}, {
														default: withCtx(() => [createTextVNode(" Сохранить ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Редактировать media")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [
													createVNode(VSelect, {
														modelValue: editForm.folder_id,
														"onUpdate:modelValue": ($event) => editForm.folder_id = $event,
														items: folderItems.value,
														"item-title": "title",
														"item-value": "value",
														label: "Папка",
														variant: "outlined",
														density: "compact",
														clearable: ""
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													]),
													createVNode(VTextField, {
														modelValue: editForm.title,
														"onUpdate:modelValue": ($event) => editForm.title = $event,
														label: "Title",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextField, {
														modelValue: editForm.alt,
														"onUpdate:modelValue": ($event) => editForm.alt = $event,
														label: "Alt",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextarea, {
														modelValue: editForm.caption,
														"onUpdate:modelValue": ($event) => editForm.caption = $event,
														label: "Caption",
														variant: "outlined",
														density: "compact",
														rows: "3"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextField, {
														modelValue: editForm.sort_order,
														"onUpdate:modelValue": ($event) => editForm.sort_order = $event,
														label: "Сортировка",
														type: "number",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VSwitch, {
														modelValue: editForm.is_published,
														"onUpdate:modelValue": ($event) => editForm.is_published = $event,
														label: "Опубликовано",
														color: "green",
														inset: ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])
												]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogEdit.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Закрыть ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "deep-purple-darken-1",
													variant: "tonal",
													loading: unref(saving),
													onClick: submitEditMedia
												}, {
													default: withCtx(() => [createTextVNode(" Сохранить ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, null, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Редактировать media")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [
												createVNode(VSelect, {
													modelValue: editForm.folder_id,
													"onUpdate:modelValue": ($event) => editForm.folder_id = $event,
													items: folderItems.value,
													"item-title": "title",
													"item-value": "value",
													label: "Папка",
													variant: "outlined",
													density: "compact",
													clearable: ""
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												]),
												createVNode(VTextField, {
													modelValue: editForm.title,
													"onUpdate:modelValue": ($event) => editForm.title = $event,
													label: "Title",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextField, {
													modelValue: editForm.alt,
													"onUpdate:modelValue": ($event) => editForm.alt = $event,
													label: "Alt",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextarea, {
													modelValue: editForm.caption,
													"onUpdate:modelValue": ($event) => editForm.caption = $event,
													label: "Caption",
													variant: "outlined",
													density: "compact",
													rows: "3"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextField, {
													modelValue: editForm.sort_order,
													"onUpdate:modelValue": ($event) => editForm.sort_order = $event,
													label: "Сортировка",
													type: "number",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VSwitch, {
													modelValue: editForm.is_published,
													"onUpdate:modelValue": ($event) => editForm.is_published = $event,
													label: "Опубликовано",
													color: "green",
													inset: ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])
											]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogEdit.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Закрыть ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "deep-purple-darken-1",
												variant: "tonal",
												loading: unref(saving),
												onClick: submitEditMedia
											}, {
												default: withCtx(() => [createTextVNode(" Сохранить ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogRename.value,
							"onUpdate:modelValue": ($event) => dialogRename.value = $event,
							width: "560"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Переименовать файл`);
													else return [createTextVNode("Переименовать файл")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VTextField, {
														modelValue: renameForm.file_name,
														"onUpdate:modelValue": ($event) => renameForm.file_name = $event,
														label: "Новое имя файла",
														variant: "outlined",
														density: "compact",
														hint: "Расширение можно оставить или не писать. Сервер сохранит исходное расширение.",
														"persistent-hint": ""
													}, null, _parent, _scopeId));
													else return [createVNode(VTextField, {
														modelValue: renameForm.file_name,
														"onUpdate:modelValue": ($event) => renameForm.file_name = $event,
														label: "Новое имя файла",
														variant: "outlined",
														density: "compact",
														hint: "Расширение можно оставить или не писать. Сервер сохранит исходное расширение.",
														"persistent-hint": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogRename.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Закрыть `);
																else return [createTextVNode(" Закрыть ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "deep-purple-darken-1",
															variant: "tonal",
															loading: unref(saving),
															disabled: !renameForm.file_name,
															onClick: submitRenameMedia
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Переименовать `);
																else return [createTextVNode(" Переименовать ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogRename.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Закрыть ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "deep-purple-darken-1",
														variant: "tonal",
														loading: unref(saving),
														disabled: !renameForm.file_name,
														onClick: submitRenameMedia
													}, {
														default: withCtx(() => [createTextVNode(" Переименовать ")]),
														_: 1
													}, 8, ["loading", "disabled"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Переименовать файл")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: renameForm.file_name,
													"onUpdate:modelValue": ($event) => renameForm.file_name = $event,
													label: "Новое имя файла",
													variant: "outlined",
													density: "compact",
													hint: "Расширение можно оставить или не писать. Сервер сохранит исходное расширение.",
													"persistent-hint": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogRename.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Закрыть ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "deep-purple-darken-1",
													variant: "tonal",
													loading: unref(saving),
													disabled: !renameForm.file_name,
													onClick: submitRenameMedia
												}, {
													default: withCtx(() => [createTextVNode(" Переименовать ")]),
													_: 1
												}, 8, ["loading", "disabled"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, null, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Переименовать файл")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: renameForm.file_name,
												"onUpdate:modelValue": ($event) => renameForm.file_name = $event,
												label: "Новое имя файла",
												variant: "outlined",
												density: "compact",
												hint: "Расширение можно оставить или не писать. Сервер сохранит исходное расширение.",
												"persistent-hint": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogRename.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Закрыть ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "deep-purple-darken-1",
												variant: "tonal",
												loading: unref(saving),
												disabled: !renameForm.file_name,
												onClick: submitRenameMedia
											}, {
												default: withCtx(() => [createTextVNode(" Переименовать ")]),
												_: 1
											}, 8, ["loading", "disabled"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogDeleteMedia.value,
							"onUpdate:modelValue": ($event) => dialogDeleteMedia.value = $event,
							width: "520"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Удалить media?`);
													else return [createTextVNode("Удалить media?")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Удалить файл: <strong data-v-c44fce58${_scopeId}>${ssrInterpolate(selectedMedia.value?.file_name || selectedMedia.value?.original_name)}</strong> ? `);
													else return [
														createTextVNode(" Удалить файл: "),
														createVNode("strong", null, toDisplayString(selectedMedia.value?.file_name || selectedMedia.value?.original_name), 1),
														createTextVNode(" ? ")
													];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogDeleteMedia.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Отмена `);
																else return [createTextVNode(" Отмена ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "red",
															variant: "tonal",
															loading: selectedMedia.value?.id ? !!unref(deleting)[`media-${selectedMedia.value.id}`] : false,
															onClick: confirmDeleteMedia
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Удалить `);
																else return [createTextVNode(" Удалить ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogDeleteMedia.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Отмена ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "red",
														variant: "tonal",
														loading: selectedMedia.value?.id ? !!unref(deleting)[`media-${selectedMedia.value.id}`] : false,
														onClick: confirmDeleteMedia
													}, {
														default: withCtx(() => [createTextVNode(" Удалить ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Удалить media?")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [
													createTextVNode(" Удалить файл: "),
													createVNode("strong", null, toDisplayString(selectedMedia.value?.file_name || selectedMedia.value?.original_name), 1),
													createTextVNode(" ? ")
												]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogDeleteMedia.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Отмена ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "red",
													variant: "tonal",
													loading: selectedMedia.value?.id ? !!unref(deleting)[`media-${selectedMedia.value.id}`] : false,
													onClick: confirmDeleteMedia
												}, {
													default: withCtx(() => [createTextVNode(" Удалить ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, null, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Удалить media?")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [
												createTextVNode(" Удалить файл: "),
												createVNode("strong", null, toDisplayString(selectedMedia.value?.file_name || selectedMedia.value?.original_name), 1),
												createTextVNode(" ? ")
											]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogDeleteMedia.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Отмена ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "red",
												variant: "tonal",
												loading: selectedMedia.value?.id ? !!unref(deleting)[`media-${selectedMedia.value.id}`] : false,
												onClick: confirmDeleteMedia
											}, {
												default: withCtx(() => [createTextVNode(" Удалить ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogDeleteFolder.value,
							"onUpdate:modelValue": ($event) => dialogDeleteFolder.value = $event,
							width: "520"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Удалить папку?`);
													else return [createTextVNode("Удалить папку?")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Удалить папку: <strong data-v-c44fce58${_scopeId}>${ssrInterpolate(selectedFolder.value?.name)}</strong> ? <div class="text-caption text-medium-emphasis mt-2" data-v-c44fce58${_scopeId}> Папку можно удалить только если в ней нет файлов и вложенных папок. </div>`);
													else return [
														createTextVNode(" Удалить папку: "),
														createVNode("strong", null, toDisplayString(selectedFolder.value?.name), 1),
														createTextVNode(" ? "),
														createVNode("div", { class: "text-caption text-medium-emphasis mt-2" }, " Папку можно удалить только если в ней нет файлов и вложенных папок. ")
													];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogDeleteFolder.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Отмена `);
																else return [createTextVNode(" Отмена ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "red",
															variant: "tonal",
															loading: selectedFolder.value?.id ? !!unref(deleting)[`folder-${selectedFolder.value.id}`] : false,
															onClick: confirmDeleteFolder
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Удалить `);
																else return [createTextVNode(" Удалить ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogDeleteFolder.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Отмена ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "red",
														variant: "tonal",
														loading: selectedFolder.value?.id ? !!unref(deleting)[`folder-${selectedFolder.value.id}`] : false,
														onClick: confirmDeleteFolder
													}, {
														default: withCtx(() => [createTextVNode(" Удалить ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Удалить папку?")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [
													createTextVNode(" Удалить папку: "),
													createVNode("strong", null, toDisplayString(selectedFolder.value?.name), 1),
													createTextVNode(" ? "),
													createVNode("div", { class: "text-caption text-medium-emphasis mt-2" }, " Папку можно удалить только если в ней нет файлов и вложенных папок. ")
												]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogDeleteFolder.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Отмена ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "red",
													variant: "tonal",
													loading: selectedFolder.value?.id ? !!unref(deleting)[`folder-${selectedFolder.value.id}`] : false,
													onClick: confirmDeleteFolder
												}, {
													default: withCtx(() => [createTextVNode(" Удалить ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, null, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Удалить папку?")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [
												createTextVNode(" Удалить папку: "),
												createVNode("strong", null, toDisplayString(selectedFolder.value?.name), 1),
												createTextVNode(" ? "),
												createVNode("div", { class: "text-caption text-medium-emphasis mt-2" }, " Папку можно удалить только если в ней нет файлов и вложенных папок. ")
											]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogDeleteFolder.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Отмена ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "red",
												variant: "tonal",
												loading: selectedFolder.value?.id ? !!unref(deleting)[`folder-${selectedFolder.value.id}`] : false,
												onClick: confirmDeleteFolder
											}, {
												default: withCtx(() => [createTextVNode(" Удалить ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx(() => [createVNode("span", null, "Media товара"), createVNode("div", { class: "d-flex align-center ga-2" }, [
								createVNode(VBtn, {
									icon: "mdi-refresh",
									variant: "text",
									loading: unref(loading) || unref(loadingFolders),
									onClick: reload
								}, null, 8, ["loading"]),
								createVNode(VBtn, {
									color: "blue-grey",
									variant: "tonal",
									"prepend-icon": "mdi-folder-plus",
									onClick: ($event) => openCreateFolder(false)
								}, {
									default: withCtx(() => [createTextVNode(" Папка ")]),
									_: 1
								}, 8, ["onClick"]),
								createVNode(VBtn, {
									color: "brown",
									variant: "tonal",
									"prepend-icon": "mdi-archive-plus",
									onClick: ($event) => openCreateFolder(true)
								}, {
									default: withCtx(() => [createTextVNode(" Архив ")]),
									_: 1
								}, 8, ["onClick"]),
								createVNode(VBtn, {
									color: "deep-purple-darken-1",
									variant: "tonal",
									"prepend-icon": "mdi-cloud-upload",
									onClick: openUpload
								}, {
									default: withCtx(() => [createTextVNode(" Загрузить ")]),
									_: 1
								})
							])]),
							_: 1
						}),
						createVNode(VCardText, null, {
							default: withCtx(() => [
								errorMessage.value ? (openBlock(), createBlock(VAlert, {
									key: 0,
									type: "error",
									variant: "tonal",
									class: "mb-4",
									closable: "",
									"onClick:close": ($event) => errorMessage.value = ""
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(errorMessage.value), 1)]),
									_: 1
								}, 8, ["onClick:close"])) : createCommentVNode("", true),
								createVNode(VAlert, {
									type: "info",
									variant: "tonal",
									class: "mb-4"
								}, {
									default: withCtx(() => [
										createTextVNode(" Фото сразу создают thumbnail. Видео пока загружается как original со статусом "),
										createVNode("strong", null, "pending"),
										createTextVNode(". Обработку видео через FFmpeg подключим следующим шагом. ")
									]),
									_: 1
								}),
								createVNode(VRow, { class: "mb-2" }, {
									default: withCtx(() => [
										createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: search.value,
												"onUpdate:modelValue": ($event) => search.value = $event,
												label: "Поиск media",
												variant: "solo-inverted",
												density: "compact",
												clearable: "",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "2"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: filterType.value,
												"onUpdate:modelValue": ($event) => filterType.value = $event,
												items: [
													{
														title: "Все типы",
														value: "all"
													},
													{
														title: "Фото",
														value: "image"
													},
													{
														title: "Видео",
														value: "video"
													},
													{
														title: "Документы",
														value: "document"
													}
												],
												label: "Тип",
												variant: "solo-inverted",
												density: "compact",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "3"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: filterFolderId.value,
												"onUpdate:modelValue": ($event) => filterFolderId.value = $event,
												items: filterFolderItems.value,
												label: "Папка",
												variant: "solo-inverted",
												density: "compact",
												"hide-details": ""
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items"
											])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											md: "3"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: filterPublished.value,
												"onUpdate:modelValue": ($event) => filterPublished.value = $event,
												items: [
													{
														title: "Все",
														value: "all"
													},
													{
														title: "Опубликованные",
														value: "published"
													},
													{
														title: "Скрытые",
														value: "hidden"
													}
												],
												label: "Публикация",
												variant: "solo-inverted",
												density: "compact",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})
									]),
									_: 1
								}),
								createVNode(VRow, null, {
									default: withCtx(() => [createVNode(VCol, {
										cols: "12",
										lg: "3"
									}, {
										default: withCtx(() => [createVNode(VCard, { variant: "tonal" }, {
											default: withCtx(() => [createVNode(VCardTitle, { class: "text-subtitle-1" }, {
												default: withCtx(() => [createTextVNode(" Папки ")]),
												_: 1
											}), createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VList, {
													density: "compact",
													lines: "two"
												}, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(folders), (folder) => {
														return openBlock(), createBlock(VListItem, { key: folder.id }, {
															prepend: withCtx(() => [createVNode(VIcon, { icon: folder.is_archive ? "mdi-archive" : "mdi-folder" }, null, 8, ["icon"])]),
															append: withCtx(() => [createVNode(VBtn, {
																icon: "mdi-pencil",
																size: "x-small",
																variant: "text",
																onClick: ($event) => openEditFolder(folder)
															}, null, 8, ["onClick"]), createVNode(VBtn, {
																icon: "mdi-delete",
																size: "x-small",
																variant: "text",
																color: "red",
																loading: !!unref(deleting)[`folder-${folder.id}`],
																onClick: ($event) => askDeleteFolder(folder)
															}, null, 8, ["loading", "onClick"])]),
															default: withCtx(() => [createVNode(VListItemTitle, null, {
																default: withCtx(() => [createTextVNode(toDisplayString(folder.name), 1)]),
																_: 2
															}, 1024), createVNode(VListItemSubtitle, null, {
																default: withCtx(() => [createTextVNode(toDisplayString(folder.path), 1)]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024);
													}), 128)), !unref(folders).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
														default: withCtx(() => [createVNode(VListItemTitle, { class: "text-medium-emphasis" }, {
															default: withCtx(() => [createTextVNode(" Папок пока нет. ")]),
															_: 1
														})]),
														_: 1
													})) : createCommentVNode("", true)]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									}), createVNode(VCol, {
										cols: "12",
										lg: "9"
									}, {
										default: withCtx(() => [unref(loading) ? (openBlock(), createBlock(VProgressLinear, {
											key: 0,
											indeterminate: "",
											class: "mb-3"
										})) : createCommentVNode("", true), filteredMedia.value.length ? (openBlock(), createBlock(VRow, { key: 1 }, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(filteredMedia.value, (item) => {
												return openBlock(), createBlock(VCol, {
													key: item.id,
													cols: "12",
													sm: "6",
													md: "4",
													xl: "3"
												}, {
													default: withCtx(() => [createVNode(VCard, { class: ["h-100 media-card", { "media-card--ava": item.is_ava }] }, {
														default: withCtx(() => [createVNode("div", { class: "media-preview" }, [mediaPreview(item) ? (openBlock(), createBlock(VImg, {
															key: 0,
															src: mediaPreview(item),
															height: "180",
															cover: ""
														}, {
															placeholder: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-center fill-height" }, [createVNode(VProgressCircular, { indeterminate: "" })])]),
															_: 1
														}, 8, ["src"])) : (openBlock(), createBlock("div", {
															key: 1,
															class: "media-empty-preview"
														}, [
															createVNode(VIcon, {
																icon: mediaTypeIcon(item),
																size: "56",
																class: "mb-2"
															}, null, 8, ["icon"]),
															createVNode("div", { class: "text-subtitle-2" }, toDisplayString(item.type === "video" ? "Видео ожидает обработки" : "Нет preview"), 1),
															createVNode("div", { class: "text-caption text-medium-emphasis mt-1" }, toDisplayString(item.processing_status || "pending"), 1)
														])), createVNode("div", { class: "media-preview__badges" }, [
															createVNode(VChip, {
																size: "x-small",
																variant: "flat"
															}, {
																default: withCtx(() => [createVNode(VIcon, {
																	icon: mediaTypeIcon(item),
																	size: "14",
																	class: "mr-1"
																}, null, 8, ["icon"]), createTextVNode(" " + toDisplayString(item.type), 1)]),
																_: 2
															}, 1024),
															item.is_ava ? (openBlock(), createBlock(VChip, {
																key: 0,
																size: "x-small",
																color: "amber",
																variant: "flat"
															}, {
																default: withCtx(() => [createTextVNode(" ava ")]),
																_: 1
															})) : createCommentVNode("", true),
															item.type === "video" ? (openBlock(), createBlock(VChip, {
																key: 1,
																size: "x-small",
																color: item.processing_status === "done" ? "green" : "orange",
																variant: "flat"
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(item.processing_status || "pending"), 1)]),
																_: 2
															}, 1032, ["color"])) : createCommentVNode("", true),
															item.is_main_video ? (openBlock(), createBlock(VChip, {
																key: 2,
																size: "x-small",
																color: "deep-purple",
																variant: "flat"
															}, {
																default: withCtx(() => [createTextVNode(" main video ")]),
																_: 1
															})) : createCommentVNode("", true)
														])]), createVNode(VCardText, null, {
															default: withCtx(() => [
																createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(item.title || item.file_name || item.original_name || `media #${item.id}`), 1),
																createVNode("div", { class: "text-caption text-medium-emphasis text-truncate" }, toDisplayString(item.folder?.name || "Без папки"), 1),
																createVNode("div", { class: "text-caption text-medium-emphasis" }, toDisplayString(formatSize(item.size)) + " · " + toDisplayString(formatDate(item.created_at)), 1),
																item.type === "video" ? (openBlock(), createBlock("div", {
																	key: 0,
																	class: "text-caption text-medium-emphasis"
																}, toDisplayString(formatDuration(item.duration_seconds)) + " · " + toDisplayString(mediaResolution(item)), 1)) : createCommentVNode("", true),
																item.alt ? (openBlock(), createBlock("div", {
																	key: 1,
																	class: "text-caption text-medium-emphasis text-truncate mt-1"
																}, " alt: " + toDisplayString(item.alt), 1)) : createCommentVNode("", true),
																createVNode("div", { class: "d-flex align-center justify-space-between mt-3" }, [createVNode(VSwitch, {
																	"model-value": !!item.is_published,
																	loading: !!unref(publishing)[item.id],
																	density: "compact",
																	color: "green",
																	inset: "",
																	"hide-details": "",
																	"onUpdate:modelValue": () => handleTogglePublish(item)
																}, null, 8, [
																	"model-value",
																	"loading",
																	"onUpdate:modelValue"
																]), createVNode("div", { class: "d-flex align-center" }, [
																	createVNode(VBtn, {
																		icon: "mdi-arrow-up",
																		size: "small",
																		variant: "text",
																		disabled: !canMoveMediaUp(item),
																		loading: !!sortingMedia.value[item.id],
																		onClick: ($event) => moveMedia(item, -1)
																	}, null, 8, [
																		"disabled",
																		"loading",
																		"onClick"
																	]),
																	createVNode(VBtn, {
																		icon: "mdi-arrow-down",
																		size: "small",
																		variant: "text",
																		disabled: !canMoveMediaDown(item),
																		loading: !!sortingMedia.value[item.id],
																		onClick: ($event) => moveMedia(item, 1)
																	}, null, 8, [
																		"disabled",
																		"loading",
																		"onClick"
																	]),
																	item.type === "image" ? (openBlock(), createBlock(VBtn, {
																		key: 0,
																		icon: "mdi-star",
																		size: "small",
																		variant: "text",
																		color: "amber-darken-2",
																		loading: !!unref(settingAva)[item.id],
																		onClick: ($event) => handleSetAva(item)
																	}, null, 8, ["loading", "onClick"])) : createCommentVNode("", true),
																	item.type === "video" ? (openBlock(), createBlock(VBtn, {
																		key: 1,
																		icon: "mdi-cog-play",
																		size: "small",
																		variant: "text",
																		color: "deep-purple",
																		loading: !!unref(processing)[item.id],
																		disabled: ["queued", "processing"].includes(item.processing_status),
																		onClick: ($event) => handleProcessVideo(item)
																	}, null, 8, [
																		"loading",
																		"disabled",
																		"onClick"
																	])) : createCommentVNode("", true),
																	item.type === "video" ? (openBlock(), createBlock(VBtn, {
																		key: 2,
																		size: "small",
																		variant: "text",
																		color: "deep-purple-darken-2",
																		loading: !!unref(settingMainVideo)[item.id],
																		disabled: item.processing_status !== "done" || !item.video_mp4_url,
																		onClick: ($event) => handleSetMainVideo(item)
																	}, {
																		default: withCtx(() => [createVNode("span", { class: "main-video-icon" }, [createVNode(VIcon, {
																			icon: "mdi-video",
																			size: "22"
																		}), createVNode(VIcon, {
																			icon: "mdi-star",
																			size: "11",
																			class: "main-video-icon__star"
																		})])]),
																		_: 1
																	}, 8, [
																		"loading",
																		"disabled",
																		"onClick"
																	])) : createCommentVNode("", true),
																	createVNode(VBtn, {
																		icon: "mdi-open-in-new",
																		size: "small",
																		variant: "text",
																		href: mediaOpenUrl(item),
																		target: "_blank"
																	}, null, 8, ["href"]),
																	createVNode(VMenu, null, {
																		activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
																			icon: "mdi-dots-vertical",
																			size: "small",
																			variant: "text"
																		}), null, 16)]),
																		default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																			default: withCtx(() => [
																				createVNode(VListItem, {
																					"prepend-icon": "mdi-pencil",
																					title: "Редактировать",
																					onClick: ($event) => openEditMedia(item)
																				}, null, 8, ["onClick"]),
																				createVNode(VListItem, {
																					"prepend-icon": "mdi-rename-box",
																					title: "Переименовать файл",
																					onClick: ($event) => openRenameMedia(item)
																				}, null, 8, ["onClick"]),
																				createVNode(VListItem, {
																					"prepend-icon": "mdi-delete",
																					title: "Удалить",
																					"base-color": "red",
																					onClick: ($event) => askDeleteMedia(item)
																				}, null, 8, ["onClick"])
																			]),
																			_: 2
																		}, 1024)]),
																		_: 2
																	}, 1024)
																])])
															]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1032, ["class"])]),
													_: 2
												}, 1024);
											}), 128))]),
											_: 1
										})) : (openBlock(), createBlock(VAlert, {
											key: 2,
											type: "info",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(" Media-файлов пока нет. ")]),
											_: 1
										}))]),
										_: 1
									})]),
									_: 1
								})
							]),
							_: 1
						}),
						createVNode(VDialog, {
							modelValue: dialogUpload.value,
							"onUpdate:modelValue": ($event) => dialogUpload.value = $event,
							width: "760"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Загрузить media")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VFileInput, {
											modelValue: uploadForm.file,
											"onUpdate:modelValue": ($event) => uploadForm.file = $event,
											label: "Файл",
											variant: "outlined",
											density: "compact",
											accept: "image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx",
											"prepend-icon": "mdi-paperclip",
											"show-size": "",
											clearable: ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VRow, null, {
											default: withCtx(() => [
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: uploadForm.folder_id,
														"onUpdate:modelValue": ($event) => uploadForm.folder_id = $event,
														items: folderItems.value,
														"item-title": "title",
														"item-value": "value",
														label: "Папка",
														variant: "outlined",
														density: "compact",
														clearable: ""
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VSwitch, {
														modelValue: uploadForm.is_published,
														"onUpdate:modelValue": ($event) => uploadForm.is_published = $event,
														label: "Опубликовать",
														color: "green",
														inset: "",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: uploadForm.title,
														"onUpdate:modelValue": ($event) => uploadForm.title = $event,
														label: "Title",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: uploadForm.alt,
														"onUpdate:modelValue": ($event) => uploadForm.alt = $event,
														label: "Alt",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: uploadForm.caption,
														"onUpdate:modelValue": ($event) => uploadForm.caption = $event,
														label: "Caption",
														variant: "outlined",
														density: "compact",
														rows: "3"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})
											]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogUpload.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Закрыть ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "deep-purple-darken-1",
											variant: "tonal",
											loading: unref(uploading),
											disabled: !extractFile(uploadForm.file),
											onClick: submitUpload
										}, {
											default: withCtx(() => [createTextVNode(" Загрузить ")]),
											_: 1
										}, 8, ["loading", "disabled"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VDialog, {
							modelValue: dialogFolder.value,
							"onUpdate:modelValue": ($event) => dialogFolder.value = $event,
							width: "620"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode(toDisplayString(folderForm.id ? "Редактировать папку" : "Создать папку"), 1)]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [
											createVNode(VTextField, {
												modelValue: folderForm.name,
												"onUpdate:modelValue": ($event) => folderForm.name = $event,
												label: "Название папки",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VSelect, {
												modelValue: folderForm.parent_id,
												"onUpdate:modelValue": ($event) => folderForm.parent_id = $event,
												items: folderItems.value,
												"item-title": "title",
												"item-value": "value",
												label: "Родительская папка",
												variant: "outlined",
												density: "compact",
												clearable: ""
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items"
											]),
											createVNode(VTextField, {
												modelValue: folderForm.sort_order,
												"onUpdate:modelValue": ($event) => folderForm.sort_order = $event,
												label: "Сортировка",
												type: "number",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VSwitch, {
												modelValue: folderForm.is_archive,
												"onUpdate:modelValue": ($event) => folderForm.is_archive = $event,
												label: "Это архив",
												color: "brown",
												inset: ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])
										]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogFolder.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Закрыть ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "deep-purple-darken-1",
											variant: "tonal",
											loading: unref(saving),
											disabled: !folderForm.name,
											onClick: submitFolder
										}, {
											default: withCtx(() => [createTextVNode(" Сохранить ")]),
											_: 1
										}, 8, ["loading", "disabled"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VDialog, {
							modelValue: dialogEdit.value,
							"onUpdate:modelValue": ($event) => dialogEdit.value = $event,
							width: "760"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Редактировать media")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [
											createVNode(VSelect, {
												modelValue: editForm.folder_id,
												"onUpdate:modelValue": ($event) => editForm.folder_id = $event,
												items: folderItems.value,
												"item-title": "title",
												"item-value": "value",
												label: "Папка",
												variant: "outlined",
												density: "compact",
												clearable: ""
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items"
											]),
											createVNode(VTextField, {
												modelValue: editForm.title,
												"onUpdate:modelValue": ($event) => editForm.title = $event,
												label: "Title",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VTextField, {
												modelValue: editForm.alt,
												"onUpdate:modelValue": ($event) => editForm.alt = $event,
												label: "Alt",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VTextarea, {
												modelValue: editForm.caption,
												"onUpdate:modelValue": ($event) => editForm.caption = $event,
												label: "Caption",
												variant: "outlined",
												density: "compact",
												rows: "3"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VTextField, {
												modelValue: editForm.sort_order,
												"onUpdate:modelValue": ($event) => editForm.sort_order = $event,
												label: "Сортировка",
												type: "number",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VSwitch, {
												modelValue: editForm.is_published,
												"onUpdate:modelValue": ($event) => editForm.is_published = $event,
												label: "Опубликовано",
												color: "green",
												inset: ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])
										]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogEdit.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Закрыть ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "deep-purple-darken-1",
											variant: "tonal",
											loading: unref(saving),
											onClick: submitEditMedia
										}, {
											default: withCtx(() => [createTextVNode(" Сохранить ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VDialog, {
							modelValue: dialogRename.value,
							"onUpdate:modelValue": ($event) => dialogRename.value = $event,
							width: "560"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Переименовать файл")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: renameForm.file_name,
											"onUpdate:modelValue": ($event) => renameForm.file_name = $event,
											label: "Новое имя файла",
											variant: "outlined",
											density: "compact",
											hint: "Расширение можно оставить или не писать. Сервер сохранит исходное расширение.",
											"persistent-hint": ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogRename.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Закрыть ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "deep-purple-darken-1",
											variant: "tonal",
											loading: unref(saving),
											disabled: !renameForm.file_name,
											onClick: submitRenameMedia
										}, {
											default: withCtx(() => [createTextVNode(" Переименовать ")]),
											_: 1
										}, 8, ["loading", "disabled"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VDialog, {
							modelValue: dialogDeleteMedia.value,
							"onUpdate:modelValue": ($event) => dialogDeleteMedia.value = $event,
							width: "520"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Удалить media?")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [
											createTextVNode(" Удалить файл: "),
											createVNode("strong", null, toDisplayString(selectedMedia.value?.file_name || selectedMedia.value?.original_name), 1),
											createTextVNode(" ? ")
										]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogDeleteMedia.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Отмена ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "red",
											variant: "tonal",
											loading: selectedMedia.value?.id ? !!unref(deleting)[`media-${selectedMedia.value.id}`] : false,
											onClick: confirmDeleteMedia
										}, {
											default: withCtx(() => [createTextVNode(" Удалить ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VDialog, {
							modelValue: dialogDeleteFolder.value,
							"onUpdate:modelValue": ($event) => dialogDeleteFolder.value = $event,
							width: "520"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Удалить папку?")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [
											createTextVNode(" Удалить папку: "),
											createVNode("strong", null, toDisplayString(selectedFolder.value?.name), 1),
											createTextVNode(" ? "),
											createVNode("div", { class: "text-caption text-medium-emphasis mt-2" }, " Папку можно удалить только если в ней нет файлов и вложенных папок. ")
										]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogDeleteFolder.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Отмена ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "red",
											variant: "tonal",
											loading: selectedFolder.value?.id ? !!unref(deleting)[`folder-${selectedFolder.value.id}`] : false,
											onClick: confirmDeleteFolder
										}, {
											default: withCtx(() => [createTextVNode(" Удалить ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"])
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Goods/GoodMediaTab.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
var GoodMediaTab_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main$1, [["__scopeId", "data-v-c44fce58"]]);
//#endregion
//#region resources/js/Pages/Ameise/Good.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$7 }, {
	__name: "Good",
	__ssrInlineRender: true,
	props: { good: {
		type: Object,
		required: true
	} },
	setup(__props) {
		const props = __props;
		const date = useDate();
		const pageLoading = ref(true);
		const pageError = ref(null);
		const activeTab = ref("overview");
		const goodData = ref(null);
		const currencies = ref([]);
		const measures = ref([]);
		const units = ref([]);
		const showFormAddPrice = ref(false);
		const dialogFormQuotation = ref(false);
		const currentVatRate = computed(() => {
			return goodData.value?.vat_rate || goodData.value?.vatRate || null;
		});
		const defaultVatRate = computed(() => {
			return Number(currentVatRate.value?.rate ?? 20);
		});
		const goodBoxWeight = computed(() => {
			return Number(goodData.value?.denominator || 1);
		});
		const headerSales = [
			{
				key: "date",
				title: "Дата",
				sortable: true,
				align: "start",
				width: "25%"
			},
			{
				key: "entity.name",
				title: "Entity",
				sortable: true,
				align: "start",
				width: "38%"
			},
			{
				key: "pivot.quantity",
				title: "Кол-во",
				sortable: true,
				align: "center",
				width: "10%"
			},
			{
				key: "pivot.price",
				title: "Цена",
				sortable: true,
				align: "start"
			}
		];
		const headerQuotations = [
			{
				key: "unit.name",
				title: "Unit",
				align: "start",
				sortable: true
			},
			{
				key: "created_at",
				title: "Дата",
				align: "start",
				sortable: true
			},
			{
				key: "price",
				title: "Цена",
				align: "start",
				sortable: true
			},
			{
				key: "measure.name",
				title: "Measure",
				align: "start",
				sortable: true
			},
			{
				key: "denominator",
				title: "Делитель",
				align: "start",
				sortable: true
			}
		];
		const headerPurchases = [
			{
				key: "date",
				title: "Дата",
				sortable: true,
				width: "120px"
			},
			{
				key: "entity.name",
				title: "Поставщик / Entity",
				sortable: true
			},
			{
				key: "pivot.quantity",
				title: "Кол-во",
				sortable: true,
				width: "110px"
			},
			{
				key: "pivot.price",
				title: "Цена",
				sortable: true,
				width: "130px"
			},
			{
				key: "pivot.total",
				title: "Сумма",
				sortable: true,
				width: "130px"
			}
		];
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
				maximumFractionDigits: 2
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
					minute: "2-digit"
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
		async function fetchGood() {
			goodData.value = (await axios.get(route("good.fetch", props.good.id))).data;
		}
		async function fetchCurrencies() {
			const response = await axios.get(route("currencies.index"));
			currencies.value = Array.isArray(response.data) ? response.data : response.data.data || [];
		}
		async function fetchMeasures() {
			const response = await axios.get(route("measures.index"));
			measures.value = Array.isArray(response.data) ? response.data : response.data.data || [];
		}
		async function fetchUnits() {
			const response = await axios.get(route("units.index"));
			units.value = Array.isArray(response.data) ? response.data : response.data.data || [];
		}
		async function loadPageData() {
			pageLoading.value = true;
			pageError.value = null;
			try {
				await Promise.all([
					fetchGood(),
					fetchCurrencies(),
					fetchMeasures(),
					fetchUnits()
				]);
			} catch (error) {
				console.error(error);
				pageError.value = error?.response?.data?.message || error?.message || "Ошибка загрузки данных";
			} finally {
				pageLoading.value = false;
			}
		}
		const formAddPrice = useForm({
			good_id: props.good.id,
			price: null,
			currency_id: null
		});
		function storePrice() {
			formAddPrice.transform((data) => ({
				...data,
				price: toNumber(data.price, null),
				currency_id: data.currency_id
			})).post(route("web.price.store"), {
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
				}
			});
		}
		const formQuotation = useForm({
			good_id: props.good.id,
			unit_id: null,
			price: null,
			measure_id: null,
			denominator: 1
		});
		function storeQuotation() {
			formQuotation.transform((data) => ({
				...data,
				price: toNumber(data.price, null),
				denominator: Math.max(toNumber(data.denominator, 1), 1e-4)
			})).post(route("web.quotation.store"), {
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
				}
			});
		}
		const seoTitle = computed(() => {
			return goodData.value?.name ? `${goodData.value.name} - Ameise` : "Good - Ameise";
		});
		const seoDescription = computed(() => {
			return safeText(goodData.value?.description, "Страница товара в админке").slice(0, 160);
		});
		useHead({
			title: seoTitle,
			meta: [
				{
					name: "description",
					content: seoDescription
				},
				{
					name: "keywords",
					content: computed(() => goodData.value?.name ? `${goodData.value.name}, товар, pischeprom` : "товар")
				},
				{
					property: "og:title",
					content: computed(() => goodData.value?.name || "Good")
				},
				{
					property: "og:description",
					content: seoDescription
				},
				{
					property: "og:image",
					content: computed(() => goodData.value?.ava_image || "/default-image.jpg")
				},
				{
					property: "og:url",
					content: window.location.href
				}
			]
		});
		onMounted(() => {
			loadPageData();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, { class: "mb-3 align-center" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										sm: "2"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VBtn, {
												text: "+ 💵",
												onClick: ($event) => showFormAddPrice.value = !showFormAddPrice.value,
												variant: "elevated",
												density: "compact",
												color: "pink-darken-4",
												block: ""
											}, null, _parent, _scopeId));
											else return [createVNode(VBtn, {
												text: "+ 💵",
												onClick: ($event) => showFormAddPrice.value = !showFormAddPrice.value,
												variant: "elevated",
												density: "compact",
												color: "pink-darken-4",
												block: ""
											}, null, 8, ["onClick"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										sm: "2"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VBtn, {
												text: "+ Q",
												onClick: ($event) => dialogFormQuotation.value = !dialogFormQuotation.value,
												variant: "elevated",
												density: "compact",
												color: "indigo",
												block: ""
											}, null, _parent, _scopeId));
											else return [createVNode(VBtn, {
												text: "+ Q",
												onClick: ($event) => dialogFormQuotation.value = !dialogFormQuotation.value,
												variant: "elevated",
												density: "compact",
												color: "indigo",
												block: ""
											}, null, 8, ["onClick"])];
										}),
										_: 1
									}, _parent, _scopeId));
									if (goodData.value) _push(ssrRenderComponent(VCol, {
										cols: "12",
										sm: "8"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(`<div class="d-flex align-center justify-end ga-2" data-v-35349ee9${_scopeId}>`);
												_push(ssrRenderComponent(VChip, {
													size: "small",
													variant: "tonal",
													color: "deep-purple"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` ID: ${ssrInterpolate(goodData.value.id)}`);
														else return [createTextVNode(" ID: " + toDisplayString(goodData.value.id), 1)];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VChip, {
													size: "small",
													variant: "tonal",
													color: goodData.value.is_published ? "green" : "grey"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`${ssrInterpolate(goodData.value.is_published ? "published" : "hidden")}`);
														else return [createTextVNode(toDisplayString(goodData.value.is_published ? "published" : "hidden"), 1)];
													}),
													_: 1
												}, _parent, _scopeId));
												if (currentVatRate.value) _push(ssrRenderComponent(VChip, {
													size: "small",
													variant: "tonal",
													color: "blue-grey"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` НДС: ${ssrInterpolate(currentVatRate.value.title)} / ${ssrInterpolate(currentVatRate.value.rate)}% `);
														else return [createTextVNode(" НДС: " + toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)];
													}),
													_: 1
												}, _parent, _scopeId));
												else _push(`<!---->`);
												_push(`</div>`);
											} else return [createVNode("div", { class: "d-flex align-center justify-end ga-2" }, [
												createVNode(VChip, {
													size: "small",
													variant: "tonal",
													color: "deep-purple"
												}, {
													default: withCtx(() => [createTextVNode(" ID: " + toDisplayString(goodData.value.id), 1)]),
													_: 1
												}),
												createVNode(VChip, {
													size: "small",
													variant: "tonal",
													color: goodData.value.is_published ? "green" : "grey"
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.is_published ? "published" : "hidden"), 1)]),
													_: 1
												}, 8, ["color"]),
												currentVatRate.value ? (openBlock(), createBlock(VChip, {
													key: 0,
													size: "small",
													variant: "tonal",
													color: "blue-grey"
												}, {
													default: withCtx(() => [createTextVNode(" НДС: " + toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)]),
													_: 1
												})) : createCommentVNode("", true)
											])];
										}),
										_: 1
									}, _parent, _scopeId));
									else _push(`<!---->`);
								} else return [
									createVNode(VCol, {
										cols: "12",
										sm: "2"
									}, {
										default: withCtx(() => [createVNode(VBtn, {
											text: "+ 💵",
											onClick: ($event) => showFormAddPrice.value = !showFormAddPrice.value,
											variant: "elevated",
											density: "compact",
											color: "pink-darken-4",
											block: ""
										}, null, 8, ["onClick"])]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										sm: "2"
									}, {
										default: withCtx(() => [createVNode(VBtn, {
											text: "+ Q",
											onClick: ($event) => dialogFormQuotation.value = !dialogFormQuotation.value,
											variant: "elevated",
											density: "compact",
											color: "indigo",
											block: ""
										}, null, 8, ["onClick"])]),
										_: 1
									}),
									goodData.value ? (openBlock(), createBlock(VCol, {
										key: 0,
										cols: "12",
										sm: "8"
									}, {
										default: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-end ga-2" }, [
											createVNode(VChip, {
												size: "small",
												variant: "tonal",
												color: "deep-purple"
											}, {
												default: withCtx(() => [createTextVNode(" ID: " + toDisplayString(goodData.value.id), 1)]),
												_: 1
											}),
											createVNode(VChip, {
												size: "small",
												variant: "tonal",
												color: goodData.value.is_published ? "green" : "grey"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.is_published ? "published" : "hidden"), 1)]),
												_: 1
											}, 8, ["color"]),
											currentVatRate.value ? (openBlock(), createBlock(VChip, {
												key: 0,
												size: "small",
												variant: "tonal",
												color: "blue-grey"
											}, {
												default: withCtx(() => [createTextVNode(" НДС: " + toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)]),
												_: 1
											})) : createCommentVNode("", true)
										])]),
										_: 1
									})) : createCommentVNode("", true)
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogFormQuotation.value,
							"onUpdate:modelValue": ($event) => dialogFormQuotation.value = $event,
							width: "771"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Form Quotation`);
													else return [createTextVNode("Form Quotation")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VForm, { onSubmit: storeQuotation }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VContainer, { fluid: "" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VRow, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(ssrRenderComponent(VAutocomplete, {
																							items: units.value,
																							"item-value": "id",
																							"item-title": "name",
																							modelValue: unref(formQuotation).unit_id,
																							"onUpdate:modelValue": ($event) => unref(formQuotation).unit_id = $event,
																							label: "Unit",
																							variant: "solo",
																							density: "comfortable",
																							"base-color": "yellow",
																							clearable: ""
																						}, null, _parent, _scopeId));
																						else return [createVNode(VAutocomplete, {
																							items: units.value,
																							"item-value": "id",
																							"item-title": "name",
																							modelValue: unref(formQuotation).unit_id,
																							"onUpdate:modelValue": ($event) => unref(formQuotation).unit_id = $event,
																							label: "Unit",
																							variant: "solo",
																							density: "comfortable",
																							"base-color": "yellow",
																							clearable: ""
																						}, null, 8, [
																							"items",
																							"modelValue",
																							"onUpdate:modelValue"
																						])];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																				else return [createVNode(VCol, { cols: "12" }, {
																					default: withCtx(() => [createVNode(VAutocomplete, {
																						items: units.value,
																						"item-value": "id",
																						"item-title": "name",
																						modelValue: unref(formQuotation).unit_id,
																						"onUpdate:modelValue": ($event) => unref(formQuotation).unit_id = $event,
																						label: "Unit",
																						variant: "solo",
																						density: "comfortable",
																						"base-color": "yellow",
																						clearable: ""
																					}, null, 8, [
																						"items",
																						"modelValue",
																						"onUpdate:modelValue"
																					])]),
																					_: 1
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VRow, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(ssrRenderComponent(VCol, {
																						cols: "12",
																						md: "4"
																					}, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(ssrRenderComponent(VTextField, {
																								modelValue: unref(formQuotation).price,
																								"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																								label: "Цена",
																								variant: "solo",
																								density: "default",
																								type: "number"
																							}, null, _parent, _scopeId));
																							else return [createVNode(VTextField, {
																								modelValue: unref(formQuotation).price,
																								"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																								label: "Цена",
																								variant: "solo",
																								density: "default",
																								type: "number"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCol, {
																						cols: "12",
																						md: "4"
																					}, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(ssrRenderComponent(VAutocomplete, {
																								items: measures.value,
																								"item-value": "id",
																								"item-title": "name",
																								modelValue: unref(formQuotation).measure_id,
																								"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																								label: "Measure",
																								variant: "solo",
																								density: "compact",
																								clearable: ""
																							}, null, _parent, _scopeId));
																							else return [createVNode(VAutocomplete, {
																								items: measures.value,
																								"item-value": "id",
																								"item-title": "name",
																								modelValue: unref(formQuotation).measure_id,
																								"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																								label: "Measure",
																								variant: "solo",
																								density: "compact",
																								clearable: ""
																							}, null, 8, [
																								"items",
																								"modelValue",
																								"onUpdate:modelValue"
																							])];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCol, {
																						cols: "12",
																						md: "4"
																					}, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(ssrRenderComponent(VTextField, {
																								modelValue: unref(formQuotation).denominator,
																								"onUpdate:modelValue": ($event) => unref(formQuotation).denominator = $event,
																								label: "Делитель quotation",
																								type: "number",
																								min: "0.0001",
																								step: "0.0001",
																								variant: "solo",
																								density: "compact",
																								hint: "Например: цена дана за 25 кг",
																								"persistent-hint": ""
																							}, null, _parent, _scopeId));
																							else return [createVNode(VTextField, {
																								modelValue: unref(formQuotation).denominator,
																								"onUpdate:modelValue": ($event) => unref(formQuotation).denominator = $event,
																								label: "Делитель quotation",
																								type: "number",
																								min: "0.0001",
																								step: "0.0001",
																								variant: "solo",
																								density: "compact",
																								hint: "Например: цена дана за 25 кг",
																								"persistent-hint": ""
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																				} else return [
																					createVNode(VCol, {
																						cols: "12",
																						md: "4"
																					}, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formQuotation).price,
																							"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																							label: "Цена",
																							variant: "solo",
																							density: "default",
																							type: "number"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}),
																					createVNode(VCol, {
																						cols: "12",
																						md: "4"
																					}, {
																						default: withCtx(() => [createVNode(VAutocomplete, {
																							items: measures.value,
																							"item-value": "id",
																							"item-title": "name",
																							modelValue: unref(formQuotation).measure_id,
																							"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																							label: "Measure",
																							variant: "solo",
																							density: "compact",
																							clearable: ""
																						}, null, 8, [
																							"items",
																							"modelValue",
																							"onUpdate:modelValue"
																						])]),
																						_: 1
																					}),
																					createVNode(VCol, {
																						cols: "12",
																						md: "4"
																					}, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formQuotation).denominator,
																							"onUpdate:modelValue": ($event) => unref(formQuotation).denominator = $event,
																							label: "Делитель quotation",
																							type: "number",
																							min: "0.0001",
																							step: "0.0001",
																							variant: "solo",
																							density: "compact",
																							hint: "Например: цена дана за 25 кг",
																							"persistent-hint": ""
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					})
																				];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																	} else return [createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																			default: withCtx(() => [createVNode(VAutocomplete, {
																				items: units.value,
																				"item-value": "id",
																				"item-title": "name",
																				modelValue: unref(formQuotation).unit_id,
																				"onUpdate:modelValue": ($event) => unref(formQuotation).unit_id = $event,
																				label: "Unit",
																				variant: "solo",
																				density: "comfortable",
																				"base-color": "yellow",
																				clearable: ""
																			}, null, 8, [
																				"items",
																				"modelValue",
																				"onUpdate:modelValue"
																			])]),
																			_: 1
																		})]),
																		_: 1
																	}), createVNode(VRow, null, {
																		default: withCtx(() => [
																			createVNode(VCol, {
																				cols: "12",
																				md: "4"
																			}, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formQuotation).price,
																					"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																					label: "Цена",
																					variant: "solo",
																					density: "default",
																					type: "number"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VCol, {
																				cols: "12",
																				md: "4"
																			}, {
																				default: withCtx(() => [createVNode(VAutocomplete, {
																					items: measures.value,
																					"item-value": "id",
																					"item-title": "name",
																					modelValue: unref(formQuotation).measure_id,
																					"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																					label: "Measure",
																					variant: "solo",
																					density: "compact",
																					clearable: ""
																				}, null, 8, [
																					"items",
																					"modelValue",
																					"onUpdate:modelValue"
																				])]),
																				_: 1
																			}),
																			createVNode(VCol, {
																				cols: "12",
																				md: "4"
																			}, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formQuotation).denominator,
																					"onUpdate:modelValue": ($event) => unref(formQuotation).denominator = $event,
																					label: "Делитель quotation",
																					type: "number",
																					min: "0.0001",
																					step: "0.0001",
																					variant: "solo",
																					density: "compact",
																					hint: "Например: цена дана за 25 кг",
																					"persistent-hint": ""
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			})
																		]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VContainer, { fluid: "" }, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																		default: withCtx(() => [createVNode(VAutocomplete, {
																			items: units.value,
																			"item-value": "id",
																			"item-title": "name",
																			modelValue: unref(formQuotation).unit_id,
																			"onUpdate:modelValue": ($event) => unref(formQuotation).unit_id = $event,
																			label: "Unit",
																			variant: "solo",
																			density: "comfortable",
																			"base-color": "yellow",
																			clearable: ""
																		}, null, 8, [
																			"items",
																			"modelValue",
																			"onUpdate:modelValue"
																		])]),
																		_: 1
																	})]),
																	_: 1
																}), createVNode(VRow, null, {
																	default: withCtx(() => [
																		createVNode(VCol, {
																			cols: "12",
																			md: "4"
																		}, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formQuotation).price,
																				"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																				label: "Цена",
																				variant: "solo",
																				density: "default",
																				type: "number"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VCol, {
																			cols: "12",
																			md: "4"
																		}, {
																			default: withCtx(() => [createVNode(VAutocomplete, {
																				items: measures.value,
																				"item-value": "id",
																				"item-title": "name",
																				modelValue: unref(formQuotation).measure_id,
																				"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																				label: "Measure",
																				variant: "solo",
																				density: "compact",
																				clearable: ""
																			}, null, 8, [
																				"items",
																				"modelValue",
																				"onUpdate:modelValue"
																			])]),
																			_: 1
																		}),
																		createVNode(VCol, {
																			cols: "12",
																			md: "4"
																		}, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formQuotation).denominator,
																				"onUpdate:modelValue": ($event) => unref(formQuotation).denominator = $event,
																				label: "Делитель quotation",
																				type: "number",
																				min: "0.0001",
																				step: "0.0001",
																				variant: "solo",
																				density: "compact",
																				hint: "Например: цена дана за 25 кг",
																				"persistent-hint": ""
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		})
																	]),
																	_: 1
																})]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(VForm, { onSubmit: withModifiers(storeQuotation, ["prevent"]) }, {
														default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
															default: withCtx(() => [createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																	default: withCtx(() => [createVNode(VAutocomplete, {
																		items: units.value,
																		"item-value": "id",
																		"item-title": "name",
																		modelValue: unref(formQuotation).unit_id,
																		"onUpdate:modelValue": ($event) => unref(formQuotation).unit_id = $event,
																		label: "Unit",
																		variant: "solo",
																		density: "comfortable",
																		"base-color": "yellow",
																		clearable: ""
																	}, null, 8, [
																		"items",
																		"modelValue",
																		"onUpdate:modelValue"
																	])]),
																	_: 1
																})]),
																_: 1
															}), createVNode(VRow, null, {
																default: withCtx(() => [
																	createVNode(VCol, {
																		cols: "12",
																		md: "4"
																	}, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formQuotation).price,
																			"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																			label: "Цена",
																			variant: "solo",
																			density: "default",
																			type: "number"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VCol, {
																		cols: "12",
																		md: "4"
																	}, {
																		default: withCtx(() => [createVNode(VAutocomplete, {
																			items: measures.value,
																			"item-value": "id",
																			"item-title": "name",
																			modelValue: unref(formQuotation).measure_id,
																			"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																			label: "Measure",
																			variant: "solo",
																			density: "compact",
																			clearable: ""
																		}, null, 8, [
																			"items",
																			"modelValue",
																			"onUpdate:modelValue"
																		])]),
																		_: 1
																	}),
																	createVNode(VCol, {
																		cols: "12",
																		md: "4"
																	}, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formQuotation).denominator,
																			"onUpdate:modelValue": ($event) => unref(formQuotation).denominator = $event,
																			label: "Делитель quotation",
																			type: "number",
																			min: "0.0001",
																			step: "0.0001",
																			variant: "solo",
																			density: "compact",
																			hint: "Например: цена дана за 25 кг",
																			"persistent-hint": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})
																]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VSpacer, null, null, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															text: "Cancel",
															variant: "text",
															onClick: ($event) => dialogFormQuotation.value = false
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															text: "Store",
															onClick: storeQuotation,
															variant: "elevated",
															density: "comfortable",
															color: "grey"
														}, null, _parent, _scopeId));
													} else return [
														createVNode(VSpacer),
														createVNode(VBtn, {
															text: "Cancel",
															variant: "text",
															onClick: ($event) => dialogFormQuotation.value = false
														}, null, 8, ["onClick"]),
														createVNode(VBtn, {
															text: "Store",
															onClick: storeQuotation,
															variant: "elevated",
															density: "comfortable",
															color: "grey"
														})
													];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Form Quotation")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storeQuotation, ["prevent"]) }, {
													default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
														default: withCtx(() => [createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																default: withCtx(() => [createVNode(VAutocomplete, {
																	items: units.value,
																	"item-value": "id",
																	"item-title": "name",
																	modelValue: unref(formQuotation).unit_id,
																	"onUpdate:modelValue": ($event) => unref(formQuotation).unit_id = $event,
																	label: "Unit",
																	variant: "solo",
																	density: "comfortable",
																	"base-color": "yellow",
																	clearable: ""
																}, null, 8, [
																	"items",
																	"modelValue",
																	"onUpdate:modelValue"
																])]),
																_: 1
															})]),
															_: 1
														}), createVNode(VRow, null, {
															default: withCtx(() => [
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formQuotation).price,
																		"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																		label: "Цена",
																		variant: "solo",
																		density: "default",
																		type: "number"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VAutocomplete, {
																		items: measures.value,
																		"item-value": "id",
																		"item-title": "name",
																		modelValue: unref(formQuotation).measure_id,
																		"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																		label: "Measure",
																		variant: "solo",
																		density: "compact",
																		clearable: ""
																	}, null, 8, [
																		"items",
																		"modelValue",
																		"onUpdate:modelValue"
																	])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formQuotation).denominator,
																		"onUpdate:modelValue": ($event) => unref(formQuotation).denominator = $event,
																		label: "Делитель quotation",
																		type: "number",
																		min: "0.0001",
																		step: "0.0001",
																		variant: "solo",
																		density: "compact",
																		hint: "Например: цена дана за 25 кг",
																		"persistent-hint": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																})
															]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [
													createVNode(VSpacer),
													createVNode(VBtn, {
														text: "Cancel",
														variant: "text",
														onClick: ($event) => dialogFormQuotation.value = false
													}, null, 8, ["onClick"]),
													createVNode(VBtn, {
														text: "Store",
														onClick: storeQuotation,
														variant: "elevated",
														density: "comfortable",
														color: "grey"
													})
												]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, null, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Form Quotation")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storeQuotation, ["prevent"]) }, {
												default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
													default: withCtx(() => [createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(VAutocomplete, {
																items: units.value,
																"item-value": "id",
																"item-title": "name",
																modelValue: unref(formQuotation).unit_id,
																"onUpdate:modelValue": ($event) => unref(formQuotation).unit_id = $event,
																label: "Unit",
																variant: "solo",
																density: "comfortable",
																"base-color": "yellow",
																clearable: ""
															}, null, 8, [
																"items",
																"modelValue",
																"onUpdate:modelValue"
															])]),
															_: 1
														})]),
														_: 1
													}), createVNode(VRow, null, {
														default: withCtx(() => [
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(formQuotation).price,
																	"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																	label: "Цена",
																	variant: "solo",
																	density: "default",
																	type: "number"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VAutocomplete, {
																	items: measures.value,
																	"item-value": "id",
																	"item-title": "name",
																	modelValue: unref(formQuotation).measure_id,
																	"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																	label: "Measure",
																	variant: "solo",
																	density: "compact",
																	clearable: ""
																}, null, 8, [
																	"items",
																	"modelValue",
																	"onUpdate:modelValue"
																])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(formQuotation).denominator,
																	"onUpdate:modelValue": ($event) => unref(formQuotation).denominator = $event,
																	label: "Делитель quotation",
																	type: "number",
																	min: "0.0001",
																	step: "0.0001",
																	variant: "solo",
																	density: "compact",
																	hint: "Например: цена дана за 25 кг",
																	"persistent-hint": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})
														]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [
												createVNode(VSpacer),
												createVNode(VBtn, {
													text: "Cancel",
													variant: "text",
													onClick: ($event) => dialogFormQuotation.value = false
												}, null, 8, ["onClick"]),
												createVNode(VBtn, {
													text: "Store",
													onClick: storeQuotation,
													variant: "elevated",
													density: "comfortable",
													color: "grey"
												})
											]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						if (pageLoading.value) _push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCol, {
									cols: "12",
									class: "text-center py-10"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VProgressCircular, {
											indeterminate: "",
											color: "primary"
										}, null, _parent, _scopeId));
										else return [createVNode(VProgressCircular, {
											indeterminate: "",
											color: "primary"
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCol, {
									cols: "12",
									class: "text-center py-10"
								}, {
									default: withCtx(() => [createVNode(VProgressCircular, {
										indeterminate: "",
										color: "primary"
									})]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						else if (pageError.value) _push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VAlert, {
											type: "error",
											variant: "tonal"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(`${ssrInterpolate(pageError.value)}`);
												else return [createTextVNode(toDisplayString(pageError.value), 1)];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VAlert, {
											type: "error",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(pageError.value), 1)]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCol, { cols: "12" }, {
									default: withCtx(() => [createVNode(VAlert, {
										type: "error",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(pageError.value), 1)]),
										_: 1
									})]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						else if (goodData.value) {
							_push(`<!--[-->`);
							_push(ssrRenderComponent(VCard, { class: "mb-4 good-header-card" }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(ssrRenderComponent(VCardText, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VRow, { class: "align-center" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCol, {
															cols: "12",
															md: "8"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`<div class="text-caption text-medium-emphasis mb-1" data-v-35349ee9${_scopeId}> Good / товар </div><h1 class="text-h5 font-weight-bold mb-1" data-v-35349ee9${_scopeId}>${ssrInterpolate(goodData.value.name)}</h1><div class="text-caption text-medium-emphasis" data-v-35349ee9${_scopeId}> slug: ${ssrInterpolate(goodData.value.slug || "—")}</div>`);
																else return [
																	createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, " Good / товар "),
																	createVNode("h1", { class: "text-h5 font-weight-bold mb-1" }, toDisplayString(goodData.value.name), 1),
																	createVNode("div", { class: "text-caption text-medium-emphasis" }, " slug: " + toDisplayString(goodData.value.slug || "—"), 1)
																];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VRow, { dense: "" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(ssrRenderComponent(VCol, { cols: "6" }, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VCard, {
																						variant: "tonal",
																						class: "pa-3"
																					}, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`<div class="text-caption text-medium-emphasis" data-v-35349ee9${_scopeId}> Упаковка / denominator </div><div class="text-subtitle-1 font-weight-bold" data-v-35349ee9${_scopeId}>${ssrInterpolate(goodData.value.denominator || "—")}</div>`);
																							else return [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Упаковка / denominator "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString(goodData.value.denominator || "—"), 1)];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																					else return [createVNode(VCard, {
																						variant: "tonal",
																						class: "pa-3"
																					}, {
																						default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Упаковка / denominator "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString(goodData.value.denominator || "—"), 1)]),
																						_: 1
																					})];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VCol, { cols: "6" }, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VCard, {
																						variant: "tonal",
																						class: "pa-3"
																					}, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`<div class="text-caption text-medium-emphasis" data-v-35349ee9${_scopeId}> Quotation </div><div class="text-subtitle-1 font-weight-bold" data-v-35349ee9${_scopeId}>${ssrInterpolate((goodData.value.quotations || []).length)}</div>`);
																							else return [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Quotation "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString((goodData.value.quotations || []).length), 1)];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																					else return [createVNode(VCard, {
																						variant: "tonal",
																						class: "pa-3"
																					}, {
																						default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Quotation "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString((goodData.value.quotations || []).length), 1)]),
																						_: 1
																					})];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																		} else return [createVNode(VCol, { cols: "6" }, {
																			default: withCtx(() => [createVNode(VCard, {
																				variant: "tonal",
																				class: "pa-3"
																			}, {
																				default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Упаковка / denominator "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString(goodData.value.denominator || "—"), 1)]),
																				_: 1
																			})]),
																			_: 1
																		}), createVNode(VCol, { cols: "6" }, {
																			default: withCtx(() => [createVNode(VCard, {
																				variant: "tonal",
																				class: "pa-3"
																			}, {
																				default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Quotation "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString((goodData.value.quotations || []).length), 1)]),
																				_: 1
																			})]),
																			_: 1
																		})];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VRow, { dense: "" }, {
																	default: withCtx(() => [createVNode(VCol, { cols: "6" }, {
																		default: withCtx(() => [createVNode(VCard, {
																			variant: "tonal",
																			class: "pa-3"
																		}, {
																			default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Упаковка / denominator "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString(goodData.value.denominator || "—"), 1)]),
																			_: 1
																		})]),
																		_: 1
																	}), createVNode(VCol, { cols: "6" }, {
																		default: withCtx(() => [createVNode(VCard, {
																			variant: "tonal",
																			class: "pa-3"
																		}, {
																			default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Quotation "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString((goodData.value.quotations || []).length), 1)]),
																			_: 1
																		})]),
																		_: 1
																	})]),
																	_: 1
																})];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VCol, {
														cols: "12",
														md: "8"
													}, {
														default: withCtx(() => [
															createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, " Good / товар "),
															createVNode("h1", { class: "text-h5 font-weight-bold mb-1" }, toDisplayString(goodData.value.name), 1),
															createVNode("div", { class: "text-caption text-medium-emphasis" }, " slug: " + toDisplayString(goodData.value.slug || "—"), 1)
														]),
														_: 1
													}), createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VRow, { dense: "" }, {
															default: withCtx(() => [createVNode(VCol, { cols: "6" }, {
																default: withCtx(() => [createVNode(VCard, {
																	variant: "tonal",
																	class: "pa-3"
																}, {
																	default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Упаковка / denominator "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString(goodData.value.denominator || "—"), 1)]),
																	_: 1
																})]),
																_: 1
															}), createVNode(VCol, { cols: "6" }, {
																default: withCtx(() => [createVNode(VCard, {
																	variant: "tonal",
																	class: "pa-3"
																}, {
																	default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Quotation "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString((goodData.value.quotations || []).length), 1)]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VRow, { class: "align-center" }, {
												default: withCtx(() => [createVNode(VCol, {
													cols: "12",
													md: "8"
												}, {
													default: withCtx(() => [
														createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, " Good / товар "),
														createVNode("h1", { class: "text-h5 font-weight-bold mb-1" }, toDisplayString(goodData.value.name), 1),
														createVNode("div", { class: "text-caption text-medium-emphasis" }, " slug: " + toDisplayString(goodData.value.slug || "—"), 1)
													]),
													_: 1
												}), createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VRow, { dense: "" }, {
														default: withCtx(() => [createVNode(VCol, { cols: "6" }, {
															default: withCtx(() => [createVNode(VCard, {
																variant: "tonal",
																class: "pa-3"
															}, {
																default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Упаковка / denominator "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString(goodData.value.denominator || "—"), 1)]),
																_: 1
															})]),
															_: 1
														}), createVNode(VCol, { cols: "6" }, {
															default: withCtx(() => [createVNode(VCard, {
																variant: "tonal",
																class: "pa-3"
															}, {
																default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Quotation "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString((goodData.value.quotations || []).length), 1)]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									else return [createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VRow, { class: "align-center" }, {
											default: withCtx(() => [createVNode(VCol, {
												cols: "12",
												md: "8"
											}, {
												default: withCtx(() => [
													createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, " Good / товар "),
													createVNode("h1", { class: "text-h5 font-weight-bold mb-1" }, toDisplayString(goodData.value.name), 1),
													createVNode("div", { class: "text-caption text-medium-emphasis" }, " slug: " + toDisplayString(goodData.value.slug || "—"), 1)
												]),
												_: 1
											}), createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VRow, { dense: "" }, {
													default: withCtx(() => [createVNode(VCol, { cols: "6" }, {
														default: withCtx(() => [createVNode(VCard, {
															variant: "tonal",
															class: "pa-3"
														}, {
															default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Упаковка / denominator "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString(goodData.value.denominator || "—"), 1)]),
															_: 1
														})]),
														_: 1
													}), createVNode(VCol, { cols: "6" }, {
														default: withCtx(() => [createVNode(VCard, {
															variant: "tonal",
															class: "pa-3"
														}, {
															default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Quotation "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString((goodData.value.quotations || []).length), 1)]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									})];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(ssrRenderComponent(VCard, { class: "mb-4" }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(ssrRenderComponent(VTabs, {
										modelValue: activeTab.value,
										"onUpdate:modelValue": ($event) => activeTab.value = $event,
										density: "compact",
										color: "deep-purple-darken-1",
										"show-arrows": ""
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VTab, { value: "overview" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`Обзор`);
														else return [createTextVNode("Обзор")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "quotations" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`Quotations`);
														else return [createTextVNode("Quotations")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "purchases" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`Закупки`);
														else return [createTextVNode("Закупки")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "calculator" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`Расчёт цены`);
														else return [createTextVNode("Расчёт цены")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "calculations" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`Сохранённые расчёты`);
														else return [createTextVNode("Сохранённые расчёты")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "price-types" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`Виды цен`);
														else return [createTextVNode("Виды цен")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "price-values" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`Цены товара`);
														else return [createTextVNode("Цены товара")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "media" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`Media`);
														else return [createTextVNode("Media")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "seo" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`SEO`);
														else return [createTextVNode("SEO")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "sales" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`Продажи`);
														else return [createTextVNode("Продажи")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "price-history" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`История цен`);
														else return [createTextVNode("История цен")];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VTab, { value: "overview" }, {
													default: withCtx(() => [createTextVNode("Обзор")]),
													_: 1
												}),
												createVNode(VTab, { value: "quotations" }, {
													default: withCtx(() => [createTextVNode("Quotations")]),
													_: 1
												}),
												createVNode(VTab, { value: "purchases" }, {
													default: withCtx(() => [createTextVNode("Закупки")]),
													_: 1
												}),
												createVNode(VTab, { value: "calculator" }, {
													default: withCtx(() => [createTextVNode("Расчёт цены")]),
													_: 1
												}),
												createVNode(VTab, { value: "calculations" }, {
													default: withCtx(() => [createTextVNode("Сохранённые расчёты")]),
													_: 1
												}),
												createVNode(VTab, { value: "price-types" }, {
													default: withCtx(() => [createTextVNode("Виды цен")]),
													_: 1
												}),
												createVNode(VTab, { value: "price-values" }, {
													default: withCtx(() => [createTextVNode("Цены товара")]),
													_: 1
												}),
												createVNode(VTab, { value: "media" }, {
													default: withCtx(() => [createTextVNode("Media")]),
													_: 1
												}),
												createVNode(VTab, { value: "seo" }, {
													default: withCtx(() => [createTextVNode("SEO")]),
													_: 1
												}),
												createVNode(VTab, { value: "sales" }, {
													default: withCtx(() => [createTextVNode("Продажи")]),
													_: 1
												}),
												createVNode(VTab, { value: "price-history" }, {
													default: withCtx(() => [createTextVNode("История цен")]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
									else return [createVNode(VTabs, {
										modelValue: activeTab.value,
										"onUpdate:modelValue": ($event) => activeTab.value = $event,
										density: "compact",
										color: "deep-purple-darken-1",
										"show-arrows": ""
									}, {
										default: withCtx(() => [
											createVNode(VTab, { value: "overview" }, {
												default: withCtx(() => [createTextVNode("Обзор")]),
												_: 1
											}),
											createVNode(VTab, { value: "quotations" }, {
												default: withCtx(() => [createTextVNode("Quotations")]),
												_: 1
											}),
											createVNode(VTab, { value: "purchases" }, {
												default: withCtx(() => [createTextVNode("Закупки")]),
												_: 1
											}),
											createVNode(VTab, { value: "calculator" }, {
												default: withCtx(() => [createTextVNode("Расчёт цены")]),
												_: 1
											}),
											createVNode(VTab, { value: "calculations" }, {
												default: withCtx(() => [createTextVNode("Сохранённые расчёты")]),
												_: 1
											}),
											createVNode(VTab, { value: "price-types" }, {
												default: withCtx(() => [createTextVNode("Виды цен")]),
												_: 1
											}),
											createVNode(VTab, { value: "price-values" }, {
												default: withCtx(() => [createTextVNode("Цены товара")]),
												_: 1
											}),
											createVNode(VTab, { value: "media" }, {
												default: withCtx(() => [createTextVNode("Media")]),
												_: 1
											}),
											createVNode(VTab, { value: "seo" }, {
												default: withCtx(() => [createTextVNode("SEO")]),
												_: 1
											}),
											createVNode(VTab, { value: "sales" }, {
												default: withCtx(() => [createTextVNode("Продажи")]),
												_: 1
											}),
											createVNode(VTab, { value: "price-history" }, {
												default: withCtx(() => [createTextVNode("История цен")]),
												_: 1
											})
										]),
										_: 1
									}, 8, ["modelValue", "onUpdate:modelValue"])];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(ssrRenderComponent(VWindow, {
								modelValue: activeTab.value,
								"onUpdate:modelValue": ($event) => activeTab.value = $event
							}, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) {
										_push(ssrRenderComponent(VWindowItem, { value: "overview" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VRow, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																lg: "3"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VCard, { class: "mb-4" }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(ssrRenderComponent(VCardTitle, { class: "text-wrap" }, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`${ssrInterpolate(goodData.value.name)}`);
																							else return [createTextVNode(toDisplayString(goodData.value.name), 1)];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCardText, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) {
																								_push(ssrRenderComponent(VImg, {
																									src: goodData.value.ava_thumb || "/default-image.jpg",
																									cover: "",
																									class: "mb-3 rounded-lg",
																									"max-height": "120"
																								}, null, _parent, _scopeId));
																								_push(ssrRenderComponent(VImg, {
																									src: goodData.value.ava_image || "/default-image.jpg",
																									alt: goodData.value.name,
																									"lazy-src": "/placeholder.jpg",
																									"aspect-ratio": "1",
																									cover: "",
																									class: "mb-4 rounded-lg"
																								}, null, _parent, _scopeId));
																								_push(`<p class="mb-0" data-v-35349ee9${_scopeId}>${ssrInterpolate(goodData.value.description || "Описание отсутствует")}</p>`);
																							} else return [
																								createVNode(VImg, {
																									src: goodData.value.ava_thumb || "/default-image.jpg",
																									cover: "",
																									class: "mb-3 rounded-lg",
																									"max-height": "120"
																								}, null, 8, ["src"]),
																								createVNode(VImg, {
																									src: goodData.value.ava_image || "/default-image.jpg",
																									alt: goodData.value.name,
																									"lazy-src": "/placeholder.jpg",
																									"aspect-ratio": "1",
																									cover: "",
																									class: "mb-4 rounded-lg"
																								}, null, 8, ["src", "alt"]),
																								createVNode("p", { class: "mb-0" }, toDisplayString(goodData.value.description || "Описание отсутствует"), 1)
																							];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																				} else return [createVNode(VCardTitle, { class: "text-wrap" }, {
																					default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.name), 1)]),
																					_: 1
																				}), createVNode(VCardText, null, {
																					default: withCtx(() => [
																						createVNode(VImg, {
																							src: goodData.value.ava_thumb || "/default-image.jpg",
																							cover: "",
																							class: "mb-3 rounded-lg",
																							"max-height": "120"
																						}, null, 8, ["src"]),
																						createVNode(VImg, {
																							src: goodData.value.ava_image || "/default-image.jpg",
																							alt: goodData.value.name,
																							"lazy-src": "/placeholder.jpg",
																							"aspect-ratio": "1",
																							cover: "",
																							class: "mb-4 rounded-lg"
																						}, null, 8, ["src", "alt"]),
																						createVNode("p", { class: "mb-0" }, toDisplayString(goodData.value.description || "Описание отсутствует"), 1)
																					]),
																					_: 1
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		if (showFormAddPrice.value) _push(ssrRenderComponent(VCard, { class: "mb-4" }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(ssrRenderComponent(VCardTitle, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`Добавить цену`);
																							else return [createTextVNode("Добавить цену")];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCardText, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(ssrRenderComponent(VForm, { onSubmit: storePrice }, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) {
																										_push(ssrRenderComponent(VRow, null, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) {
																													_push(ssrRenderComponent(VCol, { cols: "8" }, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(ssrRenderComponent(VTextField, {
																																modelValue: unref(formAddPrice).price,
																																"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																																label: "Цена",
																																variant: "solo",
																																density: "comfortable",
																																type: "number",
																																color: "deep-purple"
																															}, null, _parent, _scopeId));
																															else return [createVNode(VTextField, {
																																modelValue: unref(formAddPrice).price,
																																"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																																label: "Цена",
																																variant: "solo",
																																density: "comfortable",
																																type: "number",
																																color: "deep-purple"
																															}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																													_push(ssrRenderComponent(VCol, { cols: "4" }, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(ssrRenderComponent(VSelect, {
																																items: currencies.value,
																																"item-value": "id",
																																"item-title": "code",
																																modelValue: unref(formAddPrice).currency_id,
																																"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																																label: "Валюта",
																																variant: "solo",
																																density: "compact",
																																clearable: ""
																															}, null, _parent, _scopeId));
																															else return [createVNode(VSelect, {
																																items: currencies.value,
																																"item-value": "id",
																																"item-title": "code",
																																modelValue: unref(formAddPrice).currency_id,
																																"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																																label: "Валюта",
																																variant: "solo",
																																density: "compact",
																																clearable: ""
																															}, null, 8, [
																																"items",
																																"modelValue",
																																"onUpdate:modelValue"
																															])];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																												} else return [createVNode(VCol, { cols: "8" }, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formAddPrice).price,
																														"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																														label: "Цена",
																														variant: "solo",
																														density: "comfortable",
																														type: "number",
																														color: "deep-purple"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												}), createVNode(VCol, { cols: "4" }, {
																													default: withCtx(() => [createVNode(VSelect, {
																														items: currencies.value,
																														"item-value": "id",
																														"item-title": "code",
																														modelValue: unref(formAddPrice).currency_id,
																														"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																														label: "Валюта",
																														variant: "solo",
																														density: "compact",
																														clearable: ""
																													}, null, 8, [
																														"items",
																														"modelValue",
																														"onUpdate:modelValue"
																													])]),
																													_: 1
																												})];
																											}),
																											_: 1
																										}, _parent, _scopeId));
																										_push(ssrRenderComponent(VRow, null, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) _push(ssrRenderComponent(VBtn, {
																															text: "Store",
																															onClick: storePrice,
																															variant: "flat",
																															density: "comfortable",
																															block: ""
																														}, null, _parent, _scopeId));
																														else return [createVNode(VBtn, {
																															text: "Store",
																															onClick: storePrice,
																															variant: "flat",
																															density: "comfortable",
																															block: ""
																														})];
																													}),
																													_: 1
																												}, _parent, _scopeId));
																												else return [createVNode(VCol, { cols: "12" }, {
																													default: withCtx(() => [createVNode(VBtn, {
																														text: "Store",
																														onClick: storePrice,
																														variant: "flat",
																														density: "comfortable",
																														block: ""
																													})]),
																													_: 1
																												})];
																											}),
																											_: 1
																										}, _parent, _scopeId));
																									} else return [createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formAddPrice).price,
																												"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																												label: "Цена",
																												variant: "solo",
																												density: "comfortable",
																												type: "number",
																												color: "deep-purple"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										}), createVNode(VCol, { cols: "4" }, {
																											default: withCtx(() => [createVNode(VSelect, {
																												items: currencies.value,
																												"item-value": "id",
																												"item-title": "code",
																												modelValue: unref(formAddPrice).currency_id,
																												"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																												label: "Валюта",
																												variant: "solo",
																												density: "compact",
																												clearable: ""
																											}, null, 8, [
																												"items",
																												"modelValue",
																												"onUpdate:modelValue"
																											])]),
																											_: 1
																										})]),
																										_: 1
																									}), createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																											default: withCtx(() => [createVNode(VBtn, {
																												text: "Store",
																												onClick: storePrice,
																												variant: "flat",
																												density: "comfortable",
																												block: ""
																											})]),
																											_: 1
																										})]),
																										_: 1
																									})];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																							else return [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																								default: withCtx(() => [createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formAddPrice).price,
																											"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																											label: "Цена",
																											variant: "solo",
																											density: "comfortable",
																											type: "number",
																											color: "deep-purple"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									}), createVNode(VCol, { cols: "4" }, {
																										default: withCtx(() => [createVNode(VSelect, {
																											items: currencies.value,
																											"item-value": "id",
																											"item-title": "code",
																											modelValue: unref(formAddPrice).currency_id,
																											"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																											label: "Валюта",
																											variant: "solo",
																											density: "compact",
																											clearable: ""
																										}, null, 8, [
																											"items",
																											"modelValue",
																											"onUpdate:modelValue"
																										])]),
																										_: 1
																									})]),
																									_: 1
																								}), createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																										default: withCtx(() => [createVNode(VBtn, {
																											text: "Store",
																											onClick: storePrice,
																											variant: "flat",
																											density: "comfortable",
																											block: ""
																										})]),
																										_: 1
																									})]),
																									_: 1
																								})]),
																								_: 1
																							})];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																				} else return [createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Добавить цену")]),
																					_: 1
																				}), createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																						default: withCtx(() => [createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formAddPrice).price,
																									"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																									label: "Цена",
																									variant: "solo",
																									density: "comfortable",
																									type: "number",
																									color: "deep-purple"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							}), createVNode(VCol, { cols: "4" }, {
																								default: withCtx(() => [createVNode(VSelect, {
																									items: currencies.value,
																									"item-value": "id",
																									"item-title": "code",
																									modelValue: unref(formAddPrice).currency_id,
																									"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																									label: "Валюта",
																									variant: "solo",
																									density: "compact",
																									clearable: ""
																								}, null, 8, [
																									"items",
																									"modelValue",
																									"onUpdate:modelValue"
																								])]),
																								_: 1
																							})]),
																							_: 1
																						}), createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																								default: withCtx(() => [createVNode(VBtn, {
																									text: "Store",
																									onClick: storePrice,
																									variant: "flat",
																									density: "comfortable",
																									block: ""
																								})]),
																								_: 1
																							})]),
																							_: 1
																						})]),
																						_: 1
																					})]),
																					_: 1
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		else _push(`<!---->`);
																	} else return [createVNode(VCard, { class: "mb-4" }, {
																		default: withCtx(() => [createVNode(VCardTitle, { class: "text-wrap" }, {
																			default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.name), 1)]),
																			_: 1
																		}), createVNode(VCardText, null, {
																			default: withCtx(() => [
																				createVNode(VImg, {
																					src: goodData.value.ava_thumb || "/default-image.jpg",
																					cover: "",
																					class: "mb-3 rounded-lg",
																					"max-height": "120"
																				}, null, 8, ["src"]),
																				createVNode(VImg, {
																					src: goodData.value.ava_image || "/default-image.jpg",
																					alt: goodData.value.name,
																					"lazy-src": "/placeholder.jpg",
																					"aspect-ratio": "1",
																					cover: "",
																					class: "mb-4 rounded-lg"
																				}, null, 8, ["src", "alt"]),
																				createVNode("p", { class: "mb-0" }, toDisplayString(goodData.value.description || "Описание отсутствует"), 1)
																			]),
																			_: 1
																		})]),
																		_: 1
																	}), showFormAddPrice.value ? (openBlock(), createBlock(VCard, {
																		key: 0,
																		class: "mb-4"
																	}, {
																		default: withCtx(() => [createVNode(VCardTitle, null, {
																			default: withCtx(() => [createTextVNode("Добавить цену")]),
																			_: 1
																		}), createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																				default: withCtx(() => [createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formAddPrice).price,
																							"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																							label: "Цена",
																							variant: "solo",
																							density: "comfortable",
																							type: "number",
																							color: "deep-purple"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}), createVNode(VCol, { cols: "4" }, {
																						default: withCtx(() => [createVNode(VSelect, {
																							items: currencies.value,
																							"item-value": "id",
																							"item-title": "code",
																							modelValue: unref(formAddPrice).currency_id,
																							"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																							label: "Валюта",
																							variant: "solo",
																							density: "compact",
																							clearable: ""
																						}, null, 8, [
																							"items",
																							"modelValue",
																							"onUpdate:modelValue"
																						])]),
																						_: 1
																					})]),
																					_: 1
																				}), createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																						default: withCtx(() => [createVNode(VBtn, {
																							text: "Store",
																							onClick: storePrice,
																							variant: "flat",
																							density: "comfortable",
																							block: ""
																						})]),
																						_: 1
																					})]),
																					_: 1
																				})]),
																				_: 1
																			})]),
																			_: 1
																		})]),
																		_: 1
																	})) : createCommentVNode("", true)];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																lg: "5"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VCard, { class: "mb-4" }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(ssrRenderComponent(VCardTitle, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`Кратко по товару`);
																							else return [createTextVNode("Кратко по товару")];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCardText, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(ssrRenderComponent(VList, { density: "compact" }, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) {
																										_push(ssrRenderComponent(VListItem, null, {
																											prepend: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(ssrRenderComponent(VIcon, { icon: "mdi-pound" }, null, _parent, _scopeId));
																												else return [createVNode(VIcon, { icon: "mdi-pound" })];
																											}),
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) {
																													_push(ssrRenderComponent(VListItemTitle, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(`ID`);
																															else return [createTextVNode("ID")];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																													_push(ssrRenderComponent(VListItemSubtitle, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(`${ssrInterpolate(goodData.value.id)}`);
																															else return [createTextVNode(toDisplayString(goodData.value.id), 1)];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																												} else return [createVNode(VListItemTitle, null, {
																													default: withCtx(() => [createTextVNode("ID")]),
																													_: 1
																												}), createVNode(VListItemSubtitle, null, {
																													default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.id), 1)]),
																													_: 1
																												})];
																											}),
																											_: 1
																										}, _parent, _scopeId));
																										_push(ssrRenderComponent(VListItem, null, {
																											prepend: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(ssrRenderComponent(VIcon, { icon: "mdi-link-variant" }, null, _parent, _scopeId));
																												else return [createVNode(VIcon, { icon: "mdi-link-variant" })];
																											}),
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) {
																													_push(ssrRenderComponent(VListItemTitle, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(`Slug`);
																															else return [createTextVNode("Slug")];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																													_push(ssrRenderComponent(VListItemSubtitle, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(`${ssrInterpolate(goodData.value.slug || "—")}`);
																															else return [createTextVNode(toDisplayString(goodData.value.slug || "—"), 1)];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																												} else return [createVNode(VListItemTitle, null, {
																													default: withCtx(() => [createTextVNode("Slug")]),
																													_: 1
																												}), createVNode(VListItemSubtitle, null, {
																													default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.slug || "—"), 1)]),
																													_: 1
																												})];
																											}),
																											_: 1
																										}, _parent, _scopeId));
																										_push(ssrRenderComponent(VListItem, null, {
																											prepend: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(ssrRenderComponent(VIcon, { icon: "mdi-package-variant" }, null, _parent, _scopeId));
																												else return [createVNode(VIcon, { icon: "mdi-package-variant" })];
																											}),
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) {
																													_push(ssrRenderComponent(VListItemTitle, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(`Denominator`);
																															else return [createTextVNode("Denominator")];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																													_push(ssrRenderComponent(VListItemSubtitle, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(`${ssrInterpolate(goodData.value.denominator || "—")}`);
																															else return [createTextVNode(toDisplayString(goodData.value.denominator || "—"), 1)];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																												} else return [createVNode(VListItemTitle, null, {
																													default: withCtx(() => [createTextVNode("Denominator")]),
																													_: 1
																												}), createVNode(VListItemSubtitle, null, {
																													default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.denominator || "—"), 1)]),
																													_: 1
																												})];
																											}),
																											_: 1
																										}, _parent, _scopeId));
																										_push(ssrRenderComponent(VListItem, null, {
																											prepend: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(ssrRenderComponent(VIcon, { icon: "mdi-percent" }, null, _parent, _scopeId));
																												else return [createVNode(VIcon, { icon: "mdi-percent" })];
																											}),
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) {
																													_push(ssrRenderComponent(VListItemTitle, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(`VAT`);
																															else return [createTextVNode("VAT")];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																													_push(ssrRenderComponent(VListItemSubtitle, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) if (currentVatRate.value) _push(`<span data-v-35349ee9${_scopeId}>${ssrInterpolate(currentVatRate.value.title)} / ${ssrInterpolate(currentVatRate.value.rate)}% </span>`);
																															else _push(`<span data-v-35349ee9${_scopeId}> — </span>`);
																															else return [currentVatRate.value ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)) : (openBlock(), createBlock("span", { key: 1 }, " — "))];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																												} else return [createVNode(VListItemTitle, null, {
																													default: withCtx(() => [createTextVNode("VAT")]),
																													_: 1
																												}), createVNode(VListItemSubtitle, null, {
																													default: withCtx(() => [currentVatRate.value ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)) : (openBlock(), createBlock("span", { key: 1 }, " — "))]),
																													_: 1
																												})];
																											}),
																											_: 1
																										}, _parent, _scopeId));
																									} else return [
																										createVNode(VListItem, null, {
																											prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-pound" })]),
																											default: withCtx(() => [createVNode(VListItemTitle, null, {
																												default: withCtx(() => [createTextVNode("ID")]),
																												_: 1
																											}), createVNode(VListItemSubtitle, null, {
																												default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.id), 1)]),
																												_: 1
																											})]),
																											_: 1
																										}),
																										createVNode(VListItem, null, {
																											prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-link-variant" })]),
																											default: withCtx(() => [createVNode(VListItemTitle, null, {
																												default: withCtx(() => [createTextVNode("Slug")]),
																												_: 1
																											}), createVNode(VListItemSubtitle, null, {
																												default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.slug || "—"), 1)]),
																												_: 1
																											})]),
																											_: 1
																										}),
																										createVNode(VListItem, null, {
																											prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-package-variant" })]),
																											default: withCtx(() => [createVNode(VListItemTitle, null, {
																												default: withCtx(() => [createTextVNode("Denominator")]),
																												_: 1
																											}), createVNode(VListItemSubtitle, null, {
																												default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.denominator || "—"), 1)]),
																												_: 1
																											})]),
																											_: 1
																										}),
																										createVNode(VListItem, null, {
																											prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-percent" })]),
																											default: withCtx(() => [createVNode(VListItemTitle, null, {
																												default: withCtx(() => [createTextVNode("VAT")]),
																												_: 1
																											}), createVNode(VListItemSubtitle, null, {
																												default: withCtx(() => [currentVatRate.value ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)) : (openBlock(), createBlock("span", { key: 1 }, " — "))]),
																												_: 1
																											})]),
																											_: 1
																										})
																									];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																							else return [createVNode(VList, { density: "compact" }, {
																								default: withCtx(() => [
																									createVNode(VListItem, null, {
																										prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-pound" })]),
																										default: withCtx(() => [createVNode(VListItemTitle, null, {
																											default: withCtx(() => [createTextVNode("ID")]),
																											_: 1
																										}), createVNode(VListItemSubtitle, null, {
																											default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.id), 1)]),
																											_: 1
																										})]),
																										_: 1
																									}),
																									createVNode(VListItem, null, {
																										prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-link-variant" })]),
																										default: withCtx(() => [createVNode(VListItemTitle, null, {
																											default: withCtx(() => [createTextVNode("Slug")]),
																											_: 1
																										}), createVNode(VListItemSubtitle, null, {
																											default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.slug || "—"), 1)]),
																											_: 1
																										})]),
																										_: 1
																									}),
																									createVNode(VListItem, null, {
																										prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-package-variant" })]),
																										default: withCtx(() => [createVNode(VListItemTitle, null, {
																											default: withCtx(() => [createTextVNode("Denominator")]),
																											_: 1
																										}), createVNode(VListItemSubtitle, null, {
																											default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.denominator || "—"), 1)]),
																											_: 1
																										})]),
																										_: 1
																									}),
																									createVNode(VListItem, null, {
																										prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-percent" })]),
																										default: withCtx(() => [createVNode(VListItemTitle, null, {
																											default: withCtx(() => [createTextVNode("VAT")]),
																											_: 1
																										}), createVNode(VListItemSubtitle, null, {
																											default: withCtx(() => [currentVatRate.value ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)) : (openBlock(), createBlock("span", { key: 1 }, " — "))]),
																											_: 1
																										})]),
																										_: 1
																									})
																								]),
																								_: 1
																							})];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																				} else return [createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Кратко по товару")]),
																					_: 1
																				}), createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																						default: withCtx(() => [
																							createVNode(VListItem, null, {
																								prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-pound" })]),
																								default: withCtx(() => [createVNode(VListItemTitle, null, {
																									default: withCtx(() => [createTextVNode("ID")]),
																									_: 1
																								}), createVNode(VListItemSubtitle, null, {
																									default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.id), 1)]),
																									_: 1
																								})]),
																								_: 1
																							}),
																							createVNode(VListItem, null, {
																								prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-link-variant" })]),
																								default: withCtx(() => [createVNode(VListItemTitle, null, {
																									default: withCtx(() => [createTextVNode("Slug")]),
																									_: 1
																								}), createVNode(VListItemSubtitle, null, {
																									default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.slug || "—"), 1)]),
																									_: 1
																								})]),
																								_: 1
																							}),
																							createVNode(VListItem, null, {
																								prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-package-variant" })]),
																								default: withCtx(() => [createVNode(VListItemTitle, null, {
																									default: withCtx(() => [createTextVNode("Denominator")]),
																									_: 1
																								}), createVNode(VListItemSubtitle, null, {
																									default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.denominator || "—"), 1)]),
																									_: 1
																								})]),
																								_: 1
																							}),
																							createVNode(VListItem, null, {
																								prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-percent" })]),
																								default: withCtx(() => [createVNode(VListItemTitle, null, {
																									default: withCtx(() => [createTextVNode("VAT")]),
																									_: 1
																								}), createVNode(VListItemSubtitle, null, {
																									default: withCtx(() => [currentVatRate.value ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)) : (openBlock(), createBlock("span", { key: 1 }, " — "))]),
																									_: 1
																								})]),
																								_: 1
																							})
																						]),
																						_: 1
																					})]),
																					_: 1
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCard, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(ssrRenderComponent(VCardTitle, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`Products`);
																							else return [createTextVNode("Products")];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCardText, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) if ((goodData.value.products || []).length) {
																								_push(`<!--[-->`);
																								ssrRenderList(goodData.value.products || [], (product) => {
																									_push(ssrRenderComponent(VChip, {
																										key: product.id,
																										class: "ma-1",
																										size: "small",
																										variant: "tonal",
																										color: "teal"
																									}, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(`${ssrInterpolate(product.rus || product.name || product.id)}`);
																											else return [createTextVNode(toDisplayString(product.rus || product.name || product.id), 1)];
																										}),
																										_: 2
																									}, _parent, _scopeId));
																								});
																								_push(`<!--]-->`);
																							} else _push(ssrRenderComponent(VAlert, {
																								type: "info",
																								variant: "tonal"
																							}, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(` Products пока не привязаны. `);
																									else return [createTextVNode(" Products пока не привязаны. ")];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																							else return [(goodData.value.products || []).length ? (openBlock(true), createBlock(Fragment, { key: 0 }, renderList(goodData.value.products || [], (product) => {
																								return openBlock(), createBlock(VChip, {
																									key: product.id,
																									class: "ma-1",
																									size: "small",
																									variant: "tonal",
																									color: "teal"
																								}, {
																									default: withCtx(() => [createTextVNode(toDisplayString(product.rus || product.name || product.id), 1)]),
																									_: 2
																								}, 1024);
																							}), 128)) : (openBlock(), createBlock(VAlert, {
																								key: 1,
																								type: "info",
																								variant: "tonal"
																							}, {
																								default: withCtx(() => [createTextVNode(" Products пока не привязаны. ")]),
																								_: 1
																							}))];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																				} else return [createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Products")]),
																					_: 1
																				}), createVNode(VCardText, null, {
																					default: withCtx(() => [(goodData.value.products || []).length ? (openBlock(true), createBlock(Fragment, { key: 0 }, renderList(goodData.value.products || [], (product) => {
																						return openBlock(), createBlock(VChip, {
																							key: product.id,
																							class: "ma-1",
																							size: "small",
																							variant: "tonal",
																							color: "teal"
																						}, {
																							default: withCtx(() => [createTextVNode(toDisplayString(product.rus || product.name || product.id), 1)]),
																							_: 2
																						}, 1024);
																					}), 128)) : (openBlock(), createBlock(VAlert, {
																						key: 1,
																						type: "info",
																						variant: "tonal"
																					}, {
																						default: withCtx(() => [createTextVNode(" Products пока не привязаны. ")]),
																						_: 1
																					}))]),
																					_: 1
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																	} else return [createVNode(VCard, { class: "mb-4" }, {
																		default: withCtx(() => [createVNode(VCardTitle, null, {
																			default: withCtx(() => [createTextVNode("Кратко по товару")]),
																			_: 1
																		}), createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																				default: withCtx(() => [
																					createVNode(VListItem, null, {
																						prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-pound" })]),
																						default: withCtx(() => [createVNode(VListItemTitle, null, {
																							default: withCtx(() => [createTextVNode("ID")]),
																							_: 1
																						}), createVNode(VListItemSubtitle, null, {
																							default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.id), 1)]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VListItem, null, {
																						prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-link-variant" })]),
																						default: withCtx(() => [createVNode(VListItemTitle, null, {
																							default: withCtx(() => [createTextVNode("Slug")]),
																							_: 1
																						}), createVNode(VListItemSubtitle, null, {
																							default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.slug || "—"), 1)]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VListItem, null, {
																						prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-package-variant" })]),
																						default: withCtx(() => [createVNode(VListItemTitle, null, {
																							default: withCtx(() => [createTextVNode("Denominator")]),
																							_: 1
																						}), createVNode(VListItemSubtitle, null, {
																							default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.denominator || "—"), 1)]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VListItem, null, {
																						prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-percent" })]),
																						default: withCtx(() => [createVNode(VListItemTitle, null, {
																							default: withCtx(() => [createTextVNode("VAT")]),
																							_: 1
																						}), createVNode(VListItemSubtitle, null, {
																							default: withCtx(() => [currentVatRate.value ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)) : (openBlock(), createBlock("span", { key: 1 }, " — "))]),
																							_: 1
																						})]),
																						_: 1
																					})
																				]),
																				_: 1
																			})]),
																			_: 1
																		})]),
																		_: 1
																	}), createVNode(VCard, null, {
																		default: withCtx(() => [createVNode(VCardTitle, null, {
																			default: withCtx(() => [createTextVNode("Products")]),
																			_: 1
																		}), createVNode(VCardText, null, {
																			default: withCtx(() => [(goodData.value.products || []).length ? (openBlock(true), createBlock(Fragment, { key: 0 }, renderList(goodData.value.products || [], (product) => {
																				return openBlock(), createBlock(VChip, {
																					key: product.id,
																					class: "ma-1",
																					size: "small",
																					variant: "tonal",
																					color: "teal"
																				}, {
																					default: withCtx(() => [createTextVNode(toDisplayString(product.rus || product.name || product.id), 1)]),
																					_: 2
																				}, 1024);
																			}), 128)) : (openBlock(), createBlock(VAlert, {
																				key: 1,
																				type: "info",
																				variant: "tonal"
																			}, {
																				default: withCtx(() => [createTextVNode(" Products пока не привязаны. ")]),
																				_: 1
																			}))]),
																			_: 1
																		})]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																lg: "4"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VCard, null, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) {
																				_push(ssrRenderComponent(VCardTitle, null, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(`Быстрая статистика`);
																						else return [createTextVNode("Быстрая статистика")];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																				_push(ssrRenderComponent(VCardText, null, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(ssrRenderComponent(VRow, { dense: "" }, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) {
																									_push(ssrRenderComponent(VCol, { cols: "6" }, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(ssrRenderComponent(VCard, {
																												variant: "tonal",
																												class: "pa-3"
																											}, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(`<div class="text-caption text-medium-emphasis" data-v-35349ee9${_scopeId}> Цены </div><div class="text-h6" data-v-35349ee9${_scopeId}>${ssrInterpolate((goodData.value.prices || []).length)}</div>`);
																													else return [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Цены "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.prices || []).length), 1)];
																												}),
																												_: 1
																											}, _parent, _scopeId));
																											else return [createVNode(VCard, {
																												variant: "tonal",
																												class: "pa-3"
																											}, {
																												default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Цены "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.prices || []).length), 1)]),
																												_: 1
																											})];
																										}),
																										_: 1
																									}, _parent, _scopeId));
																									_push(ssrRenderComponent(VCol, { cols: "6" }, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(ssrRenderComponent(VCard, {
																												variant: "tonal",
																												class: "pa-3"
																											}, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(`<div class="text-caption text-medium-emphasis" data-v-35349ee9${_scopeId}> Продажи </div><div class="text-h6" data-v-35349ee9${_scopeId}>${ssrInterpolate((goodData.value.sales || []).length)}</div>`);
																													else return [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Продажи "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.sales || []).length), 1)];
																												}),
																												_: 1
																											}, _parent, _scopeId));
																											else return [createVNode(VCard, {
																												variant: "tonal",
																												class: "pa-3"
																											}, {
																												default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Продажи "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.sales || []).length), 1)]),
																												_: 1
																											})];
																										}),
																										_: 1
																									}, _parent, _scopeId));
																									_push(ssrRenderComponent(VCol, { cols: "6" }, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(ssrRenderComponent(VCard, {
																												variant: "tonal",
																												class: "pa-3"
																											}, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(`<div class="text-caption text-medium-emphasis" data-v-35349ee9${_scopeId}> Закупки </div><div class="text-h6" data-v-35349ee9${_scopeId}>${ssrInterpolate((goodData.value.purchases || []).length)}</div>`);
																													else return [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Закупки "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.purchases || []).length), 1)];
																												}),
																												_: 1
																											}, _parent, _scopeId));
																											else return [createVNode(VCard, {
																												variant: "tonal",
																												class: "pa-3"
																											}, {
																												default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Закупки "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.purchases || []).length), 1)]),
																												_: 1
																											})];
																										}),
																										_: 1
																									}, _parent, _scopeId));
																									_push(ssrRenderComponent(VCol, { cols: "6" }, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(ssrRenderComponent(VCard, {
																												variant: "tonal",
																												class: "pa-3"
																											}, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(`<div class="text-caption text-medium-emphasis" data-v-35349ee9${_scopeId}> Media </div><div class="text-h6" data-v-35349ee9${_scopeId}>${ssrInterpolate((goodData.value.media || []).length)}</div>`);
																													else return [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Media "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.media || []).length), 1)];
																												}),
																												_: 1
																											}, _parent, _scopeId));
																											else return [createVNode(VCard, {
																												variant: "tonal",
																												class: "pa-3"
																											}, {
																												default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Media "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.media || []).length), 1)]),
																												_: 1
																											})];
																										}),
																										_: 1
																									}, _parent, _scopeId));
																								} else return [
																									createVNode(VCol, { cols: "6" }, {
																										default: withCtx(() => [createVNode(VCard, {
																											variant: "tonal",
																											class: "pa-3"
																										}, {
																											default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Цены "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.prices || []).length), 1)]),
																											_: 1
																										})]),
																										_: 1
																									}),
																									createVNode(VCol, { cols: "6" }, {
																										default: withCtx(() => [createVNode(VCard, {
																											variant: "tonal",
																											class: "pa-3"
																										}, {
																											default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Продажи "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.sales || []).length), 1)]),
																											_: 1
																										})]),
																										_: 1
																									}),
																									createVNode(VCol, { cols: "6" }, {
																										default: withCtx(() => [createVNode(VCard, {
																											variant: "tonal",
																											class: "pa-3"
																										}, {
																											default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Закупки "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.purchases || []).length), 1)]),
																											_: 1
																										})]),
																										_: 1
																									}),
																									createVNode(VCol, { cols: "6" }, {
																										default: withCtx(() => [createVNode(VCard, {
																											variant: "tonal",
																											class: "pa-3"
																										}, {
																											default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Media "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.media || []).length), 1)]),
																											_: 1
																										})]),
																										_: 1
																									})
																								];
																							}),
																							_: 1
																						}, _parent, _scopeId));
																						else return [createVNode(VRow, { dense: "" }, {
																							default: withCtx(() => [
																								createVNode(VCol, { cols: "6" }, {
																									default: withCtx(() => [createVNode(VCard, {
																										variant: "tonal",
																										class: "pa-3"
																									}, {
																										default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Цены "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.prices || []).length), 1)]),
																										_: 1
																									})]),
																									_: 1
																								}),
																								createVNode(VCol, { cols: "6" }, {
																									default: withCtx(() => [createVNode(VCard, {
																										variant: "tonal",
																										class: "pa-3"
																									}, {
																										default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Продажи "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.sales || []).length), 1)]),
																										_: 1
																									})]),
																									_: 1
																								}),
																								createVNode(VCol, { cols: "6" }, {
																									default: withCtx(() => [createVNode(VCard, {
																										variant: "tonal",
																										class: "pa-3"
																									}, {
																										default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Закупки "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.purchases || []).length), 1)]),
																										_: 1
																									})]),
																									_: 1
																								}),
																								createVNode(VCol, { cols: "6" }, {
																									default: withCtx(() => [createVNode(VCard, {
																										variant: "tonal",
																										class: "pa-3"
																									}, {
																										default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Media "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.media || []).length), 1)]),
																										_: 1
																									})]),
																									_: 1
																								})
																							]),
																							_: 1
																						})];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																			} else return [createVNode(VCardTitle, null, {
																				default: withCtx(() => [createTextVNode("Быстрая статистика")]),
																				_: 1
																			}), createVNode(VCardText, null, {
																				default: withCtx(() => [createVNode(VRow, { dense: "" }, {
																					default: withCtx(() => [
																						createVNode(VCol, { cols: "6" }, {
																							default: withCtx(() => [createVNode(VCard, {
																								variant: "tonal",
																								class: "pa-3"
																							}, {
																								default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Цены "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.prices || []).length), 1)]),
																								_: 1
																							})]),
																							_: 1
																						}),
																						createVNode(VCol, { cols: "6" }, {
																							default: withCtx(() => [createVNode(VCard, {
																								variant: "tonal",
																								class: "pa-3"
																							}, {
																								default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Продажи "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.sales || []).length), 1)]),
																								_: 1
																							})]),
																							_: 1
																						}),
																						createVNode(VCol, { cols: "6" }, {
																							default: withCtx(() => [createVNode(VCard, {
																								variant: "tonal",
																								class: "pa-3"
																							}, {
																								default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Закупки "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.purchases || []).length), 1)]),
																								_: 1
																							})]),
																							_: 1
																						}),
																						createVNode(VCol, { cols: "6" }, {
																							default: withCtx(() => [createVNode(VCard, {
																								variant: "tonal",
																								class: "pa-3"
																							}, {
																								default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Media "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.media || []).length), 1)]),
																								_: 1
																							})]),
																							_: 1
																						})
																					]),
																					_: 1
																				})]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VCard, null, {
																		default: withCtx(() => [createVNode(VCardTitle, null, {
																			default: withCtx(() => [createTextVNode("Быстрая статистика")]),
																			_: 1
																		}), createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode(VRow, { dense: "" }, {
																				default: withCtx(() => [
																					createVNode(VCol, { cols: "6" }, {
																						default: withCtx(() => [createVNode(VCard, {
																							variant: "tonal",
																							class: "pa-3"
																						}, {
																							default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Цены "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.prices || []).length), 1)]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VCol, { cols: "6" }, {
																						default: withCtx(() => [createVNode(VCard, {
																							variant: "tonal",
																							class: "pa-3"
																						}, {
																							default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Продажи "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.sales || []).length), 1)]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VCol, { cols: "6" }, {
																						default: withCtx(() => [createVNode(VCard, {
																							variant: "tonal",
																							class: "pa-3"
																						}, {
																							default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Закупки "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.purchases || []).length), 1)]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VCol, { cols: "6" }, {
																						default: withCtx(() => [createVNode(VCard, {
																							variant: "tonal",
																							class: "pa-3"
																						}, {
																							default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Media "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.media || []).length), 1)]),
																							_: 1
																						})]),
																						_: 1
																					})
																				]),
																				_: 1
																			})]),
																			_: 1
																		})]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [
															createVNode(VCol, {
																cols: "12",
																lg: "3"
															}, {
																default: withCtx(() => [createVNode(VCard, { class: "mb-4" }, {
																	default: withCtx(() => [createVNode(VCardTitle, { class: "text-wrap" }, {
																		default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.name), 1)]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [
																			createVNode(VImg, {
																				src: goodData.value.ava_thumb || "/default-image.jpg",
																				cover: "",
																				class: "mb-3 rounded-lg",
																				"max-height": "120"
																			}, null, 8, ["src"]),
																			createVNode(VImg, {
																				src: goodData.value.ava_image || "/default-image.jpg",
																				alt: goodData.value.name,
																				"lazy-src": "/placeholder.jpg",
																				"aspect-ratio": "1",
																				cover: "",
																				class: "mb-4 rounded-lg"
																			}, null, 8, ["src", "alt"]),
																			createVNode("p", { class: "mb-0" }, toDisplayString(goodData.value.description || "Описание отсутствует"), 1)
																		]),
																		_: 1
																	})]),
																	_: 1
																}), showFormAddPrice.value ? (openBlock(), createBlock(VCard, {
																	key: 0,
																	class: "mb-4"
																}, {
																	default: withCtx(() => [createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Добавить цену")]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																			default: withCtx(() => [createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formAddPrice).price,
																						"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																						label: "Цена",
																						variant: "solo",
																						density: "comfortable",
																						type: "number",
																						color: "deep-purple"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}), createVNode(VCol, { cols: "4" }, {
																					default: withCtx(() => [createVNode(VSelect, {
																						items: currencies.value,
																						"item-value": "id",
																						"item-title": "code",
																						modelValue: unref(formAddPrice).currency_id,
																						"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																						label: "Валюта",
																						variant: "solo",
																						density: "compact",
																						clearable: ""
																					}, null, 8, [
																						"items",
																						"modelValue",
																						"onUpdate:modelValue"
																					])]),
																					_: 1
																				})]),
																				_: 1
																			}), createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																					default: withCtx(() => [createVNode(VBtn, {
																						text: "Store",
																						onClick: storePrice,
																						variant: "flat",
																						density: "comfortable",
																						block: ""
																					})]),
																					_: 1
																				})]),
																				_: 1
																			})]),
																			_: 1
																		})]),
																		_: 1
																	})]),
																	_: 1
																})) : createCommentVNode("", true)]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																lg: "5"
															}, {
																default: withCtx(() => [createVNode(VCard, { class: "mb-4" }, {
																	default: withCtx(() => [createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Кратко по товару")]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																			default: withCtx(() => [
																				createVNode(VListItem, null, {
																					prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-pound" })]),
																					default: withCtx(() => [createVNode(VListItemTitle, null, {
																						default: withCtx(() => [createTextVNode("ID")]),
																						_: 1
																					}), createVNode(VListItemSubtitle, null, {
																						default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.id), 1)]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VListItem, null, {
																					prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-link-variant" })]),
																					default: withCtx(() => [createVNode(VListItemTitle, null, {
																						default: withCtx(() => [createTextVNode("Slug")]),
																						_: 1
																					}), createVNode(VListItemSubtitle, null, {
																						default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.slug || "—"), 1)]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VListItem, null, {
																					prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-package-variant" })]),
																					default: withCtx(() => [createVNode(VListItemTitle, null, {
																						default: withCtx(() => [createTextVNode("Denominator")]),
																						_: 1
																					}), createVNode(VListItemSubtitle, null, {
																						default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.denominator || "—"), 1)]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VListItem, null, {
																					prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-percent" })]),
																					default: withCtx(() => [createVNode(VListItemTitle, null, {
																						default: withCtx(() => [createTextVNode("VAT")]),
																						_: 1
																					}), createVNode(VListItemSubtitle, null, {
																						default: withCtx(() => [currentVatRate.value ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)) : (openBlock(), createBlock("span", { key: 1 }, " — "))]),
																						_: 1
																					})]),
																					_: 1
																				})
																			]),
																			_: 1
																		})]),
																		_: 1
																	})]),
																	_: 1
																}), createVNode(VCard, null, {
																	default: withCtx(() => [createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Products")]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [(goodData.value.products || []).length ? (openBlock(true), createBlock(Fragment, { key: 0 }, renderList(goodData.value.products || [], (product) => {
																			return openBlock(), createBlock(VChip, {
																				key: product.id,
																				class: "ma-1",
																				size: "small",
																				variant: "tonal",
																				color: "teal"
																			}, {
																				default: withCtx(() => [createTextVNode(toDisplayString(product.rus || product.name || product.id), 1)]),
																				_: 2
																			}, 1024);
																		}), 128)) : (openBlock(), createBlock(VAlert, {
																			key: 1,
																			type: "info",
																			variant: "tonal"
																		}, {
																			default: withCtx(() => [createTextVNode(" Products пока не привязаны. ")]),
																			_: 1
																		}))]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																lg: "4"
															}, {
																default: withCtx(() => [createVNode(VCard, null, {
																	default: withCtx(() => [createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Быстрая статистика")]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VRow, { dense: "" }, {
																			default: withCtx(() => [
																				createVNode(VCol, { cols: "6" }, {
																					default: withCtx(() => [createVNode(VCard, {
																						variant: "tonal",
																						class: "pa-3"
																					}, {
																						default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Цены "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.prices || []).length), 1)]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VCol, { cols: "6" }, {
																					default: withCtx(() => [createVNode(VCard, {
																						variant: "tonal",
																						class: "pa-3"
																					}, {
																						default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Продажи "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.sales || []).length), 1)]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VCol, { cols: "6" }, {
																					default: withCtx(() => [createVNode(VCard, {
																						variant: "tonal",
																						class: "pa-3"
																					}, {
																						default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Закупки "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.purchases || []).length), 1)]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VCol, { cols: "6" }, {
																					default: withCtx(() => [createVNode(VCard, {
																						variant: "tonal",
																						class: "pa-3"
																					}, {
																						default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Media "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.media || []).length), 1)]),
																						_: 1
																					})]),
																					_: 1
																				})
																			]),
																			_: 1
																		})]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															})
														];
													}),
													_: 1
												}, _parent, _scopeId));
												else return [createVNode(VRow, null, {
													default: withCtx(() => [
														createVNode(VCol, {
															cols: "12",
															lg: "3"
														}, {
															default: withCtx(() => [createVNode(VCard, { class: "mb-4" }, {
																default: withCtx(() => [createVNode(VCardTitle, { class: "text-wrap" }, {
																	default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.name), 1)]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [
																		createVNode(VImg, {
																			src: goodData.value.ava_thumb || "/default-image.jpg",
																			cover: "",
																			class: "mb-3 rounded-lg",
																			"max-height": "120"
																		}, null, 8, ["src"]),
																		createVNode(VImg, {
																			src: goodData.value.ava_image || "/default-image.jpg",
																			alt: goodData.value.name,
																			"lazy-src": "/placeholder.jpg",
																			"aspect-ratio": "1",
																			cover: "",
																			class: "mb-4 rounded-lg"
																		}, null, 8, ["src", "alt"]),
																		createVNode("p", { class: "mb-0" }, toDisplayString(goodData.value.description || "Описание отсутствует"), 1)
																	]),
																	_: 1
																})]),
																_: 1
															}), showFormAddPrice.value ? (openBlock(), createBlock(VCard, {
																key: 0,
																class: "mb-4"
															}, {
																default: withCtx(() => [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Добавить цену")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formAddPrice).price,
																					"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																					label: "Цена",
																					variant: "solo",
																					density: "comfortable",
																					type: "number",
																					color: "deep-purple"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}), createVNode(VCol, { cols: "4" }, {
																				default: withCtx(() => [createVNode(VSelect, {
																					items: currencies.value,
																					"item-value": "id",
																					"item-title": "code",
																					modelValue: unref(formAddPrice).currency_id,
																					"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																					label: "Валюта",
																					variant: "solo",
																					density: "compact",
																					clearable: ""
																				}, null, 8, [
																					"items",
																					"modelValue",
																					"onUpdate:modelValue"
																				])]),
																				_: 1
																			})]),
																			_: 1
																		}), createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																				default: withCtx(() => [createVNode(VBtn, {
																					text: "Store",
																					onClick: storePrice,
																					variant: "flat",
																					density: "comfortable",
																					block: ""
																				})]),
																				_: 1
																			})]),
																			_: 1
																		})]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															})) : createCommentVNode("", true)]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															lg: "5"
														}, {
															default: withCtx(() => [createVNode(VCard, { class: "mb-4" }, {
																default: withCtx(() => [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Кратко по товару")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																		default: withCtx(() => [
																			createVNode(VListItem, null, {
																				prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-pound" })]),
																				default: withCtx(() => [createVNode(VListItemTitle, null, {
																					default: withCtx(() => [createTextVNode("ID")]),
																					_: 1
																				}), createVNode(VListItemSubtitle, null, {
																					default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.id), 1)]),
																					_: 1
																				})]),
																				_: 1
																			}),
																			createVNode(VListItem, null, {
																				prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-link-variant" })]),
																				default: withCtx(() => [createVNode(VListItemTitle, null, {
																					default: withCtx(() => [createTextVNode("Slug")]),
																					_: 1
																				}), createVNode(VListItemSubtitle, null, {
																					default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.slug || "—"), 1)]),
																					_: 1
																				})]),
																				_: 1
																			}),
																			createVNode(VListItem, null, {
																				prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-package-variant" })]),
																				default: withCtx(() => [createVNode(VListItemTitle, null, {
																					default: withCtx(() => [createTextVNode("Denominator")]),
																					_: 1
																				}), createVNode(VListItemSubtitle, null, {
																					default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.denominator || "—"), 1)]),
																					_: 1
																				})]),
																				_: 1
																			}),
																			createVNode(VListItem, null, {
																				prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-percent" })]),
																				default: withCtx(() => [createVNode(VListItemTitle, null, {
																					default: withCtx(() => [createTextVNode("VAT")]),
																					_: 1
																				}), createVNode(VListItemSubtitle, null, {
																					default: withCtx(() => [currentVatRate.value ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)) : (openBlock(), createBlock("span", { key: 1 }, " — "))]),
																					_: 1
																				})]),
																				_: 1
																			})
																		]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															}), createVNode(VCard, null, {
																default: withCtx(() => [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Products")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [(goodData.value.products || []).length ? (openBlock(true), createBlock(Fragment, { key: 0 }, renderList(goodData.value.products || [], (product) => {
																		return openBlock(), createBlock(VChip, {
																			key: product.id,
																			class: "ma-1",
																			size: "small",
																			variant: "tonal",
																			color: "teal"
																		}, {
																			default: withCtx(() => [createTextVNode(toDisplayString(product.rus || product.name || product.id), 1)]),
																			_: 2
																		}, 1024);
																	}), 128)) : (openBlock(), createBlock(VAlert, {
																		key: 1,
																		type: "info",
																		variant: "tonal"
																	}, {
																		default: withCtx(() => [createTextVNode(" Products пока не привязаны. ")]),
																		_: 1
																	}))]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															lg: "4"
														}, {
															default: withCtx(() => [createVNode(VCard, null, {
																default: withCtx(() => [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Быстрая статистика")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VRow, { dense: "" }, {
																		default: withCtx(() => [
																			createVNode(VCol, { cols: "6" }, {
																				default: withCtx(() => [createVNode(VCard, {
																					variant: "tonal",
																					class: "pa-3"
																				}, {
																					default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Цены "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.prices || []).length), 1)]),
																					_: 1
																				})]),
																				_: 1
																			}),
																			createVNode(VCol, { cols: "6" }, {
																				default: withCtx(() => [createVNode(VCard, {
																					variant: "tonal",
																					class: "pa-3"
																				}, {
																					default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Продажи "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.sales || []).length), 1)]),
																					_: 1
																				})]),
																				_: 1
																			}),
																			createVNode(VCol, { cols: "6" }, {
																				default: withCtx(() => [createVNode(VCard, {
																					variant: "tonal",
																					class: "pa-3"
																				}, {
																					default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Закупки "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.purchases || []).length), 1)]),
																					_: 1
																				})]),
																				_: 1
																			}),
																			createVNode(VCol, { cols: "6" }, {
																				default: withCtx(() => [createVNode(VCard, {
																					variant: "tonal",
																					class: "pa-3"
																				}, {
																					default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Media "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.media || []).length), 1)]),
																					_: 1
																				})]),
																				_: 1
																			})
																		]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})
													]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(ssrRenderComponent(VWindowItem, { value: "quotations" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VCard, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(`<span data-v-35349ee9${_scopeId}>Quotations</span>`);
																		_push(ssrRenderComponent(VBtn, {
																			text: "+ Q",
																			color: "indigo",
																			variant: "tonal",
																			density: "compact",
																			onClick: ($event) => dialogFormQuotation.value = true
																		}, null, _parent, _scopeId));
																	} else return [createVNode("span", null, "Quotations"), createVNode(VBtn, {
																		text: "+ Q",
																		color: "indigo",
																		variant: "tonal",
																		density: "compact",
																		onClick: ($event) => dialogFormQuotation.value = true
																	}, null, 8, ["onClick"])];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCardText, { class: "pa-0" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VDataTable, {
																		items: goodData.value.quotations || [],
																		headers: headerQuotations,
																		"items-per-page": "100",
																		"fixed-header": "",
																		height: "620px",
																		density: "compact",
																		class: "border rounded",
																		hover: ""
																	}, {
																		"item.denominator": withCtx(({ item }, _push, _parent, _scopeId) => {
																			if (_push) _push(`<span data-v-35349ee9${_scopeId}>${ssrInterpolate(item.denominator || 1)}</span>`);
																			else return [createVNode("span", null, toDisplayString(item.denominator || 1), 1)];
																		}),
																		"item.created_at": withCtx(({ item }, _push, _parent, _scopeId) => {
																			if (_push) _push(`<span data-v-35349ee9${_scopeId}>${ssrInterpolate(formatDate(item.created_at))}</span>`);
																			else return [createVNode("span", null, toDisplayString(formatDate(item.created_at)), 1)];
																		}),
																		"item.price": withCtx(({ item }, _push, _parent, _scopeId) => {
																			if (_push) _push(`<span data-v-35349ee9${_scopeId}>${ssrInterpolate(formatMoney(item.price))}</span>`);
																			else return [createVNode("span", null, toDisplayString(formatMoney(item.price)), 1)];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VDataTable, {
																		items: goodData.value.quotations || [],
																		headers: headerQuotations,
																		"items-per-page": "100",
																		"fixed-header": "",
																		height: "620px",
																		density: "compact",
																		class: "border rounded",
																		hover: ""
																	}, {
																		"item.denominator": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.denominator || 1), 1)]),
																		"item.created_at": withCtx(({ item }) => [createVNode("span", null, toDisplayString(formatDate(item.created_at)), 1)]),
																		"item.price": withCtx(({ item }) => [createVNode("span", null, toDisplayString(formatMoney(item.price)), 1)]),
																		_: 1
																	}, 8, ["items"])];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
															default: withCtx(() => [createVNode("span", null, "Quotations"), createVNode(VBtn, {
																text: "+ Q",
																color: "indigo",
																variant: "tonal",
																density: "compact",
																onClick: ($event) => dialogFormQuotation.value = true
															}, null, 8, ["onClick"])]),
															_: 1
														}), createVNode(VCardText, { class: "pa-0" }, {
															default: withCtx(() => [createVNode(VDataTable, {
																items: goodData.value.quotations || [],
																headers: headerQuotations,
																"items-per-page": "100",
																"fixed-header": "",
																height: "620px",
																density: "compact",
																class: "border rounded",
																hover: ""
															}, {
																"item.denominator": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.denominator || 1), 1)]),
																"item.created_at": withCtx(({ item }) => [createVNode("span", null, toDisplayString(formatDate(item.created_at)), 1)]),
																"item.price": withCtx(({ item }) => [createVNode("span", null, toDisplayString(formatMoney(item.price)), 1)]),
																_: 1
															}, 8, ["items"])]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												else return [createVNode(VCard, null, {
													default: withCtx(() => [createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
														default: withCtx(() => [createVNode("span", null, "Quotations"), createVNode(VBtn, {
															text: "+ Q",
															color: "indigo",
															variant: "tonal",
															density: "compact",
															onClick: ($event) => dialogFormQuotation.value = true
														}, null, 8, ["onClick"])]),
														_: 1
													}), createVNode(VCardText, { class: "pa-0" }, {
														default: withCtx(() => [createVNode(VDataTable, {
															items: goodData.value.quotations || [],
															headers: headerQuotations,
															"items-per-page": "100",
															"fixed-header": "",
															height: "620px",
															density: "compact",
															class: "border rounded",
															hover: ""
														}, {
															"item.denominator": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.denominator || 1), 1)]),
															"item.created_at": withCtx(({ item }) => [createVNode("span", null, toDisplayString(formatDate(item.created_at)), 1)]),
															"item.price": withCtx(({ item }) => [createVNode("span", null, toDisplayString(formatMoney(item.price)), 1)]),
															_: 1
														}, 8, ["items"])]),
														_: 1
													})]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(ssrRenderComponent(VWindowItem, { value: "purchases" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VCard, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCardTitle, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`Закупки данного товара`);
																	else return [createTextVNode("Закупки данного товара")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCardText, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VAlert, {
																			type: "info",
																			variant: "tonal",
																			class: "mb-4"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(` Здесь показываются закупки, связанные с этим good через pivot-таблицу <strong data-v-35349ee9${_scopeId}>good_purchase</strong>. На следующем этапе добавим автоматический расчёт продажной цены от закупки. `);
																				else return [
																					createTextVNode(" Здесь показываются закупки, связанные с этим good через pivot-таблицу "),
																					createVNode("strong", null, "good_purchase"),
																					createTextVNode(". На следующем этапе добавим автоматический расчёт продажной цены от закупки. ")
																				];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VDataTable, {
																			items: goodData.value.purchases || [],
																			headers: headerPurchases,
																			"items-per-page": "50",
																			"fixed-header": "",
																			height: "560px",
																			density: "compact",
																			class: "border rounded",
																			hover: ""
																		}, {
																			"item.date": withCtx(({ item }, _push, _parent, _scopeId) => {
																				if (_push) _push(`${ssrInterpolate(item.date || "-")}`);
																				else return [createTextVNode(toDisplayString(item.date || "-"), 1)];
																			}),
																			"item.pivot.quantity": withCtx(({ item }, _push, _parent, _scopeId) => {
																				if (_push) _push(`${ssrInterpolate(item.pivot?.quantity || "—")}`);
																				else return [createTextVNode(toDisplayString(item.pivot?.quantity || "—"), 1)];
																			}),
																			"item.pivot.price": withCtx(({ item }, _push, _parent, _scopeId) => {
																				if (_push) _push(`<strong data-v-35349ee9${_scopeId}>${ssrInterpolate(formatMoney(item.pivot?.price))}</strong><span class="text-caption ml-1" data-v-35349ee9${_scopeId}>${ssrInterpolate(purchaseCurrencyCode(item))}</span>`);
																				else return [createVNode("strong", null, toDisplayString(formatMoney(item.pivot?.price)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(purchaseCurrencyCode(item)), 1)];
																			}),
																			"item.pivot.total": withCtx(({ item }, _push, _parent, _scopeId) => {
																				if (_push) _push(`<strong data-v-35349ee9${_scopeId}>${ssrInterpolate(formatMoney(item.pivot?.total))}</strong><span class="text-caption ml-1" data-v-35349ee9${_scopeId}>${ssrInterpolate(purchaseCurrencyCode(item))}</span>`);
																				else return [createVNode("strong", null, toDisplayString(formatMoney(item.pivot?.total)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(purchaseCurrencyCode(item)), 1)];
																			}),
																			"no-data": withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`<div class="pa-6 text-center text-medium-emphasis" data-v-35349ee9${_scopeId}> Закупок по этому товару пока нет. </div>`);
																				else return [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Закупок по этому товару пока нет. ")];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																	} else return [createVNode(VAlert, {
																		type: "info",
																		variant: "tonal",
																		class: "mb-4"
																	}, {
																		default: withCtx(() => [
																			createTextVNode(" Здесь показываются закупки, связанные с этим good через pivot-таблицу "),
																			createVNode("strong", null, "good_purchase"),
																			createTextVNode(". На следующем этапе добавим автоматический расчёт продажной цены от закупки. ")
																		]),
																		_: 1
																	}), createVNode(VDataTable, {
																		items: goodData.value.purchases || [],
																		headers: headerPurchases,
																		"items-per-page": "50",
																		"fixed-header": "",
																		height: "560px",
																		density: "compact",
																		class: "border rounded",
																		hover: ""
																	}, {
																		"item.date": withCtx(({ item }) => [createTextVNode(toDisplayString(item.date || "-"), 1)]),
																		"item.pivot.quantity": withCtx(({ item }) => [createTextVNode(toDisplayString(item.pivot?.quantity || "—"), 1)]),
																		"item.pivot.price": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.pivot?.price)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(purchaseCurrencyCode(item)), 1)]),
																		"item.pivot.total": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.pivot?.total)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(purchaseCurrencyCode(item)), 1)]),
																		"no-data": withCtx(() => [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Закупок по этому товару пока нет. ")]),
																		_: 1
																	}, 8, ["items"])];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Закупки данного товара")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VAlert, {
																type: "info",
																variant: "tonal",
																class: "mb-4"
															}, {
																default: withCtx(() => [
																	createTextVNode(" Здесь показываются закупки, связанные с этим good через pivot-таблицу "),
																	createVNode("strong", null, "good_purchase"),
																	createTextVNode(". На следующем этапе добавим автоматический расчёт продажной цены от закупки. ")
																]),
																_: 1
															}), createVNode(VDataTable, {
																items: goodData.value.purchases || [],
																headers: headerPurchases,
																"items-per-page": "50",
																"fixed-header": "",
																height: "560px",
																density: "compact",
																class: "border rounded",
																hover: ""
															}, {
																"item.date": withCtx(({ item }) => [createTextVNode(toDisplayString(item.date || "-"), 1)]),
																"item.pivot.quantity": withCtx(({ item }) => [createTextVNode(toDisplayString(item.pivot?.quantity || "—"), 1)]),
																"item.pivot.price": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.pivot?.price)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(purchaseCurrencyCode(item)), 1)]),
																"item.pivot.total": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.pivot?.total)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(purchaseCurrencyCode(item)), 1)]),
																"no-data": withCtx(() => [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Закупок по этому товару пока нет. ")]),
																_: 1
															}, 8, ["items"])]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												else return [createVNode(VCard, null, {
													default: withCtx(() => [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Закупки данного товара")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VAlert, {
															type: "info",
															variant: "tonal",
															class: "mb-4"
														}, {
															default: withCtx(() => [
																createTextVNode(" Здесь показываются закупки, связанные с этим good через pivot-таблицу "),
																createVNode("strong", null, "good_purchase"),
																createTextVNode(". На следующем этапе добавим автоматический расчёт продажной цены от закупки. ")
															]),
															_: 1
														}), createVNode(VDataTable, {
															items: goodData.value.purchases || [],
															headers: headerPurchases,
															"items-per-page": "50",
															"fixed-header": "",
															height: "560px",
															density: "compact",
															class: "border rounded",
															hover: ""
														}, {
															"item.date": withCtx(({ item }) => [createTextVNode(toDisplayString(item.date || "-"), 1)]),
															"item.pivot.quantity": withCtx(({ item }) => [createTextVNode(toDisplayString(item.pivot?.quantity || "—"), 1)]),
															"item.pivot.price": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.pivot?.price)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(purchaseCurrencyCode(item)), 1)]),
															"item.pivot.total": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.pivot?.total)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(purchaseCurrencyCode(item)), 1)]),
															"no-data": withCtx(() => [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Закупок по этому товару пока нет. ")]),
															_: 1
														}, 8, ["items"])]),
														_: 1
													})]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(ssrRenderComponent(VWindowItem, { value: "calculator" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(_sfc_main$6, {
													"good-id": goodData.value.id,
													quotations: goodData.value.quotations || [],
													"default-vat-rate": defaultVatRate.value,
													"default-box-weight-kg": goodBoxWeight.value,
													"currency-code": "RUB",
													onSaved: ($event) => activeTab.value = "calculations"
												}, null, _parent, _scopeId));
												else return [createVNode(_sfc_main$6, {
													"good-id": goodData.value.id,
													quotations: goodData.value.quotations || [],
													"default-vat-rate": defaultVatRate.value,
													"default-box-weight-kg": goodBoxWeight.value,
													"currency-code": "RUB",
													onSaved: ($event) => activeTab.value = "calculations"
												}, null, 8, [
													"good-id",
													"quotations",
													"default-vat-rate",
													"default-box-weight-kg",
													"onSaved"
												])];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(ssrRenderComponent(VWindowItem, { value: "calculations" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(_sfc_main$4, {
													"good-id": goodData.value.id,
													onApplied: ($event) => activeTab.value = "price-values"
												}, null, _parent, _scopeId));
												else return [createVNode(_sfc_main$4, {
													"good-id": goodData.value.id,
													onApplied: ($event) => activeTab.value = "price-values"
												}, null, 8, ["good-id", "onApplied"])];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(ssrRenderComponent(VWindowItem, { value: "price-types" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(_sfc_main$3, { currencies: currencies.value }, null, _parent, _scopeId));
												else return [createVNode(_sfc_main$3, { currencies: currencies.value }, null, 8, ["currencies"])];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(ssrRenderComponent(VWindowItem, { value: "price-values" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(_sfc_main$2, {
													"good-id": goodData.value.id,
													currencies: currencies.value
												}, null, _parent, _scopeId));
												else return [createVNode(_sfc_main$2, {
													"good-id": goodData.value.id,
													currencies: currencies.value
												}, null, 8, ["good-id", "currencies"])];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(ssrRenderComponent(VWindowItem, { value: "media" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(GoodMediaTab_default, {
													good: goodData.value,
													onChanged: fetchGood,
													onAvaUpdated: fetchGood
												}, null, _parent, _scopeId));
												else return [createVNode(GoodMediaTab_default, {
													good: goodData.value,
													onChanged: fetchGood,
													onAvaUpdated: fetchGood
												}, null, 8, ["good"])];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(ssrRenderComponent(VWindowItem, { value: "seo" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(_sfc_main$5, { good: goodData.value }, null, _parent, _scopeId));
												else return [createVNode(_sfc_main$5, { good: goodData.value }, null, 8, ["good"])];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(ssrRenderComponent(VWindowItem, { value: "sales" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VCard, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCardTitle, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`Продажи`);
																	else return [createTextVNode("Продажи")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCardText, { class: "pa-0" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VDataTable, {
																		items: goodData.value.sales || [],
																		headers: headerSales,
																		"items-per-page": "100",
																		"fixed-header": "",
																		height: "620px",
																		density: "comfortable",
																		hover: ""
																	}, {
																		"item.date": withCtx(({ item }, _push, _parent, _scopeId) => {
																			if (_push) _push(`<span class="text-xs" data-v-35349ee9${_scopeId}>${ssrInterpolate(item.date)}</span>`);
																			else return [createVNode("span", { class: "text-xs" }, toDisplayString(item.date), 1)];
																		}),
																		"item.pivot.price": withCtx(({ item }, _push, _parent, _scopeId) => {
																			if (_push) _push(`<span class="font-weight-bold" data-v-35349ee9${_scopeId}>${ssrInterpolate(formatMoney(item.pivot?.price))}</span>`);
																			else return [createVNode("span", { class: "font-weight-bold" }, toDisplayString(formatMoney(item.pivot?.price)), 1)];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VDataTable, {
																		items: goodData.value.sales || [],
																		headers: headerSales,
																		"items-per-page": "100",
																		"fixed-header": "",
																		height: "620px",
																		density: "comfortable",
																		hover: ""
																	}, {
																		"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.date), 1)]),
																		"item.pivot.price": withCtx(({ item }) => [createVNode("span", { class: "font-weight-bold" }, toDisplayString(formatMoney(item.pivot?.price)), 1)]),
																		_: 1
																	}, 8, ["items"])];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Продажи")]),
															_: 1
														}), createVNode(VCardText, { class: "pa-0" }, {
															default: withCtx(() => [createVNode(VDataTable, {
																items: goodData.value.sales || [],
																headers: headerSales,
																"items-per-page": "100",
																"fixed-header": "",
																height: "620px",
																density: "comfortable",
																hover: ""
															}, {
																"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.date), 1)]),
																"item.pivot.price": withCtx(({ item }) => [createVNode("span", { class: "font-weight-bold" }, toDisplayString(formatMoney(item.pivot?.price)), 1)]),
																_: 1
															}, 8, ["items"])]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												else return [createVNode(VCard, null, {
													default: withCtx(() => [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Продажи")]),
														_: 1
													}), createVNode(VCardText, { class: "pa-0" }, {
														default: withCtx(() => [createVNode(VDataTable, {
															items: goodData.value.sales || [],
															headers: headerSales,
															"items-per-page": "100",
															"fixed-header": "",
															height: "620px",
															density: "comfortable",
															hover: ""
														}, {
															"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.date), 1)]),
															"item.pivot.price": withCtx(({ item }) => [createVNode("span", { class: "font-weight-bold" }, toDisplayString(formatMoney(item.pivot?.price)), 1)]),
															_: 1
														}, 8, ["items"])]),
														_: 1
													})]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(ssrRenderComponent(VWindowItem, { value: "price-history" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VCard, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(`<span data-v-35349ee9${_scopeId}>История цен</span>`);
																		_push(ssrRenderComponent(VBtn, {
																			text: "+ 💵",
																			color: "pink-darken-4",
																			variant: "tonal",
																			density: "compact",
																			onClick: ($event) => showFormAddPrice.value = !showFormAddPrice.value
																		}, null, _parent, _scopeId));
																	} else return [createVNode("span", null, "История цен"), createVNode(VBtn, {
																		text: "+ 💵",
																		color: "pink-darken-4",
																		variant: "tonal",
																		density: "compact",
																		onClick: ($event) => showFormAddPrice.value = !showFormAddPrice.value
																	}, null, 8, ["onClick"])];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCardText, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		if (showFormAddPrice.value) _push(ssrRenderComponent(VCard, {
																			variant: "tonal",
																			class: "mb-4"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(ssrRenderComponent(VCardTitle, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`Добавить цену`);
																							else return [createTextVNode("Добавить цену")];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCardText, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(ssrRenderComponent(VForm, { onSubmit: storePrice }, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(ssrRenderComponent(VRow, null, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) {
																												_push(ssrRenderComponent(VCol, {
																													cols: "12",
																													md: "8"
																												}, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) _push(ssrRenderComponent(VTextField, {
																															modelValue: unref(formAddPrice).price,
																															"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																															label: "Цена",
																															variant: "solo",
																															density: "comfortable",
																															type: "number",
																															color: "deep-purple"
																														}, null, _parent, _scopeId));
																														else return [createVNode(VTextField, {
																															modelValue: unref(formAddPrice).price,
																															"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																															label: "Цена",
																															variant: "solo",
																															density: "comfortable",
																															type: "number",
																															color: "deep-purple"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																													}),
																													_: 1
																												}, _parent, _scopeId));
																												_push(ssrRenderComponent(VCol, {
																													cols: "12",
																													md: "4"
																												}, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) _push(ssrRenderComponent(VSelect, {
																															items: currencies.value,
																															"item-value": "id",
																															"item-title": "code",
																															modelValue: unref(formAddPrice).currency_id,
																															"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																															label: "Валюта",
																															variant: "solo",
																															density: "compact",
																															clearable: ""
																														}, null, _parent, _scopeId));
																														else return [createVNode(VSelect, {
																															items: currencies.value,
																															"item-value": "id",
																															"item-title": "code",
																															modelValue: unref(formAddPrice).currency_id,
																															"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																															label: "Валюта",
																															variant: "solo",
																															density: "compact",
																															clearable: ""
																														}, null, 8, [
																															"items",
																															"modelValue",
																															"onUpdate:modelValue"
																														])];
																													}),
																													_: 1
																												}, _parent, _scopeId));
																												_push(ssrRenderComponent(VCol, { cols: "12" }, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) _push(ssrRenderComponent(VBtn, {
																															text: "Store",
																															onClick: storePrice,
																															variant: "flat",
																															density: "comfortable"
																														}, null, _parent, _scopeId));
																														else return [createVNode(VBtn, {
																															text: "Store",
																															onClick: storePrice,
																															variant: "flat",
																															density: "comfortable"
																														})];
																													}),
																													_: 1
																												}, _parent, _scopeId));
																											} else return [
																												createVNode(VCol, {
																													cols: "12",
																													md: "8"
																												}, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formAddPrice).price,
																														"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																														label: "Цена",
																														variant: "solo",
																														density: "comfortable",
																														type: "number",
																														color: "deep-purple"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												}),
																												createVNode(VCol, {
																													cols: "12",
																													md: "4"
																												}, {
																													default: withCtx(() => [createVNode(VSelect, {
																														items: currencies.value,
																														"item-value": "id",
																														"item-title": "code",
																														modelValue: unref(formAddPrice).currency_id,
																														"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																														label: "Валюта",
																														variant: "solo",
																														density: "compact",
																														clearable: ""
																													}, null, 8, [
																														"items",
																														"modelValue",
																														"onUpdate:modelValue"
																													])]),
																													_: 1
																												}),
																												createVNode(VCol, { cols: "12" }, {
																													default: withCtx(() => [createVNode(VBtn, {
																														text: "Store",
																														onClick: storePrice,
																														variant: "flat",
																														density: "comfortable"
																													})]),
																													_: 1
																												})
																											];
																										}),
																										_: 1
																									}, _parent, _scopeId));
																									else return [createVNode(VRow, null, {
																										default: withCtx(() => [
																											createVNode(VCol, {
																												cols: "12",
																												md: "8"
																											}, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formAddPrice).price,
																													"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																													label: "Цена",
																													variant: "solo",
																													density: "comfortable",
																													type: "number",
																													color: "deep-purple"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											}),
																											createVNode(VCol, {
																												cols: "12",
																												md: "4"
																											}, {
																												default: withCtx(() => [createVNode(VSelect, {
																													items: currencies.value,
																													"item-value": "id",
																													"item-title": "code",
																													modelValue: unref(formAddPrice).currency_id,
																													"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																													label: "Валюта",
																													variant: "solo",
																													density: "compact",
																													clearable: ""
																												}, null, 8, [
																													"items",
																													"modelValue",
																													"onUpdate:modelValue"
																												])]),
																												_: 1
																											}),
																											createVNode(VCol, { cols: "12" }, {
																												default: withCtx(() => [createVNode(VBtn, {
																													text: "Store",
																													onClick: storePrice,
																													variant: "flat",
																													density: "comfortable"
																												})]),
																												_: 1
																											})
																										]),
																										_: 1
																									})];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																							else return [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																								default: withCtx(() => [createVNode(VRow, null, {
																									default: withCtx(() => [
																										createVNode(VCol, {
																											cols: "12",
																											md: "8"
																										}, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formAddPrice).price,
																												"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																												label: "Цена",
																												variant: "solo",
																												density: "comfortable",
																												type: "number",
																												color: "deep-purple"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										}),
																										createVNode(VCol, {
																											cols: "12",
																											md: "4"
																										}, {
																											default: withCtx(() => [createVNode(VSelect, {
																												items: currencies.value,
																												"item-value": "id",
																												"item-title": "code",
																												modelValue: unref(formAddPrice).currency_id,
																												"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																												label: "Валюта",
																												variant: "solo",
																												density: "compact",
																												clearable: ""
																											}, null, 8, [
																												"items",
																												"modelValue",
																												"onUpdate:modelValue"
																											])]),
																											_: 1
																										}),
																										createVNode(VCol, { cols: "12" }, {
																											default: withCtx(() => [createVNode(VBtn, {
																												text: "Store",
																												onClick: storePrice,
																												variant: "flat",
																												density: "comfortable"
																											})]),
																											_: 1
																										})
																									]),
																									_: 1
																								})]),
																								_: 1
																							})];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																				} else return [createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Добавить цену")]),
																					_: 1
																				}), createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																						default: withCtx(() => [createVNode(VRow, null, {
																							default: withCtx(() => [
																								createVNode(VCol, {
																									cols: "12",
																									md: "8"
																								}, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formAddPrice).price,
																										"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																										label: "Цена",
																										variant: "solo",
																										density: "comfortable",
																										type: "number",
																										color: "deep-purple"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}),
																								createVNode(VCol, {
																									cols: "12",
																									md: "4"
																								}, {
																									default: withCtx(() => [createVNode(VSelect, {
																										items: currencies.value,
																										"item-value": "id",
																										"item-title": "code",
																										modelValue: unref(formAddPrice).currency_id,
																										"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																										label: "Валюта",
																										variant: "solo",
																										density: "compact",
																										clearable: ""
																									}, null, 8, [
																										"items",
																										"modelValue",
																										"onUpdate:modelValue"
																									])]),
																									_: 1
																								}),
																								createVNode(VCol, { cols: "12" }, {
																									default: withCtx(() => [createVNode(VBtn, {
																										text: "Store",
																										onClick: storePrice,
																										variant: "flat",
																										density: "comfortable"
																									})]),
																									_: 1
																								})
																							]),
																							_: 1
																						})]),
																						_: 1
																					})]),
																					_: 1
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		else _push(`<!---->`);
																		_push(ssrRenderComponent(VList, {
																			border: "",
																			rounded: ""
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(`<!--[-->`);
																					ssrRenderList(goodData.value.prices || [], (price) => {
																						_push(ssrRenderComponent(VListItem, { key: price.id }, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VRow, null, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) {
																											_push(ssrRenderComponent(VCol, {
																												cols: "12",
																												md: "3"
																											}, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(`<span class="text-caption font-mono" data-v-35349ee9${_scopeId}>${ssrInterpolate(formatDateTime(price.created_at))}</span>`);
																													else return [createVNode("span", { class: "text-caption font-mono" }, toDisplayString(formatDateTime(price.created_at)), 1)];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																											_push(ssrRenderComponent(VCol, {
																												cols: "12",
																												md: "3"
																											}, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(`<strong data-v-35349ee9${_scopeId}>${ssrInterpolate(formatMoney(price.price))}</strong>`);
																													else return [createVNode("strong", null, toDisplayString(formatMoney(price.price)), 1)];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																											_push(ssrRenderComponent(VCol, {
																												cols: "12",
																												md: "2"
																											}, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(`<span data-v-35349ee9${_scopeId}>${ssrInterpolate(price.currency?.code || "-")}</span>`);
																													else return [createVNode("span", null, toDisplayString(price.currency?.code || "-"), 1)];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																										} else return [
																											createVNode(VCol, {
																												cols: "12",
																												md: "3"
																											}, {
																												default: withCtx(() => [createVNode("span", { class: "text-caption font-mono" }, toDisplayString(formatDateTime(price.created_at)), 1)]),
																												_: 2
																											}, 1024),
																											createVNode(VCol, {
																												cols: "12",
																												md: "3"
																											}, {
																												default: withCtx(() => [createVNode("strong", null, toDisplayString(formatMoney(price.price)), 1)]),
																												_: 2
																											}, 1024),
																											createVNode(VCol, {
																												cols: "12",
																												md: "2"
																											}, {
																												default: withCtx(() => [createVNode("span", null, toDisplayString(price.currency?.code || "-"), 1)]),
																												_: 2
																											}, 1024)
																										];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								else return [createVNode(VRow, null, {
																									default: withCtx(() => [
																										createVNode(VCol, {
																											cols: "12",
																											md: "3"
																										}, {
																											default: withCtx(() => [createVNode("span", { class: "text-caption font-mono" }, toDisplayString(formatDateTime(price.created_at)), 1)]),
																											_: 2
																										}, 1024),
																										createVNode(VCol, {
																											cols: "12",
																											md: "3"
																										}, {
																											default: withCtx(() => [createVNode("strong", null, toDisplayString(formatMoney(price.price)), 1)]),
																											_: 2
																										}, 1024),
																										createVNode(VCol, {
																											cols: "12",
																											md: "2"
																										}, {
																											default: withCtx(() => [createVNode("span", null, toDisplayString(price.currency?.code || "-"), 1)]),
																											_: 2
																										}, 1024)
																									]),
																									_: 2
																								}, 1024)];
																							}),
																							_: 2
																						}, _parent, _scopeId));
																					});
																					_push(`<!--]-->`);
																					if (!(goodData.value.prices || []).length) _push(ssrRenderComponent(VListItem, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`<span class="text-medium-emphasis" data-v-35349ee9${_scopeId}> Цен пока нет </span>`);
																							else return [createVNode("span", { class: "text-medium-emphasis" }, " Цен пока нет ")];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																					else _push(`<!---->`);
																				} else return [(openBlock(true), createBlock(Fragment, null, renderList(goodData.value.prices || [], (price) => {
																					return openBlock(), createBlock(VListItem, { key: price.id }, {
																						default: withCtx(() => [createVNode(VRow, null, {
																							default: withCtx(() => [
																								createVNode(VCol, {
																									cols: "12",
																									md: "3"
																								}, {
																									default: withCtx(() => [createVNode("span", { class: "text-caption font-mono" }, toDisplayString(formatDateTime(price.created_at)), 1)]),
																									_: 2
																								}, 1024),
																								createVNode(VCol, {
																									cols: "12",
																									md: "3"
																								}, {
																									default: withCtx(() => [createVNode("strong", null, toDisplayString(formatMoney(price.price)), 1)]),
																									_: 2
																								}, 1024),
																								createVNode(VCol, {
																									cols: "12",
																									md: "2"
																								}, {
																									default: withCtx(() => [createVNode("span", null, toDisplayString(price.currency?.code || "-"), 1)]),
																									_: 2
																								}, 1024)
																							]),
																							_: 2
																						}, 1024)]),
																						_: 2
																					}, 1024);
																				}), 128)), !(goodData.value.prices || []).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
																					default: withCtx(() => [createVNode("span", { class: "text-medium-emphasis" }, " Цен пока нет ")]),
																					_: 1
																				})) : createCommentVNode("", true)];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																	} else return [showFormAddPrice.value ? (openBlock(), createBlock(VCard, {
																		key: 0,
																		variant: "tonal",
																		class: "mb-4"
																	}, {
																		default: withCtx(() => [createVNode(VCardTitle, null, {
																			default: withCtx(() => [createTextVNode("Добавить цену")]),
																			_: 1
																		}), createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																				default: withCtx(() => [createVNode(VRow, null, {
																					default: withCtx(() => [
																						createVNode(VCol, {
																							cols: "12",
																							md: "8"
																						}, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formAddPrice).price,
																								"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																								label: "Цена",
																								variant: "solo",
																								density: "comfortable",
																								type: "number",
																								color: "deep-purple"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}),
																						createVNode(VCol, {
																							cols: "12",
																							md: "4"
																						}, {
																							default: withCtx(() => [createVNode(VSelect, {
																								items: currencies.value,
																								"item-value": "id",
																								"item-title": "code",
																								modelValue: unref(formAddPrice).currency_id,
																								"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																								label: "Валюта",
																								variant: "solo",
																								density: "compact",
																								clearable: ""
																							}, null, 8, [
																								"items",
																								"modelValue",
																								"onUpdate:modelValue"
																							])]),
																							_: 1
																						}),
																						createVNode(VCol, { cols: "12" }, {
																							default: withCtx(() => [createVNode(VBtn, {
																								text: "Store",
																								onClick: storePrice,
																								variant: "flat",
																								density: "comfortable"
																							})]),
																							_: 1
																						})
																					]),
																					_: 1
																				})]),
																				_: 1
																			})]),
																			_: 1
																		})]),
																		_: 1
																	})) : createCommentVNode("", true), createVNode(VList, {
																		border: "",
																		rounded: ""
																	}, {
																		default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(goodData.value.prices || [], (price) => {
																			return openBlock(), createBlock(VListItem, { key: price.id }, {
																				default: withCtx(() => [createVNode(VRow, null, {
																					default: withCtx(() => [
																						createVNode(VCol, {
																							cols: "12",
																							md: "3"
																						}, {
																							default: withCtx(() => [createVNode("span", { class: "text-caption font-mono" }, toDisplayString(formatDateTime(price.created_at)), 1)]),
																							_: 2
																						}, 1024),
																						createVNode(VCol, {
																							cols: "12",
																							md: "3"
																						}, {
																							default: withCtx(() => [createVNode("strong", null, toDisplayString(formatMoney(price.price)), 1)]),
																							_: 2
																						}, 1024),
																						createVNode(VCol, {
																							cols: "12",
																							md: "2"
																						}, {
																							default: withCtx(() => [createVNode("span", null, toDisplayString(price.currency?.code || "-"), 1)]),
																							_: 2
																						}, 1024)
																					]),
																					_: 2
																				}, 1024)]),
																				_: 2
																			}, 1024);
																		}), 128)), !(goodData.value.prices || []).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
																			default: withCtx(() => [createVNode("span", { class: "text-medium-emphasis" }, " Цен пока нет ")]),
																			_: 1
																		})) : createCommentVNode("", true)]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
															default: withCtx(() => [createVNode("span", null, "История цен"), createVNode(VBtn, {
																text: "+ 💵",
																color: "pink-darken-4",
																variant: "tonal",
																density: "compact",
																onClick: ($event) => showFormAddPrice.value = !showFormAddPrice.value
															}, null, 8, ["onClick"])]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [showFormAddPrice.value ? (openBlock(), createBlock(VCard, {
																key: 0,
																variant: "tonal",
																class: "mb-4"
															}, {
																default: withCtx(() => [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Добавить цену")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [
																				createVNode(VCol, {
																					cols: "12",
																					md: "8"
																				}, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formAddPrice).price,
																						"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																						label: "Цена",
																						variant: "solo",
																						density: "comfortable",
																						type: "number",
																						color: "deep-purple"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VCol, {
																					cols: "12",
																					md: "4"
																				}, {
																					default: withCtx(() => [createVNode(VSelect, {
																						items: currencies.value,
																						"item-value": "id",
																						"item-title": "code",
																						modelValue: unref(formAddPrice).currency_id,
																						"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																						label: "Валюта",
																						variant: "solo",
																						density: "compact",
																						clearable: ""
																					}, null, 8, [
																						"items",
																						"modelValue",
																						"onUpdate:modelValue"
																					])]),
																					_: 1
																				}),
																				createVNode(VCol, { cols: "12" }, {
																					default: withCtx(() => [createVNode(VBtn, {
																						text: "Store",
																						onClick: storePrice,
																						variant: "flat",
																						density: "comfortable"
																					})]),
																					_: 1
																				})
																			]),
																			_: 1
																		})]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															})) : createCommentVNode("", true), createVNode(VList, {
																border: "",
																rounded: ""
															}, {
																default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(goodData.value.prices || [], (price) => {
																	return openBlock(), createBlock(VListItem, { key: price.id }, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [
																				createVNode(VCol, {
																					cols: "12",
																					md: "3"
																				}, {
																					default: withCtx(() => [createVNode("span", { class: "text-caption font-mono" }, toDisplayString(formatDateTime(price.created_at)), 1)]),
																					_: 2
																				}, 1024),
																				createVNode(VCol, {
																					cols: "12",
																					md: "3"
																				}, {
																					default: withCtx(() => [createVNode("strong", null, toDisplayString(formatMoney(price.price)), 1)]),
																					_: 2
																				}, 1024),
																				createVNode(VCol, {
																					cols: "12",
																					md: "2"
																				}, {
																					default: withCtx(() => [createVNode("span", null, toDisplayString(price.currency?.code || "-"), 1)]),
																					_: 2
																				}, 1024)
																			]),
																			_: 2
																		}, 1024)]),
																		_: 2
																	}, 1024);
																}), 128)), !(goodData.value.prices || []).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
																	default: withCtx(() => [createVNode("span", { class: "text-medium-emphasis" }, " Цен пока нет ")]),
																	_: 1
																})) : createCommentVNode("", true)]),
																_: 1
															})]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												else return [createVNode(VCard, null, {
													default: withCtx(() => [createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
														default: withCtx(() => [createVNode("span", null, "История цен"), createVNode(VBtn, {
															text: "+ 💵",
															color: "pink-darken-4",
															variant: "tonal",
															density: "compact",
															onClick: ($event) => showFormAddPrice.value = !showFormAddPrice.value
														}, null, 8, ["onClick"])]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [showFormAddPrice.value ? (openBlock(), createBlock(VCard, {
															key: 0,
															variant: "tonal",
															class: "mb-4"
														}, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Добавить цену")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																	default: withCtx(() => [createVNode(VRow, null, {
																		default: withCtx(() => [
																			createVNode(VCol, {
																				cols: "12",
																				md: "8"
																			}, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formAddPrice).price,
																					"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																					label: "Цена",
																					variant: "solo",
																					density: "comfortable",
																					type: "number",
																					color: "deep-purple"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VCol, {
																				cols: "12",
																				md: "4"
																			}, {
																				default: withCtx(() => [createVNode(VSelect, {
																					items: currencies.value,
																					"item-value": "id",
																					"item-title": "code",
																					modelValue: unref(formAddPrice).currency_id,
																					"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																					label: "Валюта",
																					variant: "solo",
																					density: "compact",
																					clearable: ""
																				}, null, 8, [
																					"items",
																					"modelValue",
																					"onUpdate:modelValue"
																				])]),
																				_: 1
																			}),
																			createVNode(VCol, { cols: "12" }, {
																				default: withCtx(() => [createVNode(VBtn, {
																					text: "Store",
																					onClick: storePrice,
																					variant: "flat",
																					density: "comfortable"
																				})]),
																				_: 1
																			})
																		]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})) : createCommentVNode("", true), createVNode(VList, {
															border: "",
															rounded: ""
														}, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(goodData.value.prices || [], (price) => {
																return openBlock(), createBlock(VListItem, { key: price.id }, {
																	default: withCtx(() => [createVNode(VRow, null, {
																		default: withCtx(() => [
																			createVNode(VCol, {
																				cols: "12",
																				md: "3"
																			}, {
																				default: withCtx(() => [createVNode("span", { class: "text-caption font-mono" }, toDisplayString(formatDateTime(price.created_at)), 1)]),
																				_: 2
																			}, 1024),
																			createVNode(VCol, {
																				cols: "12",
																				md: "3"
																			}, {
																				default: withCtx(() => [createVNode("strong", null, toDisplayString(formatMoney(price.price)), 1)]),
																				_: 2
																			}, 1024),
																			createVNode(VCol, {
																				cols: "12",
																				md: "2"
																			}, {
																				default: withCtx(() => [createVNode("span", null, toDisplayString(price.currency?.code || "-"), 1)]),
																				_: 2
																			}, 1024)
																		]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1024);
															}), 128)), !(goodData.value.prices || []).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
																default: withCtx(() => [createVNode("span", { class: "text-medium-emphasis" }, " Цен пока нет ")]),
																_: 1
															})) : createCommentVNode("", true)]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
									} else return [
										createVNode(VWindowItem, { value: "overview" }, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [
													createVNode(VCol, {
														cols: "12",
														lg: "3"
													}, {
														default: withCtx(() => [createVNode(VCard, { class: "mb-4" }, {
															default: withCtx(() => [createVNode(VCardTitle, { class: "text-wrap" }, {
																default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.name), 1)]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [
																	createVNode(VImg, {
																		src: goodData.value.ava_thumb || "/default-image.jpg",
																		cover: "",
																		class: "mb-3 rounded-lg",
																		"max-height": "120"
																	}, null, 8, ["src"]),
																	createVNode(VImg, {
																		src: goodData.value.ava_image || "/default-image.jpg",
																		alt: goodData.value.name,
																		"lazy-src": "/placeholder.jpg",
																		"aspect-ratio": "1",
																		cover: "",
																		class: "mb-4 rounded-lg"
																	}, null, 8, ["src", "alt"]),
																	createVNode("p", { class: "mb-0" }, toDisplayString(goodData.value.description || "Описание отсутствует"), 1)
																]),
																_: 1
															})]),
															_: 1
														}), showFormAddPrice.value ? (openBlock(), createBlock(VCard, {
															key: 0,
															class: "mb-4"
														}, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Добавить цену")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																	default: withCtx(() => [createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formAddPrice).price,
																				"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																				label: "Цена",
																				variant: "solo",
																				density: "comfortable",
																				type: "number",
																				color: "deep-purple"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}), createVNode(VCol, { cols: "4" }, {
																			default: withCtx(() => [createVNode(VSelect, {
																				items: currencies.value,
																				"item-value": "id",
																				"item-title": "code",
																				modelValue: unref(formAddPrice).currency_id,
																				"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																				label: "Валюта",
																				variant: "solo",
																				density: "compact",
																				clearable: ""
																			}, null, 8, [
																				"items",
																				"modelValue",
																				"onUpdate:modelValue"
																			])]),
																			_: 1
																		})]),
																		_: 1
																	}), createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																			default: withCtx(() => [createVNode(VBtn, {
																				text: "Store",
																				onClick: storePrice,
																				variant: "flat",
																				density: "comfortable",
																				block: ""
																			})]),
																			_: 1
																		})]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})) : createCommentVNode("", true)]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														lg: "5"
													}, {
														default: withCtx(() => [createVNode(VCard, { class: "mb-4" }, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Кратко по товару")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																	default: withCtx(() => [
																		createVNode(VListItem, null, {
																			prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-pound" })]),
																			default: withCtx(() => [createVNode(VListItemTitle, null, {
																				default: withCtx(() => [createTextVNode("ID")]),
																				_: 1
																			}), createVNode(VListItemSubtitle, null, {
																				default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.id), 1)]),
																				_: 1
																			})]),
																			_: 1
																		}),
																		createVNode(VListItem, null, {
																			prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-link-variant" })]),
																			default: withCtx(() => [createVNode(VListItemTitle, null, {
																				default: withCtx(() => [createTextVNode("Slug")]),
																				_: 1
																			}), createVNode(VListItemSubtitle, null, {
																				default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.slug || "—"), 1)]),
																				_: 1
																			})]),
																			_: 1
																		}),
																		createVNode(VListItem, null, {
																			prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-package-variant" })]),
																			default: withCtx(() => [createVNode(VListItemTitle, null, {
																				default: withCtx(() => [createTextVNode("Denominator")]),
																				_: 1
																			}), createVNode(VListItemSubtitle, null, {
																				default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.denominator || "—"), 1)]),
																				_: 1
																			})]),
																			_: 1
																		}),
																		createVNode(VListItem, null, {
																			prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-percent" })]),
																			default: withCtx(() => [createVNode(VListItemTitle, null, {
																				default: withCtx(() => [createTextVNode("VAT")]),
																				_: 1
																			}), createVNode(VListItemSubtitle, null, {
																				default: withCtx(() => [currentVatRate.value ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)) : (openBlock(), createBlock("span", { key: 1 }, " — "))]),
																				_: 1
																			})]),
																			_: 1
																		})
																	]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														}), createVNode(VCard, null, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Products")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [(goodData.value.products || []).length ? (openBlock(true), createBlock(Fragment, { key: 0 }, renderList(goodData.value.products || [], (product) => {
																	return openBlock(), createBlock(VChip, {
																		key: product.id,
																		class: "ma-1",
																		size: "small",
																		variant: "tonal",
																		color: "teal"
																	}, {
																		default: withCtx(() => [createTextVNode(toDisplayString(product.rus || product.name || product.id), 1)]),
																		_: 2
																	}, 1024);
																}), 128)) : (openBlock(), createBlock(VAlert, {
																	key: 1,
																	type: "info",
																	variant: "tonal"
																}, {
																	default: withCtx(() => [createTextVNode(" Products пока не привязаны. ")]),
																	_: 1
																}))]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														lg: "4"
													}, {
														default: withCtx(() => [createVNode(VCard, null, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Быстрая статистика")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VRow, { dense: "" }, {
																	default: withCtx(() => [
																		createVNode(VCol, { cols: "6" }, {
																			default: withCtx(() => [createVNode(VCard, {
																				variant: "tonal",
																				class: "pa-3"
																			}, {
																				default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Цены "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.prices || []).length), 1)]),
																				_: 1
																			})]),
																			_: 1
																		}),
																		createVNode(VCol, { cols: "6" }, {
																			default: withCtx(() => [createVNode(VCard, {
																				variant: "tonal",
																				class: "pa-3"
																			}, {
																				default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Продажи "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.sales || []).length), 1)]),
																				_: 1
																			})]),
																			_: 1
																		}),
																		createVNode(VCol, { cols: "6" }, {
																			default: withCtx(() => [createVNode(VCard, {
																				variant: "tonal",
																				class: "pa-3"
																			}, {
																				default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Закупки "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.purchases || []).length), 1)]),
																				_: 1
																			})]),
																			_: 1
																		}),
																		createVNode(VCol, { cols: "6" }, {
																			default: withCtx(() => [createVNode(VCard, {
																				variant: "tonal",
																				class: "pa-3"
																			}, {
																				default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Media "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.media || []).length), 1)]),
																				_: 1
																			})]),
																			_: 1
																		})
																	]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})
												]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VWindowItem, { value: "quotations" }, {
											default: withCtx(() => [createVNode(VCard, null, {
												default: withCtx(() => [createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
													default: withCtx(() => [createVNode("span", null, "Quotations"), createVNode(VBtn, {
														text: "+ Q",
														color: "indigo",
														variant: "tonal",
														density: "compact",
														onClick: ($event) => dialogFormQuotation.value = true
													}, null, 8, ["onClick"])]),
													_: 1
												}), createVNode(VCardText, { class: "pa-0" }, {
													default: withCtx(() => [createVNode(VDataTable, {
														items: goodData.value.quotations || [],
														headers: headerQuotations,
														"items-per-page": "100",
														"fixed-header": "",
														height: "620px",
														density: "compact",
														class: "border rounded",
														hover: ""
													}, {
														"item.denominator": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.denominator || 1), 1)]),
														"item.created_at": withCtx(({ item }) => [createVNode("span", null, toDisplayString(formatDate(item.created_at)), 1)]),
														"item.price": withCtx(({ item }) => [createVNode("span", null, toDisplayString(formatMoney(item.price)), 1)]),
														_: 1
													}, 8, ["items"])]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VWindowItem, { value: "purchases" }, {
											default: withCtx(() => [createVNode(VCard, null, {
												default: withCtx(() => [createVNode(VCardTitle, null, {
													default: withCtx(() => [createTextVNode("Закупки данного товара")]),
													_: 1
												}), createVNode(VCardText, null, {
													default: withCtx(() => [createVNode(VAlert, {
														type: "info",
														variant: "tonal",
														class: "mb-4"
													}, {
														default: withCtx(() => [
															createTextVNode(" Здесь показываются закупки, связанные с этим good через pivot-таблицу "),
															createVNode("strong", null, "good_purchase"),
															createTextVNode(". На следующем этапе добавим автоматический расчёт продажной цены от закупки. ")
														]),
														_: 1
													}), createVNode(VDataTable, {
														items: goodData.value.purchases || [],
														headers: headerPurchases,
														"items-per-page": "50",
														"fixed-header": "",
														height: "560px",
														density: "compact",
														class: "border rounded",
														hover: ""
													}, {
														"item.date": withCtx(({ item }) => [createTextVNode(toDisplayString(item.date || "-"), 1)]),
														"item.pivot.quantity": withCtx(({ item }) => [createTextVNode(toDisplayString(item.pivot?.quantity || "—"), 1)]),
														"item.pivot.price": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.pivot?.price)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(purchaseCurrencyCode(item)), 1)]),
														"item.pivot.total": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.pivot?.total)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(purchaseCurrencyCode(item)), 1)]),
														"no-data": withCtx(() => [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Закупок по этому товару пока нет. ")]),
														_: 1
													}, 8, ["items"])]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VWindowItem, { value: "calculator" }, {
											default: withCtx(() => [createVNode(_sfc_main$6, {
												"good-id": goodData.value.id,
												quotations: goodData.value.quotations || [],
												"default-vat-rate": defaultVatRate.value,
												"default-box-weight-kg": goodBoxWeight.value,
												"currency-code": "RUB",
												onSaved: ($event) => activeTab.value = "calculations"
											}, null, 8, [
												"good-id",
												"quotations",
												"default-vat-rate",
												"default-box-weight-kg",
												"onSaved"
											])]),
											_: 1
										}),
										createVNode(VWindowItem, { value: "calculations" }, {
											default: withCtx(() => [createVNode(_sfc_main$4, {
												"good-id": goodData.value.id,
												onApplied: ($event) => activeTab.value = "price-values"
											}, null, 8, ["good-id", "onApplied"])]),
											_: 1
										}),
										createVNode(VWindowItem, { value: "price-types" }, {
											default: withCtx(() => [createVNode(_sfc_main$3, { currencies: currencies.value }, null, 8, ["currencies"])]),
											_: 1
										}),
										createVNode(VWindowItem, { value: "price-values" }, {
											default: withCtx(() => [createVNode(_sfc_main$2, {
												"good-id": goodData.value.id,
												currencies: currencies.value
											}, null, 8, ["good-id", "currencies"])]),
											_: 1
										}),
										createVNode(VWindowItem, { value: "media" }, {
											default: withCtx(() => [createVNode(GoodMediaTab_default, {
												good: goodData.value,
												onChanged: fetchGood,
												onAvaUpdated: fetchGood
											}, null, 8, ["good"])]),
											_: 1
										}),
										createVNode(VWindowItem, { value: "seo" }, {
											default: withCtx(() => [createVNode(_sfc_main$5, { good: goodData.value }, null, 8, ["good"])]),
											_: 1
										}),
										createVNode(VWindowItem, { value: "sales" }, {
											default: withCtx(() => [createVNode(VCard, null, {
												default: withCtx(() => [createVNode(VCardTitle, null, {
													default: withCtx(() => [createTextVNode("Продажи")]),
													_: 1
												}), createVNode(VCardText, { class: "pa-0" }, {
													default: withCtx(() => [createVNode(VDataTable, {
														items: goodData.value.sales || [],
														headers: headerSales,
														"items-per-page": "100",
														"fixed-header": "",
														height: "620px",
														density: "comfortable",
														hover: ""
													}, {
														"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.date), 1)]),
														"item.pivot.price": withCtx(({ item }) => [createVNode("span", { class: "font-weight-bold" }, toDisplayString(formatMoney(item.pivot?.price)), 1)]),
														_: 1
													}, 8, ["items"])]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VWindowItem, { value: "price-history" }, {
											default: withCtx(() => [createVNode(VCard, null, {
												default: withCtx(() => [createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
													default: withCtx(() => [createVNode("span", null, "История цен"), createVNode(VBtn, {
														text: "+ 💵",
														color: "pink-darken-4",
														variant: "tonal",
														density: "compact",
														onClick: ($event) => showFormAddPrice.value = !showFormAddPrice.value
													}, null, 8, ["onClick"])]),
													_: 1
												}), createVNode(VCardText, null, {
													default: withCtx(() => [showFormAddPrice.value ? (openBlock(), createBlock(VCard, {
														key: 0,
														variant: "tonal",
														class: "mb-4"
													}, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Добавить цену")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [
																		createVNode(VCol, {
																			cols: "12",
																			md: "8"
																		}, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formAddPrice).price,
																				"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																				label: "Цена",
																				variant: "solo",
																				density: "comfortable",
																				type: "number",
																				color: "deep-purple"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VCol, {
																			cols: "12",
																			md: "4"
																		}, {
																			default: withCtx(() => [createVNode(VSelect, {
																				items: currencies.value,
																				"item-value": "id",
																				"item-title": "code",
																				modelValue: unref(formAddPrice).currency_id,
																				"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																				label: "Валюта",
																				variant: "solo",
																				density: "compact",
																				clearable: ""
																			}, null, 8, [
																				"items",
																				"modelValue",
																				"onUpdate:modelValue"
																			])]),
																			_: 1
																		}),
																		createVNode(VCol, { cols: "12" }, {
																			default: withCtx(() => [createVNode(VBtn, {
																				text: "Store",
																				onClick: storePrice,
																				variant: "flat",
																				density: "comfortable"
																			})]),
																			_: 1
																		})
																	]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})) : createCommentVNode("", true), createVNode(VList, {
														border: "",
														rounded: ""
													}, {
														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(goodData.value.prices || [], (price) => {
															return openBlock(), createBlock(VListItem, { key: price.id }, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [
																		createVNode(VCol, {
																			cols: "12",
																			md: "3"
																		}, {
																			default: withCtx(() => [createVNode("span", { class: "text-caption font-mono" }, toDisplayString(formatDateTime(price.created_at)), 1)]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, {
																			cols: "12",
																			md: "3"
																		}, {
																			default: withCtx(() => [createVNode("strong", null, toDisplayString(formatMoney(price.price)), 1)]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, {
																			cols: "12",
																			md: "2"
																		}, {
																			default: withCtx(() => [createVNode("span", null, toDisplayString(price.currency?.code || "-"), 1)]),
																			_: 2
																		}, 1024)
																	]),
																	_: 2
																}, 1024)]),
																_: 2
															}, 1024);
														}), 128)), !(goodData.value.prices || []).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
															default: withCtx(() => [createVNode("span", { class: "text-medium-emphasis" }, " Цен пока нет ")]),
															_: 1
														})) : createCommentVNode("", true)]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})
									];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(`<!--]-->`);
						} else _push(`<!---->`);
					} else return [
						createVNode(VRow, { class: "mb-3 align-center" }, {
							default: withCtx(() => [
								createVNode(VCol, {
									cols: "12",
									sm: "2"
								}, {
									default: withCtx(() => [createVNode(VBtn, {
										text: "+ 💵",
										onClick: ($event) => showFormAddPrice.value = !showFormAddPrice.value,
										variant: "elevated",
										density: "compact",
										color: "pink-darken-4",
										block: ""
									}, null, 8, ["onClick"])]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									sm: "2"
								}, {
									default: withCtx(() => [createVNode(VBtn, {
										text: "+ Q",
										onClick: ($event) => dialogFormQuotation.value = !dialogFormQuotation.value,
										variant: "elevated",
										density: "compact",
										color: "indigo",
										block: ""
									}, null, 8, ["onClick"])]),
									_: 1
								}),
								goodData.value ? (openBlock(), createBlock(VCol, {
									key: 0,
									cols: "12",
									sm: "8"
								}, {
									default: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-end ga-2" }, [
										createVNode(VChip, {
											size: "small",
											variant: "tonal",
											color: "deep-purple"
										}, {
											default: withCtx(() => [createTextVNode(" ID: " + toDisplayString(goodData.value.id), 1)]),
											_: 1
										}),
										createVNode(VChip, {
											size: "small",
											variant: "tonal",
											color: goodData.value.is_published ? "green" : "grey"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.is_published ? "published" : "hidden"), 1)]),
											_: 1
										}, 8, ["color"]),
										currentVatRate.value ? (openBlock(), createBlock(VChip, {
											key: 0,
											size: "small",
											variant: "tonal",
											color: "blue-grey"
										}, {
											default: withCtx(() => [createTextVNode(" НДС: " + toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)]),
											_: 1
										})) : createCommentVNode("", true)
									])]),
									_: 1
								})) : createCommentVNode("", true)
							]),
							_: 1
						}),
						createVNode(VDialog, {
							modelValue: dialogFormQuotation.value,
							"onUpdate:modelValue": ($event) => dialogFormQuotation.value = $event,
							width: "771"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Form Quotation")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storeQuotation, ["prevent"]) }, {
											default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
												default: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VAutocomplete, {
															items: units.value,
															"item-value": "id",
															"item-title": "name",
															modelValue: unref(formQuotation).unit_id,
															"onUpdate:modelValue": ($event) => unref(formQuotation).unit_id = $event,
															label: "Unit",
															variant: "solo",
															density: "comfortable",
															"base-color": "yellow",
															clearable: ""
														}, null, 8, [
															"items",
															"modelValue",
															"onUpdate:modelValue"
														])]),
														_: 1
													})]),
													_: 1
												}), createVNode(VRow, null, {
													default: withCtx(() => [
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: unref(formQuotation).price,
																"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																label: "Цена",
																variant: "solo",
																density: "default",
																type: "number"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VAutocomplete, {
																items: measures.value,
																"item-value": "id",
																"item-title": "name",
																modelValue: unref(formQuotation).measure_id,
																"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																label: "Measure",
																variant: "solo",
																density: "compact",
																clearable: ""
															}, null, 8, [
																"items",
																"modelValue",
																"onUpdate:modelValue"
															])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: unref(formQuotation).denominator,
																"onUpdate:modelValue": ($event) => unref(formQuotation).denominator = $event,
																label: "Делитель quotation",
																type: "number",
																min: "0.0001",
																step: "0.0001",
																variant: "solo",
																density: "compact",
																hint: "Например: цена дана за 25 кг",
																"persistent-hint": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})
													]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [
											createVNode(VSpacer),
											createVNode(VBtn, {
												text: "Cancel",
												variant: "text",
												onClick: ($event) => dialogFormQuotation.value = false
											}, null, 8, ["onClick"]),
											createVNode(VBtn, {
												text: "Store",
												onClick: storeQuotation,
												variant: "elevated",
												density: "comfortable",
												color: "grey"
											})
										]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						pageLoading.value ? (openBlock(), createBlock(VRow, { key: 0 }, {
							default: withCtx(() => [createVNode(VCol, {
								cols: "12",
								class: "text-center py-10"
							}, {
								default: withCtx(() => [createVNode(VProgressCircular, {
									indeterminate: "",
									color: "primary"
								})]),
								_: 1
							})]),
							_: 1
						})) : pageError.value ? (openBlock(), createBlock(VRow, { key: 1 }, {
							default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
								default: withCtx(() => [createVNode(VAlert, {
									type: "error",
									variant: "tonal"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(pageError.value), 1)]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						})) : goodData.value ? (openBlock(), createBlock(Fragment, { key: 2 }, [
							createVNode(VCard, { class: "mb-4 good-header-card" }, {
								default: withCtx(() => [createVNode(VCardText, null, {
									default: withCtx(() => [createVNode(VRow, { class: "align-center" }, {
										default: withCtx(() => [createVNode(VCol, {
											cols: "12",
											md: "8"
										}, {
											default: withCtx(() => [
												createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, " Good / товар "),
												createVNode("h1", { class: "text-h5 font-weight-bold mb-1" }, toDisplayString(goodData.value.name), 1),
												createVNode("div", { class: "text-caption text-medium-emphasis" }, " slug: " + toDisplayString(goodData.value.slug || "—"), 1)
											]),
											_: 1
										}), createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VRow, { dense: "" }, {
												default: withCtx(() => [createVNode(VCol, { cols: "6" }, {
													default: withCtx(() => [createVNode(VCard, {
														variant: "tonal",
														class: "pa-3"
													}, {
														default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Упаковка / denominator "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString(goodData.value.denominator || "—"), 1)]),
														_: 1
													})]),
													_: 1
												}), createVNode(VCol, { cols: "6" }, {
													default: withCtx(() => [createVNode(VCard, {
														variant: "tonal",
														class: "pa-3"
													}, {
														default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Quotation "), createVNode("div", { class: "text-subtitle-1 font-weight-bold" }, toDisplayString((goodData.value.quotations || []).length), 1)]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									})]),
									_: 1
								})]),
								_: 1
							}),
							createVNode(VCard, { class: "mb-4" }, {
								default: withCtx(() => [createVNode(VTabs, {
									modelValue: activeTab.value,
									"onUpdate:modelValue": ($event) => activeTab.value = $event,
									density: "compact",
									color: "deep-purple-darken-1",
									"show-arrows": ""
								}, {
									default: withCtx(() => [
										createVNode(VTab, { value: "overview" }, {
											default: withCtx(() => [createTextVNode("Обзор")]),
											_: 1
										}),
										createVNode(VTab, { value: "quotations" }, {
											default: withCtx(() => [createTextVNode("Quotations")]),
											_: 1
										}),
										createVNode(VTab, { value: "purchases" }, {
											default: withCtx(() => [createTextVNode("Закупки")]),
											_: 1
										}),
										createVNode(VTab, { value: "calculator" }, {
											default: withCtx(() => [createTextVNode("Расчёт цены")]),
											_: 1
										}),
										createVNode(VTab, { value: "calculations" }, {
											default: withCtx(() => [createTextVNode("Сохранённые расчёты")]),
											_: 1
										}),
										createVNode(VTab, { value: "price-types" }, {
											default: withCtx(() => [createTextVNode("Виды цен")]),
											_: 1
										}),
										createVNode(VTab, { value: "price-values" }, {
											default: withCtx(() => [createTextVNode("Цены товара")]),
											_: 1
										}),
										createVNode(VTab, { value: "media" }, {
											default: withCtx(() => [createTextVNode("Media")]),
											_: 1
										}),
										createVNode(VTab, { value: "seo" }, {
											default: withCtx(() => [createTextVNode("SEO")]),
											_: 1
										}),
										createVNode(VTab, { value: "sales" }, {
											default: withCtx(() => [createTextVNode("Продажи")]),
											_: 1
										}),
										createVNode(VTab, { value: "price-history" }, {
											default: withCtx(() => [createTextVNode("История цен")]),
											_: 1
										})
									]),
									_: 1
								}, 8, ["modelValue", "onUpdate:modelValue"])]),
								_: 1
							}),
							createVNode(VWindow, {
								modelValue: activeTab.value,
								"onUpdate:modelValue": ($event) => activeTab.value = $event
							}, {
								default: withCtx(() => [
									createVNode(VWindowItem, { value: "overview" }, {
										default: withCtx(() => [createVNode(VRow, null, {
											default: withCtx(() => [
												createVNode(VCol, {
													cols: "12",
													lg: "3"
												}, {
													default: withCtx(() => [createVNode(VCard, { class: "mb-4" }, {
														default: withCtx(() => [createVNode(VCardTitle, { class: "text-wrap" }, {
															default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.name), 1)]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [
																createVNode(VImg, {
																	src: goodData.value.ava_thumb || "/default-image.jpg",
																	cover: "",
																	class: "mb-3 rounded-lg",
																	"max-height": "120"
																}, null, 8, ["src"]),
																createVNode(VImg, {
																	src: goodData.value.ava_image || "/default-image.jpg",
																	alt: goodData.value.name,
																	"lazy-src": "/placeholder.jpg",
																	"aspect-ratio": "1",
																	cover: "",
																	class: "mb-4 rounded-lg"
																}, null, 8, ["src", "alt"]),
																createVNode("p", { class: "mb-0" }, toDisplayString(goodData.value.description || "Описание отсутствует"), 1)
															]),
															_: 1
														})]),
														_: 1
													}), showFormAddPrice.value ? (openBlock(), createBlock(VCard, {
														key: 0,
														class: "mb-4"
													}, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Добавить цену")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formAddPrice).price,
																			"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																			label: "Цена",
																			variant: "solo",
																			density: "comfortable",
																			type: "number",
																			color: "deep-purple"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}), createVNode(VCol, { cols: "4" }, {
																		default: withCtx(() => [createVNode(VSelect, {
																			items: currencies.value,
																			"item-value": "id",
																			"item-title": "code",
																			modelValue: unref(formAddPrice).currency_id,
																			"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																			label: "Валюта",
																			variant: "solo",
																			density: "compact",
																			clearable: ""
																		}, null, 8, [
																			"items",
																			"modelValue",
																			"onUpdate:modelValue"
																		])]),
																		_: 1
																	})]),
																	_: 1
																}), createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																		default: withCtx(() => [createVNode(VBtn, {
																			text: "Store",
																			onClick: storePrice,
																			variant: "flat",
																			density: "comfortable",
																			block: ""
																		})]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})) : createCommentVNode("", true)]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													lg: "5"
												}, {
													default: withCtx(() => [createVNode(VCard, { class: "mb-4" }, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Кратко по товару")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																default: withCtx(() => [
																	createVNode(VListItem, null, {
																		prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-pound" })]),
																		default: withCtx(() => [createVNode(VListItemTitle, null, {
																			default: withCtx(() => [createTextVNode("ID")]),
																			_: 1
																		}), createVNode(VListItemSubtitle, null, {
																			default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.id), 1)]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VListItem, null, {
																		prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-link-variant" })]),
																		default: withCtx(() => [createVNode(VListItemTitle, null, {
																			default: withCtx(() => [createTextVNode("Slug")]),
																			_: 1
																		}), createVNode(VListItemSubtitle, null, {
																			default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.slug || "—"), 1)]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VListItem, null, {
																		prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-package-variant" })]),
																		default: withCtx(() => [createVNode(VListItemTitle, null, {
																			default: withCtx(() => [createTextVNode("Denominator")]),
																			_: 1
																		}), createVNode(VListItemSubtitle, null, {
																			default: withCtx(() => [createTextVNode(toDisplayString(goodData.value.denominator || "—"), 1)]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VListItem, null, {
																		prepend: withCtx(() => [createVNode(VIcon, { icon: "mdi-percent" })]),
																		default: withCtx(() => [createVNode(VListItemTitle, null, {
																			default: withCtx(() => [createTextVNode("VAT")]),
																			_: 1
																		}), createVNode(VListItemSubtitle, null, {
																			default: withCtx(() => [currentVatRate.value ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(currentVatRate.value.title) + " / " + toDisplayString(currentVatRate.value.rate) + "% ", 1)) : (openBlock(), createBlock("span", { key: 1 }, " — "))]),
																			_: 1
																		})]),
																		_: 1
																	})
																]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													}), createVNode(VCard, null, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Products")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [(goodData.value.products || []).length ? (openBlock(true), createBlock(Fragment, { key: 0 }, renderList(goodData.value.products || [], (product) => {
																return openBlock(), createBlock(VChip, {
																	key: product.id,
																	class: "ma-1",
																	size: "small",
																	variant: "tonal",
																	color: "teal"
																}, {
																	default: withCtx(() => [createTextVNode(toDisplayString(product.rus || product.name || product.id), 1)]),
																	_: 2
																}, 1024);
															}), 128)) : (openBlock(), createBlock(VAlert, {
																key: 1,
																type: "info",
																variant: "tonal"
															}, {
																default: withCtx(() => [createTextVNode(" Products пока не привязаны. ")]),
																_: 1
															}))]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													lg: "4"
												}, {
													default: withCtx(() => [createVNode(VCard, null, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Быстрая статистика")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VRow, { dense: "" }, {
																default: withCtx(() => [
																	createVNode(VCol, { cols: "6" }, {
																		default: withCtx(() => [createVNode(VCard, {
																			variant: "tonal",
																			class: "pa-3"
																		}, {
																			default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Цены "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.prices || []).length), 1)]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VCol, { cols: "6" }, {
																		default: withCtx(() => [createVNode(VCard, {
																			variant: "tonal",
																			class: "pa-3"
																		}, {
																			default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Продажи "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.sales || []).length), 1)]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VCol, { cols: "6" }, {
																		default: withCtx(() => [createVNode(VCard, {
																			variant: "tonal",
																			class: "pa-3"
																		}, {
																			default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Закупки "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.purchases || []).length), 1)]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VCol, { cols: "6" }, {
																		default: withCtx(() => [createVNode(VCard, {
																			variant: "tonal",
																			class: "pa-3"
																		}, {
																			default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Media "), createVNode("div", { class: "text-h6" }, toDisplayString((goodData.value.media || []).length), 1)]),
																			_: 1
																		})]),
																		_: 1
																	})
																]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})
											]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "quotations" }, {
										default: withCtx(() => [createVNode(VCard, null, {
											default: withCtx(() => [createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
												default: withCtx(() => [createVNode("span", null, "Quotations"), createVNode(VBtn, {
													text: "+ Q",
													color: "indigo",
													variant: "tonal",
													density: "compact",
													onClick: ($event) => dialogFormQuotation.value = true
												}, null, 8, ["onClick"])]),
												_: 1
											}), createVNode(VCardText, { class: "pa-0" }, {
												default: withCtx(() => [createVNode(VDataTable, {
													items: goodData.value.quotations || [],
													headers: headerQuotations,
													"items-per-page": "100",
													"fixed-header": "",
													height: "620px",
													density: "compact",
													class: "border rounded",
													hover: ""
												}, {
													"item.denominator": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.denominator || 1), 1)]),
													"item.created_at": withCtx(({ item }) => [createVNode("span", null, toDisplayString(formatDate(item.created_at)), 1)]),
													"item.price": withCtx(({ item }) => [createVNode("span", null, toDisplayString(formatMoney(item.price)), 1)]),
													_: 1
												}, 8, ["items"])]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "purchases" }, {
										default: withCtx(() => [createVNode(VCard, null, {
											default: withCtx(() => [createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Закупки данного товара")]),
												_: 1
											}), createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VAlert, {
													type: "info",
													variant: "tonal",
													class: "mb-4"
												}, {
													default: withCtx(() => [
														createTextVNode(" Здесь показываются закупки, связанные с этим good через pivot-таблицу "),
														createVNode("strong", null, "good_purchase"),
														createTextVNode(". На следующем этапе добавим автоматический расчёт продажной цены от закупки. ")
													]),
													_: 1
												}), createVNode(VDataTable, {
													items: goodData.value.purchases || [],
													headers: headerPurchases,
													"items-per-page": "50",
													"fixed-header": "",
													height: "560px",
													density: "compact",
													class: "border rounded",
													hover: ""
												}, {
													"item.date": withCtx(({ item }) => [createTextVNode(toDisplayString(item.date || "-"), 1)]),
													"item.pivot.quantity": withCtx(({ item }) => [createTextVNode(toDisplayString(item.pivot?.quantity || "—"), 1)]),
													"item.pivot.price": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.pivot?.price)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(purchaseCurrencyCode(item)), 1)]),
													"item.pivot.total": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(formatMoney(item.pivot?.total)), 1), createVNode("span", { class: "text-caption ml-1" }, toDisplayString(purchaseCurrencyCode(item)), 1)]),
													"no-data": withCtx(() => [createVNode("div", { class: "pa-6 text-center text-medium-emphasis" }, " Закупок по этому товару пока нет. ")]),
													_: 1
												}, 8, ["items"])]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "calculator" }, {
										default: withCtx(() => [createVNode(_sfc_main$6, {
											"good-id": goodData.value.id,
											quotations: goodData.value.quotations || [],
											"default-vat-rate": defaultVatRate.value,
											"default-box-weight-kg": goodBoxWeight.value,
											"currency-code": "RUB",
											onSaved: ($event) => activeTab.value = "calculations"
										}, null, 8, [
											"good-id",
											"quotations",
											"default-vat-rate",
											"default-box-weight-kg",
											"onSaved"
										])]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "calculations" }, {
										default: withCtx(() => [createVNode(_sfc_main$4, {
											"good-id": goodData.value.id,
											onApplied: ($event) => activeTab.value = "price-values"
										}, null, 8, ["good-id", "onApplied"])]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "price-types" }, {
										default: withCtx(() => [createVNode(_sfc_main$3, { currencies: currencies.value }, null, 8, ["currencies"])]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "price-values" }, {
										default: withCtx(() => [createVNode(_sfc_main$2, {
											"good-id": goodData.value.id,
											currencies: currencies.value
										}, null, 8, ["good-id", "currencies"])]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "media" }, {
										default: withCtx(() => [createVNode(GoodMediaTab_default, {
											good: goodData.value,
											onChanged: fetchGood,
											onAvaUpdated: fetchGood
										}, null, 8, ["good"])]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "seo" }, {
										default: withCtx(() => [createVNode(_sfc_main$5, { good: goodData.value }, null, 8, ["good"])]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "sales" }, {
										default: withCtx(() => [createVNode(VCard, null, {
											default: withCtx(() => [createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Продажи")]),
												_: 1
											}), createVNode(VCardText, { class: "pa-0" }, {
												default: withCtx(() => [createVNode(VDataTable, {
													items: goodData.value.sales || [],
													headers: headerSales,
													"items-per-page": "100",
													"fixed-header": "",
													height: "620px",
													density: "comfortable",
													hover: ""
												}, {
													"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.date), 1)]),
													"item.pivot.price": withCtx(({ item }) => [createVNode("span", { class: "font-weight-bold" }, toDisplayString(formatMoney(item.pivot?.price)), 1)]),
													_: 1
												}, 8, ["items"])]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "price-history" }, {
										default: withCtx(() => [createVNode(VCard, null, {
											default: withCtx(() => [createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
												default: withCtx(() => [createVNode("span", null, "История цен"), createVNode(VBtn, {
													text: "+ 💵",
													color: "pink-darken-4",
													variant: "tonal",
													density: "compact",
													onClick: ($event) => showFormAddPrice.value = !showFormAddPrice.value
												}, null, 8, ["onClick"])]),
												_: 1
											}), createVNode(VCardText, null, {
												default: withCtx(() => [showFormAddPrice.value ? (openBlock(), createBlock(VCard, {
													key: 0,
													variant: "tonal",
													class: "mb-4"
												}, {
													default: withCtx(() => [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Добавить цену")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storePrice, ["prevent"]) }, {
															default: withCtx(() => [createVNode(VRow, null, {
																default: withCtx(() => [
																	createVNode(VCol, {
																		cols: "12",
																		md: "8"
																	}, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formAddPrice).price,
																			"onUpdate:modelValue": ($event) => unref(formAddPrice).price = $event,
																			label: "Цена",
																			variant: "solo",
																			density: "comfortable",
																			type: "number",
																			color: "deep-purple"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VCol, {
																		cols: "12",
																		md: "4"
																	}, {
																		default: withCtx(() => [createVNode(VSelect, {
																			items: currencies.value,
																			"item-value": "id",
																			"item-title": "code",
																			modelValue: unref(formAddPrice).currency_id,
																			"onUpdate:modelValue": ($event) => unref(formAddPrice).currency_id = $event,
																			label: "Валюта",
																			variant: "solo",
																			density: "compact",
																			clearable: ""
																		}, null, 8, [
																			"items",
																			"modelValue",
																			"onUpdate:modelValue"
																		])]),
																		_: 1
																	}),
																	createVNode(VCol, { cols: "12" }, {
																		default: withCtx(() => [createVNode(VBtn, {
																			text: "Store",
																			onClick: storePrice,
																			variant: "flat",
																			density: "comfortable"
																		})]),
																		_: 1
																	})
																]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})) : createCommentVNode("", true), createVNode(VList, {
													border: "",
													rounded: ""
												}, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(goodData.value.prices || [], (price) => {
														return openBlock(), createBlock(VListItem, { key: price.id }, {
															default: withCtx(() => [createVNode(VRow, null, {
																default: withCtx(() => [
																	createVNode(VCol, {
																		cols: "12",
																		md: "3"
																	}, {
																		default: withCtx(() => [createVNode("span", { class: "text-caption font-mono" }, toDisplayString(formatDateTime(price.created_at)), 1)]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, {
																		cols: "12",
																		md: "3"
																	}, {
																		default: withCtx(() => [createVNode("strong", null, toDisplayString(formatMoney(price.price)), 1)]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, {
																		cols: "12",
																		md: "2"
																	}, {
																		default: withCtx(() => [createVNode("span", null, toDisplayString(price.currency?.code || "-"), 1)]),
																		_: 2
																	}, 1024)
																]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024);
													}), 128)), !(goodData.value.prices || []).length ? (openBlock(), createBlock(VListItem, { key: 0 }, {
														default: withCtx(() => [createVNode("span", { class: "text-medium-emphasis" }, " Цен пока нет ")]),
														_: 1
													})) : createCommentVNode("", true)]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									})
								]),
								_: 1
							}, 8, ["modelValue", "onUpdate:modelValue"])
						], 64)) : createCommentVNode("", true)
					];
				}),
				_: 1
			}, _parent));
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Good.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var Good_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-35349ee9"]]);
//#endregion
export { Good_default as default };

//# sourceMappingURL=Good-P3o7QYsb.js.map