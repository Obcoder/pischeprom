import { $ as VAvatar, C as VRow, E as VDataTableServer, F as VCardText, H as VSelect, I as VCardTitle, P as VCard, R as VCardActions, S as VSpacer, T as VContainer, U as VTextField, V as VAutocomplete, et as VAlert, h as VForm, lt as VImg, q as VListItem, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import "./consts-BmbOcoGu.js";
import { t as useCommodities } from "./useCommodities-CDCgIifs.js";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import { computed, createBlock, createCommentVNode, createTextVNode, createVNode, mergeModels, mergeProps, onMounted, openBlock, ref, toDisplayString, unref, useModel, useSSRContext, watch, withCtx, withModifiers } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Components/Dictionaries/Commodities/CommodityFormDialog.vue
var _sfc_main$1 = {
	__name: "CommodityFormDialog",
	__ssrInlineRender: true,
	props: /* @__PURE__ */ mergeModels({
		commodity: {
			type: Object,
			default: null
		},
		saving: {
			type: Boolean,
			default: false
		}
	}, {
		"modelValue": {
			type: Boolean,
			default: false
		},
		"modelModifiers": {}
	}),
	emits: /* @__PURE__ */ mergeModels(["save"], ["update:modelValue"]),
	setup(__props, { emit: __emit }) {
		const model = useModel(__props, "modelValue");
		const props = __props;
		const emit = __emit;
		const form = ref({ name: "" });
		const isEdit = computed(() => Boolean(props.commodity?.id));
		watch(() => props.commodity, (value) => {
			form.value = { name: value?.name || "" };
		}, { immediate: true });
		function submit() {
			emit("save", { ...form.value });
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VDialog, mergeProps({
				modelValue: model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				width: "720"
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VCard, null, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<span${_scopeId}>${ssrInterpolate(isEdit.value ? "Редактировать Commodity" : "Новая Commodity")}</span>`);
											_push(ssrRenderComponent(VBtn, {
												icon: "mdi-close",
												variant: "text",
												density: "compact",
												onClick: ($event) => model.value = false
											}, null, _parent, _scopeId));
										} else return [createVNode("span", null, toDisplayString(isEdit.value ? "Редактировать Commodity" : "Новая Commodity"), 1), createVNode(VBtn, {
											icon: "mdi-close",
											variant: "text",
											density: "compact",
											onClick: ($event) => model.value = false
										}, null, 8, ["onClick"])];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCardText, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VForm, { onSubmit: submit }, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(ssrRenderComponent(VRow, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VTextField, {
																		modelValue: form.value.name,
																		"onUpdate:modelValue": ($event) => form.value.name = $event,
																		label: "Наименование",
																		variant: "outlined",
																		density: "compact",
																		autofocus: "",
																		"hide-details": ""
																	}, null, _parent, _scopeId));
																	else return [createVNode(VTextField, {
																		modelValue: form.value.name,
																		"onUpdate:modelValue": ($event) => form.value.name = $event,
																		label: "Наименование",
																		variant: "outlined",
																		density: "compact",
																		autofocus: "",
																		"hide-details": ""
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VCol, { cols: "12" }, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: form.value.name,
																	"onUpdate:modelValue": ($event) => form.value.name = $event,
																	label: "Наименование",
																	variant: "outlined",
																	density: "compact",
																	autofocus: "",
																	"hide-details": ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
													if (isEdit.value) _push(ssrRenderComponent(VAlert, {
														type: "info",
														variant: "tonal",
														density: "compact",
														class: "mt-4"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` Изображения и ava редактируются на отдельной странице Commodity. `);
															else return [createTextVNode(" Изображения и ava редактируются на отдельной странице Commodity. ")];
														}),
														_: 1
													}, _parent, _scopeId));
													else _push(`<!---->`);
												} else return [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: form.value.name,
															"onUpdate:modelValue": ($event) => form.value.name = $event,
															label: "Наименование",
															variant: "outlined",
															density: "compact",
															autofocus: "",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})]),
													_: 1
												}), isEdit.value ? (openBlock(), createBlock(VAlert, {
													key: 0,
													type: "info",
													variant: "tonal",
													density: "compact",
													class: "mt-4"
												}, {
													default: withCtx(() => [createTextVNode(" Изображения и ava редактируются на отдельной странице Commodity. ")]),
													_: 1
												})) : createCommentVNode("", true)];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VForm, { onSubmit: withModifiers(submit, ["prevent"]) }, {
											default: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
													default: withCtx(() => [createVNode(VTextField, {
														modelValue: form.value.name,
														"onUpdate:modelValue": ($event) => form.value.name = $event,
														label: "Наименование",
														variant: "outlined",
														density: "compact",
														autofocus: "",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})]),
												_: 1
											}), isEdit.value ? (openBlock(), createBlock(VAlert, {
												key: 0,
												type: "info",
												variant: "tonal",
												density: "compact",
												class: "mt-4"
											}, {
												default: withCtx(() => [createTextVNode(" Изображения и ava редактируются на отдельной странице Commodity. ")]),
												_: 1
											})) : createCommentVNode("", true)]),
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
												text: "Отмена",
												variant: "text",
												onClick: ($event) => model.value = false
											}, null, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												text: isEdit.value ? "Сохранить" : "Создать",
												color: "primary",
												variant: "elevated",
												loading: __props.saving,
												onClick: submit
											}, null, _parent, _scopeId));
										} else return [
											createVNode(VSpacer),
											createVNode(VBtn, {
												text: "Отмена",
												variant: "text",
												onClick: ($event) => model.value = false
											}, null, 8, ["onClick"]),
											createVNode(VBtn, {
												text: isEdit.value ? "Сохранить" : "Создать",
												color: "primary",
												variant: "elevated",
												loading: __props.saving,
												onClick: submit
											}, null, 8, ["text", "loading"])
										];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
									default: withCtx(() => [createVNode("span", null, toDisplayString(isEdit.value ? "Редактировать Commodity" : "Новая Commodity"), 1), createVNode(VBtn, {
										icon: "mdi-close",
										variant: "text",
										density: "compact",
										onClick: ($event) => model.value = false
									}, null, 8, ["onClick"])]),
									_: 1
								}),
								createVNode(VCardText, null, {
									default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(submit, ["prevent"]) }, {
										default: withCtx(() => [createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: form.value.name,
													"onUpdate:modelValue": ($event) => form.value.name = $event,
													label: "Наименование",
													variant: "outlined",
													density: "compact",
													autofocus: "",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})]),
											_: 1
										}), isEdit.value ? (openBlock(), createBlock(VAlert, {
											key: 0,
											type: "info",
											variant: "tonal",
											density: "compact",
											class: "mt-4"
										}, {
											default: withCtx(() => [createTextVNode(" Изображения и ava редактируются на отдельной странице Commodity. ")]),
											_: 1
										})) : createCommentVNode("", true)]),
										_: 1
									})]),
									_: 1
								}),
								createVNode(VCardActions, null, {
									default: withCtx(() => [
										createVNode(VSpacer),
										createVNode(VBtn, {
											text: "Отмена",
											variant: "text",
											onClick: ($event) => model.value = false
										}, null, 8, ["onClick"]),
										createVNode(VBtn, {
											text: isEdit.value ? "Сохранить" : "Создать",
											color: "primary",
											variant: "elevated",
											loading: __props.saving,
											onClick: submit
										}, null, 8, ["text", "loading"])
									]),
									_: 1
								})
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VCard, null, {
						default: withCtx(() => [
							createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
								default: withCtx(() => [createVNode("span", null, toDisplayString(isEdit.value ? "Редактировать Commodity" : "Новая Commodity"), 1), createVNode(VBtn, {
									icon: "mdi-close",
									variant: "text",
									density: "compact",
									onClick: ($event) => model.value = false
								}, null, 8, ["onClick"])]),
								_: 1
							}),
							createVNode(VCardText, null, {
								default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(submit, ["prevent"]) }, {
									default: withCtx(() => [createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: form.value.name,
												"onUpdate:modelValue": ($event) => form.value.name = $event,
												label: "Наименование",
												variant: "outlined",
												density: "compact",
												autofocus: "",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})]),
										_: 1
									}), isEdit.value ? (openBlock(), createBlock(VAlert, {
										key: 0,
										type: "info",
										variant: "tonal",
										density: "compact",
										class: "mt-4"
									}, {
										default: withCtx(() => [createTextVNode(" Изображения и ava редактируются на отдельной странице Commodity. ")]),
										_: 1
									})) : createCommentVNode("", true)]),
									_: 1
								})]),
								_: 1
							}),
							createVNode(VCardActions, null, {
								default: withCtx(() => [
									createVNode(VSpacer),
									createVNode(VBtn, {
										text: "Отмена",
										variant: "text",
										onClick: ($event) => model.value = false
									}, null, 8, ["onClick"]),
									createVNode(VBtn, {
										text: isEdit.value ? "Сохранить" : "Создать",
										color: "primary",
										variant: "elevated",
										loading: __props.saving,
										onClick: submit
									}, null, 8, ["text", "loading"])
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
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Dictionaries/Commodities/CommodityFormDialog.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Dictionaries/Commodities/CommoditiesPage.vue
var _sfc_main = {
	__name: "CommoditiesPage",
	__ssrInlineRender: true,
	setup(__props) {
		const { commodities, loadingCommodities, savingCommodity, deletingCommodity, totalItems, options, filters, indexCommodities, storeCommodity, updateCommodity, destroyCommodity, resetCommodityFilters } = useCommodities();
		const checks = ref([]);
		const dialog = ref(false);
		const selectedCommodity = ref(null);
		const avaFilterItems = [
			{
				title: "Все",
				value: "all"
			},
			{
				title: "С ava",
				value: true
			},
			{
				title: "Без ava",
				value: false
			}
		];
		const headers = [
			{
				title: "Ava",
				key: "ava",
				sortable: true,
				width: 80
			},
			{
				title: "ID",
				key: "id",
				sortable: true,
				width: 80
			},
			{
				title: "Наименование",
				key: "name",
				sortable: true
			},
			{
				title: "Checks",
				key: "checks_count",
				sortable: true,
				width: 110
			},
			{
				title: "Media",
				key: "media_count",
				sortable: true,
				width: 110
			},
			{
				title: "Created",
				key: "created_at",
				sortable: true,
				width: 170
			},
			{
				title: "Updated",
				key: "updated_at",
				sortable: true,
				width: 170
			},
			{
				title: "",
				key: "actions",
				sortable: false,
				align: "end",
				width: 150
			}
		];
		const tableHeight = computed(() => "760px");
		let searchTimer = null;
		watch(() => ({ ...filters.value }), () => {
			clearTimeout(searchTimer);
			searchTimer = setTimeout(() => {
				options.value.page = 1;
				indexCommodities();
			}, 350);
		}, { deep: true });
		async function loadChecks() {
			try {
				const response = await axios.get(route("checks.index"));
				checks.value = response.data.data || response.data || [];
			} catch (error) {
				console.error("loadChecks error:", error);
			}
		}
		function openCreate() {
			selectedCommodity.value = null;
			dialog.value = true;
		}
		function openEdit(item) {
			selectedCommodity.value = item;
			dialog.value = true;
		}
		async function saveCommodity(payload) {
			if (selectedCommodity.value?.id) await updateCommodity(selectedCommodity.value.id, payload);
			else await storeCommodity(payload);
			dialog.value = false;
			selectedCommodity.value = null;
			await indexCommodities();
		}
		async function removeCommodity(item) {
			if (!confirm(`Удалить Commodity "${item.name}"?`)) return;
			await destroyCommodity(item.id);
			await indexCommodities();
		}
		function formatDate(value) {
			if (!value) return "—";
			return new Intl.DateTimeFormat("ru-RU", {
				dateStyle: "medium",
				timeStyle: "short"
			}).format(new Date(value));
		}
		function resetFilters() {
			resetCommodityFilters();
			indexCommodities();
		}
		onMounted(async () => {
			await Promise.all([loadChecks(), indexCommodities()]);
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, {
							align: "center",
							class: "mb-2"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										md: "3"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VTextField, {
												modelValue: unref(filters).search,
												"onUpdate:modelValue": ($event) => unref(filters).search = $event,
												label: "Поиск по имени",
												variant: "outlined",
												density: "compact",
												clearable: "",
												"hide-details": ""
											}, null, _parent, _scopeId));
											else return [createVNode(VTextField, {
												modelValue: unref(filters).search,
												"onUpdate:modelValue": ($event) => unref(filters).search = $event,
												label: "Поиск по имени",
												variant: "outlined",
												density: "compact",
												clearable: "",
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
											if (_push) _push(ssrRenderComponent(VAutocomplete, {
												modelValue: unref(filters).check_id,
												"onUpdate:modelValue": ($event) => unref(filters).check_id = $event,
												items: checks.value,
												"item-value": "id",
												"item-title": "id",
												label: "Фильтр по Check",
												variant: "outlined",
												density: "compact",
												clearable: "",
												"hide-details": ""
											}, {
												item: withCtx(({ props, item }, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VListItem, props, {
														title: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` Check #${ssrInterpolate(item.raw.id)}`);
															else return [createTextVNode(" Check #" + toDisplayString(item.raw.id), 1)];
														}),
														subtitle: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(`${ssrInterpolate(item.raw.date)} — ${ssrInterpolate(item.raw.entity?.name || "без entity")}`);
															else return [createTextVNode(toDisplayString(item.raw.date) + " — " + toDisplayString(item.raw.entity?.name || "без entity"), 1)];
														}),
														_: 2
													}, _parent, _scopeId));
													else return [createVNode(VListItem, props, {
														title: withCtx(() => [createTextVNode(" Check #" + toDisplayString(item.raw.id), 1)]),
														subtitle: withCtx(() => [createTextVNode(toDisplayString(item.raw.date) + " — " + toDisplayString(item.raw.entity?.name || "без entity"), 1)]),
														_: 2
													}, 1040)];
												}),
												selection: withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) _push(` Check #${ssrInterpolate(item.raw.id)}`);
													else return [createTextVNode(" Check #" + toDisplayString(item.raw.id), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VAutocomplete, {
												modelValue: unref(filters).check_id,
												"onUpdate:modelValue": ($event) => unref(filters).check_id = $event,
												items: checks.value,
												"item-value": "id",
												"item-title": "id",
												label: "Фильтр по Check",
												variant: "outlined",
												density: "compact",
												clearable: "",
												"hide-details": ""
											}, {
												item: withCtx(({ props, item }) => [createVNode(VListItem, props, {
													title: withCtx(() => [createTextVNode(" Check #" + toDisplayString(item.raw.id), 1)]),
													subtitle: withCtx(() => [createTextVNode(toDisplayString(item.raw.date) + " — " + toDisplayString(item.raw.entity?.name || "без entity"), 1)]),
													_: 2
												}, 1040)]),
												selection: withCtx(({ item }) => [createTextVNode(" Check #" + toDisplayString(item.raw.id), 1)]),
												_: 1
											}, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items"
											])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										md: "2"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VSelect, {
												modelValue: unref(filters).has_ava,
												"onUpdate:modelValue": ($event) => unref(filters).has_ava = $event,
												items: avaFilterItems,
												label: "Ava",
												variant: "outlined",
												density: "compact",
												"hide-details": ""
											}, null, _parent, _scopeId));
											else return [createVNode(VSelect, {
												modelValue: unref(filters).has_ava,
												"onUpdate:modelValue": ($event) => unref(filters).has_ava = $event,
												items: avaFilterItems,
												label: "Ava",
												variant: "outlined",
												density: "compact",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										md: "2"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VTextField, {
												modelValue: unref(filters).created_from,
												"onUpdate:modelValue": ($event) => unref(filters).created_from = $event,
												label: "Created from",
												type: "date",
												variant: "outlined",
												density: "compact",
												"hide-details": "",
												clearable: ""
											}, null, _parent, _scopeId));
											else return [createVNode(VTextField, {
												modelValue: unref(filters).created_from,
												"onUpdate:modelValue": ($event) => unref(filters).created_from = $event,
												label: "Created from",
												type: "date",
												variant: "outlined",
												density: "compact",
												"hide-details": "",
												clearable: ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										md: "2"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VTextField, {
												modelValue: unref(filters).created_to,
												"onUpdate:modelValue": ($event) => unref(filters).created_to = $event,
												label: "Created to",
												type: "date",
												variant: "outlined",
												density: "compact",
												"hide-details": "",
												clearable: ""
											}, null, _parent, _scopeId));
											else return [createVNode(VTextField, {
												modelValue: unref(filters).created_to,
												"onUpdate:modelValue": ($event) => unref(filters).created_to = $event,
												label: "Created to",
												type: "date",
												variant: "outlined",
												density: "compact",
												"hide-details": "",
												clearable: ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VCol, {
										cols: "12",
										md: "3"
									}, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: unref(filters).search,
											"onUpdate:modelValue": ($event) => unref(filters).search = $event,
											label: "Поиск по имени",
											variant: "outlined",
											density: "compact",
											clearable: "",
											"hide-details": ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										md: "3"
									}, {
										default: withCtx(() => [createVNode(VAutocomplete, {
											modelValue: unref(filters).check_id,
											"onUpdate:modelValue": ($event) => unref(filters).check_id = $event,
											items: checks.value,
											"item-value": "id",
											"item-title": "id",
											label: "Фильтр по Check",
											variant: "outlined",
											density: "compact",
											clearable: "",
											"hide-details": ""
										}, {
											item: withCtx(({ props, item }) => [createVNode(VListItem, props, {
												title: withCtx(() => [createTextVNode(" Check #" + toDisplayString(item.raw.id), 1)]),
												subtitle: withCtx(() => [createTextVNode(toDisplayString(item.raw.date) + " — " + toDisplayString(item.raw.entity?.name || "без entity"), 1)]),
												_: 2
											}, 1040)]),
											selection: withCtx(({ item }) => [createTextVNode(" Check #" + toDisplayString(item.raw.id), 1)]),
											_: 1
										}, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"items"
										])]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										md: "2"
									}, {
										default: withCtx(() => [createVNode(VSelect, {
											modelValue: unref(filters).has_ava,
											"onUpdate:modelValue": ($event) => unref(filters).has_ava = $event,
											items: avaFilterItems,
											label: "Ava",
											variant: "outlined",
											density: "compact",
											"hide-details": ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										md: "2"
									}, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: unref(filters).created_from,
											"onUpdate:modelValue": ($event) => unref(filters).created_from = $event,
											label: "Created from",
											type: "date",
											variant: "outlined",
											density: "compact",
											"hide-details": "",
											clearable: ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										md: "2"
									}, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: unref(filters).created_to,
											"onUpdate:modelValue": ($event) => unref(filters).created_to = $event,
											label: "Created to",
											type: "date",
											variant: "outlined",
											density: "compact",
											"hide-details": "",
											clearable: ""
										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, {
							align: "center",
							class: "mb-2"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`<div class="text-caption text-grey"${_scopeId}> Всего: ${ssrInterpolate(unref(totalItems))}</div>`);
											else return [createVNode("div", { class: "text-caption text-grey" }, " Всего: " + toDisplayString(unref(totalItems)), 1)];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "auto" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VBtn, {
												text: "Сбросить",
												variant: "tonal",
												density: "compact",
												onClick: resetFilters
											}, null, _parent, _scopeId));
											else return [createVNode(VBtn, {
												text: "Сбросить",
												variant: "tonal",
												density: "compact",
												onClick: resetFilters
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "auto" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VBtn, {
												text: "+ Commodity",
												color: "primary",
												variant: "elevated",
												density: "compact",
												onClick: openCreate
											}, null, _parent, _scopeId));
											else return [createVNode(VBtn, {
												text: "+ Commodity",
												color: "primary",
												variant: "elevated",
												density: "compact",
												onClick: openCreate
											})];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VCol, null, {
										default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Всего: " + toDisplayString(unref(totalItems)), 1)]),
										_: 1
									}),
									createVNode(VCol, { cols: "auto" }, {
										default: withCtx(() => [createVNode(VBtn, {
											text: "Сбросить",
											variant: "tonal",
											density: "compact",
											onClick: resetFilters
										})]),
										_: 1
									}),
									createVNode(VCol, { cols: "auto" }, {
										default: withCtx(() => [createVNode(VBtn, {
											text: "+ Commodity",
											color: "primary",
											variant: "elevated",
											density: "compact",
											onClick: openCreate
										})]),
										_: 1
									})
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDataTableServer, {
							page: unref(options).page,
							"onUpdate:page": ($event) => unref(options).page = $event,
							"items-per-page": unref(options).itemsPerPage,
							"onUpdate:itemsPerPage": ($event) => unref(options).itemsPerPage = $event,
							"sort-by": unref(options).sortBy,
							"onUpdate:sortBy": ($event) => unref(options).sortBy = $event,
							headers,
							items: unref(commodities),
							"items-length": unref(totalItems),
							loading: unref(loadingCommodities),
							height: tableHeight.value,
							"fixed-header": "",
							hover: "",
							density: "compact",
							class: "border rounded",
							"items-per-page-options": [
								50,
								100,
								200,
								500
							],
							"onUpdate:options": unref(indexCommodities)
						}, {
							"item.ava": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VAvatar, {
									size: "42",
									rounded: "lg"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VImg, {
											src: item.ava_url || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
											cover: ""
										}, null, _parent, _scopeId));
										else return [createVNode(VImg, {
											src: item.ava_url || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
											cover: ""
										}, null, 8, ["src"])];
									}),
									_: 2
								}, _parent, _scopeId));
								else return [createVNode(VAvatar, {
									size: "42",
									rounded: "lg"
								}, {
									default: withCtx(() => [createVNode(VImg, {
										src: item.ava_url || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
										cover: ""
									}, null, 8, ["src"])]),
									_: 2
								}, 1024)];
							}),
							"item.name": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(unref(Link), {
									href: unref(route)("Ameise.commodity.show", item.id),
									class: "text-primary font-weight-medium"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(`${ssrInterpolate(item.name)}`);
										else return [createTextVNode(toDisplayString(item.name), 1)];
									}),
									_: 2
								}, _parent, _scopeId));
								else return [createVNode(unref(Link), {
									href: unref(route)("Ameise.commodity.show", item.id),
									class: "text-primary font-weight-medium"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
									_: 2
								}, 1032, ["href"])];
							}),
							"item.created_at": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(`<span class="text-caption"${_scopeId}>${ssrInterpolate(formatDate(item.created_at))}</span>`);
								else return [createVNode("span", { class: "text-caption" }, toDisplayString(formatDate(item.created_at)), 1)];
							}),
							"item.updated_at": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) _push(`<span class="text-caption"${_scopeId}>${ssrInterpolate(formatDate(item.updated_at))}</span>`);
								else return [createVNode("span", { class: "text-caption" }, toDisplayString(formatDate(item.updated_at)), 1)];
							}),
							"item.actions": withCtx(({ item }, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VBtn, {
										icon: "mdi-pencil",
										variant: "text",
										density: "compact",
										onClick: ($event) => openEdit(item)
									}, null, _parent, _scopeId));
									_push(ssrRenderComponent(VBtn, {
										icon: "mdi-delete",
										variant: "text",
										density: "compact",
										color: "error",
										loading: unref(deletingCommodity),
										onClick: ($event) => removeCommodity(item)
									}, null, _parent, _scopeId));
								} else return [createVNode(VBtn, {
									icon: "mdi-pencil",
									variant: "text",
									density: "compact",
									onClick: ($event) => openEdit(item)
								}, null, 8, ["onClick"]), createVNode(VBtn, {
									icon: "mdi-delete",
									variant: "text",
									density: "compact",
									color: "error",
									loading: unref(deletingCommodity),
									onClick: ($event) => removeCommodity(item)
								}, null, 8, ["loading", "onClick"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(_sfc_main$1, {
							modelValue: dialog.value,
							"onUpdate:modelValue": ($event) => dialog.value = $event,
							commodity: selectedCommodity.value,
							saving: unref(savingCommodity),
							onSave: saveCommodity
						}, null, _parent, _scopeId));
					} else return [
						createVNode(VRow, {
							align: "center",
							class: "mb-2"
						}, {
							default: withCtx(() => [
								createVNode(VCol, {
									cols: "12",
									md: "3"
								}, {
									default: withCtx(() => [createVNode(VTextField, {
										modelValue: unref(filters).search,
										"onUpdate:modelValue": ($event) => unref(filters).search = $event,
										label: "Поиск по имени",
										variant: "outlined",
										density: "compact",
										clearable: "",
										"hide-details": ""
									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									md: "3"
								}, {
									default: withCtx(() => [createVNode(VAutocomplete, {
										modelValue: unref(filters).check_id,
										"onUpdate:modelValue": ($event) => unref(filters).check_id = $event,
										items: checks.value,
										"item-value": "id",
										"item-title": "id",
										label: "Фильтр по Check",
										variant: "outlined",
										density: "compact",
										clearable: "",
										"hide-details": ""
									}, {
										item: withCtx(({ props, item }) => [createVNode(VListItem, props, {
											title: withCtx(() => [createTextVNode(" Check #" + toDisplayString(item.raw.id), 1)]),
											subtitle: withCtx(() => [createTextVNode(toDisplayString(item.raw.date) + " — " + toDisplayString(item.raw.entity?.name || "без entity"), 1)]),
											_: 2
										}, 1040)]),
										selection: withCtx(({ item }) => [createTextVNode(" Check #" + toDisplayString(item.raw.id), 1)]),
										_: 1
									}, 8, [
										"modelValue",
										"onUpdate:modelValue",
										"items"
									])]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									md: "2"
								}, {
									default: withCtx(() => [createVNode(VSelect, {
										modelValue: unref(filters).has_ava,
										"onUpdate:modelValue": ($event) => unref(filters).has_ava = $event,
										items: avaFilterItems,
										label: "Ava",
										variant: "outlined",
										density: "compact",
										"hide-details": ""
									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									md: "2"
								}, {
									default: withCtx(() => [createVNode(VTextField, {
										modelValue: unref(filters).created_from,
										"onUpdate:modelValue": ($event) => unref(filters).created_from = $event,
										label: "Created from",
										type: "date",
										variant: "outlined",
										density: "compact",
										"hide-details": "",
										clearable: ""
									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									md: "2"
								}, {
									default: withCtx(() => [createVNode(VTextField, {
										modelValue: unref(filters).created_to,
										"onUpdate:modelValue": ($event) => unref(filters).created_to = $event,
										label: "Created to",
										type: "date",
										variant: "outlined",
										density: "compact",
										"hide-details": "",
										clearable: ""
									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
									_: 1
								})
							]),
							_: 1
						}),
						createVNode(VRow, {
							align: "center",
							class: "mb-2"
						}, {
							default: withCtx(() => [
								createVNode(VCol, null, {
									default: withCtx(() => [createVNode("div", { class: "text-caption text-grey" }, " Всего: " + toDisplayString(unref(totalItems)), 1)]),
									_: 1
								}),
								createVNode(VCol, { cols: "auto" }, {
									default: withCtx(() => [createVNode(VBtn, {
										text: "Сбросить",
										variant: "tonal",
										density: "compact",
										onClick: resetFilters
									})]),
									_: 1
								}),
								createVNode(VCol, { cols: "auto" }, {
									default: withCtx(() => [createVNode(VBtn, {
										text: "+ Commodity",
										color: "primary",
										variant: "elevated",
										density: "compact",
										onClick: openCreate
									})]),
									_: 1
								})
							]),
							_: 1
						}),
						createVNode(VDataTableServer, {
							page: unref(options).page,
							"onUpdate:page": ($event) => unref(options).page = $event,
							"items-per-page": unref(options).itemsPerPage,
							"onUpdate:itemsPerPage": ($event) => unref(options).itemsPerPage = $event,
							"sort-by": unref(options).sortBy,
							"onUpdate:sortBy": ($event) => unref(options).sortBy = $event,
							headers,
							items: unref(commodities),
							"items-length": unref(totalItems),
							loading: unref(loadingCommodities),
							height: tableHeight.value,
							"fixed-header": "",
							hover: "",
							density: "compact",
							class: "border rounded",
							"items-per-page-options": [
								50,
								100,
								200,
								500
							],
							"onUpdate:options": unref(indexCommodities)
						}, {
							"item.ava": withCtx(({ item }) => [createVNode(VAvatar, {
								size: "42",
								rounded: "lg"
							}, {
								default: withCtx(() => [createVNode(VImg, {
									src: item.ava_url || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"),
									cover: ""
								}, null, 8, ["src"])]),
								_: 2
							}, 1024)]),
							"item.name": withCtx(({ item }) => [createVNode(unref(Link), {
								href: unref(route)("Ameise.commodity.show", item.id),
								class: "text-primary font-weight-medium"
							}, {
								default: withCtx(() => [createTextVNode(toDisplayString(item.name), 1)]),
								_: 2
							}, 1032, ["href"])]),
							"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-caption" }, toDisplayString(formatDate(item.created_at)), 1)]),
							"item.updated_at": withCtx(({ item }) => [createVNode("span", { class: "text-caption" }, toDisplayString(formatDate(item.updated_at)), 1)]),
							"item.actions": withCtx(({ item }) => [createVNode(VBtn, {
								icon: "mdi-pencil",
								variant: "text",
								density: "compact",
								onClick: ($event) => openEdit(item)
							}, null, 8, ["onClick"]), createVNode(VBtn, {
								icon: "mdi-delete",
								variant: "text",
								density: "compact",
								color: "error",
								loading: unref(deletingCommodity),
								onClick: ($event) => removeCommodity(item)
							}, null, 8, ["loading", "onClick"])]),
							_: 1
						}, 8, [
							"page",
							"onUpdate:page",
							"items-per-page",
							"onUpdate:itemsPerPage",
							"sort-by",
							"onUpdate:sortBy",
							"items",
							"items-length",
							"loading",
							"height",
							"onUpdate:options"
						]),
						createVNode(_sfc_main$1, {
							modelValue: dialog.value,
							"onUpdate:modelValue": ($event) => dialog.value = $event,
							commodity: selectedCommodity.value,
							saving: unref(savingCommodity),
							onSave: saveCommodity
						}, null, 8, [
							"modelValue",
							"onUpdate:modelValue",
							"commodity",
							"saving"
						])
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Dictionaries/Commodities/CommoditiesPage.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as t };

//# sourceMappingURL=CommoditiesPage-CXnWj9Z8.js.map