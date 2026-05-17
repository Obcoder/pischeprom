import { C as VRow, D as VDataTable, F as VCardText, G as VList, I as VCardTitle, K as VDivider, P as VCard, R as VCardActions, T as VContainer, U as VTextField, h as VForm, l as VSwitch, q as VListItem, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { useForm } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, mergeProps, onMounted, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderAttr, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Ameise/Botany.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Botany",
	__ssrInlineRender: true,
	setup(__props) {
		const genera = ref([]);
		const plants = ref([]);
		const showOnlyAgriculturable = ref(false);
		function indexGenera(like) {
			axios.get(route("genera.index"), { params: { search: like } }).then(function(response) {
				genera.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		const searchGenera = ref("");
		const filteredGenera = computed(() => {
			let list = genera.value;
			if (showOnlyAgriculturable.value) list = list.filter((g) => g.agriculturable);
			if (searchGenera.value.trim()) {
				const term = searchGenera.value.toLowerCase();
				list = list.filter((g) => g.name.toLowerCase().includes(term));
			}
			return list;
		});
		const headerGenera = [
			{
				title: "agriculturable",
				key: "agriculturable",
				align: "center"
			},
			{
				title: "name",
				key: "name",
				align: "start"
			},
			{
				title: "nameLat",
				key: "nameLat",
				align: "start"
			},
			{
				title: "Wiki",
				key: "wiki",
				align: "center"
			}
		];
		const dialogFormGenus = ref(false);
		const formGenus = useForm({
			name: null,
			nameLat: null,
			wiki: null
		});
		function storeGenus() {
			formGenus.post(route("web.genus.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: true,
				onSuccess: () => {
					formGenus.reset();
					indexGenera();
				}
			});
		}
		function indexPlants(like) {
			axios.get(route("plants.index"), { params: { search: like } }).then(function(response) {
				plants.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		function toggleAgriculturable(genus) {
			axios.patch(route("genera.toggleAgriculturable", genus.id)).then((response) => {
				const updated = response.data;
				const index = genera.value.findIndex((g) => g.id === updated.id);
				if (index !== -1) genera.value[index] = updated;
			}).catch((error) => {
				console.log(error);
			});
		}
		onMounted(() => {
			indexGenera();
			indexPlants();
		});
		useHead({
			title: `Ботаника`,
			meta: [{
				name: "description",
				content: `Материалы для изучения Ботаники и растений, физиология растений,`
			}]
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { lg: "3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VTextField, {
												modelValue: searchGenera.value,
												"onUpdate:modelValue": ($event) => searchGenera.value = $event,
												label: "Поиск",
												variant: "underlined",
												density: "comfortable",
												class: "rounded",
												"hide-details": ""
											}, null, _parent, _scopeId));
											else return [createVNode(VTextField, {
												modelValue: searchGenera.value,
												"onUpdate:modelValue": ($event) => searchGenera.value = $event,
												label: "Поиск",
												variant: "underlined",
												density: "comfortable",
												class: "rounded",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { lg: "1" }, null, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "2" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VSwitch, {
												modelValue: showOnlyAgriculturable.value,
												"onUpdate:modelValue": ($event) => showOnlyAgriculturable.value = $event,
												label: "Показать только пригодные для сельского хозяйства",
												color: "green",
												inset: "",
												density: "compact"
											}, null, _parent, _scopeId));
											else return [createVNode(VSwitch, {
												modelValue: showOnlyAgriculturable.value,
												"onUpdate:modelValue": ($event) => showOnlyAgriculturable.value = $event,
												label: "Показать только пригодные для сельского хозяйства",
												color: "green",
												inset: "",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "1" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VBtn, {
													text: "+genus",
													onClick: ($event) => dialogFormGenus.value = !dialogFormGenus.value,
													variant: "elevated",
													size: "small",
													color: "deep-purple"
												}, null, _parent, _scopeId));
												_push(ssrRenderComponent(VDialog, {
													modelValue: dialogFormGenus.value,
													"onUpdate:modelValue": ($event) => dialogFormGenus.value = $event,
													width: "600",
													transition: "dialog-left-transition",
													class: "border-3"
												}, {
													default: withCtx(({ isActive }, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VCard, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	_push(ssrRenderComponent(VCardTitle, null, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(`Form Genus`);
																			else return [createTextVNode("Form Genus")];
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
																								if (_push) {
																									_push(ssrRenderComponent(VCol, null, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(ssrRenderComponent(VTextField, {
																												modelValue: unref(formGenus).name,
																												"onUpdate:modelValue": ($event) => unref(formGenus).name = $event,
																												label: "Name",
																												variant: "outlined",
																												density: "comfortable"
																											}, null, _parent, _scopeId));
																											else return [createVNode(VTextField, {
																												modelValue: unref(formGenus).name,
																												"onUpdate:modelValue": ($event) => unref(formGenus).name = $event,
																												label: "Name",
																												variant: "outlined",
																												density: "comfortable"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																										}),
																										_: 2
																									}, _parent, _scopeId));
																									_push(ssrRenderComponent(VCol, null, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(ssrRenderComponent(VTextField, {
																												modelValue: unref(formGenus).nameLat,
																												"onUpdate:modelValue": ($event) => unref(formGenus).nameLat = $event,
																												label: "Latin",
																												variant: "outlined",
																												density: "comfortable"
																											}, null, _parent, _scopeId));
																											else return [createVNode(VTextField, {
																												modelValue: unref(formGenus).nameLat,
																												"onUpdate:modelValue": ($event) => unref(formGenus).nameLat = $event,
																												label: "Latin",
																												variant: "outlined",
																												density: "comfortable"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																										}),
																										_: 2
																									}, _parent, _scopeId));
																								} else return [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formGenus).name,
																										"onUpdate:modelValue": ($event) => unref(formGenus).name = $event,
																										label: "Name",
																										variant: "outlined",
																										density: "comfortable"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}), createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formGenus).nameLat,
																										"onUpdate:modelValue": ($event) => unref(formGenus).nameLat = $event,
																										label: "Latin",
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
																											modelValue: unref(formGenus).wiki,
																											"onUpdate:modelValue": ($event) => unref(formGenus).wiki = $event,
																											label: "WIKI",
																											variant: "solo",
																											density: "comfortable"
																										}, null, _parent, _scopeId));
																										else return [createVNode(VTextField, {
																											modelValue: unref(formGenus).wiki,
																											"onUpdate:modelValue": ($event) => unref(formGenus).wiki = $event,
																											label: "WIKI",
																											variant: "solo",
																											density: "comfortable"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								else return [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formGenus).wiki,
																										"onUpdate:modelValue": ($event) => unref(formGenus).wiki = $event,
																										label: "WIKI",
																										variant: "solo",
																										density: "comfortable"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								})];
																							}),
																							_: 2
																						}, _parent, _scopeId));
																					} else return [createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formGenus).name,
																								"onUpdate:modelValue": ($event) => unref(formGenus).name = $event,
																								label: "Name",
																								variant: "outlined",
																								density: "comfortable"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}), createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formGenus).nameLat,
																								"onUpdate:modelValue": ($event) => unref(formGenus).nameLat = $event,
																								label: "Latin",
																								variant: "outlined",
																								density: "comfortable"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						})]),
																						_: 1
																					}), createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formGenus).wiki,
																								"onUpdate:modelValue": ($event) => unref(formGenus).wiki = $event,
																								label: "WIKI",
																								variant: "solo",
																								density: "comfortable"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						})]),
																						_: 1
																					})];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																			else return [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																				default: withCtx(() => [createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formGenus).name,
																							"onUpdate:modelValue": ($event) => unref(formGenus).name = $event,
																							label: "Name",
																							variant: "outlined",
																							density: "comfortable"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}), createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formGenus).nameLat,
																							"onUpdate:modelValue": ($event) => unref(formGenus).nameLat = $event,
																							label: "Latin",
																							variant: "outlined",
																							density: "comfortable"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					})]),
																					_: 1
																				}), createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formGenus).wiki,
																							"onUpdate:modelValue": ($event) => unref(formGenus).wiki = $event,
																							label: "WIKI",
																							variant: "solo",
																							density: "comfortable"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					})]),
																					_: 1
																				})]),
																				_: 1
																			}, 8, ["onSubmit"])];
																		}),
																		_: 2
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VCardActions, null, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) {
																				_push(ssrRenderComponent(VDivider, {
																					vertical: "",
																					opacity: "0.91"
																				}, null, _parent, _scopeId));
																				_push(ssrRenderComponent(VBtn, {
																					text: "store",
																					onClick: storeGenus,
																					variant: "elevated",
																					density: "comfortable",
																					color: "orange"
																				}, null, _parent, _scopeId));
																			} else return [createVNode(VDivider, {
																				vertical: "",
																				opacity: "0.91"
																			}), createVNode(VBtn, {
																				text: "store",
																				onClick: storeGenus,
																				variant: "elevated",
																				density: "comfortable",
																				color: "orange"
																			})];
																		}),
																		_: 2
																	}, _parent, _scopeId));
																} else return [
																	createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Form Genus")]),
																		_: 1
																	}),
																	createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																			default: withCtx(() => [createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formGenus).name,
																						"onUpdate:modelValue": ($event) => unref(formGenus).name = $event,
																						label: "Name",
																						variant: "outlined",
																						density: "comfortable"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}), createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formGenus).nameLat,
																						"onUpdate:modelValue": ($event) => unref(formGenus).nameLat = $event,
																						label: "Latin",
																						variant: "outlined",
																						density: "comfortable"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				})]),
																				_: 1
																			}), createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formGenus).wiki,
																						"onUpdate:modelValue": ($event) => unref(formGenus).wiki = $event,
																						label: "WIKI",
																						variant: "solo",
																						density: "comfortable"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				})]),
																				_: 1
																			})]),
																			_: 1
																		}, 8, ["onSubmit"])]),
																		_: 1
																	}),
																	createVNode(VCardActions, null, {
																		default: withCtx(() => [createVNode(VDivider, {
																			vertical: "",
																			opacity: "0.91"
																		}), createVNode(VBtn, {
																			text: "store",
																			onClick: storeGenus,
																			variant: "elevated",
																			density: "comfortable",
																			color: "orange"
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
																	default: withCtx(() => [createTextVNode("Form Genus")]),
																	_: 1
																}),
																createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formGenus).name,
																					"onUpdate:modelValue": ($event) => unref(formGenus).name = $event,
																					label: "Name",
																					variant: "outlined",
																					density: "comfortable"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}), createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formGenus).nameLat,
																					"onUpdate:modelValue": ($event) => unref(formGenus).nameLat = $event,
																					label: "Latin",
																					variant: "outlined",
																					density: "comfortable"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			})]),
																			_: 1
																		}), createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formGenus).wiki,
																					"onUpdate:modelValue": ($event) => unref(formGenus).wiki = $event,
																					label: "WIKI",
																					variant: "solo",
																					density: "comfortable"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			})]),
																			_: 1
																		})]),
																		_: 1
																	}, 8, ["onSubmit"])]),
																	_: 1
																}),
																createVNode(VCardActions, null, {
																	default: withCtx(() => [createVNode(VDivider, {
																		vertical: "",
																		opacity: "0.91"
																	}), createVNode(VBtn, {
																		text: "store",
																		onClick: storeGenus,
																		variant: "elevated",
																		density: "comfortable",
																		color: "orange"
																	})]),
																	_: 1
																})
															]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [createVNode(VBtn, {
												text: "+genus",
												onClick: ($event) => dialogFormGenus.value = !dialogFormGenus.value,
												variant: "elevated",
												size: "small",
												color: "deep-purple"
											}, null, 8, ["onClick"]), createVNode(VDialog, {
												modelValue: dialogFormGenus.value,
												"onUpdate:modelValue": ($event) => dialogFormGenus.value = $event,
												width: "600",
												transition: "dialog-left-transition",
												class: "border-3"
											}, {
												default: withCtx(({ isActive }) => [createVNode(VCard, null, {
													default: withCtx(() => [
														createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Form Genus")]),
															_: 1
														}),
														createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formGenus).name,
																			"onUpdate:modelValue": ($event) => unref(formGenus).name = $event,
																			label: "Name",
																			variant: "outlined",
																			density: "comfortable"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}), createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formGenus).nameLat,
																			"onUpdate:modelValue": ($event) => unref(formGenus).nameLat = $event,
																			label: "Latin",
																			variant: "outlined",
																			density: "comfortable"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})]),
																	_: 1
																}), createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formGenus).wiki,
																			"onUpdate:modelValue": ($event) => unref(formGenus).wiki = $event,
																			label: "WIKI",
																			variant: "solo",
																			density: "comfortable"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															}, 8, ["onSubmit"])]),
															_: 1
														}),
														createVNode(VCardActions, null, {
															default: withCtx(() => [createVNode(VDivider, {
																vertical: "",
																opacity: "0.91"
															}), createVNode(VBtn, {
																text: "store",
																onClick: storeGenus,
																variant: "elevated",
																density: "comfortable",
																color: "orange"
															})]),
															_: 1
														})
													]),
													_: 1
												})]),
												_: 1
											}, 8, ["modelValue", "onUpdate:modelValue"])];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VCol, { lg: "3" }, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: searchGenera.value,
											"onUpdate:modelValue": ($event) => searchGenera.value = $event,
											label: "Поиск",
											variant: "underlined",
											density: "comfortable",
											class: "rounded",
											"hide-details": ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}),
									createVNode(VCol, { lg: "1" }),
									createVNode(VCol, { cols: "2" }, {
										default: withCtx(() => [createVNode(VSwitch, {
											modelValue: showOnlyAgriculturable.value,
											"onUpdate:modelValue": ($event) => showOnlyAgriculturable.value = $event,
											label: "Показать только пригодные для сельского хозяйства",
											color: "green",
											inset: "",
											density: "compact"
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}),
									createVNode(VCol, { cols: "1" }, {
										default: withCtx(() => [createVNode(VBtn, {
											text: "+genus",
											onClick: ($event) => dialogFormGenus.value = !dialogFormGenus.value,
											variant: "elevated",
											size: "small",
											color: "deep-purple"
										}, null, 8, ["onClick"]), createVNode(VDialog, {
											modelValue: dialogFormGenus.value,
											"onUpdate:modelValue": ($event) => dialogFormGenus.value = $event,
											width: "600",
											transition: "dialog-left-transition",
											class: "border-3"
										}, {
											default: withCtx(({ isActive }) => [createVNode(VCard, null, {
												default: withCtx(() => [
													createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Form Genus")]),
														_: 1
													}),
													createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
															default: withCtx(() => [createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formGenus).name,
																		"onUpdate:modelValue": ($event) => unref(formGenus).name = $event,
																		label: "Name",
																		variant: "outlined",
																		density: "comfortable"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}), createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formGenus).nameLat,
																		"onUpdate:modelValue": ($event) => unref(formGenus).nameLat = $event,
																		label: "Latin",
																		variant: "outlined",
																		density: "comfortable"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																})]),
																_: 1
															}), createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formGenus).wiki,
																		"onUpdate:modelValue": ($event) => unref(formGenus).wiki = $event,
																		label: "WIKI",
																		variant: "solo",
																		density: "comfortable"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														}, 8, ["onSubmit"])]),
														_: 1
													}),
													createVNode(VCardActions, null, {
														default: withCtx(() => [createVNode(VDivider, {
															vertical: "",
															opacity: "0.91"
														}), createVNode(VBtn, {
															text: "store",
															onClick: storeGenus,
															variant: "elevated",
															density: "comfortable",
															color: "orange"
														})]),
														_: 1
													})
												]),
												_: 1
											})]),
											_: 1
										}, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { cols: "3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VDataTable, {
													items: filteredGenera.value,
													"items-per-page": "203",
													headers: headerGenera,
													"fixed-header": "",
													height: "906px",
													"fixed-footer": "",
													density: "compact",
													hover: ""
												}, {
													"item.agriculturable": withCtx(({ item }, _push, _parent, _scopeId) => {
														if (_push) if (item.agriculturable) _push(`<span class="mr-1" data-v-282bfc0c${_scopeId}>🌱</span>`);
														else _push(`<!---->`);
														else return [item.agriculturable ? (openBlock(), createBlock("span", {
															key: 0,
															class: "mr-1"
														}, "🌱")) : createCommentVNode("", true)];
													}),
													"item.nameLat": withCtx(({ item }, _push, _parent, _scopeId) => {
														if (_push) _push(`<span class="text-xs" data-v-282bfc0c${_scopeId}>${ssrInterpolate(item.nameLat)}</span>`);
														else return [createVNode("span", { class: "text-xs" }, toDisplayString(item.nameLat), 1)];
													}),
													"item.wiki": withCtx(({ item }, _push, _parent, _scopeId) => {
														if (_push) _push(`<a${ssrRenderAttr("href", item.wiki)} target="_blank" class="text-xs px-2 py-1 border" data-v-282bfc0c${_scopeId}>wiki</a>`);
														else return [createVNode("a", {
															href: item.wiki,
															target: "_blank",
															class: "text-xs px-2 py-1 border"
														}, "wiki", 8, ["href"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VList, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<!--[-->`);
															ssrRenderList(filteredGenera.value, (genus) => {
																_push(ssrRenderComponent(VListItem, {
																	key: genus.id,
																	class: ["text-sm", {
																		"bg-light-green-lighten-4": genus.agriculturable,
																		"bg-grey-lighten-4": !genus.agriculturable,
																		"border": true,
																		"border-green": genus.agriculturable,
																		"border-grey": !genus.agriculturable
																	}],
																	density: "compact",
																	variant: "elevated",
																	onClick: ($event) => toggleAgriculturable(genus)
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(`<div class="flex flex-row" data-v-282bfc0c${_scopeId}>`);
																			if (genus.agriculturable) _push(`<span class="mr-1" data-v-282bfc0c${_scopeId}>🌱</span>`);
																			else _push(`<!---->`);
																			_push(`<span data-v-282bfc0c${_scopeId}>${ssrInterpolate(genus.name)}</span><a${ssrRenderAttr("href", genus.wiki)} target="_blank" class="grow text-sm font-RubikMedium border rounded ml-2 elevation-2 text-center" data-v-282bfc0c${_scopeId}> wiki </a></div>`);
																		} else return [createVNode("div", { class: "flex flex-row" }, [
																			genus.agriculturable ? (openBlock(), createBlock("span", {
																				key: 0,
																				class: "mr-1"
																			}, "🌱")) : createCommentVNode("", true),
																			createVNode("span", null, toDisplayString(genus.name), 1),
																			createVNode("a", {
																				href: genus.wiki,
																				target: "_blank",
																				onClick: withModifiers(() => {}, ["stop"]),
																				class: "grow text-sm font-RubikMedium border rounded ml-2 elevation-2 text-center"
																			}, " wiki ", 8, ["href", "onClick"])
																		])];
																	}),
																	_: 2
																}, _parent, _scopeId));
															});
															_push(`<!--]-->`);
														} else return [(openBlock(true), createBlock(Fragment, null, renderList(filteredGenera.value, (genus) => {
															return openBlock(), createBlock(VListItem, {
																key: genus.id,
																class: ["text-sm", {
																	"bg-light-green-lighten-4": genus.agriculturable,
																	"bg-grey-lighten-4": !genus.agriculturable,
																	"border": true,
																	"border-green": genus.agriculturable,
																	"border-grey": !genus.agriculturable
																}],
																density: "compact",
																variant: "elevated",
																onClick: ($event) => toggleAgriculturable(genus)
															}, {
																default: withCtx(() => [createVNode("div", { class: "flex flex-row" }, [
																	genus.agriculturable ? (openBlock(), createBlock("span", {
																		key: 0,
																		class: "mr-1"
																	}, "🌱")) : createCommentVNode("", true),
																	createVNode("span", null, toDisplayString(genus.name), 1),
																	createVNode("a", {
																		href: genus.wiki,
																		target: "_blank",
																		onClick: withModifiers(() => {}, ["stop"]),
																		class: "grow text-sm font-RubikMedium border rounded ml-2 elevation-2 text-center"
																	}, " wiki ", 8, ["href", "onClick"])
																])]),
																_: 2
															}, 1032, ["class", "onClick"]);
														}), 128))];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [createVNode(VDataTable, {
												items: filteredGenera.value,
												"items-per-page": "203",
												headers: headerGenera,
												"fixed-header": "",
												height: "906px",
												"fixed-footer": "",
												density: "compact",
												hover: ""
											}, {
												"item.agriculturable": withCtx(({ item }) => [item.agriculturable ? (openBlock(), createBlock("span", {
													key: 0,
													class: "mr-1"
												}, "🌱")) : createCommentVNode("", true)]),
												"item.nameLat": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.nameLat), 1)]),
												"item.wiki": withCtx(({ item }) => [createVNode("a", {
													href: item.wiki,
													target: "_blank",
													class: "text-xs px-2 py-1 border"
												}, "wiki", 8, ["href"])]),
												_: 1
											}, 8, ["items"]), createVNode(VList, null, {
												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(filteredGenera.value, (genus) => {
													return openBlock(), createBlock(VListItem, {
														key: genus.id,
														class: ["text-sm", {
															"bg-light-green-lighten-4": genus.agriculturable,
															"bg-grey-lighten-4": !genus.agriculturable,
															"border": true,
															"border-green": genus.agriculturable,
															"border-grey": !genus.agriculturable
														}],
														density: "compact",
														variant: "elevated",
														onClick: ($event) => toggleAgriculturable(genus)
													}, {
														default: withCtx(() => [createVNode("div", { class: "flex flex-row" }, [
															genus.agriculturable ? (openBlock(), createBlock("span", {
																key: 0,
																class: "mr-1"
															}, "🌱")) : createCommentVNode("", true),
															createVNode("span", null, toDisplayString(genus.name), 1),
															createVNode("a", {
																href: genus.wiki,
																target: "_blank",
																onClick: withModifiers(() => {}, ["stop"]),
																class: "grow text-sm font-RubikMedium border rounded ml-2 elevation-2 text-center"
															}, " wiki ", 8, ["href", "onClick"])
														])]),
														_: 2
													}, 1032, ["class", "onClick"]);
												}), 128))]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "1" }, null, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "4" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VList, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(`<!--[-->`);
														ssrRenderList(plants.value, (plant) => {
															_push(ssrRenderComponent(VListItem, {
																variant: "text",
																density: "compact",
																color: "light-green-lighten-5",
																class: "text-xs"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`<span data-v-282bfc0c${_scopeId}>${ssrInterpolate(plant.name)}</span>`);
																	else return [createVNode("span", null, toDisplayString(plant.name), 1)];
																}),
																_: 2
															}, _parent, _scopeId));
														});
														_push(`<!--]-->`);
													} else return [(openBlock(true), createBlock(Fragment, null, renderList(plants.value, (plant) => {
														return openBlock(), createBlock(VListItem, {
															variant: "text",
															density: "compact",
															color: "light-green-lighten-5",
															class: "text-xs"
														}, {
															default: withCtx(() => [createVNode("span", null, toDisplayString(plant.name), 1)]),
															_: 2
														}, 1024);
													}), 256))];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VList, null, {
												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(plants.value, (plant) => {
													return openBlock(), createBlock(VListItem, {
														variant: "text",
														density: "compact",
														color: "light-green-lighten-5",
														class: "text-xs"
													}, {
														default: withCtx(() => [createVNode("span", null, toDisplayString(plant.name), 1)]),
														_: 2
													}, 1024);
												}), 256))]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
								} else return [
									createVNode(VCol, { cols: "3" }, {
										default: withCtx(() => [createVNode(VDataTable, {
											items: filteredGenera.value,
											"items-per-page": "203",
											headers: headerGenera,
											"fixed-header": "",
											height: "906px",
											"fixed-footer": "",
											density: "compact",
											hover: ""
										}, {
											"item.agriculturable": withCtx(({ item }) => [item.agriculturable ? (openBlock(), createBlock("span", {
												key: 0,
												class: "mr-1"
											}, "🌱")) : createCommentVNode("", true)]),
											"item.nameLat": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.nameLat), 1)]),
											"item.wiki": withCtx(({ item }) => [createVNode("a", {
												href: item.wiki,
												target: "_blank",
												class: "text-xs px-2 py-1 border"
											}, "wiki", 8, ["href"])]),
											_: 1
										}, 8, ["items"]), createVNode(VList, null, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(filteredGenera.value, (genus) => {
												return openBlock(), createBlock(VListItem, {
													key: genus.id,
													class: ["text-sm", {
														"bg-light-green-lighten-4": genus.agriculturable,
														"bg-grey-lighten-4": !genus.agriculturable,
														"border": true,
														"border-green": genus.agriculturable,
														"border-grey": !genus.agriculturable
													}],
													density: "compact",
													variant: "elevated",
													onClick: ($event) => toggleAgriculturable(genus)
												}, {
													default: withCtx(() => [createVNode("div", { class: "flex flex-row" }, [
														genus.agriculturable ? (openBlock(), createBlock("span", {
															key: 0,
															class: "mr-1"
														}, "🌱")) : createCommentVNode("", true),
														createVNode("span", null, toDisplayString(genus.name), 1),
														createVNode("a", {
															href: genus.wiki,
															target: "_blank",
															onClick: withModifiers(() => {}, ["stop"]),
															class: "grow text-sm font-RubikMedium border rounded ml-2 elevation-2 text-center"
														}, " wiki ", 8, ["href", "onClick"])
													])]),
													_: 2
												}, 1032, ["class", "onClick"]);
											}), 128))]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCol, { cols: "1" }),
									createVNode(VCol, { cols: "4" }, {
										default: withCtx(() => [createVNode(VList, null, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(plants.value, (plant) => {
												return openBlock(), createBlock(VListItem, {
													variant: "text",
													density: "compact",
													color: "light-green-lighten-5",
													class: "text-xs"
												}, {
													default: withCtx(() => [createVNode("span", null, toDisplayString(plant.name), 1)]),
													_: 2
												}, 1024);
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
							createVNode(VCol, { lg: "3" }, {
								default: withCtx(() => [createVNode(VTextField, {
									modelValue: searchGenera.value,
									"onUpdate:modelValue": ($event) => searchGenera.value = $event,
									label: "Поиск",
									variant: "underlined",
									density: "comfortable",
									class: "rounded",
									"hide-details": ""
								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
								_: 1
							}),
							createVNode(VCol, { lg: "1" }),
							createVNode(VCol, { cols: "2" }, {
								default: withCtx(() => [createVNode(VSwitch, {
									modelValue: showOnlyAgriculturable.value,
									"onUpdate:modelValue": ($event) => showOnlyAgriculturable.value = $event,
									label: "Показать только пригодные для сельского хозяйства",
									color: "green",
									inset: "",
									density: "compact"
								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
								_: 1
							}),
							createVNode(VCol, { cols: "1" }, {
								default: withCtx(() => [createVNode(VBtn, {
									text: "+genus",
									onClick: ($event) => dialogFormGenus.value = !dialogFormGenus.value,
									variant: "elevated",
									size: "small",
									color: "deep-purple"
								}, null, 8, ["onClick"]), createVNode(VDialog, {
									modelValue: dialogFormGenus.value,
									"onUpdate:modelValue": ($event) => dialogFormGenus.value = $event,
									width: "600",
									transition: "dialog-left-transition",
									class: "border-3"
								}, {
									default: withCtx(({ isActive }) => [createVNode(VCard, null, {
										default: withCtx(() => [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Form Genus")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
													default: withCtx(() => [createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: unref(formGenus).name,
																"onUpdate:modelValue": ($event) => unref(formGenus).name = $event,
																label: "Name",
																variant: "outlined",
																density: "comfortable"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}), createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: unref(formGenus).nameLat,
																"onUpdate:modelValue": ($event) => unref(formGenus).nameLat = $event,
																label: "Latin",
																variant: "outlined",
																density: "comfortable"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})]),
														_: 1
													}), createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: unref(formGenus).wiki,
																"onUpdate:modelValue": ($event) => unref(formGenus).wiki = $event,
																label: "WIKI",
																variant: "solo",
																density: "comfortable"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												}, 8, ["onSubmit"])]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VDivider, {
													vertical: "",
													opacity: "0.91"
												}), createVNode(VBtn, {
													text: "store",
													onClick: storeGenus,
													variant: "elevated",
													density: "comfortable",
													color: "orange"
												})]),
												_: 1
											})
										]),
										_: 1
									})]),
									_: 1
								}, 8, ["modelValue", "onUpdate:modelValue"])]),
								_: 1
							})
						]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol, { cols: "3" }, {
								default: withCtx(() => [createVNode(VDataTable, {
									items: filteredGenera.value,
									"items-per-page": "203",
									headers: headerGenera,
									"fixed-header": "",
									height: "906px",
									"fixed-footer": "",
									density: "compact",
									hover: ""
								}, {
									"item.agriculturable": withCtx(({ item }) => [item.agriculturable ? (openBlock(), createBlock("span", {
										key: 0,
										class: "mr-1"
									}, "🌱")) : createCommentVNode("", true)]),
									"item.nameLat": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.nameLat), 1)]),
									"item.wiki": withCtx(({ item }) => [createVNode("a", {
										href: item.wiki,
										target: "_blank",
										class: "text-xs px-2 py-1 border"
									}, "wiki", 8, ["href"])]),
									_: 1
								}, 8, ["items"]), createVNode(VList, null, {
									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(filteredGenera.value, (genus) => {
										return openBlock(), createBlock(VListItem, {
											key: genus.id,
											class: ["text-sm", {
												"bg-light-green-lighten-4": genus.agriculturable,
												"bg-grey-lighten-4": !genus.agriculturable,
												"border": true,
												"border-green": genus.agriculturable,
												"border-grey": !genus.agriculturable
											}],
											density: "compact",
											variant: "elevated",
											onClick: ($event) => toggleAgriculturable(genus)
										}, {
											default: withCtx(() => [createVNode("div", { class: "flex flex-row" }, [
												genus.agriculturable ? (openBlock(), createBlock("span", {
													key: 0,
													class: "mr-1"
												}, "🌱")) : createCommentVNode("", true),
												createVNode("span", null, toDisplayString(genus.name), 1),
												createVNode("a", {
													href: genus.wiki,
													target: "_blank",
													onClick: withModifiers(() => {}, ["stop"]),
													class: "grow text-sm font-RubikMedium border rounded ml-2 elevation-2 text-center"
												}, " wiki ", 8, ["href", "onClick"])
											])]),
											_: 2
										}, 1032, ["class", "onClick"]);
									}), 128))]),
									_: 1
								})]),
								_: 1
							}),
							createVNode(VCol, { cols: "1" }),
							createVNode(VCol, { cols: "4" }, {
								default: withCtx(() => [createVNode(VList, null, {
									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(plants.value, (plant) => {
										return openBlock(), createBlock(VListItem, {
											variant: "text",
											density: "compact",
											color: "light-green-lighten-5",
											class: "text-xs"
										}, {
											default: withCtx(() => [createVNode("span", null, toDisplayString(plant.name), 1)]),
											_: 2
										}, 1024);
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Botany.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var Botany_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-282bfc0c"]]);
//#endregion
export { Botany_default as default };

//# sourceMappingURL=Botany-C_CQxTRs.js.map