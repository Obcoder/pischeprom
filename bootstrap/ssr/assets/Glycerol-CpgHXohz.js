import { t as LayoutDefault_default } from "./LayoutDefault-CXC0Lg2S.js";
import { useSSRContext } from "vue";
import { ssrRenderAttrs } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Glycerol.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: LayoutDefault_default }, {
	__name: "Glycerol",
	__ssrInlineRender: true,
	setup(__props) {
		useHead({ title: "" });
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${ssrRenderAttrs(_attrs)}><h1> Глицерины </h1></div>`);
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Glycerol.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Glycerol-CpgHXohz.js.map