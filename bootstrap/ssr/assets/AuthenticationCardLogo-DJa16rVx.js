import { Link } from "@inertiajs/vue3";
import { createBlock, createVNode, mergeProps, openBlock, unref, useSSRContext, withCtx } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Components/AuthenticationCardLogo.vue
var _sfc_main = {
	__name: "AuthenticationCardLogo",
	__ssrInlineRender: true,
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(unref(Link), mergeProps({ href: "/" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`<svg class="w-16 h-16" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"${_scopeId}><path d="M11.395 44.428C4.557 40.198 0 32.632 0 24 0 10.745 10.745 0 24 0a23.891 23.891 0 0113.997 4.502c-.2 17.907-11.097 33.245-26.602 39.926z" fill="#6875F5"${_scopeId}></path><path d="M14.134 45.885A23.914 23.914 0 0024 48c13.255 0 24-10.745 24-24 0-3.516-.756-6.856-2.115-9.866-4.659 15.143-16.608 27.092-31.75 31.751z" fill="#6875F5"${_scopeId}></path></svg>`);
					else return [(openBlock(), createBlock("svg", {
						class: "w-16 h-16",
						viewBox: "0 0 48 48",
						fill: "none",
						xmlns: "http://www.w3.org/2000/svg"
					}, [createVNode("path", {
						d: "M11.395 44.428C4.557 40.198 0 32.632 0 24 0 10.745 10.745 0 24 0a23.891 23.891 0 0113.997 4.502c-.2 17.907-11.097 33.245-26.602 39.926z",
						fill: "#6875F5"
					}), createVNode("path", {
						d: "M14.134 45.885A23.914 23.914 0 0024 48c13.255 0 24-10.745 24-24 0-3.516-.756-6.856-2.115-9.866-4.659 15.143-16.608 27.092-31.75 31.751z",
						fill: "#6875F5"
					})]))];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/AuthenticationCardLogo.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as t };

//# sourceMappingURL=AuthenticationCardLogo-DJa16rVx.js.map