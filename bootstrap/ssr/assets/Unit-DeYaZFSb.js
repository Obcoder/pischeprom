import { A as VCombobox, C as VRow, D as VDataTable, E as VDataTableServer, F as VCardText, G as VList, H as VSelect, I as VCardTitle, K as VDivider, M as VWindowItem, N as VWindow, P as VCard, R as VCardActions, S as VSpacer, T as VContainer, U as VTextField, V as VAutocomplete, W as VMenu, X as VChip, a as VTabs, c as VTab, d as VSkeletonLoader, dt as useDate, i as VTextarea, it as VProgressLinear, j as VSheet, l as VSwitch, ot as VIcon, q as VListItem, rt as VBtn, ut as VExpandTransition, w as VCol, z as VDialog } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { t as _sfc_main$15 } from "./VerwalterLayout-BLmFLvbQ.js";
import { t as MailMessageReaderDialog_default } from "./MailMessageReaderDialog-BQOlI2wo.js";
import axios from "axios";
import { Link, useForm } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, isRef, mergeModels, mergeProps, onMounted, onUnmounted, openBlock, ref, renderList, renderSlot, toDisplayString, toRef, unref, useModel, useSSRContext, watch, withCtx, withKeys, withModifiers } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderAttr, ssrRenderAttrs, ssrRenderClass, ssrRenderComponent, ssrRenderList, ssrRenderSlot } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
import { useDebounceFn } from "@vueuse/core";
//#region resources/js/Composables/useUnitPage.js
function asArray(payload) {
	if (Array.isArray(payload)) return payload;
	if (Array.isArray(payload?.data)) return payload.data;
	return [];
}
function sortByString(items, key) {
	return [...items].sort((a, b) => String(a?.[key] ?? "").localeCompare(String(b?.[key] ?? "")));
}
function useUnitPage(initialUnit, initialDictionaries = {}, initialFiles = []) {
	const unit = ref(initialUnit);
	const files = ref(Array.isArray(initialFiles) ? initialFiles : []);
	const loading = ref({
		unit: false,
		dict: false,
		files: false,
		goods: false
	});
	const dict = ref({
		buildings: asArray(initialDictionaries.buildings),
		cities: asArray(initialDictionaries.cities),
		emails: asArray(initialDictionaries.emails),
		entities: asArray(initialDictionaries.entities),
		entityClassifications: asArray(initialDictionaries.entityClassifications),
		fields: asArray(initialDictionaries.fields),
		goods: asArray(initialDictionaries.goods),
		labels: asArray(initialDictionaries.labels),
		measures: asArray(initialDictionaries.measures),
		products: asArray(initialDictionaries.products),
		telephones: asArray(initialDictionaries.telephones),
		uris: asArray(initialDictionaries.uris)
	});
	async function refreshUnit() {
		if (!unit.value?.id) return;
		loading.value.unit = true;
		try {
			const { data } = await axios.get(route("api.units.show", unit.value.id));
			unit.value = data?.data ?? data;
		} catch (e) {
			console.error("Ошибка обновления unit:", e);
		} finally {
			loading.value.unit = false;
		}
	}
	async function loadFiles() {
		if (!unit.value?.id) {
			files.value = [];
			return;
		}
		loading.value.files = true;
		try {
			const { data } = await axios.get(`/api/units/${unit.value.id}/files`);
			files.value = data.files ?? data ?? [];
		} catch (error) {
			console.error("Ошибка загрузки файлов:", {
				unitId: unit.value?.id,
				unitName: unit.value?.name,
				status: error.response?.status,
				data: error.response?.data,
				error
			});
			files.value = [];
		} finally {
			loading.value.files = false;
		}
	}
	async function loadDictionaries() {
		loading.value.dict = true;
		try {
			const [buildingsRes, citiesRes, emailsRes, entitiesRes, classificationsRes, fieldsRes, goodsRes, labelsRes, measuresRes, productsRes, telephonesRes, urisRes] = await Promise.all([
				axios.get(route("buildings.index")),
				axios.get(route("cities.index")),
				axios.get(route("emails.index")),
				axios.get(route("entities.index")),
				axios.get(route("entities-classification.index")),
				axios.get(route("fields.index")),
				axios.get(route("goods.index")),
				axios.get(route("labels.index")),
				axios.get(route("measures.index")),
				axios.get(route("products.index")),
				axios.get(route("telephones.index")),
				axios.get(route("uris.index"))
			]);
			dict.value = {
				buildings: asArray(buildingsRes.data),
				cities: sortByString(asArray(citiesRes.data), "name"),
				emails: sortByString(asArray(emailsRes.data), "address"),
				entities: asArray(entitiesRes.data),
				entityClassifications: asArray(classificationsRes.data),
				fields: sortByString(asArray(fieldsRes.data), "name"),
				goods: asArray(goodsRes.data),
				labels: sortByString(asArray(labelsRes.data), "name"),
				measures: asArray(measuresRes.data),
				products: sortByString(asArray(productsRes.data), "rus"),
				telephones: sortByString(asArray(telephonesRes.data), "number"),
				uris: sortByString(asArray(urisRes.data), "address")
			};
		} catch (e) {
			console.error("Ошибка загрузки справочников:", e);
		} finally {
			loading.value.dict = false;
		}
	}
	async function searchGoods(search = "") {
		loading.value.goods = true;
		try {
			const { data } = await axios.get(route("goods.index"), { params: { search } });
			dict.value.goods = asArray(data);
		} catch (e) {
			console.error("Ошибка поиска goods:", e);
		} finally {
			loading.value.goods = false;
		}
	}
	return {
		unit,
		files,
		dict,
		loading,
		refreshUnit,
		loadFiles,
		loadDictionaries,
		searchGoods
	};
}
//#endregion
//#region resources/js/Components/Unit/BaseSectionCard.vue
var _sfc_main$14 = {
	__name: "BaseSectionCard",
	__ssrInlineRender: true,
	props: {
		title: {
			type: String,
			required: true
		},
		icon: {
			type: String,
			default: null
		},
		headerColor: {
			type: String,
			default: "maroon"
		},
		compact: {
			type: Boolean,
			default: false
		},
		stretch: {
			type: Boolean,
			default: false
		},
		bodyClass: {
			type: [
				String,
				Array,
				Object
			],
			default: null
		}
	},
	setup(__props) {
		const props = __props;
		const cardClasses = computed(() => ["base-section-card", props.stretch ? "h-100" : null]);
		const headerClasses = computed(() => [
			"base-section-card__header",
			`base-section-card__header--${props.headerColor}`,
			props.compact ? "base-section-card__header--compact" : null
		]);
		const bodyClasses = computed(() => [
			"base-section-card__body",
			props.compact ? "base-section-card__body--compact" : null,
			props.bodyClass
		]);
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, mergeProps({
				rounded: "xl",
				elevation: "1",
				border: "",
				color: "white",
				class: cardClasses.value
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="${ssrRenderClass(headerClasses.value)}" data-v-4bdcf4bb${_scopeId}><div class="d-flex align-center min-w-0" data-v-4bdcf4bb${_scopeId}>`);
						if (__props.icon) _push(ssrRenderComponent(VIcon, {
							icon: __props.icon,
							size: "small",
							class: "me-2 base-section-card__icon"
						}, null, _parent, _scopeId));
						else _push(`<!---->`);
						_push(`<div class="base-section-card__title text-truncate" data-v-4bdcf4bb${_scopeId}>${ssrInterpolate(__props.title)}</div></div><div class="base-section-card__actions" data-v-4bdcf4bb${_scopeId}>`);
						ssrRenderSlot(_ctx.$slots, "actions", {}, null, _push, _parent, _scopeId);
						_push(`</div></div>`);
						_push(ssrRenderComponent(VCardText, { class: bodyClasses.value }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent, _scopeId);
								else return [renderSlot(_ctx.$slots, "default", {}, void 0, true)];
							}),
							_: 3
						}, _parent, _scopeId));
					} else return [createVNode("div", { class: headerClasses.value }, [createVNode("div", { class: "d-flex align-center min-w-0" }, [__props.icon ? (openBlock(), createBlock(VIcon, {
						key: 0,
						icon: __props.icon,
						size: "small",
						class: "me-2 base-section-card__icon"
					}, null, 8, ["icon"])) : createCommentVNode("", true), createVNode("div", { class: "base-section-card__title text-truncate" }, toDisplayString(__props.title), 1)]), createVNode("div", { class: "base-section-card__actions" }, [renderSlot(_ctx.$slots, "actions", {}, void 0, true)])], 2), createVNode(VCardText, { class: bodyClasses.value }, {
						default: withCtx(() => [renderSlot(_ctx.$slots, "default", {}, void 0, true)]),
						_: 3
					}, 8, ["class"])];
				}),
				_: 3
			}, _parent));
		};
	}
};
var _sfc_setup$14 = _sfc_main$14.setup;
_sfc_main$14.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/BaseSectionCard.vue");
	return _sfc_setup$14 ? _sfc_setup$14(props, ctx) : void 0;
};
var BaseSectionCard_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main$14, [["__scopeId", "data-v-4bdcf4bb"]]);
//#endregion
//#region resources/js/Composables/useUnitFiles.js
function useUnitFiles(unitId) {
	const files = ref([]);
	const loadingFiles = ref(false);
	const uploadingFile = ref(false);
	const deletingFilePath = ref(null);
	const renamingFilePath = ref(null);
	function getUnitId() {
		const value = unref(unitId);
		if (!value) return null;
		const id = Number(value);
		if (!Number.isInteger(id) || id <= 0) {
			console.error("useUnitFiles ожидает unit.id, но получил:", value);
			return null;
		}
		return id;
	}
	function getFilesUrl() {
		const id = getUnitId();
		if (!id) return null;
		return `/api/units/${id}/files`;
	}
	async function loadFiles() {
		const url = getFilesUrl();
		if (!url) {
			console.warn("useUnitFiles: loadFiles skipped, unitId is invalid");
			files.value = [];
			return;
		}
		loadingFiles.value = true;
		try {
			const { data } = await axios.get(url);
			files.value = data.files ?? data ?? [];
		} catch (error) {
			console.error("Ошибка загрузки файлов:", {
				url,
				status: error.response?.status,
				data: error.response?.data,
				error
			});
			files.value = [];
		} finally {
			loadingFiles.value = false;
		}
	}
	async function uploadFile(file) {
		const url = getFilesUrl();
		if (!file || !url) return;
		uploadingFile.value = true;
		const formData = new FormData();
		formData.append("file", file);
		try {
			await axios.post(url, formData, { headers: { "Content-Type": "multipart/form-data" } });
			await loadFiles();
		} finally {
			uploadingFile.value = false;
		}
	}
	async function deleteFile(path) {
		const url = getFilesUrl();
		if (!path || !url) return;
		deletingFilePath.value = path;
		try {
			await axios.delete(url, { data: { path } });
			await loadFiles();
		} finally {
			deletingFilePath.value = null;
		}
	}
	async function renameFile(path, name) {
		const url = getFilesUrl();
		if (!path || !name || !url) return;
		renamingFilePath.value = path;
		try {
			await axios.patch(`${url}/rename`, {
				path,
				name
			});
			await loadFiles();
		} finally {
			renamingFilePath.value = null;
		}
	}
	return {
		files,
		loadingFiles,
		uploadingFile,
		deletingFilePath,
		renamingFilePath,
		loadFiles,
		uploadFile,
		deleteFile,
		renameFile
	};
}
//#endregion
//#region resources/js/Components/Unit/UnitFilesTab.vue
var _sfc_main$13 = {
	__name: "UnitFilesTab",
	__ssrInlineRender: true,
	props: { unitId: {
		type: Number,
		required: true
	} },
	emits: ["send-file"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const unitIdRef = toRef(props, "unitId");
		const fileInput = ref(null);
		const renameDialog = ref(false);
		const fileToRename = ref(null);
		const newBaseFileName = ref("");
		const fileExtension = ref("");
		const { files, loadingFiles, uploadingFile, deletingFilePath, renamingFilePath, loadFiles, uploadFile, deleteFile, renameFile } = useUnitFiles(unitIdRef);
		const isRenameDisabled = computed(() => {
			const trimmedBaseName = newBaseFileName.value.trim();
			if (!fileToRename.value) return true;
			if (!trimmedBaseName) return true;
			if (/^\.{1,}$/.test(trimmedBaseName)) return true;
			return false;
		});
		function splitFileName(fileName) {
			const lastDotIndex = fileName.lastIndexOf(".");
			if (lastDotIndex <= 0) return {
				baseName: fileName,
				extension: ""
			};
			return {
				baseName: fileName.slice(0, lastDotIndex),
				extension: fileName.slice(lastDotIndex)
			};
		}
		function openRenameDialog(file) {
			fileToRename.value = file;
			const { baseName, extension } = splitFileName(file.name);
			newBaseFileName.value = baseName;
			fileExtension.value = extension;
			renameDialog.value = true;
		}
		function closeRenameDialog() {
			renameDialog.value = false;
			fileToRename.value = null;
			newBaseFileName.value = "";
			fileExtension.value = "";
		}
		async function submitRename() {
			if (isRenameDisabled.value) return;
			const file = fileToRename.value;
			const trimmedBaseName = newBaseFileName.value.trim();
			if (!file) return;
			const finalName = `${trimmedBaseName}${fileExtension.value}`;
			await renameFile(file.path, finalName);
			closeRenameDialog();
		}
		onMounted(() => {
			if (props.unitId) loadFiles();
		});
		watch(() => props.unitId, (value, oldValue) => {
			if (value && value !== oldValue) loadFiles();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${ssrRenderAttrs(_attrs)}><div class="d-flex justify-space-between align-center mb-4"><div class="text-subtitle-1 font-weight-medium">Files</div><div class="d-flex ga-2"><input type="file" class="d-none">`);
			_push(ssrRenderComponent(VBtn, {
				color: "primary",
				variant: "tonal",
				loading: unref(uploadingFile),
				onClick: ($event) => fileInput.value?.click()
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Upload file `);
					else return [createTextVNode(" Upload file ")];
				}),
				_: 1
			}, _parent));
			_push(`</div></div>`);
			if (unref(loadingFiles)) _push(ssrRenderComponent(VSkeletonLoader, { type: "list-item-three-line" }, null, _parent));
			else _push(ssrRenderComponent(VList, { density: "comfortable" }, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<!--[-->`);
						ssrRenderList(unref(files), (file) => {
							_push(ssrRenderComponent(VListItem, { key: file.path }, {
								title: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(`<a${ssrRenderAttr("href", file.url)} target="_blank" class="text-decoration-none"${_scopeId}>${ssrInterpolate(file.name)}</a>`);
									else return [createVNode("a", {
										href: file.url,
										target: "_blank",
										class: "text-decoration-none"
									}, toDisplayString(file.name), 9, ["href"])];
								}),
								subtitle: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(`${ssrInterpolate(Math.round((file.size || 0) / 1024))} KB `);
									else return [createTextVNode(toDisplayString(Math.round((file.size || 0) / 1024)) + " KB ", 1)];
								}),
								append: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) {
										_push(`<div class="d-flex align-center ga-1"${_scopeId}>`);
										_push(ssrRenderComponent(VBtn, {
											icon: "mdi-email-send-outline",
											variant: "text",
											color: "blue",
											onClick: ($event) => emit("send-file", file)
										}, null, _parent, _scopeId));
										_push(ssrRenderComponent(VBtn, {
											icon: "mdi-pencil-outline",
											variant: "text",
											color: "primary",
											loading: unref(renamingFilePath) === file.path,
											onClick: ($event) => openRenameDialog(file)
										}, null, _parent, _scopeId));
										_push(ssrRenderComponent(VBtn, {
											icon: "mdi-delete-outline",
											variant: "text",
											color: "error",
											loading: unref(deletingFilePath) === file.path,
											onClick: ($event) => unref(deleteFile)(file.path)
										}, null, _parent, _scopeId));
										_push(`</div>`);
									} else return [createVNode("div", { class: "d-flex align-center ga-1" }, [
										createVNode(VBtn, {
											icon: "mdi-email-send-outline",
											variant: "text",
											color: "blue",
											onClick: ($event) => emit("send-file", file)
										}, null, 8, ["onClick"]),
										createVNode(VBtn, {
											icon: "mdi-pencil-outline",
											variant: "text",
											color: "primary",
											loading: unref(renamingFilePath) === file.path,
											onClick: ($event) => openRenameDialog(file)
										}, null, 8, ["loading", "onClick"]),
										createVNode(VBtn, {
											icon: "mdi-delete-outline",
											variant: "text",
											color: "error",
											loading: unref(deletingFilePath) === file.path,
											onClick: ($event) => unref(deleteFile)(file.path)
										}, null, 8, ["loading", "onClick"])
									])];
								}),
								_: 2
							}, _parent, _scopeId));
						});
						_push(`<!--]-->`);
					} else return [(openBlock(true), createBlock(Fragment, null, renderList(unref(files), (file) => {
						return openBlock(), createBlock(VListItem, { key: file.path }, {
							title: withCtx(() => [createVNode("a", {
								href: file.url,
								target: "_blank",
								class: "text-decoration-none"
							}, toDisplayString(file.name), 9, ["href"])]),
							subtitle: withCtx(() => [createTextVNode(toDisplayString(Math.round((file.size || 0) / 1024)) + " KB ", 1)]),
							append: withCtx(() => [createVNode("div", { class: "d-flex align-center ga-1" }, [
								createVNode(VBtn, {
									icon: "mdi-email-send-outline",
									variant: "text",
									color: "blue",
									onClick: ($event) => emit("send-file", file)
								}, null, 8, ["onClick"]),
								createVNode(VBtn, {
									icon: "mdi-pencil-outline",
									variant: "text",
									color: "primary",
									loading: unref(renamingFilePath) === file.path,
									onClick: ($event) => openRenameDialog(file)
								}, null, 8, ["loading", "onClick"]),
								createVNode(VBtn, {
									icon: "mdi-delete-outline",
									variant: "text",
									color: "error",
									loading: unref(deletingFilePath) === file.path,
									onClick: ($event) => unref(deleteFile)(file.path)
								}, null, 8, ["loading", "onClick"])
							])]),
							_: 2
						}, 1024);
					}), 128))];
				}),
				_: 1
			}, _parent));
			_push(ssrRenderComponent(VDialog, {
				modelValue: renameDialog.value,
				"onUpdate:modelValue": ($event) => renameDialog.value = $event,
				"max-width": "600"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VCard, { rounded: "xl" }, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCardTitle, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(`Rename file`);
										else return [createTextVNode("Rename file")];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCardText, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VTextField, {
												modelValue: newBaseFileName.value,
												"onUpdate:modelValue": ($event) => newBaseFileName.value = $event,
												label: "New file name",
												variant: "outlined",
												density: "comfortable",
												autofocus: ""
											}, null, _parent, _scopeId));
											_push(`<div class="text-caption text-medium-emphasis mt-2"${_scopeId}> Extension: ${ssrInterpolate(fileExtension.value || "none")}</div>`);
											if (newBaseFileName.value && isRenameDisabled.value) _push(`<div class="text-caption text-error mt-2"${_scopeId}> Enter a valid file name. </div>`);
											else _push(`<!---->`);
										} else return [
											createVNode(VTextField, {
												modelValue: newBaseFileName.value,
												"onUpdate:modelValue": ($event) => newBaseFileName.value = $event,
												label: "New file name",
												variant: "outlined",
												density: "comfortable",
												autofocus: ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode("div", { class: "text-caption text-medium-emphasis mt-2" }, " Extension: " + toDisplayString(fileExtension.value || "none"), 1),
											newBaseFileName.value && isRenameDisabled.value ? (openBlock(), createBlock("div", {
												key: 0,
												class: "text-caption text-error mt-2"
											}, " Enter a valid file name. ")) : createCommentVNode("", true)
										];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCardActions, { class: "justify-end" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VBtn, {
												variant: "text",
												onClick: closeRenameDialog
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Cancel `);
													else return [createTextVNode(" Cancel ")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												color: "primary",
												loading: fileToRename.value && unref(renamingFilePath) === fileToRename.value.path,
												disabled: isRenameDisabled.value,
												onClick: submitRename
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Save `);
													else return [createTextVNode(" Save ")];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [createVNode(VBtn, {
											variant: "text",
											onClick: closeRenameDialog
										}, {
											default: withCtx(() => [createTextVNode(" Cancel ")]),
											_: 1
										}), createVNode(VBtn, {
											color: "primary",
											loading: fileToRename.value && unref(renamingFilePath) === fileToRename.value.path,
											disabled: isRenameDisabled.value,
											onClick: submitRename
										}, {
											default: withCtx(() => [createTextVNode(" Save ")]),
											_: 1
										}, 8, ["loading", "disabled"])];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCardTitle, null, {
									default: withCtx(() => [createTextVNode("Rename file")]),
									_: 1
								}),
								createVNode(VCardText, null, {
									default: withCtx(() => [
										createVNode(VTextField, {
											modelValue: newBaseFileName.value,
											"onUpdate:modelValue": ($event) => newBaseFileName.value = $event,
											label: "New file name",
											variant: "outlined",
											density: "comfortable",
											autofocus: ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"]),
										createVNode("div", { class: "text-caption text-medium-emphasis mt-2" }, " Extension: " + toDisplayString(fileExtension.value || "none"), 1),
										newBaseFileName.value && isRenameDisabled.value ? (openBlock(), createBlock("div", {
											key: 0,
											class: "text-caption text-error mt-2"
										}, " Enter a valid file name. ")) : createCommentVNode("", true)
									]),
									_: 1
								}),
								createVNode(VCardActions, { class: "justify-end" }, {
									default: withCtx(() => [createVNode(VBtn, {
										variant: "text",
										onClick: closeRenameDialog
									}, {
										default: withCtx(() => [createTextVNode(" Cancel ")]),
										_: 1
									}), createVNode(VBtn, {
										color: "primary",
										loading: fileToRename.value && unref(renamingFilePath) === fileToRename.value.path,
										disabled: isRenameDisabled.value,
										onClick: submitRename
									}, {
										default: withCtx(() => [createTextVNode(" Save ")]),
										_: 1
									}, 8, ["loading", "disabled"])]),
									_: 1
								})
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VCard, { rounded: "xl" }, {
						default: withCtx(() => [
							createVNode(VCardTitle, null, {
								default: withCtx(() => [createTextVNode("Rename file")]),
								_: 1
							}),
							createVNode(VCardText, null, {
								default: withCtx(() => [
									createVNode(VTextField, {
										modelValue: newBaseFileName.value,
										"onUpdate:modelValue": ($event) => newBaseFileName.value = $event,
										label: "New file name",
										variant: "outlined",
										density: "comfortable",
										autofocus: ""
									}, null, 8, ["modelValue", "onUpdate:modelValue"]),
									createVNode("div", { class: "text-caption text-medium-emphasis mt-2" }, " Extension: " + toDisplayString(fileExtension.value || "none"), 1),
									newBaseFileName.value && isRenameDisabled.value ? (openBlock(), createBlock("div", {
										key: 0,
										class: "text-caption text-error mt-2"
									}, " Enter a valid file name. ")) : createCommentVNode("", true)
								]),
								_: 1
							}),
							createVNode(VCardActions, { class: "justify-end" }, {
								default: withCtx(() => [createVNode(VBtn, {
									variant: "text",
									onClick: closeRenameDialog
								}, {
									default: withCtx(() => [createTextVNode(" Cancel ")]),
									_: 1
								}), createVNode(VBtn, {
									color: "primary",
									loading: fileToRename.value && unref(renamingFilePath) === fileToRename.value.path,
									disabled: isRenameDisabled.value,
									onClick: submitRename
								}, {
									default: withCtx(() => [createTextVNode(" Save ")]),
									_: 1
								}, 8, ["loading", "disabled"])]),
								_: 1
							})
						]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
			_push(`</div>`);
		};
	}
};
var _sfc_setup$13 = _sfc_main$13.setup;
_sfc_main$13.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/UnitFilesTab.vue");
	return _sfc_setup$13 ? _sfc_setup$13(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Unit/UnitUrisCard.vue
var _sfc_main$12 = {
	__name: "UnitUrisCard",
	__ssrInlineRender: true,
	props: {
		unit: {
			type: Object,
			required: true
		},
		dict: {
			type: Object,
			default: () => ({})
		}
	},
	emits: ["refresh"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const dialogAttach = ref(false);
		const selectedUri = ref(null);
		const uriSearch = ref("");
		const saving = ref(false);
		const deletingId = ref(null);
		const uriItems = computed(() => props.dict?.uris || []);
		const unitUris = computed(() => props.unit?.uris || []);
		function normalizeUri(address) {
			const value = String(address || "").trim();
			if (!value) return "#";
			if (/^https?:\/\//i.test(value)) return value;
			return `https://${value}`;
		}
		async function attachUri() {
			const selectedId = selectedUri.value && typeof selectedUri.value === "object" ? selectedUri.value.id ?? null : null;
			const typedValue = String(uriSearch.value || "").trim();
			const fallbackString = typeof selectedUri.value === "string" ? selectedUri.value.trim() : "";
			const address = selectedId ? null : typedValue || fallbackString || null;
			if (!selectedId && !address) return;
			saving.value = true;
			try {
				await axios.post(route("api.units.uris.attach", props.unit.id), selectedId ? { uri_id: selectedId } : { address });
				selectedUri.value = null;
				uriSearch.value = "";
				dialogAttach.value = false;
				emit("refresh");
			} catch (error) {
				console.error("Ошибка привязки URI:", error);
			} finally {
				saving.value = false;
			}
		}
		async function detachUri(uriId) {
			deletingId.value = uriId;
			try {
				await axios.delete(route("api.units.uris.detach", {
					unit: props.unit.id,
					uri: uriId
				}));
				emit("refresh");
			} catch (error) {
				console.error("Ошибка отвязки URI:", error);
			} finally {
				deletingId.value = null;
			}
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${ssrRenderAttrs(_attrs)}><div class="d-flex align-center justify-space-between mb-2"><div class="text-h6">Uris</div>`);
			_push(ssrRenderComponent(VBtn, {
				size: "small",
				variant: "text",
				onClick: ($event) => dialogAttach.value = true
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Attach `);
					else return [createTextVNode(" Attach ")];
				}),
				_: 1
			}, _parent));
			_push(`</div>`);
			if (unitUris.value.length) {
				_push(`<div class="d-flex flex-wrap ga-2"><!--[-->`);
				ssrRenderList(unitUris.value, (uri) => {
					_push(ssrRenderComponent(VChip, {
						key: uri.id,
						closable: "",
						color: "primary",
						variant: "tonal",
						"onClick:close": ($event) => detachUri(uri.id),
						density: "compact",
						size: "small"
					}, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) _push(`<a${ssrRenderAttr("href", normalizeUri(uri.address))} target="_blank" rel="noopener noreferrer" class="text-decoration-none text-inherit"${_scopeId}>${ssrInterpolate(uri.address)}</a>`);
							else return [createVNode("a", {
								href: normalizeUri(uri.address),
								target: "_blank",
								rel: "noopener noreferrer",
								class: "text-decoration-none text-inherit",
								onClick: withModifiers(() => {}, ["stop"])
							}, toDisplayString(uri.address), 9, ["href", "onClick"])];
						}),
						_: 2
					}, _parent));
				});
				_push(`<!--]--></div>`);
			} else _push(`<div class="text-caption text-disabled"> No uris </div>`);
			_push(ssrRenderComponent(VDialog, {
				modelValue: dialogAttach.value,
				"onUpdate:modelValue": ($event) => dialogAttach.value = $event,
				"max-width": "720"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VCard, { rounded: "xl" }, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCardTitle, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(`Select URI`);
										else return [createTextVNode("Select URI")];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCardText, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCombobox, {
											modelValue: selectedUri.value,
											"onUpdate:modelValue": ($event) => selectedUri.value = $event,
											search: uriSearch.value,
											"onUpdate:search": ($event) => uriSearch.value = $event,
											items: uriItems.value,
											"item-title": "address",
											"item-value": "id",
											label: "Uri",
											hint: "Выберите URI из базы или введите новый прямо в этом поле",
											"persistent-hint": "",
											clearable: "",
											"return-object": "",
											variant: "outlined",
											density: "comfortable",
											onKeydown: attachUri
										}, null, _parent, _scopeId));
										else return [createVNode(VCombobox, {
											modelValue: selectedUri.value,
											"onUpdate:modelValue": ($event) => selectedUri.value = $event,
											search: uriSearch.value,
											"onUpdate:search": ($event) => uriSearch.value = $event,
											items: uriItems.value,
											"item-title": "address",
											"item-value": "id",
											label: "Uri",
											hint: "Выберите URI из базы или введите новый прямо в этом поле",
											"persistent-hint": "",
											clearable: "",
											"return-object": "",
											variant: "outlined",
											density: "comfortable",
											onKeydown: withKeys(withModifiers(attachUri, ["prevent"]), ["enter"])
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"search",
											"onUpdate:search",
											"items",
											"onKeydown"
										])];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCardActions, { class: "justify-end" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VBtn, {
												variant: "text",
												onClick: ($event) => dialogAttach.value = false
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Cancel `);
													else return [createTextVNode(" Cancel ")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												color: "primary",
												loading: saving.value,
												onClick: attachUri
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Attach `);
													else return [createTextVNode(" Attach ")];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogAttach.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Cancel ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "primary",
											loading: saving.value,
											onClick: attachUri
										}, {
											default: withCtx(() => [createTextVNode(" Attach ")]),
											_: 1
										}, 8, ["loading"])];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCardTitle, null, {
									default: withCtx(() => [createTextVNode("Select URI")]),
									_: 1
								}),
								createVNode(VCardText, null, {
									default: withCtx(() => [createVNode(VCombobox, {
										modelValue: selectedUri.value,
										"onUpdate:modelValue": ($event) => selectedUri.value = $event,
										search: uriSearch.value,
										"onUpdate:search": ($event) => uriSearch.value = $event,
										items: uriItems.value,
										"item-title": "address",
										"item-value": "id",
										label: "Uri",
										hint: "Выберите URI из базы или введите новый прямо в этом поле",
										"persistent-hint": "",
										clearable: "",
										"return-object": "",
										variant: "outlined",
										density: "comfortable",
										onKeydown: withKeys(withModifiers(attachUri, ["prevent"]), ["enter"])
									}, null, 8, [
										"modelValue",
										"onUpdate:modelValue",
										"search",
										"onUpdate:search",
										"items",
										"onKeydown"
									])]),
									_: 1
								}),
								createVNode(VCardActions, { class: "justify-end" }, {
									default: withCtx(() => [createVNode(VBtn, {
										variant: "text",
										onClick: ($event) => dialogAttach.value = false
									}, {
										default: withCtx(() => [createTextVNode(" Cancel ")]),
										_: 1
									}, 8, ["onClick"]), createVNode(VBtn, {
										color: "primary",
										loading: saving.value,
										onClick: attachUri
									}, {
										default: withCtx(() => [createTextVNode(" Attach ")]),
										_: 1
									}, 8, ["loading"])]),
									_: 1
								})
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VCard, { rounded: "xl" }, {
						default: withCtx(() => [
							createVNode(VCardTitle, null, {
								default: withCtx(() => [createTextVNode("Select URI")]),
								_: 1
							}),
							createVNode(VCardText, null, {
								default: withCtx(() => [createVNode(VCombobox, {
									modelValue: selectedUri.value,
									"onUpdate:modelValue": ($event) => selectedUri.value = $event,
									search: uriSearch.value,
									"onUpdate:search": ($event) => uriSearch.value = $event,
									items: uriItems.value,
									"item-title": "address",
									"item-value": "id",
									label: "Uri",
									hint: "Выберите URI из базы или введите новый прямо в этом поле",
									"persistent-hint": "",
									clearable: "",
									"return-object": "",
									variant: "outlined",
									density: "comfortable",
									onKeydown: withKeys(withModifiers(attachUri, ["prevent"]), ["enter"])
								}, null, 8, [
									"modelValue",
									"onUpdate:modelValue",
									"search",
									"onUpdate:search",
									"items",
									"onKeydown"
								])]),
								_: 1
							}),
							createVNode(VCardActions, { class: "justify-end" }, {
								default: withCtx(() => [createVNode(VBtn, {
									variant: "text",
									onClick: ($event) => dialogAttach.value = false
								}, {
									default: withCtx(() => [createTextVNode(" Cancel ")]),
									_: 1
								}, 8, ["onClick"]), createVNode(VBtn, {
									color: "primary",
									loading: saving.value,
									onClick: attachUri
								}, {
									default: withCtx(() => [createTextVNode(" Attach ")]),
									_: 1
								}, 8, ["loading"])]),
								_: 1
							})
						]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
			_push(`</div>`);
		};
	}
};
var _sfc_setup$12 = _sfc_main$12.setup;
_sfc_main$12.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/UnitUrisCard.vue");
	return _sfc_setup$12 ? _sfc_setup$12(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Unit/UnitRelationManagerDialog.vue
var _sfc_main$11 = {
	__name: "UnitRelationManagerDialog",
	__ssrInlineRender: true,
	props: {
		modelValue: {
			type: Boolean,
			default: false
		},
		title: {
			type: String,
			required: true
		},
		items: {
			type: Array,
			default: () => []
		},
		dictItems: {
			type: Array,
			default: () => []
		},
		itemTitle: {
			type: String,
			default: "name"
		},
		itemValue: {
			type: String,
			default: "id"
		},
		hint: {
			type: String,
			default: "Можно выбрать из списка или ввести новое значение"
		},
		loading: {
			type: Boolean,
			default: false
		}
	},
	emits: ["update:modelValue", "save"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const localValue = ref([]);
		watch(() => props.modelValue, (opened) => {
			if (opened) localValue.value = [...props.items || []];
		});
		watch(() => props.items, (items) => {
			if (props.modelValue) localValue.value = [...items || []];
		}, { deep: true });
		function close() {
			emit("update:modelValue", false);
		}
		function normalizeItem(item) {
			if (typeof item === "string") return item.trim();
			if (item?.[props.itemValue]) return {
				id: item[props.itemValue],
				[props.itemTitle]: item[props.itemTitle]
			};
			return item?.[props.itemTitle] ?? null;
		}
		function submit() {
			emit("save", (localValue.value || []).map(normalizeItem).filter(Boolean));
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VDialog, mergeProps({
				"model-value": __props.modelValue,
				"max-width": "760",
				"onUpdate:modelValue": ($event) => emit("update:modelValue", $event)
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VCard, { rounded: "xl" }, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCardTitle, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(`${ssrInterpolate(__props.title)}`);
										else return [createTextVNode(toDisplayString(__props.title), 1)];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCardText, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCombobox, {
											modelValue: localValue.value,
											"onUpdate:modelValue": ($event) => localValue.value = $event,
											items: __props.dictItems,
											"item-title": __props.itemTitle,
											"item-value": __props.itemValue,
											label: __props.title,
											hint: __props.hint,
											multiple: "",
											chips: "",
											"closable-chips": "",
											clearable: "",
											"return-object": "",
											variant: "outlined",
											density: "comfortable",
											"persistent-hint": ""
										}, null, _parent, _scopeId));
										else return [createVNode(VCombobox, {
											modelValue: localValue.value,
											"onUpdate:modelValue": ($event) => localValue.value = $event,
											items: __props.dictItems,
											"item-title": __props.itemTitle,
											"item-value": __props.itemValue,
											label: __props.title,
											hint: __props.hint,
											multiple: "",
											chips: "",
											"closable-chips": "",
											clearable: "",
											"return-object": "",
											variant: "outlined",
											density: "comfortable",
											"persistent-hint": ""
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"items",
											"item-title",
											"item-value",
											"label",
											"hint"
										])];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCardActions, { class: "justify-end" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VBtn, {
												variant: "text",
												onClick: close
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Cancel `);
													else return [createTextVNode(" Cancel ")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												color: "primary",
												loading: __props.loading,
												onClick: submit
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Save `);
													else return [createTextVNode(" Save ")];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [createVNode(VBtn, {
											variant: "text",
											onClick: close
										}, {
											default: withCtx(() => [createTextVNode(" Cancel ")]),
											_: 1
										}), createVNode(VBtn, {
											color: "primary",
											loading: __props.loading,
											onClick: submit
										}, {
											default: withCtx(() => [createTextVNode(" Save ")]),
											_: 1
										}, 8, ["loading"])];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCardTitle, null, {
									default: withCtx(() => [createTextVNode(toDisplayString(__props.title), 1)]),
									_: 1
								}),
								createVNode(VCardText, null, {
									default: withCtx(() => [createVNode(VCombobox, {
										modelValue: localValue.value,
										"onUpdate:modelValue": ($event) => localValue.value = $event,
										items: __props.dictItems,
										"item-title": __props.itemTitle,
										"item-value": __props.itemValue,
										label: __props.title,
										hint: __props.hint,
										multiple: "",
										chips: "",
										"closable-chips": "",
										clearable: "",
										"return-object": "",
										variant: "outlined",
										density: "comfortable",
										"persistent-hint": ""
									}, null, 8, [
										"modelValue",
										"onUpdate:modelValue",
										"items",
										"item-title",
										"item-value",
										"label",
										"hint"
									])]),
									_: 1
								}),
								createVNode(VCardActions, { class: "justify-end" }, {
									default: withCtx(() => [createVNode(VBtn, {
										variant: "text",
										onClick: close
									}, {
										default: withCtx(() => [createTextVNode(" Cancel ")]),
										_: 1
									}), createVNode(VBtn, {
										color: "primary",
										loading: __props.loading,
										onClick: submit
									}, {
										default: withCtx(() => [createTextVNode(" Save ")]),
										_: 1
									}, 8, ["loading"])]),
									_: 1
								})
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VCard, { rounded: "xl" }, {
						default: withCtx(() => [
							createVNode(VCardTitle, null, {
								default: withCtx(() => [createTextVNode(toDisplayString(__props.title), 1)]),
								_: 1
							}),
							createVNode(VCardText, null, {
								default: withCtx(() => [createVNode(VCombobox, {
									modelValue: localValue.value,
									"onUpdate:modelValue": ($event) => localValue.value = $event,
									items: __props.dictItems,
									"item-title": __props.itemTitle,
									"item-value": __props.itemValue,
									label: __props.title,
									hint: __props.hint,
									multiple: "",
									chips: "",
									"closable-chips": "",
									clearable: "",
									"return-object": "",
									variant: "outlined",
									density: "comfortable",
									"persistent-hint": ""
								}, null, 8, [
									"modelValue",
									"onUpdate:modelValue",
									"items",
									"item-title",
									"item-value",
									"label",
									"hint"
								])]),
								_: 1
							}),
							createVNode(VCardActions, { class: "justify-end" }, {
								default: withCtx(() => [createVNode(VBtn, {
									variant: "text",
									onClick: close
								}, {
									default: withCtx(() => [createTextVNode(" Cancel ")]),
									_: 1
								}), createVNode(VBtn, {
									color: "primary",
									loading: __props.loading,
									onClick: submit
								}, {
									default: withCtx(() => [createTextVNode(" Save ")]),
									_: 1
								}, 8, ["loading"])]),
								_: 1
							})
						]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$11 = _sfc_main$11.setup;
_sfc_main$11.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/UnitRelationManagerDialog.vue");
	return _sfc_setup$11 ? _sfc_setup$11(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Unit/Mail/UnitMailComposerDialog.vue
var _sfc_main$10 = {
	__name: "UnitMailComposerDialog",
	__ssrInlineRender: true,
	props: /* @__PURE__ */ mergeModels({
		unitId: {
			type: Number,
			required: true
		},
		recipients: {
			type: Array,
			default: () => []
		},
		unitFiles: {
			type: Array,
			default: () => []
		},
		initialStorageFiles: {
			type: Array,
			default: () => []
		},
		sending: Boolean
	}, {
		"modelValue": {
			type: Boolean,
			default: false
		},
		"modelModifiers": {}
	}),
	emits: /* @__PURE__ */ mergeModels(["sent", "open-templates"], ["update:modelValue"]),
	setup(__props, { emit: __emit }) {
		const model = useModel(__props, "modelValue");
		const props = __props;
		const emit = __emit;
		const to = ref([]);
		const cc = ref([]);
		const subject = ref("");
		const body = ref("");
		const localFiles = ref([]);
		const storageFiles = ref([]);
		const templates = ref([]);
		const selectedTemplateId = ref(null);
		const loadingTemplates = ref(false);
		const fileInput = ref(null);
		const recipientItems = computed(() => {
			return (props.recipients || []).filter((item) => item?.address).map((item) => ({
				...item,
				title: `${item.address}${item.source_label ? ` — ${item.source_label}` : ""}`,
				value: item.address
			}));
		});
		const storageFileItems = computed(() => {
			const fromUnit = props.unitFiles.map((file) => ({
				title: file.name || file.path,
				value: file.path
			}));
			const fromInitial = props.initialStorageFiles.map((path) => ({
				title: path,
				value: path
			}));
			return [...fromUnit, ...fromInitial].filter((item, index, array) => array.findIndex((candidate) => candidate.value === item.value) === index);
		});
		async function fetchTemplates() {
			loadingTemplates.value = true;
			try {
				const { data } = await axios.get("/api/mail-templates");
				templates.value = data ?? [];
			} catch (error) {
				console.error("Templates loading error:", error);
			} finally {
				loadingTemplates.value = false;
			}
		}
		function applyTemplate(templateId) {
			const template = templates.value.find((item) => item.id === templateId);
			if (!template) return;
			subject.value = template.subject || subject.value;
			body.value = template.body || body.value;
		}
		function onLocalFilesSelected(event) {
			localFiles.value = Array.from(event.target.files || []);
		}
		function removeLocalFile(index) {
			localFiles.value.splice(index, 1);
			if (!localFiles.value.length && fileInput.value) fileInput.value.value = "";
		}
		async function submit() {
			const formData = new FormData();
			to.value.forEach((address) => formData.append("to[]", address));
			cc.value.forEach((address) => formData.append("cc[]", address));
			formData.append("subject", subject.value);
			formData.append("body", body.value);
			storageFiles.value.forEach((path) => {
				formData.append("storage_files[]", path);
			});
			localFiles.value.forEach((file) => {
				formData.append("attachments[]", file);
			});
			await axios.post(`/api/units/${props.unitId}/mail/send`, formData, { headers: { "Content-Type": "multipart/form-data" } });
			emit("sent");
			close();
		}
		function close() {
			model.value = false;
		}
		function resetForm() {
			to.value = [];
			cc.value = [];
			subject.value = "";
			body.value = "";
			localFiles.value = [];
			storageFiles.value = [...props.initialStorageFiles];
			selectedTemplateId.value = null;
			if (fileInput.value) fileInput.value.value = "";
		}
		watch(model, async (value) => {
			if (value) {
				storageFiles.value = [...props.initialStorageFiles];
				if (!templates.value.length) await fetchTemplates();
			} else resetForm();
		});
		watch(selectedTemplateId, applyTemplate);
		watch(() => props.initialStorageFiles, (value) => {
			if (model.value) storageFiles.value = [...value];
		}, { deep: true });
		watch(() => props.recipients, (value) => {
			console.log("UnitMailComposerDialog recipients:", value);
			console.log("UnitMailComposerDialog recipientItems:", recipientItems.value);
		}, {
			immediate: true,
			deep: true
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VDialog, mergeProps({
				modelValue: model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"max-width": "980",
				scrollable: ""
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VCard, { class: "rounded border border-blue-900 bg-slate-950" }, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<div${_scopeId}><div class="text-blue-lighten-3"${_scopeId}> Написать письмо </div><div class="text-[10px] text-grey"${_scopeId}> Можно использовать шаблон и приложить локальные/S3 файлы </div></div>`);
											_push(ssrRenderComponent(VBtn, {
												icon: "mdi-close",
												variant: "text",
												onClick: close
											}, null, _parent, _scopeId));
										} else return [createVNode("div", null, [createVNode("div", { class: "text-blue-lighten-3" }, " Написать письмо "), createVNode("div", { class: "text-[10px] text-grey" }, " Можно использовать шаблон и приложить локальные/S3 файлы ")]), createVNode(VBtn, {
											icon: "mdi-close",
											variant: "text",
											onClick: close
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
								_push(ssrRenderComponent(VCardText, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VRow, { dense: "" }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(ssrRenderComponent(VCol, {
														cols: "12",
														lg: "8"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VAutocomplete, {
																modelValue: to.value,
																"onUpdate:modelValue": ($event) => to.value = $event,
																items: recipientItems.value,
																label: "Кому",
																"item-title": "title",
																"item-value": "value",
																variant: "outlined",
																density: "compact",
																multiple: "",
																chips: "",
																"closable-chips": ""
															}, null, _parent, _scopeId));
															else return [createVNode(VAutocomplete, {
																modelValue: to.value,
																"onUpdate:modelValue": ($event) => to.value = $event,
																items: recipientItems.value,
																label: "Кому",
																"item-title": "title",
																"item-value": "value",
																variant: "outlined",
																density: "compact",
																multiple: "",
																chips: "",
																"closable-chips": ""
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items"
															])];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCol, {
														cols: "12",
														lg: "4"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VAutocomplete, {
																modelValue: cc.value,
																"onUpdate:modelValue": ($event) => cc.value = $event,
																items: recipientItems.value,
																label: "CC",
																"item-title": "title",
																"item-value": "value",
																variant: "outlined",
																density: "compact",
																multiple: "",
																chips: "",
																"closable-chips": ""
															}, null, _parent, _scopeId));
															else return [createVNode(VAutocomplete, {
																modelValue: cc.value,
																"onUpdate:modelValue": ($event) => cc.value = $event,
																items: recipientItems.value,
																label: "CC",
																"item-title": "title",
																"item-value": "value",
																variant: "outlined",
																density: "compact",
																multiple: "",
																chips: "",
																"closable-chips": ""
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items"
															])];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCol, {
														cols: "12",
														lg: "8"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VSelect, {
																modelValue: selectedTemplateId.value,
																"onUpdate:modelValue": ($event) => selectedTemplateId.value = $event,
																items: templates.value,
																"item-title": "name",
																"item-value": "id",
																label: "Шаблон",
																variant: "outlined",
																density: "compact",
																clearable: "",
																loading: loadingTemplates.value
															}, null, _parent, _scopeId));
															else return [createVNode(VSelect, {
																modelValue: selectedTemplateId.value,
																"onUpdate:modelValue": ($event) => selectedTemplateId.value = $event,
																items: templates.value,
																"item-title": "name",
																"item-value": "id",
																label: "Шаблон",
																variant: "outlined",
																density: "compact",
																clearable: "",
																loading: loadingTemplates.value
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items",
																"loading"
															])];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCol, {
														cols: "12",
														lg: "4"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VBtn, {
																block: "",
																variant: "tonal",
																color: "blue",
																"prepend-icon": "mdi-file-document-edit-outline",
																onClick: ($event) => emit("open-templates")
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` Управлять шаблонами `);
																	else return [createTextVNode(" Управлять шаблонами ")];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VBtn, {
																block: "",
																variant: "tonal",
																color: "blue",
																"prepend-icon": "mdi-file-document-edit-outline",
																onClick: ($event) => emit("open-templates")
															}, {
																default: withCtx(() => [createTextVNode(" Управлять шаблонами ")]),
																_: 1
															}, 8, ["onClick"])];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCol, { cols: "12" }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VTextField, {
																modelValue: subject.value,
																"onUpdate:modelValue": ($event) => subject.value = $event,
																label: "Тема",
																variant: "outlined",
																density: "compact"
															}, null, _parent, _scopeId));
															else return [createVNode(VTextField, {
																modelValue: subject.value,
																"onUpdate:modelValue": ($event) => subject.value = $event,
																label: "Тема",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCol, { cols: "12" }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VTextarea, {
																modelValue: body.value,
																"onUpdate:modelValue": ($event) => body.value = $event,
																label: "Текст письма",
																variant: "outlined",
																rows: "10",
																"auto-grow": "",
																hint: "Доступные переменные: {{unit.name}}, {{unit.id}}, {{email.address}}",
																"persistent-hint": ""
															}, null, _parent, _scopeId));
															else return [createVNode(VTextarea, {
																modelValue: body.value,
																"onUpdate:modelValue": ($event) => body.value = $event,
																label: "Текст письма",
																variant: "outlined",
																rows: "10",
																"auto-grow": "",
																hint: "Доступные переменные: {{unit.name}}, {{unit.id}}, {{email.address}}",
																"persistent-hint": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCol, {
														cols: "12",
														lg: "6"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VSelect, {
																modelValue: storageFiles.value,
																"onUpdate:modelValue": ($event) => storageFiles.value = $event,
																items: storageFileItems.value,
																label: "Файлы Unit из хранилища",
																variant: "outlined",
																density: "compact",
																multiple: "",
																chips: "",
																"closable-chips": ""
															}, null, _parent, _scopeId));
															else return [createVNode(VSelect, {
																modelValue: storageFiles.value,
																"onUpdate:modelValue": ($event) => storageFiles.value = $event,
																items: storageFileItems.value,
																label: "Файлы Unit из хранилища",
																variant: "outlined",
																density: "compact",
																multiple: "",
																chips: "",
																"closable-chips": ""
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items"
															])];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCol, {
														cols: "12",
														lg: "6"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(`<input type="file" multiple class="d-none"${_scopeId}>`);
																_push(ssrRenderComponent(VBtn, {
																	block: "",
																	variant: "tonal",
																	color: "teal",
																	"prepend-icon": "mdi-paperclip",
																	onClick: ($event) => fileInput.value?.click()
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(` Прикрепить локальные файлы `);
																		else return [createTextVNode(" Прикрепить локальные файлы ")];
																	}),
																	_: 1
																}, _parent, _scopeId));
																if (localFiles.value.length) {
																	_push(`<div class="d-flex flex-wrap ga-1 mt-2"${_scopeId}><!--[-->`);
																	ssrRenderList(localFiles.value, (file, index) => {
																		_push(ssrRenderComponent(VChip, {
																			key: `${file.name}-${index}`,
																			size: "small",
																			color: "teal",
																			variant: "tonal",
																			closable: "",
																			"onClick:close": ($event) => removeLocalFile(index)
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`${ssrInterpolate(file.name)}`);
																				else return [createTextVNode(toDisplayString(file.name), 1)];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																	});
																	_push(`<!--]--></div>`);
																} else _push(`<!---->`);
															} else return [
																createVNode("input", {
																	ref_key: "fileInput",
																	ref: fileInput,
																	type: "file",
																	multiple: "",
																	class: "d-none",
																	onChange: onLocalFilesSelected
																}, null, 544),
																createVNode(VBtn, {
																	block: "",
																	variant: "tonal",
																	color: "teal",
																	"prepend-icon": "mdi-paperclip",
																	onClick: ($event) => fileInput.value?.click()
																}, {
																	default: withCtx(() => [createTextVNode(" Прикрепить локальные файлы ")]),
																	_: 1
																}, 8, ["onClick"]),
																localFiles.value.length ? (openBlock(), createBlock("div", {
																	key: 0,
																	class: "d-flex flex-wrap ga-1 mt-2"
																}, [(openBlock(true), createBlock(Fragment, null, renderList(localFiles.value, (file, index) => {
																	return openBlock(), createBlock(VChip, {
																		key: `${file.name}-${index}`,
																		size: "small",
																		color: "teal",
																		variant: "tonal",
																		closable: "",
																		"onClick:close": ($event) => removeLocalFile(index)
																	}, {
																		default: withCtx(() => [createTextVNode(toDisplayString(file.name), 1)]),
																		_: 2
																	}, 1032, ["onClick:close"]);
																}), 128))])) : createCommentVNode("", true)
															];
														}),
														_: 1
													}, _parent, _scopeId));
												} else return [
													createVNode(VCol, {
														cols: "12",
														lg: "8"
													}, {
														default: withCtx(() => [createVNode(VAutocomplete, {
															modelValue: to.value,
															"onUpdate:modelValue": ($event) => to.value = $event,
															items: recipientItems.value,
															label: "Кому",
															"item-title": "title",
															"item-value": "value",
															variant: "outlined",
															density: "compact",
															multiple: "",
															chips: "",
															"closable-chips": ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														lg: "4"
													}, {
														default: withCtx(() => [createVNode(VAutocomplete, {
															modelValue: cc.value,
															"onUpdate:modelValue": ($event) => cc.value = $event,
															items: recipientItems.value,
															label: "CC",
															"item-title": "title",
															"item-value": "value",
															variant: "outlined",
															density: "compact",
															multiple: "",
															chips: "",
															"closable-chips": ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														lg: "8"
													}, {
														default: withCtx(() => [createVNode(VSelect, {
															modelValue: selectedTemplateId.value,
															"onUpdate:modelValue": ($event) => selectedTemplateId.value = $event,
															items: templates.value,
															"item-title": "name",
															"item-value": "id",
															label: "Шаблон",
															variant: "outlined",
															density: "compact",
															clearable: "",
															loading: loadingTemplates.value
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items",
															"loading"
														])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														lg: "4"
													}, {
														default: withCtx(() => [createVNode(VBtn, {
															block: "",
															variant: "tonal",
															color: "blue",
															"prepend-icon": "mdi-file-document-edit-outline",
															onClick: ($event) => emit("open-templates")
														}, {
															default: withCtx(() => [createTextVNode(" Управлять шаблонами ")]),
															_: 1
														}, 8, ["onClick"])]),
														_: 1
													}),
													createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: subject.value,
															"onUpdate:modelValue": ($event) => subject.value = $event,
															label: "Тема",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VTextarea, {
															modelValue: body.value,
															"onUpdate:modelValue": ($event) => body.value = $event,
															label: "Текст письма",
															variant: "outlined",
															rows: "10",
															"auto-grow": "",
															hint: "Доступные переменные: {{unit.name}}, {{unit.id}}, {{email.address}}",
															"persistent-hint": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														lg: "6"
													}, {
														default: withCtx(() => [createVNode(VSelect, {
															modelValue: storageFiles.value,
															"onUpdate:modelValue": ($event) => storageFiles.value = $event,
															items: storageFileItems.value,
															label: "Файлы Unit из хранилища",
															variant: "outlined",
															density: "compact",
															multiple: "",
															chips: "",
															"closable-chips": ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														lg: "6"
													}, {
														default: withCtx(() => [
															createVNode("input", {
																ref_key: "fileInput",
																ref: fileInput,
																type: "file",
																multiple: "",
																class: "d-none",
																onChange: onLocalFilesSelected
															}, null, 544),
															createVNode(VBtn, {
																block: "",
																variant: "tonal",
																color: "teal",
																"prepend-icon": "mdi-paperclip",
																onClick: ($event) => fileInput.value?.click()
															}, {
																default: withCtx(() => [createTextVNode(" Прикрепить локальные файлы ")]),
																_: 1
															}, 8, ["onClick"]),
															localFiles.value.length ? (openBlock(), createBlock("div", {
																key: 0,
																class: "d-flex flex-wrap ga-1 mt-2"
															}, [(openBlock(true), createBlock(Fragment, null, renderList(localFiles.value, (file, index) => {
																return openBlock(), createBlock(VChip, {
																	key: `${file.name}-${index}`,
																	size: "small",
																	color: "teal",
																	variant: "tonal",
																	closable: "",
																	"onClick:close": ($event) => removeLocalFile(index)
																}, {
																	default: withCtx(() => [createTextVNode(toDisplayString(file.name), 1)]),
																	_: 2
																}, 1032, ["onClick:close"]);
															}), 128))])) : createCommentVNode("", true)
														]),
														_: 1
													})
												];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VRow, { dense: "" }, {
											default: withCtx(() => [
												createVNode(VCol, {
													cols: "12",
													lg: "8"
												}, {
													default: withCtx(() => [createVNode(VAutocomplete, {
														modelValue: to.value,
														"onUpdate:modelValue": ($event) => to.value = $event,
														items: recipientItems.value,
														label: "Кому",
														"item-title": "title",
														"item-value": "value",
														variant: "outlined",
														density: "compact",
														multiple: "",
														chips: "",
														"closable-chips": ""
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													lg: "4"
												}, {
													default: withCtx(() => [createVNode(VAutocomplete, {
														modelValue: cc.value,
														"onUpdate:modelValue": ($event) => cc.value = $event,
														items: recipientItems.value,
														label: "CC",
														"item-title": "title",
														"item-value": "value",
														variant: "outlined",
														density: "compact",
														multiple: "",
														chips: "",
														"closable-chips": ""
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													lg: "8"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: selectedTemplateId.value,
														"onUpdate:modelValue": ($event) => selectedTemplateId.value = $event,
														items: templates.value,
														"item-title": "name",
														"item-value": "id",
														label: "Шаблон",
														variant: "outlined",
														density: "compact",
														clearable: "",
														loading: loadingTemplates.value
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items",
														"loading"
													])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													lg: "4"
												}, {
													default: withCtx(() => [createVNode(VBtn, {
														block: "",
														variant: "tonal",
														color: "blue",
														"prepend-icon": "mdi-file-document-edit-outline",
														onClick: ($event) => emit("open-templates")
													}, {
														default: withCtx(() => [createTextVNode(" Управлять шаблонами ")]),
														_: 1
													}, 8, ["onClick"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: subject.value,
														"onUpdate:modelValue": ($event) => subject.value = $event,
														label: "Тема",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: body.value,
														"onUpdate:modelValue": ($event) => body.value = $event,
														label: "Текст письма",
														variant: "outlined",
														rows: "10",
														"auto-grow": "",
														hint: "Доступные переменные: {{unit.name}}, {{unit.id}}, {{email.address}}",
														"persistent-hint": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													lg: "6"
												}, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: storageFiles.value,
														"onUpdate:modelValue": ($event) => storageFiles.value = $event,
														items: storageFileItems.value,
														label: "Файлы Unit из хранилища",
														variant: "outlined",
														density: "compact",
														multiple: "",
														chips: "",
														"closable-chips": ""
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													])]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													lg: "6"
												}, {
													default: withCtx(() => [
														createVNode("input", {
															ref_key: "fileInput",
															ref: fileInput,
															type: "file",
															multiple: "",
															class: "d-none",
															onChange: onLocalFilesSelected
														}, null, 544),
														createVNode(VBtn, {
															block: "",
															variant: "tonal",
															color: "teal",
															"prepend-icon": "mdi-paperclip",
															onClick: ($event) => fileInput.value?.click()
														}, {
															default: withCtx(() => [createTextVNode(" Прикрепить локальные файлы ")]),
															_: 1
														}, 8, ["onClick"]),
														localFiles.value.length ? (openBlock(), createBlock("div", {
															key: 0,
															class: "d-flex flex-wrap ga-1 mt-2"
														}, [(openBlock(true), createBlock(Fragment, null, renderList(localFiles.value, (file, index) => {
															return openBlock(), createBlock(VChip, {
																key: `${file.name}-${index}`,
																size: "small",
																color: "teal",
																variant: "tonal",
																closable: "",
																"onClick:close": ($event) => removeLocalFile(index)
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(file.name), 1)]),
																_: 2
															}, 1032, ["onClick:close"]);
														}), 128))])) : createCommentVNode("", true)
													]),
													_: 1
												})
											]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCardActions, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VSpacer, null, null, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												variant: "text",
												onClick: close
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Отмена `);
													else return [createTextVNode(" Отмена ")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												color: "blue",
												variant: "elevated",
												loading: __props.sending,
												disabled: !to.value.length || !subject.value || !body.value,
												onClick: submit
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Отправить `);
													else return [createTextVNode(" Отправить ")];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VSpacer),
											createVNode(VBtn, {
												variant: "text",
												onClick: close
											}, {
												default: withCtx(() => [createTextVNode(" Отмена ")]),
												_: 1
											}),
											createVNode(VBtn, {
												color: "blue",
												variant: "elevated",
												loading: __props.sending,
												disabled: !to.value.length || !subject.value || !body.value,
												onClick: submit
											}, {
												default: withCtx(() => [createTextVNode(" Отправить ")]),
												_: 1
											}, 8, ["loading", "disabled"])
										];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
									default: withCtx(() => [createVNode("div", null, [createVNode("div", { class: "text-blue-lighten-3" }, " Написать письмо "), createVNode("div", { class: "text-[10px] text-grey" }, " Можно использовать шаблон и приложить локальные/S3 файлы ")]), createVNode(VBtn, {
										icon: "mdi-close",
										variant: "text",
										onClick: close
									})]),
									_: 1
								}),
								createVNode(VDivider),
								createVNode(VCardText, null, {
									default: withCtx(() => [createVNode(VRow, { dense: "" }, {
										default: withCtx(() => [
											createVNode(VCol, {
												cols: "12",
												lg: "8"
											}, {
												default: withCtx(() => [createVNode(VAutocomplete, {
													modelValue: to.value,
													"onUpdate:modelValue": ($event) => to.value = $event,
													items: recipientItems.value,
													label: "Кому",
													"item-title": "title",
													"item-value": "value",
													variant: "outlined",
													density: "compact",
													multiple: "",
													chips: "",
													"closable-chips": ""
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												lg: "4"
											}, {
												default: withCtx(() => [createVNode(VAutocomplete, {
													modelValue: cc.value,
													"onUpdate:modelValue": ($event) => cc.value = $event,
													items: recipientItems.value,
													label: "CC",
													"item-title": "title",
													"item-value": "value",
													variant: "outlined",
													density: "compact",
													multiple: "",
													chips: "",
													"closable-chips": ""
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												lg: "8"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: selectedTemplateId.value,
													"onUpdate:modelValue": ($event) => selectedTemplateId.value = $event,
													items: templates.value,
													"item-title": "name",
													"item-value": "id",
													label: "Шаблон",
													variant: "outlined",
													density: "compact",
													clearable: "",
													loading: loadingTemplates.value
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items",
													"loading"
												])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												lg: "4"
											}, {
												default: withCtx(() => [createVNode(VBtn, {
													block: "",
													variant: "tonal",
													color: "blue",
													"prepend-icon": "mdi-file-document-edit-outline",
													onClick: ($event) => emit("open-templates")
												}, {
													default: withCtx(() => [createTextVNode(" Управлять шаблонами ")]),
													_: 1
												}, 8, ["onClick"])]),
												_: 1
											}),
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: subject.value,
													"onUpdate:modelValue": ($event) => subject.value = $event,
													label: "Тема",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VTextarea, {
													modelValue: body.value,
													"onUpdate:modelValue": ($event) => body.value = $event,
													label: "Текст письма",
													variant: "outlined",
													rows: "10",
													"auto-grow": "",
													hint: "Доступные переменные: {{unit.name}}, {{unit.id}}, {{email.address}}",
													"persistent-hint": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												lg: "6"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: storageFiles.value,
													"onUpdate:modelValue": ($event) => storageFiles.value = $event,
													items: storageFileItems.value,
													label: "Файлы Unit из хранилища",
													variant: "outlined",
													density: "compact",
													multiple: "",
													chips: "",
													"closable-chips": ""
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												lg: "6"
											}, {
												default: withCtx(() => [
													createVNode("input", {
														ref_key: "fileInput",
														ref: fileInput,
														type: "file",
														multiple: "",
														class: "d-none",
														onChange: onLocalFilesSelected
													}, null, 544),
													createVNode(VBtn, {
														block: "",
														variant: "tonal",
														color: "teal",
														"prepend-icon": "mdi-paperclip",
														onClick: ($event) => fileInput.value?.click()
													}, {
														default: withCtx(() => [createTextVNode(" Прикрепить локальные файлы ")]),
														_: 1
													}, 8, ["onClick"]),
													localFiles.value.length ? (openBlock(), createBlock("div", {
														key: 0,
														class: "d-flex flex-wrap ga-1 mt-2"
													}, [(openBlock(true), createBlock(Fragment, null, renderList(localFiles.value, (file, index) => {
														return openBlock(), createBlock(VChip, {
															key: `${file.name}-${index}`,
															size: "small",
															color: "teal",
															variant: "tonal",
															closable: "",
															"onClick:close": ($event) => removeLocalFile(index)
														}, {
															default: withCtx(() => [createTextVNode(toDisplayString(file.name), 1)]),
															_: 2
														}, 1032, ["onClick:close"]);
													}), 128))])) : createCommentVNode("", true)
												]),
												_: 1
											})
										]),
										_: 1
									})]),
									_: 1
								}),
								createVNode(VCardActions, null, {
									default: withCtx(() => [
										createVNode(VSpacer),
										createVNode(VBtn, {
											variant: "text",
											onClick: close
										}, {
											default: withCtx(() => [createTextVNode(" Отмена ")]),
											_: 1
										}),
										createVNode(VBtn, {
											color: "blue",
											variant: "elevated",
											loading: __props.sending,
											disabled: !to.value.length || !subject.value || !body.value,
											onClick: submit
										}, {
											default: withCtx(() => [createTextVNode(" Отправить ")]),
											_: 1
										}, 8, ["loading", "disabled"])
									]),
									_: 1
								})
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VCard, { class: "rounded border border-blue-900 bg-slate-950" }, {
						default: withCtx(() => [
							createVNode(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
								default: withCtx(() => [createVNode("div", null, [createVNode("div", { class: "text-blue-lighten-3" }, " Написать письмо "), createVNode("div", { class: "text-[10px] text-grey" }, " Можно использовать шаблон и приложить локальные/S3 файлы ")]), createVNode(VBtn, {
									icon: "mdi-close",
									variant: "text",
									onClick: close
								})]),
								_: 1
							}),
							createVNode(VDivider),
							createVNode(VCardText, null, {
								default: withCtx(() => [createVNode(VRow, { dense: "" }, {
									default: withCtx(() => [
										createVNode(VCol, {
											cols: "12",
											lg: "8"
										}, {
											default: withCtx(() => [createVNode(VAutocomplete, {
												modelValue: to.value,
												"onUpdate:modelValue": ($event) => to.value = $event,
												items: recipientItems.value,
												label: "Кому",
												"item-title": "title",
												"item-value": "value",
												variant: "outlined",
												density: "compact",
												multiple: "",
												chips: "",
												"closable-chips": ""
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items"
											])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											lg: "4"
										}, {
											default: withCtx(() => [createVNode(VAutocomplete, {
												modelValue: cc.value,
												"onUpdate:modelValue": ($event) => cc.value = $event,
												items: recipientItems.value,
												label: "CC",
												"item-title": "title",
												"item-value": "value",
												variant: "outlined",
												density: "compact",
												multiple: "",
												chips: "",
												"closable-chips": ""
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items"
											])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											lg: "8"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: selectedTemplateId.value,
												"onUpdate:modelValue": ($event) => selectedTemplateId.value = $event,
												items: templates.value,
												"item-title": "name",
												"item-value": "id",
												label: "Шаблон",
												variant: "outlined",
												density: "compact",
												clearable: "",
												loading: loadingTemplates.value
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items",
												"loading"
											])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											lg: "4"
										}, {
											default: withCtx(() => [createVNode(VBtn, {
												block: "",
												variant: "tonal",
												color: "blue",
												"prepend-icon": "mdi-file-document-edit-outline",
												onClick: ($event) => emit("open-templates")
											}, {
												default: withCtx(() => [createTextVNode(" Управлять шаблонами ")]),
												_: 1
											}, 8, ["onClick"])]),
											_: 1
										}),
										createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: subject.value,
												"onUpdate:modelValue": ($event) => subject.value = $event,
												label: "Тема",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: body.value,
												"onUpdate:modelValue": ($event) => body.value = $event,
												label: "Текст письма",
												variant: "outlined",
												rows: "10",
												"auto-grow": "",
												hint: "Доступные переменные: {{unit.name}}, {{unit.id}}, {{email.address}}",
												"persistent-hint": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											lg: "6"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: storageFiles.value,
												"onUpdate:modelValue": ($event) => storageFiles.value = $event,
												items: storageFileItems.value,
												label: "Файлы Unit из хранилища",
												variant: "outlined",
												density: "compact",
												multiple: "",
												chips: "",
												"closable-chips": ""
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items"
											])]),
											_: 1
										}),
										createVNode(VCol, {
											cols: "12",
											lg: "6"
										}, {
											default: withCtx(() => [
												createVNode("input", {
													ref_key: "fileInput",
													ref: fileInput,
													type: "file",
													multiple: "",
													class: "d-none",
													onChange: onLocalFilesSelected
												}, null, 544),
												createVNode(VBtn, {
													block: "",
													variant: "tonal",
													color: "teal",
													"prepend-icon": "mdi-paperclip",
													onClick: ($event) => fileInput.value?.click()
												}, {
													default: withCtx(() => [createTextVNode(" Прикрепить локальные файлы ")]),
													_: 1
												}, 8, ["onClick"]),
												localFiles.value.length ? (openBlock(), createBlock("div", {
													key: 0,
													class: "d-flex flex-wrap ga-1 mt-2"
												}, [(openBlock(true), createBlock(Fragment, null, renderList(localFiles.value, (file, index) => {
													return openBlock(), createBlock(VChip, {
														key: `${file.name}-${index}`,
														size: "small",
														color: "teal",
														variant: "tonal",
														closable: "",
														"onClick:close": ($event) => removeLocalFile(index)
													}, {
														default: withCtx(() => [createTextVNode(toDisplayString(file.name), 1)]),
														_: 2
													}, 1032, ["onClick:close"]);
												}), 128))])) : createCommentVNode("", true)
											]),
											_: 1
										})
									]),
									_: 1
								})]),
								_: 1
							}),
							createVNode(VCardActions, null, {
								default: withCtx(() => [
									createVNode(VSpacer),
									createVNode(VBtn, {
										variant: "text",
										onClick: close
									}, {
										default: withCtx(() => [createTextVNode(" Отмена ")]),
										_: 1
									}),
									createVNode(VBtn, {
										color: "blue",
										variant: "elevated",
										loading: __props.sending,
										disabled: !to.value.length || !subject.value || !body.value,
										onClick: submit
									}, {
										default: withCtx(() => [createTextVNode(" Отправить ")]),
										_: 1
									}, 8, ["loading", "disabled"])
								]),
								_: 1
							})
						]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$10 = _sfc_main$10.setup;
_sfc_main$10.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/Mail/UnitMailComposerDialog.vue");
	return _sfc_setup$10 ? _sfc_setup$10(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Unit/UnitOverviewCard.vue
var _sfc_main$9 = {
	__name: "UnitOverviewCard",
	__ssrInlineRender: true,
	props: {
		unit: {
			type: Object,
			required: true
		},
		files: {
			type: Array,
			default: () => []
		},
		dict: {
			type: Object,
			default: () => ({})
		},
		loading: {
			type: Object,
			default: () => ({})
		}
	},
	emits: ["refresh"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const emojiDigitMap = {
			"0": "0️⃣",
			"1": "1️⃣",
			"2": "2️⃣",
			"3": "3️⃣",
			"4": "4️⃣",
			"5": "5️⃣",
			"6": "6️⃣",
			"7": "7️⃣",
			"8": "8️⃣",
			"9": "9️⃣"
		};
		function formatUnitIdToEmoji(id) {
			return String(id ?? "").split("").map((char) => emojiDigitMap[char] ?? char).join("");
		}
		const activeTab = ref("info");
		const dialogAttachEmail = ref(false);
		const dialogAddEmail = ref(false);
		const dialogLabels = ref(false);
		const dialogFields = ref(false);
		const dialogTelephones = ref(false);
		const dialogCities = ref(false);
		const savingLabels = ref(false);
		const savingFields = ref(false);
		const savingTelephones = ref(false);
		const savingCities = ref(false);
		const formAttachEmail = useForm({
			email_id: null,
			unit_id: props.unit.id
		});
		const formAddEmail = useForm({ address: null });
		function getIds(items = []) {
			return items.filter((item) => typeof item === "object" && item?.id).map((item) => item.id);
		}
		function getStrings(items = []) {
			return items.filter((item) => typeof item === "string" && item.trim() !== "").map((item) => item.trim());
		}
		function attachEmail() {
			formAttachEmail.unit_id = props.unit.id;
			formAttachEmail.post(route("emailgood.store"), {
				preserveState: true,
				preserveScroll: true,
				onSuccess: () => {
					formAttachEmail.reset();
					dialogAttachEmail.value = false;
					emit("refresh");
				}
			});
		}
		function storeEmail() {
			formAddEmail.post(route("web.email.store"), {
				preserveState: true,
				preserveScroll: true,
				onSuccess: () => {
					formAddEmail.reset();
					dialogAddEmail.value = false;
					emit("refresh");
				}
			});
		}
		async function syncLabels(payload) {
			savingLabels.value = true;
			try {
				const currentIds = (props.unit.labels || []).map((item) => item.id);
				const nextIds = getIds(payload);
				const newNames = getStrings(payload);
				const idsToDetach = currentIds.filter((id) => !nextIds.includes(id));
				await Promise.all(idsToDetach.map((id) => axios.delete(route("api.units.labels.detach", {
					unit: props.unit.id,
					label: id
				}))));
				await Promise.all(nextIds.filter((id) => !currentIds.includes(id)).map((id) => axios.post(route("api.units.labels.attach", props.unit.id), { label_id: id })));
				await Promise.all(newNames.map((name) => axios.post(route("api.units.labels.attach", props.unit.id), { name })));
				dialogLabels.value = false;
				emit("refresh");
			} catch (error) {
				console.error("Ошибка сохранения labels:", error);
			} finally {
				savingLabels.value = false;
			}
		}
		async function syncFields(payload) {
			savingFields.value = true;
			try {
				const currentIds = (props.unit.fields || []).map((item) => item.id);
				const nextIds = getIds(payload);
				const newNames = getStrings(payload);
				const idsToDetach = currentIds.filter((id) => !nextIds.includes(id));
				await Promise.all(idsToDetach.map((id) => axios.delete(route("api.units.fields.detach", {
					unit: props.unit.id,
					field: id
				}))));
				await Promise.all(nextIds.filter((id) => !currentIds.includes(id)).map((id) => axios.post(route("api.units.fields.attach", props.unit.id), { field_id: id })));
				await Promise.all(newNames.map((name) => axios.post(route("api.units.fields.attach", props.unit.id), { name })));
				dialogFields.value = false;
				emit("refresh");
			} catch (error) {
				console.error("Ошибка сохранения fields:", error);
			} finally {
				savingFields.value = false;
			}
		}
		async function syncTelephones(payload) {
			savingTelephones.value = true;
			try {
				const currentIds = (props.unit.telephones || []).map((item) => item.id);
				const nextIds = getIds(payload);
				const newNumbers = getStrings(payload);
				const idsToDetach = currentIds.filter((id) => !nextIds.includes(id));
				await Promise.all(idsToDetach.map((id) => axios.delete(route("api.units.telephones.detach", {
					unit: props.unit.id,
					telephone: id
				}))));
				await Promise.all(nextIds.filter((id) => !currentIds.includes(id)).map((id) => axios.post(route("api.units.telephones.attach", props.unit.id), { telephone_id: id })));
				await Promise.all(newNumbers.map((number) => axios.post(route("api.units.telephones.attach", props.unit.id), { number })));
				dialogTelephones.value = false;
				emit("refresh");
			} catch (error) {
				console.error("Ошибка сохранения telephones:", error);
			} finally {
				savingTelephones.value = false;
			}
		}
		async function syncCities(payload) {
			savingCities.value = true;
			try {
				const currentIds = (props.unit.cities || []).map((item) => item.id);
				const nextIds = getIds(payload);
				const newNames = getStrings(payload);
				const idsToDetach = currentIds.filter((id) => !nextIds.includes(id));
				await Promise.all(idsToDetach.map((id) => axios.delete(route("api.units.cities.detach", {
					unit: props.unit.id,
					city: id
				}))));
				await Promise.all(nextIds.filter((id) => !currentIds.includes(id)).map((id) => axios.post(route("api.units.cities.attach", props.unit.id), { city_id: id })));
				await Promise.all(newNames.map((name) => axios.post(route("api.units.cities.attach", props.unit.id), { name })));
				dialogCities.value = false;
				emit("refresh");
			} catch (error) {
				console.error("Ошибка сохранения cities:", error);
			} finally {
				savingCities.value = false;
			}
		}
		const quickMailDialog = ref(false);
		const quickMailFiles = ref([]);
		const quickMailRecipients = ref([]);
		function cloneRecipients(items = []) {
			return items.map((item) => ({ ...item }));
		}
		const unitMailRecipients = computed(() => {
			const result = [];
			(props.unit?.emails || []).forEach((email) => {
				if (!email?.address) return;
				result.push({
					id: email.id ?? email.address,
					address: email.address,
					name: email.name || null,
					source: "unit",
					source_label: "Unit"
				});
			});
			(props.unit?.entities || []).forEach((entity) => {
				(entity?.emails || []).forEach((email) => {
					if (!email?.address) return;
					result.push({
						id: email.id ?? email.address,
						address: email.address,
						name: email.name || null,
						source: "entity",
						source_label: `Entity: ${entity.name}`,
						entity_id: entity.id,
						entity_name: entity.name
					});
				});
			});
			return result.filter((email, index, array) => {
				return array.findIndex((item) => item.address === email.address) === index;
			});
		});
		function openFileMail(file) {
			quickMailFiles.value = file?.path ? [file.path] : [];
			quickMailRecipients.value = cloneRecipients(unitMailRecipients.value);
			quickMailDialog.value = true;
		}
		function openQuickMail() {
			quickMailFiles.value = [];
			quickMailRecipients.value = cloneRecipients(unitMailRecipients.value);
			quickMailDialog.value = true;
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(BaseSectionCard_default, mergeProps({
				title: "Unit Overview",
				icon: "mdi-factory",
				compact: ""
			}, _attrs), {
				actions: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VMenu, null, {
						activator: withCtx(({ props: menuProps }, _push, _parent, _scopeId) => {
							if (_push) _push(ssrRenderComponent(VBtn, mergeProps(menuProps, {
								icon: "mdi-dots-vertical",
								variant: "text",
								size: "small"
							}), null, _parent, _scopeId));
							else return [createVNode(VBtn, mergeProps(menuProps, {
								icon: "mdi-dots-vertical",
								variant: "text",
								size: "small"
							}), null, 16)];
						}),
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) _push(ssrRenderComponent(VList, { density: "compact" }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) {
										_push(ssrRenderComponent(VListItem, {
											title: "Write email",
											"prepend-icon": "mdi-email-plus-outline",
											onClick: openQuickMail
										}, null, _parent, _scopeId));
										_push(ssrRenderComponent(VListItem, {
											title: "Attach email",
											"prepend-icon": "mdi-email-link-outline",
											onClick: ($event) => dialogAttachEmail.value = true
										}, null, _parent, _scopeId));
									} else return [createVNode(VListItem, {
										title: "Write email",
										"prepend-icon": "mdi-email-plus-outline",
										onClick: openQuickMail
									}), createVNode(VListItem, {
										title: "Attach email",
										"prepend-icon": "mdi-email-link-outline",
										onClick: ($event) => dialogAttachEmail.value = true
									}, null, 8, ["onClick"])];
								}),
								_: 1
							}, _parent, _scopeId));
							else return [createVNode(VList, { density: "compact" }, {
								default: withCtx(() => [createVNode(VListItem, {
									title: "Write email",
									"prepend-icon": "mdi-email-plus-outline",
									onClick: openQuickMail
								}), createVNode(VListItem, {
									title: "Attach email",
									"prepend-icon": "mdi-email-link-outline",
									onClick: ($event) => dialogAttachEmail.value = true
								}, null, 8, ["onClick"])]),
								_: 1
							})];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VMenu, null, {
						activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps(menuProps, {
							icon: "mdi-dots-vertical",
							variant: "text",
							size: "small"
						}), null, 16)]),
						default: withCtx(() => [createVNode(VList, { density: "compact" }, {
							default: withCtx(() => [createVNode(VListItem, {
								title: "Write email",
								"prepend-icon": "mdi-email-plus-outline",
								onClick: openQuickMail
							}), createVNode(VListItem, {
								title: "Attach email",
								"prepend-icon": "mdi-email-link-outline",
								onClick: ($event) => dialogAttachEmail.value = true
							}, null, 8, ["onClick"])]),
							_: 1
						})]),
						_: 1
					})];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="d-flex align-start justify-space-between mb-3"${_scopeId}><div class="text-h4 font-weight-bold"${_scopeId}>${ssrInterpolate(__props.unit.name)}</div><div class="text-right"${_scopeId}><div class="text-caption text-medium-emphasis"${_scopeId}> ID </div><div class="text-h5 font-weight-bold"${_scopeId}>${ssrInterpolate(formatUnitIdToEmoji(__props.unit.id))}</div></div></div>`);
						_push(ssrRenderComponent(VTabs, {
							modelValue: activeTab.value,
							"onUpdate:modelValue": ($event) => activeTab.value = $event,
							color: "primary",
							density: "comfortable"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VTab, { value: "info" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`Info`);
											else return [createTextVNode("Info")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VTab, { value: "contacts" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`Contacts`);
											else return [createTextVNode("Contacts")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VTab, { value: "files" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`Files`);
											else return [createTextVNode("Files")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VTab, { value: "buildings" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`Buildings`);
											else return [createTextVNode("Buildings")];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VTab, { value: "info" }, {
										default: withCtx(() => [createTextVNode("Info")]),
										_: 1
									}),
									createVNode(VTab, { value: "contacts" }, {
										default: withCtx(() => [createTextVNode("Contacts")]),
										_: 1
									}),
									createVNode(VTab, { value: "files" }, {
										default: withCtx(() => [createTextVNode("Files")]),
										_: 1
									}),
									createVNode(VTab, { value: "buildings" }, {
										default: withCtx(() => [createTextVNode("Buildings")]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VWindow, {
							modelValue: activeTab.value,
							"onUpdate:modelValue": ($event) => activeTab.value = $event,
							class: "mt-4"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VWindowItem, { value: "info" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(`<div class="mb-4"${_scopeId}><div class="text-caption text-medium-emphasis mb-2"${_scopeId}>URI</div>`);
												_push(ssrRenderComponent(_sfc_main$12, {
													unit: __props.unit,
													dict: __props.dict,
													onRefresh: ($event) => emit("refresh")
												}, null, _parent, _scopeId));
												_push(`</div><div class="mb-4"${_scopeId}><div class="d-flex align-center justify-space-between mb-2"${_scopeId}><div class="text-caption text-medium-emphasis"${_scopeId}>Labels</div>`);
												_push(ssrRenderComponent(VBtn, {
													size: "x-small",
													variant: "text",
													onClick: ($event) => dialogLabels.value = true
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` Manage `);
														else return [createTextVNode(" Manage ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`</div>`);
												if (__props.unit.labels?.length) {
													_push(`<div class="d-flex flex-wrap ga-2"${_scopeId}><!--[-->`);
													ssrRenderList(__props.unit.labels, (label) => {
														_push(ssrRenderComponent(VChip, {
															key: label.id,
															size: "small",
															color: "primary",
															variant: "tonal"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`${ssrInterpolate(label.name)}`);
																else return [createTextVNode(toDisplayString(label.name), 1)];
															}),
															_: 2
														}, _parent, _scopeId));
													});
													_push(`<!--]--></div>`);
												} else _push(`<div class="text-caption text-disabled"${_scopeId}> No labels </div>`);
												_push(`</div><div class="mb-4"${_scopeId}><div class="d-flex align-center justify-space-between mb-2"${_scopeId}><div class="text-caption text-medium-emphasis"${_scopeId}>Fields</div>`);
												_push(ssrRenderComponent(VBtn, {
													size: "x-small",
													variant: "text",
													onClick: ($event) => dialogFields.value = true
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` Manage `);
														else return [createTextVNode(" Manage ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`</div>`);
												if (__props.unit.fields?.length) {
													_push(`<div class="d-flex flex-wrap ga-2"${_scopeId}><!--[-->`);
													ssrRenderList(__props.unit.fields, (field) => {
														_push(ssrRenderComponent(VChip, {
															key: field.id,
															size: "small",
															color: "deep-purple",
															variant: "tonal"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`${ssrInterpolate(field.name)}`);
																else return [createTextVNode(toDisplayString(field.name), 1)];
															}),
															_: 2
														}, _parent, _scopeId));
													});
													_push(`<!--]--></div>`);
												} else _push(`<div class="text-caption text-disabled"${_scopeId}> No fields </div>`);
												_push(`</div><div class="mb-2"${_scopeId}><div class="d-flex align-center justify-space-between mb-2"${_scopeId}><div class="text-caption text-medium-emphasis"${_scopeId}> Cities for offices / warehouses / production / delivery </div>`);
												_push(ssrRenderComponent(VBtn, {
													size: "x-small",
													variant: "text",
													onClick: ($event) => dialogCities.value = true
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` Manage `);
														else return [createTextVNode(" Manage ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`</div>`);
												if (__props.unit.cities?.length) {
													_push(`<div class="d-flex flex-wrap ga-2"${_scopeId}><!--[-->`);
													ssrRenderList(__props.unit.cities, (city) => {
														_push(ssrRenderComponent(VChip, {
															key: city.id,
															size: "small",
															color: "teal",
															variant: "tonal"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`${ssrInterpolate(city.name)}`);
																else return [createTextVNode(toDisplayString(city.name), 1)];
															}),
															_: 2
														}, _parent, _scopeId));
													});
													_push(`<!--]--></div>`);
												} else _push(`<div class="text-caption text-disabled"${_scopeId}> No cities </div>`);
												_push(`</div>`);
											} else return [
												createVNode("div", { class: "mb-4" }, [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, "URI"), createVNode(_sfc_main$12, {
													unit: __props.unit,
													dict: __props.dict,
													onRefresh: ($event) => emit("refresh")
												}, null, 8, [
													"unit",
													"dict",
													"onRefresh"
												])]),
												createVNode("div", { class: "mb-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between mb-2" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, "Labels"), createVNode(VBtn, {
													size: "x-small",
													variant: "text",
													onClick: ($event) => dialogLabels.value = true
												}, {
													default: withCtx(() => [createTextVNode(" Manage ")]),
													_: 1
												}, 8, ["onClick"])]), __props.unit.labels?.length ? (openBlock(), createBlock("div", {
													key: 0,
													class: "d-flex flex-wrap ga-2"
												}, [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.labels, (label) => {
													return openBlock(), createBlock(VChip, {
														key: label.id,
														size: "small",
														color: "primary",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(toDisplayString(label.name), 1)]),
														_: 2
													}, 1024);
												}), 128))])) : (openBlock(), createBlock("div", {
													key: 1,
													class: "text-caption text-disabled"
												}, " No labels "))]),
												createVNode("div", { class: "mb-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between mb-2" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, "Fields"), createVNode(VBtn, {
													size: "x-small",
													variant: "text",
													onClick: ($event) => dialogFields.value = true
												}, {
													default: withCtx(() => [createTextVNode(" Manage ")]),
													_: 1
												}, 8, ["onClick"])]), __props.unit.fields?.length ? (openBlock(), createBlock("div", {
													key: 0,
													class: "d-flex flex-wrap ga-2"
												}, [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.fields, (field) => {
													return openBlock(), createBlock(VChip, {
														key: field.id,
														size: "small",
														color: "deep-purple",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(toDisplayString(field.name), 1)]),
														_: 2
													}, 1024);
												}), 128))])) : (openBlock(), createBlock("div", {
													key: 1,
													class: "text-caption text-disabled"
												}, " No fields "))]),
												createVNode("div", { class: "mb-2" }, [createVNode("div", { class: "d-flex align-center justify-space-between mb-2" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Cities for offices / warehouses / production / delivery "), createVNode(VBtn, {
													size: "x-small",
													variant: "text",
													onClick: ($event) => dialogCities.value = true
												}, {
													default: withCtx(() => [createTextVNode(" Manage ")]),
													_: 1
												}, 8, ["onClick"])]), __props.unit.cities?.length ? (openBlock(), createBlock("div", {
													key: 0,
													class: "d-flex flex-wrap ga-2"
												}, [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.cities, (city) => {
													return openBlock(), createBlock(VChip, {
														key: city.id,
														size: "small",
														color: "teal",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
														_: 2
													}, 1024);
												}), 128))])) : (openBlock(), createBlock("div", {
													key: 1,
													class: "text-caption text-disabled"
												}, " No cities "))])
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VWindowItem, { value: "contacts" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(`<div class="mb-4"${_scopeId}><div class="d-flex align-center justify-space-between mb-2"${_scopeId}><div class="text-caption text-medium-emphasis"${_scopeId}>Telephones</div>`);
												_push(ssrRenderComponent(VBtn, {
													size: "x-small",
													variant: "text",
													onClick: ($event) => dialogTelephones.value = true
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` Manage `);
														else return [createTextVNode(" Manage ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`</div>`);
												if (__props.unit.telephones?.length) {
													_push(`<div class="d-flex flex-wrap ga-2"${_scopeId}><!--[-->`);
													ssrRenderList(__props.unit.telephones, (telephone) => {
														_push(ssrRenderComponent(VChip, {
															key: telephone.id,
															size: "small",
															color: "indigo",
															variant: "tonal"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`${ssrInterpolate(telephone.number)}`);
																else return [createTextVNode(toDisplayString(telephone.number), 1)];
															}),
															_: 2
														}, _parent, _scopeId));
													});
													_push(`<!--]--></div>`);
												} else _push(`<div class="text-caption text-disabled"${_scopeId}> No telephones </div>`);
												_push(`</div><div${_scopeId}><div class="text-caption text-medium-emphasis mb-2"${_scopeId}>Emails</div>`);
												_push(ssrRenderComponent(VList, {
													density: "compact",
													lines: "two"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<!--[-->`);
															ssrRenderList(__props.unit.emails || [], (email) => {
																_push(ssrRenderComponent(VListItem, {
																	key: email.id,
																	title: email.address,
																	subtitle: "Email"
																}, null, _parent, _scopeId));
															});
															_push(`<!--]-->`);
														} else return [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.emails || [], (email) => {
															return openBlock(), createBlock(VListItem, {
																key: email.id,
																title: email.address,
																subtitle: "Email"
															}, null, 8, ["title"]);
														}), 128))];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`</div>`);
											} else return [createVNode("div", { class: "mb-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between mb-2" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, "Telephones"), createVNode(VBtn, {
												size: "x-small",
												variant: "text",
												onClick: ($event) => dialogTelephones.value = true
											}, {
												default: withCtx(() => [createTextVNode(" Manage ")]),
												_: 1
											}, 8, ["onClick"])]), __props.unit.telephones?.length ? (openBlock(), createBlock("div", {
												key: 0,
												class: "d-flex flex-wrap ga-2"
											}, [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.telephones, (telephone) => {
												return openBlock(), createBlock(VChip, {
													key: telephone.id,
													size: "small",
													color: "indigo",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(telephone.number), 1)]),
													_: 2
												}, 1024);
											}), 128))])) : (openBlock(), createBlock("div", {
												key: 1,
												class: "text-caption text-disabled"
											}, " No telephones "))]), createVNode("div", null, [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, "Emails"), createVNode(VList, {
												density: "compact",
												lines: "two"
											}, {
												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.emails || [], (email) => {
													return openBlock(), createBlock(VListItem, {
														key: email.id,
														title: email.address,
														subtitle: "Email"
													}, null, 8, ["title"]);
												}), 128))]),
												_: 1
											})])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VWindowItem, { value: "files" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) if (__props.unit?.id) _push(ssrRenderComponent(_sfc_main$13, {
												"unit-id": Number(__props.unit.id),
												onSendFile: openFileMail
											}, null, _parent, _scopeId));
											else _push(`<!---->`);
											else return [__props.unit?.id ? (openBlock(), createBlock(_sfc_main$13, {
												key: 0,
												"unit-id": Number(__props.unit.id),
												onSendFile: openFileMail
											}, null, 8, ["unit-id"])) : createCommentVNode("", true)];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VWindowItem, { value: "buildings" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VList, { density: "compact" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(`<!--[-->`);
														ssrRenderList(__props.unit.buildings || [], (building) => {
															_push(ssrRenderComponent(VListItem, {
																key: building.id,
																title: building.address,
																subtitle: building.city?.name
															}, null, _parent, _scopeId));
														});
														_push(`<!--]-->`);
													} else return [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.buildings || [], (building) => {
														return openBlock(), createBlock(VListItem, {
															key: building.id,
															title: building.address,
															subtitle: building.city?.name
														}, null, 8, ["title", "subtitle"]);
													}), 128))];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VList, { density: "compact" }, {
												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.buildings || [], (building) => {
													return openBlock(), createBlock(VListItem, {
														key: building.id,
														title: building.address,
														subtitle: building.city?.name
													}, null, 8, ["title", "subtitle"]);
												}), 128))]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VWindowItem, { value: "info" }, {
										default: withCtx(() => [
											createVNode("div", { class: "mb-4" }, [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, "URI"), createVNode(_sfc_main$12, {
												unit: __props.unit,
												dict: __props.dict,
												onRefresh: ($event) => emit("refresh")
											}, null, 8, [
												"unit",
												"dict",
												"onRefresh"
											])]),
											createVNode("div", { class: "mb-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between mb-2" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, "Labels"), createVNode(VBtn, {
												size: "x-small",
												variant: "text",
												onClick: ($event) => dialogLabels.value = true
											}, {
												default: withCtx(() => [createTextVNode(" Manage ")]),
												_: 1
											}, 8, ["onClick"])]), __props.unit.labels?.length ? (openBlock(), createBlock("div", {
												key: 0,
												class: "d-flex flex-wrap ga-2"
											}, [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.labels, (label) => {
												return openBlock(), createBlock(VChip, {
													key: label.id,
													size: "small",
													color: "primary",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(label.name), 1)]),
													_: 2
												}, 1024);
											}), 128))])) : (openBlock(), createBlock("div", {
												key: 1,
												class: "text-caption text-disabled"
											}, " No labels "))]),
											createVNode("div", { class: "mb-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between mb-2" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, "Fields"), createVNode(VBtn, {
												size: "x-small",
												variant: "text",
												onClick: ($event) => dialogFields.value = true
											}, {
												default: withCtx(() => [createTextVNode(" Manage ")]),
												_: 1
											}, 8, ["onClick"])]), __props.unit.fields?.length ? (openBlock(), createBlock("div", {
												key: 0,
												class: "d-flex flex-wrap ga-2"
											}, [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.fields, (field) => {
												return openBlock(), createBlock(VChip, {
													key: field.id,
													size: "small",
													color: "deep-purple",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(field.name), 1)]),
													_: 2
												}, 1024);
											}), 128))])) : (openBlock(), createBlock("div", {
												key: 1,
												class: "text-caption text-disabled"
											}, " No fields "))]),
											createVNode("div", { class: "mb-2" }, [createVNode("div", { class: "d-flex align-center justify-space-between mb-2" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Cities for offices / warehouses / production / delivery "), createVNode(VBtn, {
												size: "x-small",
												variant: "text",
												onClick: ($event) => dialogCities.value = true
											}, {
												default: withCtx(() => [createTextVNode(" Manage ")]),
												_: 1
											}, 8, ["onClick"])]), __props.unit.cities?.length ? (openBlock(), createBlock("div", {
												key: 0,
												class: "d-flex flex-wrap ga-2"
											}, [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.cities, (city) => {
												return openBlock(), createBlock(VChip, {
													key: city.id,
													size: "small",
													color: "teal",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
													_: 2
												}, 1024);
											}), 128))])) : (openBlock(), createBlock("div", {
												key: 1,
												class: "text-caption text-disabled"
											}, " No cities "))])
										]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "contacts" }, {
										default: withCtx(() => [createVNode("div", { class: "mb-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between mb-2" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, "Telephones"), createVNode(VBtn, {
											size: "x-small",
											variant: "text",
											onClick: ($event) => dialogTelephones.value = true
										}, {
											default: withCtx(() => [createTextVNode(" Manage ")]),
											_: 1
										}, 8, ["onClick"])]), __props.unit.telephones?.length ? (openBlock(), createBlock("div", {
											key: 0,
											class: "d-flex flex-wrap ga-2"
										}, [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.telephones, (telephone) => {
											return openBlock(), createBlock(VChip, {
												key: telephone.id,
												size: "small",
												color: "indigo",
												variant: "tonal"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(telephone.number), 1)]),
												_: 2
											}, 1024);
										}), 128))])) : (openBlock(), createBlock("div", {
											key: 1,
											class: "text-caption text-disabled"
										}, " No telephones "))]), createVNode("div", null, [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, "Emails"), createVNode(VList, {
											density: "compact",
											lines: "two"
										}, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.emails || [], (email) => {
												return openBlock(), createBlock(VListItem, {
													key: email.id,
													title: email.address,
													subtitle: "Email"
												}, null, 8, ["title"]);
											}), 128))]),
											_: 1
										})])]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "files" }, {
										default: withCtx(() => [__props.unit?.id ? (openBlock(), createBlock(_sfc_main$13, {
											key: 0,
											"unit-id": Number(__props.unit.id),
											onSendFile: openFileMail
										}, null, 8, ["unit-id"])) : createCommentVNode("", true)]),
										_: 1
									}),
									createVNode(VWindowItem, { value: "buildings" }, {
										default: withCtx(() => [createVNode(VList, { density: "compact" }, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.buildings || [], (building) => {
												return openBlock(), createBlock(VListItem, {
													key: building.id,
													title: building.address,
													subtitle: building.city?.name
												}, null, 8, ["title", "subtitle"]);
											}), 128))]),
											_: 1
										})]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogAttachEmail.value,
							"onUpdate:modelValue": ($event) => dialogAttachEmail.value = $event,
							"max-width": "720"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, { rounded: "xl" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Attach Email`);
													else return [createTextVNode("Attach Email")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VAutocomplete, {
															modelValue: unref(formAttachEmail).email_id,
															"onUpdate:modelValue": ($event) => unref(formAttachEmail).email_id = $event,
															items: __props.dict.emails || [],
															"item-title": "address",
															"item-value": "id",
															label: "Emails",
															variant: "outlined",
															density: "comfortable"
														}, null, _parent, _scopeId));
														_push(`<div class="mt-4"${_scopeId}>`);
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogAddEmail.value = true
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` New email `);
																else return [createTextVNode(" New email ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(`</div>`);
													} else return [createVNode(VAutocomplete, {
														modelValue: unref(formAttachEmail).email_id,
														"onUpdate:modelValue": ($event) => unref(formAttachEmail).email_id = $event,
														items: __props.dict.emails || [],
														"item-title": "address",
														"item-value": "id",
														label: "Emails",
														variant: "outlined",
														density: "comfortable"
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													]), createVNode("div", { class: "mt-4" }, [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogAddEmail.value = true
													}, {
														default: withCtx(() => [createTextVNode(" New email ")]),
														_: 1
													}, 8, ["onClick"])])];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, { class: "justify-end" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogAttachEmail.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Cancel `);
																else return [createTextVNode(" Cancel ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "primary",
															loading: unref(formAttachEmail).processing,
															onClick: attachEmail
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Attach `);
																else return [createTextVNode(" Attach ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogAttachEmail.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Cancel ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "primary",
														loading: unref(formAttachEmail).processing,
														onClick: attachEmail
													}, {
														default: withCtx(() => [createTextVNode(" Attach ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Attach Email")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VAutocomplete, {
													modelValue: unref(formAttachEmail).email_id,
													"onUpdate:modelValue": ($event) => unref(formAttachEmail).email_id = $event,
													items: __props.dict.emails || [],
													"item-title": "address",
													"item-value": "id",
													label: "Emails",
													variant: "outlined",
													density: "comfortable"
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												]), createVNode("div", { class: "mt-4" }, [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogAddEmail.value = true
												}, {
													default: withCtx(() => [createTextVNode(" New email ")]),
													_: 1
												}, 8, ["onClick"])])]),
												_: 1
											}),
											createVNode(VCardActions, { class: "justify-end" }, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogAttachEmail.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Cancel ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "primary",
													loading: unref(formAttachEmail).processing,
													onClick: attachEmail
												}, {
													default: withCtx(() => [createTextVNode(" Attach ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, { rounded: "xl" }, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Attach Email")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [createVNode(VAutocomplete, {
												modelValue: unref(formAttachEmail).email_id,
												"onUpdate:modelValue": ($event) => unref(formAttachEmail).email_id = $event,
												items: __props.dict.emails || [],
												"item-title": "address",
												"item-value": "id",
												label: "Emails",
												variant: "outlined",
												density: "comfortable"
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items"
											]), createVNode("div", { class: "mt-4" }, [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogAddEmail.value = true
											}, {
												default: withCtx(() => [createTextVNode(" New email ")]),
												_: 1
											}, 8, ["onClick"])])]),
											_: 1
										}),
										createVNode(VCardActions, { class: "justify-end" }, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogAttachEmail.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Cancel ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "primary",
												loading: unref(formAttachEmail).processing,
												onClick: attachEmail
											}, {
												default: withCtx(() => [createTextVNode(" Attach ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogAddEmail.value,
							"onUpdate:modelValue": ($event) => dialogAddEmail.value = $event,
							"max-width": "640"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, { rounded: "xl" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`New Email`);
													else return [createTextVNode("New Email")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VTextField, {
														modelValue: unref(formAddEmail).address,
														"onUpdate:modelValue": ($event) => unref(formAddEmail).address = $event,
														label: "Email address",
														variant: "outlined",
														density: "comfortable"
													}, null, _parent, _scopeId));
													else return [createVNode(VTextField, {
														modelValue: unref(formAddEmail).address,
														"onUpdate:modelValue": ($event) => unref(formAddEmail).address = $event,
														label: "Email address",
														variant: "outlined",
														density: "comfortable"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, { class: "justify-end" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogAddEmail.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Cancel `);
																else return [createTextVNode(" Cancel ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "primary",
															loading: unref(formAddEmail).processing,
															onClick: storeEmail
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Save `);
																else return [createTextVNode(" Save ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogAddEmail.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Cancel ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "primary",
														loading: unref(formAddEmail).processing,
														onClick: storeEmail
													}, {
														default: withCtx(() => [createTextVNode(" Save ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("New Email")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: unref(formAddEmail).address,
													"onUpdate:modelValue": ($event) => unref(formAddEmail).address = $event,
													label: "Email address",
													variant: "outlined",
													density: "comfortable"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCardActions, { class: "justify-end" }, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogAddEmail.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Cancel ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "primary",
													loading: unref(formAddEmail).processing,
													onClick: storeEmail
												}, {
													default: withCtx(() => [createTextVNode(" Save ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, { rounded: "xl" }, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("New Email")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: unref(formAddEmail).address,
												"onUpdate:modelValue": ($event) => unref(formAddEmail).address = $event,
												label: "Email address",
												variant: "outlined",
												density: "comfortable"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}),
										createVNode(VCardActions, { class: "justify-end" }, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogAddEmail.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Cancel ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "primary",
												loading: unref(formAddEmail).processing,
												onClick: storeEmail
											}, {
												default: withCtx(() => [createTextVNode(" Save ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$11, {
							modelValue: dialogLabels.value,
							"onUpdate:modelValue": ($event) => dialogLabels.value = $event,
							title: "Manage Labels",
							items: __props.unit.labels || [],
							"dict-items": __props.dict.labels || [],
							"item-title": "name",
							"item-value": "id",
							loading: savingLabels.value,
							onSave: syncLabels
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$11, {
							modelValue: dialogFields.value,
							"onUpdate:modelValue": ($event) => dialogFields.value = $event,
							title: "Manage Fields",
							items: __props.unit.fields || [],
							"dict-items": __props.dict.fields || [],
							"item-title": "name",
							"item-value": "id",
							loading: savingFields.value,
							onSave: syncFields
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$11, {
							modelValue: dialogTelephones.value,
							"onUpdate:modelValue": ($event) => dialogTelephones.value = $event,
							title: "Manage Telephones",
							items: __props.unit.telephones || [],
							"dict-items": __props.dict.telephones || [],
							"item-title": "number",
							"item-value": "id",
							hint: "Можно выбрать номер из базы или ввести новый",
							loading: savingTelephones.value,
							onSave: syncTelephones
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$11, {
							modelValue: dialogCities.value,
							"onUpdate:modelValue": ($event) => dialogCities.value = $event,
							title: "Manage Cities",
							items: __props.unit.cities || [],
							"dict-items": __props.dict.cities || [],
							"item-title": "name",
							"item-value": "id",
							hint: "Города офисов, складов, производств и доставки",
							loading: savingCities.value,
							onSave: syncCities
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$10, {
							modelValue: quickMailDialog.value,
							"onUpdate:modelValue": ($event) => quickMailDialog.value = $event,
							"unit-id": __props.unit.id,
							recipients: quickMailRecipients.value,
							"initial-storage-files": quickMailFiles.value,
							onSent: ($event) => emit("refresh")
						}, null, _parent, _scopeId));
					} else return [
						createVNode("div", { class: "d-flex align-start justify-space-between mb-3" }, [createVNode("div", { class: "text-h4 font-weight-bold" }, toDisplayString(__props.unit.name), 1), createVNode("div", { class: "text-right" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID "), createVNode("div", { class: "text-h5 font-weight-bold" }, toDisplayString(formatUnitIdToEmoji(__props.unit.id)), 1)])]),
						createVNode(VTabs, {
							modelValue: activeTab.value,
							"onUpdate:modelValue": ($event) => activeTab.value = $event,
							color: "primary",
							density: "comfortable"
						}, {
							default: withCtx(() => [
								createVNode(VTab, { value: "info" }, {
									default: withCtx(() => [createTextVNode("Info")]),
									_: 1
								}),
								createVNode(VTab, { value: "contacts" }, {
									default: withCtx(() => [createTextVNode("Contacts")]),
									_: 1
								}),
								createVNode(VTab, { value: "files" }, {
									default: withCtx(() => [createTextVNode("Files")]),
									_: 1
								}),
								createVNode(VTab, { value: "buildings" }, {
									default: withCtx(() => [createTextVNode("Buildings")]),
									_: 1
								})
							]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VWindow, {
							modelValue: activeTab.value,
							"onUpdate:modelValue": ($event) => activeTab.value = $event,
							class: "mt-4"
						}, {
							default: withCtx(() => [
								createVNode(VWindowItem, { value: "info" }, {
									default: withCtx(() => [
										createVNode("div", { class: "mb-4" }, [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, "URI"), createVNode(_sfc_main$12, {
											unit: __props.unit,
											dict: __props.dict,
											onRefresh: ($event) => emit("refresh")
										}, null, 8, [
											"unit",
											"dict",
											"onRefresh"
										])]),
										createVNode("div", { class: "mb-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between mb-2" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, "Labels"), createVNode(VBtn, {
											size: "x-small",
											variant: "text",
											onClick: ($event) => dialogLabels.value = true
										}, {
											default: withCtx(() => [createTextVNode(" Manage ")]),
											_: 1
										}, 8, ["onClick"])]), __props.unit.labels?.length ? (openBlock(), createBlock("div", {
											key: 0,
											class: "d-flex flex-wrap ga-2"
										}, [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.labels, (label) => {
											return openBlock(), createBlock(VChip, {
												key: label.id,
												size: "small",
												color: "primary",
												variant: "tonal"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(label.name), 1)]),
												_: 2
											}, 1024);
										}), 128))])) : (openBlock(), createBlock("div", {
											key: 1,
											class: "text-caption text-disabled"
										}, " No labels "))]),
										createVNode("div", { class: "mb-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between mb-2" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, "Fields"), createVNode(VBtn, {
											size: "x-small",
											variant: "text",
											onClick: ($event) => dialogFields.value = true
										}, {
											default: withCtx(() => [createTextVNode(" Manage ")]),
											_: 1
										}, 8, ["onClick"])]), __props.unit.fields?.length ? (openBlock(), createBlock("div", {
											key: 0,
											class: "d-flex flex-wrap ga-2"
										}, [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.fields, (field) => {
											return openBlock(), createBlock(VChip, {
												key: field.id,
												size: "small",
												color: "deep-purple",
												variant: "tonal"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(field.name), 1)]),
												_: 2
											}, 1024);
										}), 128))])) : (openBlock(), createBlock("div", {
											key: 1,
											class: "text-caption text-disabled"
										}, " No fields "))]),
										createVNode("div", { class: "mb-2" }, [createVNode("div", { class: "d-flex align-center justify-space-between mb-2" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Cities for offices / warehouses / production / delivery "), createVNode(VBtn, {
											size: "x-small",
											variant: "text",
											onClick: ($event) => dialogCities.value = true
										}, {
											default: withCtx(() => [createTextVNode(" Manage ")]),
											_: 1
										}, 8, ["onClick"])]), __props.unit.cities?.length ? (openBlock(), createBlock("div", {
											key: 0,
											class: "d-flex flex-wrap ga-2"
										}, [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.cities, (city) => {
											return openBlock(), createBlock(VChip, {
												key: city.id,
												size: "small",
												color: "teal",
												variant: "tonal"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
												_: 2
											}, 1024);
										}), 128))])) : (openBlock(), createBlock("div", {
											key: 1,
											class: "text-caption text-disabled"
										}, " No cities "))])
									]),
									_: 1
								}),
								createVNode(VWindowItem, { value: "contacts" }, {
									default: withCtx(() => [createVNode("div", { class: "mb-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between mb-2" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, "Telephones"), createVNode(VBtn, {
										size: "x-small",
										variant: "text",
										onClick: ($event) => dialogTelephones.value = true
									}, {
										default: withCtx(() => [createTextVNode(" Manage ")]),
										_: 1
									}, 8, ["onClick"])]), __props.unit.telephones?.length ? (openBlock(), createBlock("div", {
										key: 0,
										class: "d-flex flex-wrap ga-2"
									}, [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.telephones, (telephone) => {
										return openBlock(), createBlock(VChip, {
											key: telephone.id,
											size: "small",
											color: "indigo",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(telephone.number), 1)]),
											_: 2
										}, 1024);
									}), 128))])) : (openBlock(), createBlock("div", {
										key: 1,
										class: "text-caption text-disabled"
									}, " No telephones "))]), createVNode("div", null, [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, "Emails"), createVNode(VList, {
										density: "compact",
										lines: "two"
									}, {
										default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.emails || [], (email) => {
											return openBlock(), createBlock(VListItem, {
												key: email.id,
												title: email.address,
												subtitle: "Email"
											}, null, 8, ["title"]);
										}), 128))]),
										_: 1
									})])]),
									_: 1
								}),
								createVNode(VWindowItem, { value: "files" }, {
									default: withCtx(() => [__props.unit?.id ? (openBlock(), createBlock(_sfc_main$13, {
										key: 0,
										"unit-id": Number(__props.unit.id),
										onSendFile: openFileMail
									}, null, 8, ["unit-id"])) : createCommentVNode("", true)]),
									_: 1
								}),
								createVNode(VWindowItem, { value: "buildings" }, {
									default: withCtx(() => [createVNode(VList, { density: "compact" }, {
										default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.unit.buildings || [], (building) => {
											return openBlock(), createBlock(VListItem, {
												key: building.id,
												title: building.address,
												subtitle: building.city?.name
											}, null, 8, ["title", "subtitle"]);
										}), 128))]),
										_: 1
									})]),
									_: 1
								})
							]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VDialog, {
							modelValue: dialogAttachEmail.value,
							"onUpdate:modelValue": ($event) => dialogAttachEmail.value = $event,
							"max-width": "720"
						}, {
							default: withCtx(() => [createVNode(VCard, { rounded: "xl" }, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Attach Email")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VAutocomplete, {
											modelValue: unref(formAttachEmail).email_id,
											"onUpdate:modelValue": ($event) => unref(formAttachEmail).email_id = $event,
											items: __props.dict.emails || [],
											"item-title": "address",
											"item-value": "id",
											label: "Emails",
											variant: "outlined",
											density: "comfortable"
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"items"
										]), createVNode("div", { class: "mt-4" }, [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogAddEmail.value = true
										}, {
											default: withCtx(() => [createTextVNode(" New email ")]),
											_: 1
										}, 8, ["onClick"])])]),
										_: 1
									}),
									createVNode(VCardActions, { class: "justify-end" }, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogAttachEmail.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Cancel ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "primary",
											loading: unref(formAttachEmail).processing,
											onClick: attachEmail
										}, {
											default: withCtx(() => [createTextVNode(" Attach ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VDialog, {
							modelValue: dialogAddEmail.value,
							"onUpdate:modelValue": ($event) => dialogAddEmail.value = $event,
							"max-width": "640"
						}, {
							default: withCtx(() => [createVNode(VCard, { rounded: "xl" }, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("New Email")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: unref(formAddEmail).address,
											"onUpdate:modelValue": ($event) => unref(formAddEmail).address = $event,
											label: "Email address",
											variant: "outlined",
											density: "comfortable"
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}),
									createVNode(VCardActions, { class: "justify-end" }, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogAddEmail.value = false
										}, {
											default: withCtx(() => [createTextVNode(" Cancel ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "primary",
											loading: unref(formAddEmail).processing,
											onClick: storeEmail
										}, {
											default: withCtx(() => [createTextVNode(" Save ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(_sfc_main$11, {
							modelValue: dialogLabels.value,
							"onUpdate:modelValue": ($event) => dialogLabels.value = $event,
							title: "Manage Labels",
							items: __props.unit.labels || [],
							"dict-items": __props.dict.labels || [],
							"item-title": "name",
							"item-value": "id",
							loading: savingLabels.value,
							onSave: syncLabels
						}, null, 8, [
							"modelValue",
							"onUpdate:modelValue",
							"items",
							"dict-items",
							"loading"
						]),
						createVNode(_sfc_main$11, {
							modelValue: dialogFields.value,
							"onUpdate:modelValue": ($event) => dialogFields.value = $event,
							title: "Manage Fields",
							items: __props.unit.fields || [],
							"dict-items": __props.dict.fields || [],
							"item-title": "name",
							"item-value": "id",
							loading: savingFields.value,
							onSave: syncFields
						}, null, 8, [
							"modelValue",
							"onUpdate:modelValue",
							"items",
							"dict-items",
							"loading"
						]),
						createVNode(_sfc_main$11, {
							modelValue: dialogTelephones.value,
							"onUpdate:modelValue": ($event) => dialogTelephones.value = $event,
							title: "Manage Telephones",
							items: __props.unit.telephones || [],
							"dict-items": __props.dict.telephones || [],
							"item-title": "number",
							"item-value": "id",
							hint: "Можно выбрать номер из базы или ввести новый",
							loading: savingTelephones.value,
							onSave: syncTelephones
						}, null, 8, [
							"modelValue",
							"onUpdate:modelValue",
							"items",
							"dict-items",
							"loading"
						]),
						createVNode(_sfc_main$11, {
							modelValue: dialogCities.value,
							"onUpdate:modelValue": ($event) => dialogCities.value = $event,
							title: "Manage Cities",
							items: __props.unit.cities || [],
							"dict-items": __props.dict.cities || [],
							"item-title": "name",
							"item-value": "id",
							hint: "Города офисов, складов, производств и доставки",
							loading: savingCities.value,
							onSave: syncCities
						}, null, 8, [
							"modelValue",
							"onUpdate:modelValue",
							"items",
							"dict-items",
							"loading"
						]),
						createVNode(_sfc_main$10, {
							modelValue: quickMailDialog.value,
							"onUpdate:modelValue": ($event) => quickMailDialog.value = $event,
							"unit-id": __props.unit.id,
							recipients: quickMailRecipients.value,
							"initial-storage-files": quickMailFiles.value,
							onSent: ($event) => emit("refresh")
						}, null, 8, [
							"modelValue",
							"onUpdate:modelValue",
							"unit-id",
							"recipients",
							"initial-storage-files",
							"onSent"
						])
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$9 = _sfc_main$9.setup;
_sfc_main$9.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/UnitOverviewCard.vue");
	return _sfc_setup$9 ? _sfc_setup$9(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Unit/UnitEntitiesCard.vue
var _sfc_main$8 = {
	__name: "UnitEntitiesCard",
	__ssrInlineRender: true,
	props: {
		unit: Object,
		dict: Object
	},
	emits: ["refresh"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const dialogAttachEntity = ref(false);
		const dialogCreateEntity = ref(false);
		const formAttachEntity = useForm({
			entity_id: null,
			unit_id: props.unit.id
		});
		const formEntity = useForm({
			name: null,
			entity_classification_id: null,
			telephones: []
		});
		const headersEntities = [
			{
				title: "Вид",
				key: "classification.name"
			},
			{
				title: "Name",
				key: "name"
			},
			{
				title: "Телефоны",
				key: "telephones"
			}
		];
		function attachEntity() {
			formAttachEntity.post(route("api.entity_unit.store"), {
				preserveState: true,
				onSuccess: async () => {
					formAttachEntity.reset();
					dialogAttachEntity.value = false;
					emit("refresh");
				}
			});
		}
		function storeEntity() {
			formEntity.post(route("api.entity.store"), {
				preserveState: true,
				onSuccess: async () => {
					formEntity.reset();
					dialogCreateEntity.value = false;
					emit("refresh");
				}
			});
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(BaseSectionCard_default, mergeProps({
				title: "Entities",
				icon: "mdi-domain"
			}, _attrs), {
				actions: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="d-flex ga-2"${_scopeId}>`);
						_push(ssrRenderComponent(VBtn, {
							size: "small",
							variant: "text",
							onClick: ($event) => dialogAttachEntity.value = true
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Attach `);
								else return [createTextVNode(" Attach ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VBtn, {
							size: "small",
							variant: "text",
							onClick: ($event) => dialogCreateEntity.value = true
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Create `);
								else return [createTextVNode(" Create ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div>`);
					} else return [createVNode("div", { class: "d-flex ga-2" }, [createVNode(VBtn, {
						size: "small",
						variant: "text",
						onClick: ($event) => dialogAttachEntity.value = true
					}, {
						default: withCtx(() => [createTextVNode(" Attach ")]),
						_: 1
					}, 8, ["onClick"]), createVNode(VBtn, {
						size: "small",
						variant: "text",
						onClick: ($event) => dialogCreateEntity.value = true
					}, {
						default: withCtx(() => [createTextVNode(" Create ")]),
						_: 1
					}, 8, ["onClick"])])];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VDataTable, {
							items: __props.unit.entities || [],
							headers: headersEntities,
							density: "compact",
							class: "border rounded-lg",
							"items-per-page": "5"
						}, {
							"item.name": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(`<div class="font-weight-medium"${_scopeId}>${ssrInterpolate(item.name)}</div>`);
								else return [createVNode("div", { class: "font-weight-medium" }, toDisplayString(item.name), 1)];
							}),
							"item.telephones": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<div class="d-flex flex-column"${_scopeId}><!--[-->`);
									ssrRenderList(item.telephones, (telephone) => {
										_push(`<span class="text-caption"${_scopeId}>${ssrInterpolate(telephone.number)}</span>`);
									});
									_push(`<!--]--></div>`);
								} else return [createVNode("div", { class: "d-flex flex-column" }, [(openBlock(true), createBlock(Fragment, null, renderList(item.telephones, (telephone) => {
									return openBlock(), createBlock("span", {
										key: telephone.id,
										class: "text-caption"
									}, toDisplayString(telephone.number), 1);
								}), 128))])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogAttachEntity.value,
							"onUpdate:modelValue": ($event) => dialogAttachEntity.value = $event,
							"max-width": "720"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, { rounded: "xl" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Attach Entity`);
													else return [createTextVNode("Attach Entity")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VAutocomplete, {
														modelValue: unref(formAttachEntity).entity_id,
														"onUpdate:modelValue": ($event) => unref(formAttachEntity).entity_id = $event,
														items: __props.dict.entities,
														"item-title": "name",
														"item-value": "id",
														label: "Entity",
														variant: "outlined",
														density: "comfortable"
													}, null, _parent, _scopeId));
													else return [createVNode(VAutocomplete, {
														modelValue: unref(formAttachEntity).entity_id,
														"onUpdate:modelValue": ($event) => unref(formAttachEntity).entity_id = $event,
														items: __props.dict.entities,
														"item-title": "name",
														"item-value": "id",
														label: "Entity",
														variant: "outlined",
														density: "comfortable"
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													])];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, { class: "justify-end" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogAttachEntity.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Cancel`);
																else return [createTextVNode("Cancel")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "primary",
															onClick: attachEntity,
															loading: unref(formAttachEntity).processing
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Attach `);
																else return [createTextVNode(" Attach ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogAttachEntity.value = false
													}, {
														default: withCtx(() => [createTextVNode("Cancel")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "primary",
														onClick: attachEntity,
														loading: unref(formAttachEntity).processing
													}, {
														default: withCtx(() => [createTextVNode(" Attach ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Attach Entity")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VAutocomplete, {
													modelValue: unref(formAttachEntity).entity_id,
													"onUpdate:modelValue": ($event) => unref(formAttachEntity).entity_id = $event,
													items: __props.dict.entities,
													"item-title": "name",
													"item-value": "id",
													label: "Entity",
													variant: "outlined",
													density: "comfortable"
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												])]),
												_: 1
											}),
											createVNode(VCardActions, { class: "justify-end" }, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogAttachEntity.value = false
												}, {
													default: withCtx(() => [createTextVNode("Cancel")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "primary",
													onClick: attachEntity,
													loading: unref(formAttachEntity).processing
												}, {
													default: withCtx(() => [createTextVNode(" Attach ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, { rounded: "xl" }, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Attach Entity")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [createVNode(VAutocomplete, {
												modelValue: unref(formAttachEntity).entity_id,
												"onUpdate:modelValue": ($event) => unref(formAttachEntity).entity_id = $event,
												items: __props.dict.entities,
												"item-title": "name",
												"item-value": "id",
												label: "Entity",
												variant: "outlined",
												density: "comfortable"
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items"
											])]),
											_: 1
										}),
										createVNode(VCardActions, { class: "justify-end" }, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogAttachEntity.value = false
											}, {
												default: withCtx(() => [createTextVNode("Cancel")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "primary",
												onClick: attachEntity,
												loading: unref(formAttachEntity).processing
											}, {
												default: withCtx(() => [createTextVNode(" Attach ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogCreateEntity.value,
							"onUpdate:modelValue": ($event) => dialogCreateEntity.value = $event,
							"max-width": "820"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, { rounded: "xl" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Create Entity`);
													else return [createTextVNode("Create Entity")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VRow, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(ssrRenderComponent(VCol, { cols: "12" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VSelect, {
																			modelValue: unref(formEntity).entity_classification_id,
																			"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																			items: __props.dict.entityClassifications,
																			"item-title": "name",
																			"item-value": "id",
																			label: "Вид",
																			variant: "outlined",
																			density: "comfortable"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VSelect, {
																			modelValue: unref(formEntity).entity_classification_id,
																			"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																			items: __props.dict.entityClassifications,
																			"item-title": "name",
																			"item-value": "id",
																			label: "Вид",
																			variant: "outlined",
																			density: "comfortable"
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"items"
																		])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, { cols: "12" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			modelValue: unref(formEntity).name,
																			"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																			label: "Name",
																			variant: "outlined",
																			density: "comfortable"
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			modelValue: unref(formEntity).name,
																			"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																			label: "Name",
																			variant: "outlined",
																			density: "comfortable"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, { cols: "12" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VAutocomplete, {
																			modelValue: unref(formEntity).telephones,
																			"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																			items: __props.dict.telephones,
																			"item-title": "number",
																			"item-value": "id",
																			label: "Telephones",
																			variant: "outlined",
																			density: "comfortable",
																			multiple: "",
																			chips: ""
																		}, null, _parent, _scopeId));
																		else return [createVNode(VAutocomplete, {
																			modelValue: unref(formEntity).telephones,
																			"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																			items: __props.dict.telephones,
																			"item-title": "number",
																			"item-value": "id",
																			label: "Telephones",
																			variant: "outlined",
																			density: "comfortable",
																			multiple: "",
																			chips: ""
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"items"
																		])];
																	}),
																	_: 1
																}, _parent, _scopeId));
															} else return [
																createVNode(VCol, { cols: "12" }, {
																	default: withCtx(() => [createVNode(VSelect, {
																		modelValue: unref(formEntity).entity_classification_id,
																		"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																		items: __props.dict.entityClassifications,
																		"item-title": "name",
																		"item-value": "id",
																		label: "Вид",
																		variant: "outlined",
																		density: "comfortable"
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items"
																	])]),
																	_: 1
																}),
																createVNode(VCol, { cols: "12" }, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formEntity).name,
																		"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																		label: "Name",
																		variant: "outlined",
																		density: "comfortable"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VCol, { cols: "12" }, {
																	default: withCtx(() => [createVNode(VAutocomplete, {
																		modelValue: unref(formEntity).telephones,
																		"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																		items: __props.dict.telephones,
																		"item-title": "number",
																		"item-value": "id",
																		label: "Telephones",
																		variant: "outlined",
																		density: "comfortable",
																		multiple: "",
																		chips: ""
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items"
																	])]),
																	_: 1
																})
															];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(VRow, null, {
														default: withCtx(() => [
															createVNode(VCol, { cols: "12" }, {
																default: withCtx(() => [createVNode(VSelect, {
																	modelValue: unref(formEntity).entity_classification_id,
																	"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																	items: __props.dict.entityClassifications,
																	"item-title": "name",
																	"item-value": "id",
																	label: "Вид",
																	variant: "outlined",
																	density: "comfortable"
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items"
																])]),
																_: 1
															}),
															createVNode(VCol, { cols: "12" }, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(formEntity).name,
																	"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																	label: "Name",
																	variant: "outlined",
																	density: "comfortable"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, { cols: "12" }, {
																default: withCtx(() => [createVNode(VAutocomplete, {
																	modelValue: unref(formEntity).telephones,
																	"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																	items: __props.dict.telephones,
																	"item-title": "number",
																	"item-value": "id",
																	label: "Telephones",
																	variant: "outlined",
																	density: "comfortable",
																	multiple: "",
																	chips: ""
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items"
																])]),
																_: 1
															})
														]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, { class: "justify-end" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogCreateEntity.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Cancel`);
																else return [createTextVNode("Cancel")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "primary",
															onClick: storeEntity,
															loading: unref(formEntity).processing
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Save `);
																else return [createTextVNode(" Save ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogCreateEntity.value = false
													}, {
														default: withCtx(() => [createTextVNode("Cancel")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "primary",
														onClick: storeEntity,
														loading: unref(formEntity).processing
													}, {
														default: withCtx(() => [createTextVNode(" Save ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Create Entity")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [
														createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(VSelect, {
																modelValue: unref(formEntity).entity_classification_id,
																"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
																items: __props.dict.entityClassifications,
																"item-title": "name",
																"item-value": "id",
																label: "Вид",
																variant: "outlined",
																density: "comfortable"
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items"
															])]),
															_: 1
														}),
														createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: unref(formEntity).name,
																"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
																label: "Name",
																variant: "outlined",
																density: "comfortable"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}),
														createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(VAutocomplete, {
																modelValue: unref(formEntity).telephones,
																"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
																items: __props.dict.telephones,
																"item-title": "number",
																"item-value": "id",
																label: "Telephones",
																variant: "outlined",
																density: "comfortable",
																multiple: "",
																chips: ""
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items"
															])]),
															_: 1
														})
													]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCardActions, { class: "justify-end" }, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogCreateEntity.value = false
												}, {
													default: withCtx(() => [createTextVNode("Cancel")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "primary",
													onClick: storeEntity,
													loading: unref(formEntity).processing
												}, {
													default: withCtx(() => [createTextVNode(" Save ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, { rounded: "xl" }, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Create Entity")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [
													createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VSelect, {
															modelValue: unref(formEntity).entity_classification_id,
															"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
															items: __props.dict.entityClassifications,
															"item-title": "name",
															"item-value": "id",
															label: "Вид",
															variant: "outlined",
															density: "comfortable"
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														])]),
														_: 1
													}),
													createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: unref(formEntity).name,
															"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
															label: "Name",
															variant: "outlined",
															density: "comfortable"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VAutocomplete, {
															modelValue: unref(formEntity).telephones,
															"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
															items: __props.dict.telephones,
															"item-title": "number",
															"item-value": "id",
															label: "Telephones",
															variant: "outlined",
															density: "comfortable",
															multiple: "",
															chips: ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														])]),
														_: 1
													})
												]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VCardActions, { class: "justify-end" }, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogCreateEntity.value = false
											}, {
												default: withCtx(() => [createTextVNode("Cancel")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "primary",
												onClick: storeEntity,
												loading: unref(formEntity).processing
											}, {
												default: withCtx(() => [createTextVNode(" Save ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode(VDataTable, {
							items: __props.unit.entities || [],
							headers: headersEntities,
							density: "compact",
							class: "border rounded-lg",
							"items-per-page": "5"
						}, {
							"item.name": withCtx(({ item }) => [createVNode("div", { class: "font-weight-medium" }, toDisplayString(item.name), 1)]),
							"item.telephones": withCtx(({ item }) => [createVNode("div", { class: "d-flex flex-column" }, [(openBlock(true), createBlock(Fragment, null, renderList(item.telephones, (telephone) => {
								return openBlock(), createBlock("span", {
									key: telephone.id,
									class: "text-caption"
								}, toDisplayString(telephone.number), 1);
							}), 128))])]),
							_: 1
						}, 8, ["items"]),
						createVNode(VDialog, {
							modelValue: dialogAttachEntity.value,
							"onUpdate:modelValue": ($event) => dialogAttachEntity.value = $event,
							"max-width": "720"
						}, {
							default: withCtx(() => [createVNode(VCard, { rounded: "xl" }, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Attach Entity")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VAutocomplete, {
											modelValue: unref(formAttachEntity).entity_id,
											"onUpdate:modelValue": ($event) => unref(formAttachEntity).entity_id = $event,
											items: __props.dict.entities,
											"item-title": "name",
											"item-value": "id",
											label: "Entity",
											variant: "outlined",
											density: "comfortable"
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"items"
										])]),
										_: 1
									}),
									createVNode(VCardActions, { class: "justify-end" }, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogAttachEntity.value = false
										}, {
											default: withCtx(() => [createTextVNode("Cancel")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "primary",
											onClick: attachEntity,
											loading: unref(formAttachEntity).processing
										}, {
											default: withCtx(() => [createTextVNode(" Attach ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VDialog, {
							modelValue: dialogCreateEntity.value,
							"onUpdate:modelValue": ($event) => dialogCreateEntity.value = $event,
							"max-width": "820"
						}, {
							default: withCtx(() => [createVNode(VCard, { rounded: "xl" }, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Create Entity")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VRow, null, {
											default: withCtx(() => [
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VSelect, {
														modelValue: unref(formEntity).entity_classification_id,
														"onUpdate:modelValue": ($event) => unref(formEntity).entity_classification_id = $event,
														items: __props.dict.entityClassifications,
														"item-title": "name",
														"item-value": "id",
														label: "Вид",
														variant: "outlined",
														density: "comfortable"
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: unref(formEntity).name,
														"onUpdate:modelValue": ($event) => unref(formEntity).name = $event,
														label: "Name",
														variant: "outlined",
														density: "comfortable"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}),
												createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VAutocomplete, {
														modelValue: unref(formEntity).telephones,
														"onUpdate:modelValue": ($event) => unref(formEntity).telephones = $event,
														items: __props.dict.telephones,
														"item-title": "number",
														"item-value": "id",
														label: "Telephones",
														variant: "outlined",
														density: "comfortable",
														multiple: "",
														chips: ""
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													])]),
													_: 1
												})
											]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCardActions, { class: "justify-end" }, {
										default: withCtx(() => [createVNode(VBtn, {
											variant: "text",
											onClick: ($event) => dialogCreateEntity.value = false
										}, {
											default: withCtx(() => [createTextVNode("Cancel")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											color: "primary",
											onClick: storeEntity,
											loading: unref(formEntity).processing
										}, {
											default: withCtx(() => [createTextVNode(" Save ")]),
											_: 1
										}, 8, ["loading"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"])
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$8 = _sfc_main$8.setup;
_sfc_main$8.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/UnitEntitiesCard.vue");
	return _sfc_setup$8 ? _sfc_setup$8(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Unit/UnitQuotationsCard.vue
var _sfc_main$7 = {
	__name: "UnitQuotationsCard",
	__ssrInlineRender: true,
	props: {
		unit: Object,
		dict: Object,
		goodsLoading: Boolean,
		searchGoods: {
			type: Function,
			required: true
		}
	},
	emits: ["refresh"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const dialogAddQuotation = ref(false);
		const goodsSearch = ref("");
		const headers = [
			{
				key: "good.name",
				title: "Good",
				sortable: true
			},
			{
				key: "price",
				title: "Price",
				sortable: true
			},
			{
				key: "measure.name",
				title: "Measure",
				sortable: true
			}
		];
		const formQuotation = useForm({
			unit_id: props.unit.id,
			good_id: null,
			price: null,
			measure_id: null
		});
		const debouncedSearchGoods = useDebounceFn(async (value) => {
			await props.searchGoods(value);
		}, 350);
		watch(goodsSearch, (value) => {
			debouncedSearchGoods(value);
		});
		async function openDialog() {
			dialogAddQuotation.value = true;
			if (!props.dict.goods?.length) await props.searchGoods("");
		}
		function storeQuotation() {
			formQuotation.unit_id = props.unit.id;
			formQuotation.post(route("web.quotation.store"), {
				preserveState: true,
				preserveScroll: true,
				onSuccess: async () => {
					formQuotation.reset();
					goodsSearch.value = "";
					dialogAddQuotation.value = false;
					emit("refresh");
				}
			});
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(BaseSectionCard_default, mergeProps({
				title: "Quotations",
				icon: "mdi-cash-multiple"
			}, _attrs), {
				actions: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VBtn, {
						size: "small",
						color: "deep-purple-darken-1",
						variant: "tonal",
						onClick: openDialog
					}, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) _push(` + Quotation `);
							else return [createTextVNode(" + Quotation ")];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VBtn, {
						size: "small",
						color: "deep-purple-darken-1",
						variant: "tonal",
						onClick: openDialog
					}, {
						default: withCtx(() => [createTextVNode(" + Quotation ")]),
						_: 1
					})];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VDataTable, {
							items: __props.unit.quotations || [],
							headers,
							"items-per-page": "10",
							"fixed-header": "",
							height: "340",
							density: "compact",
							class: "border rounded-lg"
						}, {
							"item.good.name": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(unref(Link), {
									href: unref(route)("Ameise.good.show", item.good.id),
									class: "text-primary font-weight-medium text-decoration-none"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(`${ssrInterpolate(item.good.name)}`);
										else return [createTextVNode(toDisplayString(item.good.name), 1)];
									}),
									_: 2
								}, _parent, _scopeId));
								else return [createVNode(unref(Link), {
									href: unref(route)("Ameise.good.show", item.good.id),
									class: "text-primary font-weight-medium text-decoration-none"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(item.good.name), 1)]),
									_: 2
								}, 1032, ["href"])];
							}),
							"item.price": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(`${ssrInterpolate(Number(item.price).toFixed(2))}`);
								else return [createTextVNode(toDisplayString(Number(item.price).toFixed(2)), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialogAddQuotation.value,
							"onUpdate:modelValue": ($event) => dialogAddQuotation.value = $event,
							"max-width": "860"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, { rounded: "xl" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`Новая цена (Quotation)`);
													else return [createTextVNode("Новая цена (Quotation)")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VRow, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "6"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(ssrRenderComponent(VAutocomplete, {
																				modelValue: unref(formQuotation).good_id,
																				"onUpdate:modelValue": ($event) => unref(formQuotation).good_id = $event,
																				search: goodsSearch.value,
																				"onUpdate:search": ($event) => goodsSearch.value = $event,
																				items: __props.dict.goods,
																				"item-title": "name",
																				"item-value": "id",
																				label: "Good",
																				variant: "outlined",
																				density: "comfortable",
																				clearable: "",
																				loading: __props.goodsLoading,
																				"no-filter": ""
																			}, null, _parent, _scopeId));
																			if (unref(formQuotation).errors.good_id) _push(`<div class="text-red text-caption"${_scopeId}>${ssrInterpolate(unref(formQuotation).errors.good_id)}</div>`);
																			else _push(`<!---->`);
																		} else return [createVNode(VAutocomplete, {
																			modelValue: unref(formQuotation).good_id,
																			"onUpdate:modelValue": ($event) => unref(formQuotation).good_id = $event,
																			search: goodsSearch.value,
																			"onUpdate:search": ($event) => goodsSearch.value = $event,
																			items: __props.dict.goods,
																			"item-title": "name",
																			"item-value": "id",
																			label: "Good",
																			variant: "outlined",
																			density: "comfortable",
																			clearable: "",
																			loading: __props.goodsLoading,
																			"no-filter": ""
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"search",
																			"onUpdate:search",
																			"items",
																			"loading"
																		]), unref(formQuotation).errors.good_id ? (openBlock(), createBlock("div", {
																			key: 0,
																			class: "text-red text-caption"
																		}, toDisplayString(unref(formQuotation).errors.good_id), 1)) : createCommentVNode("", true)];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "3"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(ssrRenderComponent(VTextField, {
																				modelValue: unref(formQuotation).price,
																				"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																				label: "Price",
																				type: "number",
																				step: "0.01",
																				variant: "outlined",
																				density: "comfortable",
																				clearable: ""
																			}, null, _parent, _scopeId));
																			if (unref(formQuotation).errors.price) _push(`<div class="text-red text-caption"${_scopeId}>${ssrInterpolate(unref(formQuotation).errors.price)}</div>`);
																			else _push(`<!---->`);
																		} else return [createVNode(VTextField, {
																			modelValue: unref(formQuotation).price,
																			"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																			label: "Price",
																			type: "number",
																			step: "0.01",
																			variant: "outlined",
																			density: "comfortable",
																			clearable: ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"]), unref(formQuotation).errors.price ? (openBlock(), createBlock("div", {
																			key: 0,
																			class: "text-red text-caption"
																		}, toDisplayString(unref(formQuotation).errors.price), 1)) : createCommentVNode("", true)];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, {
																	cols: "12",
																	md: "3"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(ssrRenderComponent(VSelect, {
																				modelValue: unref(formQuotation).measure_id,
																				"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																				items: __props.dict.measures,
																				"item-title": "name",
																				"item-value": "id",
																				label: "Measure",
																				variant: "outlined",
																				density: "comfortable",
																				clearable: ""
																			}, null, _parent, _scopeId));
																			if (unref(formQuotation).errors.measure_id) _push(`<div class="text-red text-caption"${_scopeId}>${ssrInterpolate(unref(formQuotation).errors.measure_id)}</div>`);
																			else _push(`<!---->`);
																		} else return [createVNode(VSelect, {
																			modelValue: unref(formQuotation).measure_id,
																			"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																			items: __props.dict.measures,
																			"item-title": "name",
																			"item-value": "id",
																			label: "Measure",
																			variant: "outlined",
																			density: "comfortable",
																			clearable: ""
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"items"
																		]), unref(formQuotation).errors.measure_id ? (openBlock(), createBlock("div", {
																			key: 0,
																			class: "text-red text-caption"
																		}, toDisplayString(unref(formQuotation).errors.measure_id), 1)) : createCommentVNode("", true)];
																	}),
																	_: 1
																}, _parent, _scopeId));
															} else return [
																createVNode(VCol, {
																	cols: "12",
																	md: "6"
																}, {
																	default: withCtx(() => [createVNode(VAutocomplete, {
																		modelValue: unref(formQuotation).good_id,
																		"onUpdate:modelValue": ($event) => unref(formQuotation).good_id = $event,
																		search: goodsSearch.value,
																		"onUpdate:search": ($event) => goodsSearch.value = $event,
																		items: __props.dict.goods,
																		"item-title": "name",
																		"item-value": "id",
																		label: "Good",
																		variant: "outlined",
																		density: "comfortable",
																		clearable: "",
																		loading: __props.goodsLoading,
																		"no-filter": ""
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"search",
																		"onUpdate:search",
																		"items",
																		"loading"
																	]), unref(formQuotation).errors.good_id ? (openBlock(), createBlock("div", {
																		key: 0,
																		class: "text-red text-caption"
																	}, toDisplayString(unref(formQuotation).errors.good_id), 1)) : createCommentVNode("", true)]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "3"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formQuotation).price,
																		"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																		label: "Price",
																		type: "number",
																		step: "0.01",
																		variant: "outlined",
																		density: "comfortable",
																		clearable: ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"]), unref(formQuotation).errors.price ? (openBlock(), createBlock("div", {
																		key: 0,
																		class: "text-red text-caption"
																	}, toDisplayString(unref(formQuotation).errors.price), 1)) : createCommentVNode("", true)]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "3"
																}, {
																	default: withCtx(() => [createVNode(VSelect, {
																		modelValue: unref(formQuotation).measure_id,
																		"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																		items: __props.dict.measures,
																		"item-title": "name",
																		"item-value": "id",
																		label: "Measure",
																		variant: "outlined",
																		density: "comfortable",
																		clearable: ""
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items"
																	]), unref(formQuotation).errors.measure_id ? (openBlock(), createBlock("div", {
																		key: 0,
																		class: "text-red text-caption"
																	}, toDisplayString(unref(formQuotation).errors.measure_id), 1)) : createCommentVNode("", true)]),
																	_: 1
																})
															];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(VRow, null, {
														default: withCtx(() => [
															createVNode(VCol, {
																cols: "12",
																md: "6"
															}, {
																default: withCtx(() => [createVNode(VAutocomplete, {
																	modelValue: unref(formQuotation).good_id,
																	"onUpdate:modelValue": ($event) => unref(formQuotation).good_id = $event,
																	search: goodsSearch.value,
																	"onUpdate:search": ($event) => goodsSearch.value = $event,
																	items: __props.dict.goods,
																	"item-title": "name",
																	"item-value": "id",
																	label: "Good",
																	variant: "outlined",
																	density: "comfortable",
																	clearable: "",
																	loading: __props.goodsLoading,
																	"no-filter": ""
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"search",
																	"onUpdate:search",
																	"items",
																	"loading"
																]), unref(formQuotation).errors.good_id ? (openBlock(), createBlock("div", {
																	key: 0,
																	class: "text-red text-caption"
																}, toDisplayString(unref(formQuotation).errors.good_id), 1)) : createCommentVNode("", true)]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "3"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(formQuotation).price,
																	"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																	label: "Price",
																	type: "number",
																	step: "0.01",
																	variant: "outlined",
																	density: "comfortable",
																	clearable: ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"]), unref(formQuotation).errors.price ? (openBlock(), createBlock("div", {
																	key: 0,
																	class: "text-red text-caption"
																}, toDisplayString(unref(formQuotation).errors.price), 1)) : createCommentVNode("", true)]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "3"
															}, {
																default: withCtx(() => [createVNode(VSelect, {
																	modelValue: unref(formQuotation).measure_id,
																	"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																	items: __props.dict.measures,
																	"item-title": "name",
																	"item-value": "id",
																	label: "Measure",
																	variant: "outlined",
																	density: "comfortable",
																	clearable: ""
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items"
																]), unref(formQuotation).errors.measure_id ? (openBlock(), createBlock("div", {
																	key: 0,
																	class: "text-red text-caption"
																}, toDisplayString(unref(formQuotation).errors.measure_id), 1)) : createCommentVNode("", true)]),
																_: 1
															})
														]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, { class: "justify-end" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => dialogAddQuotation.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Cancel `);
																else return [createTextVNode(" Cancel ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "deep-purple-darken-1",
															variant: "tonal",
															loading: unref(formQuotation).processing,
															onClick: storeQuotation
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Save `);
																else return [createTextVNode(" Save ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => dialogAddQuotation.value = false
													}, {
														default: withCtx(() => [createTextVNode(" Cancel ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														color: "deep-purple-darken-1",
														variant: "tonal",
														loading: unref(formQuotation).processing,
														onClick: storeQuotation
													}, {
														default: withCtx(() => [createTextVNode(" Save ")]),
														_: 1
													}, 8, ["loading"])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Новая цена (Quotation)")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [
														createVNode(VCol, {
															cols: "12",
															md: "6"
														}, {
															default: withCtx(() => [createVNode(VAutocomplete, {
																modelValue: unref(formQuotation).good_id,
																"onUpdate:modelValue": ($event) => unref(formQuotation).good_id = $event,
																search: goodsSearch.value,
																"onUpdate:search": ($event) => goodsSearch.value = $event,
																items: __props.dict.goods,
																"item-title": "name",
																"item-value": "id",
																label: "Good",
																variant: "outlined",
																density: "comfortable",
																clearable: "",
																loading: __props.goodsLoading,
																"no-filter": ""
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"search",
																"onUpdate:search",
																"items",
																"loading"
															]), unref(formQuotation).errors.good_id ? (openBlock(), createBlock("div", {
																key: 0,
																class: "text-red text-caption"
															}, toDisplayString(unref(formQuotation).errors.good_id), 1)) : createCommentVNode("", true)]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "3"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: unref(formQuotation).price,
																"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
																label: "Price",
																type: "number",
																step: "0.01",
																variant: "outlined",
																density: "comfortable",
																clearable: ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"]), unref(formQuotation).errors.price ? (openBlock(), createBlock("div", {
																key: 0,
																class: "text-red text-caption"
															}, toDisplayString(unref(formQuotation).errors.price), 1)) : createCommentVNode("", true)]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "3"
														}, {
															default: withCtx(() => [createVNode(VSelect, {
																modelValue: unref(formQuotation).measure_id,
																"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
																items: __props.dict.measures,
																"item-title": "name",
																"item-value": "id",
																label: "Measure",
																variant: "outlined",
																density: "comfortable",
																clearable: ""
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items"
															]), unref(formQuotation).errors.measure_id ? (openBlock(), createBlock("div", {
																key: 0,
																class: "text-red text-caption"
															}, toDisplayString(unref(formQuotation).errors.measure_id), 1)) : createCommentVNode("", true)]),
															_: 1
														})
													]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCardActions, { class: "justify-end" }, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => dialogAddQuotation.value = false
												}, {
													default: withCtx(() => [createTextVNode(" Cancel ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													color: "deep-purple-darken-1",
													variant: "tonal",
													loading: unref(formQuotation).processing,
													onClick: storeQuotation
												}, {
													default: withCtx(() => [createTextVNode(" Save ")]),
													_: 1
												}, 8, ["loading"])]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, { rounded: "xl" }, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Новая цена (Quotation)")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [
													createVNode(VCol, {
														cols: "12",
														md: "6"
													}, {
														default: withCtx(() => [createVNode(VAutocomplete, {
															modelValue: unref(formQuotation).good_id,
															"onUpdate:modelValue": ($event) => unref(formQuotation).good_id = $event,
															search: goodsSearch.value,
															"onUpdate:search": ($event) => goodsSearch.value = $event,
															items: __props.dict.goods,
															"item-title": "name",
															"item-value": "id",
															label: "Good",
															variant: "outlined",
															density: "comfortable",
															clearable: "",
															loading: __props.goodsLoading,
															"no-filter": ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"search",
															"onUpdate:search",
															"items",
															"loading"
														]), unref(formQuotation).errors.good_id ? (openBlock(), createBlock("div", {
															key: 0,
															class: "text-red text-caption"
														}, toDisplayString(unref(formQuotation).errors.good_id), 1)) : createCommentVNode("", true)]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "3"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: unref(formQuotation).price,
															"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
															label: "Price",
															type: "number",
															step: "0.01",
															variant: "outlined",
															density: "comfortable",
															clearable: ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"]), unref(formQuotation).errors.price ? (openBlock(), createBlock("div", {
															key: 0,
															class: "text-red text-caption"
														}, toDisplayString(unref(formQuotation).errors.price), 1)) : createCommentVNode("", true)]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "3"
													}, {
														default: withCtx(() => [createVNode(VSelect, {
															modelValue: unref(formQuotation).measure_id,
															"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
															items: __props.dict.measures,
															"item-title": "name",
															"item-value": "id",
															label: "Measure",
															variant: "outlined",
															density: "comfortable",
															clearable: ""
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														]), unref(formQuotation).errors.measure_id ? (openBlock(), createBlock("div", {
															key: 0,
															class: "text-red text-caption"
														}, toDisplayString(unref(formQuotation).errors.measure_id), 1)) : createCommentVNode("", true)]),
														_: 1
													})
												]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VCardActions, { class: "justify-end" }, {
											default: withCtx(() => [createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => dialogAddQuotation.value = false
											}, {
												default: withCtx(() => [createTextVNode(" Cancel ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												color: "deep-purple-darken-1",
												variant: "tonal",
												loading: unref(formQuotation).processing,
												onClick: storeQuotation
											}, {
												default: withCtx(() => [createTextVNode(" Save ")]),
												_: 1
											}, 8, ["loading"])]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VDataTable, {
						items: __props.unit.quotations || [],
						headers,
						"items-per-page": "10",
						"fixed-header": "",
						height: "340",
						density: "compact",
						class: "border rounded-lg"
					}, {
						"item.good.name": withCtx(({ item }) => [createVNode(unref(Link), {
							href: unref(route)("Ameise.good.show", item.good.id),
							class: "text-primary font-weight-medium text-decoration-none"
						}, {
							default: withCtx(() => [createTextVNode(toDisplayString(item.good.name), 1)]),
							_: 2
						}, 1032, ["href"])]),
						"item.price": withCtx(({ item }) => [createTextVNode(toDisplayString(Number(item.price).toFixed(2)), 1)]),
						_: 1
					}, 8, ["items"]), createVNode(VDialog, {
						modelValue: dialogAddQuotation.value,
						"onUpdate:modelValue": ($event) => dialogAddQuotation.value = $event,
						"max-width": "860"
					}, {
						default: withCtx(() => [createVNode(VCard, { rounded: "xl" }, {
							default: withCtx(() => [
								createVNode(VCardTitle, null, {
									default: withCtx(() => [createTextVNode("Новая цена (Quotation)")]),
									_: 1
								}),
								createVNode(VCardText, null, {
									default: withCtx(() => [createVNode(VRow, null, {
										default: withCtx(() => [
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VAutocomplete, {
													modelValue: unref(formQuotation).good_id,
													"onUpdate:modelValue": ($event) => unref(formQuotation).good_id = $event,
													search: goodsSearch.value,
													"onUpdate:search": ($event) => goodsSearch.value = $event,
													items: __props.dict.goods,
													"item-title": "name",
													"item-value": "id",
													label: "Good",
													variant: "outlined",
													density: "comfortable",
													clearable: "",
													loading: __props.goodsLoading,
													"no-filter": ""
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"search",
													"onUpdate:search",
													"items",
													"loading"
												]), unref(formQuotation).errors.good_id ? (openBlock(), createBlock("div", {
													key: 0,
													class: "text-red text-caption"
												}, toDisplayString(unref(formQuotation).errors.good_id), 1)) : createCommentVNode("", true)]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: unref(formQuotation).price,
													"onUpdate:modelValue": ($event) => unref(formQuotation).price = $event,
													label: "Price",
													type: "number",
													step: "0.01",
													variant: "outlined",
													density: "comfortable",
													clearable: ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"]), unref(formQuotation).errors.price ? (openBlock(), createBlock("div", {
													key: 0,
													class: "text-red text-caption"
												}, toDisplayString(unref(formQuotation).errors.price), 1)) : createCommentVNode("", true)]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: unref(formQuotation).measure_id,
													"onUpdate:modelValue": ($event) => unref(formQuotation).measure_id = $event,
													items: __props.dict.measures,
													"item-title": "name",
													"item-value": "id",
													label: "Measure",
													variant: "outlined",
													density: "comfortable",
													clearable: ""
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												]), unref(formQuotation).errors.measure_id ? (openBlock(), createBlock("div", {
													key: 0,
													class: "text-red text-caption"
												}, toDisplayString(unref(formQuotation).errors.measure_id), 1)) : createCommentVNode("", true)]),
												_: 1
											})
										]),
										_: 1
									})]),
									_: 1
								}),
								createVNode(VCardActions, { class: "justify-end" }, {
									default: withCtx(() => [createVNode(VBtn, {
										variant: "text",
										onClick: ($event) => dialogAddQuotation.value = false
									}, {
										default: withCtx(() => [createTextVNode(" Cancel ")]),
										_: 1
									}, 8, ["onClick"]), createVNode(VBtn, {
										color: "deep-purple-darken-1",
										variant: "tonal",
										loading: unref(formQuotation).processing,
										onClick: storeQuotation
									}, {
										default: withCtx(() => [createTextVNode(" Save ")]),
										_: 1
									}, 8, ["loading"])]),
									_: 1
								})
							]),
							_: 1
						})]),
						_: 1
					}, 8, ["modelValue", "onUpdate:modelValue"])];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$7 = _sfc_main$7.setup;
_sfc_main$7.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/UnitQuotationsCard.vue");
	return _sfc_setup$7 ? _sfc_setup$7(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Unit/UnitManufacturesCard.vue
var _sfc_main$6 = {
	__name: "UnitManufacturesCard",
	__ssrInlineRender: true,
	props: {
		unit: Object,
		dict: Object
	},
	emits: ["refresh"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const showAttachForm = ref(false);
		const formAttachManufacturer = useForm({
			product_id: null,
			unit_id: props.unit.id
		});
		const headers = [{
			key: "rus",
			title: "Title",
			align: "start",
			sortable: true
		}];
		function attachManufacturer() {
			formAttachManufacturer.post(route("web.manufacturer.store"), {
				preserveState: true,
				onSuccess: async () => {
					formAttachManufacturer.reset();
					showAttachForm.value = false;
					emit("refresh");
				}
			});
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(BaseSectionCard_default, mergeProps({
				title: "Manufactures",
				icon: "mdi-package-variant-closed"
			}, _attrs), {
				actions: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VBtn, {
						icon: "mdi-plus",
						variant: "text",
						size: "small",
						onClick: ($event) => showAttachForm.value = !showAttachForm.value
					}, null, _parent, _scopeId));
					else return [createVNode(VBtn, {
						icon: "mdi-plus",
						variant: "text",
						size: "small",
						onClick: ($event) => showAttachForm.value = !showAttachForm.value
					}, null, 8, ["onClick"])];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VDataTable, {
							items: __props.unit.manufactures || [],
							headers,
							"items-per-page": "8",
							"fixed-header": "",
							height: "280",
							density: "compact",
							class: "border rounded-lg"
						}, {
							"item.rus": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(unref(Link), {
									href: unref(route)("product.show", item.id),
									class: "text-decoration-none font-weight-medium"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(`${ssrInterpolate(item.rus)}`);
										else return [createTextVNode(toDisplayString(item.rus), 1)];
									}),
									_: 2
								}, _parent, _scopeId));
								else return [createVNode(unref(Link), {
									href: unref(route)("product.show", item.id),
									class: "text-decoration-none font-weight-medium"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(item.rus), 1)]),
									_: 2
								}, 1032, ["href"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VExpandTransition, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) if (showAttachForm.value) {
									_push(`<div class="mt-4"${_scopeId}>`);
									_push(ssrRenderComponent(VSheet, {
										rounded: "lg",
										border: "",
										class: "pa-3 bg-grey-lighten-5"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VRow, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VAutocomplete, {
																	modelValue: unref(formAttachManufacturer).product_id,
																	"onUpdate:modelValue": ($event) => unref(formAttachManufacturer).product_id = $event,
																	items: __props.dict.products,
																	"item-title": "rus",
																	"item-value": "id",
																	label: "Product",
																	variant: "outlined",
																	density: "comfortable",
																	"hide-details": ""
																}, null, _parent, _scopeId));
																else return [createVNode(VAutocomplete, {
																	modelValue: unref(formAttachManufacturer).product_id,
																	"onUpdate:modelValue": ($event) => unref(formAttachManufacturer).product_id = $event,
																	items: __props.dict.products,
																	"item-title": "rus",
																	"item-value": "id",
																	label: "Product",
																	variant: "outlined",
																	density: "comfortable",
																	"hide-details": ""
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items"
																])];
															}),
															_: 1
														}, _parent, _scopeId));
														else return [createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(VAutocomplete, {
																modelValue: unref(formAttachManufacturer).product_id,
																"onUpdate:modelValue": ($event) => unref(formAttachManufacturer).product_id = $event,
																items: __props.dict.products,
																"item-title": "rus",
																"item-value": "id",
																label: "Product",
																variant: "outlined",
																density: "comfortable",
																"hide-details": ""
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items"
															])]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`<div class="d-flex justify-end mt-3"${_scopeId}>`);
												_push(ssrRenderComponent(VBtn, {
													color: "primary",
													onClick: attachManufacturer,
													loading: unref(formAttachManufacturer).processing
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` Attach `);
														else return [createTextVNode(" Attach ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`</div>`);
											} else return [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VAutocomplete, {
														modelValue: unref(formAttachManufacturer).product_id,
														"onUpdate:modelValue": ($event) => unref(formAttachManufacturer).product_id = $event,
														items: __props.dict.products,
														"item-title": "rus",
														"item-value": "id",
														label: "Product",
														variant: "outlined",
														density: "comfortable",
														"hide-details": ""
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													])]),
													_: 1
												})]),
												_: 1
											}), createVNode("div", { class: "d-flex justify-end mt-3" }, [createVNode(VBtn, {
												color: "primary",
												onClick: attachManufacturer,
												loading: unref(formAttachManufacturer).processing
											}, {
												default: withCtx(() => [createTextVNode(" Attach ")]),
												_: 1
											}, 8, ["loading"])])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(`</div>`);
								} else _push(`<!---->`);
								else return [showAttachForm.value ? (openBlock(), createBlock("div", {
									key: 0,
									class: "mt-4"
								}, [createVNode(VSheet, {
									rounded: "lg",
									border: "",
									class: "pa-3 bg-grey-lighten-5"
								}, {
									default: withCtx(() => [createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VAutocomplete, {
												modelValue: unref(formAttachManufacturer).product_id,
												"onUpdate:modelValue": ($event) => unref(formAttachManufacturer).product_id = $event,
												items: __props.dict.products,
												"item-title": "rus",
												"item-value": "id",
												label: "Product",
												variant: "outlined",
												density: "comfortable",
												"hide-details": ""
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items"
											])]),
											_: 1
										})]),
										_: 1
									}), createVNode("div", { class: "d-flex justify-end mt-3" }, [createVNode(VBtn, {
										color: "primary",
										onClick: attachManufacturer,
										loading: unref(formAttachManufacturer).processing
									}, {
										default: withCtx(() => [createTextVNode(" Attach ")]),
										_: 1
									}, 8, ["loading"])])]),
									_: 1
								})])) : createCommentVNode("", true)];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VDataTable, {
						items: __props.unit.manufactures || [],
						headers,
						"items-per-page": "8",
						"fixed-header": "",
						height: "280",
						density: "compact",
						class: "border rounded-lg"
					}, {
						"item.rus": withCtx(({ item }) => [createVNode(unref(Link), {
							href: unref(route)("product.show", item.id),
							class: "text-decoration-none font-weight-medium"
						}, {
							default: withCtx(() => [createTextVNode(toDisplayString(item.rus), 1)]),
							_: 2
						}, 1032, ["href"])]),
						_: 1
					}, 8, ["items"]), createVNode(VExpandTransition, null, {
						default: withCtx(() => [showAttachForm.value ? (openBlock(), createBlock("div", {
							key: 0,
							class: "mt-4"
						}, [createVNode(VSheet, {
							rounded: "lg",
							border: "",
							class: "pa-3 bg-grey-lighten-5"
						}, {
							default: withCtx(() => [createVNode(VRow, null, {
								default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
									default: withCtx(() => [createVNode(VAutocomplete, {
										modelValue: unref(formAttachManufacturer).product_id,
										"onUpdate:modelValue": ($event) => unref(formAttachManufacturer).product_id = $event,
										items: __props.dict.products,
										"item-title": "rus",
										"item-value": "id",
										label: "Product",
										variant: "outlined",
										density: "comfortable",
										"hide-details": ""
									}, null, 8, [
										"modelValue",
										"onUpdate:modelValue",
										"items"
									])]),
									_: 1
								})]),
								_: 1
							}), createVNode("div", { class: "d-flex justify-end mt-3" }, [createVNode(VBtn, {
								color: "primary",
								onClick: attachManufacturer,
								loading: unref(formAttachManufacturer).processing
							}, {
								default: withCtx(() => [createTextVNode(" Attach ")]),
								_: 1
							}, 8, ["loading"])])]),
							_: 1
						})])) : createCommentVNode("", true)]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$6 = _sfc_main$6.setup;
_sfc_main$6.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/UnitManufacturesCard.vue");
	return _sfc_setup$6 ? _sfc_setup$6(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Unit/UnitConsumptionsCard.vue
var _sfc_main$5 = {
	__name: "UnitConsumptionsCard",
	__ssrInlineRender: true,
	props: {
		unit: Object,
		dict: Object
	},
	emits: ["refresh"],
	setup(__props, { emit: __emit }) {
		const props = __props;
		const emit = __emit;
		const date = useDate();
		const showForm = ref(false);
		const formConsumption = useForm({
			unit_id: props.unit.id,
			product_id: null,
			quantity: null,
			measure_id: null
		});
		const headers = [
			{
				title: "Created",
				key: "created_at"
			},
			{
				title: "Product",
				key: "product_id"
			},
			{
				title: "Quantity",
				key: "quantity"
			},
			{
				title: "Measure",
				key: "measure_id"
			}
		];
		function storeConsumption() {
			formConsumption.post(route("api.consumption.store"), {
				preserveState: true,
				onSuccess: async () => {
					formConsumption.reset();
					showForm.value = false;
					emit("refresh");
				}
			});
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(BaseSectionCard_default, mergeProps({
				title: "Consumptions",
				icon: "mdi-chart-timeline-variant"
			}, _attrs), {
				actions: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VBtn, {
						size: "small",
						variant: "text",
						onClick: ($event) => showForm.value = !showForm.value
					}, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) _push(` Add `);
							else return [createTextVNode(" Add ")];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VBtn, {
						size: "small",
						variant: "text",
						onClick: ($event) => showForm.value = !showForm.value
					}, {
						default: withCtx(() => [createTextVNode(" Add ")]),
						_: 1
					}, 8, ["onClick"])];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VExpandTransition, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) if (showForm.value) {
									_push(`<div class="mb-4"${_scopeId}>`);
									_push(ssrRenderComponent(VSheet, {
										rounded: "lg",
										border: "",
										class: "pa-4 bg-grey-lighten-5"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VRow, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																md: "6"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VAutocomplete, {
																		modelValue: unref(formConsumption).product_id,
																		"onUpdate:modelValue": ($event) => unref(formConsumption).product_id = $event,
																		items: __props.dict.products,
																		"item-title": "rus",
																		"item-value": "id",
																		label: "Product",
																		variant: "outlined",
																		density: "comfortable"
																	}, null, _parent, _scopeId));
																	else return [createVNode(VAutocomplete, {
																		modelValue: unref(formConsumption).product_id,
																		"onUpdate:modelValue": ($event) => unref(formConsumption).product_id = $event,
																		items: __props.dict.products,
																		"item-title": "rus",
																		"item-value": "id",
																		label: "Product",
																		variant: "outlined",
																		density: "comfortable"
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items"
																	])];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																md: "3"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VTextField, {
																		modelValue: unref(formConsumption).quantity,
																		"onUpdate:modelValue": ($event) => unref(formConsumption).quantity = $event,
																		label: "Quantity",
																		variant: "outlined",
																		density: "comfortable"
																	}, null, _parent, _scopeId));
																	else return [createVNode(VTextField, {
																		modelValue: unref(formConsumption).quantity,
																		"onUpdate:modelValue": ($event) => unref(formConsumption).quantity = $event,
																		label: "Quantity",
																		variant: "outlined",
																		density: "comfortable"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																md: "3"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VSelect, {
																		modelValue: unref(formConsumption).measure_id,
																		"onUpdate:modelValue": ($event) => unref(formConsumption).measure_id = $event,
																		items: __props.dict.measures,
																		"item-title": "name",
																		"item-value": "id",
																		label: "Measure",
																		variant: "outlined",
																		density: "comfortable"
																	}, null, _parent, _scopeId));
																	else return [createVNode(VSelect, {
																		modelValue: unref(formConsumption).measure_id,
																		"onUpdate:modelValue": ($event) => unref(formConsumption).measure_id = $event,
																		items: __props.dict.measures,
																		"item-title": "name",
																		"item-value": "id",
																		label: "Measure",
																		variant: "outlined",
																		density: "comfortable"
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items"
																	])];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [
															createVNode(VCol, {
																cols: "12",
																md: "6"
															}, {
																default: withCtx(() => [createVNode(VAutocomplete, {
																	modelValue: unref(formConsumption).product_id,
																	"onUpdate:modelValue": ($event) => unref(formConsumption).product_id = $event,
																	items: __props.dict.products,
																	"item-title": "rus",
																	"item-value": "id",
																	label: "Product",
																	variant: "outlined",
																	density: "comfortable"
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items"
																])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "3"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(formConsumption).quantity,
																	"onUpdate:modelValue": ($event) => unref(formConsumption).quantity = $event,
																	label: "Quantity",
																	variant: "outlined",
																	density: "comfortable"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "3"
															}, {
																default: withCtx(() => [createVNode(VSelect, {
																	modelValue: unref(formConsumption).measure_id,
																	"onUpdate:modelValue": ($event) => unref(formConsumption).measure_id = $event,
																	items: __props.dict.measures,
																	"item-title": "name",
																	"item-value": "id",
																	label: "Measure",
																	variant: "outlined",
																	density: "comfortable"
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items"
																])]),
																_: 1
															})
														];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`<div class="d-flex justify-end"${_scopeId}>`);
												_push(ssrRenderComponent(VBtn, {
													color: "primary",
													onClick: storeConsumption,
													loading: unref(formConsumption).processing
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` Save `);
														else return [createTextVNode(" Save ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`</div>`);
											} else return [createVNode(VRow, null, {
												default: withCtx(() => [
													createVNode(VCol, {
														cols: "12",
														md: "6"
													}, {
														default: withCtx(() => [createVNode(VAutocomplete, {
															modelValue: unref(formConsumption).product_id,
															"onUpdate:modelValue": ($event) => unref(formConsumption).product_id = $event,
															items: __props.dict.products,
															"item-title": "rus",
															"item-value": "id",
															label: "Product",
															variant: "outlined",
															density: "comfortable"
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "3"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: unref(formConsumption).quantity,
															"onUpdate:modelValue": ($event) => unref(formConsumption).quantity = $event,
															label: "Quantity",
															variant: "outlined",
															density: "comfortable"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "3"
													}, {
														default: withCtx(() => [createVNode(VSelect, {
															modelValue: unref(formConsumption).measure_id,
															"onUpdate:modelValue": ($event) => unref(formConsumption).measure_id = $event,
															items: __props.dict.measures,
															"item-title": "name",
															"item-value": "id",
															label: "Measure",
															variant: "outlined",
															density: "comfortable"
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														])]),
														_: 1
													})
												]),
												_: 1
											}), createVNode("div", { class: "d-flex justify-end" }, [createVNode(VBtn, {
												color: "primary",
												onClick: storeConsumption,
												loading: unref(formConsumption).processing
											}, {
												default: withCtx(() => [createTextVNode(" Save ")]),
												_: 1
											}, 8, ["loading"])])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(`</div>`);
								} else _push(`<!---->`);
								else return [showForm.value ? (openBlock(), createBlock("div", {
									key: 0,
									class: "mb-4"
								}, [createVNode(VSheet, {
									rounded: "lg",
									border: "",
									class: "pa-4 bg-grey-lighten-5"
								}, {
									default: withCtx(() => [createVNode(VRow, null, {
										default: withCtx(() => [
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VAutocomplete, {
													modelValue: unref(formConsumption).product_id,
													"onUpdate:modelValue": ($event) => unref(formConsumption).product_id = $event,
													items: __props.dict.products,
													"item-title": "rus",
													"item-value": "id",
													label: "Product",
													variant: "outlined",
													density: "comfortable"
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: unref(formConsumption).quantity,
													"onUpdate:modelValue": ($event) => unref(formConsumption).quantity = $event,
													label: "Quantity",
													variant: "outlined",
													density: "comfortable"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "3"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: unref(formConsumption).measure_id,
													"onUpdate:modelValue": ($event) => unref(formConsumption).measure_id = $event,
													items: __props.dict.measures,
													"item-title": "name",
													"item-value": "id",
													label: "Measure",
													variant: "outlined",
													density: "comfortable"
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												])]),
												_: 1
											})
										]),
										_: 1
									}), createVNode("div", { class: "d-flex justify-end" }, [createVNode(VBtn, {
										color: "primary",
										onClick: storeConsumption,
										loading: unref(formConsumption).processing
									}, {
										default: withCtx(() => [createTextVNode(" Save ")]),
										_: 1
									}, 8, ["loading"])])]),
									_: 1
								})])) : createCommentVNode("", true)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDataTable, {
							items: __props.unit.consumptions || [],
							headers,
							density: "compact",
							"items-per-page": "8",
							"fixed-header": "",
							height: "320",
							class: "border rounded-lg"
						}, {
							"item.product_id": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(unref(Link), {
									href: unref(route)("product.show", item.product_id),
									class: "text-decoration-none"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(`${ssrInterpolate(item.product?.rus)}`);
										else return [createTextVNode(toDisplayString(item.product?.rus), 1)];
									}),
									_: 2
								}, _parent, _scopeId));
								else return [createVNode(unref(Link), {
									href: unref(route)("product.show", item.product_id),
									class: "text-decoration-none"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(item.product?.rus), 1)]),
									_: 2
								}, 1032, ["href"])];
							}),
							"item.created_at": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(`${ssrInterpolate(unref(date).format(item.created_at, "fullDate"))}`);
								else return [createTextVNode(toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)];
							}),
							"item.measure_id": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(`${ssrInterpolate(item.measure?.name)}`);
								else return [createTextVNode(toDisplayString(item.measure?.name), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VExpandTransition, null, {
						default: withCtx(() => [showForm.value ? (openBlock(), createBlock("div", {
							key: 0,
							class: "mb-4"
						}, [createVNode(VSheet, {
							rounded: "lg",
							border: "",
							class: "pa-4 bg-grey-lighten-5"
						}, {
							default: withCtx(() => [createVNode(VRow, null, {
								default: withCtx(() => [
									createVNode(VCol, {
										cols: "12",
										md: "6"
									}, {
										default: withCtx(() => [createVNode(VAutocomplete, {
											modelValue: unref(formConsumption).product_id,
											"onUpdate:modelValue": ($event) => unref(formConsumption).product_id = $event,
											items: __props.dict.products,
											"item-title": "rus",
											"item-value": "id",
											label: "Product",
											variant: "outlined",
											density: "comfortable"
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"items"
										])]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										md: "3"
									}, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: unref(formConsumption).quantity,
											"onUpdate:modelValue": ($event) => unref(formConsumption).quantity = $event,
											label: "Quantity",
											variant: "outlined",
											density: "comfortable"
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										md: "3"
									}, {
										default: withCtx(() => [createVNode(VSelect, {
											modelValue: unref(formConsumption).measure_id,
											"onUpdate:modelValue": ($event) => unref(formConsumption).measure_id = $event,
											items: __props.dict.measures,
											"item-title": "name",
											"item-value": "id",
											label: "Measure",
											variant: "outlined",
											density: "comfortable"
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"items"
										])]),
										_: 1
									})
								]),
								_: 1
							}), createVNode("div", { class: "d-flex justify-end" }, [createVNode(VBtn, {
								color: "primary",
								onClick: storeConsumption,
								loading: unref(formConsumption).processing
							}, {
								default: withCtx(() => [createTextVNode(" Save ")]),
								_: 1
							}, 8, ["loading"])])]),
							_: 1
						})])) : createCommentVNode("", true)]),
						_: 1
					}), createVNode(VDataTable, {
						items: __props.unit.consumptions || [],
						headers,
						density: "compact",
						"items-per-page": "8",
						"fixed-header": "",
						height: "320",
						class: "border rounded-lg"
					}, {
						"item.product_id": withCtx(({ item }) => [createVNode(unref(Link), {
							href: unref(route)("product.show", item.product_id),
							class: "text-decoration-none"
						}, {
							default: withCtx(() => [createTextVNode(toDisplayString(item.product?.rus), 1)]),
							_: 2
						}, 1032, ["href"])]),
						"item.created_at": withCtx(({ item }) => [createTextVNode(toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
						"item.measure_id": withCtx(({ item }) => [createTextVNode(toDisplayString(item.measure?.name), 1)]),
						_: 1
					}, 8, ["items"])];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$5 = _sfc_main$5.setup;
_sfc_main$5.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/UnitConsumptionsCard.vue");
	return _sfc_setup$5 ? _sfc_setup$5(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Unit/UnitStagesCard.vue
var _sfc_main$4 = {
	__name: "UnitStagesCard",
	__ssrInlineRender: true,
	props: { stages: {
		type: Array,
		default: () => []
	} },
	setup(__props) {
		const date = useDate();
		function timeDiff(time) {
			if (!time) return null;
			const diffMilliseconds = /* @__PURE__ */ new Date() - Date.parse(time);
			return Math.round(diffMilliseconds / (1e3 * 3600 * 24));
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(BaseSectionCard_default, mergeProps({
				title: "Stages",
				icon: "mdi-timeline-clock-outline",
				compact: ""
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="d-flex flex-column ga-3"${_scopeId}><!--[-->`);
						ssrRenderList(__props.stages, (stage) => {
							_push(ssrRenderComponent(VSheet, {
								key: stage.id,
								rounded: "lg",
								border: "",
								class: "pa-3"
							}, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) {
										_push(`<div class="d-flex justify-space-between align-start mb-2"${_scopeId}><div class="font-weight-bold"${_scopeId}>${ssrInterpolate(stage.name)}</div>`);
										_push(ssrRenderComponent(VChip, {
											size: "small",
											color: stage.pivot?.isActive ? "green" : "grey",
											variant: "tonal"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(`${ssrInterpolate(stage.pivot?.isActive ? "Active" : "Inactive")}`);
												else return [createTextVNode(toDisplayString(stage.pivot?.isActive ? "Active" : "Inactive"), 1)];
											}),
											_: 2
										}, _parent, _scopeId));
										_push(`</div><div class="text-body-2 mb-1"${_scopeId}> Start: <span class="font-weight-medium"${_scopeId}>${ssrInterpolate(stage.pivot?.startDate ? unref(date).format(stage.pivot.startDate, "fullDate") : "—")}</span></div><div class="text-body-2 mb-1"${_scopeId}> Year: <span class="font-weight-medium"${_scopeId}>${ssrInterpolate(stage.pivot?.startDate ? unref(date).format(stage.pivot.startDate, "year") : "—")}</span></div><div class="text-body-2"${_scopeId}> Days passed: `);
										_push(ssrRenderComponent(VChip, {
											size: "small",
											variant: "outlined",
											class: "ms-2"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(`${ssrInterpolate(timeDiff(stage.pivot?.startDate) ?? "—")}`);
												else return [createTextVNode(toDisplayString(timeDiff(stage.pivot?.startDate) ?? "—"), 1)];
											}),
											_: 2
										}, _parent, _scopeId));
										_push(`</div>`);
									} else return [
										createVNode("div", { class: "d-flex justify-space-between align-start mb-2" }, [createVNode("div", { class: "font-weight-bold" }, toDisplayString(stage.name), 1), createVNode(VChip, {
											size: "small",
											color: stage.pivot?.isActive ? "green" : "grey",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(stage.pivot?.isActive ? "Active" : "Inactive"), 1)]),
											_: 2
										}, 1032, ["color"])]),
										createVNode("div", { class: "text-body-2 mb-1" }, [createTextVNode(" Start: "), createVNode("span", { class: "font-weight-medium" }, toDisplayString(stage.pivot?.startDate ? unref(date).format(stage.pivot.startDate, "fullDate") : "—"), 1)]),
										createVNode("div", { class: "text-body-2 mb-1" }, [createTextVNode(" Year: "), createVNode("span", { class: "font-weight-medium" }, toDisplayString(stage.pivot?.startDate ? unref(date).format(stage.pivot.startDate, "year") : "—"), 1)]),
										createVNode("div", { class: "text-body-2" }, [createTextVNode(" Days passed: "), createVNode(VChip, {
											size: "small",
											variant: "outlined",
											class: "ms-2"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(timeDiff(stage.pivot?.startDate) ?? "—"), 1)]),
											_: 2
										}, 1024)])
									];
								}),
								_: 2
							}, _parent, _scopeId));
						});
						_push(`<!--]--></div>`);
					} else return [createVNode("div", { class: "d-flex flex-column ga-3" }, [(openBlock(true), createBlock(Fragment, null, renderList(__props.stages, (stage) => {
						return openBlock(), createBlock(VSheet, {
							key: stage.id,
							rounded: "lg",
							border: "",
							class: "pa-3"
						}, {
							default: withCtx(() => [
								createVNode("div", { class: "d-flex justify-space-between align-start mb-2" }, [createVNode("div", { class: "font-weight-bold" }, toDisplayString(stage.name), 1), createVNode(VChip, {
									size: "small",
									color: stage.pivot?.isActive ? "green" : "grey",
									variant: "tonal"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(stage.pivot?.isActive ? "Active" : "Inactive"), 1)]),
									_: 2
								}, 1032, ["color"])]),
								createVNode("div", { class: "text-body-2 mb-1" }, [createTextVNode(" Start: "), createVNode("span", { class: "font-weight-medium" }, toDisplayString(stage.pivot?.startDate ? unref(date).format(stage.pivot.startDate, "fullDate") : "—"), 1)]),
								createVNode("div", { class: "text-body-2 mb-1" }, [createTextVNode(" Year: "), createVNode("span", { class: "font-weight-medium" }, toDisplayString(stage.pivot?.startDate ? unref(date).format(stage.pivot.startDate, "year") : "—"), 1)]),
								createVNode("div", { class: "text-body-2" }, [createTextVNode(" Days passed: "), createVNode(VChip, {
									size: "small",
									variant: "outlined",
									class: "ms-2"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(timeDiff(stage.pivot?.startDate) ?? "—"), 1)]),
									_: 2
								}, 1024)])
							]),
							_: 2
						}, 1024);
					}), 128))])];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$4 = _sfc_main$4.setup;
_sfc_main$4.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/UnitStagesCard.vue");
	return _sfc_setup$4 ? _sfc_setup$4(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Unit/Mail/UnitMailTemplatesDialog.vue
var _sfc_main$3 = {
	__name: "UnitMailTemplatesDialog",
	__ssrInlineRender: true,
	props: {
		"modelValue": {
			type: Boolean,
			default: false
		},
		"modelModifiers": {}
	},
	emits: ["update:modelValue"],
	setup(__props) {
		const model = useModel(__props, "modelValue");
		const templates = ref([]);
		const loading = ref(false);
		const saving = ref(false);
		const editingId = ref(null);
		const form = ref({
			name: "",
			subject: "",
			body: "",
			is_active: true
		});
		async function fetchTemplates() {
			loading.value = true;
			try {
				const { data } = await axios.get("/api/mail-templates");
				templates.value = data ?? [];
			} catch (error) {
				console.error("Templates loading error:", error);
			} finally {
				loading.value = false;
			}
		}
		function resetForm() {
			editingId.value = null;
			form.value = {
				name: "",
				subject: "",
				body: "",
				is_active: true
			};
		}
		function editTemplate(template) {
			editingId.value = template.id;
			form.value = {
				name: template.name || "",
				subject: template.subject || "",
				body: template.body || "",
				is_active: Boolean(template.is_active)
			};
		}
		async function saveTemplate() {
			saving.value = true;
			try {
				if (editingId.value) await axios.put(`/api/mail-templates/${editingId.value}`, form.value);
				else await axios.post("/api/mail-templates", form.value);
				resetForm();
				await fetchTemplates();
			} catch (error) {
				console.error("Template saving error:", error);
			} finally {
				saving.value = false;
			}
		}
		async function deleteTemplate(template) {
			if (!confirm(`Удалить шаблон "${template.name}"?`)) return;
			await axios.delete(`/api/mail-templates/${template.id}`);
			if (editingId.value === template.id) resetForm();
			await fetchTemplates();
		}
		watch(model, (value) => {
			if (value) fetchTemplates();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VDialog, mergeProps({
				modelValue: model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"max-width": "1100",
				scrollable: ""
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VCard, { class: "rounded border border-blue-900 bg-slate-950" }, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<div class="text-blue-lighten-3"${_scopeId}> Шаблоны писем </div>`);
											_push(ssrRenderComponent(VBtn, {
												icon: "mdi-close",
												variant: "text",
												onClick: ($event) => model.value = false
											}, null, _parent, _scopeId));
										} else return [createVNode("div", { class: "text-blue-lighten-3" }, " Шаблоны писем "), createVNode(VBtn, {
											icon: "mdi-close",
											variant: "text",
											onClick: ($event) => model.value = false
										}, null, 8, ["onClick"])];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
								_push(ssrRenderComponent(VCardText, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VRow, null, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(ssrRenderComponent(VCol, {
														cols: "12",
														lg: "5"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VCard, {
																variant: "tonal",
																class: "pa-3"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(`<div class="text-subtitle-2 mb-3"${_scopeId}>${ssrInterpolate(editingId.value ? "Редактировать шаблон" : "Новый шаблон")}</div>`);
																		_push(ssrRenderComponent(VTextField, {
																			modelValue: form.value.name,
																			"onUpdate:modelValue": ($event) => form.value.name = $event,
																			label: "Название",
																			variant: "outlined",
																			density: "compact"
																		}, null, _parent, _scopeId));
																		_push(ssrRenderComponent(VTextField, {
																			modelValue: form.value.subject,
																			"onUpdate:modelValue": ($event) => form.value.subject = $event,
																			label: "Тема",
																			variant: "outlined",
																			density: "compact"
																		}, null, _parent, _scopeId));
																		_push(ssrRenderComponent(VTextarea, {
																			modelValue: form.value.body,
																			"onUpdate:modelValue": ($event) => form.value.body = $event,
																			label: "Текст",
																			variant: "outlined",
																			rows: "12",
																			"auto-grow": "",
																			hint: "Можно использовать: {{unit.name}}, {{unit.id}}, {{email.address}}",
																			"persistent-hint": ""
																		}, null, _parent, _scopeId));
																		_push(ssrRenderComponent(VSwitch, {
																			modelValue: form.value.is_active,
																			"onUpdate:modelValue": ($event) => form.value.is_active = $event,
																			label: "Активен",
																			color: "blue",
																			"hide-details": ""
																		}, null, _parent, _scopeId));
																		_push(`<div class="d-flex justify-end ga-2 mt-3"${_scopeId}>`);
																		_push(ssrRenderComponent(VBtn, {
																			variant: "text",
																			onClick: resetForm
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(` Сброс `);
																				else return [createTextVNode(" Сброс ")];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VBtn, {
																			color: "blue",
																			loading: saving.value,
																			disabled: !form.value.name,
																			onClick: saveTemplate
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(` Сохранить `);
																				else return [createTextVNode(" Сохранить ")];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(`</div>`);
																	} else return [
																		createVNode("div", { class: "text-subtitle-2 mb-3" }, toDisplayString(editingId.value ? "Редактировать шаблон" : "Новый шаблон"), 1),
																		createVNode(VTextField, {
																			modelValue: form.value.name,
																			"onUpdate:modelValue": ($event) => form.value.name = $event,
																			label: "Название",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"]),
																		createVNode(VTextField, {
																			modelValue: form.value.subject,
																			"onUpdate:modelValue": ($event) => form.value.subject = $event,
																			label: "Тема",
																			variant: "outlined",
																			density: "compact"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"]),
																		createVNode(VTextarea, {
																			modelValue: form.value.body,
																			"onUpdate:modelValue": ($event) => form.value.body = $event,
																			label: "Текст",
																			variant: "outlined",
																			rows: "12",
																			"auto-grow": "",
																			hint: "Можно использовать: {{unit.name}}, {{unit.id}}, {{email.address}}",
																			"persistent-hint": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"]),
																		createVNode(VSwitch, {
																			modelValue: form.value.is_active,
																			"onUpdate:modelValue": ($event) => form.value.is_active = $event,
																			label: "Активен",
																			color: "blue",
																			"hide-details": ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"]),
																		createVNode("div", { class: "d-flex justify-end ga-2 mt-3" }, [createVNode(VBtn, {
																			variant: "text",
																			onClick: resetForm
																		}, {
																			default: withCtx(() => [createTextVNode(" Сброс ")]),
																			_: 1
																		}), createVNode(VBtn, {
																			color: "blue",
																			loading: saving.value,
																			disabled: !form.value.name,
																			onClick: saveTemplate
																		}, {
																			default: withCtx(() => [createTextVNode(" Сохранить ")]),
																			_: 1
																		}, 8, ["loading", "disabled"])])
																	];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VCard, {
																variant: "tonal",
																class: "pa-3"
															}, {
																default: withCtx(() => [
																	createVNode("div", { class: "text-subtitle-2 mb-3" }, toDisplayString(editingId.value ? "Редактировать шаблон" : "Новый шаблон"), 1),
																	createVNode(VTextField, {
																		modelValue: form.value.name,
																		"onUpdate:modelValue": ($event) => form.value.name = $event,
																		label: "Название",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"]),
																	createVNode(VTextField, {
																		modelValue: form.value.subject,
																		"onUpdate:modelValue": ($event) => form.value.subject = $event,
																		label: "Тема",
																		variant: "outlined",
																		density: "compact"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"]),
																	createVNode(VTextarea, {
																		modelValue: form.value.body,
																		"onUpdate:modelValue": ($event) => form.value.body = $event,
																		label: "Текст",
																		variant: "outlined",
																		rows: "12",
																		"auto-grow": "",
																		hint: "Можно использовать: {{unit.name}}, {{unit.id}}, {{email.address}}",
																		"persistent-hint": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"]),
																	createVNode(VSwitch, {
																		modelValue: form.value.is_active,
																		"onUpdate:modelValue": ($event) => form.value.is_active = $event,
																		label: "Активен",
																		color: "blue",
																		"hide-details": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"]),
																	createVNode("div", { class: "d-flex justify-end ga-2 mt-3" }, [createVNode(VBtn, {
																		variant: "text",
																		onClick: resetForm
																	}, {
																		default: withCtx(() => [createTextVNode(" Сброс ")]),
																		_: 1
																	}), createVNode(VBtn, {
																		color: "blue",
																		loading: saving.value,
																		disabled: !form.value.name,
																		onClick: saveTemplate
																	}, {
																		default: withCtx(() => [createTextVNode(" Сохранить ")]),
																		_: 1
																	}, 8, ["loading", "disabled"])])
																]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCol, {
														cols: "12",
														lg: "7"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																if (loading.value) _push(ssrRenderComponent(VProgressLinear, {
																	indeterminate: "",
																	color: "blue"
																}, null, _parent, _scopeId));
																else _push(`<!---->`);
																_push(ssrRenderComponent(VList, {
																	density: "compact",
																	class: "rounded border border-blue-900 bg-slate-950"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(`<!--[-->`);
																			ssrRenderList(templates.value, (template) => {
																				_push(ssrRenderComponent(VListItem, { key: template.id }, {
																					title: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(`<div class="text-blue-lighten-3"${_scopeId}>${ssrInterpolate(template.name)}</div>`);
																						else return [createVNode("div", { class: "text-blue-lighten-3" }, toDisplayString(template.name), 1)];
																					}),
																					subtitle: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(`<div class="text-[11px] text-grey"${_scopeId}>${ssrInterpolate(template.subject || "Без темы")}</div>`);
																						else return [createVNode("div", { class: "text-[11px] text-grey" }, toDisplayString(template.subject || "Без темы"), 1)];
																					}),
																					append: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) {
																							_push(`<div class="d-flex ga-1"${_scopeId}>`);
																							_push(ssrRenderComponent(VBtn, {
																								icon: "mdi-pencil",
																								size: "x-small",
																								variant: "text",
																								color: "amber",
																								onClick: ($event) => editTemplate(template)
																							}, null, _parent, _scopeId));
																							_push(ssrRenderComponent(VBtn, {
																								icon: "mdi-delete",
																								size: "x-small",
																								variant: "text",
																								color: "red",
																								onClick: ($event) => deleteTemplate(template)
																							}, null, _parent, _scopeId));
																							_push(`</div>`);
																						} else return [createVNode("div", { class: "d-flex ga-1" }, [createVNode(VBtn, {
																							icon: "mdi-pencil",
																							size: "x-small",
																							variant: "text",
																							color: "amber",
																							onClick: ($event) => editTemplate(template)
																						}, null, 8, ["onClick"]), createVNode(VBtn, {
																							icon: "mdi-delete",
																							size: "x-small",
																							variant: "text",
																							color: "red",
																							onClick: ($event) => deleteTemplate(template)
																						}, null, 8, ["onClick"])])];
																					}),
																					_: 2
																				}, _parent, _scopeId));
																			});
																			_push(`<!--]-->`);
																		} else return [(openBlock(true), createBlock(Fragment, null, renderList(templates.value, (template) => {
																			return openBlock(), createBlock(VListItem, { key: template.id }, {
																				title: withCtx(() => [createVNode("div", { class: "text-blue-lighten-3" }, toDisplayString(template.name), 1)]),
																				subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, toDisplayString(template.subject || "Без темы"), 1)]),
																				append: withCtx(() => [createVNode("div", { class: "d-flex ga-1" }, [createVNode(VBtn, {
																					icon: "mdi-pencil",
																					size: "x-small",
																					variant: "text",
																					color: "amber",
																					onClick: ($event) => editTemplate(template)
																				}, null, 8, ["onClick"]), createVNode(VBtn, {
																					icon: "mdi-delete",
																					size: "x-small",
																					variant: "text",
																					color: "red",
																					onClick: ($event) => deleteTemplate(template)
																				}, null, 8, ["onClick"])])]),
																				_: 2
																			}, 1024);
																		}), 128))];
																	}),
																	_: 1
																}, _parent, _scopeId));
															} else return [loading.value ? (openBlock(), createBlock(VProgressLinear, {
																key: 0,
																indeterminate: "",
																color: "blue"
															})) : createCommentVNode("", true), createVNode(VList, {
																density: "compact",
																class: "rounded border border-blue-900 bg-slate-950"
															}, {
																default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(templates.value, (template) => {
																	return openBlock(), createBlock(VListItem, { key: template.id }, {
																		title: withCtx(() => [createVNode("div", { class: "text-blue-lighten-3" }, toDisplayString(template.name), 1)]),
																		subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, toDisplayString(template.subject || "Без темы"), 1)]),
																		append: withCtx(() => [createVNode("div", { class: "d-flex ga-1" }, [createVNode(VBtn, {
																			icon: "mdi-pencil",
																			size: "x-small",
																			variant: "text",
																			color: "amber",
																			onClick: ($event) => editTemplate(template)
																		}, null, 8, ["onClick"]), createVNode(VBtn, {
																			icon: "mdi-delete",
																			size: "x-small",
																			variant: "text",
																			color: "red",
																			onClick: ($event) => deleteTemplate(template)
																		}, null, 8, ["onClick"])])]),
																		_: 2
																	}, 1024);
																}), 128))]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
												} else return [createVNode(VCol, {
													cols: "12",
													lg: "5"
												}, {
													default: withCtx(() => [createVNode(VCard, {
														variant: "tonal",
														class: "pa-3"
													}, {
														default: withCtx(() => [
															createVNode("div", { class: "text-subtitle-2 mb-3" }, toDisplayString(editingId.value ? "Редактировать шаблон" : "Новый шаблон"), 1),
															createVNode(VTextField, {
																modelValue: form.value.name,
																"onUpdate:modelValue": ($event) => form.value.name = $event,
																label: "Название",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"]),
															createVNode(VTextField, {
																modelValue: form.value.subject,
																"onUpdate:modelValue": ($event) => form.value.subject = $event,
																label: "Тема",
																variant: "outlined",
																density: "compact"
															}, null, 8, ["modelValue", "onUpdate:modelValue"]),
															createVNode(VTextarea, {
																modelValue: form.value.body,
																"onUpdate:modelValue": ($event) => form.value.body = $event,
																label: "Текст",
																variant: "outlined",
																rows: "12",
																"auto-grow": "",
																hint: "Можно использовать: {{unit.name}}, {{unit.id}}, {{email.address}}",
																"persistent-hint": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"]),
															createVNode(VSwitch, {
																modelValue: form.value.is_active,
																"onUpdate:modelValue": ($event) => form.value.is_active = $event,
																label: "Активен",
																color: "blue",
																"hide-details": ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"]),
															createVNode("div", { class: "d-flex justify-end ga-2 mt-3" }, [createVNode(VBtn, {
																variant: "text",
																onClick: resetForm
															}, {
																default: withCtx(() => [createTextVNode(" Сброс ")]),
																_: 1
															}), createVNode(VBtn, {
																color: "blue",
																loading: saving.value,
																disabled: !form.value.name,
																onClick: saveTemplate
															}, {
																default: withCtx(() => [createTextVNode(" Сохранить ")]),
																_: 1
															}, 8, ["loading", "disabled"])])
														]),
														_: 1
													})]),
													_: 1
												}), createVNode(VCol, {
													cols: "12",
													lg: "7"
												}, {
													default: withCtx(() => [loading.value ? (openBlock(), createBlock(VProgressLinear, {
														key: 0,
														indeterminate: "",
														color: "blue"
													})) : createCommentVNode("", true), createVNode(VList, {
														density: "compact",
														class: "rounded border border-blue-900 bg-slate-950"
													}, {
														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(templates.value, (template) => {
															return openBlock(), createBlock(VListItem, { key: template.id }, {
																title: withCtx(() => [createVNode("div", { class: "text-blue-lighten-3" }, toDisplayString(template.name), 1)]),
																subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, toDisplayString(template.subject || "Без темы"), 1)]),
																append: withCtx(() => [createVNode("div", { class: "d-flex ga-1" }, [createVNode(VBtn, {
																	icon: "mdi-pencil",
																	size: "x-small",
																	variant: "text",
																	color: "amber",
																	onClick: ($event) => editTemplate(template)
																}, null, 8, ["onClick"]), createVNode(VBtn, {
																	icon: "mdi-delete",
																	size: "x-small",
																	variant: "text",
																	color: "red",
																	onClick: ($event) => deleteTemplate(template)
																}, null, 8, ["onClick"])])]),
																_: 2
															}, 1024);
														}), 128))]),
														_: 1
													})]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, {
												cols: "12",
												lg: "5"
											}, {
												default: withCtx(() => [createVNode(VCard, {
													variant: "tonal",
													class: "pa-3"
												}, {
													default: withCtx(() => [
														createVNode("div", { class: "text-subtitle-2 mb-3" }, toDisplayString(editingId.value ? "Редактировать шаблон" : "Новый шаблон"), 1),
														createVNode(VTextField, {
															modelValue: form.value.name,
															"onUpdate:modelValue": ($event) => form.value.name = $event,
															label: "Название",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextField, {
															modelValue: form.value.subject,
															"onUpdate:modelValue": ($event) => form.value.subject = $event,
															label: "Тема",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextarea, {
															modelValue: form.value.body,
															"onUpdate:modelValue": ($event) => form.value.body = $event,
															label: "Текст",
															variant: "outlined",
															rows: "12",
															"auto-grow": "",
															hint: "Можно использовать: {{unit.name}}, {{unit.id}}, {{email.address}}",
															"persistent-hint": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VSwitch, {
															modelValue: form.value.is_active,
															"onUpdate:modelValue": ($event) => form.value.is_active = $event,
															label: "Активен",
															color: "blue",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode("div", { class: "d-flex justify-end ga-2 mt-3" }, [createVNode(VBtn, {
															variant: "text",
															onClick: resetForm
														}, {
															default: withCtx(() => [createTextVNode(" Сброс ")]),
															_: 1
														}), createVNode(VBtn, {
															color: "blue",
															loading: saving.value,
															disabled: !form.value.name,
															onClick: saveTemplate
														}, {
															default: withCtx(() => [createTextVNode(" Сохранить ")]),
															_: 1
														}, 8, ["loading", "disabled"])])
													]),
													_: 1
												})]),
												_: 1
											}), createVNode(VCol, {
												cols: "12",
												lg: "7"
											}, {
												default: withCtx(() => [loading.value ? (openBlock(), createBlock(VProgressLinear, {
													key: 0,
													indeterminate: "",
													color: "blue"
												})) : createCommentVNode("", true), createVNode(VList, {
													density: "compact",
													class: "rounded border border-blue-900 bg-slate-950"
												}, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(templates.value, (template) => {
														return openBlock(), createBlock(VListItem, { key: template.id }, {
															title: withCtx(() => [createVNode("div", { class: "text-blue-lighten-3" }, toDisplayString(template.name), 1)]),
															subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, toDisplayString(template.subject || "Без темы"), 1)]),
															append: withCtx(() => [createVNode("div", { class: "d-flex ga-1" }, [createVNode(VBtn, {
																icon: "mdi-pencil",
																size: "x-small",
																variant: "text",
																color: "amber",
																onClick: ($event) => editTemplate(template)
															}, null, 8, ["onClick"]), createVNode(VBtn, {
																icon: "mdi-delete",
																size: "x-small",
																variant: "text",
																color: "red",
																onClick: ($event) => deleteTemplate(template)
															}, null, 8, ["onClick"])])]),
															_: 2
														}, 1024);
													}), 128))]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
									default: withCtx(() => [createVNode("div", { class: "text-blue-lighten-3" }, " Шаблоны писем "), createVNode(VBtn, {
										icon: "mdi-close",
										variant: "text",
										onClick: ($event) => model.value = false
									}, null, 8, ["onClick"])]),
									_: 1
								}),
								createVNode(VDivider),
								createVNode(VCardText, null, {
									default: withCtx(() => [createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, {
											cols: "12",
											lg: "5"
										}, {
											default: withCtx(() => [createVNode(VCard, {
												variant: "tonal",
												class: "pa-3"
											}, {
												default: withCtx(() => [
													createVNode("div", { class: "text-subtitle-2 mb-3" }, toDisplayString(editingId.value ? "Редактировать шаблон" : "Новый шаблон"), 1),
													createVNode(VTextField, {
														modelValue: form.value.name,
														"onUpdate:modelValue": ($event) => form.value.name = $event,
														label: "Название",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextField, {
														modelValue: form.value.subject,
														"onUpdate:modelValue": ($event) => form.value.subject = $event,
														label: "Тема",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextarea, {
														modelValue: form.value.body,
														"onUpdate:modelValue": ($event) => form.value.body = $event,
														label: "Текст",
														variant: "outlined",
														rows: "12",
														"auto-grow": "",
														hint: "Можно использовать: {{unit.name}}, {{unit.id}}, {{email.address}}",
														"persistent-hint": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VSwitch, {
														modelValue: form.value.is_active,
														"onUpdate:modelValue": ($event) => form.value.is_active = $event,
														label: "Активен",
														color: "blue",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode("div", { class: "d-flex justify-end ga-2 mt-3" }, [createVNode(VBtn, {
														variant: "text",
														onClick: resetForm
													}, {
														default: withCtx(() => [createTextVNode(" Сброс ")]),
														_: 1
													}), createVNode(VBtn, {
														color: "blue",
														loading: saving.value,
														disabled: !form.value.name,
														onClick: saveTemplate
													}, {
														default: withCtx(() => [createTextVNode(" Сохранить ")]),
														_: 1
													}, 8, ["loading", "disabled"])])
												]),
												_: 1
											})]),
											_: 1
										}), createVNode(VCol, {
											cols: "12",
											lg: "7"
										}, {
											default: withCtx(() => [loading.value ? (openBlock(), createBlock(VProgressLinear, {
												key: 0,
												indeterminate: "",
												color: "blue"
											})) : createCommentVNode("", true), createVNode(VList, {
												density: "compact",
												class: "rounded border border-blue-900 bg-slate-950"
											}, {
												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(templates.value, (template) => {
													return openBlock(), createBlock(VListItem, { key: template.id }, {
														title: withCtx(() => [createVNode("div", { class: "text-blue-lighten-3" }, toDisplayString(template.name), 1)]),
														subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, toDisplayString(template.subject || "Без темы"), 1)]),
														append: withCtx(() => [createVNode("div", { class: "d-flex ga-1" }, [createVNode(VBtn, {
															icon: "mdi-pencil",
															size: "x-small",
															variant: "text",
															color: "amber",
															onClick: ($event) => editTemplate(template)
														}, null, 8, ["onClick"]), createVNode(VBtn, {
															icon: "mdi-delete",
															size: "x-small",
															variant: "text",
															color: "red",
															onClick: ($event) => deleteTemplate(template)
														}, null, 8, ["onClick"])])]),
														_: 2
													}, 1024);
												}), 128))]),
												_: 1
											})]),
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
					else return [createVNode(VCard, { class: "rounded border border-blue-900 bg-slate-950" }, {
						default: withCtx(() => [
							createVNode(VCardTitle, { class: "d-flex justify-space-between align-center" }, {
								default: withCtx(() => [createVNode("div", { class: "text-blue-lighten-3" }, " Шаблоны писем "), createVNode(VBtn, {
									icon: "mdi-close",
									variant: "text",
									onClick: ($event) => model.value = false
								}, null, 8, ["onClick"])]),
								_: 1
							}),
							createVNode(VDivider),
							createVNode(VCardText, null, {
								default: withCtx(() => [createVNode(VRow, null, {
									default: withCtx(() => [createVNode(VCol, {
										cols: "12",
										lg: "5"
									}, {
										default: withCtx(() => [createVNode(VCard, {
											variant: "tonal",
											class: "pa-3"
										}, {
											default: withCtx(() => [
												createVNode("div", { class: "text-subtitle-2 mb-3" }, toDisplayString(editingId.value ? "Редактировать шаблон" : "Новый шаблон"), 1),
												createVNode(VTextField, {
													modelValue: form.value.name,
													"onUpdate:modelValue": ($event) => form.value.name = $event,
													label: "Название",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextField, {
													modelValue: form.value.subject,
													"onUpdate:modelValue": ($event) => form.value.subject = $event,
													label: "Тема",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextarea, {
													modelValue: form.value.body,
													"onUpdate:modelValue": ($event) => form.value.body = $event,
													label: "Текст",
													variant: "outlined",
													rows: "12",
													"auto-grow": "",
													hint: "Можно использовать: {{unit.name}}, {{unit.id}}, {{email.address}}",
													"persistent-hint": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VSwitch, {
													modelValue: form.value.is_active,
													"onUpdate:modelValue": ($event) => form.value.is_active = $event,
													label: "Активен",
													color: "blue",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode("div", { class: "d-flex justify-end ga-2 mt-3" }, [createVNode(VBtn, {
													variant: "text",
													onClick: resetForm
												}, {
													default: withCtx(() => [createTextVNode(" Сброс ")]),
													_: 1
												}), createVNode(VBtn, {
													color: "blue",
													loading: saving.value,
													disabled: !form.value.name,
													onClick: saveTemplate
												}, {
													default: withCtx(() => [createTextVNode(" Сохранить ")]),
													_: 1
												}, 8, ["loading", "disabled"])])
											]),
											_: 1
										})]),
										_: 1
									}), createVNode(VCol, {
										cols: "12",
										lg: "7"
									}, {
										default: withCtx(() => [loading.value ? (openBlock(), createBlock(VProgressLinear, {
											key: 0,
											indeterminate: "",
											color: "blue"
										})) : createCommentVNode("", true), createVNode(VList, {
											density: "compact",
											class: "rounded border border-blue-900 bg-slate-950"
										}, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(templates.value, (template) => {
												return openBlock(), createBlock(VListItem, { key: template.id }, {
													title: withCtx(() => [createVNode("div", { class: "text-blue-lighten-3" }, toDisplayString(template.name), 1)]),
													subtitle: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, toDisplayString(template.subject || "Без темы"), 1)]),
													append: withCtx(() => [createVNode("div", { class: "d-flex ga-1" }, [createVNode(VBtn, {
														icon: "mdi-pencil",
														size: "x-small",
														variant: "text",
														color: "amber",
														onClick: ($event) => editTemplate(template)
													}, null, 8, ["onClick"]), createVNode(VBtn, {
														icon: "mdi-delete",
														size: "x-small",
														variant: "text",
														color: "red",
														onClick: ($event) => deleteTemplate(template)
													}, null, 8, ["onClick"])])]),
													_: 2
												}, 1024);
											}), 128))]),
											_: 1
										})]),
										_: 1
									})]),
									_: 1
								})]),
								_: 1
							})
						]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$3 = _sfc_main$3.setup;
_sfc_main$3.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/Mail/UnitMailTemplatesDialog.vue");
	return _sfc_setup$3 ? _sfc_setup$3(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Composables/useUnitMail.js
function useUnitMail(unitId) {
	const messages = ref([]);
	const relatedEmails = ref([]);
	const totalItems = ref(0);
	const loading = ref(false);
	const sending = ref(false);
	const reading = ref(false);
	const selectedMessage = ref(null);
	const search = ref("");
	const direction = ref(null);
	const options = ref({
		page: 1,
		itemsPerPage: 15,
		sortBy: []
	});
	async function fetchMessages() {
		if (!unitId) return;
		loading.value = true;
		try {
			const { data } = await axios.get(`/api/units/${unitId}/mail-messages`, { params: {
				search: search.value,
				direction: direction.value,
				page: options.value.page,
				per_page: options.value.itemsPerPage
			} });
			messages.value = data.data ?? [];
			totalItems.value = data.meta?.total ?? data.total ?? 0;
			relatedEmails.value = data.related_emails ?? [];
		} catch (error) {
			console.error("Unit mail loading error:", error);
		} finally {
			loading.value = false;
		}
	}
	async function readMessage(message, force = false) {
		if (!message?.id) return;
		reading.value = true;
		try {
			const { data } = await axios.get(`/api/mail-messages/${message.id}`, { params: { force } });
			selectedMessage.value = data;
		} catch (error) {
			console.error("Mail message reading error:", error);
		} finally {
			reading.value = false;
		}
	}
	async function sendMail(payload) {
		sending.value = true;
		try {
			await axios.post(`/api/units/${unitId}/mail/send`, payload, { headers: { "Content-Type": "multipart/form-data" } });
			await fetchMessages();
		} catch (error) {
			console.error("Unit mail sending error:", error);
			throw error;
		} finally {
			sending.value = false;
		}
	}
	let searchTimer = null;
	watch(search, () => {
		clearTimeout(searchTimer);
		searchTimer = setTimeout(() => {
			options.value.page = 1;
			fetchMessages();
		}, 350);
	});
	watch(direction, () => {
		options.value.page = 1;
		fetchMessages();
	});
	watch(options, () => {
		fetchMessages();
	}, { deep: true });
	return {
		messages,
		relatedEmails,
		totalItems,
		loading,
		sending,
		reading,
		selectedMessage,
		search,
		direction,
		options,
		fetchMessages,
		readMessage,
		sendMail
	};
}
//#endregion
//#region resources/js/Components/Unit/UnitSendingsCard.vue
var _sfc_main$2 = {
	__name: "UnitSendingsCard",
	__ssrInlineRender: true,
	props: { unit: {
		type: Object,
		required: true
	} },
	setup(__props) {
		const props = __props;
		const { messages, relatedEmails, totalItems, loading, sending, reading, selectedMessage, search, direction, options, fetchMessages, readMessage } = useUnitMail(props.unit.id);
		const { files, loadFiles } = useUnitFiles(props.unit.id);
		const readerDialog = ref(false);
		const composerDialog = ref(false);
		const templatesDialog = ref(false);
		let autoRefreshTimer = null;
		const headers = [
			{
				title: "Тип",
				key: "direction",
				sortable: false,
				width: "84px"
			},
			{
				title: "Дата",
				key: "message_date",
				sortable: false,
				width: "140px"
			},
			{
				title: "Контакт",
				key: "contact",
				sortable: false,
				width: "260px"
			},
			{
				title: "Тема",
				key: "subject",
				sortable: false
			},
			{
				title: "",
				key: "actions",
				sortable: false,
				align: "end",
				width: "70px"
			}
		];
		const directionItems = [
			{
				title: "Все",
				value: null
			},
			{
				title: "Входящие",
				value: "incoming"
			},
			{
				title: "Исходящие",
				value: "outgoing"
			}
		];
		const visibleRelatedEmails = computed(() => {
			return relatedEmails.value.slice(0, 5);
		});
		const hiddenRelatedEmailsCount = computed(() => {
			return Math.max(relatedEmails.value.length - visibleRelatedEmails.value.length, 0);
		});
		function formatDate(value) {
			if (!value) return "—";
			return new Intl.DateTimeFormat("ru-RU", {
				day: "2-digit",
				month: "2-digit",
				year: "2-digit",
				hour: "2-digit",
				minute: "2-digit"
			}).format(new Date(value));
		}
		function recipients(item) {
			const to = item.to?.map((recipient) => recipient.address).join(", ");
			const cc = item.cc?.map((recipient) => recipient.address).join(", ");
			return [to ? `To: ${to}` : null, cc ? `CC: ${cc}` : null].filter(Boolean);
		}
		async function openMessage(message) {
			readerDialog.value = true;
			await readMessage(message);
		}
		async function forceReloadMessage() {
			if (selectedMessage.value) await readMessage(selectedMessage.value, true);
		}
		async function afterSent() {
			composerDialog.value = false;
			await fetchMessages();
		}
		onMounted(async () => {
			await Promise.all([fetchMessages(), loadFiles()]);
			autoRefreshTimer = window.setInterval(() => {
				fetchMessages();
			}, 3e4);
		});
		onUnmounted(() => {
			if (autoRefreshTimer) window.clearInterval(autoRefreshTimer);
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(BaseSectionCard_default, mergeProps({
				title: "Mail",
				icon: "mdi-email-fast-outline",
				compact: ""
			}, _attrs), {
				actions: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="d-flex ga-1" data-v-9261ad95${_scopeId}>`);
						_push(ssrRenderComponent(VBtn, {
							icon: "mdi-file-document-edit-outline",
							size: "small",
							variant: "text",
							color: "blue",
							onClick: ($event) => templatesDialog.value = true
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(VBtn, {
							icon: "mdi-refresh",
							size: "small",
							variant: "text",
							color: "teal",
							loading: unref(loading),
							onClick: unref(fetchMessages)
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(VBtn, {
							icon: "mdi-email-plus-outline",
							size: "small",
							variant: "text",
							color: "blue",
							onClick: ($event) => composerDialog.value = true
						}, null, _parent, _scopeId));
						_push(`</div>`);
					} else return [createVNode("div", { class: "d-flex ga-1" }, [
						createVNode(VBtn, {
							icon: "mdi-file-document-edit-outline",
							size: "small",
							variant: "text",
							color: "blue",
							onClick: ($event) => templatesDialog.value = true
						}, null, 8, ["onClick"]),
						createVNode(VBtn, {
							icon: "mdi-refresh",
							size: "small",
							variant: "text",
							color: "teal",
							loading: unref(loading),
							onClick: unref(fetchMessages)
						}, null, 8, ["loading", "onClick"]),
						createVNode(VBtn, {
							icon: "mdi-email-plus-outline",
							size: "small",
							variant: "text",
							color: "blue",
							onClick: ($event) => composerDialog.value = true
						}, null, 8, ["onClick"])
					])];
				}),
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="mb-3" data-v-9261ad95${_scopeId}><div class="text-caption text-medium-emphasis" data-v-9261ad95${_scopeId}> Письма по emails Unit и emails связанных Entities </div><div class="d-flex flex-wrap ga-1 mt-1 unit-mail-related-emails" data-v-9261ad95${_scopeId}><!--[-->`);
						ssrRenderList(visibleRelatedEmails.value, (email) => {
							_push(ssrRenderComponent(VChip, {
								key: email.address,
								size: "x-small",
								color: "blue",
								variant: "tonal"
							}, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(`${ssrInterpolate(email.address)}`);
									else return [createTextVNode(toDisplayString(email.address), 1)];
								}),
								_: 2
							}, _parent, _scopeId));
						});
						_push(`<!--]-->`);
						if (hiddenRelatedEmailsCount.value) _push(ssrRenderComponent(VChip, {
							size: "x-small",
							color: "grey",
							variant: "tonal"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` +${ssrInterpolate(hiddenRelatedEmailsCount.value)}`);
								else return [createTextVNode(" +" + toDisplayString(hiddenRelatedEmailsCount.value), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
						else _push(`<!---->`);
						_push(`</div></div>`);
						_push(ssrRenderComponent(VRow, {
							dense: "",
							class: "mb-3"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "8"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VTextField, {
												modelValue: unref(search),
												"onUpdate:modelValue": ($event) => isRef(search) ? search.value = $event : null,
												label: "Поиск по письмам",
												"prepend-inner-icon": "mdi-magnify",
												variant: "solo",
												density: "compact",
												clearable: "",
												"hide-details": ""
											}, null, _parent, _scopeId));
											else return [createVNode(VTextField, {
												modelValue: unref(search),
												"onUpdate:modelValue": ($event) => isRef(search) ? search.value = $event : null,
												label: "Поиск по письмам",
												"prepend-inner-icon": "mdi-magnify",
												variant: "solo",
												density: "compact",
												clearable: "",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "4"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VSelect, {
												modelValue: unref(direction),
												"onUpdate:modelValue": ($event) => isRef(direction) ? direction.value = $event : null,
												items: directionItems,
												label: "Тип",
												variant: "outlined",
												density: "compact",
												"hide-details": ""
											}, null, _parent, _scopeId));
											else return [createVNode(VSelect, {
												modelValue: unref(direction),
												"onUpdate:modelValue": ($event) => isRef(direction) ? direction.value = $event : null,
												items: directionItems,
												label: "Тип",
												variant: "outlined",
												density: "compact",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, {
									cols: "12",
									lg: "8"
								}, {
									default: withCtx(() => [createVNode(VTextField, {
										modelValue: unref(search),
										"onUpdate:modelValue": ($event) => isRef(search) ? search.value = $event : null,
										label: "Поиск по письмам",
										"prepend-inner-icon": "mdi-magnify",
										variant: "solo",
										density: "compact",
										clearable: "",
										"hide-details": ""
									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
									_: 1
								}), createVNode(VCol, {
									cols: "12",
									lg: "4"
								}, {
									default: withCtx(() => [createVNode(VSelect, {
										modelValue: unref(direction),
										"onUpdate:modelValue": ($event) => isRef(direction) ? direction.value = $event : null,
										items: directionItems,
										label: "Тип",
										variant: "outlined",
										density: "compact",
										"hide-details": ""
									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDataTableServer, {
							headers,
							items: unref(messages),
							"items-length": unref(totalItems),
							loading: unref(loading),
							page: unref(options).page,
							"items-per-page": unref(options).itemsPerPage,
							"item-value": "id",
							density: "compact",
							"fixed-header": "",
							height: "340",
							hover: "",
							class: "rounded border border-blue-900 bg-slate-950",
							"onUpdate:options": ($event) => options.value = $event,
							"onClick:row": (_, row) => openMessage(row.item)
						}, {
							"item.direction": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VChip, {
									size: "x-small",
									color: item.direction === "incoming" ? "purple" : "blue",
									variant: "tonal"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(`${ssrInterpolate(item.direction === "incoming" ? "↓ in" : "↑ out")}`);
										else return [createTextVNode(toDisplayString(item.direction === "incoming" ? "↓ in" : "↑ out"), 1)];
									}),
									_: 2
								}, _parent, _scopeId));
								else return [createVNode(VChip, {
									size: "x-small",
									color: item.direction === "incoming" ? "purple" : "blue",
									variant: "tonal"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(item.direction === "incoming" ? "↓ in" : "↑ out"), 1)]),
									_: 2
								}, 1032, ["color"])];
							}),
							"item.message_date": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(`<span class="text-[10px] font-mono" data-v-9261ad95${_scopeId}>${ssrInterpolate(formatDate(item.message_date))}</span><div class="text-[9px] text-grey" data-v-9261ad95${_scopeId}>${ssrInterpolate(item.folder)}</div>`);
								else return [createVNode("span", { class: "text-[10px] font-mono" }, toDisplayString(formatDate(item.message_date)), 1), createVNode("div", { class: "text-[9px] text-grey" }, toDisplayString(item.folder), 1)];
							}),
							"item.contact": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) if (item.direction === "incoming") {
									_push(`<div data-v-9261ad95${_scopeId}><div class="text-purple-lighten-3 text-xs" data-v-9261ad95${_scopeId}>${ssrInterpolate(item.from_address || "—")}</div>`);
									if (item.from_name) _push(`<div class="text-[10px] text-grey" data-v-9261ad95${_scopeId}>${ssrInterpolate(item.from_name)}</div>`);
									else _push(`<!---->`);
									_push(`</div>`);
								} else {
									_push(`<div class="text-[10px] text-grey-lighten-1" data-v-9261ad95${_scopeId}><!--[-->`);
									ssrRenderList(recipients(item), (line) => {
										_push(`<div data-v-9261ad95${_scopeId}>${ssrInterpolate(line)}</div>`);
									});
									_push(`<!--]--></div>`);
								}
								else return [item.direction === "incoming" ? (openBlock(), createBlock("div", { key: 0 }, [createVNode("div", { class: "text-purple-lighten-3 text-xs" }, toDisplayString(item.from_address || "—"), 1), item.from_name ? (openBlock(), createBlock("div", {
									key: 0,
									class: "text-[10px] text-grey"
								}, toDisplayString(item.from_name), 1)) : createCommentVNode("", true)])) : (openBlock(), createBlock("div", {
									key: 1,
									class: "text-[10px] text-grey-lighten-1"
								}, [(openBlock(true), createBlock(Fragment, null, renderList(recipients(item), (line) => {
									return openBlock(), createBlock("div", { key: line }, toDisplayString(line), 1);
								}), 128))]))];
							}),
							"item.subject": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<div class="py-1 cursor-pointer" data-v-9261ad95${_scopeId}><div class="text-sm text-blue-lighten-4 hover:text-white" data-v-9261ad95${_scopeId}>${ssrInterpolate(item.subject || "Без темы")}</div>`);
									if (item.preview) _push(`<div class="text-[10px] text-grey-lighten-1 line-clamp-2 mt-1" data-v-9261ad95${_scopeId}>${ssrInterpolate(item.preview)}</div>`);
									else _push(`<!---->`);
									_push(`</div>`);
								} else return [createVNode("div", { class: "py-1 cursor-pointer" }, [createVNode("div", { class: "text-sm text-blue-lighten-4 hover:text-white" }, toDisplayString(item.subject || "Без темы"), 1), item.preview ? (openBlock(), createBlock("div", {
									key: 0,
									class: "text-[10px] text-grey-lighten-1 line-clamp-2 mt-1"
								}, toDisplayString(item.preview), 1)) : createCommentVNode("", true)])];
							}),
							"item.actions": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VBtn, {
									icon: "mdi-email-open-outline",
									size: "x-small",
									variant: "text",
									color: "blue",
									onClick: ($event) => openMessage(item)
								}, null, _parent, _scopeId));
								else return [createVNode(VBtn, {
									icon: "mdi-email-open-outline",
									size: "x-small",
									variant: "text",
									color: "blue",
									onClick: withModifiers(($event) => openMessage(item), ["stop"])
								}, null, 8, ["onClick"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$10, {
							modelValue: composerDialog.value,
							"onUpdate:modelValue": ($event) => composerDialog.value = $event,
							"unit-id": __props.unit.id,
							recipients: unref(relatedEmails),
							"unit-files": unref(files),
							sending: unref(sending),
							onSent: afterSent,
							onOpenTemplates: ($event) => templatesDialog.value = true
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$3, {
							modelValue: templatesDialog.value,
							"onUpdate:modelValue": ($event) => templatesDialog.value = $event
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(MailMessageReaderDialog_default, {
							modelValue: readerDialog.value,
							"onUpdate:modelValue": ($event) => readerDialog.value = $event,
							message: unref(selectedMessage),
							loading: unref(reading),
							onReload: forceReloadMessage
						}, null, _parent, _scopeId));
					} else return [
						createVNode("div", { class: "mb-3" }, [createVNode("div", { class: "text-caption text-medium-emphasis" }, " Письма по emails Unit и emails связанных Entities "), createVNode("div", { class: "d-flex flex-wrap ga-1 mt-1 unit-mail-related-emails" }, [(openBlock(true), createBlock(Fragment, null, renderList(visibleRelatedEmails.value, (email) => {
							return openBlock(), createBlock(VChip, {
								key: email.address,
								size: "x-small",
								color: "blue",
								variant: "tonal"
							}, {
								default: withCtx(() => [createTextVNode(toDisplayString(email.address), 1)]),
								_: 2
							}, 1024);
						}), 128)), hiddenRelatedEmailsCount.value ? (openBlock(), createBlock(VChip, {
							key: 0,
							size: "x-small",
							color: "grey",
							variant: "tonal"
						}, {
							default: withCtx(() => [createTextVNode(" +" + toDisplayString(hiddenRelatedEmailsCount.value), 1)]),
							_: 1
						})) : createCommentVNode("", true)])]),
						createVNode(VRow, {
							dense: "",
							class: "mb-3"
						}, {
							default: withCtx(() => [createVNode(VCol, {
								cols: "12",
								lg: "8"
							}, {
								default: withCtx(() => [createVNode(VTextField, {
									modelValue: unref(search),
									"onUpdate:modelValue": ($event) => isRef(search) ? search.value = $event : null,
									label: "Поиск по письмам",
									"prepend-inner-icon": "mdi-magnify",
									variant: "solo",
									density: "compact",
									clearable: "",
									"hide-details": ""
								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
								_: 1
							}), createVNode(VCol, {
								cols: "12",
								lg: "4"
							}, {
								default: withCtx(() => [createVNode(VSelect, {
									modelValue: unref(direction),
									"onUpdate:modelValue": ($event) => isRef(direction) ? direction.value = $event : null,
									items: directionItems,
									label: "Тип",
									variant: "outlined",
									density: "compact",
									"hide-details": ""
								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
								_: 1
							})]),
							_: 1
						}),
						createVNode(VDataTableServer, {
							headers,
							items: unref(messages),
							"items-length": unref(totalItems),
							loading: unref(loading),
							page: unref(options).page,
							"items-per-page": unref(options).itemsPerPage,
							"item-value": "id",
							density: "compact",
							"fixed-header": "",
							height: "340",
							hover: "",
							class: "rounded border border-blue-900 bg-slate-950",
							"onUpdate:options": ($event) => options.value = $event,
							"onClick:row": (_, row) => openMessage(row.item)
						}, {
							"item.direction": withCtx(({ item }) => [createVNode(VChip, {
								size: "x-small",
								color: item.direction === "incoming" ? "purple" : "blue",
								variant: "tonal"
							}, {
								default: withCtx(() => [createTextVNode(toDisplayString(item.direction === "incoming" ? "↓ in" : "↑ out"), 1)]),
								_: 2
							}, 1032, ["color"])]),
							"item.message_date": withCtx(({ item }) => [createVNode("span", { class: "text-[10px] font-mono" }, toDisplayString(formatDate(item.message_date)), 1), createVNode("div", { class: "text-[9px] text-grey" }, toDisplayString(item.folder), 1)]),
							"item.contact": withCtx(({ item }) => [item.direction === "incoming" ? (openBlock(), createBlock("div", { key: 0 }, [createVNode("div", { class: "text-purple-lighten-3 text-xs" }, toDisplayString(item.from_address || "—"), 1), item.from_name ? (openBlock(), createBlock("div", {
								key: 0,
								class: "text-[10px] text-grey"
							}, toDisplayString(item.from_name), 1)) : createCommentVNode("", true)])) : (openBlock(), createBlock("div", {
								key: 1,
								class: "text-[10px] text-grey-lighten-1"
							}, [(openBlock(true), createBlock(Fragment, null, renderList(recipients(item), (line) => {
								return openBlock(), createBlock("div", { key: line }, toDisplayString(line), 1);
							}), 128))]))]),
							"item.subject": withCtx(({ item }) => [createVNode("div", { class: "py-1 cursor-pointer" }, [createVNode("div", { class: "text-sm text-blue-lighten-4 hover:text-white" }, toDisplayString(item.subject || "Без темы"), 1), item.preview ? (openBlock(), createBlock("div", {
								key: 0,
								class: "text-[10px] text-grey-lighten-1 line-clamp-2 mt-1"
							}, toDisplayString(item.preview), 1)) : createCommentVNode("", true)])]),
							"item.actions": withCtx(({ item }) => [createVNode(VBtn, {
								icon: "mdi-email-open-outline",
								size: "x-small",
								variant: "text",
								color: "blue",
								onClick: withModifiers(($event) => openMessage(item), ["stop"])
							}, null, 8, ["onClick"])]),
							_: 1
						}, 8, [
							"items",
							"items-length",
							"loading",
							"page",
							"items-per-page",
							"onUpdate:options",
							"onClick:row"
						]),
						createVNode(_sfc_main$10, {
							modelValue: composerDialog.value,
							"onUpdate:modelValue": ($event) => composerDialog.value = $event,
							"unit-id": __props.unit.id,
							recipients: unref(relatedEmails),
							"unit-files": unref(files),
							sending: unref(sending),
							onSent: afterSent,
							onOpenTemplates: ($event) => templatesDialog.value = true
						}, null, 8, [
							"modelValue",
							"onUpdate:modelValue",
							"unit-id",
							"recipients",
							"unit-files",
							"sending",
							"onOpenTemplates"
						]),
						createVNode(_sfc_main$3, {
							modelValue: templatesDialog.value,
							"onUpdate:modelValue": ($event) => templatesDialog.value = $event
						}, null, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(MailMessageReaderDialog_default, {
							modelValue: readerDialog.value,
							"onUpdate:modelValue": ($event) => readerDialog.value = $event,
							message: unref(selectedMessage),
							loading: unref(reading),
							onReload: forceReloadMessage
						}, null, 8, [
							"modelValue",
							"onUpdate:modelValue",
							"message",
							"loading"
						])
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/UnitSendingsCard.vue");
	return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
var UnitSendingsCard_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main$2, [["__scopeId", "data-v-9261ad95"]]);
//#endregion
//#region resources/js/Components/Unit/UnitSalesCard.vue
var _sfc_main$1 = {
	__name: "UnitSalesCard",
	__ssrInlineRender: true,
	props: { entities: {
		type: Array,
		default: () => []
	} },
	setup(__props) {
		const date = useDate();
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(BaseSectionCard_default, mergeProps({
				title: "Sales",
				icon: "mdi-currency-usd"
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="d-flex flex-column ga-4"${_scopeId}><!--[-->`);
						ssrRenderList(__props.entities, (entity) => {
							_push(`<!--[-->`);
							if (entity.sales?.length) _push(ssrRenderComponent(VSheet, {
								rounded: "lg",
								border: "",
								class: "pa-3"
							}, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) {
										_push(`<div class="font-weight-bold mb-3"${_scopeId}>${ssrInterpolate(entity.name)}</div>`);
										_push(ssrRenderComponent(VList, {
											density: "compact",
											class: "pa-0"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(`<!--[-->`);
													ssrRenderList(entity.sales, (sale) => {
														_push(ssrRenderComponent(VListItem, {
															key: sale.id,
															class: "px-0"
														}, {
															title: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`<div class="d-flex justify-space-between"${_scopeId}><span${_scopeId}>${ssrInterpolate(unref(date).format(sale.date, "fullDate"))}</span><span class="font-weight-medium"${_scopeId}>${ssrInterpolate(sale.total)}</span></div>`);
																else return [createVNode("div", { class: "d-flex justify-space-between" }, [createVNode("span", null, toDisplayString(unref(date).format(sale.date, "fullDate")), 1), createVNode("span", { class: "font-weight-medium" }, toDisplayString(sale.total), 1)])];
															}),
															_: 2
														}, _parent, _scopeId));
													});
													_push(`<!--]-->`);
												} else return [(openBlock(true), createBlock(Fragment, null, renderList(entity.sales, (sale) => {
													return openBlock(), createBlock(VListItem, {
														key: sale.id,
														class: "px-0"
													}, {
														title: withCtx(() => [createVNode("div", { class: "d-flex justify-space-between" }, [createVNode("span", null, toDisplayString(unref(date).format(sale.date, "fullDate")), 1), createVNode("span", { class: "font-weight-medium" }, toDisplayString(sale.total), 1)])]),
														_: 2
													}, 1024);
												}), 128))];
											}),
											_: 2
										}, _parent, _scopeId));
									} else return [createVNode("div", { class: "font-weight-bold mb-3" }, toDisplayString(entity.name), 1), createVNode(VList, {
										density: "compact",
										class: "pa-0"
									}, {
										default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.sales, (sale) => {
											return openBlock(), createBlock(VListItem, {
												key: sale.id,
												class: "px-0"
											}, {
												title: withCtx(() => [createVNode("div", { class: "d-flex justify-space-between" }, [createVNode("span", null, toDisplayString(unref(date).format(sale.date, "fullDate")), 1), createVNode("span", { class: "font-weight-medium" }, toDisplayString(sale.total), 1)])]),
												_: 2
											}, 1024);
										}), 128))]),
										_: 2
									}, 1024)];
								}),
								_: 2
							}, _parent, _scopeId));
							else _push(`<!---->`);
							_push(`<!--]-->`);
						});
						_push(`<!--]--></div>`);
					} else return [createVNode("div", { class: "d-flex flex-column ga-4" }, [(openBlock(true), createBlock(Fragment, null, renderList(__props.entities, (entity) => {
						return openBlock(), createBlock(Fragment, { key: entity.id }, [entity.sales?.length ? (openBlock(), createBlock(VSheet, {
							key: 0,
							rounded: "lg",
							border: "",
							class: "pa-3"
						}, {
							default: withCtx(() => [createVNode("div", { class: "font-weight-bold mb-3" }, toDisplayString(entity.name), 1), createVNode(VList, {
								density: "compact",
								class: "pa-0"
							}, {
								default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(entity.sales, (sale) => {
									return openBlock(), createBlock(VListItem, {
										key: sale.id,
										class: "px-0"
									}, {
										title: withCtx(() => [createVNode("div", { class: "d-flex justify-space-between" }, [createVNode("span", null, toDisplayString(unref(date).format(sale.date, "fullDate")), 1), createVNode("span", { class: "font-weight-medium" }, toDisplayString(sale.total), 1)])]),
										_: 2
									}, 1024);
								}), 128))]),
								_: 2
							}, 1024)]),
							_: 2
						}, 1024)) : createCommentVNode("", true)], 64);
					}), 128))])];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Unit/UnitSalesCard.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Pages/Ameise/Unit.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$15 }, {
	__name: "Unit",
	__ssrInlineRender: true,
	props: {
		unit: Object,
		dictionaries: {
			type: Object,
			default: () => ({})
		},
		files: {
			type: Array,
			default: () => []
		}
	},
	setup(__props) {
		const props = __props;
		const { unit, files, dict, loading, refreshUnit, loadFiles, loadDictionaries, searchGoods } = useUnitPage(props.unit, props.dictionaries, props.files);
		const pageTitle = computed(() => `Unit: ${unit.value?.name ?? ""}`);
		useHead(() => ({
			title: pageTitle.value,
			meta: [{
				name: "description",
				content: `Информация о блоке ${unit.value?.name ?? ""}`
			}]
		}));
		const requiredDictionaryKeys = [
			"buildings",
			"cities",
			"emails",
			"entities",
			"entityClassifications",
			"fields",
			"goods",
			"labels",
			"measures",
			"products",
			"telephones",
			"uris"
		];
		function hasAllDictionaries(source = {}) {
			return requiredDictionaryKeys.every((key) => Array.isArray(source?.[key]));
		}
		onMounted(async () => {
			if (!props.files?.length) await loadFiles();
			if (!hasAllDictionaries(props.dictionaries)) await loadDictionaries();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({
				fluid: "",
				class: "pa-4"
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, {
							align: "start",
							dense: ""
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "4"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(_sfc_main$9, {
												unit: unref(unit),
												files: unref(files),
												dict: unref(dict),
												loading: unref(loading),
												onRefresh: unref(refreshUnit)
											}, null, _parent, _scopeId));
											else return [createVNode(_sfc_main$9, {
												unit: unref(unit),
												files: unref(files),
												dict: unref(dict),
												loading: unref(loading),
												onRefresh: unref(refreshUnit)
											}, null, 8, [
												"unit",
												"files",
												"dict",
												"loading",
												"onRefresh"
											])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "5"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(UnitSendingsCard_default, { unit: unref(unit) }, null, _parent, _scopeId));
											else return [createVNode(UnitSendingsCard_default, { unit: unref(unit) }, null, 8, ["unit"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "3"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(_sfc_main$4, { stages: unref(unit).stages || [] }, null, _parent, _scopeId));
											else return [createVNode(_sfc_main$4, { stages: unref(unit).stages || [] }, null, 8, ["stages"])];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VCol, {
										cols: "12",
										lg: "4"
									}, {
										default: withCtx(() => [createVNode(_sfc_main$9, {
											unit: unref(unit),
											files: unref(files),
											dict: unref(dict),
											loading: unref(loading),
											onRefresh: unref(refreshUnit)
										}, null, 8, [
											"unit",
											"files",
											"dict",
											"loading",
											"onRefresh"
										])]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										lg: "5"
									}, {
										default: withCtx(() => [createVNode(UnitSendingsCard_default, { unit: unref(unit) }, null, 8, ["unit"])]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										lg: "3"
									}, {
										default: withCtx(() => [createVNode(_sfc_main$4, { stages: unref(unit).stages || [] }, null, 8, ["stages"])]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "4"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(_sfc_main$6, {
												unit: unref(unit),
												dict: unref(dict),
												onRefresh: unref(refreshUnit)
											}, null, _parent, _scopeId));
											else return [createVNode(_sfc_main$6, {
												unit: unref(unit),
												dict: unref(dict),
												onRefresh: unref(refreshUnit)
											}, null, 8, [
												"unit",
												"dict",
												"onRefresh"
											])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "4"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(_sfc_main$7, {
												unit: unref(unit),
												dict: unref(dict),
												"goods-loading": unref(loading).goods,
												"search-goods": unref(searchGoods),
												onRefresh: unref(refreshUnit)
											}, null, _parent, _scopeId));
											else return [createVNode(_sfc_main$7, {
												unit: unref(unit),
												dict: unref(dict),
												"goods-loading": unref(loading).goods,
												"search-goods": unref(searchGoods),
												onRefresh: unref(refreshUnit)
											}, null, 8, [
												"unit",
												"dict",
												"goods-loading",
												"search-goods",
												"onRefresh"
											])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "4"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(_sfc_main$5, {
												unit: unref(unit),
												dict: unref(dict),
												onRefresh: unref(refreshUnit)
											}, null, _parent, _scopeId));
											else return [createVNode(_sfc_main$5, {
												unit: unref(unit),
												dict: unref(dict),
												onRefresh: unref(refreshUnit)
											}, null, 8, [
												"unit",
												"dict",
												"onRefresh"
											])];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VCol, {
										cols: "12",
										lg: "4"
									}, {
										default: withCtx(() => [createVNode(_sfc_main$6, {
											unit: unref(unit),
											dict: unref(dict),
											onRefresh: unref(refreshUnit)
										}, null, 8, [
											"unit",
											"dict",
											"onRefresh"
										])]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										lg: "4"
									}, {
										default: withCtx(() => [createVNode(_sfc_main$7, {
											unit: unref(unit),
											dict: unref(dict),
											"goods-loading": unref(loading).goods,
											"search-goods": unref(searchGoods),
											onRefresh: unref(refreshUnit)
										}, null, 8, [
											"unit",
											"dict",
											"goods-loading",
											"search-goods",
											"onRefresh"
										])]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										lg: "4"
									}, {
										default: withCtx(() => [createVNode(_sfc_main$5, {
											unit: unref(unit),
											dict: unref(dict),
											onRefresh: unref(refreshUnit)
										}, null, 8, [
											"unit",
											"dict",
											"onRefresh"
										])]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "5"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(_sfc_main$8, {
												unit: unref(unit),
												dict: unref(dict),
												onRefresh: unref(refreshUnit)
											}, null, _parent, _scopeId));
											else return [createVNode(_sfc_main$8, {
												unit: unref(unit),
												dict: unref(dict),
												onRefresh: unref(refreshUnit)
											}, null, 8, [
												"unit",
												"dict",
												"onRefresh"
											])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "7"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(_sfc_main$1, { entities: unref(unit).entities || [] }, null, _parent, _scopeId));
											else return [createVNode(_sfc_main$1, { entities: unref(unit).entities || [] }, null, 8, ["entities"])];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, {
									cols: "12",
									lg: "5"
								}, {
									default: withCtx(() => [createVNode(_sfc_main$8, {
										unit: unref(unit),
										dict: unref(dict),
										onRefresh: unref(refreshUnit)
									}, null, 8, [
										"unit",
										"dict",
										"onRefresh"
									])]),
									_: 1
								}), createVNode(VCol, {
									cols: "12",
									lg: "7"
								}, {
									default: withCtx(() => [createVNode(_sfc_main$1, { entities: unref(unit).entities || [] }, null, 8, ["entities"])]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode(VRow, {
							align: "start",
							dense: ""
						}, {
							default: withCtx(() => [
								createVNode(VCol, {
									cols: "12",
									lg: "4"
								}, {
									default: withCtx(() => [createVNode(_sfc_main$9, {
										unit: unref(unit),
										files: unref(files),
										dict: unref(dict),
										loading: unref(loading),
										onRefresh: unref(refreshUnit)
									}, null, 8, [
										"unit",
										"files",
										"dict",
										"loading",
										"onRefresh"
									])]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									lg: "5"
								}, {
									default: withCtx(() => [createVNode(UnitSendingsCard_default, { unit: unref(unit) }, null, 8, ["unit"])]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									lg: "3"
								}, {
									default: withCtx(() => [createVNode(_sfc_main$4, { stages: unref(unit).stages || [] }, null, 8, ["stages"])]),
									_: 1
								})
							]),
							_: 1
						}),
						createVNode(VRow, null, {
							default: withCtx(() => [
								createVNode(VCol, {
									cols: "12",
									lg: "4"
								}, {
									default: withCtx(() => [createVNode(_sfc_main$6, {
										unit: unref(unit),
										dict: unref(dict),
										onRefresh: unref(refreshUnit)
									}, null, 8, [
										"unit",
										"dict",
										"onRefresh"
									])]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									lg: "4"
								}, {
									default: withCtx(() => [createVNode(_sfc_main$7, {
										unit: unref(unit),
										dict: unref(dict),
										"goods-loading": unref(loading).goods,
										"search-goods": unref(searchGoods),
										onRefresh: unref(refreshUnit)
									}, null, 8, [
										"unit",
										"dict",
										"goods-loading",
										"search-goods",
										"onRefresh"
									])]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									lg: "4"
								}, {
									default: withCtx(() => [createVNode(_sfc_main$5, {
										unit: unref(unit),
										dict: unref(dict),
										onRefresh: unref(refreshUnit)
									}, null, 8, [
										"unit",
										"dict",
										"onRefresh"
									])]),
									_: 1
								})
							]),
							_: 1
						}),
						createVNode(VRow, null, {
							default: withCtx(() => [createVNode(VCol, {
								cols: "12",
								lg: "5"
							}, {
								default: withCtx(() => [createVNode(_sfc_main$8, {
									unit: unref(unit),
									dict: unref(dict),
									onRefresh: unref(refreshUnit)
								}, null, 8, [
									"unit",
									"dict",
									"onRefresh"
								])]),
								_: 1
							}), createVNode(VCol, {
								cols: "12",
								lg: "7"
							}, {
								default: withCtx(() => [createVNode(_sfc_main$1, { entities: unref(unit).entities || [] }, null, 8, ["entities"])]),
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
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Unit.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Unit-DeYaZFSb.js.map