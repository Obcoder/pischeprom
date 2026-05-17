import { C as VRow, D as VDataTable, F as VCardText, G as VList, I as VCardTitle, P as VCard, T as VContainer, q as VListItem, w as VCol } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import { Link } from "@inertiajs/vue3";
import { Fragment, createBlock, createTextVNode, createVNode, mergeProps, onMounted, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderAttr, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
//#region resources/js/Pages/Ameise/Product.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Product",
	__ssrInlineRender: true,
	props: { product: Object },
	setup(__props) {
		const props = __props;
		const headerComponents = ref([{
			key: "name",
			title: "name",
			align: "start",
			sortable: true
		}]);
		const headerConsumers = [
			{
				key: "unit.name",
				title: "Unit",
				align: "start",
				sortable: true
			},
			{
				title: "Uris",
				key: "unit.uris",
				align: "start"
			},
			{
				title: "Emails",
				key: "unit.emails",
				align: "start"
			},
			{
				title: "Entities",
				key: "unit.entities",
				align: "start"
			},
			{
				title: "Кол-во",
				key: "quantity"
			},
			{
				title: "ед изм",
				key: "measure"
			},
			{
				title: "Nodes",
				key: "unit.buildings",
				align: "start"
			}
		];
		const headerGoods = ref([{
			key: "name",
			title: "Name",
			align: "start",
			sortable: true
		}]);
		const sales = ref([]);
		const fetchSales = async () => {
			sales.value = (await axios.get(route("sales.index"), { params: { product_id: props.product.id } })).data;
		};
		const headerSales = ref([
			{
				key: "date",
				title: "Дата",
				align: "start",
				sortable: true
			},
			{
				key: "entity.name",
				title: "Entity",
				align: "start",
				sortable: true,
				width: "50%"
			},
			{
				key: "total",
				title: "Сумма",
				align: "start",
				sortable: true
			}
		]);
		onMounted(() => {
			fetchSales();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { cols: "3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VCard, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCardTitle, { class: "font-Typingrad text-black bg-indigo" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`${ssrInterpolate(__props.product.rus)}`);
																else return [createTextVNode(toDisplayString(__props.product.rus), 1)];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCardText, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VList, { density: "compact" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(ssrRenderComponent(VListItem, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(`${ssrInterpolate(__props.product.eng)}`);
																					else return [createTextVNode(toDisplayString(__props.product.eng), 1)];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VListItem, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(`${ssrInterpolate(__props.product.zh)}`);
																					else return [createTextVNode(toDisplayString(__props.product.zh), 1)];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VListItem, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(`${ssrInterpolate(__props.product.es)}`);
																					else return [createTextVNode(toDisplayString(__props.product.es), 1)];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																		} else return [
																			createVNode(VListItem, null, {
																				default: withCtx(() => [createTextVNode(toDisplayString(__props.product.eng), 1)]),
																				_: 1
																			}),
																			createVNode(VListItem, null, {
																				default: withCtx(() => [createTextVNode(toDisplayString(__props.product.zh), 1)]),
																				_: 1
																			}),
																			createVNode(VListItem, null, {
																				default: withCtx(() => [createTextVNode(toDisplayString(__props.product.es), 1)]),
																				_: 1
																			})
																		];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VList, { density: "compact" }, {
																	default: withCtx(() => [
																		createVNode(VListItem, null, {
																			default: withCtx(() => [createTextVNode(toDisplayString(__props.product.eng), 1)]),
																			_: 1
																		}),
																		createVNode(VListItem, null, {
																			default: withCtx(() => [createTextVNode(toDisplayString(__props.product.zh), 1)]),
																			_: 1
																		}),
																		createVNode(VListItem, null, {
																			default: withCtx(() => [createTextVNode(toDisplayString(__props.product.es), 1)]),
																			_: 1
																		})
																	]),
																	_: 1
																})];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VCardTitle, { class: "font-Typingrad text-black bg-indigo" }, {
														default: withCtx(() => [createTextVNode(toDisplayString(__props.product.rus), 1)]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VList, { density: "compact" }, {
															default: withCtx(() => [
																createVNode(VListItem, null, {
																	default: withCtx(() => [createTextVNode(toDisplayString(__props.product.eng), 1)]),
																	_: 1
																}),
																createVNode(VListItem, null, {
																	default: withCtx(() => [createTextVNode(toDisplayString(__props.product.zh), 1)]),
																	_: 1
																}),
																createVNode(VListItem, null, {
																	default: withCtx(() => [createTextVNode(toDisplayString(__props.product.es), 1)]),
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
											else return [createVNode(VCard, null, {
												default: withCtx(() => [createVNode(VCardTitle, { class: "font-Typingrad text-black bg-indigo" }, {
													default: withCtx(() => [createTextVNode(toDisplayString(__props.product.rus), 1)]),
													_: 1
												}), createVNode(VCardText, null, {
													default: withCtx(() => [createVNode(VList, { density: "compact" }, {
														default: withCtx(() => [
															createVNode(VListItem, null, {
																default: withCtx(() => [createTextVNode(toDisplayString(__props.product.eng), 1)]),
																_: 1
															}),
															createVNode(VListItem, null, {
																default: withCtx(() => [createTextVNode(toDisplayString(__props.product.zh), 1)]),
																_: 1
															}),
															createVNode(VListItem, null, {
																default: withCtx(() => [createTextVNode(toDisplayString(__props.product.es), 1)]),
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
									_push(ssrRenderComponent(VCol, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VCard, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCardTitle, { class: "bg-cyan-950 text-cyan-100" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Manufacturers`);
																else return [createTextVNode(" Manufacturers")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCardText, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VList, null, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(`<!--[-->`);
																			ssrRenderList(__props.product.manufacturers, (manufacturer) => {
																				_push(ssrRenderComponent(VListItem, null, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(ssrRenderComponent(unref(Link), {
																							href: unref(route)("web.unit.show", manufacturer.id),
																							class: "font-OrelegaOneRegular"
																						}, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(`<span${_scopeId}>${ssrInterpolate(manufacturer.name)}</span>`);
																								else return [createVNode("span", null, toDisplayString(manufacturer.name), 1)];
																							}),
																							_: 2
																						}, _parent, _scopeId));
																						else return [createVNode(unref(Link), {
																							href: unref(route)("web.unit.show", manufacturer.id),
																							class: "font-OrelegaOneRegular"
																						}, {
																							default: withCtx(() => [createVNode("span", null, toDisplayString(manufacturer.name), 1)]),
																							_: 2
																						}, 1032, ["href"])];
																					}),
																					_: 2
																				}, _parent, _scopeId));
																			});
																			_push(`<!--]-->`);
																		} else return [(openBlock(true), createBlock(Fragment, null, renderList(__props.product.manufacturers, (manufacturer) => {
																			return openBlock(), createBlock(VListItem, null, {
																				default: withCtx(() => [createVNode(unref(Link), {
																					href: unref(route)("web.unit.show", manufacturer.id),
																					class: "font-OrelegaOneRegular"
																				}, {
																					default: withCtx(() => [createVNode("span", null, toDisplayString(manufacturer.name), 1)]),
																					_: 2
																				}, 1032, ["href"])]),
																				_: 2
																			}, 1024);
																		}), 256))];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VList, null, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.product.manufacturers, (manufacturer) => {
																		return openBlock(), createBlock(VListItem, null, {
																			default: withCtx(() => [createVNode(unref(Link), {
																				href: unref(route)("web.unit.show", manufacturer.id),
																				class: "font-OrelegaOneRegular"
																			}, {
																				default: withCtx(() => [createVNode("span", null, toDisplayString(manufacturer.name), 1)]),
																				_: 2
																			}, 1032, ["href"])]),
																			_: 2
																		}, 1024);
																	}), 256))]),
																	_: 1
																})];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VCardTitle, { class: "bg-cyan-950 text-cyan-100" }, {
														default: withCtx(() => [createTextVNode(" Manufacturers")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VList, null, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.product.manufacturers, (manufacturer) => {
																return openBlock(), createBlock(VListItem, null, {
																	default: withCtx(() => [createVNode(unref(Link), {
																		href: unref(route)("web.unit.show", manufacturer.id),
																		class: "font-OrelegaOneRegular"
																	}, {
																		default: withCtx(() => [createVNode("span", null, toDisplayString(manufacturer.name), 1)]),
																		_: 2
																	}, 1032, ["href"])]),
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
												default: withCtx(() => [createVNode(VCardTitle, { class: "bg-cyan-950 text-cyan-100" }, {
													default: withCtx(() => [createTextVNode(" Manufacturers")]),
													_: 1
												}), createVNode(VCardText, null, {
													default: withCtx(() => [createVNode(VList, null, {
														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.product.manufacturers, (manufacturer) => {
															return openBlock(), createBlock(VListItem, null, {
																default: withCtx(() => [createVNode(unref(Link), {
																	href: unref(route)("web.unit.show", manufacturer.id),
																	class: "font-OrelegaOneRegular"
																}, {
																	default: withCtx(() => [createVNode("span", null, toDisplayString(manufacturer.name), 1)]),
																	_: 2
																}, 1032, ["href"])]),
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
																if (_push) _push(`Goods`);
																else return [createTextVNode("Goods")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCardText, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VDataTable, {
																	items: __props.product.goods,
																	"items-per-page": "30",
																	headers: headerGoods.value,
																	"fixed-header": "",
																	height: "160px",
																	density: "compact",
																	hover: ""
																}, {
																	"item.name": withCtx(({ item }, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(unref(Link), { href: unref(route)("Ameise.good.show", item.id) }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`${ssrInterpolate(item.name)}`);
																				else return [createTextVNode(toDisplayString(item.name), 1)];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		else return [createVNode(unref(Link), { href: unref(route)("Ameise.good.show", item.id) }, {
																			default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
																			_: 2
																		}, 1032, ["href"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VDataTable, {
																	items: __props.product.goods,
																	"items-per-page": "30",
																	headers: headerGoods.value,
																	"fixed-header": "",
																	height: "160px",
																	density: "compact",
																	hover: ""
																}, {
																	"item.name": withCtx(({ item }) => [createVNode(unref(Link), { href: unref(route)("Ameise.good.show", item.id) }, {
																		default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
																		_: 2
																	}, 1032, ["href"])]),
																	_: 1
																}, 8, ["items", "headers"])];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Goods")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VDataTable, {
															items: __props.product.goods,
															"items-per-page": "30",
															headers: headerGoods.value,
															"fixed-header": "",
															height: "160px",
															density: "compact",
															hover: ""
														}, {
															"item.name": withCtx(({ item }) => [createVNode(unref(Link), { href: unref(route)("Ameise.good.show", item.id) }, {
																default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
																_: 2
															}, 1032, ["href"])]),
															_: 1
														}, 8, ["items", "headers"])]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VCard, null, {
												default: withCtx(() => [createVNode(VCardTitle, null, {
													default: withCtx(() => [createTextVNode("Goods")]),
													_: 1
												}), createVNode(VCardText, null, {
													default: withCtx(() => [createVNode(VDataTable, {
														items: __props.product.goods,
														"items-per-page": "30",
														headers: headerGoods.value,
														"fixed-header": "",
														height: "160px",
														density: "compact",
														hover: ""
													}, {
														"item.name": withCtx(({ item }) => [createVNode(unref(Link), { href: unref(route)("Ameise.good.show", item.id) }, {
															default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
															_: 2
														}, 1032, ["href"])]),
														_: 1
													}, 8, ["items", "headers"])]),
													_: 1
												})]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VCol, { cols: "3" }, {
										default: withCtx(() => [createVNode(VCard, null, {
											default: withCtx(() => [createVNode(VCardTitle, { class: "font-Typingrad text-black bg-indigo" }, {
												default: withCtx(() => [createTextVNode(toDisplayString(__props.product.rus), 1)]),
												_: 1
											}), createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VList, { density: "compact" }, {
													default: withCtx(() => [
														createVNode(VListItem, null, {
															default: withCtx(() => [createTextVNode(toDisplayString(__props.product.eng), 1)]),
															_: 1
														}),
														createVNode(VListItem, null, {
															default: withCtx(() => [createTextVNode(toDisplayString(__props.product.zh), 1)]),
															_: 1
														}),
														createVNode(VListItem, null, {
															default: withCtx(() => [createTextVNode(toDisplayString(__props.product.es), 1)]),
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
									}),
									createVNode(VCol, null, {
										default: withCtx(() => [createVNode(VCard, null, {
											default: withCtx(() => [createVNode(VCardTitle, { class: "bg-cyan-950 text-cyan-100" }, {
												default: withCtx(() => [createTextVNode(" Manufacturers")]),
												_: 1
											}), createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VList, null, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.product.manufacturers, (manufacturer) => {
														return openBlock(), createBlock(VListItem, null, {
															default: withCtx(() => [createVNode(unref(Link), {
																href: unref(route)("web.unit.show", manufacturer.id),
																class: "font-OrelegaOneRegular"
															}, {
																default: withCtx(() => [createVNode("span", null, toDisplayString(manufacturer.name), 1)]),
																_: 2
															}, 1032, ["href"])]),
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
									}),
									createVNode(VCol, null, {
										default: withCtx(() => [createVNode(VCard, null, {
											default: withCtx(() => [createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Goods")]),
												_: 1
											}), createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VDataTable, {
													items: __props.product.goods,
													"items-per-page": "30",
													headers: headerGoods.value,
													"fixed-header": "",
													height: "160px",
													density: "compact",
													hover: ""
												}, {
													"item.name": withCtx(({ item }) => [createVNode(unref(Link), { href: unref(route)("Ameise.good.show", item.id) }, {
														default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
														_: 2
													}, 1032, ["href"])]),
													_: 1
												}, 8, ["items", "headers"])]),
												_: 1
											})]),
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
									_push(ssrRenderComponent(VCol, { cols: "3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VDataTable, {
												items: sales.value,
												"items-per-page": "100",
												headers: headerSales.value,
												"fixed-header": "",
												height: "405px",
												density: "compact",
												hover: "",
												class: "border rounded"
											}, {
												"item.date": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) _push(`<span class="text-xs"${_scopeId}>${ssrInterpolate(item.date)}</span>`);
													else return [createVNode("span", { class: "text-xs" }, toDisplayString(item.date), 1)];
												}),
												"item.entity.name": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) _push(`<a${ssrRenderAttr("href", unref(route)("entities.show", item.entity.id))} class="text-sm"${_scopeId}>${ssrInterpolate(item.entity.name)}</a>`);
													else return [createVNode("a", {
														href: unref(route)("entities.show", item.entity.id),
														class: "text-sm"
													}, toDisplayString(item.entity.name), 9, ["href"])];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VDataTable, {
												items: sales.value,
												"items-per-page": "100",
												headers: headerSales.value,
												"fixed-header": "",
												height: "405px",
												density: "compact",
												hover: "",
												class: "border rounded"
											}, {
												"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.date), 1)]),
												"item.entity.name": withCtx(({ item }) => [createVNode("a", {
													href: unref(route)("entities.show", item.entity.id),
													class: "text-sm"
												}, toDisplayString(item.entity.name), 9, ["href"])]),
												_: 1
											}, 8, ["items", "headers"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "6" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VCard, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCardTitle, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Consumptions`);
																else return [createTextVNode("Consumptions")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCardText, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VDataTable, {
																	items: __props.product.consumers,
																	"items-per-page": "100",
																	headers: headerConsumers,
																	"fixed-header": "",
																	height: "556px",
																	density: "compact",
																	hover: ""
																}, {
																	"item.unit.name": withCtx(({ item }, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(unref(Link), {
																			href: unref(route)("web.unit.show", item.unit.id),
																			class: "text-xs"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`${ssrInterpolate(item.unit.name)}`);
																				else return [createTextVNode(toDisplayString(item.unit.name), 1)];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		else return [createVNode(unref(Link), {
																			href: unref(route)("web.unit.show", item.unit.id),
																			class: "text-xs"
																		}, {
																			default: withCtx(() => [createTextVNode(toDisplayString(item.unit.name), 1)]),
																			_: 2
																		}, 1032, ["href"])];
																	}),
																	"item.unit.uris": withCtx(({ item }, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(`<!--[-->`);
																			ssrRenderList(item.unit.uris, (uri) => {
																				_push(`<div class="text-[9px] font-sans"${_scopeId}>${ssrInterpolate(uri.address)}</div>`);
																			});
																			_push(`<!--]-->`);
																		} else return [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.uris, (uri) => {
																			return openBlock(), createBlock("div", { class: "text-[9px] font-sans" }, toDisplayString(uri.address), 1);
																		}), 256))];
																	}),
																	"item.measure": withCtx(({ item }, _push, _parent, _scopeId) => {
																		if (_push) _push(`<span${_scopeId}>${ssrInterpolate(item.measure.name)}</span>`);
																		else return [createVNode("span", null, toDisplayString(item.measure.name), 1)];
																	}),
																	"item.unit.emails": withCtx(({ item }, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(`<!--[-->`);
																			ssrRenderList(item.unit.emails, (email) => {
																				_push(`<div${_scopeId}>${ssrInterpolate(email.address)}</div>`);
																			});
																			_push(`<!--]-->`);
																		} else return [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.emails, (email) => {
																			return openBlock(), createBlock("div", null, toDisplayString(email.address), 1);
																		}), 256))];
																	}),
																	"item.unit.entities": withCtx(({ item }, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(`<!--[-->`);
																			ssrRenderList(item.unit.entities, (entity) => {
																				_push(`<div${_scopeId}>${ssrInterpolate(entity.name)}</div>`);
																			});
																			_push(`<!--]-->`);
																		} else return [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.entities, (entity) => {
																			return openBlock(), createBlock("div", null, toDisplayString(entity.name), 1);
																		}), 256))];
																	}),
																	"item.unit.buildings": withCtx(({ item }, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(`<!--[-->`);
																			ssrRenderList(item.unit.buildings, (building) => {
																				_push(`<div${_scopeId}><div${_scopeId}>${ssrInterpolate(building.city.name)}</div><div${_scopeId}>${ssrInterpolate(building.address)}</div></div>`);
																			});
																			_push(`<!--]-->`);
																		} else return [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.buildings, (building) => {
																			return openBlock(), createBlock("div", null, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
																		}), 256))];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VDataTable, {
																	items: __props.product.consumers,
																	"items-per-page": "100",
																	headers: headerConsumers,
																	"fixed-header": "",
																	height: "556px",
																	density: "compact",
																	hover: ""
																}, {
																	"item.unit.name": withCtx(({ item }) => [createVNode(unref(Link), {
																		href: unref(route)("web.unit.show", item.unit.id),
																		class: "text-xs"
																	}, {
																		default: withCtx(() => [createTextVNode(toDisplayString(item.unit.name), 1)]),
																		_: 2
																	}, 1032, ["href"])]),
																	"item.unit.uris": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.uris, (uri) => {
																		return openBlock(), createBlock("div", { class: "text-[9px] font-sans" }, toDisplayString(uri.address), 1);
																	}), 256))]),
																	"item.measure": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.measure.name), 1)]),
																	"item.unit.emails": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.emails, (email) => {
																		return openBlock(), createBlock("div", null, toDisplayString(email.address), 1);
																	}), 256))]),
																	"item.unit.entities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.entities, (entity) => {
																		return openBlock(), createBlock("div", null, toDisplayString(entity.name), 1);
																	}), 256))]),
																	"item.unit.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.buildings, (building) => {
																		return openBlock(), createBlock("div", null, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
																	}), 256))]),
																	_: 1
																}, 8, ["items"])];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Consumptions")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VDataTable, {
															items: __props.product.consumers,
															"items-per-page": "100",
															headers: headerConsumers,
															"fixed-header": "",
															height: "556px",
															density: "compact",
															hover: ""
														}, {
															"item.unit.name": withCtx(({ item }) => [createVNode(unref(Link), {
																href: unref(route)("web.unit.show", item.unit.id),
																class: "text-xs"
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(item.unit.name), 1)]),
																_: 2
															}, 1032, ["href"])]),
															"item.unit.uris": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.uris, (uri) => {
																return openBlock(), createBlock("div", { class: "text-[9px] font-sans" }, toDisplayString(uri.address), 1);
															}), 256))]),
															"item.measure": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.measure.name), 1)]),
															"item.unit.emails": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.emails, (email) => {
																return openBlock(), createBlock("div", null, toDisplayString(email.address), 1);
															}), 256))]),
															"item.unit.entities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.entities, (entity) => {
																return openBlock(), createBlock("div", null, toDisplayString(entity.name), 1);
															}), 256))]),
															"item.unit.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.buildings, (building) => {
																return openBlock(), createBlock("div", null, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
															}), 256))]),
															_: 1
														}, 8, ["items"])]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VCard, null, {
												default: withCtx(() => [createVNode(VCardTitle, null, {
													default: withCtx(() => [createTextVNode("Consumptions")]),
													_: 1
												}), createVNode(VCardText, null, {
													default: withCtx(() => [createVNode(VDataTable, {
														items: __props.product.consumers,
														"items-per-page": "100",
														headers: headerConsumers,
														"fixed-header": "",
														height: "556px",
														density: "compact",
														hover: ""
													}, {
														"item.unit.name": withCtx(({ item }) => [createVNode(unref(Link), {
															href: unref(route)("web.unit.show", item.unit.id),
															class: "text-xs"
														}, {
															default: withCtx(() => [createTextVNode(toDisplayString(item.unit.name), 1)]),
															_: 2
														}, 1032, ["href"])]),
														"item.unit.uris": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.uris, (uri) => {
															return openBlock(), createBlock("div", { class: "text-[9px] font-sans" }, toDisplayString(uri.address), 1);
														}), 256))]),
														"item.measure": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.measure.name), 1)]),
														"item.unit.emails": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.emails, (email) => {
															return openBlock(), createBlock("div", null, toDisplayString(email.address), 1);
														}), 256))]),
														"item.unit.entities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.entities, (entity) => {
															return openBlock(), createBlock("div", null, toDisplayString(entity.name), 1);
														}), 256))]),
														"item.unit.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.buildings, (building) => {
															return openBlock(), createBlock("div", null, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
														}), 256))]),
														_: 1
													}, 8, ["items"])]),
													_: 1
												})]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VDataTable, {
												items: __props.product.components,
												"items-per-page": "24",
												headers: headerComponents.value,
												"fixed-header": "",
												height: "306px",
												hover: "",
												density: "compact",
												class: "border rounded"
											}, null, _parent, _scopeId));
											else return [createVNode(VDataTable, {
												items: __props.product.components,
												"items-per-page": "24",
												headers: headerComponents.value,
												"fixed-header": "",
												height: "306px",
												hover: "",
												density: "compact",
												class: "border rounded"
											}, null, 8, ["items", "headers"])];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VCol, { cols: "3" }, {
										default: withCtx(() => [createVNode(VDataTable, {
											items: sales.value,
											"items-per-page": "100",
											headers: headerSales.value,
											"fixed-header": "",
											height: "405px",
											density: "compact",
											hover: "",
											class: "border rounded"
										}, {
											"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.date), 1)]),
											"item.entity.name": withCtx(({ item }) => [createVNode("a", {
												href: unref(route)("entities.show", item.entity.id),
												class: "text-sm"
											}, toDisplayString(item.entity.name), 9, ["href"])]),
											_: 1
										}, 8, ["items", "headers"])]),
										_: 1
									}),
									createVNode(VCol, { cols: "6" }, {
										default: withCtx(() => [createVNode(VCard, null, {
											default: withCtx(() => [createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Consumptions")]),
												_: 1
											}), createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VDataTable, {
													items: __props.product.consumers,
													"items-per-page": "100",
													headers: headerConsumers,
													"fixed-header": "",
													height: "556px",
													density: "compact",
													hover: ""
												}, {
													"item.unit.name": withCtx(({ item }) => [createVNode(unref(Link), {
														href: unref(route)("web.unit.show", item.unit.id),
														class: "text-xs"
													}, {
														default: withCtx(() => [createTextVNode(toDisplayString(item.unit.name), 1)]),
														_: 2
													}, 1032, ["href"])]),
													"item.unit.uris": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.uris, (uri) => {
														return openBlock(), createBlock("div", { class: "text-[9px] font-sans" }, toDisplayString(uri.address), 1);
													}), 256))]),
													"item.measure": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.measure.name), 1)]),
													"item.unit.emails": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.emails, (email) => {
														return openBlock(), createBlock("div", null, toDisplayString(email.address), 1);
													}), 256))]),
													"item.unit.entities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.entities, (entity) => {
														return openBlock(), createBlock("div", null, toDisplayString(entity.name), 1);
													}), 256))]),
													"item.unit.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.buildings, (building) => {
														return openBlock(), createBlock("div", null, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
													}), 256))]),
													_: 1
												}, 8, ["items"])]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCol, null, {
										default: withCtx(() => [createVNode(VDataTable, {
											items: __props.product.components,
											"items-per-page": "24",
											headers: headerComponents.value,
											"fixed-header": "",
											height: "306px",
											hover: "",
											density: "compact",
											class: "border rounded"
										}, null, 8, ["items", "headers"])]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol, { cols: "3" }, {
								default: withCtx(() => [createVNode(VCard, null, {
									default: withCtx(() => [createVNode(VCardTitle, { class: "font-Typingrad text-black bg-indigo" }, {
										default: withCtx(() => [createTextVNode(toDisplayString(__props.product.rus), 1)]),
										_: 1
									}), createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VList, { density: "compact" }, {
											default: withCtx(() => [
												createVNode(VListItem, null, {
													default: withCtx(() => [createTextVNode(toDisplayString(__props.product.eng), 1)]),
													_: 1
												}),
												createVNode(VListItem, null, {
													default: withCtx(() => [createTextVNode(toDisplayString(__props.product.zh), 1)]),
													_: 1
												}),
												createVNode(VListItem, null, {
													default: withCtx(() => [createTextVNode(toDisplayString(__props.product.es), 1)]),
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
							}),
							createVNode(VCol, null, {
								default: withCtx(() => [createVNode(VCard, null, {
									default: withCtx(() => [createVNode(VCardTitle, { class: "bg-cyan-950 text-cyan-100" }, {
										default: withCtx(() => [createTextVNode(" Manufacturers")]),
										_: 1
									}), createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VList, null, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.product.manufacturers, (manufacturer) => {
												return openBlock(), createBlock(VListItem, null, {
													default: withCtx(() => [createVNode(unref(Link), {
														href: unref(route)("web.unit.show", manufacturer.id),
														class: "font-OrelegaOneRegular"
													}, {
														default: withCtx(() => [createVNode("span", null, toDisplayString(manufacturer.name), 1)]),
														_: 2
													}, 1032, ["href"])]),
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
							}),
							createVNode(VCol, null, {
								default: withCtx(() => [createVNode(VCard, null, {
									default: withCtx(() => [createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Goods")]),
										_: 1
									}), createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VDataTable, {
											items: __props.product.goods,
											"items-per-page": "30",
											headers: headerGoods.value,
											"fixed-header": "",
											height: "160px",
											density: "compact",
											hover: ""
										}, {
											"item.name": withCtx(({ item }) => [createVNode(unref(Link), { href: unref(route)("Ameise.good.show", item.id) }, {
												default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
												_: 2
											}, 1032, ["href"])]),
											_: 1
										}, 8, ["items", "headers"])]),
										_: 1
									})]),
									_: 1
								})]),
								_: 1
							})
						]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol, { cols: "3" }, {
								default: withCtx(() => [createVNode(VDataTable, {
									items: sales.value,
									"items-per-page": "100",
									headers: headerSales.value,
									"fixed-header": "",
									height: "405px",
									density: "compact",
									hover: "",
									class: "border rounded"
								}, {
									"item.date": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.date), 1)]),
									"item.entity.name": withCtx(({ item }) => [createVNode("a", {
										href: unref(route)("entities.show", item.entity.id),
										class: "text-sm"
									}, toDisplayString(item.entity.name), 9, ["href"])]),
									_: 1
								}, 8, ["items", "headers"])]),
								_: 1
							}),
							createVNode(VCol, { cols: "6" }, {
								default: withCtx(() => [createVNode(VCard, null, {
									default: withCtx(() => [createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Consumptions")]),
										_: 1
									}), createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VDataTable, {
											items: __props.product.consumers,
											"items-per-page": "100",
											headers: headerConsumers,
											"fixed-header": "",
											height: "556px",
											density: "compact",
											hover: ""
										}, {
											"item.unit.name": withCtx(({ item }) => [createVNode(unref(Link), {
												href: unref(route)("web.unit.show", item.unit.id),
												class: "text-xs"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(item.unit.name), 1)]),
												_: 2
											}, 1032, ["href"])]),
											"item.unit.uris": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.uris, (uri) => {
												return openBlock(), createBlock("div", { class: "text-[9px] font-sans" }, toDisplayString(uri.address), 1);
											}), 256))]),
											"item.measure": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.measure.name), 1)]),
											"item.unit.emails": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.emails, (email) => {
												return openBlock(), createBlock("div", null, toDisplayString(email.address), 1);
											}), 256))]),
											"item.unit.entities": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.entities, (entity) => {
												return openBlock(), createBlock("div", null, toDisplayString(entity.name), 1);
											}), 256))]),
											"item.unit.buildings": withCtx(({ item }) => [(openBlock(true), createBlock(Fragment, null, renderList(item.unit.buildings, (building) => {
												return openBlock(), createBlock("div", null, [createVNode("div", null, toDisplayString(building.city.name), 1), createVNode("div", null, toDisplayString(building.address), 1)]);
											}), 256))]),
											_: 1
										}, 8, ["items"])]),
										_: 1
									})]),
									_: 1
								})]),
								_: 1
							}),
							createVNode(VCol, null, {
								default: withCtx(() => [createVNode(VDataTable, {
									items: __props.product.components,
									"items-per-page": "24",
									headers: headerComponents.value,
									"fixed-header": "",
									height: "306px",
									hover: "",
									density: "compact",
									class: "border rounded"
								}, null, 8, ["items", "headers"])]),
								_: 1
							})
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Product.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Product-C57FayuB.js.map