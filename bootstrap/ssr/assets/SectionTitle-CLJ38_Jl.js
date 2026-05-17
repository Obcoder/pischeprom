import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { mergeProps, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderSlot } from "vue/server-renderer";
//#region resources/js/Components/SectionTitle.vue
var _sfc_main = {};
function _sfc_ssrRender(_ctx, _push, _parent, _attrs) {
	_push(`<div${ssrRenderAttrs(mergeProps({ class: "md:col-span-1 flex justify-between" }, _attrs))}><div class="px-4 sm:px-0"><h3 class="text-lg font-medium text-gray-900">`);
	ssrRenderSlot(_ctx.$slots, "title", {}, null, _push, _parent);
	_push(`</h3><p class="mt-1 text-sm text-gray-600">`);
	ssrRenderSlot(_ctx.$slots, "description", {}, null, _push, _parent);
	_push(`</p></div><div class="px-4 sm:px-0">`);
	ssrRenderSlot(_ctx.$slots, "aside", {}, null, _push, _parent);
	_push(`</div></div>`);
}
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/SectionTitle.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var SectionTitle_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["ssrRender", _sfc_ssrRender]]);
//#endregion
export { SectionTitle_default as t };

//# sourceMappingURL=SectionTitle-CLJ38_Jl.js.map