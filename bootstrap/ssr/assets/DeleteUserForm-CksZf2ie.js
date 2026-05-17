import { r as _sfc_main$1, t as _sfc_main$2 } from "./DialogModal-D_ebQbIN.js";
import { t as _sfc_main$3 } from "./DangerButton-wW1RBvmQ.js";
import { n as _sfc_main$5, t as _sfc_main$4 } from "./TextInput-CFCe_DYd.js";
import { t as _sfc_main$6 } from "./SecondaryButton-Cj9pZkX5.js";
import { useForm } from "@inertiajs/vue3";
import { createTextVNode, createVNode, ref, unref, useSSRContext, withCtx, withKeys } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Profile/Partials/DeleteUserForm.vue
var _sfc_main = {
	__name: "DeleteUserForm",
	__ssrInlineRender: true,
	setup(__props) {
		const confirmingUserDeletion = ref(false);
		const passwordInput = ref(null);
		const form = useForm({ password: "" });
		const confirmUserDeletion = () => {
			confirmingUserDeletion.value = true;
			setTimeout(() => passwordInput.value.focus(), 250);
		};
		const deleteUser = () => {
			form.delete(route("current-user.destroy"), {
				preserveScroll: true,
				onSuccess: () => closeModal(),
				onError: () => passwordInput.value.focus(),
				onFinish: () => form.reset()
			});
		};
		const closeModal = () => {
			confirmingUserDeletion.value = false;
			form.reset();
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(_sfc_main$1, _attrs, {
				title: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Delete Account `);
					else return [createTextVNode(" Delete Account ")];
				}),
				description: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Permanently delete your account. `);
					else return [createTextVNode(" Permanently delete your account. ")];
				}),
				content: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="max-w-xl text-sm text-gray-600"${_scopeId}> Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain. </div><div class="mt-5"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$3, { onClick: confirmUserDeletion }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Delete Account `);
								else return [createTextVNode(" Delete Account ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div>`);
						_push(ssrRenderComponent(_sfc_main$2, {
							show: confirmingUserDeletion.value,
							onClose: closeModal
						}, {
							title: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Delete Account `);
								else return [createTextVNode(" Delete Account ")];
							}),
							content: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(` Are you sure you want to delete your account? Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account. <div class="mt-4"${_scopeId}>`);
									_push(ssrRenderComponent(_sfc_main$4, {
										ref_key: "passwordInput",
										ref: passwordInput,
										modelValue: unref(form).password,
										"onUpdate:modelValue": ($event) => unref(form).password = $event,
										type: "password",
										class: "mt-1 block w-3/4",
										placeholder: "Password",
										autocomplete: "current-password",
										onKeyup: deleteUser
									}, null, _parent, _scopeId));
									_push(ssrRenderComponent(_sfc_main$5, {
										message: unref(form).errors.password,
										class: "mt-2"
									}, null, _parent, _scopeId));
									_push(`</div>`);
								} else return [createTextVNode(" Are you sure you want to delete your account? Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account. "), createVNode("div", { class: "mt-4" }, [createVNode(_sfc_main$4, {
									ref_key: "passwordInput",
									ref: passwordInput,
									modelValue: unref(form).password,
									"onUpdate:modelValue": ($event) => unref(form).password = $event,
									type: "password",
									class: "mt-1 block w-3/4",
									placeholder: "Password",
									autocomplete: "current-password",
									onKeyup: withKeys(deleteUser, ["enter"])
								}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(_sfc_main$5, {
									message: unref(form).errors.password,
									class: "mt-2"
								}, null, 8, ["message"])])];
							}),
							footer: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(_sfc_main$6, { onClick: closeModal }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Cancel `);
											else return [createTextVNode(" Cancel ")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(_sfc_main$3, {
										class: ["ms-3", { "opacity-25": unref(form).processing }],
										disabled: unref(form).processing,
										onClick: deleteUser
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Delete Account `);
											else return [createTextVNode(" Delete Account ")];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(_sfc_main$6, { onClick: closeModal }, {
									default: withCtx(() => [createTextVNode(" Cancel ")]),
									_: 1
								}), createVNode(_sfc_main$3, {
									class: ["ms-3", { "opacity-25": unref(form).processing }],
									disabled: unref(form).processing,
									onClick: deleteUser
								}, {
									default: withCtx(() => [createTextVNode(" Delete Account ")]),
									_: 1
								}, 8, ["class", "disabled"])];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode("div", { class: "max-w-xl text-sm text-gray-600" }, " Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain. "),
						createVNode("div", { class: "mt-5" }, [createVNode(_sfc_main$3, { onClick: confirmUserDeletion }, {
							default: withCtx(() => [createTextVNode(" Delete Account ")]),
							_: 1
						})]),
						createVNode(_sfc_main$2, {
							show: confirmingUserDeletion.value,
							onClose: closeModal
						}, {
							title: withCtx(() => [createTextVNode(" Delete Account ")]),
							content: withCtx(() => [createTextVNode(" Are you sure you want to delete your account? Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account. "), createVNode("div", { class: "mt-4" }, [createVNode(_sfc_main$4, {
								ref_key: "passwordInput",
								ref: passwordInput,
								modelValue: unref(form).password,
								"onUpdate:modelValue": ($event) => unref(form).password = $event,
								type: "password",
								class: "mt-1 block w-3/4",
								placeholder: "Password",
								autocomplete: "current-password",
								onKeyup: withKeys(deleteUser, ["enter"])
							}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(_sfc_main$5, {
								message: unref(form).errors.password,
								class: "mt-2"
							}, null, 8, ["message"])])]),
							footer: withCtx(() => [createVNode(_sfc_main$6, { onClick: closeModal }, {
								default: withCtx(() => [createTextVNode(" Cancel ")]),
								_: 1
							}), createVNode(_sfc_main$3, {
								class: ["ms-3", { "opacity-25": unref(form).processing }],
								disabled: unref(form).processing,
								onClick: deleteUser
							}, {
								default: withCtx(() => [createTextVNode(" Delete Account ")]),
								_: 1
							}, 8, ["class", "disabled"])]),
							_: 1
						}, 8, ["show"])
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Profile/Partials/DeleteUserForm.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=DeleteUserForm-CksZf2ie.js.map