import { $ as VAvatar, C as VRow, D as VDataTable, F as VCardText, I as VCardTitle, P as VCard, R as VCardActions, S as VSpacer, T as VContainer, U as VTextField, X as VChip, et as VAlert, g as VFileInput, lt as VImg, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import { t as _sfc_main$3 } from "./VerwalterLayout-BLmFLvbQ.js";
import "./consts-BmbOcoGu.js";
import { t as useCommodities } from "./useCommodities-CDCgIifs.js";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, mergeProps, onMounted, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, watch, withCtx } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderAttr, ssrRenderComponent, ssrRenderList, ssrRenderStyle } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Composables/useCommodityMedia.js
function useCommodityMedia() {
	const media = ref([]);
	const loadingMedia = ref(false);
	const uploadingMedia = ref(false);
	const deletingMedia = ref(false);
	async function indexCommodityMedia(commodityId) {
		loadingMedia.value = true;
		try {
			media.value = (await axios.get(route("api.commodities.media.index", commodityId))).data.data || [];
			return media.value;
		} catch (error) {
			console.error("indexCommodityMedia error:", error);
			throw error;
		} finally {
			loadingMedia.value = false;
		}
	}
	async function uploadCommodityMedia(commodityId, files) {
		uploadingMedia.value = true;
		const formData = new FormData();
		Array.from(files || []).forEach((file) => {
			formData.append("files[]", file);
		});
		try {
			media.value = (await axios.post(route("api.commodities.media.store", commodityId), formData, { headers: { "Content-Type": "multipart/form-data" } })).data.data || [];
			return media.value;
		} catch (error) {
			console.error("uploadCommodityMedia error:", error);
			if (error.response) {
				console.error("status:", error.response.status);
				console.error("data:", error.response.data);
			}
			throw error;
		} finally {
			uploadingMedia.value = false;
		}
	}
	async function renameCommodityMedia(commodityId, mediaId, filename) {
		try {
			return (await axios.patch(route("api.commodities.media.rename", {
				commodity: commodityId,
				media: mediaId
			}), { filename })).data.data;
		} catch (error) {
			console.error("renameCommodityMedia error:", error);
			throw error;
		}
	}
	async function setCommodityAva(commodityId, mediaId) {
		try {
			return (await axios.patch(route("api.commodities.media.ava", {
				commodity: commodityId,
				media: mediaId
			}))).data;
		} catch (error) {
			console.error("setCommodityAva error:", error);
			throw error;
		}
	}
	async function deleteCommodityMedia(commodityId, mediaId) {
		deletingMedia.value = true;
		try {
			await axios.delete(route("api.commodities.media.destroy", {
				commodity: commodityId,
				media: mediaId
			}));
		} catch (error) {
			console.error("deleteCommodityMedia error:", error);
			throw error;
		} finally {
			deletingMedia.value = false;
		}
	}
	return {
		media,
		loadingMedia,
		uploadingMedia,
		deletingMedia,
		indexCommodityMedia,
		uploadCommodityMedia,
		renameCommodityMedia,
		setCommodityAva,
		deleteCommodityMedia
	};
}
//#endregion
//#region resources/js/Components/Dictionaries/Commodities/CommodityMediaManager.vue
var _sfc_main$2 = {
	__name: "CommodityMediaManager",
	__ssrInlineRender: true,
	props: {
		commodityId: {
			type: Number,
			required: true
		},
		items: {
			type: Array,
			default: () => []
		}
	},
	emits: ["refresh"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const { media, loadingMedia, uploadingMedia, deletingMedia, indexCommodityMedia, uploadCommodityMedia, renameCommodityMedia, setCommodityAva, deleteCommodityMedia } = useCommodityMedia();
		const files = ref([]);
		const renameDialog = ref(false);
		const renameItem = ref(null);
		const renameFilename = ref("");
		watch(() => props.items, (value) => {
			media.value = value || [];
		}, {
			immediate: true,
			deep: true
		});
		async function refresh() {
			await indexCommodityMedia(props.commodityId);
			emit("refresh");
		}
		async function upload() {
			if (!files.value?.length) return;
			await uploadCommodityMedia(props.commodityId, files.value);
			files.value = [];
			emit("refresh");
		}
		function openRename(item) {
			renameItem.value = item;
			renameFilename.value = item.filename;
			renameDialog.value = true;
		}
		async function rename() {
			if (!renameItem.value?.id) return;
			await renameCommodityMedia(props.commodityId, renameItem.value.id, renameFilename.value);
			renameDialog.value = false;
			renameItem.value = null;
			renameFilename.value = "";
			await refresh();
		}
		async function makeAva(item) {
			await setCommodityAva(props.commodityId, item.id);
			await refresh();
		}
		async function remove(item) {
			if (!confirm(`Удалить изображение "${item.filename}"?`)) return;
			await deleteCommodityMedia(props.commodityId, item.id);
			await refresh();
		}
		function formatSize(size) {
			if (!size) return "0 KB";
			if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`;
			return `${(size / 1024 / 1024).toFixed(2)} MB`;
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(VCard, { class: "border rounded" }, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<span${_scopeId}>Media Commodity</span>`);
									_push(ssrRenderComponent(VBtn, {
										icon: "mdi-refresh",
										variant: "text",
										density: "compact",
										loading: unref(loadingMedia),
										onClick: refresh
									}, null, _parent, _scopeId));
								} else return [createVNode("span", null, "Media Commodity"), createVNode(VBtn, {
									icon: "mdi-refresh",
									variant: "text",
									density: "compact",
									loading: unref(loadingMedia),
									onClick: refresh
								}, null, 8, ["loading"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VRow, { align: "center" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "9"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VFileInput, {
															modelValue: files.value,
															"onUpdate:modelValue": ($event) => files.value = $event,
															label: "Добавить изображения",
															accept: "image/*",
															multiple: "",
															"show-size": "",
															clearable: "",
															variant: "outlined",
															density: "compact",
															"hide-details": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VFileInput, {
															modelValue: files.value,
															"onUpdate:modelValue": ($event) => files.value = $event,
															label: "Добавить изображения",
															accept: "image/*",
															multiple: "",
															"show-size": "",
															clearable: "",
															variant: "outlined",
															density: "compact",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VBtn, {
															block: "",
															text: "Загрузить",
															color: "primary",
															variant: "elevated",
															loading: unref(uploadingMedia),
															disabled: !files.value?.length,
															onClick: upload
														}, null, _parent, _scopeId));
														else return [createVNode(VBtn, {
															block: "",
															text: "Загрузить",
															color: "primary",
															variant: "elevated",
															loading: unref(uploadingMedia),
															disabled: !files.value?.length,
															onClick: upload
														}, null, 8, ["loading", "disabled"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [createVNode(VCol, {
												cols: "12",
												md: "9"
											}, {
												default: withCtx(() => [createVNode(VFileInput, {
													modelValue: files.value,
													"onUpdate:modelValue": ($event) => files.value = $event,
													label: "Добавить изображения",
													accept: "image/*",
													multiple: "",
													"show-size": "",
													clearable: "",
													variant: "outlined",
													density: "compact",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}), createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VBtn, {
													block: "",
													text: "Загрузить",
													color: "primary",
													variant: "elevated",
													loading: unref(uploadingMedia),
													disabled: !files.value?.length,
													onClick: upload
												}, null, 8, ["loading", "disabled"])]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VRow, { class: "mt-4" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(`<!--[-->`);
												ssrRenderList(unref(media), (item) => {
													_push(ssrRenderComponent(VCol, {
														key: item.id,
														cols: "12",
														sm: "6",
														md: "4",
														lg: "3"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VCard, { class: ["h-100 border rounded", { "border-primary": item.is_ava }] }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VImg, {
																			src: item.url,
																			height: "160",
																			cover: "",
																			class: "bg-grey-lighten-3"
																		}, null, _parent, _scopeId));
																		_push(ssrRenderComponent(VCardText, { class: "py-2" }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(`<div class="d-flex align-center ga-2"${_scopeId}>`);
																					if (item.is_ava) _push(ssrRenderComponent(VChip, {
																						size: "x-small",
																						color: "primary",
																						variant: "elevated"
																					}, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(` ava `);
																							else return [createTextVNode(" ava ")];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					else _push(`<!---->`);
																					_push(`<span class="text-caption text-truncate"${_scopeId}>${ssrInterpolate(item.filename)}</span></div><div class="text-caption text-grey mt-1"${_scopeId}>${ssrInterpolate(formatSize(item.size))}</div>`);
																				} else return [createVNode("div", { class: "d-flex align-center ga-2" }, [item.is_ava ? (openBlock(), createBlock(VChip, {
																					key: 0,
																					size: "x-small",
																					color: "primary",
																					variant: "elevated"
																				}, {
																					default: withCtx(() => [createTextVNode(" ava ")]),
																					_: 1
																				})) : createCommentVNode("", true), createVNode("span", { class: "text-caption text-truncate" }, toDisplayString(item.filename), 1)]), createVNode("div", { class: "text-caption text-grey mt-1" }, toDisplayString(formatSize(item.size)), 1)];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCardActions, { class: "pt-0" }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(ssrRenderComponent(VBtn, {
																						text: "ava",
																						size: "small",
																						variant: "tonal",
																						color: "primary",
																						disabled: item.is_ava,
																						onClick: ($event) => makeAva(item)
																					}, null, _parent, _scopeId));
																					_push(ssrRenderComponent(VBtn, {
																						icon: "mdi-pencil",
																						size: "small",
																						variant: "text",
																						onClick: ($event) => openRename(item)
																					}, null, _parent, _scopeId));
																					_push(ssrRenderComponent(VSpacer, null, null, _parent, _scopeId));
																					_push(ssrRenderComponent(VBtn, {
																						icon: "mdi-delete",
																						size: "small",
																						variant: "text",
																						color: "error",
																						loading: unref(deletingMedia),
																						onClick: ($event) => remove(item)
																					}, null, _parent, _scopeId));
																				} else return [
																					createVNode(VBtn, {
																						text: "ava",
																						size: "small",
																						variant: "tonal",
																						color: "primary",
																						disabled: item.is_ava,
																						onClick: ($event) => makeAva(item)
																					}, null, 8, ["disabled", "onClick"]),
																					createVNode(VBtn, {
																						icon: "mdi-pencil",
																						size: "small",
																						variant: "text",
																						onClick: ($event) => openRename(item)
																					}, null, 8, ["onClick"]),
																					createVNode(VSpacer),
																					createVNode(VBtn, {
																						icon: "mdi-delete",
																						size: "small",
																						variant: "text",
																						color: "error",
																						loading: unref(deletingMedia),
																						onClick: ($event) => remove(item)
																					}, null, 8, ["loading", "onClick"])
																				];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																	} else return [
																		createVNode(VImg, {
																			src: item.url,
																			height: "160",
																			cover: "",
																			class: "bg-grey-lighten-3"
																		}, null, 8, ["src"]),
																		createVNode(VCardText, { class: "py-2" }, {
																			default: withCtx(() => [createVNode("div", { class: "d-flex align-center ga-2" }, [item.is_ava ? (openBlock(), createBlock(VChip, {
																				key: 0,
																				size: "x-small",
																				color: "primary",
																				variant: "elevated"
																			}, {
																				default: withCtx(() => [createTextVNode(" ava ")]),
																				_: 1
																			})) : createCommentVNode("", true), createVNode("span", { class: "text-caption text-truncate" }, toDisplayString(item.filename), 1)]), createVNode("div", { class: "text-caption text-grey mt-1" }, toDisplayString(formatSize(item.size)), 1)]),
																			_: 2
																		}, 1024),
																		createVNode(VCardActions, { class: "pt-0" }, {
																			default: withCtx(() => [
																				createVNode(VBtn, {
																					text: "ava",
																					size: "small",
																					variant: "tonal",
																					color: "primary",
																					disabled: item.is_ava,
																					onClick: ($event) => makeAva(item)
																				}, null, 8, ["disabled", "onClick"]),
																				createVNode(VBtn, {
																					icon: "mdi-pencil",
																					size: "small",
																					variant: "text",
																					onClick: ($event) => openRename(item)
																				}, null, 8, ["onClick"]),
																				createVNode(VSpacer),
																				createVNode(VBtn, {
																					icon: "mdi-delete",
																					size: "small",
																					variant: "text",
																					color: "error",
																					loading: unref(deletingMedia),
																					onClick: ($event) => remove(item)
																				}, null, 8, ["loading", "onClick"])
																			]),
																			_: 2
																		}, 1024)
																	];
																}),
																_: 2
															}, _parent, _scopeId));
															else return [createVNode(VCard, { class: ["h-100 border rounded", { "border-primary": item.is_ava }] }, {
																default: withCtx(() => [
																	createVNode(VImg, {
																		src: item.url,
																		height: "160",
																		cover: "",
																		class: "bg-grey-lighten-3"
																	}, null, 8, ["src"]),
																	createVNode(VCardText, { class: "py-2" }, {
																		default: withCtx(() => [createVNode("div", { class: "d-flex align-center ga-2" }, [item.is_ava ? (openBlock(), createBlock(VChip, {
																			key: 0,
																			size: "x-small",
																			color: "primary",
																			variant: "elevated"
																		}, {
																			default: withCtx(() => [createTextVNode(" ava ")]),
																			_: 1
																		})) : createCommentVNode("", true), createVNode("span", { class: "text-caption text-truncate" }, toDisplayString(item.filename), 1)]), createVNode("div", { class: "text-caption text-grey mt-1" }, toDisplayString(formatSize(item.size)), 1)]),
																		_: 2
																	}, 1024),
																	createVNode(VCardActions, { class: "pt-0" }, {
																		default: withCtx(() => [
																			createVNode(VBtn, {
																				text: "ava",
																				size: "small",
																				variant: "tonal",
																				color: "primary",
																				disabled: item.is_ava,
																				onClick: ($event) => makeAva(item)
																			}, null, 8, ["disabled", "onClick"]),
																			createVNode(VBtn, {
																				icon: "mdi-pencil",
																				size: "small",
																				variant: "text",
																				onClick: ($event) => openRename(item)
																			}, null, 8, ["onClick"]),
																			createVNode(VSpacer),
																			createVNode(VBtn, {
																				icon: "mdi-delete",
																				size: "small",
																				variant: "text",
																				color: "error",
																				loading: unref(deletingMedia),
																				onClick: ($event) => remove(item)
																			}, null, 8, ["loading", "onClick"])
																		]),
																		_: 2
																	}, 1024)
																]),
																_: 2
															}, 1032, ["class"])];
														}),
														_: 2
													}, _parent, _scopeId));
												});
												_push(`<!--]-->`);
												if (!unref(media).length) _push(ssrRenderComponent(VCol, { cols: "12" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VAlert, {
															type: "info",
															variant: "tonal",
															density: "compact"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Изображений пока нет. `);
																else return [createTextVNode(" Изображений пока нет. ")];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VAlert, {
															type: "info",
															variant: "tonal",
															density: "compact"
														}, {
															default: withCtx(() => [createTextVNode(" Изображений пока нет. ")]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												else _push(`<!---->`);
											} else return [(openBlock(true), createBlock(Fragment, null, renderList(unref(media), (item) => {
												return openBlock(), createBlock(VCol, {
													key: item.id,
													cols: "12",
													sm: "6",
													md: "4",
													lg: "3"
												}, {
													default: withCtx(() => [createVNode(VCard, { class: ["h-100 border rounded", { "border-primary": item.is_ava }] }, {
														default: withCtx(() => [
															createVNode(VImg, {
																src: item.url,
																height: "160",
																cover: "",
																class: "bg-grey-lighten-3"
															}, null, 8, ["src"]),
															createVNode(VCardText, { class: "py-2" }, {
																default: withCtx(() => [createVNode("div", { class: "d-flex align-center ga-2" }, [item.is_ava ? (openBlock(), createBlock(VChip, {
																	key: 0,
																	size: "x-small",
																	color: "primary",
																	variant: "elevated"
																}, {
																	default: withCtx(() => [createTextVNode(" ava ")]),
																	_: 1
																})) : createCommentVNode("", true), createVNode("span", { class: "text-caption text-truncate" }, toDisplayString(item.filename), 1)]), createVNode("div", { class: "text-caption text-grey mt-1" }, toDisplayString(formatSize(item.size)), 1)]),
																_: 2
															}, 1024),
															createVNode(VCardActions, { class: "pt-0" }, {
																default: withCtx(() => [
																	createVNode(VBtn, {
																		text: "ava",
																		size: "small",
																		variant: "tonal",
																		color: "primary",
																		disabled: item.is_ava,
																		onClick: ($event) => makeAva(item)
																	}, null, 8, ["disabled", "onClick"]),
																	createVNode(VBtn, {
																		icon: "mdi-pencil",
																		size: "small",
																		variant: "text",
																		onClick: ($event) => openRename(item)
																	}, null, 8, ["onClick"]),
																	createVNode(VSpacer),
																	createVNode(VBtn, {
																		icon: "mdi-delete",
																		size: "small",
																		variant: "text",
																		color: "error",
																		loading: unref(deletingMedia),
																		onClick: ($event) => remove(item)
																	}, null, 8, ["loading", "onClick"])
																]),
																_: 2
															}, 1024)
														]),
														_: 2
													}, 1032, ["class"])]),
													_: 2
												}, 1024);
											}), 128)), !unref(media).length ? (openBlock(), createBlock(VCol, {
												key: 0,
												cols: "12"
											}, {
												default: withCtx(() => [createVNode(VAlert, {
													type: "info",
													variant: "tonal",
													density: "compact"
												}, {
													default: withCtx(() => [createTextVNode(" Изображений пока нет. ")]),
													_: 1
												})]),
												_: 1
											})) : createCommentVNode("", true)];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VRow, { align: "center" }, {
									default: withCtx(() => [createVNode(VCol, {
										cols: "12",
										md: "9"
									}, {
										default: withCtx(() => [createVNode(VFileInput, {
											modelValue: files.value,
											"onUpdate:modelValue": ($event) => files.value = $event,
											label: "Добавить изображения",
											accept: "image/*",
											multiple: "",
											"show-size": "",
											clearable: "",
											variant: "outlined",
											density: "compact",
											"hide-details": ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}), createVNode(VCol, {
										cols: "12",
										md: "3"
									}, {
										default: withCtx(() => [createVNode(VBtn, {
											block: "",
											text: "Загрузить",
											color: "primary",
											variant: "elevated",
											loading: unref(uploadingMedia),
											disabled: !files.value?.length,
											onClick: upload
										}, null, 8, ["loading", "disabled"])]),
										_: 1
									})]),
									_: 1
								}), createVNode(VRow, { class: "mt-4" }, {
									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(media), (item) => {
										return openBlock(), createBlock(VCol, {
											key: item.id,
											cols: "12",
											sm: "6",
											md: "4",
											lg: "3"
										}, {
											default: withCtx(() => [createVNode(VCard, { class: ["h-100 border rounded", { "border-primary": item.is_ava }] }, {
												default: withCtx(() => [
													createVNode(VImg, {
														src: item.url,
														height: "160",
														cover: "",
														class: "bg-grey-lighten-3"
													}, null, 8, ["src"]),
													createVNode(VCardText, { class: "py-2" }, {
														default: withCtx(() => [createVNode("div", { class: "d-flex align-center ga-2" }, [item.is_ava ? (openBlock(), createBlock(VChip, {
															key: 0,
															size: "x-small",
															color: "primary",
															variant: "elevated"
														}, {
															default: withCtx(() => [createTextVNode(" ava ")]),
															_: 1
														})) : createCommentVNode("", true), createVNode("span", { class: "text-caption text-truncate" }, toDisplayString(item.filename), 1)]), createVNode("div", { class: "text-caption text-grey mt-1" }, toDisplayString(formatSize(item.size)), 1)]),
														_: 2
													}, 1024),
													createVNode(VCardActions, { class: "pt-0" }, {
														default: withCtx(() => [
															createVNode(VBtn, {
																text: "ava",
																size: "small",
																variant: "tonal",
																color: "primary",
																disabled: item.is_ava,
																onClick: ($event) => makeAva(item)
															}, null, 8, ["disabled", "onClick"]),
															createVNode(VBtn, {
																icon: "mdi-pencil",
																size: "small",
																variant: "text",
																onClick: ($event) => openRename(item)
															}, null, 8, ["onClick"]),
															createVNode(VSpacer),
															createVNode(VBtn, {
																icon: "mdi-delete",
																size: "small",
																variant: "text",
																color: "error",
																loading: unref(deletingMedia),
																onClick: ($event) => remove(item)
															}, null, 8, ["loading", "onClick"])
														]),
														_: 2
													}, 1024)
												]),
												_: 2
											}, 1032, ["class"])]),
											_: 2
										}, 1024);
									}), 128)), !unref(media).length ? (openBlock(), createBlock(VCol, {
										key: 0,
										cols: "12"
									}, {
										default: withCtx(() => [createVNode(VAlert, {
											type: "info",
											variant: "tonal",
											density: "compact"
										}, {
											default: withCtx(() => [createTextVNode(" Изображений пока нет. ")]),
											_: 1
										})]),
										_: 1
									})) : createCommentVNode("", true)]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
						default: withCtx(() => [createVNode("span", null, "Media Commodity"), createVNode(VBtn, {
							icon: "mdi-refresh",
							variant: "text",
							density: "compact",
							loading: unref(loadingMedia),
							onClick: refresh
						}, null, 8, ["loading"])]),
						_: 1
					}), createVNode(VCardText, null, {
						default: withCtx(() => [createVNode(VRow, { align: "center" }, {
							default: withCtx(() => [createVNode(VCol, {
								cols: "12",
								md: "9"
							}, {
								default: withCtx(() => [createVNode(VFileInput, {
									modelValue: files.value,
									"onUpdate:modelValue": ($event) => files.value = $event,
									label: "Добавить изображения",
									accept: "image/*",
									multiple: "",
									"show-size": "",
									clearable: "",
									variant: "outlined",
									density: "compact",
									"hide-details": ""
								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
								_: 1
							}), createVNode(VCol, {
								cols: "12",
								md: "3"
							}, {
								default: withCtx(() => [createVNode(VBtn, {
									block: "",
									text: "Загрузить",
									color: "primary",
									variant: "elevated",
									loading: unref(uploadingMedia),
									disabled: !files.value?.length,
									onClick: upload
								}, null, 8, ["loading", "disabled"])]),
								_: 1
							})]),
							_: 1
						}), createVNode(VRow, { class: "mt-4" }, {
							default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(media), (item) => {
								return openBlock(), createBlock(VCol, {
									key: item.id,
									cols: "12",
									sm: "6",
									md: "4",
									lg: "3"
								}, {
									default: withCtx(() => [createVNode(VCard, { class: ["h-100 border rounded", { "border-primary": item.is_ava }] }, {
										default: withCtx(() => [
											createVNode(VImg, {
												src: item.url,
												height: "160",
												cover: "",
												class: "bg-grey-lighten-3"
											}, null, 8, ["src"]),
											createVNode(VCardText, { class: "py-2" }, {
												default: withCtx(() => [createVNode("div", { class: "d-flex align-center ga-2" }, [item.is_ava ? (openBlock(), createBlock(VChip, {
													key: 0,
													size: "x-small",
													color: "primary",
													variant: "elevated"
												}, {
													default: withCtx(() => [createTextVNode(" ava ")]),
													_: 1
												})) : createCommentVNode("", true), createVNode("span", { class: "text-caption text-truncate" }, toDisplayString(item.filename), 1)]), createVNode("div", { class: "text-caption text-grey mt-1" }, toDisplayString(formatSize(item.size)), 1)]),
												_: 2
											}, 1024),
											createVNode(VCardActions, { class: "pt-0" }, {
												default: withCtx(() => [
													createVNode(VBtn, {
														text: "ava",
														size: "small",
														variant: "tonal",
														color: "primary",
														disabled: item.is_ava,
														onClick: ($event) => makeAva(item)
													}, null, 8, ["disabled", "onClick"]),
													createVNode(VBtn, {
														icon: "mdi-pencil",
														size: "small",
														variant: "text",
														onClick: ($event) => openRename(item)
													}, null, 8, ["onClick"]),
													createVNode(VSpacer),
													createVNode(VBtn, {
														icon: "mdi-delete",
														size: "small",
														variant: "text",
														color: "error",
														loading: unref(deletingMedia),
														onClick: ($event) => remove(item)
													}, null, 8, ["loading", "onClick"])
												]),
												_: 2
											}, 1024)
										]),
										_: 2
									}, 1032, ["class"])]),
									_: 2
								}, 1024);
							}), 128)), !unref(media).length ? (openBlock(), createBlock(VCol, {
								key: 0,
								cols: "12"
							}, {
								default: withCtx(() => [createVNode(VAlert, {
									type: "info",
									variant: "tonal",
									density: "compact"
								}, {
									default: withCtx(() => [createTextVNode(" Изображений пока нет. ")]),
									_: 1
								})]),
								_: 1
							})) : createCommentVNode("", true)]),
							_: 1
						})]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
			_push(ssrRenderComponent(VDialog, {
				modelValue: renameDialog.value,
				"onUpdate:modelValue": ($event) => renameDialog.value = $event,
				width: "560"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VCard, null, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCardTitle, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(`Переименовать файл`);
										else return [createTextVNode("Переименовать файл")];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCardText, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VTextField, {
											modelValue: renameFilename.value,
											"onUpdate:modelValue": ($event) => renameFilename.value = $event,
											label: "Новое имя файла",
											variant: "outlined",
											density: "compact",
											"hide-details": ""
										}, null, _parent, _scopeId));
										else return [createVNode(VTextField, {
											modelValue: renameFilename.value,
											"onUpdate:modelValue": ($event) => renameFilename.value = $event,
											label: "Новое имя файла",
											variant: "outlined",
											density: "compact",
											"hide-details": ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"])];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCardActions, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VSpacer, null, null, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												text: "Отмена",
												variant: "text",
												onClick: ($event) => renameDialog.value = false
											}, null, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												text: "Сохранить",
												color: "primary",
												variant: "elevated",
												onClick: rename
											}, null, _parent, _scopeId));
										} else return [
											createVNode(VSpacer),
											createVNode(VBtn, {
												text: "Отмена",
												variant: "text",
												onClick: ($event) => renameDialog.value = false
											}, null, 8, ["onClick"]),
											createVNode(VBtn, {
												text: "Сохранить",
												color: "primary",
												variant: "elevated",
												onClick: rename
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCardTitle, null, {
									default: withCtx(() => [createTextVNode("Переименовать файл")]),
									_: 1
								}),
								createVNode(VCardText, null, {
									default: withCtx(() => [createVNode(VTextField, {
										modelValue: renameFilename.value,
										"onUpdate:modelValue": ($event) => renameFilename.value = $event,
										label: "Новое имя файла",
										variant: "outlined",
										density: "compact",
										"hide-details": ""
									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
									_: 1
								}),
								createVNode(VCardActions, null, {
									default: withCtx(() => [
										createVNode(VSpacer),
										createVNode(VBtn, {
											text: "Отмена",
											variant: "text",
											onClick: ($event) => renameDialog.value = false
										}, null, 8, ["onClick"]),
										createVNode(VBtn, {
											text: "Сохранить",
											color: "primary",
											variant: "elevated",
											onClick: rename
										})
									]),
									_: 1
								})
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VCard, null, {
						default: withCtx(() => [
							createVNode(VCardTitle, null, {
								default: withCtx(() => [createTextVNode("Переименовать файл")]),
								_: 1
							}),
							createVNode(VCardText, null, {
								default: withCtx(() => [createVNode(VTextField, {
									modelValue: renameFilename.value,
									"onUpdate:modelValue": ($event) => renameFilename.value = $event,
									label: "Новое имя файла",
									variant: "outlined",
									density: "compact",
									"hide-details": ""
								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
								_: 1
							}),
							createVNode(VCardActions, null, {
								default: withCtx(() => [
									createVNode(VSpacer),
									createVNode(VBtn, {
										text: "Отмена",
										variant: "text",
										onClick: ($event) => renameDialog.value = false
									}, null, 8, ["onClick"]),
									createVNode(VBtn, {
										text: "Сохранить",
										color: "primary",
										variant: "elevated",
										onClick: rename
									})
								]),
								_: 1
							})
						]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
			_push(`<!--]-->`);
		};
	}
};
var _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Dictionaries/Commodities/CommodityMediaManager.vue");
	return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Dictionaries/Commodities/CommodityShowPage.vue
var _sfc_main$1 = {
	__name: "CommodityShowPage",
	__ssrInlineRender: true,
	props: { commodityId: {
		type: Number,
		required: true
	} },
	setup(__props) {
		const props = __props;
		const { commodity, history, stats, loadingCommodities, savingCommodity, showCommodity, updateCommodity } = useCommodities();
		const editName = ref("");
		const nameEditing = ref(false);
		const historyHeaders = [
			{
				title: "Дата",
				key: "check_date"
			},
			{
				title: "Поставщик",
				key: "entity_name"
			},
			{
				title: "Кол-во",
				key: "quantity"
			},
			{
				title: "Ед.",
				key: "measure_name"
			},
			{
				title: "Цена",
				key: "price"
			},
			{
				title: "Сумма",
				key: "total_price"
			},
			{
				title: "Check",
				key: "check_id"
			}
		];
		const priceHistoryAsc = computed(() => {
			return [...history.value].filter((item) => item.price !== null && item.price !== void 0).sort((a, b) => new Date(a.check_date) - new Date(b.check_date));
		});
		const maxPrice = computed(() => {
			const values = priceHistoryAsc.value.map((item) => Number(item.price || 0));
			return Math.max(...values, 1);
		});
		const latestPrice = computed(() => {
			return priceHistoryAsc.value.at(-1)?.price || null;
		});
		const minPrice = computed(() => {
			const values = priceHistoryAsc.value.map((item) => Number(item.price || 0));
			if (!values.length) return null;
			return Math.min(...values);
		});
		const maxMonthlyTotal = computed(() => {
			const values = stats.value.monthly.map((item) => Number(item.total || 0));
			return Math.max(...values, 1);
		});
		function money(value) {
			const number = Number(value || 0);
			return new Intl.NumberFormat("ru-RU", {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			}).format(number);
		}
		function formatDate(value) {
			if (!value) return "—";
			return new Intl.DateTimeFormat("ru-RU", { dateStyle: "medium" }).format(new Date(value));
		}
		async function refresh() {
			editName.value = (await showCommodity(props.commodityId)).data?.name || "";
		}
		function startEditName() {
			editName.value = commodity.value?.name || "";
			nameEditing.value = true;
		}
		async function saveName() {
			await updateCommodity(props.commodityId, { name: editName.value });
			nameEditing.value = false;
			await refresh();
		}
		onMounted(refresh);
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, {
							align: "center",
							class: "mb-4"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(unref(Link), {
													href: unref(route)("Ameise.commodities"),
													class: "text-teal-lighten-2 text-caption"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` ← Commodities `);
														else return [createTextVNode(" ← Commodities ")];
													}),
													_: 1
												}, _parent, _scopeId));
												if (unref(commodity)) {
													_push(`<div class="d-flex align-center ga-3 mt-2"${_scopeId}>`);
													_push(ssrRenderComponent(VAvatar, {
														size: "72",
														rounded: "lg"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VImg, {
																src: unref(commodity).ava_url || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
																cover: ""
															}, null, _parent, _scopeId));
															else return [createVNode(VImg, {
																src: unref(commodity).ava_url || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
																cover: ""
															}, null, 8, ["src"])];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(`<div class="flex-grow-1"${_scopeId}>`);
													if (!nameEditing.value) {
														_push(`<div class="d-flex align-center ga-2"${_scopeId}><h1 class="text-h5 mb-0"${_scopeId}>${ssrInterpolate(unref(commodity).name)}</h1>`);
														_push(ssrRenderComponent(VBtn, {
															icon: "mdi-pencil",
															variant: "text",
															density: "compact",
															onClick: startEditName
														}, null, _parent, _scopeId));
														_push(`</div>`);
													} else {
														_push(`<div class="d-flex align-center ga-2"${_scopeId}>`);
														_push(ssrRenderComponent(VTextField, {
															modelValue: editName.value,
															"onUpdate:modelValue": ($event) => editName.value = $event,
															variant: "outlined",
															density: "compact",
															"hide-details": "",
															style: { "max-width": "520px" }
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															icon: "mdi-check",
															color: "success",
															variant: "tonal",
															density: "compact",
															loading: unref(savingCommodity),
															onClick: saveName
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															icon: "mdi-close",
															variant: "text",
															density: "compact",
															onClick: ($event) => nameEditing.value = false
														}, null, _parent, _scopeId));
														_push(`</div>`);
													}
													_push(`<div class="text-caption text-grey"${_scopeId}> ID: ${ssrInterpolate(unref(commodity).id)} · Checks: ${ssrInterpolate(unref(commodity).checks_count)} · Media: ${ssrInterpolate(unref(commodity).media_count)}</div></div></div>`);
												} else _push(`<!---->`);
											} else return [createVNode(unref(Link), {
												href: unref(route)("Ameise.commodities"),
												class: "text-teal-lighten-2 text-caption"
											}, {
												default: withCtx(() => [createTextVNode(" ← Commodities ")]),
												_: 1
											}, 8, ["href"]), unref(commodity) ? (openBlock(), createBlock("div", {
												key: 0,
												class: "d-flex align-center ga-3 mt-2"
											}, [createVNode(VAvatar, {
												size: "72",
												rounded: "lg"
											}, {
												default: withCtx(() => [createVNode(VImg, {
													src: unref(commodity).ava_url || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
													cover: ""
												}, null, 8, ["src"])]),
												_: 1
											}), createVNode("div", { class: "flex-grow-1" }, [!nameEditing.value ? (openBlock(), createBlock("div", {
												key: 0,
												class: "d-flex align-center ga-2"
											}, [createVNode("h1", { class: "text-h5 mb-0" }, toDisplayString(unref(commodity).name), 1), createVNode(VBtn, {
												icon: "mdi-pencil",
												variant: "text",
												density: "compact",
												onClick: startEditName
											})])) : (openBlock(), createBlock("div", {
												key: 1,
												class: "d-flex align-center ga-2"
											}, [
												createVNode(VTextField, {
													modelValue: editName.value,
													"onUpdate:modelValue": ($event) => editName.value = $event,
													variant: "outlined",
													density: "compact",
													"hide-details": "",
													style: { "max-width": "520px" }
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VBtn, {
													icon: "mdi-check",
													color: "success",
													variant: "tonal",
													density: "compact",
													loading: unref(savingCommodity),
													onClick: saveName
												}, null, 8, ["loading"]),
												createVNode(VBtn, {
													icon: "mdi-close",
													variant: "text",
													density: "compact",
													onClick: ($event) => nameEditing.value = false
												}, null, 8, ["onClick"])
											])), createVNode("div", { class: "text-caption text-grey" }, " ID: " + toDisplayString(unref(commodity).id) + " · Checks: " + toDisplayString(unref(commodity).checks_count) + " · Media: " + toDisplayString(unref(commodity).media_count), 1)])])) : createCommentVNode("", true)];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "auto" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VBtn, {
												icon: "mdi-refresh",
												variant: "tonal",
												loading: unref(loadingCommodities),
												onClick: refresh
											}, null, _parent, _scopeId));
											else return [createVNode(VBtn, {
												icon: "mdi-refresh",
												variant: "tonal",
												loading: unref(loadingCommodities),
												onClick: refresh
											}, null, 8, ["loading"])];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, null, {
									default: withCtx(() => [createVNode(unref(Link), {
										href: unref(route)("Ameise.commodities"),
										class: "text-teal-lighten-2 text-caption"
									}, {
										default: withCtx(() => [createTextVNode(" ← Commodities ")]),
										_: 1
									}, 8, ["href"]), unref(commodity) ? (openBlock(), createBlock("div", {
										key: 0,
										class: "d-flex align-center ga-3 mt-2"
									}, [createVNode(VAvatar, {
										size: "72",
										rounded: "lg"
									}, {
										default: withCtx(() => [createVNode(VImg, {
											src: unref(commodity).ava_url || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
											cover: ""
										}, null, 8, ["src"])]),
										_: 1
									}), createVNode("div", { class: "flex-grow-1" }, [!nameEditing.value ? (openBlock(), createBlock("div", {
										key: 0,
										class: "d-flex align-center ga-2"
									}, [createVNode("h1", { class: "text-h5 mb-0" }, toDisplayString(unref(commodity).name), 1), createVNode(VBtn, {
										icon: "mdi-pencil",
										variant: "text",
										density: "compact",
										onClick: startEditName
									})])) : (openBlock(), createBlock("div", {
										key: 1,
										class: "d-flex align-center ga-2"
									}, [
										createVNode(VTextField, {
											modelValue: editName.value,
											"onUpdate:modelValue": ($event) => editName.value = $event,
											variant: "outlined",
											density: "compact",
											"hide-details": "",
											style: { "max-width": "520px" }
										}, null, 8, ["modelValue", "onUpdate:modelValue"]),
										createVNode(VBtn, {
											icon: "mdi-check",
											color: "success",
											variant: "tonal",
											density: "compact",
											loading: unref(savingCommodity),
											onClick: saveName
										}, null, 8, ["loading"]),
										createVNode(VBtn, {
											icon: "mdi-close",
											variant: "text",
											density: "compact",
											onClick: ($event) => nameEditing.value = false
										}, null, 8, ["onClick"])
									])), createVNode("div", { class: "text-caption text-grey" }, " ID: " + toDisplayString(unref(commodity).id) + " · Checks: " + toDisplayString(unref(commodity).checks_count) + " · Media: " + toDisplayString(unref(commodity).media_count), 1)])])) : createCommentVNode("", true)]),
									_: 1
								}), createVNode(VCol, { cols: "auto" }, {
									default: withCtx(() => [createVNode(VBtn, {
										icon: "mdi-refresh",
										variant: "tonal",
										loading: unref(loadingCommodities),
										onClick: refresh
									}, null, 8, ["loading"])]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						if (unref(commodity)) _push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "5"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(_sfc_main$2, {
												"commodity-id": unref(commodity).id,
												items: unref(commodity).media || [],
												onRefresh: refresh
											}, null, _parent, _scopeId));
											else return [createVNode(_sfc_main$2, {
												"commodity-id": unref(commodity).id,
												items: unref(commodity).media || [],
												onRefresh: refresh
											}, null, 8, ["commodity-id", "items"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "7"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VRow, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VCard, { class: "border rounded" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VCardText, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(`<div class="text-caption text-grey"${_scopeId}> Последняя цена </div><div class="text-h5"${_scopeId}>${ssrInterpolate(latestPrice.value ? money(latestPrice.value) : "—")}</div>`);
																					else return [createVNode("div", { class: "text-caption text-grey" }, " Последняя цена "), createVNode("div", { class: "text-h5" }, toDisplayString(latestPrice.value ? money(latestPrice.value) : "—"), 1)];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			else return [createVNode(VCardText, null, {
																				default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Последняя цена "), createVNode("div", { class: "text-h5" }, toDisplayString(latestPrice.value ? money(latestPrice.value) : "—"), 1)]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VCard, { class: "border rounded" }, {
																		default: withCtx(() => [createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Последняя цена "), createVNode("div", { class: "text-h5" }, toDisplayString(latestPrice.value ? money(latestPrice.value) : "—"), 1)]),
																			_: 1
																		})]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VCard, { class: "border rounded" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VCardText, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(`<div class="text-caption text-grey"${_scopeId}> Минимальная цена </div><div class="text-h5"${_scopeId}>${ssrInterpolate(minPrice.value ? money(minPrice.value) : "—")}</div>`);
																					else return [createVNode("div", { class: "text-caption text-grey" }, " Минимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(minPrice.value ? money(minPrice.value) : "—"), 1)];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			else return [createVNode(VCardText, null, {
																				default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Минимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(minPrice.value ? money(minPrice.value) : "—"), 1)]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VCard, { class: "border rounded" }, {
																		default: withCtx(() => [createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Минимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(minPrice.value ? money(minPrice.value) : "—"), 1)]),
																			_: 1
																		})]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VCard, { class: "border rounded" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VCardText, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(`<div class="text-caption text-grey"${_scopeId}> Максимальная цена </div><div class="text-h5"${_scopeId}>${ssrInterpolate(maxPrice.value ? money(maxPrice.value) : "—")}</div>`);
																					else return [createVNode("div", { class: "text-caption text-grey" }, " Максимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(maxPrice.value ? money(maxPrice.value) : "—"), 1)];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			else return [createVNode(VCardText, null, {
																				default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Максимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(maxPrice.value ? money(maxPrice.value) : "—"), 1)]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VCard, { class: "border rounded" }, {
																		default: withCtx(() => [createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Максимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(maxPrice.value ? money(maxPrice.value) : "—"), 1)]),
																			_: 1
																		})]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
																	default: withCtx(() => [createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Последняя цена "), createVNode("div", { class: "text-h5" }, toDisplayString(latestPrice.value ? money(latestPrice.value) : "—"), 1)]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
																	default: withCtx(() => [createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Минимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(minPrice.value ? money(minPrice.value) : "—"), 1)]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
																	default: withCtx(() => [createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Максимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(maxPrice.value ? money(maxPrice.value) : "—"), 1)]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															})
														];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCard, { class: "border rounded mt-4" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCardTitle, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` График изменения цены `);
																	else return [createTextVNode(" График изменения цены ")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCardText, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) if (priceHistoryAsc.value.length) {
																		_push(`<div class="d-flex align-end ga-1 border rounded pa-3" style="${ssrRenderStyle({
																			"height": "180px",
																			"overflow-x": "auto"
																		})}"${_scopeId}><!--[-->`);
																		ssrRenderList(priceHistoryAsc.value, (point) => {
																			_push(`<div class="d-flex flex-column align-center" style="${ssrRenderStyle({
																				"min-width": "24px",
																				"height": "100%"
																			})}"${_scopeId}><div class="flex-grow-1 d-flex align-end"${_scopeId}><div class="bg-primary rounded-t"${ssrRenderAttr("title", `${formatDate(point.check_date)} — ${money(point.price)}`)} style="${ssrRenderStyle({
																				height: `${Math.max(6, Number(point.price || 0) / maxPrice.value * 130)}px`,
																				width: "16px"
																			})}"${_scopeId}></div></div><div class="text-caption text-grey mt-1" style="${ssrRenderStyle({ "font-size": "9px" })}"${_scopeId}>${ssrInterpolate(new Date(point.check_date).getFullYear())}</div></div>`);
																		});
																		_push(`<!--]--></div>`);
																	} else _push(ssrRenderComponent(VAlert, {
																		type: "info",
																		variant: "tonal",
																		density: "compact"
																	}, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(` Истории цен пока нет. `);
																			else return [createTextVNode(" Истории цен пока нет. ")];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [priceHistoryAsc.value.length ? (openBlock(), createBlock("div", {
																		key: 0,
																		class: "d-flex align-end ga-1 border rounded pa-3",
																		style: {
																			"height": "180px",
																			"overflow-x": "auto"
																		}
																	}, [(openBlock(true), createBlock(Fragment, null, renderList(priceHistoryAsc.value, (point) => {
																		return openBlock(), createBlock("div", {
																			key: `${point.check_id}-${point.id}`,
																			class: "d-flex flex-column align-center",
																			style: {
																				"min-width": "24px",
																				"height": "100%"
																			}
																		}, [createVNode("div", { class: "flex-grow-1 d-flex align-end" }, [createVNode("div", {
																			class: "bg-primary rounded-t",
																			title: `${formatDate(point.check_date)} — ${money(point.price)}`,
																			style: {
																				height: `${Math.max(6, Number(point.price || 0) / maxPrice.value * 130)}px`,
																				width: "16px"
																			}
																		}, null, 12, ["title"])]), createVNode("div", {
																			class: "text-caption text-grey mt-1",
																			style: { "font-size": "9px" }
																		}, toDisplayString(new Date(point.check_date).getFullYear()), 1)]);
																	}), 128))])) : (openBlock(), createBlock(VAlert, {
																		key: 1,
																		type: "info",
																		variant: "tonal",
																		density: "compact"
																	}, {
																		default: withCtx(() => [createTextVNode(" Истории цен пока нет. ")]),
																		_: 1
																	}))];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode(" График изменения цены ")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [priceHistoryAsc.value.length ? (openBlock(), createBlock("div", {
																key: 0,
																class: "d-flex align-end ga-1 border rounded pa-3",
																style: {
																	"height": "180px",
																	"overflow-x": "auto"
																}
															}, [(openBlock(true), createBlock(Fragment, null, renderList(priceHistoryAsc.value, (point) => {
																return openBlock(), createBlock("div", {
																	key: `${point.check_id}-${point.id}`,
																	class: "d-flex flex-column align-center",
																	style: {
																		"min-width": "24px",
																		"height": "100%"
																	}
																}, [createVNode("div", { class: "flex-grow-1 d-flex align-end" }, [createVNode("div", {
																	class: "bg-primary rounded-t",
																	title: `${formatDate(point.check_date)} — ${money(point.price)}`,
																	style: {
																		height: `${Math.max(6, Number(point.price || 0) / maxPrice.value * 130)}px`,
																		width: "16px"
																	}
																}, null, 12, ["title"])]), createVNode("div", {
																	class: "text-caption text-grey mt-1",
																	style: { "font-size": "9px" }
																}, toDisplayString(new Date(point.check_date).getFullYear()), 1)]);
															}), 128))])) : (openBlock(), createBlock(VAlert, {
																key: 1,
																type: "info",
																variant: "tonal",
																density: "compact"
															}, {
																default: withCtx(() => [createTextVNode(" Истории цен пока нет. ")]),
																_: 1
															}))]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCard, { class: "border rounded mt-4" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCardTitle, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` Потраченные суммы по месяцам `);
																	else return [createTextVNode(" Потраченные суммы по месяцам ")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCardText, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) if (unref(stats).monthly.length) {
																		_push(`<div class="d-flex align-end ga-2 border rounded pa-3" style="${ssrRenderStyle({
																			"height": "180px",
																			"overflow-x": "auto"
																		})}"${_scopeId}><!--[-->`);
																		ssrRenderList(unref(stats).monthly, (item) => {
																			_push(`<div class="d-flex flex-column align-center" style="${ssrRenderStyle({
																				"min-width": "52px",
																				"height": "100%"
																			})}"${_scopeId}><div class="flex-grow-1 d-flex align-end"${_scopeId}><div class="bg-deep-orange rounded-t"${ssrRenderAttr("title", `${item.period} — ${money(item.total)}`)} style="${ssrRenderStyle({
																				height: `${Math.max(6, Number(item.total || 0) / maxMonthlyTotal.value * 130)}px`,
																				width: "32px"
																			})}"${_scopeId}></div></div><div class="text-caption text-grey mt-1" style="${ssrRenderStyle({ "font-size": "10px" })}"${_scopeId}>${ssrInterpolate(item.period)}</div></div>`);
																		});
																		_push(`<!--]--></div>`);
																	} else _push(ssrRenderComponent(VAlert, {
																		type: "info",
																		variant: "tonal",
																		density: "compact"
																	}, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(` Данных по суммам пока нет. `);
																			else return [createTextVNode(" Данных по суммам пока нет. ")];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [unref(stats).monthly.length ? (openBlock(), createBlock("div", {
																		key: 0,
																		class: "d-flex align-end ga-2 border rounded pa-3",
																		style: {
																			"height": "180px",
																			"overflow-x": "auto"
																		}
																	}, [(openBlock(true), createBlock(Fragment, null, renderList(unref(stats).monthly, (item) => {
																		return openBlock(), createBlock("div", {
																			key: item.period,
																			class: "d-flex flex-column align-center",
																			style: {
																				"min-width": "52px",
																				"height": "100%"
																			}
																		}, [createVNode("div", { class: "flex-grow-1 d-flex align-end" }, [createVNode("div", {
																			class: "bg-deep-orange rounded-t",
																			title: `${item.period} — ${money(item.total)}`,
																			style: {
																				height: `${Math.max(6, Number(item.total || 0) / maxMonthlyTotal.value * 130)}px`,
																				width: "32px"
																			}
																		}, null, 12, ["title"])]), createVNode("div", {
																			class: "text-caption text-grey mt-1",
																			style: { "font-size": "10px" }
																		}, toDisplayString(item.period), 1)]);
																	}), 128))])) : (openBlock(), createBlock(VAlert, {
																		key: 1,
																		type: "info",
																		variant: "tonal",
																		density: "compact"
																	}, {
																		default: withCtx(() => [createTextVNode(" Данных по суммам пока нет. ")]),
																		_: 1
																	}))];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode(" Потраченные суммы по месяцам ")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [unref(stats).monthly.length ? (openBlock(), createBlock("div", {
																key: 0,
																class: "d-flex align-end ga-2 border rounded pa-3",
																style: {
																	"height": "180px",
																	"overflow-x": "auto"
																}
															}, [(openBlock(true), createBlock(Fragment, null, renderList(unref(stats).monthly, (item) => {
																return openBlock(), createBlock("div", {
																	key: item.period,
																	class: "d-flex flex-column align-center",
																	style: {
																		"min-width": "52px",
																		"height": "100%"
																	}
																}, [createVNode("div", { class: "flex-grow-1 d-flex align-end" }, [createVNode("div", {
																	class: "bg-deep-orange rounded-t",
																	title: `${item.period} — ${money(item.total)}`,
																	style: {
																		height: `${Math.max(6, Number(item.total || 0) / maxMonthlyTotal.value * 130)}px`,
																		width: "32px"
																	}
																}, null, 12, ["title"])]), createVNode("div", {
																	class: "text-caption text-grey mt-1",
																	style: { "font-size": "10px" }
																}, toDisplayString(item.period), 1)]);
															}), 128))])) : (openBlock(), createBlock(VAlert, {
																key: 1,
																type: "info",
																variant: "tonal",
																density: "compact"
															}, {
																default: withCtx(() => [createTextVNode(" Данных по суммам пока нет. ")]),
																_: 1
															}))]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VRow, null, {
													default: withCtx(() => [
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
																default: withCtx(() => [createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Последняя цена "), createVNode("div", { class: "text-h5" }, toDisplayString(latestPrice.value ? money(latestPrice.value) : "—"), 1)]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
																default: withCtx(() => [createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Минимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(minPrice.value ? money(minPrice.value) : "—"), 1)]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
																default: withCtx(() => [createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Максимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(maxPrice.value ? money(maxPrice.value) : "—"), 1)]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})
													]),
													_: 1
												}),
												createVNode(VCard, { class: "border rounded mt-4" }, {
													default: withCtx(() => [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode(" График изменения цены ")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [priceHistoryAsc.value.length ? (openBlock(), createBlock("div", {
															key: 0,
															class: "d-flex align-end ga-1 border rounded pa-3",
															style: {
																"height": "180px",
																"overflow-x": "auto"
															}
														}, [(openBlock(true), createBlock(Fragment, null, renderList(priceHistoryAsc.value, (point) => {
															return openBlock(), createBlock("div", {
																key: `${point.check_id}-${point.id}`,
																class: "d-flex flex-column align-center",
																style: {
																	"min-width": "24px",
																	"height": "100%"
																}
															}, [createVNode("div", { class: "flex-grow-1 d-flex align-end" }, [createVNode("div", {
																class: "bg-primary rounded-t",
																title: `${formatDate(point.check_date)} — ${money(point.price)}`,
																style: {
																	height: `${Math.max(6, Number(point.price || 0) / maxPrice.value * 130)}px`,
																	width: "16px"
																}
															}, null, 12, ["title"])]), createVNode("div", {
																class: "text-caption text-grey mt-1",
																style: { "font-size": "9px" }
															}, toDisplayString(new Date(point.check_date).getFullYear()), 1)]);
														}), 128))])) : (openBlock(), createBlock(VAlert, {
															key: 1,
															type: "info",
															variant: "tonal",
															density: "compact"
														}, {
															default: withCtx(() => [createTextVNode(" Истории цен пока нет. ")]),
															_: 1
														}))]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VCard, { class: "border rounded mt-4" }, {
													default: withCtx(() => [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode(" Потраченные суммы по месяцам ")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [unref(stats).monthly.length ? (openBlock(), createBlock("div", {
															key: 0,
															class: "d-flex align-end ga-2 border rounded pa-3",
															style: {
																"height": "180px",
																"overflow-x": "auto"
															}
														}, [(openBlock(true), createBlock(Fragment, null, renderList(unref(stats).monthly, (item) => {
															return openBlock(), createBlock("div", {
																key: item.period,
																class: "d-flex flex-column align-center",
																style: {
																	"min-width": "52px",
																	"height": "100%"
																}
															}, [createVNode("div", { class: "flex-grow-1 d-flex align-end" }, [createVNode("div", {
																class: "bg-deep-orange rounded-t",
																title: `${item.period} — ${money(item.total)}`,
																style: {
																	height: `${Math.max(6, Number(item.total || 0) / maxMonthlyTotal.value * 130)}px`,
																	width: "32px"
																}
															}, null, 12, ["title"])]), createVNode("div", {
																class: "text-caption text-grey mt-1",
																style: { "font-size": "10px" }
															}, toDisplayString(item.period), 1)]);
														}), 128))])) : (openBlock(), createBlock(VAlert, {
															key: 1,
															type: "info",
															variant: "tonal",
															density: "compact"
														}, {
															default: withCtx(() => [createTextVNode(" Данных по суммам пока нет. ")]),
															_: 1
														}))]),
														_: 1
													})]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, {
									cols: "12",
									lg: "5"
								}, {
									default: withCtx(() => [createVNode(_sfc_main$2, {
										"commodity-id": unref(commodity).id,
										items: unref(commodity).media || [],
										onRefresh: refresh
									}, null, 8, ["commodity-id", "items"])]),
									_: 1
								}), createVNode(VCol, {
									cols: "12",
									lg: "7"
								}, {
									default: withCtx(() => [
										createVNode(VRow, null, {
											default: withCtx(() => [
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
														default: withCtx(() => [createVNode(VCardText, null, {
															default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Последняя цена "), createVNode("div", { class: "text-h5" }, toDisplayString(latestPrice.value ? money(latestPrice.value) : "—"), 1)]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
														default: withCtx(() => [createVNode(VCardText, null, {
															default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Минимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(minPrice.value ? money(minPrice.value) : "—"), 1)]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
														default: withCtx(() => [createVNode(VCardText, null, {
															default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Максимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(maxPrice.value ? money(maxPrice.value) : "—"), 1)]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})
											]),
											_: 1
										}),
										createVNode(VCard, { class: "border rounded mt-4" }, {
											default: withCtx(() => [createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode(" График изменения цены ")]),
												_: 1
											}), createVNode(VCardText, null, {
												default: withCtx(() => [priceHistoryAsc.value.length ? (openBlock(), createBlock("div", {
													key: 0,
													class: "d-flex align-end ga-1 border rounded pa-3",
													style: {
														"height": "180px",
														"overflow-x": "auto"
													}
												}, [(openBlock(true), createBlock(Fragment, null, renderList(priceHistoryAsc.value, (point) => {
													return openBlock(), createBlock("div", {
														key: `${point.check_id}-${point.id}`,
														class: "d-flex flex-column align-center",
														style: {
															"min-width": "24px",
															"height": "100%"
														}
													}, [createVNode("div", { class: "flex-grow-1 d-flex align-end" }, [createVNode("div", {
														class: "bg-primary rounded-t",
														title: `${formatDate(point.check_date)} — ${money(point.price)}`,
														style: {
															height: `${Math.max(6, Number(point.price || 0) / maxPrice.value * 130)}px`,
															width: "16px"
														}
													}, null, 12, ["title"])]), createVNode("div", {
														class: "text-caption text-grey mt-1",
														style: { "font-size": "9px" }
													}, toDisplayString(new Date(point.check_date).getFullYear()), 1)]);
												}), 128))])) : (openBlock(), createBlock(VAlert, {
													key: 1,
													type: "info",
													variant: "tonal",
													density: "compact"
												}, {
													default: withCtx(() => [createTextVNode(" Истории цен пока нет. ")]),
													_: 1
												}))]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VCard, { class: "border rounded mt-4" }, {
											default: withCtx(() => [createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode(" Потраченные суммы по месяцам ")]),
												_: 1
											}), createVNode(VCardText, null, {
												default: withCtx(() => [unref(stats).monthly.length ? (openBlock(), createBlock("div", {
													key: 0,
													class: "d-flex align-end ga-2 border rounded pa-3",
													style: {
														"height": "180px",
														"overflow-x": "auto"
													}
												}, [(openBlock(true), createBlock(Fragment, null, renderList(unref(stats).monthly, (item) => {
													return openBlock(), createBlock("div", {
														key: item.period,
														class: "d-flex flex-column align-center",
														style: {
															"min-width": "52px",
															"height": "100%"
														}
													}, [createVNode("div", { class: "flex-grow-1 d-flex align-end" }, [createVNode("div", {
														class: "bg-deep-orange rounded-t",
														title: `${item.period} — ${money(item.total)}`,
														style: {
															height: `${Math.max(6, Number(item.total || 0) / maxMonthlyTotal.value * 130)}px`,
															width: "32px"
														}
													}, null, 12, ["title"])]), createVNode("div", {
														class: "text-caption text-grey mt-1",
														style: { "font-size": "10px" }
													}, toDisplayString(item.period), 1)]);
												}), 128))])) : (openBlock(), createBlock(VAlert, {
													key: 1,
													type: "info",
													variant: "tonal",
													density: "compact"
												}, {
													default: withCtx(() => [createTextVNode(" Данных по суммам пока нет. ")]),
													_: 1
												}))]),
												_: 1
											})]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						else _push(`<!---->`);
						_push(ssrRenderComponent(VRow, { class: "mt-4" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCard, { class: "border rounded" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(ssrRenderComponent(VCardTitle, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` История закупок Commodity `);
															else return [createTextVNode(" История закупок Commodity ")];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VDataTable, {
														headers: historyHeaders,
														items: unref(history),
														"items-per-page": 100,
														density: "compact",
														"fixed-header": "",
														height: "520",
														hover: ""
													}, {
														"item.check_date": withCtx(({ item }, _push, _parent, _scopeId) => {
															if (_push) _push(`${ssrInterpolate(formatDate(item.check_date))}`);
															else return [createTextVNode(toDisplayString(formatDate(item.check_date)), 1)];
														}),
														"item.quantity": withCtx(({ item }, _push, _parent, _scopeId) => {
															if (_push) _push(`${ssrInterpolate(money(item.quantity))}`);
															else return [createTextVNode(toDisplayString(money(item.quantity)), 1)];
														}),
														"item.price": withCtx(({ item }, _push, _parent, _scopeId) => {
															if (_push) _push(`${ssrInterpolate(money(item.price))}`);
															else return [createTextVNode(toDisplayString(money(item.price)), 1)];
														}),
														"item.total_price": withCtx(({ item }, _push, _parent, _scopeId) => {
															if (_push) _push(`<strong${_scopeId}>${ssrInterpolate(money(item.total_price))}</strong>`);
															else return [createVNode("strong", null, toDisplayString(money(item.total_price)), 1)];
														}),
														"item.check_id": withCtx(({ item }, _push, _parent, _scopeId) => {
															if (_push) _push(` #${ssrInterpolate(item.check_id)}`);
															else return [createTextVNode(" #" + toDisplayString(item.check_id), 1)];
														}),
														_: 1
													}, _parent, _scopeId));
												} else return [createVNode(VCardTitle, null, {
													default: withCtx(() => [createTextVNode(" История закупок Commodity ")]),
													_: 1
												}), createVNode(VDataTable, {
													headers: historyHeaders,
													items: unref(history),
													"items-per-page": 100,
													density: "compact",
													"fixed-header": "",
													height: "520",
													hover: ""
												}, {
													"item.check_date": withCtx(({ item }) => [createTextVNode(toDisplayString(formatDate(item.check_date)), 1)]),
													"item.quantity": withCtx(({ item }) => [createTextVNode(toDisplayString(money(item.quantity)), 1)]),
													"item.price": withCtx(({ item }) => [createTextVNode(toDisplayString(money(item.price)), 1)]),
													"item.total_price": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(money(item.total_price)), 1)]),
													"item.check_id": withCtx(({ item }) => [createTextVNode(" #" + toDisplayString(item.check_id), 1)]),
													_: 1
												}, 8, ["items"])];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VCard, { class: "border rounded" }, {
											default: withCtx(() => [createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode(" История закупок Commodity ")]),
												_: 1
											}), createVNode(VDataTable, {
												headers: historyHeaders,
												items: unref(history),
												"items-per-page": 100,
												density: "compact",
												"fixed-header": "",
												height: "520",
												hover: ""
											}, {
												"item.check_date": withCtx(({ item }) => [createTextVNode(toDisplayString(formatDate(item.check_date)), 1)]),
												"item.quantity": withCtx(({ item }) => [createTextVNode(toDisplayString(money(item.quantity)), 1)]),
												"item.price": withCtx(({ item }) => [createTextVNode(toDisplayString(money(item.price)), 1)]),
												"item.total_price": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(money(item.total_price)), 1)]),
												"item.check_id": withCtx(({ item }) => [createTextVNode(" #" + toDisplayString(item.check_id), 1)]),
												_: 1
											}, 8, ["items"])]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCol, { cols: "12" }, {
									default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
										default: withCtx(() => [createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode(" История закупок Commodity ")]),
											_: 1
										}), createVNode(VDataTable, {
											headers: historyHeaders,
											items: unref(history),
											"items-per-page": 100,
											density: "compact",
											"fixed-header": "",
											height: "520",
											hover: ""
										}, {
											"item.check_date": withCtx(({ item }) => [createTextVNode(toDisplayString(formatDate(item.check_date)), 1)]),
											"item.quantity": withCtx(({ item }) => [createTextVNode(toDisplayString(money(item.quantity)), 1)]),
											"item.price": withCtx(({ item }) => [createTextVNode(toDisplayString(money(item.price)), 1)]),
											"item.total_price": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(money(item.total_price)), 1)]),
											"item.check_id": withCtx(({ item }) => [createTextVNode(" #" + toDisplayString(item.check_id), 1)]),
											_: 1
										}, 8, ["items"])]),
										_: 1
									})]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode(VRow, {
							align: "center",
							class: "mb-4"
						}, {
							default: withCtx(() => [createVNode(VCol, null, {
								default: withCtx(() => [createVNode(unref(Link), {
									href: unref(route)("Ameise.commodities"),
									class: "text-teal-lighten-2 text-caption"
								}, {
									default: withCtx(() => [createTextVNode(" ← Commodities ")]),
									_: 1
								}, 8, ["href"]), unref(commodity) ? (openBlock(), createBlock("div", {
									key: 0,
									class: "d-flex align-center ga-3 mt-2"
								}, [createVNode(VAvatar, {
									size: "72",
									rounded: "lg"
								}, {
									default: withCtx(() => [createVNode(VImg, {
										src: unref(commodity).ava_url || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
										cover: ""
									}, null, 8, ["src"])]),
									_: 1
								}), createVNode("div", { class: "flex-grow-1" }, [!nameEditing.value ? (openBlock(), createBlock("div", {
									key: 0,
									class: "d-flex align-center ga-2"
								}, [createVNode("h1", { class: "text-h5 mb-0" }, toDisplayString(unref(commodity).name), 1), createVNode(VBtn, {
									icon: "mdi-pencil",
									variant: "text",
									density: "compact",
									onClick: startEditName
								})])) : (openBlock(), createBlock("div", {
									key: 1,
									class: "d-flex align-center ga-2"
								}, [
									createVNode(VTextField, {
										modelValue: editName.value,
										"onUpdate:modelValue": ($event) => editName.value = $event,
										variant: "outlined",
										density: "compact",
										"hide-details": "",
										style: { "max-width": "520px" }
									}, null, 8, ["modelValue", "onUpdate:modelValue"]),
									createVNode(VBtn, {
										icon: "mdi-check",
										color: "success",
										variant: "tonal",
										density: "compact",
										loading: unref(savingCommodity),
										onClick: saveName
									}, null, 8, ["loading"]),
									createVNode(VBtn, {
										icon: "mdi-close",
										variant: "text",
										density: "compact",
										onClick: ($event) => nameEditing.value = false
									}, null, 8, ["onClick"])
								])), createVNode("div", { class: "text-caption text-grey" }, " ID: " + toDisplayString(unref(commodity).id) + " · Checks: " + toDisplayString(unref(commodity).checks_count) + " · Media: " + toDisplayString(unref(commodity).media_count), 1)])])) : createCommentVNode("", true)]),
								_: 1
							}), createVNode(VCol, { cols: "auto" }, {
								default: withCtx(() => [createVNode(VBtn, {
									icon: "mdi-refresh",
									variant: "tonal",
									loading: unref(loadingCommodities),
									onClick: refresh
								}, null, 8, ["loading"])]),
								_: 1
							})]),
							_: 1
						}),
						unref(commodity) ? (openBlock(), createBlock(VRow, { key: 0 }, {
							default: withCtx(() => [createVNode(VCol, {
								cols: "12",
								lg: "5"
							}, {
								default: withCtx(() => [createVNode(_sfc_main$2, {
									"commodity-id": unref(commodity).id,
									items: unref(commodity).media || [],
									onRefresh: refresh
								}, null, 8, ["commodity-id", "items"])]),
								_: 1
							}), createVNode(VCol, {
								cols: "12",
								lg: "7"
							}, {
								default: withCtx(() => [
									createVNode(VRow, null, {
										default: withCtx(() => [
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
													default: withCtx(() => [createVNode(VCardText, null, {
														default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Последняя цена "), createVNode("div", { class: "text-h5" }, toDisplayString(latestPrice.value ? money(latestPrice.value) : "—"), 1)]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
													default: withCtx(() => [createVNode(VCardText, null, {
														default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Минимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(minPrice.value ? money(minPrice.value) : "—"), 1)]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
													default: withCtx(() => [createVNode(VCardText, null, {
														default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Максимальная цена "), createVNode("div", { class: "text-h5" }, toDisplayString(maxPrice.value ? money(maxPrice.value) : "—"), 1)]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})
										]),
										_: 1
									}),
									createVNode(VCard, { class: "border rounded mt-4" }, {
										default: withCtx(() => [createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode(" График изменения цены ")]),
											_: 1
										}), createVNode(VCardText, null, {
											default: withCtx(() => [priceHistoryAsc.value.length ? (openBlock(), createBlock("div", {
												key: 0,
												class: "d-flex align-end ga-1 border rounded pa-3",
												style: {
													"height": "180px",
													"overflow-x": "auto"
												}
											}, [(openBlock(true), createBlock(Fragment, null, renderList(priceHistoryAsc.value, (point) => {
												return openBlock(), createBlock("div", {
													key: `${point.check_id}-${point.id}`,
													class: "d-flex flex-column align-center",
													style: {
														"min-width": "24px",
														"height": "100%"
													}
												}, [createVNode("div", { class: "flex-grow-1 d-flex align-end" }, [createVNode("div", {
													class: "bg-primary rounded-t",
													title: `${formatDate(point.check_date)} — ${money(point.price)}`,
													style: {
														height: `${Math.max(6, Number(point.price || 0) / maxPrice.value * 130)}px`,
														width: "16px"
													}
												}, null, 12, ["title"])]), createVNode("div", {
													class: "text-caption text-grey mt-1",
													style: { "font-size": "9px" }
												}, toDisplayString(new Date(point.check_date).getFullYear()), 1)]);
											}), 128))])) : (openBlock(), createBlock(VAlert, {
												key: 1,
												type: "info",
												variant: "tonal",
												density: "compact"
											}, {
												default: withCtx(() => [createTextVNode(" Истории цен пока нет. ")]),
												_: 1
											}))]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCard, { class: "border rounded mt-4" }, {
										default: withCtx(() => [createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode(" Потраченные суммы по месяцам ")]),
											_: 1
										}), createVNode(VCardText, null, {
											default: withCtx(() => [unref(stats).monthly.length ? (openBlock(), createBlock("div", {
												key: 0,
												class: "d-flex align-end ga-2 border rounded pa-3",
												style: {
													"height": "180px",
													"overflow-x": "auto"
												}
											}, [(openBlock(true), createBlock(Fragment, null, renderList(unref(stats).monthly, (item) => {
												return openBlock(), createBlock("div", {
													key: item.period,
													class: "d-flex flex-column align-center",
													style: {
														"min-width": "52px",
														"height": "100%"
													}
												}, [createVNode("div", { class: "flex-grow-1 d-flex align-end" }, [createVNode("div", {
													class: "bg-deep-orange rounded-t",
													title: `${item.period} — ${money(item.total)}`,
													style: {
														height: `${Math.max(6, Number(item.total || 0) / maxMonthlyTotal.value * 130)}px`,
														width: "32px"
													}
												}, null, 12, ["title"])]), createVNode("div", {
													class: "text-caption text-grey mt-1",
													style: { "font-size": "10px" }
												}, toDisplayString(item.period), 1)]);
											}), 128))])) : (openBlock(), createBlock(VAlert, {
												key: 1,
												type: "info",
												variant: "tonal",
												density: "compact"
											}, {
												default: withCtx(() => [createTextVNode(" Данных по суммам пока нет. ")]),
												_: 1
											}))]),
											_: 1
										})]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						})) : createCommentVNode("", true),
						createVNode(VRow, { class: "mt-4" }, {
							default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
								default: withCtx(() => [createVNode(VCard, { class: "border rounded" }, {
									default: withCtx(() => [createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode(" История закупок Commodity ")]),
										_: 1
									}), createVNode(VDataTable, {
										headers: historyHeaders,
										items: unref(history),
										"items-per-page": 100,
										density: "compact",
										"fixed-header": "",
										height: "520",
										hover: ""
									}, {
										"item.check_date": withCtx(({ item }) => [createTextVNode(toDisplayString(formatDate(item.check_date)), 1)]),
										"item.quantity": withCtx(({ item }) => [createTextVNode(toDisplayString(money(item.quantity)), 1)]),
										"item.price": withCtx(({ item }) => [createTextVNode(toDisplayString(money(item.price)), 1)]),
										"item.total_price": withCtx(({ item }) => [createVNode("strong", null, toDisplayString(money(item.total_price)), 1)]),
										"item.check_id": withCtx(({ item }) => [createTextVNode(" #" + toDisplayString(item.check_id), 1)]),
										_: 1
									}, 8, ["items"])]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						})
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Dictionaries/Commodities/CommodityShowPage.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Pages/Ameise/Commodity.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$3 }, {
	__name: "Commodity",
	__ssrInlineRender: true,
	props: { commodityId: {
		type: Number,
		required: true
	} },
	setup(__props) {
		useHead({ title: `Commodity #${__props.commodityId}` });
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(_sfc_main$1, mergeProps({ "commodity-id": __props.commodityId }, _attrs), null, _parent));
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Commodity.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Commodity-QQ_cXABS.js.map