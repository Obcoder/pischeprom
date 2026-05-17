import { t as _sfc_main$1 } from "./ActionMessage-B88A34Mo.js";
import { t as _sfc_main$2 } from "./FormSection-CI6-KeKr.js";
import { n as _sfc_main$3, t as _sfc_main$4 } from "./TextInput-CFCe_DYd.js";
import { t as _sfc_main$5 } from "./InputLabel-OtLfNjnS.js";
import { t as _sfc_main$6 } from "./PrimaryButton-Cs6KvgUY.js";
import { t as _sfc_main$7 } from "./SecondaryButton-Cj9pZkX5.js";
import { Link, router, useForm } from "@inertiajs/vue3";
import { createBlock, createCommentVNode, createTextVNode, createVNode, mergeProps, openBlock, ref, unref, useSSRContext, vShow, withCtx, withDirectives, withModifiers } from "vue";
import { ssrRenderAttr, ssrRenderComponent, ssrRenderStyle } from "vue/server-renderer";
//#region resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue
var _sfc_main = {
	__name: "UpdateProfileInformationForm",
	__ssrInlineRender: true,
	props: { user: Object },
	setup(__props) {
		const props = __props;
		const form = useForm({
			_method: "PUT",
			name: props.user.name,
			email: props.user.email,
			photo: null
		});
		const verificationLinkSent = ref(null);
		const photoPreview = ref(null);
		const photoInput = ref(null);
		const updateProfileInformation = () => {
			if (photoInput.value) form.photo = photoInput.value.files[0];
			form.post(route("user-profile-information.update"), {
				errorBag: "updateProfileInformation",
				preserveScroll: true,
				onSuccess: () => clearPhotoFileInput()
			});
		};
		const sendEmailVerification = () => {
			verificationLinkSent.value = true;
		};
		const selectNewPhoto = () => {
			photoInput.value.click();
		};
		const updatePhotoPreview = () => {
			const photo = photoInput.value.files[0];
			if (!photo) return;
			const reader = new FileReader();
			reader.onload = (e) => {
				photoPreview.value = e.target.result;
			};
			reader.readAsDataURL(photo);
		};
		const deletePhoto = () => {
			router.delete(route("current-user-photo.destroy"), {
				preserveScroll: true,
				onSuccess: () => {
					photoPreview.value = null;
					clearPhotoFileInput();
				}
			});
		};
		const clearPhotoFileInput = () => {
			if (photoInput.value?.value) photoInput.value.value = null;
		};
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(_sfc_main$2, mergeProps({ onSubmitted: updateProfileInformation }, _attrs), {
				title: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Profile Information `);
					else return [createTextVNode(" Profile Information ")];
				}),
				description: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Update your account&#39;s profile information and email address. `);
					else return [createTextVNode(" Update your account's profile information and email address. ")];
				}),
				form: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						if (_ctx.$page.props.jetstream.managesProfilePhotos) {
							_push(`<div class="col-span-6 sm:col-span-4"${_scopeId}><input id="photo" type="file" class="hidden"${_scopeId}>`);
							_push(ssrRenderComponent(_sfc_main$5, {
								for: "photo",
								value: "Photo"
							}, null, _parent, _scopeId));
							_push(`<div class="mt-2" style="${ssrRenderStyle(!photoPreview.value ? null : { display: "none" })}"${_scopeId}><img${ssrRenderAttr("src", __props.user.profile_photo_url)}${ssrRenderAttr("alt", __props.user.name)} class="rounded-full h-20 w-20 object-cover"${_scopeId}></div><div class="mt-2" style="${ssrRenderStyle(photoPreview.value ? null : { display: "none" })}"${_scopeId}><span class="block rounded-full w-20 h-20 bg-cover bg-no-repeat bg-center" style="${ssrRenderStyle("background-image: url('" + photoPreview.value + "');")}"${_scopeId}></span></div>`);
							_push(ssrRenderComponent(_sfc_main$7, {
								class: "mt-2 me-2",
								type: "button",
								onClick: selectNewPhoto
							}, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(` Select A New Photo `);
									else return [createTextVNode(" Select A New Photo ")];
								}),
								_: 1
							}, _parent, _scopeId));
							if (__props.user.profile_photo_path) _push(ssrRenderComponent(_sfc_main$7, {
								type: "button",
								class: "mt-2",
								onClick: deletePhoto
							}, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(` Remove Photo `);
									else return [createTextVNode(" Remove Photo ")];
								}),
								_: 1
							}, _parent, _scopeId));
							else _push(`<!---->`);
							_push(ssrRenderComponent(_sfc_main$3, {
								message: unref(form).errors.photo,
								class: "mt-2"
							}, null, _parent, _scopeId));
							_push(`</div>`);
						} else _push(`<!---->`);
						_push(`<div class="col-span-6 sm:col-span-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$5, {
							for: "name",
							value: "Name"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$4, {
							id: "name",
							modelValue: unref(form).name,
							"onUpdate:modelValue": ($event) => unref(form).name = $event,
							type: "text",
							class: "mt-1 block w-full",
							required: "",
							autocomplete: "name"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							message: unref(form).errors.name,
							class: "mt-2"
						}, null, _parent, _scopeId));
						_push(`</div><div class="col-span-6 sm:col-span-4"${_scopeId}>`);
						_push(ssrRenderComponent(_sfc_main$5, {
							for: "email",
							value: "Email"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$4, {
							id: "email",
							modelValue: unref(form).email,
							"onUpdate:modelValue": ($event) => unref(form).email = $event,
							type: "email",
							class: "mt-1 block w-full",
							required: "",
							autocomplete: "username"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							message: unref(form).errors.email,
							class: "mt-2"
						}, null, _parent, _scopeId));
						if (_ctx.$page.props.jetstream.hasEmailVerification && __props.user.email_verified_at === null) {
							_push(`<div${_scopeId}><p class="text-sm mt-2"${_scopeId}> Your email address is unverified. `);
							_push(ssrRenderComponent(unref(Link), {
								href: _ctx.route("verification.send"),
								method: "post",
								as: "button",
								class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500",
								onClick: sendEmailVerification
							}, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(` Click here to re-send the verification email. `);
									else return [createTextVNode(" Click here to re-send the verification email. ")];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(`</p><div class="mt-2 font-medium text-sm text-green-600" style="${ssrRenderStyle(verificationLinkSent.value ? null : { display: "none" })}"${_scopeId}> A new verification link has been sent to your email address. </div></div>`);
						} else _push(`<!---->`);
						_push(`</div>`);
					} else return [
						_ctx.$page.props.jetstream.managesProfilePhotos ? (openBlock(), createBlock("div", {
							key: 0,
							class: "col-span-6 sm:col-span-4"
						}, [
							createVNode("input", {
								id: "photo",
								ref_key: "photoInput",
								ref: photoInput,
								type: "file",
								class: "hidden",
								onChange: updatePhotoPreview
							}, null, 544),
							createVNode(_sfc_main$5, {
								for: "photo",
								value: "Photo"
							}),
							withDirectives(createVNode("div", { class: "mt-2" }, [createVNode("img", {
								src: __props.user.profile_photo_url,
								alt: __props.user.name,
								class: "rounded-full h-20 w-20 object-cover"
							}, null, 8, ["src", "alt"])], 512), [[vShow, !photoPreview.value]]),
							withDirectives(createVNode("div", { class: "mt-2" }, [createVNode("span", {
								class: "block rounded-full w-20 h-20 bg-cover bg-no-repeat bg-center",
								style: "background-image: url('" + photoPreview.value + "');"
							}, null, 4)], 512), [[vShow, photoPreview.value]]),
							createVNode(_sfc_main$7, {
								class: "mt-2 me-2",
								type: "button",
								onClick: withModifiers(selectNewPhoto, ["prevent"])
							}, {
								default: withCtx(() => [createTextVNode(" Select A New Photo ")]),
								_: 1
							}),
							__props.user.profile_photo_path ? (openBlock(), createBlock(_sfc_main$7, {
								key: 0,
								type: "button",
								class: "mt-2",
								onClick: withModifiers(deletePhoto, ["prevent"])
							}, {
								default: withCtx(() => [createTextVNode(" Remove Photo ")]),
								_: 1
							})) : createCommentVNode("", true),
							createVNode(_sfc_main$3, {
								message: unref(form).errors.photo,
								class: "mt-2"
							}, null, 8, ["message"])
						])) : createCommentVNode("", true),
						createVNode("div", { class: "col-span-6 sm:col-span-4" }, [
							createVNode(_sfc_main$5, {
								for: "name",
								value: "Name"
							}),
							createVNode(_sfc_main$4, {
								id: "name",
								modelValue: unref(form).name,
								"onUpdate:modelValue": ($event) => unref(form).name = $event,
								type: "text",
								class: "mt-1 block w-full",
								required: "",
								autocomplete: "name"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$3, {
								message: unref(form).errors.name,
								class: "mt-2"
							}, null, 8, ["message"])
						]),
						createVNode("div", { class: "col-span-6 sm:col-span-4" }, [
							createVNode(_sfc_main$5, {
								for: "email",
								value: "Email"
							}),
							createVNode(_sfc_main$4, {
								id: "email",
								modelValue: unref(form).email,
								"onUpdate:modelValue": ($event) => unref(form).email = $event,
								type: "email",
								class: "mt-1 block w-full",
								required: "",
								autocomplete: "username"
							}, null, 8, ["modelValue", "onUpdate:modelValue"]),
							createVNode(_sfc_main$3, {
								message: unref(form).errors.email,
								class: "mt-2"
							}, null, 8, ["message"]),
							_ctx.$page.props.jetstream.hasEmailVerification && __props.user.email_verified_at === null ? (openBlock(), createBlock("div", { key: 0 }, [createVNode("p", { class: "text-sm mt-2" }, [createTextVNode(" Your email address is unverified. "), createVNode(unref(Link), {
								href: _ctx.route("verification.send"),
								method: "post",
								as: "button",
								class: "underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500",
								onClick: withModifiers(sendEmailVerification, ["prevent"])
							}, {
								default: withCtx(() => [createTextVNode(" Click here to re-send the verification email. ")]),
								_: 1
							}, 8, ["href"])]), withDirectives(createVNode("div", { class: "mt-2 font-medium text-sm text-green-600" }, " A new verification link has been sent to your email address. ", 512), [[vShow, verificationLinkSent.value]])])) : createCommentVNode("", true)
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=UpdateProfileInformationForm-B7C5hZIB.js.map