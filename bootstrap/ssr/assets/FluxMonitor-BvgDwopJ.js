import { C as VRow, F as VCardText, G as VList, I as VCardTitle, P as VCard, T as VContainer, q as VListItem, w as VCol } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import { Fragment, createBlock, createTextVNode, createVNode, mergeProps, onMounted, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Ameise/FluxMonitor.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "FluxMonitor",
	__ssrInlineRender: true,
	setup(__props) {
		let stages = ref([]);
		function indexStages() {
			axios.get(route("stages.index")).then(function(response) {
				stages.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		onMounted(() => {
			indexStages();
		});
		useHead({
			title: `Монитор текущей работы`,
			meta: [{
				name: "description",
				content: `Монитор текущей работы`
			}]
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
								else return [createVNode(VCol)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										class: "flex flex-row"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(`<!--[-->`);
												ssrRenderList(unref(stages), (stage) => {
													_push(ssrRenderComponent(VCard, {
														variant: "outlined",
														density: "compact"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(ssrRenderComponent(VCardTitle, null, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(`${ssrInterpolate(stage.name)}`);
																		else return [createTextVNode(toDisplayString(stage.name), 1)];
																	}),
																	_: 2
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCardText, null, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VList, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(`<!--[-->`);
																					ssrRenderList(stage.units, (unit) => {
																						_push(ssrRenderComponent(VListItem, { class: "text-xs" }, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) _push(ssrRenderComponent(unref(Link), { href: _ctx.route("web.unit.show", unit.id) }, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(`${ssrInterpolate(unit.name)}`);
																										else return [createTextVNode(toDisplayString(unit.name), 1)];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								else return [createVNode(unref(Link), { href: _ctx.route("web.unit.show", unit.id) }, {
																									default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																									_: 2
																								}, 1032, ["href"])];
																							}),
																							_: 2
																						}, _parent, _scopeId));
																					});
																					_push(`<!--]-->`);
																				} else return [(openBlock(true), createBlock(Fragment, null, renderList(stage.units, (unit) => {
																					return openBlock(), createBlock(VListItem, { class: "text-xs" }, {
																						default: withCtx(() => [createVNode(unref(Link), { href: _ctx.route("web.unit.show", unit.id) }, {
																							default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																							_: 2
																						}, 1032, ["href"])]),
																						_: 2
																					}, 1024);
																				}), 256))];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		else return [createVNode(VList, null, {
																			default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(stage.units, (unit) => {
																				return openBlock(), createBlock(VListItem, { class: "text-xs" }, {
																					default: withCtx(() => [createVNode(unref(Link), { href: _ctx.route("web.unit.show", unit.id) }, {
																						default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																						_: 2
																					}, 1032, ["href"])]),
																					_: 2
																				}, 1024);
																			}), 256))]),
																			_: 2
																		}, 1024)];
																	}),
																	_: 2
																}, _parent, _scopeId));
															} else return [createVNode(VCardTitle, null, {
																default: withCtx(() => [createTextVNode(toDisplayString(stage.name), 1)]),
																_: 2
															}, 1024), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VList, null, {
																	default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(stage.units, (unit) => {
																		return openBlock(), createBlock(VListItem, { class: "text-xs" }, {
																			default: withCtx(() => [createVNode(unref(Link), { href: _ctx.route("web.unit.show", unit.id) }, {
																				default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																				_: 2
																			}, 1032, ["href"])]),
																			_: 2
																		}, 1024);
																	}), 256))]),
																	_: 2
																}, 1024)]),
																_: 2
															}, 1024)];
														}),
														_: 2
													}, _parent, _scopeId));
												});
												_push(`<!--]-->`);
											} else return [(openBlock(true), createBlock(Fragment, null, renderList(unref(stages), (stage) => {
												return openBlock(), createBlock(VCard, {
													variant: "outlined",
													density: "compact"
												}, {
													default: withCtx(() => [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode(toDisplayString(stage.name), 1)]),
														_: 2
													}, 1024), createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VList, null, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(stage.units, (unit) => {
																return openBlock(), createBlock(VListItem, { class: "text-xs" }, {
																	default: withCtx(() => [createVNode(unref(Link), { href: _ctx.route("web.unit.show", unit.id) }, {
																		default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																		_: 2
																	}, 1032, ["href"])]),
																	_: 2
																}, 1024);
															}), 256))]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1024)]),
													_: 2
												}, 1024);
											}), 256))];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
								} else return [createVNode(VCol, {
									cols: "12",
									class: "flex flex-row"
								}, {
									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(stages), (stage) => {
										return openBlock(), createBlock(VCard, {
											variant: "outlined",
											density: "compact"
										}, {
											default: withCtx(() => [createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode(toDisplayString(stage.name), 1)]),
												_: 2
											}, 1024), createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VList, null, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(stage.units, (unit) => {
														return openBlock(), createBlock(VListItem, { class: "text-xs" }, {
															default: withCtx(() => [createVNode(unref(Link), { href: _ctx.route("web.unit.show", unit.id) }, {
																default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
																_: 2
															}, 1032, ["href"])]),
															_: 2
														}, 1024);
													}), 256))]),
													_: 2
												}, 1024)]),
												_: 2
											}, 1024)]),
											_: 2
										}, 1024);
									}), 256))]),
									_: 1
								}), createVNode(VCol)];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol)]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, {
							cols: "12",
							class: "flex flex-row"
						}, {
							default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(stages), (stage) => {
								return openBlock(), createBlock(VCard, {
									variant: "outlined",
									density: "compact"
								}, {
									default: withCtx(() => [createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode(toDisplayString(stage.name), 1)]),
										_: 2
									}, 1024), createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VList, null, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(stage.units, (unit) => {
												return openBlock(), createBlock(VListItem, { class: "text-xs" }, {
													default: withCtx(() => [createVNode(unref(Link), { href: _ctx.route("web.unit.show", unit.id) }, {
														default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
														_: 2
													}, 1032, ["href"])]),
													_: 2
												}, 1024);
											}), 256))]),
											_: 2
										}, 1024)]),
										_: 2
									}, 1024)]),
									_: 2
								}, 1024);
							}), 256))]),
							_: 1
						}), createVNode(VCol)]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/FluxMonitor.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=FluxMonitor-BvgDwopJ.js.map