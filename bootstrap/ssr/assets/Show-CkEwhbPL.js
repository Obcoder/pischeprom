import { C as VRow, F as VCardText, G as VList, I as VCardTitle, P as VCard, T as VContainer, lt as VImg, q as VListItem, w as VCol } from "../ssr.js";
import { t as LayoutDefault_default } from "./LayoutDefault-CXC0Lg2S.js";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, mergeProps, openBlock, renderList, toDisplayString, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
//#region resources/js/Pages/Categories/Show.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: LayoutDefault_default }, {
	__name: "Show",
	__ssrInlineRender: true,
	props: { category: {
		type: Object,
		required: true
	} },
	setup(__props) {
		const props = __props;
		const category = computed(() => props.category ?? {});
		const products = computed(() => category.value.products ?? []);
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ class: "py-8" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, { class: "mb-6" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										md: "3"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) if (category.value.image) _push(ssrRenderComponent(VImg, {
												src: category.value.image,
												"aspect-ratio": "1/1",
												cover: "",
												rounded: ""
											}, null, _parent, _scopeId));
											else _push(`<!---->`);
											else return [category.value.image ? (openBlock(), createBlock(VImg, {
												key: 0,
												src: category.value.image,
												"aspect-ratio": "1/1",
												cover: "",
												rounded: ""
											}, null, 8, ["src"])) : createCommentVNode("", true)];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										md: "9"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`<h1 class="text-h4 font-weight-bold mb-2"${_scopeId}>${ssrInterpolate(category.value.name)}</h1><div class="text-body-1 text-medium-emphasis"${_scopeId}> Товаров в категории: ${ssrInterpolate(category.value.products_count ?? 0)}</div>`);
											else return [createVNode("h1", { class: "text-h4 font-weight-bold mb-2" }, toDisplayString(category.value.name), 1), createVNode("div", { class: "text-body-1 text-medium-emphasis" }, " Товаров в категории: " + toDisplayString(category.value.products_count ?? 0), 1)];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, {
									cols: "12",
									md: "3"
								}, {
									default: withCtx(() => [category.value.image ? (openBlock(), createBlock(VImg, {
										key: 0,
										src: category.value.image,
										"aspect-ratio": "1/1",
										cover: "",
										rounded: ""
									}, null, 8, ["src"])) : createCommentVNode("", true)]),
									_: 1
								}), createVNode(VCol, {
									cols: "12",
									md: "9"
								}, {
									default: withCtx(() => [createVNode("h1", { class: "text-h4 font-weight-bold mb-2" }, toDisplayString(category.value.name), 1), createVNode("div", { class: "text-body-1 text-medium-emphasis" }, " Товаров в категории: " + toDisplayString(category.value.products_count ?? 0), 1)]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCol, {
									cols: "12",
									md: "8",
									lg: "6"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCard, {
											rounded: "xl",
											elevation: "2"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(ssrRenderComponent(VCardTitle, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(`Товары категории`);
															else return [createTextVNode("Товары категории")];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCardText, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) if (products.value.length) _push(ssrRenderComponent(VList, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(`<!--[-->`);
																		ssrRenderList(products.value, (product) => {
																			_push(ssrRenderComponent(VListItem, { key: product.id }, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(`<div${_scopeId}>${ssrInterpolate(product.rus || product.name)}</div>`);
																					else return [createVNode("div", null, toDisplayString(product.rus || product.name), 1)];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																		});
																		_push(`<!--]-->`);
																	} else return [(openBlock(true), createBlock(Fragment, null, renderList(products.value, (product) => {
																		return openBlock(), createBlock(VListItem, { key: product.id }, {
																			default: withCtx(() => [createVNode("div", null, toDisplayString(product.rus || product.name), 1)]),
																			_: 2
																		}, 1024);
																	}), 128))];
																}),
																_: 1
															}, _parent, _scopeId));
															else _push(`<div class="text-body-2 text-medium-emphasis"${_scopeId}> В этой категории пока нет опубликованных товаров. </div>`);
															else return [products.value.length ? (openBlock(), createBlock(VList, { key: 0 }, {
																default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(products.value, (product) => {
																	return openBlock(), createBlock(VListItem, { key: product.id }, {
																		default: withCtx(() => [createVNode("div", null, toDisplayString(product.rus || product.name), 1)]),
																		_: 2
																	}, 1024);
																}), 128))]),
																_: 1
															})) : (openBlock(), createBlock("div", {
																key: 1,
																class: "text-body-2 text-medium-emphasis"
															}, " В этой категории пока нет опубликованных товаров. "))];
														}),
														_: 1
													}, _parent, _scopeId));
												} else return [createVNode(VCardTitle, null, {
													default: withCtx(() => [createTextVNode("Товары категории")]),
													_: 1
												}), createVNode(VCardText, null, {
													default: withCtx(() => [products.value.length ? (openBlock(), createBlock(VList, { key: 0 }, {
														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(products.value, (product) => {
															return openBlock(), createBlock(VListItem, { key: product.id }, {
																default: withCtx(() => [createVNode("div", null, toDisplayString(product.rus || product.name), 1)]),
																_: 2
															}, 1024);
														}), 128))]),
														_: 1
													})) : (openBlock(), createBlock("div", {
														key: 1,
														class: "text-body-2 text-medium-emphasis"
													}, " В этой категории пока нет опубликованных товаров. "))]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VCard, {
											rounded: "xl",
											elevation: "2"
										}, {
											default: withCtx(() => [createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Товары категории")]),
												_: 1
											}), createVNode(VCardText, null, {
												default: withCtx(() => [products.value.length ? (openBlock(), createBlock(VList, { key: 0 }, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(products.value, (product) => {
														return openBlock(), createBlock(VListItem, { key: product.id }, {
															default: withCtx(() => [createVNode("div", null, toDisplayString(product.rus || product.name), 1)]),
															_: 2
														}, 1024);
													}), 128))]),
													_: 1
												})) : (openBlock(), createBlock("div", {
													key: 1,
													class: "text-body-2 text-medium-emphasis"
												}, " В этой категории пока нет опубликованных товаров. "))]),
												_: 1
											})]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCol, {
									cols: "12",
									md: "8",
									lg: "6"
								}, {
									default: withCtx(() => [createVNode(VCard, {
										rounded: "xl",
										elevation: "2"
									}, {
										default: withCtx(() => [createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Товары категории")]),
											_: 1
										}), createVNode(VCardText, null, {
											default: withCtx(() => [products.value.length ? (openBlock(), createBlock(VList, { key: 0 }, {
												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(products.value, (product) => {
													return openBlock(), createBlock(VListItem, { key: product.id }, {
														default: withCtx(() => [createVNode("div", null, toDisplayString(product.rus || product.name), 1)]),
														_: 2
													}, 1024);
												}), 128))]),
												_: 1
											})) : (openBlock(), createBlock("div", {
												key: 1,
												class: "text-body-2 text-medium-emphasis"
											}, " В этой категории пока нет опубликованных товаров. "))]),
											_: 1
										})]),
										_: 1
									})]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, { class: "mb-6" }, {
						default: withCtx(() => [createVNode(VCol, {
							cols: "12",
							md: "3"
						}, {
							default: withCtx(() => [category.value.image ? (openBlock(), createBlock(VImg, {
								key: 0,
								src: category.value.image,
								"aspect-ratio": "1/1",
								cover: "",
								rounded: ""
							}, null, 8, ["src"])) : createCommentVNode("", true)]),
							_: 1
						}), createVNode(VCol, {
							cols: "12",
							md: "9"
						}, {
							default: withCtx(() => [createVNode("h1", { class: "text-h4 font-weight-bold mb-2" }, toDisplayString(category.value.name), 1), createVNode("div", { class: "text-body-1 text-medium-emphasis" }, " Товаров в категории: " + toDisplayString(category.value.products_count ?? 0), 1)]),
							_: 1
						})]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, {
							cols: "12",
							md: "8",
							lg: "6"
						}, {
							default: withCtx(() => [createVNode(VCard, {
								rounded: "xl",
								elevation: "2"
							}, {
								default: withCtx(() => [createVNode(VCardTitle, null, {
									default: withCtx(() => [createTextVNode("Товары категории")]),
									_: 1
								}), createVNode(VCardText, null, {
									default: withCtx(() => [products.value.length ? (openBlock(), createBlock(VList, { key: 0 }, {
										default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(products.value, (product) => {
											return openBlock(), createBlock(VListItem, { key: product.id }, {
												default: withCtx(() => [createVNode("div", null, toDisplayString(product.rus || product.name), 1)]),
												_: 2
											}, 1024);
										}), 128))]),
										_: 1
									})) : (openBlock(), createBlock("div", {
										key: 1,
										class: "text-body-2 text-medium-emphasis"
									}, " В этой категории пока нет опубликованных товаров. "))]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Categories/Show.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Show-CkEwhbPL.js.map