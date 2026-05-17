import { C as VRow, D as VDataTable, F as VCardText, H as VSelect, I as VCardTitle, P as VCard, R as VCardActions, S as VSpacer, T as VContainer, U as VTextField, V as VAutocomplete, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import axios from "axios";
import { Fragment, computed, createBlock, createTextVNode, createVNode, mergeProps, onMounted, openBlock, reactive, ref, renderList, toDisplayString, unref, useSSRContext, withCtx, withKeys } from "vue";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
//#region resources/js/Composables/usePurchases.js
function usePurchases() {
	const items = ref([]);
	const item = ref(null);
	const loading = ref(false);
	const errors = ref({});
	const pagination = ref({
		total: 0,
		per_page: 15,
		current_page: 1,
		last_page: 1
	});
	const fetchPurchases = async (params = {}) => {
		loading.value = true;
		try {
			const { data } = await axios.get("/api/purchases", { params });
			items.value = data.data;
			if (data.meta) pagination.value = {
				total: data.meta.total,
				per_page: data.meta.per_page,
				current_page: data.meta.current_page,
				last_page: data.meta.last_page
			};
		} finally {
			loading.value = false;
		}
	};
	const fetchPurchase = async (id) => {
		loading.value = true;
		try {
			const { data } = await axios.get(`/api/purchases/${id}`);
			item.value = data.data;
			return data.data;
		} finally {
			loading.value = false;
		}
	};
	const createPurchase = async (payload) => {
		errors.value = {};
		const { data } = await axios.post("/api/purchases", payload);
		return data.data;
	};
	const updatePurchase = async (id, payload) => {
		errors.value = {};
		const { data } = await axios.put(`/api/purchases/${id}`, payload);
		return data.data;
	};
	const deletePurchase = async (id) => {
		await axios.delete(`/api/purchases/${id}`);
	};
	return {
		items,
		item,
		loading,
		errors,
		pagination,
		fetchPurchases,
		fetchPurchase,
		createPurchase,
		updatePurchase,
		deletePurchase
	};
}
//#endregion
//#region resources/js/Composables/usePurchaseForm.js
var emptyItem = () => ({
	good_id: null,
	quantity: 1,
	measure_id: null,
	price: 0,
	currency_id: null,
	total: 0
});
function usePurchaseForm() {
	const form = reactive({
		id: null,
		date: "",
		entity_id: null,
		amount: 0,
		items: [emptyItem()]
	});
	const isEdit = computed(() => Boolean(form.id));
	const recalcItem = (item) => {
		item.total = +(Number(item.quantity || 0) * Number(item.price || 0)).toFixed(2);
	};
	const recalcAmount = () => {
		form.amount = +form.items.reduce((sum, item) => sum + Number(item.total || 0), 0).toFixed(2);
	};
	const resetForm = () => {
		form.id = null;
		form.date = "";
		form.entity_id = null;
		form.amount = 0;
		form.items = [emptyItem()];
	};
	const fillForm = (purchase) => {
		form.id = purchase.id;
		form.date = purchase.date;
		form.entity_id = purchase.entity?.id ?? null;
		form.amount = purchase.amount ?? 0;
		form.items = (purchase.items?.length ? purchase.items : [emptyItem()]).map((item) => ({
			good_id: item.good_id,
			quantity: Number(item.quantity ?? 1),
			measure_id: item.measure_id ?? null,
			price: Number(item.price ?? 0),
			currency_id: item.currency_id ?? null,
			total: Number(item.total ?? 0)
		}));
	};
	return {
		form,
		isEdit,
		resetForm,
		fillForm,
		payload: computed(() => ({
			date: form.date,
			entity_id: form.entity_id,
			amount: Number(form.amount || 0),
			items: form.items.filter((item) => item.good_id).map((item) => ({
				good_id: item.good_id,
				quantity: Number(item.quantity || 0),
				measure_id: item.measure_id || null,
				price: Number(item.price || 0),
				currency_id: item.currency_id || null
			}))
		})),
		recalcItem,
		recalcAmount,
		emptyItem
	};
}
//#endregion
//#region resources/js/Pages/Purchases/Purchases.vue
var _sfc_main = {
	__name: "Purchases",
	__ssrInlineRender: true,
	setup(__props) {
		const { items, loading, fetchPurchases, fetchPurchase, createPurchase, updatePurchase, deletePurchase } = usePurchases();
		const { form, isEdit, resetForm, fillForm, payload, recalcItem, recalcAmount, emptyItem } = usePurchaseForm();
		const dialog = ref(false);
		const saving = ref(false);
		const search = ref("");
		const entities = ref([]);
		const goodsOptions = ref([]);
		const measures = ref([]);
		const currencies = ref([]);
		const serverErrors = ref({});
		const headers = [
			{
				title: "ID",
				key: "id"
			},
			{
				title: "Дата",
				key: "date"
			},
			{
				title: "Контрагент",
				key: "entity"
			},
			{
				title: "Сумма",
				key: "amount"
			},
			{
				title: "Позиции",
				key: "items",
				sortable: false
			},
			{
				title: "Действия",
				key: "actions",
				sortable: false
			}
		];
		const extractItems = (response) => {
			if (Array.isArray(response?.data)) return response.data;
			if (Array.isArray(response?.data?.data)) return response.data.data;
			return [];
		};
		const loadPurchases = async () => {
			await fetchPurchases({ search: search.value });
		};
		const loadDictionaries = async () => {
			const [entitiesRes, goodsRes, measuresRes, currenciesRes] = await Promise.all([
				axios.get("/api/entities"),
				axios.get("/api/goods"),
				axios.get("/api/measures"),
				axios.get("/api/currencies")
			]);
			entities.value = extractItems(entitiesRes);
			goodsOptions.value = extractItems(goodsRes);
			measures.value = extractItems(measuresRes);
			currencies.value = extractItems(currenciesRes);
		};
		const openCreate = () => {
			resetForm();
			serverErrors.value = {};
			dialog.value = true;
		};
		const openEdit = async (id) => {
			const purchase = await fetchPurchase(id);
			resetForm();
			fillForm(purchase);
			recalcAmount();
			serverErrors.value = {};
			dialog.value = true;
		};
		const closeDialog = () => {
			dialog.value = false;
			resetForm();
			serverErrors.value = {};
		};
		const addItemRow = () => {
			form.items.push(emptyItem());
		};
		const removeItemRow = (index) => {
			form.items.splice(index, 1);
			if (!form.items.length) form.items.push(emptyItem());
			recalcAmount();
		};
		const onItemChanged = (itemRow) => {
			recalcItem(itemRow);
			recalcAmount();
		};
		const submit = async () => {
			saving.value = true;
			serverErrors.value = {};
			try {
				recalcAmount();
				if (isEdit.value) await updatePurchase(form.id, payload.value);
				else await createPurchase(payload.value);
				closeDialog();
				await loadPurchases();
			} catch (error) {
				if (error.response?.status === 422) serverErrors.value = error.response.data.errors || {};
				else console.error(error);
			} finally {
				saving.value = false;
			}
		};
		const remove = async (id) => {
			await deletePurchase(id);
			await loadPurchases();
		};
		onMounted(async () => {
			await Promise.all([loadPurchases(), loadDictionaries()]);
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="d-flex justify-space-between align-center mb-4"${_scopeId}><h1 class="text-h4"${_scopeId}>Закупки</h1>`);
						_push(ssrRenderComponent(VBtn, {
							color: "primary",
							onClick: openCreate
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Создать закупку `);
								else return [createTextVNode(" Создать закупку ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div>`);
						_push(ssrRenderComponent(VCard, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCardText, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VTextField, {
												modelValue: search.value,
												"onUpdate:modelValue": ($event) => search.value = $event,
												label: "Поиск по контрагенту",
												variant: "outlined",
												clearable: "",
												onKeyup: loadPurchases
											}, null, _parent, _scopeId));
											_push(ssrRenderComponent(VDataTable, {
												headers,
												items: unref(items),
												loading: unref(loading),
												"item-value": "id",
												hover: ""
											}, {
												"item.entity": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(item.entity?.name)}`);
													else return [createTextVNode(toDisplayString(item.entity?.name), 1)];
												}),
												"item.items": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) {
														_push(`<div class="d-flex flex-column ga-1"${_scopeId}><!--[-->`);
														ssrRenderList(item.items, (row, idx) => {
															_push(`<div class="text-body-2"${_scopeId}>${ssrInterpolate(row.good_name)} — ${ssrInterpolate(row.quantity)} × ${ssrInterpolate(row.price)} = ${ssrInterpolate(row.total)}</div>`);
														});
														_push(`<!--]--></div>`);
													} else return [createVNode("div", { class: "d-flex flex-column ga-1" }, [(openBlock(true), createBlock(Fragment, null, renderList(item.items, (row, idx) => {
														return openBlock(), createBlock("div", {
															key: idx,
															class: "text-body-2"
														}, toDisplayString(row.good_name) + " — " + toDisplayString(row.quantity) + " × " + toDisplayString(row.price) + " = " + toDisplayString(row.total), 1);
													}), 128))])];
												}),
												"item.actions": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) {
														_push(`<div class="d-flex ga-2"${_scopeId}>`);
														_push(ssrRenderComponent(VBtn, {
															size: "small",
															variant: "text",
															onClick: ($event) => openEdit(item.id)
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Редактировать `);
																else return [createTextVNode(" Редактировать ")];
															}),
															_: 2
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															size: "small",
															variant: "text",
															color: "error",
															onClick: ($event) => remove(item.id)
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Удалить `);
																else return [createTextVNode(" Удалить ")];
															}),
															_: 2
														}, _parent, _scopeId));
														_push(`</div>`);
													} else return [createVNode("div", { class: "d-flex ga-2" }, [createVNode(VBtn, {
														size: "small",
														variant: "text",
														onClick: ($event) => openEdit(item.id)
													}, {
														default: withCtx(() => [createTextVNode(" Редактировать ")]),
														_: 1
													}, 8, ["onClick"]), createVNode(VBtn, {
														size: "small",
														variant: "text",
														color: "error",
														onClick: ($event) => remove(item.id)
													}, {
														default: withCtx(() => [createTextVNode(" Удалить ")]),
														_: 1
													}, 8, ["onClick"])])];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [createVNode(VTextField, {
											modelValue: search.value,
											"onUpdate:modelValue": ($event) => search.value = $event,
											label: "Поиск по контрагенту",
											variant: "outlined",
											clearable: "",
											onKeyup: withKeys(loadPurchases, ["enter"])
										}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VDataTable, {
											headers,
											items: unref(items),
											loading: unref(loading),
											"item-value": "id",
											hover: ""
										}, {
											"item.entity": withCtx(({ item }) => [createTextVNode(toDisplayString(item.entity?.name), 1)]),
											"item.items": withCtx(({ item }) => [createVNode("div", { class: "d-flex flex-column ga-1" }, [(openBlock(true), createBlock(Fragment, null, renderList(item.items, (row, idx) => {
												return openBlock(), createBlock("div", {
													key: idx,
													class: "text-body-2"
												}, toDisplayString(row.good_name) + " — " + toDisplayString(row.quantity) + " × " + toDisplayString(row.price) + " = " + toDisplayString(row.total), 1);
											}), 128))])]),
											"item.actions": withCtx(({ item }) => [createVNode("div", { class: "d-flex ga-2" }, [createVNode(VBtn, {
												size: "small",
												variant: "text",
												onClick: ($event) => openEdit(item.id)
											}, {
												default: withCtx(() => [createTextVNode(" Редактировать ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												size: "small",
												variant: "text",
												color: "error",
												onClick: ($event) => remove(item.id)
											}, {
												default: withCtx(() => [createTextVNode(" Удалить ")]),
												_: 1
											}, 8, ["onClick"])])]),
											_: 1
										}, 8, ["items", "loading"])];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCardText, null, {
									default: withCtx(() => [createVNode(VTextField, {
										modelValue: search.value,
										"onUpdate:modelValue": ($event) => search.value = $event,
										label: "Поиск по контрагенту",
										variant: "outlined",
										clearable: "",
										onKeyup: withKeys(loadPurchases, ["enter"])
									}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VDataTable, {
										headers,
										items: unref(items),
										loading: unref(loading),
										"item-value": "id",
										hover: ""
									}, {
										"item.entity": withCtx(({ item }) => [createTextVNode(toDisplayString(item.entity?.name), 1)]),
										"item.items": withCtx(({ item }) => [createVNode("div", { class: "d-flex flex-column ga-1" }, [(openBlock(true), createBlock(Fragment, null, renderList(item.items, (row, idx) => {
											return openBlock(), createBlock("div", {
												key: idx,
												class: "text-body-2"
											}, toDisplayString(row.good_name) + " — " + toDisplayString(row.quantity) + " × " + toDisplayString(row.price) + " = " + toDisplayString(row.total), 1);
										}), 128))])]),
										"item.actions": withCtx(({ item }) => [createVNode("div", { class: "d-flex ga-2" }, [createVNode(VBtn, {
											size: "small",
											variant: "text",
											onClick: ($event) => openEdit(item.id)
										}, {
											default: withCtx(() => [createTextVNode(" Редактировать ")]),
											_: 1
										}, 8, ["onClick"]), createVNode(VBtn, {
											size: "small",
											variant: "text",
											color: "error",
											onClick: ($event) => remove(item.id)
										}, {
											default: withCtx(() => [createTextVNode(" Удалить ")]),
											_: 1
										}, 8, ["onClick"])])]),
										_: 1
									}, 8, ["items", "loading"])]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: dialog.value,
							"onUpdate:modelValue": ($event) => dialog.value = $event,
							"max-width": "1200"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(unref(isEdit) ? "Редактирование закупки" : "Создание закупки")}`);
													else return [createTextVNode(toDisplayString(unref(isEdit) ? "Редактирование закупки" : "Создание закупки"), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, null, {
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
																			if (_push) _push(ssrRenderComponent(VTextField, {
																				modelValue: unref(form).date,
																				"onUpdate:modelValue": ($event) => unref(form).date = $event,
																				type: "date",
																				label: "Дата",
																				variant: "outlined",
																				"error-messages": serverErrors.value.date
																			}, null, _parent, _scopeId));
																			else return [createVNode(VTextField, {
																				modelValue: unref(form).date,
																				"onUpdate:modelValue": ($event) => unref(form).date = $event,
																				type: "date",
																				label: "Дата",
																				variant: "outlined",
																				"error-messages": serverErrors.value.date
																			}, null, 8, [
																				"modelValue",
																				"onUpdate:modelValue",
																				"error-messages"
																			])];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VCol, {
																		cols: "12",
																		md: "4"
																	}, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VAutocomplete, {
																				modelValue: unref(form).entity_id,
																				"onUpdate:modelValue": ($event) => unref(form).entity_id = $event,
																				items: entities.value,
																				"item-title": "name",
																				"item-value": "id",
																				label: "Контрагент",
																				variant: "outlined",
																				"error-messages": serverErrors.value.entity_id
																			}, null, _parent, _scopeId));
																			else return [createVNode(VAutocomplete, {
																				modelValue: unref(form).entity_id,
																				"onUpdate:modelValue": ($event) => unref(form).entity_id = $event,
																				items: entities.value,
																				"item-title": "name",
																				"item-value": "id",
																				label: "Контрагент",
																				variant: "outlined",
																				"error-messages": serverErrors.value.entity_id
																			}, null, 8, [
																				"modelValue",
																				"onUpdate:modelValue",
																				"items",
																				"error-messages"
																			])];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VCol, {
																		cols: "12",
																		md: "4"
																	}, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VTextField, {
																				"model-value": unref(form).amount,
																				label: "Итоговая сумма",
																				variant: "outlined",
																				readonly: ""
																			}, null, _parent, _scopeId));
																			else return [createVNode(VTextField, {
																				"model-value": unref(form).amount,
																				label: "Итоговая сумма",
																				variant: "outlined",
																				readonly: ""
																			}, null, 8, ["model-value"])];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																} else return [
																	createVNode(VCol, {
																		cols: "12",
																		md: "4"
																	}, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(form).date,
																			"onUpdate:modelValue": ($event) => unref(form).date = $event,
																			type: "date",
																			label: "Дата",
																			variant: "outlined",
																			"error-messages": serverErrors.value.date
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"error-messages"
																		])]),
																		_: 1
																	}),
																	createVNode(VCol, {
																		cols: "12",
																		md: "4"
																	}, {
																		default: withCtx(() => [createVNode(VAutocomplete, {
																			modelValue: unref(form).entity_id,
																			"onUpdate:modelValue": ($event) => unref(form).entity_id = $event,
																			items: entities.value,
																			"item-title": "name",
																			"item-value": "id",
																			label: "Контрагент",
																			variant: "outlined",
																			"error-messages": serverErrors.value.entity_id
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"items",
																			"error-messages"
																		])]),
																		_: 1
																	}),
																	createVNode(VCol, {
																		cols: "12",
																		md: "4"
																	}, {
																		default: withCtx(() => [createVNode(VTextField, {
																			"model-value": unref(form).amount,
																			label: "Итоговая сумма",
																			variant: "outlined",
																			readonly: ""
																		}, null, 8, ["model-value"])]),
																		_: 1
																	})
																];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(`<div class="d-flex justify-space-between align-center mb-4"${_scopeId}><h3 class="text-h6"${_scopeId}>Позиции закупки</h3>`);
														_push(ssrRenderComponent(VBtn, {
															size: "small",
															onClick: addItemRow
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Добавить позицию`);
																else return [createTextVNode("Добавить позицию")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(`</div><!--[-->`);
														ssrRenderList(unref(form).items, (itemRow, index) => {
															_push(ssrRenderComponent(VRow, {
																key: index,
																class: "align-center mb-2"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VCol, {
																			cols: "12",
																			md: "3"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VAutocomplete, {
																					modelValue: itemRow.good_id,
																					"onUpdate:modelValue": ($event) => itemRow.good_id = $event,
																					items: goodsOptions.value,
																					"item-title": "name",
																					"item-value": "id",
																					label: "Товар",
																					variant: "outlined",
																					"error-messages": serverErrors.value[`items.${index}.good_id`]
																				}, null, _parent, _scopeId));
																				else return [createVNode(VAutocomplete, {
																					modelValue: itemRow.good_id,
																					"onUpdate:modelValue": ($event) => itemRow.good_id = $event,
																					items: goodsOptions.value,
																					"item-title": "name",
																					"item-value": "id",
																					label: "Товар",
																					variant: "outlined",
																					"error-messages": serverErrors.value[`items.${index}.good_id`]
																				}, null, 8, [
																					"modelValue",
																					"onUpdate:modelValue",
																					"items",
																					"error-messages"
																				])];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCol, {
																			cols: "12",
																			md: "1"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VTextField, {
																					modelValue: itemRow.quantity,
																					"onUpdate:modelValue": [($event) => itemRow.quantity = $event, ($event) => onItemChanged(itemRow)],
																					type: "number",
																					label: "Кол-во",
																					variant: "outlined",
																					"error-messages": serverErrors.value[`items.${index}.quantity`]
																				}, null, _parent, _scopeId));
																				else return [createVNode(VTextField, {
																					modelValue: itemRow.quantity,
																					"onUpdate:modelValue": [($event) => itemRow.quantity = $event, ($event) => onItemChanged(itemRow)],
																					type: "number",
																					label: "Кол-во",
																					variant: "outlined",
																					"error-messages": serverErrors.value[`items.${index}.quantity`]
																				}, null, 8, [
																					"modelValue",
																					"onUpdate:modelValue",
																					"error-messages"
																				])];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCol, {
																			cols: "12",
																			md: "2"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VSelect, {
																					modelValue: itemRow.measure_id,
																					"onUpdate:modelValue": ($event) => itemRow.measure_id = $event,
																					items: measures.value,
																					"item-title": "name",
																					"item-value": "id",
																					label: "Ед. изм.",
																					variant: "outlined",
																					"error-messages": serverErrors.value[`items.${index}.measure_id`]
																				}, null, _parent, _scopeId));
																				else return [createVNode(VSelect, {
																					modelValue: itemRow.measure_id,
																					"onUpdate:modelValue": ($event) => itemRow.measure_id = $event,
																					items: measures.value,
																					"item-title": "name",
																					"item-value": "id",
																					label: "Ед. изм.",
																					variant: "outlined",
																					"error-messages": serverErrors.value[`items.${index}.measure_id`]
																				}, null, 8, [
																					"modelValue",
																					"onUpdate:modelValue",
																					"items",
																					"error-messages"
																				])];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCol, {
																			cols: "12",
																			md: "2"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VTextField, {
																					modelValue: itemRow.price,
																					"onUpdate:modelValue": [($event) => itemRow.price = $event, ($event) => onItemChanged(itemRow)],
																					type: "number",
																					label: "Цена",
																					variant: "outlined",
																					"error-messages": serverErrors.value[`items.${index}.price`]
																				}, null, _parent, _scopeId));
																				else return [createVNode(VTextField, {
																					modelValue: itemRow.price,
																					"onUpdate:modelValue": [($event) => itemRow.price = $event, ($event) => onItemChanged(itemRow)],
																					type: "number",
																					label: "Цена",
																					variant: "outlined",
																					"error-messages": serverErrors.value[`items.${index}.price`]
																				}, null, 8, [
																					"modelValue",
																					"onUpdate:modelValue",
																					"error-messages"
																				])];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCol, {
																			cols: "12",
																			md: "2"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VSelect, {
																					modelValue: itemRow.currency_id,
																					"onUpdate:modelValue": ($event) => itemRow.currency_id = $event,
																					items: currencies.value,
																					"item-title": "name",
																					"item-value": "id",
																					label: "Валюта",
																					variant: "outlined",
																					"error-messages": serverErrors.value[`items.${index}.currency_id`]
																				}, null, _parent, _scopeId));
																				else return [createVNode(VSelect, {
																					modelValue: itemRow.currency_id,
																					"onUpdate:modelValue": ($event) => itemRow.currency_id = $event,
																					items: currencies.value,
																					"item-title": "name",
																					"item-value": "id",
																					label: "Валюта",
																					variant: "outlined",
																					"error-messages": serverErrors.value[`items.${index}.currency_id`]
																				}, null, 8, [
																					"modelValue",
																					"onUpdate:modelValue",
																					"items",
																					"error-messages"
																				])];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCol, {
																			cols: "12",
																			md: "1"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VTextField, {
																					"model-value": itemRow.total,
																					label: "Сумма",
																					variant: "outlined",
																					readonly: "",
																					"error-messages": serverErrors.value[`items.${index}.total`]
																				}, null, _parent, _scopeId));
																				else return [createVNode(VTextField, {
																					"model-value": itemRow.total,
																					label: "Сумма",
																					variant: "outlined",
																					readonly: "",
																					"error-messages": serverErrors.value[`items.${index}.total`]
																				}, null, 8, ["model-value", "error-messages"])];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCol, {
																			cols: "12",
																			md: "1"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VBtn, {
																					color: "error",
																					variant: "text",
																					onClick: ($event) => removeItemRow(index)
																				}, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(` Удалить `);
																						else return [createTextVNode(" Удалить ")];
																					}),
																					_: 2
																				}, _parent, _scopeId));
																				else return [createVNode(VBtn, {
																					color: "error",
																					variant: "text",
																					onClick: ($event) => removeItemRow(index)
																				}, {
																					default: withCtx(() => [createTextVNode(" Удалить ")]),
																					_: 1
																				}, 8, ["onClick"])];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																	} else return [
																		createVNode(VCol, {
																			cols: "12",
																			md: "3"
																		}, {
																			default: withCtx(() => [createVNode(VAutocomplete, {
																				modelValue: itemRow.good_id,
																				"onUpdate:modelValue": ($event) => itemRow.good_id = $event,
																				items: goodsOptions.value,
																				"item-title": "name",
																				"item-value": "id",
																				label: "Товар",
																				variant: "outlined",
																				"error-messages": serverErrors.value[`items.${index}.good_id`]
																			}, null, 8, [
																				"modelValue",
																				"onUpdate:modelValue",
																				"items",
																				"error-messages"
																			])]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, {
																			cols: "12",
																			md: "1"
																		}, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: itemRow.quantity,
																				"onUpdate:modelValue": [($event) => itemRow.quantity = $event, ($event) => onItemChanged(itemRow)],
																				type: "number",
																				label: "Кол-во",
																				variant: "outlined",
																				"error-messages": serverErrors.value[`items.${index}.quantity`]
																			}, null, 8, [
																				"modelValue",
																				"onUpdate:modelValue",
																				"error-messages"
																			])]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, {
																			cols: "12",
																			md: "2"
																		}, {
																			default: withCtx(() => [createVNode(VSelect, {
																				modelValue: itemRow.measure_id,
																				"onUpdate:modelValue": ($event) => itemRow.measure_id = $event,
																				items: measures.value,
																				"item-title": "name",
																				"item-value": "id",
																				label: "Ед. изм.",
																				variant: "outlined",
																				"error-messages": serverErrors.value[`items.${index}.measure_id`]
																			}, null, 8, [
																				"modelValue",
																				"onUpdate:modelValue",
																				"items",
																				"error-messages"
																			])]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, {
																			cols: "12",
																			md: "2"
																		}, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: itemRow.price,
																				"onUpdate:modelValue": [($event) => itemRow.price = $event, ($event) => onItemChanged(itemRow)],
																				type: "number",
																				label: "Цена",
																				variant: "outlined",
																				"error-messages": serverErrors.value[`items.${index}.price`]
																			}, null, 8, [
																				"modelValue",
																				"onUpdate:modelValue",
																				"error-messages"
																			])]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, {
																			cols: "12",
																			md: "2"
																		}, {
																			default: withCtx(() => [createVNode(VSelect, {
																				modelValue: itemRow.currency_id,
																				"onUpdate:modelValue": ($event) => itemRow.currency_id = $event,
																				items: currencies.value,
																				"item-title": "name",
																				"item-value": "id",
																				label: "Валюта",
																				variant: "outlined",
																				"error-messages": serverErrors.value[`items.${index}.currency_id`]
																			}, null, 8, [
																				"modelValue",
																				"onUpdate:modelValue",
																				"items",
																				"error-messages"
																			])]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, {
																			cols: "12",
																			md: "1"
																		}, {
																			default: withCtx(() => [createVNode(VTextField, {
																				"model-value": itemRow.total,
																				label: "Сумма",
																				variant: "outlined",
																				readonly: "",
																				"error-messages": serverErrors.value[`items.${index}.total`]
																			}, null, 8, ["model-value", "error-messages"])]),
																			_: 2
																		}, 1024),
																		createVNode(VCol, {
																			cols: "12",
																			md: "1"
																		}, {
																			default: withCtx(() => [createVNode(VBtn, {
																				color: "error",
																				variant: "text",
																				onClick: ($event) => removeItemRow(index)
																			}, {
																				default: withCtx(() => [createTextVNode(" Удалить ")]),
																				_: 1
																			}, 8, ["onClick"])]),
																			_: 2
																		}, 1024)
																	];
																}),
																_: 2
															}, _parent, _scopeId));
														});
														_push(`<!--]-->`);
													} else return [
														createVNode(VRow, null, {
															default: withCtx(() => [
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(form).date,
																		"onUpdate:modelValue": ($event) => unref(form).date = $event,
																		type: "date",
																		label: "Дата",
																		variant: "outlined",
																		"error-messages": serverErrors.value.date
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"error-messages"
																	])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VAutocomplete, {
																		modelValue: unref(form).entity_id,
																		"onUpdate:modelValue": ($event) => unref(form).entity_id = $event,
																		items: entities.value,
																		"item-title": "name",
																		"item-value": "id",
																		label: "Контрагент",
																		variant: "outlined",
																		"error-messages": serverErrors.value.entity_id
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items",
																		"error-messages"
																	])]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	md: "4"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		"model-value": unref(form).amount,
																		label: "Итоговая сумма",
																		variant: "outlined",
																		readonly: ""
																	}, null, 8, ["model-value"])]),
																	_: 1
																})
															]),
															_: 1
														}),
														createVNode("div", { class: "d-flex justify-space-between align-center mb-4" }, [createVNode("h3", { class: "text-h6" }, "Позиции закупки"), createVNode(VBtn, {
															size: "small",
															onClick: addItemRow
														}, {
															default: withCtx(() => [createTextVNode("Добавить позицию")]),
															_: 1
														})]),
														(openBlock(true), createBlock(Fragment, null, renderList(unref(form).items, (itemRow, index) => {
															return openBlock(), createBlock(VRow, {
																key: index,
																class: "align-center mb-2"
															}, {
																default: withCtx(() => [
																	createVNode(VCol, {
																		cols: "12",
																		md: "3"
																	}, {
																		default: withCtx(() => [createVNode(VAutocomplete, {
																			modelValue: itemRow.good_id,
																			"onUpdate:modelValue": ($event) => itemRow.good_id = $event,
																			items: goodsOptions.value,
																			"item-title": "name",
																			"item-value": "id",
																			label: "Товар",
																			variant: "outlined",
																			"error-messages": serverErrors.value[`items.${index}.good_id`]
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"items",
																			"error-messages"
																		])]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, {
																		cols: "12",
																		md: "1"
																	}, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: itemRow.quantity,
																			"onUpdate:modelValue": [($event) => itemRow.quantity = $event, ($event) => onItemChanged(itemRow)],
																			type: "number",
																			label: "Кол-во",
																			variant: "outlined",
																			"error-messages": serverErrors.value[`items.${index}.quantity`]
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"error-messages"
																		])]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, {
																		cols: "12",
																		md: "2"
																	}, {
																		default: withCtx(() => [createVNode(VSelect, {
																			modelValue: itemRow.measure_id,
																			"onUpdate:modelValue": ($event) => itemRow.measure_id = $event,
																			items: measures.value,
																			"item-title": "name",
																			"item-value": "id",
																			label: "Ед. изм.",
																			variant: "outlined",
																			"error-messages": serverErrors.value[`items.${index}.measure_id`]
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"items",
																			"error-messages"
																		])]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, {
																		cols: "12",
																		md: "2"
																	}, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: itemRow.price,
																			"onUpdate:modelValue": [($event) => itemRow.price = $event, ($event) => onItemChanged(itemRow)],
																			type: "number",
																			label: "Цена",
																			variant: "outlined",
																			"error-messages": serverErrors.value[`items.${index}.price`]
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"error-messages"
																		])]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, {
																		cols: "12",
																		md: "2"
																	}, {
																		default: withCtx(() => [createVNode(VSelect, {
																			modelValue: itemRow.currency_id,
																			"onUpdate:modelValue": ($event) => itemRow.currency_id = $event,
																			items: currencies.value,
																			"item-title": "name",
																			"item-value": "id",
																			label: "Валюта",
																			variant: "outlined",
																			"error-messages": serverErrors.value[`items.${index}.currency_id`]
																		}, null, 8, [
																			"modelValue",
																			"onUpdate:modelValue",
																			"items",
																			"error-messages"
																		])]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, {
																		cols: "12",
																		md: "1"
																	}, {
																		default: withCtx(() => [createVNode(VTextField, {
																			"model-value": itemRow.total,
																			label: "Сумма",
																			variant: "outlined",
																			readonly: "",
																			"error-messages": serverErrors.value[`items.${index}.total`]
																		}, null, 8, ["model-value", "error-messages"])]),
																		_: 2
																	}, 1024),
																	createVNode(VCol, {
																		cols: "12",
																		md: "1"
																	}, {
																		default: withCtx(() => [createVNode(VBtn, {
																			color: "error",
																			variant: "text",
																			onClick: ($event) => removeItemRow(index)
																		}, {
																			default: withCtx(() => [createTextVNode(" Удалить ")]),
																			_: 1
																		}, 8, ["onClick"])]),
																		_: 2
																	}, 1024)
																]),
																_: 2
															}, 1024);
														}), 128))
													];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VSpacer, null, null, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: closeDialog
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Отмена`);
																else return [createTextVNode("Отмена")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "primary",
															loading: saving.value,
															onClick: submit
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Сохранить `);
																else return [createTextVNode(" Сохранить ")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [
														createVNode(VSpacer),
														createVNode(VBtn, {
															variant: "text",
															onClick: closeDialog
														}, {
															default: withCtx(() => [createTextVNode("Отмена")]),
															_: 1
														}),
														createVNode(VBtn, {
															color: "primary",
															loading: saving.value,
															onClick: submit
														}, {
															default: withCtx(() => [createTextVNode(" Сохранить ")]),
															_: 1
														}, 8, ["loading"])
													];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode(toDisplayString(unref(isEdit) ? "Редактирование закупки" : "Создание закупки"), 1)]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [
													createVNode(VRow, null, {
														default: withCtx(() => [
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: unref(form).date,
																	"onUpdate:modelValue": ($event) => unref(form).date = $event,
																	type: "date",
																	label: "Дата",
																	variant: "outlined",
																	"error-messages": serverErrors.value.date
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"error-messages"
																])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VAutocomplete, {
																	modelValue: unref(form).entity_id,
																	"onUpdate:modelValue": ($event) => unref(form).entity_id = $event,
																	items: entities.value,
																	"item-title": "name",
																	"item-value": "id",
																	label: "Контрагент",
																	variant: "outlined",
																	"error-messages": serverErrors.value.entity_id
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items",
																	"error-messages"
																])]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																md: "4"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	"model-value": unref(form).amount,
																	label: "Итоговая сумма",
																	variant: "outlined",
																	readonly: ""
																}, null, 8, ["model-value"])]),
																_: 1
															})
														]),
														_: 1
													}),
													createVNode("div", { class: "d-flex justify-space-between align-center mb-4" }, [createVNode("h3", { class: "text-h6" }, "Позиции закупки"), createVNode(VBtn, {
														size: "small",
														onClick: addItemRow
													}, {
														default: withCtx(() => [createTextVNode("Добавить позицию")]),
														_: 1
													})]),
													(openBlock(true), createBlock(Fragment, null, renderList(unref(form).items, (itemRow, index) => {
														return openBlock(), createBlock(VRow, {
															key: index,
															class: "align-center mb-2"
														}, {
															default: withCtx(() => [
																createVNode(VCol, {
																	cols: "12",
																	md: "3"
																}, {
																	default: withCtx(() => [createVNode(VAutocomplete, {
																		modelValue: itemRow.good_id,
																		"onUpdate:modelValue": ($event) => itemRow.good_id = $event,
																		items: goodsOptions.value,
																		"item-title": "name",
																		"item-value": "id",
																		label: "Товар",
																		variant: "outlined",
																		"error-messages": serverErrors.value[`items.${index}.good_id`]
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items",
																		"error-messages"
																	])]),
																	_: 2
																}, 1024),
																createVNode(VCol, {
																	cols: "12",
																	md: "1"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: itemRow.quantity,
																		"onUpdate:modelValue": [($event) => itemRow.quantity = $event, ($event) => onItemChanged(itemRow)],
																		type: "number",
																		label: "Кол-во",
																		variant: "outlined",
																		"error-messages": serverErrors.value[`items.${index}.quantity`]
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"error-messages"
																	])]),
																	_: 2
																}, 1024),
																createVNode(VCol, {
																	cols: "12",
																	md: "2"
																}, {
																	default: withCtx(() => [createVNode(VSelect, {
																		modelValue: itemRow.measure_id,
																		"onUpdate:modelValue": ($event) => itemRow.measure_id = $event,
																		items: measures.value,
																		"item-title": "name",
																		"item-value": "id",
																		label: "Ед. изм.",
																		variant: "outlined",
																		"error-messages": serverErrors.value[`items.${index}.measure_id`]
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items",
																		"error-messages"
																	])]),
																	_: 2
																}, 1024),
																createVNode(VCol, {
																	cols: "12",
																	md: "2"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: itemRow.price,
																		"onUpdate:modelValue": [($event) => itemRow.price = $event, ($event) => onItemChanged(itemRow)],
																		type: "number",
																		label: "Цена",
																		variant: "outlined",
																		"error-messages": serverErrors.value[`items.${index}.price`]
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"error-messages"
																	])]),
																	_: 2
																}, 1024),
																createVNode(VCol, {
																	cols: "12",
																	md: "2"
																}, {
																	default: withCtx(() => [createVNode(VSelect, {
																		modelValue: itemRow.currency_id,
																		"onUpdate:modelValue": ($event) => itemRow.currency_id = $event,
																		items: currencies.value,
																		"item-title": "name",
																		"item-value": "id",
																		label: "Валюта",
																		variant: "outlined",
																		"error-messages": serverErrors.value[`items.${index}.currency_id`]
																	}, null, 8, [
																		"modelValue",
																		"onUpdate:modelValue",
																		"items",
																		"error-messages"
																	])]),
																	_: 2
																}, 1024),
																createVNode(VCol, {
																	cols: "12",
																	md: "1"
																}, {
																	default: withCtx(() => [createVNode(VTextField, {
																		"model-value": itemRow.total,
																		label: "Сумма",
																		variant: "outlined",
																		readonly: "",
																		"error-messages": serverErrors.value[`items.${index}.total`]
																	}, null, 8, ["model-value", "error-messages"])]),
																	_: 2
																}, 1024),
																createVNode(VCol, {
																	cols: "12",
																	md: "1"
																}, {
																	default: withCtx(() => [createVNode(VBtn, {
																		color: "error",
																		variant: "text",
																		onClick: ($event) => removeItemRow(index)
																	}, {
																		default: withCtx(() => [createTextVNode(" Удалить ")]),
																		_: 1
																	}, 8, ["onClick"])]),
																	_: 2
																}, 1024)
															]),
															_: 2
														}, 1024);
													}), 128))
												]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [
													createVNode(VSpacer),
													createVNode(VBtn, {
														variant: "text",
														onClick: closeDialog
													}, {
														default: withCtx(() => [createTextVNode("Отмена")]),
														_: 1
													}),
													createVNode(VBtn, {
														color: "primary",
														loading: saving.value,
														onClick: submit
													}, {
														default: withCtx(() => [createTextVNode(" Сохранить ")]),
														_: 1
													}, 8, ["loading"])
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
											default: withCtx(() => [createTextVNode(toDisplayString(unref(isEdit) ? "Редактирование закупки" : "Создание закупки"), 1)]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [
												createVNode(VRow, null, {
													default: withCtx(() => [
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: unref(form).date,
																"onUpdate:modelValue": ($event) => unref(form).date = $event,
																type: "date",
																label: "Дата",
																variant: "outlined",
																"error-messages": serverErrors.value.date
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"error-messages"
															])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VAutocomplete, {
																modelValue: unref(form).entity_id,
																"onUpdate:modelValue": ($event) => unref(form).entity_id = $event,
																items: entities.value,
																"item-title": "name",
																"item-value": "id",
																label: "Контрагент",
																variant: "outlined",
																"error-messages": serverErrors.value.entity_id
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items",
																"error-messages"
															])]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "4"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																"model-value": unref(form).amount,
																label: "Итоговая сумма",
																variant: "outlined",
																readonly: ""
															}, null, 8, ["model-value"])]),
															_: 1
														})
													]),
													_: 1
												}),
												createVNode("div", { class: "d-flex justify-space-between align-center mb-4" }, [createVNode("h3", { class: "text-h6" }, "Позиции закупки"), createVNode(VBtn, {
													size: "small",
													onClick: addItemRow
												}, {
													default: withCtx(() => [createTextVNode("Добавить позицию")]),
													_: 1
												})]),
												(openBlock(true), createBlock(Fragment, null, renderList(unref(form).items, (itemRow, index) => {
													return openBlock(), createBlock(VRow, {
														key: index,
														class: "align-center mb-2"
													}, {
														default: withCtx(() => [
															createVNode(VCol, {
																cols: "12",
																md: "3"
															}, {
																default: withCtx(() => [createVNode(VAutocomplete, {
																	modelValue: itemRow.good_id,
																	"onUpdate:modelValue": ($event) => itemRow.good_id = $event,
																	items: goodsOptions.value,
																	"item-title": "name",
																	"item-value": "id",
																	label: "Товар",
																	variant: "outlined",
																	"error-messages": serverErrors.value[`items.${index}.good_id`]
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items",
																	"error-messages"
																])]),
																_: 2
															}, 1024),
															createVNode(VCol, {
																cols: "12",
																md: "1"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: itemRow.quantity,
																	"onUpdate:modelValue": [($event) => itemRow.quantity = $event, ($event) => onItemChanged(itemRow)],
																	type: "number",
																	label: "Кол-во",
																	variant: "outlined",
																	"error-messages": serverErrors.value[`items.${index}.quantity`]
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"error-messages"
																])]),
																_: 2
															}, 1024),
															createVNode(VCol, {
																cols: "12",
																md: "2"
															}, {
																default: withCtx(() => [createVNode(VSelect, {
																	modelValue: itemRow.measure_id,
																	"onUpdate:modelValue": ($event) => itemRow.measure_id = $event,
																	items: measures.value,
																	"item-title": "name",
																	"item-value": "id",
																	label: "Ед. изм.",
																	variant: "outlined",
																	"error-messages": serverErrors.value[`items.${index}.measure_id`]
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items",
																	"error-messages"
																])]),
																_: 2
															}, 1024),
															createVNode(VCol, {
																cols: "12",
																md: "2"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	modelValue: itemRow.price,
																	"onUpdate:modelValue": [($event) => itemRow.price = $event, ($event) => onItemChanged(itemRow)],
																	type: "number",
																	label: "Цена",
																	variant: "outlined",
																	"error-messages": serverErrors.value[`items.${index}.price`]
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"error-messages"
																])]),
																_: 2
															}, 1024),
															createVNode(VCol, {
																cols: "12",
																md: "2"
															}, {
																default: withCtx(() => [createVNode(VSelect, {
																	modelValue: itemRow.currency_id,
																	"onUpdate:modelValue": ($event) => itemRow.currency_id = $event,
																	items: currencies.value,
																	"item-title": "name",
																	"item-value": "id",
																	label: "Валюта",
																	variant: "outlined",
																	"error-messages": serverErrors.value[`items.${index}.currency_id`]
																}, null, 8, [
																	"modelValue",
																	"onUpdate:modelValue",
																	"items",
																	"error-messages"
																])]),
																_: 2
															}, 1024),
															createVNode(VCol, {
																cols: "12",
																md: "1"
															}, {
																default: withCtx(() => [createVNode(VTextField, {
																	"model-value": itemRow.total,
																	label: "Сумма",
																	variant: "outlined",
																	readonly: "",
																	"error-messages": serverErrors.value[`items.${index}.total`]
																}, null, 8, ["model-value", "error-messages"])]),
																_: 2
															}, 1024),
															createVNode(VCol, {
																cols: "12",
																md: "1"
															}, {
																default: withCtx(() => [createVNode(VBtn, {
																	color: "error",
																	variant: "text",
																	onClick: ($event) => removeItemRow(index)
																}, {
																	default: withCtx(() => [createTextVNode(" Удалить ")]),
																	_: 1
																}, 8, ["onClick"])]),
																_: 2
															}, 1024)
														]),
														_: 2
													}, 1024);
												}), 128))
											]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [
												createVNode(VSpacer),
												createVNode(VBtn, {
													variant: "text",
													onClick: closeDialog
												}, {
													default: withCtx(() => [createTextVNode("Отмена")]),
													_: 1
												}),
												createVNode(VBtn, {
													color: "primary",
													loading: saving.value,
													onClick: submit
												}, {
													default: withCtx(() => [createTextVNode(" Сохранить ")]),
													_: 1
												}, 8, ["loading"])
											]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode("div", { class: "d-flex justify-space-between align-center mb-4" }, [createVNode("h1", { class: "text-h4" }, "Закупки"), createVNode(VBtn, {
							color: "primary",
							onClick: openCreate
						}, {
							default: withCtx(() => [createTextVNode(" Создать закупку ")]),
							_: 1
						})]),
						createVNode(VCard, null, {
							default: withCtx(() => [createVNode(VCardText, null, {
								default: withCtx(() => [createVNode(VTextField, {
									modelValue: search.value,
									"onUpdate:modelValue": ($event) => search.value = $event,
									label: "Поиск по контрагенту",
									variant: "outlined",
									clearable: "",
									onKeyup: withKeys(loadPurchases, ["enter"])
								}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VDataTable, {
									headers,
									items: unref(items),
									loading: unref(loading),
									"item-value": "id",
									hover: ""
								}, {
									"item.entity": withCtx(({ item }) => [createTextVNode(toDisplayString(item.entity?.name), 1)]),
									"item.items": withCtx(({ item }) => [createVNode("div", { class: "d-flex flex-column ga-1" }, [(openBlock(true), createBlock(Fragment, null, renderList(item.items, (row, idx) => {
										return openBlock(), createBlock("div", {
											key: idx,
											class: "text-body-2"
										}, toDisplayString(row.good_name) + " — " + toDisplayString(row.quantity) + " × " + toDisplayString(row.price) + " = " + toDisplayString(row.total), 1);
									}), 128))])]),
									"item.actions": withCtx(({ item }) => [createVNode("div", { class: "d-flex ga-2" }, [createVNode(VBtn, {
										size: "small",
										variant: "text",
										onClick: ($event) => openEdit(item.id)
									}, {
										default: withCtx(() => [createTextVNode(" Редактировать ")]),
										_: 1
									}, 8, ["onClick"]), createVNode(VBtn, {
										size: "small",
										variant: "text",
										color: "error",
										onClick: ($event) => remove(item.id)
									}, {
										default: withCtx(() => [createTextVNode(" Удалить ")]),
										_: 1
									}, 8, ["onClick"])])]),
									_: 1
								}, 8, ["items", "loading"])]),
								_: 1
							})]),
							_: 1
						}),
						createVNode(VDialog, {
							modelValue: dialog.value,
							"onUpdate:modelValue": ($event) => dialog.value = $event,
							"max-width": "1200"
						}, {
							default: withCtx(() => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode(toDisplayString(unref(isEdit) ? "Редактирование закупки" : "Создание закупки"), 1)]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [
											createVNode(VRow, null, {
												default: withCtx(() => [
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															modelValue: unref(form).date,
															"onUpdate:modelValue": ($event) => unref(form).date = $event,
															type: "date",
															label: "Дата",
															variant: "outlined",
															"error-messages": serverErrors.value.date
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"error-messages"
														])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VAutocomplete, {
															modelValue: unref(form).entity_id,
															"onUpdate:modelValue": ($event) => unref(form).entity_id = $event,
															items: entities.value,
															"item-title": "name",
															"item-value": "id",
															label: "Контрагент",
															variant: "outlined",
															"error-messages": serverErrors.value.entity_id
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items",
															"error-messages"
														])]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "4"
													}, {
														default: withCtx(() => [createVNode(VTextField, {
															"model-value": unref(form).amount,
															label: "Итоговая сумма",
															variant: "outlined",
															readonly: ""
														}, null, 8, ["model-value"])]),
														_: 1
													})
												]),
												_: 1
											}),
											createVNode("div", { class: "d-flex justify-space-between align-center mb-4" }, [createVNode("h3", { class: "text-h6" }, "Позиции закупки"), createVNode(VBtn, {
												size: "small",
												onClick: addItemRow
											}, {
												default: withCtx(() => [createTextVNode("Добавить позицию")]),
												_: 1
											})]),
											(openBlock(true), createBlock(Fragment, null, renderList(unref(form).items, (itemRow, index) => {
												return openBlock(), createBlock(VRow, {
													key: index,
													class: "align-center mb-2"
												}, {
													default: withCtx(() => [
														createVNode(VCol, {
															cols: "12",
															md: "3"
														}, {
															default: withCtx(() => [createVNode(VAutocomplete, {
																modelValue: itemRow.good_id,
																"onUpdate:modelValue": ($event) => itemRow.good_id = $event,
																items: goodsOptions.value,
																"item-title": "name",
																"item-value": "id",
																label: "Товар",
																variant: "outlined",
																"error-messages": serverErrors.value[`items.${index}.good_id`]
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items",
																"error-messages"
															])]),
															_: 2
														}, 1024),
														createVNode(VCol, {
															cols: "12",
															md: "1"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: itemRow.quantity,
																"onUpdate:modelValue": [($event) => itemRow.quantity = $event, ($event) => onItemChanged(itemRow)],
																type: "number",
																label: "Кол-во",
																variant: "outlined",
																"error-messages": serverErrors.value[`items.${index}.quantity`]
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"error-messages"
															])]),
															_: 2
														}, 1024),
														createVNode(VCol, {
															cols: "12",
															md: "2"
														}, {
															default: withCtx(() => [createVNode(VSelect, {
																modelValue: itemRow.measure_id,
																"onUpdate:modelValue": ($event) => itemRow.measure_id = $event,
																items: measures.value,
																"item-title": "name",
																"item-value": "id",
																label: "Ед. изм.",
																variant: "outlined",
																"error-messages": serverErrors.value[`items.${index}.measure_id`]
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items",
																"error-messages"
															])]),
															_: 2
														}, 1024),
														createVNode(VCol, {
															cols: "12",
															md: "2"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																modelValue: itemRow.price,
																"onUpdate:modelValue": [($event) => itemRow.price = $event, ($event) => onItemChanged(itemRow)],
																type: "number",
																label: "Цена",
																variant: "outlined",
																"error-messages": serverErrors.value[`items.${index}.price`]
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"error-messages"
															])]),
															_: 2
														}, 1024),
														createVNode(VCol, {
															cols: "12",
															md: "2"
														}, {
															default: withCtx(() => [createVNode(VSelect, {
																modelValue: itemRow.currency_id,
																"onUpdate:modelValue": ($event) => itemRow.currency_id = $event,
																items: currencies.value,
																"item-title": "name",
																"item-value": "id",
																label: "Валюта",
																variant: "outlined",
																"error-messages": serverErrors.value[`items.${index}.currency_id`]
															}, null, 8, [
																"modelValue",
																"onUpdate:modelValue",
																"items",
																"error-messages"
															])]),
															_: 2
														}, 1024),
														createVNode(VCol, {
															cols: "12",
															md: "1"
														}, {
															default: withCtx(() => [createVNode(VTextField, {
																"model-value": itemRow.total,
																label: "Сумма",
																variant: "outlined",
																readonly: "",
																"error-messages": serverErrors.value[`items.${index}.total`]
															}, null, 8, ["model-value", "error-messages"])]),
															_: 2
														}, 1024),
														createVNode(VCol, {
															cols: "12",
															md: "1"
														}, {
															default: withCtx(() => [createVNode(VBtn, {
																color: "error",
																variant: "text",
																onClick: ($event) => removeItemRow(index)
															}, {
																default: withCtx(() => [createTextVNode(" Удалить ")]),
																_: 1
															}, 8, ["onClick"])]),
															_: 2
														}, 1024)
													]),
													_: 2
												}, 1024);
											}), 128))
										]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [
											createVNode(VSpacer),
											createVNode(VBtn, {
												variant: "text",
												onClick: closeDialog
											}, {
												default: withCtx(() => [createTextVNode("Отмена")]),
												_: 1
											}),
											createVNode(VBtn, {
												color: "primary",
												loading: saving.value,
												onClick: submit
											}, {
												default: withCtx(() => [createTextVNode(" Сохранить ")]),
												_: 1
											}, 8, ["loading"])
										]),
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
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Purchases/Purchases.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Purchases-BwA6yP9D.js.map