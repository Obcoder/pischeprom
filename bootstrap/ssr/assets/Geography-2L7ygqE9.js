import { C as VRow, F as VCardText, G as VList, I as VCardTitle, P as VCard, T as VContainer, U as VTextField, V as VAutocomplete, Y as VListItemSubtitle, a as VTabs, c as VTab, h as VForm, o as VTabsWindowItem, q as VListItem, rt as VBtn, s as VTabsWindow, w as VCol, z as VDialog } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { useForm } from "@inertiajs/vue3";
import { Fragment, createBlock, createTextVNode, createVNode, isRef, onMounted, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
//#region resources/js/Pages/Ameise/Geography.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Geography",
	__ssrInlineRender: true,
	setup(__props) {
		let countries = ref([]);
		let regions = ref([]);
		let cities = ref([]);
		let buildings = ref([]);
		let arrayData = [
			{
				name: "countries",
				list: countries
			},
			{
				name: "regions",
				list: regions
			},
			{
				name: "cities",
				list: cities
			},
			{
				name: "buildings",
				list: buildings
			}
		];
		function loadData(routeName, list, like) {
			axios.get(route(routeName), { params: { search: like } }).then(function(response) {
				list.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let tab = ref();
		onMounted(() => {
			arrayData.forEach((element) => {
				loadData(element.name + ".index", element.list);
				console.log(element.list);
			});
		});
		let searchBuildingsLike = ref();
		function indexBuildings(like) {
			axios.get(route("buildings.index"), { params: { search: like } }).then(function(response) {
				buildings.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let showFormBuilding = ref(false);
		const formBuilding = useForm({
			address: null,
			city_id: null,
			postcode: null
		});
		function storeBuilding() {
			formBuilding.post(route("buildings.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: false,
				onSuccess: () => {
					formBuilding.reset();
				},
				only: ["buildings"]
			});
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VRow, null, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) _push(ssrRenderComponent(VCol, null, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(ssrRenderComponent(VCard, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VTabs, {
													modelValue: unref(tab),
													"onUpdate:modelValue": ($event) => isRef(tab) ? tab.value = $event : tab = $event,
													"align-tabs": "center",
													color: "deep-purple-accent-4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VTab, { value: "countries" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`Countries`);
																	else return [createTextVNode("Countries")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VTab, { value: "cities" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`Cities`);
																	else return [createTextVNode("Cities")];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [createVNode(VTab, { value: "countries" }, {
															default: withCtx(() => [createTextVNode("Countries")]),
															_: 1
														}), createVNode(VTab, { value: "cities" }, {
															default: withCtx(() => [createTextVNode("Cities")]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCardText, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTabsWindow, {
															modelValue: unref(tab),
															"onUpdate:modelValue": ($event) => isRef(tab) ? tab.value = $event : tab = $event
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	_push(ssrRenderComponent(VTabsWindowItem, { value: "countries" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VRow, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) {
																						_push(ssrRenderComponent(VCol, null, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VCard, null, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) {
																											_push(ssrRenderComponent(VCardTitle, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(`Countries`);
																													else return [createTextVNode("Countries")];
																												}),
																												_: 1
																											}, _parent, _scopeId));
																											_push(ssrRenderComponent(VCardText, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(ssrRenderComponent(VList, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) {
																																_push(`<!--[-->`);
																																ssrRenderList(unref(countries), (country) => {
																																	_push(ssrRenderComponent(VListItem, { class: "text-[12px] font-sans" }, {
																																		default: withCtx((_, _push, _parent, _scopeId) => {
																																			if (_push) _push(`${ssrInterpolate(country.name)}`);
																																			else return [createTextVNode(toDisplayString(country.name), 1)];
																																		}),
																																		_: 2
																																	}, _parent, _scopeId));
																																});
																																_push(`<!--]-->`);
																															} else return [(openBlock(true), createBlock(Fragment, null, renderList(unref(countries), (country) => {
																																return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																																	default: withCtx(() => [createTextVNode(toDisplayString(country.name), 1)]),
																																	_: 2
																																}, 1024);
																															}), 256))];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																													else return [createVNode(VList, null, {
																														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(countries), (country) => {
																															return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																																default: withCtx(() => [createTextVNode(toDisplayString(country.name), 1)]),
																																_: 2
																															}, 1024);
																														}), 256))]),
																														_: 1
																													})];
																												}),
																												_: 1
																											}, _parent, _scopeId));
																										} else return [createVNode(VCardTitle, null, {
																											default: withCtx(() => [createTextVNode("Countries")]),
																											_: 1
																										}), createVNode(VCardText, null, {
																											default: withCtx(() => [createVNode(VList, null, {
																												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(countries), (country) => {
																													return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																														default: withCtx(() => [createTextVNode(toDisplayString(country.name), 1)]),
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
																								else return [createVNode(VCard, null, {
																									default: withCtx(() => [createVNode(VCardTitle, null, {
																										default: withCtx(() => [createTextVNode("Countries")]),
																										_: 1
																									}), createVNode(VCardText, null, {
																										default: withCtx(() => [createVNode(VList, null, {
																											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(countries), (country) => {
																												return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																													default: withCtx(() => [createTextVNode(toDisplayString(country.name), 1)]),
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
																						_push(ssrRenderComponent(VCol, null, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VCard, null, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) {
																											_push(ssrRenderComponent(VCardTitle, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(`Regions`);
																													else return [createTextVNode("Regions")];
																												}),
																												_: 1
																											}, _parent, _scopeId));
																											_push(ssrRenderComponent(VCardText, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(ssrRenderComponent(VList, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) {
																																_push(`<!--[-->`);
																																ssrRenderList(unref(regions), (region) => {
																																	_push(ssrRenderComponent(VListItem, { class: "text-[12px] font-sans" }, {
																																		default: withCtx((_, _push, _parent, _scopeId) => {
																																			if (_push) _push(`${ssrInterpolate(region.name)}`);
																																			else return [createTextVNode(toDisplayString(region.name), 1)];
																																		}),
																																		_: 2
																																	}, _parent, _scopeId));
																																});
																																_push(`<!--]-->`);
																															} else return [(openBlock(true), createBlock(Fragment, null, renderList(unref(regions), (region) => {
																																return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																																	default: withCtx(() => [createTextVNode(toDisplayString(region.name), 1)]),
																																	_: 2
																																}, 1024);
																															}), 256))];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																													else return [createVNode(VList, null, {
																														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(regions), (region) => {
																															return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																																default: withCtx(() => [createTextVNode(toDisplayString(region.name), 1)]),
																																_: 2
																															}, 1024);
																														}), 256))]),
																														_: 1
																													})];
																												}),
																												_: 1
																											}, _parent, _scopeId));
																										} else return [createVNode(VCardTitle, null, {
																											default: withCtx(() => [createTextVNode("Regions")]),
																											_: 1
																										}), createVNode(VCardText, null, {
																											default: withCtx(() => [createVNode(VList, null, {
																												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(regions), (region) => {
																													return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																														default: withCtx(() => [createTextVNode(toDisplayString(region.name), 1)]),
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
																								else return [createVNode(VCard, null, {
																									default: withCtx(() => [createVNode(VCardTitle, null, {
																										default: withCtx(() => [createTextVNode("Regions")]),
																										_: 1
																									}), createVNode(VCardText, null, {
																										default: withCtx(() => [createVNode(VList, null, {
																											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(regions), (region) => {
																												return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																													default: withCtx(() => [createTextVNode(toDisplayString(region.name), 1)]),
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
																					} else return [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VCard, null, {
																							default: withCtx(() => [createVNode(VCardTitle, null, {
																								default: withCtx(() => [createTextVNode("Countries")]),
																								_: 1
																							}), createVNode(VCardText, null, {
																								default: withCtx(() => [createVNode(VList, null, {
																									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(countries), (country) => {
																										return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																											default: withCtx(() => [createTextVNode(toDisplayString(country.name), 1)]),
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
																					}), createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VCard, null, {
																							default: withCtx(() => [createVNode(VCardTitle, null, {
																								default: withCtx(() => [createTextVNode("Regions")]),
																								_: 1
																							}), createVNode(VCardText, null, {
																								default: withCtx(() => [createVNode(VList, null, {
																									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(regions), (region) => {
																										return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																											default: withCtx(() => [createTextVNode(toDisplayString(region.name), 1)]),
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
																			else return [createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VCard, null, {
																						default: withCtx(() => [createVNode(VCardTitle, null, {
																							default: withCtx(() => [createTextVNode("Countries")]),
																							_: 1
																						}), createVNode(VCardText, null, {
																							default: withCtx(() => [createVNode(VList, null, {
																								default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(countries), (country) => {
																									return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																										default: withCtx(() => [createTextVNode(toDisplayString(country.name), 1)]),
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
																				}), createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VCard, null, {
																						default: withCtx(() => [createVNode(VCardTitle, null, {
																							default: withCtx(() => [createTextVNode("Regions")]),
																							_: 1
																						}), createVNode(VCardText, null, {
																							default: withCtx(() => [createVNode(VList, null, {
																								default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(regions), (region) => {
																									return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																										default: withCtx(() => [createTextVNode(toDisplayString(region.name), 1)]),
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
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VTabsWindowItem, { value: "cities" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VRow, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) {
																						_push(ssrRenderComponent(VCol, null, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VCard, null, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) {
																											_push(ssrRenderComponent(VCardTitle, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(`Cities`);
																													else return [createTextVNode("Cities")];
																												}),
																												_: 1
																											}, _parent, _scopeId));
																											_push(ssrRenderComponent(VCardText, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(ssrRenderComponent(VList, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) {
																																_push(`<!--[-->`);
																																ssrRenderList(unref(cities), (city) => {
																																	_push(ssrRenderComponent(VListItem, null, {
																																		default: withCtx((_, _push, _parent, _scopeId) => {
																																			if (_push) _push(`${ssrInterpolate(city.name)}`);
																																			else return [createTextVNode(toDisplayString(city.name), 1)];
																																		}),
																																		_: 2
																																	}, _parent, _scopeId));
																																});
																																_push(`<!--]-->`);
																															} else return [(openBlock(true), createBlock(Fragment, null, renderList(unref(cities), (city) => {
																																return openBlock(), createBlock(VListItem, null, {
																																	default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
																																	_: 2
																																}, 1024);
																															}), 256))];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																													else return [createVNode(VList, null, {
																														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(cities), (city) => {
																															return openBlock(), createBlock(VListItem, null, {
																																default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
																																_: 2
																															}, 1024);
																														}), 256))]),
																														_: 1
																													})];
																												}),
																												_: 1
																											}, _parent, _scopeId));
																										} else return [createVNode(VCardTitle, null, {
																											default: withCtx(() => [createTextVNode("Cities")]),
																											_: 1
																										}), createVNode(VCardText, null, {
																											default: withCtx(() => [createVNode(VList, null, {
																												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(cities), (city) => {
																													return openBlock(), createBlock(VListItem, null, {
																														default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
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
																								else return [createVNode(VCard, null, {
																									default: withCtx(() => [createVNode(VCardTitle, null, {
																										default: withCtx(() => [createTextVNode("Cities")]),
																										_: 1
																									}), createVNode(VCardText, null, {
																										default: withCtx(() => [createVNode(VList, null, {
																											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(cities), (city) => {
																												return openBlock(), createBlock(VListItem, null, {
																													default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
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
																						_push(ssrRenderComponent(VCol, null, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(VCard, null, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) {
																											_push(ssrRenderComponent(VCardTitle, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(`Buildings`);
																													else return [createTextVNode("Buildings")];
																												}),
																												_: 1
																											}, _parent, _scopeId));
																											_push(ssrRenderComponent(VCardText, null, {
																												default: withCtx((_, _push, _parent, _scopeId) => {
																													if (_push) _push(ssrRenderComponent(VContainer, null, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) {
																																_push(ssrRenderComponent(VRow, null, {
																																	default: withCtx((_, _push, _parent, _scopeId) => {
																																		if (_push) {
																																			_push(ssrRenderComponent(VCol, { cols: "9" }, {
																																				default: withCtx((_, _push, _parent, _scopeId) => {
																																					if (_push) _push(ssrRenderComponent(VTextField, {
																																						modelValue: unref(searchBuildingsLike),
																																						"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																																						onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																																						variant: "outlined",
																																						density: "compact",
																																						class: "text-sm"
																																					}, null, _parent, _scopeId));
																																					else return [createVNode(VTextField, {
																																						modelValue: unref(searchBuildingsLike),
																																						"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																																						onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																																						variant: "outlined",
																																						density: "compact",
																																						class: "text-sm"
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
																																						_push(ssrRenderComponent(VBtn, {
																																							onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																																							text: "+ building",
																																							variant: "elevated",
																																							density: "compact",
																																							color: "yellow"
																																						}, null, _parent, _scopeId));
																																						_push(ssrRenderComponent(VDialog, {
																																							modelValue: unref(showFormBuilding),
																																							"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																																							width: "700"
																																						}, {
																																							default: withCtx(({ isActive }, _push, _parent, _scopeId) => {
																																								if (_push) _push(ssrRenderComponent(VCard, null, {
																																									default: withCtx((_, _push, _parent, _scopeId) => {
																																										if (_push) {
																																											_push(ssrRenderComponent(VCardTitle, null, {
																																												default: withCtx((_, _push, _parent, _scopeId) => {
																																													if (_push) _push(`Form Building`);
																																													else return [createTextVNode("Form Building")];
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
																																																			_push(ssrRenderComponent(VCol, { cols: "7" }, {
																																																				default: withCtx((_, _push, _parent, _scopeId) => {
																																																					if (_push) _push(ssrRenderComponent(VTextField, {
																																																						modelValue: unref(formBuilding).address,
																																																						"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																																						label: "Address",
																																																						variant: "outlined",
																																																						density: "comfortable"
																																																					}, null, _parent, _scopeId));
																																																					else return [createVNode(VTextField, {
																																																						modelValue: unref(formBuilding).address,
																																																						"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																																						label: "Address",
																																																						variant: "outlined",
																																																						density: "comfortable"
																																																					}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																																																				}),
																																																				_: 2
																																																			}, _parent, _scopeId));
																																																			_push(ssrRenderComponent(VCol, { cols: "5" }, {
																																																				default: withCtx((_, _push, _parent, _scopeId) => {
																																																					if (_push) _push(ssrRenderComponent(VAutocomplete, {
																																																						items: unref(cities),
																																																						"item-value": "id",
																																																						"item-title": "name",
																																																						modelValue: unref(formBuilding).city_id,
																																																						"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																																						label: "Город",
																																																						variant: "outlined",
																																																						density: "comfortable",
																																																						color: "teal",
																																																						chips: ""
																																																					}, null, _parent, _scopeId));
																																																					else return [createVNode(VAutocomplete, {
																																																						items: unref(cities),
																																																						"item-value": "id",
																																																						"item-title": "name",
																																																						modelValue: unref(formBuilding).city_id,
																																																						"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																																						label: "Город",
																																																						variant: "outlined",
																																																						density: "comfortable",
																																																						color: "teal",
																																																						chips: ""
																																																					}, null, 8, [
																																																						"items",
																																																						"modelValue",
																																																						"onUpdate:modelValue"
																																																					])];
																																																				}),
																																																				_: 2
																																																			}, _parent, _scopeId));
																																																		} else return [createVNode(VCol, { cols: "7" }, {
																																																			default: withCtx(() => [createVNode(VTextField, {
																																																				modelValue: unref(formBuilding).address,
																																																				"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																																				label: "Address",
																																																				variant: "outlined",
																																																				density: "comfortable"
																																																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																																			_: 1
																																																		}), createVNode(VCol, { cols: "5" }, {
																																																			default: withCtx(() => [createVNode(VAutocomplete, {
																																																				items: unref(cities),
																																																				"item-value": "id",
																																																				"item-title": "name",
																																																				modelValue: unref(formBuilding).city_id,
																																																				"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																																				label: "Город",
																																																				variant: "outlined",
																																																				density: "comfortable",
																																																				color: "teal",
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
																																																					if (_push) _push(ssrRenderComponent(VTextField, {
																																																						modelValue: unref(formBuilding).postcode,
																																																						"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																																						label: "Postcode",
																																																						variant: "outlined",
																																																						density: "comfortable"
																																																					}, null, _parent, _scopeId));
																																																					else return [createVNode(VTextField, {
																																																						modelValue: unref(formBuilding).postcode,
																																																						"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																																						label: "Postcode",
																																																						variant: "outlined",
																																																						density: "comfortable"
																																																					}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																																																				}),
																																																				_: 2
																																																			}, _parent, _scopeId));
																																																			_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
																																																			_push(ssrRenderComponent(VCol, null, {
																																																				default: withCtx((_, _push, _parent, _scopeId) => {
																																																					if (_push) _push(ssrRenderComponent(VBtn, {
																																																						onClick: storeBuilding,
																																																						text: "store",
																																																						variant: "outlined",
																																																						density: "comfortable"
																																																					}, null, _parent, _scopeId));
																																																					else return [createVNode(VBtn, {
																																																						onClick: storeBuilding,
																																																						text: "store",
																																																						variant: "outlined",
																																																						density: "comfortable"
																																																					})];
																																																				}),
																																																				_: 2
																																																			}, _parent, _scopeId));
																																																		} else return [
																																																			createVNode(VCol, null, {
																																																				default: withCtx(() => [createVNode(VTextField, {
																																																					modelValue: unref(formBuilding).postcode,
																																																					"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																																					label: "Postcode",
																																																					variant: "outlined",
																																																					density: "comfortable"
																																																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																																				_: 1
																																																			}),
																																																			createVNode(VCol),
																																																			createVNode(VCol, null, {
																																																				default: withCtx(() => [createVNode(VBtn, {
																																																					onClick: storeBuilding,
																																																					text: "store",
																																																					variant: "outlined",
																																																					density: "comfortable"
																																																				})]),
																																																				_: 1
																																																			})
																																																		];
																																																	}),
																																																	_: 2
																																																}, _parent, _scopeId));
																																															} else return [createVNode(VRow, null, {
																																																default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																																	default: withCtx(() => [createVNode(VTextField, {
																																																		modelValue: unref(formBuilding).address,
																																																		"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																																		label: "Address",
																																																		variant: "outlined",
																																																		density: "comfortable"
																																																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																																	_: 1
																																																}), createVNode(VCol, { cols: "5" }, {
																																																	default: withCtx(() => [createVNode(VAutocomplete, {
																																																		items: unref(cities),
																																																		"item-value": "id",
																																																		"item-title": "name",
																																																		modelValue: unref(formBuilding).city_id,
																																																		"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																																		label: "Город",
																																																		variant: "outlined",
																																																		density: "comfortable",
																																																		color: "teal",
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
																																																default: withCtx(() => [
																																																	createVNode(VCol, null, {
																																																		default: withCtx(() => [createVNode(VTextField, {
																																																			modelValue: unref(formBuilding).postcode,
																																																			"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																																			label: "Postcode",
																																																			variant: "outlined",
																																																			density: "comfortable"
																																																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																																		_: 1
																																																	}),
																																																	createVNode(VCol),
																																																	createVNode(VCol, null, {
																																																		default: withCtx(() => [createVNode(VBtn, {
																																																			onClick: storeBuilding,
																																																			text: "store",
																																																			variant: "outlined",
																																																			density: "comfortable"
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
																																														default: withCtx(() => [createVNode(VRow, null, {
																																															default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																																default: withCtx(() => [createVNode(VTextField, {
																																																	modelValue: unref(formBuilding).address,
																																																	"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																																	label: "Address",
																																																	variant: "outlined",
																																																	density: "comfortable"
																																																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																																_: 1
																																															}), createVNode(VCol, { cols: "5" }, {
																																																default: withCtx(() => [createVNode(VAutocomplete, {
																																																	items: unref(cities),
																																																	"item-value": "id",
																																																	"item-title": "name",
																																																	modelValue: unref(formBuilding).city_id,
																																																	"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																																	label: "Город",
																																																	variant: "outlined",
																																																	density: "comfortable",
																																																	color: "teal",
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
																																															default: withCtx(() => [
																																																createVNode(VCol, null, {
																																																	default: withCtx(() => [createVNode(VTextField, {
																																																		modelValue: unref(formBuilding).postcode,
																																																		"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																																		label: "Postcode",
																																																		variant: "outlined",
																																																		density: "comfortable"
																																																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																																	_: 1
																																																}),
																																																createVNode(VCol),
																																																createVNode(VCol, null, {
																																																	default: withCtx(() => [createVNode(VBtn, {
																																																		onClick: storeBuilding,
																																																		text: "store",
																																																		variant: "outlined",
																																																		density: "comfortable"
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
																																										} else return [createVNode(VCardTitle, null, {
																																											default: withCtx(() => [createTextVNode("Form Building")]),
																																											_: 1
																																										}), createVNode(VCardText, null, {
																																											default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																												default: withCtx(() => [createVNode(VRow, null, {
																																													default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																														default: withCtx(() => [createVNode(VTextField, {
																																															modelValue: unref(formBuilding).address,
																																															"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																															label: "Address",
																																															variant: "outlined",
																																															density: "comfortable"
																																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																														_: 1
																																													}), createVNode(VCol, { cols: "5" }, {
																																														default: withCtx(() => [createVNode(VAutocomplete, {
																																															items: unref(cities),
																																															"item-value": "id",
																																															"item-title": "name",
																																															modelValue: unref(formBuilding).city_id,
																																															"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																															label: "Город",
																																															variant: "outlined",
																																															density: "comfortable",
																																															color: "teal",
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
																																													default: withCtx(() => [
																																														createVNode(VCol, null, {
																																															default: withCtx(() => [createVNode(VTextField, {
																																																modelValue: unref(formBuilding).postcode,
																																																"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																																label: "Postcode",
																																																variant: "outlined",
																																																density: "comfortable"
																																															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																															_: 1
																																														}),
																																														createVNode(VCol),
																																														createVNode(VCol, null, {
																																															default: withCtx(() => [createVNode(VBtn, {
																																																onClick: storeBuilding,
																																																text: "store",
																																																variant: "outlined",
																																																density: "comfortable"
																																															})]),
																																															_: 1
																																														})
																																													]),
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
																																									default: withCtx(() => [createVNode(VCardTitle, null, {
																																										default: withCtx(() => [createTextVNode("Form Building")]),
																																										_: 1
																																									}), createVNode(VCardText, null, {
																																										default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																											default: withCtx(() => [createVNode(VRow, null, {
																																												default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																													default: withCtx(() => [createVNode(VTextField, {
																																														modelValue: unref(formBuilding).address,
																																														"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																														label: "Address",
																																														variant: "outlined",
																																														density: "comfortable"
																																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																													_: 1
																																												}), createVNode(VCol, { cols: "5" }, {
																																													default: withCtx(() => [createVNode(VAutocomplete, {
																																														items: unref(cities),
																																														"item-value": "id",
																																														"item-title": "name",
																																														modelValue: unref(formBuilding).city_id,
																																														"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																														label: "Город",
																																														variant: "outlined",
																																														density: "comfortable",
																																														color: "teal",
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
																																												default: withCtx(() => [
																																													createVNode(VCol, null, {
																																														default: withCtx(() => [createVNode(VTextField, {
																																															modelValue: unref(formBuilding).postcode,
																																															"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																															label: "Postcode",
																																															variant: "outlined",
																																															density: "comfortable"
																																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																														_: 1
																																													}),
																																													createVNode(VCol),
																																													createVNode(VCol, null, {
																																														default: withCtx(() => [createVNode(VBtn, {
																																															onClick: storeBuilding,
																																															text: "store",
																																															variant: "outlined",
																																															density: "comfortable"
																																														})]),
																																														_: 1
																																													})
																																												]),
																																												_: 1
																																											})]),
																																											_: 1
																																										}, 8, ["onSubmit"])]),
																																										_: 1
																																									})]),
																																									_: 1
																																								})];
																																							}),
																																							_: 1
																																						}, _parent, _scopeId));
																																					} else return [createVNode(VBtn, {
																																						onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																																						text: "+ building",
																																						variant: "elevated",
																																						density: "compact",
																																						color: "yellow"
																																					}, null, 8, ["onClick"]), createVNode(VDialog, {
																																						modelValue: unref(showFormBuilding),
																																						"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																																						width: "700"
																																					}, {
																																						default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																																							default: withCtx(() => [createVNode(VCardTitle, null, {
																																								default: withCtx(() => [createTextVNode("Form Building")]),
																																								_: 1
																																							}), createVNode(VCardText, null, {
																																								default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																									default: withCtx(() => [createVNode(VRow, null, {
																																										default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																											default: withCtx(() => [createVNode(VTextField, {
																																												modelValue: unref(formBuilding).address,
																																												"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																												label: "Address",
																																												variant: "outlined",
																																												density: "comfortable"
																																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																											_: 1
																																										}), createVNode(VCol, { cols: "5" }, {
																																											default: withCtx(() => [createVNode(VAutocomplete, {
																																												items: unref(cities),
																																												"item-value": "id",
																																												"item-title": "name",
																																												modelValue: unref(formBuilding).city_id,
																																												"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																												label: "Город",
																																												variant: "outlined",
																																												density: "comfortable",
																																												color: "teal",
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
																																										default: withCtx(() => [
																																											createVNode(VCol, null, {
																																												default: withCtx(() => [createVNode(VTextField, {
																																													modelValue: unref(formBuilding).postcode,
																																													"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																													label: "Postcode",
																																													variant: "outlined",
																																													density: "comfortable"
																																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																												_: 1
																																											}),
																																											createVNode(VCol),
																																											createVNode(VCol, null, {
																																												default: withCtx(() => [createVNode(VBtn, {
																																													onClick: storeBuilding,
																																													text: "store",
																																													variant: "outlined",
																																													density: "comfortable"
																																												})]),
																																												_: 1
																																											})
																																										]),
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
																																				_: 1
																																			}, _parent, _scopeId));
																																		} else return [createVNode(VCol, { cols: "9" }, {
																																			default: withCtx(() => [createVNode(VTextField, {
																																				modelValue: unref(searchBuildingsLike),
																																				"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																																				onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																																				variant: "outlined",
																																				density: "compact",
																																				class: "text-sm"
																																			}, null, 8, [
																																				"modelValue",
																																				"onUpdate:modelValue",
																																				"onInput"
																																			])]),
																																			_: 1
																																		}), createVNode(VCol, { cols: "3" }, {
																																			default: withCtx(() => [createVNode(VBtn, {
																																				onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																																				text: "+ building",
																																				variant: "elevated",
																																				density: "compact",
																																				color: "yellow"
																																			}, null, 8, ["onClick"]), createVNode(VDialog, {
																																				modelValue: unref(showFormBuilding),
																																				"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																																				width: "700"
																																			}, {
																																				default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																																					default: withCtx(() => [createVNode(VCardTitle, null, {
																																						default: withCtx(() => [createTextVNode("Form Building")]),
																																						_: 1
																																					}), createVNode(VCardText, null, {
																																						default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																							default: withCtx(() => [createVNode(VRow, null, {
																																								default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																									default: withCtx(() => [createVNode(VTextField, {
																																										modelValue: unref(formBuilding).address,
																																										"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																										label: "Address",
																																										variant: "outlined",
																																										density: "comfortable"
																																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																									_: 1
																																								}), createVNode(VCol, { cols: "5" }, {
																																									default: withCtx(() => [createVNode(VAutocomplete, {
																																										items: unref(cities),
																																										"item-value": "id",
																																										"item-title": "name",
																																										modelValue: unref(formBuilding).city_id,
																																										"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																										label: "Город",
																																										variant: "outlined",
																																										density: "comfortable",
																																										color: "teal",
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
																																								default: withCtx(() => [
																																									createVNode(VCol, null, {
																																										default: withCtx(() => [createVNode(VTextField, {
																																											modelValue: unref(formBuilding).postcode,
																																											"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																											label: "Postcode",
																																											variant: "outlined",
																																											density: "comfortable"
																																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																										_: 1
																																									}),
																																									createVNode(VCol),
																																									createVNode(VCol, null, {
																																										default: withCtx(() => [createVNode(VBtn, {
																																											onClick: storeBuilding,
																																											text: "store",
																																											variant: "outlined",
																																											density: "comfortable"
																																										})]),
																																										_: 1
																																									})
																																								]),
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
																																	_: 1
																																}, _parent, _scopeId));
																																_push(ssrRenderComponent(VRow, null, {
																																	default: withCtx((_, _push, _parent, _scopeId) => {
																																		if (_push) _push(ssrRenderComponent(VCol, null, {
																																			default: withCtx((_, _push, _parent, _scopeId) => {
																																				if (_push) _push(ssrRenderComponent(VList, {
																																					lines: false,
																																					density: "compact"
																																				}, {
																																					default: withCtx((_, _push, _parent, _scopeId) => {
																																						if (_push) {
																																							_push(`<!--[-->`);
																																							ssrRenderList(unref(buildings), (building) => {
																																								_push(ssrRenderComponent(VListItem, {
																																									key: building.id,
																																									class: "hover:bg-rose-200"
																																								}, {
																																									default: withCtx((_, _push, _parent, _scopeId) => {
																																										if (_push) {
																																											_push(ssrRenderComponent(VListItemSubtitle, null, {
																																												default: withCtx((_, _push, _parent, _scopeId) => {
																																													if (_push) _push(`${ssrInterpolate(building.city.name)}`);
																																													else return [createTextVNode(toDisplayString(building.city.name), 1)];
																																												}),
																																												_: 2
																																											}, _parent, _scopeId));
																																											_push(ssrRenderComponent(VListItemSubtitle, null, {
																																												default: withCtx((_, _push, _parent, _scopeId) => {
																																													if (_push) _push(`${ssrInterpolate(building.city.region.name)}`);
																																													else return [createTextVNode(toDisplayString(building.city.region.name), 1)];
																																												}),
																																												_: 2
																																											}, _parent, _scopeId));
																																											_push(` ${ssrInterpolate(building.address)}`);
																																										} else return [
																																											createVNode(VListItemSubtitle, null, {
																																												default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																																												_: 2
																																											}, 1024),
																																											createVNode(VListItemSubtitle, null, {
																																												default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																																												_: 2
																																											}, 1024),
																																											createTextVNode(" " + toDisplayString(building.address), 1)
																																										];
																																									}),
																																									_: 2
																																								}, _parent, _scopeId));
																																							});
																																							_push(`<!--]-->`);
																																						} else return [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																																							return openBlock(), createBlock(VListItem, {
																																								key: building.id,
																																								class: "hover:bg-rose-200"
																																							}, {
																																								default: withCtx(() => [
																																									createVNode(VListItemSubtitle, null, {
																																										default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																																										_: 2
																																									}, 1024),
																																									createVNode(VListItemSubtitle, null, {
																																										default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																																										_: 2
																																									}, 1024),
																																									createTextVNode(" " + toDisplayString(building.address), 1)
																																								]),
																																								_: 2
																																							}, 1024);
																																						}), 128))];
																																					}),
																																					_: 1
																																				}, _parent, _scopeId));
																																				else return [createVNode(VList, {
																																					lines: false,
																																					density: "compact"
																																				}, {
																																					default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																																						return openBlock(), createBlock(VListItem, {
																																							key: building.id,
																																							class: "hover:bg-rose-200"
																																						}, {
																																							default: withCtx(() => [
																																								createVNode(VListItemSubtitle, null, {
																																									default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																																									_: 2
																																								}, 1024),
																																								createVNode(VListItemSubtitle, null, {
																																									default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																																									_: 2
																																								}, 1024),
																																								createTextVNode(" " + toDisplayString(building.address), 1)
																																							]),
																																							_: 2
																																						}, 1024);
																																					}), 128))]),
																																					_: 1
																																				})];
																																			}),
																																			_: 1
																																		}, _parent, _scopeId));
																																		else return [createVNode(VCol, null, {
																																			default: withCtx(() => [createVNode(VList, {
																																				lines: false,
																																				density: "compact"
																																			}, {
																																				default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																																					return openBlock(), createBlock(VListItem, {
																																						key: building.id,
																																						class: "hover:bg-rose-200"
																																					}, {
																																						default: withCtx(() => [
																																							createVNode(VListItemSubtitle, null, {
																																								default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																																								_: 2
																																							}, 1024),
																																							createVNode(VListItemSubtitle, null, {
																																								default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																																								_: 2
																																							}, 1024),
																																							createTextVNode(" " + toDisplayString(building.address), 1)
																																						]),
																																						_: 2
																																					}, 1024);
																																				}), 128))]),
																																				_: 1
																																			})]),
																																			_: 1
																																		})];
																																	}),
																																	_: 1
																																}, _parent, _scopeId));
																															} else return [createVNode(VRow, null, {
																																default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																																	default: withCtx(() => [createVNode(VTextField, {
																																		modelValue: unref(searchBuildingsLike),
																																		"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																																		onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																																		variant: "outlined",
																																		density: "compact",
																																		class: "text-sm"
																																	}, null, 8, [
																																		"modelValue",
																																		"onUpdate:modelValue",
																																		"onInput"
																																	])]),
																																	_: 1
																																}), createVNode(VCol, { cols: "3" }, {
																																	default: withCtx(() => [createVNode(VBtn, {
																																		onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																																		text: "+ building",
																																		variant: "elevated",
																																		density: "compact",
																																		color: "yellow"
																																	}, null, 8, ["onClick"]), createVNode(VDialog, {
																																		modelValue: unref(showFormBuilding),
																																		"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																																		width: "700"
																																	}, {
																																		default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																																			default: withCtx(() => [createVNode(VCardTitle, null, {
																																				default: withCtx(() => [createTextVNode("Form Building")]),
																																				_: 1
																																			}), createVNode(VCardText, null, {
																																				default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																					default: withCtx(() => [createVNode(VRow, null, {
																																						default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																							default: withCtx(() => [createVNode(VTextField, {
																																								modelValue: unref(formBuilding).address,
																																								"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																								label: "Address",
																																								variant: "outlined",
																																								density: "comfortable"
																																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																							_: 1
																																						}), createVNode(VCol, { cols: "5" }, {
																																							default: withCtx(() => [createVNode(VAutocomplete, {
																																								items: unref(cities),
																																								"item-value": "id",
																																								"item-title": "name",
																																								modelValue: unref(formBuilding).city_id,
																																								"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																								label: "Город",
																																								variant: "outlined",
																																								density: "comfortable",
																																								color: "teal",
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
																																						default: withCtx(() => [
																																							createVNode(VCol, null, {
																																								default: withCtx(() => [createVNode(VTextField, {
																																									modelValue: unref(formBuilding).postcode,
																																									"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																									label: "Postcode",
																																									variant: "outlined",
																																									density: "comfortable"
																																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																								_: 1
																																							}),
																																							createVNode(VCol),
																																							createVNode(VCol, null, {
																																								default: withCtx(() => [createVNode(VBtn, {
																																									onClick: storeBuilding,
																																									text: "store",
																																									variant: "outlined",
																																									density: "comfortable"
																																								})]),
																																								_: 1
																																							})
																																						]),
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
																															}), createVNode(VRow, null, {
																																default: withCtx(() => [createVNode(VCol, null, {
																																	default: withCtx(() => [createVNode(VList, {
																																		lines: false,
																																		density: "compact"
																																	}, {
																																		default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																																			return openBlock(), createBlock(VListItem, {
																																				key: building.id,
																																				class: "hover:bg-rose-200"
																																			}, {
																																				default: withCtx(() => [
																																					createVNode(VListItemSubtitle, null, {
																																						default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																																						_: 2
																																					}, 1024),
																																					createVNode(VListItemSubtitle, null, {
																																						default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																																						_: 2
																																					}, 1024),
																																					createTextVNode(" " + toDisplayString(building.address), 1)
																																				]),
																																				_: 2
																																			}, 1024);
																																		}), 128))]),
																																		_: 1
																																	})]),
																																	_: 1
																																})]),
																																_: 1
																															})];
																														}),
																														_: 1
																													}, _parent, _scopeId));
																													else return [createVNode(VContainer, null, {
																														default: withCtx(() => [createVNode(VRow, null, {
																															default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																																default: withCtx(() => [createVNode(VTextField, {
																																	modelValue: unref(searchBuildingsLike),
																																	"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																																	onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																																	variant: "outlined",
																																	density: "compact",
																																	class: "text-sm"
																																}, null, 8, [
																																	"modelValue",
																																	"onUpdate:modelValue",
																																	"onInput"
																																])]),
																																_: 1
																															}), createVNode(VCol, { cols: "3" }, {
																																default: withCtx(() => [createVNode(VBtn, {
																																	onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																																	text: "+ building",
																																	variant: "elevated",
																																	density: "compact",
																																	color: "yellow"
																																}, null, 8, ["onClick"]), createVNode(VDialog, {
																																	modelValue: unref(showFormBuilding),
																																	"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																																	width: "700"
																																}, {
																																	default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																																		default: withCtx(() => [createVNode(VCardTitle, null, {
																																			default: withCtx(() => [createTextVNode("Form Building")]),
																																			_: 1
																																		}), createVNode(VCardText, null, {
																																			default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																				default: withCtx(() => [createVNode(VRow, null, {
																																					default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																						default: withCtx(() => [createVNode(VTextField, {
																																							modelValue: unref(formBuilding).address,
																																							"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																							label: "Address",
																																							variant: "outlined",
																																							density: "comfortable"
																																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																						_: 1
																																					}), createVNode(VCol, { cols: "5" }, {
																																						default: withCtx(() => [createVNode(VAutocomplete, {
																																							items: unref(cities),
																																							"item-value": "id",
																																							"item-title": "name",
																																							modelValue: unref(formBuilding).city_id,
																																							"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																							label: "Город",
																																							variant: "outlined",
																																							density: "comfortable",
																																							color: "teal",
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
																																					default: withCtx(() => [
																																						createVNode(VCol, null, {
																																							default: withCtx(() => [createVNode(VTextField, {
																																								modelValue: unref(formBuilding).postcode,
																																								"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																								label: "Postcode",
																																								variant: "outlined",
																																								density: "comfortable"
																																							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																							_: 1
																																						}),
																																						createVNode(VCol),
																																						createVNode(VCol, null, {
																																							default: withCtx(() => [createVNode(VBtn, {
																																								onClick: storeBuilding,
																																								text: "store",
																																								variant: "outlined",
																																								density: "comfortable"
																																							})]),
																																							_: 1
																																						})
																																					]),
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
																														}), createVNode(VRow, null, {
																															default: withCtx(() => [createVNode(VCol, null, {
																																default: withCtx(() => [createVNode(VList, {
																																	lines: false,
																																	density: "compact"
																																}, {
																																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																																		return openBlock(), createBlock(VListItem, {
																																			key: building.id,
																																			class: "hover:bg-rose-200"
																																		}, {
																																			default: withCtx(() => [
																																				createVNode(VListItemSubtitle, null, {
																																					default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																																					_: 2
																																				}, 1024),
																																				createVNode(VListItemSubtitle, null, {
																																					default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																																					_: 2
																																				}, 1024),
																																				createTextVNode(" " + toDisplayString(building.address), 1)
																																			]),
																																			_: 2
																																		}, 1024);
																																	}), 128))]),
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
																										} else return [createVNode(VCardTitle, null, {
																											default: withCtx(() => [createTextVNode("Buildings")]),
																											_: 1
																										}), createVNode(VCardText, null, {
																											default: withCtx(() => [createVNode(VContainer, null, {
																												default: withCtx(() => [createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(searchBuildingsLike),
																															"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																															onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																															variant: "outlined",
																															density: "compact",
																															class: "text-sm"
																														}, null, 8, [
																															"modelValue",
																															"onUpdate:modelValue",
																															"onInput"
																														])]),
																														_: 1
																													}), createVNode(VCol, { cols: "3" }, {
																														default: withCtx(() => [createVNode(VBtn, {
																															onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																															text: "+ building",
																															variant: "elevated",
																															density: "compact",
																															color: "yellow"
																														}, null, 8, ["onClick"]), createVNode(VDialog, {
																															modelValue: unref(showFormBuilding),
																															"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																															width: "700"
																														}, {
																															default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																																default: withCtx(() => [createVNode(VCardTitle, null, {
																																	default: withCtx(() => [createTextVNode("Form Building")]),
																																	_: 1
																																}), createVNode(VCardText, null, {
																																	default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																		default: withCtx(() => [createVNode(VRow, null, {
																																			default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																				default: withCtx(() => [createVNode(VTextField, {
																																					modelValue: unref(formBuilding).address,
																																					"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																					label: "Address",
																																					variant: "outlined",
																																					density: "comfortable"
																																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																				_: 1
																																			}), createVNode(VCol, { cols: "5" }, {
																																				default: withCtx(() => [createVNode(VAutocomplete, {
																																					items: unref(cities),
																																					"item-value": "id",
																																					"item-title": "name",
																																					modelValue: unref(formBuilding).city_id,
																																					"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																					label: "Город",
																																					variant: "outlined",
																																					density: "comfortable",
																																					color: "teal",
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
																																			default: withCtx(() => [
																																				createVNode(VCol, null, {
																																					default: withCtx(() => [createVNode(VTextField, {
																																						modelValue: unref(formBuilding).postcode,
																																						"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																						label: "Postcode",
																																						variant: "outlined",
																																						density: "comfortable"
																																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																					_: 1
																																				}),
																																				createVNode(VCol),
																																				createVNode(VCol, null, {
																																					default: withCtx(() => [createVNode(VBtn, {
																																						onClick: storeBuilding,
																																						text: "store",
																																						variant: "outlined",
																																						density: "comfortable"
																																					})]),
																																					_: 1
																																				})
																																			]),
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
																												}), createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VCol, null, {
																														default: withCtx(() => [createVNode(VList, {
																															lines: false,
																															density: "compact"
																														}, {
																															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																																return openBlock(), createBlock(VListItem, {
																																	key: building.id,
																																	class: "hover:bg-rose-200"
																																}, {
																																	default: withCtx(() => [
																																		createVNode(VListItemSubtitle, null, {
																																			default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																																			_: 2
																																		}, 1024),
																																		createVNode(VListItemSubtitle, null, {
																																			default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																																			_: 2
																																		}, 1024),
																																		createTextVNode(" " + toDisplayString(building.address), 1)
																																	]),
																																	_: 2
																																}, 1024);
																															}), 128))]),
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
																								}, _parent, _scopeId));
																								else return [createVNode(VCard, null, {
																									default: withCtx(() => [createVNode(VCardTitle, null, {
																										default: withCtx(() => [createTextVNode("Buildings")]),
																										_: 1
																									}), createVNode(VCardText, null, {
																										default: withCtx(() => [createVNode(VContainer, null, {
																											default: withCtx(() => [createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(searchBuildingsLike),
																														"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																														onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																														variant: "outlined",
																														density: "compact",
																														class: "text-sm"
																													}, null, 8, [
																														"modelValue",
																														"onUpdate:modelValue",
																														"onInput"
																													])]),
																													_: 1
																												}), createVNode(VCol, { cols: "3" }, {
																													default: withCtx(() => [createVNode(VBtn, {
																														onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																														text: "+ building",
																														variant: "elevated",
																														density: "compact",
																														color: "yellow"
																													}, null, 8, ["onClick"]), createVNode(VDialog, {
																														modelValue: unref(showFormBuilding),
																														"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																														width: "700"
																													}, {
																														default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																															default: withCtx(() => [createVNode(VCardTitle, null, {
																																default: withCtx(() => [createTextVNode("Form Building")]),
																																_: 1
																															}), createVNode(VCardText, null, {
																																default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																																	default: withCtx(() => [createVNode(VRow, null, {
																																		default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																			default: withCtx(() => [createVNode(VTextField, {
																																				modelValue: unref(formBuilding).address,
																																				"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																				label: "Address",
																																				variant: "outlined",
																																				density: "comfortable"
																																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																			_: 1
																																		}), createVNode(VCol, { cols: "5" }, {
																																			default: withCtx(() => [createVNode(VAutocomplete, {
																																				items: unref(cities),
																																				"item-value": "id",
																																				"item-title": "name",
																																				modelValue: unref(formBuilding).city_id,
																																				"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																				label: "Город",
																																				variant: "outlined",
																																				density: "comfortable",
																																				color: "teal",
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
																																		default: withCtx(() => [
																																			createVNode(VCol, null, {
																																				default: withCtx(() => [createVNode(VTextField, {
																																					modelValue: unref(formBuilding).postcode,
																																					"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																					label: "Postcode",
																																					variant: "outlined",
																																					density: "comfortable"
																																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																				_: 1
																																			}),
																																			createVNode(VCol),
																																			createVNode(VCol, null, {
																																				default: withCtx(() => [createVNode(VBtn, {
																																					onClick: storeBuilding,
																																					text: "store",
																																					variant: "outlined",
																																					density: "comfortable"
																																				})]),
																																				_: 1
																																			})
																																		]),
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
																											}), createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VCol, null, {
																													default: withCtx(() => [createVNode(VList, {
																														lines: false,
																														density: "compact"
																													}, {
																														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																															return openBlock(), createBlock(VListItem, {
																																key: building.id,
																																class: "hover:bg-rose-200"
																															}, {
																																default: withCtx(() => [
																																	createVNode(VListItemSubtitle, null, {
																																		default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																																		_: 2
																																	}, 1024),
																																	createVNode(VListItemSubtitle, null, {
																																		default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																																		_: 2
																																	}, 1024),
																																	createTextVNode(" " + toDisplayString(building.address), 1)
																																]),
																																_: 2
																															}, 1024);
																														}), 128))]),
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
																								})];
																							}),
																							_: 1
																						}, _parent, _scopeId));
																					} else return [createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VCard, null, {
																							default: withCtx(() => [createVNode(VCardTitle, null, {
																								default: withCtx(() => [createTextVNode("Cities")]),
																								_: 1
																							}), createVNode(VCardText, null, {
																								default: withCtx(() => [createVNode(VList, null, {
																									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(cities), (city) => {
																										return openBlock(), createBlock(VListItem, null, {
																											default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
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
																					}), createVNode(VCol, null, {
																						default: withCtx(() => [createVNode(VCard, null, {
																							default: withCtx(() => [createVNode(VCardTitle, null, {
																								default: withCtx(() => [createTextVNode("Buildings")]),
																								_: 1
																							}), createVNode(VCardText, null, {
																								default: withCtx(() => [createVNode(VContainer, null, {
																									default: withCtx(() => [createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(searchBuildingsLike),
																												"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																												onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																												variant: "outlined",
																												density: "compact",
																												class: "text-sm"
																											}, null, 8, [
																												"modelValue",
																												"onUpdate:modelValue",
																												"onInput"
																											])]),
																											_: 1
																										}), createVNode(VCol, { cols: "3" }, {
																											default: withCtx(() => [createVNode(VBtn, {
																												onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																												text: "+ building",
																												variant: "elevated",
																												density: "compact",
																												color: "yellow"
																											}, null, 8, ["onClick"]), createVNode(VDialog, {
																												modelValue: unref(showFormBuilding),
																												"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																												width: "700"
																											}, {
																												default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																													default: withCtx(() => [createVNode(VCardTitle, null, {
																														default: withCtx(() => [createTextVNode("Form Building")]),
																														_: 1
																													}), createVNode(VCardText, null, {
																														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																															default: withCtx(() => [createVNode(VRow, null, {
																																default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																	default: withCtx(() => [createVNode(VTextField, {
																																		modelValue: unref(formBuilding).address,
																																		"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																		label: "Address",
																																		variant: "outlined",
																																		density: "comfortable"
																																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																	_: 1
																																}), createVNode(VCol, { cols: "5" }, {
																																	default: withCtx(() => [createVNode(VAutocomplete, {
																																		items: unref(cities),
																																		"item-value": "id",
																																		"item-title": "name",
																																		modelValue: unref(formBuilding).city_id,
																																		"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																		label: "Город",
																																		variant: "outlined",
																																		density: "comfortable",
																																		color: "teal",
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
																																default: withCtx(() => [
																																	createVNode(VCol, null, {
																																		default: withCtx(() => [createVNode(VTextField, {
																																			modelValue: unref(formBuilding).postcode,
																																			"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																			label: "Postcode",
																																			variant: "outlined",
																																			density: "comfortable"
																																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																		_: 1
																																	}),
																																	createVNode(VCol),
																																	createVNode(VCol, null, {
																																		default: withCtx(() => [createVNode(VBtn, {
																																			onClick: storeBuilding,
																																			text: "store",
																																			variant: "outlined",
																																			density: "comfortable"
																																		})]),
																																		_: 1
																																	})
																																]),
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
																									}), createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, null, {
																											default: withCtx(() => [createVNode(VList, {
																												lines: false,
																												density: "compact"
																											}, {
																												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																													return openBlock(), createBlock(VListItem, {
																														key: building.id,
																														class: "hover:bg-rose-200"
																													}, {
																														default: withCtx(() => [
																															createVNode(VListItemSubtitle, null, {
																																default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																																_: 2
																															}, 1024),
																															createVNode(VListItemSubtitle, null, {
																																default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																																_: 2
																															}, 1024),
																															createTextVNode(" " + toDisplayString(building.address), 1)
																														]),
																														_: 2
																													}, 1024);
																												}), 128))]),
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
																						})]),
																						_: 1
																					})];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			else return [createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VCard, null, {
																						default: withCtx(() => [createVNode(VCardTitle, null, {
																							default: withCtx(() => [createTextVNode("Cities")]),
																							_: 1
																						}), createVNode(VCardText, null, {
																							default: withCtx(() => [createVNode(VList, null, {
																								default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(cities), (city) => {
																									return openBlock(), createBlock(VListItem, null, {
																										default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
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
																				}), createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VCard, null, {
																						default: withCtx(() => [createVNode(VCardTitle, null, {
																							default: withCtx(() => [createTextVNode("Buildings")]),
																							_: 1
																						}), createVNode(VCardText, null, {
																							default: withCtx(() => [createVNode(VContainer, null, {
																								default: withCtx(() => [createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(searchBuildingsLike),
																											"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																											onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																											variant: "outlined",
																											density: "compact",
																											class: "text-sm"
																										}, null, 8, [
																											"modelValue",
																											"onUpdate:modelValue",
																											"onInput"
																										])]),
																										_: 1
																									}), createVNode(VCol, { cols: "3" }, {
																										default: withCtx(() => [createVNode(VBtn, {
																											onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																											text: "+ building",
																											variant: "elevated",
																											density: "compact",
																											color: "yellow"
																										}, null, 8, ["onClick"]), createVNode(VDialog, {
																											modelValue: unref(showFormBuilding),
																											"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																											width: "700"
																										}, {
																											default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																												default: withCtx(() => [createVNode(VCardTitle, null, {
																													default: withCtx(() => [createTextVNode("Form Building")]),
																													_: 1
																												}), createVNode(VCardText, null, {
																													default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																														default: withCtx(() => [createVNode(VRow, null, {
																															default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																																default: withCtx(() => [createVNode(VTextField, {
																																	modelValue: unref(formBuilding).address,
																																	"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																																	label: "Address",
																																	variant: "outlined",
																																	density: "comfortable"
																																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																_: 1
																															}), createVNode(VCol, { cols: "5" }, {
																																default: withCtx(() => [createVNode(VAutocomplete, {
																																	items: unref(cities),
																																	"item-value": "id",
																																	"item-title": "name",
																																	modelValue: unref(formBuilding).city_id,
																																	"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																																	label: "Город",
																																	variant: "outlined",
																																	density: "comfortable",
																																	color: "teal",
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
																															default: withCtx(() => [
																																createVNode(VCol, null, {
																																	default: withCtx(() => [createVNode(VTextField, {
																																		modelValue: unref(formBuilding).postcode,
																																		"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																		label: "Postcode",
																																		variant: "outlined",
																																		density: "comfortable"
																																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																																	_: 1
																																}),
																																createVNode(VCol),
																																createVNode(VCol, null, {
																																	default: withCtx(() => [createVNode(VBtn, {
																																		onClick: storeBuilding,
																																		text: "store",
																																		variant: "outlined",
																																		density: "comfortable"
																																	})]),
																																	_: 1
																																})
																															]),
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
																								}), createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VList, {
																											lines: false,
																											density: "compact"
																										}, {
																											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																												return openBlock(), createBlock(VListItem, {
																													key: building.id,
																													class: "hover:bg-rose-200"
																												}, {
																													default: withCtx(() => [
																														createVNode(VListItemSubtitle, null, {
																															default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																															_: 2
																														}, 1024),
																														createVNode(VListItemSubtitle, null, {
																															default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																															_: 2
																														}, 1024),
																														createTextVNode(" " + toDisplayString(building.address), 1)
																													]),
																													_: 2
																												}, 1024);
																											}), 128))]),
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
																					})]),
																					_: 1
																				})]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																} else return [createVNode(VTabsWindowItem, { value: "countries" }, {
																	default: withCtx(() => [createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VCard, null, {
																				default: withCtx(() => [createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Countries")]),
																					_: 1
																				}), createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode(VList, null, {
																						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(countries), (country) => {
																							return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																								default: withCtx(() => [createTextVNode(toDisplayString(country.name), 1)]),
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
																		}), createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VCard, null, {
																				default: withCtx(() => [createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Regions")]),
																					_: 1
																				}), createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode(VList, null, {
																						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(regions), (region) => {
																							return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																								default: withCtx(() => [createTextVNode(toDisplayString(region.name), 1)]),
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
																	})]),
																	_: 1
																}), createVNode(VTabsWindowItem, { value: "cities" }, {
																	default: withCtx(() => [createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VCard, null, {
																				default: withCtx(() => [createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Cities")]),
																					_: 1
																				}), createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode(VList, null, {
																						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(cities), (city) => {
																							return openBlock(), createBlock(VListItem, null, {
																								default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
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
																		}), createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VCard, null, {
																				default: withCtx(() => [createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Buildings")]),
																					_: 1
																				}), createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode(VContainer, null, {
																						default: withCtx(() => [createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(searchBuildingsLike),
																									"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																									onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																									variant: "outlined",
																									density: "compact",
																									class: "text-sm"
																								}, null, 8, [
																									"modelValue",
																									"onUpdate:modelValue",
																									"onInput"
																								])]),
																								_: 1
																							}), createVNode(VCol, { cols: "3" }, {
																								default: withCtx(() => [createVNode(VBtn, {
																									onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																									text: "+ building",
																									variant: "elevated",
																									density: "compact",
																									color: "yellow"
																								}, null, 8, ["onClick"]), createVNode(VDialog, {
																									modelValue: unref(showFormBuilding),
																									"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																									width: "700"
																								}, {
																									default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																										default: withCtx(() => [createVNode(VCardTitle, null, {
																											default: withCtx(() => [createTextVNode("Form Building")]),
																											_: 1
																										}), createVNode(VCardText, null, {
																											default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																												default: withCtx(() => [createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(formBuilding).address,
																															"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																															label: "Address",
																															variant: "outlined",
																															density: "comfortable"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																														_: 1
																													}), createVNode(VCol, { cols: "5" }, {
																														default: withCtx(() => [createVNode(VAutocomplete, {
																															items: unref(cities),
																															"item-value": "id",
																															"item-title": "name",
																															modelValue: unref(formBuilding).city_id,
																															"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																															label: "Город",
																															variant: "outlined",
																															density: "comfortable",
																															color: "teal",
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
																													default: withCtx(() => [
																														createVNode(VCol, null, {
																															default: withCtx(() => [createVNode(VTextField, {
																																modelValue: unref(formBuilding).postcode,
																																"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																																label: "Postcode",
																																variant: "outlined",
																																density: "comfortable"
																															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																															_: 1
																														}),
																														createVNode(VCol),
																														createVNode(VCol, null, {
																															default: withCtx(() => [createVNode(VBtn, {
																																onClick: storeBuilding,
																																text: "store",
																																variant: "outlined",
																																density: "comfortable"
																															})]),
																															_: 1
																														})
																													]),
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
																						}), createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, null, {
																								default: withCtx(() => [createVNode(VList, {
																									lines: false,
																									density: "compact"
																								}, {
																									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																										return openBlock(), createBlock(VListItem, {
																											key: building.id,
																											class: "hover:bg-rose-200"
																										}, {
																											default: withCtx(() => [
																												createVNode(VListItemSubtitle, null, {
																													default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																													_: 2
																												}, 1024),
																												createVNode(VListItemSubtitle, null, {
																													default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																													_: 2
																												}, 1024),
																												createTextVNode(" " + toDisplayString(building.address), 1)
																											]),
																											_: 2
																										}, 1024);
																									}), 128))]),
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
														else return [createVNode(VTabsWindow, {
															modelValue: unref(tab),
															"onUpdate:modelValue": ($event) => isRef(tab) ? tab.value = $event : tab = $event
														}, {
															default: withCtx(() => [createVNode(VTabsWindowItem, { value: "countries" }, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VCard, null, {
																			default: withCtx(() => [createVNode(VCardTitle, null, {
																				default: withCtx(() => [createTextVNode("Countries")]),
																				_: 1
																			}), createVNode(VCardText, null, {
																				default: withCtx(() => [createVNode(VList, null, {
																					default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(countries), (country) => {
																						return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																							default: withCtx(() => [createTextVNode(toDisplayString(country.name), 1)]),
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
																	}), createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VCard, null, {
																			default: withCtx(() => [createVNode(VCardTitle, null, {
																				default: withCtx(() => [createTextVNode("Regions")]),
																				_: 1
																			}), createVNode(VCardText, null, {
																				default: withCtx(() => [createVNode(VList, null, {
																					default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(regions), (region) => {
																						return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																							default: withCtx(() => [createTextVNode(toDisplayString(region.name), 1)]),
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
																})]),
																_: 1
															}), createVNode(VTabsWindowItem, { value: "cities" }, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VCard, null, {
																			default: withCtx(() => [createVNode(VCardTitle, null, {
																				default: withCtx(() => [createTextVNode("Cities")]),
																				_: 1
																			}), createVNode(VCardText, null, {
																				default: withCtx(() => [createVNode(VList, null, {
																					default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(cities), (city) => {
																						return openBlock(), createBlock(VListItem, null, {
																							default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
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
																	}), createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VCard, null, {
																			default: withCtx(() => [createVNode(VCardTitle, null, {
																				default: withCtx(() => [createTextVNode("Buildings")]),
																				_: 1
																			}), createVNode(VCardText, null, {
																				default: withCtx(() => [createVNode(VContainer, null, {
																					default: withCtx(() => [createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																							default: withCtx(() => [createVNode(VTextField, {
																								modelValue: unref(searchBuildingsLike),
																								"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																								onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																								variant: "outlined",
																								density: "compact",
																								class: "text-sm"
																							}, null, 8, [
																								"modelValue",
																								"onUpdate:modelValue",
																								"onInput"
																							])]),
																							_: 1
																						}), createVNode(VCol, { cols: "3" }, {
																							default: withCtx(() => [createVNode(VBtn, {
																								onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																								text: "+ building",
																								variant: "elevated",
																								density: "compact",
																								color: "yellow"
																							}, null, 8, ["onClick"]), createVNode(VDialog, {
																								modelValue: unref(showFormBuilding),
																								"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																								width: "700"
																							}, {
																								default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																									default: withCtx(() => [createVNode(VCardTitle, null, {
																										default: withCtx(() => [createTextVNode("Form Building")]),
																										_: 1
																									}), createVNode(VCardText, null, {
																										default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																											default: withCtx(() => [createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formBuilding).address,
																														"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																														label: "Address",
																														variant: "outlined",
																														density: "comfortable"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												}), createVNode(VCol, { cols: "5" }, {
																													default: withCtx(() => [createVNode(VAutocomplete, {
																														items: unref(cities),
																														"item-value": "id",
																														"item-title": "name",
																														modelValue: unref(formBuilding).city_id,
																														"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																														label: "Город",
																														variant: "outlined",
																														density: "comfortable",
																														color: "teal",
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
																												default: withCtx(() => [
																													createVNode(VCol, null, {
																														default: withCtx(() => [createVNode(VTextField, {
																															modelValue: unref(formBuilding).postcode,
																															"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																															label: "Postcode",
																															variant: "outlined",
																															density: "comfortable"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																														_: 1
																													}),
																													createVNode(VCol),
																													createVNode(VCol, null, {
																														default: withCtx(() => [createVNode(VBtn, {
																															onClick: storeBuilding,
																															text: "store",
																															variant: "outlined",
																															density: "comfortable"
																														})]),
																														_: 1
																													})
																												]),
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
																					}), createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VCol, null, {
																							default: withCtx(() => [createVNode(VList, {
																								lines: false,
																								density: "compact"
																							}, {
																								default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																									return openBlock(), createBlock(VListItem, {
																										key: building.id,
																										class: "hover:bg-rose-200"
																									}, {
																										default: withCtx(() => [
																											createVNode(VListItemSubtitle, null, {
																												default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																												_: 2
																											}, 1024),
																											createVNode(VListItemSubtitle, null, {
																												default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																												_: 2
																											}, 1024),
																											createTextVNode(" " + toDisplayString(building.address), 1)
																										]),
																										_: 2
																									}, 1024);
																								}), 128))]),
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
																		})]),
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
											} else return [createVNode(VTabs, {
												modelValue: unref(tab),
												"onUpdate:modelValue": ($event) => isRef(tab) ? tab.value = $event : tab = $event,
												"align-tabs": "center",
												color: "deep-purple-accent-4"
											}, {
												default: withCtx(() => [createVNode(VTab, { value: "countries" }, {
													default: withCtx(() => [createTextVNode("Countries")]),
													_: 1
												}), createVNode(VTab, { value: "cities" }, {
													default: withCtx(() => [createTextVNode("Cities")]),
													_: 1
												})]),
												_: 1
											}, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VTabsWindow, {
													modelValue: unref(tab),
													"onUpdate:modelValue": ($event) => isRef(tab) ? tab.value = $event : tab = $event
												}, {
													default: withCtx(() => [createVNode(VTabsWindowItem, { value: "countries" }, {
														default: withCtx(() => [createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VCard, null, {
																	default: withCtx(() => [createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Countries")]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VList, null, {
																			default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(countries), (country) => {
																				return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																					default: withCtx(() => [createTextVNode(toDisplayString(country.name), 1)]),
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
															}), createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VCard, null, {
																	default: withCtx(() => [createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Regions")]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VList, null, {
																			default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(regions), (region) => {
																				return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																					default: withCtx(() => [createTextVNode(toDisplayString(region.name), 1)]),
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
														})]),
														_: 1
													}), createVNode(VTabsWindowItem, { value: "cities" }, {
														default: withCtx(() => [createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VCard, null, {
																	default: withCtx(() => [createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Cities")]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VList, null, {
																			default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(cities), (city) => {
																				return openBlock(), createBlock(VListItem, null, {
																					default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
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
															}), createVNode(VCol, null, {
																default: withCtx(() => [createVNode(VCard, null, {
																	default: withCtx(() => [createVNode(VCardTitle, null, {
																		default: withCtx(() => [createTextVNode("Buildings")]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VContainer, null, {
																			default: withCtx(() => [createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(searchBuildingsLike),
																						"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																						onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																						variant: "outlined",
																						density: "compact",
																						class: "text-sm"
																					}, null, 8, [
																						"modelValue",
																						"onUpdate:modelValue",
																						"onInput"
																					])]),
																					_: 1
																				}), createVNode(VCol, { cols: "3" }, {
																					default: withCtx(() => [createVNode(VBtn, {
																						onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																						text: "+ building",
																						variant: "elevated",
																						density: "compact",
																						color: "yellow"
																					}, null, 8, ["onClick"]), createVNode(VDialog, {
																						modelValue: unref(showFormBuilding),
																						"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																						width: "700"
																					}, {
																						default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																							default: withCtx(() => [createVNode(VCardTitle, null, {
																								default: withCtx(() => [createTextVNode("Form Building")]),
																								_: 1
																							}), createVNode(VCardText, null, {
																								default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																									default: withCtx(() => [createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formBuilding).address,
																												"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																												label: "Address",
																												variant: "outlined",
																												density: "comfortable"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										}), createVNode(VCol, { cols: "5" }, {
																											default: withCtx(() => [createVNode(VAutocomplete, {
																												items: unref(cities),
																												"item-value": "id",
																												"item-title": "name",
																												modelValue: unref(formBuilding).city_id,
																												"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																												label: "Город",
																												variant: "outlined",
																												density: "comfortable",
																												color: "teal",
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
																										default: withCtx(() => [
																											createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formBuilding).postcode,
																													"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																													label: "Postcode",
																													variant: "outlined",
																													density: "comfortable"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											}),
																											createVNode(VCol),
																											createVNode(VCol, null, {
																												default: withCtx(() => [createVNode(VBtn, {
																													onClick: storeBuilding,
																													text: "store",
																													variant: "outlined",
																													density: "comfortable"
																												})]),
																												_: 1
																											})
																										]),
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
																			}), createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VCol, null, {
																					default: withCtx(() => [createVNode(VList, {
																						lines: false,
																						density: "compact"
																					}, {
																						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																							return openBlock(), createBlock(VListItem, {
																								key: building.id,
																								class: "hover:bg-rose-200"
																							}, {
																								default: withCtx(() => [
																									createVNode(VListItemSubtitle, null, {
																										default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																										_: 2
																									}, 1024),
																									createVNode(VListItemSubtitle, null, {
																										default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																										_: 2
																									}, 1024),
																									createTextVNode(" " + toDisplayString(building.address), 1)
																								]),
																								_: 2
																							}, 1024);
																						}), 128))]),
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
																})]),
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
									else return [createVNode(VCard, null, {
										default: withCtx(() => [createVNode(VTabs, {
											modelValue: unref(tab),
											"onUpdate:modelValue": ($event) => isRef(tab) ? tab.value = $event : tab = $event,
											"align-tabs": "center",
											color: "deep-purple-accent-4"
										}, {
											default: withCtx(() => [createVNode(VTab, { value: "countries" }, {
												default: withCtx(() => [createTextVNode("Countries")]),
												_: 1
											}), createVNode(VTab, { value: "cities" }, {
												default: withCtx(() => [createTextVNode("Cities")]),
												_: 1
											})]),
											_: 1
										}, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VCardText, null, {
											default: withCtx(() => [createVNode(VTabsWindow, {
												modelValue: unref(tab),
												"onUpdate:modelValue": ($event) => isRef(tab) ? tab.value = $event : tab = $event
											}, {
												default: withCtx(() => [createVNode(VTabsWindowItem, { value: "countries" }, {
													default: withCtx(() => [createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VCard, null, {
																default: withCtx(() => [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Countries")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VList, null, {
																		default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(countries), (country) => {
																			return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																				default: withCtx(() => [createTextVNode(toDisplayString(country.name), 1)]),
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
														}), createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VCard, null, {
																default: withCtx(() => [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Regions")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VList, null, {
																		default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(regions), (region) => {
																			return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																				default: withCtx(() => [createTextVNode(toDisplayString(region.name), 1)]),
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
													})]),
													_: 1
												}), createVNode(VTabsWindowItem, { value: "cities" }, {
													default: withCtx(() => [createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VCard, null, {
																default: withCtx(() => [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Cities")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VList, null, {
																		default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(cities), (city) => {
																			return openBlock(), createBlock(VListItem, null, {
																				default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
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
														}), createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VCard, null, {
																default: withCtx(() => [createVNode(VCardTitle, null, {
																	default: withCtx(() => [createTextVNode("Buildings")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VContainer, null, {
																		default: withCtx(() => [createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(searchBuildingsLike),
																					"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																					onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																					variant: "outlined",
																					density: "compact",
																					class: "text-sm"
																				}, null, 8, [
																					"modelValue",
																					"onUpdate:modelValue",
																					"onInput"
																				])]),
																				_: 1
																			}), createVNode(VCol, { cols: "3" }, {
																				default: withCtx(() => [createVNode(VBtn, {
																					onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																					text: "+ building",
																					variant: "elevated",
																					density: "compact",
																					color: "yellow"
																				}, null, 8, ["onClick"]), createVNode(VDialog, {
																					modelValue: unref(showFormBuilding),
																					"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																					width: "700"
																				}, {
																					default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																						default: withCtx(() => [createVNode(VCardTitle, null, {
																							default: withCtx(() => [createTextVNode("Form Building")]),
																							_: 1
																						}), createVNode(VCardText, null, {
																							default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																								default: withCtx(() => [createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formBuilding).address,
																											"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																											label: "Address",
																											variant: "outlined",
																											density: "comfortable"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									}), createVNode(VCol, { cols: "5" }, {
																										default: withCtx(() => [createVNode(VAutocomplete, {
																											items: unref(cities),
																											"item-value": "id",
																											"item-title": "name",
																											modelValue: unref(formBuilding).city_id,
																											"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																											label: "Город",
																											variant: "outlined",
																											density: "comfortable",
																											color: "teal",
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
																									default: withCtx(() => [
																										createVNode(VCol, null, {
																											default: withCtx(() => [createVNode(VTextField, {
																												modelValue: unref(formBuilding).postcode,
																												"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																												label: "Postcode",
																												variant: "outlined",
																												density: "comfortable"
																											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																											_: 1
																										}),
																										createVNode(VCol),
																										createVNode(VCol, null, {
																											default: withCtx(() => [createVNode(VBtn, {
																												onClick: storeBuilding,
																												text: "store",
																												variant: "outlined",
																												density: "comfortable"
																											})]),
																											_: 1
																										})
																									]),
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
																		}), createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VCol, null, {
																				default: withCtx(() => [createVNode(VList, {
																					lines: false,
																					density: "compact"
																				}, {
																					default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																						return openBlock(), createBlock(VListItem, {
																							key: building.id,
																							class: "hover:bg-rose-200"
																						}, {
																							default: withCtx(() => [
																								createVNode(VListItemSubtitle, null, {
																									default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																									_: 2
																								}, 1024),
																								createVNode(VListItemSubtitle, null, {
																									default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																									_: 2
																								}, 1024),
																								createTextVNode(" " + toDisplayString(building.address), 1)
																							]),
																							_: 2
																						}, 1024);
																					}), 128))]),
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
															})]),
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
							else return [createVNode(VCol, null, {
								default: withCtx(() => [createVNode(VCard, null, {
									default: withCtx(() => [createVNode(VTabs, {
										modelValue: unref(tab),
										"onUpdate:modelValue": ($event) => isRef(tab) ? tab.value = $event : tab = $event,
										"align-tabs": "center",
										color: "deep-purple-accent-4"
									}, {
										default: withCtx(() => [createVNode(VTab, { value: "countries" }, {
											default: withCtx(() => [createTextVNode("Countries")]),
											_: 1
										}), createVNode(VTab, { value: "cities" }, {
											default: withCtx(() => [createTextVNode("Cities")]),
											_: 1
										})]),
										_: 1
									}, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VTabsWindow, {
											modelValue: unref(tab),
											"onUpdate:modelValue": ($event) => isRef(tab) ? tab.value = $event : tab = $event
										}, {
											default: withCtx(() => [createVNode(VTabsWindowItem, { value: "countries" }, {
												default: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VCard, null, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Countries")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VList, null, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(countries), (country) => {
																		return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																			default: withCtx(() => [createTextVNode(toDisplayString(country.name), 1)]),
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
													}), createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VCard, null, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Regions")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VList, null, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(regions), (region) => {
																		return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																			default: withCtx(() => [createTextVNode(toDisplayString(region.name), 1)]),
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
												})]),
												_: 1
											}), createVNode(VTabsWindowItem, { value: "cities" }, {
												default: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VCard, null, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Cities")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VList, null, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(cities), (city) => {
																		return openBlock(), createBlock(VListItem, null, {
																			default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
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
													}), createVNode(VCol, null, {
														default: withCtx(() => [createVNode(VCard, null, {
															default: withCtx(() => [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode("Buildings")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VContainer, null, {
																	default: withCtx(() => [createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(searchBuildingsLike),
																				"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																				onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																				variant: "outlined",
																				density: "compact",
																				class: "text-sm"
																			}, null, 8, [
																				"modelValue",
																				"onUpdate:modelValue",
																				"onInput"
																			])]),
																			_: 1
																		}), createVNode(VCol, { cols: "3" }, {
																			default: withCtx(() => [createVNode(VBtn, {
																				onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																				text: "+ building",
																				variant: "elevated",
																				density: "compact",
																				color: "yellow"
																			}, null, 8, ["onClick"]), createVNode(VDialog, {
																				modelValue: unref(showFormBuilding),
																				"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																				width: "700"
																			}, {
																				default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																					default: withCtx(() => [createVNode(VCardTitle, null, {
																						default: withCtx(() => [createTextVNode("Form Building")]),
																						_: 1
																					}), createVNode(VCardText, null, {
																						default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																							default: withCtx(() => [createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formBuilding).address,
																										"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																										label: "Address",
																										variant: "outlined",
																										density: "comfortable"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}), createVNode(VCol, { cols: "5" }, {
																									default: withCtx(() => [createVNode(VAutocomplete, {
																										items: unref(cities),
																										"item-value": "id",
																										"item-title": "name",
																										modelValue: unref(formBuilding).city_id,
																										"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																										label: "Город",
																										variant: "outlined",
																										density: "comfortable",
																										color: "teal",
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
																								default: withCtx(() => [
																									createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formBuilding).postcode,
																											"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																											label: "Postcode",
																											variant: "outlined",
																											density: "comfortable"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									}),
																									createVNode(VCol),
																									createVNode(VCol, null, {
																										default: withCtx(() => [createVNode(VBtn, {
																											onClick: storeBuilding,
																											text: "store",
																											variant: "outlined",
																											density: "comfortable"
																										})]),
																										_: 1
																									})
																								]),
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
																	}), createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(VList, {
																				lines: false,
																				density: "compact"
																			}, {
																				default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																					return openBlock(), createBlock(VListItem, {
																						key: building.id,
																						class: "hover:bg-rose-200"
																					}, {
																						default: withCtx(() => [
																							createVNode(VListItemSubtitle, null, {
																								default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																								_: 2
																							}, 1024),
																							createVNode(VListItemSubtitle, null, {
																								default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																								_: 2
																							}, 1024),
																							createTextVNode(" " + toDisplayString(building.address), 1)
																						]),
																						_: 2
																					}, 1024);
																				}), 128))]),
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
														})]),
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
					}, _parent, _scopeId));
					else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, null, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [createVNode(VTabs, {
									modelValue: unref(tab),
									"onUpdate:modelValue": ($event) => isRef(tab) ? tab.value = $event : tab = $event,
									"align-tabs": "center",
									color: "deep-purple-accent-4"
								}, {
									default: withCtx(() => [createVNode(VTab, { value: "countries" }, {
										default: withCtx(() => [createTextVNode("Countries")]),
										_: 1
									}), createVNode(VTab, { value: "cities" }, {
										default: withCtx(() => [createTextVNode("Cities")]),
										_: 1
									})]),
									_: 1
								}, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VCardText, null, {
									default: withCtx(() => [createVNode(VTabsWindow, {
										modelValue: unref(tab),
										"onUpdate:modelValue": ($event) => isRef(tab) ? tab.value = $event : tab = $event
									}, {
										default: withCtx(() => [createVNode(VTabsWindowItem, { value: "countries" }, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VCard, null, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Countries")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VList, null, {
																default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(countries), (country) => {
																	return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																		default: withCtx(() => [createTextVNode(toDisplayString(country.name), 1)]),
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
												}), createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VCard, null, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Regions")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VList, null, {
																default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(regions), (region) => {
																	return openBlock(), createBlock(VListItem, { class: "text-[12px] font-sans" }, {
																		default: withCtx(() => [createTextVNode(toDisplayString(region.name), 1)]),
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
											})]),
											_: 1
										}), createVNode(VTabsWindowItem, { value: "cities" }, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VCard, null, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Cities")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VList, null, {
																default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(cities), (city) => {
																	return openBlock(), createBlock(VListItem, null, {
																		default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
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
												}), createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VCard, null, {
														default: withCtx(() => [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Buildings")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VContainer, null, {
																default: withCtx(() => [createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(searchBuildingsLike),
																			"onUpdate:modelValue": ($event) => isRef(searchBuildingsLike) ? searchBuildingsLike.value = $event : searchBuildingsLike = $event,
																			onInput: ($event) => indexBuildings(unref(searchBuildingsLike)),
																			variant: "outlined",
																			density: "compact",
																			class: "text-sm"
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"onInput"
																		])]),
																		_: 1
																	}), createVNode(VCol, { cols: "3" }, {
																		default: withCtx(() => [createVNode(VBtn, {
																			onClick: ($event) => isRef(showFormBuilding) ? showFormBuilding.value = !unref(showFormBuilding) : showFormBuilding = !unref(showFormBuilding),
																			text: "+ building",
																			variant: "elevated",
																			density: "compact",
																			color: "yellow"
																		}, null, 8, ["onClick"]), createVNode(VDialog, {
																			modelValue: unref(showFormBuilding),
																			"onUpdate:modelValue": ($event) => isRef(showFormBuilding) ? showFormBuilding.value = $event : showFormBuilding = $event,
																			width: "700"
																		}, {
																			default: withCtx(({ isActive }) => [createVNode(VCard, null, {
																				default: withCtx(() => [createVNode(VCardTitle, null, {
																					default: withCtx(() => [createTextVNode("Form Building")]),
																					_: 1
																				}), createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																						default: withCtx(() => [createVNode(VRow, null, {
																							default: withCtx(() => [createVNode(VCol, { cols: "7" }, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formBuilding).address,
																									"onUpdate:modelValue": ($event) => unref(formBuilding).address = $event,
																									label: "Address",
																									variant: "outlined",
																									density: "comfortable"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							}), createVNode(VCol, { cols: "5" }, {
																								default: withCtx(() => [createVNode(VAutocomplete, {
																									items: unref(cities),
																									"item-value": "id",
																									"item-title": "name",
																									modelValue: unref(formBuilding).city_id,
																									"onUpdate:modelValue": ($event) => unref(formBuilding).city_id = $event,
																									label: "Город",
																									variant: "outlined",
																									density: "comfortable",
																									color: "teal",
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
																							default: withCtx(() => [
																								createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formBuilding).postcode,
																										"onUpdate:modelValue": ($event) => unref(formBuilding).postcode = $event,
																										label: "Postcode",
																										variant: "outlined",
																										density: "comfortable"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}),
																								createVNode(VCol),
																								createVNode(VCol, null, {
																									default: withCtx(() => [createVNode(VBtn, {
																										onClick: storeBuilding,
																										text: "store",
																										variant: "outlined",
																										density: "comfortable"
																									})]),
																									_: 1
																								})
																							]),
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
																}), createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VCol, null, {
																		default: withCtx(() => [createVNode(VList, {
																			lines: false,
																			density: "compact"
																		}, {
																			default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(buildings), (building) => {
																				return openBlock(), createBlock(VListItem, {
																					key: building.id,
																					class: "hover:bg-rose-200"
																				}, {
																					default: withCtx(() => [
																						createVNode(VListItemSubtitle, null, {
																							default: withCtx(() => [createTextVNode(toDisplayString(building.city.name), 1)]),
																							_: 2
																						}, 1024),
																						createVNode(VListItemSubtitle, null, {
																							default: withCtx(() => [createTextVNode(toDisplayString(building.city.region.name), 1)]),
																							_: 2
																						}, 1024),
																						createTextVNode(" " + toDisplayString(building.address), 1)
																					]),
																					_: 2
																				}, 1024);
																			}), 128))]),
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
													})]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Geography.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Geography-2L7ygqE9.js.map