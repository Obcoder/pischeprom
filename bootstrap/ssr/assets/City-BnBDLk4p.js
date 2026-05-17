import { $ as VAvatar, C as VRow, F as VCardText, G as VList, I as VCardTitle, K as VDivider, O as VTable, P as VCard, R as VCardActions, S as VSpacer, T as VContainer, U as VTextField, X as VChip, at as VProgressCircular, d as VSkeletonLoader, et as VAlert, h as VForm, j as VSheet, lt as VImg, q as VListItem, rt as VBtn, u as VSnackbar, w as VCol, z as VDialog } from "../ssr.js";
import { t as _sfc_main$12 } from "./VerwalterLayout-BLmFLvbQ.js";
import { n as _sfc_main$14, r as _sfc_main$13, t as useRegions } from "./useRegions-CiNRh2jq.js";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, mergeModels, mergeProps, onMounted, openBlock, reactive, ref, renderList, toDisplayString, unref, useModel, useSSRContext, watch, withCtx, withModifiers } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderAttr, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Components/Geography/Cities/Show/CityBuildingFormDialog.vue
var _sfc_main$11 = {
	__name: "CityBuildingFormDialog",
	__ssrInlineRender: true,
	props: /* @__PURE__ */ mergeModels({
		mode: {
			type: String,
			default: "create"
		},
		city: {
			type: Object,
			required: true
		},
		building: {
			type: Object,
			default: null
		},
		loading: Boolean
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
		const form = reactive({
			address: null,
			postcode: null
		});
		const title = computed(() => {
			return props.mode === "create" ? `Добавить здание: ${props.city?.name ?? ""}` : `Редактировать здание`;
		});
		function resetForm() {
			form.address = props.building?.address ?? null;
			form.postcode = props.building?.postcode ?? null;
		}
		function submit() {
			emit("save", {
				city_id: props.city.id,
				address: form.address,
				postcode: form.postcode
			});
		}
		watch(() => model.value, (value) => {
			if (value) resetForm();
		});
		watch(() => props.building, () => {
			if (model.value) resetForm();
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
										if (_push) {
											_push(ssrRenderComponent(VAlert, {
												type: "info",
												variant: "tonal",
												density: "compact",
												class: "mb-4"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Город будет подставлен автоматически: <strong${_scopeId}>${ssrInterpolate(__props.city.name)}</strong>`);
													else return [createTextVNode(" Город будет подставлен автоматически: "), createVNode("strong", null, toDisplayString(__props.city.name), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VForm, { onSubmit: submit }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VRow, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VCol, null, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: form.address,
																			"onUpdate:modelValue": ($event) => form.address = $event,
																			label: "Адрес здания",
																			placeholder: "ул. Ленина, 10",
																			variant: "outlined",
																			density: "comfortable",
																			color: "teal",
																			autofocus: ""
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: form.address,
																			"onUpdate:modelValue": ($event) => form.address = $event,
																			label: "Адрес здания",
																			placeholder: "ул. Ленина, 10",
																			variant: "outlined",
																			density: "comfortable",
																			color: "teal",
																			autofocus: ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: form.address,
																		"onUpdate:modelValue": ($event) => form.address = $event,
																		label: "Адрес здания",
																		placeholder: "ул. Ленина, 10",
																		variant: "outlined",
																		density: "comfortable",
																		color: "teal",
																		autofocus: ""
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
																		md: "5"
																	}, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VTextField, {
																				modelValue: form.postcode,
																				"onUpdate:modelValue": ($event) => form.postcode = $event,
																				label: "Почтовый индекс",
																				placeholder: "140000",
																				variant: "solo",
																				density: "comfortable",
																				color: "blue-grey"
																			}, null, _parent, _scopeId));
																			else return [createVNode(VTextField, {
																				modelValue: form.postcode,
																				"onUpdate:modelValue": ($event) => form.postcode = $event,
																				label: "Почтовый индекс",
																				placeholder: "140000",
																				variant: "solo",
																				density: "comfortable",
																				color: "blue-grey"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VCol, {
																		cols: "12",
																		md: "7"
																	}, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VTextField, {
																				"model-value": __props.city.name,
																				label: "Город",
																				variant: "solo-filled",
																				density: "comfortable",
																				readonly: ""
																			}, null, _parent, _scopeId));
																			else return [createVNode(VTextField, {
																				"model-value": __props.city.name,
																				label: "Город",
																				variant: "solo-filled",
																				density: "comfortable",
																				readonly: ""
																			}, null, 8, ["model-value"])];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																} else return [createVNode(VCol, {
																	cols: "12",
																	md: "5"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: form.postcode,
																		"onUpdate:modelValue": ($event) => form.postcode = $event,
																		label: "Почтовый индекс",
																		placeholder: "140000",
																		variant: "solo",
																		density: "comfortable",
																		color: "blue-grey"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}), createVNode(VCol, {
																	cols: "12",
																	md: "7"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		"model-value": __props.city.name,
																		label: "Город",
																		variant: "solo-filled",
																		density: "comfortable",
																		readonly: ""
																	}, null, 8, ["model-value"])]),
																	_: 1
																})];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.address,
																"onUpdate:modelValue": ($event) => form.address = $event,
																label: "Адрес здания",
																placeholder: "ул. Ленина, 10",
																variant: "outlined",
																density: "comfortable",
																color: "teal",
																autofocus: ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})]),
														_: 1
													}), createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, {
															cols: "12",
															md: "5"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: form.postcode,
																"onUpdate:modelValue": ($event) => form.postcode = $event,
																label: "Почтовый индекс",
																placeholder: "140000",
																variant: "solo",
																density: "comfortable",
																color: "blue-grey"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}), createVNode(VCol, {
															cols: "12",
															md: "7"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																"model-value": __props.city.name,
																label: "Город",
																variant: "solo-filled",
																density: "comfortable",
																readonly: ""
															}, null, 8, ["model-value"])]),
															_: 1
														})]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [createVNode(VAlert, {
											type: "info",
											variant: "tonal",
											density: "compact",
											class: "mb-4"
										}, {
											default: withCtx(() => [createTextVNode(" Город будет подставлен автоматически: "), createVNode("strong", null, toDisplayString(__props.city.name), 1)]),
											_: 1
										}), createVNode(VForm, { onSubmit: withModifiers(submit, ["prevent"]) }, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.address,
														"onUpdate:modelValue": ($event) => form.address = $event,
														label: "Адрес здания",
														placeholder: "ул. Ленина, 10",
														variant: "outlined",
														density: "comfortable",
														color: "teal",
														autofocus: ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})]),
												_: 1
											}), createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, {
													cols: "12",
													md: "5"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.postcode,
														"onUpdate:modelValue": ($event) => form.postcode = $event,
														label: "Почтовый индекс",
														placeholder: "140000",
														variant: "solo",
														density: "comfortable",
														color: "blue-grey"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}), createVNode(VCol, {
													cols: "12",
													md: "7"
												}, {
													default: withCtx(() => [createVNode(VTextField, {
														"model-value": __props.city.name,
														label: "Город",
														variant: "solo-filled",
														density: "comfortable",
														readonly: ""
													}, null, 8, ["model-value"])]),
													_: 1
												})]),
												_: 1
											})]),
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
												text: __props.mode === "create" ? "Добавить" : "Обновить",
												loading: __props.loading,
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
												text: __props.mode === "create" ? "Добавить" : "Обновить",
												loading: __props.loading,
												color: "teal",
												variant: "elevated",
												onClick: submit
											}, null, 8, ["text", "loading"])
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
									default: withCtx(() => [createVNode(VAlert, {
										type: "info",
										variant: "tonal",
										density: "compact",
										class: "mb-4"
									}, {
										default: withCtx(() => [createTextVNode(" Город будет подставлен автоматически: "), createVNode("strong", null, toDisplayString(__props.city.name), 1)]),
										_: 1
									}), createVNode(VForm, { onSubmit: withModifiers(submit, ["prevent"]) }, {
										default: withCtx(() => [createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, null, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.address,
													"onUpdate:modelValue": ($event) => form.address = $event,
													label: "Адрес здания",
													placeholder: "ул. Ленина, 10",
													variant: "outlined",
													density: "comfortable",
													color: "teal",
													autofocus: ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})]),
											_: 1
										}), createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, {
												cols: "12",
												md: "5"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.postcode,
													"onUpdate:modelValue": ($event) => form.postcode = $event,
													label: "Почтовый индекс",
													placeholder: "140000",
													variant: "solo",
													density: "comfortable",
													color: "blue-grey"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}), createVNode(VCol, {
												cols: "12",
												md: "7"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													"model-value": __props.city.name,
													label: "Город",
													variant: "solo-filled",
													density: "comfortable",
													readonly: ""
												}, null, 8, ["model-value"])]),
												_: 1
											})]),
											_: 1
										})]),
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
											text: __props.mode === "create" ? "Добавить" : "Обновить",
											loading: __props.loading,
											color: "teal",
											variant: "elevated",
											onClick: submit
										}, null, 8, ["text", "loading"])
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
								default: withCtx(() => [createVNode(VAlert, {
									type: "info",
									variant: "tonal",
									density: "compact",
									class: "mb-4"
								}, {
									default: withCtx(() => [createTextVNode(" Город будет подставлен автоматически: "), createVNode("strong", null, toDisplayString(__props.city.name), 1)]),
									_: 1
								}), createVNode(VForm, { onSubmit: withModifiers(submit, ["prevent"]) }, {
									default: withCtx(() => [createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, null, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.address,
												"onUpdate:modelValue": ($event) => form.address = $event,
												label: "Адрес здания",
												placeholder: "ул. Ленина, 10",
												variant: "outlined",
												density: "comfortable",
												color: "teal",
												autofocus: ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})]),
										_: 1
									}), createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, {
											cols: "12",
											md: "5"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.postcode,
												"onUpdate:modelValue": ($event) => form.postcode = $event,
												label: "Почтовый индекс",
												placeholder: "140000",
												variant: "solo",
												density: "comfortable",
												color: "blue-grey"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}), createVNode(VCol, {
											cols: "12",
											md: "7"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												"model-value": __props.city.name,
												label: "Город",
												variant: "solo-filled",
												density: "comfortable",
												readonly: ""
											}, null, 8, ["model-value"])]),
											_: 1
										})]),
										_: 1
									})]),
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
										text: __props.mode === "create" ? "Добавить" : "Обновить",
										loading: __props.loading,
										color: "teal",
										variant: "elevated",
										onClick: submit
									}, null, 8, ["text", "loading"])
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
var _sfc_setup$11 = _sfc_main$11.setup;
_sfc_main$11.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/Show/CityBuildingFormDialog.vue");
	return _sfc_setup$11 ? _sfc_setup$11(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Geography/Cities/Show/CityHeroCard.vue
var _sfc_main$10 = {
	__name: "CityHeroCard",
	__ssrInlineRender: true,
	props: {
		city: {
			type: Object,
			required: true
		},
		latestPopulation: {
			type: [Number, String],
			default: null
		},
		latestPopulationYear: {
			type: [Number, String],
			default: null
		}
	},
	emits: ["edit", "population"],
	setup(__props) {
		function formatNumber(value) {
			if (value === null || value === void 0 || value === "") return "—";
			return new Intl.NumberFormat("ru-RU").format(value);
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VSheet, mergeProps({ class: "pa-4 rounded border border-teal-800 bg-slate-900" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VRow, { align: "center" }, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCol, {
									cols: "12",
									md: "2",
									class: "d-flex justify-center"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) if (__props.city.wiki) {
											_push(`<a${ssrRenderAttr("href", __props.city.wiki)} target="_blank" rel="noopener noreferrer" title="Открыть статью Wikipedia"${_scopeId}>`);
											_push(ssrRenderComponent(VAvatar, {
												size: "112",
												rounded: "xl",
												class: "border border-teal-600 bg-slate-800 cursor-pointer hover:opacity-80"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) if (__props.city.wiki_thumbnail) _push(ssrRenderComponent(VImg, {
														src: __props.city.wiki_thumbnail,
														cover: ""
													}, null, _parent, _scopeId));
													else _push(`<span class="text-h3 text-teal-lighten-3"${_scopeId}>${ssrInterpolate(__props.city.name?.slice(0, 1))}</span>`);
													else return [__props.city.wiki_thumbnail ? (openBlock(), createBlock(VImg, {
														key: 0,
														src: __props.city.wiki_thumbnail,
														cover: ""
													}, null, 8, ["src"])) : (openBlock(), createBlock("span", {
														key: 1,
														class: "text-h3 text-teal-lighten-3"
													}, toDisplayString(__props.city.name?.slice(0, 1)), 1))];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(`</a>`);
										} else _push(ssrRenderComponent(VAvatar, {
											size: "112",
											rounded: "xl",
											class: "border border-teal-600 bg-slate-800"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) if (__props.city.wiki_thumbnail) _push(ssrRenderComponent(VImg, {
													src: __props.city.wiki_thumbnail,
													cover: ""
												}, null, _parent, _scopeId));
												else _push(`<span class="text-h3 text-teal-lighten-3"${_scopeId}>${ssrInterpolate(__props.city.name?.slice(0, 1))}</span>`);
												else return [__props.city.wiki_thumbnail ? (openBlock(), createBlock(VImg, {
													key: 0,
													src: __props.city.wiki_thumbnail,
													cover: ""
												}, null, 8, ["src"])) : (openBlock(), createBlock("span", {
													key: 1,
													class: "text-h3 text-teal-lighten-3"
												}, toDisplayString(__props.city.name?.slice(0, 1)), 1))];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [__props.city.wiki ? (openBlock(), createBlock("a", {
											key: 0,
											href: __props.city.wiki,
											target: "_blank",
											rel: "noopener noreferrer",
											title: "Открыть статью Wikipedia"
										}, [createVNode(VAvatar, {
											size: "112",
											rounded: "xl",
											class: "border border-teal-600 bg-slate-800 cursor-pointer hover:opacity-80"
										}, {
											default: withCtx(() => [__props.city.wiki_thumbnail ? (openBlock(), createBlock(VImg, {
												key: 0,
												src: __props.city.wiki_thumbnail,
												cover: ""
											}, null, 8, ["src"])) : (openBlock(), createBlock("span", {
												key: 1,
												class: "text-h3 text-teal-lighten-3"
											}, toDisplayString(__props.city.name?.slice(0, 1)), 1))]),
											_: 1
										})], 8, ["href"])) : (openBlock(), createBlock(VAvatar, {
											key: 1,
											size: "112",
											rounded: "xl",
											class: "border border-teal-600 bg-slate-800"
										}, {
											default: withCtx(() => [__props.city.wiki_thumbnail ? (openBlock(), createBlock(VImg, {
												key: 0,
												src: __props.city.wiki_thumbnail,
												cover: ""
											}, null, 8, ["src"])) : (openBlock(), createBlock("span", {
												key: 1,
												class: "text-h3 text-teal-lighten-3"
											}, toDisplayString(__props.city.name?.slice(0, 1)), 1))]),
											_: 1
										}))];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCol, {
									cols: "12",
									md: "7"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<div class="text-h4 text-teal-lighten-3 font-ComfortaaVariableFont"${_scopeId}>${ssrInterpolate(__props.city.name)}</div><div class="mt-1 text-caption text-teal-darken-1"${_scopeId}>${ssrInterpolate(__props.city.region?.name || "—")} <span class="mx-1"${_scopeId}>/</span> ${ssrInterpolate(__props.city.region?.country?.name || "—")}</div><div class="mt-3 d-flex flex-wrap ga-2"${_scopeId}>`);
											_push(ssrRenderComponent(VChip, {
												color: "teal",
												variant: "tonal",
												size: "small"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(` 👥 ${ssrInterpolate(formatNumber(__props.latestPopulation))} `);
														if (__props.latestPopulationYear) _push(`<span class="ml-1"${_scopeId}> / ${ssrInterpolate(__props.latestPopulationYear)}</span>`);
														else _push(`<!---->`);
													} else return [createTextVNode(" 👥 " + toDisplayString(formatNumber(__props.latestPopulation)) + " ", 1), __props.latestPopulationYear ? (openBlock(), createBlock("span", {
														key: 0,
														class: "ml-1"
													}, " / " + toDisplayString(__props.latestPopulationYear), 1)) : createCommentVNode("", true)];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VChip, {
												color: "blue-grey",
												variant: "tonal",
												size: "small"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` lat: ${ssrInterpolate(__props.city.latitude ?? "—")}`);
													else return [createTextVNode(" lat: " + toDisplayString(__props.city.latitude ?? "—"), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VChip, {
												color: "blue-grey",
												variant: "tonal",
												size: "small"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` lon: ${ssrInterpolate(__props.city.longitude ?? "—")}`);
													else return [createTextVNode(" lon: " + toDisplayString(__props.city.longitude ?? "—"), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(`</div><div class="mt-3 d-flex flex-wrap ga-2"${_scopeId}>`);
											if (__props.city.wiki) _push(`<a${ssrRenderAttr("href", __props.city.wiki)} target="_blank" rel="noopener noreferrer" class="text-teal-lighten-3 hover:text-white text-sm"${_scopeId}> Wikipedia → </a>`);
											else _push(`<!---->`);
											if (__props.city.yandexmapsgeo) _push(`<a${ssrRenderAttr("href", __props.city.yandexmapsgeo)} target="_blank" rel="noopener noreferrer" class="text-teal-lighten-3 hover:text-white text-sm"${_scopeId}> Yandex Maps → </a>`);
											else _push(`<!---->`);
											if (__props.city.twogis) _push(`<a${ssrRenderAttr("href", __props.city.twogis)} target="_blank" rel="noopener noreferrer" class="text-teal-lighten-3 hover:text-white text-sm"${_scopeId}> 2GIS → </a>`);
											else _push(`<!---->`);
											_push(`</div>`);
										} else return [
											createVNode("div", { class: "text-h4 text-teal-lighten-3 font-ComfortaaVariableFont" }, toDisplayString(__props.city.name), 1),
											createVNode("div", { class: "mt-1 text-caption text-teal-darken-1" }, [
												createTextVNode(toDisplayString(__props.city.region?.name || "—") + " ", 1),
												createVNode("span", { class: "mx-1" }, "/"),
												createTextVNode(" " + toDisplayString(__props.city.region?.country?.name || "—"), 1)
											]),
											createVNode("div", { class: "mt-3 d-flex flex-wrap ga-2" }, [
												createVNode(VChip, {
													color: "teal",
													variant: "tonal",
													size: "small"
												}, {
													default: withCtx(() => [createTextVNode(" 👥 " + toDisplayString(formatNumber(__props.latestPopulation)) + " ", 1), __props.latestPopulationYear ? (openBlock(), createBlock("span", {
														key: 0,
														class: "ml-1"
													}, " / " + toDisplayString(__props.latestPopulationYear), 1)) : createCommentVNode("", true)]),
													_: 1
												}),
												createVNode(VChip, {
													color: "blue-grey",
													variant: "tonal",
													size: "small"
												}, {
													default: withCtx(() => [createTextVNode(" lat: " + toDisplayString(__props.city.latitude ?? "—"), 1)]),
													_: 1
												}),
												createVNode(VChip, {
													color: "blue-grey",
													variant: "tonal",
													size: "small"
												}, {
													default: withCtx(() => [createTextVNode(" lon: " + toDisplayString(__props.city.longitude ?? "—"), 1)]),
													_: 1
												})
											]),
											createVNode("div", { class: "mt-3 d-flex flex-wrap ga-2" }, [
												__props.city.wiki ? (openBlock(), createBlock("a", {
													key: 0,
													href: __props.city.wiki,
													target: "_blank",
													rel: "noopener noreferrer",
													class: "text-teal-lighten-3 hover:text-white text-sm"
												}, " Wikipedia → ", 8, ["href"])) : createCommentVNode("", true),
												__props.city.yandexmapsgeo ? (openBlock(), createBlock("a", {
													key: 1,
													href: __props.city.yandexmapsgeo,
													target: "_blank",
													rel: "noopener noreferrer",
													class: "text-teal-lighten-3 hover:text-white text-sm"
												}, " Yandex Maps → ", 8, ["href"])) : createCommentVNode("", true),
												__props.city.twogis ? (openBlock(), createBlock("a", {
													key: 2,
													href: __props.city.twogis,
													target: "_blank",
													rel: "noopener noreferrer",
													class: "text-teal-lighten-3 hover:text-white text-sm"
												}, " 2GIS → ", 8, ["href"])) : createCommentVNode("", true)
											])
										];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCol, {
									cols: "12",
									md: "3"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<div class="d-flex flex-column ga-2"${_scopeId}>`);
											_push(ssrRenderComponent(VBtn, {
												text: "Редактировать",
												color: "amber",
												variant: "tonal",
												density: "comfortable",
												"prepend-icon": "mdi-pencil",
												onClick: ($event) => _ctx.$emit("edit")
											}, null, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												text: "Население по годам",
												color: "teal",
												variant: "tonal",
												density: "comfortable",
												"prepend-icon": "mdi-chart-line",
												onClick: ($event) => _ctx.$emit("population")
											}, null, _parent, _scopeId));
											_push(`</div>`);
										} else return [createVNode("div", { class: "d-flex flex-column ga-2" }, [createVNode(VBtn, {
											text: "Редактировать",
											color: "amber",
											variant: "tonal",
											density: "comfortable",
											"prepend-icon": "mdi-pencil",
											onClick: ($event) => _ctx.$emit("edit")
										}, null, 8, ["onClick"]), createVNode(VBtn, {
											text: "Население по годам",
											color: "teal",
											variant: "tonal",
											density: "comfortable",
											"prepend-icon": "mdi-chart-line",
											onClick: ($event) => _ctx.$emit("population")
										}, null, 8, ["onClick"])])];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCol, {
									cols: "12",
									md: "2",
									class: "d-flex justify-center"
								}, {
									default: withCtx(() => [__props.city.wiki ? (openBlock(), createBlock("a", {
										key: 0,
										href: __props.city.wiki,
										target: "_blank",
										rel: "noopener noreferrer",
										title: "Открыть статью Wikipedia"
									}, [createVNode(VAvatar, {
										size: "112",
										rounded: "xl",
										class: "border border-teal-600 bg-slate-800 cursor-pointer hover:opacity-80"
									}, {
										default: withCtx(() => [__props.city.wiki_thumbnail ? (openBlock(), createBlock(VImg, {
											key: 0,
											src: __props.city.wiki_thumbnail,
											cover: ""
										}, null, 8, ["src"])) : (openBlock(), createBlock("span", {
											key: 1,
											class: "text-h3 text-teal-lighten-3"
										}, toDisplayString(__props.city.name?.slice(0, 1)), 1))]),
										_: 1
									})], 8, ["href"])) : (openBlock(), createBlock(VAvatar, {
										key: 1,
										size: "112",
										rounded: "xl",
										class: "border border-teal-600 bg-slate-800"
									}, {
										default: withCtx(() => [__props.city.wiki_thumbnail ? (openBlock(), createBlock(VImg, {
											key: 0,
											src: __props.city.wiki_thumbnail,
											cover: ""
										}, null, 8, ["src"])) : (openBlock(), createBlock("span", {
											key: 1,
											class: "text-h3 text-teal-lighten-3"
										}, toDisplayString(__props.city.name?.slice(0, 1)), 1))]),
										_: 1
									}))]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									md: "7"
								}, {
									default: withCtx(() => [
										createVNode("div", { class: "text-h4 text-teal-lighten-3 font-ComfortaaVariableFont" }, toDisplayString(__props.city.name), 1),
										createVNode("div", { class: "mt-1 text-caption text-teal-darken-1" }, [
											createTextVNode(toDisplayString(__props.city.region?.name || "—") + " ", 1),
											createVNode("span", { class: "mx-1" }, "/"),
											createTextVNode(" " + toDisplayString(__props.city.region?.country?.name || "—"), 1)
										]),
										createVNode("div", { class: "mt-3 d-flex flex-wrap ga-2" }, [
											createVNode(VChip, {
												color: "teal",
												variant: "tonal",
												size: "small"
											}, {
												default: withCtx(() => [createTextVNode(" 👥 " + toDisplayString(formatNumber(__props.latestPopulation)) + " ", 1), __props.latestPopulationYear ? (openBlock(), createBlock("span", {
													key: 0,
													class: "ml-1"
												}, " / " + toDisplayString(__props.latestPopulationYear), 1)) : createCommentVNode("", true)]),
												_: 1
											}),
											createVNode(VChip, {
												color: "blue-grey",
												variant: "tonal",
												size: "small"
											}, {
												default: withCtx(() => [createTextVNode(" lat: " + toDisplayString(__props.city.latitude ?? "—"), 1)]),
												_: 1
											}),
											createVNode(VChip, {
												color: "blue-grey",
												variant: "tonal",
												size: "small"
											}, {
												default: withCtx(() => [createTextVNode(" lon: " + toDisplayString(__props.city.longitude ?? "—"), 1)]),
												_: 1
											})
										]),
										createVNode("div", { class: "mt-3 d-flex flex-wrap ga-2" }, [
											__props.city.wiki ? (openBlock(), createBlock("a", {
												key: 0,
												href: __props.city.wiki,
												target: "_blank",
												rel: "noopener noreferrer",
												class: "text-teal-lighten-3 hover:text-white text-sm"
											}, " Wikipedia → ", 8, ["href"])) : createCommentVNode("", true),
											__props.city.yandexmapsgeo ? (openBlock(), createBlock("a", {
												key: 1,
												href: __props.city.yandexmapsgeo,
												target: "_blank",
												rel: "noopener noreferrer",
												class: "text-teal-lighten-3 hover:text-white text-sm"
											}, " Yandex Maps → ", 8, ["href"])) : createCommentVNode("", true),
											__props.city.twogis ? (openBlock(), createBlock("a", {
												key: 2,
												href: __props.city.twogis,
												target: "_blank",
												rel: "noopener noreferrer",
												class: "text-teal-lighten-3 hover:text-white text-sm"
											}, " 2GIS → ", 8, ["href"])) : createCommentVNode("", true)
										])
									]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									md: "3"
								}, {
									default: withCtx(() => [createVNode("div", { class: "d-flex flex-column ga-2" }, [createVNode(VBtn, {
										text: "Редактировать",
										color: "amber",
										variant: "tonal",
										density: "comfortable",
										"prepend-icon": "mdi-pencil",
										onClick: ($event) => _ctx.$emit("edit")
									}, null, 8, ["onClick"]), createVNode(VBtn, {
										text: "Население по годам",
										color: "teal",
										variant: "tonal",
										density: "comfortable",
										"prepend-icon": "mdi-chart-line",
										onClick: ($event) => _ctx.$emit("population")
									}, null, 8, ["onClick"])])]),
									_: 1
								})
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VRow, { align: "center" }, {
						default: withCtx(() => [
							createVNode(VCol, {
								cols: "12",
								md: "2",
								class: "d-flex justify-center"
							}, {
								default: withCtx(() => [__props.city.wiki ? (openBlock(), createBlock("a", {
									key: 0,
									href: __props.city.wiki,
									target: "_blank",
									rel: "noopener noreferrer",
									title: "Открыть статью Wikipedia"
								}, [createVNode(VAvatar, {
									size: "112",
									rounded: "xl",
									class: "border border-teal-600 bg-slate-800 cursor-pointer hover:opacity-80"
								}, {
									default: withCtx(() => [__props.city.wiki_thumbnail ? (openBlock(), createBlock(VImg, {
										key: 0,
										src: __props.city.wiki_thumbnail,
										cover: ""
									}, null, 8, ["src"])) : (openBlock(), createBlock("span", {
										key: 1,
										class: "text-h3 text-teal-lighten-3"
									}, toDisplayString(__props.city.name?.slice(0, 1)), 1))]),
									_: 1
								})], 8, ["href"])) : (openBlock(), createBlock(VAvatar, {
									key: 1,
									size: "112",
									rounded: "xl",
									class: "border border-teal-600 bg-slate-800"
								}, {
									default: withCtx(() => [__props.city.wiki_thumbnail ? (openBlock(), createBlock(VImg, {
										key: 0,
										src: __props.city.wiki_thumbnail,
										cover: ""
									}, null, 8, ["src"])) : (openBlock(), createBlock("span", {
										key: 1,
										class: "text-h3 text-teal-lighten-3"
									}, toDisplayString(__props.city.name?.slice(0, 1)), 1))]),
									_: 1
								}))]),
								_: 1
							}),
							createVNode(VCol, {
								cols: "12",
								md: "7"
							}, {
								default: withCtx(() => [
									createVNode("div", { class: "text-h4 text-teal-lighten-3 font-ComfortaaVariableFont" }, toDisplayString(__props.city.name), 1),
									createVNode("div", { class: "mt-1 text-caption text-teal-darken-1" }, [
										createTextVNode(toDisplayString(__props.city.region?.name || "—") + " ", 1),
										createVNode("span", { class: "mx-1" }, "/"),
										createTextVNode(" " + toDisplayString(__props.city.region?.country?.name || "—"), 1)
									]),
									createVNode("div", { class: "mt-3 d-flex flex-wrap ga-2" }, [
										createVNode(VChip, {
											color: "teal",
											variant: "tonal",
											size: "small"
										}, {
											default: withCtx(() => [createTextVNode(" 👥 " + toDisplayString(formatNumber(__props.latestPopulation)) + " ", 1), __props.latestPopulationYear ? (openBlock(), createBlock("span", {
												key: 0,
												class: "ml-1"
											}, " / " + toDisplayString(__props.latestPopulationYear), 1)) : createCommentVNode("", true)]),
											_: 1
										}),
										createVNode(VChip, {
											color: "blue-grey",
											variant: "tonal",
											size: "small"
										}, {
											default: withCtx(() => [createTextVNode(" lat: " + toDisplayString(__props.city.latitude ?? "—"), 1)]),
											_: 1
										}),
										createVNode(VChip, {
											color: "blue-grey",
											variant: "tonal",
											size: "small"
										}, {
											default: withCtx(() => [createTextVNode(" lon: " + toDisplayString(__props.city.longitude ?? "—"), 1)]),
											_: 1
										})
									]),
									createVNode("div", { class: "mt-3 d-flex flex-wrap ga-2" }, [
										__props.city.wiki ? (openBlock(), createBlock("a", {
											key: 0,
											href: __props.city.wiki,
											target: "_blank",
											rel: "noopener noreferrer",
											class: "text-teal-lighten-3 hover:text-white text-sm"
										}, " Wikipedia → ", 8, ["href"])) : createCommentVNode("", true),
										__props.city.yandexmapsgeo ? (openBlock(), createBlock("a", {
											key: 1,
											href: __props.city.yandexmapsgeo,
											target: "_blank",
											rel: "noopener noreferrer",
											class: "text-teal-lighten-3 hover:text-white text-sm"
										}, " Yandex Maps → ", 8, ["href"])) : createCommentVNode("", true),
										__props.city.twogis ? (openBlock(), createBlock("a", {
											key: 2,
											href: __props.city.twogis,
											target: "_blank",
											rel: "noopener noreferrer",
											class: "text-teal-lighten-3 hover:text-white text-sm"
										}, " 2GIS → ", 8, ["href"])) : createCommentVNode("", true)
									])
								]),
								_: 1
							}),
							createVNode(VCol, {
								cols: "12",
								md: "3"
							}, {
								default: withCtx(() => [createVNode("div", { class: "d-flex flex-column ga-2" }, [createVNode(VBtn, {
									text: "Редактировать",
									color: "amber",
									variant: "tonal",
									density: "comfortable",
									"prepend-icon": "mdi-pencil",
									onClick: ($event) => _ctx.$emit("edit")
								}, null, 8, ["onClick"]), createVNode(VBtn, {
									text: "Население по годам",
									color: "teal",
									variant: "tonal",
									density: "comfortable",
									"prepend-icon": "mdi-chart-line",
									onClick: ($event) => _ctx.$emit("population")
								}, null, 8, ["onClick"])])]),
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
var _sfc_setup$10 = _sfc_main$10.setup;
_sfc_main$10.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/Show/CityHeroCard.vue");
	return _sfc_setup$10 ? _sfc_setup$10(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Geography/Cities/Show/CityStatsCards.vue
var _sfc_main$9 = {
	__name: "CityStatsCards",
	__ssrInlineRender: true,
	props: { city: {
		type: Object,
		required: true
	} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VRow, mergeProps({ dense: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCol, {
							cols: "12",
							md: "4"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, { class: "rounded border border-teal-900 bg-slate-950" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCardText, null, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(`<div class="text-caption text-teal-darken-1"${_scopeId}>Buildings</div><div class="text-h5 text-teal-lighten-3"${_scopeId}>${ssrInterpolate(__props.city.buildings_count ?? __props.city.buildings?.length ?? 0)}</div>`);
												else return [createVNode("div", { class: "text-caption text-teal-darken-1" }, "Buildings"), createVNode("div", { class: "text-h5 text-teal-lighten-3" }, toDisplayString(__props.city.buildings_count ?? __props.city.buildings?.length ?? 0), 1)];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VCardText, null, {
											default: withCtx(() => [createVNode("div", { class: "text-caption text-teal-darken-1" }, "Buildings"), createVNode("div", { class: "text-h5 text-teal-lighten-3" }, toDisplayString(__props.city.buildings_count ?? __props.city.buildings?.length ?? 0), 1)]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, { class: "rounded border border-teal-900 bg-slate-950" }, {
									default: withCtx(() => [createVNode(VCardText, null, {
										default: withCtx(() => [createVNode("div", { class: "text-caption text-teal-darken-1" }, "Buildings"), createVNode("div", { class: "text-h5 text-teal-lighten-3" }, toDisplayString(__props.city.buildings_count ?? __props.city.buildings?.length ?? 0), 1)]),
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
								if (_push) _push(ssrRenderComponent(VCard, { class: "rounded border border-purple-900 bg-slate-950" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCardText, null, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(`<div class="text-caption text-purple-lighten-2"${_scopeId}>Entities</div><div class="text-h5 text-purple-lighten-3"${_scopeId}>${ssrInterpolate(__props.city.entities_count ?? __props.city.entities?.length ?? 0)}</div>`);
												else return [createVNode("div", { class: "text-caption text-purple-lighten-2" }, "Entities"), createVNode("div", { class: "text-h5 text-purple-lighten-3" }, toDisplayString(__props.city.entities_count ?? __props.city.entities?.length ?? 0), 1)];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VCardText, null, {
											default: withCtx(() => [createVNode("div", { class: "text-caption text-purple-lighten-2" }, "Entities"), createVNode("div", { class: "text-h5 text-purple-lighten-3" }, toDisplayString(__props.city.entities_count ?? __props.city.entities?.length ?? 0), 1)]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, { class: "rounded border border-purple-900 bg-slate-950" }, {
									default: withCtx(() => [createVNode(VCardText, null, {
										default: withCtx(() => [createVNode("div", { class: "text-caption text-purple-lighten-2" }, "Entities"), createVNode("div", { class: "text-h5 text-purple-lighten-3" }, toDisplayString(__props.city.entities_count ?? __props.city.entities?.length ?? 0), 1)]),
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
								if (_push) _push(ssrRenderComponent(VCard, { class: "rounded border border-blue-grey bg-slate-950" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCardText, null, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(`<div class="text-caption text-blue-grey-lighten-2"${_scopeId}>Units</div><div class="text-h5 text-blue-grey-lighten-3"${_scopeId}>${ssrInterpolate(__props.city.units_count ?? __props.city.units?.length ?? 0)}</div>`);
												else return [createVNode("div", { class: "text-caption text-blue-grey-lighten-2" }, "Units"), createVNode("div", { class: "text-h5 text-blue-grey-lighten-3" }, toDisplayString(__props.city.units_count ?? __props.city.units?.length ?? 0), 1)];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VCardText, null, {
											default: withCtx(() => [createVNode("div", { class: "text-caption text-blue-grey-lighten-2" }, "Units"), createVNode("div", { class: "text-h5 text-blue-grey-lighten-3" }, toDisplayString(__props.city.units_count ?? __props.city.units?.length ?? 0), 1)]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, { class: "rounded border border-blue-grey bg-slate-950" }, {
									default: withCtx(() => [createVNode(VCardText, null, {
										default: withCtx(() => [createVNode("div", { class: "text-caption text-blue-grey-lighten-2" }, "Units"), createVNode("div", { class: "text-h5 text-blue-grey-lighten-3" }, toDisplayString(__props.city.units_count ?? __props.city.units?.length ?? 0), 1)]),
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
							default: withCtx(() => [createVNode(VCard, { class: "rounded border border-teal-900 bg-slate-950" }, {
								default: withCtx(() => [createVNode(VCardText, null, {
									default: withCtx(() => [createVNode("div", { class: "text-caption text-teal-darken-1" }, "Buildings"), createVNode("div", { class: "text-h5 text-teal-lighten-3" }, toDisplayString(__props.city.buildings_count ?? __props.city.buildings?.length ?? 0), 1)]),
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
							default: withCtx(() => [createVNode(VCard, { class: "rounded border border-purple-900 bg-slate-950" }, {
								default: withCtx(() => [createVNode(VCardText, null, {
									default: withCtx(() => [createVNode("div", { class: "text-caption text-purple-lighten-2" }, "Entities"), createVNode("div", { class: "text-h5 text-purple-lighten-3" }, toDisplayString(__props.city.entities_count ?? __props.city.entities?.length ?? 0), 1)]),
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
							default: withCtx(() => [createVNode(VCard, { class: "rounded border border-blue-grey bg-slate-950" }, {
								default: withCtx(() => [createVNode(VCardText, null, {
									default: withCtx(() => [createVNode("div", { class: "text-caption text-blue-grey-lighten-2" }, "Units"), createVNode("div", { class: "text-h5 text-blue-grey-lighten-3" }, toDisplayString(__props.city.units_count ?? __props.city.units?.length ?? 0), 1)]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						})
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$9 = _sfc_main$9.setup;
_sfc_main$9.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/Show/CityStatsCards.vue");
	return _sfc_setup$9 ? _sfc_setup$9(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Geography/Cities/Show/CityWikipediaCard.vue
var _sfc_main$8 = {
	__name: "CityWikipediaCard",
	__ssrInlineRender: true,
	props: { city: {
		type: Object,
		required: true
	} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, mergeProps({ class: "rounded border border-teal-900 bg-slate-950 h-100" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "text-teal-lighten-3" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Wikipedia `);
								else return [createTextVNode(" Wikipedia ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									if (__props.city.wiki_title) _push(`<div class="text-h6 font-ComfortaaVariableFont"${_scopeId}>${ssrInterpolate(__props.city.wiki_title)}</div>`);
									else _push(`<!---->`);
									if (__props.city.wiki_summary) _push(`<div class="mt-2 text-sm text-grey-lighten-1"${_scopeId}>${ssrInterpolate(__props.city.wiki_summary)}</div>`);
									else _push(`<div class="text-grey text-sm"${_scopeId}> Описание из Wikipedia пока не загружено. </div>`);
									_push(`<div class="mt-4 d-flex flex-column ga-1"${_scopeId}><div class="text-caption text-teal-darken-1"${_scopeId}> Page ID: ${ssrInterpolate(__props.city.wiki_page_id ?? "—")}</div><div class="text-caption text-teal-darken-1"${_scopeId}> Lang: ${ssrInterpolate(__props.city.wiki_lang ?? "—")}</div></div><div class="mt-4"${_scopeId}>`);
									if (__props.city.wiki) _push(ssrRenderComponent(VBtn, {
										href: __props.city.wiki,
										target: "_blank",
										rel: "noopener noreferrer",
										text: "Открыть статью",
										color: "teal",
										variant: "tonal",
										density: "compact",
										"append-icon": "mdi-open-in-new"
									}, null, _parent, _scopeId));
									else _push(`<!---->`);
									_push(`</div>`);
								} else return [
									__props.city.wiki_title ? (openBlock(), createBlock("div", {
										key: 0,
										class: "text-h6 font-ComfortaaVariableFont"
									}, toDisplayString(__props.city.wiki_title), 1)) : createCommentVNode("", true),
									__props.city.wiki_summary ? (openBlock(), createBlock("div", {
										key: 1,
										class: "mt-2 text-sm text-grey-lighten-1"
									}, toDisplayString(__props.city.wiki_summary), 1)) : (openBlock(), createBlock("div", {
										key: 2,
										class: "text-grey text-sm"
									}, " Описание из Wikipedia пока не загружено. ")),
									createVNode("div", { class: "mt-4 d-flex flex-column ga-1" }, [createVNode("div", { class: "text-caption text-teal-darken-1" }, " Page ID: " + toDisplayString(__props.city.wiki_page_id ?? "—"), 1), createVNode("div", { class: "text-caption text-teal-darken-1" }, " Lang: " + toDisplayString(__props.city.wiki_lang ?? "—"), 1)]),
									createVNode("div", { class: "mt-4" }, [__props.city.wiki ? (openBlock(), createBlock(VBtn, {
										key: 0,
										href: __props.city.wiki,
										target: "_blank",
										rel: "noopener noreferrer",
										text: "Открыть статью",
										color: "teal",
										variant: "tonal",
										density: "compact",
										"append-icon": "mdi-open-in-new"
									}, null, 8, ["href"])) : createCommentVNode("", true)])
								];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VCardTitle, { class: "text-teal-lighten-3" }, {
						default: withCtx(() => [createTextVNode(" Wikipedia ")]),
						_: 1
					}), createVNode(VCardText, null, {
						default: withCtx(() => [
							__props.city.wiki_title ? (openBlock(), createBlock("div", {
								key: 0,
								class: "text-h6 font-ComfortaaVariableFont"
							}, toDisplayString(__props.city.wiki_title), 1)) : createCommentVNode("", true),
							__props.city.wiki_summary ? (openBlock(), createBlock("div", {
								key: 1,
								class: "mt-2 text-sm text-grey-lighten-1"
							}, toDisplayString(__props.city.wiki_summary), 1)) : (openBlock(), createBlock("div", {
								key: 2,
								class: "text-grey text-sm"
							}, " Описание из Wikipedia пока не загружено. ")),
							createVNode("div", { class: "mt-4 d-flex flex-column ga-1" }, [createVNode("div", { class: "text-caption text-teal-darken-1" }, " Page ID: " + toDisplayString(__props.city.wiki_page_id ?? "—"), 1), createVNode("div", { class: "text-caption text-teal-darken-1" }, " Lang: " + toDisplayString(__props.city.wiki_lang ?? "—"), 1)]),
							createVNode("div", { class: "mt-4" }, [__props.city.wiki ? (openBlock(), createBlock(VBtn, {
								key: 0,
								href: __props.city.wiki,
								target: "_blank",
								rel: "noopener noreferrer",
								text: "Открыть статью",
								color: "teal",
								variant: "tonal",
								density: "compact",
								"append-icon": "mdi-open-in-new"
							}, null, 8, ["href"])) : createCommentVNode("", true)])
						]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$8 = _sfc_main$8.setup;
_sfc_main$8.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/Show/CityWikipediaCard.vue");
	return _sfc_setup$8 ? _sfc_setup$8(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Geography/Cities/Show/CityPopulationTimeline.vue
var _sfc_main$7 = {
	__name: "CityPopulationTimeline",
	__ssrInlineRender: true,
	props: { city: {
		type: Object,
		required: true
	} },
	emits: ["edit"],
	setup(__props) {
		function formatNumber(value) {
			return new Intl.NumberFormat("ru-RU").format(value);
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, mergeProps({ class: "rounded border border-teal-900 bg-slate-950 h-100" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<span class="text-teal-lighten-3"${_scopeId}>Население</span>`);
									_push(ssrRenderComponent(VBtn, {
										icon: "mdi-pencil",
										size: "x-small",
										variant: "text",
										color: "amber",
										onClick: ($event) => _ctx.$emit("edit")
									}, null, _parent, _scopeId));
								} else return [createVNode("span", { class: "text-teal-lighten-3" }, "Население"), createVNode(VBtn, {
									icon: "mdi-pencil",
									size: "x-small",
									variant: "text",
									color: "amber",
									onClick: ($event) => _ctx.$emit("edit")
								}, null, 8, ["onClick"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) if (__props.city.populations?.length) _push(ssrRenderComponent(VTable, {
									density: "compact",
									class: "bg-transparent"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<thead${_scopeId}><tr${_scopeId}><th${_scopeId}>Год</th><th class="text-right"${_scopeId}>Кол-во</th></tr></thead><tbody${_scopeId}><!--[-->`);
											ssrRenderList(__props.city.populations, (item) => {
												_push(`<tr${_scopeId}><td class="text-teal-lighten-3"${_scopeId}>${ssrInterpolate(item.year)}</td><td class="text-right text-grey-lighten-1"${_scopeId}>${ssrInterpolate(formatNumber(item.population))}</td></tr>`);
											});
											_push(`<!--]--></tbody>`);
										} else return [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", null, "Год"), createVNode("th", { class: "text-right" }, "Кол-во")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(__props.city.populations, (item) => {
											return openBlock(), createBlock("tr", { key: item.id }, [createVNode("td", { class: "text-teal-lighten-3" }, toDisplayString(item.year), 1), createVNode("td", { class: "text-right text-grey-lighten-1" }, toDisplayString(formatNumber(item.population)), 1)]);
										}), 128))])];
									}),
									_: 1
								}, _parent, _scopeId));
								else _push(`<div class="text-grey text-sm"${_scopeId}> Данных по населению пока нет. </div>`);
								else return [__props.city.populations?.length ? (openBlock(), createBlock(VTable, {
									key: 0,
									density: "compact",
									class: "bg-transparent"
								}, {
									default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", null, "Год"), createVNode("th", { class: "text-right" }, "Кол-во")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(__props.city.populations, (item) => {
										return openBlock(), createBlock("tr", { key: item.id }, [createVNode("td", { class: "text-teal-lighten-3" }, toDisplayString(item.year), 1), createVNode("td", { class: "text-right text-grey-lighten-1" }, toDisplayString(formatNumber(item.population)), 1)]);
									}), 128))])]),
									_: 1
								})) : (openBlock(), createBlock("div", {
									key: 1,
									class: "text-grey text-sm"
								}, " Данных по населению пока нет. "))];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
						default: withCtx(() => [createVNode("span", { class: "text-teal-lighten-3" }, "Население"), createVNode(VBtn, {
							icon: "mdi-pencil",
							size: "x-small",
							variant: "text",
							color: "amber",
							onClick: ($event) => _ctx.$emit("edit")
						}, null, 8, ["onClick"])]),
						_: 1
					}), createVNode(VCardText, null, {
						default: withCtx(() => [__props.city.populations?.length ? (openBlock(), createBlock(VTable, {
							key: 0,
							density: "compact",
							class: "bg-transparent"
						}, {
							default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", null, "Год"), createVNode("th", { class: "text-right" }, "Кол-во")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(__props.city.populations, (item) => {
								return openBlock(), createBlock("tr", { key: item.id }, [createVNode("td", { class: "text-teal-lighten-3" }, toDisplayString(item.year), 1), createVNode("td", { class: "text-right text-grey-lighten-1" }, toDisplayString(formatNumber(item.population)), 1)]);
							}), 128))])]),
							_: 1
						})) : (openBlock(), createBlock("div", {
							key: 1,
							class: "text-grey text-sm"
						}, " Данных по населению пока нет. "))]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$7 = _sfc_main$7.setup;
