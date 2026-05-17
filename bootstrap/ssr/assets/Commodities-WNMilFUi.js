import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import { t as _sfc_main$2 } from "./CommoditiesPage-CXnWj9Z8.js";
import { useSSRContext } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Ameise/Commodities.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Commodities",
	__ssrInlineRender: true,
	setup(__props) {
		useHead({ title: "Commodities" });
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(_sfc_main$2, _attrs, null, _parent));
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Commodities.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Commodities-WNMilFUi.js.map