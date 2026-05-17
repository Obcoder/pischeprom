import { t as SectionTitle_default } from "./SectionTitle-CLJ38_Jl.js";
import { computed, createVNode, mergeProps, onMounted, onUnmounted, ref, renderSlot, useSSRContext, watch, withCtx } from "vue";
import { ssrRenderAttrs, ssrRenderClass, ssrRenderComponent, ssrRenderSlot, ssrRenderStyle } from "vue/server-renderer";
//#region resources/js/Components/ActionSection.vue
var _sfc_main$2 = {
	__name: "ActionSection",
	__ssrInlineRender: true,
	setup(__props) {
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
			_push(`<div class="mt-5 md:mt-0 md:col-span-2"><div class="px-4 py-5 sm:p-6 bg-white shadow sm:rounded-lg">`);
			ssrRenderSlot(_ctx.$slots, "content", {}, null, _push, _parent);
			_push(`</div></div></div>`);
		};
	}
};
var _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/ActionSection.vue");
	return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Modal.vue
var _sfc_main$1 = {
	__name: "Modal",
	__ssrInlineRender: true,
	props: {
		show: {
			type: Boolean,
			default: false
		},
		maxWidth: {
			type: String,
			default: "2xl"
		},
		closeable: {
			type: Boolean,
			default: true
		}
	},
	emits: ["close"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const dialog = ref();
		const showSlot = ref(props.show);
		watch(() => props.show, () => {
			if (props.show) {
				document.body.style.overflow = "hidden";
				showSlot.value = true;
				dialog.value?.showModal();
			} else {
				document.body.style.overflow = null;
				setTimeout(() => {
					dialog.value?.close();
					showSlot.value = false;
				}, 200);
			}
		});
		const close = () => {
			if (props.closeable) emit("close");
		};
		const closeOnEscape = (e) => {
			if (e.key === "Escape" && props.show) close();
		};
		onMounted(() => document.addEventListener("keydown", closeOnEscape));
		onUnmounted(() => {
			document.removeEventListener("keydown", closeOnEscape);
			document.body.style.overflow = null;
		});
		const maxWidthClass = computed(() => {
			return {
				"sm": "sm:max-w-sm",
				"md": "sm:max-w-md",
				"lg": "sm:max-w-lg",
				"xl": "sm:max-w-xl",
				"2xl": "sm:max-w-2xl"
			}[props.maxWidth];
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<dialog${ssrRenderAttrs(mergeProps({
				class: "z-50 m-0 min-h-full min-w-full overflow-y-auto bg-transparent backdrop:bg-transparent",
				ref_key: "dialog",
				ref: dialog
			}, _attrs))}><div class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" scroll-region><div class="fixed inset-0 transform transition-all" style="${ssrRenderStyle(__props.show ? null : { display: "none" })}"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div><div class="${ssrRenderClass([maxWidthClass.value, "mb-6 bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:mx-auto"])}" style="${ssrRenderStyle(__props.show ? null : { display: "none" })}">`);
			if (showSlot.value) ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
			else _push(`<!---->`);
			_push(`</div></div></dialog>`);
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Modal.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/DialogModal.vue
var _sfc_main = {
	__name: "DialogModal",
	__ssrInlineRender: true,
	props: {
		show: {
			type: Boolean,
			default: false
		},
		maxWidth: {
			type: String,
			default: "2xl"
		},
		closeable: {
			type: Boolean,
			default: true
		}
	},
	emits: ["close"],
	setup(__props, { emit: __emit }) {
		const emit = __emit;
		const close = () => {
			emit("close");
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(_sfc_main$1, mergeProps({
				show: __props.show,
				"max-width": __props.maxWidth,
				closeable: __props.closeable,
				onClose: close
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="px-6 py-4"${_scopeId}><div class="text-lg font-medium text-gray-900"${_scopeId}>`);
						ssrRenderSlot(_ctx.$slots, "title", {}, null, _push, _parent, _scopeId);
						_push(`</div><div class="mt-4 text-sm text-gray-600"${_scopeId}>`);
						ssrRenderSlot(_ctx.$slots, "content", {}, null, _push, _parent, _scopeId);
						_push(`</div></div><div class="flex flex-row justify-end px-6 py-4 bg-gray-100 text-end"${_scopeId}>`);
						ssrRenderSlot(_ctx.$slots, "footer", {}, null, _push, _parent, _scopeId);
						_push(`</div>`);
					} else return [createVNode("div", { class: "px-6 py-4" }, [createVNode("div", { class: "text-lg font-medium text-gray-900" }, [renderSlot(_ctx.$slots, "title")]), createVNode("div", { class: "mt-4 text-sm text-gray-600" }, [renderSlot(_ctx.$slots, "content")])]), createVNode("div", { class: "flex flex-row justify-end px-6 py-4 bg-gray-100 text-end" }, [renderSlot(_ctx.$slots, "footer")])];
				}),
				_: 3
			}, _parent));
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/DialogModal.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main$1 as n, _sfc_main$2 as r, _sfc_main as t };

//# sourceMappingURL=DialogModal-D_ebQbIN.js.map