_sfc_main$7.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/Show/CityPopulationTimeline.vue");
	return _sfc_setup$7 ? _sfc_setup$7(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Geography/Cities/Show/CityRegionCard.vue
var _sfc_main$6 = {
	__name: "CityRegionCard",
	__ssrInlineRender: true,
	props: { city: {
		type: Object,
		required: true
	} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, mergeProps({ class: "rounded border border-teal-900 bg-slate-950 h-100" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "text-teal-lighten-3" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Region / Country `);
								else return [createTextVNode(" Region / Country ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) if (__props.city.region) {
									_push(`<div${_scopeId}><div class="text-caption text-teal-darken-1"${_scopeId}> Регион </div>`);
									_push(ssrRenderComponent(unref(Link), {
										href: unref(route)("Ameise.region", __props.city.region.id),
										class: "text-h6 text-teal-lighten-3 hover:text-white font-ComfortaaVariableFont"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(__props.city.region.name)}`);
											else return [createTextVNode(toDisplayString(__props.city.region.name), 1)];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(`<div class="mt-4 text-caption text-teal-darken-1"${_scopeId}> Страна </div><div class="d-flex align-center ga-3 mt-1"${_scopeId}>`);
									if (__props.city.region.country?.flag) _push(ssrRenderComponent(VAvatar, {
										size: "42",
										rounded: "lg",
										class: "border"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VImg, {
												src: __props.city.region.country.flag,
												cover: ""
											}, null, _parent, _scopeId));
											else return [createVNode(VImg, {
												src: __props.city.region.country.flag,
												cover: ""
											}, null, 8, ["src"])];
										}),
										_: 1
									}, _parent, _scopeId));
									else _push(`<!---->`);
									_push(`<div${_scopeId}><div class="text-teal-lighten-4"${_scopeId}>${ssrInterpolate(__props.city.region.country?.name || "—")}</div><div class="text-caption text-grey"${_scopeId}> ISO: ${ssrInterpolate(__props.city.region.country?.сodeISO ?? "—")}</div></div></div><div class="mt-4 text-caption text-grey"${_scopeId}> Площадь региона: ${ssrInterpolate(__props.city.region.area ?? "—")}</div></div>`);
								} else _push(`<div class="text-grey text-sm"${_scopeId}> Регион не указан. </div>`);
								else return [__props.city.region ? (openBlock(), createBlock("div", { key: 0 }, [
									createVNode("div", { class: "text-caption text-teal-darken-1" }, " Регион "),
									createVNode(unref(Link), {
										href: unref(route)("Ameise.region", __props.city.region.id),
										class: "text-h6 text-teal-lighten-3 hover:text-white font-ComfortaaVariableFont"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(__props.city.region.name), 1)]),
										_: 1
									}, 8, ["href"]),
									createVNode("div", { class: "mt-4 text-caption text-teal-darken-1" }, " Страна "),
									createVNode("div", { class: "d-flex align-center ga-3 mt-1" }, [__props.city.region.country?.flag ? (openBlock(), createBlock(VAvatar, {
										key: 0,
										size: "42",
										rounded: "lg",
										class: "border"
									}, {
										default: withCtx(() => [createVNode(VImg, {
											src: __props.city.region.country.flag,
											cover: ""
										}, null, 8, ["src"])]),
										_: 1
									})) : createCommentVNode("", true), createVNode("div", null, [createVNode("div", { class: "text-teal-lighten-4" }, toDisplayString(__props.city.region.country?.name || "—"), 1), createVNode("div", { class: "text-caption text-grey" }, " ISO: " + toDisplayString(__props.city.region.country?.сodeISO ?? "—"), 1)])]),
									createVNode("div", { class: "mt-4 text-caption text-grey" }, " Площадь региона: " + toDisplayString(__props.city.region.area ?? "—"), 1)
								])) : (openBlock(), createBlock("div", {
									key: 1,
									class: "text-grey text-sm"
								}, " Регион не указан. "))];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VCardTitle, { class: "text-teal-lighten-3" }, {
						default: withCtx(() => [createTextVNode(" Region / Country ")]),
						_: 1
					}), createVNode(VCardText, null, {
						default: withCtx(() => [__props.city.region ? (openBlock(), createBlock("div", { key: 0 }, [
							createVNode("div", { class: "text-caption text-teal-darken-1" }, " Регион "),
							createVNode(unref(Link), {
								href: unref(route)("Ameise.region", __props.city.region.id),
								class: "text-h6 text-teal-lighten-3 hover:text-white font-ComfortaaVariableFont"
							}, {
								default: withCtx(() => [createTextVNode(toDisplayString(__props.city.region.name), 1)]),
								_: 1
							}, 8, ["href"]),
							createVNode("div", { class: "mt-4 text-caption text-teal-darken-1" }, " Страна "),
							createVNode("div", { class: "d-flex align-center ga-3 mt-1" }, [__props.city.region.country?.flag ? (openBlock(), createBlock(VAvatar, {
								key: 0,
								size: "42",
								rounded: "lg",
								class: "border"
							}, {
								default: withCtx(() => [createVNode(VImg, {
									src: __props.city.region.country.flag,
									cover: ""
								}, null, 8, ["src"])]),
								_: 1
							})) : createCommentVNode("", true), createVNode("div", null, [createVNode("div", { class: "text-teal-lighten-4" }, toDisplayString(__props.city.region.country?.name || "—"), 1), createVNode("div", { class: "text-caption text-grey" }, " ISO: " + toDisplayString(__props.city.region.country?.сodeISO ?? "—"), 1)])]),
							createVNode("div", { class: "mt-4 text-caption text-grey" }, " Площадь региона: " + toDisplayString(__props.city.region.area ?? "—"), 1)
						])) : (openBlock(), createBlock("div", {
							key: 1,
							class: "text-grey text-sm"
						}, " Регион не указан. "))]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$6 = _sfc_main$6.setup;
_sfc_main$6.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/Show/CityRegionCard.vue");
	return _sfc_setup$6 ? _sfc_setup$6(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Geography/Cities/Show/CityBuildingsCard.vue
var _sfc_main$5 = {
	__name: "CityBuildingsCard",
	__ssrInlineRender: true,
	props: { city: {
		type: Object,
		required: true
	} },
	emits: [
		"create",
		"edit",
		"delete"
	],
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, mergeProps({ class: "rounded border border-teal-900 bg-slate-950 h-100" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<span class="text-teal-lighten-3"${_scopeId}> Buildings </span>`);
									_push(ssrRenderComponent(VBtn, {
										text: "+ building",
										color: "teal",
										variant: "tonal",
										density: "compact",
										size: "small",
										onClick: ($event) => _ctx.$emit("create")
									}, null, _parent, _scopeId));
								} else return [createVNode("span", { class: "text-teal-lighten-3" }, " Buildings "), createVNode(VBtn, {
									text: "+ building",
									color: "teal",
									variant: "tonal",
									density: "compact",
									size: "small",
									onClick: ($event) => _ctx.$emit("create")
								}, null, 8, ["onClick"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) if (__props.city.buildings?.length) _push(ssrRenderComponent(VList, {
									density: "compact",
									class: "bg-transparent"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<!--[-->`);
											ssrRenderList(__props.city.buildings, (building) => {
												_push(ssrRenderComponent(VListItem, {
													key: building.id,
													class: "rounded mb-1 hover:bg-slate-800"
												}, {
													title: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<div class="d-flex align-center justify-space-between ga-2"${_scopeId}><div class="text-sm text-teal-lighten-3"${_scopeId}>${ssrInterpolate(building.address)}</div><div class="d-flex ga-1"${_scopeId}>`);
															_push(ssrRenderComponent(VBtn, {
																icon: "mdi-pencil",
																size: "x-small",
																variant: "text",
																color: "amber",
																title: "Редактировать здание",
																onClick: ($event) => _ctx.$emit("edit", building)
															}, null, _parent, _scopeId));
															_push(ssrRenderComponent(VBtn, {
																icon: "mdi-delete",
																size: "x-small",
																variant: "text",
																color: "red",
																title: "Удалить здание",
																onClick: ($event) => _ctx.$emit("delete", building)
															}, null, _parent, _scopeId));
															_push(`</div></div>`);
														} else return [createVNode("div", { class: "d-flex align-center justify-space-between ga-2" }, [createVNode("div", { class: "text-sm text-teal-lighten-3" }, toDisplayString(building.address), 1), createVNode("div", { class: "d-flex ga-1" }, [createVNode(VBtn, {
															icon: "mdi-pencil",
															size: "x-small",
															variant: "text",
															color: "amber",
															title: "Редактировать здание",
															onClick: ($event) => _ctx.$emit("edit", building)
														}, null, 8, ["onClick"]), createVNode(VBtn, {
															icon: "mdi-delete",
															size: "x-small",
															variant: "text",
															color: "red",
															title: "Удалить здание",
															onClick: ($event) => _ctx.$emit("delete", building)
														}, null, 8, ["onClick"])])])];
													}),
													subtitle: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<div class="text-[11px] text-grey"${_scopeId}> postcode: ${ssrInterpolate(building.postcode ?? "—")}</div>`);
															if (building.units?.length) {
																_push(`<div class="mt-1 d-flex flex-wrap ga-1"${_scopeId}><!--[-->`);
																ssrRenderList(building.units, (unit) => {
																	_push(ssrRenderComponent(VChip, {
																		key: unit.id,
																		size: "x-small",
																		color: "teal",
																		variant: "tonal"
																	}, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(`${ssrInterpolate(unit.name)}`);
																			else return [createTextVNode(toDisplayString(unit.name), 1)];
																		}),
																		_: 2
																	}, _parent, _scopeId));
																});
																_push(`<!--]--></div>`);
															} else _push(`<!---->`);
														} else return [createVNode("div", { class: "text-[11px] text-grey" }, " postcode: " + toDisplayString(building.postcode ?? "—"), 1), building.units?.length ? (openBlock(), createBlock("div", {
															key: 0,
															class: "mt-1 d-flex flex-wrap ga-1"
														}, [(openBlock(true), createBlock(Fragment, null, renderList(building.units, (unit) => {
															return openBlock(), createBlock(VChip, {
																key: unit.id,
																size: "x-small",
																color: "teal",
																variant: "tonal"
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																_: 2
															}, 1024);
														}), 128))])) : createCommentVNode("", true)];
													}),
													_: 2
												}, _parent, _scopeId));
											});
											_push(`<!--]-->`);
										} else return [(openBlock(true), createBlock(Fragment, null, renderList(__props.city.buildings, (building) => {
											return openBlock(), createBlock(VListItem, {
												key: building.id,
												class: "rounded mb-1 hover:bg-slate-800"
											}, {
												title: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-space-between ga-2" }, [createVNode("div", { class: "text-sm text-teal-lighten-3" }, toDisplayString(building.address), 1), createVNode("div", { class: "d-flex ga-1" }, [createVNode(VBtn, {
													icon: "mdi-pencil",
													size: "x-small",
													variant: "text",
													color: "amber",
													title: "Редактировать здание",
													onClick: ($event) => _ctx.$emit("edit", building)
												}, null, 8, ["onClick"]), createVNode(VBtn, {
													icon: "mdi-delete",
													size: "x-small",
													variant: "text",
													color: "red",
													title: "Удалить здание",
													onClick: ($event) => _ctx.$emit("delete", building)
												}, null, 8, ["onClick"])])])]),
												subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, " postcode: " + toDisplayString(building.postcode ?? "—"), 1), building.units?.length ? (openBlock(), createBlock("div", {
													key: 0,
													class: "mt-1 d-flex flex-wrap ga-1"
												}, [(openBlock(true), createBlock(Fragment, null, renderList(building.units, (unit) => {
													return openBlock(), createBlock(VChip, {
														key: unit.id,
														size: "x-small",
														color: "teal",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
														_: 2
													}, 1024);
												}), 128))])) : createCommentVNode("", true)]),
												_: 2
											}, 1024);
										}), 128))];
									}),
									_: 1
								}, _parent, _scopeId));
								else _push(`<div class="text-grey text-sm"${_scopeId}> Здания пока не привязаны. </div>`);
								else return [__props.city.buildings?.length ? (openBlock(), createBlock(VList, {
									key: 0,
									density: "compact",
									class: "bg-transparent"
								}, {
									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.city.buildings, (building) => {
										return openBlock(), createBlock(VListItem, {
											key: building.id,
											class: "rounded mb-1 hover:bg-slate-800"
										}, {
											title: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-space-between ga-2" }, [createVNode("div", { class: "text-sm text-teal-lighten-3" }, toDisplayString(building.address), 1), createVNode("div", { class: "d-flex ga-1" }, [createVNode(VBtn, {
												icon: "mdi-pencil",
												size: "x-small",
												variant: "text",
												color: "amber",
												title: "Редактировать здание",
												onClick: ($event) => _ctx.$emit("edit", building)
											}, null, 8, ["onClick"]), createVNode(VBtn, {
												icon: "mdi-delete",
												size: "x-small",
												variant: "text",
												color: "red",
												title: "Удалить здание",
												onClick: ($event) => _ctx.$emit("delete", building)
											}, null, 8, ["onClick"])])])]),
											subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, " postcode: " + toDisplayString(building.postcode ?? "—"), 1), building.units?.length ? (openBlock(), createBlock("div", {
												key: 0,
												class: "mt-1 d-flex flex-wrap ga-1"
											}, [(openBlock(true), createBlock(Fragment, null, renderList(building.units, (unit) => {
												return openBlock(), createBlock(VChip, {
													key: unit.id,
													size: "x-small",
													color: "teal",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
													_: 2
												}, 1024);
											}), 128))])) : createCommentVNode("", true)]),
											_: 2
										}, 1024);
									}), 128))]),
									_: 1
								})) : (openBlock(), createBlock("div", {
									key: 1,
									class: "text-grey text-sm"
								}, " Здания пока не привязаны. "))];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
						default: withCtx(() => [createVNode("span", { class: "text-teal-lighten-3" }, " Buildings "), createVNode(VBtn, {
							text: "+ building",
							color: "teal",
							variant: "tonal",
							density: "compact",
							size: "small",
							onClick: ($event) => _ctx.$emit("create")
						}, null, 8, ["onClick"])]),
						_: 1
					}), createVNode(VCardText, null, {
						default: withCtx(() => [__props.city.buildings?.length ? (openBlock(), createBlock(VList, {
							key: 0,
							density: "compact",
							class: "bg-transparent"
						}, {
							default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.city.buildings, (building) => {
								return openBlock(), createBlock(VListItem, {
									key: building.id,
									class: "rounded mb-1 hover:bg-slate-800"
								}, {
									title: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-space-between ga-2" }, [createVNode("div", { class: "text-sm text-teal-lighten-3" }, toDisplayString(building.address), 1), createVNode("div", { class: "d-flex ga-1" }, [createVNode(VBtn, {
										icon: "mdi-pencil",
										size: "x-small",
										variant: "text",
										color: "amber",
										title: "Редактировать здание",
										onClick: ($event) => _ctx.$emit("edit", building)
									}, null, 8, ["onClick"]), createVNode(VBtn, {
										icon: "mdi-delete",
										size: "x-small",
										variant: "text",
										color: "red",
										title: "Удалить здание",
										onClick: ($event) => _ctx.$emit("delete", building)
									}, null, 8, ["onClick"])])])]),
									subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, " postcode: " + toDisplayString(building.postcode ?? "—"), 1), building.units?.length ? (openBlock(), createBlock("div", {
										key: 0,
										class: "mt-1 d-flex flex-wrap ga-1"
									}, [(openBlock(true), createBlock(Fragment, null, renderList(building.units, (unit) => {
										return openBlock(), createBlock(VChip, {
											key: unit.id,
											size: "x-small",
											color: "teal",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
											_: 2
										}, 1024);
									}), 128))])) : createCommentVNode("", true)]),
									_: 2
								}, 1024);
							}), 128))]),
							_: 1
						})) : (openBlock(), createBlock("div", {
							key: 1,
							class: "text-grey text-sm"
						}, " Здания пока не привязаны. "))]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$5 = _sfc_main$5.setup;
