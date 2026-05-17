import { t as _sfc_main$1 } from "./Checkbox-CRHdc492.js";
import { n as _sfc_main$3, t as _sfc_main$2 } from "./TextInput-CFCe_DYd.js";
import { t as _sfc_main$4 } from "./InputLabel-OtLfNjnS.js";
import { t as _sfc_main$5 } from "./PrimaryButton-Cs6KvgUY.js";
import { t as AuthenticationCard_default } from "./AuthenticationCard-Bl54sXXe.js";
import { t as _sfc_main$6 } from "./AuthenticationCardLogo-DJa16rVx.js";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { createBlock, createCommentVNode, createTextVNode, createVNode, openBlock, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { ssrRenderAttr, ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Auth/Register.vue
var _sfc_main = {
	__name: "Register",
	__ssrInlineRender: true,
	setup(__props) {
		const form = useForm({
			name: "",
			email: "",
			password: "",
			password_confirmation: "",
			terms: false
		});
		const submit = () => {
			form.post(route("register"), { onFinish: () => form.reset("password", "password_confirmation") });
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: "Register" }, null, _parent));
			_push(ssrRenderComponent(AuthenticationCard_default, null, {
				logo: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(_sfc_main$6, null, null, _parent, _scopeId));
					else return [createVNode(_sfc_main$6)];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<form${_scopeId}><div${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$4, {
							for: "name",
							value: "Name"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$2, {
							id: "name",
							modelValue: unref(form).name,
							"onUpdate:modelValue": ($event) => unref(form).name = $event,
							type: "text",
							class: "mt-1 block w-full",
							required: "",
							autofocus: "",
							autocomplete: "name"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							class: "mt-2",
							message: unref(form).errors.name
						}, null, _parent, _scopeId));
						_push(`</div><div class="mt-4"${_scopeId}>`);
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
							autocomplete: "new-password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							class: "mt-2",
							message: unref(form).errors.password
						}, null, _parent, _scopeId));
						_push(`</div><div class="mt-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$4, {
							for: "password_confirmation",
							value: "Confirm Password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$2, {
							id: "password_confirmation",
							modelValue: unref(form).password_confirmation,
							"onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
							type: "password",
							class: "mt-1 block w-full",
							required: "",
							autocomplete: "new-password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							class: "mt-2",
							message: unref(form).errors.password_confirmation
						}, null, _parent, _scopeId));
						_push(`</div>`);
						if (_ctx.$page.props.jetstream.hasTermsAndPrivacyPolicyFeature) {
							_push(`<div class="mt-4"${_scopeId}>`);
							_push(ssrRenderComponent(_sfc_main$4, { for: "terms" }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) {
										_push(`<div class="flex items-center"${_scopeId}>`);
										_push(ssrRenderComponent(_sfc_main$1, {
											id: "terms",
											checked: unref(form).terms,
											"onUpdate:checked": ($event) => unref(form).terms = $event,
											name: "terms",
											required: ""
										}, null, _parent, _scopeId));
										_push(`<div class="ms-2"${_scopeId}> I agree to the <a target="_blank"${ssrRenderAttr("href", _ctx.route("terms.show"))} class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"${_scopeId}>Terms of Service</a> and <a target="_blank"${ssrRenderAttr("href", _ctx.route("policy.show"))} class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"${_scopeId}>Privacy Policy</a></div></div>`);
										_push(ssrRenderComponent(_sfc_main$3, {
											class: "mt-2",
											message: unref(form).errors.terms
										}, null, _parent, _scopeId));
									} else return [createVNode("div", { class: "flex items-center" }, [createVNode(_sfc_main$1, {
										id: "terms",
										checked: unref(form).terms,
										"onUpdate:checked": ($event) => unref(form).terms = $event,
										name: "terms",
										required: ""
									}, null, 8, ["checked", "onUpdate:checked"]), createVNode("div", { class: "ms-2" }, [
										createTextVNode(" I agree to the "),
										createVNode("a", {
											target: "_blank",
											href: _ctx.route("terms.show"),
											class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
										}, "Terms of Service", 8, ["href"]),
										createTextVNode(" and "),
										createVNode("a", {
											target: "_blank",
											href: _ctx.route("policy.show"),
											class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
										}, "Privacy Policy", 8, ["href"])
									])]), createVNode(_sfc_main$3, {
										class: "mt-2",
										message: unref(form).errors.terms
									}, null, 8, ["message"])];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(`</div>`);
						} else _push(`<!---->`);
						_push(`<div class="flex items-center justify-end mt-4"${_scopeId}>`);
						_push(ssrRenderComponent(unref(Link), {
							href: _ctx.route("login"),
							class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Already registered? `);
								else return [createTextVNode(" Already registered? ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$5, {
							class: ["ms-4", { "opacity-25": unref(form).processing }],
							disabled: unref(form).processing
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Register `);
								else return [createTextVNode(" Register ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div></form>`);
					} else return [createVNode("form", { onSubmit: withModifiers(submit, ["prevent"]) }, [
						createVNode("div", null, [
							createVNode(_sfc_main$4, {
								for: "name",
								value: "Name"
							}),
							createVNode(_sfc_main$2, {
								id: "name",
								modelValue: unref(form).name,
								"onUpdate:modelValue": ($event) => unref(form).name = $event,
								type: "text",
								class: "mt-1 block w-full",
								required: "",
								autofocus: "",
								autocomplete: "name"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$3, {
								class: "mt-2",
								message: unref(form).errors.name
							}, null, 8, ["message"])
						]),
						createVNode("div", { class: "mt-4" }, [
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
								autocomplete: "new-password"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$3, {
								class: "mt-2",
								message: unref(form).errors.password
							}, null, 8, ["message"])
						]),
						createVNode("div", { class: "mt-4" }, [
							createVNode(_sfc_main$4, {
								for: "password_confirmation",
								value: "Confirm Password"
							}),
							createVNode(_sfc_main$2, {
								id: "password_confirmation",
								modelValue: unref(form).password_confirmation,
								"onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
								type: "password",
								class: "mt-1 block w-full",
								required: "",
								autocomplete: "new-password"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$3, {
								class: "mt-2",
								message: unref(form).errors.password_confirmation
							}, null, 8, ["message"])
						]),
						_ctx.$page.props.jetstream.hasTermsAndPrivacyPolicyFeature ? (openBlock(), createBlock("div", {
							key: 0,
							class: "mt-4"
						}, [createVNode(_sfc_main$4, { for: "terms" }, {
							default: withCtx(() => [createVNode("div", { class: "flex items-center" }, [createVNode(_sfc_main$1, {
								id: "terms",
								checked: unref(form).terms,
								"onUpdate:checked": ($event) => unref(form).terms = $event,
								name: "terms",
								required: ""
							}, null, 8, ["checked", "onUpdate:checked"]), createVNode("div", { class: "ms-2" }, [
								createTextVNode(" I agree to the "),
								createVNode("a", {
									target: "_blank",
									href: _ctx.route("terms.show"),
									class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
								}, "Terms of Service", 8, ["href"]),
								createTextVNode(" and "),
								createVNode("a", {
									target: "_blank",
									href: _ctx.route("policy.show"),
									class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
								}, "Privacy Policy", 8, ["href"])
							])]), createVNode(_sfc_main$3, {
								class: "mt-2",
								message: unref(form).errors.terms
							}, null, 8, ["message"])]),
							_: 1
						})])) : createCommentVNode("", true),
						createVNode("div", { class: "flex items-center justify-end mt-4" }, [createVNode(unref(Link), {
							href: _ctx.route("login"),
							class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
						}, {
							default: withCtx(() => [createTextVNode(" Already registered? ")]),
							_: 1
						}, 8, ["href"]), createVNode(_sfc_main$5, {
							class: ["ms-4", { "opacity-25": unref(form).processing }],
							disabled: unref(form).processing
						}, {
							default: withCtx(() => [createTextVNode(" Register ")]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/Register.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Register-CClgr5Tk.js.map