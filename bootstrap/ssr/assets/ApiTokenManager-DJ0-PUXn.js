import { t as _sfc_main$2 } from "./ActionMessage-B88A34Mo.js";
import { n as _sfc_main$3, r as _sfc_main$4, t as _sfc_main$5 } from "./DialogModal-D_ebQbIN.js";
import { t as _sfc_main$6 } from "./Checkbox-CRHdc492.js";
import { t as _sfc_main$7 } from "./DangerButton-wW1RBvmQ.js";
import { t as _sfc_main$8 } from "./FormSection-CI6-KeKr.js";
import { n as _sfc_main$10, t as _sfc_main$9 } from "./TextInput-CFCe_DYd.js";
import { t as _sfc_main$11 } from "./InputLabel-OtLfNjnS.js";
import { t as _sfc_main$12 } from "./PrimaryButton-Cs6KvgUY.js";
import { t as _sfc_main$13 } from "./SecondaryButton-Cj9pZkX5.js";
import { t as SectionBorder_default } from "./SectionBorder-CrRflTdP.js";
import { useForm } from "@inertiajs/vue3";
import { Fragment, createBlock, createCommentVNode, createTextVNode, createVNode, mergeProps, openBlock, ref, renderList, renderSlot, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderSlot } from "vue/server-renderer";
//#region resources/js/Components/ConfirmationModal.vue
var _sfc_main$1 = {
	__name: "ConfirmationModal",
	__ssrInlineRender: true,
	props: {
		show: {
			type: Boolean,
			default: false
		},
		maxWidth: {
			type: String,
			default: "2xl"
		},
		closeable: {
			type: Boolean,
			default: true
		}
	},
	emits: ["close"],
	setup(__props, { emit: __emit }) {
		const emit = __emit;
		const close = () => {
			emit("close");
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(_sfc_main$3, mergeProps({
				show: __props.show,
				"max-width": __props.maxWidth,
				closeable: __props.closeable,
				onClose: close
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4"${_scopeId}><div class="sm:flex sm:items-start"${_scopeId}><div class="mx-auto shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"${_scopeId}><svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"${_scopeId}></path></svg></div><div class="mt-3 text-center sm:mt-0 sm:ms-4 sm:text-start"${_scopeId}><h3 class="text-lg font-medium text-gray-900"${_scopeId}>`);
						ssrRenderSlot(_ctx.$slots, "title", {}, null, _push, _parent, _scopeId);
						_push(`</h3><div class="mt-4 text-sm text-gray-600"${_scopeId}>`);
						ssrRenderSlot(_ctx.$slots, "content", {}, null, _push, _parent, _scopeId);
						_push(`</div></div></div></div><div class="flex flex-row justify-end px-6 py-4 bg-gray-100 text-end"${_scopeId}>`);
						ssrRenderSlot(_ctx.$slots, "footer", {}, null, _push, _parent, _scopeId);
						_push(`</div>`);
					} else return [createVNode("div", { class: "bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4" }, [createVNode("div", { class: "sm:flex sm:items-start" }, [createVNode("div", { class: "mx-auto shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10" }, [(openBlock(), createBlock("svg", {
						class: "h-6 w-6 text-red-600",
						xmlns: "http://www.w3.org/2000/svg",
						fill: "none",
						viewBox: "0 0 24 24",
						"stroke-width": "1.5",
						stroke: "currentColor"
					}, [createVNode("path", {
						"stroke-linecap": "round",
						"stroke-linejoin": "round",
						d: "M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
					})]))]), createVNode("div", { class: "mt-3 text-center sm:mt-0 sm:ms-4 sm:text-start" }, [createVNode("h3", { class: "text-lg font-medium text-gray-900" }, [renderSlot(_ctx.$slots, "title")]), createVNode("div", { class: "mt-4 text-sm text-gray-600" }, [renderSlot(_ctx.$slots, "content")])])])]), createVNode("div", { class: "flex flex-row justify-end px-6 py-4 bg-gray-100 text-end" }, [renderSlot(_ctx.$slots, "footer")])];
				}),
				_: 3
			}, _parent));
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/ConfirmationModal.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Pages/API/Partials/ApiTokenManager.vue
var _sfc_main = {
	__name: "ApiTokenManager",
	__ssrInlineRender: true,
	props: {
		tokens: Array,
		availablePermissions: Array,
		defaultPermissions: Array
	},
	setup(__props) {
		const createApiTokenForm = useForm({
			name: "",
			permissions: __props.defaultPermissions
		});
		const updateApiTokenForm = useForm({ permissions: [] });
		const deleteApiTokenForm = useForm({});
		const displayingToken = ref(false);
		const managingPermissionsFor = ref(null);
		const apiTokenBeingDeleted = ref(null);
		const createApiToken = () => {
			createApiTokenForm.post(route("api-tokens.store"), {
				preserveScroll: true,
				onSuccess: () => {
					displayingToken.value = true;
					createApiTokenForm.reset();
				}
			});
		};
		const manageApiTokenPermissions = (token) => {
			updateApiTokenForm.permissions = token.abilities;
			managingPermissionsFor.value = token;
		};
		const updateApiToken = () => {
			updateApiTokenForm.put(route("api-tokens.update", managingPermissionsFor.value), {
				preserveScroll: true,
				preserveState: true,
				onSuccess: () => managingPermissionsFor.value = null
			});
		};
		const confirmApiTokenDeletion = (token) => {
			apiTokenBeingDeleted.value = token;
		};
		const deleteApiToken = () => {
			deleteApiTokenForm.delete(route("api-tokens.destroy", apiTokenBeingDeleted.value), {
				preserveScroll: true,
				preserveState: true,
				onSuccess: () => apiTokenBeingDeleted.value = null
			});
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${ssrRenderAttrs(_attrs)}>`);
			_push(ssrRenderComponent(_sfc_main$8, { onSubmitted: createApiToken }, {
				title: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Create API Token `);
					else return [createTextVNode(" Create API Token ")];
				}),
				description: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` API tokens allow third-party services to authenticate with our application on your behalf. `);
					else return [createTextVNode(" API tokens allow third-party services to authenticate with our application on your behalf. ")];
				}),
				form: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="col-span-6 sm:col-span-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$11, {
							for: "name",
							value: "Name"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$9, {
							id: "name",
							modelValue: unref(createApiTokenForm).name,
							"onUpdate:modelValue": ($event) => unref(createApiTokenForm).name = $event,
							type: "text",
							class: "mt-1 block w-full",
							autofocus: ""
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$10, {
							message: unref(createApiTokenForm).errors.name,
							class: "mt-2"
						}, null, _parent, _scopeId));
						_push(`</div>`);
						if (__props.availablePermissions.length > 0) {
							_push(`<div class="col-span-6"${_scopeId}>`);
							_push(ssrRenderComponent(_sfc_main$11, {
								for: "permissions",
								value: "Permissions"
							}, null, _parent, _scopeId));
							_push(`<div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4"${_scopeId}><!--[-->`);
							ssrRenderList(__props.availablePermissions, (permission) => {
								_push(`<div${_scopeId}><label class="flex items-center"${_scopeId}>`);
								_push(ssrRenderComponent(_sfc_main$6, {
									checked: unref(createApiTokenForm).permissions,
									"onUpdate:checked": ($event) => unref(createApiTokenForm).permissions = $event,
									value: permission
								}, null, _parent, _scopeId));
								_push(`<span class="ms-2 text-sm text-gray-600"${_scopeId}>${ssrInterpolate(permission)}</span></label></div>`);
							});
							_push(`<!--]--></div></div>`);
						} else _push(`<!---->`);
					} else return [createVNode("div", { class: "col-span-6 sm:col-span-4" }, [
						createVNode(_sfc_main$11, {
							for: "name",
							value: "Name"
						}),
						createVNode(_sfc_main$9, {
							id: "name",
							modelValue: unref(createApiTokenForm).name,
							"onUpdate:modelValue": ($event) => unref(createApiTokenForm).name = $event,
							type: "text",
							class: "mt-1 block w-full",
							autofocus: ""
						}, null, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(_sfc_main$10, {
							message: unref(createApiTokenForm).errors.name,
							class: "mt-2"
						}, null, 8, ["message"])
					]), __props.availablePermissions.length > 0 ? (openBlock(), createBlock("div", {
						key: 0,
						class: "col-span-6"
					}, [createVNode(_sfc_main$11, {
						for: "permissions",
						value: "Permissions"
					}), createVNode("div", { class: "mt-2 grid grid-cols-1 md:grid-cols-2 gap-4" }, [(openBlock(true), createBlock(Fragment, null, renderList(__props.availablePermissions, (permission) => {
						return openBlock(), createBlock("div", { key: permission }, [createVNode("label", { class: "flex items-center" }, [createVNode(_sfc_main$6, {
							checked: unref(createApiTokenForm).permissions,
							"onUpdate:checked": ($event) => unref(createApiTokenForm).permissions = $event,
							value: permission
						}, null, 8, [
							"checked",
							"onUpdate:checked",
							"value"
						]), createVNode("span", { class: "ms-2 text-sm text-gray-600" }, toDisplayString(permission), 1)])]);
					}), 128))])])) : createCommentVNode("", true)];
				}),
				actions: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(_sfc_main$2, {
							on: unref(createApiTokenForm).recentlySuccessful,
							class: "me-3"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Created. `);
								else return [createTextVNode(" Created. ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$12, {
							class: { "opacity-25": unref(createApiTokenForm).processing },
							disabled: unref(createApiTokenForm).processing
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Create `);
								else return [createTextVNode(" Create ")];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(_sfc_main$2, {
						on: unref(createApiTokenForm).recentlySuccessful,
						class: "me-3"
					}, {
						default: withCtx(() => [createTextVNode(" Created. ")]),
						_: 1
					}, 8, ["on"]), createVNode(_sfc_main$12, {
						class: { "opacity-25": unref(createApiTokenForm).processing },
						disabled: unref(createApiTokenForm).processing
					}, {
						default: withCtx(() => [createTextVNode(" Create ")]),
						_: 1
					}, 8, ["class", "disabled"])];
				}),
				_: 1
			}, _parent));
			if (__props.tokens.length > 0) {
				_push(`<div>`);
				_push(ssrRenderComponent(SectionBorder_default, null, null, _parent));
				_push(`<div class="mt-10 sm:mt-0">`);
				_push(ssrRenderComponent(_sfc_main$4, null, {
					title: withCtx((_, _push, _parent, _scopeId) => {
						if (_push) _push(` Manage API Tokens `);
						else return [createTextVNode(" Manage API Tokens ")];
					}),
					description: withCtx((_, _push, _parent, _scopeId) => {
						if (_push) _push(` You may delete any of your existing tokens if they are no longer needed. `);
						else return [createTextVNode(" You may delete any of your existing tokens if they are no longer needed. ")];
					}),
					content: withCtx((_, _push, _parent, _scopeId) => {
						if (_push) {
							_push(`<div class="space-y-6"${_scopeId}><!--[-->`);
							ssrRenderList(__props.tokens, (token) => {
								_push(`<div class="flex items-center justify-between"${_scopeId}><div class="break-all"${_scopeId}>${ssrInterpolate(token.name)}</div><div class="flex items-center ms-2"${_scopeId}>`);
								if (token.last_used_ago) _push(`<div class="text-sm text-gray-400"${_scopeId}> Last used ${ssrInterpolate(token.last_used_ago)}</div>`);
								else _push(`<!---->`);
								if (__props.availablePermissions.length > 0) _push(`<button class="cursor-pointer ms-6 text-sm text-gray-400 underline"${_scopeId}> Permissions </button>`);
								else _push(`<!---->`);
								_push(`<button class="cursor-pointer ms-6 text-sm text-red-500"${_scopeId}> Delete </button></div></div>`);
							});
							_push(`<!--]--></div>`);
						} else return [createVNode("div", { class: "space-y-6" }, [(openBlock(true), createBlock(Fragment, null, renderList(__props.tokens, (token) => {
							return openBlock(), createBlock("div", {
								key: token.id,
								class: "flex items-center justify-between"
							}, [createVNode("div", { class: "break-all" }, toDisplayString(token.name), 1), createVNode("div", { class: "flex items-center ms-2" }, [
								token.last_used_ago ? (openBlock(), createBlock("div", {
									key: 0,
									class: "text-sm text-gray-400"
								}, " Last used " + toDisplayString(token.last_used_ago), 1)) : createCommentVNode("", true),
								__props.availablePermissions.length > 0 ? (openBlock(), createBlock("button", {
									key: 1,
									class: "cursor-pointer ms-6 text-sm text-gray-400 underline",
									onClick: ($event) => manageApiTokenPermissions(token)
								}, " Permissions ", 8, ["onClick"])) : createCommentVNode("", true),
								createVNode("button", {
									class: "cursor-pointer ms-6 text-sm text-red-500",
									onClick: ($event) => confirmApiTokenDeletion(token)
								}, " Delete ", 8, ["onClick"])
							])]);
						}), 128))])];
					}),
					_: 1
				}, _parent));
				_push(`</div></div>`);
			} else _push(`<!---->`);
			_push(ssrRenderComponent(_sfc_main$5, {
				show: displayingToken.value,
				onClose: ($event) => displayingToken.value = false
			}, {
				title: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` API Token `);
					else return [createTextVNode(" API Token ")];
				}),
				content: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div${_scopeId}> Please copy your new API token. For your security, it won&#39;t be shown again. </div>`);
						if (_ctx.$page.props.jetstream.flash.token) _push(`<div class="mt-4 bg-gray-100 px-4 py-2 rounded font-mono text-sm text-gray-500 break-all"${_scopeId}>${ssrInterpolate(_ctx.$page.props.jetstream.flash.token)}</div>`);
						else _push(`<!---->`);
					} else return [createVNode("div", null, " Please copy your new API token. For your security, it won't be shown again. "), _ctx.$page.props.jetstream.flash.token ? (openBlock(), createBlock("div", {
						key: 0,
						class: "mt-4 bg-gray-100 px-4 py-2 rounded font-mono text-sm text-gray-500 break-all"
					}, toDisplayString(_ctx.$page.props.jetstream.flash.token), 1)) : createCommentVNode("", true)];
				}),
				footer: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(_sfc_main$13, { onClick: ($event) => displayingToken.value = false }, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) _push(` Close `);
							else return [createTextVNode(" Close ")];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(_sfc_main$13, { onClick: ($event) => displayingToken.value = false }, {
						default: withCtx(() => [createTextVNode(" Close ")]),
						_: 1
					}, 8, ["onClick"])];
				}),
				_: 1
			}, _parent));
			_push(ssrRenderComponent(_sfc_main$5, {
				show: managingPermissionsFor.value != null,
				onClose: ($event) => managingPermissionsFor.value = null
			}, {
				title: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` API Token Permissions `);
					else return [createTextVNode(" API Token Permissions ")];
				}),
				content: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="grid grid-cols-1 md:grid-cols-2 gap-4"${_scopeId}><!--[-->`);
						ssrRenderList(__props.availablePermissions, (permission) => {
							_push(`<div${_scopeId}><label class="flex items-center"${_scopeId}>`);
							_push(ssrRenderComponent(_sfc_main$6, {
								checked: unref(updateApiTokenForm).permissions,
								"onUpdate:checked": ($event) => unref(updateApiTokenForm).permissions = $event,
								value: permission
							}, null, _parent, _scopeId));
							_push(`<span class="ms-2 text-sm text-gray-600"${_scopeId}>${ssrInterpolate(permission)}</span></label></div>`);
						});
						_push(`<!--]--></div>`);
					} else return [createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 gap-4" }, [(openBlock(true), createBlock(Fragment, null, renderList(__props.availablePermissions, (permission) => {
						return openBlock(), createBlock("div", { key: permission }, [createVNode("label", { class: "flex items-center" }, [createVNode(_sfc_main$6, {
							checked: unref(updateApiTokenForm).permissions,
							"onUpdate:checked": ($event) => unref(updateApiTokenForm).permissions = $event,
							value: permission
						}, null, 8, [
							"checked",
							"onUpdate:checked",
							"value"
						]), createVNode("span", { class: "ms-2 text-sm text-gray-600" }, toDisplayString(permission), 1)])]);
					}), 128))])];
				}),
				footer: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(_sfc_main$13, { onClick: ($event) => managingPermissionsFor.value = null }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Cancel `);
								else return [createTextVNode(" Cancel ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$12, {
							class: ["ms-3", { "opacity-25": unref(updateApiTokenForm).processing }],
							disabled: unref(updateApiTokenForm).processing,
							onClick: updateApiToken
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Save `);
								else return [createTextVNode(" Save ")];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(_sfc_main$13, { onClick: ($event) => managingPermissionsFor.value = null }, {
						default: withCtx(() => [createTextVNode(" Cancel ")]),
						_: 1
					}, 8, ["onClick"]), createVNode(_sfc_main$12, {
						class: ["ms-3", { "opacity-25": unref(updateApiTokenForm).processing }],
						disabled: unref(updateApiTokenForm).processing,
						onClick: updateApiToken
					}, {
						default: withCtx(() => [createTextVNode(" Save ")]),
						_: 1
					}, 8, ["class", "disabled"])];
				}),
				_: 1
			}, _parent));
			_push(ssrRenderComponent(_sfc_main$1, {
				show: apiTokenBeingDeleted.value != null,
				onClose: ($event) => apiTokenBeingDeleted.value = null
			}, {
				title: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Delete API Token `);
					else return [createTextVNode(" Delete API Token ")];
				}),
				content: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Are you sure you would like to delete this API token? `);
					else return [createTextVNode(" Are you sure you would like to delete this API token? ")];
				}),
				footer: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(_sfc_main$13, { onClick: ($event) => apiTokenBeingDeleted.value = null }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Cancel `);
								else return [createTextVNode(" Cancel ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$7, {
							class: ["ms-3", { "opacity-25": unref(deleteApiTokenForm).processing }],
							disabled: unref(deleteApiTokenForm).processing,
							onClick: deleteApiToken
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Delete `);
								else return [createTextVNode(" Delete ")];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(_sfc_main$13, { onClick: ($event) => apiTokenBeingDeleted.value = null }, {
						default: withCtx(() => [createTextVNode(" Cancel ")]),
						_: 1
					}, 8, ["onClick"]), createVNode(_sfc_main$7, {
						class: ["ms-3", { "opacity-25": unref(deleteApiTokenForm).processing }],
						disabled: unref(deleteApiTokenForm).processing,
						onClick: deleteApiToken
					}, {
						default: withCtx(() => [createTextVNode(" Delete ")]),
						_: 1
					}, 8, ["class", "disabled"])];
				}),
				_: 1
			}, _parent));
			_push(`</div>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/API/Partials/ApiTokenManager.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=ApiTokenManager-DJ0-PUXn.js.map