_sfc_main$5.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/Show/CityBuildingsCard.vue");
	return _sfc_setup$5 ? _sfc_setup$5(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Geography/Cities/Show/CityEntitiesCard.vue
var _sfc_main$4 = {
	__name: "CityEntitiesCard",
	__ssrInlineRender: true,
	props: { city: {
		type: Object,
		required: true
	} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, mergeProps({ class: "rounded border border-purple-900 bg-slate-950 h-100" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "text-purple-lighten-3" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Entities `);
								else return [createTextVNode(" Entities ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) if (__props.city.entities?.length) _push(ssrRenderComponent(VList, {
									density: "compact",
									class: "bg-transparent"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<!--[-->`);
											ssrRenderList(__props.city.entities, (entity) => {
												_push(ssrRenderComponent(VListItem, {
													key: entity.id,
													class: "rounded mb-1 hover:bg-slate-800"
												}, {
													title: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`<div class="text-sm text-purple-lighten-3"${_scopeId}>${ssrInterpolate(entity.name)}</div>`);
														else return [createVNode("div", { class: "text-sm text-purple-lighten-3" }, toDisplayString(entity.name), 1)];
													}),
													subtitle: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`<div class="text-[11px] text-grey"${_scopeId}>${ssrInterpolate(entity.classification?.name ?? "classification: —")}</div>`);
														else return [createVNode("div", { class: "text-[11px] text-grey" }, toDisplayString(entity.classification?.name ?? "classification: —"), 1)];
													}),
													_: 2
												}, _parent, _scopeId));
											});
											_push(`<!--]-->`);
										} else return [(openBlock(true), createBlock(Fragment, null, renderList(__props.city.entities, (entity) => {
											return openBlock(), createBlock(VListItem, {
												key: entity.id,
												class: "rounded mb-1 hover:bg-slate-800"
											}, {
												title: withCtx(() => [createVNode("div", { class: "text-sm text-purple-lighten-3" }, toDisplayString(entity.name), 1)]),
												subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, toDisplayString(entity.classification?.name ?? "classification: —"), 1)]),
												_: 2
											}, 1024);
										}), 128))];
									}),
									_: 1
								}, _parent, _scopeId));
								else _push(`<div class="text-grey text-sm"${_scopeId}> Связанные сущности пока не указаны. </div>`);
								else return [__props.city.entities?.length ? (openBlock(), createBlock(VList, {
									key: 0,
									density: "compact",
									class: "bg-transparent"
								}, {
									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.city.entities, (entity) => {
										return openBlock(), createBlock(VListItem, {
											key: entity.id,
											class: "rounded mb-1 hover:bg-slate-800"
										}, {
											title: withCtx(() => [createVNode("div", { class: "text-sm text-purple-lighten-3" }, toDisplayString(entity.name), 1)]),
											subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, toDisplayString(entity.classification?.name ?? "classification: —"), 1)]),
											_: 2
										}, 1024);
									}), 128))]),
									_: 1
								})) : (openBlock(), createBlock("div", {
									key: 1,
									class: "text-grey text-sm"
								}, " Связанные сущности пока не указаны. "))];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VCardTitle, { class: "text-purple-lighten-3" }, {
						default: withCtx(() => [createTextVNode(" Entities ")]),
						_: 1
					}), createVNode(VCardText, null, {
						default: withCtx(() => [__props.city.entities?.length ? (openBlock(), createBlock(VList, {
							key: 0,
							density: "compact",
							class: "bg-transparent"
						}, {
							default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.city.entities, (entity) => {
								return openBlock(), createBlock(VListItem, {
									key: entity.id,
									class: "rounded mb-1 hover:bg-slate-800"
								}, {
									title: withCtx(() => [createVNode("div", { class: "text-sm text-purple-lighten-3" }, toDisplayString(entity.name), 1)]),
									subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, toDisplayString(entity.classification?.name ?? "classification: —"), 1)]),
									_: 2
								}, 1024);
							}), 128))]),
							_: 1
						})) : (openBlock(), createBlock("div", {
							key: 1,
							class: "text-grey text-sm"
						}, " Связанные сущности пока не указаны. "))]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$4 = _sfc_main$4.setup;
