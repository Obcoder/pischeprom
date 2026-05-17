import { useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderSlot, ssrRenderStyle } from "vue/server-renderer";
//#region resources/js/Components/ActionMessage.vue
var _sfc_main = {
	__name: "ActionMessage",
	__ssrInlineRender: true,
	props: { on: Boolean },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${ssrRenderAttrs(_attrs)}><div class="text-sm text-gray-600" style="${ssrRenderStyle(__props.on ? null : { display: "none" })}">`);
			ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
			_push(`</div></div>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/ActionMessage.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as t };

//# sourceMappingURL=ActionMessage-B88A34Mo.js.map