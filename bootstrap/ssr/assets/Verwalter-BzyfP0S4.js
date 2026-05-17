import { C as VRow, D as VDataTable, T as VContainer, U as VTextField, X as VChip, w as VCol } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createTextVNode, createVNode, mergeProps, onMounted, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Ameise/Verwalter.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Verwalter",
	__ssrInlineRender: true,
	props: {
		title: String,
		entities: Object
	},
	setup(__props) {
		const props = __props;
		const fields = ref([]);
		const goods = ref([]);
		const sales = ref([]);
		const filteredEntities = computed(() => {
			const searchLike = searchEntity.value.toLowerCase();
			return props.entities.filter((entity) => entity.name.toLowerCase().includes(searchLike));
		});
		function indexFields() {
			axios.get(route("fields.index")).then(function(response) {
				fields.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		function indexGoods() {
			axios.get(route("goods.index")).then(function(response) {
				goods.value = response.data;
			}).catch(function(error) {
				console.error(error);
			});
		}
		const headerGoods = ref([{
			key: "name",
			title: "Name",
			align: "start",
			sortable: true
		}]);
		const searchEntity = ref("");
		function indexSales() {
			axios.get(route("sales.index")).then(function(response) {
				sales.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		onMounted(() => {
			indexFields();
			indexGoods();
			indexSales();
		});
		useHead({
			title: `Управление торговлей`,
			meta: [{
				name: "description",
				content: `Управление торговлей`
			}]
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { cols: "1" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VRow, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCol, { cols: "9" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`<span class="text-xs"${_scopeId}>Товаров</span>`);
																else return [createVNode("span", { class: "text-xs" }, "Товаров")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCol, { cols: "3" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`${ssrInterpolate(goods.value.length)}`);
																else return [createTextVNode(toDisplayString(goods.value.length), 1)];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VCol, { cols: "9" }, {
														default: withCtx(() => [createVNode("span", { class: "text-xs" }, "Товаров")]),
														_: 1
													}), createVNode(VCol, { cols: "3" }, {
														default: withCtx(() => [createTextVNode(toDisplayString(goods.value.length), 1)]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
													default: withCtx(() => [createVNode("span", { class: "text-xs" }, "Товаров")]),
													_: 1
												}), createVNode(VCol, { cols: "3" }, {
													default: withCtx(() => [createTextVNode(toDisplayString(goods.value.length), 1)]),
													_: 1
												})]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "1" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VRow, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCol, { cols: "9" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`<span class="text-xs"${_scopeId}>Продаж</span>`);
																else return [createVNode("span", { class: "text-xs" }, "Продаж")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCol, { cols: "3" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VChip, { size: "x-small" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(`${ssrInterpolate(sales.value.length)}`);
																		else return [createTextVNode(toDisplayString(sales.value.length), 1)];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VChip, { size: "x-small" }, {
																	default: withCtx(() => [createTextVNode(toDisplayString(sales.value.length), 1)]),
																	_: 1
																})];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VCol, { cols: "9" }, {
														default: withCtx(() => [createVNode("span", { class: "text-xs" }, "Продаж")]),
														_: 1
													}), createVNode(VCol, { cols: "3" }, {
														default: withCtx(() => [createVNode(VChip, { size: "x-small" }, {
															default: withCtx(() => [createTextVNode(toDisplayString(sales.value.length), 1)]),
															_: 1
														})]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
													default: withCtx(() => [createVNode("span", { class: "text-xs" }, "Продаж")]),
													_: 1
												}), createVNode(VCol, { cols: "3" }, {
													default: withCtx(() => [createVNode(VChip, { size: "x-small" }, {
														default: withCtx(() => [createTextVNode(toDisplayString(sales.value.length), 1)]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, { cols: "1" }, {
									default: withCtx(() => [createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
											default: withCtx(() => [createVNode("span", { class: "text-xs" }, "Товаров")]),
											_: 1
										}), createVNode(VCol, { cols: "3" }, {
											default: withCtx(() => [createTextVNode(toDisplayString(goods.value.length), 1)]),
											_: 1
										})]),
										_: 1
									})]),
									_: 1
								}), createVNode(VCol, { cols: "1" }, {
									default: withCtx(() => [createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
											default: withCtx(() => [createVNode("span", { class: "text-xs" }, "Продаж")]),
											_: 1
										}), createVNode(VCol, { cols: "3" }, {
											default: withCtx(() => [createVNode(VChip, { size: "x-small" }, {
												default: withCtx(() => [createTextVNode(toDisplayString(sales.value.length), 1)]),
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
									_push(ssrRenderComponent(VCol, { cols: "3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VRow, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VCol, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VTextField, {
																	modelValue: searchEntity.value,
																	"onUpdate:modelValue": ($event) => searchEntity.value = $event,
																	label: "search",
																	variant: "solo",
																	density: "compact",
																	"hide-details": ""
																}, null, _parent, _scopeId));
																else return [createVNode(VTextField, {
																	modelValue: searchEntity.value,
																	"onUpdate:modelValue": ($event) => searchEntity.value = $event,
																	label: "search",
																	variant: "solo",
																	density: "compact",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: searchEntity.value,
																"onUpdate:modelValue": ($event) => searchEntity.value = $event,
																label: "search",
																variant: "solo",
																density: "compact",
																"hide-details": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VRow, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VCol, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VDataTable, {
																	items: filteredEntities.value,
																	"items-per-page": "100",
																	headers: headerGoods.value,
																	"fixed-header": "",
																	height: "367px",
																	density: "compact",
																	class: "border rounded",
																	hover: ""
																}, null, _parent, _scopeId));
																else return [createVNode(VDataTable, {
																	items: filteredEntities.value,
																	"items-per-page": "100",
																	headers: headerGoods.value,
																	"fixed-header": "",
																	height: "367px",
																	density: "compact",
																	class: "border rounded",
																	hover: ""
																}, null, 8, ["items", "headers"])];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VCol, null, {
															default: withCtx(() => [createVNode(VDataTable, {
																items: filteredEntities.value,
																"items-per-page": "100",
																headers: headerGoods.value,
																"fixed-header": "",
																height: "367px",
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
												default: withCtx(() => [createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: searchEntity.value,
														"onUpdate:modelValue": ($event) => searchEntity.value = $event,
														label: "search",
														variant: "solo",
														density: "compact",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})]),
												_: 1
											}), createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, null, {
													default: withCtx(() => [createVNode(VDataTable, {
														items: filteredEntities.value,
														"items-per-page": "100",
														headers: headerGoods.value,
														"fixed-header": "",
														height: "367px",
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
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "9" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(`<div class="flex flex-row"${_scopeId}><!--[-->`);
												ssrRenderList(fields.value, (field) => {
													_push(`<div class="p-6 border border-slate-800 rounded text-center"${_scopeId}><div class="border-b"${_scopeId}><span${_scopeId}>${ssrInterpolate(field.title)}</span></div><!--[-->`);
													ssrRenderList(field.units, (unit) => {
														_push(`<div class="text-xs"${_scopeId}>`);
														_push(ssrRenderComponent(unref(Link), { href: unref(route)("web.unit.show", unit.id) }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`${ssrInterpolate(unit.name)}`);
																else return [createTextVNode(toDisplayString(unit.name), 1)];
															}),
															_: 2
														}, _parent, _scopeId));
														_push(`</div>`);
													});
													_push(`<!--]--></div>`);
												});
												_push(`<!--]--></div>`);
											} else return [createVNode("div", { class: "flex flex-row" }, [(openBlock(true), createBlock(Fragment, null, renderList(fields.value, (field) => {
												return openBlock(), createBlock("div", { class: "p-6 border border-slate-800 rounded text-center" }, [createVNode("div", { class: "border-b" }, [createVNode("span", null, toDisplayString(field.title), 1)]), (openBlock(true), createBlock(Fragment, null, renderList(field.units, (unit) => {
													return openBlock(), createBlock("div", { class: "text-xs" }, [createVNode(unref(Link), { href: unref(route)("web.unit.show", unit.id) }, {
														default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
														_: 2
													}, 1032, ["href"])]);
												}), 256))]);
											}), 256))])];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, { cols: "3" }, {
									default: withCtx(() => [createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, null, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: searchEntity.value,
												"onUpdate:modelValue": ($event) => searchEntity.value = $event,
												label: "search",
												variant: "solo",
												density: "compact",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})]),
										_: 1
									}), createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, null, {
											default: withCtx(() => [createVNode(VDataTable, {
												items: filteredEntities.value,
												"items-per-page": "100",
												headers: headerGoods.value,
												"fixed-header": "",
												height: "367px",
												density: "compact",
												class: "border rounded",
												hover: ""
											}, null, 8, ["items", "headers"])]),
											_: 1
										})]),
										_: 1
									})]),
									_: 1
								}), createVNode(VCol, { cols: "9" }, {
									default: withCtx(() => [createVNode("div", { class: "flex flex-row" }, [(openBlock(true), createBlock(Fragment, null, renderList(fields.value, (field) => {
										return openBlock(), createBlock("div", { class: "p-6 border border-slate-800 rounded text-center" }, [createVNode("div", { class: "border-b" }, [createVNode("span", null, toDisplayString(field.title), 1)]), (openBlock(true), createBlock(Fragment, null, renderList(field.units, (unit) => {
											return openBlock(), createBlock("div", { class: "text-xs" }, [createVNode(unref(Link), { href: unref(route)("web.unit.show", unit.id) }, {
												default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
												_: 2
											}, 1032, ["href"])]);
										}), 256))]);
									}), 256))])]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, { cols: "1" }, {
							default: withCtx(() => [createVNode(VRow, null, {
								default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
									default: withCtx(() => [createVNode("span", { class: "text-xs" }, "Товаров")]),
									_: 1
								}), createVNode(VCol, { cols: "3" }, {
									default: withCtx(() => [createTextVNode(toDisplayString(goods.value.length), 1)]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						}), createVNode(VCol, { cols: "1" }, {
							default: withCtx(() => [createVNode(VRow, null, {
								default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
									default: withCtx(() => [createVNode("span", { class: "text-xs" }, "Продаж")]),
									_: 1
								}), createVNode(VCol, { cols: "3" }, {
									default: withCtx(() => [createVNode(VChip, { size: "x-small" }, {
										default: withCtx(() => [createTextVNode(toDisplayString(sales.value.length), 1)]),
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
						default: withCtx(() => [createVNode(VCol, { cols: "3" }, {
							default: withCtx(() => [createVNode(VRow, null, {
								default: withCtx(() => [createVNode(VCol, null, {
									default: withCtx(() => [createVNode(VTextField, {
										modelValue: searchEntity.value,
										"onUpdate:modelValue": ($event) => searchEntity.value = $event,
										label: "search",
										variant: "solo",
										density: "compact",
										"hide-details": ""
									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
									_: 1
								})]),
								_: 1
							}), createVNode(VRow, null, {
								default: withCtx(() => [createVNode(VCol, null, {
									default: withCtx(() => [createVNode(VDataTable, {
										items: filteredEntities.value,
										"items-per-page": "100",
										headers: headerGoods.value,
										"fixed-header": "",
										height: "367px",
										density: "compact",
										class: "border rounded",
										hover: ""
									}, null, 8, ["items", "headers"])]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						}), createVNode(VCol, { cols: "9" }, {
							default: withCtx(() => [createVNode("div", { class: "flex flex-row" }, [(openBlock(true), createBlock(Fragment, null, renderList(fields.value, (field) => {
								return openBlock(), createBlock("div", { class: "p-6 border border-slate-800 rounded text-center" }, [createVNode("div", { class: "border-b" }, [createVNode("span", null, toDisplayString(field.title), 1)]), (openBlock(true), createBlock(Fragment, null, renderList(field.units, (unit) => {
									return openBlock(), createBlock("div", { class: "text-xs" }, [createVNode(unref(Link), { href: unref(route)("web.unit.show", unit.id) }, {
										default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
										_: 2
									}, 1032, ["href"])]);
								}), 256))]);
							}), 256))])]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Verwalter.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Verwalter-BzyfP0S4.js.map