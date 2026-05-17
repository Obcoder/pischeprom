import { C as VRow, D as VDataTable, F as VCardText, G as VList, H as VSelect, I as VCardTitle, J as VListItemTitle, K as VDivider, P as VCard, R as VCardActions, T as VContainer, U as VTextField, V as VAutocomplete, Y as VListItemSubtitle, dt as useDate, h as VForm, q as VListItem, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { useForm } from "@inertiajs/vue3";
import { Fragment, createBlock, createTextVNode, createVNode, isRef, onMounted, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Ameise/Entities.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Entities",
	__ssrInlineRender: true,
	setup(__props) {
		const date = useDate();
		const buildings = ref([]);
		const cities = ref([]);
		const entities = ref([]);
		const telephones = ref();
		let searchEntitiesLike = ref();
		let searchTelephones = ref();
		const headerTelephones = [{
			title: "Number",
			key: "number"
		}, {
			title: "Создан",
			key: "created_at",
			align: "start"
		}];
		const indexBuildings = async () => {
			try {
				buildings.value = (await axios.get(route("buildings.index"))).data;
			} catch (error) {
				console.log(error);
			}
		};
		const indexCities = async () => {
			try {
				cities.value = (await axios.get(route("cities.index"))).data;
			} catch (error) {
				console.log(error);
			}
		};
		function indexEntities(like) {
			axios.get(route("entities.index"), { params: { search: like } }).then(function(response) {
				entities.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		function indexTelephones(like) {
			axios.get(route("telephones.index"), { params: { search: like } }).then(function(response) {
				telephones.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let listEntityClassifications = ref();
		function apiIndexEntityClassifications() {
			axios.get(route("api.entitiesclassifications")).then(function(response) {
				listEntityClassifications.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let showFormTelephone = ref(false);
		const formTelephone = useForm({ number: null });
		let showFormEntity = ref(false);
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
		function storeTelephone() {
			formTelephone.post(route("telephones.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: true,
				onSuccess: () => {
					formTelephone.reset();
					indexTelephones(searchTelephones.value);
				}
			});
		}
		onMounted(() => {
			indexBuildings();
			indexCities();
			indexEntities();
			apiIndexEntityClassifications();
			indexTelephones();
		});
		useHead({
			title: `Entities: ${entities.value.length}`,
			meta: [{
				name: "description",
				content: `Информация о блоке Entities`
			}]
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VTextField, {
												modelValue: unref(searchEntitiesLike),
												"onUpdate:modelValue": ($event) => isRef(searchEntitiesLike) ? searchEntitiesLike.value = $event : searchEntitiesLike = $event,
												onInput: ($event) => indexEntities(unref(searchEntitiesLike)),
												label: "Search",
												variant: "solo",
												density: "compact"
											}, null, _parent, _scopeId));
											else return [createVNode(VTextField, {
												modelValue: unref(searchEntitiesLike),
												"onUpdate:modelValue": ($event) => isRef(searchEntitiesLike) ? searchEntitiesLike.value = $event : searchEntitiesLike = $event,
												onInput: ($event) => indexEntities(unref(searchEntitiesLike)),
												label: "Search",
												variant: "solo",
												density: "compact"
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"onInput"
											])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VBtn, {
													text: "+",
													onClick: ($event) => isRef(showFormEntity) ? showFormEntity.value = true : showFormEntity = true,
													variant: "elevated",
													color: "purple-darken-4"
												}, null, _parent, _scopeId));
												_push(ssrRenderComponent(VDialog, {
													modelValue: unref(showFormEntity),
													"onUpdate:modelValue": ($event) => isRef(showFormEntity) ? showFormEntity.value = $event : showFormEntity = $event,
													width: "990",
													transition: "dialog-top-transition"
												}, {
													default: withCtx(({ isActive }, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VCard, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	_push(ssrRenderComponent(VCardTitle, null, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(`Form Entity`);
																			else return [createTextVNode("Form Entity")];
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
																							_: 2
																						}, _parent, _scopeId));
																						_push(ssrRenderComponent(VRow, null, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) {
																									_push(ssrRenderComponent(VCol, null, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(ssrRenderComponent(VSelect, {
																												items: unref(listEntityClassifications),
																												"item-value": "id",
																												"item-title": "name",
																												modelValue: unref(formEntity).entity_classification_id,
																												"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																												variant: "outlined",
																												label: "Вид"
																											}, null, _parent, _scopeId));
																											else return [createVNode(VSelect, {
																												items: unref(listEntityClassifications),
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
																										_: 2
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
																										_: 2
																									}, _parent, _scopeId));
																								} else return [createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VSelect, {
																										items: unref(listEntityClassifications),
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
																							_: 2
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
																										_: 2
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
																										_: 2
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
																							_: 2
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
																									items: unref(listEntityClassifications),
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
																				_: 2
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
																								items: unref(listEntityClassifications),
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
																		_: 2
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
																		_: 2
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
																							items: unref(listEntityClassifications),
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
															_: 2
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
																						items: unref(listEntityClassifications),
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
												onClick: ($event) => isRef(showFormEntity) ? showFormEntity.value = true : showFormEntity = true,
												variant: "elevated",
												color: "purple-darken-4"
											}, null, 8, ["onClick"]), createVNode(VDialog, {
												modelValue: unref(showFormEntity),
												"onUpdate:modelValue": ($event) => isRef(showFormEntity) ? showFormEntity.value = $event : showFormEntity = $event,
												width: "990",
												transition: "dialog-top-transition"
											}, {
												default: withCtx(({ isActive }) => [createVNode(VCard, null, {
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
																				items: unref(listEntityClassifications),
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
								} else return [
									createVNode(VCol, null, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: unref(searchEntitiesLike),
											"onUpdate:modelValue": ($event) => isRef(searchEntitiesLike) ? searchEntitiesLike.value = $event : searchEntitiesLike = $event,
											onInput: ($event) => indexEntities(unref(searchEntitiesLike)),
											label: "Search",
											variant: "solo",
											density: "compact"
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"onInput"
										])]),
										_: 1
									}),
									createVNode(VCol),
									createVNode(VCol, null, {
										default: withCtx(() => [createVNode(VBtn, {
											text: "+",
											onClick: ($event) => isRef(showFormEntity) ? showFormEntity.value = true : showFormEntity = true,
											variant: "elevated",
											color: "purple-darken-4"
										}, null, 8, ["onClick"]), createVNode(VDialog, {
											modelValue: unref(showFormEntity),
											"onUpdate:modelValue": ($event) => isRef(showFormEntity) ? showFormEntity.value = $event : showFormEntity = $event,
											width: "990",
											transition: "dialog-top-transition"
										}, {
											default: withCtx(({ isActive }) => [createVNode(VCard, null, {
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
																			items: unref(listEntityClassifications),
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
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { lg: "5" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VList, { lines: "one" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(`<!--[-->`);
														ssrRenderList(entities.value, (entity) => {
															_push(ssrRenderComponent(VListItem, {
																class: "hover:teal-lighten-3",
																"base-color": "teal-darken-4",
																border: "border",
																color: "teal-lighten-5",
																density: "comfortable",
																elevation: "1",
																link: "",
																ripple: "",
																slim: "",
																rounded: ""
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VListItemTitle, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`${ssrInterpolate(entity.name)}`);
																				else return [createTextVNode(toDisplayString(entity.name), 1)];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VListItemSubtitle, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`${ssrInterpolate(entity.classification.name)}`);
																				else return [createTextVNode(toDisplayString(entity.classification.name), 1)];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VDivider, {
																			opacity: "0.75",
																			thickness: "1",
																			color: "teal-darken-3"
																		}, null, _parent, _scopeId));
																		_push(ssrRenderComponent(VRow, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(ssrRenderComponent(VCol, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) {
																								_push(`<!--[-->`);
																								ssrRenderList(entity.cities, (city) => {
																									_push(`<div${_scopeId}><span class="text-xs font-serif text-slate-800"${_scopeId}>${ssrInterpolate(city.name)}</span></div>`);
																								});
																								_push(`<!--]-->`);
																							} else return [(openBlock(true), createBlock(Fragment, null, renderList(entity.cities, (city) => {
																								return openBlock(), createBlock("div", null, [createVNode("span", { class: "text-xs font-serif text-slate-800" }, toDisplayString(city.name), 1)]);
																							}), 256))];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCol, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) {
																								_push(`<!--[-->`);
																								ssrRenderList(entity.buildings, (building) => {
																									_push(`<div class="text-[8px]"${_scopeId}>${ssrInterpolate(building.address)}</div>`);
																								});
																								_push(`<!--]-->`);
																							} else return [(openBlock(true), createBlock(Fragment, null, renderList(entity.buildings, (building) => {
																								return openBlock(), createBlock("div", { class: "text-[8px]" }, toDisplayString(building.address), 1);
																							}), 256))];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCol, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) {
																								_push(`<!--[-->`);
																								ssrRenderList(entity.telephones, (telephone) => {
																									_push(`<div${_scopeId}>${ssrInterpolate(telephone.number)}</div>`);
																								});
																								_push(`<!--]-->`);
																							} else return [(openBlock(true), createBlock(Fragment, null, renderList(entity.telephones, (telephone) => {
																								return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																							}), 256))];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCol, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) {
																								_push(`<!--[-->`);
																								ssrRenderList(entity.chats, (chat) => {
																									_push(ssrRenderComponent(VRow, null, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) {
																												_push(ssrRenderComponent(VCol, null, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) _push(`${ssrInterpolate(chat.numbers)}`);
																														else return [createTextVNode(toDisplayString(chat.numbers), 1)];
																													}),
																													_: 2
																												}, _parent, _scopeId));
																												_push(ssrRenderComponent(VCol, null, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) _push(`${ssrInterpolate(chat.first_name)}`);
																														else return [createTextVNode(toDisplayString(chat.first_name), 1)];
																													}),
																													_: 2
																												}, _parent, _scopeId));
																											} else return [createVNode(VCol, null, {
																												default: withCtx(() => [createTextVNode(toDisplayString(chat.numbers), 1)]),
																												_: 2
																											}, 1024), createVNode(VCol, null, {
																												default: withCtx(() => [createTextVNode(toDisplayString(chat.first_name), 1)]),
																												_: 2
																											}, 1024)];
																										}),
																										_: 2
																									}, _parent, _scopeId));
																								});
																								_push(`<!--]-->`);
																							} else return [(openBlock(true), createBlock(Fragment, null, renderList(entity.chats, (chat) => {
																								return openBlock(), createBlock(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, null, {
																										default: withCtx(() => [createTextVNode(toDisplayString(chat.numbers), 1)]),
																										_: 2
																									}, 1024), createVNode(VCol, null, {
																										default: withCtx(() => [createTextVNode(toDisplayString(chat.first_name), 1)]),
																										_: 2
																									}, 1024)]),
																									_: 2
																								}, 1024);
																							}), 256))];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCol, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) {
																								_push(`<!--[-->`);
																								ssrRenderList(entity.units, (unit) => {
																									_push(ssrRenderComponent(VRow, null, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(ssrRenderComponent(VCol, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(`${ssrInterpolate(unit.name)}`);
																													else return [createTextVNode(toDisplayString(unit.name), 1)];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																											else return [createVNode(VCol, null, {
																												default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																												_: 2
																											}, 1024)];
																										}),
																										_: 2
																									}, _parent, _scopeId));
																								});
																								_push(`<!--]-->`);
																							} else return [(openBlock(true), createBlock(Fragment, null, renderList(entity.units, (unit) => {
																								return openBlock(), createBlock(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, null, {
																										default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																										_: 2
																									}, 1024)]),
																									_: 2
																								}, 1024);
																							}), 256))];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																				} else return [
																					createVNode(VCol, null, {
																						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.cities, (city) => {
																							return openBlock(), createBlock("div", null, [createVNode("span", { class: "text-xs font-serif text-slate-800" }, toDisplayString(city.name), 1)]);
																						}), 256))]),
																						_: 2
																					}, 1024),
																					createVNode(VCol, null, {
																						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.buildings, (building) => {
																							return openBlock(), createBlock("div", { class: "text-[8px]" }, toDisplayString(building.address), 1);
																						}), 256))]),
																						_: 2
																					}, 1024),
																					createVNode(VCol, null, {
																						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.telephones, (telephone) => {
																							return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																						}), 256))]),
																						_: 2
																					}, 1024),
																					createVNode(VCol, null, {
																						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.chats, (chat) => {
																							return openBlock(), createBlock(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, null, {
																									default: withCtx(() => [createTextVNode(toDisplayString(chat.numbers), 1)]),
																									_: 2
																								}, 1024), createVNode(VCol, null, {
																									default: withCtx(() => [createTextVNode(toDisplayString(chat.first_name), 1)]),
																									_: 2
																								}, 1024)]),
																								_: 2
																							}, 1024);
																						}), 256))]),
																						_: 2
																					}, 1024),
																					createVNode(VCol, null, {
																						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.units, (unit) => {
																							return openBlock(), createBlock(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, null, {
																									default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																									_: 2
																								}, 1024)]),
																								_: 2
																							}, 1024);
																						}), 256))]),
																						_: 2
																					}, 1024)
																				];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																	} else return [
																		createVNode(VListItemTitle, null, {
																			default: withCtx(() => [createTextVNode(toDisplayString(entity.name), 1)]),
																			_: 2
																		}, 1024),
																		createVNode(VListItemSubtitle, null, {
																			default: withCtx(() => [createTextVNode(toDisplayString(entity.classification.name), 1)]),
																			_: 2
																		}, 1024),
																		createVNode(VDivider, {
																			opacity: "0.75",
																			thickness: "1",
																			color: "teal-darken-3"
																		}),
																		createVNode(VRow, null, {
																			default: withCtx(() => [
																				createVNode(VCol, null, {
																					default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.cities, (city) => {
																						return openBlock(), createBlock("div", null, [createVNode("span", { class: "text-xs font-serif text-slate-800" }, toDisplayString(city.name), 1)]);
																					}), 256))]),
																					_: 2
																				}, 1024),
																				createVNode(VCol, null, {
																					default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.buildings, (building) => {
																						return openBlock(), createBlock("div", { class: "text-[8px]" }, toDisplayString(building.address), 1);
																					}), 256))]),
																					_: 2
																				}, 1024),
																				createVNode(VCol, null, {
																					default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.telephones, (telephone) => {
																						return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																					}), 256))]),
																					_: 2
																				}, 1024),
																				createVNode(VCol, null, {
																					default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.chats, (chat) => {
																						return openBlock(), createBlock(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, null, {
																								default: withCtx(() => [createTextVNode(toDisplayString(chat.numbers), 1)]),
																								_: 2
																							}, 1024), createVNode(VCol, null, {
																								default: withCtx(() => [createTextVNode(toDisplayString(chat.first_name), 1)]),
																								_: 2
																							}, 1024)]),
																							_: 2
																						}, 1024);
																					}), 256))]),
																					_: 2
																				}, 1024),
																				createVNode(VCol, null, {
																					default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.units, (unit) => {
																						return openBlock(), createBlock(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, null, {
																								default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																								_: 2
																							}, 1024)]),
																							_: 2
																						}, 1024);
																					}), 256))]),
																					_: 2
																				}, 1024)
																			]),
																			_: 2
																		}, 1024)
																	];
																}),
																_: 2
															}, _parent, _scopeId));
														});
														_push(`<!--]-->`);
													} else return [(openBlock(true), createBlock(Fragment, null, renderList(entities.value, (entity) => {
														return openBlock(), createBlock(VListItem, {
															class: "hover:teal-lighten-3",
															"base-color": "teal-darken-4",
															border: "border",
															color: "teal-lighten-5",
															density: "comfortable",
															elevation: "1",
															link: "",
															ripple: "",
															slim: "",
															rounded: ""
														}, {
															default: withCtx(() => [
																createVNode(VListItemTitle, null, {
																	default: withCtx(() => [createTextVNode(toDisplayString(entity.name), 1)]),
																	_: 2
																}, 1024),
																createVNode(VListItemSubtitle, null, {
																	default: withCtx(() => [createTextVNode(toDisplayString(entity.classification.name), 1)]),
																	_: 2
																}, 1024),
																createVNode(VDivider, {
																	opacity: "0.75",
																	thickness: "1",
																	color: "teal-darken-3"
																}),
																createVNode(VRow, null, {
																	default: withCtx(() => [
																		createVNode(VCol, null, {
																			default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.cities, (city) => {
																				return openBlock(), createBlock("div", null, [createVNode("span", { class: "text-xs font-serif text-slate-800" }, toDisplayString(city.name), 1)]);
																			}), 256))]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, null, {
																			default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.buildings, (building) => {
																				return openBlock(), createBlock("div", { class: "text-[8px]" }, toDisplayString(building.address), 1);
																			}), 256))]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, null, {
																			default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.telephones, (telephone) => {
																				return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																			}), 256))]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, null, {
																			default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.chats, (chat) => {
																				return openBlock(), createBlock(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createTextVNode(toDisplayString(chat.numbers), 1)]),
																						_: 2
																					}, 1024), createVNode(VCol, null, {
																						default: withCtx(() => [createTextVNode(toDisplayString(chat.first_name), 1)]),
																						_: 2
																					}, 1024)]),
																					_: 2
																				}, 1024);
																			}), 256))]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, null, {
																			default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.units, (unit) => {
																				return openBlock(), createBlock(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																						_: 2
																					}, 1024)]),
																					_: 2
																				}, 1024);
																			}), 256))]),
																			_: 2
																		}, 1024)
																	]),
																	_: 2
																}, 1024)
															]),
															_: 2
														}, 1024);
													}), 256))];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VList, { lines: "one" }, {
												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entities.value, (entity) => {
													return openBlock(), createBlock(VListItem, {
														class: "hover:teal-lighten-3",
														"base-color": "teal-darken-4",
														border: "border",
														color: "teal-lighten-5",
														density: "comfortable",
														elevation: "1",
														link: "",
														ripple: "",
														slim: "",
														rounded: ""
													}, {
														default: withCtx(() => [
															createVNode(VListItemTitle, null, {
																default: withCtx(() => [createTextVNode(toDisplayString(entity.name), 1)]),
																_: 2
															}, 1024),
															createVNode(VListItemSubtitle, null, {
																default: withCtx(() => [createTextVNode(toDisplayString(entity.classification.name), 1)]),
																_: 2
															}, 1024),
															createVNode(VDivider, {
																opacity: "0.75",
																thickness: "1",
																color: "teal-darken-3"
															}),
															createVNode(VRow, null, {
																default: withCtx(() => [
																	createVNode(VCol, null, {
																		default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.cities, (city) => {
																			return openBlock(), createBlock("div", null, [createVNode("span", { class: "text-xs font-serif text-slate-800" }, toDisplayString(city.name), 1)]);
																		}), 256))]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, null, {
																		default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.buildings, (building) => {
																			return openBlock(), createBlock("div", { class: "text-[8px]" }, toDisplayString(building.address), 1);
																		}), 256))]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, null, {
																		default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.telephones, (telephone) => {
																			return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																		}), 256))]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, null, {
																		default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.chats, (chat) => {
																			return openBlock(), createBlock(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createTextVNode(toDisplayString(chat.numbers), 1)]),
																					_: 2
																				}, 1024), createVNode(VCol, null, {
																					default: withCtx(() => [createTextVNode(toDisplayString(chat.first_name), 1)]),
																					_: 2
																				}, 1024)]),
																				_: 2
																			}, 1024);
																		}), 256))]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, null, {
																		default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.units, (unit) => {
																			return openBlock(), createBlock(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																					_: 2
																				}, 1024)]),
																				_: 2
																			}, 1024);
																		}), 256))]),
																		_: 2
																	}, 1024)
																]),
																_: 2
															}, 1024)
														]),
														_: 2
													}, 1024);
												}), 256))]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VRow, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCol, { cols: "10" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VTextField, {
																		modelValue: unref(searchTelephones),
																		"onUpdate:modelValue": ($event) => isRef(searchTelephones) ? searchTelephones.value = $event : searchTelephones = $event,
																		onInput: ($event) => indexTelephones(unref(searchTelephones)),
																		density: "comfortable",
																		label: "search Telephones",
																		variant: "solo"
																	}, null, _parent, _scopeId));
																	else return [createVNode(VTextField, {
																		modelValue: unref(searchTelephones),
																		"onUpdate:modelValue": ($event) => isRef(searchTelephones) ? searchTelephones.value = $event : searchTelephones = $event,
																		onInput: ($event) => indexTelephones(unref(searchTelephones)),
																		density: "comfortable",
																		label: "search Telephones",
																		variant: "solo"
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"onInput"
																	])];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, { cols: "2" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VBtn, {
																			text: "New telephone",
																			onClick: ($event) => isRef(showFormTelephone) ? showFormTelephone.value = !unref(showFormTelephone) : showFormTelephone = !unref(showFormTelephone),
																			variant: "elevated",
																			color: "purple"
																		}, null, _parent, _scopeId));
																		_push(ssrRenderComponent(VDialog, {
																			modelValue: unref(showFormTelephone),
																			"onUpdate:modelValue": ($event) => isRef(showFormTelephone) ? showFormTelephone.value = $event : showFormTelephone = $event,
																			width: "600"
																		}, {
																			default: withCtx(({ isActive }, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VCard, null, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) {
																							_push(ssrRenderComponent(VCardTitle, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(`Form Telephone`);
																									else return [createTextVNode("Form Telephone")];
																								}),
																								_: 2
																							}, _parent, _scopeId));
																							_push(ssrRenderComponent(VCardText, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(ssrRenderComponent(VForm, { onSubmit: () => {} }, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(ssrRenderComponent(VRow, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(ssrRenderComponent(VCol, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(ssrRenderComponent(VTextField, {
																																modelValue: unref(formTelephone).number,
																																"onUpdate:modelValue": ($event) => unref(formTelephone).number = $event,
																																label: "Number",
																																placeholder: "+....",
																																variant: "outlined",
																																color: "red"
																															}, null, _parent, _scopeId));
																															else return [createVNode(VTextField, {
																																modelValue: unref(formTelephone).number,
																																"onUpdate:modelValue": ($event) => unref(formTelephone).number = $event,
																																label: "Number",
																																placeholder: "+....",
																																variant: "outlined",
																																color: "red"
																															}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																														}),
																														_: 2
																													}, _parent, _scopeId));
																													else return [createVNode(VCol, null, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(formTelephone).number,
																															"onUpdate:modelValue": ($event) => unref(formTelephone).number = $event,
																															label: "Number",
																															placeholder: "+....",
																															variant: "outlined",
																															color: "red"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																														_: 1
																													})];
																												}),
																												_: 2
																											}, _parent, _scopeId));
																											else return [createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VCol, null, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formTelephone).number,
																														"onUpdate:modelValue": ($event) => unref(formTelephone).number = $event,
																														label: "Number",
																														placeholder: "+....",
																														variant: "outlined",
																														color: "red"
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
																													modelValue: unref(formTelephone).number,
																													"onUpdate:modelValue": ($event) => unref(formTelephone).number = $event,
																													label: "Number",
																													placeholder: "+....",
																													variant: "outlined",
																													color: "red"
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
																											thickness: "1",
																											opacity: "90"
																										}, null, _parent, _scopeId));
																										_push(ssrRenderComponent(VBtn, {
																											onClick: storeTelephone,
																											text: "save",
																											variant: "elevated",
																											color: "success"
																										}, null, _parent, _scopeId));
																									} else return [createVNode(VDivider, {
																										vertical: "",
																										thickness: "1",
																										opacity: "90"
																									}), createVNode(VBtn, {
																										onClick: storeTelephone,
																										text: "save",
																										variant: "elevated",
																										color: "success"
																									})];
																								}),
																								_: 2
																							}, _parent, _scopeId));
																						} else return [
																							createVNode(VCardTitle, null, {
																								default: withCtx(() => [createTextVNode("Form Telephone")]),
																								_: 1
																							}),
																							createVNode(VCardText, null, {
																								default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																									default: withCtx(() => [createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, null, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formTelephone).number,
																												"onUpdate:modelValue": ($event) => unref(formTelephone).number = $event,
																												label: "Number",
																												placeholder: "+....",
																												variant: "outlined",
																												color: "red"
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
																									thickness: "1",
																									opacity: "90"
																								}), createVNode(VBtn, {
																									onClick: storeTelephone,
																									text: "save",
																									variant: "elevated",
																									color: "success"
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
																							default: withCtx(() => [createTextVNode("Form Telephone")]),
																							_: 1
																						}),
																						createVNode(VCardText, null, {
																							default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																								default: withCtx(() => [createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formTelephone).number,
																											"onUpdate:modelValue": ($event) => unref(formTelephone).number = $event,
																											label: "Number",
																											placeholder: "+....",
																											variant: "outlined",
																											color: "red"
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
																								thickness: "1",
																								opacity: "90"
																							}), createVNode(VBtn, {
																								onClick: storeTelephone,
																								text: "save",
																								variant: "elevated",
																								color: "success"
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
																		text: "New telephone",
																		onClick: ($event) => isRef(showFormTelephone) ? showFormTelephone.value = !unref(showFormTelephone) : showFormTelephone = !unref(showFormTelephone),
																		variant: "elevated",
																		color: "purple"
																	}, null, 8, ["onClick"]), createVNode(VDialog, {
																		modelValue: unref(showFormTelephone),
																		"onUpdate:modelValue": ($event) => isRef(showFormTelephone) ? showFormTelephone.value = $event : showFormTelephone = $event,
																		width: "600"
																	}, {
																		default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																			default: withCtx(() => [
																				createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Form Telephone")]),
																					_: 1
																				}),
																				createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																						default: withCtx(() => [createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formTelephone).number,
																									"onUpdate:modelValue": ($event) => unref(formTelephone).number = $event,
																									label: "Number",
																									placeholder: "+....",
																									variant: "outlined",
																									color: "red"
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
																						thickness: "1",
																						opacity: "90"
																					}), createVNode(VBtn, {
																						onClick: storeTelephone,
																						text: "save",
																						variant: "elevated",
																						color: "success"
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
														} else return [createVNode(VCol, { cols: "10" }, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: unref(searchTelephones),
																"onUpdate:modelValue": ($event) => isRef(searchTelephones) ? searchTelephones.value = $event : searchTelephones = $event,
																onInput: ($event) => indexTelephones(unref(searchTelephones)),
																density: "comfortable",
																label: "search Telephones",
																variant: "solo"
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"onInput"
															])]),
															_: 1
														}), createVNode(VCol, { cols: "2" }, {
															default: withCtx(() => [createVNode(VBtn, {
																text: "New telephone",
																onClick: ($event) => isRef(showFormTelephone) ? showFormTelephone.value = !unref(showFormTelephone) : showFormTelephone = !unref(showFormTelephone),
																variant: "elevated",
																color: "purple"
															}, null, 8, ["onClick"]), createVNode(VDialog, {
																modelValue: unref(showFormTelephone),
																"onUpdate:modelValue": ($event) => isRef(showFormTelephone) ? showFormTelephone.value = $event : showFormTelephone = $event,
																width: "600"
															}, {
																default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																	default: withCtx(() => [
																		createVNode(VCardTitle, null, {
																			default: withCtx(() => [createTextVNode("Form Telephone")]),
																			_: 1
																		}),
																		createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																				default: withCtx(() => [createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formTelephone).number,
																							"onUpdate:modelValue": ($event) => unref(formTelephone).number = $event,
																							label: "Number",
																							placeholder: "+....",
																							variant: "outlined",
																							color: "red"
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
																				thickness: "1",
																				opacity: "90"
																			}), createVNode(VBtn, {
																				onClick: storeTelephone,
																				text: "save",
																				variant: "elevated",
																				color: "success"
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
														if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VDataTable, {
																	items: telephones.value,
																	headers: headerTelephones,
																	"items-per-page": "100",
																	density: "compact",
																	hover: "true"
																}, {
																	"item.created_at": withCtx(({ item }, _push, _parent, _scopeId) => {
																		if (_push) _push(`<span class="text-xs text-zinc-600"${_scopeId}>${ssrInterpolate(unref(date).format(item.created_at, "fullDate"))}</span>`);
																		else return [createVNode("span", { class: "text-xs text-zinc-600" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VDataTable, {
																	items: telephones.value,
																	headers: headerTelephones,
																	"items-per-page": "100",
																	density: "compact",
																	hover: "true"
																}, {
																	"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-xs text-zinc-600" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
																	_: 1
																}, 8, ["items"])];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(VDataTable, {
																items: telephones.value,
																headers: headerTelephones,
																"items-per-page": "100",
																density: "compact",
																hover: "true"
															}, {
																"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-xs text-zinc-600" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
																_: 1
															}, 8, ["items"])]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, { cols: "10" }, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: unref(searchTelephones),
														"onUpdate:modelValue": ($event) => isRef(searchTelephones) ? searchTelephones.value = $event : searchTelephones = $event,
														onInput: ($event) => indexTelephones(unref(searchTelephones)),
														density: "comfortable",
														label: "search Telephones",
														variant: "solo"
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"onInput"
													])]),
													_: 1
												}), createVNode(VCol, { cols: "2" }, {
													default: withCtx(() => [createVNode(VBtn, {
														text: "New telephone",
														onClick: ($event) => isRef(showFormTelephone) ? showFormTelephone.value = !unref(showFormTelephone) : showFormTelephone = !unref(showFormTelephone),
														variant: "elevated",
														color: "purple"
													}, null, 8, ["onClick"]), createVNode(VDialog, {
														modelValue: unref(showFormTelephone),
														"onUpdate:modelValue": ($event) => isRef(showFormTelephone) ? showFormTelephone.value = $event : showFormTelephone = $event,
														width: "600"
													}, {
														default: withCtx(({ isActive }) => [createVNode(VCard, null, {
															default: withCtx(() => [
																createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Form Telephone")]),
																	_: 1
																}),
																createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formTelephone).number,
																					"onUpdate:modelValue": ($event) => unref(formTelephone).number = $event,
																					label: "Number",
																					placeholder: "+....",
																					variant: "outlined",
																					color: "red"
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
																		thickness: "1",
																		opacity: "90"
																	}), createVNode(VBtn, {
																		onClick: storeTelephone,
																		text: "save",
																		variant: "elevated",
																		color: "success"
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
												default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VDataTable, {
														items: telephones.value,
														headers: headerTelephones,
														"items-per-page": "100",
														density: "compact",
														hover: "true"
													}, {
														"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-xs text-zinc-600" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
														_: 1
													}, 8, ["items"])]),
													_: 1
												})]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
								} else return [
									createVNode(VCol, { lg: "5" }, {
										default: withCtx(() => [createVNode(VList, { lines: "one" }, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entities.value, (entity) => {
												return openBlock(), createBlock(VListItem, {
													class: "hover:teal-lighten-3",
													"base-color": "teal-darken-4",
													border: "border",
													color: "teal-lighten-5",
													density: "comfortable",
													elevation: "1",
													link: "",
													ripple: "",
													slim: "",
													rounded: ""
												}, {
													default: withCtx(() => [
														createVNode(VListItemTitle, null, {
															default: withCtx(() => [createTextVNode(toDisplayString(entity.name), 1)]),
															_: 2
														}, 1024),
														createVNode(VListItemSubtitle, null, {
															default: withCtx(() => [createTextVNode(toDisplayString(entity.classification.name), 1)]),
															_: 2
														}, 1024),
														createVNode(VDivider, {
															opacity: "0.75",
															thickness: "1",
															color: "teal-darken-3"
														}),
														createVNode(VRow, null, {
															default: withCtx(() => [
																createVNode(VCol, null, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.cities, (city) => {
																		return openBlock(), createBlock("div", null, [createVNode("span", { class: "text-xs font-serif text-slate-800" }, toDisplayString(city.name), 1)]);
																	}), 256))]),
																	_: 2
																}, 1024),
																createVNode(VCol, null, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.buildings, (building) => {
																		return openBlock(), createBlock("div", { class: "text-[8px]" }, toDisplayString(building.address), 1);
																	}), 256))]),
																	_: 2
																}, 1024),
																createVNode(VCol, null, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.telephones, (telephone) => {
																		return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
																	}), 256))]),
																	_: 2
																}, 1024),
																createVNode(VCol, null, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.chats, (chat) => {
																		return openBlock(), createBlock(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, null, {
																				default: withCtx(() => [createTextVNode(toDisplayString(chat.numbers), 1)]),
																				_: 2
																			}, 1024), createVNode(VCol, null, {
																				default: withCtx(() => [createTextVNode(toDisplayString(chat.first_name), 1)]),
																				_: 2
																			}, 1024)]),
																			_: 2
																		}, 1024);
																	}), 256))]),
																	_: 2
																}, 1024),
																createVNode(VCol, null, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.units, (unit) => {
																		return openBlock(), createBlock(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, null, {
																				default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																				_: 2
																			}, 1024)]),
																			_: 2
																		}, 1024);
																	}), 256))]),
																	_: 2
																}, 1024)
															]),
															_: 2
														}, 1024)
													]),
													_: 2
												}, 1024);
											}), 256))]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCol, { cols: "3" }, {
										default: withCtx(() => [createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, { cols: "10" }, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: unref(searchTelephones),
													"onUpdate:modelValue": ($event) => isRef(searchTelephones) ? searchTelephones.value = $event : searchTelephones = $event,
													onInput: ($event) => indexTelephones(unref(searchTelephones)),
													density: "comfortable",
													label: "search Telephones",
													variant: "solo"
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"onInput"
												])]),
												_: 1
											}), createVNode(VCol, { cols: "2" }, {
												default: withCtx(() => [createVNode(VBtn, {
													text: "New telephone",
													onClick: ($event) => isRef(showFormTelephone) ? showFormTelephone.value = !unref(showFormTelephone) : showFormTelephone = !unref(showFormTelephone),
													variant: "elevated",
													color: "purple"
												}, null, 8, ["onClick"]), createVNode(VDialog, {
													modelValue: unref(showFormTelephone),
													"onUpdate:modelValue": ($event) => isRef(showFormTelephone) ? showFormTelephone.value = $event : showFormTelephone = $event,
													width: "600"
												}, {
													default: withCtx(({ isActive }) => [createVNode(VCard, null, {
														default: withCtx(() => [
															createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Form Telephone")]),
																_: 1
															}),
															createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																	default: withCtx(() => [createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formTelephone).number,
																				"onUpdate:modelValue": ($event) => unref(formTelephone).number = $event,
																				label: "Number",
																				placeholder: "+....",
																				variant: "outlined",
																				color: "red"
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
																	thickness: "1",
																	opacity: "90"
																}), createVNode(VBtn, {
																	onClick: storeTelephone,
																	text: "save",
																	variant: "elevated",
																	color: "success"
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
											default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VDataTable, {
													items: telephones.value,
													headers: headerTelephones,
													"items-per-page": "100",
													density: "compact",
													hover: "true"
												}, {
													"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-xs text-zinc-600" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
													_: 1
												}, 8, ["items"])]),
												_: 1
											})]),
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
							createVNode(VCol, null, {
								default: withCtx(() => [createVNode(VTextField, {
									modelValue: unref(searchEntitiesLike),
									"onUpdate:modelValue": ($event) => isRef(searchEntitiesLike) ? searchEntitiesLike.value = $event : searchEntitiesLike = $event,
									onInput: ($event) => indexEntities(unref(searchEntitiesLike)),
									label: "Search",
									variant: "solo",
									density: "compact"
								}, null, 8, [
									"modelValue",
									"onUpdate:modelValue",
									"onInput"
								])]),
								_: 1
							}),
							createVNode(VCol),
							createVNode(VCol, null, {
								default: withCtx(() => [createVNode(VBtn, {
									text: "+",
									onClick: ($event) => isRef(showFormEntity) ? showFormEntity.value = true : showFormEntity = true,
									variant: "elevated",
									color: "purple-darken-4"
								}, null, 8, ["onClick"]), createVNode(VDialog, {
									modelValue: unref(showFormEntity),
									"onUpdate:modelValue": ($event) => isRef(showFormEntity) ? showFormEntity.value = $event : showFormEntity = $event,
									width: "990",
									transition: "dialog-top-transition"
								}, {
									default: withCtx(({ isActive }) => [createVNode(VCard, null, {
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
																	items: unref(listEntityClassifications),
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
							})
						]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol, { lg: "5" }, {
								default: withCtx(() => [createVNode(VList, { lines: "one" }, {
									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entities.value, (entity) => {
										return openBlock(), createBlock(VListItem, {
											class: "hover:teal-lighten-3",
											"base-color": "teal-darken-4",
											border: "border",
											color: "teal-lighten-5",
											density: "comfortable",
											elevation: "1",
											link: "",
											ripple: "",
											slim: "",
											rounded: ""
										}, {
											default: withCtx(() => [
												createVNode(VListItemTitle, null, {
													default: withCtx(() => [createTextVNode(toDisplayString(entity.name), 1)]),
													_: 2
												}, 1024),
												createVNode(VListItemSubtitle, null, {
													default: withCtx(() => [createTextVNode(toDisplayString(entity.classification.name), 1)]),
													_: 2
												}, 1024),
												createVNode(VDivider, {
													opacity: "0.75",
													thickness: "1",
													color: "teal-darken-3"
												}),
												createVNode(VRow, null, {
													default: withCtx(() => [
														createVNode(VCol, null, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.cities, (city) => {
																return openBlock(), createBlock("div", null, [createVNode("span", { class: "text-xs font-serif text-slate-800" }, toDisplayString(city.name), 1)]);
															}), 256))]),
															_: 2
														}, 1024),
														createVNode(VCol, null, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.buildings, (building) => {
																return openBlock(), createBlock("div", { class: "text-[8px]" }, toDisplayString(building.address), 1);
															}), 256))]),
															_: 2
														}, 1024),
														createVNode(VCol, null, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.telephones, (telephone) => {
																return openBlock(), createBlock("div", null, toDisplayString(telephone.number), 1);
															}), 256))]),
															_: 2
														}, 1024),
														createVNode(VCol, null, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.chats, (chat) => {
																return openBlock(), createBlock(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createTextVNode(toDisplayString(chat.numbers), 1)]),
																		_: 2
																	}, 1024), createVNode(VCol, null, {
																		default: withCtx(() => [createTextVNode(toDisplayString(chat.first_name), 1)]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1024);
															}), 256))]),
															_: 2
														}, 1024),
														createVNode(VCol, null, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.units, (unit) => {
																return openBlock(), createBlock(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1024);
															}), 256))]),
															_: 2
														}, 1024)
													]),
													_: 2
												}, 1024)
											]),
											_: 2
										}, 1024);
									}), 256))]),
									_: 1
								})]),
								_: 1
							}),
							createVNode(VCol, { cols: "3" }, {
								default: withCtx(() => [createVNode(VRow, null, {
									default: withCtx(() => [createVNode(VCol, { cols: "10" }, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: unref(searchTelephones),
											"onUpdate:modelValue": ($event) => isRef(searchTelephones) ? searchTelephones.value = $event : searchTelephones = $event,
											onInput: ($event) => indexTelephones(unref(searchTelephones)),
											density: "comfortable",
											label: "search Telephones",
											variant: "solo"
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"onInput"
										])]),
										_: 1
									}), createVNode(VCol, { cols: "2" }, {
										default: withCtx(() => [createVNode(VBtn, {
											text: "New telephone",
											onClick: ($event) => isRef(showFormTelephone) ? showFormTelephone.value = !unref(showFormTelephone) : showFormTelephone = !unref(showFormTelephone),
											variant: "elevated",
											color: "purple"
										}, null, 8, ["onClick"]), createVNode(VDialog, {
											modelValue: unref(showFormTelephone),
											"onUpdate:modelValue": ($event) => isRef(showFormTelephone) ? showFormTelephone.value = $event : showFormTelephone = $event,
											width: "600"
										}, {
											default: withCtx(({ isActive }) => [createVNode(VCard, null, {
												default: withCtx(() => [
													createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Form Telephone")]),
														_: 1
													}),
													createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
															default: withCtx(() => [createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formTelephone).number,
																		"onUpdate:modelValue": ($event) => unref(formTelephone).number = $event,
																		label: "Number",
																		placeholder: "+....",
																		variant: "outlined",
																		color: "red"
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
															thickness: "1",
															opacity: "90"
														}), createVNode(VBtn, {
															onClick: storeTelephone,
															text: "save",
															variant: "elevated",
															color: "success"
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
									default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
										default: withCtx(() => [createVNode(VDataTable, {
											items: telephones.value,
											headers: headerTelephones,
											"items-per-page": "100",
											density: "compact",
											hover: "true"
										}, {
											"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-xs text-zinc-600" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
											_: 1
										}, 8, ["items"])]),
										_: 1
									})]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Entities.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Entities-JoNIP4ln.js.map