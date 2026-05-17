import { t as _sfc_main$1 } from "./ActionMessage-B88A34Mo.js";
import { t as _sfc_main$2 } from "./FormSection-CI6-KeKr.js";
import { n as _sfc_main$4, t as _sfc_main$3 } from "./TextInput-CFCe_DYd.js";
import { t as _sfc_main$5 } from "./InputLabel-OtLfNjnS.js";
import { t as _sfc_main$6 } from "./PrimaryButton-Cs6KvgUY.js";
import { useForm } from "@inertiajs/vue3";
import { createTextVNode, createVNode, mergeProps, ref, unref, useSSRContext, withCtx } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue
var _sfc_main = {
	__name: "UpdatePasswordForm",
	__ssrInlineRender: true,
	setup(__props) {
		const passwordInput = ref(null);
		const currentPasswordInput = ref(null);
		const form = useForm({
			current_password: "",
			password: "",
			password_confirmation: ""
		});
		const updatePassword = () => {
			form.put(route("user-password.update"), {
				errorBag: "updatePassword",
				preserveScroll: true,
				onSuccess: () => form.reset(),
				onError: () => {
					if (form.errors.password) {
						form.reset("password", "password_confirmation");
						passwordInput.value.focus();
					}
					if (form.errors.current_password) {
						form.reset("current_password");
						currentPasswordInput.value.focus();
					}
				}
			});
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(_sfc_main$2, mergeProps({ onSubmitted: updatePassword }, _attrs), {
				title: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Update Password `);
					else return [createTextVNode(" Update Password ")];
				}),
				description: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Ensure your account is using a long, random password to stay secure. `);
					else return [createTextVNode(" Ensure your account is using a long, random password to stay secure. ")];
				}),
				form: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="col-span-6 sm:col-span-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$5, {
							for: "current_password",
							value: "Current Password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							id: "current_password",
							ref_key: "currentPasswordInput",
							ref: currentPasswordInput,
							modelValue: unref(form).current_password,
							"onUpdate:modelValue": ($event) => unref(form).current_password = $event,
							type: "password",
							class: "mt-1 block w-full",
							autocomplete: "current-password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$4, {
							message: unref(form).errors.current_password,
							class: "mt-2"
						}, null, _parent, _scopeId));
						_push(`</div><div class="col-span-6 sm:col-span-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$5, {
							for: "password",
							value: "New Password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							id: "password",
							ref_key: "passwordInput",
							ref: passwordInput,
							modelValue: unref(form).password,
							"onUpdate:modelValue": ($event) => unref(form).password = $event,
							type: "password",
							class: "mt-1 block w-full",
							autocomplete: "new-password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$4, {
							message: unref(form).errors.password,
							class: "mt-2"
						}, null, _parent, _scopeId));
						_push(`</div><div class="col-span-6 sm:col-span-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$5, {
							for: "password_confirmation",
							value: "Confirm Password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							id: "password_confirmation",
							modelValue: unref(form).password_confirmation,
							"onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
							type: "password",
							class: "mt-1 block w-full",
							autocomplete: "new-password"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$4, {
							message: unref(form).errors.password_confirmation,
							class: "mt-2"
						}, null, _parent, _scopeId));
						_push(`</div>`);
					} else return [
						createVNode("div", { class: "col-span-6 sm:col-span-4" }, [
							createVNode(_sfc_main$5, {
								for: "current_password",
								value: "Current Password"
							}),
							createVNode(_sfc_main$3, {
								id: "current_password",
								ref_key: "currentPasswordInput",
								ref: currentPasswordInput,
								modelValue: unref(form).current_password,
								"onUpdate:modelValue": ($event) => unref(form).current_password = $event,
								type: "password",
								class: "mt-1 block w-full",
								autocomplete: "current-password"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$4, {
								message: unref(form).errors.current_password,
								class: "mt-2"
							}, null, 8, ["message"])
						]),
						createVNode("div", { class: "col-span-6 sm:col-span-4" }, [
							createVNode(_sfc_main$5, {
								for: "password",
								value: "New Password"
							}),
							createVNode(_sfc_main$3, {
								id: "password",
								ref_key: "passwordInput",
								ref: passwordInput,
								modelValue: unref(form).password,
								"onUpdate:modelValue": ($event) => unref(form).password = $event,
								type: "password",
								class: "mt-1 block w-full",
								autocomplete: "new-password"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$4, {
								message: unref(form).errors.password,
								class: "mt-2"
							}, null, 8, ["message"])
						]),
						createVNode("div", { class: "col-span-6 sm:col-span-4" }, [
							createVNode(_sfc_main$5, {
								for: "password_confirmation",
								value: "Confirm Password"
							}),
							createVNode(_sfc_main$3, {
								id: "password_confirmation",
								modelValue: unref(form).password_confirmation,
								"onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
								type: "password",
								class: "mt-1 block w-full",
								autocomplete: "new-password"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$4, {
								message: unref(form).errors.password_confirmation,
								class: "mt-2"
							}, null, 8, ["message"])
						])
					];
				}),
				actions: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(_sfc_main$1, {
							on: unref(form).recentlySuccessful,
							class: "me-3"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Saved. `);
								else return [createTextVNode(" Saved. ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$6, {
							class: { "opacity-25": unref(form).processing },
							disabled: unref(form).processing
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Save `);
								else return [createTextVNode(" Save ")];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(_sfc_main$1, {
						on: unref(form).recentlySuccessful,
						class: "me-3"
					}, {
						default: withCtx(() => [createTextVNode(" Saved. ")]),
						_: 1
					}, 8, ["on"]), createVNode(_sfc_main$6, {
						class: { "opacity-25": unref(form).processing },
						disabled: unref(form).processing
					}, {
						default: withCtx(() => [createTextVNode(" Save ")]),
						_: 1
					}, 8, ["class", "disabled"])];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=UpdatePasswordForm-BrHurooS.js.map