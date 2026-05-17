import { r as _sfc_main$3, t as _sfc_main$2 } from "./DialogModal-D_ebQbIN.js";
import { t as _sfc_main$4 } from "./DangerButton-wW1RBvmQ.js";
import { n as _sfc_main$6, t as _sfc_main$5 } from "./TextInput-CFCe_DYd.js";
import { t as _sfc_main$7 } from "./InputLabel-OtLfNjnS.js";
import { t as _sfc_main$8 } from "./PrimaryButton-Cs6KvgUY.js";
import { t as _sfc_main$9 } from "./SecondaryButton-Cj9pZkX5.js";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, nextTick, openBlock, reactive, ref, renderList, toDisplayString, unref, useSSRContext, watch, withCtx, withKeys } from "vue";
import { ssrInterpolate, ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderSlot } from "vue/server-renderer";
//#region resources/js/Components/ConfirmsPassword.vue
var _sfc_main$1 = {
	__name: "ConfirmsPassword",
	__ssrInlineRender: true,
	props: {
		title: {
			type: String,
			default: "Confirm Password"
		},
		content: {
			type: String,
			default: "For your security, please confirm your password to continue."
		},
		button: {
			type: String,
			default: "Confirm"
		}
	},
	emits: ["confirmed"],
	setup(__props, { emit: __emit }) {
		const emit = __emit;
		const confirmingPassword = ref(false);
		const form = reactive({
			password: "",
			error: "",
			processing: false
		});
		const passwordInput = ref(null);
		const confirmPassword = () => {
			form.processing = true;
			axios.post(route("password.confirm"), { password: form.password }).then(() => {
				form.processing = false;
				closeModal();
				nextTick().then(() => emit("confirmed"));
			}).catch((error) => {
				form.processing = false;
				form.error = error.response.data.errors.password[0];
				passwordInput.value.focus();
			});
		};
		const closeModal = () => {
			confirmingPassword.value = false;
			form.password = "";
			form.error = "";
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<span${ssrRenderAttrs(_attrs)}><span>`);
			ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
			_push(`</span>`);
			_push(ssrRenderComponent(_sfc_main$2, {
				show: confirmingPassword.value,
				onClose: closeModal
			}, {
				title: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`${ssrInterpolate(__props.title)}`);
					else return [createTextVNode(toDisplayString(__props.title), 1)];
				}),
				content: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`${ssrInterpolate(__props.content)} <div class="mt-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$5, {
							ref_key: "passwordInput",
							ref: passwordInput,
							modelValue: form.password,
							"onUpdate:modelValue": ($event) => form.password = $event,
							type: "password",
							class: "mt-1 block w-3/4",
							placeholder: "Password",
							autocomplete: "current-password",
							onKeyup: confirmPassword
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$6, {
							message: form.error,
							class: "mt-2"
						}, null, _parent, _scopeId));
						_push(`</div>`);
					} else return [createTextVNode(toDisplayString(__props.content) + " ", 1), createVNode("div", { class: "mt-4" }, [createVNode(_sfc_main$5, {
						ref_key: "passwordInput",
						ref: passwordInput,
						modelValue: form.password,
						"onUpdate:modelValue": ($event) => form.password = $event,
						type: "password",
						class: "mt-1 block w-3/4",
						placeholder: "Password",
						autocomplete: "current-password",
						onKeyup: withKeys(confirmPassword, ["enter"])
					}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(_sfc_main$6, {
						message: form.error,
						class: "mt-2"
					}, null, 8, ["message"])])];
				}),
				footer: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(_sfc_main$9, { onClick: closeModal }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Cancel `);
								else return [createTextVNode(" Cancel ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$8, {
							class: ["ms-3", { "opacity-25": form.processing }],
							disabled: form.processing,
							onClick: confirmPassword
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(`${ssrInterpolate(__props.button)}`);
								else return [createTextVNode(toDisplayString(__props.button), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(_sfc_main$9, { onClick: closeModal }, {
						default: withCtx(() => [createTextVNode(" Cancel ")]),
						_: 1
					}), createVNode(_sfc_main$8, {
						class: ["ms-3", { "opacity-25": form.processing }],
						disabled: form.processing,
						onClick: confirmPassword
					}, {
						default: withCtx(() => [createTextVNode(toDisplayString(__props.button), 1)]),
						_: 1
					}, 8, ["class", "disabled"])];
				}),
				_: 1
			}, _parent));
			_push(`</span>`);
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/ConfirmsPassword.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue
var _sfc_main = {
	__name: "TwoFactorAuthenticationForm",
	__ssrInlineRender: true,
	props: { requiresConfirmation: Boolean },
	setup(__props) {
		const props = __props;
		const page = usePage();
		const enabling = ref(false);
		const confirming = ref(false);
		const disabling = ref(false);
		const qrCode = ref(null);
		const setupKey = ref(null);
		const recoveryCodes = ref([]);
		const confirmationForm = useForm({ code: "" });
		const twoFactorEnabled = computed(() => !enabling.value && page.props.auth.user?.two_factor_enabled);
		watch(twoFactorEnabled, () => {
			if (!twoFactorEnabled.value) {
				confirmationForm.reset();
				confirmationForm.clearErrors();
			}
		});
		const enableTwoFactorAuthentication = () => {
			enabling.value = true;
			router.post(route("two-factor.enable"), {}, {
				preserveScroll: true,
				onSuccess: () => Promise.all([
					showQrCode(),
					showSetupKey(),
					showRecoveryCodes()
				]),
				onFinish: () => {
					enabling.value = false;
					confirming.value = props.requiresConfirmation;
				}
			});
		};
		const showQrCode = () => {
			return axios.get(route("two-factor.qr-code")).then((response) => {
				qrCode.value = response.data.svg;
			});
		};
		const showSetupKey = () => {
			return axios.get(route("two-factor.secret-key")).then((response) => {
				setupKey.value = response.data.secretKey;
			});
		};
		const showRecoveryCodes = () => {
			return axios.get(route("two-factor.recovery-codes")).then((response) => {
				recoveryCodes.value = response.data;
			});
		};
		const confirmTwoFactorAuthentication = () => {
			confirmationForm.post(route("two-factor.confirm"), {
				errorBag: "confirmTwoFactorAuthentication",
				preserveScroll: true,
				preserveState: true,
				onSuccess: () => {
					confirming.value = false;
					qrCode.value = null;
					setupKey.value = null;
				}
			});
		};
		const regenerateRecoveryCodes = () => {
			axios.post(route("two-factor.recovery-codes")).then(() => showRecoveryCodes());
		};
		const disableTwoFactorAuthentication = () => {
			disabling.value = true;
			router.delete(route("two-factor.disable"), {
				preserveScroll: true,
				onSuccess: () => {
					disabling.value = false;
					confirming.value = false;
				}
			});
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(_sfc_main$3, _attrs, {
				title: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Two Factor Authentication `);
					else return [createTextVNode(" Two Factor Authentication ")];
				}),
				description: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Add additional security to your account using two factor authentication. `);
					else return [createTextVNode(" Add additional security to your account using two factor authentication. ")];
				}),
				content: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						if (twoFactorEnabled.value && !confirming.value) _push(`<h3 class="text-lg font-medium text-gray-900"${_scopeId}> You have enabled two factor authentication. </h3>`);
						else if (twoFactorEnabled.value && confirming.value) _push(`<h3 class="text-lg font-medium text-gray-900"${_scopeId}> Finish enabling two factor authentication. </h3>`);
						else _push(`<h3 class="text-lg font-medium text-gray-900"${_scopeId}> You have not enabled two factor authentication. </h3>`);
						_push(`<div class="mt-3 max-w-xl text-sm text-gray-600"${_scopeId}><p${_scopeId}> When two factor authentication is enabled, you will be prompted for a secure, random token during authentication. You may retrieve this token from your phone&#39;s Google Authenticator application. </p></div>`);
						if (twoFactorEnabled.value) {
							_push(`<div${_scopeId}>`);
							if (qrCode.value) {
								_push(`<div${_scopeId}><div class="mt-4 max-w-xl text-sm text-gray-600"${_scopeId}>`);
								if (confirming.value) _push(`<p class="font-semibold"${_scopeId}> To finish enabling two factor authentication, scan the following QR code using your phone&#39;s authenticator application or enter the setup key and provide the generated OTP code. </p>`);
								else _push(`<p${_scopeId}> Two factor authentication is now enabled. Scan the following QR code using your phone&#39;s authenticator application or enter the setup key. </p>`);
								_push(`</div><div class="mt-4 p-2 inline-block bg-white"${_scopeId}>${qrCode.value ?? ""}</div>`);
								if (setupKey.value) _push(`<div class="mt-4 max-w-xl text-sm text-gray-600"${_scopeId}><p class="font-semibold"${_scopeId}> Setup Key: <span${_scopeId}>${setupKey.value ?? ""}</span></p></div>`);
								else _push(`<!---->`);
								if (confirming.value) {
									_push(`<div class="mt-4"${_scopeId}>`);
									_push(ssrRenderComponent(_sfc_main$7, {
										for: "code",
										value: "Code"
									}, null, _parent, _scopeId));
									_push(ssrRenderComponent(_sfc_main$5, {
										id: "code",
										modelValue: unref(confirmationForm).code,
										"onUpdate:modelValue": ($event) => unref(confirmationForm).code = $event,
										type: "text",
										name: "code",
										class: "block mt-1 w-1/2",
										inputmode: "numeric",
										autofocus: "",
										autocomplete: "one-time-code",
										onKeyup: confirmTwoFactorAuthentication
									}, null, _parent, _scopeId));
									_push(ssrRenderComponent(_sfc_main$6, {
										message: unref(confirmationForm).errors.code,
										class: "mt-2"
									}, null, _parent, _scopeId));
									_push(`</div>`);
								} else _push(`<!---->`);
								_push(`</div>`);
							} else _push(`<!---->`);
							if (recoveryCodes.value.length > 0 && !confirming.value) {
								_push(`<div${_scopeId}><div class="mt-4 max-w-xl text-sm text-gray-600"${_scopeId}><p class="font-semibold"${_scopeId}> Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two factor authentication device is lost. </p></div><div class="grid gap-1 max-w-xl mt-4 px-4 py-4 font-mono text-sm bg-gray-100 rounded-lg"${_scopeId}><!--[-->`);
								ssrRenderList(recoveryCodes.value, (code) => {
									_push(`<div${_scopeId}>${ssrInterpolate(code)}</div>`);
								});
								_push(`<!--]--></div></div>`);
							} else _push(`<!---->`);
							_push(`</div>`);
						} else _push(`<!---->`);
						_push(`<div class="mt-5"${_scopeId}>`);
						if (!twoFactorEnabled.value) {
							_push(`<div${_scopeId}>`);
							_push(ssrRenderComponent(_sfc_main$1, { onConfirmed: enableTwoFactorAuthentication }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(ssrRenderComponent(_sfc_main$8, {
										type: "button",
										class: { "opacity-25": enabling.value },
										disabled: enabling.value
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Enable `);
											else return [createTextVNode(" Enable ")];
										}),
										_: 1
									}, _parent, _scopeId));
									else return [createVNode(_sfc_main$8, {
										type: "button",
										class: { "opacity-25": enabling.value },
										disabled: enabling.value
									}, {
										default: withCtx(() => [createTextVNode(" Enable ")]),
										_: 1
									}, 8, ["class", "disabled"])];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(`</div>`);
						} else {
							_push(`<div${_scopeId}>`);
							_push(ssrRenderComponent(_sfc_main$1, { onConfirmed: confirmTwoFactorAuthentication }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) if (confirming.value) _push(ssrRenderComponent(_sfc_main$8, {
										type: "button",
										class: ["me-3", { "opacity-25": enabling.value }],
										disabled: enabling.value
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Confirm `);
											else return [createTextVNode(" Confirm ")];
										}),
										_: 1
									}, _parent, _scopeId));
									else _push(`<!---->`);
									else return [confirming.value ? (openBlock(), createBlock(_sfc_main$8, {
										key: 0,
										type: "button",
										class: ["me-3", { "opacity-25": enabling.value }],
										disabled: enabling.value
									}, {
										default: withCtx(() => [createTextVNode(" Confirm ")]),
										_: 1
									}, 8, ["class", "disabled"])) : createCommentVNode("", true)];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(ssrRenderComponent(_sfc_main$1, { onConfirmed: regenerateRecoveryCodes }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) if (recoveryCodes.value.length > 0 && !confirming.value) _push(ssrRenderComponent(_sfc_main$9, { class: "me-3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Regenerate Recovery Codes `);
											else return [createTextVNode(" Regenerate Recovery Codes ")];
										}),
										_: 1
									}, _parent, _scopeId));
									else _push(`<!---->`);
									else return [recoveryCodes.value.length > 0 && !confirming.value ? (openBlock(), createBlock(_sfc_main$9, {
										key: 0,
										class: "me-3"
									}, {
										default: withCtx(() => [createTextVNode(" Regenerate Recovery Codes ")]),
										_: 1
									})) : createCommentVNode("", true)];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(ssrRenderComponent(_sfc_main$1, { onConfirmed: showRecoveryCodes }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) if (recoveryCodes.value.length === 0 && !confirming.value) _push(ssrRenderComponent(_sfc_main$9, { class: "me-3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Show Recovery Codes `);
											else return [createTextVNode(" Show Recovery Codes ")];
										}),
										_: 1
									}, _parent, _scopeId));
									else _push(`<!---->`);
									else return [recoveryCodes.value.length === 0 && !confirming.value ? (openBlock(), createBlock(_sfc_main$9, {
										key: 0,
										class: "me-3"
									}, {
										default: withCtx(() => [createTextVNode(" Show Recovery Codes ")]),
										_: 1
									})) : createCommentVNode("", true)];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(ssrRenderComponent(_sfc_main$1, { onConfirmed: disableTwoFactorAuthentication }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) if (confirming.value) _push(ssrRenderComponent(_sfc_main$9, {
										class: { "opacity-25": disabling.value },
										disabled: disabling.value
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Cancel `);
											else return [createTextVNode(" Cancel ")];
										}),
										_: 1
									}, _parent, _scopeId));
									else _push(`<!---->`);
									else return [confirming.value ? (openBlock(), createBlock(_sfc_main$9, {
										key: 0,
										class: { "opacity-25": disabling.value },
										disabled: disabling.value
									}, {
										default: withCtx(() => [createTextVNode(" Cancel ")]),
										_: 1
									}, 8, ["class", "disabled"])) : createCommentVNode("", true)];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(ssrRenderComponent(_sfc_main$1, { onConfirmed: disableTwoFactorAuthentication }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) if (!confirming.value) _push(ssrRenderComponent(_sfc_main$4, {
										class: { "opacity-25": disabling.value },
										disabled: disabling.value
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Disable `);
											else return [createTextVNode(" Disable ")];
										}),
										_: 1
									}, _parent, _scopeId));
									else _push(`<!---->`);
									else return [!confirming.value ? (openBlock(), createBlock(_sfc_main$4, {
										key: 0,
										class: { "opacity-25": disabling.value },
										disabled: disabling.value
									}, {
										default: withCtx(() => [createTextVNode(" Disable ")]),
										_: 1
									}, 8, ["class", "disabled"])) : createCommentVNode("", true)];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(`</div>`);
						}
						_push(`</div>`);
					} else return [
						twoFactorEnabled.value && !confirming.value ? (openBlock(), createBlock("h3", {
							key: 0,
							class: "text-lg font-medium text-gray-900"
						}, " You have enabled two factor authentication. ")) : twoFactorEnabled.value && confirming.value ? (openBlock(), createBlock("h3", {
							key: 1,
							class: "text-lg font-medium text-gray-900"
						}, " Finish enabling two factor authentication. ")) : (openBlock(), createBlock("h3", {
							key: 2,
							class: "text-lg font-medium text-gray-900"
						}, " You have not enabled two factor authentication. ")),
						createVNode("div", { class: "mt-3 max-w-xl text-sm text-gray-600" }, [createVNode("p", null, " When two factor authentication is enabled, you will be prompted for a secure, random token during authentication. You may retrieve this token from your phone's Google Authenticator application. ")]),
						twoFactorEnabled.value ? (openBlock(), createBlock("div", { key: 3 }, [qrCode.value ? (openBlock(), createBlock("div", { key: 0 }, [
							createVNode("div", { class: "mt-4 max-w-xl text-sm text-gray-600" }, [confirming.value ? (openBlock(), createBlock("p", {
								key: 0,
								class: "font-semibold"
							}, " To finish enabling two factor authentication, scan the following QR code using your phone's authenticator application or enter the setup key and provide the generated OTP code. ")) : (openBlock(), createBlock("p", { key: 1 }, " Two factor authentication is now enabled. Scan the following QR code using your phone's authenticator application or enter the setup key. "))]),
							createVNode("div", {
								class: "mt-4 p-2 inline-block bg-white",
								innerHTML: qrCode.value
							}, null, 8, ["innerHTML"]),
							setupKey.value ? (openBlock(), createBlock("div", {
								key: 0,
								class: "mt-4 max-w-xl text-sm text-gray-600"
							}, [createVNode("p", { class: "font-semibold" }, [createTextVNode(" Setup Key: "), createVNode("span", { innerHTML: setupKey.value }, null, 8, ["innerHTML"])])])) : createCommentVNode("", true),
							confirming.value ? (openBlock(), createBlock("div", {
								key: 1,
								class: "mt-4"
							}, [
								createVNode(_sfc_main$7, {
									for: "code",
									value: "Code"
								}),
								createVNode(_sfc_main$5, {
									id: "code",
									modelValue: unref(confirmationForm).code,
									"onUpdate:modelValue": ($event) => unref(confirmationForm).code = $event,
									type: "text",
									name: "code",
									class: "block mt-1 w-1/2",
									inputmode: "numeric",
									autofocus: "",
									autocomplete: "one-time-code",
									onKeyup: withKeys(confirmTwoFactorAuthentication, ["enter"])
								}, null, 8, ["modelValue", "onUpdate:modelValue"]),
								createVNode(_sfc_main$6, {
									message: unref(confirmationForm).errors.code,
									class: "mt-2"
								}, null, 8, ["message"])
							])) : createCommentVNode("", true)
						])) : createCommentVNode("", true), recoveryCodes.value.length > 0 && !confirming.value ? (openBlock(), createBlock("div", { key: 1 }, [createVNode("div", { class: "mt-4 max-w-xl text-sm text-gray-600" }, [createVNode("p", { class: "font-semibold" }, " Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two factor authentication device is lost. ")]), createVNode("div", { class: "grid gap-1 max-w-xl mt-4 px-4 py-4 font-mono text-sm bg-gray-100 rounded-lg" }, [(openBlock(true), createBlock(Fragment, null, renderList(recoveryCodes.value, (code) => {
							return openBlock(), createBlock("div", { key: code }, toDisplayString(code), 1);
						}), 128))])])) : createCommentVNode("", true)])) : createCommentVNode("", true),
						createVNode("div", { class: "mt-5" }, [!twoFactorEnabled.value ? (openBlock(), createBlock("div", { key: 0 }, [createVNode(_sfc_main$1, { onConfirmed: enableTwoFactorAuthentication }, {
							default: withCtx(() => [createVNode(_sfc_main$8, {
								type: "button",
								class: { "opacity-25": enabling.value },
								disabled: enabling.value
							}, {
								default: withCtx(() => [createTextVNode(" Enable ")]),
								_: 1
							}, 8, ["class", "disabled"])]),
							_: 1
						})])) : (openBlock(), createBlock("div", { key: 1 }, [
							createVNode(_sfc_main$1, { onConfirmed: confirmTwoFactorAuthentication }, {
								default: withCtx(() => [confirming.value ? (openBlock(), createBlock(_sfc_main$8, {
									key: 0,
									type: "button",
									class: ["me-3", { "opacity-25": enabling.value }],
									disabled: enabling.value
								}, {
									default: withCtx(() => [createTextVNode(" Confirm ")]),
									_: 1
								}, 8, ["class", "disabled"])) : createCommentVNode("", true)]),
								_: 1
							}),
							createVNode(_sfc_main$1, { onConfirmed: regenerateRecoveryCodes }, {
								default: withCtx(() => [recoveryCodes.value.length > 0 && !confirming.value ? (openBlock(), createBlock(_sfc_main$9, {
									key: 0,
									class: "me-3"
								}, {
									default: withCtx(() => [createTextVNode(" Regenerate Recovery Codes ")]),
									_: 1
								})) : createCommentVNode("", true)]),
								_: 1
							}),
							createVNode(_sfc_main$1, { onConfirmed: showRecoveryCodes }, {
								default: withCtx(() => [recoveryCodes.value.length === 0 && !confirming.value ? (openBlock(), createBlock(_sfc_main$9, {
									key: 0,
									class: "me-3"
								}, {
									default: withCtx(() => [createTextVNode(" Show Recovery Codes ")]),
									_: 1
								})) : createCommentVNode("", true)]),
								_: 1
							}),
							createVNode(_sfc_main$1, { onConfirmed: disableTwoFactorAuthentication }, {
								default: withCtx(() => [confirming.value ? (openBlock(), createBlock(_sfc_main$9, {
									key: 0,
									class: { "opacity-25": disabling.value },
									disabled: disabling.value
								}, {
									default: withCtx(() => [createTextVNode(" Cancel ")]),
									_: 1
								}, 8, ["class", "disabled"])) : createCommentVNode("", true)]),
								_: 1
							}),
							createVNode(_sfc_main$1, { onConfirmed: disableTwoFactorAuthentication }, {
								default: withCtx(() => [!confirming.value ? (openBlock(), createBlock(_sfc_main$4, {
									key: 0,
									class: { "opacity-25": disabling.value },
									disabled: disabling.value
								}, {
									default: withCtx(() => [createTextVNode(" Disable ")]),
									_: 1
								}, 8, ["class", "disabled"])) : createCommentVNode("", true)]),
								_: 1
							})
						]))])
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=TwoFactorAuthenticationForm-B4oEkEiu.js.map