import { t as _sfc_main$1 } from "./Checkbox-CRHdc492.js";
import { n as _sfc_main$3, t as _sfc_main$2 } from "./TextInput-CFCe_DYd.js";
import { t as _sfc_main$4 } from "./InputLabel-OtLfNjnS.js";
import { t as _sfc_main$5 } from "./PrimaryButton-Cs6KvgUY.js";
import { t as AuthenticationCard_default } from "./AuthenticationCard-Bl54sXXe.js";
import { t as _sfc_main$6 } from "./AuthenticationCardLogo-DJa16rVx.js";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { createBlock, createCommentVNode, createTextVNode, createVNode, openBlock, toDisplayString, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { ssrInterpolate, ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Auth/Login.vue
var _sfc_main = {
	__name: "Login",
	__ssrInlineRender: true,
	props: {
		canResetPassword: Boolean,
		status: String
	},
	setup(__props) {
		const form = useForm({
			email: "",
			password: "",
			remember: false
		});
		const submit = () => {
			form.transform((data) => ({
				...data,
				remember: form.remember ? "on" : ""
			})).post(route("login"), { onFinish: () => form.reset("password") });
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: "Log in" }, null, _parent));
			_push(ssrRenderComponent(AuthenticationCard_default, null, {
				logo: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(_sfc_main$6, null, null, _parent, _scopeId));
					else return [createVNode(_sfc_main$6)];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						if (__props.status) _push(`<div class="mb-4 font-medium text-sm text-green-600"${_scopeId}>${ssrInterpolate(__props.status)}</div>`);
						else _push(`<!---->`);
						_push(`<form${_scopeId}><div${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$4, {
							for: "email",
							value: "Email"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$2, {
							id: "email",
							modelValue: unref(form).email,
							"onUpdate:modelValue": ($event) => unref(form).email = $event,
							type: "email",
							class: "mt-1 block w-full",
							required: "",
							autofocus: "",
							autocomplete: "username"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							class: "mt-2",
							message: unref(form).errors.email
						}, null, _parent, _scopeId));
						_push(`</div><div class="mt-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$4, {
							for: "password",
							value: "Password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$2, {
							id: "password",
							modelValue: unref(form).password,
							"onUpdate:modelValue": ($event) => unref(form).password = $event,
							type: "password",
							class: "mt-1 block w-full",
							required: "",
							autocomplete: "current-password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							class: "mt-2",
							message: unref(form).errors.password
						}, null, _parent, _scopeId));
						_push(`</div><div class="block mt-4"${_scopeId}><label class="flex items-center"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$1, {
							checked: unref(form).remember,
							"onUpdate:checked": ($event) => unref(form).remember = $event,
							name: "remember"
						}, null, _parent, _scopeId));
						_push(`<span class="ms-2 text-sm text-gray-600"${_scopeId}>Remember me</span></label></div><div class="flex items-center justify-end mt-4"${_scopeId}>`);
						if (__props.canResetPassword) _push(ssrRenderComponent(unref(Link), {
							href: _ctx.route("password.request"),
							class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Forgot your password? `);
								else return [createTextVNode(" Forgot your password? ")];
							}),
							_: 1
						}, _parent, _scopeId));
						else _push(`<!---->`);
						_push(ssrRenderComponent(_sfc_main$5, {
							class: ["ms-4", { "opacity-25": unref(form).processing }],
							disabled: unref(form).processing
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Log in `);
								else return [createTextVNode(" Log in ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div></form>`);
					} else return [__props.status ? (openBlock(), createBlock("div", {
						key: 0,
						class: "mb-4 font-medium text-sm text-green-600"
					}, toDisplayString(__props.status), 1)) : createCommentVNode("", true), createVNode("form", { onSubmit: withModifiers(submit, ["prevent"]) }, [
						createVNode("div", null, [
							createVNode(_sfc_main$4, {
								for: "email",
								value: "Email"
							}),
							createVNode(_sfc_main$2, {
								id: "email",
								modelValue: unref(form).email,
								"onUpdate:modelValue": ($event) => unref(form).email = $event,
								type: "email",
								class: "mt-1 block w-full",
								required: "",
								autofocus: "",
								autocomplete: "username"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$3, {
								class: "mt-2",
								message: unref(form).errors.email
							}, null, 8, ["message"])
						]),
						createVNode("div", { class: "mt-4" }, [
							createVNode(_sfc_main$4, {
								for: "password",
								value: "Password"
							}),
							createVNode(_sfc_main$2, {
								id: "password",
								modelValue: unref(form).password,
								"onUpdate:modelValue": ($event) => unref(form).password = $event,
								type: "password",
								class: "mt-1 block w-full",
								required: "",
								autocomplete: "current-password"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$3, {
								class: "mt-2",
								message: unref(form).errors.password
							}, null, 8, ["message"])
						]),
						createVNode("div", { class: "block mt-4" }, [createVNode("label", { class: "flex items-center" }, [createVNode(_sfc_main$1, {
							checked: unref(form).remember,
							"onUpdate:checked": ($event) => unref(form).remember = $event,
							name: "remember"
						}, null, 8, ["checked", "onUpdate:checked"]), createVNode("span", { class: "ms-2 text-sm text-gray-600" }, "Remember me")])]),
						createVNode("div", { class: "flex items-center justify-end mt-4" }, [__props.canResetPassword ? (openBlock(), createBlock(unref(Link), {
							key: 0,
							href: _ctx.route("password.request"),
							class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
						}, {
							default: withCtx(() => [createTextVNode(" Forgot your password? ")]),
							_: 1
						}, 8, ["href"])) : createCommentVNode("", true), createVNode(_sfc_main$5, {
							class: ["ms-4", { "opacity-25": unref(form).processing }],
							disabled: unref(form).processing
						}, {
							default: withCtx(() => [createTextVNode(" Log in ")]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/Login.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Login-YtafiQIo.js.map