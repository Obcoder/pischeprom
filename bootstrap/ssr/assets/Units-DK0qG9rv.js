import { C as VRow, F as VCardText, G as VList, H as VSelect, I as VCardTitle, P as VCard, Q as VLabel, R as VCardActions, T as VContainer, U as VTextField, V as VAutocomplete, X as VChip, ct as VToolbar, h as VForm, j as VSheet, q as VListItem, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { Link, useForm } from "@inertiajs/vue3";
import { Fragment, createBlock, createCommentVNode, createTextVNode, createVNode, isRef, mergeProps, onMounted, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, vModelText, withCtx, withDirectives, withModifiers } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderAttr, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Ameise/Units.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Units",
	__ssrInlineRender: true,
	setup(__props) {
		const labels = ref([]);
		const units = ref([]);
		const uris = ref([]);
		let searchUnitsLike = ref("");
		let limitUnits = ref(135);
		function indexUnits(like, limit) {
			axios.get(route("units.index"), { params: {
				search: like,
				limit
			} }).then(function(response) {
				units.value = response.data;
			}).catch(function(error) {
				console.log();
			});
		}
		function indexUris() {
			axios.get(route("uris.index")).then(function(response) {
				uris.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let showFormUri = ref(false);
		function apiIndexLabels() {
			axios.get(route("labels.index")).then(function(response) {
				labels.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let listBuildings = ref();
		function apiIndexBuildings() {
			axios.get(route("buildings.index")).then(function(response) {
				listBuildings.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		const formatBuildingTitle = (building) => {
			if (!building) return "";
			return `${building.city?.name || " - "} , ${building.address}`;
		};
		const formUnit = useForm({
			name: null,
			uris: null,
			labels: null,
			buildings: null
		});
		function storeUnit() {
			formUnit.post(route("web.unit.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: false,
				onSuccess: () => {
					formUnit.reset();
					indexUnits(searchUnitsLike.value, 100);
				}
			});
		}
		const formUri = useForm({ address: null });
		function storeUri() {
			formUri.post(route("web.uri.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: false,
				onSuccess: () => {
					formUri.reset();
					indexUris();
				}
			});
		}
		onMounted(() => {
			indexUnits("", limitUnits.value);
			indexUris();
			apiIndexLabels();
			apiIndexBuildings();
		});
		useHead({
			title: `Units: ${units.value.length}`,
			meta: [{
				name: "description",
				content: `Все units in db`
			}]
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { cols: "7" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VTextField, {
												modelValue: unref(searchUnitsLike),
												"onUpdate:modelValue": ($event) => isRef(searchUnitsLike) ? searchUnitsLike.value = $event : searchUnitsLike = $event,
												onInput: ($event) => indexUnits(unref(searchUnitsLike), unref(limitUnits)),
												label: "Search",
												variant: "outlined",
												density: "compact"
											}, null, _parent, _scopeId));
											else return [createVNode(VTextField, {
												modelValue: unref(searchUnitsLike),
												"onUpdate:modelValue": ($event) => isRef(searchUnitsLike) ? searchUnitsLike.value = $event : searchUnitsLike = $event,
												onInput: ($event) => indexUnits(unref(searchUnitsLike), unref(limitUnits)),
												label: "Search",
												variant: "outlined",
												density: "compact"
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"onInput"
											])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VLabel, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`Limit`);
														else return [createTextVNode("Limit")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`<input type="number"${ssrRenderAttr("value", unref(limitUnits))}${_scopeId}>`);
											} else return [createVNode(VLabel, null, {
												default: withCtx(() => [createTextVNode("Limit")]),
												_: 1
											}), withDirectives(createVNode("input", {
												type: "number",
												"onUpdate:modelValue": ($event) => isRef(limitUnits) ? limitUnits.value = $event : limitUnits = $event,
												onChange: ($event) => indexUnits(unref(searchUnitsLike), unref(limitUnits))
											}, null, 40, ["onUpdate:modelValue", "onChange"]), [[vModelText, unref(limitUnits)]])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "2" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VDialog, {
												transition: "dialog-top-transition",
												width: "990"
											}, {
												activator: withCtx(({ props: activatorProps }, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VBtn, mergeProps(activatorProps, {
														text: "Новый Unit",
														block: "",
														variant: "elevated",
														color: "teal-lighten-3"
													}), null, _parent, _scopeId));
													else return [createVNode(VBtn, mergeProps(activatorProps, {
														text: "Новый Unit",
														block: "",
														variant: "elevated",
														color: "teal-lighten-3"
													}), null, 16)];
												}),
												default: withCtx(({ isActive }, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VCard, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(ssrRenderComponent(VCardTitle, null, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(`Form Unit`);
																		else return [createTextVNode("Form Unit")];
																	}),
																	_: 2
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCardText, { class: "text-h2 pa-12" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VForm, { onSubmit: () => {} }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VContainer, null, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) {
																							_push(ssrRenderComponent(VRow, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(ssrRenderComponent(VTextField, {
																										modelValue: unref(formUnit).name,
																										"onUpdate:modelValue": ($event) => unref(formUnit).name = $event,
																										label: "Name",
																										variant: "outlined"
																									}, null, _parent, _scopeId));
																									else return [createVNode(VTextField, {
																										modelValue: unref(formUnit).name,
																										"onUpdate:modelValue": ($event) => unref(formUnit).name = $event,
																										label: "Name",
																										variant: "outlined"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																								}),
																								_: 2
																							}, _parent, _scopeId));
																							_push(ssrRenderComponent(VRow, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) {
																										_push(ssrRenderComponent(VCol, { cols: "9" }, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(ssrRenderComponent(VAutocomplete, {
																													modelValue: unref(formUnit).uris,
																													"onUpdate:modelValue": ($event) => unref(formUnit).uris = $event,
																													items: uris.value,
																													"item-value": "id",
																													"item-title": "address",
																													label: "Uris selected",
																													chips: "",
																													multiple: ""
																												}, null, _parent, _scopeId));
																												else return [createVNode(VAutocomplete, {
																													modelValue: unref(formUnit).uris,
																													"onUpdate:modelValue": ($event) => unref(formUnit).uris = $event,
																													items: uris.value,
																													"item-value": "id",
																													"item-title": "address",
																													label: "Uris selected",
																													chips: "",
																													multiple: ""
																												}, null, 8, [
																													"modelValue",
																													"onUpdate:modelValue",
																													"items"
																												])];
																											}),
																											_: 2
																										}, _parent, _scopeId));
																										_push(ssrRenderComponent(VCol, { cols: "3" }, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) {
																													_push(ssrRenderComponent(VBtn, {
																														class: "my-2",
																														text: "+ uri",
																														onClick: ($event) => isRef(showFormUri) ? showFormUri.value = true : showFormUri = true
																													}, null, _parent, _scopeId));
																													_push(ssrRenderComponent(VDialog, {
																														modelValue: unref(showFormUri),
																														"onUpdate:modelValue": ($event) => isRef(showFormUri) ? showFormUri.value = $event : showFormUri = $event,
																														width: "501"
																													}, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(ssrRenderComponent(VCard, null, {
																																default: withCtx((_, _push, _parent, _scopeId) => {
																																	if (_push) {
																																		_push(ssrRenderComponent(VToolbar, { title: "FORM: Uri" }, null, _parent, _scopeId));
																																		_push(ssrRenderComponent(VCardText, null, {
																																			default: withCtx((_, _push, _parent, _scopeId) => {
																																				if (_push) _push(ssrRenderComponent(VForm, { onSubmit: () => {} }, {
																																					default: withCtx((_, _push, _parent, _scopeId) => {
																																						if (_push) {
																																							_push(ssrRenderComponent(VRow, null, {
																																								default: withCtx((_, _push, _parent, _scopeId) => {
																																									if (_push) _push(ssrRenderComponent(VTextField, {
																																										modelValue: unref(formUri).address,
																																										"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																																										label: "Uri address",
																																										variant: "outlined"
																																									}, null, _parent, _scopeId));
																																									else return [createVNode(VTextField, {
																																										modelValue: unref(formUri).address,
																																										"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																																										label: "Uri address",
																																										variant: "outlined"
																																									}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																																								}),
																																								_: 2
																																							}, _parent, _scopeId));
																																							_push(ssrRenderComponent(VRow, null, {
																																								default: withCtx((_, _push, _parent, _scopeId) => {
																																									if (_push) _push(ssrRenderComponent(VCol, { cols: "4" }, {
																																										default: withCtx((_, _push, _parent, _scopeId) => {
																																											if (_push) _push(ssrRenderComponent(VBtn, {
																																												text: "store",
																																												block: "",
																																												onClick: storeUri
																																											}, null, _parent, _scopeId));
																																											else return [createVNode(VBtn, {
																																												text: "store",
																																												block: "",
																																												onClick: storeUri
																																											})];
																																										}),
																																										_: 2
																																									}, _parent, _scopeId));
																																									else return [createVNode(VCol, { cols: "4" }, {
																																										default: withCtx(() => [createVNode(VBtn, {
																																											text: "store",
																																											block: "",
																																											onClick: storeUri
																																										})]),
																																										_: 1
																																									})];
																																								}),
																																								_: 2
																																							}, _parent, _scopeId));
																																						} else return [createVNode(VRow, null, {
																																							default: withCtx(() => [createVNode(VTextField, {
																																								modelValue: unref(formUri).address,
																																								"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																																								label: "Uri address",
																																								variant: "outlined"
																																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																							_: 1
																																						}), createVNode(VRow, null, {
																																							default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																																								default: withCtx(() => [createVNode(VBtn, {
																																									text: "store",
																																									block: "",
																																									onClick: storeUri
																																								})]),
																																								_: 1
																																							})]),
																																							_: 1
																																						})];
																																					}),
																																					_: 2
																																				}, _parent, _scopeId));
																																				else return [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																					default: withCtx(() => [createVNode(VRow, null, {
																																						default: withCtx(() => [createVNode(VTextField, {
																																							modelValue: unref(formUri).address,
																																							"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																																							label: "Uri address",
																																							variant: "outlined"
																																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																						_: 1
																																					}), createVNode(VRow, null, {
																																						default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																																							default: withCtx(() => [createVNode(VBtn, {
																																								text: "store",
																																								block: "",
																																								onClick: storeUri
																																							})]),
																																							_: 1
																																						})]),
																																						_: 1
																																					})]),
																																					_: 1
																																				}, 8, ["onSubmit"])];
																																			}),
																																			_: 2
																																		}, _parent, _scopeId));
																																	} else return [createVNode(VToolbar, { title: "FORM: Uri" }), createVNode(VCardText, null, {
																																		default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																			default: withCtx(() => [createVNode(VRow, null, {
																																				default: withCtx(() => [createVNode(VTextField, {
																																					modelValue: unref(formUri).address,
																																					"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																																					label: "Uri address",
																																					variant: "outlined"
																																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																				_: 1
																																			}), createVNode(VRow, null, {
																																				default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																																					default: withCtx(() => [createVNode(VBtn, {
																																						text: "store",
																																						block: "",
																																						onClick: storeUri
																																					})]),
																																					_: 1
																																				})]),
																																				_: 1
																																			})]),
																																			_: 1
																																		}, 8, ["onSubmit"])]),
																																		_: 1
																																	})];
																																}),
																																_: 2
																															}, _parent, _scopeId));
																															else return [createVNode(VCard, null, {
																																default: withCtx(() => [createVNode(VToolbar, { title: "FORM: Uri" }), createVNode(VCardText, null, {
																																	default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																		default: withCtx(() => [createVNode(VRow, null, {
																																			default: withCtx(() => [createVNode(VTextField, {
																																				modelValue: unref(formUri).address,
																																				"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																																				label: "Uri address",
																																				variant: "outlined"
																																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																			_: 1
																																		}), createVNode(VRow, null, {
																																			default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																																				default: withCtx(() => [createVNode(VBtn, {
																																					text: "store",
																																					block: "",
																																					onClick: storeUri
																																				})]),
																																				_: 1
																																			})]),
																																			_: 1
																																		})]),
																																		_: 1
																																	}, 8, ["onSubmit"])]),
																																	_: 1
																																})]),
																																_: 1
																															})];
																														}),
																														_: 2
																													}, _parent, _scopeId));
																												} else return [createVNode(VBtn, {
																													class: "my-2",
																													text: "+ uri",
																													onClick: ($event) => isRef(showFormUri) ? showFormUri.value = true : showFormUri = true
																												}, null, 8, ["onClick"]), createVNode(VDialog, {
																													modelValue: unref(showFormUri),
																													"onUpdate:modelValue": ($event) => isRef(showFormUri) ? showFormUri.value = $event : showFormUri = $event,
																													width: "501"
																												}, {
																													default: withCtx(() => [createVNode(VCard, null, {
																														default: withCtx(() => [createVNode(VToolbar, { title: "FORM: Uri" }), createVNode(VCardText, null, {
																															default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																default: withCtx(() => [createVNode(VRow, null, {
																																	default: withCtx(() => [createVNode(VTextField, {
																																		modelValue: unref(formUri).address,
																																		"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																																		label: "Uri address",
																																		variant: "outlined"
																																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																	_: 1
																																}), createVNode(VRow, null, {
																																	default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																																		default: withCtx(() => [createVNode(VBtn, {
																																			text: "store",
																																			block: "",
																																			onClick: storeUri
																																		})]),
																																		_: 1
																																	})]),
																																	_: 1
																																})]),
																																_: 1
																															}, 8, ["onSubmit"])]),
																															_: 1
																														})]),
																														_: 1
																													})]),
																													_: 1
																												}, 8, ["modelValue", "onUpdate:modelValue"])];
																											}),
																											_: 2
																										}, _parent, _scopeId));
																									} else return [createVNode(VCol, { cols: "9" }, {
																										default: withCtx(() => [createVNode(VAutocomplete, {
																											modelValue: unref(formUnit).uris,
																											"onUpdate:modelValue": ($event) => unref(formUnit).uris = $event,
																											items: uris.value,
																											"item-value": "id",
																											"item-title": "address",
																											label: "Uris selected",
																											chips: "",
																											multiple: ""
																										}, null, 8, [
																											"modelValue",
																											"onUpdate:modelValue",
																											"items"
																										])]),
																										_: 1
																									}), createVNode(VCol, { cols: "3" }, {
																										default: withCtx(() => [createVNode(VBtn, {
																											class: "my-2",
																											text: "+ uri",
																											onClick: ($event) => isRef(showFormUri) ? showFormUri.value = true : showFormUri = true
																										}, null, 8, ["onClick"]), createVNode(VDialog, {
																											modelValue: unref(showFormUri),
																											"onUpdate:modelValue": ($event) => isRef(showFormUri) ? showFormUri.value = $event : showFormUri = $event,
																											width: "501"
																										}, {
																											default: withCtx(() => [createVNode(VCard, null, {
																												default: withCtx(() => [createVNode(VToolbar, { title: "FORM: Uri" }), createVNode(VCardText, null, {
																													default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																														default: withCtx(() => [createVNode(VRow, null, {
																															default: withCtx(() => [createVNode(VTextField, {
																																modelValue: unref(formUri).address,
																																"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																																label: "Uri address",
																																variant: "outlined"
																															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																															_: 1
																														}), createVNode(VRow, null, {
																															default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																																default: withCtx(() => [createVNode(VBtn, {
																																	text: "store",
																																	block: "",
																																	onClick: storeUri
																																})]),
																																_: 1
																															})]),
																															_: 1
																														})]),
																														_: 1
																													}, 8, ["onSubmit"])]),
																													_: 1
																												})]),
																												_: 1
																											})]),
																											_: 1
																										}, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									})];
																								}),
																								_: 2
																							}, _parent, _scopeId));
																							_push(ssrRenderComponent(VRow, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(ssrRenderComponent(VCol, { cols: "4" }, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(ssrRenderComponent(VSelect, {
																												modelValue: unref(formUnit).labels,
																												"onUpdate:modelValue": ($event) => unref(formUnit).labels = $event,
																												items: labels.value,
																												"item-value": "id",
																												"item-title": "name",
																												label: "Labels",
																												multiple: ""
																											}, null, _parent, _scopeId));
																											else return [createVNode(VSelect, {
																												modelValue: unref(formUnit).labels,
																												"onUpdate:modelValue": ($event) => unref(formUnit).labels = $event,
																												items: labels.value,
																												"item-value": "id",
																												"item-title": "name",
																												label: "Labels",
																												multiple: ""
																											}, null, 8, [
																												"modelValue",
																												"onUpdate:modelValue",
																												"items"
																											])];
																										}),
																										_: 2
																									}, _parent, _scopeId));
																									else return [createVNode(VCol, { cols: "4" }, {
																										default: withCtx(() => [createVNode(VSelect, {
																											modelValue: unref(formUnit).labels,
																											"onUpdate:modelValue": ($event) => unref(formUnit).labels = $event,
																											items: labels.value,
																											"item-value": "id",
																											"item-title": "name",
																											label: "Labels",
																											multiple: ""
																										}, null, 8, [
																											"modelValue",
																											"onUpdate:modelValue",
																											"items"
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
																											if (_push) _push(ssrRenderComponent(VAutocomplete, {
																												modelValue: unref(formUnit).buildings,
																												"onUpdate:modelValue": ($event) => unref(formUnit).buildings = $event,
																												items: unref(listBuildings),
																												"item-title": formatBuildingTitle,
																												"item-value": "id",
																												label: "Buildings",
																												color: "blue",
																												multiple: "",
																												chips: ""
																											}, null, _parent, _scopeId));
																											else return [createVNode(VAutocomplete, {
																												modelValue: unref(formUnit).buildings,
																												"onUpdate:modelValue": ($event) => unref(formUnit).buildings = $event,
																												items: unref(listBuildings),
																												"item-title": formatBuildingTitle,
																												"item-value": "id",
																												label: "Buildings",
																												color: "blue",
																												multiple: "",
																												chips: ""
																											}, null, 8, [
																												"modelValue",
																												"onUpdate:modelValue",
																												"items"
																											])];
																										}),
																										_: 2
																									}, _parent, _scopeId));
																									else return [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VAutocomplete, {
																											modelValue: unref(formUnit).buildings,
																											"onUpdate:modelValue": ($event) => unref(formUnit).buildings = $event,
																											items: unref(listBuildings),
																											"item-title": formatBuildingTitle,
																											"item-value": "id",
																											label: "Buildings",
																											color: "blue",
																											multiple: "",
																											chips: ""
																										}, null, 8, [
																											"modelValue",
																											"onUpdate:modelValue",
																											"items"
																										])]),
																										_: 1
																									})];
																								}),
																								_: 2
																							}, _parent, _scopeId));
																						} else return [
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formUnit).name,
																									"onUpdate:modelValue": ($event) => unref(formUnit).name = $event,
																									label: "Name",
																									variant: "outlined"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							}),
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																									default: withCtx(() => [createVNode(VAutocomplete, {
																										modelValue: unref(formUnit).uris,
																										"onUpdate:modelValue": ($event) => unref(formUnit).uris = $event,
																										items: uris.value,
																										"item-value": "id",
																										"item-title": "address",
																										label: "Uris selected",
																										chips: "",
																										multiple: ""
																									}, null, 8, [
																										"modelValue",
																										"onUpdate:modelValue",
																										"items"
																									])]),
																									_: 1
																								}), createVNode(VCol, { cols: "3" }, {
																									default: withCtx(() => [createVNode(VBtn, {
																										class: "my-2",
																										text: "+ uri",
																										onClick: ($event) => isRef(showFormUri) ? showFormUri.value = true : showFormUri = true
																									}, null, 8, ["onClick"]), createVNode(VDialog, {
																										modelValue: unref(showFormUri),
																										"onUpdate:modelValue": ($event) => isRef(showFormUri) ? showFormUri.value = $event : showFormUri = $event,
																										width: "501"
																									}, {
																										default: withCtx(() => [createVNode(VCard, null, {
																											default: withCtx(() => [createVNode(VToolbar, { title: "FORM: Uri" }), createVNode(VCardText, null, {
																												default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																													default: withCtx(() => [createVNode(VRow, null, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(formUri).address,
																															"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																															label: "Uri address",
																															variant: "outlined"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																														_: 1
																													}), createVNode(VRow, null, {
																														default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																															default: withCtx(() => [createVNode(VBtn, {
																																text: "store",
																																block: "",
																																onClick: storeUri
																															})]),
																															_: 1
																														})]),
																														_: 1
																													})]),
																													_: 1
																												}, 8, ["onSubmit"])]),
																												_: 1
																											})]),
																											_: 1
																										})]),
																										_: 1
																									}, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								})]),
																								_: 1
																							}),
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																									default: withCtx(() => [createVNode(VSelect, {
																										modelValue: unref(formUnit).labels,
																										"onUpdate:modelValue": ($event) => unref(formUnit).labels = $event,
																										items: labels.value,
																										"item-value": "id",
																										"item-title": "name",
																										label: "Labels",
																										multiple: ""
																									}, null, 8, [
																										"modelValue",
																										"onUpdate:modelValue",
																										"items"
																									])]),
																									_: 1
																								})]),
																								_: 1
																							}),
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VAutocomplete, {
																										modelValue: unref(formUnit).buildings,
																										"onUpdate:modelValue": ($event) => unref(formUnit).buildings = $event,
																										items: unref(listBuildings),
																										"item-title": formatBuildingTitle,
																										"item-value": "id",
																										label: "Buildings",
																										color: "blue",
																										multiple: "",
																										chips: ""
																									}, null, 8, [
																										"modelValue",
																										"onUpdate:modelValue",
																										"items"
																									])]),
																									_: 1
																								})]),
																								_: 1
																							})
																						];
																					}),
																					_: 2
																				}, _parent, _scopeId));
																				else return [createVNode(VContainer, null, {
																					default: withCtx(() => [
																						createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formUnit).name,
																								"onUpdate:modelValue": ($event) => unref(formUnit).name = $event,
																								label: "Name",
																								variant: "outlined"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}),
																						createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																								default: withCtx(() => [createVNode(VAutocomplete, {
																									modelValue: unref(formUnit).uris,
																									"onUpdate:modelValue": ($event) => unref(formUnit).uris = $event,
																									items: uris.value,
																									"item-value": "id",
																									"item-title": "address",
																									label: "Uris selected",
																									chips: "",
																									multiple: ""
																								}, null, 8, [
																									"modelValue",
																									"onUpdate:modelValue",
																									"items"
																								])]),
																								_: 1
																							}), createVNode(VCol, { cols: "3" }, {
																								default: withCtx(() => [createVNode(VBtn, {
																									class: "my-2",
																									text: "+ uri",
																									onClick: ($event) => isRef(showFormUri) ? showFormUri.value = true : showFormUri = true
																								}, null, 8, ["onClick"]), createVNode(VDialog, {
																									modelValue: unref(showFormUri),
																									"onUpdate:modelValue": ($event) => isRef(showFormUri) ? showFormUri.value = $event : showFormUri = $event,
																									width: "501"
																								}, {
																									default: withCtx(() => [createVNode(VCard, null, {
																										default: withCtx(() => [createVNode(VToolbar, { title: "FORM: Uri" }), createVNode(VCardText, null, {
																											default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																												default: withCtx(() => [createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formUri).address,
																														"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																														label: "Uri address",
																														variant: "outlined"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												}), createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																														default: withCtx(() => [createVNode(VBtn, {
																															text: "store",
																															block: "",
																															onClick: storeUri
																														})]),
																														_: 1
																													})]),
																													_: 1
																												})]),
																												_: 1
																											}, 8, ["onSubmit"])]),
																											_: 1
																										})]),
																										_: 1
																									})]),
																									_: 1
																								}, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							})]),
																							_: 1
																						}),
																						createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																								default: withCtx(() => [createVNode(VSelect, {
																									modelValue: unref(formUnit).labels,
																									"onUpdate:modelValue": ($event) => unref(formUnit).labels = $event,
																									items: labels.value,
																									"item-value": "id",
																									"item-title": "name",
																									label: "Labels",
																									multiple: ""
																								}, null, 8, [
																									"modelValue",
																									"onUpdate:modelValue",
																									"items"
																								])]),
																								_: 1
																							})]),
																							_: 1
																						}),
																						createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VAutocomplete, {
																									modelValue: unref(formUnit).buildings,
																									"onUpdate:modelValue": ($event) => unref(formUnit).buildings = $event,
																									items: unref(listBuildings),
																									"item-title": formatBuildingTitle,
																									"item-value": "id",
																									label: "Buildings",
																									color: "blue",
																									multiple: "",
																									chips: ""
																								}, null, 8, [
																									"modelValue",
																									"onUpdate:modelValue",
																									"items"
																								])]),
																								_: 1
																							})]),
																							_: 1
																						})
																					]),
																					_: 1
																				})];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		else return [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																			default: withCtx(() => [createVNode(VContainer, null, {
																				default: withCtx(() => [
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formUnit).name,
																							"onUpdate:modelValue": ($event) => unref(formUnit).name = $event,
																							label: "Name",
																							variant: "outlined"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																							default: withCtx(() => [createVNode(VAutocomplete, {
																								modelValue: unref(formUnit).uris,
																								"onUpdate:modelValue": ($event) => unref(formUnit).uris = $event,
																								items: uris.value,
																								"item-value": "id",
																								"item-title": "address",
																								label: "Uris selected",
																								chips: "",
																								multiple: ""
																							}, null, 8, [
																								"modelValue",
																								"onUpdate:modelValue",
																								"items"
																							])]),
																							_: 1
																						}), createVNode(VCol, { cols: "3" }, {
																							default: withCtx(() => [createVNode(VBtn, {
																								class: "my-2",
																								text: "+ uri",
																								onClick: ($event) => isRef(showFormUri) ? showFormUri.value = true : showFormUri = true
																							}, null, 8, ["onClick"]), createVNode(VDialog, {
																								modelValue: unref(showFormUri),
																								"onUpdate:modelValue": ($event) => isRef(showFormUri) ? showFormUri.value = $event : showFormUri = $event,
																								width: "501"
																							}, {
																								default: withCtx(() => [createVNode(VCard, null, {
																									default: withCtx(() => [createVNode(VToolbar, { title: "FORM: Uri" }), createVNode(VCardText, null, {
																										default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																											default: withCtx(() => [createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formUri).address,
																													"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																													label: "Uri address",
																													variant: "outlined"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											}), createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																													default: withCtx(() => [createVNode(VBtn, {
																														text: "store",
																														block: "",
																														onClick: storeUri
																													})]),
																													_: 1
																												})]),
																												_: 1
																											})]),
																											_: 1
																										}, 8, ["onSubmit"])]),
																										_: 1
																									})]),
																									_: 1
																								})]),
																								_: 1
																							}, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																							default: withCtx(() => [createVNode(VSelect, {
																								modelValue: unref(formUnit).labels,
																								"onUpdate:modelValue": ($event) => unref(formUnit).labels = $event,
																								items: labels.value,
																								"item-value": "id",
																								"item-title": "name",
																								label: "Labels",
																								multiple: ""
																							}, null, 8, [
																								"modelValue",
																								"onUpdate:modelValue",
																								"items"
																							])]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VAutocomplete, {
																								modelValue: unref(formUnit).buildings,
																								"onUpdate:modelValue": ($event) => unref(formUnit).buildings = $event,
																								items: unref(listBuildings),
																								"item-title": formatBuildingTitle,
																								"item-value": "id",
																								label: "Buildings",
																								color: "blue",
																								multiple: "",
																								chips: ""
																							}, null, 8, [
																								"modelValue",
																								"onUpdate:modelValue",
																								"items"
																							])]),
																							_: 1
																						})]),
																						_: 1
																					})
																				]),
																				_: 1
																			})]),
																			_: 1
																		}, 8, ["onSubmit"])];
																	}),
																	_: 2
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCardActions, { class: "justify-end" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(ssrRenderComponent(VBtn, {
																				text: "Close",
																				onClick: ($event) => isActive.value = false
																			}, null, _parent, _scopeId));
																			_push(ssrRenderComponent(VBtn, {
																				text: "Сохранить",
																				onClick: storeUnit
																			}, null, _parent, _scopeId));
																		} else return [createVNode(VBtn, {
																			text: "Close",
																			onClick: ($event) => isActive.value = false
																		}, null, 8, ["onClick"]), createVNode(VBtn, {
																			text: "Сохранить",
																			onClick: storeUnit
																		})];
																	}),
																	_: 2
																}, _parent, _scopeId));
															} else return [
																createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Form Unit")]),
																	_: 1
																}),
																createVNode(VCardText, { class: "text-h2 pa-12" }, {
																	default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																		default: withCtx(() => [createVNode(VContainer, null, {
																			default: withCtx(() => [
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formUnit).name,
																						"onUpdate:modelValue": ($event) => unref(formUnit).name = $event,
																						label: "Name",
																						variant: "outlined"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																						default: withCtx(() => [createVNode(VAutocomplete, {
																							modelValue: unref(formUnit).uris,
																							"onUpdate:modelValue": ($event) => unref(formUnit).uris = $event,
																							items: uris.value,
																							"item-value": "id",
																							"item-title": "address",
																							label: "Uris selected",
																							chips: "",
																							multiple: ""
																						}, null, 8, [
																							"modelValue",
																							"onUpdate:modelValue",
																							"items"
																						])]),
																						_: 1
																					}), createVNode(VCol, { cols: "3" }, {
																						default: withCtx(() => [createVNode(VBtn, {
																							class: "my-2",
																							text: "+ uri",
																							onClick: ($event) => isRef(showFormUri) ? showFormUri.value = true : showFormUri = true
																						}, null, 8, ["onClick"]), createVNode(VDialog, {
																							modelValue: unref(showFormUri),
																							"onUpdate:modelValue": ($event) => isRef(showFormUri) ? showFormUri.value = $event : showFormUri = $event,
																							width: "501"
																						}, {
																							default: withCtx(() => [createVNode(VCard, null, {
																								default: withCtx(() => [createVNode(VToolbar, { title: "FORM: Uri" }), createVNode(VCardText, null, {
																									default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																										default: withCtx(() => [createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formUri).address,
																												"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																												label: "Uri address",
																												variant: "outlined"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										}), createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																												default: withCtx(() => [createVNode(VBtn, {
																													text: "store",
																													block: "",
																													onClick: storeUri
																												})]),
																												_: 1
																											})]),
																											_: 1
																										})]),
																										_: 1
																									}, 8, ["onSubmit"])]),
																									_: 1
																								})]),
																								_: 1
																							})]),
																							_: 1
																						}, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																						default: withCtx(() => [createVNode(VSelect, {
																							modelValue: unref(formUnit).labels,
																							"onUpdate:modelValue": ($event) => unref(formUnit).labels = $event,
																							items: labels.value,
																							"item-value": "id",
																							"item-title": "name",
																							label: "Labels",
																							multiple: ""
																						}, null, 8, [
																							"modelValue",
																							"onUpdate:modelValue",
																							"items"
																						])]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VAutocomplete, {
																							modelValue: unref(formUnit).buildings,
																							"onUpdate:modelValue": ($event) => unref(formUnit).buildings = $event,
																							items: unref(listBuildings),
																							"item-title": formatBuildingTitle,
																							"item-value": "id",
																							label: "Buildings",
																							color: "blue",
																							multiple: "",
																							chips: ""
																						}, null, 8, [
																							"modelValue",
																							"onUpdate:modelValue",
																							"items"
																						])]),
																						_: 1
																					})]),
																					_: 1
																				})
																			]),
																			_: 1
																		})]),
																		_: 1
																	}, 8, ["onSubmit"])]),
																	_: 1
																}),
																createVNode(VCardActions, { class: "justify-end" }, {
																	default: withCtx(() => [createVNode(VBtn, {
																		text: "Close",
																		onClick: ($event) => isActive.value = false
																	}, null, 8, ["onClick"]), createVNode(VBtn, {
																		text: "Сохранить",
																		onClick: storeUnit
																	})]),
																	_: 2
																}, 1024)
															];
														}),
														_: 2
													}, _parent, _scopeId));
													else return [createVNode(VCard, null, {
														default: withCtx(() => [
															createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Form Unit")]),
																_: 1
															}),
															createVNode(VCardText, { class: "text-h2 pa-12" }, {
																default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																	default: withCtx(() => [createVNode(VContainer, null, {
																		default: withCtx(() => [
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formUnit).name,
																					"onUpdate:modelValue": ($event) => unref(formUnit).name = $event,
																					label: "Name",
																					variant: "outlined"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																					default: withCtx(() => [createVNode(VAutocomplete, {
																						modelValue: unref(formUnit).uris,
																						"onUpdate:modelValue": ($event) => unref(formUnit).uris = $event,
																						items: uris.value,
																						"item-value": "id",
																						"item-title": "address",
																						label: "Uris selected",
																						chips: "",
																						multiple: ""
																					}, null, 8, [
																						"modelValue",
																						"onUpdate:modelValue",
																						"items"
																					])]),
																					_: 1
																				}), createVNode(VCol, { cols: "3" }, {
																					default: withCtx(() => [createVNode(VBtn, {
																						class: "my-2",
																						text: "+ uri",
																						onClick: ($event) => isRef(showFormUri) ? showFormUri.value = true : showFormUri = true
																					}, null, 8, ["onClick"]), createVNode(VDialog, {
																						modelValue: unref(showFormUri),
																						"onUpdate:modelValue": ($event) => isRef(showFormUri) ? showFormUri.value = $event : showFormUri = $event,
																						width: "501"
																					}, {
																						default: withCtx(() => [createVNode(VCard, null, {
																							default: withCtx(() => [createVNode(VToolbar, { title: "FORM: Uri" }), createVNode(VCardText, null, {
																								default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																									default: withCtx(() => [createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formUri).address,
																											"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																											label: "Uri address",
																											variant: "outlined"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									}), createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																											default: withCtx(() => [createVNode(VBtn, {
																												text: "store",
																												block: "",
																												onClick: storeUri
																											})]),
																											_: 1
																										})]),
																										_: 1
																									})]),
																									_: 1
																								}, 8, ["onSubmit"])]),
																								_: 1
																							})]),
																							_: 1
																						})]),
																						_: 1
																					}, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				})]),
																				_: 1
																			}),
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																					default: withCtx(() => [createVNode(VSelect, {
																						modelValue: unref(formUnit).labels,
																						"onUpdate:modelValue": ($event) => unref(formUnit).labels = $event,
																						items: labels.value,
																						"item-value": "id",
																						"item-title": "name",
																						label: "Labels",
																						multiple: ""
																					}, null, 8, [
																						"modelValue",
																						"onUpdate:modelValue",
																						"items"
																					])]),
																					_: 1
																				})]),
																				_: 1
																			}),
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VAutocomplete, {
																						modelValue: unref(formUnit).buildings,
																						"onUpdate:modelValue": ($event) => unref(formUnit).buildings = $event,
																						items: unref(listBuildings),
																						"item-title": formatBuildingTitle,
																						"item-value": "id",
																						label: "Buildings",
																						color: "blue",
																						multiple: "",
																						chips: ""
																					}, null, 8, [
																						"modelValue",
																						"onUpdate:modelValue",
																						"items"
																					])]),
																					_: 1
																				})]),
																				_: 1
																			})
																		]),
																		_: 1
																	})]),
																	_: 1
																}, 8, ["onSubmit"])]),
																_: 1
															}),
															createVNode(VCardActions, { class: "justify-end" }, {
																default: withCtx(() => [createVNode(VBtn, {
																	text: "Close",
																	onClick: ($event) => isActive.value = false
																}, null, 8, ["onClick"]), createVNode(VBtn, {
																	text: "Сохранить",
																	onClick: storeUnit
																})]),
																_: 2
															}, 1024)
														]),
														_: 2
													}, 1024)];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VDialog, {
												transition: "dialog-top-transition",
												width: "990"
											}, {
												activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
													text: "Новый Unit",
													block: "",
													variant: "elevated",
													color: "teal-lighten-3"
												}), null, 16)]),
												default: withCtx(({ isActive }) => [createVNode(VCard, null, {
													default: withCtx(() => [
														createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Form Unit")]),
															_: 1
														}),
														createVNode(VCardText, { class: "text-h2 pa-12" }, {
															default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																default: withCtx(() => [createVNode(VContainer, null, {
																	default: withCtx(() => [
																		createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formUnit).name,
																				"onUpdate:modelValue": ($event) => unref(formUnit).name = $event,
																				label: "Name",
																				variant: "outlined"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																				default: withCtx(() => [createVNode(VAutocomplete, {
																					modelValue: unref(formUnit).uris,
																					"onUpdate:modelValue": ($event) => unref(formUnit).uris = $event,
																					items: uris.value,
																					"item-value": "id",
																					"item-title": "address",
																					label: "Uris selected",
																					chips: "",
																					multiple: ""
																				}, null, 8, [
																					"modelValue",
																					"onUpdate:modelValue",
																					"items"
																				])]),
																				_: 1
																			}), createVNode(VCol, { cols: "3" }, {
																				default: withCtx(() => [createVNode(VBtn, {
																					class: "my-2",
																					text: "+ uri",
																					onClick: ($event) => isRef(showFormUri) ? showFormUri.value = true : showFormUri = true
																				}, null, 8, ["onClick"]), createVNode(VDialog, {
																					modelValue: unref(showFormUri),
																					"onUpdate:modelValue": ($event) => isRef(showFormUri) ? showFormUri.value = $event : showFormUri = $event,
																					width: "501"
																				}, {
																					default: withCtx(() => [createVNode(VCard, null, {
																						default: withCtx(() => [createVNode(VToolbar, { title: "FORM: Uri" }), createVNode(VCardText, null, {
																							default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																								default: withCtx(() => [createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formUri).address,
																										"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																										label: "Uri address",
																										variant: "outlined"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}), createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																										default: withCtx(() => [createVNode(VBtn, {
																											text: "store",
																											block: "",
																											onClick: storeUri
																										})]),
																										_: 1
																									})]),
																									_: 1
																								})]),
																								_: 1
																							}, 8, ["onSubmit"])]),
																							_: 1
																						})]),
																						_: 1
																					})]),
																					_: 1
																				}, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			})]),
																			_: 1
																		}),
																		createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																				default: withCtx(() => [createVNode(VSelect, {
																					modelValue: unref(formUnit).labels,
																					"onUpdate:modelValue": ($event) => unref(formUnit).labels = $event,
																					items: labels.value,
																					"item-value": "id",
																					"item-title": "name",
																					label: "Labels",
																					multiple: ""
																				}, null, 8, [
																					"modelValue",
																					"onUpdate:modelValue",
																					"items"
																				])]),
																				_: 1
																			})]),
																			_: 1
																		}),
																		createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VAutocomplete, {
																					modelValue: unref(formUnit).buildings,
																					"onUpdate:modelValue": ($event) => unref(formUnit).buildings = $event,
																					items: unref(listBuildings),
																					"item-title": formatBuildingTitle,
																					"item-value": "id",
																					label: "Buildings",
																					color: "blue",
																					multiple: "",
																					chips: ""
																				}, null, 8, [
																					"modelValue",
																					"onUpdate:modelValue",
																					"items"
																				])]),
																				_: 1
																			})]),
																			_: 1
																		})
																	]),
																	_: 1
																})]),
																_: 1
															}, 8, ["onSubmit"])]),
															_: 1
														}),
														createVNode(VCardActions, { class: "justify-end" }, {
															default: withCtx(() => [createVNode(VBtn, {
																text: "Close",
																onClick: ($event) => isActive.value = false
															}, null, 8, ["onClick"]), createVNode(VBtn, {
																text: "Сохранить",
																onClick: storeUnit
															})]),
															_: 2
														}, 1024)
													]),
													_: 2
												}, 1024)]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VCol, { cols: "7" }, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: unref(searchUnitsLike),
											"onUpdate:modelValue": ($event) => isRef(searchUnitsLike) ? searchUnitsLike.value = $event : searchUnitsLike = $event,
											onInput: ($event) => indexUnits(unref(searchUnitsLike), unref(limitUnits)),
											label: "Search",
											variant: "outlined",
											density: "compact"
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"onInput"
										])]),
										_: 1
									}),
									createVNode(VCol, { cols: "3" }, {
										default: withCtx(() => [createVNode(VLabel, null, {
											default: withCtx(() => [createTextVNode("Limit")]),
											_: 1
										}), withDirectives(createVNode("input", {
											type: "number",
											"onUpdate:modelValue": ($event) => isRef(limitUnits) ? limitUnits.value = $event : limitUnits = $event,
											onChange: ($event) => indexUnits(unref(searchUnitsLike), unref(limitUnits))
										}, null, 40, ["onUpdate:modelValue", "onChange"]), [[vModelText, unref(limitUnits)]])]),
										_: 1
									}),
									createVNode(VCol, { cols: "2" }, {
										default: withCtx(() => [createVNode(VDialog, {
											transition: "dialog-top-transition",
											width: "990"
										}, {
											activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
												text: "Новый Unit",
												block: "",
												variant: "elevated",
												color: "teal-lighten-3"
											}), null, 16)]),
											default: withCtx(({ isActive }) => [createVNode(VCard, null, {
												default: withCtx(() => [
													createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Form Unit")]),
														_: 1
													}),
													createVNode(VCardText, { class: "text-h2 pa-12" }, {
														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
															default: withCtx(() => [createVNode(VContainer, null, {
																default: withCtx(() => [
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formUnit).name,
																			"onUpdate:modelValue": ($event) => unref(formUnit).name = $event,
																			label: "Name",
																			variant: "outlined"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																			default: withCtx(() => [createVNode(VAutocomplete, {
																				modelValue: unref(formUnit).uris,
																				"onUpdate:modelValue": ($event) => unref(formUnit).uris = $event,
																				items: uris.value,
																				"item-value": "id",
																				"item-title": "address",
																				label: "Uris selected",
																				chips: "",
																				multiple: ""
																			}, null, 8, [
																				"modelValue",
																				"onUpdate:modelValue",
																				"items"
																			])]),
																			_: 1
																		}), createVNode(VCol, { cols: "3" }, {
																			default: withCtx(() => [createVNode(VBtn, {
																				class: "my-2",
																				text: "+ uri",
																				onClick: ($event) => isRef(showFormUri) ? showFormUri.value = true : showFormUri = true
																			}, null, 8, ["onClick"]), createVNode(VDialog, {
																				modelValue: unref(showFormUri),
																				"onUpdate:modelValue": ($event) => isRef(showFormUri) ? showFormUri.value = $event : showFormUri = $event,
																				width: "501"
																			}, {
																				default: withCtx(() => [createVNode(VCard, null, {
																					default: withCtx(() => [createVNode(VToolbar, { title: "FORM: Uri" }), createVNode(VCardText, null, {
																						default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																							default: withCtx(() => [createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formUri).address,
																									"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																									label: "Uri address",
																									variant: "outlined"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							}), createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																									default: withCtx(() => [createVNode(VBtn, {
																										text: "store",
																										block: "",
																										onClick: storeUri
																									})]),
																									_: 1
																								})]),
																								_: 1
																							})]),
																							_: 1
																						}, 8, ["onSubmit"])]),
																						_: 1
																					})]),
																					_: 1
																				})]),
																				_: 1
																			}, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																			default: withCtx(() => [createVNode(VSelect, {
																				modelValue: unref(formUnit).labels,
																				"onUpdate:modelValue": ($event) => unref(formUnit).labels = $event,
																				items: labels.value,
																				"item-value": "id",
																				"item-title": "name",
																				label: "Labels",
																				multiple: ""
																			}, null, 8, [
																				"modelValue",
																				"onUpdate:modelValue",
																				"items"
																			])]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VAutocomplete, {
																				modelValue: unref(formUnit).buildings,
																				"onUpdate:modelValue": ($event) => unref(formUnit).buildings = $event,
																				items: unref(listBuildings),
																				"item-title": formatBuildingTitle,
																				"item-value": "id",
																				label: "Buildings",
																				color: "blue",
																				multiple: "",
																				chips: ""
																			}, null, 8, [
																				"modelValue",
																				"onUpdate:modelValue",
																				"items"
																			])]),
																			_: 1
																		})]),
																		_: 1
																	})
																]),
																_: 1
															})]),
															_: 1
														}, 8, ["onSubmit"])]),
														_: 1
													}),
													createVNode(VCardActions, { class: "justify-end" }, {
														default: withCtx(() => [createVNode(VBtn, {
															text: "Close",
															onClick: ($event) => isActive.value = false
														}, null, 8, ["onClick"]), createVNode(VBtn, {
															text: "Сохранить",
															onClick: storeUnit
														})]),
														_: 2
													}, 1024)
												]),
												_: 2
											}, 1024)]),
											_: 1
										})]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { cols: "4" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VList, { lines: "two" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(`<!--[-->`);
														ssrRenderList(units.value, (unit) => {
															_push(ssrRenderComponent(VListItem, {
																key: unit.id,
																class: "border-emerald-900",
																elevation: "0",
																density: "comfortable",
																border: "",
																rounded: ""
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VRow, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VCol, null, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(ssrRenderComponent(unref(Link), {
																							href: unref(route)("web.unit.show", unit.id),
																							class: "font-RubikMedium"
																						}, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(`<span${_scopeId}>${ssrInterpolate(unit.name)}</span>`);
																								else return [createVNode("span", null, toDisplayString(unit.name), 1)];
																							}),
																							_: 2
																						}, _parent, _scopeId));
																						else return [createVNode(unref(Link), {
																							href: unref(route)("web.unit.show", unit.id),
																							class: "font-RubikMedium"
																						}, {
																							default: withCtx(() => [createVNode("span", null, toDisplayString(unit.name), 1)]),
																							_: 2
																						}, 1032, ["href"])];
																					}),
																					_: 2
																				}, _parent, _scopeId));
																				else return [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(unref(Link), {
																						href: unref(route)("web.unit.show", unit.id),
																						class: "font-RubikMedium"
																					}, {
																						default: withCtx(() => [createVNode("span", null, toDisplayString(unit.name), 1)]),
																						_: 2
																					}, 1032, ["href"])]),
																					_: 2
																				}, 1024)];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VRow, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(ssrRenderComponent(VCol, { cols: "8" }, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) {
																								_push(`<!--[-->`);
																								ssrRenderList(unit.labels, (label) => {
																									_push(`<div class="text-xs"${_scopeId}>${ssrInterpolate(label.name)}</div>`);
																								});
																								_push(`<!--]-->`);
																							} else return [(openBlock(true), createBlock(Fragment, null, renderList(unit.labels, (label) => {
																								return openBlock(), createBlock("div", {
																									key: label.id,
																									class: "text-xs"
																								}, toDisplayString(label.name), 1);
																							}), 128))];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCol, { cols: "4" }, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) if (unit.entities.length > 0) _push(ssrRenderComponent(VChip, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(`${ssrInterpolate(unit.entities.length)}`);
																									else return [createTextVNode(toDisplayString(unit.entities.length), 1)];
																								}),
																								_: 2
																							}, _parent, _scopeId));
																							else _push(`<!---->`);
																							else return [unit.entities.length > 0 ? (openBlock(), createBlock(VChip, { key: 0 }, {
																								default: withCtx(() => [createTextVNode(toDisplayString(unit.entities.length), 1)]),
																								_: 2
																							}, 1024)) : createCommentVNode("", true)];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																				} else return [createVNode(VCol, { cols: "8" }, {
																					default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unit.labels, (label) => {
																						return openBlock(), createBlock("div", {
																							key: label.id,
																							class: "text-xs"
																						}, toDisplayString(label.name), 1);
																					}), 128))]),
																					_: 2
																				}, 1024), createVNode(VCol, { cols: "4" }, {
																					default: withCtx(() => [unit.entities.length > 0 ? (openBlock(), createBlock(VChip, { key: 0 }, {
																						default: withCtx(() => [createTextVNode(toDisplayString(unit.entities.length), 1)]),
																						_: 2
																					}, 1024)) : createCommentVNode("", true)]),
																					_: 2
																				}, 1024)];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																	} else return [createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(unref(Link), {
																				href: unref(route)("web.unit.show", unit.id),
																				class: "font-RubikMedium"
																			}, {
																				default: withCtx(() => [createVNode("span", null, toDisplayString(unit.name), 1)]),
																				_: 2
																			}, 1032, ["href"])]),
																			_: 2
																		}, 1024)]),
																		_: 2
																	}, 1024), createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																			default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unit.labels, (label) => {
																				return openBlock(), createBlock("div", {
																					key: label.id,
																					class: "text-xs"
																				}, toDisplayString(label.name), 1);
																			}), 128))]),
																			_: 2
																		}, 1024), createVNode(VCol, { cols: "4" }, {
																			default: withCtx(() => [unit.entities.length > 0 ? (openBlock(), createBlock(VChip, { key: 0 }, {
																				default: withCtx(() => [createTextVNode(toDisplayString(unit.entities.length), 1)]),
																				_: 2
																			}, 1024)) : createCommentVNode("", true)]),
																			_: 2
																		}, 1024)]),
																		_: 2
																	}, 1024)];
																}),
																_: 2
															}, _parent, _scopeId));
														});
														_push(`<!--]-->`);
													} else return [(openBlock(true), createBlock(Fragment, null, renderList(units.value, (unit) => {
														return openBlock(), createBlock(VListItem, {
															key: unit.id,
															class: "border-emerald-900",
															elevation: "0",
															density: "comfortable",
															border: "",
															rounded: ""
														}, {
															default: withCtx(() => [createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(unref(Link), {
																		href: unref(route)("web.unit.show", unit.id),
																		class: "font-RubikMedium"
																	}, {
																		default: withCtx(() => [createVNode("span", null, toDisplayString(unit.name), 1)]),
																		_: 2
																	}, 1032, ["href"])]),
																	_: 2
																}, 1024)]),
																_: 2
															}, 1024), createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unit.labels, (label) => {
																		return openBlock(), createBlock("div", {
																			key: label.id,
																			class: "text-xs"
																		}, toDisplayString(label.name), 1);
																	}), 128))]),
																	_: 2
																}, 1024), createVNode(VCol, { cols: "4" }, {
																	default: withCtx(() => [unit.entities.length > 0 ? (openBlock(), createBlock(VChip, { key: 0 }, {
																		default: withCtx(() => [createTextVNode(toDisplayString(unit.entities.length), 1)]),
																		_: 2
																	}, 1024)) : createCommentVNode("", true)]),
																	_: 2
																}, 1024)]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024);
													}), 128))];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VList, { lines: "two" }, {
												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(units.value, (unit) => {
													return openBlock(), createBlock(VListItem, {
														key: unit.id,
														class: "border-emerald-900",
														elevation: "0",
														density: "comfortable",
														border: "",
														rounded: ""
													}, {
														default: withCtx(() => [createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, null, {
																default: withCtx(() => [createVNode(unref(Link), {
																	href: unref(route)("web.unit.show", unit.id),
																	class: "font-RubikMedium"
																}, {
																	default: withCtx(() => [createVNode("span", null, toDisplayString(unit.name), 1)]),
																	_: 2
																}, 1032, ["href"])]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024), createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
																default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unit.labels, (label) => {
																	return openBlock(), createBlock("div", {
																		key: label.id,
																		class: "text-xs"
																	}, toDisplayString(label.name), 1);
																}), 128))]),
																_: 2
															}, 1024), createVNode(VCol, { cols: "4" }, {
																default: withCtx(() => [unit.entities.length > 0 ? (openBlock(), createBlock(VChip, { key: 0 }, {
																	default: withCtx(() => [createTextVNode(toDisplayString(unit.entities.length), 1)]),
																	_: 2
																}, 1024)) : createCommentVNode("", true)]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1024);
												}), 128))]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { lg: "4" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VSheet, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(`<!--[-->`);
														ssrRenderList(units.value, (unit) => {
															_push(`<div class="inline-block mr-2 p-1 text-xs"${_scopeId}>${ssrInterpolate(unit.name)}</div>`);
														});
														_push(`<!--]-->`);
													} else return [(openBlock(true), createBlock(Fragment, null, renderList(units.value, (unit) => {
														return openBlock(), createBlock("div", { class: "inline-block mr-2 p-1 text-xs" }, toDisplayString(unit.name), 1);
													}), 256))];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VSheet, null, {
												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(units.value, (unit) => {
													return openBlock(), createBlock("div", { class: "inline-block mr-2 p-1 text-xs" }, toDisplayString(unit.name), 1);
												}), 256))]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
								} else return [
									createVNode(VCol, { cols: "4" }, {
										default: withCtx(() => [createVNode(VList, { lines: "two" }, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(units.value, (unit) => {
												return openBlock(), createBlock(VListItem, {
													key: unit.id,
													class: "border-emerald-900",
													elevation: "0",
													density: "comfortable",
													border: "",
													rounded: ""
												}, {
													default: withCtx(() => [createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(unref(Link), {
																href: unref(route)("web.unit.show", unit.id),
																class: "font-RubikMedium"
															}, {
																default: withCtx(() => [createVNode("span", null, toDisplayString(unit.name), 1)]),
																_: 2
															}, 1032, ["href"])]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1024), createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unit.labels, (label) => {
																return openBlock(), createBlock("div", {
																	key: label.id,
																	class: "text-xs"
																}, toDisplayString(label.name), 1);
															}), 128))]),
															_: 2
														}, 1024), createVNode(VCol, { cols: "4" }, {
															default: withCtx(() => [unit.entities.length > 0 ? (openBlock(), createBlock(VChip, { key: 0 }, {
																default: withCtx(() => [createTextVNode(toDisplayString(unit.entities.length), 1)]),
																_: 2
															}, 1024)) : createCommentVNode("", true)]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1024)]),
													_: 2
												}, 1024);
											}), 128))]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCol, { lg: "4" }, {
										default: withCtx(() => [createVNode(VSheet, null, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(units.value, (unit) => {
												return openBlock(), createBlock("div", { class: "inline-block mr-2 p-1 text-xs" }, toDisplayString(unit.name), 1);
											}), 256))]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCol)
								];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol, { cols: "7" }, {
								default: withCtx(() => [createVNode(VTextField, {
									modelValue: unref(searchUnitsLike),
									"onUpdate:modelValue": ($event) => isRef(searchUnitsLike) ? searchUnitsLike.value = $event : searchUnitsLike = $event,
									onInput: ($event) => indexUnits(unref(searchUnitsLike), unref(limitUnits)),
									label: "Search",
									variant: "outlined",
									density: "compact"
								}, null, 8, [
									"modelValue",
									"onUpdate:modelValue",
									"onInput"
								])]),
								_: 1
							}),
							createVNode(VCol, { cols: "3" }, {
								default: withCtx(() => [createVNode(VLabel, null, {
									default: withCtx(() => [createTextVNode("Limit")]),
									_: 1
								}), withDirectives(createVNode("input", {
									type: "number",
									"onUpdate:modelValue": ($event) => isRef(limitUnits) ? limitUnits.value = $event : limitUnits = $event,
									onChange: ($event) => indexUnits(unref(searchUnitsLike), unref(limitUnits))
								}, null, 40, ["onUpdate:modelValue", "onChange"]), [[vModelText, unref(limitUnits)]])]),
								_: 1
							}),
							createVNode(VCol, { cols: "2" }, {
								default: withCtx(() => [createVNode(VDialog, {
									transition: "dialog-top-transition",
									width: "990"
								}, {
									activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
										text: "Новый Unit",
										block: "",
										variant: "elevated",
										color: "teal-lighten-3"
									}), null, 16)]),
									default: withCtx(({ isActive }) => [createVNode(VCard, null, {
										default: withCtx(() => [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Form Unit")]),
												_: 1
											}),
											createVNode(VCardText, { class: "text-h2 pa-12" }, {
												default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
													default: withCtx(() => [createVNode(VContainer, null, {
														default: withCtx(() => [
															createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(formUnit).name,
																	"onUpdate:modelValue": ($event) => unref(formUnit).name = $event,
																	label: "Name",
																	variant: "outlined"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																	default: withCtx(() => [createVNode(VAutocomplete, {
																		modelValue: unref(formUnit).uris,
																		"onUpdate:modelValue": ($event) => unref(formUnit).uris = $event,
																		items: uris.value,
																		"item-value": "id",
																		"item-title": "address",
																		label: "Uris selected",
																		chips: "",
																		multiple: ""
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items"
																	])]),
																	_: 1
																}), createVNode(VCol, { cols: "3" }, {
																	default: withCtx(() => [createVNode(VBtn, {
																		class: "my-2",
																		text: "+ uri",
																		onClick: ($event) => isRef(showFormUri) ? showFormUri.value = true : showFormUri = true
																	}, null, 8, ["onClick"]), createVNode(VDialog, {
																		modelValue: unref(showFormUri),
																		"onUpdate:modelValue": ($event) => isRef(showFormUri) ? showFormUri.value = $event : showFormUri = $event,
																		width: "501"
																	}, {
																		default: withCtx(() => [createVNode(VCard, null, {
																			default: withCtx(() => [createVNode(VToolbar, { title: "FORM: Uri" }), createVNode(VCardText, null, {
																				default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																					default: withCtx(() => [createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formUri).address,
																							"onUpdate:modelValue": ($event) => unref(formUri).address = $event,
																							label: "Uri address",
																							variant: "outlined"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}), createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																							default: withCtx(() => [createVNode(VBtn, {
																								text: "store",
																								block: "",
																								onClick: storeUri
																							})]),
																							_: 1
																						})]),
																						_: 1
																					})]),
																					_: 1
																				}, 8, ["onSubmit"])]),
																				_: 1
																			})]),
																			_: 1
																		})]),
																		_: 1
																	}, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																})]),
																_: 1
															}),
															createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, { cols: "4" }, {
																	default: withCtx(() => [createVNode(VSelect, {
																		modelValue: unref(formUnit).labels,
																		"onUpdate:modelValue": ($event) => unref(formUnit).labels = $event,
																		items: labels.value,
																		"item-value": "id",
																		"item-title": "name",
																		label: "Labels",
																		multiple: ""
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items"
																	])]),
																	_: 1
																})]),
																_: 1
															}),
															createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VAutocomplete, {
																		modelValue: unref(formUnit).buildings,
																		"onUpdate:modelValue": ($event) => unref(formUnit).buildings = $event,
																		items: unref(listBuildings),
																		"item-title": formatBuildingTitle,
																		"item-value": "id",
																		label: "Buildings",
																		color: "blue",
																		multiple: "",
																		chips: ""
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items"
																	])]),
																	_: 1
																})]),
																_: 1
															})
														]),
														_: 1
													})]),
													_: 1
												}, 8, ["onSubmit"])]),
												_: 1
											}),
											createVNode(VCardActions, { class: "justify-end" }, {
												default: withCtx(() => [createVNode(VBtn, {
													text: "Close",
													onClick: ($event) => isActive.value = false
												}, null, 8, ["onClick"]), createVNode(VBtn, {
													text: "Сохранить",
													onClick: storeUnit
												})]),
												_: 2
											}, 1024)
										]),
										_: 2
									}, 1024)]),
									_: 1
								})]),
								_: 1
							})
						]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol, { cols: "4" }, {
								default: withCtx(() => [createVNode(VList, { lines: "two" }, {
									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(units.value, (unit) => {
										return openBlock(), createBlock(VListItem, {
											key: unit.id,
											class: "border-emerald-900",
											elevation: "0",
											density: "comfortable",
											border: "",
											rounded: ""
										}, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, null, {
													default: withCtx(() => [createVNode(unref(Link), {
														href: unref(route)("web.unit.show", unit.id),
														class: "font-RubikMedium"
													}, {
														default: withCtx(() => [createVNode("span", null, toDisplayString(unit.name), 1)]),
														_: 2
													}, 1032, ["href"])]),
													_: 2
												}, 1024)]),
												_: 2
											}, 1024), createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, { cols: "8" }, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unit.labels, (label) => {
														return openBlock(), createBlock("div", {
															key: label.id,
															class: "text-xs"
														}, toDisplayString(label.name), 1);
													}), 128))]),
													_: 2
												}, 1024), createVNode(VCol, { cols: "4" }, {
													default: withCtx(() => [unit.entities.length > 0 ? (openBlock(), createBlock(VChip, { key: 0 }, {
														default: withCtx(() => [createTextVNode(toDisplayString(unit.entities.length), 1)]),
														_: 2
													}, 1024)) : createCommentVNode("", true)]),
													_: 2
												}, 1024)]),
												_: 2
											}, 1024)]),
											_: 2
										}, 1024);
									}), 128))]),
									_: 1
								})]),
								_: 1
							}),
							createVNode(VCol, { lg: "4" }, {
								default: withCtx(() => [createVNode(VSheet, null, {
									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(units.value, (unit) => {
										return openBlock(), createBlock("div", { class: "inline-block mr-2 p-1 text-xs" }, toDisplayString(unit.name), 1);
									}), 256))]),
									_: 1
								})]),
								_: 1
							}),
							createVNode(VCol)
						]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Units.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Units-DK0qG9rv.js.map