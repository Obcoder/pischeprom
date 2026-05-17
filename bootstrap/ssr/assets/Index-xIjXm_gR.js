import _sfc_main$1 from "./ApiTokenManager-DJ0-PUXn.js";
import { t as _sfc_main$2 } from "./AppLayout-C17wTMGp.js";
import { createVNode, mergeProps, useSSRContext, withCtx } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/API/Index.vue
var _sfc_main = {
	__name: "Index",
	__ssrInlineRender: true,
	props: {
		tokens: Array,
		availablePermissions: Array,
		defaultPermissions: Array
	},
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(_sfc_main$2, mergeProps({ title: "API Tokens" }, _attrs), {
				header: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`<h2 class="font-semibold text-xl text-gray-800 leading-tight"${_scopeId}> API Tokens </h2>`);
					else return [createVNode("h2", { class: "font-semibold text-xl text-gray-800 leading-tight" }, " API Tokens ")];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div${_scopeId}><div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$1, {
							tokens: __props.tokens,
							"available-permissions": __props.availablePermissions,
							"default-permissions": __props.defaultPermissions
						}, null, _parent, _scopeId));
						_push(`</div></div>`);
					} else return [createVNode("div", null, [createVNode("div", { class: "max-w-7xl mx-auto py-10 sm:px-6 lg:px-8" }, [createVNode(_sfc_main$1, {
						tokens: __props.tokens,
						"available-permissions": __props.availablePermissions,
						"default-permissions": __props.defaultPermissions
					}, null, 8, [
						"tokens",
						"available-permissions",
						"default-permissions"
					])])])];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/API/Index.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Index-xIjXm_gR.js.map