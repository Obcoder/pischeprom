import { n as _sfc_main$2, t as _sfc_main$1 } from "./TextInput-CFCe_DYd.js";
import { t as _sfc_main$3 } from "./InputLabel-OtLfNjnS.js";
import { t as _sfc_main$4 } from "./PrimaryButton-Cs6KvgUY.js";
import { t as AuthenticationCard_default } from "./AuthenticationCard-Bl54sXXe.js";
import { t as _sfc_main$5 } from "./AuthenticationCardLogo-DJa16rVx.js";
import { Head, useForm } from "@inertiajs/vue3";
import { Fragment, createBlock, createTextVNode, createVNode, nextTick, openBlock, ref, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Auth/TwoFactorChallenge.vue
var _sfc_main = {
	__name: "TwoFactorChallenge",
	__ssrInlineRender: true,
	setup(__props) {
		const recovery = ref(false);
		const form = useForm({
			code: "",
			recovery_code: ""
		});
		const recoveryCodeInput = ref(null);
		const codeInput = ref(null);
		const toggleRecovery = async () => {
			recovery.value ^= true;
			await nextTick();
			if (recovery.value) {
				recoveryCodeInput.value.focus();
				form.code = "";
			} else {
				codeInput.value.focus();
				form.recovery_code = "";
			}
		};
		const submit = () => {
			form.post(route("two-factor.login"));
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: "Two-factor Confirmation" }, null, _parent));
			_push(ssrRenderComponent(AuthenticationCard_default, null, {
				logo: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(_sfc_main$5, null, null, _parent, _scopeId));
					else return [createVNode(_sfc_main$5)];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="mb-4 text-sm text-gray-600"${_scopeId}>`);
						if (!recovery.value) _push(`<!--[--> Please confirm access to your account by entering the authentication code provided by your authenticator application. <!--]-->`);
						else _push(`<!--[--> Please confirm access to your account by entering one of your emergency recovery codes. <!--]-->`);
						_push(`</div><form${_scopeId}>`);
						if (!recovery.value) {
							_push(`<div${_scopeId}>`);
							_push(ssrRenderComponent(_sfc_main$3, {
								for: "code",
								value: "Code"
							}, null, _parent, _scopeId));
							_push(ssrRenderComponent(_sfc_main$1, {
								id: "code",
								ref_key: "codeInput",
								ref: codeInput,
								modelValue: unref(form).code,
								"onUpdate:modelValue": ($event) => unref(form).code = $event,
								type: "text",
								inputmode: "numeric",
								class: "mt-1 block w-full",
								autofocus: "",
								autocomplete: "one-time-code"
							}, null, _parent, _scopeId));
							_push(ssrRenderComponent(_sfc_main$2, {
								class: "mt-2",
								message: unref(form).errors.code
							}, null, _parent, _scopeId));
							_push(`</div>`);
						} else {
							_push(`<div${_scopeId}>`);
							_push(ssrRenderComponent(_sfc_main$3, {
								for: "recovery_code",
								value: "Recovery Code"
							}, null, _parent, _scopeId));
							_push(ssrRenderComponent(_sfc_main$1, {
								id: "recovery_code",
								ref_key: "recoveryCodeInput",
								ref: recoveryCodeInput,
								modelValue: unref(form).recovery_code,
								"onUpdate:modelValue": ($event) => unref(form).recovery_code = $event,
								type: "text",
								class: "mt-1 block w-full",
								autocomplete: "one-time-code"
							}, null, _parent, _scopeId));
							_push(ssrRenderComponent(_sfc_main$2, {
								class: "mt-2",
								message: unref(form).errors.recovery_code
							}, null, _parent, _scopeId));
							_push(`</div>`);
						}
						_push(`<div class="flex items-center justify-end mt-4"${_scopeId}><button type="button" class="text-sm text-gray-600 hover:text-gray-900 underline cursor-pointer"${_scopeId}>`);
						if (!recovery.value) _push(`<!--[--> Use a recovery code <!--]-->`);
						else _push(`<!--[--> Use an authentication code <!--]-->`);
						_push(`</button>`);
						_push(ssrRenderComponent(_sfc_main$4, {
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
					} else return [createVNode("div", { class: "mb-4 text-sm text-gray-600" }, [!recovery.value ? (openBlock(), createBlock(Fragment, { key: 0 }, [createTextVNode(" Please confirm access to your account by entering the authentication code provided by your authenticator application. ")], 64)) : (openBlock(), createBlock(Fragment, { key: 1 }, [createTextVNode(" Please confirm access to your account by entering one of your emergency recovery codes. ")], 64))]), createVNode("form", { onSubmit: withModifiers(submit, ["prevent"]) }, [!recovery.value ? (openBlock(), createBlock("div", { key: 0 }, [
						createVNode(_sfc_main$3, {
							for: "code",
							value: "Code"
						}),
						createVNode(_sfc_main$1, {
							id: "code",
							ref_key: "codeInput",
							ref: codeInput,
							modelValue: unref(form).code,
							"onUpdate:modelValue": ($event) => unref(form).code = $event,
							type: "text",
							inputmode: "numeric",
							class: "mt-1 block w-full",
							autofocus: "",
							autocomplete: "one-time-code"
						}, null, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(_sfc_main$2, {
							class: "mt-2",
							message: unref(form).errors.code
						}, null, 8, ["message"])
					])) : (openBlock(), createBlock("div", { key: 1 }, [
						createVNode(_sfc_main$3, {
							for: "recovery_code",
							value: "Recovery Code"
						}),
						createVNode(_sfc_main$1, {
							id: "recovery_code",
							ref_key: "recoveryCodeInput",
							ref: recoveryCodeInput,
							modelValue: unref(form).recovery_code,
							"onUpdate:modelValue": ($event) => unref(form).recovery_code = $event,
							type: "text",
							class: "mt-1 block w-full",
							autocomplete: "one-time-code"
						}, null, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(_sfc_main$2, {
							class: "mt-2",
							message: unref(form).errors.recovery_code
						}, null, 8, ["message"])
					])), createVNode("div", { class: "flex items-center justify-end mt-4" }, [createVNode("button", {
						type: "button",
						class: "text-sm text-gray-600 hover:text-gray-900 underline cursor-pointer",
						onClick: withModifiers(toggleRecovery, ["prevent"])
					}, [!recovery.value ? (openBlock(), createBlock(Fragment, { key: 0 }, [createTextVNode(" Use a recovery code ")], 64)) : (openBlock(), createBlock(Fragment, { key: 1 }, [createTextVNode(" Use an authentication code ")], 64))]), createVNode(_sfc_main$4, {
						class: ["ms-4", { "opacity-25": unref(form).processing }],
						disabled: unref(form).processing
					}, {
						default: withCtx(() => [createTextVNode(" Log in ")]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/TwoFactorChallenge.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=TwoFactorChallenge-CbI5T8oZ.js.map