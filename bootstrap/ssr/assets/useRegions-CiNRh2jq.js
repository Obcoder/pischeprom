import { C as VRow, F as VCardText, I as VCardTitle, K as VDivider, O as VTable, P as VCard, R as VCardActions, S as VSpacer, U as VTextField, V as VAutocomplete, d as VSkeletonLoader, h as VForm, q as VListItem, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import axios from "axios";
import { Fragment, computed, createBlock, createCommentVNode, createVNode, mergeModels, mergeProps, openBlock, reactive, ref, renderList, toDisplayString, useModel, useSSRContext, watch, withCtx, withModifiers } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
//#region resources/js/Components/Geography/Cities/CityFormDialog.vue
var _sfc_main$1 = {
	__name: "CityFormDialog",
	__ssrInlineRender: true,
	props: /* @__PURE__ */ mergeModels({
		mode: {
			type: String,
			default: "create"
		},
		city: {
			type: Object,
			default: null
		},
		regions: {
			type: Array,
			default: () => []
		},
		loadingRegions: Boolean
	}, {
		"modelValue": {
			type: Boolean,
			default: false
		},
		"modelModifiers": {}
	}),
	emits: /* @__PURE__ */ mergeModels(["save"], ["update:modelValue"]),
	setup(__props, { emit: __emit }) {
		const model = useModel(__props, "modelValue");
		const props = __props;
		const emit = __emit;
		const emptyForm = () => ({
			name: null,
			region_id: null,
			wiki: null,
			yandexmapsgeo: null,
			twogis: null,
			latitude: null,
			longitude: null,
			populations: []
		});
		const form = reactive(emptyForm());
		const title = computed(() => {
			return props.mode === "create" ? "Добавить город" : `Редактировать город: ${props.city?.name ?? ""}`;
		});
		function resetForm() {
			Object.assign(form, emptyForm());
			if (props.city) {
				form.name = props.city.name;
				form.region_id = props.city.region_id;
				form.wiki = props.city.wiki;
				form.yandexmapsgeo = props.city.yandexmapsgeo;
				form.twogis = props.city.twogis;
				form.latitude = props.city.latitude;
				form.longitude = props.city.longitude;
				form.populations = (props.city.populations ?? []).map((item) => ({
					year: item.year,
					population: item.population
				}));
			}
		}
		function addPopulationRow() {
			form.populations.push({
				year: (/* @__PURE__ */ new Date()).getFullYear(),
				population: null
			});
		}
		function removePopulationRow(index) {
			form.populations.splice(index, 1);
		}
		function submit() {
			emit("save", {
				name: form.name,
				region_id: form.region_id,
				wiki: form.wiki,
				yandexmapsgeo: form.yandexmapsgeo,
				twogis: form.twogis,
				latitude: form.latitude,
				longitude: form.longitude,
				populations: form.populations.filter((item) => item.year && item.population !== null && item.population !== "").map((item) => ({
					year: Number(item.year),
					population: Number(item.population)
				}))
			});
		}
		function openWiki() {
			if (!form.wiki) return;
			window.open(form.wiki, "_blank", "noopener,noreferrer");
		}
		watch(() => model.value, (value) => {
			if (value) resetForm();
		});
		watch(() => props.city, () => {
			if (model.value) resetForm();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VDialog, mergeProps({
				modelValue: model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				width: "980",
				scrollable: ""
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VCard, null, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<span${_scopeId}>${ssrInterpolate(title.value)}</span>`);
											_push(ssrRenderComponent(VBtn, {
												icon: "mdi-close",
												variant: "text",
												onClick: ($event) => model.value = false
											}, null, _parent, _scopeId));
										} else return [createVNode("span", null, toDisplayString(title.value), 1), createVNode(VBtn, {
											icon: "mdi-close",
											variant: "text",
											onClick: ($event) => model.value = false
										}, null, 8, ["onClick"])];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
								_push(ssrRenderComponent(VCardText, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VForm, { onSubmit: submit }, {
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
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: form.name,
																			"onUpdate:modelValue": ($event) => form.name = $event,
																			label: "Название города",
																			variant: "solo",
																			density: "comfortable",
																			color: "teal",
																			class: "font-UnderdogRegular"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: form.name,
																			"onUpdate:modelValue": ($event) => form.name = $event,
																			label: "Название города",
																			variant: "solo",
																			density: "comfortable",
																			color: "teal",
																			class: "font-UnderdogRegular"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "6"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VAutocomplete, {
																			modelValue: form.region_id,
																			"onUpdate:modelValue": ($event) => form.region_id = $event,
																			items: __props.regions,
																			loading: __props.loadingRegions,
																			"item-title": "name",
																			"item-value": "id",
																			label: "Регион",
																			variant: "filled",
																			density: "comfortable",
																			color: "purple"
																		}, {
																			item: withCtx(({ props: itemProps, item }, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VListItem, mergeProps(itemProps, { subtitle: item.raw.country?.name }), null, _parent, _scopeId));
																				else return [createVNode(VListItem, mergeProps(itemProps, { subtitle: item.raw.country?.name }), null, 16, ["subtitle"])];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		else return [createVNode(VAutocomplete, {
																			modelValue: form.region_id,
																			"onUpdate:modelValue": ($event) => form.region_id = $event,
																			items: __props.regions,
																			loading: __props.loadingRegions,
																			"item-title": "name",
																			"item-value": "id",
																			label: "Регион",
																			variant: "filled",
																			density: "comfortable",
																			color: "purple"
																		}, {
																			item: withCtx(({ props: itemProps, item }) => [createVNode(VListItem, mergeProps(itemProps, { subtitle: item.raw.country?.name }), null, 16, ["subtitle"])]),
																			_: 1
																		}, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"items",
																			"loading"
																		])];
																	}),
																	_: 1
																}, _parent, _scopeId));
															} else return [createVNode(VCol, {
																cols: "12",
																md: "6"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.name,
																	"onUpdate:modelValue": ($event) => form.name = $event,
																	label: "Название города",
																	variant: "solo",
																	density: "comfortable",
																	color: "teal",
																	class: "font-UnderdogRegular"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}), createVNode(VCol, {
																cols: "12",
																md: "6"
															}, {
																default: withCtx(() => [createVNode(VAutocomplete, {
																	modelValue: form.region_id,
																	"onUpdate:modelValue": ($event) => form.region_id = $event,
																	items: __props.regions,
																	loading: __props.loadingRegions,
																	"item-title": "name",
																	"item-value": "id",
																	label: "Регион",
																	variant: "filled",
																	density: "comfortable",
																	color: "purple"
																}, {
																	item: withCtx(({ props: itemProps, item }) => [createVNode(VListItem, mergeProps(itemProps, { subtitle: item.raw.country?.name }), null, 16, ["subtitle"])]),
																	_: 1
																}, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items",
																	"loading"
																])]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VRow, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VCol, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VTextField, {
																		modelValue: form.wiki,
																		"onUpdate:modelValue": ($event) => form.wiki = $event,
																		label: "Wikipedia",
																		placeholder: "https://ru.wikipedia.org/wiki/Москва",
																		variant: "outlined",
																		density: "comfortable",
																		color: "blue",
																		clearable: ""
																	}, {
																		"append-inner": withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VBtn, {
																				icon: "mdi-open-in-new",
																				size: "x-small",
																				variant: "text",
																				color: "blue-lighten-2",
																				disabled: !form.wiki,
																				title: "Открыть статью в новой вкладке",
																				onClick: openWiki
																			}, null, _parent, _scopeId));
																			else return [createVNode(VBtn, {
																				icon: "mdi-open-in-new",
																				size: "x-small",
																				variant: "text",
																				color: "blue-lighten-2",
																				disabled: !form.wiki,
																				title: "Открыть статью в новой вкладке",
																				onClick: withModifiers(openWiki, ["stop"])
																			}, null, 8, ["disabled"])];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VTextField, {
																		modelValue: form.wiki,
																		"onUpdate:modelValue": ($event) => form.wiki = $event,
																		label: "Wikipedia",
																		placeholder: "https://ru.wikipedia.org/wiki/Москва",
																		variant: "outlined",
																		density: "comfortable",
																		color: "blue",
																		clearable: ""
																	}, {
																		"append-inner": withCtx(() => [createVNode(VBtn, {
																			icon: "mdi-open-in-new",
																			size: "x-small",
																			variant: "text",
																			color: "blue-lighten-2",
																			disabled: !form.wiki,
																			title: "Открыть статью в новой вкладке",
																			onClick: withModifiers(openWiki, ["stop"])
																		}, null, 8, ["disabled"])]),
																		_: 1
																	}, 8, ["modelValue", "onUpdate:modelValue"])];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.wiki,
																	"onUpdate:modelValue": ($event) => form.wiki = $event,
																	label: "Wikipedia",
																	placeholder: "https://ru.wikipedia.org/wiki/Москва",
																	variant: "outlined",
																	density: "comfortable",
																	color: "blue",
																	clearable: ""
																}, {
																	"append-inner": withCtx(() => [createVNode(VBtn, {
																		icon: "mdi-open-in-new",
																		size: "x-small",
																		variant: "text",
																		color: "blue-lighten-2",
																		disabled: !form.wiki,
																		title: "Открыть статью в новой вкладке",
																		onClick: withModifiers(openWiki, ["stop"])
																	}, null, 8, ["disabled"])]),
																	_: 1
																}, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VRow, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VCol, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VTextField, {
																		modelValue: form.yandexmapsgeo,
																		"onUpdate:modelValue": ($event) => form.yandexmapsgeo = $event,
																		label: "Yandex Maps Geo",
																		variant: "solo",
																		density: "comfortable"
																	}, null, _parent, _scopeId));
																	else return [createVNode(VTextField, {
																		modelValue: form.yandexmapsgeo,
																		"onUpdate:modelValue": ($event) => form.yandexmapsgeo = $event,
																		label: "Yandex Maps Geo",
																		variant: "solo",
																		density: "comfortable"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.yandexmapsgeo,
																	"onUpdate:modelValue": ($event) => form.yandexmapsgeo = $event,
																	label: "Yandex Maps Geo",
																	variant: "solo",
																	density: "comfortable"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
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
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: form.latitude,
																			"onUpdate:modelValue": ($event) => form.latitude = $event,
																			label: "Широта",
																			variant: "outlined",
																			density: "comfortable",
																			type: "number"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: form.latitude,
																			"onUpdate:modelValue": ($event) => form.latitude = $event,
																			label: "Широта",
																			variant: "outlined",
																			density: "comfortable",
																			type: "number"
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
																			modelValue: form.longitude,
																			"onUpdate:modelValue": ($event) => form.longitude = $event,
																			label: "Долгота",
																			variant: "outlined",
																			density: "comfortable",
																			type: "number"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: form.longitude,
																			"onUpdate:modelValue": ($event) => form.longitude = $event,
																			label: "Долгота",
																			variant: "outlined",
																			density: "comfortable",
																			type: "number"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
															} else return [createVNode(VCol, {
																cols: "12",
																md: "6"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.latitude,
																	"onUpdate:modelValue": ($event) => form.latitude = $event,
																	label: "Широта",
																	variant: "outlined",
																	density: "comfortable",
																	type: "number"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}), createVNode(VCol, {
																cols: "12",
																md: "6"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.longitude,
																	"onUpdate:modelValue": ($event) => form.longitude = $event,
																	label: "Долгота",
																	variant: "outlined",
																	density: "comfortable",
																	type: "number"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VRow, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VCol, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VTextField, {
																		modelValue: form.twogis,
																		"onUpdate:modelValue": ($event) => form.twogis = $event,
																		label: "2GIS",
																		variant: "solo",
																		density: "compact"
																	}, null, _parent, _scopeId));
																	else return [createVNode(VTextField, {
																		modelValue: form.twogis,
																		"onUpdate:modelValue": ($event) => form.twogis = $event,
																		label: "2GIS",
																		variant: "solo",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.twogis,
																	"onUpdate:modelValue": ($event) => form.twogis = $event,
																	label: "2GIS",
																	variant: "solo",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VDivider, { class: "my-4" }, null, _parent, _scopeId));
													_push(`<div class="d-flex justify-space-between align-center mb-2"${_scopeId}><div${_scopeId}><div class="text-subtitle-2"${_scopeId}> Население по годам </div><div class="text-caption text-grey"${_scopeId}> Можно добавить сразу несколько значений: год — численность. </div></div>`);
													_push(ssrRenderComponent(VBtn, {
														text: "+ год",
														color: "teal",
														variant: "tonal",
														density: "compact",
														onClick: addPopulationRow
													}, null, _parent, _scopeId));
													_push(`</div><!--[-->`);
													ssrRenderList(form.populations, (population, index) => {
														_push(ssrRenderComponent(VRow, {
															key: index,
															dense: ""
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	_push(ssrRenderComponent(VCol, { cols: "5" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VTextField, {
																				modelValue: population.year,
																				"onUpdate:modelValue": ($event) => population.year = $event,
																				label: "Год",
																				type: "number",
																				variant: "outlined",
																				density: "compact",
																				"hide-details": ""
																			}, null, _parent, _scopeId));
																			else return [createVNode(VTextField, {
																				modelValue: population.year,
																				"onUpdate:modelValue": ($event) => population.year = $event,
																				label: "Год",
																				type: "number",
																				variant: "outlined",
																				density: "compact",
																				"hide-details": ""
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																		}),
																		_: 2
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VCol, { cols: "6" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VTextField, {
																				modelValue: population.population,
																				"onUpdate:modelValue": ($event) => population.population = $event,
																				label: "Население",
																				type: "number",
																				variant: "outlined",
																				density: "compact",
																				"hide-details": ""
																			}, null, _parent, _scopeId));
																			else return [createVNode(VTextField, {
																				modelValue: population.population,
																				"onUpdate:modelValue": ($event) => population.population = $event,
																				label: "Население",
																				type: "number",
																				variant: "outlined",
																				density: "compact",
																				"hide-details": ""
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																		}),
																		_: 2
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VCol, {
																		cols: "1",
																		class: "d-flex align-center"
																	}, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VBtn, {
																				icon: "mdi-close",
																				size: "x-small",
																				color: "red",
																				variant: "text",
																				onClick: ($event) => removePopulationRow(index)
																			}, null, _parent, _scopeId));
																			else return [createVNode(VBtn, {
																				icon: "mdi-close",
																				size: "x-small",
																				color: "red",
																				variant: "text",
																				onClick: ($event) => removePopulationRow(index)
																			}, null, 8, ["onClick"])];
																		}),
																		_: 2
																	}, _parent, _scopeId));
																} else return [
																	createVNode(VCol, { cols: "5" }, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: population.year,
																			"onUpdate:modelValue": ($event) => population.year = $event,
																			label: "Год",
																			type: "number",
																			variant: "outlined",
																			density: "compact",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, { cols: "6" }, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: population.population,
																			"onUpdate:modelValue": ($event) => population.population = $event,
																			label: "Население",
																			type: "number",
																			variant: "outlined",
																			density: "compact",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, {
																		cols: "1",
																		class: "d-flex align-center"
																	}, {
																		default: withCtx(() => [createVNode(VBtn, {
																			icon: "mdi-close",
																			size: "x-small",
																			color: "red",
																			variant: "text",
																			onClick: ($event) => removePopulationRow(index)
																		}, null, 8, ["onClick"])]),
																		_: 2
																	}, 1024)
																];
															}),
															_: 2
														}, _parent, _scopeId));
													});
													_push(`<!--]-->`);
												} else return [
													createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, {
															cols: "12",
															md: "6"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.name,
																"onUpdate:modelValue": ($event) => form.name = $event,
																label: "Название города",
																variant: "solo",
																density: "comfortable",
																color: "teal",
																class: "font-UnderdogRegular"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}), createVNode(VCol, {
															cols: "12",
															md: "6"
														}, {
															default: withCtx(() => [createVNode(VAutocomplete, {
																modelValue: form.region_id,
																"onUpdate:modelValue": ($event) => form.region_id = $event,
																items: __props.regions,
																loading: __props.loadingRegions,
																"item-title": "name",
																"item-value": "id",
																label: "Регион",
																variant: "filled",
																density: "comfortable",
																color: "purple"
															}, {
																item: withCtx(({ props: itemProps, item }) => [createVNode(VListItem, mergeProps(itemProps, { subtitle: item.raw.country?.name }), null, 16, ["subtitle"])]),
																_: 1
															}, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items",
																"loading"
															])]),
															_: 1
														})]),
														_: 1
													}),
													createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.wiki,
																"onUpdate:modelValue": ($event) => form.wiki = $event,
																label: "Wikipedia",
																placeholder: "https://ru.wikipedia.org/wiki/Москва",
																variant: "outlined",
																density: "comfortable",
																color: "blue",
																clearable: ""
															}, {
																"append-inner": withCtx(() => [createVNode(VBtn, {
																	icon: "mdi-open-in-new",
																	size: "x-small",
																	variant: "text",
																	color: "blue-lighten-2",
																	disabled: !form.wiki,
																	title: "Открыть статью в новой вкладке",
																	onClick: withModifiers(openWiki, ["stop"])
																}, null, 8, ["disabled"])]),
																_: 1
															}, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})]),
														_: 1
													}),
													createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.yandexmapsgeo,
																"onUpdate:modelValue": ($event) => form.yandexmapsgeo = $event,
																label: "Yandex Maps Geo",
																variant: "solo",
																density: "comfortable"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})]),
														_: 1
													}),
													createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, {
															cols: "12",
															md: "6"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.latitude,
																"onUpdate:modelValue": ($event) => form.latitude = $event,
																label: "Широта",
																variant: "outlined",
																density: "comfortable",
																type: "number"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}), createVNode(VCol, {
															cols: "12",
															md: "6"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.longitude,
																"onUpdate:modelValue": ($event) => form.longitude = $event,
																label: "Долгота",
																variant: "outlined",
																density: "comfortable",
																type: "number"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})]),
														_: 1
													}),
													createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.twogis,
																"onUpdate:modelValue": ($event) => form.twogis = $event,
																label: "2GIS",
																variant: "solo",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})]),
														_: 1
													}),
													createVNode(VDivider, { class: "my-4" }),
													createVNode("div", { class: "d-flex justify-space-between align-center mb-2" }, [createVNode("div", null, [createVNode("div", { class: "text-subtitle-2" }, " Население по годам "), createVNode("div", { class: "text-caption text-grey" }, " Можно добавить сразу несколько значений: год — численность. ")]), createVNode(VBtn, {
														text: "+ год",
														color: "teal",
														variant: "tonal",
														density: "compact",
														onClick: addPopulationRow
													})]),
													(openBlock(true), createBlock(Fragment, null, renderList(form.populations, (population, index) => {
														return openBlock(), createBlock(VRow, {
															key: index,
															dense: ""
														}, {
															default: withCtx(() => [
																createVNode(VCol, { cols: "5" }, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: population.year,
																		"onUpdate:modelValue": ($event) => population.year = $event,
																		label: "Год",
																		type: "number",
																		variant: "outlined",
																		density: "compact",
																		"hide-details": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 2
																}, 1024),
																createVNode(VCol, { cols: "6" }, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: population.population,
																		"onUpdate:modelValue": ($event) => population.population = $event,
																		label: "Население",
																		type: "number",
																		variant: "outlined",
																		density: "compact",
																		"hide-details": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 2
																}, 1024),
																createVNode(VCol, {
																	cols: "1",
																	class: "d-flex align-center"
																}, {
																	default: withCtx(() => [createVNode(VBtn, {
																		icon: "mdi-close",
																		size: "x-small",
																		color: "red",
																		variant: "text",
																		onClick: ($event) => removePopulationRow(index)
																	}, null, 8, ["onClick"])]),
																	_: 2
																}, 1024)
															]),
															_: 2
														}, 1024);
													}), 128))
												];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VForm, { onSubmit: withModifiers(submit, ["prevent"]) }, {
											default: withCtx(() => [
												createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, {
														cols: "12",
														md: "6"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.name,
															"onUpdate:modelValue": ($event) => form.name = $event,
															label: "Название города",
															variant: "solo",
															density: "comfortable",
															color: "teal",
															class: "font-UnderdogRegular"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}), createVNode(VCol, {
														cols: "12",
														md: "6"
													}, {
														default: withCtx(() => [createVNode(VAutocomplete, {
															modelValue: form.region_id,
															"onUpdate:modelValue": ($event) => form.region_id = $event,
															items: __props.regions,
															loading: __props.loadingRegions,
															"item-title": "name",
															"item-value": "id",
															label: "Регион",
															variant: "filled",
															density: "comfortable",
															color: "purple"
														}, {
															item: withCtx(({ props: itemProps, item }) => [createVNode(VListItem, mergeProps(itemProps, { subtitle: item.raw.country?.name }), null, 16, ["subtitle"])]),
															_: 1
														}, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items",
															"loading"
														])]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.wiki,
															"onUpdate:modelValue": ($event) => form.wiki = $event,
															label: "Wikipedia",
															placeholder: "https://ru.wikipedia.org/wiki/Москва",
															variant: "outlined",
															density: "comfortable",
															color: "blue",
															clearable: ""
														}, {
															"append-inner": withCtx(() => [createVNode(VBtn, {
																icon: "mdi-open-in-new",
																size: "x-small",
																variant: "text",
																color: "blue-lighten-2",
																disabled: !form.wiki,
																title: "Открыть статью в новой вкладке",
																onClick: withModifiers(openWiki, ["stop"])
															}, null, 8, ["disabled"])]),
															_: 1
														}, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.yandexmapsgeo,
															"onUpdate:modelValue": ($event) => form.yandexmapsgeo = $event,
															label: "Yandex Maps Geo",
															variant: "solo",
															density: "comfortable"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, {
														cols: "12",
														md: "6"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.latitude,
															"onUpdate:modelValue": ($event) => form.latitude = $event,
															label: "Широта",
															variant: "outlined",
															density: "comfortable",
															type: "number"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}), createVNode(VCol, {
														cols: "12",
														md: "6"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.longitude,
															"onUpdate:modelValue": ($event) => form.longitude = $event,
															label: "Долгота",
															variant: "outlined",
															density: "comfortable",
															type: "number"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.twogis,
															"onUpdate:modelValue": ($event) => form.twogis = $event,
															label: "2GIS",
															variant: "solo",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VDivider, { class: "my-4" }),
												createVNode("div", { class: "d-flex justify-space-between align-center mb-2" }, [createVNode("div", null, [createVNode("div", { class: "text-subtitle-2" }, " Население по годам "), createVNode("div", { class: "text-caption text-grey" }, " Можно добавить сразу несколько значений: год — численность. ")]), createVNode(VBtn, {
													text: "+ год",
													color: "teal",
													variant: "tonal",
													density: "compact",
													onClick: addPopulationRow
												})]),
												(openBlock(true), createBlock(Fragment, null, renderList(form.populations, (population, index) => {
													return openBlock(), createBlock(VRow, {
														key: index,
														dense: ""
													}, {
														default: withCtx(() => [
															createVNode(VCol, { cols: "5" }, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: population.year,
																	"onUpdate:modelValue": ($event) => population.year = $event,
																	label: "Год",
																	type: "number",
																	variant: "outlined",
																	density: "compact",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 2
															}, 1024),
															createVNode(VCol, { cols: "6" }, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: population.population,
																	"onUpdate:modelValue": ($event) => population.population = $event,
																	label: "Население",
																	type: "number",
																	variant: "outlined",
																	density: "compact",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 2
															}, 1024),
															createVNode(VCol, {
																cols: "1",
																class: "d-flex align-center"
															}, {
																default: withCtx(() => [createVNode(VBtn, {
																	icon: "mdi-close",
																	size: "x-small",
																	color: "red",
																	variant: "text",
																	onClick: ($event) => removePopulationRow(index)
																}, null, 8, ["onClick"])]),
																_: 2
															}, 1024)
														]),
														_: 2
													}, 1024);
												}), 128))
											]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
								_push(ssrRenderComponent(VCardActions, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VSpacer, null, null, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												text: "Отмена",
												variant: "text",
												onClick: ($event) => model.value = false
											}, null, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												text: "Сохранить",
												color: "teal",
												variant: "elevated",
												onClick: submit
											}, null, _parent, _scopeId));
										} else return [
											createVNode(VSpacer),
											createVNode(VBtn, {
												text: "Отмена",
												variant: "text",
												onClick: ($event) => model.value = false
											}, null, 8, ["onClick"]),
											createVNode(VBtn, {
												text: "Сохранить",
												color: "teal",
												variant: "elevated",
												onClick: submit
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
									default: withCtx(() => [createVNode("span", null, toDisplayString(title.value), 1), createVNode(VBtn, {
										icon: "mdi-close",
										variant: "text",
										onClick: ($event) => model.value = false
									}, null, 8, ["onClick"])]),
									_: 1
								}),
								createVNode(VDivider),
								createVNode(VCardText, null, {
									default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(submit, ["prevent"]) }, {
										default: withCtx(() => [
											createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.name,
														"onUpdate:modelValue": ($event) => form.name = $event,
														label: "Название города",
														variant: "solo",
														density: "comfortable",
														color: "teal",
														class: "font-UnderdogRegular"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}), createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VAutocomplete, {
														modelValue: form.region_id,
														"onUpdate:modelValue": ($event) => form.region_id = $event,
														items: __props.regions,
														loading: __props.loadingRegions,
														"item-title": "name",
														"item-value": "id",
														label: "Регион",
														variant: "filled",
														density: "comfortable",
														color: "purple"
													}, {
														item: withCtx(({ props: itemProps, item }) => [createVNode(VListItem, mergeProps(itemProps, { subtitle: item.raw.country?.name }), null, 16, ["subtitle"])]),
														_: 1
													}, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items",
														"loading"
													])]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.wiki,
														"onUpdate:modelValue": ($event) => form.wiki = $event,
														label: "Wikipedia",
														placeholder: "https://ru.wikipedia.org/wiki/Москва",
														variant: "outlined",
														density: "comfortable",
														color: "blue",
														clearable: ""
													}, {
														"append-inner": withCtx(() => [createVNode(VBtn, {
															icon: "mdi-open-in-new",
															size: "x-small",
															variant: "text",
															color: "blue-lighten-2",
															disabled: !form.wiki,
															title: "Открыть статью в новой вкладке",
															onClick: withModifiers(openWiki, ["stop"])
														}, null, 8, ["disabled"])]),
														_: 1
													}, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.yandexmapsgeo,
														"onUpdate:modelValue": ($event) => form.yandexmapsgeo = $event,
														label: "Yandex Maps Geo",
														variant: "solo",
														density: "comfortable"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.latitude,
														"onUpdate:modelValue": ($event) => form.latitude = $event,
														label: "Широта",
														variant: "outlined",
														density: "comfortable",
														type: "number"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}), createVNode(VCol, {
													cols: "12",
													md: "6"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.longitude,
														"onUpdate:modelValue": ($event) => form.longitude = $event,
														label: "Долгота",
														variant: "outlined",
														density: "comfortable",
														type: "number"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.twogis,
														"onUpdate:modelValue": ($event) => form.twogis = $event,
														label: "2GIS",
														variant: "solo",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VDivider, { class: "my-4" }),
											createVNode("div", { class: "d-flex justify-space-between align-center mb-2" }, [createVNode("div", null, [createVNode("div", { class: "text-subtitle-2" }, " Население по годам "), createVNode("div", { class: "text-caption text-grey" }, " Можно добавить сразу несколько значений: год — численность. ")]), createVNode(VBtn, {
												text: "+ год",
												color: "teal",
												variant: "tonal",
												density: "compact",
												onClick: addPopulationRow
											})]),
											(openBlock(true), createBlock(Fragment, null, renderList(form.populations, (population, index) => {
												return openBlock(), createBlock(VRow, {
													key: index,
													dense: ""
												}, {
													default: withCtx(() => [
														createVNode(VCol, { cols: "5" }, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: population.year,
																"onUpdate:modelValue": ($event) => population.year = $event,
																label: "Год",
																type: "number",
																variant: "outlined",
																density: "compact",
																"hide-details": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 2
														}, 1024),
														createVNode(VCol, { cols: "6" }, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: population.population,
																"onUpdate:modelValue": ($event) => population.population = $event,
																label: "Население",
																type: "number",
																variant: "outlined",
																density: "compact",
																"hide-details": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 2
														}, 1024),
														createVNode(VCol, {
															cols: "1",
															class: "d-flex align-center"
														}, {
															default: withCtx(() => [createVNode(VBtn, {
																icon: "mdi-close",
																size: "x-small",
																color: "red",
																variant: "text",
																onClick: ($event) => removePopulationRow(index)
															}, null, 8, ["onClick"])]),
															_: 2
														}, 1024)
													]),
													_: 2
												}, 1024);
											}), 128))
										]),
										_: 1
									})]),
									_: 1
								}),
								createVNode(VDivider),
								createVNode(VCardActions, null, {
									default: withCtx(() => [
										createVNode(VSpacer),
										createVNode(VBtn, {
											text: "Отмена",
											variant: "text",
											onClick: ($event) => model.value = false
										}, null, 8, ["onClick"]),
										createVNode(VBtn, {
											text: "Сохранить",
											color: "teal",
											variant: "elevated",
											onClick: submit
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
							createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
								default: withCtx(() => [createVNode("span", null, toDisplayString(title.value), 1), createVNode(VBtn, {
									icon: "mdi-close",
									variant: "text",
									onClick: ($event) => model.value = false
								}, null, 8, ["onClick"])]),
								_: 1
							}),
							createVNode(VDivider),
							createVNode(VCardText, null, {
								default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(submit, ["prevent"]) }, {
									default: withCtx(() => [
										createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.name,
													"onUpdate:modelValue": ($event) => form.name = $event,
													label: "Название города",
													variant: "solo",
													density: "comfortable",
													color: "teal",
													class: "font-UnderdogRegular"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}), createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VAutocomplete, {
													modelValue: form.region_id,
													"onUpdate:modelValue": ($event) => form.region_id = $event,
													items: __props.regions,
													loading: __props.loadingRegions,
													"item-title": "name",
													"item-value": "id",
													label: "Регион",
													variant: "filled",
													density: "comfortable",
													color: "purple"
												}, {
													item: withCtx(({ props: itemProps, item }) => [createVNode(VListItem, mergeProps(itemProps, { subtitle: item.raw.country?.name }), null, 16, ["subtitle"])]),
													_: 1
												}, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items",
													"loading"
												])]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, null, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.wiki,
													"onUpdate:modelValue": ($event) => form.wiki = $event,
													label: "Wikipedia",
													placeholder: "https://ru.wikipedia.org/wiki/Москва",
													variant: "outlined",
													density: "comfortable",
													color: "blue",
													clearable: ""
												}, {
													"append-inner": withCtx(() => [createVNode(VBtn, {
														icon: "mdi-open-in-new",
														size: "x-small",
														variant: "text",
														color: "blue-lighten-2",
														disabled: !form.wiki,
														title: "Открыть статью в новой вкладке",
														onClick: withModifiers(openWiki, ["stop"])
													}, null, 8, ["disabled"])]),
													_: 1
												}, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, null, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.yandexmapsgeo,
													"onUpdate:modelValue": ($event) => form.yandexmapsgeo = $event,
													label: "Yandex Maps Geo",
													variant: "solo",
													density: "comfortable"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.latitude,
													"onUpdate:modelValue": ($event) => form.latitude = $event,
													label: "Широта",
													variant: "outlined",
													density: "comfortable",
													type: "number"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}), createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.longitude,
													"onUpdate:modelValue": ($event) => form.longitude = $event,
													label: "Долгота",
													variant: "outlined",
													density: "comfortable",
													type: "number"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, null, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.twogis,
													"onUpdate:modelValue": ($event) => form.twogis = $event,
													label: "2GIS",
													variant: "solo",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VDivider, { class: "my-4" }),
										createVNode("div", { class: "d-flex justify-space-between align-center mb-2" }, [createVNode("div", null, [createVNode("div", { class: "text-subtitle-2" }, " Население по годам "), createVNode("div", { class: "text-caption text-grey" }, " Можно добавить сразу несколько значений: год — численность. ")]), createVNode(VBtn, {
											text: "+ год",
											color: "teal",
											variant: "tonal",
											density: "compact",
											onClick: addPopulationRow
										})]),
										(openBlock(true), createBlock(Fragment, null, renderList(form.populations, (population, index) => {
											return openBlock(), createBlock(VRow, {
												key: index,
												dense: ""
											}, {
												default: withCtx(() => [
													createVNode(VCol, { cols: "5" }, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: population.year,
															"onUpdate:modelValue": ($event) => population.year = $event,
															label: "Год",
															type: "number",
															variant: "outlined",
															density: "compact",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 2
													}, 1024),
													createVNode(VCol, { cols: "6" }, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: population.population,
															"onUpdate:modelValue": ($event) => population.population = $event,
															label: "Население",
															type: "number",
															variant: "outlined",
															density: "compact",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 2
													}, 1024),
													createVNode(VCol, {
														cols: "1",
														class: "d-flex align-center"
													}, {
														default: withCtx(() => [createVNode(VBtn, {
															icon: "mdi-close",
															size: "x-small",
															color: "red",
															variant: "text",
															onClick: ($event) => removePopulationRow(index)
														}, null, 8, ["onClick"])]),
														_: 2
													}, 1024)
												]),
												_: 2
											}, 1024);
										}), 128))
									]),
									_: 1
								})]),
								_: 1
							}),
							createVNode(VDivider),
							createVNode(VCardActions, null, {
								default: withCtx(() => [
									createVNode(VSpacer),
									createVNode(VBtn, {
										text: "Отмена",
										variant: "text",
										onClick: ($event) => model.value = false
									}, null, 8, ["onClick"]),
									createVNode(VBtn, {
										text: "Сохранить",
										color: "teal",
										variant: "elevated",
										onClick: submit
									})
								]),
								_: 1
							})
						]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/CityFormDialog.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Geography/Cities/CityPopulationDialog.vue
var _sfc_main = {
	__name: "CityPopulationDialog",
	__ssrInlineRender: true,
	props: /* @__PURE__ */ mergeModels({ city: {
		type: Object,
		default: null
	} }, {
		"modelValue": {
			type: Boolean,
			default: false
		},
		"modelModifiers": {}
	}),
	emits: /* @__PURE__ */ mergeModels(["saved"], ["update:modelValue"]),
	setup(__props, { emit: __emit }) {
		const model = useModel(__props, "modelValue");
		const props = __props;
		const emit = __emit;
		const loading = ref(false);
		const saving = ref(false);
		const populations = ref([]);
		const form = reactive({
			id: null,
			year: (/* @__PURE__ */ new Date()).getFullYear(),
			population: null
		});
		const isEdit = computed(() => Boolean(form.id));
		const title = computed(() => {
			return props.city ? `Население: ${props.city.name}` : "Население города";
		});
		function resetForm() {
			form.id = null;
			form.year = (/* @__PURE__ */ new Date()).getFullYear();
			form.population = null;
		}
		async function fetchPopulations() {
			if (!props.city?.id) return;
			loading.value = true;
			try {
				populations.value = (await axios.get(route("cities.populations.index", props.city.id))).data;
			} catch (error) {
				console.error("City populations loading error:", error);
			} finally {
				loading.value = false;
			}
		}
		function editPopulation(item) {
			form.id = item.id;
			form.year = item.year;
			form.population = item.population;
		}
		async function savePopulation() {
			if (!props.city?.id) return;
			saving.value = true;
			const payload = {
				year: Number(form.year),
				population: Number(form.population)
			};
			try {
				if (isEdit.value) await axios.patch(route("cities.populations.update", {
					city: props.city.id,
					cityPopulation: form.id
				}), payload);
				else await axios.post(route("cities.populations.store", props.city.id), payload);
				resetForm();
				await fetchPopulations();
				emit("saved");
			} catch (error) {
				console.error("City population saving error:", error);
			} finally {
				saving.value = false;
			}
		}
		async function deletePopulation(item) {
			if (!window.confirm(`Удалить население за ${item.year} год?`)) return;
			try {
				await axios.delete(route("cities.populations.destroy", {
					city: props.city.id,
					cityPopulation: item.id
				}));
				await fetchPopulations();
				emit("saved");
			} catch (error) {
				console.error("City population deleting error:", error);
			}
		}
		function formatNumber(value) {
			return new Intl.NumberFormat("ru-RU").format(value);
		}
		watch(() => model.value, async (value) => {
			if (value) {
				resetForm();
				await fetchPopulations();
			}
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VDialog, mergeProps({
				modelValue: model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				width: "720"
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VCard, null, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<span${_scopeId}>${ssrInterpolate(title.value)}</span>`);
											_push(ssrRenderComponent(VBtn, {
												icon: "mdi-close",
												variant: "text",
												onClick: ($event) => model.value = false
											}, null, _parent, _scopeId));
										} else return [createVNode("span", null, toDisplayString(title.value), 1), createVNode(VBtn, {
											icon: "mdi-close",
											variant: "text",
											onClick: ($event) => model.value = false
										}, null, 8, ["onClick"])];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
								_push(ssrRenderComponent(VCardText, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VRow, { dense: "" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCol, { cols: "4" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VTextField, {
																	modelValue: form.year,
																	"onUpdate:modelValue": ($event) => form.year = $event,
																	label: "Год",
																	type: "number",
																	variant: "outlined",
																	density: "compact"
																}, null, _parent, _scopeId));
																else return [createVNode(VTextField, {
																	modelValue: form.year,
																	"onUpdate:modelValue": ($event) => form.year = $event,
																	label: "Год",
																	type: "number",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCol, { cols: "6" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VTextField, {
																	modelValue: form.population,
																	"onUpdate:modelValue": ($event) => form.population = $event,
																	label: "Население",
																	type: "number",
																	variant: "outlined",
																	density: "compact"
																}, null, _parent, _scopeId));
																else return [createVNode(VTextField, {
																	modelValue: form.population,
																	"onUpdate:modelValue": ($event) => form.population = $event,
																	label: "Население",
																	type: "number",
																	variant: "outlined",
																	density: "compact"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCol, {
															cols: "2",
															class: "d-flex align-center"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VBtn, {
																	text: isEdit.value ? "update" : "add",
																	loading: saving.value,
																	color: "teal",
																	variant: "elevated",
																	density: "compact",
																	onClick: savePopulation
																}, null, _parent, _scopeId));
																else return [createVNode(VBtn, {
																	text: isEdit.value ? "update" : "add",
																	loading: saving.value,
																	color: "teal",
																	variant: "elevated",
																	density: "compact",
																	onClick: savePopulation
																}, null, 8, ["text", "loading"])];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [
														createVNode(VCol, { cols: "4" }, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.year,
																"onUpdate:modelValue": ($event) => form.year = $event,
																label: "Год",
																type: "number",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, { cols: "6" }, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.population,
																"onUpdate:modelValue": ($event) => form.population = $event,
																label: "Население",
																type: "number",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "2",
															class: "d-flex align-center"
														}, {
															default: withCtx(() => [createVNode(VBtn, {
																text: isEdit.value ? "update" : "add",
																loading: saving.value,
																color: "teal",
																variant: "elevated",
																density: "compact",
																onClick: savePopulation
															}, null, 8, ["text", "loading"])]),
															_: 1
														})
													];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VDivider, { class: "my-3" }, null, _parent, _scopeId));
											if (loading.value) _push(ssrRenderComponent(VSkeletonLoader, { type: "table" }, null, _parent, _scopeId));
											else _push(ssrRenderComponent(VTable, {
												density: "compact",
												class: "rounded border"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(`<thead${_scopeId}><tr${_scopeId}><th${_scopeId}>Год</th><th class="text-right"${_scopeId}>Население</th><th class="text-right"${_scopeId}></th></tr></thead><tbody${_scopeId}><!--[-->`);
														ssrRenderList(populations.value, (item) => {
															_push(`<tr${_scopeId}><td${_scopeId}>${ssrInterpolate(item.year)}</td><td class="text-right"${_scopeId}>${ssrInterpolate(formatNumber(item.population))}</td><td class="text-right"${_scopeId}>`);
															_push(ssrRenderComponent(VBtn, {
																icon: "mdi-pencil",
																size: "x-small",
																color: "amber",
																variant: "text",
																onClick: ($event) => editPopulation(item)
															}, null, _parent, _scopeId));
															_push(ssrRenderComponent(VBtn, {
																icon: "mdi-delete",
																size: "x-small",
																color: "red",
																variant: "text",
																onClick: ($event) => deletePopulation(item)
															}, null, _parent, _scopeId));
															_push(`</td></tr>`);
														});
														_push(`<!--]-->`);
														if (!populations.value.length) _push(`<tr${_scopeId}><td colspan="3" class="text-center text-grey py-4"${_scopeId}> Нет данных по населению </td></tr>`);
														else _push(`<!---->`);
														_push(`</tbody>`);
													} else return [createVNode("thead", null, [createVNode("tr", null, [
														createVNode("th", null, "Год"),
														createVNode("th", { class: "text-right" }, "Население"),
														createVNode("th", { class: "text-right" })
													])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(populations.value, (item) => {
														return openBlock(), createBlock("tr", { key: item.id }, [
															createVNode("td", null, toDisplayString(item.year), 1),
															createVNode("td", { class: "text-right" }, toDisplayString(formatNumber(item.population)), 1),
															createVNode("td", { class: "text-right" }, [createVNode(VBtn, {
																icon: "mdi-pencil",
																size: "x-small",
																color: "amber",
																variant: "text",
																onClick: ($event) => editPopulation(item)
															}, null, 8, ["onClick"]), createVNode(VBtn, {
																icon: "mdi-delete",
																size: "x-small",
																color: "red",
																variant: "text",
																onClick: ($event) => deletePopulation(item)
															}, null, 8, ["onClick"])])
														]);
													}), 128)), !populations.value.length ? (openBlock(), createBlock("tr", { key: 0 }, [createVNode("td", {
														colspan: "3",
														class: "text-center text-grey py-4"
													}, " Нет данных по населению ")])) : createCommentVNode("", true)])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VRow, { dense: "" }, {
												default: withCtx(() => [
													createVNode(VCol, { cols: "4" }, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.year,
															"onUpdate:modelValue": ($event) => form.year = $event,
															label: "Год",
															type: "number",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, { cols: "6" }, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.population,
															"onUpdate:modelValue": ($event) => form.population = $event,
															label: "Население",
															type: "number",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "2",
														class: "d-flex align-center"
													}, {
														default: withCtx(() => [createVNode(VBtn, {
															text: isEdit.value ? "update" : "add",
															loading: saving.value,
															color: "teal",
															variant: "elevated",
															density: "compact",
															onClick: savePopulation
														}, null, 8, ["text", "loading"])]),
														_: 1
													})
												]),
												_: 1
											}),
											createVNode(VDivider, { class: "my-3" }),
											loading.value ? (openBlock(), createBlock(VSkeletonLoader, {
												key: 0,
												type: "table"
											})) : (openBlock(), createBlock(VTable, {
												key: 1,
												density: "compact",
												class: "rounded border"
											}, {
												default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
													createVNode("th", null, "Год"),
													createVNode("th", { class: "text-right" }, "Население"),
													createVNode("th", { class: "text-right" })
												])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(populations.value, (item) => {
													return openBlock(), createBlock("tr", { key: item.id }, [
														createVNode("td", null, toDisplayString(item.year), 1),
														createVNode("td", { class: "text-right" }, toDisplayString(formatNumber(item.population)), 1),
														createVNode("td", { class: "text-right" }, [createVNode(VBtn, {
															icon: "mdi-pencil",
															size: "x-small",
															color: "amber",
															variant: "text",
															onClick: ($event) => editPopulation(item)
														}, null, 8, ["onClick"]), createVNode(VBtn, {
															icon: "mdi-delete",
															size: "x-small",
															color: "red",
															variant: "text",
															onClick: ($event) => deletePopulation(item)
														}, null, 8, ["onClick"])])
													]);
												}), 128)), !populations.value.length ? (openBlock(), createBlock("tr", { key: 0 }, [createVNode("td", {
													colspan: "3",
													class: "text-center text-grey py-4"
												}, " Нет данных по населению ")])) : createCommentVNode("", true)])]),
												_: 1
											}))
										];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
									default: withCtx(() => [createVNode("span", null, toDisplayString(title.value), 1), createVNode(VBtn, {
										icon: "mdi-close",
										variant: "text",
										onClick: ($event) => model.value = false
									}, null, 8, ["onClick"])]),
									_: 1
								}),
								createVNode(VDivider),
								createVNode(VCardText, null, {
									default: withCtx(() => [
										createVNode(VRow, { dense: "" }, {
											default: withCtx(() => [
												createVNode(VCol, { cols: "4" }, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.year,
														"onUpdate:modelValue": ($event) => form.year = $event,
														label: "Год",
														type: "number",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "6" }, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.population,
														"onUpdate:modelValue": ($event) => form.population = $event,
														label: "Население",
														type: "number",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "2",
													class: "d-flex align-center"
												}, {
													default: withCtx(() => [createVNode(VBtn, {
														text: isEdit.value ? "update" : "add",
														loading: saving.value,
														color: "teal",
														variant: "elevated",
														density: "compact",
														onClick: savePopulation
													}, null, 8, ["text", "loading"])]),
													_: 1
												})
											]),
											_: 1
										}),
										createVNode(VDivider, { class: "my-3" }),
										loading.value ? (openBlock(), createBlock(VSkeletonLoader, {
											key: 0,
											type: "table"
										})) : (openBlock(), createBlock(VTable, {
											key: 1,
											density: "compact",
											class: "rounded border"
										}, {
											default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
												createVNode("th", null, "Год"),
												createVNode("th", { class: "text-right" }, "Население"),
												createVNode("th", { class: "text-right" })
											])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(populations.value, (item) => {
												return openBlock(), createBlock("tr", { key: item.id }, [
													createVNode("td", null, toDisplayString(item.year), 1),
													createVNode("td", { class: "text-right" }, toDisplayString(formatNumber(item.population)), 1),
													createVNode("td", { class: "text-right" }, [createVNode(VBtn, {
														icon: "mdi-pencil",
														size: "x-small",
														color: "amber",
														variant: "text",
														onClick: ($event) => editPopulation(item)
													}, null, 8, ["onClick"]), createVNode(VBtn, {
														icon: "mdi-delete",
														size: "x-small",
														color: "red",
														variant: "text",
														onClick: ($event) => deletePopulation(item)
													}, null, 8, ["onClick"])])
												]);
											}), 128)), !populations.value.length ? (openBlock(), createBlock("tr", { key: 0 }, [createVNode("td", {
												colspan: "3",
												class: "text-center text-grey py-4"
											}, " Нет данных по населению ")])) : createCommentVNode("", true)])]),
											_: 1
										}))
									]),
									_: 1
								})
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VCard, null, {
						default: withCtx(() => [
							createVNode(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
								default: withCtx(() => [createVNode("span", null, toDisplayString(title.value), 1), createVNode(VBtn, {
									icon: "mdi-close",
									variant: "text",
									onClick: ($event) => model.value = false
								}, null, 8, ["onClick"])]),
								_: 1
							}),
							createVNode(VDivider),
							createVNode(VCardText, null, {
								default: withCtx(() => [
									createVNode(VRow, { dense: "" }, {
										default: withCtx(() => [
											createVNode(VCol, { cols: "4" }, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.year,
													"onUpdate:modelValue": ($event) => form.year = $event,
													label: "Год",
													type: "number",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, { cols: "6" }, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.population,
													"onUpdate:modelValue": ($event) => form.population = $event,
													label: "Население",
													type: "number",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "2",
												class: "d-flex align-center"
											}, {
												default: withCtx(() => [createVNode(VBtn, {
													text: isEdit.value ? "update" : "add",
													loading: saving.value,
													color: "teal",
													variant: "elevated",
													density: "compact",
													onClick: savePopulation
												}, null, 8, ["text", "loading"])]),
												_: 1
											})
										]),
										_: 1
									}),
									createVNode(VDivider, { class: "my-3" }),
									loading.value ? (openBlock(), createBlock(VSkeletonLoader, {
										key: 0,
										type: "table"
									})) : (openBlock(), createBlock(VTable, {
										key: 1,
										density: "compact",
										class: "rounded border"
									}, {
										default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
											createVNode("th", null, "Год"),
											createVNode("th", { class: "text-right" }, "Население"),
											createVNode("th", { class: "text-right" })
										])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(populations.value, (item) => {
											return openBlock(), createBlock("tr", { key: item.id }, [
												createVNode("td", null, toDisplayString(item.year), 1),
												createVNode("td", { class: "text-right" }, toDisplayString(formatNumber(item.population)), 1),
												createVNode("td", { class: "text-right" }, [createVNode(VBtn, {
													icon: "mdi-pencil",
													size: "x-small",
													color: "amber",
													variant: "text",
													onClick: ($event) => editPopulation(item)
												}, null, 8, ["onClick"]), createVNode(VBtn, {
													icon: "mdi-delete",
													size: "x-small",
													color: "red",
													variant: "text",
													onClick: ($event) => deletePopulation(item)
												}, null, 8, ["onClick"])])
											]);
										}), 128)), !populations.value.length ? (openBlock(), createBlock("tr", { key: 0 }, [createVNode("td", {
											colspan: "3",
											class: "text-center text-grey py-4"
										}, " Нет данных по населению ")])) : createCommentVNode("", true)])]),
										_: 1
									}))
								]),
								_: 1
							})
						]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/CityPopulationDialog.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Composables/Geography/useRegions.js
function useRegions() {
	const regions = ref([]);
	const loadingRegions = ref(false);
	async function fetchRegions() {
		loadingRegions.value = true;
		try {
			const response = await axios.get(route("regions.index"), { params: { itemsPerPage: -1 } });
			regions.value = Array.isArray(response.data) ? response.data : response.data.items ?? response.data.data ?? [];
		} catch (error) {
			console.error("Regions loading error:", error);
		} finally {
			loadingRegions.value = false;
		}
	}
	return {
		regions,
		loadingRegions,
		fetchRegions
	};
}
//#endregion
export { _sfc_main as n, _sfc_main$1 as r, useRegions as t };

//# sourceMappingURL=useRegions-CiNRh2jq.js.map