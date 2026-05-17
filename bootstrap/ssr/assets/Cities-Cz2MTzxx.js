import { C as VRow, D as VDataTable, F as VCardText, I as VCardTitle, P as VCard, R as VCardActions, T as VContainer, U as VTextField, V as VAutocomplete, h as VForm, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { Link, useForm } from "@inertiajs/vue3";
import { createTextVNode, createVNode, isRef, mergeProps, onMounted, ref, toDisplayString, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { ssrInterpolate, ssrRenderAttr, ssrRenderComponent } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Ameise/Cities.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Cities",
	__ssrInlineRender: true,
	setup(__props) {
		const headersCities = [
			{
				title: "name",
				key: "name"
			},
			{
				title: "Регион",
				key: "region_id"
			},
			{
				title: "Широта",
				key: "latitude"
			},
			{
				title: "Долгота",
				key: "longitude"
			},
			{
				title: "population",
				key: "population"
			},
			{
				title: "wiki",
				key: "wiki"
			},
			{
				title: "Maps",
				key: "yandexmapsgeo"
			}
		];
		const cities = ref();
		const regions = ref();
		let searchCitiesLike = ref();
		function indexCities(like) {
			axios.get(route("cities.index"), { params: { search: like } }).then(function(response) {
				cities.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		function indexRegions() {
			axios.get(route("regions.index")).then(function(response) {
				regions.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		const formCity = useForm({
			name: null,
			population: null,
			wiki: null,
			region_id: null,
			yandexmapsgeo: null,
			twogis: null,
			latitude: null,
			longitude: null
		});
		function storeCity() {
			formCity.post(route("cities.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: true,
				onSuccess: () => {
					formCity.reset();
					indexCities(searchCitiesLike.value);
				}
			});
		}
		onMounted(() => {
			indexCities();
			indexRegions();
		});
		useHead({
			title: `Cities: Города, пгт, сёла, деревни, станицы`,
			meta: [{
				name: "description",
				content: `Все cities in db`
			}]
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VRow, null, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
								_push(ssrRenderComponent(VCol, { cols: "11" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VDataTable, {
											items: cities.value,
											headers: headersCities,
											"items-per-page": "500",
											density: "compact",
											hover: "hover"
										}, {
											top: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VRow, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCol, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VTextField, {
																		modelValue: unref(searchCitiesLike),
																		"onUpdate:modelValue": ($event) => isRef(searchCitiesLike) ? searchCitiesLike.value = $event : searchCitiesLike = $event,
																		onInput: ($event) => indexCities(unref(searchCitiesLike)),
																		label: "Поиск городов по like",
																		variant: "underlined",
																		density: "comfortable",
																		color: "blue",
																		class: "my-3",
																		flat: ""
																	}, null, _parent, _scopeId));
																	else return [createVNode(VTextField, {
																		modelValue: unref(searchCitiesLike),
																		"onUpdate:modelValue": ($event) => isRef(searchCitiesLike) ? searchCitiesLike.value = $event : searchCitiesLike = $event,
																		onInput: ($event) => indexCities(unref(searchCitiesLike)),
																		label: "Поиск городов по like",
																		variant: "underlined",
																		density: "comfortable",
																		color: "blue",
																		class: "my-3",
																		flat: ""
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"onInput"
																	])];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VDialog, { width: "800" }, {
																		activator: withCtx(({ props: activatorProps }, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VBtn, mergeProps(activatorProps, {
																				text: "+ город",
																				variant: "elevated",
																				density: "comfortable",
																				color: "deep-purple"
																			}), null, _parent, _scopeId));
																			else return [createVNode(VBtn, mergeProps(activatorProps, {
																				text: "+ город",
																				variant: "elevated",
																				density: "comfortable",
																				color: "deep-purple"
																			}), null, 16)];
																		}),
																		default: withCtx(({ isActive }, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VCard, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) {
																						_push(ssrRenderComponent(VCardTitle, null, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(`Form City`);
																								else return [createTextVNode("Form City")];
																							}),
																							_: 2
																						}, _parent, _scopeId));
																						_push(ssrRenderComponent(VCardText, null, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VForm, { onSubmit: () => {} }, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) {
																											_push(ssrRenderComponent(VRow, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(ssrRenderComponent(VTextField, {
																														modelValue: unref(formCity).wiki,
																														"onUpdate:modelValue": ($event) => unref(formCity).wiki = $event,
																														label: "Wiki",
																														variant: "outlined",
																														density: "comfortable"
																													}, null, _parent, _scopeId));
																													else return [createVNode(VTextField, {
																														modelValue: unref(formCity).wiki,
																														"onUpdate:modelValue": ($event) => unref(formCity).wiki = $event,
																														label: "Wiki",
																														variant: "outlined",
																														density: "comfortable"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																											_push(ssrRenderComponent(VRow, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) {
																														_push(ssrRenderComponent(VCol, { cols: "8" }, {
																															default: withCtx((_, _push, _parent, _scopeId) => {
																																if (_push) _push(ssrRenderComponent(VTextField, {
																																	modelValue: unref(formCity).name,
																																	"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																																	label: "Название",
																																	variant: "outlined",
																																	density: "comfortable"
																																}, null, _parent, _scopeId));
																																else return [createVNode(VTextField, {
																																	modelValue: unref(formCity).name,
																																	"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																																	label: "Название",
																																	variant: "outlined",
																																	density: "comfortable"
																																}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																															}),
																															_: 2
																														}, _parent, _scopeId));
																														_push(ssrRenderComponent(VCol, { cols: "4" }, {
																															default: withCtx((_, _push, _parent, _scopeId) => {
																																if (_push) _push(ssrRenderComponent(VTextField, {
																																	modelValue: unref(formCity).population,
																																	"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																																	label: "Население",
																																	variant: "outlined",
																																	density: "comfortable"
																																}, null, _parent, _scopeId));
																																else return [createVNode(VTextField, {
																																	modelValue: unref(formCity).population,
																																	"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																																	label: "Население",
																																	variant: "outlined",
																																	density: "comfortable"
																																}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																															}),
																															_: 2
																														}, _parent, _scopeId));
																													} else return [createVNode(VCol, { cols: "8" }, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(formCity).name,
																															"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																															label: "Название",
																															variant: "outlined",
																															density: "comfortable"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																														_: 1
																													}), createVNode(VCol, { cols: "4" }, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(formCity).population,
																															"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																															label: "Население",
																															variant: "outlined",
																															density: "comfortable"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																														_: 1
																													})];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																											_push(ssrRenderComponent(VRow, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(ssrRenderComponent(VCol, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(ssrRenderComponent(VAutocomplete, {
																																items: regions.value,
																																"item-title": "name",
																																"item-value": "id",
																																label: "Регион",
																																density: "comfortable",
																																color: "purple",
																																modelValue: unref(formCity).region_id,
																																"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																															}, null, _parent, _scopeId));
																															else return [createVNode(VAutocomplete, {
																																items: regions.value,
																																"item-title": "name",
																																"item-value": "id",
																																label: "Регион",
																																density: "comfortable",
																																color: "purple",
																																modelValue: unref(formCity).region_id,
																																"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																															}, null, 8, [
																																"items",
																																"modelValue",
																																"onUpdate:modelValue"
																															])];
																														}),
																														_: 2
																													}, _parent, _scopeId));
																													else return [createVNode(VCol, null, {
																														default: withCtx(() => [createVNode(VAutocomplete, {
																															items: regions.value,
																															"item-title": "name",
																															"item-value": "id",
																															label: "Регион",
																															density: "comfortable",
																															color: "purple",
																															modelValue: unref(formCity).region_id,
																															"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																														}, null, 8, [
																															"items",
																															"modelValue",
																															"onUpdate:modelValue"
																														])]),
																														_: 1
																													})];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																											_push(ssrRenderComponent(VRow, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(ssrRenderComponent(VCol, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(ssrRenderComponent(VTextField, {
																																modelValue: unref(formCity).yandexmapsgeo,
																																"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																																label: "yandex.ru/maps/geo/",
																																variant: "solo",
																																density: "comfortable"
																															}, null, _parent, _scopeId));
																															else return [createVNode(VTextField, {
																																modelValue: unref(formCity).yandexmapsgeo,
																																"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																																label: "yandex.ru/maps/geo/",
																																variant: "solo",
																																density: "comfortable"
																															}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																														}),
																														_: 2
																													}, _parent, _scopeId));
																													else return [createVNode(VCol, null, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(formCity).yandexmapsgeo,
																															"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																															label: "yandex.ru/maps/geo/",
																															variant: "solo",
																															density: "comfortable"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																														_: 1
																													})];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																											_push(ssrRenderComponent(VRow, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) {
																														_push(ssrRenderComponent(VCol, null, {
																															default: withCtx((_, _push, _parent, _scopeId) => {
																																if (_push) _push(ssrRenderComponent(VTextField, {
																																	modelValue: unref(formCity).latitude,
																																	"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																																	label: "Широта",
																																	variant: "outlined",
																																	density: "comfortable"
																																}, null, _parent, _scopeId));
																																else return [createVNode(VTextField, {
																																	modelValue: unref(formCity).latitude,
																																	"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																																	label: "Широта",
																																	variant: "outlined",
																																	density: "comfortable"
																																}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																															}),
																															_: 2
																														}, _parent, _scopeId));
																														_push(ssrRenderComponent(VCol, null, {
																															default: withCtx((_, _push, _parent, _scopeId) => {
																																if (_push) _push(ssrRenderComponent(VTextField, {
																																	modelValue: unref(formCity).longitude,
																																	"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																																	label: "Долгота",
																																	variant: "outlined",
																																	density: "comfortable"
																																}, null, _parent, _scopeId));
																																else return [createVNode(VTextField, {
																																	modelValue: unref(formCity).longitude,
																																	"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																																	label: "Долгота",
																																	variant: "outlined",
																																	density: "comfortable"
																																}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																															}),
																															_: 2
																														}, _parent, _scopeId));
																													} else return [createVNode(VCol, null, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(formCity).latitude,
																															"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																															label: "Широта",
																															variant: "outlined",
																															density: "comfortable"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																														_: 1
																													}), createVNode(VCol, null, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(formCity).longitude,
																															"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																															label: "Долгота",
																															variant: "outlined",
																															density: "comfortable"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																														_: 1
																													})];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																											_push(ssrRenderComponent(VRow, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(ssrRenderComponent(VCol, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(ssrRenderComponent(VTextField, {
																																modelValue: unref(formCity).twogis,
																																"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																																label: "2GIS",
																																variant: "solo",
																																density: "comfortable"
																															}, null, _parent, _scopeId));
																															else return [createVNode(VTextField, {
																																modelValue: unref(formCity).twogis,
																																"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																																label: "2GIS",
																																variant: "solo",
																																density: "comfortable"
																															}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																														}),
																														_: 2
																													}, _parent, _scopeId));
																													else return [createVNode(VCol, null, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(formCity).twogis,
																															"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																															label: "2GIS",
																															variant: "solo",
																															density: "comfortable"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																														_: 1
																													})];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																										} else return [
																											createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formCity).wiki,
																													"onUpdate:modelValue": ($event) => unref(formCity).wiki = $event,
																													label: "Wiki",
																													variant: "outlined",
																													density: "comfortable"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											}),
																											createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formCity).name,
																														"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																														label: "Название",
																														variant: "outlined",
																														density: "comfortable"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												}), createVNode(VCol, { cols: "4" }, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formCity).population,
																														"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																														label: "Население",
																														variant: "outlined",
																														density: "comfortable"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												})]),
																												_: 1
																											}),
																											createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VCol, null, {
																													default: withCtx(() => [createVNode(VAutocomplete, {
																														items: regions.value,
																														"item-title": "name",
																														"item-value": "id",
																														label: "Регион",
																														density: "comfortable",
																														color: "purple",
																														modelValue: unref(formCity).region_id,
																														"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																													}, null, 8, [
																														"items",
																														"modelValue",
																														"onUpdate:modelValue"
																													])]),
																													_: 1
																												})]),
																												_: 1
																											}),
																											createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VCol, null, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formCity).yandexmapsgeo,
																														"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																														label: "yandex.ru/maps/geo/",
																														variant: "solo",
																														density: "comfortable"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												})]),
																												_: 1
																											}),
																											createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VCol, null, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formCity).latitude,
																														"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																														label: "Широта",
																														variant: "outlined",
																														density: "comfortable"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												}), createVNode(VCol, null, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formCity).longitude,
																														"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																														label: "Долгота",
																														variant: "outlined",
																														density: "comfortable"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												})]),
																												_: 1
																											}),
																											createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VCol, null, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formCity).twogis,
																														"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																														label: "2GIS",
																														variant: "solo",
																														density: "comfortable"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												})]),
																												_: 1
																											})
																										];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								else return [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																									default: withCtx(() => [
																										createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formCity).wiki,
																												"onUpdate:modelValue": ($event) => unref(formCity).wiki = $event,
																												label: "Wiki",
																												variant: "outlined",
																												density: "comfortable"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										}),
																										createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formCity).name,
																													"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																													label: "Название",
																													variant: "outlined",
																													density: "comfortable"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											}), createVNode(VCol, { cols: "4" }, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formCity).population,
																													"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																													label: "Население",
																													variant: "outlined",
																													density: "comfortable"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											})]),
																											_: 1
																										}),
																										createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VAutocomplete, {
																													items: regions.value,
																													"item-title": "name",
																													"item-value": "id",
																													label: "Регион",
																													density: "comfortable",
																													color: "purple",
																													modelValue: unref(formCity).region_id,
																													"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																												}, null, 8, [
																													"items",
																													"modelValue",
																													"onUpdate:modelValue"
																												])]),
																												_: 1
																											})]),
																											_: 1
																										}),
																										createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formCity).yandexmapsgeo,
																													"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																													label: "yandex.ru/maps/geo/",
																													variant: "solo",
																													density: "comfortable"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											})]),
																											_: 1
																										}),
																										createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formCity).latitude,
																													"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																													label: "Широта",
																													variant: "outlined",
																													density: "comfortable"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											}), createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formCity).longitude,
																													"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																													label: "Долгота",
																													variant: "outlined",
																													density: "comfortable"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											})]),
																											_: 1
																										}),
																										createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formCity).twogis,
																													"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																													label: "2GIS",
																													variant: "solo",
																													density: "comfortable"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											})]),
																											_: 1
																										})
																									]),
																									_: 1
																								}, 8, ["onSubmit"])];
																							}),
																							_: 2
																						}, _parent, _scopeId));
																						_push(ssrRenderComponent(VCardActions, null, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VBtn, {
																									onClick: storeCity,
																									text: "сохранить",
																									variant: "elevated",
																									color: "green"
																								}, null, _parent, _scopeId));
																								else return [createVNode(VBtn, {
																									onClick: storeCity,
																									text: "сохранить",
																									variant: "elevated",
																									color: "green"
																								})];
																							}),
																							_: 2
																						}, _parent, _scopeId));
																					} else return [
																						createVNode(VCardTitle, null, {
																							default: withCtx(() => [createTextVNode("Form City")]),
																							_: 1
																						}),
																						createVNode(VCardText, null, {
																							default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																								default: withCtx(() => [
																									createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formCity).wiki,
																											"onUpdate:modelValue": ($event) => unref(formCity).wiki = $event,
																											label: "Wiki",
																											variant: "outlined",
																											density: "comfortable"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									}),
																									createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formCity).name,
																												"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																												label: "Название",
																												variant: "outlined",
																												density: "comfortable"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										}), createVNode(VCol, { cols: "4" }, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formCity).population,
																												"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																												label: "Население",
																												variant: "outlined",
																												density: "comfortable"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										})]),
																										_: 1
																									}),
																									createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, null, {
																											default: withCtx(() => [createVNode(VAutocomplete, {
																												items: regions.value,
																												"item-title": "name",
																												"item-value": "id",
																												label: "Регион",
																												density: "comfortable",
																												color: "purple",
																												modelValue: unref(formCity).region_id,
																												"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																											}, null, 8, [
																												"items",
																												"modelValue",
																												"onUpdate:modelValue"
																											])]),
																											_: 1
																										})]),
																										_: 1
																									}),
																									createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, null, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formCity).yandexmapsgeo,
																												"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																												label: "yandex.ru/maps/geo/",
																												variant: "solo",
																												density: "comfortable"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										})]),
																										_: 1
																									}),
																									createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, null, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formCity).latitude,
																												"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																												label: "Широта",
																												variant: "outlined",
																												density: "comfortable"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										}), createVNode(VCol, null, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formCity).longitude,
																												"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																												label: "Долгота",
																												variant: "outlined",
																												density: "comfortable"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										})]),
																										_: 1
																									}),
																									createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, null, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formCity).twogis,
																												"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																												label: "2GIS",
																												variant: "solo",
																												density: "comfortable"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										})]),
																										_: 1
																									})
																								]),
																								_: 1
																							}, 8, ["onSubmit"])]),
																							_: 1
																						}),
																						createVNode(VCardActions, null, {
																							default: withCtx(() => [createVNode(VBtn, {
																								onClick: storeCity,
																								text: "сохранить",
																								variant: "elevated",
																								color: "green"
																							})]),
																							_: 1
																						})
																					];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																			else return [createVNode(VCard, null, {
																				default: withCtx(() => [
																					createVNode(VCardTitle, null, {
																						default: withCtx(() => [createTextVNode("Form City")]),
																						_: 1
																					}),
																					createVNode(VCardText, null, {
																						default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																							default: withCtx(() => [
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formCity).wiki,
																										"onUpdate:modelValue": ($event) => unref(formCity).wiki = $event,
																										label: "Wiki",
																										variant: "outlined",
																										density: "comfortable"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}),
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formCity).name,
																											"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																											label: "Название",
																											variant: "outlined",
																											density: "comfortable"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									}), createVNode(VCol, { cols: "4" }, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formCity).population,
																											"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																											label: "Население",
																											variant: "outlined",
																											density: "comfortable"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									})]),
																									_: 1
																								}),
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VAutocomplete, {
																											items: regions.value,
																											"item-title": "name",
																											"item-value": "id",
																											label: "Регион",
																											density: "comfortable",
																											color: "purple",
																											modelValue: unref(formCity).region_id,
																											"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																										}, null, 8, [
																											"items",
																											"modelValue",
																											"onUpdate:modelValue"
																										])]),
																										_: 1
																									})]),
																									_: 1
																								}),
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formCity).yandexmapsgeo,
																											"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																											label: "yandex.ru/maps/geo/",
																											variant: "solo",
																											density: "comfortable"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									})]),
																									_: 1
																								}),
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formCity).latitude,
																											"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																											label: "Широта",
																											variant: "outlined",
																											density: "comfortable"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									}), createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formCity).longitude,
																											"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																											label: "Долгота",
																											variant: "outlined",
																											density: "comfortable"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									})]),
																									_: 1
																								}),
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formCity).twogis,
																											"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																											label: "2GIS",
																											variant: "solo",
																											density: "comfortable"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									})]),
																									_: 1
																								})
																							]),
																							_: 1
																						}, 8, ["onSubmit"])]),
																						_: 1
																					}),
																					createVNode(VCardActions, null, {
																						default: withCtx(() => [createVNode(VBtn, {
																							onClick: storeCity,
																							text: "сохранить",
																							variant: "elevated",
																							color: "green"
																						})]),
																						_: 1
																					})
																				]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VDialog, { width: "800" }, {
																		activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
																			text: "+ город",
																			variant: "elevated",
																			density: "comfortable",
																			color: "deep-purple"
																		}), null, 16)]),
																		default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																			default: withCtx(() => [
																				createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Form City")]),
																					_: 1
																				}),
																				createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																						default: withCtx(() => [
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formCity).wiki,
																									"onUpdate:modelValue": ($event) => unref(formCity).wiki = $event,
																									label: "Wiki",
																									variant: "outlined",
																									density: "comfortable"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							}),
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formCity).name,
																										"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																										label: "Название",
																										variant: "outlined",
																										density: "comfortable"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}), createVNode(VCol, { cols: "4" }, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formCity).population,
																										"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																										label: "Население",
																										variant: "outlined",
																										density: "comfortable"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								})]),
																								_: 1
																							}),
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VAutocomplete, {
																										items: regions.value,
																										"item-title": "name",
																										"item-value": "id",
																										label: "Регион",
																										density: "comfortable",
																										color: "purple",
																										modelValue: unref(formCity).region_id,
																										"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																									}, null, 8, [
																										"items",
																										"modelValue",
																										"onUpdate:modelValue"
																									])]),
																									_: 1
																								})]),
																								_: 1
																							}),
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formCity).yandexmapsgeo,
																										"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																										label: "yandex.ru/maps/geo/",
																										variant: "solo",
																										density: "comfortable"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								})]),
																								_: 1
																							}),
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formCity).latitude,
																										"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																										label: "Широта",
																										variant: "outlined",
																										density: "comfortable"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}), createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formCity).longitude,
																										"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																										label: "Долгота",
																										variant: "outlined",
																										density: "comfortable"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								})]),
																								_: 1
																							}),
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formCity).twogis,
																										"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																										label: "2GIS",
																										variant: "solo",
																										density: "comfortable"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								})]),
																								_: 1
																							})
																						]),
																						_: 1
																					}, 8, ["onSubmit"])]),
																					_: 1
																				}),
																				createVNode(VCardActions, null, {
																					default: withCtx(() => [createVNode(VBtn, {
																						onClick: storeCity,
																						text: "сохранить",
																						variant: "elevated",
																						color: "green"
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
														} else return [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: unref(searchCitiesLike),
																"onUpdate:modelValue": ($event) => isRef(searchCitiesLike) ? searchCitiesLike.value = $event : searchCitiesLike = $event,
																onInput: ($event) => indexCities(unref(searchCitiesLike)),
																label: "Поиск городов по like",
																variant: "underlined",
																density: "comfortable",
																color: "blue",
																class: "my-3",
																flat: ""
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"onInput"
															])]),
															_: 1
														}), createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VDialog, { width: "800" }, {
																activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
																	text: "+ город",
																	variant: "elevated",
																	density: "comfortable",
																	color: "deep-purple"
																}), null, 16)]),
																default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																	default: withCtx(() => [
																		createVNode(VCardTitle, null, {
																			default: withCtx(() => [createTextVNode("Form City")]),
																			_: 1
																		}),
																		createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																				default: withCtx(() => [
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formCity).wiki,
																							"onUpdate:modelValue": ($event) => unref(formCity).wiki = $event,
																							label: "Wiki",
																							variant: "outlined",
																							density: "comfortable"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formCity).name,
																								"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																								label: "Название",
																								variant: "outlined",
																								density: "comfortable"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}), createVNode(VCol, { cols: "4" }, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formCity).population,
																								"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																								label: "Население",
																								variant: "outlined",
																								density: "comfortable"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VAutocomplete, {
																								items: regions.value,
																								"item-title": "name",
																								"item-value": "id",
																								label: "Регион",
																								density: "comfortable",
																								color: "purple",
																								modelValue: unref(formCity).region_id,
																								"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																							}, null, 8, [
																								"items",
																								"modelValue",
																								"onUpdate:modelValue"
																							])]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formCity).yandexmapsgeo,
																								"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																								label: "yandex.ru/maps/geo/",
																								variant: "solo",
																								density: "comfortable"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formCity).latitude,
																								"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																								label: "Широта",
																								variant: "outlined",
																								density: "comfortable"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}), createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formCity).longitude,
																								"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																								label: "Долгота",
																								variant: "outlined",
																								density: "comfortable"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formCity).twogis,
																								"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																								label: "2GIS",
																								variant: "solo",
																								density: "comfortable"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						})]),
																						_: 1
																					})
																				]),
																				_: 1
																			}, 8, ["onSubmit"])]),
																			_: 1
																		}),
																		createVNode(VCardActions, null, {
																			default: withCtx(() => [createVNode(VBtn, {
																				onClick: storeCity,
																				text: "сохранить",
																				variant: "elevated",
																				color: "green"
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
												else return [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: unref(searchCitiesLike),
															"onUpdate:modelValue": ($event) => isRef(searchCitiesLike) ? searchCitiesLike.value = $event : searchCitiesLike = $event,
															onInput: ($event) => indexCities(unref(searchCitiesLike)),
															label: "Поиск городов по like",
															variant: "underlined",
															density: "comfortable",
															color: "blue",
															class: "my-3",
															flat: ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"onInput"
														])]),
														_: 1
													}), createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VDialog, { width: "800" }, {
															activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
																text: "+ город",
																variant: "elevated",
																density: "comfortable",
																color: "deep-purple"
															}), null, 16)]),
															default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																default: withCtx(() => [
																	createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Form City")]),
																		_: 1
																	}),
																	createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																			default: withCtx(() => [
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formCity).wiki,
																						"onUpdate:modelValue": ($event) => unref(formCity).wiki = $event,
																						label: "Wiki",
																						variant: "outlined",
																						density: "comfortable"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formCity).name,
																							"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																							label: "Название",
																							variant: "outlined",
																							density: "comfortable"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}), createVNode(VCol, { cols: "4" }, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formCity).population,
																							"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																							label: "Население",
																							variant: "outlined",
																							density: "comfortable"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VAutocomplete, {
																							items: regions.value,
																							"item-title": "name",
																							"item-value": "id",
																							label: "Регион",
																							density: "comfortable",
																							color: "purple",
																							modelValue: unref(formCity).region_id,
																							"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																						}, null, 8, [
																							"items",
																							"modelValue",
																							"onUpdate:modelValue"
																						])]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formCity).yandexmapsgeo,
																							"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																							label: "yandex.ru/maps/geo/",
																							variant: "solo",
																							density: "comfortable"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formCity).latitude,
																							"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																							label: "Широта",
																							variant: "outlined",
																							density: "comfortable"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}), createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formCity).longitude,
																							"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																							label: "Долгота",
																							variant: "outlined",
																							density: "comfortable"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formCity).twogis,
																							"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																							label: "2GIS",
																							variant: "solo",
																							density: "comfortable"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					})]),
																					_: 1
																				})
																			]),
																			_: 1
																		}, 8, ["onSubmit"])]),
																		_: 1
																	}),
																	createVNode(VCardActions, null, {
																		default: withCtx(() => [createVNode(VBtn, {
																			onClick: storeCity,
																			text: "сохранить",
																			variant: "elevated",
																			color: "green"
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
												})];
											}),
											"item.name": withCtx(({ item }, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(unref(Link), { href: _ctx.route("city.show", item.id) }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`<span class="font-UnderdogRegular text-2xl"${_scopeId}>${ssrInterpolate(item.name)}</span>`);
														else return [createVNode("span", { class: "font-UnderdogRegular text-2xl" }, toDisplayString(item.name), 1)];
													}),
													_: 2
												}, _parent, _scopeId));
												else return [createVNode(unref(Link), { href: _ctx.route("city.show", item.id) }, {
													default: withCtx(() => [createVNode("span", { class: "font-UnderdogRegular text-2xl" }, toDisplayString(item.name), 1)]),
													_: 2
												}, 1032, ["href"])];
											}),
											"item.wiki": withCtx(({ item }, _push, _parent, _scopeId) => {
												if (_push) _push(`<a${ssrRenderAttr("href", item.wiki)} target="_blank" class="text-xs text-zinc-800"${_scopeId}>${ssrInterpolate(item.wiki)}</a>`);
												else return [createVNode("a", {
													href: item.wiki,
													target: "_blank",
													class: "text-xs text-zinc-800"
												}, toDisplayString(item.wiki), 9, ["href"])];
											}),
											"item.yandexmapsgeo": withCtx(({ item }, _push, _parent, _scopeId) => {
												if (_push) _push(`<a${ssrRenderAttr("href", item.yandexmapsgeo)} target="_blank" class="mx-3"${_scopeId}> yandex</a><a${ssrRenderAttr("href", item.twogis)} target="_blank" class="mx-3"${_scopeId}> 2gis </a>`);
												else return [createVNode("a", {
													href: item.yandexmapsgeo,
													target: "_blank",
													class: "mx-3"
												}, " yandex", 8, ["href"]), createVNode("a", {
													href: item.twogis,
													target: "_blank",
													class: "mx-3"
												}, " 2gis ", 8, ["href"])];
											}),
											"item.region_id": withCtx(({ item }, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(unref(Link), { href: _ctx.route("Ameise.region", item.region.id) }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`<span class="text-sm font-mono"${_scopeId}>${ssrInterpolate(item.region.name)}</span>`);
														else return [createVNode("span", { class: "text-sm font-mono" }, toDisplayString(item.region.name), 1)];
													}),
													_: 2
												}, _parent, _scopeId));
												else return [createVNode(unref(Link), { href: _ctx.route("Ameise.region", item.region.id) }, {
													default: withCtx(() => [createVNode("span", { class: "text-sm font-mono" }, toDisplayString(item.region.name), 1)]),
													_: 2
												}, 1032, ["href"])];
											}),
											"item.latitude": withCtx(({ item }, _push, _parent, _scopeId) => {
												if (_push) _push(`<span class="text-sm"${_scopeId}>${ssrInterpolate(item.latitude)}</span>`);
												else return [createVNode("span", { class: "text-sm" }, toDisplayString(item.latitude), 1)];
											}),
											"item.longitude": withCtx(({ item }, _push, _parent, _scopeId) => {
												if (_push) _push(`<span class="text-xs"${_scopeId}>${ssrInterpolate(item.longitude)}</span>`);
												else return [createVNode("span", { class: "text-xs" }, toDisplayString(item.longitude), 1)];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VDataTable, {
											items: cities.value,
											headers: headersCities,
											"items-per-page": "500",
											density: "compact",
											hover: "hover"
										}, {
											top: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: unref(searchCitiesLike),
														"onUpdate:modelValue": ($event) => isRef(searchCitiesLike) ? searchCitiesLike.value = $event : searchCitiesLike = $event,
														onInput: ($event) => indexCities(unref(searchCitiesLike)),
														label: "Поиск городов по like",
														variant: "underlined",
														density: "comfortable",
														color: "blue",
														class: "my-3",
														flat: ""
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"onInput"
													])]),
													_: 1
												}), createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VDialog, { width: "800" }, {
														activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
															text: "+ город",
															variant: "elevated",
															density: "comfortable",
															color: "deep-purple"
														}), null, 16)]),
														default: withCtx(({ isActive }) => [createVNode(VCard, null, {
															default: withCtx(() => [
																createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Form City")]),
																	_: 1
																}),
																createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																		default: withCtx(() => [
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formCity).wiki,
																					"onUpdate:modelValue": ($event) => unref(formCity).wiki = $event,
																					label: "Wiki",
																					variant: "outlined",
																					density: "comfortable"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formCity).name,
																						"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																						label: "Название",
																						variant: "outlined",
																						density: "comfortable"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}), createVNode(VCol, { cols: "4" }, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formCity).population,
																						"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																						label: "Население",
																						variant: "outlined",
																						density: "comfortable"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				})]),
																				_: 1
																			}),
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VAutocomplete, {
																						items: regions.value,
																						"item-title": "name",
																						"item-value": "id",
																						label: "Регион",
																						density: "comfortable",
																						color: "purple",
																						modelValue: unref(formCity).region_id,
																						"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																					}, null, 8, [
																						"items",
																						"modelValue",
																						"onUpdate:modelValue"
																					])]),
																					_: 1
																				})]),
																				_: 1
																			}),
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formCity).yandexmapsgeo,
																						"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																						label: "yandex.ru/maps/geo/",
																						variant: "solo",
																						density: "comfortable"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				})]),
																				_: 1
																			}),
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formCity).latitude,
																						"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																						label: "Широта",
																						variant: "outlined",
																						density: "comfortable"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}), createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formCity).longitude,
																						"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																						label: "Долгота",
																						variant: "outlined",
																						density: "comfortable"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				})]),
																				_: 1
																			}),
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formCity).twogis,
																						"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																						label: "2GIS",
																						variant: "solo",
																						density: "comfortable"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				})]),
																				_: 1
																			})
																		]),
																		_: 1
																	}, 8, ["onSubmit"])]),
																	_: 1
																}),
																createVNode(VCardActions, null, {
																	default: withCtx(() => [createVNode(VBtn, {
																		onClick: storeCity,
																		text: "сохранить",
																		variant: "elevated",
																		color: "green"
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
											})]),
											"item.name": withCtx(({ item }) => [createVNode(unref(Link), { href: _ctx.route("city.show", item.id) }, {
												default: withCtx(() => [createVNode("span", { class: "font-UnderdogRegular text-2xl" }, toDisplayString(item.name), 1)]),
												_: 2
											}, 1032, ["href"])]),
											"item.wiki": withCtx(({ item }) => [createVNode("a", {
												href: item.wiki,
												target: "_blank",
												class: "text-xs text-zinc-800"
											}, toDisplayString(item.wiki), 9, ["href"])]),
											"item.yandexmapsgeo": withCtx(({ item }) => [createVNode("a", {
												href: item.yandexmapsgeo,
												target: "_blank",
												class: "mx-3"
											}, " yandex", 8, ["href"]), createVNode("a", {
												href: item.twogis,
												target: "_blank",
												class: "mx-3"
											}, " 2gis ", 8, ["href"])]),
											"item.region_id": withCtx(({ item }) => [createVNode(unref(Link), { href: _ctx.route("Ameise.region", item.region.id) }, {
												default: withCtx(() => [createVNode("span", { class: "text-sm font-mono" }, toDisplayString(item.region.name), 1)]),
												_: 2
											}, 1032, ["href"])]),
											"item.latitude": withCtx(({ item }) => [createVNode("span", { class: "text-sm" }, toDisplayString(item.latitude), 1)]),
											"item.longitude": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.longitude), 1)]),
											_: 1
										}, 8, ["items"])];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [createVNode(VCol), createVNode(VCol, { cols: "11" }, {
								default: withCtx(() => [createVNode(VDataTable, {
									items: cities.value,
									headers: headersCities,
									"items-per-page": "500",
									density: "compact",
									hover: "hover"
								}, {
									top: withCtx(() => [createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, null, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: unref(searchCitiesLike),
												"onUpdate:modelValue": ($event) => isRef(searchCitiesLike) ? searchCitiesLike.value = $event : searchCitiesLike = $event,
												onInput: ($event) => indexCities(unref(searchCitiesLike)),
												label: "Поиск городов по like",
												variant: "underlined",
												density: "comfortable",
												color: "blue",
												class: "my-3",
												flat: ""
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"onInput"
											])]),
											_: 1
										}), createVNode(VCol, null, {
											default: withCtx(() => [createVNode(VDialog, { width: "800" }, {
												activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
													text: "+ город",
													variant: "elevated",
													density: "comfortable",
													color: "deep-purple"
												}), null, 16)]),
												default: withCtx(({ isActive }) => [createVNode(VCard, null, {
													default: withCtx(() => [
														createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Form City")]),
															_: 1
														}),
														createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																default: withCtx(() => [
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formCity).wiki,
																			"onUpdate:modelValue": ($event) => unref(formCity).wiki = $event,
																			label: "Wiki",
																			variant: "outlined",
																			density: "comfortable"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formCity).name,
																				"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																				label: "Название",
																				variant: "outlined",
																				density: "comfortable"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}), createVNode(VCol, { cols: "4" }, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formCity).population,
																				"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																				label: "Население",
																				variant: "outlined",
																				density: "comfortable"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VAutocomplete, {
																				items: regions.value,
																				"item-title": "name",
																				"item-value": "id",
																				label: "Регион",
																				density: "comfortable",
																				color: "purple",
																				modelValue: unref(formCity).region_id,
																				"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																			}, null, 8, [
																				"items",
																				"modelValue",
																				"onUpdate:modelValue"
																			])]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formCity).yandexmapsgeo,
																				"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																				label: "yandex.ru/maps/geo/",
																				variant: "solo",
																				density: "comfortable"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formCity).latitude,
																				"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																				label: "Широта",
																				variant: "outlined",
																				density: "comfortable"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}), createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formCity).longitude,
																				"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																				label: "Долгота",
																				variant: "outlined",
																				density: "comfortable"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formCity).twogis,
																				"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																				label: "2GIS",
																				variant: "solo",
																				density: "comfortable"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		})]),
																		_: 1
																	})
																]),
																_: 1
															}, 8, ["onSubmit"])]),
															_: 1
														}),
														createVNode(VCardActions, null, {
															default: withCtx(() => [createVNode(VBtn, {
																onClick: storeCity,
																text: "сохранить",
																variant: "elevated",
																color: "green"
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
									})]),
									"item.name": withCtx(({ item }) => [createVNode(unref(Link), { href: _ctx.route("city.show", item.id) }, {
										default: withCtx(() => [createVNode("span", { class: "font-UnderdogRegular text-2xl" }, toDisplayString(item.name), 1)]),
										_: 2
									}, 1032, ["href"])]),
									"item.wiki": withCtx(({ item }) => [createVNode("a", {
										href: item.wiki,
										target: "_blank",
										class: "text-xs text-zinc-800"
									}, toDisplayString(item.wiki), 9, ["href"])]),
									"item.yandexmapsgeo": withCtx(({ item }) => [createVNode("a", {
										href: item.yandexmapsgeo,
										target: "_blank",
										class: "mx-3"
									}, " yandex", 8, ["href"]), createVNode("a", {
										href: item.twogis,
										target: "_blank",
										class: "mx-3"
									}, " 2gis ", 8, ["href"])]),
									"item.region_id": withCtx(({ item }) => [createVNode(unref(Link), { href: _ctx.route("Ameise.region", item.region.id) }, {
										default: withCtx(() => [createVNode("span", { class: "text-sm font-mono" }, toDisplayString(item.region.name), 1)]),
										_: 2
									}, 1032, ["href"])]),
									"item.latitude": withCtx(({ item }) => [createVNode("span", { class: "text-sm" }, toDisplayString(item.latitude), 1)]),
									"item.longitude": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.longitude), 1)]),
									_: 1
								}, 8, ["items"])]),
								_: 1
							})];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol), createVNode(VCol, { cols: "11" }, {
							default: withCtx(() => [createVNode(VDataTable, {
								items: cities.value,
								headers: headersCities,
								"items-per-page": "500",
								density: "compact",
								hover: "hover"
							}, {
								top: withCtx(() => [createVNode(VRow, null, {
									default: withCtx(() => [createVNode(VCol, null, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: unref(searchCitiesLike),
											"onUpdate:modelValue": ($event) => isRef(searchCitiesLike) ? searchCitiesLike.value = $event : searchCitiesLike = $event,
											onInput: ($event) => indexCities(unref(searchCitiesLike)),
											label: "Поиск городов по like",
											variant: "underlined",
											density: "comfortable",
											color: "blue",
											class: "my-3",
											flat: ""
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"onInput"
										])]),
										_: 1
									}), createVNode(VCol, null, {
										default: withCtx(() => [createVNode(VDialog, { width: "800" }, {
											activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
												text: "+ город",
												variant: "elevated",
												density: "comfortable",
												color: "deep-purple"
											}), null, 16)]),
											default: withCtx(({ isActive }) => [createVNode(VCard, null, {
												default: withCtx(() => [
													createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Form City")]),
														_: 1
													}),
													createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
															default: withCtx(() => [
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formCity).wiki,
																		"onUpdate:modelValue": ($event) => unref(formCity).wiki = $event,
																		label: "Wiki",
																		variant: "outlined",
																		density: "comfortable"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formCity).name,
																			"onUpdate:modelValue": ($event) => unref(formCity).name = $event,
																			label: "Название",
																			variant: "outlined",
																			density: "comfortable"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}), createVNode(VCol, { cols: "4" }, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formCity).population,
																			"onUpdate:modelValue": ($event) => unref(formCity).population = $event,
																			label: "Население",
																			variant: "outlined",
																			density: "comfortable"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})]),
																	_: 1
																}),
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VAutocomplete, {
																			items: regions.value,
																			"item-title": "name",
																			"item-value": "id",
																			label: "Регион",
																			density: "comfortable",
																			color: "purple",
																			modelValue: unref(formCity).region_id,
																			"onUpdate:modelValue": ($event) => unref(formCity).region_id = $event
																		}, null, 8, [
																			"items",
																			"modelValue",
																			"onUpdate:modelValue"
																		])]),
																		_: 1
																	})]),
																	_: 1
																}),
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formCity).yandexmapsgeo,
																			"onUpdate:modelValue": ($event) => unref(formCity).yandexmapsgeo = $event,
																			label: "yandex.ru/maps/geo/",
																			variant: "solo",
																			density: "comfortable"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})]),
																	_: 1
																}),
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formCity).latitude,
																			"onUpdate:modelValue": ($event) => unref(formCity).latitude = $event,
																			label: "Широта",
																			variant: "outlined",
																			density: "comfortable"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}), createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formCity).longitude,
																			"onUpdate:modelValue": ($event) => unref(formCity).longitude = $event,
																			label: "Долгота",
																			variant: "outlined",
																			density: "comfortable"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})]),
																	_: 1
																}),
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formCity).twogis,
																			"onUpdate:modelValue": ($event) => unref(formCity).twogis = $event,
																			label: "2GIS",
																			variant: "solo",
																			density: "comfortable"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})]),
																	_: 1
																})
															]),
															_: 1
														}, 8, ["onSubmit"])]),
														_: 1
													}),
													createVNode(VCardActions, null, {
														default: withCtx(() => [createVNode(VBtn, {
															onClick: storeCity,
															text: "сохранить",
															variant: "elevated",
															color: "green"
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
								})]),
								"item.name": withCtx(({ item }) => [createVNode(unref(Link), { href: _ctx.route("city.show", item.id) }, {
									default: withCtx(() => [createVNode("span", { class: "font-UnderdogRegular text-2xl" }, toDisplayString(item.name), 1)]),
									_: 2
								}, 1032, ["href"])]),
								"item.wiki": withCtx(({ item }) => [createVNode("a", {
									href: item.wiki,
									target: "_blank",
									class: "text-xs text-zinc-800"
								}, toDisplayString(item.wiki), 9, ["href"])]),
								"item.yandexmapsgeo": withCtx(({ item }) => [createVNode("a", {
									href: item.yandexmapsgeo,
									target: "_blank",
									class: "mx-3"
								}, " yandex", 8, ["href"]), createVNode("a", {
									href: item.twogis,
									target: "_blank",
									class: "mx-3"
								}, " 2gis ", 8, ["href"])]),
								"item.region_id": withCtx(({ item }) => [createVNode(unref(Link), { href: _ctx.route("Ameise.region", item.region.id) }, {
									default: withCtx(() => [createVNode("span", { class: "text-sm font-mono" }, toDisplayString(item.region.name), 1)]),
									_: 2
								}, 1032, ["href"])]),
								"item.latitude": withCtx(({ item }) => [createVNode("span", { class: "text-sm" }, toDisplayString(item.latitude), 1)]),
								"item.longitude": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.longitude), 1)]),
								_: 1
							}, 8, ["items"])]),
							_: 1
						})]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Cities.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Cities-Cz2MTzxx.js.map