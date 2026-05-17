import { t as SectionBorder_default } from "./SectionBorder-CrRflTdP.js";
import { t as _sfc_main$1 } from "./AppLayout-C17wTMGp.js";
import _sfc_main$2 from "./DeleteUserForm-CksZf2ie.js";
import _sfc_main$3 from "./LogoutOtherBrowserSessionsForm-D8kyiEIK.js";
import _sfc_main$4 from "./TwoFactorAuthenticationForm-B4oEkEiu.js";
import _sfc_main$5 from "./UpdatePasswordForm-BrHurooS.js";
import _sfc_main$6 from "./UpdateProfileInformationForm-B7C5hZIB.js";
import { Fragment, createBlock, createCommentVNode, createVNode, mergeProps, openBlock, useSSRContext, withCtx } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Profile/Show.vue
var _sfc_main = {
	__name: "Show",
	__ssrInlineRender: true,
	props: {
		confirmsTwoFactorAuthentication: Boolean,
		sessions: Array
	},
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(_sfc_main$1, mergeProps({ title: "Profile" }, _attrs), {
				header: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`<h2 class="font-semibold text-xl text-gray-800 leading-tight"${_scopeId}> Profile </h2>`);
					else return [createVNode("h2", { class: "font-semibold text-xl text-gray-800 leading-tight" }, " Profile ")];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div${_scopeId}><div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8"${_scopeId}>`);
						if (_ctx.$page.props.jetstream.canUpdateProfileInformation) {
							_push(`<div${_scopeId}>`);
							_push(ssrRenderComponent(_sfc_main$6, { user: _ctx.$page.props.auth.user }, null, _parent, _scopeId));
							_push(ssrRenderComponent(SectionBorder_default, null, null, _parent, _scopeId));
							_push(`</div>`);
						} else _push(`<!---->`);
						if (_ctx.$page.props.jetstream.canUpdatePassword) {
							_push(`<div${_scopeId}>`);
							_push(ssrRenderComponent(_sfc_main$5, { class: "mt-10 sm:mt-0" }, null, _parent, _scopeId));
							_push(ssrRenderComponent(SectionBorder_default, null, null, _parent, _scopeId));
							_push(`</div>`);
						} else _push(`<!---->`);
						if (_ctx.$page.props.jetstream.canManageTwoFactorAuthentication) {
							_push(`<div${_scopeId}>`);
							_push(ssrRenderComponent(_sfc_main$4, {
								"requires-confirmation": __props.confirmsTwoFactorAuthentication,
								class: "mt-10 sm:mt-0"
							}, null, _parent, _scopeId));
							_push(ssrRenderComponent(SectionBorder_default, null, null, _parent, _scopeId));
							_push(`</div>`);
						} else _push(`<!---->`);
						_push(ssrRenderComponent(_sfc_main$3, {
							sessions: __props.sessions,
							class: "mt-10 sm:mt-0"
						}, null, _parent, _scopeId));
						if (_ctx.$page.props.jetstream.hasAccountDeletionFeatures) {
							_push(`<!--[-->`);
							_push(ssrRenderComponent(SectionBorder_default, null, null, _parent, _scopeId));
							_push(ssrRenderComponent(_sfc_main$2, { class: "mt-10 sm:mt-0" }, null, _parent, _scopeId));
							_push(`<!--]-->`);
						} else _push(`<!---->`);
						_push(`</div></div>`);
					} else return [createVNode("div", null, [createVNode("div", { class: "max-w-7xl mx-auto py-10 sm:px-6 lg:px-8" }, [
						_ctx.$page.props.jetstream.canUpdateProfileInformation ? (openBlock(), createBlock("div", { key: 0 }, [createVNode(_sfc_main$6, { user: _ctx.$page.props.auth.user }, null, 8, ["user"]), createVNode(SectionBorder_default)])) : createCommentVNode("", true),
						_ctx.$page.props.jetstream.canUpdatePassword ? (openBlock(), createBlock("div", { key: 1 }, [createVNode(_sfc_main$5, { class: "mt-10 sm:mt-0" }), createVNode(SectionBorder_default)])) : createCommentVNode("", true),
						_ctx.$page.props.jetstream.canManageTwoFactorAuthentication ? (openBlock(), createBlock("div", { key: 2 }, [createVNode(_sfc_main$4, {
							"requires-confirmation": __props.confirmsTwoFactorAuthentication,
							class: "mt-10 sm:mt-0"
						}, null, 8, ["requires-confirmation"]), createVNode(SectionBorder_default)])) : createCommentVNode("", true),
						createVNode(_sfc_main$3, {
							sessions: __props.sessions,
							class: "mt-10 sm:mt-0"
						}, null, 8, ["sessions"]),
						_ctx.$page.props.jetstream.hasAccountDeletionFeatures ? (openBlock(), createBlock(Fragment, { key: 3 }, [createVNode(SectionBorder_default), createVNode(_sfc_main$2, { class: "mt-10 sm:mt-0" })], 64)) : createCommentVNode("", true)
					])])];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Profile/Show.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Show-DMt8BikA.js.map