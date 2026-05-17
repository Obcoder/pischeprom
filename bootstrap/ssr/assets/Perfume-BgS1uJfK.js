import { C as VRow, D as VDataTable, K as VDivider, P as VCard, R as VCardActions, T as VContainer, U as VTextField, V as VAutocomplete, W as VMenu, ct as VToolbar, h as VForm, n as VToolbarItems, rt as VBtn, w as VCol } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { useForm } from "@inertiajs/vue3";
import { createTextVNode, createVNode, mergeProps, onMounted, ref, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { route } from "ziggy-js";
import { ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Ameise/Perfume.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Perfume",
	__ssrInlineRender: true,
	setup(__props) {
		const brands = ref([]);
		const fragrances = ref([]);
		const notes = ref([]);
		function indexBrands() {
			axios.get(route("brands.index")).then(function(response) {
				brands.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		function indexFragrances() {
			axios.get(route("fragrances.index")).then(function(response) {
				fragrances.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		const headerFragrances = ref([{
			key: "brand.name",
			title: "Brand",
			align: "start",
			sortable: true
		}, {
			key: "name",
			title: "name",
			align: "start",
			sortable: true
		}]);
		ref(false);
		const menu = ref(false);
		const formFragrance = useForm({
			brand_id: null,
			name: null
		});
		const submitForm = () => {
			formFragrance.post(route("web.fragrance.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: true,
				onSuccess: () => {
					menu.value = false;
					formFragrance.reset();
					indexFragrances();
				}
			});
		};
		function indexNotes() {
			axios.get(route("notes.index")).then(function(response) {
				notes.value = response.data;
			}).catch(function(error) {
				console.error(error);
			});
		}
		const headerNotes = ref([{
			key: "name",
			title: "Name",
			align: "start",
			sortable: true
		}]);
		const menuFormNote = ref(false);
		const formNote = useForm({ name: null });
		function storeNote() {
			formNote.post(route("web.note.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: true,
				onSuccess: () => {
					menuFormNote.value = false;
					formNote.reset();
					indexNotes();
				}
			});
		}
		onMounted(() => {
			indexBrands();
			indexFragrances();
			indexNotes();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VToolbar, {
									density: "compact",
									rounded: "",
									color: "pink"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VToolbarItems, null, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(ssrRenderComponent(VMenu, {
														modelValue: menuFormNote.value,
														"onUpdate:modelValue": ($event) => menuFormNote.value = $event,
														"close-on-content-click": false,
														transition: "transition",
														"offset-y": ""
													}, {
														activator: withCtx(({ props }, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VBtn, mergeProps(props, {
																text: "",
																variant: "text"
															}), {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` +No `);
																	else return [createTextVNode(" +No ")];
																}),
																_: 2
															}, _parent, _scopeId));
															else return [createVNode(VBtn, mergeProps(props, {
																text: "",
																variant: "text"
															}), {
																default: withCtx(() => [createTextVNode(" +No ")]),
																_: 1
															}, 16)];
														}),
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VCard, {
																class: "pa-4",
																"min-width": "500"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VForm, { onSubmit: storeNote }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VContainer, { fluid: "" }, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(ssrRenderComponent(VRow, null, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VCol, null, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(ssrRenderComponent(VTextField, {
																											modelValue: unref(formNote).name,
																											"onUpdate:modelValue": ($event) => unref(formNote).name = $event,
																											label: "Название",
																											variant: "solo",
																											density: "comfortable",
																											"hide-details": "",
																											color: "pink"
																										}, null, _parent, _scopeId));
																										else return [createVNode(VTextField, {
																											modelValue: unref(formNote).name,
																											"onUpdate:modelValue": ($event) => unref(formNote).name = $event,
																											label: "Название",
																											variant: "solo",
																											density: "comfortable",
																											"hide-details": "",
																											color: "pink"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																									}),
																									_: 1
																								}, _parent, _scopeId));
																								else return [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formNote).name,
																										"onUpdate:modelValue": ($event) => unref(formNote).name = $event,
																										label: "Название",
																										variant: "solo",
																										density: "comfortable",
																										"hide-details": "",
																										color: "pink"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								})];
																							}),
																							_: 1
																						}, _parent, _scopeId));
																						else return [createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formNote).name,
																									"onUpdate:modelValue": ($event) => unref(formNote).name = $event,
																									label: "Название",
																									variant: "solo",
																									density: "comfortable",
																									"hide-details": "",
																									color: "pink"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							})]),
																							_: 1
																						})];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																				else return [createVNode(VContainer, { fluid: "" }, {
																					default: withCtx(() => [createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formNote).name,
																								"onUpdate:modelValue": ($event) => unref(formNote).name = $event,
																								label: "Название",
																								variant: "solo",
																								density: "comfortable",
																								"hide-details": "",
																								color: "pink"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
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
																					_push(ssrRenderComponent(VDivider, {
																						vertical: "",
																						opacity: "0.91",
																						color: "pink"
																					}, null, _parent, _scopeId));
																					_push(ssrRenderComponent(VBtn, {
																						text: "сохранить",
																						onClick: storeNote,
																						variant: "elevated",
																						density: "comfortable",
																						color: "light-blue"
																					}, null, _parent, _scopeId));
																				} else return [createVNode(VDivider, {
																					vertical: "",
																					opacity: "0.91",
																					color: "pink"
																				}), createVNode(VBtn, {
																					text: "сохранить",
																					onClick: storeNote,
																					variant: "elevated",
																					density: "comfortable",
																					color: "light-blue"
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																	} else return [createVNode(VForm, { onSubmit: withModifiers(storeNote, ["prevent"]) }, {
																		default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
																			default: withCtx(() => [createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formNote).name,
																						"onUpdate:modelValue": ($event) => unref(formNote).name = $event,
																						label: "Название",
																						variant: "solo",
																						density: "comfortable",
																						"hide-details": "",
																						color: "pink"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				})]),
																				_: 1
																			})]),
																			_: 1
																		})]),
																		_: 1
																	}), createVNode(VCardActions, null, {
																		default: withCtx(() => [createVNode(VDivider, {
																			vertical: "",
																			opacity: "0.91",
																			color: "pink"
																		}), createVNode(VBtn, {
																			text: "сохранить",
																			onClick: storeNote,
																			variant: "elevated",
																			density: "comfortable",
																			color: "light-blue"
																		})]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VCard, {
																class: "pa-4",
																"min-width": "500"
															}, {
																default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storeNote, ["prevent"]) }, {
																	default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formNote).name,
																					"onUpdate:modelValue": ($event) => unref(formNote).name = $event,
																					label: "Название",
																					variant: "solo",
																					density: "comfortable",
																					"hide-details": "",
																					color: "pink"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			})]),
																			_: 1
																		})]),
																		_: 1
																	})]),
																	_: 1
																}), createVNode(VCardActions, null, {
																	default: withCtx(() => [createVNode(VDivider, {
																		vertical: "",
																		opacity: "0.91",
																		color: "pink"
																	}), createVNode(VBtn, {
																		text: "сохранить",
																		onClick: storeNote,
																		variant: "elevated",
																		density: "comfortable",
																		color: "light-blue"
																	})]),
																	_: 1
																})]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VMenu, {
														modelValue: menu.value,
														"onUpdate:modelValue": ($event) => menu.value = $event,
														"close-on-content-click": false,
														transition: "scale-transition",
														"offset-y": ""
													}, {
														activator: withCtx(({ props }, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VBtn, mergeProps(props, {
																text: "",
																variant: "text"
															}), {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` +Fr `);
																	else return [createTextVNode(" +Fr ")];
																}),
																_: 2
															}, _parent, _scopeId));
															else return [createVNode(VBtn, mergeProps(props, {
																text: "",
																variant: "text"
															}), {
																default: withCtx(() => [createTextVNode(" +Fr ")]),
																_: 1
															}, 16)];
														}),
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VCard, {
																class: "pa-4",
																"min-width": "500"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VForm, { onSubmit: submitForm }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) {
																				_push(ssrRenderComponent(VContainer, { fluid: "" }, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) {
																							_push(ssrRenderComponent(VRow, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(ssrRenderComponent(VCol, null, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(ssrRenderComponent(VAutocomplete, {
																												items: brands.value,
																												"item-value": "id",
																												"item-title": "name",
																												modelValue: unref(formFragrance).brand_id,
																												"onUpdate:modelValue": ($event) => unref(formFragrance).brand_id = $event,
																												label: "Выбрать brand",
																												placeholder: "Брэнды",
																												variant: "solo",
																												density: "comfortable",
																												"hide-details": "",
																												chips: ""
																											}, null, _parent, _scopeId));
																											else return [createVNode(VAutocomplete, {
																												items: brands.value,
																												"item-value": "id",
																												"item-title": "name",
																												modelValue: unref(formFragrance).brand_id,
																												"onUpdate:modelValue": ($event) => unref(formFragrance).brand_id = $event,
																												label: "Выбрать brand",
																												placeholder: "Брэнды",
																												variant: "solo",
																												density: "comfortable",
																												"hide-details": "",
																												chips: ""
																											}, null, 8, [
																												"items",
																												"modelValue",
																												"onUpdate:modelValue"
																											])];
																										}),
																										_: 1
																									}, _parent, _scopeId));
																									else return [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VAutocomplete, {
																											items: brands.value,
																											"item-value": "id",
																											"item-title": "name",
																											modelValue: unref(formFragrance).brand_id,
																											"onUpdate:modelValue": ($event) => unref(formFragrance).brand_id = $event,
																											label: "Выбрать brand",
																											placeholder: "Брэнды",
																											variant: "solo",
																											density: "comfortable",
																											"hide-details": "",
																											chips: ""
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
																									if (_push) _push(ssrRenderComponent(VCol, null, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(ssrRenderComponent(VTextField, {
																												modelValue: unref(formFragrance).name,
																												"onUpdate:modelValue": ($event) => unref(formFragrance).name = $event,
																												label: "Название",
																												variant: "solo",
																												dense: "",
																												required: "",
																												"error-messages": unref(formFragrance).errors.name,
																												"hide-details": ""
																											}, null, _parent, _scopeId));
																											else return [createVNode(VTextField, {
																												modelValue: unref(formFragrance).name,
																												"onUpdate:modelValue": ($event) => unref(formFragrance).name = $event,
																												label: "Название",
																												variant: "solo",
																												dense: "",
																												required: "",
																												"error-messages": unref(formFragrance).errors.name,
																												"hide-details": ""
																											}, null, 8, [
																												"modelValue",
																												"onUpdate:modelValue",
																												"error-messages"
																											])];
																										}),
																										_: 1
																									}, _parent, _scopeId));
																									else return [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formFragrance).name,
																											"onUpdate:modelValue": ($event) => unref(formFragrance).name = $event,
																											label: "Название",
																											variant: "solo",
																											dense: "",
																											required: "",
																											"error-messages": unref(formFragrance).errors.name,
																											"hide-details": ""
																										}, null, 8, [
																											"modelValue",
																											"onUpdate:modelValue",
																											"error-messages"
																										])]),
																										_: 1
																									})];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																						} else return [createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VAutocomplete, {
																									items: brands.value,
																									"item-value": "id",
																									"item-title": "name",
																									modelValue: unref(formFragrance).brand_id,
																									"onUpdate:modelValue": ($event) => unref(formFragrance).brand_id = $event,
																									label: "Выбрать brand",
																									placeholder: "Брэнды",
																									variant: "solo",
																									density: "comfortable",
																									"hide-details": "",
																									chips: ""
																								}, null, 8, [
																									"items",
																									"modelValue",
																									"onUpdate:modelValue"
																								])]),
																								_: 1
																							})]),
																							_: 1
																						}), createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formFragrance).name,
																									"onUpdate:modelValue": ($event) => unref(formFragrance).name = $event,
																									label: "Название",
																									variant: "solo",
																									dense: "",
																									required: "",
																									"error-messages": unref(formFragrance).errors.name,
																									"hide-details": ""
																								}, null, 8, [
																									"modelValue",
																									"onUpdate:modelValue",
																									"error-messages"
																								])]),
																								_: 1
																							})]),
																							_: 1
																						})];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																				_push(ssrRenderComponent(VCardActions, { class: "d-flex justify-end" }, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) {
																							_push(ssrRenderComponent(VBtn, {
																								text: "",
																								onClick: ($event) => menu.value = false
																							}, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(`Отмена`);
																									else return [createTextVNode("Отмена")];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																							_push(ssrRenderComponent(VBtn, {
																								text: "сохранить",
																								variant: "elevated",
																								density: "comfortable",
																								color: "light-blue",
																								type: "submit",
																								loading: unref(formFragrance).processing
																							}, null, _parent, _scopeId));
																						} else return [createVNode(VBtn, {
																							text: "",
																							onClick: ($event) => menu.value = false
																						}, {
																							default: withCtx(() => [createTextVNode("Отмена")]),
																							_: 1
																						}, 8, ["onClick"]), createVNode(VBtn, {
																							text: "сохранить",
																							variant: "elevated",
																							density: "comfortable",
																							color: "light-blue",
																							type: "submit",
																							loading: unref(formFragrance).processing
																						}, null, 8, ["loading"])];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																			} else return [createVNode(VContainer, { fluid: "" }, {
																				default: withCtx(() => [createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VAutocomplete, {
																							items: brands.value,
																							"item-value": "id",
																							"item-title": "name",
																							modelValue: unref(formFragrance).brand_id,
																							"onUpdate:modelValue": ($event) => unref(formFragrance).brand_id = $event,
																							label: "Выбрать brand",
																							placeholder: "Брэнды",
																							variant: "solo",
																							density: "comfortable",
																							"hide-details": "",
																							chips: ""
																						}, null, 8, [
																							"items",
																							"modelValue",
																							"onUpdate:modelValue"
																						])]),
																						_: 1
																					})]),
																					_: 1
																				}), createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formFragrance).name,
																							"onUpdate:modelValue": ($event) => unref(formFragrance).name = $event,
																							label: "Название",
																							variant: "solo",
																							dense: "",
																							required: "",
																							"error-messages": unref(formFragrance).errors.name,
																							"hide-details": ""
																						}, null, 8, [
																							"modelValue",
																							"onUpdate:modelValue",
																							"error-messages"
																						])]),
																						_: 1
																					})]),
																					_: 1
																				})]),
																				_: 1
																			}), createVNode(VCardActions, { class: "d-flex justify-end" }, {
																				default: withCtx(() => [createVNode(VBtn, {
																					text: "",
																					onClick: ($event) => menu.value = false
																				}, {
																					default: withCtx(() => [createTextVNode("Отмена")]),
																					_: 1
																				}, 8, ["onClick"]), createVNode(VBtn, {
																					text: "сохранить",
																					variant: "elevated",
																					density: "comfortable",
																					color: "light-blue",
																					type: "submit",
																					loading: unref(formFragrance).processing
																				}, null, 8, ["loading"])]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VForm, { onSubmit: withModifiers(submitForm, ["prevent"]) }, {
																		default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
																			default: withCtx(() => [createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VAutocomplete, {
																						items: brands.value,
																						"item-value": "id",
																						"item-title": "name",
																						modelValue: unref(formFragrance).brand_id,
																						"onUpdate:modelValue": ($event) => unref(formFragrance).brand_id = $event,
																						label: "Выбрать brand",
																						placeholder: "Брэнды",
																						variant: "solo",
																						density: "comfortable",
																						"hide-details": "",
																						chips: ""
																					}, null, 8, [
																						"items",
																						"modelValue",
																						"onUpdate:modelValue"
																					])]),
																					_: 1
																				})]),
																				_: 1
																			}), createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formFragrance).name,
																						"onUpdate:modelValue": ($event) => unref(formFragrance).name = $event,
																						label: "Название",
																						variant: "solo",
																						dense: "",
																						required: "",
																						"error-messages": unref(formFragrance).errors.name,
																						"hide-details": ""
																					}, null, 8, [
																						"modelValue",
																						"onUpdate:modelValue",
																						"error-messages"
																					])]),
																					_: 1
																				})]),
																				_: 1
																			})]),
																			_: 1
																		}), createVNode(VCardActions, { class: "d-flex justify-end" }, {
																			default: withCtx(() => [createVNode(VBtn, {
																				text: "",
																				onClick: ($event) => menu.value = false
																			}, {
																				default: withCtx(() => [createTextVNode("Отмена")]),
																				_: 1
																			}, 8, ["onClick"]), createVNode(VBtn, {
																				text: "сохранить",
																				variant: "elevated",
																				density: "comfortable",
																				color: "light-blue",
																				type: "submit",
																				loading: unref(formFragrance).processing
																			}, null, 8, ["loading"])]),
																			_: 1
																		})]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VCard, {
																class: "pa-4",
																"min-width": "500"
															}, {
																default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(submitForm, ["prevent"]) }, {
																	default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VAutocomplete, {
																					items: brands.value,
																					"item-value": "id",
																					"item-title": "name",
																					modelValue: unref(formFragrance).brand_id,
																					"onUpdate:modelValue": ($event) => unref(formFragrance).brand_id = $event,
																					label: "Выбрать brand",
																					placeholder: "Брэнды",
																					variant: "solo",
																					density: "comfortable",
																					"hide-details": "",
																					chips: ""
																				}, null, 8, [
																					"items",
																					"modelValue",
																					"onUpdate:modelValue"
																				])]),
																				_: 1
																			})]),
																			_: 1
																		}), createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formFragrance).name,
																					"onUpdate:modelValue": ($event) => unref(formFragrance).name = $event,
																					label: "Название",
																					variant: "solo",
																					dense: "",
																					required: "",
																					"error-messages": unref(formFragrance).errors.name,
																					"hide-details": ""
																				}, null, 8, [
																					"modelValue",
																					"onUpdate:modelValue",
																					"error-messages"
																				])]),
																				_: 1
																			})]),
																			_: 1
																		})]),
																		_: 1
																	}), createVNode(VCardActions, { class: "d-flex justify-end" }, {
																		default: withCtx(() => [createVNode(VBtn, {
																			text: "",
																			onClick: ($event) => menu.value = false
																		}, {
																			default: withCtx(() => [createTextVNode("Отмена")]),
																			_: 1
																		}, 8, ["onClick"]), createVNode(VBtn, {
																			text: "сохранить",
																			variant: "elevated",
																			density: "comfortable",
																			color: "light-blue",
																			type: "submit",
																			loading: unref(formFragrance).processing
																		}, null, 8, ["loading"])]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
												} else return [createVNode(VMenu, {
													modelValue: menuFormNote.value,
													"onUpdate:modelValue": ($event) => menuFormNote.value = $event,
													"close-on-content-click": false,
													transition: "transition",
													"offset-y": ""
												}, {
													activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps(props, {
														text: "",
														variant: "text"
													}), {
														default: withCtx(() => [createTextVNode(" +No ")]),
														_: 1
													}, 16)]),
													default: withCtx(() => [createVNode(VCard, {
														class: "pa-4",
														"min-width": "500"
													}, {
														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storeNote, ["prevent"]) }, {
															default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formNote).name,
																			"onUpdate:modelValue": ($event) => unref(formNote).name = $event,
																			label: "Название",
																			variant: "solo",
																			density: "comfortable",
																			"hide-details": "",
																			color: "pink"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														}), createVNode(VCardActions, null, {
															default: withCtx(() => [createVNode(VDivider, {
																vertical: "",
																opacity: "0.91",
																color: "pink"
															}), createVNode(VBtn, {
																text: "сохранить",
																onClick: storeNote,
																variant: "elevated",
																density: "comfortable",
																color: "light-blue"
															})]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												}, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VMenu, {
													modelValue: menu.value,
													"onUpdate:modelValue": ($event) => menu.value = $event,
													"close-on-content-click": false,
													transition: "scale-transition",
													"offset-y": ""
												}, {
													activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps(props, {
														text: "",
														variant: "text"
													}), {
														default: withCtx(() => [createTextVNode(" +Fr ")]),
														_: 1
													}, 16)]),
													default: withCtx(() => [createVNode(VCard, {
														class: "pa-4",
														"min-width": "500"
													}, {
														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(submitForm, ["prevent"]) }, {
															default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VAutocomplete, {
																			items: brands.value,
																			"item-value": "id",
																			"item-title": "name",
																			modelValue: unref(formFragrance).brand_id,
																			"onUpdate:modelValue": ($event) => unref(formFragrance).brand_id = $event,
																			label: "Выбрать brand",
																			placeholder: "Брэнды",
																			variant: "solo",
																			density: "comfortable",
																			"hide-details": "",
																			chips: ""
																		}, null, 8, [
																			"items",
																			"modelValue",
																			"onUpdate:modelValue"
																		])]),
																		_: 1
																	})]),
																	_: 1
																}), createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formFragrance).name,
																			"onUpdate:modelValue": ($event) => unref(formFragrance).name = $event,
																			label: "Название",
																			variant: "solo",
																			dense: "",
																			required: "",
																			"error-messages": unref(formFragrance).errors.name,
																			"hide-details": ""
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"error-messages"
																		])]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															}), createVNode(VCardActions, { class: "d-flex justify-end" }, {
																default: withCtx(() => [createVNode(VBtn, {
																	text: "",
																	onClick: ($event) => menu.value = false
																}, {
																	default: withCtx(() => [createTextVNode("Отмена")]),
																	_: 1
																}, 8, ["onClick"]), createVNode(VBtn, {
																	text: "сохранить",
																	variant: "elevated",
																	density: "comfortable",
																	color: "light-blue",
																	type: "submit",
																	loading: unref(formFragrance).processing
																}, null, 8, ["loading"])]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												}, 8, ["modelValue", "onUpdate:modelValue"])];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VToolbarItems, null, {
											default: withCtx(() => [createVNode(VMenu, {
												modelValue: menuFormNote.value,
												"onUpdate:modelValue": ($event) => menuFormNote.value = $event,
												"close-on-content-click": false,
												transition: "transition",
												"offset-y": ""
											}, {
												activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps(props, {
													text: "",
													variant: "text"
												}), {
													default: withCtx(() => [createTextVNode(" +No ")]),
													_: 1
												}, 16)]),
												default: withCtx(() => [createVNode(VCard, {
													class: "pa-4",
													"min-width": "500"
												}, {
													default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storeNote, ["prevent"]) }, {
														default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
															default: withCtx(() => [createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formNote).name,
																		"onUpdate:modelValue": ($event) => unref(formNote).name = $event,
																		label: "Название",
																		variant: "solo",
																		density: "comfortable",
																		"hide-details": "",
																		color: "pink"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													}), createVNode(VCardActions, null, {
														default: withCtx(() => [createVNode(VDivider, {
															vertical: "",
															opacity: "0.91",
															color: "pink"
														}), createVNode(VBtn, {
															text: "сохранить",
															onClick: storeNote,
															variant: "elevated",
															density: "comfortable",
															color: "light-blue"
														})]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											}, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VMenu, {
												modelValue: menu.value,
												"onUpdate:modelValue": ($event) => menu.value = $event,
												"close-on-content-click": false,
												transition: "scale-transition",
												"offset-y": ""
											}, {
												activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps(props, {
													text: "",
													variant: "text"
												}), {
													default: withCtx(() => [createTextVNode(" +Fr ")]),
													_: 1
												}, 16)]),
												default: withCtx(() => [createVNode(VCard, {
													class: "pa-4",
													"min-width": "500"
												}, {
													default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(submitForm, ["prevent"]) }, {
														default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
															default: withCtx(() => [createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VAutocomplete, {
																		items: brands.value,
																		"item-value": "id",
																		"item-title": "name",
																		modelValue: unref(formFragrance).brand_id,
																		"onUpdate:modelValue": ($event) => unref(formFragrance).brand_id = $event,
																		label: "Выбрать brand",
																		placeholder: "Брэнды",
																		variant: "solo",
																		density: "comfortable",
																		"hide-details": "",
																		chips: ""
																	}, null, 8, [
																		"items",
																		"modelValue",
																		"onUpdate:modelValue"
																	])]),
																	_: 1
																})]),
																_: 1
															}), createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formFragrance).name,
																		"onUpdate:modelValue": ($event) => unref(formFragrance).name = $event,
																		label: "Название",
																		variant: "solo",
																		dense: "",
																		required: "",
																		"error-messages": unref(formFragrance).errors.name,
																		"hide-details": ""
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"error-messages"
																	])]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														}), createVNode(VCardActions, { class: "d-flex justify-end" }, {
															default: withCtx(() => [createVNode(VBtn, {
																text: "",
																onClick: ($event) => menu.value = false
															}, {
																default: withCtx(() => [createTextVNode("Отмена")]),
																_: 1
															}, 8, ["onClick"]), createVNode(VBtn, {
																text: "сохранить",
																variant: "elevated",
																density: "comfortable",
																color: "light-blue",
																type: "submit",
																loading: unref(formFragrance).processing
															}, null, 8, ["loading"])]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											}, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VToolbar, {
									density: "compact",
									rounded: "",
									color: "pink"
								}, {
									default: withCtx(() => [createVNode(VToolbarItems, null, {
										default: withCtx(() => [createVNode(VMenu, {
											modelValue: menuFormNote.value,
											"onUpdate:modelValue": ($event) => menuFormNote.value = $event,
											"close-on-content-click": false,
											transition: "transition",
											"offset-y": ""
										}, {
											activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps(props, {
												text: "",
												variant: "text"
											}), {
												default: withCtx(() => [createTextVNode(" +No ")]),
												_: 1
											}, 16)]),
											default: withCtx(() => [createVNode(VCard, {
												class: "pa-4",
												"min-width": "500"
											}, {
												default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storeNote, ["prevent"]) }, {
													default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
														default: withCtx(() => [createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(formNote).name,
																	"onUpdate:modelValue": ($event) => unref(formNote).name = $event,
																	label: "Название",
																	variant: "solo",
																	density: "comfortable",
																	"hide-details": "",
																	color: "pink"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												}), createVNode(VCardActions, null, {
													default: withCtx(() => [createVNode(VDivider, {
														vertical: "",
														opacity: "0.91",
														color: "pink"
													}), createVNode(VBtn, {
														text: "сохранить",
														onClick: storeNote,
														variant: "elevated",
														density: "comfortable",
														color: "light-blue"
													})]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										}, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VMenu, {
											modelValue: menu.value,
											"onUpdate:modelValue": ($event) => menu.value = $event,
											"close-on-content-click": false,
											transition: "scale-transition",
											"offset-y": ""
										}, {
											activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps(props, {
												text: "",
												variant: "text"
											}), {
												default: withCtx(() => [createTextVNode(" +Fr ")]),
												_: 1
											}, 16)]),
											default: withCtx(() => [createVNode(VCard, {
												class: "pa-4",
												"min-width": "500"
											}, {
												default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(submitForm, ["prevent"]) }, {
													default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
														default: withCtx(() => [createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VAutocomplete, {
																	items: brands.value,
																	"item-value": "id",
																	"item-title": "name",
																	modelValue: unref(formFragrance).brand_id,
																	"onUpdate:modelValue": ($event) => unref(formFragrance).brand_id = $event,
																	label: "Выбрать brand",
																	placeholder: "Брэнды",
																	variant: "solo",
																	density: "comfortable",
																	"hide-details": "",
																	chips: ""
																}, null, 8, [
																	"items",
																	"modelValue",
																	"onUpdate:modelValue"
																])]),
																_: 1
															})]),
															_: 1
														}), createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(formFragrance).name,
																	"onUpdate:modelValue": ($event) => unref(formFragrance).name = $event,
																	label: "Название",
																	variant: "solo",
																	dense: "",
																	required: "",
																	"error-messages": unref(formFragrance).errors.name,
																	"hide-details": ""
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"error-messages"
																])]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													}), createVNode(VCardActions, { class: "d-flex justify-end" }, {
														default: withCtx(() => [createVNode(VBtn, {
															text: "",
															onClick: ($event) => menu.value = false
														}, {
															default: withCtx(() => [createTextVNode("Отмена")]),
															_: 1
														}, 8, ["onClick"]), createVNode(VBtn, {
															text: "сохранить",
															variant: "elevated",
															density: "comfortable",
															color: "light-blue",
															type: "submit",
															loading: unref(formFragrance).processing
														}, null, 8, ["loading"])]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										}, 8, ["modelValue", "onUpdate:modelValue"])]),
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
									_push(ssrRenderComponent(VCol, { cols: "3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VDataTable, {
												items: notes.value,
												"items-per-page": "100",
												headers: headerNotes.value,
												"fixed-header": "",
												height: "600px",
												density: "compact",
												class: "border rounded text-xs",
												hover: ""
											}, null, _parent, _scopeId));
											else return [createVNode(VDataTable, {
												items: notes.value,
												"items-per-page": "100",
												headers: headerNotes.value,
												"fixed-header": "",
												height: "600px",
												density: "compact",
												class: "border rounded text-xs",
												hover: ""
											}, null, 8, ["items", "headers"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VDataTable, {
												items: fragrances.value,
												"items-per-page": "100",
												headers: headerFragrances.value,
												"fixed-header": "",
												height: "600px",
												density: "compact",
												class: "border rounded",
												hover: ""
											}, null, _parent, _scopeId));
											else return [createVNode(VDataTable, {
												items: fragrances.value,
												"items-per-page": "100",
												headers: headerFragrances.value,
												"fixed-header": "",
												height: "600px",
												density: "compact",
												class: "border rounded",
												hover: ""
											}, null, 8, ["items", "headers"])];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, { cols: "3" }, {
									default: withCtx(() => [createVNode(VDataTable, {
										items: notes.value,
										"items-per-page": "100",
										headers: headerNotes.value,
										"fixed-header": "",
										height: "600px",
										density: "compact",
										class: "border rounded text-xs",
										hover: ""
									}, null, 8, ["items", "headers"])]),
									_: 1
								}), createVNode(VCol, { cols: "3" }, {
									default: withCtx(() => [createVNode(VDataTable, {
										items: fragrances.value,
										"items-per-page": "100",
										headers: headerFragrances.value,
										"fixed-header": "",
										height: "600px",
										density: "compact",
										class: "border rounded",
										hover: ""
									}, null, 8, ["items", "headers"])]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VToolbar, {
							density: "compact",
							rounded: "",
							color: "pink"
						}, {
							default: withCtx(() => [createVNode(VToolbarItems, null, {
								default: withCtx(() => [createVNode(VMenu, {
									modelValue: menuFormNote.value,
									"onUpdate:modelValue": ($event) => menuFormNote.value = $event,
									"close-on-content-click": false,
									transition: "transition",
									"offset-y": ""
								}, {
									activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps(props, {
										text: "",
										variant: "text"
									}), {
										default: withCtx(() => [createTextVNode(" +No ")]),
										_: 1
									}, 16)]),
									default: withCtx(() => [createVNode(VCard, {
										class: "pa-4",
										"min-width": "500"
									}, {
										default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(storeNote, ["prevent"]) }, {
											default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
												default: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: unref(formNote).name,
															"onUpdate:modelValue": ($event) => unref(formNote).name = $event,
															label: "Название",
															variant: "solo",
															density: "comfortable",
															"hide-details": "",
															color: "pink"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										}), createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VDivider, {
												vertical: "",
												opacity: "0.91",
												color: "pink"
											}), createVNode(VBtn, {
												text: "сохранить",
												onClick: storeNote,
												variant: "elevated",
												density: "comfortable",
												color: "light-blue"
											})]),
											_: 1
										})]),
										_: 1
									})]),
									_: 1
								}, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VMenu, {
									modelValue: menu.value,
									"onUpdate:modelValue": ($event) => menu.value = $event,
									"close-on-content-click": false,
									transition: "scale-transition",
									"offset-y": ""
								}, {
									activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps(props, {
										text: "",
										variant: "text"
									}), {
										default: withCtx(() => [createTextVNode(" +Fr ")]),
										_: 1
									}, 16)]),
									default: withCtx(() => [createVNode(VCard, {
										class: "pa-4",
										"min-width": "500"
									}, {
										default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(submitForm, ["prevent"]) }, {
											default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
												default: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VAutocomplete, {
															items: brands.value,
															"item-value": "id",
															"item-title": "name",
															modelValue: unref(formFragrance).brand_id,
															"onUpdate:modelValue": ($event) => unref(formFragrance).brand_id = $event,
															label: "Выбрать brand",
															placeholder: "Брэнды",
															variant: "solo",
															density: "comfortable",
															"hide-details": "",
															chips: ""
														}, null, 8, [
															"items",
															"modelValue",
															"onUpdate:modelValue"
														])]),
														_: 1
													})]),
													_: 1
												}), createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: unref(formFragrance).name,
															"onUpdate:modelValue": ($event) => unref(formFragrance).name = $event,
															label: "Название",
															variant: "solo",
															dense: "",
															required: "",
															"error-messages": unref(formFragrance).errors.name,
															"hide-details": ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"error-messages"
														])]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											}), createVNode(VCardActions, { class: "d-flex justify-end" }, {
												default: withCtx(() => [createVNode(VBtn, {
													text: "",
													onClick: ($event) => menu.value = false
												}, {
													default: withCtx(() => [createTextVNode("Отмена")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													text: "сохранить",
													variant: "elevated",
													density: "comfortable",
													color: "light-blue",
													type: "submit",
													loading: unref(formFragrance).processing
												}, null, 8, ["loading"])]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									})]),
									_: 1
								}, 8, ["modelValue", "onUpdate:modelValue"])]),
								_: 1
							})]),
							_: 1
						})]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, { cols: "3" }, {
							default: withCtx(() => [createVNode(VDataTable, {
								items: notes.value,
								"items-per-page": "100",
								headers: headerNotes.value,
								"fixed-header": "",
								height: "600px",
								density: "compact",
								class: "border rounded text-xs",
								hover: ""
							}, null, 8, ["items", "headers"])]),
							_: 1
						}), createVNode(VCol, { cols: "3" }, {
							default: withCtx(() => [createVNode(VDataTable, {
								items: fragrances.value,
								"items-per-page": "100",
								headers: headerFragrances.value,
								"fixed-header": "",
								height: "600px",
								density: "compact",
								class: "border rounded",
								hover: ""
							}, null, 8, ["items", "headers"])]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Perfume.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Perfume-BgS1uJfK.js.map