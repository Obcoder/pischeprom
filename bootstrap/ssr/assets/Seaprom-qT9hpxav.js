import { lt as VImg } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { mergeProps, useSSRContext } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Seaprom.vue
var _sfc_main = {};
function _sfc_ssrRender(_ctx, _push, _parent, _attrs) {
	_push(ssrRenderComponent(VImg, mergeProps({ src: "https://storage.yandexcloud.net/cold-reserve/Seaprom%20for%20insta.png" }, _attrs), null, _parent));
}
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Seaprom.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var Seaprom_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["ssrRender", _sfc_ssrRender]]);
//#endregion
export { Seaprom_default as default };

//# sourceMappingURL=Seaprom-qT9hpxav.js.map