_sfc_main$4.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/Show/CityEntitiesCard.vue");
	return _sfc_setup$4 ? _sfc_setup$4(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Geography/Cities/Show/CityUnitsCard.vue
var _sfc_main$3 = {
	__name: "CityUnitsCard",
	__ssrInlineRender: true,
	props: { city: {
		type: Object,
		required: true
	} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, mergeProps({ class: "rounded border border-blue-grey bg-slate-950 h-100" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "text-blue-grey-lighten-3" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Units `);
								else return [createTextVNode(" Units ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) if (__props.city.units?.length) _push(ssrRenderComponent(VList, {
									density: "compact",
									class: "bg-transparent"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<!--[-->`);
											ssrRenderList(__props.city.units, (unit) => {
												_push(ssrRenderComponent(VListItem, {
													key: unit.id,
													class: "rounded mb-1 hover:bg-slate-800"
												}, {
													title: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(unref(Link), {
															href: unref(route)("web.unit.show", unit.id),
															class: "text-sm text-blue-grey-lighten-3 hover:text-white"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`${ssrInterpolate(unit.name)}`);
																else return [createTextVNode(toDisplayString(unit.name), 1)];
															}),
															_: 2
														}, _parent, _scopeId));
														else return [createVNode(unref(Link), {
															href: unref(route)("web.unit.show", unit.id),
															class: "text-sm text-blue-grey-lighten-3 hover:text-white"
														}, {
															default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
															_: 2
														}, 1032, ["href"])];
													}),
													subtitle: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`<div class="text-[11px] text-grey"${_scopeId}> id: ${ssrInterpolate(unit.id)}</div>`);
														else return [createVNode("div", { class: "text-[11px] text-grey" }, " id: " + toDisplayString(unit.id), 1)];
													}),
													_: 2
												}, _parent, _scopeId));
											});
											_push(`<!--]-->`);
										} else return [(openBlock(true), createBlock(Fragment, null, renderList(__props.city.units, (unit) => {
											return openBlock(), createBlock(VListItem, {
												key: unit.id,
												class: "rounded mb-1 hover:bg-slate-800"
											}, {
												title: withCtx(() => [createVNode(unref(Link), {
													href: unref(route)("web.unit.show", unit.id),
													class: "text-sm text-blue-grey-lighten-3 hover:text-white"
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
													_: 2
												}, 1032, ["href"])]),
												subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, " id: " + toDisplayString(unit.id), 1)]),
												_: 2
											}, 1024);
										}), 128))];
									}),
									_: 1
								}, _parent, _scopeId));
								else _push(`<div class="text-grey text-sm"${_scopeId}> Units пока не привязаны. </div>`);
								else return [__props.city.units?.length ? (openBlock(), createBlock(VList, {
									key: 0,
									density: "compact",
									class: "bg-transparent"
								}, {
									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.city.units, (unit) => {
										return openBlock(), createBlock(VListItem, {
											key: unit.id,
											class: "rounded mb-1 hover:bg-slate-800"
										}, {
											title: withCtx(() => [createVNode(unref(Link), {
												href: unref(route)("web.unit.show", unit.id),
												class: "text-sm text-blue-grey-lighten-3 hover:text-white"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
												_: 2
											}, 1032, ["href"])]),
											subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, " id: " + toDisplayString(unit.id), 1)]),
											_: 2
										}, 1024);
									}), 128))]),
									_: 1
								})) : (openBlock(), createBlock("div", {
									key: 1,
									class: "text-grey text-sm"
								}, " Units пока не привязаны. "))];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VCardTitle, { class: "text-blue-grey-lighten-3" }, {
						default: withCtx(() => [createTextVNode(" Units ")]),
						_: 1
					}), createVNode(VCardText, null, {
						default: withCtx(() => [__props.city.units?.length ? (openBlock(), createBlock(VList, {
							key: 0,
							density: "compact",
							class: "bg-transparent"
						}, {
							default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.city.units, (unit) => {
								return openBlock(), createBlock(VListItem, {
									key: unit.id,
									class: "rounded mb-1 hover:bg-slate-800"
								}, {
									title: withCtx(() => [createVNode(unref(Link), {
										href: unref(route)("web.unit.show", unit.id),
										class: "text-sm text-blue-grey-lighten-3 hover:text-white"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
										_: 2
									}, 1032, ["href"])]),
									subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, " id: " + toDisplayString(unit.id), 1)]),
									_: 2
								}, 1024);
							}), 128))]),
							_: 1
						})) : (openBlock(), createBlock("div", {
							key: 1,
							class: "text-grey text-sm"
						}, " Units пока не привязаны. "))]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$3 = _sfc_main$3.setup;
