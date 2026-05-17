import { C as VRow, G as VList, T as VContainer, lt as VImg, q as VListItem, w as VCol } from "../ssr.js";
import { t as LayoutDefault_default } from "./LayoutDefault-CXC0Lg2S.js";
import "axios";
import { Fragment, createBlock, createVNode, onMounted, openBlock, ref, renderList, toDisplayString, useSSRContext, withCtx } from "vue";
import "ziggy-js";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
//#region resources/js/Pages/Categories.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: LayoutDefault_default }, {
	__name: "Categories",
	__ssrInlineRender: true,
	props: { category: Object },
	setup(__props) {
		const props = __props;
		const category = ref();
		onMounted(() => {
			category.value = props.category;
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCol, { cols: "1" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VImg, {
											src: category.value.image,
											"aspect-ratio": "1/1",
											cover: "",
											rounded: ""
										}, null, _parent, _scopeId));
										else return [createVNode(VImg, {
											src: category.value.image,
											"aspect-ratio": "1/1",
											cover: "",
											rounded: ""
										}, null, 8, ["src"])];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCol, { cols: "1" }, {
									default: withCtx(() => [createVNode(VImg, {
										src: category.value.image,
										"aspect-ratio": "1/1",
										cover: "",
										rounded: ""
									}, null, 8, ["src"])]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { lg: "1" }, null, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { lg: "3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VList, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(`<!--[-->`);
														ssrRenderList(category.value.products, (product) => {
															_push(ssrRenderComponent(VListItem, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`<div${_scopeId}>${ssrInterpolate(product.rus)}</div>`);
																	else return [createVNode("div", null, toDisplayString(product.rus), 1)];
																}),
																_: 2
															}, _parent, _scopeId));
														});
														_push(`<!--]-->`);
													} else return [(openBlock(true), createBlock(Fragment, null, renderList(category.value.products, (product) => {
														return openBlock(), createBlock(VListItem, null, {
															default: withCtx(() => [createVNode("div", null, toDisplayString(product.rus), 1)]),
															_: 2
														}, 1024);
													}), 256))];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VList, null, {
												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(category.value.products, (product) => {
													return openBlock(), createBlock(VListItem, null, {
														default: withCtx(() => [createVNode("div", null, toDisplayString(product.rus), 1)]),
														_: 2
													}, 1024);
												}), 256))]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, { lg: "1" }), createVNode(VCol, { lg: "3" }, {
									default: withCtx(() => [createVNode(VList, null, {
										default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(category.value.products, (product) => {
											return openBlock(), createBlock(VListItem, null, {
												default: withCtx(() => [createVNode("div", null, toDisplayString(product.rus), 1)]),
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
							default: withCtx(() => [createVNode(VImg, {
								src: category.value.image,
								"aspect-ratio": "1/1",
								cover: "",
								rounded: ""
							}, null, 8, ["src"])]),
							_: 1
						})]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, { lg: "1" }), createVNode(VCol, { lg: "3" }, {
							default: withCtx(() => [createVNode(VList, null, {
								default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(category.value.products, (product) => {
									return openBlock(), createBlock(VListItem, null, {
										default: withCtx(() => [createVNode("div", null, toDisplayString(product.rus), 1)]),
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
			}, _parent));
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Categories.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Categories-6Gs_Txwi.js.map