import { C as VRow, D as VDataTable, H as VSelect, T as VContainer, U as VTextField, V as VAutocomplete, dt as useDate, h as VForm, rt as VBtn, w as VCol } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { Link, useForm } from "@inertiajs/vue3";
import { createBlock, createCommentVNode, createTextVNode, createVNode, isRef, mergeProps, onMounted, openBlock, ref, toDisplayString, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderComponent } from "vue/server-renderer";
import "postcss";
//#region resources/js/Pages/Ameise/Check.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Check",
	__ssrInlineRender: true,
	props: { check: Object },
	setup(__props) {
		const props = __props;
		const date = useDate();
		const commodities = ref([]);
		const commoditiesInCheck = ref([]);
		function indexCommodities() {
			axios.get(route("commodities.index")).then(function(response) {
				commodities.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let dialogAddCommodity = ref(false);
		const headersCommodities = [
			{
				title: "name",
				key: "name",
				width: "60%"
			},
			{
				title: "quantity",
				key: "pivot.quantity"
			},
			{
				title: "measure",
				key: "pivot.measure_id"
			},
			{
				title: "price",
				key: "pivot.price"
			},
			{
				title: "total_price",
				key: "pivot.total_price",
				class: "bg-green-300"
			}
		];
		const formCommodityCheck = useForm({
			check_id: null,
			commodity_id: null,
			quantity: null,
			measure_id: null,
			price: null
		});
		function storeCommodityToCheck() {
			formCommodityCheck.check_id = props.check.id;
			formCommodityCheck.post(route("web.checkcommodity.store"), {
				replace: true,
				preserveState: true,
				preserveScroll: false,
				onSuccess: () => {
					formCommodityCheck.reset();
					commoditiesInCheck.value = props.check.commodities;
				}
			});
		}
		let measures = ref();
		function indexMeasures() {
			axios.get(route("measures.index")).then(function(response) {
				measures.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		onMounted(() => {
			commoditiesInCheck.value = props.check.commodities;
			indexCommodities();
			indexMeasures();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ class: "border border-1 rounded" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, { class: "rounded border border-black-1 mx-10" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(unref(date).format(__props.check.date, "fullDate"))}`);
											else return [createTextVNode(toDisplayString(unref(date).format(__props.check.date, "fullDate")), 1)];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(unref(Link), {
												href: unref(route)("entities.show", __props.check.entity.id),
												class: "text-xl font-RubikMedium"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(__props.check.entity.name)}`);
													else return [createTextVNode(toDisplayString(__props.check.entity.name), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(unref(Link), {
												href: unref(route)("entities.show", __props.check.entity.id),
												class: "text-xl font-RubikMedium"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(__props.check.entity.name), 1)]),
												_: 1
											}, 8, ["href"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(__props.check.amount)}`);
											else return [createTextVNode(toDisplayString(__props.check.amount), 1)];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { lg: "1" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VBtn, {
												text: "+ commodity",
												onClick: ($event) => isRef(dialogAddCommodity) ? dialogAddCommodity.value = !unref(dialogAddCommodity) : dialogAddCommodity = !unref(dialogAddCommodity)
											}, null, _parent, _scopeId));
											else return [createVNode(VBtn, {
												text: "+ commodity",
												onClick: ($event) => isRef(dialogAddCommodity) ? dialogAddCommodity.value = !unref(dialogAddCommodity) : dialogAddCommodity = !unref(dialogAddCommodity)
											}, null, 8, ["onClick"])];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VCol, null, {
										default: withCtx(() => [createTextVNode(toDisplayString(unref(date).format(__props.check.date, "fullDate")), 1)]),
										_: 1
									}),
									createVNode(VCol, null, {
										default: withCtx(() => [createVNode(unref(Link), {
											href: unref(route)("entities.show", __props.check.entity.id),
											class: "text-xl font-RubikMedium"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(__props.check.entity.name), 1)]),
											_: 1
										}, 8, ["href"])]),
										_: 1
									}),
									createVNode(VCol, null, {
										default: withCtx(() => [createTextVNode(toDisplayString(__props.check.amount), 1)]),
										_: 1
									}),
									createVNode(VCol, { lg: "1" }, {
										default: withCtx(() => [createVNode(VBtn, {
											text: "+ commodity",
											onClick: ($event) => isRef(dialogAddCommodity) ? dialogAddCommodity.value = !unref(dialogAddCommodity) : dialogAddCommodity = !unref(dialogAddCommodity)
										}, null, 8, ["onClick"])]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { lg: "6" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VDataTable, {
												items: commoditiesInCheck.value,
												headers: headersCommodities,
												density: "comfortable",
												hover: ""
											}, {
												top: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) if (unref(dialogAddCommodity)) _push(ssrRenderComponent(VForm, {
														onSubmit: () => {},
														class: "bg-slate-600 p-2 border border-1 border-lime-950 rounded"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(ssrRenderComponent(VRow, null, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VCol, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VAutocomplete, {
																					items: commodities.value,
																					"item-value": "id",
																					"item-title": "name",
																					modelValue: unref(formCommodityCheck).commodity_id,
																					"onUpdate:modelValue": ($event) => unref(formCommodityCheck).commodity_id = $event,
																					label: "Commodities",
																					variant: "solo",
																					density: "comfortable",
																					"hide-details": ""
																				}, null, _parent, _scopeId));
																				else return [createVNode(VAutocomplete, {
																					items: commodities.value,
																					"item-value": "id",
																					"item-title": "name",
																					modelValue: unref(formCommodityCheck).commodity_id,
																					"onUpdate:modelValue": ($event) => unref(formCommodityCheck).commodity_id = $event,
																					label: "Commodities",
																					variant: "solo",
																					density: "comfortable",
																					"hide-details": ""
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
																				items: commodities.value,
																				"item-value": "id",
																				"item-title": "name",
																				modelValue: unref(formCommodityCheck).commodity_id,
																				"onUpdate:modelValue": ($event) => unref(formCommodityCheck).commodity_id = $event,
																				label: "Commodities",
																				variant: "solo",
																				density: "comfortable",
																				"hide-details": ""
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
																			_push(ssrRenderComponent(VCol, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VTextField, {
																						modelValue: unref(formCommodityCheck).quantity,
																						"onUpdate:modelValue": ($event) => unref(formCommodityCheck).quantity = $event,
																						label: "Кол-во",
																						variant: "solo-filled",
																						density: "comfortable",
																						"hide-details": ""
																					}, null, _parent, _scopeId));
																					else return [createVNode(VTextField, {
																						modelValue: unref(formCommodityCheck).quantity,
																						"onUpdate:modelValue": ($event) => unref(formCommodityCheck).quantity = $event,
																						label: "Кол-во",
																						variant: "solo-filled",
																						density: "comfortable",
																						"hide-details": ""
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VCol, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VSelect, {
																						items: unref(measures),
																						"item-value": "id",
																						"item-title": "name",
																						modelValue: unref(formCommodityCheck).measure_id,
																						"onUpdate:modelValue": ($event) => unref(formCommodityCheck).measure_id = $event,
																						variant: "solo",
																						density: "comfortable",
																						"hide-details": ""
																					}, null, _parent, _scopeId));
																					else return [createVNode(VSelect, {
																						items: unref(measures),
																						"item-value": "id",
																						"item-title": "name",
																						modelValue: unref(formCommodityCheck).measure_id,
																						"onUpdate:modelValue": ($event) => unref(formCommodityCheck).measure_id = $event,
																						variant: "solo",
																						density: "comfortable",
																						"hide-details": ""
																					}, null, 8, [
																						"items",
																						"modelValue",
																						"onUpdate:modelValue"
																					])];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VCol, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VTextField, {
																						modelValue: unref(formCommodityCheck).price,
																						"onUpdate:modelValue": ($event) => unref(formCommodityCheck).price = $event,
																						label: "Цена",
																						variant: "solo-filled",
																						density: "comfortable",
																						class: "bg-cyan-700",
																						"hide-details": ""
																					}, null, _parent, _scopeId));
																					else return [createVNode(VTextField, {
																						modelValue: unref(formCommodityCheck).price,
																						"onUpdate:modelValue": ($event) => unref(formCommodityCheck).price = $event,
																						label: "Цена",
																						variant: "solo-filled",
																						density: "comfortable",
																						class: "bg-cyan-700",
																						"hide-details": ""
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																		} else return [
																			createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formCommodityCheck).quantity,
																					"onUpdate:modelValue": ($event) => unref(formCommodityCheck).quantity = $event,
																					label: "Кол-во",
																					variant: "solo-filled",
																					density: "comfortable",
																					"hide-details": ""
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VSelect, {
																					items: unref(measures),
																					"item-value": "id",
																					"item-title": "name",
																					modelValue: unref(formCommodityCheck).measure_id,
																					"onUpdate:modelValue": ($event) => unref(formCommodityCheck).measure_id = $event,
																					variant: "solo",
																					density: "comfortable",
																					"hide-details": ""
																				}, null, 8, [
																					"items",
																					"modelValue",
																					"onUpdate:modelValue"
																				])]),
																				_: 1
																			}),
																			createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formCommodityCheck).price,
																					"onUpdate:modelValue": ($event) => unref(formCommodityCheck).price = $event,
																					label: "Цена",
																					variant: "solo-filled",
																					density: "comfortable",
																					class: "bg-cyan-700",
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
																			_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
																			_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
																			_push(ssrRenderComponent(VCol, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VBtn, {
																						onClick: storeCommodityToCheck,
																						text: "store",
																						variant: "outlined",
																						density: "compact"
																					}, null, _parent, _scopeId));
																					else return [createVNode(VBtn, {
																						onClick: storeCommodityToCheck,
																						text: "store",
																						variant: "outlined",
																						density: "compact"
																					})];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																		} else return [
																			createVNode(VCol),
																			createVNode(VCol),
																			createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VBtn, {
																					onClick: storeCommodityToCheck,
																					text: "store",
																					variant: "outlined",
																					density: "compact"
																				})]),
																				_: 1
																			})
																		];
																	}),
																	_: 1
																}, _parent, _scopeId));
															} else return [
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VAutocomplete, {
																			items: commodities.value,
																			"item-value": "id",
																			"item-title": "name",
																			modelValue: unref(formCommodityCheck).commodity_id,
																			"onUpdate:modelValue": ($event) => unref(formCommodityCheck).commodity_id = $event,
																			label: "Commodities",
																			variant: "solo",
																			density: "comfortable",
																			"hide-details": ""
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
																	default: withCtx(() => [
																		createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formCommodityCheck).quantity,
																				"onUpdate:modelValue": ($event) => unref(formCommodityCheck).quantity = $event,
																				label: "Кол-во",
																				variant: "solo-filled",
																				density: "comfortable",
																				"hide-details": ""
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VSelect, {
																				items: unref(measures),
																				"item-value": "id",
																				"item-title": "name",
																				modelValue: unref(formCommodityCheck).measure_id,
																				"onUpdate:modelValue": ($event) => unref(formCommodityCheck).measure_id = $event,
																				variant: "solo",
																				density: "comfortable",
																				"hide-details": ""
																			}, null, 8, [
																				"items",
																				"modelValue",
																				"onUpdate:modelValue"
																			])]),
																			_: 1
																		}),
																		createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formCommodityCheck).price,
																				"onUpdate:modelValue": ($event) => unref(formCommodityCheck).price = $event,
																				label: "Цена",
																				variant: "solo-filled",
																				density: "comfortable",
																				class: "bg-cyan-700",
																				"hide-details": ""
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		})
																	]),
																	_: 1
																}),
																createVNode(VRow, null, {
																	default: withCtx(() => [
																		createVNode(VCol),
																		createVNode(VCol),
																		createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VBtn, {
																				onClick: storeCommodityToCheck,
																				text: "store",
																				variant: "outlined",
																				density: "compact"
																			})]),
																			_: 1
																		})
																	]),
																	_: 1
																})
															];
														}),
														_: 1
													}, _parent, _scopeId));
													else _push(`<!---->`);
													else return [unref(dialogAddCommodity) ? (openBlock(), createBlock(VForm, {
														key: 0,
														onSubmit: withModifiers(() => {}, ["prevent"]),
														class: "bg-slate-600 p-2 border border-1 border-lime-950 rounded"
													}, {
														default: withCtx(() => [
															createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VAutocomplete, {
																		items: commodities.value,
																		"item-value": "id",
																		"item-title": "name",
																		modelValue: unref(formCommodityCheck).commodity_id,
																		"onUpdate:modelValue": ($event) => unref(formCommodityCheck).commodity_id = $event,
																		label: "Commodities",
																		variant: "solo",
																		density: "comfortable",
																		"hide-details": ""
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
																default: withCtx(() => [
																	createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formCommodityCheck).quantity,
																			"onUpdate:modelValue": ($event) => unref(formCommodityCheck).quantity = $event,
																			label: "Кол-во",
																			variant: "solo-filled",
																			density: "comfortable",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VSelect, {
																			items: unref(measures),
																			"item-value": "id",
																			"item-title": "name",
																			modelValue: unref(formCommodityCheck).measure_id,
																			"onUpdate:modelValue": ($event) => unref(formCommodityCheck).measure_id = $event,
																			variant: "solo",
																			density: "comfortable",
																			"hide-details": ""
																		}, null, 8, [
																			"items",
																			"modelValue",
																			"onUpdate:modelValue"
																		])]),
																		_: 1
																	}),
																	createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formCommodityCheck).price,
																			"onUpdate:modelValue": ($event) => unref(formCommodityCheck).price = $event,
																			label: "Цена",
																			variant: "solo-filled",
																			density: "comfortable",
																			class: "bg-cyan-700",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})
																]),
																_: 1
															}),
															createVNode(VRow, null, {
																default: withCtx(() => [
																	createVNode(VCol),
																	createVNode(VCol),
																	createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VBtn, {
																			onClick: storeCommodityToCheck,
																			text: "store",
																			variant: "outlined",
																			density: "compact"
																		})]),
																		_: 1
																	})
																]),
																_: 1
															})
														]),
														_: 1
													}, 8, ["onSubmit"])) : createCommentVNode("", true)];
												}),
												"item.name": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) _push(`<span class="text-sm font-sans text-zinc-800"${_scopeId}>${ssrInterpolate(item.name)}</span>`);
													else return [createVNode("span", { class: "text-sm font-sans text-zinc-800" }, toDisplayString(item.name), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VDataTable, {
												items: commoditiesInCheck.value,
												headers: headersCommodities,
												density: "comfortable",
												hover: ""
											}, {
												top: withCtx(() => [unref(dialogAddCommodity) ? (openBlock(), createBlock(VForm, {
													key: 0,
													onSubmit: withModifiers(() => {}, ["prevent"]),
													class: "bg-slate-600 p-2 border border-1 border-lime-950 rounded"
												}, {
													default: withCtx(() => [
														createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VAutocomplete, {
																	items: commodities.value,
																	"item-value": "id",
																	"item-title": "name",
																	modelValue: unref(formCommodityCheck).commodity_id,
																	"onUpdate:modelValue": ($event) => unref(formCommodityCheck).commodity_id = $event,
																	label: "Commodities",
																	variant: "solo",
																	density: "comfortable",
																	"hide-details": ""
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
															default: withCtx(() => [
																createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formCommodityCheck).quantity,
																		"onUpdate:modelValue": ($event) => unref(formCommodityCheck).quantity = $event,
																		label: "Кол-во",
																		variant: "solo-filled",
																		density: "comfortable",
																		"hide-details": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VSelect, {
																		items: unref(measures),
																		"item-value": "id",
																		"item-title": "name",
																		modelValue: unref(formCommodityCheck).measure_id,
																		"onUpdate:modelValue": ($event) => unref(formCommodityCheck).measure_id = $event,
																		variant: "solo",
																		density: "comfortable",
																		"hide-details": ""
																	}, null, 8, [
																		"items",
																		"modelValue",
																		"onUpdate:modelValue"
																	])]),
																	_: 1
																}),
																createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formCommodityCheck).price,
																		"onUpdate:modelValue": ($event) => unref(formCommodityCheck).price = $event,
																		label: "Цена",
																		variant: "solo-filled",
																		density: "comfortable",
																		class: "bg-cyan-700",
																		"hide-details": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																})
															]),
															_: 1
														}),
														createVNode(VRow, null, {
															default: withCtx(() => [
																createVNode(VCol),
																createVNode(VCol),
																createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VBtn, {
																		onClick: storeCommodityToCheck,
																		text: "store",
																		variant: "outlined",
																		density: "compact"
																	})]),
																	_: 1
																})
															]),
															_: 1
														})
													]),
													_: 1
												}, 8, ["onSubmit"])) : createCommentVNode("", true)]),
												"item.name": withCtx(({ item }) => [createVNode("span", { class: "text-sm font-sans text-zinc-800" }, toDisplayString(item.name), 1)]),
												_: 1
											}, 8, ["items"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
								} else return [
									createVNode(VCol),
									createVNode(VCol, { lg: "6" }, {
										default: withCtx(() => [createVNode(VDataTable, {
											items: commoditiesInCheck.value,
											headers: headersCommodities,
											density: "comfortable",
											hover: ""
										}, {
											top: withCtx(() => [unref(dialogAddCommodity) ? (openBlock(), createBlock(VForm, {
												key: 0,
												onSubmit: withModifiers(() => {}, ["prevent"]),
												class: "bg-slate-600 p-2 border border-1 border-lime-950 rounded"
											}, {
												default: withCtx(() => [
													createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VAutocomplete, {
																items: commodities.value,
																"item-value": "id",
																"item-title": "name",
																modelValue: unref(formCommodityCheck).commodity_id,
																"onUpdate:modelValue": ($event) => unref(formCommodityCheck).commodity_id = $event,
																label: "Commodities",
																variant: "solo",
																density: "comfortable",
																"hide-details": ""
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
														default: withCtx(() => [
															createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(formCommodityCheck).quantity,
																	"onUpdate:modelValue": ($event) => unref(formCommodityCheck).quantity = $event,
																	label: "Кол-во",
																	variant: "solo-filled",
																	density: "comfortable",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VSelect, {
																	items: unref(measures),
																	"item-value": "id",
																	"item-title": "name",
																	modelValue: unref(formCommodityCheck).measure_id,
																	"onUpdate:modelValue": ($event) => unref(formCommodityCheck).measure_id = $event,
																	variant: "solo",
																	density: "comfortable",
																	"hide-details": ""
																}, null, 8, [
																	"items",
																	"modelValue",
																	"onUpdate:modelValue"
																])]),
																_: 1
															}),
															createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(formCommodityCheck).price,
																	"onUpdate:modelValue": ($event) => unref(formCommodityCheck).price = $event,
																	label: "Цена",
																	variant: "solo-filled",
																	density: "comfortable",
																	class: "bg-cyan-700",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})
														]),
														_: 1
													}),
													createVNode(VRow, null, {
														default: withCtx(() => [
															createVNode(VCol),
															createVNode(VCol),
															createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VBtn, {
																	onClick: storeCommodityToCheck,
																	text: "store",
																	variant: "outlined",
																	density: "compact"
																})]),
																_: 1
															})
														]),
														_: 1
													})
												]),
												_: 1
											}, 8, ["onSubmit"])) : createCommentVNode("", true)]),
											"item.name": withCtx(({ item }) => [createVNode("span", { class: "text-sm font-sans text-zinc-800" }, toDisplayString(item.name), 1)]),
											_: 1
										}, 8, ["items"])]),
										_: 1
									}),
									createVNode(VCol)
								];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, { class: "rounded border border-black-1 mx-10" }, {
						default: withCtx(() => [
							createVNode(VCol, null, {
								default: withCtx(() => [createTextVNode(toDisplayString(unref(date).format(__props.check.date, "fullDate")), 1)]),
								_: 1
							}),
							createVNode(VCol, null, {
								default: withCtx(() => [createVNode(unref(Link), {
									href: unref(route)("entities.show", __props.check.entity.id),
									class: "text-xl font-RubikMedium"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(__props.check.entity.name), 1)]),
									_: 1
								}, 8, ["href"])]),
								_: 1
							}),
							createVNode(VCol, null, {
								default: withCtx(() => [createTextVNode(toDisplayString(__props.check.amount), 1)]),
								_: 1
							}),
							createVNode(VCol, { lg: "1" }, {
								default: withCtx(() => [createVNode(VBtn, {
									text: "+ commodity",
									onClick: ($event) => isRef(dialogAddCommodity) ? dialogAddCommodity.value = !unref(dialogAddCommodity) : dialogAddCommodity = !unref(dialogAddCommodity)
								}, null, 8, ["onClick"])]),
								_: 1
							})
						]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol),
							createVNode(VCol, { lg: "6" }, {
								default: withCtx(() => [createVNode(VDataTable, {
									items: commoditiesInCheck.value,
									headers: headersCommodities,
									density: "comfortable",
									hover: ""
								}, {
									top: withCtx(() => [unref(dialogAddCommodity) ? (openBlock(), createBlock(VForm, {
										key: 0,
										onSubmit: withModifiers(() => {}, ["prevent"]),
										class: "bg-slate-600 p-2 border border-1 border-lime-950 rounded"
									}, {
										default: withCtx(() => [
											createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VAutocomplete, {
														items: commodities.value,
														"item-value": "id",
														"item-title": "name",
														modelValue: unref(formCommodityCheck).commodity_id,
														"onUpdate:modelValue": ($event) => unref(formCommodityCheck).commodity_id = $event,
														label: "Commodities",
														variant: "solo",
														density: "comfortable",
														"hide-details": ""
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
												default: withCtx(() => [
													createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: unref(formCommodityCheck).quantity,
															"onUpdate:modelValue": ($event) => unref(formCommodityCheck).quantity = $event,
															label: "Кол-во",
															variant: "solo-filled",
															density: "comfortable",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VSelect, {
															items: unref(measures),
															"item-value": "id",
															"item-title": "name",
															modelValue: unref(formCommodityCheck).measure_id,
															"onUpdate:modelValue": ($event) => unref(formCommodityCheck).measure_id = $event,
															variant: "solo",
															density: "comfortable",
															"hide-details": ""
														}, null, 8, [
															"items",
															"modelValue",
															"onUpdate:modelValue"
														])]),
														_: 1
													}),
													createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: unref(formCommodityCheck).price,
															"onUpdate:modelValue": ($event) => unref(formCommodityCheck).price = $event,
															label: "Цена",
															variant: "solo-filled",
															density: "comfortable",
															class: "bg-cyan-700",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})
												]),
												_: 1
											}),
											createVNode(VRow, null, {
												default: withCtx(() => [
													createVNode(VCol),
													createVNode(VCol),
													createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VBtn, {
															onClick: storeCommodityToCheck,
															text: "store",
															variant: "outlined",
															density: "compact"
														})]),
														_: 1
													})
												]),
												_: 1
											})
										]),
										_: 1
									}, 8, ["onSubmit"])) : createCommentVNode("", true)]),
									"item.name": withCtx(({ item }) => [createVNode("span", { class: "text-sm font-sans text-zinc-800" }, toDisplayString(item.name), 1)]),
									_: 1
								}, 8, ["items"])]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Check.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Check-DbwLa1IG.js.map