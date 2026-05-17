import { n as _sfc_main$2, t as _sfc_main$1 } from "./TextInput-CFCe_DYd.js";
import { t as _sfc_main$3 } from "./InputLabel-OtLfNjnS.js";
import { t as _sfc_main$4 } from "./PrimaryButton-Cs6KvgUY.js";
import { t as AuthenticationCard_default } from "./AuthenticationCard-Bl54sXXe.js";
import { t as _sfc_main$5 } from "./AuthenticationCardLogo-DJa16rVx.js";
import { Head, useForm } from "@inertiajs/vue3";
import { createTextVNode, createVNode, ref, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Auth/ConfirmPassword.vue
var _sfc_main = {
	__name: "ConfirmPassword",
	__ssrInlineRender: true,
	setup(__props) {
		const form = useForm({ password: "" });
		const passwordInput = ref(null);
		const submit = () => {
			form.post(route("password.confirm"), { onFinish: () => {
				form.reset();
				passwordInput.value.focus();
			} });
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: "Secure Area" }, null, _parent));
			_push(ssrRenderComponent(AuthenticationCard_default, null, {
				logo: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(_sfc_main$5, null, null, _parent, _scopeId));
					else return [createVNode(_sfc_main$5)];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="mb-4 text-sm text-gray-600"${_scopeId}> This is a secure area of the application. Please confirm your password before continuing. </div><form${_scopeId}><div${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$3, {
							for: "password",
							value: "Password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$1, {
							id: "password",
							ref_key: "passwordInput",
							ref: passwordInput,
							modelValue: unref(form).password,
							"onUpdate:modelValue": ($event) => unref(form).password = $event,
							type: "password",
							class: "mt-1 block w-full",
							required: "",
							autocomplete: "current-password",
							autofocus: ""
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$2, {
							class: "mt-2",
							message: unref(form).errors.password
						}, null, _parent, _scopeId));
						_push(`</div><div class="flex justify-end mt-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$4, {
							class: ["ms-4", { "opacity-25": unref(form).processing }],
							disabled: unref(form).processing
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Confirm `);
								else return [createTextVNode(" Confirm ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div></form>`);
					} else return [createVNode("div", { class: "mb-4 text-sm text-gray-600" }, " This is a secure area of the application. Please confirm your password before continuing. "), createVNode("form", { onSubmit: withModifiers(submit, ["prevent"]) }, [createVNode("div", null, [
						createVNode(_sfc_main$3, {
							for: "password",
							value: "Password"
						}),
						createVNode(_sfc_main$1, {
							id: "password",
							ref_key: "passwordInput",
							ref: passwordInput,
							modelValue: unref(form).password,
							"onUpdate:modelValue": ($event) => unref(form).password = $event,
							type: "password",
							class: "mt-1 block w-full",
							required: "",
							autocomplete: "current-password",
							autofocus: ""
						}, null, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(_sfc_main$2, {
							class: "mt-2",
							message: unref(form).errors.password
						}, null, 8, ["message"])
					]), createVNode("div", { class: "flex justify-end mt-4" }, [createVNode(_sfc_main$4, {
						class: ["ms-4", { "opacity-25": unref(form).processing }],
						disabled: unref(form).processing
					}, {
						default: withCtx(() => [createTextVNode(" Confirm ")]),
						_: 1
					}, 8, ["class", "disabled"])])], 32)];
				}),
				_: 1
			}, _parent));
			_push(`<!--]-->`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/ConfirmPassword.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=ConfirmPassword-Mz4B5mzL.js.map