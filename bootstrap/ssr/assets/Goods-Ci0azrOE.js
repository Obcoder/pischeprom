import { B as VBadge, C as VRow, D as VDataTable, F as VCardText, I as VCardTitle, K as VDivider, P as VCard, R as VCardActions, S as VSpacer, T as VContainer, U as VTextField, V as VAutocomplete, dt as useDate, g as VFileInput, h as VForm, j as VSheet, lt as VImg, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import "./consts-BmbOcoGu.js";
import axios from "axios";
import { Link, useForm } from "@inertiajs/vue3";
import { createBlock, createTextVNode, createVNode, isRef, mergeProps, onMounted, openBlock, ref, toDisplayString, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderComponent } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Ameise/Goods.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Goods",
	__ssrInlineRender: true,
	setup(__props) {
		useDate();
		let goods = ref();
		const good = ref(null);
		let products = ref();
		let searchGoods = ref();
		const formGood = useForm({
			name: null,
			ava_image: null,
			products: null
		});
		function storeGood() {
			formGood.post(route("goods.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: false,
				onSuccess: () => {
					formGood.reset();
					indexGoods(searchGoods);
				}
			});
		}
		function fetchGood(id) {
			axios.get(route("good.fetch", id)).then(function(response) {
				good.value = response.data;
			}).catch(function(error) {
				console.error(error);
			});
		}
		function indexGoods() {
			axios.get(route("goods.index")).then(function(response) {
				goods.value = response.data;
				if (goods.value && goods.value.length > 0) fetchGood(goods.value[0].id);
			}).catch(function(error) {
				console.log(error);
			});
		}
		function indexProducts() {
			axios.get(route("products.index")).then(function(response) {
				products.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		const headersGoods = [{
			title: "Avatar",
			key: "ava_image"
		}, {
			title: "name",
			key: "name"
		}];
		onMounted(() => {
			indexGoods();
			indexProducts();
		});
		useHead({
			title: `Товары`,
			meta: [{
				name: "description",
				content: `Товары в БД ПИЩЕПРОМ-СЕРВЕРА`
			}]
		});
		const generateSlug = (name) => {
			return name.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VRow, null, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCol, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VRow, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCol, { cols: "9" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VTextField, {
																	label: "Поиск: Товары",
																	modelValue: unref(searchGoods),
																	"onUpdate:modelValue": ($event) => isRef(searchGoods) ? searchGoods.value = $event : searchGoods = $event,
																	variant: "outlined",
																	class: "mt-1 py-1"
																}, null, _parent, _scopeId));
																else return [createVNode(VTextField, {
																	label: "Поиск: Товары",
																	modelValue: unref(searchGoods),
																	"onUpdate:modelValue": ($event) => isRef(searchGoods) ? searchGoods.value = $event : searchGoods = $event,
																	variant: "outlined",
																	class: "mt-1 py-1"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCol, { cols: "3" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VDialog, {
																	transition: "dialog-top-transition",
																	width: "900"
																}, {
																	activator: withCtx(({ props: activatorProps }, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VBtn, mergeProps(activatorProps, {
																			text: "Новый товар",
																			block: ""
																		}), null, _parent, _scopeId));
																		else return [createVNode(VBtn, mergeProps(activatorProps, {
																			text: "Новый товар",
																			block: ""
																		}), null, 16)];
																	}),
																	default: withCtx(({ isActive }, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VCard, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(ssrRenderComponent(VCardTitle, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`Form Good`);
																							else return [createTextVNode("Form Good")];
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
																												if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) _push(ssrRenderComponent(VAutocomplete, {
																															items: unref(products),
																															"item-title": "rus",
																															"item-value": "id",
																															modelValue: unref(formGood).products,
																															"onUpdate:modelValue": ($event) => unref(formGood).products = $event,
																															label: "Products",
																															placeholder: "Выбери Product",
																															density: "compact",
																															variant: "outlined",
																															color: "red",
																															multiple: ""
																														}, null, _parent, _scopeId));
																														else return [createVNode(VAutocomplete, {
																															items: unref(products),
																															"item-title": "rus",
																															"item-value": "id",
																															modelValue: unref(formGood).products,
																															"onUpdate:modelValue": ($event) => unref(formGood).products = $event,
																															label: "Products",
																															placeholder: "Выбери Product",
																															density: "compact",
																															variant: "outlined",
																															color: "red",
																															multiple: ""
																														}, null, 8, [
																															"items",
																															"modelValue",
																															"onUpdate:modelValue"
																														])];
																													}),
																													_: 2
																												}, _parent, _scopeId));
																												else return [createVNode(VCol, { cols: "12" }, {
																													default: withCtx(() => [createVNode(VAutocomplete, {
																														items: unref(products),
																														"item-title": "rus",
																														"item-value": "id",
																														modelValue: unref(formGood).products,
																														"onUpdate:modelValue": ($event) => unref(formGood).products = $event,
																														label: "Products",
																														placeholder: "Выбери Product",
																														density: "compact",
																														variant: "outlined",
																														color: "red",
																														multiple: ""
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
																												if (_push) _push(ssrRenderComponent(VTextField, {
																													modelValue: unref(formGood).name,
																													"onUpdate:modelValue": ($event) => unref(formGood).name = $event,
																													label: "Good name",
																													variant: "outlined"
																												}, null, _parent, _scopeId));
																												else return [createVNode(VTextField, {
																													modelValue: unref(formGood).name,
																													"onUpdate:modelValue": ($event) => unref(formGood).name = $event,
																													label: "Good name",
																													variant: "outlined"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																											}),
																											_: 2
																										}, _parent, _scopeId));
																										_push(ssrRenderComponent(VRow, null, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(ssrRenderComponent(VFileInput, {
																													modelValue: unref(formGood).ava_image,
																													"onUpdate:modelValue": ($event) => unref(formGood).ava_image = $event,
																													label: "Good's avatar",
																													chips: ""
																												}, null, _parent, _scopeId));
																												else return [createVNode(VFileInput, {
																													modelValue: unref(formGood).ava_image,
																													"onUpdate:modelValue": ($event) => unref(formGood).ava_image = $event,
																													label: "Good's avatar",
																													chips: ""
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																											}),
																											_: 2
																										}, _parent, _scopeId));
																									} else return [
																										createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																												default: withCtx(() => [createVNode(VAutocomplete, {
																													items: unref(products),
																													"item-title": "rus",
																													"item-value": "id",
																													modelValue: unref(formGood).products,
																													"onUpdate:modelValue": ($event) => unref(formGood).products = $event,
																													label: "Products",
																													placeholder: "Выбери Product",
																													density: "compact",
																													variant: "outlined",
																													color: "red",
																													multiple: ""
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
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formGood).name,
																												"onUpdate:modelValue": ($event) => unref(formGood).name = $event,
																												label: "Good name",
																												variant: "outlined"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										}),
																										createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VFileInput, {
																												modelValue: unref(formGood).ava_image,
																												"onUpdate:modelValue": ($event) => unref(formGood).ava_image = $event,
																												label: "Good's avatar",
																												chips: ""
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										})
																									];
																								}),
																								_: 2
																							}, _parent, _scopeId));
																							else return [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																								default: withCtx(() => [
																									createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																											default: withCtx(() => [createVNode(VAutocomplete, {
																												items: unref(products),
																												"item-title": "rus",
																												"item-value": "id",
																												modelValue: unref(formGood).products,
																												"onUpdate:modelValue": ($event) => unref(formGood).products = $event,
																												label: "Products",
																												placeholder: "Выбери Product",
																												density: "compact",
																												variant: "outlined",
																												color: "red",
																												multiple: ""
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
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formGood).name,
																											"onUpdate:modelValue": ($event) => unref(formGood).name = $event,
																											label: "Good name",
																											variant: "outlined"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									}),
																									createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VFileInput, {
																											modelValue: unref(formGood).ava_image,
																											"onUpdate:modelValue": ($event) => unref(formGood).ava_image = $event,
																											label: "Good's avatar",
																											chips: ""
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
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
																							if (_push) {
																								_push(ssrRenderComponent(VSpacer, null, null, _parent, _scopeId));
																								_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
																								_push(ssrRenderComponent(VBtn, {
																									text: "save",
																									onClick: storeGood,
																									variant: "plain",
																									color: "indigo-darken-4"
																								}, null, _parent, _scopeId));
																							} else return [
																								createVNode(VSpacer),
																								createVNode(VDivider),
																								createVNode(VBtn, {
																									text: "save",
																									onClick: storeGood,
																									variant: "plain",
																									color: "indigo-darken-4"
																								})
																							];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																				} else return [
																					createVNode(VCardTitle, null, {
																						default: withCtx(() => [createTextVNode("Form Good")]),
																						_: 1
																					}),
																					createVNode(VCardText, null, {
																						default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																							default: withCtx(() => [
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																										default: withCtx(() => [createVNode(VAutocomplete, {
																											items: unref(products),
																											"item-title": "rus",
																											"item-value": "id",
																											modelValue: unref(formGood).products,
																											"onUpdate:modelValue": ($event) => unref(formGood).products = $event,
																											label: "Products",
																											placeholder: "Выбери Product",
																											density: "compact",
																											variant: "outlined",
																											color: "red",
																											multiple: ""
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
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formGood).name,
																										"onUpdate:modelValue": ($event) => unref(formGood).name = $event,
																										label: "Good name",
																										variant: "outlined"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}),
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VFileInput, {
																										modelValue: unref(formGood).ava_image,
																										"onUpdate:modelValue": ($event) => unref(formGood).ava_image = $event,
																										label: "Good's avatar",
																										chips: ""
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								})
																							]),
																							_: 1
																						}, 8, ["onSubmit"])]),
																						_: 1
																					}),
																					createVNode(VCardActions, null, {
																						default: withCtx(() => [
																							createVNode(VSpacer),
																							createVNode(VDivider),
																							createVNode(VBtn, {
																								text: "save",
																								onClick: storeGood,
																								variant: "plain",
																								color: "indigo-darken-4"
																							})
																						]),
																						_: 1
																					})
																				];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		else return [createVNode(VCard, null, {
																			default: withCtx(() => [
																				createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Form Good")]),
																					_: 1
																				}),
																				createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																						default: withCtx(() => [
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																									default: withCtx(() => [createVNode(VAutocomplete, {
																										items: unref(products),
																										"item-title": "rus",
																										"item-value": "id",
																										modelValue: unref(formGood).products,
																										"onUpdate:modelValue": ($event) => unref(formGood).products = $event,
																										label: "Products",
																										placeholder: "Выбери Product",
																										density: "compact",
																										variant: "outlined",
																										color: "red",
																										multiple: ""
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
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formGood).name,
																									"onUpdate:modelValue": ($event) => unref(formGood).name = $event,
																									label: "Good name",
																									variant: "outlined"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							}),
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VFileInput, {
																									modelValue: unref(formGood).ava_image,
																									"onUpdate:modelValue": ($event) => unref(formGood).ava_image = $event,
																									label: "Good's avatar",
																									chips: ""
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							})
																						]),
																						_: 1
																					}, 8, ["onSubmit"])]),
																					_: 1
																				}),
																				createVNode(VCardActions, null, {
																					default: withCtx(() => [
																						createVNode(VSpacer),
																						createVNode(VDivider),
																						createVNode(VBtn, {
																							text: "save",
																							onClick: storeGood,
																							variant: "plain",
																							color: "indigo-darken-4"
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
																else return [createVNode(VDialog, {
																	transition: "dialog-top-transition",
																	width: "900"
																}, {
																	activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
																		text: "Новый товар",
																		block: ""
																	}), null, 16)]),
																	default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																		default: withCtx(() => [
																			createVNode(VCardTitle, null, {
																				default: withCtx(() => [createTextVNode("Form Good")]),
																				_: 1
																			}),
																			createVNode(VCardText, null, {
																				default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																					default: withCtx(() => [
																						createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																								default: withCtx(() => [createVNode(VAutocomplete, {
																									items: unref(products),
																									"item-title": "rus",
																									"item-value": "id",
																									modelValue: unref(formGood).products,
																									"onUpdate:modelValue": ($event) => unref(formGood).products = $event,
																									label: "Products",
																									placeholder: "Выбери Product",
																									density: "compact",
																									variant: "outlined",
																									color: "red",
																									multiple: ""
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
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formGood).name,
																								"onUpdate:modelValue": ($event) => unref(formGood).name = $event,
																								label: "Good name",
																								variant: "outlined"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}),
																						createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VFileInput, {
																								modelValue: unref(formGood).ava_image,
																								"onUpdate:modelValue": ($event) => unref(formGood).ava_image = $event,
																								label: "Good's avatar",
																								chips: ""
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						})
																					]),
																					_: 1
																				}, 8, ["onSubmit"])]),
																				_: 1
																			}),
																			createVNode(VCardActions, null, {
																				default: withCtx(() => [
																					createVNode(VSpacer),
																					createVNode(VDivider),
																					createVNode(VBtn, {
																						text: "save",
																						onClick: storeGood,
																						variant: "plain",
																						color: "indigo-darken-4"
																					})
																				]),
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
													} else return [createVNode(VCol, { cols: "9" }, {
														default: withCtx(() => [createVNode(VTextField, {
															label: "Поиск: Товары",
															modelValue: unref(searchGoods),
															"onUpdate:modelValue": ($event) => isRef(searchGoods) ? searchGoods.value = $event : searchGoods = $event,
															variant: "outlined",
															class: "mt-1 py-1"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}), createVNode(VCol, { cols: "3" }, {
														default: withCtx(() => [createVNode(VDialog, {
															transition: "dialog-top-transition",
															width: "900"
														}, {
															activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
																text: "Новый товар",
																block: ""
															}), null, 16)]),
															default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																default: withCtx(() => [
																	createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Form Good")]),
																		_: 1
																	}),
																	createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																			default: withCtx(() => [
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																						default: withCtx(() => [createVNode(VAutocomplete, {
																							items: unref(products),
																							"item-title": "rus",
																							"item-value": "id",
																							modelValue: unref(formGood).products,
																							"onUpdate:modelValue": ($event) => unref(formGood).products = $event,
																							label: "Products",
																							placeholder: "Выбери Product",
																							density: "compact",
																							variant: "outlined",
																							color: "red",
																							multiple: ""
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
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formGood).name,
																						"onUpdate:modelValue": ($event) => unref(formGood).name = $event,
																						label: "Good name",
																						variant: "outlined"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VFileInput, {
																						modelValue: unref(formGood).ava_image,
																						"onUpdate:modelValue": ($event) => unref(formGood).ava_image = $event,
																						label: "Good's avatar",
																						chips: ""
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				})
																			]),
																			_: 1
																		}, 8, ["onSubmit"])]),
																		_: 1
																	}),
																	createVNode(VCardActions, null, {
																		default: withCtx(() => [
																			createVNode(VSpacer),
																			createVNode(VDivider),
																			createVNode(VBtn, {
																				text: "save",
																				onClick: storeGood,
																				variant: "plain",
																				color: "indigo-darken-4"
																			})
																		]),
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
											_push(ssrRenderComponent(VRow, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VDataTable, {
														items: unref(goods),
														headers: headersGoods,
														search: unref(searchGoods),
														"items-per-page": "90",
														density: "compact",
														hover: "hover"
													}, {
														"item.ava_image": withCtx(({ item }, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VBadge, {
																content: item.id,
																color: "teal"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VImg, {
																		src: item.ava_image || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
																		onClick: ($event) => fetchGood(item.id),
																		alt: "Avatar",
																		width: "50",
																		height: "50",
																		cover: "",
																		class: "rounded"
																	}, null, _parent, _scopeId));
																	else return [createVNode(VImg, {
																		src: item.ava_image || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
																		onClick: ($event) => fetchGood(item.id),
																		alt: "Avatar",
																		width: "50",
																		height: "50",
																		cover: "",
																		class: "rounded"
																	}, null, 8, ["src", "onClick"])];
																}),
																_: 2
															}, _parent, _scopeId));
															else return [createVNode(VBadge, {
																content: item.id,
																color: "teal"
															}, {
																default: withCtx(() => [createVNode(VImg, {
																	src: item.ava_image || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
																	onClick: ($event) => fetchGood(item.id),
																	alt: "Avatar",
																	width: "50",
																	height: "50",
																	cover: "",
																	class: "rounded"
																}, null, 8, ["src", "onClick"])]),
																_: 2
															}, 1032, ["content"])];
														}),
														"item.name": withCtx(({ item }, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(unref(Link), {
																href: unref(route)("Ameise.good.show", {
																	id: item.id,
																	slug: item.slug || generateSlug(item.name)
																}),
																class: "text-decoration-none"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`${ssrInterpolate(item.name)}`);
																	else return [createTextVNode(toDisplayString(item.name), 1)];
																}),
																_: 2
															}, _parent, _scopeId));
															else return [createVNode(unref(Link), {
																href: unref(route)("Ameise.good.show", {
																	id: item.id,
																	slug: item.slug || generateSlug(item.name)
																}),
																class: "text-decoration-none"
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
																_: 2
															}, 1032, ["href"])];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(VDataTable, {
														items: unref(goods),
														headers: headersGoods,
														search: unref(searchGoods),
														"items-per-page": "90",
														density: "compact",
														hover: "hover"
													}, {
														"item.ava_image": withCtx(({ item }) => [createVNode(VBadge, {
															content: item.id,
															color: "teal"
														}, {
															default: withCtx(() => [createVNode(VImg, {
																src: item.ava_image || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
																onClick: ($event) => fetchGood(item.id),
																alt: "Avatar",
																width: "50",
																height: "50",
																cover: "",
																class: "rounded"
															}, null, 8, ["src", "onClick"])]),
															_: 2
														}, 1032, ["content"])]),
														"item.name": withCtx(({ item }) => [createVNode(unref(Link), {
															href: unref(route)("Ameise.good.show", {
																id: item.id,
																slug: item.slug || generateSlug(item.name)
															}),
															class: "text-decoration-none"
														}, {
															default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
															_: 2
														}, 1032, ["href"])]),
														_: 1
													}, 8, ["items", "search"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
												default: withCtx(() => [createVNode(VTextField, {
													label: "Поиск: Товары",
													modelValue: unref(searchGoods),
													"onUpdate:modelValue": ($event) => isRef(searchGoods) ? searchGoods.value = $event : searchGoods = $event,
													variant: "outlined",
													class: "mt-1 py-1"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}), createVNode(VCol, { cols: "3" }, {
												default: withCtx(() => [createVNode(VDialog, {
													transition: "dialog-top-transition",
													width: "900"
												}, {
													activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
														text: "Новый товар",
														block: ""
													}), null, 16)]),
													default: withCtx(({ isActive }) => [createVNode(VCard, null, {
														default: withCtx(() => [
															createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Form Good")]),
																_: 1
															}),
															createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																	default: withCtx(() => [
																		createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																				default: withCtx(() => [createVNode(VAutocomplete, {
																					items: unref(products),
																					"item-title": "rus",
																					"item-value": "id",
																					modelValue: unref(formGood).products,
																					"onUpdate:modelValue": ($event) => unref(formGood).products = $event,
																					label: "Products",
																					placeholder: "Выбери Product",
																					density: "compact",
																					variant: "outlined",
																					color: "red",
																					multiple: ""
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
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formGood).name,
																				"onUpdate:modelValue": ($event) => unref(formGood).name = $event,
																				label: "Good name",
																				variant: "outlined"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VFileInput, {
																				modelValue: unref(formGood).ava_image,
																				"onUpdate:modelValue": ($event) => unref(formGood).ava_image = $event,
																				label: "Good's avatar",
																				chips: ""
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		})
																	]),
																	_: 1
																}, 8, ["onSubmit"])]),
																_: 1
															}),
															createVNode(VCardActions, null, {
																default: withCtx(() => [
																	createVNode(VSpacer),
																	createVNode(VDivider),
																	createVNode(VBtn, {
																		text: "save",
																		onClick: storeGood,
																		variant: "plain",
																		color: "indigo-darken-4"
																	})
																]),
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
										}), createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VDataTable, {
												items: unref(goods),
												headers: headersGoods,
												search: unref(searchGoods),
												"items-per-page": "90",
												density: "compact",
												hover: "hover"
											}, {
												"item.ava_image": withCtx(({ item }) => [createVNode(VBadge, {
													content: item.id,
													color: "teal"
												}, {
													default: withCtx(() => [createVNode(VImg, {
														src: item.ava_image || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
														onClick: ($event) => fetchGood(item.id),
														alt: "Avatar",
														width: "50",
														height: "50",
														cover: "",
														class: "rounded"
													}, null, 8, ["src", "onClick"])]),
													_: 2
												}, 1032, ["content"])]),
												"item.name": withCtx(({ item }) => [createVNode(unref(Link), {
													href: unref(route)("Ameise.good.show", {
														id: item.id,
														slug: item.slug || generateSlug(item.name)
													}),
													class: "text-decoration-none"
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
													_: 2
												}, 1032, ["href"])]),
												_: 1
											}, 8, ["items", "search"])]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCol, { lg: "3" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VSheet, null, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VRow, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VCol, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) if (good.value && good.value.name) _push(`<span data-v-aa029060${_scopeId}>${ssrInterpolate(good.value.name)}</span>`);
																else _push(`<span data-v-aa029060${_scopeId}>Загрузка...</span>`);
																else return [good.value && good.value.name ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(good.value.name), 1)) : (openBlock(), createBlock("span", { key: 1 }, "Загрузка..."))];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VCol, null, {
															default: withCtx(() => [good.value && good.value.name ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(good.value.name), 1)) : (openBlock(), createBlock("span", { key: 1 }, "Загрузка..."))]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												else return [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, null, {
														default: withCtx(() => [good.value && good.value.name ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(good.value.name), 1)) : (openBlock(), createBlock("span", { key: 1 }, "Загрузка..."))]),
														_: 1
													})]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VSheet, null, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, null, {
													default: withCtx(() => [good.value && good.value.name ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(good.value.name), 1)) : (openBlock(), createBlock("span", { key: 1 }, "Загрузка..."))]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [createVNode(VCol, null, {
								default: withCtx(() => [createVNode(VRow, null, {
									default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
										default: withCtx(() => [createVNode(VTextField, {
											label: "Поиск: Товары",
											modelValue: unref(searchGoods),
											"onUpdate:modelValue": ($event) => isRef(searchGoods) ? searchGoods.value = $event : searchGoods = $event,
											variant: "outlined",
											class: "mt-1 py-1"
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}), createVNode(VCol, { cols: "3" }, {
										default: withCtx(() => [createVNode(VDialog, {
											transition: "dialog-top-transition",
											width: "900"
										}, {
											activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
												text: "Новый товар",
												block: ""
											}), null, 16)]),
											default: withCtx(({ isActive }) => [createVNode(VCard, null, {
												default: withCtx(() => [
													createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Form Good")]),
														_: 1
													}),
													createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
															default: withCtx(() => [
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																		default: withCtx(() => [createVNode(VAutocomplete, {
																			items: unref(products),
																			"item-title": "rus",
																			"item-value": "id",
																			modelValue: unref(formGood).products,
																			"onUpdate:modelValue": ($event) => unref(formGood).products = $event,
																			label: "Products",
																			placeholder: "Выбери Product",
																			density: "compact",
																			variant: "outlined",
																			color: "red",
																			multiple: ""
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
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formGood).name,
																		"onUpdate:modelValue": ($event) => unref(formGood).name = $event,
																		label: "Good name",
																		variant: "outlined"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VFileInput, {
																		modelValue: unref(formGood).ava_image,
																		"onUpdate:modelValue": ($event) => unref(formGood).ava_image = $event,
																		label: "Good's avatar",
																		chips: ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																})
															]),
															_: 1
														}, 8, ["onSubmit"])]),
														_: 1
													}),
													createVNode(VCardActions, null, {
														default: withCtx(() => [
															createVNode(VSpacer),
															createVNode(VDivider),
															createVNode(VBtn, {
																text: "save",
																onClick: storeGood,
																variant: "plain",
																color: "indigo-darken-4"
															})
														]),
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
								}), createVNode(VRow, null, {
									default: withCtx(() => [createVNode(VDataTable, {
										items: unref(goods),
										headers: headersGoods,
										search: unref(searchGoods),
										"items-per-page": "90",
										density: "compact",
										hover: "hover"
									}, {
										"item.ava_image": withCtx(({ item }) => [createVNode(VBadge, {
											content: item.id,
											color: "teal"
										}, {
											default: withCtx(() => [createVNode(VImg, {
												src: item.ava_image || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
												onClick: ($event) => fetchGood(item.id),
												alt: "Avatar",
												width: "50",
												height: "50",
												cover: "",
												class: "rounded"
											}, null, 8, ["src", "onClick"])]),
											_: 2
										}, 1032, ["content"])]),
										"item.name": withCtx(({ item }) => [createVNode(unref(Link), {
											href: unref(route)("Ameise.good.show", {
												id: item.id,
												slug: item.slug || generateSlug(item.name)
											}),
											class: "text-decoration-none"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
											_: 2
										}, 1032, ["href"])]),
										_: 1
									}, 8, ["items", "search"])]),
									_: 1
								})]),
								_: 1
							}), createVNode(VCol, { lg: "3" }, {
								default: withCtx(() => [createVNode(VSheet, null, {
									default: withCtx(() => [createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, null, {
											default: withCtx(() => [good.value && good.value.name ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(good.value.name), 1)) : (openBlock(), createBlock("span", { key: 1 }, "Загрузка..."))]),
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
					else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, null, {
							default: withCtx(() => [createVNode(VRow, null, {
								default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
									default: withCtx(() => [createVNode(VTextField, {
										label: "Поиск: Товары",
										modelValue: unref(searchGoods),
										"onUpdate:modelValue": ($event) => isRef(searchGoods) ? searchGoods.value = $event : searchGoods = $event,
										variant: "outlined",
										class: "mt-1 py-1"
									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
									_: 1
								}), createVNode(VCol, { cols: "3" }, {
									default: withCtx(() => [createVNode(VDialog, {
										transition: "dialog-top-transition",
										width: "900"
									}, {
										activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
											text: "Новый товар",
											block: ""
										}), null, 16)]),
										default: withCtx(({ isActive }) => [createVNode(VCard, null, {
											default: withCtx(() => [
												createVNode(VCardTitle, null, {
													default: withCtx(() => [createTextVNode("Form Good")]),
													_: 1
												}),
												createVNode(VCardText, null, {
													default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
														default: withCtx(() => [
															createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
																	default: withCtx(() => [createVNode(VAutocomplete, {
																		items: unref(products),
																		"item-title": "rus",
																		"item-value": "id",
																		modelValue: unref(formGood).products,
																		"onUpdate:modelValue": ($event) => unref(formGood).products = $event,
																		label: "Products",
																		placeholder: "Выбери Product",
																		density: "compact",
																		variant: "outlined",
																		color: "red",
																		multiple: ""
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
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(formGood).name,
																	"onUpdate:modelValue": ($event) => unref(formGood).name = $event,
																	label: "Good name",
																	variant: "outlined"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VFileInput, {
																	modelValue: unref(formGood).ava_image,
																	"onUpdate:modelValue": ($event) => unref(formGood).ava_image = $event,
																	label: "Good's avatar",
																	chips: ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})
														]),
														_: 1
													}, 8, ["onSubmit"])]),
													_: 1
												}),
												createVNode(VCardActions, null, {
													default: withCtx(() => [
														createVNode(VSpacer),
														createVNode(VDivider),
														createVNode(VBtn, {
															text: "save",
															onClick: storeGood,
															variant: "plain",
															color: "indigo-darken-4"
														})
													]),
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
							}), createVNode(VRow, null, {
								default: withCtx(() => [createVNode(VDataTable, {
									items: unref(goods),
									headers: headersGoods,
									search: unref(searchGoods),
									"items-per-page": "90",
									density: "compact",
									hover: "hover"
								}, {
									"item.ava_image": withCtx(({ item }) => [createVNode(VBadge, {
										content: item.id,
										color: "teal"
									}, {
										default: withCtx(() => [createVNode(VImg, {
											src: item.ava_image || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
											onClick: ($event) => fetchGood(item.id),
											alt: "Avatar",
											width: "50",
											height: "50",
											cover: "",
											class: "rounded"
										}, null, 8, ["src", "onClick"])]),
										_: 2
									}, 1032, ["content"])]),
									"item.name": withCtx(({ item }) => [createVNode(unref(Link), {
										href: unref(route)("Ameise.good.show", {
											id: item.id,
											slug: item.slug || generateSlug(item.name)
										}),
										class: "text-decoration-none"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
										_: 2
									}, 1032, ["href"])]),
									_: 1
								}, 8, ["items", "search"])]),
								_: 1
							})]),
							_: 1
						}), createVNode(VCol, { lg: "3" }, {
							default: withCtx(() => [createVNode(VSheet, null, {
								default: withCtx(() => [createVNode(VRow, null, {
									default: withCtx(() => [createVNode(VCol, null, {
										default: withCtx(() => [good.value && good.value.name ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(good.value.name), 1)) : (openBlock(), createBlock("span", { key: 1 }, "Загрузка..."))]),
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
			}, _parent));
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Goods.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var Goods_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-aa029060"]]);
//#endregion
export { Goods_default as default };

//# sourceMappingURL=Goods-Ci0azrOE.js.map