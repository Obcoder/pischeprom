import { C as VRow, T as VContainer, w as VCol } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import "axios";
import { Link } from "@inertiajs/vue3";
import { Fragment, createBlock, createVNode, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
//#region resources/js/Pages/Ameise/Region.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Region",
	__ssrInlineRender: true,
	props: { region: Object },
	setup(__props) {
		ref();
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`<span${_scopeId}>${ssrInterpolate(__props.region.name)}</span>`);
											else return [createVNode("span", null, toDisplayString(__props.region.name), 1)];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
								} else return [
									createVNode(VCol),
									createVNode(VCol, null, {
										default: withCtx(() => [createVNode("span", null, toDisplayString(__props.region.name), 1)]),
										_: 1
									}),
									createVNode(VCol)
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(`<!--[-->`);
												ssrRenderList(__props.region.cities, (city) => {
													_push(`<div class="p-2 my-1 font-UnderdogRegular text-sm text-fuchsia-900 border border-fuchsia-950 rounded"${_scopeId}>`);
													_push(ssrRenderComponent(unref(Link), { href: _ctx.route("city.show", city.id) }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(`<span${_scopeId}>${ssrInterpolate(city.name)}</span>`);
															else return [createVNode("span", null, toDisplayString(city.name), 1)];
														}),
														_: 2
													}, _parent, _scopeId));
													_push(`</div>`);
												});
												_push(`<!--]-->`);
											} else return [(openBlock(true), createBlock(Fragment, null, renderList(__props.region.cities, (city) => {
												return openBlock(), createBlock("div", { class: "p-2 my-1 font-UnderdogRegular text-sm text-fuchsia-900 border border-fuchsia-950 rounded" }, [createVNode(unref(Link), { href: _ctx.route("city.show", city.id) }, {
													default: withCtx(() => [createVNode("span", null, toDisplayString(city.name), 1)]),
													_: 2
												}, 1032, ["href"])]);
											}), 256))];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
								} else return [
									createVNode(VCol, null, {
										default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.region.cities, (city) => {
											return openBlock(), createBlock("div", { class: "p-2 my-1 font-UnderdogRegular text-sm text-fuchsia-900 border border-fuchsia-950 rounded" }, [createVNode(unref(Link), { href: _ctx.route("city.show", city.id) }, {
												default: withCtx(() => [createVNode("span", null, toDisplayString(city.name), 1)]),
												_: 2
											}, 1032, ["href"])]);
										}), 256))]),
										_: 1
									}),
									createVNode(VCol),
									createVNode(VCol)
								];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol),
							createVNode(VCol, null, {
								default: withCtx(() => [createVNode("span", null, toDisplayString(__props.region.name), 1)]),
								_: 1
							}),
							createVNode(VCol)
						]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol, null, {
								default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.region.cities, (city) => {
									return openBlock(), createBlock("div", { class: "p-2 my-1 font-UnderdogRegular text-sm text-fuchsia-900 border border-fuchsia-950 rounded" }, [createVNode(unref(Link), { href: _ctx.route("city.show", city.id) }, {
										default: withCtx(() => [createVNode("span", null, toDisplayString(city.name), 1)]),
										_: 2
									}, 1032, ["href"])]);
								}), 256))]),
								_: 1
							}),
							createVNode(VCol),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Region.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Region-DHwDo7AV.js.map