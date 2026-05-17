import { t as SectionTitle_default } from "./SectionTitle-CLJ38_Jl.js";
import { computed, mergeProps, renderSlot, useSSRContext, useSlots, withCtx } from "vue";
import { ssrRenderAttrs, ssrRenderClass, ssrRenderComponent, ssrRenderSlot } from "vue/server-renderer";
//#region resources/js/Components/FormSection.vue
var _sfc_main = {
	__name: "FormSection",
	__ssrInlineRender: true,
	emits: ["submitted"],
	setup(__props) {
		const hasActions = computed(() => !!useSlots().actions);
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${ssrRenderAttrs(mergeProps({ class: "md:grid md:grid-cols-3 md:gap-6" }, _attrs))}>`);
			_push(ssrRenderComponent(SectionTitle_default, null, {
				title: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) ssrRenderSlot(_ctx.$slots, "title", {}, null, _push, _parent, _scopeId);
					else return [renderSlot(_ctx.$slots, "title")];
				}),
				description: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) ssrRenderSlot(_ctx.$slots, "description", {}, null, _push, _parent, _scopeId);
					else return [renderSlot(_ctx.$slots, "description")];
				}),
				_: 3
			}, _parent));
			_push(`<div class="mt-5 md:mt-0 md:col-span-2"><form><div class="${ssrRenderClass([hasActions.value ? "sm:rounded-tl-md sm:rounded-tr-md" : "sm:rounded-md", "px-4 py-5 bg-white sm:p-6 shadow"])}"><div class="grid grid-cols-6 gap-6">`);
			ssrRenderSlot(_ctx.$slots, "form", {}, null, _push, _parent);
			_push(`</div></div>`);
			if (hasActions.value) {
				_push(`<div class="flex items-center justify-end px-4 py-3 bg-gray-50 text-end sm:px-6 shadow sm:rounded-bl-md sm:rounded-br-md">`);
				ssrRenderSlot(_ctx.$slots, "actions", {}, null, _push, _parent);
				_push(`</div>`);
			} else _push(`<!---->`);
			_push(`</form></div></div>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/FormSection.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as t };

//# sourceMappingURL=FormSection-CI6-KeKr.js.map