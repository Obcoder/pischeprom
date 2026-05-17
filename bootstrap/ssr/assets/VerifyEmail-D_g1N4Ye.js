import { t as _sfc_main$1 } from "./PrimaryButton-Cs6KvgUY.js";
import { t as AuthenticationCard_default } from "./AuthenticationCard-Bl54sXXe.js";
import { t as _sfc_main$2 } from "./AuthenticationCardLogo-DJa16rVx.js";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, createBlock, createCommentVNode, createTextVNode, createVNode, openBlock, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Auth/VerifyEmail.vue
var _sfc_main = {
	__name: "VerifyEmail",
	__ssrInlineRender: true,
	props: { status: String },
	setup(__props) {
		const props = __props;
		const form = useForm({});
		const submit = () => {
			form.post(route("verification.send"));
		};
		const verificationLinkSent = computed(() => props.status === "verification-link-sent");
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: "Email Verification" }, null, _parent));
			_push(ssrRenderComponent(AuthenticationCard_default, null, {
				logo: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(_sfc_main$2, null, null, _parent, _scopeId));
					else return [createVNode(_sfc_main$2)];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="mb-4 text-sm text-gray-600"${_scopeId}> Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn&#39;t receive the email, we will gladly send you another. </div>`);
						if (verificationLinkSent.value) _push(`<div class="mb-4 font-medium text-sm text-green-600"${_scopeId}> A new verification link has been sent to the email address you provided in your profile settings. </div>`);
						else _push(`<!---->`);
						_push(`<form${_scopeId}><div class="mt-4 flex items-center justify-between"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$1, {
							class: { "opacity-25": unref(form).processing },
							disabled: unref(form).processing
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Resend Verification Email `);
								else return [createTextVNode(" Resend Verification Email ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`<div${_scopeId}>`);
						_push(ssrRenderComponent(unref(Link), {
							href: _ctx.route("profile.show"),
							class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Edit Profile`);
								else return [createTextVNode(" Edit Profile")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(unref(Link), {
							href: _ctx.route("logout"),
							method: "post",
							as: "button",
							class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 ms-2"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Log Out `);
								else return [createTextVNode(" Log Out ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div></div></form>`);
					} else return [
						createVNode("div", { class: "mb-4 text-sm text-gray-600" }, " Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another. "),
						verificationLinkSent.value ? (openBlock(), createBlock("div", {
							key: 0,
							class: "mb-4 font-medium text-sm text-green-600"
						}, " A new verification link has been sent to the email address you provided in your profile settings. ")) : createCommentVNode("", true),
						createVNode("form", { onSubmit: withModifiers(submit, ["prevent"]) }, [createVNode("div", { class: "mt-4 flex items-center justify-between" }, [createVNode(_sfc_main$1, {
							class: { "opacity-25": unref(form).processing },
							disabled: unref(form).processing
						}, {
							default: withCtx(() => [createTextVNode(" Resend Verification Email ")]),
							_: 1
						}, 8, ["class", "disabled"]), createVNode("div", null, [createVNode(unref(Link), {
							href: _ctx.route("profile.show"),
							class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
						}, {
							default: withCtx(() => [createTextVNode(" Edit Profile")]),
							_: 1
						}, 8, ["href"]), createVNode(unref(Link), {
							href: _ctx.route("logout"),
							method: "post",
							as: "button",
							class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 ms-2"
						}, {
							default: withCtx(() => [createTextVNode(" Log Out ")]),
							_: 1
						}, 8, ["href"])])])], 32)
					];
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/VerifyEmail.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=VerifyEmail-D_g1N4Ye.js.map