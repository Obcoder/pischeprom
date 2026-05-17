import { mergeProps, useSSRContext } from "vue";
import { ssrInterpolate, ssrRenderAttrs, ssrRenderSlot } from "vue/server-renderer";
//#region resources/js/Components/InputLabel.vue
var _sfc_main = {
	__name: "InputLabel",
	__ssrInlineRender: true,
	props: { value: String },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<label${ssrRenderAttrs(mergeProps({ class: "block font-medium text-sm text-gray-700" }, _attrs))}>`);
			if (__props.value) _push(`<span>${ssrInterpolate(__props.value)}</span>`);
			else {
				_push(`<span>`);
				ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
				_push(`</span>`);
			}
			_push(`</label>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/InputLabel.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as t };

//# sourceMappingURL=InputLabel-OtLfNjnS.js.map