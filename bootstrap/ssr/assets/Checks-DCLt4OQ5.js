import { C as VRow, D as VDataTable, F as VCardText, I as VCardTitle, P as VCard, T as VContainer, U as VTextField, V as VAutocomplete, W as VMenu, h as VForm, rt as VBtn, w as VCol, x as VDatePicker, z as VDialog } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { Link, useForm } from "@inertiajs/vue3";
import { createTextVNode, createVNode, mergeProps, onMounted, ref, toDisplayString, unref, useSSRContext, vModelText, withCtx, withDirectives, withModifiers } from "vue";
import { ssrInterpolate, ssrRenderAttr, ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Ameise/Checks.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Checks",
	__ssrInlineRender: true,
	setup(__props) {
		const headersChecks = [
			{
				title: "id",
				key: "id"
			},
			{
				title: "date",
				key: "date"
			},
			{
				title: "Entity",
				key: "entity_id"
			},
			{
				title: "Amount",
				key: "amount"
			}
		];
		let checks = ref();
		function indexChecks() {
			axios.get(route("checks.index")).then(function(response) {
				checks.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let entities = ref();
		function indexEntities() {
			axios.get(route("entities.index")).then(function(response) {
				entities.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		const formCheck = useForm({
			date: null,
			entity_id: null,
			amount: null
		});
		function storeCheck() {
			formCheck.post(route("checks.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: true,
				onSuccess: () => {
					formCheck.reset();
					indexChecks();
				}
			});
		}
		onMounted(() => {
			indexChecks();
			indexEntities();
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
											if (_push) _push(ssrRenderComponent(VMenu, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VDatePicker, null, null, _parent, _scopeId));
													else return [createVNode(VDatePicker)];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VMenu, null, {
												default: withCtx(() => [createVNode(VDatePicker)]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "5" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VDialog, {
												transition: "dialog-top-transition",
												width: "602"
											}, {
												activator: withCtx(({ props: activatorProps }, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VBtn, mergeProps(activatorProps, {
														text: "+ check",
														block: ""
													}), null, _parent, _scopeId));
													else return [createVNode(VBtn, mergeProps(activatorProps, {
														text: "+ check",
														block: ""
													}), null, 16)];
												}),
												default: withCtx(({ isActive }, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VCard, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(ssrRenderComponent(VCardTitle, null, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(`Check form`);
																		else return [createTextVNode("Check form")];
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
																							if (_push) _push(ssrRenderComponent(VCol, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(ssrRenderComponent(VAutocomplete, {
																										items: unref(entities),
																										"item-title": "name",
																										"item-value": "id",
																										modelValue: unref(formCheck).entity_id,
																										"onUpdate:modelValue": ($event) => unref(formCheck).entity_id = $event,
																										label: "Сущность",
																										variant: "outlined",
																										density: "compact"
																									}, null, _parent, _scopeId));
																									else return [createVNode(VAutocomplete, {
																										items: unref(entities),
																										"item-title": "name",
																										"item-value": "id",
																										modelValue: unref(formCheck).entity_id,
																										"onUpdate:modelValue": ($event) => unref(formCheck).entity_id = $event,
																										label: "Сущность",
																										variant: "outlined",
																										density: "compact"
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
																									items: unref(entities),
																									"item-title": "name",
																									"item-value": "id",
																									modelValue: unref(formCheck).entity_id,
																									"onUpdate:modelValue": ($event) => unref(formCheck).entity_id = $event,
																									label: "Сущность",
																									variant: "outlined",
																									density: "compact"
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
																							if (_push) {
																								_push(ssrRenderComponent(VCol, null, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(`<input type="date"${ssrRenderAttr("value", unref(formCheck).date)}${_scopeId}>`);
																										else return [withDirectives(createVNode("input", {
																											type: "date",
																											"onUpdate:modelValue": ($event) => unref(formCheck).date = $event
																										}, null, 8, ["onUpdate:modelValue"]), [[vModelText, unref(formCheck).date]])];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								_push(ssrRenderComponent(VCol, null, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(ssrRenderComponent(VTextField, {
																											modelValue: unref(formCheck).amount,
																											"onUpdate:modelValue": ($event) => unref(formCheck).amount = $event,
																											label: "Amount",
																											density: "comfortable",
																											variant: "outlined"
																										}, null, _parent, _scopeId));
																										else return [createVNode(VTextField, {
																											modelValue: unref(formCheck).amount,
																											"onUpdate:modelValue": ($event) => unref(formCheck).amount = $event,
																											label: "Amount",
																											density: "comfortable",
																											variant: "outlined"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																							} else return [createVNode(VCol, null, {
																								default: withCtx(() => [withDirectives(createVNode("input", {
																									type: "date",
																									"onUpdate:modelValue": ($event) => unref(formCheck).date = $event
																								}, null, 8, ["onUpdate:modelValue"]), [[vModelText, unref(formCheck).date]])]),
																								_: 1
																							}), createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formCheck).amount,
																									"onUpdate:modelValue": ($event) => unref(formCheck).amount = $event,
																									label: "Amount",
																									density: "comfortable",
																									variant: "outlined"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							})];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VRow, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) {
																								_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
																								_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
																								_push(ssrRenderComponent(VCol, null, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(ssrRenderComponent(VBtn, {
																											text: "save",
																											variant: "outlined",
																											onClick: storeCheck
																										}, null, _parent, _scopeId));
																										else return [createVNode(VBtn, {
																											text: "save",
																											variant: "outlined",
																											onClick: storeCheck
																										})];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																							} else return [
																								createVNode(VCol),
																								createVNode(VCol),
																								createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VBtn, {
																										text: "save",
																										variant: "outlined",
																										onClick: storeCheck
																									})]),
																									_: 1
																								})
																							];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																				} else return [
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VAutocomplete, {
																								items: unref(entities),
																								"item-title": "name",
																								"item-value": "id",
																								modelValue: unref(formCheck).entity_id,
																								"onUpdate:modelValue": ($event) => unref(formCheck).entity_id = $event,
																								label: "Сущность",
																								variant: "outlined",
																								density: "compact"
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
																							default: withCtx(() => [withDirectives(createVNode("input", {
																								type: "date",
																								"onUpdate:modelValue": ($event) => unref(formCheck).date = $event
																							}, null, 8, ["onUpdate:modelValue"]), [[vModelText, unref(formCheck).date]])]),
																							_: 1
																						}), createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formCheck).amount,
																								"onUpdate:modelValue": ($event) => unref(formCheck).amount = $event,
																								label: "Amount",
																								density: "comfortable",
																								variant: "outlined"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						})]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [
																							createVNode(VCol),
																							createVNode(VCol),
																							createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VBtn, {
																									text: "save",
																									variant: "outlined",
																									onClick: storeCheck
																								})]),
																								_: 1
																							})
																						]),
																						_: 1
																					})
																				];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		else return [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																			default: withCtx(() => [
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VAutocomplete, {
																							items: unref(entities),
																							"item-title": "name",
																							"item-value": "id",
																							modelValue: unref(formCheck).entity_id,
																							"onUpdate:modelValue": ($event) => unref(formCheck).entity_id = $event,
																							label: "Сущность",
																							variant: "outlined",
																							density: "compact"
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
																						default: withCtx(() => [withDirectives(createVNode("input", {
																							type: "date",
																							"onUpdate:modelValue": ($event) => unref(formCheck).date = $event
																						}, null, 8, ["onUpdate:modelValue"]), [[vModelText, unref(formCheck).date]])]),
																						_: 1
																					}), createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formCheck).amount,
																							"onUpdate:modelValue": ($event) => unref(formCheck).amount = $event,
																							label: "Amount",
																							density: "comfortable",
																							variant: "outlined"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					})]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [
																						createVNode(VCol),
																						createVNode(VCol),
																						createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VBtn, {
																								text: "save",
																								variant: "outlined",
																								onClick: storeCheck
																							})]),
																							_: 1
																						})
																					]),
																					_: 1
																				})
																			]),
																			_: 1
																		}, 8, ["onSubmit"])];
																	}),
																	_: 2
																}, _parent, _scopeId));
															} else return [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Check form")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																	default: withCtx(() => [
																		createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VAutocomplete, {
																					items: unref(entities),
																					"item-title": "name",
																					"item-value": "id",
																					modelValue: unref(formCheck).entity_id,
																					"onUpdate:modelValue": ($event) => unref(formCheck).entity_id = $event,
																					label: "Сущность",
																					variant: "outlined",
																					density: "compact"
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
																				default: withCtx(() => [withDirectives(createVNode("input", {
																					type: "date",
																					"onUpdate:modelValue": ($event) => unref(formCheck).date = $event
																				}, null, 8, ["onUpdate:modelValue"]), [[vModelText, unref(formCheck).date]])]),
																				_: 1
																			}), createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formCheck).amount,
																					"onUpdate:modelValue": ($event) => unref(formCheck).amount = $event,
																					label: "Amount",
																					density: "comfortable",
																					variant: "outlined"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			})]),
																			_: 1
																		}),
																		createVNode(VRow, null, {
																			default: withCtx(() => [
																				createVNode(VCol),
																				createVNode(VCol),
																				createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VBtn, {
																						text: "save",
																						variant: "outlined",
																						onClick: storeCheck
																					})]),
																					_: 1
																				})
																			]),
																			_: 1
																		})
																	]),
																	_: 1
																}, 8, ["onSubmit"])]),
																_: 1
															})];
														}),
														_: 2
													}, _parent, _scopeId));
													else return [createVNode(VCard, null, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Check form")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																default: withCtx(() => [
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VAutocomplete, {
																				items: unref(entities),
																				"item-title": "name",
																				"item-value": "id",
																				modelValue: unref(formCheck).entity_id,
																				"onUpdate:modelValue": ($event) => unref(formCheck).entity_id = $event,
																				label: "Сущность",
																				variant: "outlined",
																				density: "compact"
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
																			default: withCtx(() => [withDirectives(createVNode("input", {
																				type: "date",
																				"onUpdate:modelValue": ($event) => unref(formCheck).date = $event
																			}, null, 8, ["onUpdate:modelValue"]), [[vModelText, unref(formCheck).date]])]),
																			_: 1
																		}), createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formCheck).amount,
																				"onUpdate:modelValue": ($event) => unref(formCheck).amount = $event,
																				label: "Amount",
																				density: "comfortable",
																				variant: "outlined"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		})]),
																		_: 1
																	}),
																	createVNode(VRow, null, {
																		default: withCtx(() => [
																			createVNode(VCol),
																			createVNode(VCol),
																			createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VBtn, {
																					text: "save",
																					variant: "outlined",
																					onClick: storeCheck
																				})]),
																				_: 1
																			})
																		]),
																		_: 1
																	})
																]),
																_: 1
															}, 8, ["onSubmit"])]),
															_: 1
														})]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VDialog, {
												transition: "dialog-top-transition",
												width: "602"
											}, {
												activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
													text: "+ check",
													block: ""
												}), null, 16)]),
												default: withCtx(({ isActive }) => [createVNode(VCard, null, {
													default: withCtx(() => [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Check form")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
															default: withCtx(() => [
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VAutocomplete, {
																			items: unref(entities),
																			"item-title": "name",
																			"item-value": "id",
																			modelValue: unref(formCheck).entity_id,
																			"onUpdate:modelValue": ($event) => unref(formCheck).entity_id = $event,
																			label: "Сущность",
																			variant: "outlined",
																			density: "compact"
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
																		default: withCtx(() => [withDirectives(createVNode("input", {
																			type: "date",
																			"onUpdate:modelValue": ($event) => unref(formCheck).date = $event
																		}, null, 8, ["onUpdate:modelValue"]), [[vModelText, unref(formCheck).date]])]),
																		_: 1
																	}), createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formCheck).amount,
																			"onUpdate:modelValue": ($event) => unref(formCheck).amount = $event,
																			label: "Amount",
																			density: "comfortable",
																			variant: "outlined"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})]),
																	_: 1
																}),
																createVNode(VRow, null, {
																	default: withCtx(() => [
																		createVNode(VCol),
																		createVNode(VCol),
																		createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VBtn, {
																				text: "save",
																				variant: "outlined",
																				onClick: storeCheck
																			})]),
																			_: 1
																		})
																	]),
																	_: 1
																})
															]),
															_: 1
														}, 8, ["onSubmit"])]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, { cols: "7" }, {
									default: withCtx(() => [createVNode(VMenu, null, {
										default: withCtx(() => [createVNode(VDatePicker)]),
										_: 1
									})]),
									_: 1
								}), createVNode(VCol, { cols: "5" }, {
									default: withCtx(() => [createVNode(VDialog, {
										transition: "dialog-top-transition",
										width: "602"
									}, {
										activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
											text: "+ check",
											block: ""
										}), null, 16)]),
										default: withCtx(({ isActive }) => [createVNode(VCard, null, {
											default: withCtx(() => [createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Check form")]),
												_: 1
											}), createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
													default: withCtx(() => [
														createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VAutocomplete, {
																	items: unref(entities),
																	"item-title": "name",
																	"item-value": "id",
																	modelValue: unref(formCheck).entity_id,
																	"onUpdate:modelValue": ($event) => unref(formCheck).entity_id = $event,
																	label: "Сущность",
																	variant: "outlined",
																	density: "compact"
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
																default: withCtx(() => [withDirectives(createVNode("input", {
																	type: "date",
																	"onUpdate:modelValue": ($event) => unref(formCheck).date = $event
																}, null, 8, ["onUpdate:modelValue"]), [[vModelText, unref(formCheck).date]])]),
																_: 1
															}), createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(formCheck).amount,
																	"onUpdate:modelValue": ($event) => unref(formCheck).amount = $event,
																	label: "Amount",
																	density: "comfortable",
																	variant: "outlined"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})]),
															_: 1
														}),
														createVNode(VRow, null, {
															default: withCtx(() => [
																createVNode(VCol),
																createVNode(VCol),
																createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VBtn, {
																		text: "save",
																		variant: "outlined",
																		onClick: storeCheck
																	})]),
																	_: 1
																})
															]),
															_: 1
														})
													]),
													_: 1
												}, 8, ["onSubmit"])]),
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
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { cols: "2" }, null, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "8" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VDataTable, {
												items: unref(checks),
												headers: headersChecks,
												"items-per-page": "125",
												density: "compact",
												hover: "hover"
											}, {
												"item.entity_id": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(item.entity.name)}`);
													else return [createTextVNode(toDisplayString(item.entity.name), 1)];
												}),
												"item.amount": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(unref(Link), { href: _ctx.route("checks.show", item.id) }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(`${ssrInterpolate(item.amount)}`);
															else return [createTextVNode(toDisplayString(item.amount), 1)];
														}),
														_: 2
													}, _parent, _scopeId));
													else return [createVNode(unref(Link), { href: _ctx.route("checks.show", item.id) }, {
														default: withCtx(() => [createTextVNode(toDisplayString(item.amount), 1)]),
														_: 2
													}, 1032, ["href"])];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VDataTable, {
												items: unref(checks),
												headers: headersChecks,
												"items-per-page": "125",
												density: "compact",
												hover: "hover"
											}, {
												"item.entity_id": withCtx(({ item }) => [createTextVNode(toDisplayString(item.entity.name), 1)]),
												"item.amount": withCtx(({ item }) => [createVNode(unref(Link), { href: _ctx.route("checks.show", item.id) }, {
													default: withCtx(() => [createTextVNode(toDisplayString(item.amount), 1)]),
													_: 2
												}, 1032, ["href"])]),
												_: 1
											}, 8, ["items"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "2" }, null, _parent, _scopeId));
								} else return [
									createVNode(VCol, { cols: "2" }),
									createVNode(VCol, { cols: "8" }, {
										default: withCtx(() => [createVNode(VDataTable, {
											items: unref(checks),
											headers: headersChecks,
											"items-per-page": "125",
											density: "compact",
											hover: "hover"
										}, {
											"item.entity_id": withCtx(({ item }) => [createTextVNode(toDisplayString(item.entity.name), 1)]),
											"item.amount": withCtx(({ item }) => [createVNode(unref(Link), { href: _ctx.route("checks.show", item.id) }, {
												default: withCtx(() => [createTextVNode(toDisplayString(item.amount), 1)]),
												_: 2
											}, 1032, ["href"])]),
											_: 1
										}, 8, ["items"])]),
										_: 1
									}),
									createVNode(VCol, { cols: "2" })
								];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
							default: withCtx(() => [createVNode(VMenu, null, {
								default: withCtx(() => [createVNode(VDatePicker)]),
								_: 1
							})]),
							_: 1
						}), createVNode(VCol, { cols: "5" }, {
							default: withCtx(() => [createVNode(VDialog, {
								transition: "dialog-top-transition",
								width: "602"
							}, {
								activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, {
									text: "+ check",
									block: ""
								}), null, 16)]),
								default: withCtx(({ isActive }) => [createVNode(VCard, null, {
									default: withCtx(() => [createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Check form")]),
										_: 1
									}), createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
											default: withCtx(() => [
												createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VAutocomplete, {
															items: unref(entities),
															"item-title": "name",
															"item-value": "id",
															modelValue: unref(formCheck).entity_id,
															"onUpdate:modelValue": ($event) => unref(formCheck).entity_id = $event,
															label: "Сущность",
															variant: "outlined",
															density: "compact"
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
														default: withCtx(() => [withDirectives(createVNode("input", {
															type: "date",
															"onUpdate:modelValue": ($event) => unref(formCheck).date = $event
														}, null, 8, ["onUpdate:modelValue"]), [[vModelText, unref(formCheck).date]])]),
														_: 1
													}), createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: unref(formCheck).amount,
															"onUpdate:modelValue": ($event) => unref(formCheck).amount = $event,
															label: "Amount",
															density: "comfortable",
															variant: "outlined"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VRow, null, {
													default: withCtx(() => [
														createVNode(VCol),
														createVNode(VCol),
														createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VBtn, {
																text: "save",
																variant: "outlined",
																onClick: storeCheck
															})]),
															_: 1
														})
													]),
													_: 1
												})
											]),
											_: 1
										}, 8, ["onSubmit"])]),
										_: 1
									})]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						})]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol, { cols: "2" }),
							createVNode(VCol, { cols: "8" }, {
								default: withCtx(() => [createVNode(VDataTable, {
									items: unref(checks),
									headers: headersChecks,
									"items-per-page": "125",
									density: "compact",
									hover: "hover"
								}, {
									"item.entity_id": withCtx(({ item }) => [createTextVNode(toDisplayString(item.entity.name), 1)]),
									"item.amount": withCtx(({ item }) => [createVNode(unref(Link), { href: _ctx.route("checks.show", item.id) }, {
										default: withCtx(() => [createTextVNode(toDisplayString(item.amount), 1)]),
										_: 2
									}, 1032, ["href"])]),
									_: 1
								}, 8, ["items"])]),
								_: 1
							}),
							createVNode(VCol, { cols: "2" })
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Checks.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Checks-DCLt4OQ5.js.map