_sfc_main$3.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/Show/CityUnitsCard.vue");
	return _sfc_setup$3 ? _sfc_setup$3(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Geography/Cities/Show/CityYandexMapCard.vue
var _sfc_main$2 = {
	__name: "CityYandexMapCard",
	__ssrInlineRender: true,
	props: { city: {
		type: Object,
		required: true
	} },
	setup(__props) {
		const props = __props;
		const parsedYandexLink = computed(() => {
			if (!props.city.yandexmapsgeo) return {
				center: null,
				zoom: null
			};
			try {
				const url = new URL(props.city.yandexmapsgeo);
				const ll = url.searchParams.get("ll");
				const z = url.searchParams.get("z");
				return {
					center: ll ? decodeURIComponent(ll) : null,
					zoom: z ? Math.floor(Number(z)) : null
				};
			} catch (error) {
				return {
					center: null,
					zoom: null
				};
			}
		});
		computed(() => {
			if (props.city.longitude && props.city.latitude) return `${props.city.longitude},${props.city.latitude}`;
			return parsedYandexLink.value.center;
		});
		const zoom = computed(() => {
			return parsedYandexLink.value.zoom || 11;
		});
		const staticMapUrl = computed(() => {
			return null;
		});
		const hasMap = computed(() => {
			return Boolean(staticMapUrl.value);
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, mergeProps({ class: "rounded border border-teal-900 bg-slate-950 h-100" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<span class="text-teal-lighten-3"${_scopeId}> Yandex Map </span>`);
									if (__props.city.yandexmapsgeo) _push(ssrRenderComponent(VBtn, {
										href: __props.city.yandexmapsgeo,
										target: "_blank",
										rel: "noopener noreferrer",
										icon: "mdi-open-in-new",
										size: "x-small",
										variant: "text",
										color: "teal-lighten-3",
										title: "Открыть в Яндекс.Картах"
									}, null, _parent, _scopeId));
									else _push(`<!---->`);
								} else return [createVNode("span", { class: "text-teal-lighten-3" }, " Yandex Map "), __props.city.yandexmapsgeo ? (openBlock(), createBlock(VBtn, {
									key: 0,
									href: __props.city.yandexmapsgeo,
									target: "_blank",
									rel: "noopener noreferrer",
									icon: "mdi-open-in-new",
									size: "x-small",
									variant: "text",
									color: "teal-lighten-3",
									title: "Открыть в Яндекс.Картах"
								}, null, 8, ["href"])) : createCommentVNode("", true)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									if (hasMap.value) {
										_push(`<a${ssrRenderAttr("href", __props.city.yandexmapsgeo || "#")} target="_blank" rel="noopener noreferrer" class="block"${_scopeId}>`);
										_push(ssrRenderComponent(VImg, {
											src: staticMapUrl.value,
											height: "330",
											cover: "",
											class: "rounded border border-teal-800 bg-slate-900 cursor-pointer"
										}, {
											placeholder: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(`<div class="d-flex align-center justify-center fill-height"${_scopeId}>`);
													_push(ssrRenderComponent(VProgressCircular, {
														indeterminate: "",
														color: "teal"
													}, null, _parent, _scopeId));
													_push(`</div>`);
												} else return [createVNode("div", { class: "d-flex align-center justify-center fill-height" }, [createVNode(VProgressCircular, {
													indeterminate: "",
													color: "teal"
												})])];
											}),
											_: 1
										}, _parent, _scopeId));
										_push(`</a>`);
									} else _push(ssrRenderComponent(VAlert, {
										type: "info",
										variant: "tonal",
										density: "compact"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Для отображения карты нужны координаты города и ключ <code${_scopeId}>VITE_YANDEX_STATIC_MAPS_API_KEY</code>. `);
											else return [
												createTextVNode(" Для отображения карты нужны координаты города и ключ "),
												createVNode("code", null, "VITE_YANDEX_STATIC_MAPS_API_KEY"),
												createTextVNode(". ")
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(`<div class="mt-3 d-flex flex-wrap ga-2"${_scopeId}>`);
									_push(ssrRenderComponent(VChip, {
										size: "x-small",
										color: "blue-grey",
										variant: "tonal"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` lat: ${ssrInterpolate(__props.city.latitude ?? "—")}`);
											else return [createTextVNode(" lat: " + toDisplayString(__props.city.latitude ?? "—"), 1)];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VChip, {
										size: "x-small",
										color: "blue-grey",
										variant: "tonal"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` lon: ${ssrInterpolate(__props.city.longitude ?? "—")}`);
											else return [createTextVNode(" lon: " + toDisplayString(__props.city.longitude ?? "—"), 1)];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VChip, {
										size: "x-small",
										color: "teal",
										variant: "tonal"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` zoom: ${ssrInterpolate(zoom.value)}`);
											else return [createTextVNode(" zoom: " + toDisplayString(zoom.value), 1)];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(`</div>`);
								} else return [hasMap.value ? (openBlock(), createBlock("a", {
									key: 0,
									href: __props.city.yandexmapsgeo || "#",
									target: "_blank",
									rel: "noopener noreferrer",
									class: "block"
								}, [createVNode(VImg, {
									src: staticMapUrl.value,
									height: "330",
									cover: "",
									class: "rounded border border-teal-800 bg-slate-900 cursor-pointer"
								}, {
									placeholder: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-center fill-height" }, [createVNode(VProgressCircular, {
										indeterminate: "",
										color: "teal"
									})])]),
									_: 1
								}, 8, ["src"])], 8, ["href"])) : (openBlock(), createBlock(VAlert, {
									key: 1,
									type: "info",
									variant: "tonal",
									density: "compact"
								}, {
									default: withCtx(() => [
										createTextVNode(" Для отображения карты нужны координаты города и ключ "),
										createVNode("code", null, "VITE_YANDEX_STATIC_MAPS_API_KEY"),
										createTextVNode(". ")
									]),
									_: 1
								})), createVNode("div", { class: "mt-3 d-flex flex-wrap ga-2" }, [
									createVNode(VChip, {
										size: "x-small",
										color: "blue-grey",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(" lat: " + toDisplayString(__props.city.latitude ?? "—"), 1)]),
										_: 1
									}),
									createVNode(VChip, {
										size: "x-small",
										color: "blue-grey",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(" lon: " + toDisplayString(__props.city.longitude ?? "—"), 1)]),
										_: 1
									}),
									createVNode(VChip, {
										size: "x-small",
										color: "teal",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(" zoom: " + toDisplayString(zoom.value), 1)]),
										_: 1
									})
								])];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
						default: withCtx(() => [createVNode("span", { class: "text-teal-lighten-3" }, " Yandex Map "), __props.city.yandexmapsgeo ? (openBlock(), createBlock(VBtn, {
							key: 0,
							href: __props.city.yandexmapsgeo,
							target: "_blank",
							rel: "noopener noreferrer",
							icon: "mdi-open-in-new",
							size: "x-small",
							variant: "text",
							color: "teal-lighten-3",
							title: "Открыть в Яндекс.Картах"
						}, null, 8, ["href"])) : createCommentVNode("", true)]),
						_: 1
					}), createVNode(VCardText, null, {
						default: withCtx(() => [hasMap.value ? (openBlock(), createBlock("a", {
							key: 0,
							href: __props.city.yandexmapsgeo || "#",
							target: "_blank",
							rel: "noopener noreferrer",
							class: "block"
						}, [createVNode(VImg, {
							src: staticMapUrl.value,
							height: "330",
							cover: "",
							class: "rounded border border-teal-800 bg-slate-900 cursor-pointer"
						}, {
							placeholder: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-center fill-height" }, [createVNode(VProgressCircular, {
								indeterminate: "",
								color: "teal"
							})])]),
							_: 1
						}, 8, ["src"])], 8, ["href"])) : (openBlock(), createBlock(VAlert, {
							key: 1,
							type: "info",
							variant: "tonal",
							density: "compact"
						}, {
							default: withCtx(() => [
								createTextVNode(" Для отображения карты нужны координаты города и ключ "),
								createVNode("code", null, "VITE_YANDEX_STATIC_MAPS_API_KEY"),
								createTextVNode(". ")
							]),
							_: 1
						})), createVNode("div", { class: "mt-3 d-flex flex-wrap ga-2" }, [
							createVNode(VChip, {
								size: "x-small",
								color: "blue-grey",
								variant: "tonal"
							}, {
								default: withCtx(() => [createTextVNode(" lat: " + toDisplayString(__props.city.latitude ?? "—"), 1)]),
								_: 1
							}),
							createVNode(VChip, {
								size: "x-small",
								color: "blue-grey",
								variant: "tonal"
							}, {
								default: withCtx(() => [createTextVNode(" lon: " + toDisplayString(__props.city.longitude ?? "—"), 1)]),
								_: 1
							}),
							createVNode(VChip, {
								size: "x-small",
								color: "teal",
								variant: "tonal"
							}, {
								default: withCtx(() => [createTextVNode(" zoom: " + toDisplayString(zoom.value), 1)]),
								_: 1
							})
						])]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/Show/CityYandexMapCard.vue");
	return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Composables/Geography/useCityShow.js
