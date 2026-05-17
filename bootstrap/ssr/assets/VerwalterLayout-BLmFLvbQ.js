import { G as VList, f as VNavigationDrawer, m as VLayout, nt as VAppBarNavIcon, ot as VIcon, p as VMain, q as VListItem, rt as VBtn, st as VAppBar, tt as VAppBarTitle } from "../ssr.js";
import { Link } from "@inertiajs/vue3";
import { createTextVNode, createVNode, mergeProps, renderSlot, unref, useSSRContext, withCtx } from "vue";
import { route } from "ziggy-js";
import { ssrRenderComponent, ssrRenderSlot } from "vue/server-renderer";
//#region resources/js/Layouts/VerwalterLayout.vue
var _sfc_main = {
	__name: "VerwalterLayout",
	__ssrInlineRender: true,
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VLayout, mergeProps({ class: "rounded rounded-md" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VNavigationDrawer, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VList, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VListItem, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(unref(Link), { href: "https://пищепром-сервер.рф/" }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` Пищепром-Сервер `);
															else return [createTextVNode(" Пищепром-Сервер ")];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(unref(Link), { href: "https://пищепром-сервер.рф/" }, {
														default: withCtx(() => [createTextVNode(" Пищепром-Сервер ")]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VListItem, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(unref(Link), { href: unref(route)("Ameise.checks") }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(`Закупка`);
															else return [createTextVNode("Закупка")];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(unref(Link), { href: unref(route)("Ameise.checks") }, {
														default: withCtx(() => [createTextVNode("Закупка")]),
														_: 1
													}, 8, ["href"])];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VListItem, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(unref(Link), { href: unref(route)("Ameise.contactsCentre") }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(`Contacts centre`);
															else return [createTextVNode("Contacts centre")];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(unref(Link), { href: unref(route)("Ameise.contactsCentre") }, {
														default: withCtx(() => [createTextVNode("Contacts centre")]),
														_: 1
													}, 8, ["href"])];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VListItem, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(unref(Link), { href: unref(route)("Ameise/avito") }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(`Avito`);
															else return [createTextVNode("Avito")];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(unref(Link), { href: unref(route)("Ameise/avito") }, {
														default: withCtx(() => [createTextVNode("Avito")]),
														_: 1
													}, 8, ["href"])];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VListItem, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(unref(Link), {
														href: unref(route)("ameise.telegrambot"),
														color: "blue-darken-1"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(`Telegram`);
															else return [createTextVNode("Telegram")];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(unref(Link), {
														href: unref(route)("ameise.telegrambot"),
														color: "blue-darken-1"
													}, {
														default: withCtx(() => [createTextVNode("Telegram")]),
														_: 1
													}, 8, ["href"])];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VListItem, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(unref(Link), { href: unref(route)("Ameise.yandex") }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` Yandex `);
															else return [createTextVNode(" Yandex ")];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(unref(Link), { href: unref(route)("Ameise.yandex") }, {
														default: withCtx(() => [createTextVNode(" Yandex ")]),
														_: 1
													}, 8, ["href"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VListItem, null, {
												default: withCtx(() => [createVNode(unref(Link), { href: "https://пищепром-сервер.рф/" }, {
													default: withCtx(() => [createTextVNode(" Пищепром-Сервер ")]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VListItem, null, {
												default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.checks") }, {
													default: withCtx(() => [createTextVNode("Закупка")]),
													_: 1
												}, 8, ["href"])]),
												_: 1
											}),
											createVNode(VListItem, null, {
												default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.contactsCentre") }, {
													default: withCtx(() => [createTextVNode("Contacts centre")]),
													_: 1
												}, 8, ["href"])]),
												_: 1
											}),
											createVNode(VListItem, null, {
												default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise/avito") }, {
													default: withCtx(() => [createTextVNode("Avito")]),
													_: 1
												}, 8, ["href"])]),
												_: 1
											}),
											createVNode(VListItem, null, {
												default: withCtx(() => [createVNode(unref(Link), {
													href: unref(route)("ameise.telegrambot"),
													color: "blue-darken-1"
												}, {
													default: withCtx(() => [createTextVNode("Telegram")]),
													_: 1
												}, 8, ["href"])]),
												_: 1
											}),
											createVNode(VListItem, null, {
												default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.yandex") }, {
													default: withCtx(() => [createTextVNode(" Yandex ")]),
													_: 1
												}, 8, ["href"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VList, null, {
									default: withCtx(() => [
										createVNode(VListItem, null, {
											default: withCtx(() => [createVNode(unref(Link), { href: "https://пищепром-сервер.рф/" }, {
												default: withCtx(() => [createTextVNode(" Пищепром-Сервер ")]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VListItem, null, {
											default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.checks") }, {
												default: withCtx(() => [createTextVNode("Закупка")]),
												_: 1
											}, 8, ["href"])]),
											_: 1
										}),
										createVNode(VListItem, null, {
											default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.contactsCentre") }, {
												default: withCtx(() => [createTextVNode("Contacts centre")]),
												_: 1
											}, 8, ["href"])]),
											_: 1
										}),
										createVNode(VListItem, null, {
											default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise/avito") }, {
												default: withCtx(() => [createTextVNode("Avito")]),
												_: 1
											}, 8, ["href"])]),
											_: 1
										}),
										createVNode(VListItem, null, {
											default: withCtx(() => [createVNode(unref(Link), {
												href: unref(route)("ameise.telegrambot"),
												color: "blue-darken-1"
											}, {
												default: withCtx(() => [createTextVNode("Telegram")]),
												_: 1
											}, 8, ["href"])]),
											_: 1
										}),
										createVNode(VListItem, null, {
											default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.yandex") }, {
												default: withCtx(() => [createTextVNode(" Yandex ")]),
												_: 1
											}, 8, ["href"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VAppBar, {
							elevation: 2,
							rounded: "",
							density: "compact",
							"scroll-behavior": "fade-image elevate",
							image: "https://picsum.photos/1920/1080?random"
						}, {
							prepend: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VAppBarNavIcon, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(unref(Link), { href: unref(route)("Ameise") }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VIcon, {
													icon: "mdi-halloween",
													size: "large"
												}, null, _parent, _scopeId));
												else return [createVNode(VIcon, {
													icon: "mdi-halloween",
													size: "large"
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(unref(Link), { href: unref(route)("Ameise") }, {
											default: withCtx(() => [createVNode(VIcon, {
												icon: "mdi-halloween",
												size: "large"
											})]),
											_: 1
										}, 8, ["href"])];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VAppBarNavIcon, null, {
									default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise") }, {
										default: withCtx(() => [createVNode(VIcon, {
											icon: "mdi-halloween",
											size: "large"
										})]),
										_: 1
									}, 8, ["href"])]),
									_: 1
								})];
							}),
							append: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VBtn, { icon: "mdi-heart" }, null, _parent, _scopeId));
									_push(ssrRenderComponent(VBtn, { icon: "mdi-magnify" }, null, _parent, _scopeId));
									_push(ssrRenderComponent(VBtn, { icon: "mdi-dots-vertical" }, null, _parent, _scopeId));
									_push(ssrRenderComponent(VIcon, {
										icon: "mdi-barn",
										size: "x-small"
									}, null, _parent, _scopeId));
								} else return [
									createVNode(VBtn, { icon: "mdi-heart" }),
									createVNode(VBtn, { icon: "mdi-magnify" }),
									createVNode(VBtn, { icon: "mdi-dots-vertical" }),
									createVNode(VIcon, {
										icon: "mdi-barn",
										size: "x-small"
									})
								];
							}),
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VAppBarTitle, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(unref(Link), { href: unref(route)("Ameise.großbuch") }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VIcon, {
															icon: "mdi-access-point",
															size: "x-small"
														}, null, _parent, _scopeId));
														_push(`<span class="ml-1 text-xs"${_scopeId}>Großbuch</span>`);
													} else return [createVNode(VIcon, {
														icon: "mdi-access-point",
														size: "x-small"
													}), createVNode("span", { class: "ml-1 text-xs" }, "Großbuch")];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(unref(Link), { href: unref(route)("Ameise.großbuch") }, {
												default: withCtx(() => [createVNode(VIcon, {
													icon: "mdi-access-point",
													size: "x-small"
												}), createVNode("span", { class: "ml-1 text-xs" }, "Großbuch")]),
												_: 1
											}, 8, ["href"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VAppBarTitle, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(unref(Link), { href: unref(route)("Ameise.fluxmonitor") }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`<span${_scopeId}>M</span>`);
													else return [createVNode("span", null, "M")];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(unref(Link), { href: unref(route)("Ameise.fluxmonitor") }, {
												default: withCtx(() => [createVNode("span", null, "M")]),
												_: 1
											}, 8, ["href"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VAppBarTitle, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(unref(Link), { href: unref(route)("ameise.workboard") }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`<span${_scopeId}>WorkBoard</span>`);
													else return [createVNode("span", null, "WorkBoard")];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(unref(Link), { href: unref(route)("ameise.workboard") }, {
												default: withCtx(() => [createVNode("span", null, "WorkBoard")]),
												_: 1
											}, 8, ["href"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VAppBarTitle, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(unref(Link), { href: unref(route)("Ameise.botany") }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`<span${_scopeId}>Botany</span>`);
													else return [createVNode("span", null, "Botany")];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(unref(Link), { href: unref(route)("Ameise.botany") }, {
												default: withCtx(() => [createVNode("span", null, "Botany")]),
												_: 1
											}, 8, ["href"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VAppBarTitle, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(unref(Link), { href: unref(route)("Ameise.perfume") }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VIcon, {
															icon: "mdi-scent",
															size: "small",
															class: "mx-2"
														}, null, _parent, _scopeId));
														_push(`<span${_scopeId}>Perfume</span>`);
													} else return [createVNode(VIcon, {
														icon: "mdi-scent",
														size: "small",
														class: "mx-2"
													}), createVNode("span", null, "Perfume")];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(unref(Link), { href: unref(route)("Ameise.perfume") }, {
												default: withCtx(() => [createVNode(VIcon, {
													icon: "mdi-scent",
													size: "small",
													class: "mx-2"
												}), createVNode("span", null, "Perfume")]),
												_: 1
											}, 8, ["href"])];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VAppBarTitle, null, {
										default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.großbuch") }, {
											default: withCtx(() => [createVNode(VIcon, {
												icon: "mdi-access-point",
												size: "x-small"
											}), createVNode("span", { class: "ml-1 text-xs" }, "Großbuch")]),
											_: 1
										}, 8, ["href"])]),
										_: 1
									}),
									createVNode(VAppBarTitle, null, {
										default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.fluxmonitor") }, {
											default: withCtx(() => [createVNode("span", null, "M")]),
											_: 1
										}, 8, ["href"])]),
										_: 1
									}),
									createVNode(VAppBarTitle, null, {
										default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("ameise.workboard") }, {
											default: withCtx(() => [createVNode("span", null, "WorkBoard")]),
											_: 1
										}, 8, ["href"])]),
										_: 1
									}),
									createVNode(VAppBarTitle, null, {
										default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.botany") }, {
											default: withCtx(() => [createVNode("span", null, "Botany")]),
											_: 1
										}, 8, ["href"])]),
										_: 1
									}),
									createVNode(VAppBarTitle, null, {
										default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.perfume") }, {
											default: withCtx(() => [createVNode(VIcon, {
												icon: "mdi-scent",
												size: "small",
												class: "mx-2"
											}), createVNode("span", null, "Perfume")]),
											_: 1
										}, 8, ["href"])]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VMain, {
							class: "d-flex align-center justify-center",
							style: { "min-height": "300px" }
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent, _scopeId);
								else return [renderSlot(_ctx.$slots, "default")];
							}),
							_: 3
						}, _parent, _scopeId));
					} else return [
						createVNode(VNavigationDrawer, null, {
							default: withCtx(() => [createVNode(VList, null, {
								default: withCtx(() => [
									createVNode(VListItem, null, {
										default: withCtx(() => [createVNode(unref(Link), { href: "https://пищепром-сервер.рф/" }, {
											default: withCtx(() => [createTextVNode(" Пищепром-Сервер ")]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VListItem, null, {
										default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.checks") }, {
											default: withCtx(() => [createTextVNode("Закупка")]),
											_: 1
										}, 8, ["href"])]),
										_: 1
									}),
									createVNode(VListItem, null, {
										default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.contactsCentre") }, {
											default: withCtx(() => [createTextVNode("Contacts centre")]),
											_: 1
										}, 8, ["href"])]),
										_: 1
									}),
									createVNode(VListItem, null, {
										default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise/avito") }, {
											default: withCtx(() => [createTextVNode("Avito")]),
											_: 1
										}, 8, ["href"])]),
										_: 1
									}),
									createVNode(VListItem, null, {
										default: withCtx(() => [createVNode(unref(Link), {
											href: unref(route)("ameise.telegrambot"),
											color: "blue-darken-1"
										}, {
											default: withCtx(() => [createTextVNode("Telegram")]),
											_: 1
										}, 8, ["href"])]),
										_: 1
									}),
									createVNode(VListItem, null, {
										default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.yandex") }, {
											default: withCtx(() => [createTextVNode(" Yandex ")]),
											_: 1
										}, 8, ["href"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}),
						createVNode(VAppBar, {
							elevation: 2,
							rounded: "",
							density: "compact",
							"scroll-behavior": "fade-image elevate",
							image: "https://picsum.photos/1920/1080?random"
						}, {
							prepend: withCtx(() => [createVNode(VAppBarNavIcon, null, {
								default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise") }, {
									default: withCtx(() => [createVNode(VIcon, {
										icon: "mdi-halloween",
										size: "large"
									})]),
									_: 1
								}, 8, ["href"])]),
								_: 1
							})]),
							append: withCtx(() => [
								createVNode(VBtn, { icon: "mdi-heart" }),
								createVNode(VBtn, { icon: "mdi-magnify" }),
								createVNode(VBtn, { icon: "mdi-dots-vertical" }),
								createVNode(VIcon, {
									icon: "mdi-barn",
									size: "x-small"
								})
							]),
							default: withCtx(() => [
								createVNode(VAppBarTitle, null, {
									default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.großbuch") }, {
										default: withCtx(() => [createVNode(VIcon, {
											icon: "mdi-access-point",
											size: "x-small"
										}), createVNode("span", { class: "ml-1 text-xs" }, "Großbuch")]),
										_: 1
									}, 8, ["href"])]),
									_: 1
								}),
								createVNode(VAppBarTitle, null, {
									default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.fluxmonitor") }, {
										default: withCtx(() => [createVNode("span", null, "M")]),
										_: 1
									}, 8, ["href"])]),
									_: 1
								}),
								createVNode(VAppBarTitle, null, {
									default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("ameise.workboard") }, {
										default: withCtx(() => [createVNode("span", null, "WorkBoard")]),
										_: 1
									}, 8, ["href"])]),
									_: 1
								}),
								createVNode(VAppBarTitle, null, {
									default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.botany") }, {
										default: withCtx(() => [createVNode("span", null, "Botany")]),
										_: 1
									}, 8, ["href"])]),
									_: 1
								}),
								createVNode(VAppBarTitle, null, {
									default: withCtx(() => [createVNode(unref(Link), { href: unref(route)("Ameise.perfume") }, {
										default: withCtx(() => [createVNode(VIcon, {
											icon: "mdi-scent",
											size: "small",
											class: "mx-2"
										}), createVNode("span", null, "Perfume")]),
										_: 1
									}, 8, ["href"])]),
									_: 1
								})
							]),
							_: 1
						}),
						createVNode(VMain, {
							class: "d-flex align-center justify-center",
							style: { "min-height": "300px" }
						}, {
							default: withCtx(() => [renderSlot(_ctx.$slots, "default")]),
							_: 3
						})
					];
				}),
				_: 3
			}, _parent));
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Layouts/VerwalterLayout.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as t };

//# sourceMappingURL=VerwalterLayout-BLmFLvbQ.js.map