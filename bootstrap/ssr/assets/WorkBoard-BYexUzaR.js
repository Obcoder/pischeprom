import { C as VRow, F as VCardText, G as VList, H as VSelect, I as VCardTitle, J as VListItemTitle, L as VCardSubtitle, P as VCard, R as VCardActions, S as VSpacer, T as VContainer, U as VTextField, V as VAutocomplete, W as VMenu, X as VChip, et as VAlert, i as VTextarea, it as VProgressLinear, l as VSwitch, ot as VIcon, q as VListItem, rt as VBtn, u as VSnackbar, w as VCol, z as VDialog } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-m8ed4N5m.js";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, mergeProps, onMounted, openBlock, reactive, ref, renderList, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList, ssrRenderStyle } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
import { VueDraggableNext } from "vue-draggable-next";
//#region resources/js/Pages/Ameise/WorkBoard.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "WorkBoard",
	__ssrInlineRender: true,
	setup(__props) {
		const loading = ref(false);
		const saving = ref(false);
		const selectedPipelineId = ref(null);
		const unitOptions = ref([]);
		const unitSearch = ref("");
		const board = reactive({
			pipelines: [],
			pipeline: null
		});
		const snackbar = reactive({
			show: false,
			text: "",
			color: "success"
		});
		const pipelineDialog = ref(false);
		const stageDialog = ref(false);
		const cardDialog = ref(false);
		const editingPipeline = ref(null);
		const editingStage = ref(null);
		const editingCard = ref(null);
		const pipelineForm = reactive({
			name: "",
			description: "",
			is_active: true,
			sort_order: null
		});
		const stageForm = reactive({
			name: "",
			color: "#800000",
			description: "",
			sort_order: null
		});
		const cardForm = reactive({
			unit_id: null,
			supplier_pipeline_stage_id: null,
			title: "",
			notes: "",
			next_contact_at: ""
		});
		const currentPipeline = computed(() => board.pipeline);
		const stages = computed(() => currentPipeline.value?.stages || []);
		const pipelineItems = computed(() => board.pipelines || []);
		const hasPipeline = computed(() => Boolean(currentPipeline.value?.id));
		const totalCards = computed(() => stages.value.reduce((sum, stage) => sum + (stage.cards?.length || 0), 0));
		useHead({
			title: "Supplier Work Board - Ameise",
			meta: [{
				name: "description",
				content: "Воронки и рабочая доска поставщиков Ameise"
			}]
		});
		function notify(text, color = "success") {
			snackbar.text = text;
			snackbar.color = color;
			snackbar.show = true;
		}
		function normalizePipeline(pipeline) {
			if (!pipeline) return null;
			return {
				...pipeline,
				stages: (pipeline.stages || []).map((stage) => ({
					...stage,
					cards: stage.cards || []
				}))
			};
		}
		async function fetchBoard(pipelineId = selectedPipelineId.value) {
			loading.value = true;
			try {
				const response = await axios.get("/api/supplier-work/board", { params: pipelineId ? { pipeline_id: pipelineId } : {} });
				board.pipelines = response.data.pipelines || [];
				board.pipeline = normalizePipeline(response.data.pipeline);
				selectedPipelineId.value = board.pipeline?.id || board.pipelines[0]?.id || null;
			} catch (error) {
				console.error(error);
				notify("Не удалось загрузить доску поставщиков.", "error");
			} finally {
				loading.value = false;
			}
		}
		async function fetchUnitOptions(search = unitSearch.value) {
			try {
				unitOptions.value = (await axios.get("/api/supplier-work/unit-options", { params: {
					search,
					limit: 60
				} })).data || [];
			} catch (error) {
				console.error(error);
				notify("Не удалось загрузить Units.", "error");
			}
		}
		function resetPipelineForm() {
			editingPipeline.value = null;
			pipelineForm.name = "";
			pipelineForm.description = "";
			pipelineForm.is_active = true;
			pipelineForm.sort_order = null;
		}
		function openPipelineDialog(pipeline = null) {
			resetPipelineForm();
			if (pipeline) {
				editingPipeline.value = pipeline;
				pipelineForm.name = pipeline.name || "";
				pipelineForm.description = pipeline.description || "";
				pipelineForm.is_active = Boolean(pipeline.is_active);
				pipelineForm.sort_order = pipeline.sort_order ?? null;
			}
			pipelineDialog.value = true;
		}
		async function savePipeline() {
			saving.value = true;
			const payload = {
				name: pipelineForm.name,
				description: pipelineForm.description || null,
				is_active: pipelineForm.is_active,
				sort_order: pipelineForm.sort_order ?? void 0
			};
			try {
				if (editingPipeline.value?.id) {
					await axios.patch(`/api/supplier-work/pipelines/${editingPipeline.value.id}`, payload);
					notify("Воронка обновлена.");
				} else {
					selectedPipelineId.value = (await axios.post("/api/supplier-work/pipelines", payload)).data.id;
					notify("Воронка создана.");
				}
				pipelineDialog.value = false;
				await fetchBoard(selectedPipelineId.value);
			} catch (error) {
				console.error(error);
				notify(error.response?.data?.message || "Не удалось сохранить воронку.", "error");
			} finally {
				saving.value = false;
			}
		}
		async function deletePipeline(pipeline) {
			if (!pipeline?.id || !window.confirm(`Удалить воронку "${pipeline.name}" вместе со стадиями и карточками?`)) return;
			saving.value = true;
			try {
				await axios.delete(`/api/supplier-work/pipelines/${pipeline.id}`);
				selectedPipelineId.value = null;
				notify("Воронка удалена.");
				await fetchBoard();
			} catch (error) {
				console.error(error);
				notify("Не удалось удалить воронку.", "error");
			} finally {
				saving.value = false;
			}
		}
		function resetStageForm() {
			editingStage.value = null;
			stageForm.name = "";
			stageForm.color = "#800000";
			stageForm.description = "";
			stageForm.sort_order = null;
		}
		function openStageDialog(stage = null) {
			resetStageForm();
			if (stage) {
				editingStage.value = stage;
				stageForm.name = stage.name || "";
				stageForm.color = stage.color || "#800000";
				stageForm.description = stage.description || "";
				stageForm.sort_order = stage.sort_order ?? null;
			}
			stageDialog.value = true;
		}
		async function saveStage() {
			if (!currentPipeline.value?.id) {
				notify("Сначала создайте воронку.", "error");
				return;
			}
			saving.value = true;
			const payload = {
				name: stageForm.name,
				color: stageForm.color || null,
				description: stageForm.description || null,
				sort_order: stageForm.sort_order ?? void 0
			};
			try {
				if (editingStage.value?.id) {
					await axios.patch(`/api/supplier-work/stages/${editingStage.value.id}`, payload);
					notify("Стадия обновлена.");
				} else {
					await axios.post(`/api/supplier-work/pipelines/${currentPipeline.value.id}/stages`, payload);
					notify("Стадия создана.");
				}
				stageDialog.value = false;
				await fetchBoard(currentPipeline.value.id);
			} catch (error) {
				console.error(error);
				notify(error.response?.data?.message || "Не удалось сохранить стадию.", "error");
			} finally {
				saving.value = false;
			}
		}
		async function deleteStage(stage) {
			if (!stage?.id || !window.confirm(`Удалить стадию "${stage.name}"? Карточки будут перенесены в первую доступную стадию.`)) return;
			saving.value = true;
			try {
				await axios.delete(`/api/supplier-work/stages/${stage.id}`);
				notify("Стадия удалена.");
				await fetchBoard(currentPipeline.value.id);
			} catch (error) {
				console.error(error);
				notify("Не удалось удалить стадию.", "error");
			} finally {
				saving.value = false;
			}
		}
		async function reorderStages(stage, direction) {
			const index = stages.value.findIndex((item) => item.id === stage.id);
			const nextIndex = index + direction;
			if (index < 0 || nextIndex < 0 || nextIndex >= stages.value.length) return;
			const ids = stages.value.map((item) => item.id);
			const temp = ids[index];
			ids[index] = ids[nextIndex];
			ids[nextIndex] = temp;
			saving.value = true;
			try {
				await axios.patch(`/api/supplier-work/pipelines/${currentPipeline.value.id}/stages/reorder`, { stage_ids: ids });
				await fetchBoard(currentPipeline.value.id);
			} catch (error) {
				console.error(error);
				notify("Не удалось изменить порядок стадий.", "error");
			} finally {
				saving.value = false;
			}
		}
		function resetCardForm() {
			editingCard.value = null;
			cardForm.unit_id = null;
			cardForm.supplier_pipeline_stage_id = stages.value[0]?.id || null;
			cardForm.title = "";
			cardForm.notes = "";
			cardForm.next_contact_at = "";
		}
		async function openCardDialog(stage = null, card = null) {
			resetCardForm();
			await fetchUnitOptions();
			if (stage?.id) cardForm.supplier_pipeline_stage_id = stage.id;
			if (card) {
				editingCard.value = card;
				cardForm.unit_id = card.unit_id;
				cardForm.supplier_pipeline_stage_id = card.supplier_pipeline_stage_id;
				cardForm.title = card.title || "";
				cardForm.notes = card.notes || "";
				cardForm.next_contact_at = card.next_contact_at ? String(card.next_contact_at).slice(0, 16) : "";
				if (card.unit && !unitOptions.value.some((unit) => unit.id === card.unit.id)) unitOptions.value = [card.unit, ...unitOptions.value];
			}
			cardDialog.value = true;
		}
		async function saveCard() {
			if (!currentPipeline.value?.id) {
				notify("Сначала создайте воронку.", "error");
				return;
			}
			saving.value = true;
			const payload = {
				unit_id: cardForm.unit_id,
				supplier_pipeline_stage_id: cardForm.supplier_pipeline_stage_id,
				title: cardForm.title || null,
				notes: cardForm.notes || null,
				next_contact_at: cardForm.next_contact_at || null
			};
			try {
				if (editingCard.value?.id) {
					await axios.patch(`/api/supplier-work/cards/${editingCard.value.id}`, payload);
					notify("Карточка обновлена.");
				} else {
					await axios.post(`/api/supplier-work/pipelines/${currentPipeline.value.id}/cards`, payload);
					notify("Поставщик добавлен в воронку.");
				}
				cardDialog.value = false;
				await fetchBoard(currentPipeline.value.id);
			} catch (error) {
				console.error(error);
				notify(error.response?.data?.message || "Не удалось сохранить карточку.", "error");
			} finally {
				saving.value = false;
			}
		}
		async function deleteCard(card) {
			const title = card.title || card.unit?.name || "карточку";
			if (!card?.id || !window.confirm(`Удалить "${title}" из воронки?`)) return;
			saving.value = true;
			try {
				await axios.delete(`/api/supplier-work/cards/${card.id}`);
				notify("Карточка удалена.");
				await fetchBoard(currentPipeline.value.id);
			} catch (error) {
				console.error(error);
				notify("Не удалось удалить карточку.", "error");
			} finally {
				saving.value = false;
			}
		}
		async function onCardChange(stage, event) {
			const changedCard = event.added?.element || event.moved?.element;
			if (!changedCard?.id) return;
			saving.value = true;
			try {
				await axios.patch(`/api/supplier-work/cards/${changedCard.id}/move`, {
					supplier_pipeline_stage_id: stage.id,
					ordered_card_ids: (stage.cards || []).map((card) => card.id)
				});
			} catch (error) {
				console.error(error);
				notify("Не удалось переместить карточку. Доска будет восстановлена.", "error");
				await fetchBoard(currentPipeline.value.id);
			} finally {
				saving.value = false;
			}
		}
		function onPipelineSelected(pipelineId) {
			selectedPipelineId.value = pipelineId;
			fetchBoard(pipelineId);
		}
		function unitTitle(unit) {
			if (!unit) return "Unit";
			return unit.name || `Unit #${unit.id}`;
		}
		function cardTitle(card) {
			return card.title || unitTitle(card.unit);
		}
		function shortText(text, limit = 90) {
			const value = String(text || "").trim();
			if (!value) return "";
			return value.length > limit ? `${value.slice(0, limit)}...` : value;
		}
		function formatDate(value) {
			if (!value) return null;
			return new Intl.DateTimeFormat("ru-RU", {
				day: "2-digit",
				month: "2-digit",
				year: "2-digit",
				hour: "2-digit",
				minute: "2-digit"
			}).format(new Date(value));
		}
		onMounted(async () => {
			await Promise.all([fetchBoard(), fetchUnitOptions()]);
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({
				fluid: "",
				class: "supplier-work pa-4"
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="supplier-work__hero" data-v-0088517b${_scopeId}><div data-v-0088517b${_scopeId}><div class="supplier-work__eyebrow" data-v-0088517b${_scopeId}>Ameise CRM</div><h1 class="supplier-work__title" data-v-0088517b${_scopeId}>Поставщики: воронки и рабочая доска</h1><p class="supplier-work__subtitle" data-v-0088517b${_scopeId}> Переносите карточки поставщиков по стадиям drag-and-drop, ведите заметки и управляйте структурой воронок без ухода со страницы. </p></div><div class="supplier-work__metrics" data-v-0088517b${_scopeId}><div class="supplier-work__metric" data-v-0088517b${_scopeId}><span data-v-0088517b${_scopeId}>${ssrInterpolate(pipelineItems.value.length)}</span><small data-v-0088517b${_scopeId}>воронок</small></div><div class="supplier-work__metric" data-v-0088517b${_scopeId}><span data-v-0088517b${_scopeId}>${ssrInterpolate(stages.value.length)}</span><small data-v-0088517b${_scopeId}>стадий</small></div><div class="supplier-work__metric" data-v-0088517b${_scopeId}><span data-v-0088517b${_scopeId}>${ssrInterpolate(totalCards.value)}</span><small data-v-0088517b${_scopeId}>карточек</small></div></div></div>`);
						_push(ssrRenderComponent(VCard, {
							rounded: "xl",
							elevation: "0",
							class: "supplier-work__toolbar mb-4"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VRow, {
									align: "center",
									dense: ""
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCol, {
												cols: "12",
												lg: "5"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VSelect, {
														"model-value": selectedPipelineId.value,
														items: pipelineItems.value,
														"item-title": "name",
														"item-value": "id",
														label: "Воронка",
														density: "compact",
														variant: "outlined",
														"hide-details": "",
														loading: loading.value,
														"onUpdate:modelValue": onPipelineSelected
													}, null, _parent, _scopeId));
													else return [createVNode(VSelect, {
														"model-value": selectedPipelineId.value,
														items: pipelineItems.value,
														"item-title": "name",
														"item-value": "id",
														label: "Воронка",
														density: "compact",
														variant: "outlined",
														"hide-details": "",
														loading: loading.value,
														"onUpdate:modelValue": onPipelineSelected
													}, null, 8, [
														"model-value",
														"items",
														"loading"
													])];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCol, {
												cols: "12",
												lg: "7",
												class: "supplier-work__toolbar-actions"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VBtn, {
															color: "#800000",
															"prepend-icon": "mdi-plus",
															rounded: "lg",
															onClick: ($event) => openPipelineDialog()
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Воронка `);
																else return [createTextVNode(" Воронка ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "#b45309",
															variant: "tonal",
															"prepend-icon": "mdi-timeline-plus",
															rounded: "lg",
															disabled: !hasPipeline.value,
															onClick: ($event) => openStageDialog()
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Стадия `);
																else return [createTextVNode(" Стадия ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "#166534",
															variant: "tonal",
															"prepend-icon": "mdi-account-plus",
															rounded: "lg",
															disabled: !hasPipeline.value || !stages.value.length,
															onClick: ($event) => openCardDialog(stages.value[0])
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Поставщик `);
																else return [createTextVNode(" Поставщик ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															icon: "mdi-refresh",
															variant: "text",
															loading: loading.value,
															onClick: ($event) => fetchBoard(selectedPipelineId.value)
														}, null, _parent, _scopeId));
													} else return [
														createVNode(VBtn, {
															color: "#800000",
															"prepend-icon": "mdi-plus",
															rounded: "lg",
															onClick: ($event) => openPipelineDialog()
														}, {
															default: withCtx(() => [createTextVNode(" Воронка ")]),
															_: 1
														}, 8, ["onClick"]),
														createVNode(VBtn, {
															color: "#b45309",
															variant: "tonal",
															"prepend-icon": "mdi-timeline-plus",
															rounded: "lg",
															disabled: !hasPipeline.value,
															onClick: ($event) => openStageDialog()
														}, {
															default: withCtx(() => [createTextVNode(" Стадия ")]),
															_: 1
														}, 8, ["disabled", "onClick"]),
														createVNode(VBtn, {
															color: "#166534",
															variant: "tonal",
															"prepend-icon": "mdi-account-plus",
															rounded: "lg",
															disabled: !hasPipeline.value || !stages.value.length,
															onClick: ($event) => openCardDialog(stages.value[0])
														}, {
															default: withCtx(() => [createTextVNode(" Поставщик ")]),
															_: 1
														}, 8, ["disabled", "onClick"]),
														createVNode(VBtn, {
															icon: "mdi-refresh",
															variant: "text",
															loading: loading.value,
															onClick: ($event) => fetchBoard(selectedPipelineId.value)
														}, null, 8, ["loading", "onClick"])
													];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [createVNode(VCol, {
											cols: "12",
											lg: "5"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												"model-value": selectedPipelineId.value,
												items: pipelineItems.value,
												"item-title": "name",
												"item-value": "id",
												label: "Воронка",
												density: "compact",
												variant: "outlined",
												"hide-details": "",
												loading: loading.value,
												"onUpdate:modelValue": onPipelineSelected
											}, null, 8, [
												"model-value",
												"items",
												"loading"
											])]),
											_: 1
										}), createVNode(VCol, {
											cols: "12",
											lg: "7",
											class: "supplier-work__toolbar-actions"
										}, {
											default: withCtx(() => [
												createVNode(VBtn, {
													color: "#800000",
													"prepend-icon": "mdi-plus",
													rounded: "lg",
													onClick: ($event) => openPipelineDialog()
												}, {
													default: withCtx(() => [createTextVNode(" Воронка ")]),
													_: 1
												}, 8, ["onClick"]),
												createVNode(VBtn, {
													color: "#b45309",
													variant: "tonal",
													"prepend-icon": "mdi-timeline-plus",
													rounded: "lg",
													disabled: !hasPipeline.value,
													onClick: ($event) => openStageDialog()
												}, {
													default: withCtx(() => [createTextVNode(" Стадия ")]),
													_: 1
												}, 8, ["disabled", "onClick"]),
												createVNode(VBtn, {
													color: "#166534",
													variant: "tonal",
													"prepend-icon": "mdi-account-plus",
													rounded: "lg",
													disabled: !hasPipeline.value || !stages.value.length,
													onClick: ($event) => openCardDialog(stages.value[0])
												}, {
													default: withCtx(() => [createTextVNode(" Поставщик ")]),
													_: 1
												}, 8, ["disabled", "onClick"]),
												createVNode(VBtn, {
													icon: "mdi-refresh",
													variant: "text",
													loading: loading.value,
													onClick: ($event) => fetchBoard(selectedPipelineId.value)
												}, null, 8, ["loading", "onClick"])
											]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VRow, {
									align: "center",
									dense: ""
								}, {
									default: withCtx(() => [createVNode(VCol, {
										cols: "12",
										lg: "5"
									}, {
										default: withCtx(() => [createVNode(VSelect, {
											"model-value": selectedPipelineId.value,
											items: pipelineItems.value,
											"item-title": "name",
											"item-value": "id",
											label: "Воронка",
											density: "compact",
											variant: "outlined",
											"hide-details": "",
											loading: loading.value,
											"onUpdate:modelValue": onPipelineSelected
										}, null, 8, [
											"model-value",
											"items",
											"loading"
										])]),
										_: 1
									}), createVNode(VCol, {
										cols: "12",
										lg: "7",
										class: "supplier-work__toolbar-actions"
									}, {
										default: withCtx(() => [
											createVNode(VBtn, {
												color: "#800000",
												"prepend-icon": "mdi-plus",
												rounded: "lg",
												onClick: ($event) => openPipelineDialog()
											}, {
												default: withCtx(() => [createTextVNode(" Воронка ")]),
												_: 1
											}, 8, ["onClick"]),
											createVNode(VBtn, {
												color: "#b45309",
												variant: "tonal",
												"prepend-icon": "mdi-timeline-plus",
												rounded: "lg",
												disabled: !hasPipeline.value,
												onClick: ($event) => openStageDialog()
											}, {
												default: withCtx(() => [createTextVNode(" Стадия ")]),
												_: 1
											}, 8, ["disabled", "onClick"]),
											createVNode(VBtn, {
												color: "#166534",
												variant: "tonal",
												"prepend-icon": "mdi-account-plus",
												rounded: "lg",
												disabled: !hasPipeline.value || !stages.value.length,
												onClick: ($event) => openCardDialog(stages.value[0])
											}, {
												default: withCtx(() => [createTextVNode(" Поставщик ")]),
												_: 1
											}, 8, ["disabled", "onClick"]),
											createVNode(VBtn, {
												icon: "mdi-refresh",
												variant: "text",
												loading: loading.value,
												onClick: ($event) => fetchBoard(selectedPipelineId.value)
											}, null, 8, ["loading", "onClick"])
										]),
										_: 1
									})]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						if (!hasPipeline.value) _push(ssrRenderComponent(VAlert, {
							type: "info",
							variant: "tonal",
							rounded: "xl",
							class: "mb-4"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Воронок поставщиков пока нет. Создайте первую воронку, затем добавьте стадии и карточки Units-поставщиков. `);
								else return [createTextVNode(" Воронок поставщиков пока нет. Создайте первую воронку, затем добавьте стадии и карточки Units-поставщиков. ")];
							}),
							_: 1
						}, _parent, _scopeId));
						else _push(ssrRenderComponent(VRow, { align: "start" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										xl: "9"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												if (loading.value) _push(ssrRenderComponent(VProgressLinear, {
													indeterminate: "",
													color: "#800000",
													class: "mb-3"
												}, null, _parent, _scopeId));
												else _push(`<!---->`);
												if (stages.value.length) {
													_push(`<div class="supplier-board" data-v-0088517b${_scopeId}><!--[-->`);
													ssrRenderList(stages.value, (stage) => {
														_push(`<section class="supplier-board__stage" data-v-0088517b${_scopeId}><header class="supplier-board__stage-header" data-v-0088517b${_scopeId}><div class="supplier-board__stage-main" data-v-0088517b${_scopeId}><span class="supplier-board__stage-dot" style="${ssrRenderStyle({ backgroundColor: stage.color || "#800000" })}" data-v-0088517b${_scopeId}></span><div data-v-0088517b${_scopeId}><h2 data-v-0088517b${_scopeId}>${ssrInterpolate(stage.name)}</h2><p data-v-0088517b${_scopeId}>${ssrInterpolate(stage.cards?.length || 0)} карточек</p></div></div>`);
														_push(ssrRenderComponent(VMenu, null, {
															activator: withCtx(({ props: menuProps }, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VBtn, mergeProps({ ref_for: true }, menuProps, {
																	icon: "mdi-dots-horizontal",
																	size: "small",
																	variant: "text"
																}), null, _parent, _scopeId));
																else return [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
																	icon: "mdi-dots-horizontal",
																	size: "small",
																	variant: "text"
																}), null, 16)];
															}),
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VList, { density: "compact" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(ssrRenderComponent(VListItem, { onClick: ($event) => openCardDialog(stage) }, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VListItemTitle, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`Добавить поставщика`);
																							else return [createTextVNode("Добавить поставщика")];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					else return [createVNode(VListItemTitle, null, {
																						default: withCtx(() => [createTextVNode("Добавить поставщика")]),
																						_: 1
																					})];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VListItem, { onClick: ($event) => openStageDialog(stage) }, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VListItemTitle, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`Редактировать стадию`);
																							else return [createTextVNode("Редактировать стадию")];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					else return [createVNode(VListItemTitle, null, {
																						default: withCtx(() => [createTextVNode("Редактировать стадию")]),
																						_: 1
																					})];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VListItem, { onClick: ($event) => reorderStages(stage, -1) }, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VListItemTitle, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`Поднять выше`);
																							else return [createTextVNode("Поднять выше")];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					else return [createVNode(VListItemTitle, null, {
																						default: withCtx(() => [createTextVNode("Поднять выше")]),
																						_: 1
																					})];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VListItem, { onClick: ($event) => reorderStages(stage, 1) }, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VListItemTitle, null, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`Опустить ниже`);
																							else return [createTextVNode("Опустить ниже")];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					else return [createVNode(VListItemTitle, null, {
																						default: withCtx(() => [createTextVNode("Опустить ниже")]),
																						_: 1
																					})];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VListItem, { onClick: ($event) => deleteStage(stage) }, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VListItemTitle, { class: "text-error" }, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(`Удалить стадию`);
																							else return [createTextVNode("Удалить стадию")];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					else return [createVNode(VListItemTitle, { class: "text-error" }, {
																						default: withCtx(() => [createTextVNode("Удалить стадию")]),
																						_: 1
																					})];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																		} else return [
																			createVNode(VListItem, { onClick: ($event) => openCardDialog(stage) }, {
																				default: withCtx(() => [createVNode(VListItemTitle, null, {
																					default: withCtx(() => [createTextVNode("Добавить поставщика")]),
																					_: 1
																				})]),
																				_: 1
																			}, 8, ["onClick"]),
																			createVNode(VListItem, { onClick: ($event) => openStageDialog(stage) }, {
																				default: withCtx(() => [createVNode(VListItemTitle, null, {
																					default: withCtx(() => [createTextVNode("Редактировать стадию")]),
																					_: 1
																				})]),
																				_: 1
																			}, 8, ["onClick"]),
																			createVNode(VListItem, { onClick: ($event) => reorderStages(stage, -1) }, {
																				default: withCtx(() => [createVNode(VListItemTitle, null, {
																					default: withCtx(() => [createTextVNode("Поднять выше")]),
																					_: 1
																				})]),
																				_: 1
																			}, 8, ["onClick"]),
																			createVNode(VListItem, { onClick: ($event) => reorderStages(stage, 1) }, {
																				default: withCtx(() => [createVNode(VListItemTitle, null, {
																					default: withCtx(() => [createTextVNode("Опустить ниже")]),
																					_: 1
																				})]),
																				_: 1
																			}, 8, ["onClick"]),
																			createVNode(VListItem, { onClick: ($event) => deleteStage(stage) }, {
																				default: withCtx(() => [createVNode(VListItemTitle, { class: "text-error" }, {
																					default: withCtx(() => [createTextVNode("Удалить стадию")]),
																					_: 1
																				})]),
																				_: 1
																			}, 8, ["onClick"])
																		];
																	}),
																	_: 2
																}, _parent, _scopeId));
																else return [createVNode(VList, { density: "compact" }, {
																	default: withCtx(() => [
																		createVNode(VListItem, { onClick: ($event) => openCardDialog(stage) }, {
																			default: withCtx(() => [createVNode(VListItemTitle, null, {
																				default: withCtx(() => [createTextVNode("Добавить поставщика")]),
																				_: 1
																			})]),
																			_: 1
																		}, 8, ["onClick"]),
																		createVNode(VListItem, { onClick: ($event) => openStageDialog(stage) }, {
																			default: withCtx(() => [createVNode(VListItemTitle, null, {
																				default: withCtx(() => [createTextVNode("Редактировать стадию")]),
																				_: 1
																			})]),
																			_: 1
																		}, 8, ["onClick"]),
																		createVNode(VListItem, { onClick: ($event) => reorderStages(stage, -1) }, {
																			default: withCtx(() => [createVNode(VListItemTitle, null, {
																				default: withCtx(() => [createTextVNode("Поднять выше")]),
																				_: 1
																			})]),
																			_: 1
																		}, 8, ["onClick"]),
																		createVNode(VListItem, { onClick: ($event) => reorderStages(stage, 1) }, {
																			default: withCtx(() => [createVNode(VListItemTitle, null, {
																				default: withCtx(() => [createTextVNode("Опустить ниже")]),
																				_: 1
																			})]),
																			_: 1
																		}, 8, ["onClick"]),
																		createVNode(VListItem, { onClick: ($event) => deleteStage(stage) }, {
																			default: withCtx(() => [createVNode(VListItemTitle, { class: "text-error" }, {
																				default: withCtx(() => [createTextVNode("Удалить стадию")]),
																				_: 1
																			})]),
																			_: 1
																		}, 8, ["onClick"])
																	]),
																	_: 2
																}, 1024)];
															}),
															_: 2
														}, _parent, _scopeId));
														_push(`</header>`);
														_push(ssrRenderComponent(unref(VueDraggableNext), {
															modelValue: stage.cards,
															"onUpdate:modelValue": ($event) => stage.cards = $event,
															group: "supplier-cards",
															"item-key": "id",
															class: "supplier-board__dropzone",
															"ghost-class": "supplier-board__ghost",
															"chosen-class": "supplier-board__chosen",
															onChange: (event) => onCardChange(stage, event)
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	_push(`<!--[-->`);
																	ssrRenderList(stage.cards, (card) => {
																		_push(`<article class="supplier-card" data-v-0088517b${_scopeId}><div class="supplier-card__topline" data-v-0088517b${_scopeId}>`);
																		_push(ssrRenderComponent(VChip, {
																			size: "x-small",
																			color: "#800000",
																			variant: "tonal"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(` Unit #${ssrInterpolate(card.unit_id)}`);
																				else return [createTextVNode(" Unit #" + toDisplayString(card.unit_id), 1)];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VBtn, {
																			icon: "mdi-pencil",
																			size: "x-small",
																			variant: "text",
																			onClick: ($event) => openCardDialog(stage, card)
																		}, null, _parent, _scopeId));
																		_push(`</div><h3 data-v-0088517b${_scopeId}>${ssrInterpolate(cardTitle(card))}</h3>`);
																		if (card.unit?.id) _push(ssrRenderComponent(unref(Link), {
																			href: unref(route)("web.unit.show", card.unit.id),
																			class: "supplier-card__unit-link"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`${ssrInterpolate(card.unit.name)}`);
																				else return [createTextVNode(toDisplayString(card.unit.name), 1)];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		else _push(`<!---->`);
																		if (card.notes) _push(`<p class="supplier-card__notes" data-v-0088517b${_scopeId}>${ssrInterpolate(shortText(card.notes))}</p>`);
																		else _push(`<!---->`);
																		_push(`<div class="supplier-card__tags" data-v-0088517b${_scopeId}><!--[-->`);
																		ssrRenderList(card.unit?.labels || [], (label) => {
																			_push(ssrRenderComponent(VChip, {
																				key: label.id,
																				size: "x-small",
																				variant: "outlined"
																			}, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(`${ssrInterpolate(label.name)}`);
																					else return [createTextVNode(toDisplayString(label.name), 1)];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																		});
																		_push(`<!--]--><!--[-->`);
																		ssrRenderList(card.unit?.cities || [], (city) => {
																			_push(ssrRenderComponent(VChip, {
																				key: `city-${city.id}`,
																				size: "x-small",
																				color: "#0f766e",
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
																		if (card.next_contact_at) {
																			_push(`<div class="supplier-card__footer" data-v-0088517b${_scopeId}>`);
																			_push(ssrRenderComponent(VIcon, {
																				icon: "mdi-calendar-clock",
																				size: "small"
																			}, null, _parent, _scopeId));
																			_push(`<span data-v-0088517b${_scopeId}>${ssrInterpolate(formatDate(card.next_contact_at))}</span></div>`);
																		} else _push(`<!---->`);
																		_push(`</article>`);
																	});
																	_push(`<!--]-->`);
																} else return [(openBlock(true), createBlock(Fragment, null, renderList(stage.cards, (card) => {
																	return openBlock(), createBlock("article", {
																		key: card.id,
																		class: "supplier-card"
																	}, [
																		createVNode("div", { class: "supplier-card__topline" }, [createVNode(VChip, {
																			size: "x-small",
																			color: "#800000",
																			variant: "tonal"
																		}, {
																			default: withCtx(() => [createTextVNode(" Unit #" + toDisplayString(card.unit_id), 1)]),
																			_: 2
																		}, 1024), createVNode(VBtn, {
																			icon: "mdi-pencil",
																			size: "x-small",
																			variant: "text",
																			onClick: ($event) => openCardDialog(stage, card)
																		}, null, 8, ["onClick"])]),
																		createVNode("h3", null, toDisplayString(cardTitle(card)), 1),
																		card.unit?.id ? (openBlock(), createBlock(unref(Link), {
																			key: 0,
																			href: unref(route)("web.unit.show", card.unit.id),
																			class: "supplier-card__unit-link"
																		}, {
																			default: withCtx(() => [createTextVNode(toDisplayString(card.unit.name), 1)]),
																			_: 2
																		}, 1032, ["href"])) : createCommentVNode("", true),
																		card.notes ? (openBlock(), createBlock("p", {
																			key: 1,
																			class: "supplier-card__notes"
																		}, toDisplayString(shortText(card.notes)), 1)) : createCommentVNode("", true),
																		createVNode("div", { class: "supplier-card__tags" }, [(openBlock(true), createBlock(Fragment, null, renderList(card.unit?.labels || [], (label) => {
																			return openBlock(), createBlock(VChip, {
																				key: label.id,
																				size: "x-small",
																				variant: "outlined"
																			}, {
																				default: withCtx(() => [createTextVNode(toDisplayString(label.name), 1)]),
																				_: 2
																			}, 1024);
																		}), 128)), (openBlock(true), createBlock(Fragment, null, renderList(card.unit?.cities || [], (city) => {
																			return openBlock(), createBlock(VChip, {
																				key: `city-${city.id}`,
																				size: "x-small",
																				color: "#0f766e",
																				variant: "tonal"
																			}, {
																				default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
																				_: 2
																			}, 1024);
																		}), 128))]),
																		card.next_contact_at ? (openBlock(), createBlock("div", {
																			key: 2,
																			class: "supplier-card__footer"
																		}, [createVNode(VIcon, {
																			icon: "mdi-calendar-clock",
																			size: "small"
																		}), createVNode("span", null, toDisplayString(formatDate(card.next_contact_at)), 1)])) : createCommentVNode("", true)
																	]);
																}), 128))];
															}),
															_: 2
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															block: "",
															variant: "tonal",
															color: "#800000",
															rounded: "lg",
															"prepend-icon": "mdi-plus",
															class: "mt-3",
															onClick: ($event) => openCardDialog(stage)
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Добавить `);
																else return [createTextVNode(" Добавить ")];
															}),
															_: 2
														}, _parent, _scopeId));
														_push(`</section>`);
													});
													_push(`<!--]--></div>`);
												} else _push(ssrRenderComponent(VAlert, {
													type: "warning",
													variant: "tonal",
													rounded: "xl"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` В этой воронке нет стадий. Добавьте хотя бы одну стадию, чтобы начать работу с карточками поставщиков. `);
														else return [createTextVNode(" В этой воронке нет стадий. Добавьте хотя бы одну стадию, чтобы начать работу с карточками поставщиков. ")];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [loading.value ? (openBlock(), createBlock(VProgressLinear, {
												key: 0,
												indeterminate: "",
												color: "#800000",
												class: "mb-3"
											})) : createCommentVNode("", true), stages.value.length ? (openBlock(), createBlock("div", {
												key: 1,
												class: "supplier-board"
											}, [(openBlock(true), createBlock(Fragment, null, renderList(stages.value, (stage) => {
												return openBlock(), createBlock("section", {
													key: stage.id,
													class: "supplier-board__stage"
												}, [
													createVNode("header", { class: "supplier-board__stage-header" }, [createVNode("div", { class: "supplier-board__stage-main" }, [createVNode("span", {
														class: "supplier-board__stage-dot",
														style: { backgroundColor: stage.color || "#800000" }
													}, null, 4), createVNode("div", null, [createVNode("h2", null, toDisplayString(stage.name), 1), createVNode("p", null, toDisplayString(stage.cards?.length || 0) + " карточек", 1)])]), createVNode(VMenu, null, {
														activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
															icon: "mdi-dots-horizontal",
															size: "small",
															variant: "text"
														}), null, 16)]),
														default: withCtx(() => [createVNode(VList, { density: "compact" }, {
															default: withCtx(() => [
																createVNode(VListItem, { onClick: ($event) => openCardDialog(stage) }, {
																	default: withCtx(() => [createVNode(VListItemTitle, null, {
																		default: withCtx(() => [createTextVNode("Добавить поставщика")]),
																		_: 1
																	})]),
																	_: 1
																}, 8, ["onClick"]),
																createVNode(VListItem, { onClick: ($event) => openStageDialog(stage) }, {
																	default: withCtx(() => [createVNode(VListItemTitle, null, {
																		default: withCtx(() => [createTextVNode("Редактировать стадию")]),
																		_: 1
																	})]),
																	_: 1
																}, 8, ["onClick"]),
																createVNode(VListItem, { onClick: ($event) => reorderStages(stage, -1) }, {
																	default: withCtx(() => [createVNode(VListItemTitle, null, {
																		default: withCtx(() => [createTextVNode("Поднять выше")]),
																		_: 1
																	})]),
																	_: 1
																}, 8, ["onClick"]),
																createVNode(VListItem, { onClick: ($event) => reorderStages(stage, 1) }, {
																	default: withCtx(() => [createVNode(VListItemTitle, null, {
																		default: withCtx(() => [createTextVNode("Опустить ниже")]),
																		_: 1
																	})]),
																	_: 1
																}, 8, ["onClick"]),
																createVNode(VListItem, { onClick: ($event) => deleteStage(stage) }, {
																	default: withCtx(() => [createVNode(VListItemTitle, { class: "text-error" }, {
																		default: withCtx(() => [createTextVNode("Удалить стадию")]),
																		_: 1
																	})]),
																	_: 1
																}, 8, ["onClick"])
															]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1024)]),
													createVNode(unref(VueDraggableNext), {
														modelValue: stage.cards,
														"onUpdate:modelValue": ($event) => stage.cards = $event,
														group: "supplier-cards",
														"item-key": "id",
														class: "supplier-board__dropzone",
														"ghost-class": "supplier-board__ghost",
														"chosen-class": "supplier-board__chosen",
														onChange: (event) => onCardChange(stage, event)
													}, {
														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(stage.cards, (card) => {
															return openBlock(), createBlock("article", {
																key: card.id,
																class: "supplier-card"
															}, [
																createVNode("div", { class: "supplier-card__topline" }, [createVNode(VChip, {
																	size: "x-small",
																	color: "#800000",
																	variant: "tonal"
																}, {
																	default: withCtx(() => [createTextVNode(" Unit #" + toDisplayString(card.unit_id), 1)]),
																	_: 2
																}, 1024), createVNode(VBtn, {
																	icon: "mdi-pencil",
																	size: "x-small",
																	variant: "text",
																	onClick: ($event) => openCardDialog(stage, card)
																}, null, 8, ["onClick"])]),
																createVNode("h3", null, toDisplayString(cardTitle(card)), 1),
																card.unit?.id ? (openBlock(), createBlock(unref(Link), {
																	key: 0,
																	href: unref(route)("web.unit.show", card.unit.id),
																	class: "supplier-card__unit-link"
																}, {
																	default: withCtx(() => [createTextVNode(toDisplayString(card.unit.name), 1)]),
																	_: 2
																}, 1032, ["href"])) : createCommentVNode("", true),
																card.notes ? (openBlock(), createBlock("p", {
																	key: 1,
																	class: "supplier-card__notes"
																}, toDisplayString(shortText(card.notes)), 1)) : createCommentVNode("", true),
																createVNode("div", { class: "supplier-card__tags" }, [(openBlock(true), createBlock(Fragment, null, renderList(card.unit?.labels || [], (label) => {
																	return openBlock(), createBlock(VChip, {
																		key: label.id,
																		size: "x-small",
																		variant: "outlined"
																	}, {
																		default: withCtx(() => [createTextVNode(toDisplayString(label.name), 1)]),
																		_: 2
																	}, 1024);
																}), 128)), (openBlock(true), createBlock(Fragment, null, renderList(card.unit?.cities || [], (city) => {
																	return openBlock(), createBlock(VChip, {
																		key: `city-${city.id}`,
																		size: "x-small",
																		color: "#0f766e",
																		variant: "tonal"
																	}, {
																		default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
																		_: 2
																	}, 1024);
																}), 128))]),
																card.next_contact_at ? (openBlock(), createBlock("div", {
																	key: 2,
																	class: "supplier-card__footer"
																}, [createVNode(VIcon, {
																	icon: "mdi-calendar-clock",
																	size: "small"
																}), createVNode("span", null, toDisplayString(formatDate(card.next_contact_at)), 1)])) : createCommentVNode("", true)
															]);
														}), 128))]),
														_: 2
													}, 1032, [
														"modelValue",
														"onUpdate:modelValue",
														"onChange"
													]),
													createVNode(VBtn, {
														block: "",
														variant: "tonal",
														color: "#800000",
														rounded: "lg",
														"prepend-icon": "mdi-plus",
														class: "mt-3",
														onClick: ($event) => openCardDialog(stage)
													}, {
														default: withCtx(() => [createTextVNode(" Добавить ")]),
														_: 1
													}, 8, ["onClick"])
												]);
											}), 128))])) : (openBlock(), createBlock(VAlert, {
												key: 2,
												type: "warning",
												variant: "tonal",
												rounded: "xl"
											}, {
												default: withCtx(() => [createTextVNode(" В этой воронке нет стадий. Добавьте хотя бы одну стадию, чтобы начать работу с карточками поставщиков. ")]),
												_: 1
											}))];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										xl: "3"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VCard, {
													rounded: "xl",
													elevation: "0",
													class: "supplier-side-card mb-4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` Управление воронкой `);
																	else return [createTextVNode(" Управление воронкой ")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCardSubtitle, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` CRUD воронки и стадий `);
																	else return [createTextVNode(" CRUD воронки и стадий ")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCardText, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(`<div class="supplier-side-card__current" data-v-0088517b${_scopeId}><strong data-v-0088517b${_scopeId}>${ssrInterpolate(currentPipeline.value.name)}</strong><span data-v-0088517b${_scopeId}>${ssrInterpolate(currentPipeline.value.description || "Описание не заполнено")}</span></div><div class="supplier-side-card__actions" data-v-0088517b${_scopeId}>`);
																		_push(ssrRenderComponent(VBtn, {
																			block: "",
																			color: "#800000",
																			variant: "tonal",
																			"prepend-icon": "mdi-pencil",
																			onClick: ($event) => openPipelineDialog(currentPipeline.value)
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(` Редактировать воронку `);
																				else return [createTextVNode(" Редактировать воронку ")];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VBtn, {
																			block: "",
																			color: "error",
																			variant: "tonal",
																			"prepend-icon": "mdi-delete",
																			onClick: ($event) => deletePipeline(currentPipeline.value)
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(` Удалить воронку `);
																				else return [createTextVNode(" Удалить воронку ")];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(`</div>`);
																	} else return [createVNode("div", { class: "supplier-side-card__current" }, [createVNode("strong", null, toDisplayString(currentPipeline.value.name), 1), createVNode("span", null, toDisplayString(currentPipeline.value.description || "Описание не заполнено"), 1)]), createVNode("div", { class: "supplier-side-card__actions" }, [createVNode(VBtn, {
																		block: "",
																		color: "#800000",
																		variant: "tonal",
																		"prepend-icon": "mdi-pencil",
																		onClick: ($event) => openPipelineDialog(currentPipeline.value)
																	}, {
																		default: withCtx(() => [createTextVNode(" Редактировать воронку ")]),
																		_: 1
																	}, 8, ["onClick"]), createVNode(VBtn, {
																		block: "",
																		color: "error",
																		variant: "tonal",
																		"prepend-icon": "mdi-delete",
																		onClick: ($event) => deletePipeline(currentPipeline.value)
																	}, {
																		default: withCtx(() => [createTextVNode(" Удалить воронку ")]),
																		_: 1
																	}, 8, ["onClick"])])];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [
															createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
																default: withCtx(() => [createTextVNode(" Управление воронкой ")]),
																_: 1
															}),
															createVNode(VCardSubtitle, null, {
																default: withCtx(() => [createTextVNode(" CRUD воронки и стадий ")]),
																_: 1
															}),
															createVNode(VCardText, null, {
																default: withCtx(() => [createVNode("div", { class: "supplier-side-card__current" }, [createVNode("strong", null, toDisplayString(currentPipeline.value.name), 1), createVNode("span", null, toDisplayString(currentPipeline.value.description || "Описание не заполнено"), 1)]), createVNode("div", { class: "supplier-side-card__actions" }, [createVNode(VBtn, {
																	block: "",
																	color: "#800000",
																	variant: "tonal",
																	"prepend-icon": "mdi-pencil",
																	onClick: ($event) => openPipelineDialog(currentPipeline.value)
																}, {
																	default: withCtx(() => [createTextVNode(" Редактировать воронку ")]),
																	_: 1
																}, 8, ["onClick"]), createVNode(VBtn, {
																	block: "",
																	color: "error",
																	variant: "tonal",
																	"prepend-icon": "mdi-delete",
																	onClick: ($event) => deletePipeline(currentPipeline.value)
																}, {
																	default: withCtx(() => [createTextVNode(" Удалить воронку ")]),
																	_: 1
																}, 8, ["onClick"])])]),
																_: 1
															})
														];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCard, {
													rounded: "xl",
													elevation: "0",
													class: "supplier-side-card"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` Стадии `);
																	else return [createTextVNode(" Стадии ")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCardText, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(`<div class="supplier-stage-list" data-v-0088517b${_scopeId}><!--[-->`);
																		ssrRenderList(stages.value, (stage) => {
																			_push(`<div class="supplier-stage-list__item" data-v-0088517b${_scopeId}><span class="supplier-board__stage-dot" style="${ssrRenderStyle({ backgroundColor: stage.color || "#800000" })}" data-v-0088517b${_scopeId}></span><div data-v-0088517b${_scopeId}><strong data-v-0088517b${_scopeId}>${ssrInterpolate(stage.name)}</strong><small data-v-0088517b${_scopeId}>${ssrInterpolate(stage.cards?.length || 0)} карточек</small></div>`);
																			_push(ssrRenderComponent(VBtn, {
																				icon: "mdi-pencil",
																				size: "x-small",
																				variant: "text",
																				onClick: ($event) => openStageDialog(stage)
																			}, null, _parent, _scopeId));
																			_push(`</div>`);
																		});
																		_push(`<!--]--></div>`);
																	} else return [createVNode("div", { class: "supplier-stage-list" }, [(openBlock(true), createBlock(Fragment, null, renderList(stages.value, (stage) => {
																		return openBlock(), createBlock("div", {
																			key: `side-${stage.id}`,
																			class: "supplier-stage-list__item"
																		}, [
																			createVNode("span", {
																				class: "supplier-board__stage-dot",
																				style: { backgroundColor: stage.color || "#800000" }
																			}, null, 4),
																			createVNode("div", null, [createVNode("strong", null, toDisplayString(stage.name), 1), createVNode("small", null, toDisplayString(stage.cards?.length || 0) + " карточек", 1)]),
																			createVNode(VBtn, {
																				icon: "mdi-pencil",
																				size: "x-small",
																				variant: "text",
																				onClick: ($event) => openStageDialog(stage)
																			}, null, 8, ["onClick"])
																		]);
																	}), 128))])];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
															default: withCtx(() => [createTextVNode(" Стадии ")]),
															_: 1
														}), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode("div", { class: "supplier-stage-list" }, [(openBlock(true), createBlock(Fragment, null, renderList(stages.value, (stage) => {
																return openBlock(), createBlock("div", {
																	key: `side-${stage.id}`,
																	class: "supplier-stage-list__item"
																}, [
																	createVNode("span", {
																		class: "supplier-board__stage-dot",
																		style: { backgroundColor: stage.color || "#800000" }
																	}, null, 4),
																	createVNode("div", null, [createVNode("strong", null, toDisplayString(stage.name), 1), createVNode("small", null, toDisplayString(stage.cards?.length || 0) + " карточек", 1)]),
																	createVNode(VBtn, {
																		icon: "mdi-pencil",
																		size: "x-small",
																		variant: "text",
																		onClick: ($event) => openStageDialog(stage)
																	}, null, 8, ["onClick"])
																]);
															}), 128))])]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [createVNode(VCard, {
												rounded: "xl",
												elevation: "0",
												class: "supplier-side-card mb-4"
											}, {
												default: withCtx(() => [
													createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
														default: withCtx(() => [createTextVNode(" Управление воронкой ")]),
														_: 1
													}),
													createVNode(VCardSubtitle, null, {
														default: withCtx(() => [createTextVNode(" CRUD воронки и стадий ")]),
														_: 1
													}),
													createVNode(VCardText, null, {
														default: withCtx(() => [createVNode("div", { class: "supplier-side-card__current" }, [createVNode("strong", null, toDisplayString(currentPipeline.value.name), 1), createVNode("span", null, toDisplayString(currentPipeline.value.description || "Описание не заполнено"), 1)]), createVNode("div", { class: "supplier-side-card__actions" }, [createVNode(VBtn, {
															block: "",
															color: "#800000",
															variant: "tonal",
															"prepend-icon": "mdi-pencil",
															onClick: ($event) => openPipelineDialog(currentPipeline.value)
														}, {
															default: withCtx(() => [createTextVNode(" Редактировать воронку ")]),
															_: 1
														}, 8, ["onClick"]), createVNode(VBtn, {
															block: "",
															color: "error",
															variant: "tonal",
															"prepend-icon": "mdi-delete",
															onClick: ($event) => deletePipeline(currentPipeline.value)
														}, {
															default: withCtx(() => [createTextVNode(" Удалить воронку ")]),
															_: 1
														}, 8, ["onClick"])])]),
														_: 1
													})
												]),
												_: 1
											}), createVNode(VCard, {
												rounded: "xl",
												elevation: "0",
												class: "supplier-side-card"
											}, {
												default: withCtx(() => [createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
													default: withCtx(() => [createTextVNode(" Стадии ")]),
													_: 1
												}), createVNode(VCardText, null, {
													default: withCtx(() => [createVNode("div", { class: "supplier-stage-list" }, [(openBlock(true), createBlock(Fragment, null, renderList(stages.value, (stage) => {
														return openBlock(), createBlock("div", {
															key: `side-${stage.id}`,
															class: "supplier-stage-list__item"
														}, [
															createVNode("span", {
																class: "supplier-board__stage-dot",
																style: { backgroundColor: stage.color || "#800000" }
															}, null, 4),
															createVNode("div", null, [createVNode("strong", null, toDisplayString(stage.name), 1), createVNode("small", null, toDisplayString(stage.cards?.length || 0) + " карточек", 1)]),
															createVNode(VBtn, {
																icon: "mdi-pencil",
																size: "x-small",
																variant: "text",
																onClick: ($event) => openStageDialog(stage)
															}, null, 8, ["onClick"])
														]);
													}), 128))])]),
													_: 1
												})]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, {
									cols: "12",
									xl: "9"
								}, {
									default: withCtx(() => [loading.value ? (openBlock(), createBlock(VProgressLinear, {
										key: 0,
										indeterminate: "",
										color: "#800000",
										class: "mb-3"
									})) : createCommentVNode("", true), stages.value.length ? (openBlock(), createBlock("div", {
										key: 1,
										class: "supplier-board"
									}, [(openBlock(true), createBlock(Fragment, null, renderList(stages.value, (stage) => {
										return openBlock(), createBlock("section", {
											key: stage.id,
											class: "supplier-board__stage"
										}, [
											createVNode("header", { class: "supplier-board__stage-header" }, [createVNode("div", { class: "supplier-board__stage-main" }, [createVNode("span", {
												class: "supplier-board__stage-dot",
												style: { backgroundColor: stage.color || "#800000" }
											}, null, 4), createVNode("div", null, [createVNode("h2", null, toDisplayString(stage.name), 1), createVNode("p", null, toDisplayString(stage.cards?.length || 0) + " карточек", 1)])]), createVNode(VMenu, null, {
												activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
													icon: "mdi-dots-horizontal",
													size: "small",
													variant: "text"
												}), null, 16)]),
												default: withCtx(() => [createVNode(VList, { density: "compact" }, {
													default: withCtx(() => [
														createVNode(VListItem, { onClick: ($event) => openCardDialog(stage) }, {
															default: withCtx(() => [createVNode(VListItemTitle, null, {
																default: withCtx(() => [createTextVNode("Добавить поставщика")]),
																_: 1
															})]),
															_: 1
														}, 8, ["onClick"]),
														createVNode(VListItem, { onClick: ($event) => openStageDialog(stage) }, {
															default: withCtx(() => [createVNode(VListItemTitle, null, {
																default: withCtx(() => [createTextVNode("Редактировать стадию")]),
																_: 1
															})]),
															_: 1
														}, 8, ["onClick"]),
														createVNode(VListItem, { onClick: ($event) => reorderStages(stage, -1) }, {
															default: withCtx(() => [createVNode(VListItemTitle, null, {
																default: withCtx(() => [createTextVNode("Поднять выше")]),
																_: 1
															})]),
															_: 1
														}, 8, ["onClick"]),
														createVNode(VListItem, { onClick: ($event) => reorderStages(stage, 1) }, {
															default: withCtx(() => [createVNode(VListItemTitle, null, {
																default: withCtx(() => [createTextVNode("Опустить ниже")]),
																_: 1
															})]),
															_: 1
														}, 8, ["onClick"]),
														createVNode(VListItem, { onClick: ($event) => deleteStage(stage) }, {
															default: withCtx(() => [createVNode(VListItemTitle, { class: "text-error" }, {
																default: withCtx(() => [createTextVNode("Удалить стадию")]),
																_: 1
															})]),
															_: 1
														}, 8, ["onClick"])
													]),
													_: 2
												}, 1024)]),
												_: 2
											}, 1024)]),
											createVNode(unref(VueDraggableNext), {
												modelValue: stage.cards,
												"onUpdate:modelValue": ($event) => stage.cards = $event,
												group: "supplier-cards",
												"item-key": "id",
												class: "supplier-board__dropzone",
												"ghost-class": "supplier-board__ghost",
												"chosen-class": "supplier-board__chosen",
												onChange: (event) => onCardChange(stage, event)
											}, {
												default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(stage.cards, (card) => {
													return openBlock(), createBlock("article", {
														key: card.id,
														class: "supplier-card"
													}, [
														createVNode("div", { class: "supplier-card__topline" }, [createVNode(VChip, {
															size: "x-small",
															color: "#800000",
															variant: "tonal"
														}, {
															default: withCtx(() => [createTextVNode(" Unit #" + toDisplayString(card.unit_id), 1)]),
															_: 2
														}, 1024), createVNode(VBtn, {
															icon: "mdi-pencil",
															size: "x-small",
															variant: "text",
															onClick: ($event) => openCardDialog(stage, card)
														}, null, 8, ["onClick"])]),
														createVNode("h3", null, toDisplayString(cardTitle(card)), 1),
														card.unit?.id ? (openBlock(), createBlock(unref(Link), {
															key: 0,
															href: unref(route)("web.unit.show", card.unit.id),
															class: "supplier-card__unit-link"
														}, {
															default: withCtx(() => [createTextVNode(toDisplayString(card.unit.name), 1)]),
															_: 2
														}, 1032, ["href"])) : createCommentVNode("", true),
														card.notes ? (openBlock(), createBlock("p", {
															key: 1,
															class: "supplier-card__notes"
														}, toDisplayString(shortText(card.notes)), 1)) : createCommentVNode("", true),
														createVNode("div", { class: "supplier-card__tags" }, [(openBlock(true), createBlock(Fragment, null, renderList(card.unit?.labels || [], (label) => {
															return openBlock(), createBlock(VChip, {
																key: label.id,
																size: "x-small",
																variant: "outlined"
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(label.name), 1)]),
																_: 2
															}, 1024);
														}), 128)), (openBlock(true), createBlock(Fragment, null, renderList(card.unit?.cities || [], (city) => {
															return openBlock(), createBlock(VChip, {
																key: `city-${city.id}`,
																size: "x-small",
																color: "#0f766e",
																variant: "tonal"
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
																_: 2
															}, 1024);
														}), 128))]),
														card.next_contact_at ? (openBlock(), createBlock("div", {
															key: 2,
															class: "supplier-card__footer"
														}, [createVNode(VIcon, {
															icon: "mdi-calendar-clock",
															size: "small"
														}), createVNode("span", null, toDisplayString(formatDate(card.next_contact_at)), 1)])) : createCommentVNode("", true)
													]);
												}), 128))]),
												_: 2
											}, 1032, [
												"modelValue",
												"onUpdate:modelValue",
												"onChange"
											]),
											createVNode(VBtn, {
												block: "",
												variant: "tonal",
												color: "#800000",
												rounded: "lg",
												"prepend-icon": "mdi-plus",
												class: "mt-3",
												onClick: ($event) => openCardDialog(stage)
											}, {
												default: withCtx(() => [createTextVNode(" Добавить ")]),
												_: 1
											}, 8, ["onClick"])
										]);
									}), 128))])) : (openBlock(), createBlock(VAlert, {
										key: 2,
										type: "warning",
										variant: "tonal",
										rounded: "xl"
									}, {
										default: withCtx(() => [createTextVNode(" В этой воронке нет стадий. Добавьте хотя бы одну стадию, чтобы начать работу с карточками поставщиков. ")]),
										_: 1
									}))]),
									_: 1
								}), createVNode(VCol, {
									cols: "12",
									xl: "3"
								}, {
									default: withCtx(() => [createVNode(VCard, {
										rounded: "xl",
										elevation: "0",
										class: "supplier-side-card mb-4"
									}, {
										default: withCtx(() => [
											createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
												default: withCtx(() => [createTextVNode(" Управление воронкой ")]),
												_: 1
											}),
											createVNode(VCardSubtitle, null, {
												default: withCtx(() => [createTextVNode(" CRUD воронки и стадий ")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode("div", { class: "supplier-side-card__current" }, [createVNode("strong", null, toDisplayString(currentPipeline.value.name), 1), createVNode("span", null, toDisplayString(currentPipeline.value.description || "Описание не заполнено"), 1)]), createVNode("div", { class: "supplier-side-card__actions" }, [createVNode(VBtn, {
													block: "",
													color: "#800000",
													variant: "tonal",
													"prepend-icon": "mdi-pencil",
													onClick: ($event) => openPipelineDialog(currentPipeline.value)
												}, {
													default: withCtx(() => [createTextVNode(" Редактировать воронку ")]),
													_: 1
												}, 8, ["onClick"]), createVNode(VBtn, {
													block: "",
													color: "error",
													variant: "tonal",
													"prepend-icon": "mdi-delete",
													onClick: ($event) => deletePipeline(currentPipeline.value)
												}, {
													default: withCtx(() => [createTextVNode(" Удалить воронку ")]),
													_: 1
												}, 8, ["onClick"])])]),
												_: 1
											})
										]),
										_: 1
									}), createVNode(VCard, {
										rounded: "xl",
										elevation: "0",
										class: "supplier-side-card"
									}, {
										default: withCtx(() => [createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
											default: withCtx(() => [createTextVNode(" Стадии ")]),
											_: 1
										}), createVNode(VCardText, null, {
											default: withCtx(() => [createVNode("div", { class: "supplier-stage-list" }, [(openBlock(true), createBlock(Fragment, null, renderList(stages.value, (stage) => {
												return openBlock(), createBlock("div", {
													key: `side-${stage.id}`,
													class: "supplier-stage-list__item"
												}, [
													createVNode("span", {
														class: "supplier-board__stage-dot",
														style: { backgroundColor: stage.color || "#800000" }
													}, null, 4),
													createVNode("div", null, [createVNode("strong", null, toDisplayString(stage.name), 1), createVNode("small", null, toDisplayString(stage.cards?.length || 0) + " карточек", 1)]),
													createVNode(VBtn, {
														icon: "mdi-pencil",
														size: "x-small",
														variant: "text",
														onClick: ($event) => openStageDialog(stage)
													}, null, 8, ["onClick"])
												]);
											}), 128))])]),
											_: 1
										})]),
										_: 1
									})]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDialog, {
							modelValue: pipelineDialog.value,
							"onUpdate:modelValue": ($event) => pipelineDialog.value = $event,
							"max-width": "620"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, { rounded: "xl" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(editingPipeline.value ? "Редактировать воронку" : "Новая воронка")}`);
													else return [createTextVNode(toDisplayString(editingPipeline.value ? "Редактировать воронку" : "Новая воронка"), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, { class: "d-grid ga-3" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VTextField, {
															modelValue: pipelineForm.name,
															"onUpdate:modelValue": ($event) => pipelineForm.name = $event,
															label: "Название",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextarea, {
															modelValue: pipelineForm.description,
															"onUpdate:modelValue": ($event) => pipelineForm.description = $event,
															label: "Описание",
															variant: "outlined",
															rows: "3"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextField, {
															modelValue: pipelineForm.sort_order,
															"onUpdate:modelValue": ($event) => pipelineForm.sort_order = $event,
															modelModifiers: { number: true },
															label: "Порядок",
															type: "number",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VSwitch, {
															modelValue: pipelineForm.is_active,
															"onUpdate:modelValue": ($event) => pipelineForm.is_active = $event,
															color: "#800000",
															label: "Активная воронка",
															"hide-details": ""
														}, null, _parent, _scopeId));
													} else return [
														createVNode(VTextField, {
															modelValue: pipelineForm.name,
															"onUpdate:modelValue": ($event) => pipelineForm.name = $event,
															label: "Название",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextarea, {
															modelValue: pipelineForm.description,
															"onUpdate:modelValue": ($event) => pipelineForm.description = $event,
															label: "Описание",
															variant: "outlined",
															rows: "3"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextField, {
															modelValue: pipelineForm.sort_order,
															"onUpdate:modelValue": ($event) => pipelineForm.sort_order = $event,
															modelModifiers: { number: true },
															label: "Порядок",
															type: "number",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VSwitch, {
															modelValue: pipelineForm.is_active,
															"onUpdate:modelValue": ($event) => pipelineForm.is_active = $event,
															color: "#800000",
															label: "Активная воронка",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])
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
															onClick: ($event) => pipelineDialog.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Отмена`);
																else return [createTextVNode("Отмена")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "#800000",
															loading: saving.value,
															onClick: savePipeline
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Сохранить`);
																else return [createTextVNode("Сохранить")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [
														createVNode(VSpacer),
														createVNode(VBtn, {
															variant: "text",
															onClick: ($event) => pipelineDialog.value = false
														}, {
															default: withCtx(() => [createTextVNode("Отмена")]),
															_: 1
														}, 8, ["onClick"]),
														createVNode(VBtn, {
															color: "#800000",
															loading: saving.value,
															onClick: savePipeline
														}, {
															default: withCtx(() => [createTextVNode("Сохранить")]),
															_: 1
														}, 8, ["loading"])
													];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode(toDisplayString(editingPipeline.value ? "Редактировать воронку" : "Новая воронка"), 1)]),
												_: 1
											}),
											createVNode(VCardText, { class: "d-grid ga-3" }, {
												default: withCtx(() => [
													createVNode(VTextField, {
														modelValue: pipelineForm.name,
														"onUpdate:modelValue": ($event) => pipelineForm.name = $event,
														label: "Название",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextarea, {
														modelValue: pipelineForm.description,
														"onUpdate:modelValue": ($event) => pipelineForm.description = $event,
														label: "Описание",
														variant: "outlined",
														rows: "3"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextField, {
														modelValue: pipelineForm.sort_order,
														"onUpdate:modelValue": ($event) => pipelineForm.sort_order = $event,
														modelModifiers: { number: true },
														label: "Порядок",
														type: "number",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VSwitch, {
														modelValue: pipelineForm.is_active,
														"onUpdate:modelValue": ($event) => pipelineForm.is_active = $event,
														color: "#800000",
														label: "Активная воронка",
														"hide-details": ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])
												]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [
													createVNode(VSpacer),
													createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => pipelineDialog.value = false
													}, {
														default: withCtx(() => [createTextVNode("Отмена")]),
														_: 1
													}, 8, ["onClick"]),
													createVNode(VBtn, {
														color: "#800000",
														loading: saving.value,
														onClick: savePipeline
													}, {
														default: withCtx(() => [createTextVNode("Сохранить")]),
														_: 1
													}, 8, ["loading"])
												]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, { rounded: "xl" }, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode(toDisplayString(editingPipeline.value ? "Редактировать воронку" : "Новая воронка"), 1)]),
											_: 1
										}),
										createVNode(VCardText, { class: "d-grid ga-3" }, {
											default: withCtx(() => [
												createVNode(VTextField, {
													modelValue: pipelineForm.name,
													"onUpdate:modelValue": ($event) => pipelineForm.name = $event,
													label: "Название",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextarea, {
													modelValue: pipelineForm.description,
													"onUpdate:modelValue": ($event) => pipelineForm.description = $event,
													label: "Описание",
													variant: "outlined",
													rows: "3"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextField, {
													modelValue: pipelineForm.sort_order,
													"onUpdate:modelValue": ($event) => pipelineForm.sort_order = $event,
													modelModifiers: { number: true },
													label: "Порядок",
													type: "number",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VSwitch, {
													modelValue: pipelineForm.is_active,
													"onUpdate:modelValue": ($event) => pipelineForm.is_active = $event,
													color: "#800000",
													label: "Активная воронка",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])
											]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [
												createVNode(VSpacer),
												createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => pipelineDialog.value = false
												}, {
													default: withCtx(() => [createTextVNode("Отмена")]),
													_: 1
												}, 8, ["onClick"]),
												createVNode(VBtn, {
													color: "#800000",
													loading: saving.value,
													onClick: savePipeline
												}, {
													default: withCtx(() => [createTextVNode("Сохранить")]),
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
						_push(ssrRenderComponent(VDialog, {
							modelValue: stageDialog.value,
							"onUpdate:modelValue": ($event) => stageDialog.value = $event,
							"max-width": "620"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, { rounded: "xl" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(editingStage.value ? "Редактировать стадию" : "Новая стадия")}`);
													else return [createTextVNode(toDisplayString(editingStage.value ? "Редактировать стадию" : "Новая стадия"), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, { class: "d-grid ga-3" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VTextField, {
															modelValue: stageForm.name,
															"onUpdate:modelValue": ($event) => stageForm.name = $event,
															label: "Название",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextField, {
															modelValue: stageForm.color,
															"onUpdate:modelValue": ($event) => stageForm.color = $event,
															label: "Цвет",
															type: "color",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextField, {
															modelValue: stageForm.sort_order,
															"onUpdate:modelValue": ($event) => stageForm.sort_order = $event,
															modelModifiers: { number: true },
															label: "Порядок",
															type: "number",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextarea, {
															modelValue: stageForm.description,
															"onUpdate:modelValue": ($event) => stageForm.description = $event,
															label: "Описание",
															variant: "outlined",
															rows: "3"
														}, null, _parent, _scopeId));
													} else return [
														createVNode(VTextField, {
															modelValue: stageForm.name,
															"onUpdate:modelValue": ($event) => stageForm.name = $event,
															label: "Название",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextField, {
															modelValue: stageForm.color,
															"onUpdate:modelValue": ($event) => stageForm.color = $event,
															label: "Цвет",
															type: "color",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextField, {
															modelValue: stageForm.sort_order,
															"onUpdate:modelValue": ($event) => stageForm.sort_order = $event,
															modelModifiers: { number: true },
															label: "Порядок",
															type: "number",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextarea, {
															modelValue: stageForm.description,
															"onUpdate:modelValue": ($event) => stageForm.description = $event,
															label: "Описание",
															variant: "outlined",
															rows: "3"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])
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
															onClick: ($event) => stageDialog.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Отмена`);
																else return [createTextVNode("Отмена")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "#800000",
															loading: saving.value,
															onClick: saveStage
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Сохранить`);
																else return [createTextVNode("Сохранить")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [
														createVNode(VSpacer),
														createVNode(VBtn, {
															variant: "text",
															onClick: ($event) => stageDialog.value = false
														}, {
															default: withCtx(() => [createTextVNode("Отмена")]),
															_: 1
														}, 8, ["onClick"]),
														createVNode(VBtn, {
															color: "#800000",
															loading: saving.value,
															onClick: saveStage
														}, {
															default: withCtx(() => [createTextVNode("Сохранить")]),
															_: 1
														}, 8, ["loading"])
													];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode(toDisplayString(editingStage.value ? "Редактировать стадию" : "Новая стадия"), 1)]),
												_: 1
											}),
											createVNode(VCardText, { class: "d-grid ga-3" }, {
												default: withCtx(() => [
													createVNode(VTextField, {
														modelValue: stageForm.name,
														"onUpdate:modelValue": ($event) => stageForm.name = $event,
														label: "Название",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextField, {
														modelValue: stageForm.color,
														"onUpdate:modelValue": ($event) => stageForm.color = $event,
														label: "Цвет",
														type: "color",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextField, {
														modelValue: stageForm.sort_order,
														"onUpdate:modelValue": ($event) => stageForm.sort_order = $event,
														modelModifiers: { number: true },
														label: "Порядок",
														type: "number",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextarea, {
														modelValue: stageForm.description,
														"onUpdate:modelValue": ($event) => stageForm.description = $event,
														label: "Описание",
														variant: "outlined",
														rows: "3"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])
												]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [
													createVNode(VSpacer),
													createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => stageDialog.value = false
													}, {
														default: withCtx(() => [createTextVNode("Отмена")]),
														_: 1
													}, 8, ["onClick"]),
													createVNode(VBtn, {
														color: "#800000",
														loading: saving.value,
														onClick: saveStage
													}, {
														default: withCtx(() => [createTextVNode("Сохранить")]),
														_: 1
													}, 8, ["loading"])
												]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, { rounded: "xl" }, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode(toDisplayString(editingStage.value ? "Редактировать стадию" : "Новая стадия"), 1)]),
											_: 1
										}),
										createVNode(VCardText, { class: "d-grid ga-3" }, {
											default: withCtx(() => [
												createVNode(VTextField, {
													modelValue: stageForm.name,
													"onUpdate:modelValue": ($event) => stageForm.name = $event,
													label: "Название",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextField, {
													modelValue: stageForm.color,
													"onUpdate:modelValue": ($event) => stageForm.color = $event,
													label: "Цвет",
													type: "color",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextField, {
													modelValue: stageForm.sort_order,
													"onUpdate:modelValue": ($event) => stageForm.sort_order = $event,
													modelModifiers: { number: true },
													label: "Порядок",
													type: "number",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextarea, {
													modelValue: stageForm.description,
													"onUpdate:modelValue": ($event) => stageForm.description = $event,
													label: "Описание",
													variant: "outlined",
													rows: "3"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])
											]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [
												createVNode(VSpacer),
												createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => stageDialog.value = false
												}, {
													default: withCtx(() => [createTextVNode("Отмена")]),
													_: 1
												}, 8, ["onClick"]),
												createVNode(VBtn, {
													color: "#800000",
													loading: saving.value,
													onClick: saveStage
												}, {
													default: withCtx(() => [createTextVNode("Сохранить")]),
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
						_push(ssrRenderComponent(VDialog, {
							modelValue: cardDialog.value,
							"onUpdate:modelValue": ($event) => cardDialog.value = $event,
							"max-width": "720"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCard, { rounded: "xl" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VCardTitle, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(editingCard.value ? "Редактировать карточку поставщика" : "Добавить поставщика")}`);
													else return [createTextVNode(toDisplayString(editingCard.value ? "Редактировать карточку поставщика" : "Добавить поставщика"), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardText, { class: "d-grid ga-3" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VAutocomplete, {
															modelValue: cardForm.unit_id,
															"onUpdate:modelValue": ($event) => cardForm.unit_id = $event,
															search: unitSearch.value,
															"onUpdate:search": [($event) => unitSearch.value = $event, fetchUnitOptions],
															items: unitOptions.value,
															"item-title": "name",
															"item-value": "id",
															label: "Unit",
															variant: "outlined",
															density: "compact",
															disabled: Boolean(editingCard.value),
															"no-filter": "",
															clearable: ""
														}, {
															item: withCtx(({ props: itemProps, item }, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VListItem, itemProps, {
																	append: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) if (item.raw.is_supplier) _push(ssrRenderComponent(VChip, {
																			size: "x-small",
																			color: "#166534",
																			variant: "tonal"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(` supplier `);
																				else return [createTextVNode(" supplier ")];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		else _push(`<!---->`);
																		else return [item.raw.is_supplier ? (openBlock(), createBlock(VChip, {
																			key: 0,
																			size: "x-small",
																			color: "#166534",
																			variant: "tonal"
																		}, {
																			default: withCtx(() => [createTextVNode(" supplier ")]),
																			_: 1
																		})) : createCommentVNode("", true)];
																	}),
																	_: 2
																}, _parent, _scopeId));
																else return [createVNode(VListItem, itemProps, {
																	append: withCtx(() => [item.raw.is_supplier ? (openBlock(), createBlock(VChip, {
																		key: 0,
																		size: "x-small",
																		color: "#166534",
																		variant: "tonal"
																	}, {
																		default: withCtx(() => [createTextVNode(" supplier ")]),
																		_: 1
																	})) : createCommentVNode("", true)]),
																	_: 2
																}, 1040)];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VSelect, {
															modelValue: cardForm.supplier_pipeline_stage_id,
															"onUpdate:modelValue": ($event) => cardForm.supplier_pipeline_stage_id = $event,
															items: stages.value,
															"item-title": "name",
															"item-value": "id",
															label: "Стадия",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextField, {
															modelValue: cardForm.title,
															"onUpdate:modelValue": ($event) => cardForm.title = $event,
															label: "Заголовок карточки",
															placeholder: "Если пусто, будет использовано название Unit",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextarea, {
															modelValue: cardForm.notes,
															"onUpdate:modelValue": ($event) => cardForm.notes = $event,
															label: "Заметки по работе",
															variant: "outlined",
															rows: "4"
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VTextField, {
															modelValue: cardForm.next_contact_at,
															"onUpdate:modelValue": ($event) => cardForm.next_contact_at = $event,
															label: "Следующий контакт",
															type: "datetime-local",
															variant: "outlined",
															density: "compact"
														}, null, _parent, _scopeId));
													} else return [
														createVNode(VAutocomplete, {
															modelValue: cardForm.unit_id,
															"onUpdate:modelValue": ($event) => cardForm.unit_id = $event,
															search: unitSearch.value,
															"onUpdate:search": [($event) => unitSearch.value = $event, fetchUnitOptions],
															items: unitOptions.value,
															"item-title": "name",
															"item-value": "id",
															label: "Unit",
															variant: "outlined",
															density: "compact",
															disabled: Boolean(editingCard.value),
															"no-filter": "",
															clearable: ""
														}, {
															item: withCtx(({ props: itemProps, item }) => [createVNode(VListItem, itemProps, {
																append: withCtx(() => [item.raw.is_supplier ? (openBlock(), createBlock(VChip, {
																	key: 0,
																	size: "x-small",
																	color: "#166534",
																	variant: "tonal"
																}, {
																	default: withCtx(() => [createTextVNode(" supplier ")]),
																	_: 1
																})) : createCommentVNode("", true)]),
																_: 2
															}, 1040)]),
															_: 1
														}, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"search",
															"onUpdate:search",
															"items",
															"disabled"
														]),
														createVNode(VSelect, {
															modelValue: cardForm.supplier_pipeline_stage_id,
															"onUpdate:modelValue": ($event) => cardForm.supplier_pipeline_stage_id = $event,
															items: stages.value,
															"item-title": "name",
															"item-value": "id",
															label: "Стадия",
															variant: "outlined",
															density: "compact"
														}, null, 8, [
															"modelValue",
															"onUpdate:modelValue",
															"items"
														]),
														createVNode(VTextField, {
															modelValue: cardForm.title,
															"onUpdate:modelValue": ($event) => cardForm.title = $event,
															label: "Заголовок карточки",
															placeholder: "Если пусто, будет использовано название Unit",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextarea, {
															modelValue: cardForm.notes,
															"onUpdate:modelValue": ($event) => cardForm.notes = $event,
															label: "Заметки по работе",
															variant: "outlined",
															rows: "4"
														}, null, 8, ["modelValue", "onUpdate:modelValue"]),
														createVNode(VTextField, {
															modelValue: cardForm.next_contact_at,
															"onUpdate:modelValue": ($event) => cardForm.next_contact_at = $event,
															label: "Следующий контакт",
															type: "datetime-local",
															variant: "outlined",
															density: "compact"
														}, null, 8, ["modelValue", "onUpdate:modelValue"])
													];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VCardActions, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														if (editingCard.value) _push(ssrRenderComponent(VBtn, {
															color: "error",
															variant: "tonal",
															onClick: ($event) => {
																deleteCard(editingCard.value);
																cardDialog.value = false;
															}
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Удалить `);
																else return [createTextVNode(" Удалить ")];
															}),
															_: 1
														}, _parent, _scopeId));
														else _push(`<!---->`);
														_push(ssrRenderComponent(VSpacer, null, null, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															variant: "text",
															onClick: ($event) => cardDialog.value = false
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Отмена`);
																else return [createTextVNode("Отмена")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VBtn, {
															color: "#800000",
															loading: saving.value,
															onClick: saveCard
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Сохранить`);
																else return [createTextVNode("Сохранить")];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [
														editingCard.value ? (openBlock(), createBlock(VBtn, {
															key: 0,
															color: "error",
															variant: "tonal",
															onClick: ($event) => {
																deleteCard(editingCard.value);
																cardDialog.value = false;
															}
														}, {
															default: withCtx(() => [createTextVNode(" Удалить ")]),
															_: 1
														}, 8, ["onClick"])) : createCommentVNode("", true),
														createVNode(VSpacer),
														createVNode(VBtn, {
															variant: "text",
															onClick: ($event) => cardDialog.value = false
														}, {
															default: withCtx(() => [createTextVNode("Отмена")]),
															_: 1
														}, 8, ["onClick"]),
														createVNode(VBtn, {
															color: "#800000",
															loading: saving.value,
															onClick: saveCard
														}, {
															default: withCtx(() => [createTextVNode("Сохранить")]),
															_: 1
														}, 8, ["loading"])
													];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode(toDisplayString(editingCard.value ? "Редактировать карточку поставщика" : "Добавить поставщика"), 1)]),
												_: 1
											}),
											createVNode(VCardText, { class: "d-grid ga-3" }, {
												default: withCtx(() => [
													createVNode(VAutocomplete, {
														modelValue: cardForm.unit_id,
														"onUpdate:modelValue": ($event) => cardForm.unit_id = $event,
														search: unitSearch.value,
														"onUpdate:search": [($event) => unitSearch.value = $event, fetchUnitOptions],
														items: unitOptions.value,
														"item-title": "name",
														"item-value": "id",
														label: "Unit",
														variant: "outlined",
														density: "compact",
														disabled: Boolean(editingCard.value),
														"no-filter": "",
														clearable: ""
													}, {
														item: withCtx(({ props: itemProps, item }) => [createVNode(VListItem, itemProps, {
															append: withCtx(() => [item.raw.is_supplier ? (openBlock(), createBlock(VChip, {
																key: 0,
																size: "x-small",
																color: "#166534",
																variant: "tonal"
															}, {
																default: withCtx(() => [createTextVNode(" supplier ")]),
																_: 1
															})) : createCommentVNode("", true)]),
															_: 2
														}, 1040)]),
														_: 1
													}, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"search",
														"onUpdate:search",
														"items",
														"disabled"
													]),
													createVNode(VSelect, {
														modelValue: cardForm.supplier_pipeline_stage_id,
														"onUpdate:modelValue": ($event) => cardForm.supplier_pipeline_stage_id = $event,
														items: stages.value,
														"item-title": "name",
														"item-value": "id",
														label: "Стадия",
														variant: "outlined",
														density: "compact"
													}, null, 8, [
														"modelValue",
														"onUpdate:modelValue",
														"items"
													]),
													createVNode(VTextField, {
														modelValue: cardForm.title,
														"onUpdate:modelValue": ($event) => cardForm.title = $event,
														label: "Заголовок карточки",
														placeholder: "Если пусто, будет использовано название Unit",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextarea, {
														modelValue: cardForm.notes,
														"onUpdate:modelValue": ($event) => cardForm.notes = $event,
														label: "Заметки по работе",
														variant: "outlined",
														rows: "4"
													}, null, 8, ["modelValue", "onUpdate:modelValue"]),
													createVNode(VTextField, {
														modelValue: cardForm.next_contact_at,
														"onUpdate:modelValue": ($event) => cardForm.next_contact_at = $event,
														label: "Следующий контакт",
														type: "datetime-local",
														variant: "outlined",
														density: "compact"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])
												]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [
													editingCard.value ? (openBlock(), createBlock(VBtn, {
														key: 0,
														color: "error",
														variant: "tonal",
														onClick: ($event) => {
															deleteCard(editingCard.value);
															cardDialog.value = false;
														}
													}, {
														default: withCtx(() => [createTextVNode(" Удалить ")]),
														_: 1
													}, 8, ["onClick"])) : createCommentVNode("", true),
													createVNode(VSpacer),
													createVNode(VBtn, {
														variant: "text",
														onClick: ($event) => cardDialog.value = false
													}, {
														default: withCtx(() => [createTextVNode("Отмена")]),
														_: 1
													}, 8, ["onClick"]),
													createVNode(VBtn, {
														color: "#800000",
														loading: saving.value,
														onClick: saveCard
													}, {
														default: withCtx(() => [createTextVNode("Сохранить")]),
														_: 1
													}, 8, ["loading"])
												]),
												_: 1
											})
										];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCard, { rounded: "xl" }, {
									default: withCtx(() => [
										createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode(toDisplayString(editingCard.value ? "Редактировать карточку поставщика" : "Добавить поставщика"), 1)]),
											_: 1
										}),
										createVNode(VCardText, { class: "d-grid ga-3" }, {
											default: withCtx(() => [
												createVNode(VAutocomplete, {
													modelValue: cardForm.unit_id,
													"onUpdate:modelValue": ($event) => cardForm.unit_id = $event,
													search: unitSearch.value,
													"onUpdate:search": [($event) => unitSearch.value = $event, fetchUnitOptions],
													items: unitOptions.value,
													"item-title": "name",
													"item-value": "id",
													label: "Unit",
													variant: "outlined",
													density: "compact",
													disabled: Boolean(editingCard.value),
													"no-filter": "",
													clearable: ""
												}, {
													item: withCtx(({ props: itemProps, item }) => [createVNode(VListItem, itemProps, {
														append: withCtx(() => [item.raw.is_supplier ? (openBlock(), createBlock(VChip, {
															key: 0,
															size: "x-small",
															color: "#166534",
															variant: "tonal"
														}, {
															default: withCtx(() => [createTextVNode(" supplier ")]),
															_: 1
														})) : createCommentVNode("", true)]),
														_: 2
													}, 1040)]),
													_: 1
												}, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"search",
													"onUpdate:search",
													"items",
													"disabled"
												]),
												createVNode(VSelect, {
													modelValue: cardForm.supplier_pipeline_stage_id,
													"onUpdate:modelValue": ($event) => cardForm.supplier_pipeline_stage_id = $event,
													items: stages.value,
													"item-title": "name",
													"item-value": "id",
													label: "Стадия",
													variant: "outlined",
													density: "compact"
												}, null, 8, [
													"modelValue",
													"onUpdate:modelValue",
													"items"
												]),
												createVNode(VTextField, {
													modelValue: cardForm.title,
													"onUpdate:modelValue": ($event) => cardForm.title = $event,
													label: "Заголовок карточки",
													placeholder: "Если пусто, будет использовано название Unit",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextarea, {
													modelValue: cardForm.notes,
													"onUpdate:modelValue": ($event) => cardForm.notes = $event,
													label: "Заметки по работе",
													variant: "outlined",
													rows: "4"
												}, null, 8, ["modelValue", "onUpdate:modelValue"]),
												createVNode(VTextField, {
													modelValue: cardForm.next_contact_at,
													"onUpdate:modelValue": ($event) => cardForm.next_contact_at = $event,
													label: "Следующий контакт",
													type: "datetime-local",
													variant: "outlined",
													density: "compact"
												}, null, 8, ["modelValue", "onUpdate:modelValue"])
											]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [
												editingCard.value ? (openBlock(), createBlock(VBtn, {
													key: 0,
													color: "error",
													variant: "tonal",
													onClick: ($event) => {
														deleteCard(editingCard.value);
														cardDialog.value = false;
													}
												}, {
													default: withCtx(() => [createTextVNode(" Удалить ")]),
													_: 1
												}, 8, ["onClick"])) : createCommentVNode("", true),
												createVNode(VSpacer),
												createVNode(VBtn, {
													variant: "text",
													onClick: ($event) => cardDialog.value = false
												}, {
													default: withCtx(() => [createTextVNode("Отмена")]),
													_: 1
												}, 8, ["onClick"]),
												createVNode(VBtn, {
													color: "#800000",
													loading: saving.value,
													onClick: saveCard
												}, {
													default: withCtx(() => [createTextVNode("Сохранить")]),
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
						_push(ssrRenderComponent(VSnackbar, {
							modelValue: snackbar.show,
							"onUpdate:modelValue": ($event) => snackbar.show = $event,
							color: snackbar.color,
							timeout: "3500"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(`${ssrInterpolate(snackbar.text)}`);
								else return [createTextVNode(toDisplayString(snackbar.text), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode("div", { class: "supplier-work__hero" }, [createVNode("div", null, [
							createVNode("div", { class: "supplier-work__eyebrow" }, "Ameise CRM"),
							createVNode("h1", { class: "supplier-work__title" }, "Поставщики: воронки и рабочая доска"),
							createVNode("p", { class: "supplier-work__subtitle" }, " Переносите карточки поставщиков по стадиям drag-and-drop, ведите заметки и управляйте структурой воронок без ухода со страницы. ")
						]), createVNode("div", { class: "supplier-work__metrics" }, [
							createVNode("div", { class: "supplier-work__metric" }, [createVNode("span", null, toDisplayString(pipelineItems.value.length), 1), createVNode("small", null, "воронок")]),
							createVNode("div", { class: "supplier-work__metric" }, [createVNode("span", null, toDisplayString(stages.value.length), 1), createVNode("small", null, "стадий")]),
							createVNode("div", { class: "supplier-work__metric" }, [createVNode("span", null, toDisplayString(totalCards.value), 1), createVNode("small", null, "карточек")])
						])]),
						createVNode(VCard, {
							rounded: "xl",
							elevation: "0",
							class: "supplier-work__toolbar mb-4"
						}, {
							default: withCtx(() => [createVNode(VRow, {
								align: "center",
								dense: ""
							}, {
								default: withCtx(() => [createVNode(VCol, {
									cols: "12",
									lg: "5"
								}, {
									default: withCtx(() => [createVNode(VSelect, {
										"model-value": selectedPipelineId.value,
										items: pipelineItems.value,
										"item-title": "name",
										"item-value": "id",
										label: "Воронка",
										density: "compact",
										variant: "outlined",
										"hide-details": "",
										loading: loading.value,
										"onUpdate:modelValue": onPipelineSelected
									}, null, 8, [
										"model-value",
										"items",
										"loading"
									])]),
									_: 1
								}), createVNode(VCol, {
									cols: "12",
									lg: "7",
									class: "supplier-work__toolbar-actions"
								}, {
									default: withCtx(() => [
										createVNode(VBtn, {
											color: "#800000",
											"prepend-icon": "mdi-plus",
											rounded: "lg",
											onClick: ($event) => openPipelineDialog()
										}, {
											default: withCtx(() => [createTextVNode(" Воронка ")]),
											_: 1
										}, 8, ["onClick"]),
										createVNode(VBtn, {
											color: "#b45309",
											variant: "tonal",
											"prepend-icon": "mdi-timeline-plus",
											rounded: "lg",
											disabled: !hasPipeline.value,
											onClick: ($event) => openStageDialog()
										}, {
											default: withCtx(() => [createTextVNode(" Стадия ")]),
											_: 1
										}, 8, ["disabled", "onClick"]),
										createVNode(VBtn, {
											color: "#166534",
											variant: "tonal",
											"prepend-icon": "mdi-account-plus",
											rounded: "lg",
											disabled: !hasPipeline.value || !stages.value.length,
											onClick: ($event) => openCardDialog(stages.value[0])
										}, {
											default: withCtx(() => [createTextVNode(" Поставщик ")]),
											_: 1
										}, 8, ["disabled", "onClick"]),
										createVNode(VBtn, {
											icon: "mdi-refresh",
											variant: "text",
											loading: loading.value,
											onClick: ($event) => fetchBoard(selectedPipelineId.value)
										}, null, 8, ["loading", "onClick"])
									]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						}),
						!hasPipeline.value ? (openBlock(), createBlock(VAlert, {
							key: 0,
							type: "info",
							variant: "tonal",
							rounded: "xl",
							class: "mb-4"
						}, {
							default: withCtx(() => [createTextVNode(" Воронок поставщиков пока нет. Создайте первую воронку, затем добавьте стадии и карточки Units-поставщиков. ")]),
							_: 1
						})) : (openBlock(), createBlock(VRow, {
							key: 1,
							align: "start"
						}, {
							default: withCtx(() => [createVNode(VCol, {
								cols: "12",
								xl: "9"
							}, {
								default: withCtx(() => [loading.value ? (openBlock(), createBlock(VProgressLinear, {
									key: 0,
									indeterminate: "",
									color: "#800000",
									class: "mb-3"
								})) : createCommentVNode("", true), stages.value.length ? (openBlock(), createBlock("div", {
									key: 1,
									class: "supplier-board"
								}, [(openBlock(true), createBlock(Fragment, null, renderList(stages.value, (stage) => {
									return openBlock(), createBlock("section", {
										key: stage.id,
										class: "supplier-board__stage"
									}, [
										createVNode("header", { class: "supplier-board__stage-header" }, [createVNode("div", { class: "supplier-board__stage-main" }, [createVNode("span", {
											class: "supplier-board__stage-dot",
											style: { backgroundColor: stage.color || "#800000" }
										}, null, 4), createVNode("div", null, [createVNode("h2", null, toDisplayString(stage.name), 1), createVNode("p", null, toDisplayString(stage.cards?.length || 0) + " карточек", 1)])]), createVNode(VMenu, null, {
											activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps({ ref_for: true }, menuProps, {
												icon: "mdi-dots-horizontal",
												size: "small",
												variant: "text"
											}), null, 16)]),
											default: withCtx(() => [createVNode(VList, { density: "compact" }, {
												default: withCtx(() => [
													createVNode(VListItem, { onClick: ($event) => openCardDialog(stage) }, {
														default: withCtx(() => [createVNode(VListItemTitle, null, {
															default: withCtx(() => [createTextVNode("Добавить поставщика")]),
															_: 1
														})]),
														_: 1
													}, 8, ["onClick"]),
													createVNode(VListItem, { onClick: ($event) => openStageDialog(stage) }, {
														default: withCtx(() => [createVNode(VListItemTitle, null, {
															default: withCtx(() => [createTextVNode("Редактировать стадию")]),
															_: 1
														})]),
														_: 1
													}, 8, ["onClick"]),
													createVNode(VListItem, { onClick: ($event) => reorderStages(stage, -1) }, {
														default: withCtx(() => [createVNode(VListItemTitle, null, {
															default: withCtx(() => [createTextVNode("Поднять выше")]),
															_: 1
														})]),
														_: 1
													}, 8, ["onClick"]),
													createVNode(VListItem, { onClick: ($event) => reorderStages(stage, 1) }, {
														default: withCtx(() => [createVNode(VListItemTitle, null, {
															default: withCtx(() => [createTextVNode("Опустить ниже")]),
															_: 1
														})]),
														_: 1
													}, 8, ["onClick"]),
													createVNode(VListItem, { onClick: ($event) => deleteStage(stage) }, {
														default: withCtx(() => [createVNode(VListItemTitle, { class: "text-error" }, {
															default: withCtx(() => [createTextVNode("Удалить стадию")]),
															_: 1
														})]),
														_: 1
													}, 8, ["onClick"])
												]),
												_: 2
											}, 1024)]),
											_: 2
										}, 1024)]),
										createVNode(unref(VueDraggableNext), {
											modelValue: stage.cards,
											"onUpdate:modelValue": ($event) => stage.cards = $event,
											group: "supplier-cards",
											"item-key": "id",
											class: "supplier-board__dropzone",
											"ghost-class": "supplier-board__ghost",
											"chosen-class": "supplier-board__chosen",
											onChange: (event) => onCardChange(stage, event)
										}, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(stage.cards, (card) => {
												return openBlock(), createBlock("article", {
													key: card.id,
													class: "supplier-card"
												}, [
													createVNode("div", { class: "supplier-card__topline" }, [createVNode(VChip, {
														size: "x-small",
														color: "#800000",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(" Unit #" + toDisplayString(card.unit_id), 1)]),
														_: 2
													}, 1024), createVNode(VBtn, {
														icon: "mdi-pencil",
														size: "x-small",
														variant: "text",
														onClick: ($event) => openCardDialog(stage, card)
													}, null, 8, ["onClick"])]),
													createVNode("h3", null, toDisplayString(cardTitle(card)), 1),
													card.unit?.id ? (openBlock(), createBlock(unref(Link), {
														key: 0,
														href: unref(route)("web.unit.show", card.unit.id),
														class: "supplier-card__unit-link"
													}, {
														default: withCtx(() => [createTextVNode(toDisplayString(card.unit.name), 1)]),
														_: 2
													}, 1032, ["href"])) : createCommentVNode("", true),
													card.notes ? (openBlock(), createBlock("p", {
														key: 1,
														class: "supplier-card__notes"
													}, toDisplayString(shortText(card.notes)), 1)) : createCommentVNode("", true),
													createVNode("div", { class: "supplier-card__tags" }, [(openBlock(true), createBlock(Fragment, null, renderList(card.unit?.labels || [], (label) => {
														return openBlock(), createBlock(VChip, {
															key: label.id,
															size: "x-small",
															variant: "outlined"
														}, {
															default: withCtx(() => [createTextVNode(toDisplayString(label.name), 1)]),
															_: 2
														}, 1024);
													}), 128)), (openBlock(true), createBlock(Fragment, null, renderList(card.unit?.cities || [], (city) => {
														return openBlock(), createBlock(VChip, {
															key: `city-${city.id}`,
															size: "x-small",
															color: "#0f766e",
															variant: "tonal"
														}, {
															default: withCtx(() => [createTextVNode(toDisplayString(city.name), 1)]),
															_: 2
														}, 1024);
													}), 128))]),
													card.next_contact_at ? (openBlock(), createBlock("div", {
														key: 2,
														class: "supplier-card__footer"
													}, [createVNode(VIcon, {
														icon: "mdi-calendar-clock",
														size: "small"
													}), createVNode("span", null, toDisplayString(formatDate(card.next_contact_at)), 1)])) : createCommentVNode("", true)
												]);
											}), 128))]),
											_: 2
										}, 1032, [
											"modelValue",
											"onUpdate:modelValue",
											"onChange"
										]),
										createVNode(VBtn, {
											block: "",
											variant: "tonal",
											color: "#800000",
											rounded: "lg",
											"prepend-icon": "mdi-plus",
											class: "mt-3",
											onClick: ($event) => openCardDialog(stage)
										}, {
											default: withCtx(() => [createTextVNode(" Добавить ")]),
											_: 1
										}, 8, ["onClick"])
									]);
								}), 128))])) : (openBlock(), createBlock(VAlert, {
									key: 2,
									type: "warning",
									variant: "tonal",
									rounded: "xl"
								}, {
									default: withCtx(() => [createTextVNode(" В этой воронке нет стадий. Добавьте хотя бы одну стадию, чтобы начать работу с карточками поставщиков. ")]),
									_: 1
								}))]),
								_: 1
							}), createVNode(VCol, {
								cols: "12",
								xl: "3"
							}, {
								default: withCtx(() => [createVNode(VCard, {
									rounded: "xl",
									elevation: "0",
									class: "supplier-side-card mb-4"
								}, {
									default: withCtx(() => [
										createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
											default: withCtx(() => [createTextVNode(" Управление воронкой ")]),
											_: 1
										}),
										createVNode(VCardSubtitle, null, {
											default: withCtx(() => [createTextVNode(" CRUD воронки и стадий ")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [createVNode("div", { class: "supplier-side-card__current" }, [createVNode("strong", null, toDisplayString(currentPipeline.value.name), 1), createVNode("span", null, toDisplayString(currentPipeline.value.description || "Описание не заполнено"), 1)]), createVNode("div", { class: "supplier-side-card__actions" }, [createVNode(VBtn, {
												block: "",
												color: "#800000",
												variant: "tonal",
												"prepend-icon": "mdi-pencil",
												onClick: ($event) => openPipelineDialog(currentPipeline.value)
											}, {
												default: withCtx(() => [createTextVNode(" Редактировать воронку ")]),
												_: 1
											}, 8, ["onClick"]), createVNode(VBtn, {
												block: "",
												color: "error",
												variant: "tonal",
												"prepend-icon": "mdi-delete",
												onClick: ($event) => deletePipeline(currentPipeline.value)
											}, {
												default: withCtx(() => [createTextVNode(" Удалить воронку ")]),
												_: 1
											}, 8, ["onClick"])])]),
											_: 1
										})
									]),
									_: 1
								}), createVNode(VCard, {
									rounded: "xl",
									elevation: "0",
									class: "supplier-side-card"
								}, {
									default: withCtx(() => [createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
										default: withCtx(() => [createTextVNode(" Стадии ")]),
										_: 1
									}), createVNode(VCardText, null, {
										default: withCtx(() => [createVNode("div", { class: "supplier-stage-list" }, [(openBlock(true), createBlock(Fragment, null, renderList(stages.value, (stage) => {
											return openBlock(), createBlock("div", {
												key: `side-${stage.id}`,
												class: "supplier-stage-list__item"
											}, [
												createVNode("span", {
													class: "supplier-board__stage-dot",
													style: { backgroundColor: stage.color || "#800000" }
												}, null, 4),
												createVNode("div", null, [createVNode("strong", null, toDisplayString(stage.name), 1), createVNode("small", null, toDisplayString(stage.cards?.length || 0) + " карточек", 1)]),
												createVNode(VBtn, {
													icon: "mdi-pencil",
													size: "x-small",
													variant: "text",
													onClick: ($event) => openStageDialog(stage)
												}, null, 8, ["onClick"])
											]);
										}), 128))])]),
										_: 1
									})]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						})),
						createVNode(VDialog, {
							modelValue: pipelineDialog.value,
							"onUpdate:modelValue": ($event) => pipelineDialog.value = $event,
							"max-width": "620"
						}, {
							default: withCtx(() => [createVNode(VCard, { rounded: "xl" }, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode(toDisplayString(editingPipeline.value ? "Редактировать воронку" : "Новая воронка"), 1)]),
										_: 1
									}),
									createVNode(VCardText, { class: "d-grid ga-3" }, {
										default: withCtx(() => [
											createVNode(VTextField, {
												modelValue: pipelineForm.name,
												"onUpdate:modelValue": ($event) => pipelineForm.name = $event,
												label: "Название",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VTextarea, {
												modelValue: pipelineForm.description,
												"onUpdate:modelValue": ($event) => pipelineForm.description = $event,
												label: "Описание",
												variant: "outlined",
												rows: "3"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VTextField, {
												modelValue: pipelineForm.sort_order,
												"onUpdate:modelValue": ($event) => pipelineForm.sort_order = $event,
												modelModifiers: { number: true },
												label: "Порядок",
												type: "number",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VSwitch, {
												modelValue: pipelineForm.is_active,
												"onUpdate:modelValue": ($event) => pipelineForm.is_active = $event,
												color: "#800000",
												label: "Активная воронка",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])
										]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [
											createVNode(VSpacer),
											createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => pipelineDialog.value = false
											}, {
												default: withCtx(() => [createTextVNode("Отмена")]),
												_: 1
											}, 8, ["onClick"]),
											createVNode(VBtn, {
												color: "#800000",
												loading: saving.value,
												onClick: savePipeline
											}, {
												default: withCtx(() => [createTextVNode("Сохранить")]),
												_: 1
											}, 8, ["loading"])
										]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VDialog, {
							modelValue: stageDialog.value,
							"onUpdate:modelValue": ($event) => stageDialog.value = $event,
							"max-width": "620"
						}, {
							default: withCtx(() => [createVNode(VCard, { rounded: "xl" }, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode(toDisplayString(editingStage.value ? "Редактировать стадию" : "Новая стадия"), 1)]),
										_: 1
									}),
									createVNode(VCardText, { class: "d-grid ga-3" }, {
										default: withCtx(() => [
											createVNode(VTextField, {
												modelValue: stageForm.name,
												"onUpdate:modelValue": ($event) => stageForm.name = $event,
												label: "Название",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VTextField, {
												modelValue: stageForm.color,
												"onUpdate:modelValue": ($event) => stageForm.color = $event,
												label: "Цвет",
												type: "color",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VTextField, {
												modelValue: stageForm.sort_order,
												"onUpdate:modelValue": ($event) => stageForm.sort_order = $event,
												modelModifiers: { number: true },
												label: "Порядок",
												type: "number",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VTextarea, {
												modelValue: stageForm.description,
												"onUpdate:modelValue": ($event) => stageForm.description = $event,
												label: "Описание",
												variant: "outlined",
												rows: "3"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])
										]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [
											createVNode(VSpacer),
											createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => stageDialog.value = false
											}, {
												default: withCtx(() => [createTextVNode("Отмена")]),
												_: 1
											}, 8, ["onClick"]),
											createVNode(VBtn, {
												color: "#800000",
												loading: saving.value,
												onClick: saveStage
											}, {
												default: withCtx(() => [createTextVNode("Сохранить")]),
												_: 1
											}, 8, ["loading"])
										]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VDialog, {
							modelValue: cardDialog.value,
							"onUpdate:modelValue": ($event) => cardDialog.value = $event,
							"max-width": "720"
						}, {
							default: withCtx(() => [createVNode(VCard, { rounded: "xl" }, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode(toDisplayString(editingCard.value ? "Редактировать карточку поставщика" : "Добавить поставщика"), 1)]),
										_: 1
									}),
									createVNode(VCardText, { class: "d-grid ga-3" }, {
										default: withCtx(() => [
											createVNode(VAutocomplete, {
												modelValue: cardForm.unit_id,
												"onUpdate:modelValue": ($event) => cardForm.unit_id = $event,
												search: unitSearch.value,
												"onUpdate:search": [($event) => unitSearch.value = $event, fetchUnitOptions],
												items: unitOptions.value,
												"item-title": "name",
												"item-value": "id",
												label: "Unit",
												variant: "outlined",
												density: "compact",
												disabled: Boolean(editingCard.value),
												"no-filter": "",
												clearable: ""
											}, {
												item: withCtx(({ props: itemProps, item }) => [createVNode(VListItem, itemProps, {
													append: withCtx(() => [item.raw.is_supplier ? (openBlock(), createBlock(VChip, {
														key: 0,
														size: "x-small",
														color: "#166534",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(" supplier ")]),
														_: 1
													})) : createCommentVNode("", true)]),
													_: 2
												}, 1040)]),
												_: 1
											}, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"search",
												"onUpdate:search",
												"items",
												"disabled"
											]),
											createVNode(VSelect, {
												modelValue: cardForm.supplier_pipeline_stage_id,
												"onUpdate:modelValue": ($event) => cardForm.supplier_pipeline_stage_id = $event,
												items: stages.value,
												"item-title": "name",
												"item-value": "id",
												label: "Стадия",
												variant: "outlined",
												density: "compact"
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"items"
											]),
											createVNode(VTextField, {
												modelValue: cardForm.title,
												"onUpdate:modelValue": ($event) => cardForm.title = $event,
												label: "Заголовок карточки",
												placeholder: "Если пусто, будет использовано название Unit",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VTextarea, {
												modelValue: cardForm.notes,
												"onUpdate:modelValue": ($event) => cardForm.notes = $event,
												label: "Заметки по работе",
												variant: "outlined",
												rows: "4"
											}, null, 8, ["modelValue", "onUpdate:modelValue"]),
											createVNode(VTextField, {
												modelValue: cardForm.next_contact_at,
												"onUpdate:modelValue": ($event) => cardForm.next_contact_at = $event,
												label: "Следующий контакт",
												type: "datetime-local",
												variant: "outlined",
												density: "compact"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])
										]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [
											editingCard.value ? (openBlock(), createBlock(VBtn, {
												key: 0,
												color: "error",
												variant: "tonal",
												onClick: ($event) => {
													deleteCard(editingCard.value);
													cardDialog.value = false;
												}
											}, {
												default: withCtx(() => [createTextVNode(" Удалить ")]),
												_: 1
											}, 8, ["onClick"])) : createCommentVNode("", true),
											createVNode(VSpacer),
											createVNode(VBtn, {
												variant: "text",
												onClick: ($event) => cardDialog.value = false
											}, {
												default: withCtx(() => [createTextVNode("Отмена")]),
												_: 1
											}, 8, ["onClick"]),
											createVNode(VBtn, {
												color: "#800000",
												loading: saving.value,
												onClick: saveCard
											}, {
												default: withCtx(() => [createTextVNode("Сохранить")]),
												_: 1
											}, 8, ["loading"])
										]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"]),
						createVNode(VSnackbar, {
							modelValue: snackbar.show,
							"onUpdate:modelValue": ($event) => snackbar.show = $event,
							color: snackbar.color,
							timeout: "3500"
						}, {
							default: withCtx(() => [createTextVNode(toDisplayString(snackbar.text), 1)]),
							_: 1
						}, 8, [
							"modelValue",
							"onUpdate:modelValue",
							"color"
						])
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/WorkBoard.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var WorkBoard_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-0088517b"]]);
//#endregion
export { WorkBoard_default as default };