function useCityShow(initialCityId = null, initialCity = null) {
	const cityId = ref(initialCityId ?? initialCity?.id ?? null);
	const city = ref(initialCity);
	const loading = ref(false);
	const latestPopulation = computed(() => {
		return city.value?.latest_population?.population ?? city.value?.latestPopulation?.population ?? city.value?.population ?? null;
	});
	const latestPopulationYear = computed(() => {
		return city.value?.latest_population?.year ?? city.value?.latestPopulation?.year ?? null;
	});
	async function fetchCity(id = cityId.value) {
		if (!id) return;
		cityId.value = id;
		loading.value = true;
		try {
			city.value = (await axios.get(route("cities.show", id))).data;
		} catch (error) {
			console.error("City show loading error:", error);
		} finally {
			loading.value = false;
		}
	}
	async function updateCity(payload) {
		if (!cityId.value) return null;
		const response = await axios.patch(route("cities.update", cityId.value), payload);
		city.value = response.data;
		return response.data;
	}
	return {
		cityId,
		city,
		loading,
		latestPopulation,
		latestPopulationYear,
		fetchCity,
		updateCity
	};
}
//#endregion
//#region resources/js/Components/Geography/Cities/Show/CityShowPage.vue
var _sfc_main$1 = {
	__name: "CityShowPage",
	__ssrInlineRender: true,
	props: {
		cityId: {
			type: [Number, String],
			required: true
		},
		initialCity: {
			type: Object,
			default: null
		}
	},
	setup(__props) {
		const props = __props;
		const { city, loading, latestPopulation, latestPopulationYear, fetchCity, updateCity } = useCityShow(props.cityId, props.initialCity);
		const { regions, loadingRegions, fetchRegions } = useRegions();
		const formDialog = ref(false);
		const populationDialog = ref(false);
		const snackbar = ref({
			show: false,
			text: "",
			color: "success"
		});
		function openEditDialog() {
			formDialog.value = true;
		}
		function openPopulationDialog() {
			populationDialog.value = true;
		}
		async function handleSaveCity(payload) {
			try {
				await updateCity(payload);
				await fetchCity();
				formDialog.value = false;
				snackbar.value = {
					show: true,
					text: "Город обновлён",
					color: "success"
				};
			} catch (error) {
				console.error(error);
				snackbar.value = {
					show: true,
					text: "Ошибка обновления города",
					color: "error"
				};
			}
		}
		async function handlePopulationSaved() {
			await fetchCity();
			snackbar.value = {
				show: true,
				text: "Данные по населению обновлены",
				color: "success"
			};
		}
		const buildingDialog = ref(false);
		const buildingDialogMode = ref("create");
		const selectedBuilding = ref(null);
		const savingBuilding = ref(false);
		function openCreateBuildingDialog() {
			selectedBuilding.value = null;
			buildingDialogMode.value = "create";
			buildingDialog.value = true;
		}
		function openEditBuildingDialog(building) {
			selectedBuilding.value = building;
			buildingDialogMode.value = "edit";
			buildingDialog.value = true;
		}
		async function handleSaveBuilding(payload) {
			savingBuilding.value = true;
			try {
				if (buildingDialogMode.value === "create") await axios.post(route("buildings.store"), payload);
				else await axios.patch(route("buildings.update", selectedBuilding.value.id), payload);
				await fetchCity();
				buildingDialog.value = false;
				snackbar.value = {
					show: true,
					text: buildingDialogMode.value === "create" ? "Здание добавлено" : "Здание обновлено",
					color: "success"
				};
			} catch (error) {
				console.error(error);
				snackbar.value = {
					show: true,
					text: "Ошибка сохранения здания",
					color: "error"
				};
			} finally {
				savingBuilding.value = false;
			}
		}
		async function handleDeleteBuilding(building) {
			if (!window.confirm(`Удалить здание "${building.address}"?`)) return;
			try {
				await axios.delete(route("buildings.destroy", building.id));
				await fetchCity();
				snackbar.value = {
					show: true,
					text: "Здание удалено",
					color: "success"
				};
			} catch (error) {
				console.error(error);
				snackbar.value = {
					show: true,
					text: "Ошибка удаления здания",
					color: "error"
				};
			}
		}
		onMounted(async () => {
			await Promise.all([fetchRegions(), fetchCity(props.cityId)]);
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCol, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<div class="mb-3"${_scopeId}><button type="button" class="text-teal-lighten-3 hover:text-white text-sm"${_scopeId}> ← Grossbuch / Geography / Cities </button></div>`);
											if (unref(loading) && !unref(city)) _push(ssrRenderComponent(VSkeletonLoader, { type: "card, article, table" }, null, _parent, _scopeId));
											else if (unref(city)) {
												_push(`<!--[-->`);
												_push(ssrRenderComponent(_sfc_main$10, {
													city: unref(city),
													"latest-population": unref(latestPopulation),
													"latest-population-year": unref(latestPopulationYear),
													onEdit: openEditDialog,
													onPopulation: openPopulationDialog
												}, null, _parent, _scopeId));
												_push(ssrRenderComponent(_sfc_main$9, {
													city: unref(city),
													class: "mt-3"
												}, null, _parent, _scopeId));
												_push(ssrRenderComponent(VRow, { class: "mt-1" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																lg: "5"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(_sfc_main$8, { city: unref(city) }, null, _parent, _scopeId));
																	else return [createVNode(_sfc_main$8, { city: unref(city) }, null, 8, ["city"])];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																lg: "4"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(_sfc_main$2, { city: unref(city) }, null, _parent, _scopeId));
																	else return [createVNode(_sfc_main$2, { city: unref(city) }, null, 8, ["city"])];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																lg: "3"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(_sfc_main$7, {
																		city: unref(city),
																		onEdit: openPopulationDialog
																	}, null, _parent, _scopeId));
																	else return [createVNode(_sfc_main$7, {
																		city: unref(city),
																		onEdit: openPopulationDialog
																	}, null, 8, ["city"])];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [
															createVNode(VCol, {
																cols: "12",
																lg: "5"
															}, {
																default: withCtx(() => [createVNode(_sfc_main$8, { city: unref(city) }, null, 8, ["city"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																lg: "4"
															}, {
																default: withCtx(() => [createVNode(_sfc_main$2, { city: unref(city) }, null, 8, ["city"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																lg: "3"
															}, {
																default: withCtx(() => [createVNode(_sfc_main$7, {
																	city: unref(city),
																	onEdit: openPopulationDialog
																}, null, 8, ["city"])]),
																_: 1
															})
														];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VRow, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(_sfc_main$6, { city: unref(city) }, null, _parent, _scopeId));
																else return [createVNode(_sfc_main$6, { city: unref(city) }, null, 8, ["city"])];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(_sfc_main$6, { city: unref(city) }, null, 8, ["city"])]),
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
																lg: "4"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(_sfc_main$5, {
																		city: unref(city),
																		onCreate: openCreateBuildingDialog,
																		onEdit: openEditBuildingDialog,
																		onDelete: handleDeleteBuilding
																	}, null, _parent, _scopeId));
																	else return [createVNode(_sfc_main$5, {
																		city: unref(city),
																		onCreate: openCreateBuildingDialog,
																		onEdit: openEditBuildingDialog,
																		onDelete: handleDeleteBuilding
																	}, null, 8, ["city"])];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																lg: "4"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(_sfc_main$4, { city: unref(city) }, null, _parent, _scopeId));
																	else return [createVNode(_sfc_main$4, { city: unref(city) }, null, 8, ["city"])];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																lg: "4"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(_sfc_main$3, { city: unref(city) }, null, _parent, _scopeId));
																	else return [createVNode(_sfc_main$3, { city: unref(city) }, null, 8, ["city"])];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [
															createVNode(VCol, {
																cols: "12",
																lg: "4"
															}, {
																default: withCtx(() => [createVNode(_sfc_main$5, {
																	city: unref(city),
																	onCreate: openCreateBuildingDialog,
																	onEdit: openEditBuildingDialog,
																	onDelete: handleDeleteBuilding
																}, null, 8, ["city"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																lg: "4"
															}, {
																default: withCtx(() => [createVNode(_sfc_main$4, { city: unref(city) }, null, 8, ["city"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																lg: "4"
															}, {
																default: withCtx(() => [createVNode(_sfc_main$3, { city: unref(city) }, null, 8, ["city"])]),
																_: 1
															})
														];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(_sfc_main$13, {
													modelValue: formDialog.value,
													"onUpdate:modelValue": ($event) => formDialog.value = $event,
													mode: "edit",
													city: unref(city),
													regions: unref(regions),
													"loading-regions": unref(loadingRegions),
													onSave: handleSaveCity
												}, null, _parent, _scopeId));
												_push(ssrRenderComponent(_sfc_main$14, {
													modelValue: populationDialog.value,
													"onUpdate:modelValue": ($event) => populationDialog.value = $event,
													city: unref(city),
													onSaved: handlePopulationSaved
												}, null, _parent, _scopeId));
												_push(ssrRenderComponent(_sfc_main$11, {
													modelValue: buildingDialog.value,
													"onUpdate:modelValue": ($event) => buildingDialog.value = $event,
													mode: buildingDialogMode.value,
													city: unref(city),
													building: selectedBuilding.value,
													loading: savingBuilding.value,
													onSave: handleSaveBuilding
												}, null, _parent, _scopeId));
												_push(`<!--]-->`);
											} else _push(ssrRenderComponent(VAlert, {
												type: "warning",
												variant: "tonal"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Город не найден. `);
													else return [createTextVNode(" Город не найден. ")];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [createVNode("div", { class: "mb-3" }, [createVNode("button", {
											type: "button",
											class: "text-teal-lighten-3 hover:text-white text-sm",
											onClick: ($event) => _ctx.window.history.back()
										}, " ← Grossbuch / Geography / Cities ", 8, ["onClick"])]), unref(loading) && !unref(city) ? (openBlock(), createBlock(VSkeletonLoader, {
											key: 0,
											type: "card, article, table"
										})) : unref(city) ? (openBlock(), createBlock(Fragment, { key: 1 }, [
											createVNode(_sfc_main$10, {
												city: unref(city),
												"latest-population": unref(latestPopulation),
												"latest-population-year": unref(latestPopulationYear),
												onEdit: openEditDialog,
												onPopulation: openPopulationDialog
											}, null, 8, [
												"city",
												"latest-population",
												"latest-population-year"
											]),
											createVNode(_sfc_main$9, {
												city: unref(city),
												class: "mt-3"
											}, null, 8, ["city"]),
											createVNode(VRow, { class: "mt-1" }, {
												default: withCtx(() => [
													createVNode(VCol, {
														cols: "12",
														lg: "5"
													}, {
														default: withCtx(() => [createVNode(_sfc_main$8, { city: unref(city) }, null, 8, ["city"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														lg: "4"
													}, {
														default: withCtx(() => [createVNode(_sfc_main$2, { city: unref(city) }, null, 8, ["city"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														lg: "3"
													}, {
														default: withCtx(() => [createVNode(_sfc_main$7, {
															city: unref(city),
															onEdit: openPopulationDialog
														}, null, 8, ["city"])]),
														_: 1
													})
												]),
												_: 1
											}),
											createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(_sfc_main$6, { city: unref(city) }, null, 8, ["city"])]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VRow, null, {
												default: withCtx(() => [
													createVNode(VCol, {
														cols: "12",
														lg: "4"
													}, {
														default: withCtx(() => [createVNode(_sfc_main$5, {
															city: unref(city),
															onCreate: openCreateBuildingDialog,
															onEdit: openEditBuildingDialog,
															onDelete: handleDeleteBuilding
														}, null, 8, ["city"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														lg: "4"
													}, {
														default: withCtx(() => [createVNode(_sfc_main$4, { city: unref(city) }, null, 8, ["city"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														lg: "4"
													}, {
														default: withCtx(() => [createVNode(_sfc_main$3, { city: unref(city) }, null, 8, ["city"])]),
														_: 1
													})
												]),
												_: 1
											}),
											createVNode(_sfc_main$13, {
												modelValue: formDialog.value,
												"onUpdate:modelValue": ($event) => formDialog.value = $event,
												mode: "edit",
												city: unref(city),
												regions: unref(regions),
												"loading-regions": unref(loadingRegions),
												onSave: handleSaveCity
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"city",
												"regions",
												"loading-regions"
											]),
											createVNode(_sfc_main$14, {
												modelValue: populationDialog.value,
												"onUpdate:modelValue": ($event) => populationDialog.value = $event,
												city: unref(city),
												onSaved: handlePopulationSaved
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"city"
											]),
											createVNode(_sfc_main$11, {
												modelValue: buildingDialog.value,
												"onUpdate:modelValue": ($event) => buildingDialog.value = $event,
												mode: buildingDialogMode.value,
												city: unref(city),
												building: selectedBuilding.value,
												loading: savingBuilding.value,
												onSave: handleSaveBuilding
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"mode",
												"city",
												"building",
												"loading"
											])
										], 64)) : (openBlock(), createBlock(VAlert, {
											key: 2,
											type: "warning",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(" Город не найден. ")]),
											_: 1
										}))];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCol, null, {
									default: withCtx(() => [createVNode("div", { class: "mb-3" }, [createVNode("button", {
										type: "button",
										class: "text-teal-lighten-3 hover:text-white text-sm",
										onClick: ($event) => _ctx.window.history.back()
									}, " ← Grossbuch / Geography / Cities ", 8, ["onClick"])]), unref(loading) && !unref(city) ? (openBlock(), createBlock(VSkeletonLoader, {
										key: 0,
										type: "card, article, table"
									})) : unref(city) ? (openBlock(), createBlock(Fragment, { key: 1 }, [
										createVNode(_sfc_main$10, {
											city: unref(city),
											"latest-population": unref(latestPopulation),
											"latest-population-year": unref(latestPopulationYear),
											onEdit: openEditDialog,
											onPopulation: openPopulationDialog
										}, null, 8, [
											"city",
											"latest-population",
											"latest-population-year"
										]),
										createVNode(_sfc_main$9, {
											city: unref(city),
											class: "mt-3"
										}, null, 8, ["city"]),
										createVNode(VRow, { class: "mt-1" }, {
											default: withCtx(() => [
												createVNode(VCol, {
													cols: "12",
													lg: "5"
												}, {
													default: withCtx(() => [createVNode(_sfc_main$8, { city: unref(city) }, null, 8, ["city"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													lg: "4"
												}, {
													default: withCtx(() => [createVNode(_sfc_main$2, { city: unref(city) }, null, 8, ["city"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													lg: "3"
												}, {
													default: withCtx(() => [createVNode(_sfc_main$7, {
														city: unref(city),
														onEdit: openPopulationDialog
													}, null, 8, ["city"])]),
													_: 1
												})
											]),
											_: 1
										}),
										createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(_sfc_main$6, { city: unref(city) }, null, 8, ["city"])]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VRow, null, {
											default: withCtx(() => [
												createVNode(VCol, {
													cols: "12",
													lg: "4"
												}, {
													default: withCtx(() => [createVNode(_sfc_main$5, {
														city: unref(city),
														onCreate: openCreateBuildingDialog,
														onEdit: openEditBuildingDialog,
														onDelete: handleDeleteBuilding
													}, null, 8, ["city"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													lg: "4"
												}, {
													default: withCtx(() => [createVNode(_sfc_main$4, { city: unref(city) }, null, 8, ["city"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													lg: "4"
												}, {
													default: withCtx(() => [createVNode(_sfc_main$3, { city: unref(city) }, null, 8, ["city"])]),
													_: 1
												})
											]),
											_: 1
										}),
										createVNode(_sfc_main$13, {
											modelValue: formDialog.value,
											"onUpdate:modelValue": ($event) => formDialog.value = $event,
											mode: "edit",
											city: unref(city),
											regions: unref(regions),
											"loading-regions": unref(loadingRegions),
											onSave: handleSaveCity
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"city",
											"regions",
											"loading-regions"
										]),
										createVNode(_sfc_main$14, {
											modelValue: populationDialog.value,
											"onUpdate:modelValue": ($event) => populationDialog.value = $event,
											city: unref(city),
											onSaved: handlePopulationSaved
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"city"
										]),
										createVNode(_sfc_main$11, {
											modelValue: buildingDialog.value,
											"onUpdate:modelValue": ($event) => buildingDialog.value = $event,
											mode: buildingDialogMode.value,
											city: unref(city),
											building: selectedBuilding.value,
											loading: savingBuilding.value,
											onSave: handleSaveBuilding
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"mode",
											"city",
											"building",
											"loading"
										])
									], 64)) : (openBlock(), createBlock(VAlert, {
										key: 2,
										type: "warning",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(" Город не найден. ")]),
										_: 1
									}))]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VSnackbar, {
							modelValue: snackbar.value.show,
							"onUpdate:modelValue": ($event) => snackbar.value.show = $event,
							color: snackbar.value.color,
							timeout: 2500
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(`${ssrInterpolate(snackbar.value.text)}`);
								else return [createTextVNode(toDisplayString(snackbar.value.text), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, null, {
							default: withCtx(() => [createVNode("div", { class: "mb-3" }, [createVNode("button", {
								type: "button",
								class: "text-teal-lighten-3 hover:text-white text-sm",
								onClick: ($event) => _ctx.window.history.back()
							}, " ← Grossbuch / Geography / Cities ", 8, ["onClick"])]), unref(loading) && !unref(city) ? (openBlock(), createBlock(VSkeletonLoader, {
								key: 0,
								type: "card, article, table"
							})) : unref(city) ? (openBlock(), createBlock(Fragment, { key: 1 }, [
								createVNode(_sfc_main$10, {
									city: unref(city),
									"latest-population": unref(latestPopulation),
									"latest-population-year": unref(latestPopulationYear),
									onEdit: openEditDialog,
									onPopulation: openPopulationDialog
								}, null, 8, [
									"city",
									"latest-population",
									"latest-population-year"
								]),
								createVNode(_sfc_main$9, {
									city: unref(city),
									class: "mt-3"
								}, null, 8, ["city"]),
								createVNode(VRow, { class: "mt-1" }, {
									default: withCtx(() => [
										createVNode(VCol, {
											cols: "12",
											lg: "5"
										}, {
											default: withCtx(() => [createVNode(_sfc_main$8, { city: unref(city) }, null, 8, ["city"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											lg: "4"
										}, {
											default: withCtx(() => [createVNode(_sfc_main$2, { city: unref(city) }, null, 8, ["city"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											lg: "3"
										}, {
											default: withCtx(() => [createVNode(_sfc_main$7, {
												city: unref(city),
												onEdit: openPopulationDialog
											}, null, 8, ["city"])]),
											_: 1
										})
									]),
									_: 1
								}),
								createVNode(VRow, null, {
									default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
										default: withCtx(() => [createVNode(_sfc_main$6, { city: unref(city) }, null, 8, ["city"])]),
										_: 1
									})]),
									_: 1
								}),
								createVNode(VRow, null, {
									default: withCtx(() => [
										createVNode(VCol, {
											cols: "12",
											lg: "4"
										}, {
											default: withCtx(() => [createVNode(_sfc_main$5, {
												city: unref(city),
												onCreate: openCreateBuildingDialog,
												onEdit: openEditBuildingDialog,
												onDelete: handleDeleteBuilding
											}, null, 8, ["city"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											lg: "4"
										}, {
											default: withCtx(() => [createVNode(_sfc_main$4, { city: unref(city) }, null, 8, ["city"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											lg: "4"
										}, {
											default: withCtx(() => [createVNode(_sfc_main$3, { city: unref(city) }, null, 8, ["city"])]),
											_: 1
										})
									]),
									_: 1
								}),
								createVNode(_sfc_main$13, {
									modelValue: formDialog.value,
									"onUpdate:modelValue": ($event) => formDialog.value = $event,
									mode: "edit",
									city: unref(city),
									regions: unref(regions),
									"loading-regions": unref(loadingRegions),
									onSave: handleSaveCity
								}, null, 8, [
									"modelValue",
									"onUpdate:modelValue",
									"city",
									"regions",
									"loading-regions"
								]),
								createVNode(_sfc_main$14, {
									modelValue: populationDialog.value,
									"onUpdate:modelValue": ($event) => populationDialog.value = $event,
									city: unref(city),
									onSaved: handlePopulationSaved
								}, null, 8, [
									"modelValue",
									"onUpdate:modelValue",
									"city"
								]),
								createVNode(_sfc_main$11, {
									modelValue: buildingDialog.value,
									"onUpdate:modelValue": ($event) => buildingDialog.value = $event,
									mode: buildingDialogMode.value,
									city: unref(city),
									building: selectedBuilding.value,
									loading: savingBuilding.value,
									onSave: handleSaveBuilding
								}, null, 8, [
									"modelValue",
									"onUpdate:modelValue",
									"mode",
									"city",
									"building",
									"loading"
								])
							], 64)) : (openBlock(), createBlock(VAlert, {
								key: 2,
								type: "warning",
								variant: "tonal"
							}, {
								default: withCtx(() => [createTextVNode(" Город не найден. ")]),
								_: 1
							}))]),
							_: 1
						})]),
						_: 1
					}), createVNode(VSnackbar, {
						modelValue: snackbar.value.show,
						"onUpdate:modelValue": ($event) => snackbar.value.show = $event,
						color: snackbar.value.color,
						timeout: 2500
					}, {
						default: withCtx(() => [createTextVNode(toDisplayString(snackbar.value.text), 1)]),
						_: 1
					}, 8, [
						"modelValue",
						"onUpdate:modelValue",
						"color"
					])];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Geography/Cities/Show/CityShowPage.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Pages/Ameise/City.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$12 }, {
	__name: "City",
	__ssrInlineRender: true,
	props: {
		cityId: {
			type: [Number, String],
			required: false,
			default: null
		},
		city: {
			type: Object,
			required: false,
			default: null
		}
	},
	setup(__props) {
		const props = __props;
		const resolvedCityId = computed(() => props.cityId ?? props.city?.id);
		useHead({
			title: "Город",
			meta: [{
				name: "description",
				content: "Страница города"
			}]
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(_sfc_main$1, mergeProps({
				"city-id": resolvedCityId.value,
				"initial-city": __props.city
			}, _attrs), null, _parent));
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/City.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=City-BnBDLk4p.js.map