import { mergeProps, onMounted, ref, useSSRContext } from "vue";
import { ssrInterpolate, ssrRenderAttrs } from "vue/server-renderer";
//#region resources/js/Components/InputError.vue
var _sfc_main$1 = {
	__name: "InputError",
	__ssrInlineRender: true,
	props: { message: String },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${ssrRenderAttrs(mergeProps(_attrs, { style: __props.message ? null : { display: "none" } }))}><p class="text-sm text-red-600">${ssrInterpolate(__props.message)}</p></div>`);
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/InputError.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/TextInput.vue
var _sfc_main = {
	__name: "TextInput",
	__ssrInlineRender: true,
	props: { modelValue: String },
	emits: ["update:modelValue"],
	setup(__props, { expose: __expose }) {
		const input = ref(null);
		onMounted(() => {
			if (input.value.hasAttribute("autofocus")) input.value.focus();
		});
		__expose({ focus: () => input.value.focus() });
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<input${ssrRenderAttrs(mergeProps({
				ref_key: "input",
				ref: input,
				class: "border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm",
				value: __props.modelValue
			}, _attrs))}>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/TextInput.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main$1 as n, _sfc_main as t };

//# sourceMappingURL=TextInput-CFCe_DYd.js.map