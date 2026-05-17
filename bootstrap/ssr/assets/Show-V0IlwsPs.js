import { C as VRow, F as VCardText, P as VCard, T as VContainer, X as VChip, et as VAlert, lt as VImg, ot as VIcon, w as VCol } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { t as LayoutDefault_default } from "./LayoutDefault-CXC0Lg2S.js";
import { Link } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, mergeProps, openBlock, renderList, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Products/Show.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: LayoutDefault_default }, {
	__name: "Show",
	__ssrInlineRender: true,
	props: {
		product: {
			type: Object,
			required: true
		},
		goods: {
			type: Array,
			default: () => []
		}
	},
	setup(__props) {
		const props = __props;
		const productTitle = computed(() => {
			return props.product.rus || props.product.name || props.product.eng || `Product #${props.product.id}`;
		});
		useHead({
			title: computed(() => `${productTitle.value} — ПИЩЕПРОМ-СЕРВЕР`),
			meta: [{
				name: "description",
				content: computed(() => `Товары по продукту: ${productTitle.value}`)
			}]
		});
		function goodImage(item) {
			const mediaImage = (item.published_media || []).find((media) => media.type === "image");
			return mediaImage?.thumb_url || mediaImage?.url || item.ava_thumb || item.ava_image || null;
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ class: "py-8" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<div class="product-page-kicker" data-v-02e8b887${_scopeId}> Product </div><h1 class="text-h3 font-weight-bold mb-3" data-v-02e8b887${_scopeId}>${ssrInterpolate(productTitle.value)}</h1>`);
											if (__props.product.category) {
												_push(`<div class="mb-6" data-v-02e8b887${_scopeId}>`);
												_push(ssrRenderComponent(VChip, {
													color: "teal",
													variant: "tonal"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`${ssrInterpolate(__props.product.category.name)}`);
														else return [createTextVNode(toDisplayString(__props.product.category.name), 1)];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`</div>`);
											} else _push(`<!---->`);
										} else return [
											createVNode("div", { class: "product-page-kicker" }, " Product "),
											createVNode("h1", { class: "text-h3 font-weight-bold mb-3" }, toDisplayString(productTitle.value), 1),
											__props.product.category ? (openBlock(), createBlock("div", {
												key: 0,
												class: "mb-6"
											}, [createVNode(VChip, {
												color: "teal",
												variant: "tonal"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(__props.product.category.name), 1)]),
												_: 1
											})])) : createCommentVNode("", true)
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCol, { cols: "12" }, {
									default: withCtx(() => [
										createVNode("div", { class: "product-page-kicker" }, " Product "),
										createVNode("h1", { class: "text-h3 font-weight-bold mb-3" }, toDisplayString(productTitle.value), 1),
										__props.product.category ? (openBlock(), createBlock("div", {
											key: 0,
											class: "mb-6"
										}, [createVNode(VChip, {
											color: "teal",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(__props.product.category.name), 1)]),
											_: 1
										})])) : createCommentVNode("", true)
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						if (__props.goods.length) _push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<!--[-->`);
									ssrRenderList(__props.goods, (good) => {
										_push(ssrRenderComponent(VCol, {
											key: good.id,
											cols: "12",
											sm: "6",
											md: "3"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(unref(Link), {
													href: unref(route)("public.goods.show", { good: good.slug }),
													class: "text-decoration-none"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VCard, {
															rounded: "xl",
															class: "h-100 product-good-card"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	if (goodImage(good)) _push(ssrRenderComponent(VImg, {
																		src: goodImage(good),
																		height: "190",
																		cover: ""
																	}, null, _parent, _scopeId));
																	else {
																		_push(`<div class="product-good-empty" data-v-02e8b887${_scopeId}>`);
																		_push(ssrRenderComponent(VIcon, {
																			icon: "mdi-image-off",
																			size: "42"
																		}, null, _parent, _scopeId));
																		_push(`</div>`);
																	}
																	_push(ssrRenderComponent(VCardText, null, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) {
																				_push(`<div class="font-weight-bold text-body-1" data-v-02e8b887${_scopeId}>${ssrInterpolate(good.name)}</div>`);
																				if (good.description) _push(`<div class="text-caption text-medium-emphasis mt-1 product-good-description" data-v-02e8b887${_scopeId}>${ssrInterpolate(good.description)}</div>`);
																				else _push(`<!---->`);
																			} else return [createVNode("div", { class: "font-weight-bold text-body-1" }, toDisplayString(good.name), 1), good.description ? (openBlock(), createBlock("div", {
																				key: 0,
																				class: "text-caption text-medium-emphasis mt-1 product-good-description"
																			}, toDisplayString(good.description), 1)) : createCommentVNode("", true)];
																		}),
																		_: 2
																	}, _parent, _scopeId));
																} else return [goodImage(good) ? (openBlock(), createBlock(VImg, {
																	key: 0,
																	src: goodImage(good),
																	height: "190",
																	cover: ""
																}, null, 8, ["src"])) : (openBlock(), createBlock("div", {
																	key: 1,
																	class: "product-good-empty"
																}, [createVNode(VIcon, {
																	icon: "mdi-image-off",
																	size: "42"
																})])), createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode("div", { class: "font-weight-bold text-body-1" }, toDisplayString(good.name), 1), good.description ? (openBlock(), createBlock("div", {
																		key: 0,
																		class: "text-caption text-medium-emphasis mt-1 product-good-description"
																	}, toDisplayString(good.description), 1)) : createCommentVNode("", true)]),
																	_: 2
																}, 1024)];
															}),
															_: 2
														}, _parent, _scopeId));
														else return [createVNode(VCard, {
															rounded: "xl",
															class: "h-100 product-good-card"
														}, {
															default: withCtx(() => [goodImage(good) ? (openBlock(), createBlock(VImg, {
																key: 0,
																src: goodImage(good),
																height: "190",
																cover: ""
															}, null, 8, ["src"])) : (openBlock(), createBlock("div", {
																key: 1,
																class: "product-good-empty"
															}, [createVNode(VIcon, {
																icon: "mdi-image-off",
																size: "42"
															})])), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode("div", { class: "font-weight-bold text-body-1" }, toDisplayString(good.name), 1), good.description ? (openBlock(), createBlock("div", {
																	key: 0,
																	class: "text-caption text-medium-emphasis mt-1 product-good-description"
																}, toDisplayString(good.description), 1)) : createCommentVNode("", true)]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024)];
													}),
													_: 2
												}, _parent, _scopeId));
												else return [createVNode(unref(Link), {
													href: unref(route)("public.goods.show", { good: good.slug }),
													class: "text-decoration-none"
												}, {
													default: withCtx(() => [createVNode(VCard, {
														rounded: "xl",
														class: "h-100 product-good-card"
													}, {
														default: withCtx(() => [goodImage(good) ? (openBlock(), createBlock(VImg, {
															key: 0,
															src: goodImage(good),
															height: "190",
															cover: ""
														}, null, 8, ["src"])) : (openBlock(), createBlock("div", {
															key: 1,
															class: "product-good-empty"
														}, [createVNode(VIcon, {
															icon: "mdi-image-off",
															size: "42"
														})])), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode("div", { class: "font-weight-bold text-body-1" }, toDisplayString(good.name), 1), good.description ? (openBlock(), createBlock("div", {
																key: 0,
																class: "text-caption text-medium-emphasis mt-1 product-good-description"
															}, toDisplayString(good.description), 1)) : createCommentVNode("", true)]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1024)]),
													_: 2
												}, 1032, ["href"])];
											}),
											_: 2
										}, _parent, _scopeId));
									});
									_push(`<!--]-->`);
								} else return [(openBlock(true), createBlock(Fragment, null, renderList(__props.goods, (good) => {
									return openBlock(), createBlock(VCol, {
										key: good.id,
										cols: "12",
										sm: "6",
										md: "3"
									}, {
										default: withCtx(() => [createVNode(unref(Link), {
											href: unref(route)("public.goods.show", { good: good.slug }),
											class: "text-decoration-none"
										}, {
											default: withCtx(() => [createVNode(VCard, {
												rounded: "xl",
												class: "h-100 product-good-card"
											}, {
												default: withCtx(() => [goodImage(good) ? (openBlock(), createBlock(VImg, {
													key: 0,
													src: goodImage(good),
													height: "190",
													cover: ""
												}, null, 8, ["src"])) : (openBlock(), createBlock("div", {
													key: 1,
													class: "product-good-empty"
												}, [createVNode(VIcon, {
													icon: "mdi-image-off",
													size: "42"
												})])), createVNode(VCardText, null, {
													default: withCtx(() => [createVNode("div", { class: "font-weight-bold text-body-1" }, toDisplayString(good.name), 1), good.description ? (openBlock(), createBlock("div", {
														key: 0,
														class: "text-caption text-medium-emphasis mt-1 product-good-description"
													}, toDisplayString(good.description), 1)) : createCommentVNode("", true)]),
													_: 2
												}, 1024)]),
												_: 2
											}, 1024)]),
											_: 2
										}, 1032, ["href"])]),
										_: 2
									}, 1024);
								}), 128))];
							}),
							_: 1
						}, _parent, _scopeId));
						else _push(ssrRenderComponent(VAlert, {
							type: "info",
							variant: "tonal"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` По этому Product пока нет опубликованных товаров. `);
								else return [createTextVNode(" По этому Product пока нет опубликованных товаров. ")];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
							default: withCtx(() => [
								createVNode("div", { class: "product-page-kicker" }, " Product "),
								createVNode("h1", { class: "text-h3 font-weight-bold mb-3" }, toDisplayString(productTitle.value), 1),
								__props.product.category ? (openBlock(), createBlock("div", {
									key: 0,
									class: "mb-6"
								}, [createVNode(VChip, {
									color: "teal",
									variant: "tonal"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(__props.product.category.name), 1)]),
									_: 1
								})])) : createCommentVNode("", true)
							]),
							_: 1
						})]),
						_: 1
					}), __props.goods.length ? (openBlock(), createBlock(VRow, { key: 0 }, {
						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.goods, (good) => {
							return openBlock(), createBlock(VCol, {
								key: good.id,
								cols: "12",
								sm: "6",
								md: "3"
							}, {
								default: withCtx(() => [createVNode(unref(Link), {
									href: unref(route)("public.goods.show", { good: good.slug }),
									class: "text-decoration-none"
								}, {
									default: withCtx(() => [createVNode(VCard, {
										rounded: "xl",
										class: "h-100 product-good-card"
									}, {
										default: withCtx(() => [goodImage(good) ? (openBlock(), createBlock(VImg, {
											key: 0,
											src: goodImage(good),
											height: "190",
											cover: ""
										}, null, 8, ["src"])) : (openBlock(), createBlock("div", {
											key: 1,
											class: "product-good-empty"
										}, [createVNode(VIcon, {
											icon: "mdi-image-off",
											size: "42"
										})])), createVNode(VCardText, null, {
											default: withCtx(() => [createVNode("div", { class: "font-weight-bold text-body-1" }, toDisplayString(good.name), 1), good.description ? (openBlock(), createBlock("div", {
												key: 0,
												class: "text-caption text-medium-emphasis mt-1 product-good-description"
											}, toDisplayString(good.description), 1)) : createCommentVNode("", true)]),
											_: 2
										}, 1024)]),
										_: 2
									}, 1024)]),
									_: 2
								}, 1032, ["href"])]),
								_: 2
							}, 1024);
						}), 128))]),
						_: 1
					})) : (openBlock(), createBlock(VAlert, {
						key: 1,
						type: "info",
						variant: "tonal"
					}, {
						default: withCtx(() => [createTextVNode(" По этому Product пока нет опубликованных товаров. ")]),
						_: 1
					}))];
				}),
				_: 1
			}, _parent));
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Products/Show.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var Show_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-02e8b887"]]);
//#endregion
export { Show_default as default };

//# sourceMappingURL=Show-V0IlwsPs.js.map