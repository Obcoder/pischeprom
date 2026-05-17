import { n as _sfc_main$2, t as _sfc_main$1 } from "./TextInput-CFCe_DYd.js";
import { t as _sfc_main$3 } from "./InputLabel-OtLfNjnS.js";
import { t as _sfc_main$4 } from "./PrimaryButton-Cs6KvgUY.js";
import { t as AuthenticationCard_default } from "./AuthenticationCard-Bl54sXXe.js";
import { t as _sfc_main$5 } from "./AuthenticationCardLogo-DJa16rVx.js";
import { Head, useForm } from "@inertiajs/vue3";
import { createTextVNode, createVNode, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Auth/ResetPassword.vue
var _sfc_main = {
	__name: "ResetPassword",
	__ssrInlineRender: true,
	props: {
		email: String,
		token: String
	},
	setup(__props) {
		const props = __props;
		const form = useForm({
			token: props.token,
			email: props.email,
			password: "",
			password_confirmation: ""
		});
		const submit = () => {
			form.post(route("password.update"), { onFinish: () => form.reset("password", "password_confirmation") });
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: "Reset Password" }, null, _parent));
			_push(ssrRenderComponent(AuthenticationCard_default, null, {
				logo: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(_sfc_main$5, null, null, _parent, _scopeId));
					else return [createVNode(_sfc_main$5)];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<form${_scopeId}><div${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$3, {
							for: "email",
							value: "Email"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$1, {
							id: "email",
							modelValue: unref(form).email,
							"onUpdate:modelValue": ($event) => unref(form).email = $event,
							type: "email",
							class: "mt-1 block w-full",
							required: "",
							autofocus: "",
							autocomplete: "username"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$2, {
							class: "mt-2",
							message: unref(form).errors.email
						}, null, _parent, _scopeId));
						_push(`</div><div class="mt-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$3, {
							for: "password",
							value: "Password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$1, {
							id: "password",
							modelValue: unref(form).password,
							"onUpdate:modelValue": ($event) => unref(form).password = $event,
							type: "password",
							class: "mt-1 block w-full",
							required: "",
							autocomplete: "new-password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$2, {
							class: "mt-2",
							message: unref(form).errors.password
						}, null, _parent, _scopeId));
						_push(`</div><div class="mt-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$3, {
							for: "password_confirmation",
							value: "Confirm Password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$1, {
							id: "password_confirmation",
							modelValue: unref(form).password_confirmation,
							"onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
							type: "password",
							class: "mt-1 block w-full",
							required: "",
							autocomplete: "new-password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$2, {
							class: "mt-2",
							message: unref(form).errors.password_confirmation
						}, null, _parent, _scopeId));
						_push(`</div><div class="flex items-center justify-end mt-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$4, {
							class: { "opacity-25": unref(form).processing },
							disabled: unref(form).processing
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Reset Password `);
								else return [createTextVNode(" Reset Password ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div></form>`);
					} else return [createVNode("form", { onSubmit: withModifiers(submit, ["prevent"]) }, [
						createVNode("div", null, [
							createVNode(_sfc_main$3, {
								for: "email",
								value: "Email"
							}),
							createVNode(_sfc_main$1, {
								id: "email",
								modelValue: unref(form).email,
								"onUpdate:modelValue": ($event) => unref(form).email = $event,
								type: "email",
								class: "mt-1 block w-full",
								required: "",
								autofocus: "",
								autocomplete: "username"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$2, {
								class: "mt-2",
								message: unref(form).errors.email
							}, null, 8, ["message"])
						]),
						createVNode("div", { class: "mt-4" }, [
							createVNode(_sfc_main$3, {
								for: "password",
								value: "Password"
							}),
							createVNode(_sfc_main$1, {
								id: "password",
								modelValue: unref(form).password,
								"onUpdate:modelValue": ($event) => unref(form).password = $event,
								type: "password",
								class: "mt-1 block w-full",
								required: "",
								autocomplete: "new-password"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$2, {
								class: "mt-2",
								message: unref(form).errors.password
							}, null, 8, ["message"])
						]),
						createVNode("div", { class: "mt-4" }, [
							createVNode(_sfc_main$3, {
								for: "password_confirmation",
								value: "Confirm Password"
							}),
							createVNode(_sfc_main$1, {
								id: "password_confirmation",
								modelValue: unref(form).password_confirmation,
								"onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
								type: "password",
								class: "mt-1 block w-full",
								required: "",
								autocomplete: "new-password"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$2, {
								class: "mt-2",
								message: unref(form).errors.password_confirmation
							}, null, 8, ["message"])
						]),
						createVNode("div", { class: "flex items-center justify-end mt-4" }, [createVNode(_sfc_main$4, {
							class: { "opacity-25": unref(form).processing },
							disabled: unref(form).processing
						}, {
							default: withCtx(() => [createTextVNode(" Reset Password ")]),
							_: 1
						}, 8, ["class", "disabled"])])
					], 32)];
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/ResetPassword.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=ResetPassword-DMESHrRN.js.map