import { C as VRow, D as VDataTable, F as VCardText, G as VList, H as VSelect, I as VCardTitle, P as VCard, R as VCardActions, S as VSpacer, T as VContainer, U as VTextField, V as VAutocomplete, a as VTabs, c as VTab, dt as useDate, h as VForm, j as VSheet, o as VTabsWindowItem, q as VListItem, rt as VBtn, s as VTabsWindow, u as VSnackbar, w as VCol, x as VDatePicker, z as VDialog } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { useForm } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createTextVNode, createVNode, isRef, mergeProps, onMounted, openBlock, reactive, ref, renderList, toDisplayString, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { format } from "date-fns";
//#region resources/js/Pages/Ameise/Sales.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Sales",
	__ssrInlineRender: true,
	setup(__props) {
		const date = useDate();
		const tab = ref();
		const buildings = ref([]);
		const cities = ref([]);
		const entities = ref([]);
		const entityClassifications = ref([]);
		const sales = ref([]);
		const sale = ref();
		const telephones = ref([]);
		const searchEntities = ref("");
		const headerEntities = [
			{
				title: "name",
				key: "name"
			},
			{
				title: "classification",
				key: "classification.name"
			},
			{
				title: "cities",
				key: "cities",
				align: "left"
			},
			{
				title: "buildings",
				key: "buildings",
				align: "left"
			},
			{
				title: "telephones",
				key: "telephones",
				align: "left"
			},
			{
				title: "unit",
				key: "units"
			}
		];
		const headerSales = [
			{
				title: "+good",
				key: "good"
			},
			{
				title: "Created",
				key: "created_at"
			},
			{
				title: "Дата",
				key: "date"
			},
			{
				title: "Entity",
				key: "entity_id"
			},
			{
				title: "Сумма",
				key: "total"
			}
		];
		function indexBuildings() {
			axios.get(route("buildings.index")).then(function(response) {
				buildings.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		function indexCities() {
			axios.get(route("cities.index")).then(function(response) {
				cities.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		function indexEntities() {
			axios.get(route("entities.index")).then(function(response) {
				entities.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		const filteredEntities = computed(() => {
			let filteredItems = entities.value;
			if (searchEntities.value) {
				const search = searchEntities.value.toLowerCase();
				filteredItems = filteredItems.filter((item) => item.name.toLowerCase().includes(search));
			}
			return filteredItems;
		});
		const showFormEntity = ref(false);
		const formEntity = useForm({
			name: null,
			entity_classification_id: null,
			buildings: null,
			telephones: null,
			cities: null
		});
		function storeEntity() {
			formEntity.post(route("web.entity.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: true,
				onSuccess: () => {
					formEntity.reset();
					indexEntities();
				}
			});
		}
		function indexEntityClassifications() {
			axios.get(route("entities-classification.index")).then(function(response) {
				entityClassifications.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		const goods = ref([]);
		function indexGoods() {
			axios.get(route("goods.index")).then(function(response) {
				goods.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		const good = reactive({});
		function showGood(id) {
			axios.get(route("goods.show", id)).then(function(response) {
				good.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		function indexSales() {
			axios.get(route("sales.index")).then(function(response) {
				sales.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		function showSale(id) {
			axios.get(route("sales.show", id)).then(function(response) {
				sale.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let formSale = useForm({
			date: null,
			entity_id: null,
			total: null
		});
		function storeSale() {
			formSale.date = format(new Date(formSale.date), "yyyy-MM-dd HH:mm:ss");
			formSale.post(route("sales.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: false,
				onSuccess: () => {
					formSale.reset();
					indexSales();
				}
			});
		}
		function indexTelephones() {
			axios.get(route("telephones.index")).then(function(response) {
				telephones.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let showFormAttachGood = ref(false);
		const loadingAttach = ref(false);
		const snackbar = ref({
			show: false,
			text: ""
		});
		const formAttachGood = useForm({
			good_id: null,
			sale_id: null,
			quantity: null,
			measure_id: null,
			price: null
		});
		function openAttachDialog(sale) {
			showSale(sale.id);
			formAttachGood.sale_id = sale.id;
			showFormAttachGood.value = true;
		}
		function attachGood() {
			loadingAttach.value = true;
			formAttachGood.post(route("goodsales.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: false,
				onSuccess: () => {
					snackbar.value = {
						show: true,
						text: "Товар успешно привязан!"
					};
					showFormAttachGood.value = false;
					formAttachGood.reset();
					indexSales();
				}
			});
		}
		const measures = ref([]);
		function indexMeasures() {
			axios.get(route("measures.index")).then(function(response) {
				measures.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		onMounted(() => {
			indexBuildings();
			indexCities();
			indexEntities();
			indexEntityClassifications();
			indexGoods();
			indexMeasures();
			indexSales();
			indexTelephones();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VRow, null, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) _push(ssrRenderComponent(VCol, null, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(ssrRenderComponent(VCard, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VTabs, {
													modelValue: tab.value,
													"onUpdate:modelValue": ($event) => tab.value = $event,
													"align-tabs": "center",
													color: "deep-purple-accent-4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VTab, { value: "sales" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`Продажи`);
																	else return [createTextVNode("Продажи")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VTab, { value: "entities" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`Entities`);
																	else return [createTextVNode("Entities")];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [createVNode(VTab, { value: "sales" }, {
															default: withCtx(() => [createTextVNode("Продажи")]),
															_: 1
														}), createVNode(VTab, { value: "entities" }, {
															default: withCtx(() => [createTextVNode("Entities")]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTabsWindow, {
													modelValue: tab.value,
													"onUpdate:modelValue": ($event) => tab.value = $event
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VTabsWindowItem, { value: "sales" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VContainer, { fluid: "" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) {
																				_push(ssrRenderComponent(VRow, null, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(ssrRenderComponent(VCol, { cols: "1" }, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VDialog, { width: "1000" }, {
																									activator: withCtx(({ props: activatorProps }, _push, _parent, _scopeId) => {
																										if (_push) _push(ssrRenderComponent(VBtn, mergeProps(activatorProps, { text: "Новая продажа" }), null, _parent, _scopeId));
																										else return [createVNode(VBtn, mergeProps(activatorProps, { text: "Новая продажа" }), null, 16)];
																									}),
																									default: withCtx(({ isActive }, _push, _parent, _scopeId) => {
																										if (_push) _push(ssrRenderComponent(VCard, null, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) {
																													_push(ssrRenderComponent(VCardTitle, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(`Form Sale`);
																															else return [createTextVNode("Form Sale")];
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
																																							items: entities.value,
																																							"item-value": "id",
																																							"item-title": "name",
																																							modelValue: unref(formSale).entity_id,
																																							"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																																							density: "compact",
																																							variant: "outlined",
																																							chips: ""
																																						}, null, _parent, _scopeId));
																																						else return [createVNode(VAutocomplete, {
																																							items: entities.value,
																																							"item-value": "id",
																																							"item-title": "name",
																																							modelValue: unref(formSale).entity_id,
																																							"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																																							density: "compact",
																																							variant: "outlined",
																																							chips: ""
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
																																						items: entities.value,
																																						"item-value": "id",
																																						"item-title": "name",
																																						modelValue: unref(formSale).entity_id,
																																						"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																																						density: "compact",
																																						variant: "outlined",
																																						chips: ""
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
																																							if (_push) _push(ssrRenderComponent(VDatePicker, {
																																								modelValue: unref(formSale).date,
																																								"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																																							}, null, _parent, _scopeId));
																																							else return [createVNode(VDatePicker, {
																																								modelValue: unref(formSale).date,
																																								"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																																							}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																																						}),
																																						_: 2
																																					}, _parent, _scopeId));
																																					_push(ssrRenderComponent(VCol, null, {
																																						default: withCtx((_, _push, _parent, _scopeId) => {
																																							if (_push) _push(ssrRenderComponent(VTextField, {
																																								modelValue: unref(formSale).total,
																																								"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																																								label: "Total",
																																								placeholder: "Сумма продажи",
																																								density: "comfortable",
																																								variant: "solo"
																																							}, null, _parent, _scopeId));
																																							else return [createVNode(VTextField, {
																																								modelValue: unref(formSale).total,
																																								"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																																								label: "Total",
																																								placeholder: "Сумма продажи",
																																								density: "comfortable",
																																								variant: "solo"
																																							}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																																						}),
																																						_: 2
																																					}, _parent, _scopeId));
																																				} else return [createVNode(VCol, null, {
																																					default: withCtx(() => [createVNode(VDatePicker, {
																																						modelValue: unref(formSale).date,
																																						"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																					_: 1
																																				}), createVNode(VCol, null, {
																																					default: withCtx(() => [createVNode(VTextField, {
																																						modelValue: unref(formSale).total,
																																						"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																																						label: "Total",
																																						placeholder: "Сумма продажи",
																																						density: "comfortable",
																																						variant: "solo"
																																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																					_: 1
																																				})];
																																			}),
																																			_: 2
																																		}, _parent, _scopeId));
																																	} else return [createVNode(VRow, null, {
																																		default: withCtx(() => [createVNode(VCol, null, {
																																			default: withCtx(() => [createVNode(VAutocomplete, {
																																				items: entities.value,
																																				"item-value": "id",
																																				"item-title": "name",
																																				modelValue: unref(formSale).entity_id,
																																				"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																																				density: "compact",
																																				variant: "outlined",
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
																																			default: withCtx(() => [createVNode(VDatePicker, {
																																				modelValue: unref(formSale).date,
																																				"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																			_: 1
																																		}), createVNode(VCol, null, {
																																			default: withCtx(() => [createVNode(VTextField, {
																																				modelValue: unref(formSale).total,
																																				"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																																				label: "Total",
																																				placeholder: "Сумма продажи",
																																				density: "comfortable",
																																				variant: "solo"
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
																																		default: withCtx(() => [createVNode(VAutocomplete, {
																																			items: entities.value,
																																			"item-value": "id",
																																			"item-title": "name",
																																			modelValue: unref(formSale).entity_id,
																																			"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																																			density: "compact",
																																			variant: "outlined",
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
																																		default: withCtx(() => [createVNode(VDatePicker, {
																																			modelValue: unref(formSale).date,
																																			"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																		_: 1
																																	}), createVNode(VCol, null, {
																																		default: withCtx(() => [createVNode(VTextField, {
																																			modelValue: unref(formSale).total,
																																			"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																																			label: "Total",
																																			placeholder: "Сумма продажи",
																																			density: "comfortable",
																																			variant: "solo"
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
																															if (_push) _push(ssrRenderComponent(VBtn, {
																																text: "store",
																																onClick: storeSale,
																																variant: "elevated",
																																density: "comfortable",
																																color: "purple"
																															}, null, _parent, _scopeId));
																															else return [createVNode(VBtn, {
																																text: "store",
																																onClick: storeSale,
																																variant: "elevated",
																																density: "comfortable",
																																color: "purple"
																															})];
																														}),
																														_: 2
																													}, _parent, _scopeId));
																												} else return [
																													createVNode(VCardTitle, null, {
																														default: withCtx(() => [createTextVNode("Form Sale")]),
																														_: 1
																													}),
																													createVNode(VCardText, null, {
																														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																															default: withCtx(() => [createVNode(VRow, null, {
																																default: withCtx(() => [createVNode(VCol, null, {
																																	default: withCtx(() => [createVNode(VAutocomplete, {
																																		items: entities.value,
																																		"item-value": "id",
																																		"item-title": "name",
																																		modelValue: unref(formSale).entity_id,
																																		"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																																		density: "compact",
																																		variant: "outlined",
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
																																	default: withCtx(() => [createVNode(VDatePicker, {
																																		modelValue: unref(formSale).date,
																																		"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																	_: 1
																																}), createVNode(VCol, null, {
																																	default: withCtx(() => [createVNode(VTextField, {
																																		modelValue: unref(formSale).total,
																																		"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																																		label: "Total",
																																		placeholder: "Сумма продажи",
																																		density: "comfortable",
																																		variant: "solo"
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
																														default: withCtx(() => [createVNode(VBtn, {
																															text: "store",
																															onClick: storeSale,
																															variant: "elevated",
																															density: "comfortable",
																															color: "purple"
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
																													default: withCtx(() => [createTextVNode("Form Sale")]),
																													_: 1
																												}),
																												createVNode(VCardText, null, {
																													default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																														default: withCtx(() => [createVNode(VRow, null, {
																															default: withCtx(() => [createVNode(VCol, null, {
																																default: withCtx(() => [createVNode(VAutocomplete, {
																																	items: entities.value,
																																	"item-value": "id",
																																	"item-title": "name",
																																	modelValue: unref(formSale).entity_id,
																																	"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																																	density: "compact",
																																	variant: "outlined",
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
																																default: withCtx(() => [createVNode(VDatePicker, {
																																	modelValue: unref(formSale).date,
																																	"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																_: 1
																															}), createVNode(VCol, null, {
																																default: withCtx(() => [createVNode(VTextField, {
																																	modelValue: unref(formSale).total,
																																	"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																																	label: "Total",
																																	placeholder: "Сумма продажи",
																																	density: "comfortable",
																																	variant: "solo"
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
																													default: withCtx(() => [createVNode(VBtn, {
																														text: "store",
																														onClick: storeSale,
																														variant: "elevated",
																														density: "comfortable",
																														color: "purple"
																													})]),
																													_: 1
																												})
																											]),
																											_: 1
																										})];
																									}),
																									_: 1
																								}, _parent, _scopeId));
																								else return [createVNode(VDialog, { width: "1000" }, {
																									activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, { text: "Новая продажа" }), null, 16)]),
																									default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																										default: withCtx(() => [
																											createVNode(VCardTitle, null, {
																												default: withCtx(() => [createTextVNode("Form Sale")]),
																												_: 1
																											}),
																											createVNode(VCardText, null, {
																												default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																													default: withCtx(() => [createVNode(VRow, null, {
																														default: withCtx(() => [createVNode(VCol, null, {
																															default: withCtx(() => [createVNode(VAutocomplete, {
																																items: entities.value,
																																"item-value": "id",
																																"item-title": "name",
																																modelValue: unref(formSale).entity_id,
																																"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																																density: "compact",
																																variant: "outlined",
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
																															default: withCtx(() => [createVNode(VDatePicker, {
																																modelValue: unref(formSale).date,
																																"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																															_: 1
																														}), createVNode(VCol, null, {
																															default: withCtx(() => [createVNode(VTextField, {
																																modelValue: unref(formSale).total,
																																"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																																label: "Total",
																																placeholder: "Сумма продажи",
																																density: "comfortable",
																																variant: "solo"
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
																												default: withCtx(() => [createVNode(VBtn, {
																													text: "store",
																													onClick: storeSale,
																													variant: "elevated",
																													density: "comfortable",
																													color: "purple"
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
																						else return [createVNode(VCol, { cols: "1" }, {
																							default: withCtx(() => [createVNode(VDialog, { width: "1000" }, {
																								activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, { text: "Новая продажа" }), null, 16)]),
																								default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																									default: withCtx(() => [
																										createVNode(VCardTitle, null, {
																											default: withCtx(() => [createTextVNode("Form Sale")]),
																											_: 1
																										}),
																										createVNode(VCardText, null, {
																											default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																												default: withCtx(() => [createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VCol, null, {
																														default: withCtx(() => [createVNode(VAutocomplete, {
																															items: entities.value,
																															"item-value": "id",
																															"item-title": "name",
																															modelValue: unref(formSale).entity_id,
																															"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																															density: "compact",
																															variant: "outlined",
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
																														default: withCtx(() => [createVNode(VDatePicker, {
																															modelValue: unref(formSale).date,
																															"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																														_: 1
																													}), createVNode(VCol, null, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(formSale).total,
																															"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																															label: "Total",
																															placeholder: "Сумма продажи",
																															density: "comfortable",
																															variant: "solo"
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
																											default: withCtx(() => [createVNode(VBtn, {
																												text: "store",
																												onClick: storeSale,
																												variant: "elevated",
																												density: "comfortable",
																												color: "purple"
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
																				_push(ssrRenderComponent(VRow, null, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) {
																							_push(ssrRenderComponent(VCol, { cols: "5" }, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) {
																										_push(ssrRenderComponent(VDataTable, {
																											items: sales.value,
																											"items-per-page": "250",
																											headers: headerSales,
																											"fixed-header": "",
																											height: "1000px",
																											density: "compact",
																											hover: ""
																										}, {
																											"item.good": withCtx(({ item }, _push, _parent, _scopeId) => {
																												if (_push) _push(ssrRenderComponent(VBtn, {
																													text: "+",
																													onClick: ($event) => openAttachDialog(item),
																													variant: "elevated",
																													color: "grey",
																													density: "compact",
																													size: "20"
																												}, null, _parent, _scopeId));
																												else return [createVNode(VBtn, {
																													text: "+",
																													onClick: ($event) => openAttachDialog(item),
																													variant: "elevated",
																													color: "grey",
																													density: "compact",
																													size: "20"
																												}, null, 8, ["onClick"])];
																											}),
																											"item.created_at": withCtx(({ item }, _push, _parent, _scopeId) => {
																												if (_push) _push(`<span class="text-[8px] font-sans"${_scopeId}>${ssrInterpolate(unref(date).format(item.created_at, "fullDate"))}</span>`);
																												else return [createVNode("span", { class: "text-[8px] font-sans" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)];
																											}),
																											"item.date": withCtx(({ item }, _push, _parent, _scopeId) => {
																												if (_push) _push(`<span class="text-xs font-sans"${_scopeId}>${ssrInterpolate(unref(date).format(item.date, "fullDateWithWeekday"))}</span>`);
																												else return [createVNode("span", { class: "text-xs font-sans" }, toDisplayString(unref(date).format(item.date, "fullDateWithWeekday")), 1)];
																											}),
																											"item.entity_id": withCtx(({ item }, _push, _parent, _scopeId) => {
																												if (_push) _push(`<span class="text-sm font-RobotoRegular"${_scopeId}>${ssrInterpolate(item.entity.name)}</span>`);
																												else return [createVNode("span", { class: "text-sm font-RobotoRegular" }, toDisplayString(item.entity.name), 1)];
																											}),
																											"item.total": withCtx(({ item }, _push, _parent, _scopeId) => {
																												if (_push) _push(`<span class="cursor-pointer"${_scopeId}>${ssrInterpolate(item.total)}</span>`);
																												else return [createVNode("span", {
																													onClick: ($event) => showSale(item.id),
																													class: "cursor-pointer"
																												}, toDisplayString(item.total), 9, ["onClick"])];
																											}),
																											_: 1
																										}, _parent, _scopeId));
																										_push(ssrRenderComponent(VDialog, {
																											modelValue: unref(showFormAttachGood),
																											"onUpdate:modelValue": ($event) => isRef(showFormAttachGood) ? showFormAttachGood.value = $event : showFormAttachGood = $event,
																											transition: "dialog-bottom-transition",
																											width: "1000"
																										}, {
																											default: withCtx(({ isActive }, _push, _parent, _scopeId) => {
																												if (_push) _push(ssrRenderComponent(VCard, { theme: "dark" }, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) {
																															_push(ssrRenderComponent(VCardTitle, null, {
																																default: withCtx((_, _push, _parent, _scopeId) => {
																																	if (_push) _push(`Form Attach Good`);
																																	else return [createTextVNode("Form Attach Good")];
																																}),
																																_: 2
																															}, _parent, _scopeId));
																															_push(ssrRenderComponent(VCardText, { class: "text-red-700" }, {
																																default: withCtx((_, _push, _parent, _scopeId) => {
																																	if (_push) _push(ssrRenderComponent(VRow, null, {
																																		default: withCtx((_, _push, _parent, _scopeId) => {
																																			if (_push) {
																																				_push(ssrRenderComponent(VCol, { cols: "7" }, {
																																					default: withCtx((_, _push, _parent, _scopeId) => {
																																						if (_push) _push(ssrRenderComponent(VForm, { onSubmit: () => {} }, {
																																							default: withCtx((_, _push, _parent, _scopeId) => {
																																								if (_push) {
																																									_push(ssrRenderComponent(VRow, null, {
																																										default: withCtx((_, _push, _parent, _scopeId) => {
																																											if (_push) _push(ssrRenderComponent(VCol, null, {
																																												default: withCtx((_, _push, _parent, _scopeId) => {
																																													if (_push) _push(ssrRenderComponent(VAutocomplete, {
																																														items: goods.value,
																																														"item-value": "id",
																																														"item-title": "name",
																																														modelValue: unref(formAttachGood).good_id,
																																														"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																																														label: "Good",
																																														variant: "outlined",
																																														onChange: ($event) => showGood(unref(formAttachGood).good_id)
																																													}, null, _parent, _scopeId));
																																													else return [createVNode(VAutocomplete, {
																																														items: goods.value,
																																														"item-value": "id",
																																														"item-title": "name",
																																														modelValue: unref(formAttachGood).good_id,
																																														"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																																														label: "Good",
																																														variant: "outlined",
																																														onChange: ($event) => showGood(unref(formAttachGood).good_id)
																																													}, null, 8, [
																																														"items",
																																														"modelValue",
																																														"onUpdate:modelValue",
																																														"onChange"
																																													])];
																																												}),
																																												_: 2
																																											}, _parent, _scopeId));
																																											else return [createVNode(VCol, null, {
																																												default: withCtx(() => [createVNode(VAutocomplete, {
																																													items: goods.value,
																																													"item-value": "id",
																																													"item-title": "name",
																																													modelValue: unref(formAttachGood).good_id,
																																													"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																																													label: "Good",
																																													variant: "outlined",
																																													onChange: ($event) => showGood(unref(formAttachGood).good_id)
																																												}, null, 8, [
																																													"items",
																																													"modelValue",
																																													"onUpdate:modelValue",
																																													"onChange"
																																												])]),
																																												_: 1
																																											})];
																																										}),
																																										_: 2
																																									}, _parent, _scopeId));
																																									_push(ssrRenderComponent(VRow, null, {
																																										default: withCtx((_, _push, _parent, _scopeId) => {
																																											if (_push) {
																																												_push(ssrRenderComponent(VCol, { cols: "3" }, {
																																													default: withCtx((_, _push, _parent, _scopeId) => {
																																														if (_push) _push(ssrRenderComponent(VTextField, {
																																															modelValue: unref(formAttachGood).quantity,
																																															"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																															label: "Количество",
																																															variant: "outlined",
																																															density: "compact",
																																															theme: "dark"
																																														}, null, _parent, _scopeId));
																																														else return [createVNode(VTextField, {
																																															modelValue: unref(formAttachGood).quantity,
																																															"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																															label: "Количество",
																																															variant: "outlined",
																																															density: "compact",
																																															theme: "dark"
																																														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																																													}),
																																													_: 2
																																												}, _parent, _scopeId));
																																												_push(ssrRenderComponent(VCol, { cols: "3" }, {
																																													default: withCtx((_, _push, _parent, _scopeId) => {
																																														if (_push) _push(ssrRenderComponent(VSelect, {
																																															items: measures.value,
																																															"item-value": "id",
																																															"item-title": "name",
																																															modelValue: unref(formAttachGood).measure_id,
																																															"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																															label: "Measures",
																																															variant: "outlined",
																																															density: "compact"
																																														}, null, _parent, _scopeId));
																																														else return [createVNode(VSelect, {
																																															items: measures.value,
																																															"item-value": "id",
																																															"item-title": "name",
																																															modelValue: unref(formAttachGood).measure_id,
																																															"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																															label: "Measures",
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
																																												_push(ssrRenderComponent(VCol, { cols: "6" }, {
																																													default: withCtx((_, _push, _parent, _scopeId) => {
																																														if (_push) _push(ssrRenderComponent(VTextField, {
																																															modelValue: unref(formAttachGood).price,
																																															"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																															label: "Price",
																																															variant: "outlined",
																																															density: "comfortable"
																																														}, null, _parent, _scopeId));
																																														else return [createVNode(VTextField, {
																																															modelValue: unref(formAttachGood).price,
																																															"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																															label: "Price",
																																															variant: "outlined",
																																															density: "comfortable"
																																														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																																													}),
																																													_: 2
																																												}, _parent, _scopeId));
																																											} else return [
																																												createVNode(VCol, { cols: "3" }, {
																																													default: withCtx(() => [createVNode(VTextField, {
																																														modelValue: unref(formAttachGood).quantity,
																																														"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																														label: "Количество",
																																														variant: "outlined",
																																														density: "compact",
																																														theme: "dark"
																																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																													_: 1
																																												}),
																																												createVNode(VCol, { cols: "3" }, {
																																													default: withCtx(() => [createVNode(VSelect, {
																																														items: measures.value,
																																														"item-value": "id",
																																														"item-title": "name",
																																														modelValue: unref(formAttachGood).measure_id,
																																														"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																														label: "Measures",
																																														variant: "outlined",
																																														density: "compact"
																																													}, null, 8, [
																																														"items",
																																														"modelValue",
																																														"onUpdate:modelValue"
																																													])]),
																																													_: 1
																																												}),
																																												createVNode(VCol, { cols: "6" }, {
																																													default: withCtx(() => [createVNode(VTextField, {
																																														modelValue: unref(formAttachGood).price,
																																														"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																														label: "Price",
																																														variant: "outlined",
																																														density: "comfortable"
																																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																													_: 1
																																												})
																																											];
																																										}),
																																										_: 2
																																									}, _parent, _scopeId));
																																								} else return [createVNode(VRow, null, {
																																									default: withCtx(() => [createVNode(VCol, null, {
																																										default: withCtx(() => [createVNode(VAutocomplete, {
																																											items: goods.value,
																																											"item-value": "id",
																																											"item-title": "name",
																																											modelValue: unref(formAttachGood).good_id,
																																											"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																																											label: "Good",
																																											variant: "outlined",
																																											onChange: ($event) => showGood(unref(formAttachGood).good_id)
																																										}, null, 8, [
																																											"items",
																																											"modelValue",
																																											"onUpdate:modelValue",
																																											"onChange"
																																										])]),
																																										_: 1
																																									})]),
																																									_: 1
																																								}), createVNode(VRow, null, {
																																									default: withCtx(() => [
																																										createVNode(VCol, { cols: "3" }, {
																																											default: withCtx(() => [createVNode(VTextField, {
																																												modelValue: unref(formAttachGood).quantity,
																																												"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																												label: "Количество",
																																												variant: "outlined",
																																												density: "compact",
																																												theme: "dark"
																																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																											_: 1
																																										}),
																																										createVNode(VCol, { cols: "3" }, {
																																											default: withCtx(() => [createVNode(VSelect, {
																																												items: measures.value,
																																												"item-value": "id",
																																												"item-title": "name",
																																												modelValue: unref(formAttachGood).measure_id,
																																												"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																												label: "Measures",
																																												variant: "outlined",
																																												density: "compact"
																																											}, null, 8, [
																																												"items",
																																												"modelValue",
																																												"onUpdate:modelValue"
																																											])]),
																																											_: 1
																																										}),
																																										createVNode(VCol, { cols: "6" }, {
																																											default: withCtx(() => [createVNode(VTextField, {
																																												modelValue: unref(formAttachGood).price,
																																												"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																												label: "Price",
																																												variant: "outlined",
																																												density: "comfortable"
																																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																											_: 1
																																										})
																																									]),
																																									_: 1
																																								})];
																																							}),
																																							_: 2
																																						}, _parent, _scopeId));
																																						else return [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																							default: withCtx(() => [createVNode(VRow, null, {
																																								default: withCtx(() => [createVNode(VCol, null, {
																																									default: withCtx(() => [createVNode(VAutocomplete, {
																																										items: goods.value,
																																										"item-value": "id",
																																										"item-title": "name",
																																										modelValue: unref(formAttachGood).good_id,
																																										"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																																										label: "Good",
																																										variant: "outlined",
																																										onChange: ($event) => showGood(unref(formAttachGood).good_id)
																																									}, null, 8, [
																																										"items",
																																										"modelValue",
																																										"onUpdate:modelValue",
																																										"onChange"
																																									])]),
																																									_: 1
																																								})]),
																																								_: 1
																																							}), createVNode(VRow, null, {
																																								default: withCtx(() => [
																																									createVNode(VCol, { cols: "3" }, {
																																										default: withCtx(() => [createVNode(VTextField, {
																																											modelValue: unref(formAttachGood).quantity,
																																											"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																											label: "Количество",
																																											variant: "outlined",
																																											density: "compact",
																																											theme: "dark"
																																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																										_: 1
																																									}),
																																									createVNode(VCol, { cols: "3" }, {
																																										default: withCtx(() => [createVNode(VSelect, {
																																											items: measures.value,
																																											"item-value": "id",
																																											"item-title": "name",
																																											modelValue: unref(formAttachGood).measure_id,
																																											"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																											label: "Measures",
																																											variant: "outlined",
																																											density: "compact"
																																										}, null, 8, [
																																											"items",
																																											"modelValue",
																																											"onUpdate:modelValue"
																																										])]),
																																										_: 1
																																									}),
																																									createVNode(VCol, { cols: "6" }, {
																																										default: withCtx(() => [createVNode(VTextField, {
																																											modelValue: unref(formAttachGood).price,
																																											"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																											label: "Price",
																																											variant: "outlined",
																																											density: "comfortable"
																																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
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
																																				_push(ssrRenderComponent(VCol, { cols: "5" }, {
																																					default: withCtx((_, _push, _parent, _scopeId) => {
																																						if (_push) _push(ssrRenderComponent(VSheet, null, {
																																							default: withCtx((_, _push, _parent, _scopeId) => {
																																								if (_push) {
																																									_push(ssrRenderComponent(VRow, null, {
																																										default: withCtx((_, _push, _parent, _scopeId) => {
																																											if (_push) {
																																												_push(ssrRenderComponent(VCol, null, {
																																													default: withCtx((_, _push, _parent, _scopeId) => {
																																														if (_push) _push(`<div${_scopeId}><label${_scopeId}>Total</label>${ssrInterpolate(sale.value.total)}</div>`);
																																														else return [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])];
																																													}),
																																													_: 2
																																												}, _parent, _scopeId));
																																												_push(ssrRenderComponent(VCol, null, {
																																													default: withCtx((_, _push, _parent, _scopeId) => {
																																														if (_push) _push(`<label${_scopeId}>Position Sum</label><span${_scopeId}>${ssrInterpolate(unref(formAttachGood).quantity * unref(formAttachGood).price)}</span>`);
																																														else return [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)];
																																													}),
																																													_: 2
																																												}, _parent, _scopeId));
																																											} else return [createVNode(VCol, null, {
																																												default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																																												_: 1
																																											}), createVNode(VCol, null, {
																																												default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																																												_: 1
																																											})];
																																										}),
																																										_: 2
																																									}, _parent, _scopeId));
																																									_push(ssrRenderComponent(VRow, null, {
																																										default: withCtx((_, _push, _parent, _scopeId) => {
																																											if (_push) _push(ssrRenderComponent(VCol, null, {
																																												default: withCtx((_, _push, _parent, _scopeId) => {
																																													if (_push) _push(`<div class="text-[9px]"${_scopeId}>${ssrInterpolate(good)}</div>`);
																																													else return [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)];
																																												}),
																																												_: 2
																																											}, _parent, _scopeId));
																																											else return [createVNode(VCol, null, {
																																												default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
																																												_: 1
																																											})];
																																										}),
																																										_: 2
																																									}, _parent, _scopeId));
																																								} else return [createVNode(VRow, null, {
																																									default: withCtx(() => [createVNode(VCol, null, {
																																										default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																																										_: 1
																																									}), createVNode(VCol, null, {
																																										default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																																										_: 1
																																									})]),
																																									_: 1
																																								}), createVNode(VRow, null, {
																																									default: withCtx(() => [createVNode(VCol, null, {
																																										default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
																																										_: 1
																																									})]),
																																									_: 1
																																								})];
																																							}),
																																							_: 2
																																						}, _parent, _scopeId));
																																						else return [createVNode(VSheet, null, {
																																							default: withCtx(() => [createVNode(VRow, null, {
																																								default: withCtx(() => [createVNode(VCol, null, {
																																									default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																																									_: 1
																																								}), createVNode(VCol, null, {
																																									default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																																									_: 1
																																								})]),
																																								_: 1
																																							}), createVNode(VRow, null, {
																																								default: withCtx(() => [createVNode(VCol, null, {
																																									default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
																																									_: 1
																																								})]),
																																								_: 1
																																							})]),
																																							_: 1
																																						})];
																																					}),
																																					_: 2
																																				}, _parent, _scopeId));
																																			} else return [createVNode(VCol, { cols: "7" }, {
																																				default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																					default: withCtx(() => [createVNode(VRow, null, {
																																						default: withCtx(() => [createVNode(VCol, null, {
																																							default: withCtx(() => [createVNode(VAutocomplete, {
																																								items: goods.value,
																																								"item-value": "id",
																																								"item-title": "name",
																																								modelValue: unref(formAttachGood).good_id,
																																								"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																																								label: "Good",
																																								variant: "outlined",
																																								onChange: ($event) => showGood(unref(formAttachGood).good_id)
																																							}, null, 8, [
																																								"items",
																																								"modelValue",
																																								"onUpdate:modelValue",
																																								"onChange"
																																							])]),
																																							_: 1
																																						})]),
																																						_: 1
																																					}), createVNode(VRow, null, {
																																						default: withCtx(() => [
																																							createVNode(VCol, { cols: "3" }, {
																																								default: withCtx(() => [createVNode(VTextField, {
																																									modelValue: unref(formAttachGood).quantity,
																																									"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																									label: "Количество",
																																									variant: "outlined",
																																									density: "compact",
																																									theme: "dark"
																																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																								_: 1
																																							}),
																																							createVNode(VCol, { cols: "3" }, {
																																								default: withCtx(() => [createVNode(VSelect, {
																																									items: measures.value,
																																									"item-value": "id",
																																									"item-title": "name",
																																									modelValue: unref(formAttachGood).measure_id,
																																									"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																									label: "Measures",
																																									variant: "outlined",
																																									density: "compact"
																																								}, null, 8, [
																																									"items",
																																									"modelValue",
																																									"onUpdate:modelValue"
																																								])]),
																																								_: 1
																																							}),
																																							createVNode(VCol, { cols: "6" }, {
																																								default: withCtx(() => [createVNode(VTextField, {
																																									modelValue: unref(formAttachGood).price,
																																									"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																									label: "Price",
																																									variant: "outlined",
																																									density: "comfortable"
																																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																								_: 1
																																							})
																																						]),
																																						_: 1
																																					})]),
																																					_: 1
																																				}, 8, ["onSubmit"])]),
																																				_: 1
																																			}), createVNode(VCol, { cols: "5" }, {
																																				default: withCtx(() => [createVNode(VSheet, null, {
																																					default: withCtx(() => [createVNode(VRow, null, {
																																						default: withCtx(() => [createVNode(VCol, null, {
																																							default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																																							_: 1
																																						}), createVNode(VCol, null, {
																																							default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																																							_: 1
																																						})]),
																																						_: 1
																																					}), createVNode(VRow, null, {
																																						default: withCtx(() => [createVNode(VCol, null, {
																																							default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
																																							_: 1
																																						})]),
																																						_: 1
																																					})]),
																																					_: 1
																																				})]),
																																				_: 1
																																			})];
																																		}),
																																		_: 2
																																	}, _parent, _scopeId));
																																	else return [createVNode(VRow, null, {
																																		default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																			default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																				default: withCtx(() => [createVNode(VRow, null, {
																																					default: withCtx(() => [createVNode(VCol, null, {
																																						default: withCtx(() => [createVNode(VAutocomplete, {
																																							items: goods.value,
																																							"item-value": "id",
																																							"item-title": "name",
																																							modelValue: unref(formAttachGood).good_id,
																																							"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																																							label: "Good",
																																							variant: "outlined",
																																							onChange: ($event) => showGood(unref(formAttachGood).good_id)
																																						}, null, 8, [
																																							"items",
																																							"modelValue",
																																							"onUpdate:modelValue",
																																							"onChange"
																																						])]),
																																						_: 1
																																					})]),
																																					_: 1
																																				}), createVNode(VRow, null, {
																																					default: withCtx(() => [
																																						createVNode(VCol, { cols: "3" }, {
																																							default: withCtx(() => [createVNode(VTextField, {
																																								modelValue: unref(formAttachGood).quantity,
																																								"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																								label: "Количество",
																																								variant: "outlined",
																																								density: "compact",
																																								theme: "dark"
																																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																							_: 1
																																						}),
																																						createVNode(VCol, { cols: "3" }, {
																																							default: withCtx(() => [createVNode(VSelect, {
																																								items: measures.value,
																																								"item-value": "id",
																																								"item-title": "name",
																																								modelValue: unref(formAttachGood).measure_id,
																																								"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																								label: "Measures",
																																								variant: "outlined",
																																								density: "compact"
																																							}, null, 8, [
																																								"items",
																																								"modelValue",
																																								"onUpdate:modelValue"
																																							])]),
																																							_: 1
																																						}),
																																						createVNode(VCol, { cols: "6" }, {
																																							default: withCtx(() => [createVNode(VTextField, {
																																								modelValue: unref(formAttachGood).price,
																																								"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																								label: "Price",
																																								variant: "outlined",
																																								density: "comfortable"
																																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																							_: 1
																																						})
																																					]),
																																					_: 1
																																				})]),
																																				_: 1
																																			}, 8, ["onSubmit"])]),
																																			_: 1
																																		}), createVNode(VCol, { cols: "5" }, {
																																			default: withCtx(() => [createVNode(VSheet, null, {
																																				default: withCtx(() => [createVNode(VRow, null, {
																																					default: withCtx(() => [createVNode(VCol, null, {
																																						default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																																						_: 1
																																					}), createVNode(VCol, null, {
																																						default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																																						_: 1
																																					})]),
																																					_: 1
																																				}), createVNode(VRow, null, {
																																					default: withCtx(() => [createVNode(VCol, null, {
																																						default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
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
																																_: 2
																															}, _parent, _scopeId));
																															_push(ssrRenderComponent(VCardActions, null, {
																																default: withCtx((_, _push, _parent, _scopeId) => {
																																	if (_push) {
																																		_push(ssrRenderComponent(VSpacer, null, null, _parent, _scopeId));
																																		_push(ssrRenderComponent(VBtn, {
																																			text: "attach",
																																			onClick: attachGood,
																																			variant: "text",
																																			density: "compact",
																																			color: "teal-lighten-2"
																																		}, null, _parent, _scopeId));
																																	} else return [createVNode(VSpacer), createVNode(VBtn, {
																																		text: "attach",
																																		onClick: attachGood,
																																		variant: "text",
																																		density: "compact",
																																		color: "teal-lighten-2"
																																	})];
																																}),
																																_: 2
																															}, _parent, _scopeId));
																														} else return [
																															createVNode(VCardTitle, null, {
																																default: withCtx(() => [createTextVNode("Form Attach Good")]),
																																_: 1
																															}),
																															createVNode(VCardText, { class: "text-red-700" }, {
																																default: withCtx(() => [createVNode(VRow, null, {
																																	default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																		default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																			default: withCtx(() => [createVNode(VRow, null, {
																																				default: withCtx(() => [createVNode(VCol, null, {
																																					default: withCtx(() => [createVNode(VAutocomplete, {
																																						items: goods.value,
																																						"item-value": "id",
																																						"item-title": "name",
																																						modelValue: unref(formAttachGood).good_id,
																																						"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																																						label: "Good",
																																						variant: "outlined",
																																						onChange: ($event) => showGood(unref(formAttachGood).good_id)
																																					}, null, 8, [
																																						"items",
																																						"modelValue",
																																						"onUpdate:modelValue",
																																						"onChange"
																																					])]),
																																					_: 1
																																				})]),
																																				_: 1
																																			}), createVNode(VRow, null, {
																																				default: withCtx(() => [
																																					createVNode(VCol, { cols: "3" }, {
																																						default: withCtx(() => [createVNode(VTextField, {
																																							modelValue: unref(formAttachGood).quantity,
																																							"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																							label: "Количество",
																																							variant: "outlined",
																																							density: "compact",
																																							theme: "dark"
																																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																						_: 1
																																					}),
																																					createVNode(VCol, { cols: "3" }, {
																																						default: withCtx(() => [createVNode(VSelect, {
																																							items: measures.value,
																																							"item-value": "id",
																																							"item-title": "name",
																																							modelValue: unref(formAttachGood).measure_id,
																																							"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																							label: "Measures",
																																							variant: "outlined",
																																							density: "compact"
																																						}, null, 8, [
																																							"items",
																																							"modelValue",
																																							"onUpdate:modelValue"
																																						])]),
																																						_: 1
																																					}),
																																					createVNode(VCol, { cols: "6" }, {
																																						default: withCtx(() => [createVNode(VTextField, {
																																							modelValue: unref(formAttachGood).price,
																																							"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																							label: "Price",
																																							variant: "outlined",
																																							density: "comfortable"
																																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																						_: 1
																																					})
																																				]),
																																				_: 1
																																			})]),
																																			_: 1
																																		}, 8, ["onSubmit"])]),
																																		_: 1
																																	}), createVNode(VCol, { cols: "5" }, {
																																		default: withCtx(() => [createVNode(VSheet, null, {
																																			default: withCtx(() => [createVNode(VRow, null, {
																																				default: withCtx(() => [createVNode(VCol, null, {
																																					default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																																					_: 1
																																				}), createVNode(VCol, null, {
																																					default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																																					_: 1
																																				})]),
																																				_: 1
																																			}), createVNode(VRow, null, {
																																				default: withCtx(() => [createVNode(VCol, null, {
																																					default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
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
																															createVNode(VCardActions, null, {
																																default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
																																	text: "attach",
																																	onClick: attachGood,
																																	variant: "text",
																																	density: "compact",
																																	color: "teal-lighten-2"
																																})]),
																																_: 1
																															})
																														];
																													}),
																													_: 2
																												}, _parent, _scopeId));
																												else return [createVNode(VCard, { theme: "dark" }, {
																													default: withCtx(() => [
																														createVNode(VCardTitle, null, {
																															default: withCtx(() => [createTextVNode("Form Attach Good")]),
																															_: 1
																														}),
																														createVNode(VCardText, { class: "text-red-700" }, {
																															default: withCtx(() => [createVNode(VRow, null, {
																																default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																	default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																		default: withCtx(() => [createVNode(VRow, null, {
																																			default: withCtx(() => [createVNode(VCol, null, {
																																				default: withCtx(() => [createVNode(VAutocomplete, {
																																					items: goods.value,
																																					"item-value": "id",
																																					"item-title": "name",
																																					modelValue: unref(formAttachGood).good_id,
																																					"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																																					label: "Good",
																																					variant: "outlined",
																																					onChange: ($event) => showGood(unref(formAttachGood).good_id)
																																				}, null, 8, [
																																					"items",
																																					"modelValue",
																																					"onUpdate:modelValue",
																																					"onChange"
																																				])]),
																																				_: 1
																																			})]),
																																			_: 1
																																		}), createVNode(VRow, null, {
																																			default: withCtx(() => [
																																				createVNode(VCol, { cols: "3" }, {
																																					default: withCtx(() => [createVNode(VTextField, {
																																						modelValue: unref(formAttachGood).quantity,
																																						"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																						label: "Количество",
																																						variant: "outlined",
																																						density: "compact",
																																						theme: "dark"
																																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																					_: 1
																																				}),
																																				createVNode(VCol, { cols: "3" }, {
																																					default: withCtx(() => [createVNode(VSelect, {
																																						items: measures.value,
																																						"item-value": "id",
																																						"item-title": "name",
																																						modelValue: unref(formAttachGood).measure_id,
																																						"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																						label: "Measures",
																																						variant: "outlined",
																																						density: "compact"
																																					}, null, 8, [
																																						"items",
																																						"modelValue",
																																						"onUpdate:modelValue"
																																					])]),
																																					_: 1
																																				}),
																																				createVNode(VCol, { cols: "6" }, {
																																					default: withCtx(() => [createVNode(VTextField, {
																																						modelValue: unref(formAttachGood).price,
																																						"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																						label: "Price",
																																						variant: "outlined",
																																						density: "comfortable"
																																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																					_: 1
																																				})
																																			]),
																																			_: 1
																																		})]),
																																		_: 1
																																	}, 8, ["onSubmit"])]),
																																	_: 1
																																}), createVNode(VCol, { cols: "5" }, {
																																	default: withCtx(() => [createVNode(VSheet, null, {
																																		default: withCtx(() => [createVNode(VRow, null, {
																																			default: withCtx(() => [createVNode(VCol, null, {
																																				default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																																				_: 1
																																			}), createVNode(VCol, null, {
																																				default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																																				_: 1
																																			})]),
																																			_: 1
																																		}), createVNode(VRow, null, {
																																			default: withCtx(() => [createVNode(VCol, null, {
																																				default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
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
																														createVNode(VCardActions, null, {
																															default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
																																text: "attach",
																																onClick: attachGood,
																																variant: "text",
																																density: "compact",
																																color: "teal-lighten-2"
																															})]),
																															_: 1
																														})
																													]),
																													_: 1
																												})];
																											}),
																											_: 1
																										}, _parent, _scopeId));
																										_push(ssrRenderComponent(VSnackbar, {
																											modelValue: snackbar.value.show,
																											"onUpdate:modelValue": ($event) => snackbar.value.show = $event,
																											timeout: 2e3,
																											color: "green"
																										}, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(`${ssrInterpolate(snackbar.value.text)}`);
																												else return [createTextVNode(toDisplayString(snackbar.value.text), 1)];
																											}),
																											_: 1
																										}, _parent, _scopeId));
																									} else return [
																										createVNode(VDataTable, {
																											items: sales.value,
																											"items-per-page": "250",
																											headers: headerSales,
																											"fixed-header": "",
																											height: "1000px",
																											density: "compact",
																											hover: ""
																										}, {
																											"item.good": withCtx(({ item }) => [createVNode(VBtn, {
																												text: "+",
																												onClick: ($event) => openAttachDialog(item),
																												variant: "elevated",
																												color: "grey",
																												density: "compact",
																												size: "20"
																											}, null, 8, ["onClick"])]),
																											"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-[8px] font-sans" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
																											"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs font-sans" }, toDisplayString(unref(date).format(item.date, "fullDateWithWeekday")), 1)]),
																											"item.entity_id": withCtx(({ item }) => [createVNode("span", { class: "text-sm font-RobotoRegular" }, toDisplayString(item.entity.name), 1)]),
																											"item.total": withCtx(({ item }) => [createVNode("span", {
																												onClick: ($event) => showSale(item.id),
																												class: "cursor-pointer"
																											}, toDisplayString(item.total), 9, ["onClick"])]),
																											_: 1
																										}, 8, ["items"]),
																										createVNode(VDialog, {
																											modelValue: unref(showFormAttachGood),
																											"onUpdate:modelValue": ($event) => isRef(showFormAttachGood) ? showFormAttachGood.value = $event : showFormAttachGood = $event,
																											transition: "dialog-bottom-transition",
																											width: "1000"
																										}, {
																											default: withCtx(({ isActive }) => [createVNode(VCard, { theme: "dark" }, {
																												default: withCtx(() => [
																													createVNode(VCardTitle, null, {
																														default: withCtx(() => [createTextVNode("Form Attach Good")]),
																														_: 1
																													}),
																													createVNode(VCardText, { class: "text-red-700" }, {
																														default: withCtx(() => [createVNode(VRow, null, {
																															default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																	default: withCtx(() => [createVNode(VRow, null, {
																																		default: withCtx(() => [createVNode(VCol, null, {
																																			default: withCtx(() => [createVNode(VAutocomplete, {
																																				items: goods.value,
																																				"item-value": "id",
																																				"item-title": "name",
																																				modelValue: unref(formAttachGood).good_id,
																																				"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																																				label: "Good",
																																				variant: "outlined",
																																				onChange: ($event) => showGood(unref(formAttachGood).good_id)
																																			}, null, 8, [
																																				"items",
																																				"modelValue",
																																				"onUpdate:modelValue",
																																				"onChange"
																																			])]),
																																			_: 1
																																		})]),
																																		_: 1
																																	}), createVNode(VRow, null, {
																																		default: withCtx(() => [
																																			createVNode(VCol, { cols: "3" }, {
																																				default: withCtx(() => [createVNode(VTextField, {
																																					modelValue: unref(formAttachGood).quantity,
																																					"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																					label: "Количество",
																																					variant: "outlined",
																																					density: "compact",
																																					theme: "dark"
																																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																				_: 1
																																			}),
																																			createVNode(VCol, { cols: "3" }, {
																																				default: withCtx(() => [createVNode(VSelect, {
																																					items: measures.value,
																																					"item-value": "id",
																																					"item-title": "name",
																																					modelValue: unref(formAttachGood).measure_id,
																																					"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																					label: "Measures",
																																					variant: "outlined",
																																					density: "compact"
																																				}, null, 8, [
																																					"items",
																																					"modelValue",
																																					"onUpdate:modelValue"
																																				])]),
																																				_: 1
																																			}),
																																			createVNode(VCol, { cols: "6" }, {
																																				default: withCtx(() => [createVNode(VTextField, {
																																					modelValue: unref(formAttachGood).price,
																																					"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																					label: "Price",
																																					variant: "outlined",
																																					density: "comfortable"
																																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																				_: 1
																																			})
																																		]),
																																		_: 1
																																	})]),
																																	_: 1
																																}, 8, ["onSubmit"])]),
																																_: 1
																															}), createVNode(VCol, { cols: "5" }, {
																																default: withCtx(() => [createVNode(VSheet, null, {
																																	default: withCtx(() => [createVNode(VRow, null, {
																																		default: withCtx(() => [createVNode(VCol, null, {
																																			default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																																			_: 1
																																		}), createVNode(VCol, null, {
																																			default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																																			_: 1
																																		})]),
																																		_: 1
																																	}), createVNode(VRow, null, {
																																		default: withCtx(() => [createVNode(VCol, null, {
																																			default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
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
																													createVNode(VCardActions, null, {
																														default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
																															text: "attach",
																															onClick: attachGood,
																															variant: "text",
																															density: "compact",
																															color: "teal-lighten-2"
																														})]),
																														_: 1
																													})
																												]),
																												_: 1
																											})]),
																											_: 1
																										}, 8, ["modelValue", "onUpdate:modelValue"]),
																										createVNode(VSnackbar, {
																											modelValue: snackbar.value.show,
																											"onUpdate:modelValue": ($event) => snackbar.value.show = $event,
																											timeout: 2e3,
																											color: "green"
																										}, {
																											default: withCtx(() => [createTextVNode(toDisplayString(snackbar.value.text), 1)]),
																											_: 1
																										}, 8, ["modelValue", "onUpdate:modelValue"])
																									];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																							_push(ssrRenderComponent(VCol, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(ssrRenderComponent(VList, { density: "compact" }, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) {
																												_push(`<!--[-->`);
																												ssrRenderList(sale.value?.goods ?? [], (good) => {
																													_push(ssrRenderComponent(VListItem, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(ssrRenderComponent(VRow, null, {
																																default: withCtx((_, _push, _parent, _scopeId) => {
																																	if (_push) {
																																		_push(ssrRenderComponent(VCol, { cols: "8" }, {
																																			default: withCtx((_, _push, _parent, _scopeId) => {
																																				if (_push) _push(`<span class="cursor-pointer text-sm font-sans"${_scopeId}>${ssrInterpolate(good.name)}</span>`);
																																				else return [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)];
																																			}),
																																			_: 2
																																		}, _parent, _scopeId));
																																		_push(ssrRenderComponent(VCol, { cols: "1" }, {
																																			default: withCtx((_, _push, _parent, _scopeId) => {
																																				if (_push) _push(`<span class="text-[11px]"${_scopeId}>${ssrInterpolate(good.pivot.price)}</span>`);
																																				else return [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)];
																																			}),
																																			_: 2
																																		}, _parent, _scopeId));
																																		_push(ssrRenderComponent(VCol, { cols: "1" }, {
																																			default: withCtx((_, _push, _parent, _scopeId) => {
																																				if (_push) _push(`<span class="text-[10px]"${_scopeId}>${ssrInterpolate(good.pivot.quantity)}</span>`);
																																				else return [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)];
																																			}),
																																			_: 2
																																		}, _parent, _scopeId));
																																		_push(ssrRenderComponent(VCol, null, {
																																			default: withCtx((_, _push, _parent, _scopeId) => {
																																				if (_push) _push(`<span class="text-sm"${_scopeId}>${ssrInterpolate(good.pivot.total)}</span>`);
																																				else return [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)];
																																			}),
																																			_: 2
																																		}, _parent, _scopeId));
																																	} else return [
																																		createVNode(VCol, { cols: "8" }, {
																																			default: withCtx(() => [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)]),
																																			_: 2
																																		}, 1024),
																																		createVNode(VCol, { cols: "1" }, {
																																			default: withCtx(() => [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)]),
																																			_: 2
																																		}, 1024),
																																		createVNode(VCol, { cols: "1" }, {
																																			default: withCtx(() => [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)]),
																																			_: 2
																																		}, 1024),
																																		createVNode(VCol, null, {
																																			default: withCtx(() => [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)]),
																																			_: 2
																																		}, 1024)
																																	];
																																}),
																																_: 2
																															}, _parent, _scopeId));
																															else return [createVNode(VRow, null, {
																																default: withCtx(() => [
																																	createVNode(VCol, { cols: "8" }, {
																																		default: withCtx(() => [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)]),
																																		_: 2
																																	}, 1024),
																																	createVNode(VCol, { cols: "1" }, {
																																		default: withCtx(() => [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)]),
																																		_: 2
																																	}, 1024),
																																	createVNode(VCol, { cols: "1" }, {
																																		default: withCtx(() => [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)]),
																																		_: 2
																																	}, 1024),
																																	createVNode(VCol, null, {
																																		default: withCtx(() => [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)]),
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
																											} else return [(openBlock(true), createBlock(Fragment, null, renderList(sale.value?.goods ?? [], (good) => {
																												return openBlock(), createBlock(VListItem, null, {
																													default: withCtx(() => [createVNode(VRow, null, {
																														default: withCtx(() => [
																															createVNode(VCol, { cols: "8" }, {
																																default: withCtx(() => [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)]),
																																_: 2
																															}, 1024),
																															createVNode(VCol, { cols: "1" }, {
																																default: withCtx(() => [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)]),
																																_: 2
																															}, 1024),
																															createVNode(VCol, { cols: "1" }, {
																																default: withCtx(() => [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)]),
																																_: 2
																															}, 1024),
																															createVNode(VCol, null, {
																																default: withCtx(() => [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)]),
																																_: 2
																															}, 1024)
																														]),
																														_: 2
																													}, 1024)]),
																													_: 2
																												}, 1024);
																											}), 256))];
																										}),
																										_: 1
																									}, _parent, _scopeId));
																									else return [createVNode(VList, { density: "compact" }, {
																										default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(sale.value?.goods ?? [], (good) => {
																											return openBlock(), createBlock(VListItem, null, {
																												default: withCtx(() => [createVNode(VRow, null, {
																													default: withCtx(() => [
																														createVNode(VCol, { cols: "8" }, {
																															default: withCtx(() => [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)]),
																															_: 2
																														}, 1024),
																														createVNode(VCol, { cols: "1" }, {
																															default: withCtx(() => [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)]),
																															_: 2
																														}, 1024),
																														createVNode(VCol, { cols: "1" }, {
																															default: withCtx(() => [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)]),
																															_: 2
																														}, 1024),
																														createVNode(VCol, null, {
																															default: withCtx(() => [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)]),
																															_: 2
																														}, 1024)
																													]),
																													_: 2
																												}, 1024)]),
																												_: 2
																											}, 1024);
																										}), 256))]),
																										_: 1
																									})];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																						} else return [createVNode(VCol, { cols: "5" }, {
																							default: withCtx(() => [
																								createVNode(VDataTable, {
																									items: sales.value,
																									"items-per-page": "250",
																									headers: headerSales,
																									"fixed-header": "",
																									height: "1000px",
																									density: "compact",
																									hover: ""
																								}, {
																									"item.good": withCtx(({ item }) => [createVNode(VBtn, {
																										text: "+",
																										onClick: ($event) => openAttachDialog(item),
																										variant: "elevated",
																										color: "grey",
																										density: "compact",
																										size: "20"
																									}, null, 8, ["onClick"])]),
																									"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-[8px] font-sans" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
																									"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs font-sans" }, toDisplayString(unref(date).format(item.date, "fullDateWithWeekday")), 1)]),
																									"item.entity_id": withCtx(({ item }) => [createVNode("span", { class: "text-sm font-RobotoRegular" }, toDisplayString(item.entity.name), 1)]),
																									"item.total": withCtx(({ item }) => [createVNode("span", {
																										onClick: ($event) => showSale(item.id),
																										class: "cursor-pointer"
																									}, toDisplayString(item.total), 9, ["onClick"])]),
																									_: 1
																								}, 8, ["items"]),
																								createVNode(VDialog, {
																									modelValue: unref(showFormAttachGood),
																									"onUpdate:modelValue": ($event) => isRef(showFormAttachGood) ? showFormAttachGood.value = $event : showFormAttachGood = $event,
																									transition: "dialog-bottom-transition",
																									width: "1000"
																								}, {
																									default: withCtx(({ isActive }) => [createVNode(VCard, { theme: "dark" }, {
																										default: withCtx(() => [
																											createVNode(VCardTitle, null, {
																												default: withCtx(() => [createTextVNode("Form Attach Good")]),
																												_: 1
																											}),
																											createVNode(VCardText, { class: "text-red-700" }, {
																												default: withCtx(() => [createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																															default: withCtx(() => [createVNode(VRow, null, {
																																default: withCtx(() => [createVNode(VCol, null, {
																																	default: withCtx(() => [createVNode(VAutocomplete, {
																																		items: goods.value,
																																		"item-value": "id",
																																		"item-title": "name",
																																		modelValue: unref(formAttachGood).good_id,
																																		"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																																		label: "Good",
																																		variant: "outlined",
																																		onChange: ($event) => showGood(unref(formAttachGood).good_id)
																																	}, null, 8, [
																																		"items",
																																		"modelValue",
																																		"onUpdate:modelValue",
																																		"onChange"
																																	])]),
																																	_: 1
																																})]),
																																_: 1
																															}), createVNode(VRow, null, {
																																default: withCtx(() => [
																																	createVNode(VCol, { cols: "3" }, {
																																		default: withCtx(() => [createVNode(VTextField, {
																																			modelValue: unref(formAttachGood).quantity,
																																			"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																			label: "Количество",
																																			variant: "outlined",
																																			density: "compact",
																																			theme: "dark"
																																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																		_: 1
																																	}),
																																	createVNode(VCol, { cols: "3" }, {
																																		default: withCtx(() => [createVNode(VSelect, {
																																			items: measures.value,
																																			"item-value": "id",
																																			"item-title": "name",
																																			modelValue: unref(formAttachGood).measure_id,
																																			"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																			label: "Measures",
																																			variant: "outlined",
																																			density: "compact"
																																		}, null, 8, [
																																			"items",
																																			"modelValue",
																																			"onUpdate:modelValue"
																																		])]),
																																		_: 1
																																	}),
																																	createVNode(VCol, { cols: "6" }, {
																																		default: withCtx(() => [createVNode(VTextField, {
																																			modelValue: unref(formAttachGood).price,
																																			"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																			label: "Price",
																																			variant: "outlined",
																																			density: "comfortable"
																																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																		_: 1
																																	})
																																]),
																																_: 1
																															})]),
																															_: 1
																														}, 8, ["onSubmit"])]),
																														_: 1
																													}), createVNode(VCol, { cols: "5" }, {
																														default: withCtx(() => [createVNode(VSheet, null, {
																															default: withCtx(() => [createVNode(VRow, null, {
																																default: withCtx(() => [createVNode(VCol, null, {
																																	default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																																	_: 1
																																}), createVNode(VCol, null, {
																																	default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																																	_: 1
																																})]),
																																_: 1
																															}), createVNode(VRow, null, {
																																default: withCtx(() => [createVNode(VCol, null, {
																																	default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
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
																											createVNode(VCardActions, null, {
																												default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
																													text: "attach",
																													onClick: attachGood,
																													variant: "text",
																													density: "compact",
																													color: "teal-lighten-2"
																												})]),
																												_: 1
																											})
																										]),
																										_: 1
																									})]),
																									_: 1
																								}, 8, ["modelValue", "onUpdate:modelValue"]),
																								createVNode(VSnackbar, {
																									modelValue: snackbar.value.show,
																									"onUpdate:modelValue": ($event) => snackbar.value.show = $event,
																									timeout: 2e3,
																									color: "green"
																								}, {
																									default: withCtx(() => [createTextVNode(toDisplayString(snackbar.value.text), 1)]),
																									_: 1
																								}, 8, ["modelValue", "onUpdate:modelValue"])
																							]),
																							_: 1
																						}), createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																								default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(sale.value?.goods ?? [], (good) => {
																									return openBlock(), createBlock(VListItem, null, {
																										default: withCtx(() => [createVNode(VRow, null, {
																											default: withCtx(() => [
																												createVNode(VCol, { cols: "8" }, {
																													default: withCtx(() => [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)]),
																													_: 2
																												}, 1024),
																												createVNode(VCol, { cols: "1" }, {
																													default: withCtx(() => [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)]),
																													_: 2
																												}, 1024),
																												createVNode(VCol, { cols: "1" }, {
																													default: withCtx(() => [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)]),
																													_: 2
																												}, 1024),
																												createVNode(VCol, null, {
																													default: withCtx(() => [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)]),
																													_: 2
																												}, 1024)
																											]),
																											_: 2
																										}, 1024)]),
																										_: 2
																									}, 1024);
																								}), 256))]),
																								_: 1
																							})]),
																							_: 1
																						})];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																			} else return [createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, { cols: "1" }, {
																					default: withCtx(() => [createVNode(VDialog, { width: "1000" }, {
																						activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, { text: "Новая продажа" }), null, 16)]),
																						default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																							default: withCtx(() => [
																								createVNode(VCardTitle, null, {
																									default: withCtx(() => [createTextVNode("Form Sale")]),
																									_: 1
																								}),
																								createVNode(VCardText, null, {
																									default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																										default: withCtx(() => [createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VAutocomplete, {
																													items: entities.value,
																													"item-value": "id",
																													"item-title": "name",
																													modelValue: unref(formSale).entity_id,
																													"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																													density: "compact",
																													variant: "outlined",
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
																												default: withCtx(() => [createVNode(VDatePicker, {
																													modelValue: unref(formSale).date,
																													"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											}), createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formSale).total,
																													"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																													label: "Total",
																													placeholder: "Сумма продажи",
																													density: "comfortable",
																													variant: "solo"
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
																									default: withCtx(() => [createVNode(VBtn, {
																										text: "store",
																										onClick: storeSale,
																										variant: "elevated",
																										density: "comfortable",
																										color: "purple"
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
																			}), createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, { cols: "5" }, {
																					default: withCtx(() => [
																						createVNode(VDataTable, {
																							items: sales.value,
																							"items-per-page": "250",
																							headers: headerSales,
																							"fixed-header": "",
																							height: "1000px",
																							density: "compact",
																							hover: ""
																						}, {
																							"item.good": withCtx(({ item }) => [createVNode(VBtn, {
																								text: "+",
																								onClick: ($event) => openAttachDialog(item),
																								variant: "elevated",
																								color: "grey",
																								density: "compact",
																								size: "20"
																							}, null, 8, ["onClick"])]),
																							"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-[8px] font-sans" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
																							"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs font-sans" }, toDisplayString(unref(date).format(item.date, "fullDateWithWeekday")), 1)]),
																							"item.entity_id": withCtx(({ item }) => [createVNode("span", { class: "text-sm font-RobotoRegular" }, toDisplayString(item.entity.name), 1)]),
																							"item.total": withCtx(({ item }) => [createVNode("span", {
																								onClick: ($event) => showSale(item.id),
																								class: "cursor-pointer"
																							}, toDisplayString(item.total), 9, ["onClick"])]),
																							_: 1
																						}, 8, ["items"]),
																						createVNode(VDialog, {
																							modelValue: unref(showFormAttachGood),
																							"onUpdate:modelValue": ($event) => isRef(showFormAttachGood) ? showFormAttachGood.value = $event : showFormAttachGood = $event,
																							transition: "dialog-bottom-transition",
																							width: "1000"
																						}, {
																							default: withCtx(({ isActive }) => [createVNode(VCard, { theme: "dark" }, {
																								default: withCtx(() => [
																									createVNode(VCardTitle, null, {
																										default: withCtx(() => [createTextVNode("Form Attach Good")]),
																										_: 1
																									}),
																									createVNode(VCardText, { class: "text-red-700" }, {
																										default: withCtx(() => [createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																												default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																													default: withCtx(() => [createVNode(VRow, null, {
																														default: withCtx(() => [createVNode(VCol, null, {
																															default: withCtx(() => [createVNode(VAutocomplete, {
																																items: goods.value,
																																"item-value": "id",
																																"item-title": "name",
																																modelValue: unref(formAttachGood).good_id,
																																"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																																label: "Good",
																																variant: "outlined",
																																onChange: ($event) => showGood(unref(formAttachGood).good_id)
																															}, null, 8, [
																																"items",
																																"modelValue",
																																"onUpdate:modelValue",
																																"onChange"
																															])]),
																															_: 1
																														})]),
																														_: 1
																													}), createVNode(VRow, null, {
																														default: withCtx(() => [
																															createVNode(VCol, { cols: "3" }, {
																																default: withCtx(() => [createVNode(VTextField, {
																																	modelValue: unref(formAttachGood).quantity,
																																	"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																	label: "Количество",
																																	variant: "outlined",
																																	density: "compact",
																																	theme: "dark"
																																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																_: 1
																															}),
																															createVNode(VCol, { cols: "3" }, {
																																default: withCtx(() => [createVNode(VSelect, {
																																	items: measures.value,
																																	"item-value": "id",
																																	"item-title": "name",
																																	modelValue: unref(formAttachGood).measure_id,
																																	"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																	label: "Measures",
																																	variant: "outlined",
																																	density: "compact"
																																}, null, 8, [
																																	"items",
																																	"modelValue",
																																	"onUpdate:modelValue"
																																])]),
																																_: 1
																															}),
																															createVNode(VCol, { cols: "6" }, {
																																default: withCtx(() => [createVNode(VTextField, {
																																	modelValue: unref(formAttachGood).price,
																																	"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																	label: "Price",
																																	variant: "outlined",
																																	density: "comfortable"
																																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																_: 1
																															})
																														]),
																														_: 1
																													})]),
																													_: 1
																												}, 8, ["onSubmit"])]),
																												_: 1
																											}), createVNode(VCol, { cols: "5" }, {
																												default: withCtx(() => [createVNode(VSheet, null, {
																													default: withCtx(() => [createVNode(VRow, null, {
																														default: withCtx(() => [createVNode(VCol, null, {
																															default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																															_: 1
																														}), createVNode(VCol, null, {
																															default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																															_: 1
																														})]),
																														_: 1
																													}), createVNode(VRow, null, {
																														default: withCtx(() => [createVNode(VCol, null, {
																															default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
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
																									createVNode(VCardActions, null, {
																										default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
																											text: "attach",
																											onClick: attachGood,
																											variant: "text",
																											density: "compact",
																											color: "teal-lighten-2"
																										})]),
																										_: 1
																									})
																								]),
																								_: 1
																							})]),
																							_: 1
																						}, 8, ["modelValue", "onUpdate:modelValue"]),
																						createVNode(VSnackbar, {
																							modelValue: snackbar.value.show,
																							"onUpdate:modelValue": ($event) => snackbar.value.show = $event,
																							timeout: 2e3,
																							color: "green"
																						}, {
																							default: withCtx(() => [createTextVNode(toDisplayString(snackbar.value.text), 1)]),
																							_: 1
																						}, 8, ["modelValue", "onUpdate:modelValue"])
																					]),
																					_: 1
																				}), createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(sale.value?.goods ?? [], (good) => {
																							return openBlock(), createBlock(VListItem, null, {
																								default: withCtx(() => [createVNode(VRow, null, {
																									default: withCtx(() => [
																										createVNode(VCol, { cols: "8" }, {
																											default: withCtx(() => [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)]),
																											_: 2
																										}, 1024),
																										createVNode(VCol, { cols: "1" }, {
																											default: withCtx(() => [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)]),
																											_: 2
																										}, 1024),
																										createVNode(VCol, { cols: "1" }, {
																											default: withCtx(() => [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)]),
																											_: 2
																										}, 1024),
																										createVNode(VCol, null, {
																											default: withCtx(() => [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)]),
																											_: 2
																										}, 1024)
																									]),
																									_: 2
																								}, 1024)]),
																								_: 2
																							}, 1024);
																						}), 256))]),
																						_: 1
																					})]),
																					_: 1
																				})]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VContainer, { fluid: "" }, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, { cols: "1" }, {
																				default: withCtx(() => [createVNode(VDialog, { width: "1000" }, {
																					activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, { text: "Новая продажа" }), null, 16)]),
																					default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																						default: withCtx(() => [
																							createVNode(VCardTitle, null, {
																								default: withCtx(() => [createTextVNode("Form Sale")]),
																								_: 1
																							}),
																							createVNode(VCardText, null, {
																								default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																									default: withCtx(() => [createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, null, {
																											default: withCtx(() => [createVNode(VAutocomplete, {
																												items: entities.value,
																												"item-value": "id",
																												"item-title": "name",
																												modelValue: unref(formSale).entity_id,
																												"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																												density: "compact",
																												variant: "outlined",
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
																											default: withCtx(() => [createVNode(VDatePicker, {
																												modelValue: unref(formSale).date,
																												"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										}), createVNode(VCol, null, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formSale).total,
																												"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																												label: "Total",
																												placeholder: "Сумма продажи",
																												density: "comfortable",
																												variant: "solo"
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
																								default: withCtx(() => [createVNode(VBtn, {
																									text: "store",
																									onClick: storeSale,
																									variant: "elevated",
																									density: "comfortable",
																									color: "purple"
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
																		}), createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, { cols: "5" }, {
																				default: withCtx(() => [
																					createVNode(VDataTable, {
																						items: sales.value,
																						"items-per-page": "250",
																						headers: headerSales,
																						"fixed-header": "",
																						height: "1000px",
																						density: "compact",
																						hover: ""
																					}, {
																						"item.good": withCtx(({ item }) => [createVNode(VBtn, {
																							text: "+",
																							onClick: ($event) => openAttachDialog(item),
																							variant: "elevated",
																							color: "grey",
																							density: "compact",
																							size: "20"
																						}, null, 8, ["onClick"])]),
																						"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-[8px] font-sans" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
																						"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs font-sans" }, toDisplayString(unref(date).format(item.date, "fullDateWithWeekday")), 1)]),
																						"item.entity_id": withCtx(({ item }) => [createVNode("span", { class: "text-sm font-RobotoRegular" }, toDisplayString(item.entity.name), 1)]),
																						"item.total": withCtx(({ item }) => [createVNode("span", {
																							onClick: ($event) => showSale(item.id),
																							class: "cursor-pointer"
																						}, toDisplayString(item.total), 9, ["onClick"])]),
																						_: 1
																					}, 8, ["items"]),
																					createVNode(VDialog, {
																						modelValue: unref(showFormAttachGood),
																						"onUpdate:modelValue": ($event) => isRef(showFormAttachGood) ? showFormAttachGood.value = $event : showFormAttachGood = $event,
																						transition: "dialog-bottom-transition",
																						width: "1000"
																					}, {
																						default: withCtx(({ isActive }) => [createVNode(VCard, { theme: "dark" }, {
																							default: withCtx(() => [
																								createVNode(VCardTitle, null, {
																									default: withCtx(() => [createTextVNode("Form Attach Good")]),
																									_: 1
																								}),
																								createVNode(VCardText, { class: "text-red-700" }, {
																									default: withCtx(() => [createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																											default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																												default: withCtx(() => [createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VCol, null, {
																														default: withCtx(() => [createVNode(VAutocomplete, {
																															items: goods.value,
																															"item-value": "id",
																															"item-title": "name",
																															modelValue: unref(formAttachGood).good_id,
																															"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																															label: "Good",
																															variant: "outlined",
																															onChange: ($event) => showGood(unref(formAttachGood).good_id)
																														}, null, 8, [
																															"items",
																															"modelValue",
																															"onUpdate:modelValue",
																															"onChange"
																														])]),
																														_: 1
																													})]),
																													_: 1
																												}), createVNode(VRow, null, {
																													default: withCtx(() => [
																														createVNode(VCol, { cols: "3" }, {
																															default: withCtx(() => [createVNode(VTextField, {
																																modelValue: unref(formAttachGood).quantity,
																																"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																																label: "Количество",
																																variant: "outlined",
																																density: "compact",
																																theme: "dark"
																															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																															_: 1
																														}),
																														createVNode(VCol, { cols: "3" }, {
																															default: withCtx(() => [createVNode(VSelect, {
																																items: measures.value,
																																"item-value": "id",
																																"item-title": "name",
																																modelValue: unref(formAttachGood).measure_id,
																																"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																																label: "Measures",
																																variant: "outlined",
																																density: "compact"
																															}, null, 8, [
																																"items",
																																"modelValue",
																																"onUpdate:modelValue"
																															])]),
																															_: 1
																														}),
																														createVNode(VCol, { cols: "6" }, {
																															default: withCtx(() => [createVNode(VTextField, {
																																modelValue: unref(formAttachGood).price,
																																"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																																label: "Price",
																																variant: "outlined",
																																density: "comfortable"
																															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																															_: 1
																														})
																													]),
																													_: 1
																												})]),
																												_: 1
																											}, 8, ["onSubmit"])]),
																											_: 1
																										}), createVNode(VCol, { cols: "5" }, {
																											default: withCtx(() => [createVNode(VSheet, null, {
																												default: withCtx(() => [createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VCol, null, {
																														default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																														_: 1
																													}), createVNode(VCol, null, {
																														default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																														_: 1
																													})]),
																													_: 1
																												}), createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VCol, null, {
																														default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
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
																								createVNode(VCardActions, null, {
																									default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
																										text: "attach",
																										onClick: attachGood,
																										variant: "text",
																										density: "compact",
																										color: "teal-lighten-2"
																									})]),
																									_: 1
																								})
																							]),
																							_: 1
																						})]),
																						_: 1
																					}, 8, ["modelValue", "onUpdate:modelValue"]),
																					createVNode(VSnackbar, {
																						modelValue: snackbar.value.show,
																						"onUpdate:modelValue": ($event) => snackbar.value.show = $event,
																						timeout: 2e3,
																						color: "green"
																					}, {
																						default: withCtx(() => [createTextVNode(toDisplayString(snackbar.value.text), 1)]),
																						_: 1
																					}, 8, ["modelValue", "onUpdate:modelValue"])
																				]),
																				_: 1
																			}), createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																					default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(sale.value?.goods ?? [], (good) => {
																						return openBlock(), createBlock(VListItem, null, {
																							default: withCtx(() => [createVNode(VRow, null, {
																								default: withCtx(() => [
																									createVNode(VCol, { cols: "8" }, {
																										default: withCtx(() => [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)]),
																										_: 2
																									}, 1024),
																									createVNode(VCol, { cols: "1" }, {
																										default: withCtx(() => [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)]),
																										_: 2
																									}, 1024),
																									createVNode(VCol, { cols: "1" }, {
																										default: withCtx(() => [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)]),
																										_: 2
																									}, 1024),
																									createVNode(VCol, null, {
																										default: withCtx(() => [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)]),
																										_: 2
																									}, 1024)
																								]),
																								_: 2
																							}, 1024)]),
																							_: 2
																						}, 1024);
																					}), 256))]),
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
															_push(ssrRenderComponent(VTabsWindowItem, { value: "entities" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VContainer, null, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) {
																				_push(ssrRenderComponent(VRow, null, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) {
																							_push(ssrRenderComponent(VCol, { lg: "3" }, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(ssrRenderComponent(VTextField, {
																										modelValue: searchEntities.value,
																										"onUpdate:modelValue": ($event) => searchEntities.value = $event,
																										label: "Search for entities",
																										variant: "solo",
																										density: "compact",
																										"hide-details": ""
																									}, null, _parent, _scopeId));
																									else return [createVNode(VTextField, {
																										modelValue: searchEntities.value,
																										"onUpdate:modelValue": ($event) => searchEntities.value = $event,
																										label: "Search for entities",
																										variant: "solo",
																										density: "compact",
																										"hide-details": ""
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																							_push(ssrRenderComponent(VCol, { lg: "2" }, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) {
																										_push(ssrRenderComponent(VBtn, {
																											text: "+",
																											onClick: ($event) => showFormEntity.value = !showFormEntity.value,
																											variant: "elevated",
																											color: "purple-darken-4"
																										}, null, _parent, _scopeId));
																										_push(ssrRenderComponent(VDialog, {
																											modelValue: showFormEntity.value,
																											"onUpdate:modelValue": ($event) => showFormEntity.value = $event,
																											width: "990",
																											transition: "dialog-top-transition"
																										}, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(ssrRenderComponent(VCard, null, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) {
																															_push(ssrRenderComponent(VCardTitle, null, {
																																default: withCtx((_, _push, _parent, _scopeId) => {
																																	if (_push) _push(`Form Entity`);
																																	else return [createTextVNode("Form Entity")];
																																}),
																																_: 1
																															}, _parent, _scopeId));
																															_push(ssrRenderComponent(VCardText, null, {
																																default: withCtx((_, _push, _parent, _scopeId) => {
																																	if (_push) _push(ssrRenderComponent(VForm, { onSubmit: () => {} }, {
																																		default: withCtx((_, _push, _parent, _scopeId) => {
																																			if (_push) {
																																				_push(ssrRenderComponent(VRow, null, {
																																					default: withCtx((_, _push, _parent, _scopeId) => {
																																						if (_push) _push(ssrRenderComponent(VTextField, {
																																							modelValue: unref(formEntity).name,
																																							"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																																							label: "Name",
																																							variant: "outlined"
																																						}, null, _parent, _scopeId));
																																						else return [createVNode(VTextField, {
																																							modelValue: unref(formEntity).name,
																																							"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																																							label: "Name",
																																							variant: "outlined"
																																						}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																																					}),
																																					_: 1
																																				}, _parent, _scopeId));
																																				_push(ssrRenderComponent(VRow, null, {
																																					default: withCtx((_, _push, _parent, _scopeId) => {
																																						if (_push) {
																																							_push(ssrRenderComponent(VCol, null, {
																																								default: withCtx((_, _push, _parent, _scopeId) => {
																																									if (_push) _push(ssrRenderComponent(VSelect, {
																																										items: entityClassifications.value,
																																										"item-value": "id",
																																										"item-title": "name",
																																										modelValue: unref(formEntity).entity_classification_id,
																																										"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																																										variant: "outlined",
																																										label: "Вид"
																																									}, null, _parent, _scopeId));
																																									else return [createVNode(VSelect, {
																																										items: entityClassifications.value,
																																										"item-value": "id",
																																										"item-title": "name",
																																										modelValue: unref(formEntity).entity_classification_id,
																																										"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																																										variant: "outlined",
																																										label: "Вид"
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
																																									if (_push) _push(ssrRenderComponent(VAutocomplete, {
																																										items: telephones.value,
																																										"item-value": "id",
																																										"item-title": "number",
																																										modelValue: unref(formEntity).telephones,
																																										"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																																										label: "Telephones",
																																										placeholder: "number without +7",
																																										variant: "outlined",
																																										density: "comfortable",
																																										color: "purple-darken-4",
																																										multiple: "",
																																										chips: ""
																																									}, null, _parent, _scopeId));
																																									else return [createVNode(VAutocomplete, {
																																										items: telephones.value,
																																										"item-value": "id",
																																										"item-title": "number",
																																										modelValue: unref(formEntity).telephones,
																																										"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																																										label: "Telephones",
																																										placeholder: "number without +7",
																																										variant: "outlined",
																																										density: "comfortable",
																																										color: "purple-darken-4",
																																										multiple: "",
																																										chips: ""
																																									}, null, 8, [
																																										"items",
																																										"modelValue",
																																										"onUpdate:modelValue"
																																									])];
																																								}),
																																								_: 1
																																							}, _parent, _scopeId));
																																						} else return [createVNode(VCol, null, {
																																							default: withCtx(() => [createVNode(VSelect, {
																																								items: entityClassifications.value,
																																								"item-value": "id",
																																								"item-title": "name",
																																								modelValue: unref(formEntity).entity_classification_id,
																																								"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																																								variant: "outlined",
																																								label: "Вид"
																																							}, null, 8, [
																																								"items",
																																								"modelValue",
																																								"onUpdate:modelValue"
																																							])]),
																																							_: 1
																																						}), createVNode(VCol, null, {
																																							default: withCtx(() => [createVNode(VAutocomplete, {
																																								items: telephones.value,
																																								"item-value": "id",
																																								"item-title": "number",
																																								modelValue: unref(formEntity).telephones,
																																								"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																																								label: "Telephones",
																																								placeholder: "number without +7",
																																								variant: "outlined",
																																								density: "comfortable",
																																								color: "purple-darken-4",
																																								multiple: "",
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
																																						if (_push) {
																																							_push(ssrRenderComponent(VCol, null, {
																																								default: withCtx((_, _push, _parent, _scopeId) => {
																																									if (_push) _push(ssrRenderComponent(VAutocomplete, {
																																										items: cities.value,
																																										"item-value": "id",
																																										"item-title": "name",
																																										modelValue: unref(formEntity).cities,
																																										"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																																										label: "Cities",
																																										placeholder: "Населенный пункт",
																																										variant: "solo",
																																										density: "comfortable",
																																										color: "grey",
																																										multiple: ""
																																									}, null, _parent, _scopeId));
																																									else return [createVNode(VAutocomplete, {
																																										items: cities.value,
																																										"item-value": "id",
																																										"item-title": "name",
																																										modelValue: unref(formEntity).cities,
																																										"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																																										label: "Cities",
																																										placeholder: "Населенный пункт",
																																										variant: "solo",
																																										density: "comfortable",
																																										color: "grey",
																																										multiple: ""
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
																																									if (_push) _push(ssrRenderComponent(VAutocomplete, {
																																										items: buildings.value,
																																										"item-value": "id",
																																										"item-title": "address",
																																										modelValue: unref(formEntity).buildings,
																																										"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																																										multiple: "",
																																										label: "Buildings",
																																										placeholder: "Адрес здания",
																																										variant: "outlined",
																																										density: "comfortable",
																																										color: "teal"
																																									}, null, _parent, _scopeId));
																																									else return [createVNode(VAutocomplete, {
																																										items: buildings.value,
																																										"item-value": "id",
																																										"item-title": "address",
																																										modelValue: unref(formEntity).buildings,
																																										"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																																										multiple: "",
																																										label: "Buildings",
																																										placeholder: "Адрес здания",
																																										variant: "outlined",
																																										density: "comfortable",
																																										color: "teal"
																																									}, null, 8, [
																																										"items",
																																										"modelValue",
																																										"onUpdate:modelValue"
																																									])];
																																								}),
																																								_: 1
																																							}, _parent, _scopeId));
																																						} else return [createVNode(VCol, null, {
																																							default: withCtx(() => [createVNode(VAutocomplete, {
																																								items: cities.value,
																																								"item-value": "id",
																																								"item-title": "name",
																																								modelValue: unref(formEntity).cities,
																																								"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																																								label: "Cities",
																																								placeholder: "Населенный пункт",
																																								variant: "solo",
																																								density: "comfortable",
																																								color: "grey",
																																								multiple: ""
																																							}, null, 8, [
																																								"items",
																																								"modelValue",
																																								"onUpdate:modelValue"
																																							])]),
																																							_: 1
																																						}), createVNode(VCol, null, {
																																							default: withCtx(() => [createVNode(VAutocomplete, {
																																								items: buildings.value,
																																								"item-value": "id",
																																								"item-title": "address",
																																								modelValue: unref(formEntity).buildings,
																																								"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																																								multiple: "",
																																								label: "Buildings",
																																								placeholder: "Адрес здания",
																																								variant: "outlined",
																																								density: "comfortable",
																																								color: "teal"
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
																																			} else return [
																																				createVNode(VRow, null, {
																																					default: withCtx(() => [createVNode(VTextField, {
																																						modelValue: unref(formEntity).name,
																																						"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																																						label: "Name",
																																						variant: "outlined"
																																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																					_: 1
																																				}),
																																				createVNode(VRow, null, {
																																					default: withCtx(() => [createVNode(VCol, null, {
																																						default: withCtx(() => [createVNode(VSelect, {
																																							items: entityClassifications.value,
																																							"item-value": "id",
																																							"item-title": "name",
																																							modelValue: unref(formEntity).entity_classification_id,
																																							"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																																							variant: "outlined",
																																							label: "Вид"
																																						}, null, 8, [
																																							"items",
																																							"modelValue",
																																							"onUpdate:modelValue"
																																						])]),
																																						_: 1
																																					}), createVNode(VCol, null, {
																																						default: withCtx(() => [createVNode(VAutocomplete, {
																																							items: telephones.value,
																																							"item-value": "id",
																																							"item-title": "number",
																																							modelValue: unref(formEntity).telephones,
																																							"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																																							label: "Telephones",
																																							placeholder: "number without +7",
																																							variant: "outlined",
																																							density: "comfortable",
																																							color: "purple-darken-4",
																																							multiple: "",
																																							chips: ""
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
																																						default: withCtx(() => [createVNode(VAutocomplete, {
																																							items: cities.value,
																																							"item-value": "id",
																																							"item-title": "name",
																																							modelValue: unref(formEntity).cities,
																																							"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																																							label: "Cities",
																																							placeholder: "Населенный пункт",
																																							variant: "solo",
																																							density: "comfortable",
																																							color: "grey",
																																							multiple: ""
																																						}, null, 8, [
																																							"items",
																																							"modelValue",
																																							"onUpdate:modelValue"
																																						])]),
																																						_: 1
																																					}), createVNode(VCol, null, {
																																						default: withCtx(() => [createVNode(VAutocomplete, {
																																							items: buildings.value,
																																							"item-value": "id",
																																							"item-title": "address",
																																							modelValue: unref(formEntity).buildings,
																																							"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																																							multiple: "",
																																							label: "Buildings",
																																							placeholder: "Адрес здания",
																																							variant: "outlined",
																																							density: "comfortable",
																																							color: "teal"
																																						}, null, 8, [
																																							"items",
																																							"modelValue",
																																							"onUpdate:modelValue"
																																						])]),
																																						_: 1
																																					})]),
																																					_: 1
																																				})
																																			];
																																		}),
																																		_: 1
																																	}, _parent, _scopeId));
																																	else return [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																		default: withCtx(() => [
																																			createVNode(VRow, null, {
																																				default: withCtx(() => [createVNode(VTextField, {
																																					modelValue: unref(formEntity).name,
																																					"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																																					label: "Name",
																																					variant: "outlined"
																																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																				_: 1
																																			}),
																																			createVNode(VRow, null, {
																																				default: withCtx(() => [createVNode(VCol, null, {
																																					default: withCtx(() => [createVNode(VSelect, {
																																						items: entityClassifications.value,
																																						"item-value": "id",
																																						"item-title": "name",
																																						modelValue: unref(formEntity).entity_classification_id,
																																						"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																																						variant: "outlined",
																																						label: "Вид"
																																					}, null, 8, [
																																						"items",
																																						"modelValue",
																																						"onUpdate:modelValue"
																																					])]),
																																					_: 1
																																				}), createVNode(VCol, null, {
																																					default: withCtx(() => [createVNode(VAutocomplete, {
																																						items: telephones.value,
																																						"item-value": "id",
																																						"item-title": "number",
																																						modelValue: unref(formEntity).telephones,
																																						"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																																						label: "Telephones",
																																						placeholder: "number without +7",
																																						variant: "outlined",
																																						density: "comfortable",
																																						color: "purple-darken-4",
																																						multiple: "",
																																						chips: ""
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
																																					default: withCtx(() => [createVNode(VAutocomplete, {
																																						items: cities.value,
																																						"item-value": "id",
																																						"item-title": "name",
																																						modelValue: unref(formEntity).cities,
																																						"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																																						label: "Cities",
																																						placeholder: "Населенный пункт",
																																						variant: "solo",
																																						density: "comfortable",
																																						color: "grey",
																																						multiple: ""
																																					}, null, 8, [
																																						"items",
																																						"modelValue",
																																						"onUpdate:modelValue"
																																					])]),
																																					_: 1
																																				}), createVNode(VCol, null, {
																																					default: withCtx(() => [createVNode(VAutocomplete, {
																																						items: buildings.value,
																																						"item-value": "id",
																																						"item-title": "address",
																																						modelValue: unref(formEntity).buildings,
																																						"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																																						multiple: "",
																																						label: "Buildings",
																																						placeholder: "Адрес здания",
																																						variant: "outlined",
																																						density: "comfortable",
																																						color: "teal"
																																					}, null, 8, [
																																						"items",
																																						"modelValue",
																																						"onUpdate:modelValue"
																																					])]),
																																					_: 1
																																				})]),
																																				_: 1
																																			})
																																		]),
																																		_: 1
																																	}, 8, ["onSubmit"])];
																																}),
																																_: 1
																															}, _parent, _scopeId));
																															_push(ssrRenderComponent(VCardActions, null, {
																																default: withCtx((_, _push, _parent, _scopeId) => {
																																	if (_push) _push(ssrRenderComponent(VBtn, {
																																		onClick: storeEntity,
																																		text: "сохранить",
																																		variant: "flat"
																																	}, null, _parent, _scopeId));
																																	else return [createVNode(VBtn, {
																																		onClick: storeEntity,
																																		text: "сохранить",
																																		variant: "flat"
																																	})];
																																}),
																																_: 1
																															}, _parent, _scopeId));
																														} else return [
																															createVNode(VCardTitle, null, {
																																default: withCtx(() => [createTextVNode("Form Entity")]),
																																_: 1
																															}),
																															createVNode(VCardText, null, {
																																default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																	default: withCtx(() => [
																																		createVNode(VRow, null, {
																																			default: withCtx(() => [createVNode(VTextField, {
																																				modelValue: unref(formEntity).name,
																																				"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																																				label: "Name",
																																				variant: "outlined"
																																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																			_: 1
																																		}),
																																		createVNode(VRow, null, {
																																			default: withCtx(() => [createVNode(VCol, null, {
																																				default: withCtx(() => [createVNode(VSelect, {
																																					items: entityClassifications.value,
																																					"item-value": "id",
																																					"item-title": "name",
																																					modelValue: unref(formEntity).entity_classification_id,
																																					"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																																					variant: "outlined",
																																					label: "Вид"
																																				}, null, 8, [
																																					"items",
																																					"modelValue",
																																					"onUpdate:modelValue"
																																				])]),
																																				_: 1
																																			}), createVNode(VCol, null, {
																																				default: withCtx(() => [createVNode(VAutocomplete, {
																																					items: telephones.value,
																																					"item-value": "id",
																																					"item-title": "number",
																																					modelValue: unref(formEntity).telephones,
																																					"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																																					label: "Telephones",
																																					placeholder: "number without +7",
																																					variant: "outlined",
																																					density: "comfortable",
																																					color: "purple-darken-4",
																																					multiple: "",
																																					chips: ""
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
																																				default: withCtx(() => [createVNode(VAutocomplete, {
																																					items: cities.value,
																																					"item-value": "id",
																																					"item-title": "name",
																																					modelValue: unref(formEntity).cities,
																																					"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																																					label: "Cities",
																																					placeholder: "Населенный пункт",
																																					variant: "solo",
																																					density: "comfortable",
																																					color: "grey",
																																					multiple: ""
																																				}, null, 8, [
																																					"items",
																																					"modelValue",
																																					"onUpdate:modelValue"
																																				])]),
																																				_: 1
																																			}), createVNode(VCol, null, {
																																				default: withCtx(() => [createVNode(VAutocomplete, {
																																					items: buildings.value,
																																					"item-value": "id",
																																					"item-title": "address",
																																					modelValue: unref(formEntity).buildings,
																																					"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																																					multiple: "",
																																					label: "Buildings",
																																					placeholder: "Адрес здания",
																																					variant: "outlined",
																																					density: "comfortable",
																																					color: "teal"
																																				}, null, 8, [
																																					"items",
																																					"modelValue",
																																					"onUpdate:modelValue"
																																				])]),
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
																																	onClick: storeEntity,
																																	text: "сохранить",
																																	variant: "flat"
																																})]),
																																_: 1
																															})
																														];
																													}),
																													_: 1
																												}, _parent, _scopeId));
																												else return [createVNode(VCard, null, {
																													default: withCtx(() => [
																														createVNode(VCardTitle, null, {
																															default: withCtx(() => [createTextVNode("Form Entity")]),
																															_: 1
																														}),
																														createVNode(VCardText, null, {
																															default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																default: withCtx(() => [
																																	createVNode(VRow, null, {
																																		default: withCtx(() => [createVNode(VTextField, {
																																			modelValue: unref(formEntity).name,
																																			"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																																			label: "Name",
																																			variant: "outlined"
																																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																		_: 1
																																	}),
																																	createVNode(VRow, null, {
																																		default: withCtx(() => [createVNode(VCol, null, {
																																			default: withCtx(() => [createVNode(VSelect, {
																																				items: entityClassifications.value,
																																				"item-value": "id",
																																				"item-title": "name",
																																				modelValue: unref(formEntity).entity_classification_id,
																																				"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																																				variant: "outlined",
																																				label: "Вид"
																																			}, null, 8, [
																																				"items",
																																				"modelValue",
																																				"onUpdate:modelValue"
																																			])]),
																																			_: 1
																																		}), createVNode(VCol, null, {
																																			default: withCtx(() => [createVNode(VAutocomplete, {
																																				items: telephones.value,
																																				"item-value": "id",
																																				"item-title": "number",
																																				modelValue: unref(formEntity).telephones,
																																				"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																																				label: "Telephones",
																																				placeholder: "number without +7",
																																				variant: "outlined",
																																				density: "comfortable",
																																				color: "purple-darken-4",
																																				multiple: "",
																																				chips: ""
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
																																			default: withCtx(() => [createVNode(VAutocomplete, {
																																				items: cities.value,
																																				"item-value": "id",
																																				"item-title": "name",
																																				modelValue: unref(formEntity).cities,
																																				"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																																				label: "Cities",
																																				placeholder: "Населенный пункт",
																																				variant: "solo",
																																				density: "comfortable",
																																				color: "grey",
																																				multiple: ""
																																			}, null, 8, [
																																				"items",
																																				"modelValue",
																																				"onUpdate:modelValue"
																																			])]),
																																			_: 1
																																		}), createVNode(VCol, null, {
																																			default: withCtx(() => [createVNode(VAutocomplete, {
																																				items: buildings.value,
																																				"item-value": "id",
																																				"item-title": "address",
																																				modelValue: unref(formEntity).buildings,
																																				"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																																				multiple: "",
																																				label: "Buildings",
																																				placeholder: "Адрес здания",
																																				variant: "outlined",
																																				density: "comfortable",
																																				color: "teal"
																																			}, null, 8, [
																																				"items",
																																				"modelValue",
																																				"onUpdate:modelValue"
																																			])]),
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
																																onClick: storeEntity,
																																text: "сохранить",
																																variant: "flat"
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
																										text: "+",
																										onClick: ($event) => showFormEntity.value = !showFormEntity.value,
																										variant: "elevated",
																										color: "purple-darken-4"
																									}, null, 8, ["onClick"]), createVNode(VDialog, {
																										modelValue: showFormEntity.value,
																										"onUpdate:modelValue": ($event) => showFormEntity.value = $event,
																										width: "990",
																										transition: "dialog-top-transition"
																									}, {
																										default: withCtx(() => [createVNode(VCard, null, {
																											default: withCtx(() => [
																												createVNode(VCardTitle, null, {
																													default: withCtx(() => [createTextVNode("Form Entity")]),
																													_: 1
																												}),
																												createVNode(VCardText, null, {
																													default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																														default: withCtx(() => [
																															createVNode(VRow, null, {
																																default: withCtx(() => [createVNode(VTextField, {
																																	modelValue: unref(formEntity).name,
																																	"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																																	label: "Name",
																																	variant: "outlined"
																																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																_: 1
																															}),
																															createVNode(VRow, null, {
																																default: withCtx(() => [createVNode(VCol, null, {
																																	default: withCtx(() => [createVNode(VSelect, {
																																		items: entityClassifications.value,
																																		"item-value": "id",
																																		"item-title": "name",
																																		modelValue: unref(formEntity).entity_classification_id,
																																		"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																																		variant: "outlined",
																																		label: "Вид"
																																	}, null, 8, [
																																		"items",
																																		"modelValue",
																																		"onUpdate:modelValue"
																																	])]),
																																	_: 1
																																}), createVNode(VCol, null, {
																																	default: withCtx(() => [createVNode(VAutocomplete, {
																																		items: telephones.value,
																																		"item-value": "id",
																																		"item-title": "number",
																																		modelValue: unref(formEntity).telephones,
																																		"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																																		label: "Telephones",
																																		placeholder: "number without +7",
																																		variant: "outlined",
																																		density: "comfortable",
																																		color: "purple-darken-4",
																																		multiple: "",
																																		chips: ""
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
																																	default: withCtx(() => [createVNode(VAutocomplete, {
																																		items: cities.value,
																																		"item-value": "id",
																																		"item-title": "name",
																																		modelValue: unref(formEntity).cities,
																																		"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																																		label: "Cities",
																																		placeholder: "Населенный пункт",
																																		variant: "solo",
																																		density: "comfortable",
																																		color: "grey",
																																		multiple: ""
																																	}, null, 8, [
																																		"items",
																																		"modelValue",
																																		"onUpdate:modelValue"
																																	])]),
																																	_: 1
																																}), createVNode(VCol, null, {
																																	default: withCtx(() => [createVNode(VAutocomplete, {
																																		items: buildings.value,
																																		"item-value": "id",
																																		"item-title": "address",
																																		modelValue: unref(formEntity).buildings,
																																		"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																																		multiple: "",
																																		label: "Buildings",
																																		placeholder: "Адрес здания",
																																		variant: "outlined",
																																		density: "comfortable",
																																		color: "teal"
																																	}, null, 8, [
																																		"items",
																																		"modelValue",
																																		"onUpdate:modelValue"
																																	])]),
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
																														onClick: storeEntity,
																														text: "сохранить",
																														variant: "flat"
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
																						} else return [createVNode(VCol, { lg: "3" }, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: searchEntities.value,
																								"onUpdate:modelValue": ($event) => searchEntities.value = $event,
																								label: "Search for entities",
																								variant: "solo",
																								density: "compact",
																								"hide-details": ""
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}), createVNode(VCol, { lg: "2" }, {
																							default: withCtx(() => [createVNode(VBtn, {
																								text: "+",
																								onClick: ($event) => showFormEntity.value = !showFormEntity.value,
																								variant: "elevated",
																								color: "purple-darken-4"
																							}, null, 8, ["onClick"]), createVNode(VDialog, {
																								modelValue: showFormEntity.value,
																								"onUpdate:modelValue": ($event) => showFormEntity.value = $event,
																								width: "990",
																								transition: "dialog-top-transition"
																							}, {
																								default: withCtx(() => [createVNode(VCard, null, {
																									default: withCtx(() => [
																										createVNode(VCardTitle, null, {
																											default: withCtx(() => [createTextVNode("Form Entity")]),
																											_: 1
																										}),
																										createVNode(VCardText, null, {
																											default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																												default: withCtx(() => [
																													createVNode(VRow, null, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(formEntity).name,
																															"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																															label: "Name",
																															variant: "outlined"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																														_: 1
																													}),
																													createVNode(VRow, null, {
																														default: withCtx(() => [createVNode(VCol, null, {
																															default: withCtx(() => [createVNode(VSelect, {
																																items: entityClassifications.value,
																																"item-value": "id",
																																"item-title": "name",
																																modelValue: unref(formEntity).entity_classification_id,
																																"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																																variant: "outlined",
																																label: "Вид"
																															}, null, 8, [
																																"items",
																																"modelValue",
																																"onUpdate:modelValue"
																															])]),
																															_: 1
																														}), createVNode(VCol, null, {
																															default: withCtx(() => [createVNode(VAutocomplete, {
																																items: telephones.value,
																																"item-value": "id",
																																"item-title": "number",
																																modelValue: unref(formEntity).telephones,
																																"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																																label: "Telephones",
																																placeholder: "number without +7",
																																variant: "outlined",
																																density: "comfortable",
																																color: "purple-darken-4",
																																multiple: "",
																																chips: ""
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
																															default: withCtx(() => [createVNode(VAutocomplete, {
																																items: cities.value,
																																"item-value": "id",
																																"item-title": "name",
																																modelValue: unref(formEntity).cities,
																																"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																																label: "Cities",
																																placeholder: "Населенный пункт",
																																variant: "solo",
																																density: "comfortable",
																																color: "grey",
																																multiple: ""
																															}, null, 8, [
																																"items",
																																"modelValue",
																																"onUpdate:modelValue"
																															])]),
																															_: 1
																														}), createVNode(VCol, null, {
																															default: withCtx(() => [createVNode(VAutocomplete, {
																																items: buildings.value,
																																"item-value": "id",
																																"item-title": "address",
																																modelValue: unref(formEntity).buildings,
																																"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																																multiple: "",
																																label: "Buildings",
																																placeholder: "Адрес здания",
																																variant: "outlined",
																																density: "comfortable",
																																color: "teal"
																															}, null, 8, [
																																"items",
																																"modelValue",
																																"onUpdate:modelValue"
																															])]),
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
																												onClick: storeEntity,
																												text: "сохранить",
																												variant: "flat"
																											})]),
																											_: 1
																										})
																									]),
																									_: 1
																								})]),
																								_: 1
																							}, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						})];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																				_push(ssrRenderComponent(VRow, null, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) {
																							_push(ssrRenderComponent(VCol, { cols: "1" }, null, _parent, _scopeId));
																							_push(ssrRenderComponent(VCol, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(ssrRenderComponent(VDataTable, {
																										items: filteredEntities.value,
																										"items-per-page": "125",
																										headers: headerEntities,
																										"fixed-header": "",
																										density: "compact",
																										hover: "",
																										height: "1000px"
																									}, {
																										"item.telephones": withCtx(({ item }, _push, _parent, _scopeId) => {
																											if (_push) {
																												_push(`<!--[-->`);
																												ssrRenderList(item.telephones, (telephone) => {
																													_push(`<div${_scopeId}>${ssrInterpolate(telephone.number)}</div>`);
																												});
																												_push(`<!--]-->`);
																											} else return [(openBlock(true), createBlock(Fragment, null, renderList(item.telephones, (telephone) => {
																												return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																											}), 256))];
																										}),
																										"item.units": withCtx(({ item }, _push, _parent, _scopeId) => {
																											if (_push) {
																												_push(`<!--[-->`);
																												ssrRenderList(item.units, (unit) => {
																													_push(`<div class="font-RobotoRegular text-sm"${_scopeId}>${ssrInterpolate(unit.name)}</div>`);
																												});
																												_push(`<!--]-->`);
																											} else return [(openBlock(true), createBlock(Fragment, null, renderList(item.units, (unit) => {
																												return openBlock(), createBlock("div", { class: "font-RobotoRegular text-sm" }, toDisplayString(unit.name), 1);
																											}), 256))];
																										}),
																										"item.cities": withCtx(({ item }, _push, _parent, _scopeId) => {
																											if (_push) {
																												_push(`<!--[-->`);
																												ssrRenderList(item.cities, (city) => {
																													_push(`<div class="text-sm"${_scopeId}>${ssrInterpolate(city.name)}</div>`);
																												});
																												_push(`<!--]-->`);
																											} else return [(openBlock(true), createBlock(Fragment, null, renderList(item.cities, (city) => {
																												return openBlock(), createBlock("div", { class: "text-sm" }, toDisplayString(city.name), 1);
																											}), 256))];
																										}),
																										"item.buildings": withCtx(({ item }, _push, _parent, _scopeId) => {
																											if (_push) {
																												_push(`<!--[-->`);
																												ssrRenderList(item.buildings, (building) => {
																													_push(`<div class="font-Typingrad text-[10px]"${_scopeId}><div${_scopeId}>${ssrInterpolate(building.city.name)}</div><div${_scopeId}>${ssrInterpolate(building.address)}</div></div>`);
																												});
																												_push(`<!--]-->`);
																											} else return [(openBlock(true), createBlock(Fragment, null, renderList(item.buildings, (building) => {
																												return openBlock(), createBlock("div", { class: "font-Typingrad text-[10px]" }, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
																											}), 256))];
																										}),
																										_: 1
																									}, _parent, _scopeId));
																									else return [createVNode(VDataTable, {
																										items: filteredEntities.value,
																										"items-per-page": "125",
																										headers: headerEntities,
																										"fixed-header": "",
																										density: "compact",
																										hover: "",
																										height: "1000px"
																									}, {
																										"item.telephones": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.telephones, (telephone) => {
																											return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																										}), 256))]),
																										"item.units": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.units, (unit) => {
																											return openBlock(), createBlock("div", { class: "font-RobotoRegular text-sm" }, toDisplayString(unit.name), 1);
																										}), 256))]),
																										"item.cities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.cities, (city) => {
																											return openBlock(), createBlock("div", { class: "text-sm" }, toDisplayString(city.name), 1);
																										}), 256))]),
																										"item.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.buildings, (building) => {
																											return openBlock(), createBlock("div", { class: "font-Typingrad text-[10px]" }, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
																										}), 256))]),
																										_: 1
																									}, 8, ["items"])];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																							_push(ssrRenderComponent(VCol, { cols: "1" }, null, _parent, _scopeId));
																						} else return [
																							createVNode(VCol, { cols: "1" }),
																							createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VDataTable, {
																									items: filteredEntities.value,
																									"items-per-page": "125",
																									headers: headerEntities,
																									"fixed-header": "",
																									density: "compact",
																									hover: "",
																									height: "1000px"
																								}, {
																									"item.telephones": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.telephones, (telephone) => {
																										return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																									}), 256))]),
																									"item.units": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.units, (unit) => {
																										return openBlock(), createBlock("div", { class: "font-RobotoRegular text-sm" }, toDisplayString(unit.name), 1);
																									}), 256))]),
																									"item.cities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.cities, (city) => {
																										return openBlock(), createBlock("div", { class: "text-sm" }, toDisplayString(city.name), 1);
																									}), 256))]),
																									"item.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.buildings, (building) => {
																										return openBlock(), createBlock("div", { class: "font-Typingrad text-[10px]" }, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
																									}), 256))]),
																									_: 1
																								}, 8, ["items"])]),
																								_: 1
																							}),
																							createVNode(VCol, { cols: "1" })
																						];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																			} else return [createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, { lg: "3" }, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: searchEntities.value,
																						"onUpdate:modelValue": ($event) => searchEntities.value = $event,
																						label: "Search for entities",
																						variant: "solo",
																						density: "compact",
																						"hide-details": ""
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}), createVNode(VCol, { lg: "2" }, {
																					default: withCtx(() => [createVNode(VBtn, {
																						text: "+",
																						onClick: ($event) => showFormEntity.value = !showFormEntity.value,
																						variant: "elevated",
																						color: "purple-darken-4"
																					}, null, 8, ["onClick"]), createVNode(VDialog, {
																						modelValue: showFormEntity.value,
																						"onUpdate:modelValue": ($event) => showFormEntity.value = $event,
																						width: "990",
																						transition: "dialog-top-transition"
																					}, {
																						default: withCtx(() => [createVNode(VCard, null, {
																							default: withCtx(() => [
																								createVNode(VCardTitle, null, {
																									default: withCtx(() => [createTextVNode("Form Entity")]),
																									_: 1
																								}),
																								createVNode(VCardText, null, {
																									default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																										default: withCtx(() => [
																											createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formEntity).name,
																													"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																													label: "Name",
																													variant: "outlined"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											}),
																											createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VCol, null, {
																													default: withCtx(() => [createVNode(VSelect, {
																														items: entityClassifications.value,
																														"item-value": "id",
																														"item-title": "name",
																														modelValue: unref(formEntity).entity_classification_id,
																														"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																														variant: "outlined",
																														label: "Вид"
																													}, null, 8, [
																														"items",
																														"modelValue",
																														"onUpdate:modelValue"
																													])]),
																													_: 1
																												}), createVNode(VCol, null, {
																													default: withCtx(() => [createVNode(VAutocomplete, {
																														items: telephones.value,
																														"item-value": "id",
																														"item-title": "number",
																														modelValue: unref(formEntity).telephones,
																														"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																														label: "Telephones",
																														placeholder: "number without +7",
																														variant: "outlined",
																														density: "comfortable",
																														color: "purple-darken-4",
																														multiple: "",
																														chips: ""
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
																													default: withCtx(() => [createVNode(VAutocomplete, {
																														items: cities.value,
																														"item-value": "id",
																														"item-title": "name",
																														modelValue: unref(formEntity).cities,
																														"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																														label: "Cities",
																														placeholder: "Населенный пункт",
																														variant: "solo",
																														density: "comfortable",
																														color: "grey",
																														multiple: ""
																													}, null, 8, [
																														"items",
																														"modelValue",
																														"onUpdate:modelValue"
																													])]),
																													_: 1
																												}), createVNode(VCol, null, {
																													default: withCtx(() => [createVNode(VAutocomplete, {
																														items: buildings.value,
																														"item-value": "id",
																														"item-title": "address",
																														modelValue: unref(formEntity).buildings,
																														"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																														multiple: "",
																														label: "Buildings",
																														placeholder: "Адрес здания",
																														variant: "outlined",
																														density: "comfortable",
																														color: "teal"
																													}, null, 8, [
																														"items",
																														"modelValue",
																														"onUpdate:modelValue"
																													])]),
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
																										onClick: storeEntity,
																										text: "сохранить",
																										variant: "flat"
																									})]),
																									_: 1
																								})
																							]),
																							_: 1
																						})]),
																						_: 1
																					}, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				})]),
																				_: 1
																			}), createVNode(VRow, null, {
																				default: withCtx(() => [
																					createVNode(VCol, { cols: "1" }),
																					createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VDataTable, {
																							items: filteredEntities.value,
																							"items-per-page": "125",
																							headers: headerEntities,
																							"fixed-header": "",
																							density: "compact",
																							hover: "",
																							height: "1000px"
																						}, {
																							"item.telephones": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.telephones, (telephone) => {
																								return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																							}), 256))]),
																							"item.units": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.units, (unit) => {
																								return openBlock(), createBlock("div", { class: "font-RobotoRegular text-sm" }, toDisplayString(unit.name), 1);
																							}), 256))]),
																							"item.cities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.cities, (city) => {
																								return openBlock(), createBlock("div", { class: "text-sm" }, toDisplayString(city.name), 1);
																							}), 256))]),
																							"item.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.buildings, (building) => {
																								return openBlock(), createBlock("div", { class: "font-Typingrad text-[10px]" }, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
																							}), 256))]),
																							_: 1
																						}, 8, ["items"])]),
																						_: 1
																					}),
																					createVNode(VCol, { cols: "1" })
																				]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VContainer, null, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, { lg: "3" }, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: searchEntities.value,
																					"onUpdate:modelValue": ($event) => searchEntities.value = $event,
																					label: "Search for entities",
																					variant: "solo",
																					density: "compact",
																					"hide-details": ""
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}), createVNode(VCol, { lg: "2" }, {
																				default: withCtx(() => [createVNode(VBtn, {
																					text: "+",
																					onClick: ($event) => showFormEntity.value = !showFormEntity.value,
																					variant: "elevated",
																					color: "purple-darken-4"
																				}, null, 8, ["onClick"]), createVNode(VDialog, {
																					modelValue: showFormEntity.value,
																					"onUpdate:modelValue": ($event) => showFormEntity.value = $event,
																					width: "990",
																					transition: "dialog-top-transition"
																				}, {
																					default: withCtx(() => [createVNode(VCard, null, {
																						default: withCtx(() => [
																							createVNode(VCardTitle, null, {
																								default: withCtx(() => [createTextVNode("Form Entity")]),
																								_: 1
																							}),
																							createVNode(VCardText, null, {
																								default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																									default: withCtx(() => [
																										createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formEntity).name,
																												"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																												label: "Name",
																												variant: "outlined"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										}),
																										createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VSelect, {
																													items: entityClassifications.value,
																													"item-value": "id",
																													"item-title": "name",
																													modelValue: unref(formEntity).entity_classification_id,
																													"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																													variant: "outlined",
																													label: "Вид"
																												}, null, 8, [
																													"items",
																													"modelValue",
																													"onUpdate:modelValue"
																												])]),
																												_: 1
																											}), createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VAutocomplete, {
																													items: telephones.value,
																													"item-value": "id",
																													"item-title": "number",
																													modelValue: unref(formEntity).telephones,
																													"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																													label: "Telephones",
																													placeholder: "number without +7",
																													variant: "outlined",
																													density: "comfortable",
																													color: "purple-darken-4",
																													multiple: "",
																													chips: ""
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
																												default: withCtx(() => [createVNode(VAutocomplete, {
																													items: cities.value,
																													"item-value": "id",
																													"item-title": "name",
																													modelValue: unref(formEntity).cities,
																													"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																													label: "Cities",
																													placeholder: "Населенный пункт",
																													variant: "solo",
																													density: "comfortable",
																													color: "grey",
																													multiple: ""
																												}, null, 8, [
																													"items",
																													"modelValue",
																													"onUpdate:modelValue"
																												])]),
																												_: 1
																											}), createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VAutocomplete, {
																													items: buildings.value,
																													"item-value": "id",
																													"item-title": "address",
																													modelValue: unref(formEntity).buildings,
																													"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																													multiple: "",
																													label: "Buildings",
																													placeholder: "Адрес здания",
																													variant: "outlined",
																													density: "comfortable",
																													color: "teal"
																												}, null, 8, [
																													"items",
																													"modelValue",
																													"onUpdate:modelValue"
																												])]),
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
																									onClick: storeEntity,
																									text: "сохранить",
																									variant: "flat"
																								})]),
																								_: 1
																							})
																						]),
																						_: 1
																					})]),
																					_: 1
																				}, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			})]),
																			_: 1
																		}), createVNode(VRow, null, {
																			default: withCtx(() => [
																				createVNode(VCol, { cols: "1" }),
																				createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VDataTable, {
																						items: filteredEntities.value,
																						"items-per-page": "125",
																						headers: headerEntities,
																						"fixed-header": "",
																						density: "compact",
																						hover: "",
																						height: "1000px"
																					}, {
																						"item.telephones": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.telephones, (telephone) => {
																							return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																						}), 256))]),
																						"item.units": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.units, (unit) => {
																							return openBlock(), createBlock("div", { class: "font-RobotoRegular text-sm" }, toDisplayString(unit.name), 1);
																						}), 256))]),
																						"item.cities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.cities, (city) => {
																							return openBlock(), createBlock("div", { class: "text-sm" }, toDisplayString(city.name), 1);
																						}), 256))]),
																						"item.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.buildings, (building) => {
																							return openBlock(), createBlock("div", { class: "font-Typingrad text-[10px]" }, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
																						}), 256))]),
																						_: 1
																					}, 8, ["items"])]),
																					_: 1
																				}),
																				createVNode(VCol, { cols: "1" })
																			]),
																			_: 1
																		})]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [createVNode(VTabsWindowItem, { value: "sales" }, {
															default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, { cols: "1" }, {
																		default: withCtx(() => [createVNode(VDialog, { width: "1000" }, {
																			activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, { text: "Новая продажа" }), null, 16)]),
																			default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																				default: withCtx(() => [
																					createVNode(VCardTitle, null, {
																						default: withCtx(() => [createTextVNode("Form Sale")]),
																						_: 1
																					}),
																					createVNode(VCardText, null, {
																						default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																							default: withCtx(() => [createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VAutocomplete, {
																										items: entities.value,
																										"item-value": "id",
																										"item-title": "name",
																										modelValue: unref(formSale).entity_id,
																										"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																										density: "compact",
																										variant: "outlined",
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
																									default: withCtx(() => [createVNode(VDatePicker, {
																										modelValue: unref(formSale).date,
																										"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}), createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formSale).total,
																										"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																										label: "Total",
																										placeholder: "Сумма продажи",
																										density: "comfortable",
																										variant: "solo"
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
																						default: withCtx(() => [createVNode(VBtn, {
																							text: "store",
																							onClick: storeSale,
																							variant: "elevated",
																							density: "comfortable",
																							color: "purple"
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
																}), createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, { cols: "5" }, {
																		default: withCtx(() => [
																			createVNode(VDataTable, {
																				items: sales.value,
																				"items-per-page": "250",
																				headers: headerSales,
																				"fixed-header": "",
																				height: "1000px",
																				density: "compact",
																				hover: ""
																			}, {
																				"item.good": withCtx(({ item }) => [createVNode(VBtn, {
																					text: "+",
																					onClick: ($event) => openAttachDialog(item),
																					variant: "elevated",
																					color: "grey",
																					density: "compact",
																					size: "20"
																				}, null, 8, ["onClick"])]),
																				"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-[8px] font-sans" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
																				"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs font-sans" }, toDisplayString(unref(date).format(item.date, "fullDateWithWeekday")), 1)]),
																				"item.entity_id": withCtx(({ item }) => [createVNode("span", { class: "text-sm font-RobotoRegular" }, toDisplayString(item.entity.name), 1)]),
																				"item.total": withCtx(({ item }) => [createVNode("span", {
																					onClick: ($event) => showSale(item.id),
																					class: "cursor-pointer"
																				}, toDisplayString(item.total), 9, ["onClick"])]),
																				_: 1
																			}, 8, ["items"]),
																			createVNode(VDialog, {
																				modelValue: unref(showFormAttachGood),
																				"onUpdate:modelValue": ($event) => isRef(showFormAttachGood) ? showFormAttachGood.value = $event : showFormAttachGood = $event,
																				transition: "dialog-bottom-transition",
																				width: "1000"
																			}, {
																				default: withCtx(({ isActive }) => [createVNode(VCard, { theme: "dark" }, {
																					default: withCtx(() => [
																						createVNode(VCardTitle, null, {
																							default: withCtx(() => [createTextVNode("Form Attach Good")]),
																							_: 1
																						}),
																						createVNode(VCardText, { class: "text-red-700" }, {
																							default: withCtx(() => [createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																									default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																										default: withCtx(() => [createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VAutocomplete, {
																													items: goods.value,
																													"item-value": "id",
																													"item-title": "name",
																													modelValue: unref(formAttachGood).good_id,
																													"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																													label: "Good",
																													variant: "outlined",
																													onChange: ($event) => showGood(unref(formAttachGood).good_id)
																												}, null, 8, [
																													"items",
																													"modelValue",
																													"onUpdate:modelValue",
																													"onChange"
																												])]),
																												_: 1
																											})]),
																											_: 1
																										}), createVNode(VRow, null, {
																											default: withCtx(() => [
																												createVNode(VCol, { cols: "3" }, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formAttachGood).quantity,
																														"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																														label: "Количество",
																														variant: "outlined",
																														density: "compact",
																														theme: "dark"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												}),
																												createVNode(VCol, { cols: "3" }, {
																													default: withCtx(() => [createVNode(VSelect, {
																														items: measures.value,
																														"item-value": "id",
																														"item-title": "name",
																														modelValue: unref(formAttachGood).measure_id,
																														"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																														label: "Measures",
																														variant: "outlined",
																														density: "compact"
																													}, null, 8, [
																														"items",
																														"modelValue",
																														"onUpdate:modelValue"
																													])]),
																													_: 1
																												}),
																												createVNode(VCol, { cols: "6" }, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formAttachGood).price,
																														"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																														label: "Price",
																														variant: "outlined",
																														density: "comfortable"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												})
																											]),
																											_: 1
																										})]),
																										_: 1
																									}, 8, ["onSubmit"])]),
																									_: 1
																								}), createVNode(VCol, { cols: "5" }, {
																									default: withCtx(() => [createVNode(VSheet, null, {
																										default: withCtx(() => [createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, null, {
																												default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																												_: 1
																											}), createVNode(VCol, null, {
																												default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																												_: 1
																											})]),
																											_: 1
																										}), createVNode(VRow, null, {
																											default: withCtx(() => [createVNode(VCol, null, {
																												default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
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
																						createVNode(VCardActions, null, {
																							default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
																								text: "attach",
																								onClick: attachGood,
																								variant: "text",
																								density: "compact",
																								color: "teal-lighten-2"
																							})]),
																							_: 1
																						})
																					]),
																					_: 1
																				})]),
																				_: 1
																			}, 8, ["modelValue", "onUpdate:modelValue"]),
																			createVNode(VSnackbar, {
																				modelValue: snackbar.value.show,
																				"onUpdate:modelValue": ($event) => snackbar.value.show = $event,
																				timeout: 2e3,
																				color: "green"
																			}, {
																				default: withCtx(() => [createTextVNode(toDisplayString(snackbar.value.text), 1)]),
																				_: 1
																			}, 8, ["modelValue", "onUpdate:modelValue"])
																		]),
																		_: 1
																	}), createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																			default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(sale.value?.goods ?? [], (good) => {
																				return openBlock(), createBlock(VListItem, null, {
																					default: withCtx(() => [createVNode(VRow, null, {
																						default: withCtx(() => [
																							createVNode(VCol, { cols: "8" }, {
																								default: withCtx(() => [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)]),
																								_: 2
																							}, 1024),
																							createVNode(VCol, { cols: "1" }, {
																								default: withCtx(() => [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)]),
																								_: 2
																							}, 1024),
																							createVNode(VCol, { cols: "1" }, {
																								default: withCtx(() => [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)]),
																								_: 2
																							}, 1024),
																							createVNode(VCol, null, {
																								default: withCtx(() => [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)]),
																								_: 2
																							}, 1024)
																						]),
																						_: 2
																					}, 1024)]),
																					_: 2
																				}, 1024);
																			}), 256))]),
																			_: 1
																		})]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														}), createVNode(VTabsWindowItem, { value: "entities" }, {
															default: withCtx(() => [createVNode(VContainer, null, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, { lg: "3" }, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: searchEntities.value,
																			"onUpdate:modelValue": ($event) => searchEntities.value = $event,
																			label: "Search for entities",
																			variant: "solo",
																			density: "compact",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}), createVNode(VCol, { lg: "2" }, {
																		default: withCtx(() => [createVNode(VBtn, {
																			text: "+",
																			onClick: ($event) => showFormEntity.value = !showFormEntity.value,
																			variant: "elevated",
																			color: "purple-darken-4"
																		}, null, 8, ["onClick"]), createVNode(VDialog, {
																			modelValue: showFormEntity.value,
																			"onUpdate:modelValue": ($event) => showFormEntity.value = $event,
																			width: "990",
																			transition: "dialog-top-transition"
																		}, {
																			default: withCtx(() => [createVNode(VCard, null, {
																				default: withCtx(() => [
																					createVNode(VCardTitle, null, {
																						default: withCtx(() => [createTextVNode("Form Entity")]),
																						_: 1
																					}),
																					createVNode(VCardText, null, {
																						default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																							default: withCtx(() => [
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formEntity).name,
																										"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																										label: "Name",
																										variant: "outlined"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}),
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VSelect, {
																											items: entityClassifications.value,
																											"item-value": "id",
																											"item-title": "name",
																											modelValue: unref(formEntity).entity_classification_id,
																											"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																											variant: "outlined",
																											label: "Вид"
																										}, null, 8, [
																											"items",
																											"modelValue",
																											"onUpdate:modelValue"
																										])]),
																										_: 1
																									}), createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VAutocomplete, {
																											items: telephones.value,
																											"item-value": "id",
																											"item-title": "number",
																											modelValue: unref(formEntity).telephones,
																											"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																											label: "Telephones",
																											placeholder: "number without +7",
																											variant: "outlined",
																											density: "comfortable",
																											color: "purple-darken-4",
																											multiple: "",
																											chips: ""
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
																										default: withCtx(() => [createVNode(VAutocomplete, {
																											items: cities.value,
																											"item-value": "id",
																											"item-title": "name",
																											modelValue: unref(formEntity).cities,
																											"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																											label: "Cities",
																											placeholder: "Населенный пункт",
																											variant: "solo",
																											density: "comfortable",
																											color: "grey",
																											multiple: ""
																										}, null, 8, [
																											"items",
																											"modelValue",
																											"onUpdate:modelValue"
																										])]),
																										_: 1
																									}), createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VAutocomplete, {
																											items: buildings.value,
																											"item-value": "id",
																											"item-title": "address",
																											modelValue: unref(formEntity).buildings,
																											"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																											multiple: "",
																											label: "Buildings",
																											placeholder: "Адрес здания",
																											variant: "outlined",
																											density: "comfortable",
																											color: "teal"
																										}, null, 8, [
																											"items",
																											"modelValue",
																											"onUpdate:modelValue"
																										])]),
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
																							onClick: storeEntity,
																							text: "сохранить",
																							variant: "flat"
																						})]),
																						_: 1
																					})
																				]),
																				_: 1
																			})]),
																			_: 1
																		}, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})]),
																	_: 1
																}), createVNode(VRow, null, {
																	default: withCtx(() => [
																		createVNode(VCol, { cols: "1" }),
																		createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VDataTable, {
																				items: filteredEntities.value,
																				"items-per-page": "125",
																				headers: headerEntities,
																				"fixed-header": "",
																				density: "compact",
																				hover: "",
																				height: "1000px"
																			}, {
																				"item.telephones": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.telephones, (telephone) => {
																					return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																				}), 256))]),
																				"item.units": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.units, (unit) => {
																					return openBlock(), createBlock("div", { class: "font-RobotoRegular text-sm" }, toDisplayString(unit.name), 1);
																				}), 256))]),
																				"item.cities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.cities, (city) => {
																					return openBlock(), createBlock("div", { class: "text-sm" }, toDisplayString(city.name), 1);
																				}), 256))]),
																				"item.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.buildings, (building) => {
																					return openBlock(), createBlock("div", { class: "font-Typingrad text-[10px]" }, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
																				}), 256))]),
																				_: 1
																			}, 8, ["items"])]),
																			_: 1
																		}),
																		createVNode(VCol, { cols: "1" })
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
											} else return [createVNode(VTabs, {
												modelValue: tab.value,
												"onUpdate:modelValue": ($event) => tab.value = $event,
												"align-tabs": "center",
												color: "deep-purple-accent-4"
											}, {
												default: withCtx(() => [createVNode(VTab, { value: "sales" }, {
													default: withCtx(() => [createTextVNode("Продажи")]),
													_: 1
												}), createVNode(VTab, { value: "entities" }, {
													default: withCtx(() => [createTextVNode("Entities")]),
													_: 1
												})]),
												_: 1
											}, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VTabsWindow, {
												modelValue: tab.value,
												"onUpdate:modelValue": ($event) => tab.value = $event
											}, {
												default: withCtx(() => [createVNode(VTabsWindowItem, { value: "sales" }, {
													default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
														default: withCtx(() => [createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, { cols: "1" }, {
																default: withCtx(() => [createVNode(VDialog, { width: "1000" }, {
																	activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, { text: "Новая продажа" }), null, 16)]),
																	default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																		default: withCtx(() => [
																			createVNode(VCardTitle, null, {
																				default: withCtx(() => [createTextVNode("Form Sale")]),
																				_: 1
																			}),
																			createVNode(VCardText, null, {
																				default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																					default: withCtx(() => [createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VAutocomplete, {
																								items: entities.value,
																								"item-value": "id",
																								"item-title": "name",
																								modelValue: unref(formSale).entity_id,
																								"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																								density: "compact",
																								variant: "outlined",
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
																							default: withCtx(() => [createVNode(VDatePicker, {
																								modelValue: unref(formSale).date,
																								"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}), createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formSale).total,
																								"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																								label: "Total",
																								placeholder: "Сумма продажи",
																								density: "comfortable",
																								variant: "solo"
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
																				default: withCtx(() => [createVNode(VBtn, {
																					text: "store",
																					onClick: storeSale,
																					variant: "elevated",
																					density: "comfortable",
																					color: "purple"
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
														}), createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, { cols: "5" }, {
																default: withCtx(() => [
																	createVNode(VDataTable, {
																		items: sales.value,
																		"items-per-page": "250",
																		headers: headerSales,
																		"fixed-header": "",
																		height: "1000px",
																		density: "compact",
																		hover: ""
																	}, {
																		"item.good": withCtx(({ item }) => [createVNode(VBtn, {
																			text: "+",
																			onClick: ($event) => openAttachDialog(item),
																			variant: "elevated",
																			color: "grey",
																			density: "compact",
																			size: "20"
																		}, null, 8, ["onClick"])]),
																		"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-[8px] font-sans" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
																		"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs font-sans" }, toDisplayString(unref(date).format(item.date, "fullDateWithWeekday")), 1)]),
																		"item.entity_id": withCtx(({ item }) => [createVNode("span", { class: "text-sm font-RobotoRegular" }, toDisplayString(item.entity.name), 1)]),
																		"item.total": withCtx(({ item }) => [createVNode("span", {
																			onClick: ($event) => showSale(item.id),
																			class: "cursor-pointer"
																		}, toDisplayString(item.total), 9, ["onClick"])]),
																		_: 1
																	}, 8, ["items"]),
																	createVNode(VDialog, {
																		modelValue: unref(showFormAttachGood),
																		"onUpdate:modelValue": ($event) => isRef(showFormAttachGood) ? showFormAttachGood.value = $event : showFormAttachGood = $event,
																		transition: "dialog-bottom-transition",
																		width: "1000"
																	}, {
																		default: withCtx(({ isActive }) => [createVNode(VCard, { theme: "dark" }, {
																			default: withCtx(() => [
																				createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Form Attach Good")]),
																					_: 1
																				}),
																				createVNode(VCardText, { class: "text-red-700" }, {
																					default: withCtx(() => [createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																							default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																								default: withCtx(() => [createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VAutocomplete, {
																											items: goods.value,
																											"item-value": "id",
																											"item-title": "name",
																											modelValue: unref(formAttachGood).good_id,
																											"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																											label: "Good",
																											variant: "outlined",
																											onChange: ($event) => showGood(unref(formAttachGood).good_id)
																										}, null, 8, [
																											"items",
																											"modelValue",
																											"onUpdate:modelValue",
																											"onChange"
																										])]),
																										_: 1
																									})]),
																									_: 1
																								}), createVNode(VRow, null, {
																									default: withCtx(() => [
																										createVNode(VCol, { cols: "3" }, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formAttachGood).quantity,
																												"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																												label: "Количество",
																												variant: "outlined",
																												density: "compact",
																												theme: "dark"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										}),
																										createVNode(VCol, { cols: "3" }, {
																											default: withCtx(() => [createVNode(VSelect, {
																												items: measures.value,
																												"item-value": "id",
																												"item-title": "name",
																												modelValue: unref(formAttachGood).measure_id,
																												"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																												label: "Measures",
																												variant: "outlined",
																												density: "compact"
																											}, null, 8, [
																												"items",
																												"modelValue",
																												"onUpdate:modelValue"
																											])]),
																											_: 1
																										}),
																										createVNode(VCol, { cols: "6" }, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formAttachGood).price,
																												"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																												label: "Price",
																												variant: "outlined",
																												density: "comfortable"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										})
																									]),
																									_: 1
																								})]),
																								_: 1
																							}, 8, ["onSubmit"])]),
																							_: 1
																						}), createVNode(VCol, { cols: "5" }, {
																							default: withCtx(() => [createVNode(VSheet, null, {
																								default: withCtx(() => [createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																										_: 1
																									}), createVNode(VCol, null, {
																										default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																										_: 1
																									})]),
																									_: 1
																								}), createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
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
																				createVNode(VCardActions, null, {
																					default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
																						text: "attach",
																						onClick: attachGood,
																						variant: "text",
																						density: "compact",
																						color: "teal-lighten-2"
																					})]),
																					_: 1
																				})
																			]),
																			_: 1
																		})]),
																		_: 1
																	}, 8, ["modelValue", "onUpdate:modelValue"]),
																	createVNode(VSnackbar, {
																		modelValue: snackbar.value.show,
																		"onUpdate:modelValue": ($event) => snackbar.value.show = $event,
																		timeout: 2e3,
																		color: "green"
																	}, {
																		default: withCtx(() => [createTextVNode(toDisplayString(snackbar.value.text), 1)]),
																		_: 1
																	}, 8, ["modelValue", "onUpdate:modelValue"])
																]),
																_: 1
															}), createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(sale.value?.goods ?? [], (good) => {
																		return openBlock(), createBlock(VListItem, null, {
																			default: withCtx(() => [createVNode(VRow, null, {
																				default: withCtx(() => [
																					createVNode(VCol, { cols: "8" }, {
																						default: withCtx(() => [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)]),
																						_: 2
																					}, 1024),
																					createVNode(VCol, { cols: "1" }, {
																						default: withCtx(() => [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)]),
																						_: 2
																					}, 1024),
																					createVNode(VCol, { cols: "1" }, {
																						default: withCtx(() => [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)]),
																						_: 2
																					}, 1024),
																					createVNode(VCol, null, {
																						default: withCtx(() => [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)]),
																						_: 2
																					}, 1024)
																				]),
																				_: 2
																			}, 1024)]),
																			_: 2
																		}, 1024);
																	}), 256))]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												}), createVNode(VTabsWindowItem, { value: "entities" }, {
													default: withCtx(() => [createVNode(VContainer, null, {
														default: withCtx(() => [createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, { lg: "3" }, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: searchEntities.value,
																	"onUpdate:modelValue": ($event) => searchEntities.value = $event,
																	label: "Search for entities",
																	variant: "solo",
																	density: "compact",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}), createVNode(VCol, { lg: "2" }, {
																default: withCtx(() => [createVNode(VBtn, {
																	text: "+",
																	onClick: ($event) => showFormEntity.value = !showFormEntity.value,
																	variant: "elevated",
																	color: "purple-darken-4"
																}, null, 8, ["onClick"]), createVNode(VDialog, {
																	modelValue: showFormEntity.value,
																	"onUpdate:modelValue": ($event) => showFormEntity.value = $event,
																	width: "990",
																	transition: "dialog-top-transition"
																}, {
																	default: withCtx(() => [createVNode(VCard, null, {
																		default: withCtx(() => [
																			createVNode(VCardTitle, null, {
																				default: withCtx(() => [createTextVNode("Form Entity")]),
																				_: 1
																			}),
																			createVNode(VCardText, null, {
																				default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																					default: withCtx(() => [
																						createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(formEntity).name,
																								"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																								label: "Name",
																								variant: "outlined"
																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																							_: 1
																						}),
																						createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VSelect, {
																									items: entityClassifications.value,
																									"item-value": "id",
																									"item-title": "name",
																									modelValue: unref(formEntity).entity_classification_id,
																									"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																									variant: "outlined",
																									label: "Вид"
																								}, null, 8, [
																									"items",
																									"modelValue",
																									"onUpdate:modelValue"
																								])]),
																								_: 1
																							}), createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VAutocomplete, {
																									items: telephones.value,
																									"item-value": "id",
																									"item-title": "number",
																									modelValue: unref(formEntity).telephones,
																									"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																									label: "Telephones",
																									placeholder: "number without +7",
																									variant: "outlined",
																									density: "comfortable",
																									color: "purple-darken-4",
																									multiple: "",
																									chips: ""
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
																								default: withCtx(() => [createVNode(VAutocomplete, {
																									items: cities.value,
																									"item-value": "id",
																									"item-title": "name",
																									modelValue: unref(formEntity).cities,
																									"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																									label: "Cities",
																									placeholder: "Населенный пункт",
																									variant: "solo",
																									density: "comfortable",
																									color: "grey",
																									multiple: ""
																								}, null, 8, [
																									"items",
																									"modelValue",
																									"onUpdate:modelValue"
																								])]),
																								_: 1
																							}), createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VAutocomplete, {
																									items: buildings.value,
																									"item-value": "id",
																									"item-title": "address",
																									modelValue: unref(formEntity).buildings,
																									"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																									multiple: "",
																									label: "Buildings",
																									placeholder: "Адрес здания",
																									variant: "outlined",
																									density: "comfortable",
																									color: "teal"
																								}, null, 8, [
																									"items",
																									"modelValue",
																									"onUpdate:modelValue"
																								])]),
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
																					onClick: storeEntity,
																					text: "сохранить",
																					variant: "flat"
																				})]),
																				_: 1
																			})
																		]),
																		_: 1
																	})]),
																	_: 1
																}, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})]),
															_: 1
														}), createVNode(VRow, null, {
															default: withCtx(() => [
																createVNode(VCol, { cols: "1" }),
																createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VDataTable, {
																		items: filteredEntities.value,
																		"items-per-page": "125",
																		headers: headerEntities,
																		"fixed-header": "",
																		density: "compact",
																		hover: "",
																		height: "1000px"
																	}, {
																		"item.telephones": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.telephones, (telephone) => {
																			return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																		}), 256))]),
																		"item.units": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.units, (unit) => {
																			return openBlock(), createBlock("div", { class: "font-RobotoRegular text-sm" }, toDisplayString(unit.name), 1);
																		}), 256))]),
																		"item.cities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.cities, (city) => {
																			return openBlock(), createBlock("div", { class: "text-sm" }, toDisplayString(city.name), 1);
																		}), 256))]),
																		"item.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.buildings, (building) => {
																			return openBlock(), createBlock("div", { class: "font-Typingrad text-[10px]" }, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
																		}), 256))]),
																		_: 1
																	}, 8, ["items"])]),
																	_: 1
																}),
																createVNode(VCol, { cols: "1" })
															]),
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
									else return [createVNode(VCard, null, {
										default: withCtx(() => [createVNode(VTabs, {
											modelValue: tab.value,
											"onUpdate:modelValue": ($event) => tab.value = $event,
											"align-tabs": "center",
											color: "deep-purple-accent-4"
										}, {
											default: withCtx(() => [createVNode(VTab, { value: "sales" }, {
												default: withCtx(() => [createTextVNode("Продажи")]),
												_: 1
											}), createVNode(VTab, { value: "entities" }, {
												default: withCtx(() => [createTextVNode("Entities")]),
												_: 1
											})]),
											_: 1
										}, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VTabsWindow, {
											modelValue: tab.value,
											"onUpdate:modelValue": ($event) => tab.value = $event
										}, {
											default: withCtx(() => [createVNode(VTabsWindowItem, { value: "sales" }, {
												default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
													default: withCtx(() => [createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, { cols: "1" }, {
															default: withCtx(() => [createVNode(VDialog, { width: "1000" }, {
																activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, { text: "Новая продажа" }), null, 16)]),
																default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																	default: withCtx(() => [
																		createVNode(VCardTitle, null, {
																			default: withCtx(() => [createTextVNode("Form Sale")]),
																			_: 1
																		}),
																		createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																				default: withCtx(() => [createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VAutocomplete, {
																							items: entities.value,
																							"item-value": "id",
																							"item-title": "name",
																							modelValue: unref(formSale).entity_id,
																							"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																							density: "compact",
																							variant: "outlined",
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
																						default: withCtx(() => [createVNode(VDatePicker, {
																							modelValue: unref(formSale).date,
																							"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}), createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formSale).total,
																							"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																							label: "Total",
																							placeholder: "Сумма продажи",
																							density: "comfortable",
																							variant: "solo"
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
																			default: withCtx(() => [createVNode(VBtn, {
																				text: "store",
																				onClick: storeSale,
																				variant: "elevated",
																				density: "comfortable",
																				color: "purple"
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
													}), createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, { cols: "5" }, {
															default: withCtx(() => [
																createVNode(VDataTable, {
																	items: sales.value,
																	"items-per-page": "250",
																	headers: headerSales,
																	"fixed-header": "",
																	height: "1000px",
																	density: "compact",
																	hover: ""
																}, {
																	"item.good": withCtx(({ item }) => [createVNode(VBtn, {
																		text: "+",
																		onClick: ($event) => openAttachDialog(item),
																		variant: "elevated",
																		color: "grey",
																		density: "compact",
																		size: "20"
																	}, null, 8, ["onClick"])]),
																	"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-[8px] font-sans" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
																	"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs font-sans" }, toDisplayString(unref(date).format(item.date, "fullDateWithWeekday")), 1)]),
																	"item.entity_id": withCtx(({ item }) => [createVNode("span", { class: "text-sm font-RobotoRegular" }, toDisplayString(item.entity.name), 1)]),
																	"item.total": withCtx(({ item }) => [createVNode("span", {
																		onClick: ($event) => showSale(item.id),
																		class: "cursor-pointer"
																	}, toDisplayString(item.total), 9, ["onClick"])]),
																	_: 1
																}, 8, ["items"]),
																createVNode(VDialog, {
																	modelValue: unref(showFormAttachGood),
																	"onUpdate:modelValue": ($event) => isRef(showFormAttachGood) ? showFormAttachGood.value = $event : showFormAttachGood = $event,
																	transition: "dialog-bottom-transition",
																	width: "1000"
																}, {
																	default: withCtx(({ isActive }) => [createVNode(VCard, { theme: "dark" }, {
																		default: withCtx(() => [
																			createVNode(VCardTitle, null, {
																				default: withCtx(() => [createTextVNode("Form Attach Good")]),
																				_: 1
																			}),
																			createVNode(VCardText, { class: "text-red-700" }, {
																				default: withCtx(() => [createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																						default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																							default: withCtx(() => [createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VAutocomplete, {
																										items: goods.value,
																										"item-value": "id",
																										"item-title": "name",
																										modelValue: unref(formAttachGood).good_id,
																										"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																										label: "Good",
																										variant: "outlined",
																										onChange: ($event) => showGood(unref(formAttachGood).good_id)
																									}, null, 8, [
																										"items",
																										"modelValue",
																										"onUpdate:modelValue",
																										"onChange"
																									])]),
																									_: 1
																								})]),
																								_: 1
																							}), createVNode(VRow, null, {
																								default: withCtx(() => [
																									createVNode(VCol, { cols: "3" }, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formAttachGood).quantity,
																											"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																											label: "Количество",
																											variant: "outlined",
																											density: "compact",
																											theme: "dark"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									}),
																									createVNode(VCol, { cols: "3" }, {
																										default: withCtx(() => [createVNode(VSelect, {
																											items: measures.value,
																											"item-value": "id",
																											"item-title": "name",
																											modelValue: unref(formAttachGood).measure_id,
																											"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																											label: "Measures",
																											variant: "outlined",
																											density: "compact"
																										}, null, 8, [
																											"items",
																											"modelValue",
																											"onUpdate:modelValue"
																										])]),
																										_: 1
																									}),
																									createVNode(VCol, { cols: "6" }, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formAttachGood).price,
																											"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																											label: "Price",
																											variant: "outlined",
																											density: "comfortable"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									})
																								]),
																								_: 1
																							})]),
																							_: 1
																						}, 8, ["onSubmit"])]),
																						_: 1
																					}), createVNode(VCol, { cols: "5" }, {
																						default: withCtx(() => [createVNode(VSheet, null, {
																							default: withCtx(() => [createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																									_: 1
																								}), createVNode(VCol, null, {
																									default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																									_: 1
																								})]),
																								_: 1
																							}), createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
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
																			createVNode(VCardActions, null, {
																				default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
																					text: "attach",
																					onClick: attachGood,
																					variant: "text",
																					density: "compact",
																					color: "teal-lighten-2"
																				})]),
																				_: 1
																			})
																		]),
																		_: 1
																	})]),
																	_: 1
																}, 8, ["modelValue", "onUpdate:modelValue"]),
																createVNode(VSnackbar, {
																	modelValue: snackbar.value.show,
																	"onUpdate:modelValue": ($event) => snackbar.value.show = $event,
																	timeout: 2e3,
																	color: "green"
																}, {
																	default: withCtx(() => [createTextVNode(toDisplayString(snackbar.value.text), 1)]),
																	_: 1
																}, 8, ["modelValue", "onUpdate:modelValue"])
															]),
															_: 1
														}), createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VList, { density: "compact" }, {
																default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(sale.value?.goods ?? [], (good) => {
																	return openBlock(), createBlock(VListItem, null, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [
																				createVNode(VCol, { cols: "8" }, {
																					default: withCtx(() => [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)]),
																					_: 2
																				}, 1024),
																				createVNode(VCol, { cols: "1" }, {
																					default: withCtx(() => [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)]),
																					_: 2
																				}, 1024),
																				createVNode(VCol, { cols: "1" }, {
																					default: withCtx(() => [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)]),
																					_: 2
																				}, 1024),
																				createVNode(VCol, null, {
																					default: withCtx(() => [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)]),
																					_: 2
																				}, 1024)
																			]),
																			_: 2
																		}, 1024)]),
																		_: 2
																	}, 1024);
																}), 256))]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											}), createVNode(VTabsWindowItem, { value: "entities" }, {
												default: withCtx(() => [createVNode(VContainer, null, {
													default: withCtx(() => [createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, { lg: "3" }, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: searchEntities.value,
																"onUpdate:modelValue": ($event) => searchEntities.value = $event,
																label: "Search for entities",
																variant: "solo",
																density: "compact",
																"hide-details": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}), createVNode(VCol, { lg: "2" }, {
															default: withCtx(() => [createVNode(VBtn, {
																text: "+",
																onClick: ($event) => showFormEntity.value = !showFormEntity.value,
																variant: "elevated",
																color: "purple-darken-4"
															}, null, 8, ["onClick"]), createVNode(VDialog, {
																modelValue: showFormEntity.value,
																"onUpdate:modelValue": ($event) => showFormEntity.value = $event,
																width: "990",
																transition: "dialog-top-transition"
															}, {
																default: withCtx(() => [createVNode(VCard, null, {
																	default: withCtx(() => [
																		createVNode(VCardTitle, null, {
																			default: withCtx(() => [createTextVNode("Form Entity")]),
																			_: 1
																		}),
																		createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																				default: withCtx(() => [
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formEntity).name,
																							"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																							label: "Name",
																							variant: "outlined"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VSelect, {
																								items: entityClassifications.value,
																								"item-value": "id",
																								"item-title": "name",
																								modelValue: unref(formEntity).entity_classification_id,
																								"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																								variant: "outlined",
																								label: "Вид"
																							}, null, 8, [
																								"items",
																								"modelValue",
																								"onUpdate:modelValue"
																							])]),
																							_: 1
																						}), createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VAutocomplete, {
																								items: telephones.value,
																								"item-value": "id",
																								"item-title": "number",
																								modelValue: unref(formEntity).telephones,
																								"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																								label: "Telephones",
																								placeholder: "number without +7",
																								variant: "outlined",
																								density: "comfortable",
																								color: "purple-darken-4",
																								multiple: "",
																								chips: ""
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
																							default: withCtx(() => [createVNode(VAutocomplete, {
																								items: cities.value,
																								"item-value": "id",
																								"item-title": "name",
																								modelValue: unref(formEntity).cities,
																								"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																								label: "Cities",
																								placeholder: "Населенный пункт",
																								variant: "solo",
																								density: "comfortable",
																								color: "grey",
																								multiple: ""
																							}, null, 8, [
																								"items",
																								"modelValue",
																								"onUpdate:modelValue"
																							])]),
																							_: 1
																						}), createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VAutocomplete, {
																								items: buildings.value,
																								"item-value": "id",
																								"item-title": "address",
																								modelValue: unref(formEntity).buildings,
																								"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																								multiple: "",
																								label: "Buildings",
																								placeholder: "Адрес здания",
																								variant: "outlined",
																								density: "comfortable",
																								color: "teal"
																							}, null, 8, [
																								"items",
																								"modelValue",
																								"onUpdate:modelValue"
																							])]),
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
																				onClick: storeEntity,
																				text: "сохранить",
																				variant: "flat"
																			})]),
																			_: 1
																		})
																	]),
																	_: 1
																})]),
																_: 1
															}, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})]),
														_: 1
													}), createVNode(VRow, null, {
														default: withCtx(() => [
															createVNode(VCol, { cols: "1" }),
															createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VDataTable, {
																	items: filteredEntities.value,
																	"items-per-page": "125",
																	headers: headerEntities,
																	"fixed-header": "",
																	density: "compact",
																	hover: "",
																	height: "1000px"
																}, {
																	"item.telephones": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.telephones, (telephone) => {
																		return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																	}), 256))]),
																	"item.units": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.units, (unit) => {
																		return openBlock(), createBlock("div", { class: "font-RobotoRegular text-sm" }, toDisplayString(unit.name), 1);
																	}), 256))]),
																	"item.cities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.cities, (city) => {
																		return openBlock(), createBlock("div", { class: "text-sm" }, toDisplayString(city.name), 1);
																	}), 256))]),
																	"item.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.buildings, (building) => {
																		return openBlock(), createBlock("div", { class: "font-Typingrad text-[10px]" }, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
																	}), 256))]),
																	_: 1
																}, 8, ["items"])]),
																_: 1
															}),
															createVNode(VCol, { cols: "1" })
														]),
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
							else return [createVNode(VCol, null, {
								default: withCtx(() => [createVNode(VCard, null, {
									default: withCtx(() => [createVNode(VTabs, {
										modelValue: tab.value,
										"onUpdate:modelValue": ($event) => tab.value = $event,
										"align-tabs": "center",
										color: "deep-purple-accent-4"
									}, {
										default: withCtx(() => [createVNode(VTab, { value: "sales" }, {
											default: withCtx(() => [createTextVNode("Продажи")]),
											_: 1
										}), createVNode(VTab, { value: "entities" }, {
											default: withCtx(() => [createTextVNode("Entities")]),
											_: 1
										})]),
										_: 1
									}, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VTabsWindow, {
										modelValue: tab.value,
										"onUpdate:modelValue": ($event) => tab.value = $event
									}, {
										default: withCtx(() => [createVNode(VTabsWindowItem, { value: "sales" }, {
											default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
												default: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, { cols: "1" }, {
														default: withCtx(() => [createVNode(VDialog, { width: "1000" }, {
															activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, { text: "Новая продажа" }), null, 16)]),
															default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																default: withCtx(() => [
																	createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Form Sale")]),
																		_: 1
																	}),
																	createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																			default: withCtx(() => [createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VAutocomplete, {
																						items: entities.value,
																						"item-value": "id",
																						"item-title": "name",
																						modelValue: unref(formSale).entity_id,
																						"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																						density: "compact",
																						variant: "outlined",
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
																					default: withCtx(() => [createVNode(VDatePicker, {
																						modelValue: unref(formSale).date,
																						"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}), createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formSale).total,
																						"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																						label: "Total",
																						placeholder: "Сумма продажи",
																						density: "comfortable",
																						variant: "solo"
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
																		default: withCtx(() => [createVNode(VBtn, {
																			text: "store",
																			onClick: storeSale,
																			variant: "elevated",
																			density: "comfortable",
																			color: "purple"
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
												}), createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, { cols: "5" }, {
														default: withCtx(() => [
															createVNode(VDataTable, {
																items: sales.value,
																"items-per-page": "250",
																headers: headerSales,
																"fixed-header": "",
																height: "1000px",
																density: "compact",
																hover: ""
															}, {
																"item.good": withCtx(({ item }) => [createVNode(VBtn, {
																	text: "+",
																	onClick: ($event) => openAttachDialog(item),
																	variant: "elevated",
																	color: "grey",
																	density: "compact",
																	size: "20"
																}, null, 8, ["onClick"])]),
																"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-[8px] font-sans" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
																"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs font-sans" }, toDisplayString(unref(date).format(item.date, "fullDateWithWeekday")), 1)]),
																"item.entity_id": withCtx(({ item }) => [createVNode("span", { class: "text-sm font-RobotoRegular" }, toDisplayString(item.entity.name), 1)]),
																"item.total": withCtx(({ item }) => [createVNode("span", {
																	onClick: ($event) => showSale(item.id),
																	class: "cursor-pointer"
																}, toDisplayString(item.total), 9, ["onClick"])]),
																_: 1
															}, 8, ["items"]),
															createVNode(VDialog, {
																modelValue: unref(showFormAttachGood),
																"onUpdate:modelValue": ($event) => isRef(showFormAttachGood) ? showFormAttachGood.value = $event : showFormAttachGood = $event,
																transition: "dialog-bottom-transition",
																width: "1000"
															}, {
																default: withCtx(({ isActive }) => [createVNode(VCard, { theme: "dark" }, {
																	default: withCtx(() => [
																		createVNode(VCardTitle, null, {
																			default: withCtx(() => [createTextVNode("Form Attach Good")]),
																			_: 1
																		}),
																		createVNode(VCardText, { class: "text-red-700" }, {
																			default: withCtx(() => [createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																					default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																						default: withCtx(() => [createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VAutocomplete, {
																									items: goods.value,
																									"item-value": "id",
																									"item-title": "name",
																									modelValue: unref(formAttachGood).good_id,
																									"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																									label: "Good",
																									variant: "outlined",
																									onChange: ($event) => showGood(unref(formAttachGood).good_id)
																								}, null, 8, [
																									"items",
																									"modelValue",
																									"onUpdate:modelValue",
																									"onChange"
																								])]),
																								_: 1
																							})]),
																							_: 1
																						}), createVNode(VRow, null, {
																							default: withCtx(() => [
																								createVNode(VCol, { cols: "3" }, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formAttachGood).quantity,
																										"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																										label: "Количество",
																										variant: "outlined",
																										density: "compact",
																										theme: "dark"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}),
																								createVNode(VCol, { cols: "3" }, {
																									default: withCtx(() => [createVNode(VSelect, {
																										items: measures.value,
																										"item-value": "id",
																										"item-title": "name",
																										modelValue: unref(formAttachGood).measure_id,
																										"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																										label: "Measures",
																										variant: "outlined",
																										density: "compact"
																									}, null, 8, [
																										"items",
																										"modelValue",
																										"onUpdate:modelValue"
																									])]),
																									_: 1
																								}),
																								createVNode(VCol, { cols: "6" }, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formAttachGood).price,
																										"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																										label: "Price",
																										variant: "outlined",
																										density: "comfortable"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								})
																							]),
																							_: 1
																						})]),
																						_: 1
																					}, 8, ["onSubmit"])]),
																					_: 1
																				}), createVNode(VCol, { cols: "5" }, {
																					default: withCtx(() => [createVNode(VSheet, null, {
																						default: withCtx(() => [createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, null, {
																								default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																								_: 1
																							}), createVNode(VCol, null, {
																								default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																								_: 1
																							})]),
																							_: 1
																						}), createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, null, {
																								default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
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
																		createVNode(VCardActions, null, {
																			default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
																				text: "attach",
																				onClick: attachGood,
																				variant: "text",
																				density: "compact",
																				color: "teal-lighten-2"
																			})]),
																			_: 1
																		})
																	]),
																	_: 1
																})]),
																_: 1
															}, 8, ["modelValue", "onUpdate:modelValue"]),
															createVNode(VSnackbar, {
																modelValue: snackbar.value.show,
																"onUpdate:modelValue": ($event) => snackbar.value.show = $event,
																timeout: 2e3,
																color: "green"
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(snackbar.value.text), 1)]),
																_: 1
															}, 8, ["modelValue", "onUpdate:modelValue"])
														]),
														_: 1
													}), createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VList, { density: "compact" }, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(sale.value?.goods ?? [], (good) => {
																return openBlock(), createBlock(VListItem, null, {
																	default: withCtx(() => [createVNode(VRow, null, {
																		default: withCtx(() => [
																			createVNode(VCol, { cols: "8" }, {
																				default: withCtx(() => [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)]),
																				_: 2
																			}, 1024),
																			createVNode(VCol, { cols: "1" }, {
																				default: withCtx(() => [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)]),
																				_: 2
																			}, 1024),
																			createVNode(VCol, { cols: "1" }, {
																				default: withCtx(() => [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)]),
																				_: 2
																			}, 1024),
																			createVNode(VCol, null, {
																				default: withCtx(() => [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)]),
																				_: 2
																			}, 1024)
																		]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1024);
															}), 256))]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										}), createVNode(VTabsWindowItem, { value: "entities" }, {
											default: withCtx(() => [createVNode(VContainer, null, {
												default: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, { lg: "3" }, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: searchEntities.value,
															"onUpdate:modelValue": ($event) => searchEntities.value = $event,
															label: "Search for entities",
															variant: "solo",
															density: "compact",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}), createVNode(VCol, { lg: "2" }, {
														default: withCtx(() => [createVNode(VBtn, {
															text: "+",
															onClick: ($event) => showFormEntity.value = !showFormEntity.value,
															variant: "elevated",
															color: "purple-darken-4"
														}, null, 8, ["onClick"]), createVNode(VDialog, {
															modelValue: showFormEntity.value,
															"onUpdate:modelValue": ($event) => showFormEntity.value = $event,
															width: "990",
															transition: "dialog-top-transition"
														}, {
															default: withCtx(() => [createVNode(VCard, null, {
																default: withCtx(() => [
																	createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Form Entity")]),
																		_: 1
																	}),
																	createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																			default: withCtx(() => [
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formEntity).name,
																						"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																						label: "Name",
																						variant: "outlined"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VSelect, {
																							items: entityClassifications.value,
																							"item-value": "id",
																							"item-title": "name",
																							modelValue: unref(formEntity).entity_classification_id,
																							"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																							variant: "outlined",
																							label: "Вид"
																						}, null, 8, [
																							"items",
																							"modelValue",
																							"onUpdate:modelValue"
																						])]),
																						_: 1
																					}), createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VAutocomplete, {
																							items: telephones.value,
																							"item-value": "id",
																							"item-title": "number",
																							modelValue: unref(formEntity).telephones,
																							"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																							label: "Telephones",
																							placeholder: "number without +7",
																							variant: "outlined",
																							density: "comfortable",
																							color: "purple-darken-4",
																							multiple: "",
																							chips: ""
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
																						default: withCtx(() => [createVNode(VAutocomplete, {
																							items: cities.value,
																							"item-value": "id",
																							"item-title": "name",
																							modelValue: unref(formEntity).cities,
																							"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																							label: "Cities",
																							placeholder: "Населенный пункт",
																							variant: "solo",
																							density: "comfortable",
																							color: "grey",
																							multiple: ""
																						}, null, 8, [
																							"items",
																							"modelValue",
																							"onUpdate:modelValue"
																						])]),
																						_: 1
																					}), createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VAutocomplete, {
																							items: buildings.value,
																							"item-value": "id",
																							"item-title": "address",
																							modelValue: unref(formEntity).buildings,
																							"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																							multiple: "",
																							label: "Buildings",
																							placeholder: "Адрес здания",
																							variant: "outlined",
																							density: "comfortable",
																							color: "teal"
																						}, null, 8, [
																							"items",
																							"modelValue",
																							"onUpdate:modelValue"
																						])]),
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
																			onClick: storeEntity,
																			text: "сохранить",
																			variant: "flat"
																		})]),
																		_: 1
																	})
																]),
																_: 1
															})]),
															_: 1
														}, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})]),
													_: 1
												}), createVNode(VRow, null, {
													default: withCtx(() => [
														createVNode(VCol, { cols: "1" }),
														createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VDataTable, {
																items: filteredEntities.value,
																"items-per-page": "125",
																headers: headerEntities,
																"fixed-header": "",
																density: "compact",
																hover: "",
																height: "1000px"
															}, {
																"item.telephones": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.telephones, (telephone) => {
																	return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																}), 256))]),
																"item.units": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.units, (unit) => {
																	return openBlock(), createBlock("div", { class: "font-RobotoRegular text-sm" }, toDisplayString(unit.name), 1);
																}), 256))]),
																"item.cities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.cities, (city) => {
																	return openBlock(), createBlock("div", { class: "text-sm" }, toDisplayString(city.name), 1);
																}), 256))]),
																"item.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.buildings, (building) => {
																	return openBlock(), createBlock("div", { class: "font-Typingrad text-[10px]" }, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
																}), 256))]),
																_: 1
															}, 8, ["items"])]),
															_: 1
														}),
														createVNode(VCol, { cols: "1" })
													]),
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
					else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, null, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [createVNode(VTabs, {
									modelValue: tab.value,
									"onUpdate:modelValue": ($event) => tab.value = $event,
									"align-tabs": "center",
									color: "deep-purple-accent-4"
								}, {
									default: withCtx(() => [createVNode(VTab, { value: "sales" }, {
										default: withCtx(() => [createTextVNode("Продажи")]),
										_: 1
									}), createVNode(VTab, { value: "entities" }, {
										default: withCtx(() => [createTextVNode("Entities")]),
										_: 1
									})]),
									_: 1
								}, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VTabsWindow, {
									modelValue: tab.value,
									"onUpdate:modelValue": ($event) => tab.value = $event
								}, {
									default: withCtx(() => [createVNode(VTabsWindowItem, { value: "sales" }, {
										default: withCtx(() => [createVNode(VContainer, { fluid: "" }, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, { cols: "1" }, {
													default: withCtx(() => [createVNode(VDialog, { width: "1000" }, {
														activator: withCtx(({ props: activatorProps }) => [createVNode(VBtn, mergeProps(activatorProps, { text: "Новая продажа" }), null, 16)]),
														default: withCtx(({ isActive }) => [createVNode(VCard, null, {
															default: withCtx(() => [
																createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Form Sale")]),
																	_: 1
																}),
																createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VAutocomplete, {
																					items: entities.value,
																					"item-value": "id",
																					"item-title": "name",
																					modelValue: unref(formSale).entity_id,
																					"onUpdate:modelValue": ($event) => unref(formSale).entity_id = $event,
																					density: "compact",
																					variant: "outlined",
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
																				default: withCtx(() => [createVNode(VDatePicker, {
																					modelValue: unref(formSale).date,
																					"onUpdate:modelValue": ($event) => unref(formSale).date = $event
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}), createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formSale).total,
																					"onUpdate:modelValue": ($event) => unref(formSale).total = $event,
																					label: "Total",
																					placeholder: "Сумма продажи",
																					density: "comfortable",
																					variant: "solo"
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
																	default: withCtx(() => [createVNode(VBtn, {
																		text: "store",
																		onClick: storeSale,
																		variant: "elevated",
																		density: "comfortable",
																		color: "purple"
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
											}), createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, { cols: "5" }, {
													default: withCtx(() => [
														createVNode(VDataTable, {
															items: sales.value,
															"items-per-page": "250",
															headers: headerSales,
															"fixed-header": "",
															height: "1000px",
															density: "compact",
															hover: ""
														}, {
															"item.good": withCtx(({ item }) => [createVNode(VBtn, {
																text: "+",
																onClick: ($event) => openAttachDialog(item),
																variant: "elevated",
																color: "grey",
																density: "compact",
																size: "20"
															}, null, 8, ["onClick"])]),
															"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-[8px] font-sans" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
															"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs font-sans" }, toDisplayString(unref(date).format(item.date, "fullDateWithWeekday")), 1)]),
															"item.entity_id": withCtx(({ item }) => [createVNode("span", { class: "text-sm font-RobotoRegular" }, toDisplayString(item.entity.name), 1)]),
															"item.total": withCtx(({ item }) => [createVNode("span", {
																onClick: ($event) => showSale(item.id),
																class: "cursor-pointer"
															}, toDisplayString(item.total), 9, ["onClick"])]),
															_: 1
														}, 8, ["items"]),
														createVNode(VDialog, {
															modelValue: unref(showFormAttachGood),
															"onUpdate:modelValue": ($event) => isRef(showFormAttachGood) ? showFormAttachGood.value = $event : showFormAttachGood = $event,
															transition: "dialog-bottom-transition",
															width: "1000"
														}, {
															default: withCtx(({ isActive }) => [createVNode(VCard, { theme: "dark" }, {
																default: withCtx(() => [
																	createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Form Attach Good")]),
																		_: 1
																	}),
																	createVNode(VCardText, { class: "text-red-700" }, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																				default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																					default: withCtx(() => [createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VAutocomplete, {
																								items: goods.value,
																								"item-value": "id",
																								"item-title": "name",
																								modelValue: unref(formAttachGood).good_id,
																								"onUpdate:modelValue": ($event) => unref(formAttachGood).good_id = $event,
																								label: "Good",
																								variant: "outlined",
																								onChange: ($event) => showGood(unref(formAttachGood).good_id)
																							}, null, 8, [
																								"items",
																								"modelValue",
																								"onUpdate:modelValue",
																								"onChange"
																							])]),
																							_: 1
																						})]),
																						_: 1
																					}), createVNode(VRow, null, {
																						default: withCtx(() => [
																							createVNode(VCol, { cols: "3" }, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formAttachGood).quantity,
																									"onUpdate:modelValue": ($event) => unref(formAttachGood).quantity = $event,
																									label: "Количество",
																									variant: "outlined",
																									density: "compact",
																									theme: "dark"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							}),
																							createVNode(VCol, { cols: "3" }, {
																								default: withCtx(() => [createVNode(VSelect, {
																									items: measures.value,
																									"item-value": "id",
																									"item-title": "name",
																									modelValue: unref(formAttachGood).measure_id,
																									"onUpdate:modelValue": ($event) => unref(formAttachGood).measure_id = $event,
																									label: "Measures",
																									variant: "outlined",
																									density: "compact"
																								}, null, 8, [
																									"items",
																									"modelValue",
																									"onUpdate:modelValue"
																								])]),
																								_: 1
																							}),
																							createVNode(VCol, { cols: "6" }, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formAttachGood).price,
																									"onUpdate:modelValue": ($event) => unref(formAttachGood).price = $event,
																									label: "Price",
																									variant: "outlined",
																									density: "comfortable"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							})
																						]),
																						_: 1
																					})]),
																					_: 1
																				}, 8, ["onSubmit"])]),
																				_: 1
																			}), createVNode(VCol, { cols: "5" }, {
																				default: withCtx(() => [createVNode(VSheet, null, {
																					default: withCtx(() => [createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode("div", null, [createVNode("label", null, "Total"), createTextVNode(toDisplayString(sale.value.total), 1)])]),
																							_: 1
																						}), createVNode(VCol, null, {
																							default: withCtx(() => [createVNode("label", null, "Position Sum"), createVNode("span", null, toDisplayString(unref(formAttachGood).quantity * unref(formAttachGood).price), 1)]),
																							_: 1
																						})]),
																						_: 1
																					}), createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode("div", { class: "text-[9px]" }, toDisplayString(good), 1)]),
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
																	createVNode(VCardActions, null, {
																		default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
																			text: "attach",
																			onClick: attachGood,
																			variant: "text",
																			density: "compact",
																			color: "teal-lighten-2"
																		})]),
																		_: 1
																	})
																]),
																_: 1
															})]),
															_: 1
														}, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VSnackbar, {
															modelValue: snackbar.value.show,
															"onUpdate:modelValue": ($event) => snackbar.value.show = $event,
															timeout: 2e3,
															color: "green"
														}, {
															default: withCtx(() => [createTextVNode(toDisplayString(snackbar.value.text), 1)]),
															_: 1
														}, 8, ["modelValue", "onUpdate:modelValue"])
													]),
													_: 1
												}), createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VList, { density: "compact" }, {
														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(sale.value?.goods ?? [], (good) => {
															return openBlock(), createBlock(VListItem, null, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [
																		createVNode(VCol, { cols: "8" }, {
																			default: withCtx(() => [createVNode("span", { class: "cursor-pointer text-sm font-sans" }, toDisplayString(good.name), 1)]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, { cols: "1" }, {
																			default: withCtx(() => [createVNode("span", { class: "text-[11px]" }, toDisplayString(good.pivot.price), 1)]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, { cols: "1" }, {
																			default: withCtx(() => [createVNode("span", { class: "text-[10px]" }, toDisplayString(good.pivot.quantity), 1)]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, null, {
																			default: withCtx(() => [createVNode("span", { class: "text-sm" }, toDisplayString(good.pivot.total), 1)]),
																			_: 2
																		}, 1024)
																	]),
																	_: 2
																}, 1024)]),
																_: 2
															}, 1024);
														}), 256))]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									}), createVNode(VTabsWindowItem, { value: "entities" }, {
										default: withCtx(() => [createVNode(VContainer, null, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, { lg: "3" }, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: searchEntities.value,
														"onUpdate:modelValue": ($event) => searchEntities.value = $event,
														label: "Search for entities",
														variant: "solo",
														density: "compact",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}), createVNode(VCol, { lg: "2" }, {
													default: withCtx(() => [createVNode(VBtn, {
														text: "+",
														onClick: ($event) => showFormEntity.value = !showFormEntity.value,
														variant: "elevated",
														color: "purple-darken-4"
													}, null, 8, ["onClick"]), createVNode(VDialog, {
														modelValue: showFormEntity.value,
														"onUpdate:modelValue": ($event) => showFormEntity.value = $event,
														width: "990",
														transition: "dialog-top-transition"
													}, {
														default: withCtx(() => [createVNode(VCard, null, {
															default: withCtx(() => [
																createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Form Entity")]),
																	_: 1
																}),
																createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																		default: withCtx(() => [
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formEntity).name,
																					"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																					label: "Name",
																					variant: "outlined"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VSelect, {
																						items: entityClassifications.value,
																						"item-value": "id",
																						"item-title": "name",
																						modelValue: unref(formEntity).entity_classification_id,
																						"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																						variant: "outlined",
																						label: "Вид"
																					}, null, 8, [
																						"items",
																						"modelValue",
																						"onUpdate:modelValue"
																					])]),
																					_: 1
																				}), createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VAutocomplete, {
																						items: telephones.value,
																						"item-value": "id",
																						"item-title": "number",
																						modelValue: unref(formEntity).telephones,
																						"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																						label: "Telephones",
																						placeholder: "number without +7",
																						variant: "outlined",
																						density: "comfortable",
																						color: "purple-darken-4",
																						multiple: "",
																						chips: ""
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
																					default: withCtx(() => [createVNode(VAutocomplete, {
																						items: cities.value,
																						"item-value": "id",
																						"item-title": "name",
																						modelValue: unref(formEntity).cities,
																						"onUpdate:modelValue": ($event) => unref(formEntity).cities = $event,
																						label: "Cities",
																						placeholder: "Населенный пункт",
																						variant: "solo",
																						density: "comfortable",
																						color: "grey",
																						multiple: ""
																					}, null, 8, [
																						"items",
																						"modelValue",
																						"onUpdate:modelValue"
																					])]),
																					_: 1
																				}), createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VAutocomplete, {
																						items: buildings.value,
																						"item-value": "id",
																						"item-title": "address",
																						modelValue: unref(formEntity).buildings,
																						"onUpdate:modelValue": ($event) => unref(formEntity).buildings = $event,
																						multiple: "",
																						label: "Buildings",
																						placeholder: "Адрес здания",
																						variant: "outlined",
																						density: "comfortable",
																						color: "teal"
																					}, null, 8, [
																						"items",
																						"modelValue",
																						"onUpdate:modelValue"
																					])]),
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
																		onClick: storeEntity,
																		text: "сохранить",
																		variant: "flat"
																	})]),
																	_: 1
																})
															]),
															_: 1
														})]),
														_: 1
													}, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})]),
												_: 1
											}), createVNode(VRow, null, {
												default: withCtx(() => [
													createVNode(VCol, { cols: "1" }),
													createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VDataTable, {
															items: filteredEntities.value,
															"items-per-page": "125",
															headers: headerEntities,
															"fixed-header": "",
															density: "compact",
															hover: "",
															height: "1000px"
														}, {
															"item.telephones": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.telephones, (telephone) => {
																return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
															}), 256))]),
															"item.units": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.units, (unit) => {
																return openBlock(), createBlock("div", { class: "font-RobotoRegular text-sm" }, toDisplayString(unit.name), 1);
															}), 256))]),
															"item.cities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.cities, (city) => {
																return openBlock(), createBlock("div", { class: "text-sm" }, toDisplayString(city.name), 1);
															}), 256))]),
															"item.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.buildings, (building) => {
																return openBlock(), createBlock("div", { class: "font-Typingrad text-[10px]" }, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
															}), 256))]),
															_: 1
														}, 8, ["items"])]),
														_: 1
													}),
													createVNode(VCol, { cols: "1" })
												]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Sales.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Sales-CX2p3Gbj.js.map