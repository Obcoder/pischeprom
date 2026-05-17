import { C as VRow, D as VDataTable, F as VCardText, H as VSelect, I as VCardTitle, K as VDivider, M as VWindowItem, N as VWindow, O as VTable, P as VCard, T as VContainer, U as VTextField, X as VChip, _ as VExpansionPanels, a as VTabs, b as VExpansionPanelText, c as VTab, d as VSkeletonLoader, et as VAlert, it as VProgressLinear, ot as VIcon, rt as VBtn, v as VExpansionPanel, w as VCol, y as VExpansionPanelTitle } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { t as _sfc_main$2 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, mergeProps, onBeforeUnmount, onMounted, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, watch, withCtx } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderAttr, ssrRenderComponent, ssrRenderList, ssrRenderStyle } from "vue/server-renderer";
//#region resources/js/Components/ProductYandexSearchCard.vue
var _sfc_main$1 = {
	__name: "ProductYandexSearchCard",
	__ssrInlineRender: true,
	props: {
		productId: {
			type: Number,
			required: true
		},
		productName: {
			type: String,
			default: ""
		}
	},
	setup(__props) {
		const props = __props;
		const headers = [
			{
				title: "#",
				key: "position",
				width: 70
			},
			{
				title: "Заголовок",
				key: "title"
			},
			{
				title: "URL",
				key: "url"
			},
			{
				title: "Сниппет",
				key: "snippet"
			}
		];
		const query = ref(props.productName ? `${props.productName} купить` : "");
		const maxResults = ref(100);
		const request = ref(null);
		const results = ref([]);
		const isSubmitting = ref(false);
		const isPolling = ref(false);
		const isLoadingInitial = ref(false);
		let pollTimer = null;
		watch(() => props.productName, (newValue) => {
			if ((!query.value || query.value === "undefined купить") && newValue) query.value = `${newValue} купить`;
		}, { immediate: true });
		function shortUrl(url) {
			if (!url) return "—";
			return url.length > 70 ? url.slice(0, 70) + "…" : url;
		}
		async function loadLatest() {
			isLoadingInitial.value = true;
			try {
				const { data } = await axios.get(`/api/products/${props.productId}/yandex-search/latest`);
				request.value = data.request;
				results.value = data.results || [];
				if (request.value && ["queued", "processing"].includes(request.value.status)) startPolling();
			} finally {
				isLoadingInitial.value = false;
			}
		}
		async function runSearch() {
			isSubmitting.value = true;
			try {
				const { data } = await axios.post(`/api/products/${props.productId}/yandex-search`, {
					query: query.value,
					max_results: maxResults.value
				});
				request.value = {
					id: data.request_id,
					status: data.status,
					query: data.query,
					results_count: 0,
					error_message: null
				};
				results.value = [];
				startPolling();
			} finally {
				isSubmitting.value = false;
			}
		}
		async function pollLatest() {
			const { data } = await axios.get(`/api/products/${props.productId}/yandex-search/latest`);
			request.value = data.request;
			results.value = data.results || [];
			if (!request.value) {
				stopPolling();
				return;
			}
			if (!["queued", "processing"].includes(request.value.status)) stopPolling();
		}
		function startPolling() {
			if (pollTimer) return;
			isPolling.value = true;
			pollTimer = setInterval(async () => {
				try {
					await pollLatest();
				} catch (e) {
					console.error(e);
					stopPolling();
				}
			}, 3e3);
		}
		function stopPolling() {
			isPolling.value = false;
			if (pollTimer) {
				clearInterval(pollTimer);
				pollTimer = null;
			}
		}
		onMounted(() => {
			loadLatest();
		});
		onBeforeUnmount(() => {
			stopPolling();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, mergeProps({ class: "mt-4" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<span${_scopeId}>Выдача Яндекса</span>`);
									_push(ssrRenderComponent(VBtn, {
										color: "primary",
										loading: isSubmitting.value,
										disabled: isSubmitting.value || isPolling.value,
										onClick: runSearch
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Получить выдачу Яндекса `);
											else return [createTextVNode(" Получить выдачу Яндекса ")];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode("span", null, "Выдача Яндекса"), createVNode(VBtn, {
									color: "primary",
									loading: isSubmitting.value,
									disabled: isSubmitting.value || isPolling.value,
									onClick: runSearch
								}, {
									default: withCtx(() => [createTextVNode(" Получить выдачу Яндекса ")]),
									_: 1
								}, 8, ["loading", "disabled"])];
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
													md: "8"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VTextField, {
															modelValue: query.value,
															"onUpdate:modelValue": ($event) => query.value = $event,
															label: "Поисковый запрос",
															variant: "outlined",
															density: "comfortable",
															"hide-details": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VTextField, {
															modelValue: query.value,
															"onUpdate:modelValue": ($event) => query.value = $event,
															label: "Поисковый запрос",
															variant: "outlined",
															density: "comfortable",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VCol, {
													cols: "12",
													md: "4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VSelect, {
															modelValue: maxResults.value,
															"onUpdate:modelValue": ($event) => maxResults.value = $event,
															items: [
																10,
																20,
																30,
																50,
																100
															],
															label: "Количество результатов",
															variant: "outlined",
															density: "comfortable",
															"hide-details": ""
														}, null, _parent, _scopeId));
														else return [createVNode(VSelect, {
															modelValue: maxResults.value,
															"onUpdate:modelValue": ($event) => maxResults.value = $event,
															items: [
																10,
																20,
																30,
																50,
																100
															],
															label: "Количество результатов",
															variant: "outlined",
															density: "comfortable",
															"hide-details": ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [createVNode(VCol, {
												cols: "12",
												md: "8"
											}, {
												default: withCtx(() => [createVNode(VTextField, {
													modelValue: query.value,
													"onUpdate:modelValue": ($event) => query.value = $event,
													label: "Поисковый запрос",
													variant: "outlined",
													density: "comfortable",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}), createVNode(VCol, {
												cols: "12",
												md: "4"
											}, {
												default: withCtx(() => [createVNode(VSelect, {
													modelValue: maxResults.value,
													"onUpdate:modelValue": ($event) => maxResults.value = $event,
													items: [
														10,
														20,
														30,
														50,
														100
													],
													label: "Количество результатов",
													variant: "outlined",
													density: "comfortable",
													"hide-details": ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									if (request.value) {
										_push(`<div class="mt-4"${_scopeId}>`);
										if (request.value.status === "queued") _push(ssrRenderComponent(VAlert, {
											type: "info",
											variant: "tonal"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(` Задача поставлена в очередь. `);
												else return [createTextVNode(" Задача поставлена в очередь. ")];
											}),
											_: 1
										}, _parent, _scopeId));
										else if (request.value.status === "processing") _push(ssrRenderComponent(VAlert, {
											type: "warning",
											variant: "tonal"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(` Идёт сбор результатов... `);
												else return [createTextVNode(" Идёт сбор результатов... ")];
											}),
											_: 1
										}, _parent, _scopeId));
										else if (request.value.status === "done") _push(ssrRenderComponent(VAlert, {
											type: "success",
											variant: "tonal"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(` Готово. Получено результатов: ${ssrInterpolate(request.value.results_count)}`);
												else return [createTextVNode(" Готово. Получено результатов: " + toDisplayString(request.value.results_count), 1)];
											}),
											_: 1
										}, _parent, _scopeId));
										else if (request.value.status === "failed") _push(ssrRenderComponent(VAlert, {
											type: "error",
											variant: "tonal"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(` Ошибка: ${ssrInterpolate(request.value.error_message || "Неизвестная ошибка")}`);
												else return [createTextVNode(" Ошибка: " + toDisplayString(request.value.error_message || "Неизвестная ошибка"), 1)];
											}),
											_: 1
										}, _parent, _scopeId));
										else _push(`<!---->`);
										_push(`</div>`);
									} else _push(`<!---->`);
									if (isPolling.value) _push(ssrRenderComponent(VProgressLinear, {
										indeterminate: "",
										class: "mt-4"
									}, null, _parent, _scopeId));
									else _push(`<!---->`);
									_push(ssrRenderComponent(VDataTable, {
										class: "mt-4",
										headers,
										items: results.value,
										loading: isLoadingInitial.value,
										"items-per-page": 20,
										"items-per-page-options": [
											10,
											20,
											50,
											100
										]
									}, {
										"item.title": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`<div class="py-2"${_scopeId}><a${ssrRenderAttr("href", item.url)} target="_blank" rel="noopener noreferrer" class="text-decoration-none"${_scopeId}>${ssrInterpolate(item.title || item.url)}</a><div class="text-caption text-medium-emphasis mt-1"${_scopeId}>${ssrInterpolate(item.domain)}</div></div>`);
											else return [createVNode("div", { class: "py-2" }, [createVNode("a", {
												href: item.url,
												target: "_blank",
												rel: "noopener noreferrer",
												class: "text-decoration-none"
											}, toDisplayString(item.title || item.url), 9, ["href"]), createVNode("div", { class: "text-caption text-medium-emphasis mt-1" }, toDisplayString(item.domain), 1)])];
										}),
										"item.url": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`<a${ssrRenderAttr("href", item.url)} target="_blank" rel="noopener noreferrer"${_scopeId}>${ssrInterpolate(shortUrl(item.url))}</a>`);
											else return [createVNode("a", {
												href: item.url,
												target: "_blank",
												rel: "noopener noreferrer"
											}, toDisplayString(shortUrl(item.url)), 9, ["href"])];
										}),
										"item.snippet": withCtx(({ item }, _push, _parent, _scopeId) => {
											if (_push) _push(`<div style="${ssrRenderStyle({ "white-space": "pre-line" })}"${_scopeId}>${ssrInterpolate(item.snippet || "—")}</div>`);
											else return [createVNode("div", { style: { "white-space": "pre-line" } }, toDisplayString(item.snippet || "—"), 1)];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [
									createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, {
											cols: "12",
											md: "8"
										}, {
											default: withCtx(() => [createVNode(VTextField, {
												modelValue: query.value,
												"onUpdate:modelValue": ($event) => query.value = $event,
												label: "Поисковый запрос",
												variant: "outlined",
												density: "comfortable",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}), createVNode(VCol, {
											cols: "12",
											md: "4"
										}, {
											default: withCtx(() => [createVNode(VSelect, {
												modelValue: maxResults.value,
												"onUpdate:modelValue": ($event) => maxResults.value = $event,
												items: [
													10,
													20,
													30,
													50,
													100
												],
												label: "Количество результатов",
												variant: "outlined",
												density: "comfortable",
												"hide-details": ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})]),
										_: 1
									}),
									request.value ? (openBlock(), createBlock("div", {
										key: 0,
										class: "mt-4"
									}, [request.value.status === "queued" ? (openBlock(), createBlock(VAlert, {
										key: 0,
										type: "info",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(" Задача поставлена в очередь. ")]),
										_: 1
									})) : request.value.status === "processing" ? (openBlock(), createBlock(VAlert, {
										key: 1,
										type: "warning",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(" Идёт сбор результатов... ")]),
										_: 1
									})) : request.value.status === "done" ? (openBlock(), createBlock(VAlert, {
										key: 2,
										type: "success",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(" Готово. Получено результатов: " + toDisplayString(request.value.results_count), 1)]),
										_: 1
									})) : request.value.status === "failed" ? (openBlock(), createBlock(VAlert, {
										key: 3,
										type: "error",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(" Ошибка: " + toDisplayString(request.value.error_message || "Неизвестная ошибка"), 1)]),
										_: 1
									})) : createCommentVNode("", true)])) : createCommentVNode("", true),
									isPolling.value ? (openBlock(), createBlock(VProgressLinear, {
										key: 1,
										indeterminate: "",
										class: "mt-4"
									})) : createCommentVNode("", true),
									createVNode(VDataTable, {
										class: "mt-4",
										headers,
										items: results.value,
										loading: isLoadingInitial.value,
										"items-per-page": 20,
										"items-per-page-options": [
											10,
											20,
											50,
											100
										]
									}, {
										"item.title": withCtx(({ item }) => [createVNode("div", { class: "py-2" }, [createVNode("a", {
											href: item.url,
											target: "_blank",
											rel: "noopener noreferrer",
											class: "text-decoration-none"
										}, toDisplayString(item.title || item.url), 9, ["href"]), createVNode("div", { class: "text-caption text-medium-emphasis mt-1" }, toDisplayString(item.domain), 1)])]),
										"item.url": withCtx(({ item }) => [createVNode("a", {
											href: item.url,
											target: "_blank",
											rel: "noopener noreferrer"
										}, toDisplayString(shortUrl(item.url)), 9, ["href"])]),
										"item.snippet": withCtx(({ item }) => [createVNode("div", { style: { "white-space": "pre-line" } }, toDisplayString(item.snippet || "—"), 1)]),
										_: 1
									}, 8, ["items", "loading"])
								];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VCardTitle, { class: "d-flex align-center justify-space-between" }, {
						default: withCtx(() => [createVNode("span", null, "Выдача Яндекса"), createVNode(VBtn, {
							color: "primary",
							loading: isSubmitting.value,
							disabled: isSubmitting.value || isPolling.value,
							onClick: runSearch
						}, {
							default: withCtx(() => [createTextVNode(" Получить выдачу Яндекса ")]),
							_: 1
						}, 8, ["loading", "disabled"])]),
						_: 1
					}), createVNode(VCardText, null, {
						default: withCtx(() => [
							createVNode(VRow, null, {
								default: withCtx(() => [createVNode(VCol, {
									cols: "12",
									md: "8"
								}, {
									default: withCtx(() => [createVNode(VTextField, {
										modelValue: query.value,
										"onUpdate:modelValue": ($event) => query.value = $event,
										label: "Поисковый запрос",
										variant: "outlined",
										density: "comfortable",
										"hide-details": ""
									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
									_: 1
								}), createVNode(VCol, {
									cols: "12",
									md: "4"
								}, {
									default: withCtx(() => [createVNode(VSelect, {
										modelValue: maxResults.value,
										"onUpdate:modelValue": ($event) => maxResults.value = $event,
										items: [
											10,
											20,
											30,
											50,
											100
										],
										label: "Количество результатов",
										variant: "outlined",
										density: "comfortable",
										"hide-details": ""
									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
									_: 1
								})]),
								_: 1
							}),
							request.value ? (openBlock(), createBlock("div", {
								key: 0,
								class: "mt-4"
							}, [request.value.status === "queued" ? (openBlock(), createBlock(VAlert, {
								key: 0,
								type: "info",
								variant: "tonal"
							}, {
								default: withCtx(() => [createTextVNode(" Задача поставлена в очередь. ")]),
								_: 1
							})) : request.value.status === "processing" ? (openBlock(), createBlock(VAlert, {
								key: 1,
								type: "warning",
								variant: "tonal"
							}, {
								default: withCtx(() => [createTextVNode(" Идёт сбор результатов... ")]),
								_: 1
							})) : request.value.status === "done" ? (openBlock(), createBlock(VAlert, {
								key: 2,
								type: "success",
								variant: "tonal"
							}, {
								default: withCtx(() => [createTextVNode(" Готово. Получено результатов: " + toDisplayString(request.value.results_count), 1)]),
								_: 1
							})) : request.value.status === "failed" ? (openBlock(), createBlock(VAlert, {
								key: 3,
								type: "error",
								variant: "tonal"
							}, {
								default: withCtx(() => [createTextVNode(" Ошибка: " + toDisplayString(request.value.error_message || "Неизвестная ошибка"), 1)]),
								_: 1
							})) : createCommentVNode("", true)])) : createCommentVNode("", true),
							isPolling.value ? (openBlock(), createBlock(VProgressLinear, {
								key: 1,
								indeterminate: "",
								class: "mt-4"
							})) : createCommentVNode("", true),
							createVNode(VDataTable, {
								class: "mt-4",
								headers,
								items: results.value,
								loading: isLoadingInitial.value,
								"items-per-page": 20,
								"items-per-page-options": [
									10,
									20,
									50,
									100
								]
							}, {
								"item.title": withCtx(({ item }) => [createVNode("div", { class: "py-2" }, [createVNode("a", {
									href: item.url,
									target: "_blank",
									rel: "noopener noreferrer",
									class: "text-decoration-none"
								}, toDisplayString(item.title || item.url), 9, ["href"]), createVNode("div", { class: "text-caption text-medium-emphasis mt-1" }, toDisplayString(item.domain), 1)])]),
								"item.url": withCtx(({ item }) => [createVNode("a", {
									href: item.url,
									target: "_blank",
									rel: "noopener noreferrer"
								}, toDisplayString(shortUrl(item.url)), 9, ["href"])]),
								"item.snippet": withCtx(({ item }) => [createVNode("div", { style: { "white-space": "pre-line" } }, toDisplayString(item.snippet || "—"), 1)]),
								_: 1
							}, 8, ["items", "loading"])
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/ProductYandexSearchCard.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Pages/Ameise/Product_02.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$2 }, {
	__name: "Product_02",
	__ssrInlineRender: true,
	props: { id: {
		type: [Number, String],
		required: true
	} },
	setup(__props) {
		const props = __props;
		const loading = ref(false);
		const error = ref("");
		const product = ref(null);
		const tab = ref("main");
		const productTitle = computed(() => {
			const p = product.value;
			if (!p) return "Продукт";
			return p.rus || p.eng || `Продукт #${p.id}`;
		});
		const languageRows = computed(() => {
			const p = product.value || {};
			return [
				{
					key: "rus",
					label: "Русский",
					value: p.rus
				},
				{
					key: "eng",
					label: "English",
					value: p.eng
				},
				{
					key: "zh",
					label: "中文",
					value: p.zh
				},
				{
					key: "es",
					label: "Español",
					value: p.es
				},
				{
					key: "ar",
					label: "العربية",
					value: p.ar
				},
				{
					key: "po",
					label: "Português",
					value: p.po
				},
				{
					key: "de",
					label: "Deutsch",
					value: p.de
				},
				{
					key: "fr",
					label: "Français",
					value: p.fr
				},
				{
					key: "hi",
					label: "हिन्दी",
					value: p.hi
				},
				{
					key: "tu",
					label: "Türkçe",
					value: p.tu
				},
				{
					key: "vi",
					label: "Tiếng Việt",
					value: p.vi
				},
				{
					key: "it",
					label: "Italiano",
					value: p.it
				}
			];
		});
		const summaryChips = computed(() => {
			const p = product.value || {};
			return [
				{
					label: "Производители",
					value: p.manufacturers?.length || 0
				},
				{
					label: "Компоненты",
					value: p.components?.length || 0
				},
				{
					label: "Goods",
					value: p.goods?.length || 0
				},
				{
					label: "Units",
					value: p.units?.length || 0
				},
				{
					label: "Consumers",
					value: p.consumers?.length || 0
				},
				{
					label: "Sales",
					value: p.sales?.length || 0
				}
			];
		});
		function formatDate(value) {
			if (!value) return "—";
			const d = new Date(value);
			if (Number.isNaN(d.getTime())) return value;
			return new Intl.DateTimeFormat("ru-RU", {
				dateStyle: "medium",
				timeStyle: "short"
			}).format(d);
		}
		function entityTitle(entity) {
			if (!entity) return "—";
			return entity.rus || entity.name || entity.title || entity.eng || entity.full_name || entity.short_name || `#${entity.id ?? "—"}`;
		}
		function consumerSite(consumer) {
			const uris = consumer?.unit?.uris || [];
			if (!uris.length) return null;
			return (uris.find((uri) => uri?.is_valid && uri?.address) || uris.find((uri) => uri?.address))?.address || null;
		}
		function normalizeUrl(url) {
			if (!url) return null;
			if (/^https?:\/\//i.test(url)) return url;
			return `https://${url}`;
		}
		function goBack() {
			if (window.history.length > 1) window.history.back();
			else window.location.href = "/Ameise/products";
		}
		async function loadProduct() {
			loading.value = true;
			error.value = "";
			try {
				const { data } = await axios.get(`/api/products/${props.id}`);
				product.value = data;
			} catch (e) {
				console.error(e);
				error.value = e?.response?.data?.message || "Не удалось загрузить продукт";
			} finally {
				loading.value = false;
			}
		}
		onMounted(loadProduct);
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ class: "page-wrap pa-3 pa-md-4" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="page-header mb-3" data-v-0302b41d${_scopeId}><div class="d-flex align-center justify-space-between ga-3 flex-wrap" data-v-0302b41d${_scopeId}><div class="d-flex align-center ga-2 min-w-0" data-v-0302b41d${_scopeId}>`);
						_push(ssrRenderComponent(VBtn, {
							variant: "text",
							density: "comfortable",
							"prepend-icon": "mdi-arrow-left",
							onClick: goBack
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Назад `);
								else return [createTextVNode(" Назад ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`<div class="min-w-0" data-v-0302b41d${_scopeId}><div class="text-h5 font-weight-bold text-truncate" data-v-0302b41d${_scopeId}>${ssrInterpolate(productTitle.value)}</div><div class="text-caption text-medium-emphasis" data-v-0302b41d${_scopeId}> Карточка продукта </div></div></div>`);
						if (product.value) {
							_push(`<div class="d-flex align-center ga-2 flex-wrap" data-v-0302b41d${_scopeId}>`);
							_push(ssrRenderComponent(VChip, {
								size: "small",
								variant: "outlined"
							}, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(`ID: ${ssrInterpolate(product.value.id)}`);
									else return [createTextVNode("ID: " + toDisplayString(product.value.id), 1)];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(ssrRenderComponent(VChip, {
								size: "small",
								variant: "outlined"
							}, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(`${ssrInterpolate(product.value.category?.rus || product.value.category?.name || "Без категории")}`);
									else return [createTextVNode(toDisplayString(product.value.category?.rus || product.value.category?.name || "Без категории"), 1)];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(`</div>`);
						} else _push(`<!---->`);
						_push(`</div></div>`);
						if (loading.value) _push(ssrRenderComponent(VSkeletonLoader, { type: "article, table" }, null, _parent, _scopeId));
						else if (error.value) _push(ssrRenderComponent(VAlert, {
							type: "error",
							variant: "tonal"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(`${ssrInterpolate(error.value)}`);
								else return [createTextVNode(toDisplayString(error.value), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
						else if (product.value) _push(ssrRenderComponent(VCard, {
							rounded: "xl",
							class: "product-card"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<div class="tabs-header" data-v-0302b41d${_scopeId}>`);
									_push(ssrRenderComponent(VTabs, {
										modelValue: tab.value,
										"onUpdate:modelValue": ($event) => tab.value = $event,
										color: "primary",
										"align-tabs": "start",
										density: "compact",
										"show-arrows": "",
										class: "px-2"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VTab, { value: "main" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VIcon, {
																start: "",
																size: "18"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`mdi-view-dashboard-outline`);
																	else return [createTextVNode("mdi-view-dashboard-outline")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(` Основное `);
														} else return [createVNode(VIcon, {
															start: "",
															size: "18"
														}, {
															default: withCtx(() => [createTextVNode("mdi-view-dashboard-outline")]),
															_: 1
														}), createTextVNode(" Основное ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "translations" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VIcon, {
																start: "",
																size: "18"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`mdi-translate`);
																	else return [createTextVNode("mdi-translate")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(` Переводы `);
														} else return [createVNode(VIcon, {
															start: "",
															size: "18"
														}, {
															default: withCtx(() => [createTextVNode("mdi-translate")]),
															_: 1
														}), createTextVNode(" Переводы ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "manufacturers" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VIcon, {
																start: "",
																size: "18"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`mdi-factory`);
																	else return [createTextVNode("mdi-factory")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(` Производители `);
														} else return [createVNode(VIcon, {
															start: "",
															size: "18"
														}, {
															default: withCtx(() => [createTextVNode("mdi-factory")]),
															_: 1
														}), createTextVNode(" Производители ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "components" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VIcon, {
																start: "",
																size: "18"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`mdi-puzzle-outline`);
																	else return [createTextVNode("mdi-puzzle-outline")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(` Компоненты `);
														} else return [createVNode(VIcon, {
															start: "",
															size: "18"
														}, {
															default: withCtx(() => [createTextVNode("mdi-puzzle-outline")]),
															_: 1
														}), createTextVNode(" Компоненты ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "goods" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VIcon, {
																start: "",
																size: "18"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`mdi-package-variant-closed`);
																	else return [createTextVNode("mdi-package-variant-closed")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(` Goods `);
														} else return [createVNode(VIcon, {
															start: "",
															size: "18"
														}, {
															default: withCtx(() => [createTextVNode("mdi-package-variant-closed")]),
															_: 1
														}), createTextVNode(" Goods ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "units" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VIcon, {
																start: "",
																size: "18"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`mdi-domain`);
																	else return [createTextVNode("mdi-domain")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(` Units `);
														} else return [createVNode(VIcon, {
															start: "",
															size: "18"
														}, {
															default: withCtx(() => [createTextVNode("mdi-domain")]),
															_: 1
														}), createTextVNode(" Units ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "consumers" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VIcon, {
																start: "",
																size: "18"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`mdi-account-group-outline`);
																	else return [createTextVNode("mdi-account-group-outline")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(` Consumers `);
														} else return [createVNode(VIcon, {
															start: "",
															size: "18"
														}, {
															default: withCtx(() => [createTextVNode("mdi-account-group-outline")]),
															_: 1
														}), createTextVNode(" Consumers ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VTab, { value: "sales" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VIcon, {
																start: "",
																size: "18"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`mdi-cash-multiple`);
																	else return [createTextVNode("mdi-cash-multiple")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(` Sales `);
														} else return [createVNode(VIcon, {
															start: "",
															size: "18"
														}, {
															default: withCtx(() => [createTextVNode("mdi-cash-multiple")]),
															_: 1
														}), createTextVNode(" Sales ")];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VTab, { value: "main" }, {
													default: withCtx(() => [createVNode(VIcon, {
														start: "",
														size: "18"
													}, {
														default: withCtx(() => [createTextVNode("mdi-view-dashboard-outline")]),
														_: 1
													}), createTextVNode(" Основное ")]),
													_: 1
												}),
												createVNode(VTab, { value: "translations" }, {
													default: withCtx(() => [createVNode(VIcon, {
														start: "",
														size: "18"
													}, {
														default: withCtx(() => [createTextVNode("mdi-translate")]),
														_: 1
													}), createTextVNode(" Переводы ")]),
													_: 1
												}),
												createVNode(VTab, { value: "manufacturers" }, {
													default: withCtx(() => [createVNode(VIcon, {
														start: "",
														size: "18"
													}, {
														default: withCtx(() => [createTextVNode("mdi-factory")]),
														_: 1
													}), createTextVNode(" Производители ")]),
													_: 1
												}),
												createVNode(VTab, { value: "components" }, {
													default: withCtx(() => [createVNode(VIcon, {
														start: "",
														size: "18"
													}, {
														default: withCtx(() => [createTextVNode("mdi-puzzle-outline")]),
														_: 1
													}), createTextVNode(" Компоненты ")]),
													_: 1
												}),
												createVNode(VTab, { value: "goods" }, {
													default: withCtx(() => [createVNode(VIcon, {
														start: "",
														size: "18"
													}, {
														default: withCtx(() => [createTextVNode("mdi-package-variant-closed")]),
														_: 1
													}), createTextVNode(" Goods ")]),
													_: 1
												}),
												createVNode(VTab, { value: "units" }, {
													default: withCtx(() => [createVNode(VIcon, {
														start: "",
														size: "18"
													}, {
														default: withCtx(() => [createTextVNode("mdi-domain")]),
														_: 1
													}), createTextVNode(" Units ")]),
													_: 1
												}),
												createVNode(VTab, { value: "consumers" }, {
													default: withCtx(() => [createVNode(VIcon, {
														start: "",
														size: "18"
													}, {
														default: withCtx(() => [createTextVNode("mdi-account-group-outline")]),
														_: 1
													}), createTextVNode(" Consumers ")]),
													_: 1
												}),
												createVNode(VTab, { value: "sales" }, {
													default: withCtx(() => [createVNode(VIcon, {
														start: "",
														size: "18"
													}, {
														default: withCtx(() => [createTextVNode("mdi-cash-multiple")]),
														_: 1
													}), createTextVNode(" Sales ")]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(`</div>`);
									_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
									_push(`<div class="tabs-body" data-v-0302b41d${_scopeId}>`);
									_push(ssrRenderComponent(VWindow, {
										modelValue: tab.value,
										"onUpdate:modelValue": ($event) => tab.value = $event,
										class: "window-fill",
										touch: false
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												_push(ssrRenderComponent(VWindowItem, {
													value: "main",
													class: "window-pane"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<div class="tab-scroll pa-4" data-v-0302b41d${_scopeId}>`);
															_push(ssrRenderComponent(VRow, {
																class: "mb-2",
																dense: ""
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(`<!--[-->`);
																		ssrRenderList(summaryChips.value, (item) => {
																			_push(ssrRenderComponent(VCol, {
																				key: item.label,
																				cols: "6",
																				md: "4",
																				lg: "2"
																			}, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VCard, {
																						rounded: "lg",
																						variant: "tonal",
																						class: "stat-card"
																					}, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(ssrRenderComponent(VCardText, { class: "py-3" }, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(`<div class="text-caption text-medium-emphasis mb-1" data-v-0302b41d${_scopeId}>${ssrInterpolate(item.label)}</div><div class="text-h6 font-weight-bold" data-v-0302b41d${_scopeId}>${ssrInterpolate(item.value)}</div>`);
																									else return [createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, toDisplayString(item.label), 1), createVNode("div", { class: "text-h6 font-weight-bold" }, toDisplayString(item.value), 1)];
																								}),
																								_: 2
																							}, _parent, _scopeId));
																							else return [createVNode(VCardText, { class: "py-3" }, {
																								default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, toDisplayString(item.label), 1), createVNode("div", { class: "text-h6 font-weight-bold" }, toDisplayString(item.value), 1)]),
																								_: 2
																							}, 1024)];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					else return [createVNode(VCard, {
																						rounded: "lg",
																						variant: "tonal",
																						class: "stat-card"
																					}, {
																						default: withCtx(() => [createVNode(VCardText, { class: "py-3" }, {
																							default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, toDisplayString(item.label), 1), createVNode("div", { class: "text-h6 font-weight-bold" }, toDisplayString(item.value), 1)]),
																							_: 2
																						}, 1024)]),
																						_: 2
																					}, 1024)];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																		});
																		_push(`<!--]-->`);
																	} else return [(openBlock(true), createBlock(Fragment, null, renderList(summaryChips.value, (item) => {
																		return openBlock(), createBlock(VCol, {
																			key: item.label,
																			cols: "6",
																			md: "4",
																			lg: "2"
																		}, {
																			default: withCtx(() => [createVNode(VCard, {
																				rounded: "lg",
																				variant: "tonal",
																				class: "stat-card"
																			}, {
																				default: withCtx(() => [createVNode(VCardText, { class: "py-3" }, {
																					default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, toDisplayString(item.label), 1), createVNode("div", { class: "text-h6 font-weight-bold" }, toDisplayString(item.value), 1)]),
																					_: 2
																				}, 1024)]),
																				_: 2
																			}, 1024)]),
																			_: 2
																		}, 1024);
																	}), 128))];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VRow, { dense: "" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VCol, {
																			cols: "12",
																			lg: "7"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VCard, {
																					rounded: "lg",
																					variant: "tonal",
																					class: "h-100"
																				}, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) {
																							_push(ssrRenderComponent(VCardTitle, { class: "text-subtitle-1" }, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(` Общая информация `);
																									else return [createTextVNode(" Общая информация ")];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																							_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
																							_push(ssrRenderComponent(VCardText, { class: "pt-3" }, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(`<div class="info-grid" data-v-0302b41d${_scopeId}><div class="info-row" data-v-0302b41d${_scopeId}><div class="info-key" data-v-0302b41d${_scopeId}>ID</div><div class="info-value" data-v-0302b41d${_scopeId}>${ssrInterpolate(product.value.id || "—")}</div></div><div class="info-row" data-v-0302b41d${_scopeId}><div class="info-key" data-v-0302b41d${_scopeId}>Название</div><div class="info-value break-word" data-v-0302b41d${_scopeId}>${ssrInterpolate(product.value.rus || "—")}</div></div><div class="info-row" data-v-0302b41d${_scopeId}><div class="info-key" data-v-0302b41d${_scopeId}>English</div><div class="info-value break-word" data-v-0302b41d${_scopeId}>${ssrInterpolate(product.value.eng || "—")}</div></div><div class="info-row" data-v-0302b41d${_scopeId}><div class="info-key" data-v-0302b41d${_scopeId}>Категория</div><div class="info-value" data-v-0302b41d${_scopeId}>${ssrInterpolate(product.value.category?.rus || product.value.category?.name || "—")}</div></div><div class="info-row" data-v-0302b41d${_scopeId}><div class="info-key" data-v-0302b41d${_scopeId}>Создан</div><div class="info-value" data-v-0302b41d${_scopeId}>${ssrInterpolate(formatDate(product.value.created_at))}</div></div><div class="info-row" data-v-0302b41d${_scopeId}><div class="info-key" data-v-0302b41d${_scopeId}>Обновлён</div><div class="info-value" data-v-0302b41d${_scopeId}>${ssrInterpolate(formatDate(product.value.updated_at))}</div></div></div>`);
																									else return [createVNode("div", { class: "info-grid" }, [
																										createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "ID"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.id || "—"), 1)]),
																										createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Название"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.rus || "—"), 1)]),
																										createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "English"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.eng || "—"), 1)]),
																										createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Категория"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.category?.rus || product.value.category?.name || "—"), 1)]),
																										createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Создан"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.created_at)), 1)]),
																										createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Обновлён"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.updated_at)), 1)])
																									])];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																						} else return [
																							createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																								default: withCtx(() => [createTextVNode(" Общая информация ")]),
																								_: 1
																							}),
																							createVNode(VDivider),
																							createVNode(VCardText, { class: "pt-3" }, {
																								default: withCtx(() => [createVNode("div", { class: "info-grid" }, [
																									createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "ID"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.id || "—"), 1)]),
																									createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Название"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.rus || "—"), 1)]),
																									createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "English"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.eng || "—"), 1)]),
																									createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Категория"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.category?.rus || product.value.category?.name || "—"), 1)]),
																									createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Создан"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.created_at)), 1)]),
																									createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Обновлён"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.updated_at)), 1)])
																								])]),
																								_: 1
																							})
																						];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																				else return [createVNode(VCard, {
																					rounded: "lg",
																					variant: "tonal",
																					class: "h-100"
																				}, {
																					default: withCtx(() => [
																						createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																							default: withCtx(() => [createTextVNode(" Общая информация ")]),
																							_: 1
																						}),
																						createVNode(VDivider),
																						createVNode(VCardText, { class: "pt-3" }, {
																							default: withCtx(() => [createVNode("div", { class: "info-grid" }, [
																								createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "ID"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.id || "—"), 1)]),
																								createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Название"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.rus || "—"), 1)]),
																								createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "English"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.eng || "—"), 1)]),
																								createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Категория"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.category?.rus || product.value.category?.name || "—"), 1)]),
																								createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Создан"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.created_at)), 1)]),
																								createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Обновлён"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.updated_at)), 1)])
																							])]),
																							_: 1
																						})
																					]),
																					_: 1
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCol, {
																			cols: "12",
																			lg: "5"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) {
																					_push(ssrRenderComponent(VCard, {
																						rounded: "lg",
																						variant: "tonal",
																						class: "mb-3"
																					}, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) {
																								_push(ssrRenderComponent(VCardTitle, { class: "text-subtitle-1" }, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(` Быстрые связи `);
																										else return [createTextVNode(" Быстрые связи ")];
																									}),
																									_: 1
																								}, _parent, _scopeId));
																								_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
																								_push(ssrRenderComponent(VCardText, { class: "pt-3" }, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) {
																											_push(`<div class="text-caption text-medium-emphasis mb-2" data-v-0302b41d${_scopeId}> Производители </div>`);
																											if (product.value.manufacturers?.length) {
																												_push(`<div class="d-flex flex-wrap ga-2" data-v-0302b41d${_scopeId}><!--[-->`);
																												ssrRenderList(product.value.manufacturers, (manufacturer) => {
																													_push(ssrRenderComponent(unref(Link), {
																														key: manufacturer.id,
																														href: unref(route)("web.unit.show", manufacturer.id),
																														class: "text-decoration-none"
																													}, {
																														default: withCtx((_, _push, _parent, _scopeId) => {
																															if (_push) _push(ssrRenderComponent(VChip, {
																																size: "small",
																																variant: "outlined"
																															}, {
																																default: withCtx((_, _push, _parent, _scopeId) => {
																																	if (_push) _push(`${ssrInterpolate(entityTitle(manufacturer))}`);
																																	else return [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)];
																																}),
																																_: 2
																															}, _parent, _scopeId));
																															else return [createVNode(VChip, {
																																size: "small",
																																variant: "outlined"
																															}, {
																																default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																																_: 2
																															}, 1024)];
																														}),
																														_: 2
																													}, _parent, _scopeId));
																												});
																												_push(`<!--]--></div>`);
																											} else _push(`<div class="text-medium-emphasis" data-v-0302b41d${_scopeId}> Нет производителей </div>`);
																										} else return [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, " Производители "), product.value.manufacturers?.length ? (openBlock(), createBlock("div", {
																											key: 0,
																											class: "d-flex flex-wrap ga-2"
																										}, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
																											return openBlock(), createBlock(unref(Link), {
																												key: manufacturer.id,
																												href: unref(route)("web.unit.show", manufacturer.id),
																												class: "text-decoration-none"
																											}, {
																												default: withCtx(() => [createVNode(VChip, {
																													size: "small",
																													variant: "outlined"
																												}, {
																													default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																													_: 2
																												}, 1024)]),
																												_: 2
																											}, 1032, ["href"]);
																										}), 128))])) : (openBlock(), createBlock("div", {
																											key: 1,
																											class: "text-medium-emphasis"
																										}, " Нет производителей "))];
																									}),
																									_: 1
																								}, _parent, _scopeId));
																							} else return [
																								createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																									default: withCtx(() => [createTextVNode(" Быстрые связи ")]),
																									_: 1
																								}),
																								createVNode(VDivider),
																								createVNode(VCardText, { class: "pt-3" }, {
																									default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, " Производители "), product.value.manufacturers?.length ? (openBlock(), createBlock("div", {
																										key: 0,
																										class: "d-flex flex-wrap ga-2"
																									}, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
																										return openBlock(), createBlock(unref(Link), {
																											key: manufacturer.id,
																											href: unref(route)("web.unit.show", manufacturer.id),
																											class: "text-decoration-none"
																										}, {
																											default: withCtx(() => [createVNode(VChip, {
																												size: "small",
																												variant: "outlined"
																											}, {
																												default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																												_: 2
																											}, 1024)]),
																											_: 2
																										}, 1032, ["href"]);
																									}), 128))])) : (openBlock(), createBlock("div", {
																										key: 1,
																										class: "text-medium-emphasis"
																									}, " Нет производителей "))]),
																									_: 1
																								})
																							];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																					_push(ssrRenderComponent(VCard, {
																						rounded: "lg",
																						variant: "tonal"
																					}, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) {
																								_push(ssrRenderComponent(VCardTitle, { class: "text-subtitle-1" }, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(` Кратко `);
																										else return [createTextVNode(" Кратко ")];
																									}),
																									_: 1
																								}, _parent, _scopeId));
																								_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
																								_push(ssrRenderComponent(VCardText, { class: "pt-3" }, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(`<div class="d-flex flex-column ga-2" data-v-0302b41d${_scopeId}><div class="d-flex justify-space-between ga-4" data-v-0302b41d${_scopeId}><span class="text-medium-emphasis" data-v-0302b41d${_scopeId}>Goods</span><strong data-v-0302b41d${_scopeId}>${ssrInterpolate(product.value.goods?.length || 0)}</strong></div><div class="d-flex justify-space-between ga-4" data-v-0302b41d${_scopeId}><span class="text-medium-emphasis" data-v-0302b41d${_scopeId}>Components</span><strong data-v-0302b41d${_scopeId}>${ssrInterpolate(product.value.components?.length || 0)}</strong></div><div class="d-flex justify-space-between ga-4" data-v-0302b41d${_scopeId}><span class="text-medium-emphasis" data-v-0302b41d${_scopeId}>Consumers</span><strong data-v-0302b41d${_scopeId}>${ssrInterpolate(product.value.consumers?.length || 0)}</strong></div><div class="d-flex justify-space-between ga-4" data-v-0302b41d${_scopeId}><span class="text-medium-emphasis" data-v-0302b41d${_scopeId}>Sales</span><strong data-v-0302b41d${_scopeId}>${ssrInterpolate(product.value.sales?.length || 0)}</strong></div></div>`);
																										else return [createVNode("div", { class: "d-flex flex-column ga-2" }, [
																											createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Goods"), createVNode("strong", null, toDisplayString(product.value.goods?.length || 0), 1)]),
																											createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Components"), createVNode("strong", null, toDisplayString(product.value.components?.length || 0), 1)]),
																											createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Consumers"), createVNode("strong", null, toDisplayString(product.value.consumers?.length || 0), 1)]),
																											createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Sales"), createVNode("strong", null, toDisplayString(product.value.sales?.length || 0), 1)])
																										])];
																									}),
																									_: 1
																								}, _parent, _scopeId));
																							} else return [
																								createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																									default: withCtx(() => [createTextVNode(" Кратко ")]),
																									_: 1
																								}),
																								createVNode(VDivider),
																								createVNode(VCardText, { class: "pt-3" }, {
																									default: withCtx(() => [createVNode("div", { class: "d-flex flex-column ga-2" }, [
																										createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Goods"), createVNode("strong", null, toDisplayString(product.value.goods?.length || 0), 1)]),
																										createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Components"), createVNode("strong", null, toDisplayString(product.value.components?.length || 0), 1)]),
																										createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Consumers"), createVNode("strong", null, toDisplayString(product.value.consumers?.length || 0), 1)]),
																										createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Sales"), createVNode("strong", null, toDisplayString(product.value.sales?.length || 0), 1)])
																									])]),
																									_: 1
																								})
																							];
																						}),
																						_: 1
																					}, _parent, _scopeId));
																				} else return [createVNode(VCard, {
																					rounded: "lg",
																					variant: "tonal",
																					class: "mb-3"
																				}, {
																					default: withCtx(() => [
																						createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																							default: withCtx(() => [createTextVNode(" Быстрые связи ")]),
																							_: 1
																						}),
																						createVNode(VDivider),
																						createVNode(VCardText, { class: "pt-3" }, {
																							default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, " Производители "), product.value.manufacturers?.length ? (openBlock(), createBlock("div", {
																								key: 0,
																								class: "d-flex flex-wrap ga-2"
																							}, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
																								return openBlock(), createBlock(unref(Link), {
																									key: manufacturer.id,
																									href: unref(route)("web.unit.show", manufacturer.id),
																									class: "text-decoration-none"
																								}, {
																									default: withCtx(() => [createVNode(VChip, {
																										size: "small",
																										variant: "outlined"
																									}, {
																										default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																										_: 2
																									}, 1024)]),
																									_: 2
																								}, 1032, ["href"]);
																							}), 128))])) : (openBlock(), createBlock("div", {
																								key: 1,
																								class: "text-medium-emphasis"
																							}, " Нет производителей "))]),
																							_: 1
																						})
																					]),
																					_: 1
																				}), createVNode(VCard, {
																					rounded: "lg",
																					variant: "tonal"
																				}, {
																					default: withCtx(() => [
																						createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																							default: withCtx(() => [createTextVNode(" Кратко ")]),
																							_: 1
																						}),
																						createVNode(VDivider),
																						createVNode(VCardText, { class: "pt-3" }, {
																							default: withCtx(() => [createVNode("div", { class: "d-flex flex-column ga-2" }, [
																								createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Goods"), createVNode("strong", null, toDisplayString(product.value.goods?.length || 0), 1)]),
																								createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Components"), createVNode("strong", null, toDisplayString(product.value.components?.length || 0), 1)]),
																								createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Consumers"), createVNode("strong", null, toDisplayString(product.value.consumers?.length || 0), 1)]),
																								createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Sales"), createVNode("strong", null, toDisplayString(product.value.sales?.length || 0), 1)])
																							])]),
																							_: 1
																						})
																					]),
																					_: 1
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VCol, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(_sfc_main$1, {
																					"product-id": product.value.id,
																					"product-name": product.value.rus
																				}, null, _parent, _scopeId));
																				else return [createVNode(_sfc_main$1, {
																					"product-id": product.value.id,
																					"product-name": product.value.rus
																				}, null, 8, ["product-id", "product-name"])];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																	} else return [
																		createVNode(VCol, {
																			cols: "12",
																			lg: "7"
																		}, {
																			default: withCtx(() => [createVNode(VCard, {
																				rounded: "lg",
																				variant: "tonal",
																				class: "h-100"
																			}, {
																				default: withCtx(() => [
																					createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																						default: withCtx(() => [createTextVNode(" Общая информация ")]),
																						_: 1
																					}),
																					createVNode(VDivider),
																					createVNode(VCardText, { class: "pt-3" }, {
																						default: withCtx(() => [createVNode("div", { class: "info-grid" }, [
																							createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "ID"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.id || "—"), 1)]),
																							createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Название"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.rus || "—"), 1)]),
																							createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "English"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.eng || "—"), 1)]),
																							createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Категория"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.category?.rus || product.value.category?.name || "—"), 1)]),
																							createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Создан"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.created_at)), 1)]),
																							createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Обновлён"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.updated_at)), 1)])
																						])]),
																						_: 1
																					})
																				]),
																				_: 1
																			})]),
																			_: 1
																		}),
																		createVNode(VCol, {
																			cols: "12",
																			lg: "5"
																		}, {
																			default: withCtx(() => [createVNode(VCard, {
																				rounded: "lg",
																				variant: "tonal",
																				class: "mb-3"
																			}, {
																				default: withCtx(() => [
																					createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																						default: withCtx(() => [createTextVNode(" Быстрые связи ")]),
																						_: 1
																					}),
																					createVNode(VDivider),
																					createVNode(VCardText, { class: "pt-3" }, {
																						default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, " Производители "), product.value.manufacturers?.length ? (openBlock(), createBlock("div", {
																							key: 0,
																							class: "d-flex flex-wrap ga-2"
																						}, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
																							return openBlock(), createBlock(unref(Link), {
																								key: manufacturer.id,
																								href: unref(route)("web.unit.show", manufacturer.id),
																								class: "text-decoration-none"
																							}, {
																								default: withCtx(() => [createVNode(VChip, {
																									size: "small",
																									variant: "outlined"
																								}, {
																									default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																									_: 2
																								}, 1024)]),
																								_: 2
																							}, 1032, ["href"]);
																						}), 128))])) : (openBlock(), createBlock("div", {
																							key: 1,
																							class: "text-medium-emphasis"
																						}, " Нет производителей "))]),
																						_: 1
																					})
																				]),
																				_: 1
																			}), createVNode(VCard, {
																				rounded: "lg",
																				variant: "tonal"
																			}, {
																				default: withCtx(() => [
																					createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																						default: withCtx(() => [createTextVNode(" Кратко ")]),
																						_: 1
																					}),
																					createVNode(VDivider),
																					createVNode(VCardText, { class: "pt-3" }, {
																						default: withCtx(() => [createVNode("div", { class: "d-flex flex-column ga-2" }, [
																							createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Goods"), createVNode("strong", null, toDisplayString(product.value.goods?.length || 0), 1)]),
																							createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Components"), createVNode("strong", null, toDisplayString(product.value.components?.length || 0), 1)]),
																							createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Consumers"), createVNode("strong", null, toDisplayString(product.value.consumers?.length || 0), 1)]),
																							createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Sales"), createVNode("strong", null, toDisplayString(product.value.sales?.length || 0), 1)])
																						])]),
																						_: 1
																					})
																				]),
																				_: 1
																			})]),
																			_: 1
																		}),
																		createVNode(VCol, null, {
																			default: withCtx(() => [createVNode(_sfc_main$1, {
																				"product-id": product.value.id,
																				"product-name": product.value.rus
																			}, null, 8, ["product-id", "product-name"])]),
																			_: 1
																		})
																	];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(`</div>`);
														} else return [createVNode("div", { class: "tab-scroll pa-4" }, [createVNode(VRow, {
															class: "mb-2",
															dense: ""
														}, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(summaryChips.value, (item) => {
																return openBlock(), createBlock(VCol, {
																	key: item.label,
																	cols: "6",
																	md: "4",
																	lg: "2"
																}, {
																	default: withCtx(() => [createVNode(VCard, {
																		rounded: "lg",
																		variant: "tonal",
																		class: "stat-card"
																	}, {
																		default: withCtx(() => [createVNode(VCardText, { class: "py-3" }, {
																			default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, toDisplayString(item.label), 1), createVNode("div", { class: "text-h6 font-weight-bold" }, toDisplayString(item.value), 1)]),
																			_: 2
																		}, 1024)]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1024);
															}), 128))]),
															_: 1
														}), createVNode(VRow, { dense: "" }, {
															default: withCtx(() => [
																createVNode(VCol, {
																	cols: "12",
																	lg: "7"
																}, {
																	default: withCtx(() => [createVNode(VCard, {
																		rounded: "lg",
																		variant: "tonal",
																		class: "h-100"
																	}, {
																		default: withCtx(() => [
																			createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																				default: withCtx(() => [createTextVNode(" Общая информация ")]),
																				_: 1
																			}),
																			createVNode(VDivider),
																			createVNode(VCardText, { class: "pt-3" }, {
																				default: withCtx(() => [createVNode("div", { class: "info-grid" }, [
																					createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "ID"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.id || "—"), 1)]),
																					createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Название"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.rus || "—"), 1)]),
																					createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "English"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.eng || "—"), 1)]),
																					createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Категория"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.category?.rus || product.value.category?.name || "—"), 1)]),
																					createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Создан"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.created_at)), 1)]),
																					createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Обновлён"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.updated_at)), 1)])
																				])]),
																				_: 1
																			})
																		]),
																		_: 1
																	})]),
																	_: 1
																}),
																createVNode(VCol, {
																	cols: "12",
																	lg: "5"
																}, {
																	default: withCtx(() => [createVNode(VCard, {
																		rounded: "lg",
																		variant: "tonal",
																		class: "mb-3"
																	}, {
																		default: withCtx(() => [
																			createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																				default: withCtx(() => [createTextVNode(" Быстрые связи ")]),
																				_: 1
																			}),
																			createVNode(VDivider),
																			createVNode(VCardText, { class: "pt-3" }, {
																				default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, " Производители "), product.value.manufacturers?.length ? (openBlock(), createBlock("div", {
																					key: 0,
																					class: "d-flex flex-wrap ga-2"
																				}, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
																					return openBlock(), createBlock(unref(Link), {
																						key: manufacturer.id,
																						href: unref(route)("web.unit.show", manufacturer.id),
																						class: "text-decoration-none"
																					}, {
																						default: withCtx(() => [createVNode(VChip, {
																							size: "small",
																							variant: "outlined"
																						}, {
																							default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																							_: 2
																						}, 1024)]),
																						_: 2
																					}, 1032, ["href"]);
																				}), 128))])) : (openBlock(), createBlock("div", {
																					key: 1,
																					class: "text-medium-emphasis"
																				}, " Нет производителей "))]),
																				_: 1
																			})
																		]),
																		_: 1
																	}), createVNode(VCard, {
																		rounded: "lg",
																		variant: "tonal"
																	}, {
																		default: withCtx(() => [
																			createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																				default: withCtx(() => [createTextVNode(" Кратко ")]),
																				_: 1
																			}),
																			createVNode(VDivider),
																			createVNode(VCardText, { class: "pt-3" }, {
																				default: withCtx(() => [createVNode("div", { class: "d-flex flex-column ga-2" }, [
																					createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Goods"), createVNode("strong", null, toDisplayString(product.value.goods?.length || 0), 1)]),
																					createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Components"), createVNode("strong", null, toDisplayString(product.value.components?.length || 0), 1)]),
																					createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Consumers"), createVNode("strong", null, toDisplayString(product.value.consumers?.length || 0), 1)]),
																					createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Sales"), createVNode("strong", null, toDisplayString(product.value.sales?.length || 0), 1)])
																				])]),
																				_: 1
																			})
																		]),
																		_: 1
																	})]),
																	_: 1
																}),
																createVNode(VCol, null, {
																	default: withCtx(() => [createVNode(_sfc_main$1, {
																		"product-id": product.value.id,
																		"product-name": product.value.rus
																	}, null, 8, ["product-id", "product-name"])]),
																	_: 1
																})
															]),
															_: 1
														})])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VWindowItem, {
													value: "translations",
													class: "window-pane"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<div class="tab-scroll pa-4" data-v-0302b41d${_scopeId}>`);
															_push(ssrRenderComponent(VCard, {
																rounded: "lg",
																variant: "tonal"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VCardTitle, { class: "text-subtitle-1" }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(` Названия и переводы `);
																				else return [createTextVNode(" Названия и переводы ")];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
																		_push(ssrRenderComponent(VCardText, { class: "pt-3" }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VTable, {
																					density: "compact",
																					class: "data-table-fixed"
																				}, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) {
																							_push(`<thead data-v-0302b41d${_scopeId}><tr data-v-0302b41d${_scopeId}><th class="text-left" data-v-0302b41d${_scopeId}>Поле</th><th class="text-left" data-v-0302b41d${_scopeId}>Значение</th></tr></thead><tbody data-v-0302b41d${_scopeId}><!--[-->`);
																							ssrRenderList(languageRows.value, (row) => {
																								_push(`<tr data-v-0302b41d${_scopeId}><td class="table-key" data-v-0302b41d${_scopeId}>${ssrInterpolate(row.label)}</td><td class="break-word" data-v-0302b41d${_scopeId}>${ssrInterpolate(row.value || "—")}</td></tr>`);
																							});
																							_push(`<!--]--></tbody>`);
																						} else return [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "Поле"), createVNode("th", { class: "text-left" }, "Значение")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(languageRows.value, (row) => {
																							return openBlock(), createBlock("tr", { key: row.key }, [createVNode("td", { class: "table-key" }, toDisplayString(row.label), 1), createVNode("td", { class: "break-word" }, toDisplayString(row.value || "—"), 1)]);
																						}), 128))])];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																				else return [createVNode(VTable, {
																					density: "compact",
																					class: "data-table-fixed"
																				}, {
																					default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "Поле"), createVNode("th", { class: "text-left" }, "Значение")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(languageRows.value, (row) => {
																						return openBlock(), createBlock("tr", { key: row.key }, [createVNode("td", { class: "table-key" }, toDisplayString(row.label), 1), createVNode("td", { class: "break-word" }, toDisplayString(row.value || "—"), 1)]);
																					}), 128))])]),
																					_: 1
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																	} else return [
																		createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																			default: withCtx(() => [createTextVNode(" Названия и переводы ")]),
																			_: 1
																		}),
																		createVNode(VDivider),
																		createVNode(VCardText, { class: "pt-3" }, {
																			default: withCtx(() => [createVNode(VTable, {
																				density: "compact",
																				class: "data-table-fixed"
																			}, {
																				default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "Поле"), createVNode("th", { class: "text-left" }, "Значение")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(languageRows.value, (row) => {
																					return openBlock(), createBlock("tr", { key: row.key }, [createVNode("td", { class: "table-key" }, toDisplayString(row.label), 1), createVNode("td", { class: "break-word" }, toDisplayString(row.value || "—"), 1)]);
																				}), 128))])]),
																				_: 1
																			})]),
																			_: 1
																		})
																	];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(`</div>`);
														} else return [createVNode("div", { class: "tab-scroll pa-4" }, [createVNode(VCard, {
															rounded: "lg",
															variant: "tonal"
														}, {
															default: withCtx(() => [
																createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																	default: withCtx(() => [createTextVNode(" Названия и переводы ")]),
																	_: 1
																}),
																createVNode(VDivider),
																createVNode(VCardText, { class: "pt-3" }, {
																	default: withCtx(() => [createVNode(VTable, {
																		density: "compact",
																		class: "data-table-fixed"
																	}, {
																		default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "Поле"), createVNode("th", { class: "text-left" }, "Значение")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(languageRows.value, (row) => {
																			return openBlock(), createBlock("tr", { key: row.key }, [createVNode("td", { class: "table-key" }, toDisplayString(row.label), 1), createVNode("td", { class: "break-word" }, toDisplayString(row.value || "—"), 1)]);
																		}), 128))])]),
																		_: 1
																	})]),
																	_: 1
																})
															]),
															_: 1
														})])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VWindowItem, {
													value: "manufacturers",
													class: "window-pane"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<div class="tab-scroll pa-4" data-v-0302b41d${_scopeId}>`);
															if (product.value.manufacturers?.length) _push(ssrRenderComponent(VRow, { dense: "" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(`<!--[-->`);
																		ssrRenderList(product.value.manufacturers, (manufacturer) => {
																			_push(ssrRenderComponent(VCol, {
																				key: manufacturer.id,
																				cols: "12",
																				sm: "6",
																				md: "4",
																				xl: "3"
																			}, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(ssrRenderComponent(VCard, {
																						rounded: "lg",
																						variant: "tonal",
																						class: "h-100"
																					}, {
																						default: withCtx((_, _push, _parent, _scopeId) => {
																							if (_push) _push(ssrRenderComponent(VCardText, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) {
																										_push(`<div class="text-subtitle-2 font-weight-medium mb-2" data-v-0302b41d${_scopeId}>`);
																										_push(ssrRenderComponent(unref(Link), {
																											href: unref(route)("web.unit.show", manufacturer.id),
																											class: "text-decoration-none"
																										}, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(`${ssrInterpolate(entityTitle(manufacturer))}`);
																												else return [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)];
																											}),
																											_: 2
																										}, _parent, _scopeId));
																										_push(`</div><div class="text-caption text-medium-emphasis" data-v-0302b41d${_scopeId}> ID: ${ssrInterpolate(manufacturer.id)}</div>`);
																									} else return [createVNode("div", { class: "text-subtitle-2 font-weight-medium mb-2" }, [createVNode(unref(Link), {
																										href: unref(route)("web.unit.show", manufacturer.id),
																										class: "text-decoration-none"
																									}, {
																										default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																										_: 2
																									}, 1032, ["href"])]), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(manufacturer.id), 1)];
																								}),
																								_: 2
																							}, _parent, _scopeId));
																							else return [createVNode(VCardText, null, {
																								default: withCtx(() => [createVNode("div", { class: "text-subtitle-2 font-weight-medium mb-2" }, [createVNode(unref(Link), {
																									href: unref(route)("web.unit.show", manufacturer.id),
																									class: "text-decoration-none"
																								}, {
																									default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																									_: 2
																								}, 1032, ["href"])]), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(manufacturer.id), 1)]),
																								_: 2
																							}, 1024)];
																						}),
																						_: 2
																					}, _parent, _scopeId));
																					else return [createVNode(VCard, {
																						rounded: "lg",
																						variant: "tonal",
																						class: "h-100"
																					}, {
																						default: withCtx(() => [createVNode(VCardText, null, {
																							default: withCtx(() => [createVNode("div", { class: "text-subtitle-2 font-weight-medium mb-2" }, [createVNode(unref(Link), {
																								href: unref(route)("web.unit.show", manufacturer.id),
																								class: "text-decoration-none"
																							}, {
																								default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																								_: 2
																							}, 1032, ["href"])]), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(manufacturer.id), 1)]),
																							_: 2
																						}, 1024)]),
																						_: 2
																					}, 1024)];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																		});
																		_push(`<!--]-->`);
																	} else return [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
																		return openBlock(), createBlock(VCol, {
																			key: manufacturer.id,
																			cols: "12",
																			sm: "6",
																			md: "4",
																			xl: "3"
																		}, {
																			default: withCtx(() => [createVNode(VCard, {
																				rounded: "lg",
																				variant: "tonal",
																				class: "h-100"
																			}, {
																				default: withCtx(() => [createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode("div", { class: "text-subtitle-2 font-weight-medium mb-2" }, [createVNode(unref(Link), {
																						href: unref(route)("web.unit.show", manufacturer.id),
																						class: "text-decoration-none"
																					}, {
																						default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																						_: 2
																					}, 1032, ["href"])]), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(manufacturer.id), 1)]),
																					_: 2
																				}, 1024)]),
																				_: 2
																			}, 1024)]),
																			_: 2
																		}, 1024);
																	}), 128))];
																}),
																_: 1
															}, _parent, _scopeId));
															else _push(ssrRenderComponent(VAlert, {
																type: "info",
																variant: "tonal"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` Производители не найдены `);
																	else return [createTextVNode(" Производители не найдены ")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(`</div>`);
														} else return [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.manufacturers?.length ? (openBlock(), createBlock(VRow, {
															key: 0,
															dense: ""
														}, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
																return openBlock(), createBlock(VCol, {
																	key: manufacturer.id,
																	cols: "12",
																	sm: "6",
																	md: "4",
																	xl: "3"
																}, {
																	default: withCtx(() => [createVNode(VCard, {
																		rounded: "lg",
																		variant: "tonal",
																		class: "h-100"
																	}, {
																		default: withCtx(() => [createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode("div", { class: "text-subtitle-2 font-weight-medium mb-2" }, [createVNode(unref(Link), {
																				href: unref(route)("web.unit.show", manufacturer.id),
																				class: "text-decoration-none"
																			}, {
																				default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																				_: 2
																			}, 1032, ["href"])]), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(manufacturer.id), 1)]),
																			_: 2
																		}, 1024)]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1024);
															}), 128))]),
															_: 1
														})) : (openBlock(), createBlock(VAlert, {
															key: 1,
															type: "info",
															variant: "tonal"
														}, {
															default: withCtx(() => [createTextVNode(" Производители не найдены ")]),
															_: 1
														}))])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VWindowItem, {
													value: "components",
													class: "window-pane"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<div class="tab-scroll pa-4" data-v-0302b41d${_scopeId}>`);
															if (product.value.components?.length) _push(ssrRenderComponent(VCard, {
																rounded: "lg",
																variant: "tonal"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VCardText, { class: "pt-3" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VTable, {
																				density: "compact",
																				class: "data-table-fixed"
																			}, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) {
																						_push(`<thead data-v-0302b41d${_scopeId}><tr data-v-0302b41d${_scopeId}><th class="text-left" data-v-0302b41d${_scopeId}>ID</th><th class="text-left" data-v-0302b41d${_scopeId}>Название</th></tr></thead><tbody data-v-0302b41d${_scopeId}><!--[-->`);
																						ssrRenderList(product.value.components, (component) => {
																							_push(`<tr data-v-0302b41d${_scopeId}><td class="table-id" data-v-0302b41d${_scopeId}>${ssrInterpolate(component.id)}</td><td class="break-word" data-v-0302b41d${_scopeId}>${ssrInterpolate(entityTitle(component))}</td></tr>`);
																						});
																						_push(`<!--]--></tbody>`);
																					} else return [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "ID"), createVNode("th", { class: "text-left" }, "Название")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.components, (component) => {
																						return openBlock(), createBlock("tr", { key: component.id }, [createVNode("td", { class: "table-id" }, toDisplayString(component.id), 1), createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(component)), 1)]);
																					}), 128))])];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			else return [createVNode(VTable, {
																				density: "compact",
																				class: "data-table-fixed"
																			}, {
																				default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "ID"), createVNode("th", { class: "text-left" }, "Название")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.components, (component) => {
																					return openBlock(), createBlock("tr", { key: component.id }, [createVNode("td", { class: "table-id" }, toDisplayString(component.id), 1), createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(component)), 1)]);
																				}), 128))])]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VCardText, { class: "pt-3" }, {
																		default: withCtx(() => [createVNode(VTable, {
																			density: "compact",
																			class: "data-table-fixed"
																		}, {
																			default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "ID"), createVNode("th", { class: "text-left" }, "Название")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.components, (component) => {
																				return openBlock(), createBlock("tr", { key: component.id }, [createVNode("td", { class: "table-id" }, toDisplayString(component.id), 1), createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(component)), 1)]);
																			}), 128))])]),
																			_: 1
																		})]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															else _push(ssrRenderComponent(VAlert, {
																type: "info",
																variant: "tonal"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` Компоненты отсутствуют `);
																	else return [createTextVNode(" Компоненты отсутствуют ")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(`</div>`);
														} else return [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.components?.length ? (openBlock(), createBlock(VCard, {
															key: 0,
															rounded: "lg",
															variant: "tonal"
														}, {
															default: withCtx(() => [createVNode(VCardText, { class: "pt-3" }, {
																default: withCtx(() => [createVNode(VTable, {
																	density: "compact",
																	class: "data-table-fixed"
																}, {
																	default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "ID"), createVNode("th", { class: "text-left" }, "Название")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.components, (component) => {
																		return openBlock(), createBlock("tr", { key: component.id }, [createVNode("td", { class: "table-id" }, toDisplayString(component.id), 1), createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(component)), 1)]);
																	}), 128))])]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})) : (openBlock(), createBlock(VAlert, {
															key: 1,
															type: "info",
															variant: "tonal"
														}, {
															default: withCtx(() => [createTextVNode(" Компоненты отсутствуют ")]),
															_: 1
														}))])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VWindowItem, {
													value: "goods",
													class: "window-pane"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<div class="tab-scroll pa-4" data-v-0302b41d${_scopeId}>`);
															if (product.value.goods?.length) _push(ssrRenderComponent(VExpansionPanels, { variant: "accordion" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(`<!--[-->`);
																		ssrRenderList(product.value.goods, (good) => {
																			_push(ssrRenderComponent(VExpansionPanel, { key: good.id }, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) {
																						_push(ssrRenderComponent(VExpansionPanelTitle, null, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) {
																									_push(`<div class="d-flex align-center justify-space-between w-100 ga-3 pr-4" data-v-0302b41d${_scopeId}><div class="min-w-0" data-v-0302b41d${_scopeId}><div class="font-weight-medium text-truncate" data-v-0302b41d${_scopeId}>${ssrInterpolate(entityTitle(good))}</div><div class="text-caption text-medium-emphasis" data-v-0302b41d${_scopeId}> ID: ${ssrInterpolate(good.id)}</div></div>`);
																									_push(ssrRenderComponent(VChip, {
																										size: "small",
																										variant: "outlined"
																									}, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) _push(` quotations: ${ssrInterpolate(good.quotations?.length || 0)}`);
																											else return [createTextVNode(" quotations: " + toDisplayString(good.quotations?.length || 0), 1)];
																										}),
																										_: 2
																									}, _parent, _scopeId));
																									_push(`</div>`);
																								} else return [createVNode("div", { class: "d-flex align-center justify-space-between w-100 ga-3 pr-4" }, [createVNode("div", { class: "min-w-0" }, [createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(entityTitle(good)), 1), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(good.id), 1)]), createVNode(VChip, {
																									size: "small",
																									variant: "outlined"
																								}, {
																									default: withCtx(() => [createTextVNode(" quotations: " + toDisplayString(good.quotations?.length || 0), 1)]),
																									_: 2
																								}, 1024)])];
																							}),
																							_: 2
																						}, _parent, _scopeId));
																						_push(ssrRenderComponent(VExpansionPanelText, null, {
																							default: withCtx((_, _push, _parent, _scopeId) => {
																								if (_push) if (good.quotations?.length) _push(ssrRenderComponent(VTable, {
																									density: "compact",
																									class: "data-table-fixed"
																								}, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) {
																											_push(`<thead data-v-0302b41d${_scopeId}><tr data-v-0302b41d${_scopeId}><th class="text-left" data-v-0302b41d${_scopeId}>ID</th><th class="text-left" data-v-0302b41d${_scopeId}>Цена</th><th class="text-left" data-v-0302b41d${_scopeId}>Дата</th></tr></thead><tbody data-v-0302b41d${_scopeId}><!--[-->`);
																											ssrRenderList(good.quotations, (quotation) => {
																												_push(`<tr data-v-0302b41d${_scopeId}><td class="table-id" data-v-0302b41d${_scopeId}>${ssrInterpolate(quotation.id)}</td><td data-v-0302b41d${_scopeId}>${ssrInterpolate(quotation.price ?? quotation.value ?? "—")}</td><td data-v-0302b41d${_scopeId}>${ssrInterpolate(formatDate(quotation.created_at || quotation.date))}</td></tr>`);
																											});
																											_push(`<!--]--></tbody>`);
																										} else return [createVNode("thead", null, [createVNode("tr", null, [
																											createVNode("th", { class: "text-left" }, "ID"),
																											createVNode("th", { class: "text-left" }, "Цена"),
																											createVNode("th", { class: "text-left" }, "Дата")
																										])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(good.quotations, (quotation) => {
																											return openBlock(), createBlock("tr", { key: quotation.id }, [
																												createVNode("td", { class: "table-id" }, toDisplayString(quotation.id), 1),
																												createVNode("td", null, toDisplayString(quotation.price ?? quotation.value ?? "—"), 1),
																												createVNode("td", null, toDisplayString(formatDate(quotation.created_at || quotation.date)), 1)
																											]);
																										}), 128))])];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								else _push(ssrRenderComponent(VAlert, {
																									type: "info",
																									variant: "tonal"
																								}, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(` Quotations отсутствуют `);
																										else return [createTextVNode(" Quotations отсутствуют ")];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								else return [good.quotations?.length ? (openBlock(), createBlock(VTable, {
																									key: 0,
																									density: "compact",
																									class: "data-table-fixed"
																								}, {
																									default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																										createVNode("th", { class: "text-left" }, "ID"),
																										createVNode("th", { class: "text-left" }, "Цена"),
																										createVNode("th", { class: "text-left" }, "Дата")
																									])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(good.quotations, (quotation) => {
																										return openBlock(), createBlock("tr", { key: quotation.id }, [
																											createVNode("td", { class: "table-id" }, toDisplayString(quotation.id), 1),
																											createVNode("td", null, toDisplayString(quotation.price ?? quotation.value ?? "—"), 1),
																											createVNode("td", null, toDisplayString(formatDate(quotation.created_at || quotation.date)), 1)
																										]);
																									}), 128))])]),
																									_: 2
																								}, 1024)) : (openBlock(), createBlock(VAlert, {
																									key: 1,
																									type: "info",
																									variant: "tonal"
																								}, {
																									default: withCtx(() => [createTextVNode(" Quotations отсутствуют ")]),
																									_: 1
																								}))];
																							}),
																							_: 2
																						}, _parent, _scopeId));
																					} else return [createVNode(VExpansionPanelTitle, null, {
																						default: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-space-between w-100 ga-3 pr-4" }, [createVNode("div", { class: "min-w-0" }, [createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(entityTitle(good)), 1), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(good.id), 1)]), createVNode(VChip, {
																							size: "small",
																							variant: "outlined"
																						}, {
																							default: withCtx(() => [createTextVNode(" quotations: " + toDisplayString(good.quotations?.length || 0), 1)]),
																							_: 2
																						}, 1024)])]),
																						_: 2
																					}, 1024), createVNode(VExpansionPanelText, null, {
																						default: withCtx(() => [good.quotations?.length ? (openBlock(), createBlock(VTable, {
																							key: 0,
																							density: "compact",
																							class: "data-table-fixed"
																						}, {
																							default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																								createVNode("th", { class: "text-left" }, "ID"),
																								createVNode("th", { class: "text-left" }, "Цена"),
																								createVNode("th", { class: "text-left" }, "Дата")
																							])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(good.quotations, (quotation) => {
																								return openBlock(), createBlock("tr", { key: quotation.id }, [
																									createVNode("td", { class: "table-id" }, toDisplayString(quotation.id), 1),
																									createVNode("td", null, toDisplayString(quotation.price ?? quotation.value ?? "—"), 1),
																									createVNode("td", null, toDisplayString(formatDate(quotation.created_at || quotation.date)), 1)
																								]);
																							}), 128))])]),
																							_: 2
																						}, 1024)) : (openBlock(), createBlock(VAlert, {
																							key: 1,
																							type: "info",
																							variant: "tonal"
																						}, {
																							default: withCtx(() => [createTextVNode(" Quotations отсутствуют ")]),
																							_: 1
																						}))]),
																						_: 2
																					}, 1024)];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																		});
																		_push(`<!--]-->`);
																	} else return [(openBlock(true), createBlock(Fragment, null, renderList(product.value.goods, (good) => {
																		return openBlock(), createBlock(VExpansionPanel, { key: good.id }, {
																			default: withCtx(() => [createVNode(VExpansionPanelTitle, null, {
																				default: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-space-between w-100 ga-3 pr-4" }, [createVNode("div", { class: "min-w-0" }, [createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(entityTitle(good)), 1), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(good.id), 1)]), createVNode(VChip, {
																					size: "small",
																					variant: "outlined"
																				}, {
																					default: withCtx(() => [createTextVNode(" quotations: " + toDisplayString(good.quotations?.length || 0), 1)]),
																					_: 2
																				}, 1024)])]),
																				_: 2
																			}, 1024), createVNode(VExpansionPanelText, null, {
																				default: withCtx(() => [good.quotations?.length ? (openBlock(), createBlock(VTable, {
																					key: 0,
																					density: "compact",
																					class: "data-table-fixed"
																				}, {
																					default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																						createVNode("th", { class: "text-left" }, "ID"),
																						createVNode("th", { class: "text-left" }, "Цена"),
																						createVNode("th", { class: "text-left" }, "Дата")
																					])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(good.quotations, (quotation) => {
																						return openBlock(), createBlock("tr", { key: quotation.id }, [
																							createVNode("td", { class: "table-id" }, toDisplayString(quotation.id), 1),
																							createVNode("td", null, toDisplayString(quotation.price ?? quotation.value ?? "—"), 1),
																							createVNode("td", null, toDisplayString(formatDate(quotation.created_at || quotation.date)), 1)
																						]);
																					}), 128))])]),
																					_: 2
																				}, 1024)) : (openBlock(), createBlock(VAlert, {
																					key: 1,
																					type: "info",
																					variant: "tonal"
																				}, {
																					default: withCtx(() => [createTextVNode(" Quotations отсутствуют ")]),
																					_: 1
																				}))]),
																				_: 2
																			}, 1024)]),
																			_: 2
																		}, 1024);
																	}), 128))];
																}),
																_: 1
															}, _parent, _scopeId));
															else _push(ssrRenderComponent(VAlert, {
																type: "info",
																variant: "tonal"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` Goods не найдены `);
																	else return [createTextVNode(" Goods не найдены ")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(`</div>`);
														} else return [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.goods?.length ? (openBlock(), createBlock(VExpansionPanels, {
															key: 0,
															variant: "accordion"
														}, {
															default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(product.value.goods, (good) => {
																return openBlock(), createBlock(VExpansionPanel, { key: good.id }, {
																	default: withCtx(() => [createVNode(VExpansionPanelTitle, null, {
																		default: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-space-between w-100 ga-3 pr-4" }, [createVNode("div", { class: "min-w-0" }, [createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(entityTitle(good)), 1), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(good.id), 1)]), createVNode(VChip, {
																			size: "small",
																			variant: "outlined"
																		}, {
																			default: withCtx(() => [createTextVNode(" quotations: " + toDisplayString(good.quotations?.length || 0), 1)]),
																			_: 2
																		}, 1024)])]),
																		_: 2
																	}, 1024), createVNode(VExpansionPanelText, null, {
																		default: withCtx(() => [good.quotations?.length ? (openBlock(), createBlock(VTable, {
																			key: 0,
																			density: "compact",
																			class: "data-table-fixed"
																		}, {
																			default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																				createVNode("th", { class: "text-left" }, "ID"),
																				createVNode("th", { class: "text-left" }, "Цена"),
																				createVNode("th", { class: "text-left" }, "Дата")
																			])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(good.quotations, (quotation) => {
																				return openBlock(), createBlock("tr", { key: quotation.id }, [
																					createVNode("td", { class: "table-id" }, toDisplayString(quotation.id), 1),
																					createVNode("td", null, toDisplayString(quotation.price ?? quotation.value ?? "—"), 1),
																					createVNode("td", null, toDisplayString(formatDate(quotation.created_at || quotation.date)), 1)
																				]);
																			}), 128))])]),
																			_: 2
																		}, 1024)) : (openBlock(), createBlock(VAlert, {
																			key: 1,
																			type: "info",
																			variant: "tonal"
																		}, {
																			default: withCtx(() => [createTextVNode(" Quotations отсутствуют ")]),
																			_: 1
																		}))]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1024);
															}), 128))]),
															_: 1
														})) : (openBlock(), createBlock(VAlert, {
															key: 1,
															type: "info",
															variant: "tonal"
														}, {
															default: withCtx(() => [createTextVNode(" Goods не найдены ")]),
															_: 1
														}))])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VWindowItem, {
													value: "units",
													class: "window-pane"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<div class="tab-scroll pa-4" data-v-0302b41d${_scopeId}>`);
															if (product.value.units?.length) _push(ssrRenderComponent(VCard, {
																rounded: "lg",
																variant: "tonal"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VCardText, { class: "pt-3" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VTable, {
																				density: "compact",
																				class: "data-table-fixed"
																			}, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) {
																						_push(`<thead data-v-0302b41d${_scopeId}><tr data-v-0302b41d${_scopeId}><th class="text-left" data-v-0302b41d${_scopeId}>ID</th><th class="text-left" data-v-0302b41d${_scopeId}>Название</th><th class="text-left" data-v-0302b41d${_scopeId}>action_id</th></tr></thead><tbody data-v-0302b41d${_scopeId}><!--[-->`);
																						ssrRenderList(product.value.units, (unit) => {
																							_push(`<tr data-v-0302b41d${_scopeId}><td class="table-id" data-v-0302b41d${_scopeId}>${ssrInterpolate(unit.id)}</td><td class="break-word" data-v-0302b41d${_scopeId}>${ssrInterpolate(entityTitle(unit))}</td><td data-v-0302b41d${_scopeId}>${ssrInterpolate(unit.pivot?.action_id ?? "—")}</td></tr>`);
																						});
																						_push(`<!--]--></tbody>`);
																					} else return [createVNode("thead", null, [createVNode("tr", null, [
																						createVNode("th", { class: "text-left" }, "ID"),
																						createVNode("th", { class: "text-left" }, "Название"),
																						createVNode("th", { class: "text-left" }, "action_id")
																					])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.units, (unit) => {
																						return openBlock(), createBlock("tr", { key: unit.id }, [
																							createVNode("td", { class: "table-id" }, toDisplayString(unit.id), 1),
																							createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(unit)), 1),
																							createVNode("td", null, toDisplayString(unit.pivot?.action_id ?? "—"), 1)
																						]);
																					}), 128))])];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			else return [createVNode(VTable, {
																				density: "compact",
																				class: "data-table-fixed"
																			}, {
																				default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																					createVNode("th", { class: "text-left" }, "ID"),
																					createVNode("th", { class: "text-left" }, "Название"),
																					createVNode("th", { class: "text-left" }, "action_id")
																				])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.units, (unit) => {
																					return openBlock(), createBlock("tr", { key: unit.id }, [
																						createVNode("td", { class: "table-id" }, toDisplayString(unit.id), 1),
																						createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(unit)), 1),
																						createVNode("td", null, toDisplayString(unit.pivot?.action_id ?? "—"), 1)
																					]);
																				}), 128))])]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VCardText, { class: "pt-3" }, {
																		default: withCtx(() => [createVNode(VTable, {
																			density: "compact",
																			class: "data-table-fixed"
																		}, {
																			default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																				createVNode("th", { class: "text-left" }, "ID"),
																				createVNode("th", { class: "text-left" }, "Название"),
																				createVNode("th", { class: "text-left" }, "action_id")
																			])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.units, (unit) => {
																				return openBlock(), createBlock("tr", { key: unit.id }, [
																					createVNode("td", { class: "table-id" }, toDisplayString(unit.id), 1),
																					createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(unit)), 1),
																					createVNode("td", null, toDisplayString(unit.pivot?.action_id ?? "—"), 1)
																				]);
																			}), 128))])]),
																			_: 1
																		})]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															else _push(ssrRenderComponent(VAlert, {
																type: "info",
																variant: "tonal"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` Units не найдены `);
																	else return [createTextVNode(" Units не найдены ")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(`</div>`);
														} else return [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.units?.length ? (openBlock(), createBlock(VCard, {
															key: 0,
															rounded: "lg",
															variant: "tonal"
														}, {
															default: withCtx(() => [createVNode(VCardText, { class: "pt-3" }, {
																default: withCtx(() => [createVNode(VTable, {
																	density: "compact",
																	class: "data-table-fixed"
																}, {
																	default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																		createVNode("th", { class: "text-left" }, "ID"),
																		createVNode("th", { class: "text-left" }, "Название"),
																		createVNode("th", { class: "text-left" }, "action_id")
																	])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.units, (unit) => {
																		return openBlock(), createBlock("tr", { key: unit.id }, [
																			createVNode("td", { class: "table-id" }, toDisplayString(unit.id), 1),
																			createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(unit)), 1),
																			createVNode("td", null, toDisplayString(unit.pivot?.action_id ?? "—"), 1)
																		]);
																	}), 128))])]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})) : (openBlock(), createBlock(VAlert, {
															key: 1,
															type: "info",
															variant: "tonal"
														}, {
															default: withCtx(() => [createTextVNode(" Units не найдены ")]),
															_: 1
														}))])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VWindowItem, {
													value: "consumers",
													class: "window-pane"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<div class="tab-scroll pa-4 consumers-pane" data-v-0302b41d${_scopeId}>`);
															if (product.value.consumers?.length) _push(ssrRenderComponent(VCard, {
																rounded: "xl",
																variant: "flat",
																class: "consumers-card"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(`<div class="consumers-hero px-4 px-md-6 py-4" data-v-0302b41d${_scopeId}><div class="d-flex align-center justify-space-between ga-3 flex-wrap" data-v-0302b41d${_scopeId}><div data-v-0302b41d${_scopeId}><div class="text-overline consumers-overline mb-1" data-v-0302b41d${_scopeId}> Потребители продукта </div><div class="text-h6 font-weight-bold" data-v-0302b41d${_scopeId}> Consumers </div><div class="text-body-2 text-medium-emphasis mt-1" data-v-0302b41d${_scopeId}> Компании и unit, использующие текущий продукт </div></div>`);
																		_push(ssrRenderComponent(VChip, {
																			color: "success",
																			variant: "flat",
																			size: "large"
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`${ssrInterpolate(product.value.consumers.length)}`);
																				else return [createTextVNode(toDisplayString(product.value.consumers.length), 1)];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(`</div></div>`);
																		_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
																		_push(ssrRenderComponent(VCardText, { class: "pt-4" }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VTable, {
																					density: "comfortable",
																					class: "data-table-fixed consumers-table"
																				}, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) {
																							_push(`<thead data-v-0302b41d${_scopeId}><tr data-v-0302b41d${_scopeId}><th class="text-left" data-v-0302b41d${_scopeId}>ID</th><th class="text-left" data-v-0302b41d${_scopeId}>Unit</th><th class="text-left" data-v-0302b41d${_scopeId}>Measure</th><th class="text-left" data-v-0302b41d${_scopeId}>Количество</th><th class="text-left" data-v-0302b41d${_scopeId}>Сайт</th></tr></thead><tbody data-v-0302b41d${_scopeId}><!--[-->`);
																							ssrRenderList(product.value.consumers, (consumer) => {
																								_push(`<tr data-v-0302b41d${_scopeId}><td class="table-id" data-v-0302b41d${_scopeId}>${ssrInterpolate(consumer.id)}</td><td class="break-word" data-v-0302b41d${_scopeId}>`);
																								if (consumer.unit?.id) _push(ssrRenderComponent(unref(Link), {
																									href: unref(route)("web.unit.show", consumer.unit.id),
																									class: "text-decoration-none"
																								}, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(ssrRenderComponent(VChip, {
																											color: "success",
																											variant: "tonal",
																											size: "small",
																											class: "consumer-unit-chip"
																										}, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(`${ssrInterpolate(entityTitle(consumer.unit))}`);
																												else return [createTextVNode(toDisplayString(entityTitle(consumer.unit)), 1)];
																											}),
																											_: 2
																										}, _parent, _scopeId));
																										else return [createVNode(VChip, {
																											color: "success",
																											variant: "tonal",
																											size: "small",
																											class: "consumer-unit-chip"
																										}, {
																											default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.unit)), 1)]),
																											_: 2
																										}, 1024)];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								else _push(`<span class="text-medium-emphasis" data-v-0302b41d${_scopeId}>—</span>`);
																								_push(`</td><td class="break-word" data-v-0302b41d${_scopeId}>`);
																								if (consumer.measure) _push(ssrRenderComponent(VChip, {
																									color: "green-darken-2",
																									variant: "outlined",
																									size: "small"
																								}, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(`${ssrInterpolate(entityTitle(consumer.measure))}`);
																										else return [createTextVNode(toDisplayString(entityTitle(consumer.measure)), 1)];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																								else _push(`<span class="text-medium-emphasis" data-v-0302b41d${_scopeId}>—</span>`);
																								_push(`</td><td data-v-0302b41d${_scopeId}><span class="consumer-qty" data-v-0302b41d${_scopeId}>${ssrInterpolate(consumer.quantity ?? "—")}</span></td><td class="break-word" data-v-0302b41d${_scopeId}>`);
																								if (consumerSite(consumer)) _push(`<a${ssrRenderAttr("href", normalizeUrl(consumerSite(consumer)))} target="_blank" rel="noopener noreferrer" class="consumer-site-link" data-v-0302b41d${_scopeId}>${ssrInterpolate(consumerSite(consumer))}</a>`);
																								else _push(`<span class="text-medium-emphasis" data-v-0302b41d${_scopeId}>—</span>`);
																								_push(`</td></tr>`);
																							});
																							_push(`<!--]--></tbody>`);
																						} else return [createVNode("thead", null, [createVNode("tr", null, [
																							createVNode("th", { class: "text-left" }, "ID"),
																							createVNode("th", { class: "text-left" }, "Unit"),
																							createVNode("th", { class: "text-left" }, "Measure"),
																							createVNode("th", { class: "text-left" }, "Количество"),
																							createVNode("th", { class: "text-left" }, "Сайт")
																						])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.consumers, (consumer) => {
																							return openBlock(), createBlock("tr", { key: consumer.id }, [
																								createVNode("td", { class: "table-id" }, toDisplayString(consumer.id), 1),
																								createVNode("td", { class: "break-word" }, [consumer.unit?.id ? (openBlock(), createBlock(unref(Link), {
																									key: 0,
																									href: unref(route)("web.unit.show", consumer.unit.id),
																									class: "text-decoration-none"
																								}, {
																									default: withCtx(() => [createVNode(VChip, {
																										color: "success",
																										variant: "tonal",
																										size: "small",
																										class: "consumer-unit-chip"
																									}, {
																										default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.unit)), 1)]),
																										_: 2
																									}, 1024)]),
																									_: 2
																								}, 1032, ["href"])) : (openBlock(), createBlock("span", {
																									key: 1,
																									class: "text-medium-emphasis"
																								}, "—"))]),
																								createVNode("td", { class: "break-word" }, [consumer.measure ? (openBlock(), createBlock(VChip, {
																									key: 0,
																									color: "green-darken-2",
																									variant: "outlined",
																									size: "small"
																								}, {
																									default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.measure)), 1)]),
																									_: 2
																								}, 1024)) : (openBlock(), createBlock("span", {
																									key: 1,
																									class: "text-medium-emphasis"
																								}, "—"))]),
																								createVNode("td", null, [createVNode("span", { class: "consumer-qty" }, toDisplayString(consumer.quantity ?? "—"), 1)]),
																								createVNode("td", { class: "break-word" }, [consumerSite(consumer) ? (openBlock(), createBlock("a", {
																									key: 0,
																									href: normalizeUrl(consumerSite(consumer)),
																									target: "_blank",
																									rel: "noopener noreferrer",
																									class: "consumer-site-link"
																								}, toDisplayString(consumerSite(consumer)), 9, ["href"])) : (openBlock(), createBlock("span", {
																									key: 1,
																									class: "text-medium-emphasis"
																								}, "—"))])
																							]);
																						}), 128))])];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																				else return [createVNode(VTable, {
																					density: "comfortable",
																					class: "data-table-fixed consumers-table"
																				}, {
																					default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																						createVNode("th", { class: "text-left" }, "ID"),
																						createVNode("th", { class: "text-left" }, "Unit"),
																						createVNode("th", { class: "text-left" }, "Measure"),
																						createVNode("th", { class: "text-left" }, "Количество"),
																						createVNode("th", { class: "text-left" }, "Сайт")
																					])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.consumers, (consumer) => {
																						return openBlock(), createBlock("tr", { key: consumer.id }, [
																							createVNode("td", { class: "table-id" }, toDisplayString(consumer.id), 1),
																							createVNode("td", { class: "break-word" }, [consumer.unit?.id ? (openBlock(), createBlock(unref(Link), {
																								key: 0,
																								href: unref(route)("web.unit.show", consumer.unit.id),
																								class: "text-decoration-none"
																							}, {
																								default: withCtx(() => [createVNode(VChip, {
																									color: "success",
																									variant: "tonal",
																									size: "small",
																									class: "consumer-unit-chip"
																								}, {
																									default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.unit)), 1)]),
																									_: 2
																								}, 1024)]),
																								_: 2
																							}, 1032, ["href"])) : (openBlock(), createBlock("span", {
																								key: 1,
																								class: "text-medium-emphasis"
																							}, "—"))]),
																							createVNode("td", { class: "break-word" }, [consumer.measure ? (openBlock(), createBlock(VChip, {
																								key: 0,
																								color: "green-darken-2",
																								variant: "outlined",
																								size: "small"
																							}, {
																								default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.measure)), 1)]),
																								_: 2
																							}, 1024)) : (openBlock(), createBlock("span", {
																								key: 1,
																								class: "text-medium-emphasis"
																							}, "—"))]),
																							createVNode("td", null, [createVNode("span", { class: "consumer-qty" }, toDisplayString(consumer.quantity ?? "—"), 1)]),
																							createVNode("td", { class: "break-word" }, [consumerSite(consumer) ? (openBlock(), createBlock("a", {
																								key: 0,
																								href: normalizeUrl(consumerSite(consumer)),
																								target: "_blank",
																								rel: "noopener noreferrer",
																								class: "consumer-site-link"
																							}, toDisplayString(consumerSite(consumer)), 9, ["href"])) : (openBlock(), createBlock("span", {
																								key: 1,
																								class: "text-medium-emphasis"
																							}, "—"))])
																						]);
																					}), 128))])]),
																					_: 1
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																	} else return [
																		createVNode("div", { class: "consumers-hero px-4 px-md-6 py-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between ga-3 flex-wrap" }, [createVNode("div", null, [
																			createVNode("div", { class: "text-overline consumers-overline mb-1" }, " Потребители продукта "),
																			createVNode("div", { class: "text-h6 font-weight-bold" }, " Consumers "),
																			createVNode("div", { class: "text-body-2 text-medium-emphasis mt-1" }, " Компании и unit, использующие текущий продукт ")
																		]), createVNode(VChip, {
																			color: "success",
																			variant: "flat",
																			size: "large"
																		}, {
																			default: withCtx(() => [createTextVNode(toDisplayString(product.value.consumers.length), 1)]),
																			_: 1
																		})])]),
																		createVNode(VDivider),
																		createVNode(VCardText, { class: "pt-4" }, {
																			default: withCtx(() => [createVNode(VTable, {
																				density: "comfortable",
																				class: "data-table-fixed consumers-table"
																			}, {
																				default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																					createVNode("th", { class: "text-left" }, "ID"),
																					createVNode("th", { class: "text-left" }, "Unit"),
																					createVNode("th", { class: "text-left" }, "Measure"),
																					createVNode("th", { class: "text-left" }, "Количество"),
																					createVNode("th", { class: "text-left" }, "Сайт")
																				])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.consumers, (consumer) => {
																					return openBlock(), createBlock("tr", { key: consumer.id }, [
																						createVNode("td", { class: "table-id" }, toDisplayString(consumer.id), 1),
																						createVNode("td", { class: "break-word" }, [consumer.unit?.id ? (openBlock(), createBlock(unref(Link), {
																							key: 0,
																							href: unref(route)("web.unit.show", consumer.unit.id),
																							class: "text-decoration-none"
																						}, {
																							default: withCtx(() => [createVNode(VChip, {
																								color: "success",
																								variant: "tonal",
																								size: "small",
																								class: "consumer-unit-chip"
																							}, {
																								default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.unit)), 1)]),
																								_: 2
																							}, 1024)]),
																							_: 2
																						}, 1032, ["href"])) : (openBlock(), createBlock("span", {
																							key: 1,
																							class: "text-medium-emphasis"
																						}, "—"))]),
																						createVNode("td", { class: "break-word" }, [consumer.measure ? (openBlock(), createBlock(VChip, {
																							key: 0,
																							color: "green-darken-2",
																							variant: "outlined",
																							size: "small"
																						}, {
																							default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.measure)), 1)]),
																							_: 2
																						}, 1024)) : (openBlock(), createBlock("span", {
																							key: 1,
																							class: "text-medium-emphasis"
																						}, "—"))]),
																						createVNode("td", null, [createVNode("span", { class: "consumer-qty" }, toDisplayString(consumer.quantity ?? "—"), 1)]),
																						createVNode("td", { class: "break-word" }, [consumerSite(consumer) ? (openBlock(), createBlock("a", {
																							key: 0,
																							href: normalizeUrl(consumerSite(consumer)),
																							target: "_blank",
																							rel: "noopener noreferrer",
																							class: "consumer-site-link"
																						}, toDisplayString(consumerSite(consumer)), 9, ["href"])) : (openBlock(), createBlock("span", {
																							key: 1,
																							class: "text-medium-emphasis"
																						}, "—"))])
																					]);
																				}), 128))])]),
																				_: 1
																			})]),
																			_: 1
																		})
																	];
																}),
																_: 1
															}, _parent, _scopeId));
															else _push(ssrRenderComponent(VAlert, {
																type: "info",
																variant: "tonal",
																color: "success"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` Consumers не найдены `);
																	else return [createTextVNode(" Consumers не найдены ")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(`</div>`);
														} else return [createVNode("div", { class: "tab-scroll pa-4 consumers-pane" }, [product.value.consumers?.length ? (openBlock(), createBlock(VCard, {
															key: 0,
															rounded: "xl",
															variant: "flat",
															class: "consumers-card"
														}, {
															default: withCtx(() => [
																createVNode("div", { class: "consumers-hero px-4 px-md-6 py-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between ga-3 flex-wrap" }, [createVNode("div", null, [
																	createVNode("div", { class: "text-overline consumers-overline mb-1" }, " Потребители продукта "),
																	createVNode("div", { class: "text-h6 font-weight-bold" }, " Consumers "),
																	createVNode("div", { class: "text-body-2 text-medium-emphasis mt-1" }, " Компании и unit, использующие текущий продукт ")
																]), createVNode(VChip, {
																	color: "success",
																	variant: "flat",
																	size: "large"
																}, {
																	default: withCtx(() => [createTextVNode(toDisplayString(product.value.consumers.length), 1)]),
																	_: 1
																})])]),
																createVNode(VDivider),
																createVNode(VCardText, { class: "pt-4" }, {
																	default: withCtx(() => [createVNode(VTable, {
																		density: "comfortable",
																		class: "data-table-fixed consumers-table"
																	}, {
																		default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																			createVNode("th", { class: "text-left" }, "ID"),
																			createVNode("th", { class: "text-left" }, "Unit"),
																			createVNode("th", { class: "text-left" }, "Measure"),
																			createVNode("th", { class: "text-left" }, "Количество"),
																			createVNode("th", { class: "text-left" }, "Сайт")
																		])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.consumers, (consumer) => {
																			return openBlock(), createBlock("tr", { key: consumer.id }, [
																				createVNode("td", { class: "table-id" }, toDisplayString(consumer.id), 1),
																				createVNode("td", { class: "break-word" }, [consumer.unit?.id ? (openBlock(), createBlock(unref(Link), {
																					key: 0,
																					href: unref(route)("web.unit.show", consumer.unit.id),
																					class: "text-decoration-none"
																				}, {
																					default: withCtx(() => [createVNode(VChip, {
																						color: "success",
																						variant: "tonal",
																						size: "small",
																						class: "consumer-unit-chip"
																					}, {
																						default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.unit)), 1)]),
																						_: 2
																					}, 1024)]),
																					_: 2
																				}, 1032, ["href"])) : (openBlock(), createBlock("span", {
																					key: 1,
																					class: "text-medium-emphasis"
																				}, "—"))]),
																				createVNode("td", { class: "break-word" }, [consumer.measure ? (openBlock(), createBlock(VChip, {
																					key: 0,
																					color: "green-darken-2",
																					variant: "outlined",
																					size: "small"
																				}, {
																					default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.measure)), 1)]),
																					_: 2
																				}, 1024)) : (openBlock(), createBlock("span", {
																					key: 1,
																					class: "text-medium-emphasis"
																				}, "—"))]),
																				createVNode("td", null, [createVNode("span", { class: "consumer-qty" }, toDisplayString(consumer.quantity ?? "—"), 1)]),
																				createVNode("td", { class: "break-word" }, [consumerSite(consumer) ? (openBlock(), createBlock("a", {
																					key: 0,
																					href: normalizeUrl(consumerSite(consumer)),
																					target: "_blank",
																					rel: "noopener noreferrer",
																					class: "consumer-site-link"
																				}, toDisplayString(consumerSite(consumer)), 9, ["href"])) : (openBlock(), createBlock("span", {
																					key: 1,
																					class: "text-medium-emphasis"
																				}, "—"))])
																			]);
																		}), 128))])]),
																		_: 1
																	})]),
																	_: 1
																})
															]),
															_: 1
														})) : (openBlock(), createBlock(VAlert, {
															key: 1,
															type: "info",
															variant: "tonal",
															color: "success"
														}, {
															default: withCtx(() => [createTextVNode(" Consumers не найдены ")]),
															_: 1
														}))])];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VWindowItem, {
													value: "sales",
													class: "window-pane"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<div class="tab-scroll pa-4" data-v-0302b41d${_scopeId}>`);
															if (product.value.sales?.length) _push(ssrRenderComponent(VCard, {
																rounded: "lg",
																variant: "tonal"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VCardText, { class: "pt-3" }, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VTable, {
																				density: "compact",
																				class: "data-table-fixed"
																			}, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) {
																						_push(`<thead data-v-0302b41d${_scopeId}><tr data-v-0302b41d${_scopeId}><th class="text-left" data-v-0302b41d${_scopeId}>ID</th><th class="text-left" data-v-0302b41d${_scopeId}>Дата</th><th class="text-left" data-v-0302b41d${_scopeId}>Контрагент</th><th class="text-left" data-v-0302b41d${_scopeId}>Goods</th><th class="text-left" data-v-0302b41d${_scopeId}>Сумма</th></tr></thead><tbody data-v-0302b41d${_scopeId}><!--[-->`);
																						ssrRenderList(product.value.sales, (sale) => {
																							_push(`<tr data-v-0302b41d${_scopeId}><td class="table-id" data-v-0302b41d${_scopeId}>${ssrInterpolate(sale.id)}</td><td data-v-0302b41d${_scopeId}>${ssrInterpolate(formatDate(sale.date || sale.created_at))}</td><td class="break-word" data-v-0302b41d${_scopeId}>${ssrInterpolate(entityTitle(sale.entity))}</td><td data-v-0302b41d${_scopeId}><div class="d-flex flex-wrap ga-1" data-v-0302b41d${_scopeId}><!--[-->`);
																							ssrRenderList(sale.goods, (good) => {
																								_push(ssrRenderComponent(VChip, {
																									key: good.id,
																									size: "x-small",
																									variant: "outlined"
																								}, {
																									default: withCtx((_, _push, _parent, _scopeId) => {
																										if (_push) _push(`${ssrInterpolate(entityTitle(good))}`);
																										else return [createTextVNode(toDisplayString(entityTitle(good)), 1)];
																									}),
																									_: 2
																								}, _parent, _scopeId));
																							});
																							_push(`<!--]--></div></td><td data-v-0302b41d${_scopeId}>${ssrInterpolate(sale.total ?? "—")}</td></tr>`);
																						});
																						_push(`<!--]--></tbody>`);
																					} else return [createVNode("thead", null, [createVNode("tr", null, [
																						createVNode("th", { class: "text-left" }, "ID"),
																						createVNode("th", { class: "text-left" }, "Дата"),
																						createVNode("th", { class: "text-left" }, "Контрагент"),
																						createVNode("th", { class: "text-left" }, "Goods"),
																						createVNode("th", { class: "text-left" }, "Сумма")
																					])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.sales, (sale) => {
																						return openBlock(), createBlock("tr", { key: sale.id }, [
																							createVNode("td", { class: "table-id" }, toDisplayString(sale.id), 1),
																							createVNode("td", null, toDisplayString(formatDate(sale.date || sale.created_at)), 1),
																							createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(sale.entity)), 1),
																							createVNode("td", null, [createVNode("div", { class: "d-flex flex-wrap ga-1" }, [(openBlock(true), createBlock(Fragment, null, renderList(sale.goods, (good) => {
																								return openBlock(), createBlock(VChip, {
																									key: good.id,
																									size: "x-small",
																									variant: "outlined"
																								}, {
																									default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(good)), 1)]),
																									_: 2
																								}, 1024);
																							}), 128))])]),
																							createVNode("td", null, toDisplayString(sale.total ?? "—"), 1)
																						]);
																					}), 128))])];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			else return [createVNode(VTable, {
																				density: "compact",
																				class: "data-table-fixed"
																			}, {
																				default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																					createVNode("th", { class: "text-left" }, "ID"),
																					createVNode("th", { class: "text-left" }, "Дата"),
																					createVNode("th", { class: "text-left" }, "Контрагент"),
																					createVNode("th", { class: "text-left" }, "Goods"),
																					createVNode("th", { class: "text-left" }, "Сумма")
																				])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.sales, (sale) => {
																					return openBlock(), createBlock("tr", { key: sale.id }, [
																						createVNode("td", { class: "table-id" }, toDisplayString(sale.id), 1),
																						createVNode("td", null, toDisplayString(formatDate(sale.date || sale.created_at)), 1),
																						createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(sale.entity)), 1),
																						createVNode("td", null, [createVNode("div", { class: "d-flex flex-wrap ga-1" }, [(openBlock(true), createBlock(Fragment, null, renderList(sale.goods, (good) => {
																							return openBlock(), createBlock(VChip, {
																								key: good.id,
																								size: "x-small",
																								variant: "outlined"
																							}, {
																								default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(good)), 1)]),
																								_: 2
																							}, 1024);
																						}), 128))])]),
																						createVNode("td", null, toDisplayString(sale.total ?? "—"), 1)
																					]);
																				}), 128))])]),
																				_: 1
																			})];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VCardText, { class: "pt-3" }, {
																		default: withCtx(() => [createVNode(VTable, {
																			density: "compact",
																			class: "data-table-fixed"
																		}, {
																			default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																				createVNode("th", { class: "text-left" }, "ID"),
																				createVNode("th", { class: "text-left" }, "Дата"),
																				createVNode("th", { class: "text-left" }, "Контрагент"),
																				createVNode("th", { class: "text-left" }, "Goods"),
																				createVNode("th", { class: "text-left" }, "Сумма")
																			])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.sales, (sale) => {
																				return openBlock(), createBlock("tr", { key: sale.id }, [
																					createVNode("td", { class: "table-id" }, toDisplayString(sale.id), 1),
																					createVNode("td", null, toDisplayString(formatDate(sale.date || sale.created_at)), 1),
																					createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(sale.entity)), 1),
																					createVNode("td", null, [createVNode("div", { class: "d-flex flex-wrap ga-1" }, [(openBlock(true), createBlock(Fragment, null, renderList(sale.goods, (good) => {
																						return openBlock(), createBlock(VChip, {
																							key: good.id,
																							size: "x-small",
																							variant: "outlined"
																						}, {
																							default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(good)), 1)]),
																							_: 2
																						}, 1024);
																					}), 128))])]),
																					createVNode("td", null, toDisplayString(sale.total ?? "—"), 1)
																				]);
																			}), 128))])]),
																			_: 1
																		})]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															else _push(ssrRenderComponent(VAlert, {
																type: "info",
																variant: "tonal"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(` Продажи отсутствуют `);
																	else return [createTextVNode(" Продажи отсутствуют ")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(`</div>`);
														} else return [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.sales?.length ? (openBlock(), createBlock(VCard, {
															key: 0,
															rounded: "lg",
															variant: "tonal"
														}, {
															default: withCtx(() => [createVNode(VCardText, { class: "pt-3" }, {
																default: withCtx(() => [createVNode(VTable, {
																	density: "compact",
																	class: "data-table-fixed"
																}, {
																	default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																		createVNode("th", { class: "text-left" }, "ID"),
																		createVNode("th", { class: "text-left" }, "Дата"),
																		createVNode("th", { class: "text-left" }, "Контрагент"),
																		createVNode("th", { class: "text-left" }, "Goods"),
																		createVNode("th", { class: "text-left" }, "Сумма")
																	])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.sales, (sale) => {
																		return openBlock(), createBlock("tr", { key: sale.id }, [
																			createVNode("td", { class: "table-id" }, toDisplayString(sale.id), 1),
																			createVNode("td", null, toDisplayString(formatDate(sale.date || sale.created_at)), 1),
																			createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(sale.entity)), 1),
																			createVNode("td", null, [createVNode("div", { class: "d-flex flex-wrap ga-1" }, [(openBlock(true), createBlock(Fragment, null, renderList(sale.goods, (good) => {
																				return openBlock(), createBlock(VChip, {
																					key: good.id,
																					size: "x-small",
																					variant: "outlined"
																				}, {
																					default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(good)), 1)]),
																					_: 2
																				}, 1024);
																			}), 128))])]),
																			createVNode("td", null, toDisplayString(sale.total ?? "—"), 1)
																		]);
																	}), 128))])]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														})) : (openBlock(), createBlock(VAlert, {
															key: 1,
															type: "info",
															variant: "tonal"
														}, {
															default: withCtx(() => [createTextVNode(" Продажи отсутствуют ")]),
															_: 1
														}))])];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												createVNode(VWindowItem, {
													value: "main",
													class: "window-pane"
												}, {
													default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [createVNode(VRow, {
														class: "mb-2",
														dense: ""
													}, {
														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(summaryChips.value, (item) => {
															return openBlock(), createBlock(VCol, {
																key: item.label,
																cols: "6",
																md: "4",
																lg: "2"
															}, {
																default: withCtx(() => [createVNode(VCard, {
																	rounded: "lg",
																	variant: "tonal",
																	class: "stat-card"
																}, {
																	default: withCtx(() => [createVNode(VCardText, { class: "py-3" }, {
																		default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, toDisplayString(item.label), 1), createVNode("div", { class: "text-h6 font-weight-bold" }, toDisplayString(item.value), 1)]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1024)]),
																_: 2
															}, 1024);
														}), 128))]),
														_: 1
													}), createVNode(VRow, { dense: "" }, {
														default: withCtx(() => [
															createVNode(VCol, {
																cols: "12",
																lg: "7"
															}, {
																default: withCtx(() => [createVNode(VCard, {
																	rounded: "lg",
																	variant: "tonal",
																	class: "h-100"
																}, {
																	default: withCtx(() => [
																		createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																			default: withCtx(() => [createTextVNode(" Общая информация ")]),
																			_: 1
																		}),
																		createVNode(VDivider),
																		createVNode(VCardText, { class: "pt-3" }, {
																			default: withCtx(() => [createVNode("div", { class: "info-grid" }, [
																				createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "ID"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.id || "—"), 1)]),
																				createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Название"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.rus || "—"), 1)]),
																				createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "English"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.eng || "—"), 1)]),
																				createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Категория"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.category?.rus || product.value.category?.name || "—"), 1)]),
																				createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Создан"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.created_at)), 1)]),
																				createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Обновлён"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.updated_at)), 1)])
																			])]),
																			_: 1
																		})
																	]),
																	_: 1
																})]),
																_: 1
															}),
															createVNode(VCol, {
																cols: "12",
																lg: "5"
															}, {
																default: withCtx(() => [createVNode(VCard, {
																	rounded: "lg",
																	variant: "tonal",
																	class: "mb-3"
																}, {
																	default: withCtx(() => [
																		createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																			default: withCtx(() => [createTextVNode(" Быстрые связи ")]),
																			_: 1
																		}),
																		createVNode(VDivider),
																		createVNode(VCardText, { class: "pt-3" }, {
																			default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, " Производители "), product.value.manufacturers?.length ? (openBlock(), createBlock("div", {
																				key: 0,
																				class: "d-flex flex-wrap ga-2"
																			}, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
																				return openBlock(), createBlock(unref(Link), {
																					key: manufacturer.id,
																					href: unref(route)("web.unit.show", manufacturer.id),
																					class: "text-decoration-none"
																				}, {
																					default: withCtx(() => [createVNode(VChip, {
																						size: "small",
																						variant: "outlined"
																					}, {
																						default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																						_: 2
																					}, 1024)]),
																					_: 2
																				}, 1032, ["href"]);
																			}), 128))])) : (openBlock(), createBlock("div", {
																				key: 1,
																				class: "text-medium-emphasis"
																			}, " Нет производителей "))]),
																			_: 1
																		})
																	]),
																	_: 1
																}), createVNode(VCard, {
																	rounded: "lg",
																	variant: "tonal"
																}, {
																	default: withCtx(() => [
																		createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																			default: withCtx(() => [createTextVNode(" Кратко ")]),
																			_: 1
																		}),
																		createVNode(VDivider),
																		createVNode(VCardText, { class: "pt-3" }, {
																			default: withCtx(() => [createVNode("div", { class: "d-flex flex-column ga-2" }, [
																				createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Goods"), createVNode("strong", null, toDisplayString(product.value.goods?.length || 0), 1)]),
																				createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Components"), createVNode("strong", null, toDisplayString(product.value.components?.length || 0), 1)]),
																				createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Consumers"), createVNode("strong", null, toDisplayString(product.value.consumers?.length || 0), 1)]),
																				createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Sales"), createVNode("strong", null, toDisplayString(product.value.sales?.length || 0), 1)])
																			])]),
																			_: 1
																		})
																	]),
																	_: 1
																})]),
																_: 1
															}),
															createVNode(VCol, null, {
																default: withCtx(() => [createVNode(_sfc_main$1, {
																	"product-id": product.value.id,
																	"product-name": product.value.rus
																}, null, 8, ["product-id", "product-name"])]),
																_: 1
															})
														]),
														_: 1
													})])]),
													_: 1
												}),
												createVNode(VWindowItem, {
													value: "translations",
													class: "window-pane"
												}, {
													default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [createVNode(VCard, {
														rounded: "lg",
														variant: "tonal"
													}, {
														default: withCtx(() => [
															createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																default: withCtx(() => [createTextVNode(" Названия и переводы ")]),
																_: 1
															}),
															createVNode(VDivider),
															createVNode(VCardText, { class: "pt-3" }, {
																default: withCtx(() => [createVNode(VTable, {
																	density: "compact",
																	class: "data-table-fixed"
																}, {
																	default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "Поле"), createVNode("th", { class: "text-left" }, "Значение")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(languageRows.value, (row) => {
																		return openBlock(), createBlock("tr", { key: row.key }, [createVNode("td", { class: "table-key" }, toDisplayString(row.label), 1), createVNode("td", { class: "break-word" }, toDisplayString(row.value || "—"), 1)]);
																	}), 128))])]),
																	_: 1
																})]),
																_: 1
															})
														]),
														_: 1
													})])]),
													_: 1
												}),
												createVNode(VWindowItem, {
													value: "manufacturers",
													class: "window-pane"
												}, {
													default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.manufacturers?.length ? (openBlock(), createBlock(VRow, {
														key: 0,
														dense: ""
													}, {
														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
															return openBlock(), createBlock(VCol, {
																key: manufacturer.id,
																cols: "12",
																sm: "6",
																md: "4",
																xl: "3"
															}, {
																default: withCtx(() => [createVNode(VCard, {
																	rounded: "lg",
																	variant: "tonal",
																	class: "h-100"
																}, {
																	default: withCtx(() => [createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode("div", { class: "text-subtitle-2 font-weight-medium mb-2" }, [createVNode(unref(Link), {
																			href: unref(route)("web.unit.show", manufacturer.id),
																			class: "text-decoration-none"
																		}, {
																			default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																			_: 2
																		}, 1032, ["href"])]), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(manufacturer.id), 1)]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1024)]),
																_: 2
															}, 1024);
														}), 128))]),
														_: 1
													})) : (openBlock(), createBlock(VAlert, {
														key: 1,
														type: "info",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(" Производители не найдены ")]),
														_: 1
													}))])]),
													_: 1
												}),
												createVNode(VWindowItem, {
													value: "components",
													class: "window-pane"
												}, {
													default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.components?.length ? (openBlock(), createBlock(VCard, {
														key: 0,
														rounded: "lg",
														variant: "tonal"
													}, {
														default: withCtx(() => [createVNode(VCardText, { class: "pt-3" }, {
															default: withCtx(() => [createVNode(VTable, {
																density: "compact",
																class: "data-table-fixed"
															}, {
																default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "ID"), createVNode("th", { class: "text-left" }, "Название")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.components, (component) => {
																	return openBlock(), createBlock("tr", { key: component.id }, [createVNode("td", { class: "table-id" }, toDisplayString(component.id), 1), createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(component)), 1)]);
																}), 128))])]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})) : (openBlock(), createBlock(VAlert, {
														key: 1,
														type: "info",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(" Компоненты отсутствуют ")]),
														_: 1
													}))])]),
													_: 1
												}),
												createVNode(VWindowItem, {
													value: "goods",
													class: "window-pane"
												}, {
													default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.goods?.length ? (openBlock(), createBlock(VExpansionPanels, {
														key: 0,
														variant: "accordion"
													}, {
														default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(product.value.goods, (good) => {
															return openBlock(), createBlock(VExpansionPanel, { key: good.id }, {
																default: withCtx(() => [createVNode(VExpansionPanelTitle, null, {
																	default: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-space-between w-100 ga-3 pr-4" }, [createVNode("div", { class: "min-w-0" }, [createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(entityTitle(good)), 1), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(good.id), 1)]), createVNode(VChip, {
																		size: "small",
																		variant: "outlined"
																	}, {
																		default: withCtx(() => [createTextVNode(" quotations: " + toDisplayString(good.quotations?.length || 0), 1)]),
																		_: 2
																	}, 1024)])]),
																	_: 2
																}, 1024), createVNode(VExpansionPanelText, null, {
																	default: withCtx(() => [good.quotations?.length ? (openBlock(), createBlock(VTable, {
																		key: 0,
																		density: "compact",
																		class: "data-table-fixed"
																	}, {
																		default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																			createVNode("th", { class: "text-left" }, "ID"),
																			createVNode("th", { class: "text-left" }, "Цена"),
																			createVNode("th", { class: "text-left" }, "Дата")
																		])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(good.quotations, (quotation) => {
																			return openBlock(), createBlock("tr", { key: quotation.id }, [
																				createVNode("td", { class: "table-id" }, toDisplayString(quotation.id), 1),
																				createVNode("td", null, toDisplayString(quotation.price ?? quotation.value ?? "—"), 1),
																				createVNode("td", null, toDisplayString(formatDate(quotation.created_at || quotation.date)), 1)
																			]);
																		}), 128))])]),
																		_: 2
																	}, 1024)) : (openBlock(), createBlock(VAlert, {
																		key: 1,
																		type: "info",
																		variant: "tonal"
																	}, {
																		default: withCtx(() => [createTextVNode(" Quotations отсутствуют ")]),
																		_: 1
																	}))]),
																	_: 2
																}, 1024)]),
																_: 2
															}, 1024);
														}), 128))]),
														_: 1
													})) : (openBlock(), createBlock(VAlert, {
														key: 1,
														type: "info",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(" Goods не найдены ")]),
														_: 1
													}))])]),
													_: 1
												}),
												createVNode(VWindowItem, {
													value: "units",
													class: "window-pane"
												}, {
													default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.units?.length ? (openBlock(), createBlock(VCard, {
														key: 0,
														rounded: "lg",
														variant: "tonal"
													}, {
														default: withCtx(() => [createVNode(VCardText, { class: "pt-3" }, {
															default: withCtx(() => [createVNode(VTable, {
																density: "compact",
																class: "data-table-fixed"
															}, {
																default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																	createVNode("th", { class: "text-left" }, "ID"),
																	createVNode("th", { class: "text-left" }, "Название"),
																	createVNode("th", { class: "text-left" }, "action_id")
																])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.units, (unit) => {
																	return openBlock(), createBlock("tr", { key: unit.id }, [
																		createVNode("td", { class: "table-id" }, toDisplayString(unit.id), 1),
																		createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(unit)), 1),
																		createVNode("td", null, toDisplayString(unit.pivot?.action_id ?? "—"), 1)
																	]);
																}), 128))])]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})) : (openBlock(), createBlock(VAlert, {
														key: 1,
														type: "info",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(" Units не найдены ")]),
														_: 1
													}))])]),
													_: 1
												}),
												createVNode(VWindowItem, {
													value: "consumers",
													class: "window-pane"
												}, {
													default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4 consumers-pane" }, [product.value.consumers?.length ? (openBlock(), createBlock(VCard, {
														key: 0,
														rounded: "xl",
														variant: "flat",
														class: "consumers-card"
													}, {
														default: withCtx(() => [
															createVNode("div", { class: "consumers-hero px-4 px-md-6 py-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between ga-3 flex-wrap" }, [createVNode("div", null, [
																createVNode("div", { class: "text-overline consumers-overline mb-1" }, " Потребители продукта "),
																createVNode("div", { class: "text-h6 font-weight-bold" }, " Consumers "),
																createVNode("div", { class: "text-body-2 text-medium-emphasis mt-1" }, " Компании и unit, использующие текущий продукт ")
															]), createVNode(VChip, {
																color: "success",
																variant: "flat",
																size: "large"
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(product.value.consumers.length), 1)]),
																_: 1
															})])]),
															createVNode(VDivider),
															createVNode(VCardText, { class: "pt-4" }, {
																default: withCtx(() => [createVNode(VTable, {
																	density: "comfortable",
																	class: "data-table-fixed consumers-table"
																}, {
																	default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																		createVNode("th", { class: "text-left" }, "ID"),
																		createVNode("th", { class: "text-left" }, "Unit"),
																		createVNode("th", { class: "text-left" }, "Measure"),
																		createVNode("th", { class: "text-left" }, "Количество"),
																		createVNode("th", { class: "text-left" }, "Сайт")
																	])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.consumers, (consumer) => {
																		return openBlock(), createBlock("tr", { key: consumer.id }, [
																			createVNode("td", { class: "table-id" }, toDisplayString(consumer.id), 1),
																			createVNode("td", { class: "break-word" }, [consumer.unit?.id ? (openBlock(), createBlock(unref(Link), {
																				key: 0,
																				href: unref(route)("web.unit.show", consumer.unit.id),
																				class: "text-decoration-none"
																			}, {
																				default: withCtx(() => [createVNode(VChip, {
																					color: "success",
																					variant: "tonal",
																					size: "small",
																					class: "consumer-unit-chip"
																				}, {
																					default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.unit)), 1)]),
																					_: 2
																				}, 1024)]),
																				_: 2
																			}, 1032, ["href"])) : (openBlock(), createBlock("span", {
																				key: 1,
																				class: "text-medium-emphasis"
																			}, "—"))]),
																			createVNode("td", { class: "break-word" }, [consumer.measure ? (openBlock(), createBlock(VChip, {
																				key: 0,
																				color: "green-darken-2",
																				variant: "outlined",
																				size: "small"
																			}, {
																				default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.measure)), 1)]),
																				_: 2
																			}, 1024)) : (openBlock(), createBlock("span", {
																				key: 1,
																				class: "text-medium-emphasis"
																			}, "—"))]),
																			createVNode("td", null, [createVNode("span", { class: "consumer-qty" }, toDisplayString(consumer.quantity ?? "—"), 1)]),
																			createVNode("td", { class: "break-word" }, [consumerSite(consumer) ? (openBlock(), createBlock("a", {
																				key: 0,
																				href: normalizeUrl(consumerSite(consumer)),
																				target: "_blank",
																				rel: "noopener noreferrer",
																				class: "consumer-site-link"
																			}, toDisplayString(consumerSite(consumer)), 9, ["href"])) : (openBlock(), createBlock("span", {
																				key: 1,
																				class: "text-medium-emphasis"
																			}, "—"))])
																		]);
																	}), 128))])]),
																	_: 1
																})]),
																_: 1
															})
														]),
														_: 1
													})) : (openBlock(), createBlock(VAlert, {
														key: 1,
														type: "info",
														variant: "tonal",
														color: "success"
													}, {
														default: withCtx(() => [createTextVNode(" Consumers не найдены ")]),
														_: 1
													}))])]),
													_: 1
												}),
												createVNode(VWindowItem, {
													value: "sales",
													class: "window-pane"
												}, {
													default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.sales?.length ? (openBlock(), createBlock(VCard, {
														key: 0,
														rounded: "lg",
														variant: "tonal"
													}, {
														default: withCtx(() => [createVNode(VCardText, { class: "pt-3" }, {
															default: withCtx(() => [createVNode(VTable, {
																density: "compact",
																class: "data-table-fixed"
															}, {
																default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																	createVNode("th", { class: "text-left" }, "ID"),
																	createVNode("th", { class: "text-left" }, "Дата"),
																	createVNode("th", { class: "text-left" }, "Контрагент"),
																	createVNode("th", { class: "text-left" }, "Goods"),
																	createVNode("th", { class: "text-left" }, "Сумма")
																])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.sales, (sale) => {
																	return openBlock(), createBlock("tr", { key: sale.id }, [
																		createVNode("td", { class: "table-id" }, toDisplayString(sale.id), 1),
																		createVNode("td", null, toDisplayString(formatDate(sale.date || sale.created_at)), 1),
																		createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(sale.entity)), 1),
																		createVNode("td", null, [createVNode("div", { class: "d-flex flex-wrap ga-1" }, [(openBlock(true), createBlock(Fragment, null, renderList(sale.goods, (good) => {
																			return openBlock(), createBlock(VChip, {
																				key: good.id,
																				size: "x-small",
																				variant: "outlined"
																			}, {
																				default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(good)), 1)]),
																				_: 2
																			}, 1024);
																		}), 128))])]),
																		createVNode("td", null, toDisplayString(sale.total ?? "—"), 1)
																	]);
																}), 128))])]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})) : (openBlock(), createBlock(VAlert, {
														key: 1,
														type: "info",
														variant: "tonal"
													}, {
														default: withCtx(() => [createTextVNode(" Продажи отсутствуют ")]),
														_: 1
													}))])]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(`</div>`);
								} else return [
									createVNode("div", { class: "tabs-header" }, [createVNode(VTabs, {
										modelValue: tab.value,
										"onUpdate:modelValue": ($event) => tab.value = $event,
										color: "primary",
										"align-tabs": "start",
										density: "compact",
										"show-arrows": "",
										class: "px-2"
									}, {
										default: withCtx(() => [
											createVNode(VTab, { value: "main" }, {
												default: withCtx(() => [createVNode(VIcon, {
													start: "",
													size: "18"
												}, {
													default: withCtx(() => [createTextVNode("mdi-view-dashboard-outline")]),
													_: 1
												}), createTextVNode(" Основное ")]),
												_: 1
											}),
											createVNode(VTab, { value: "translations" }, {
												default: withCtx(() => [createVNode(VIcon, {
													start: "",
													size: "18"
												}, {
													default: withCtx(() => [createTextVNode("mdi-translate")]),
													_: 1
												}), createTextVNode(" Переводы ")]),
												_: 1
											}),
											createVNode(VTab, { value: "manufacturers" }, {
												default: withCtx(() => [createVNode(VIcon, {
													start: "",
													size: "18"
												}, {
													default: withCtx(() => [createTextVNode("mdi-factory")]),
													_: 1
												}), createTextVNode(" Производители ")]),
												_: 1
											}),
											createVNode(VTab, { value: "components" }, {
												default: withCtx(() => [createVNode(VIcon, {
													start: "",
													size: "18"
												}, {
													default: withCtx(() => [createTextVNode("mdi-puzzle-outline")]),
													_: 1
												}), createTextVNode(" Компоненты ")]),
												_: 1
											}),
											createVNode(VTab, { value: "goods" }, {
												default: withCtx(() => [createVNode(VIcon, {
													start: "",
													size: "18"
												}, {
													default: withCtx(() => [createTextVNode("mdi-package-variant-closed")]),
													_: 1
												}), createTextVNode(" Goods ")]),
												_: 1
											}),
											createVNode(VTab, { value: "units" }, {
												default: withCtx(() => [createVNode(VIcon, {
													start: "",
													size: "18"
												}, {
													default: withCtx(() => [createTextVNode("mdi-domain")]),
													_: 1
												}), createTextVNode(" Units ")]),
												_: 1
											}),
											createVNode(VTab, { value: "consumers" }, {
												default: withCtx(() => [createVNode(VIcon, {
													start: "",
													size: "18"
												}, {
													default: withCtx(() => [createTextVNode("mdi-account-group-outline")]),
													_: 1
												}), createTextVNode(" Consumers ")]),
												_: 1
											}),
											createVNode(VTab, { value: "sales" }, {
												default: withCtx(() => [createVNode(VIcon, {
													start: "",
													size: "18"
												}, {
													default: withCtx(() => [createTextVNode("mdi-cash-multiple")]),
													_: 1
												}), createTextVNode(" Sales ")]),
												_: 1
											})
										]),
										_: 1
									}, 8, ["modelValue", "onUpdate:modelValue"])]),
									createVNode(VDivider),
									createVNode("div", { class: "tabs-body" }, [createVNode(VWindow, {
										modelValue: tab.value,
										"onUpdate:modelValue": ($event) => tab.value = $event,
										class: "window-fill",
										touch: false
									}, {
										default: withCtx(() => [
											createVNode(VWindowItem, {
												value: "main",
												class: "window-pane"
											}, {
												default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [createVNode(VRow, {
													class: "mb-2",
													dense: ""
												}, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(summaryChips.value, (item) => {
														return openBlock(), createBlock(VCol, {
															key: item.label,
															cols: "6",
															md: "4",
															lg: "2"
														}, {
															default: withCtx(() => [createVNode(VCard, {
																rounded: "lg",
																variant: "tonal",
																class: "stat-card"
															}, {
																default: withCtx(() => [createVNode(VCardText, { class: "py-3" }, {
																	default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, toDisplayString(item.label), 1), createVNode("div", { class: "text-h6 font-weight-bold" }, toDisplayString(item.value), 1)]),
																	_: 2
																}, 1024)]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024);
													}), 128))]),
													_: 1
												}), createVNode(VRow, { dense: "" }, {
													default: withCtx(() => [
														createVNode(VCol, {
															cols: "12",
															lg: "7"
														}, {
															default: withCtx(() => [createVNode(VCard, {
																rounded: "lg",
																variant: "tonal",
																class: "h-100"
															}, {
																default: withCtx(() => [
																	createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																		default: withCtx(() => [createTextVNode(" Общая информация ")]),
																		_: 1
																	}),
																	createVNode(VDivider),
																	createVNode(VCardText, { class: "pt-3" }, {
																		default: withCtx(() => [createVNode("div", { class: "info-grid" }, [
																			createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "ID"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.id || "—"), 1)]),
																			createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Название"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.rus || "—"), 1)]),
																			createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "English"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.eng || "—"), 1)]),
																			createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Категория"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.category?.rus || product.value.category?.name || "—"), 1)]),
																			createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Создан"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.created_at)), 1)]),
																			createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Обновлён"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.updated_at)), 1)])
																		])]),
																		_: 1
																	})
																]),
																_: 1
															})]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															lg: "5"
														}, {
															default: withCtx(() => [createVNode(VCard, {
																rounded: "lg",
																variant: "tonal",
																class: "mb-3"
															}, {
																default: withCtx(() => [
																	createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																		default: withCtx(() => [createTextVNode(" Быстрые связи ")]),
																		_: 1
																	}),
																	createVNode(VDivider),
																	createVNode(VCardText, { class: "pt-3" }, {
																		default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, " Производители "), product.value.manufacturers?.length ? (openBlock(), createBlock("div", {
																			key: 0,
																			class: "d-flex flex-wrap ga-2"
																		}, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
																			return openBlock(), createBlock(unref(Link), {
																				key: manufacturer.id,
																				href: unref(route)("web.unit.show", manufacturer.id),
																				class: "text-decoration-none"
																			}, {
																				default: withCtx(() => [createVNode(VChip, {
																					size: "small",
																					variant: "outlined"
																				}, {
																					default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																					_: 2
																				}, 1024)]),
																				_: 2
																			}, 1032, ["href"]);
																		}), 128))])) : (openBlock(), createBlock("div", {
																			key: 1,
																			class: "text-medium-emphasis"
																		}, " Нет производителей "))]),
																		_: 1
																	})
																]),
																_: 1
															}), createVNode(VCard, {
																rounded: "lg",
																variant: "tonal"
															}, {
																default: withCtx(() => [
																	createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																		default: withCtx(() => [createTextVNode(" Кратко ")]),
																		_: 1
																	}),
																	createVNode(VDivider),
																	createVNode(VCardText, { class: "pt-3" }, {
																		default: withCtx(() => [createVNode("div", { class: "d-flex flex-column ga-2" }, [
																			createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Goods"), createVNode("strong", null, toDisplayString(product.value.goods?.length || 0), 1)]),
																			createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Components"), createVNode("strong", null, toDisplayString(product.value.components?.length || 0), 1)]),
																			createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Consumers"), createVNode("strong", null, toDisplayString(product.value.consumers?.length || 0), 1)]),
																			createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Sales"), createVNode("strong", null, toDisplayString(product.value.sales?.length || 0), 1)])
																		])]),
																		_: 1
																	})
																]),
																_: 1
															})]),
															_: 1
														}),
														createVNode(VCol, null, {
															default: withCtx(() => [createVNode(_sfc_main$1, {
																"product-id": product.value.id,
																"product-name": product.value.rus
															}, null, 8, ["product-id", "product-name"])]),
															_: 1
														})
													]),
													_: 1
												})])]),
												_: 1
											}),
											createVNode(VWindowItem, {
												value: "translations",
												class: "window-pane"
											}, {
												default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [createVNode(VCard, {
													rounded: "lg",
													variant: "tonal"
												}, {
													default: withCtx(() => [
														createVNode(VCardTitle, { class: "text-subtitle-1" }, {
															default: withCtx(() => [createTextVNode(" Названия и переводы ")]),
															_: 1
														}),
														createVNode(VDivider),
														createVNode(VCardText, { class: "pt-3" }, {
															default: withCtx(() => [createVNode(VTable, {
																density: "compact",
																class: "data-table-fixed"
															}, {
																default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "Поле"), createVNode("th", { class: "text-left" }, "Значение")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(languageRows.value, (row) => {
																	return openBlock(), createBlock("tr", { key: row.key }, [createVNode("td", { class: "table-key" }, toDisplayString(row.label), 1), createVNode("td", { class: "break-word" }, toDisplayString(row.value || "—"), 1)]);
																}), 128))])]),
																_: 1
															})]),
															_: 1
														})
													]),
													_: 1
												})])]),
												_: 1
											}),
											createVNode(VWindowItem, {
												value: "manufacturers",
												class: "window-pane"
											}, {
												default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.manufacturers?.length ? (openBlock(), createBlock(VRow, {
													key: 0,
													dense: ""
												}, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
														return openBlock(), createBlock(VCol, {
															key: manufacturer.id,
															cols: "12",
															sm: "6",
															md: "4",
															xl: "3"
														}, {
															default: withCtx(() => [createVNode(VCard, {
																rounded: "lg",
																variant: "tonal",
																class: "h-100"
															}, {
																default: withCtx(() => [createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode("div", { class: "text-subtitle-2 font-weight-medium mb-2" }, [createVNode(unref(Link), {
																		href: unref(route)("web.unit.show", manufacturer.id),
																		class: "text-decoration-none"
																	}, {
																		default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																		_: 2
																	}, 1032, ["href"])]), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(manufacturer.id), 1)]),
																	_: 2
																}, 1024)]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024);
													}), 128))]),
													_: 1
												})) : (openBlock(), createBlock(VAlert, {
													key: 1,
													type: "info",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(" Производители не найдены ")]),
													_: 1
												}))])]),
												_: 1
											}),
											createVNode(VWindowItem, {
												value: "components",
												class: "window-pane"
											}, {
												default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.components?.length ? (openBlock(), createBlock(VCard, {
													key: 0,
													rounded: "lg",
													variant: "tonal"
												}, {
													default: withCtx(() => [createVNode(VCardText, { class: "pt-3" }, {
														default: withCtx(() => [createVNode(VTable, {
															density: "compact",
															class: "data-table-fixed"
														}, {
															default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "ID"), createVNode("th", { class: "text-left" }, "Название")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.components, (component) => {
																return openBlock(), createBlock("tr", { key: component.id }, [createVNode("td", { class: "table-id" }, toDisplayString(component.id), 1), createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(component)), 1)]);
															}), 128))])]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})) : (openBlock(), createBlock(VAlert, {
													key: 1,
													type: "info",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(" Компоненты отсутствуют ")]),
													_: 1
												}))])]),
												_: 1
											}),
											createVNode(VWindowItem, {
												value: "goods",
												class: "window-pane"
											}, {
												default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.goods?.length ? (openBlock(), createBlock(VExpansionPanels, {
													key: 0,
													variant: "accordion"
												}, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(product.value.goods, (good) => {
														return openBlock(), createBlock(VExpansionPanel, { key: good.id }, {
															default: withCtx(() => [createVNode(VExpansionPanelTitle, null, {
																default: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-space-between w-100 ga-3 pr-4" }, [createVNode("div", { class: "min-w-0" }, [createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(entityTitle(good)), 1), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(good.id), 1)]), createVNode(VChip, {
																	size: "small",
																	variant: "outlined"
																}, {
																	default: withCtx(() => [createTextVNode(" quotations: " + toDisplayString(good.quotations?.length || 0), 1)]),
																	_: 2
																}, 1024)])]),
																_: 2
															}, 1024), createVNode(VExpansionPanelText, null, {
																default: withCtx(() => [good.quotations?.length ? (openBlock(), createBlock(VTable, {
																	key: 0,
																	density: "compact",
																	class: "data-table-fixed"
																}, {
																	default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																		createVNode("th", { class: "text-left" }, "ID"),
																		createVNode("th", { class: "text-left" }, "Цена"),
																		createVNode("th", { class: "text-left" }, "Дата")
																	])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(good.quotations, (quotation) => {
																		return openBlock(), createBlock("tr", { key: quotation.id }, [
																			createVNode("td", { class: "table-id" }, toDisplayString(quotation.id), 1),
																			createVNode("td", null, toDisplayString(quotation.price ?? quotation.value ?? "—"), 1),
																			createVNode("td", null, toDisplayString(formatDate(quotation.created_at || quotation.date)), 1)
																		]);
																	}), 128))])]),
																	_: 2
																}, 1024)) : (openBlock(), createBlock(VAlert, {
																	key: 1,
																	type: "info",
																	variant: "tonal"
																}, {
																	default: withCtx(() => [createTextVNode(" Quotations отсутствуют ")]),
																	_: 1
																}))]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024);
													}), 128))]),
													_: 1
												})) : (openBlock(), createBlock(VAlert, {
													key: 1,
													type: "info",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(" Goods не найдены ")]),
													_: 1
												}))])]),
												_: 1
											}),
											createVNode(VWindowItem, {
												value: "units",
												class: "window-pane"
											}, {
												default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.units?.length ? (openBlock(), createBlock(VCard, {
													key: 0,
													rounded: "lg",
													variant: "tonal"
												}, {
													default: withCtx(() => [createVNode(VCardText, { class: "pt-3" }, {
														default: withCtx(() => [createVNode(VTable, {
															density: "compact",
															class: "data-table-fixed"
														}, {
															default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																createVNode("th", { class: "text-left" }, "ID"),
																createVNode("th", { class: "text-left" }, "Название"),
																createVNode("th", { class: "text-left" }, "action_id")
															])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.units, (unit) => {
																return openBlock(), createBlock("tr", { key: unit.id }, [
																	createVNode("td", { class: "table-id" }, toDisplayString(unit.id), 1),
																	createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(unit)), 1),
																	createVNode("td", null, toDisplayString(unit.pivot?.action_id ?? "—"), 1)
																]);
															}), 128))])]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})) : (openBlock(), createBlock(VAlert, {
													key: 1,
													type: "info",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(" Units не найдены ")]),
													_: 1
												}))])]),
												_: 1
											}),
											createVNode(VWindowItem, {
												value: "consumers",
												class: "window-pane"
											}, {
												default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4 consumers-pane" }, [product.value.consumers?.length ? (openBlock(), createBlock(VCard, {
													key: 0,
													rounded: "xl",
													variant: "flat",
													class: "consumers-card"
												}, {
													default: withCtx(() => [
														createVNode("div", { class: "consumers-hero px-4 px-md-6 py-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between ga-3 flex-wrap" }, [createVNode("div", null, [
															createVNode("div", { class: "text-overline consumers-overline mb-1" }, " Потребители продукта "),
															createVNode("div", { class: "text-h6 font-weight-bold" }, " Consumers "),
															createVNode("div", { class: "text-body-2 text-medium-emphasis mt-1" }, " Компании и unit, использующие текущий продукт ")
														]), createVNode(VChip, {
															color: "success",
															variant: "flat",
															size: "large"
														}, {
															default: withCtx(() => [createTextVNode(toDisplayString(product.value.consumers.length), 1)]),
															_: 1
														})])]),
														createVNode(VDivider),
														createVNode(VCardText, { class: "pt-4" }, {
															default: withCtx(() => [createVNode(VTable, {
																density: "comfortable",
																class: "data-table-fixed consumers-table"
															}, {
																default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																	createVNode("th", { class: "text-left" }, "ID"),
																	createVNode("th", { class: "text-left" }, "Unit"),
																	createVNode("th", { class: "text-left" }, "Measure"),
																	createVNode("th", { class: "text-left" }, "Количество"),
																	createVNode("th", { class: "text-left" }, "Сайт")
																])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.consumers, (consumer) => {
																	return openBlock(), createBlock("tr", { key: consumer.id }, [
																		createVNode("td", { class: "table-id" }, toDisplayString(consumer.id), 1),
																		createVNode("td", { class: "break-word" }, [consumer.unit?.id ? (openBlock(), createBlock(unref(Link), {
																			key: 0,
																			href: unref(route)("web.unit.show", consumer.unit.id),
																			class: "text-decoration-none"
																		}, {
																			default: withCtx(() => [createVNode(VChip, {
																				color: "success",
																				variant: "tonal",
																				size: "small",
																				class: "consumer-unit-chip"
																			}, {
																				default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.unit)), 1)]),
																				_: 2
																			}, 1024)]),
																			_: 2
																		}, 1032, ["href"])) : (openBlock(), createBlock("span", {
																			key: 1,
																			class: "text-medium-emphasis"
																		}, "—"))]),
																		createVNode("td", { class: "break-word" }, [consumer.measure ? (openBlock(), createBlock(VChip, {
																			key: 0,
																			color: "green-darken-2",
																			variant: "outlined",
																			size: "small"
																		}, {
																			default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.measure)), 1)]),
																			_: 2
																		}, 1024)) : (openBlock(), createBlock("span", {
																			key: 1,
																			class: "text-medium-emphasis"
																		}, "—"))]),
																		createVNode("td", null, [createVNode("span", { class: "consumer-qty" }, toDisplayString(consumer.quantity ?? "—"), 1)]),
																		createVNode("td", { class: "break-word" }, [consumerSite(consumer) ? (openBlock(), createBlock("a", {
																			key: 0,
																			href: normalizeUrl(consumerSite(consumer)),
																			target: "_blank",
																			rel: "noopener noreferrer",
																			class: "consumer-site-link"
																		}, toDisplayString(consumerSite(consumer)), 9, ["href"])) : (openBlock(), createBlock("span", {
																			key: 1,
																			class: "text-medium-emphasis"
																		}, "—"))])
																	]);
																}), 128))])]),
																_: 1
															})]),
															_: 1
														})
													]),
													_: 1
												})) : (openBlock(), createBlock(VAlert, {
													key: 1,
													type: "info",
													variant: "tonal",
													color: "success"
												}, {
													default: withCtx(() => [createTextVNode(" Consumers не найдены ")]),
													_: 1
												}))])]),
												_: 1
											}),
											createVNode(VWindowItem, {
												value: "sales",
												class: "window-pane"
											}, {
												default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.sales?.length ? (openBlock(), createBlock(VCard, {
													key: 0,
													rounded: "lg",
													variant: "tonal"
												}, {
													default: withCtx(() => [createVNode(VCardText, { class: "pt-3" }, {
														default: withCtx(() => [createVNode(VTable, {
															density: "compact",
															class: "data-table-fixed"
														}, {
															default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																createVNode("th", { class: "text-left" }, "ID"),
																createVNode("th", { class: "text-left" }, "Дата"),
																createVNode("th", { class: "text-left" }, "Контрагент"),
																createVNode("th", { class: "text-left" }, "Goods"),
																createVNode("th", { class: "text-left" }, "Сумма")
															])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.sales, (sale) => {
																return openBlock(), createBlock("tr", { key: sale.id }, [
																	createVNode("td", { class: "table-id" }, toDisplayString(sale.id), 1),
																	createVNode("td", null, toDisplayString(formatDate(sale.date || sale.created_at)), 1),
																	createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(sale.entity)), 1),
																	createVNode("td", null, [createVNode("div", { class: "d-flex flex-wrap ga-1" }, [(openBlock(true), createBlock(Fragment, null, renderList(sale.goods, (good) => {
																		return openBlock(), createBlock(VChip, {
																			key: good.id,
																			size: "x-small",
																			variant: "outlined"
																		}, {
																			default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(good)), 1)]),
																			_: 2
																		}, 1024);
																	}), 128))])]),
																	createVNode("td", null, toDisplayString(sale.total ?? "—"), 1)
																]);
															}), 128))])]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})) : (openBlock(), createBlock(VAlert, {
													key: 1,
													type: "info",
													variant: "tonal"
												}, {
													default: withCtx(() => [createTextVNode(" Продажи отсутствуют ")]),
													_: 1
												}))])]),
												_: 1
											})
										]),
										_: 1
									}, 8, ["modelValue", "onUpdate:modelValue"])])
								];
							}),
							_: 1
						}, _parent, _scopeId));
						else _push(ssrRenderComponent(VAlert, {
							type: "warning",
							variant: "tonal"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Продукт не найден `);
								else return [createTextVNode(" Продукт не найден ")];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode("div", { class: "page-header mb-3" }, [createVNode("div", { class: "d-flex align-center justify-space-between ga-3 flex-wrap" }, [createVNode("div", { class: "d-flex align-center ga-2 min-w-0" }, [createVNode(VBtn, {
						variant: "text",
						density: "comfortable",
						"prepend-icon": "mdi-arrow-left",
						onClick: goBack
					}, {
						default: withCtx(() => [createTextVNode(" Назад ")]),
						_: 1
					}), createVNode("div", { class: "min-w-0" }, [createVNode("div", { class: "text-h5 font-weight-bold text-truncate" }, toDisplayString(productTitle.value), 1), createVNode("div", { class: "text-caption text-medium-emphasis" }, " Карточка продукта ")])]), product.value ? (openBlock(), createBlock("div", {
						key: 0,
						class: "d-flex align-center ga-2 flex-wrap"
					}, [createVNode(VChip, {
						size: "small",
						variant: "outlined"
					}, {
						default: withCtx(() => [createTextVNode("ID: " + toDisplayString(product.value.id), 1)]),
						_: 1
					}), createVNode(VChip, {
						size: "small",
						variant: "outlined"
					}, {
						default: withCtx(() => [createTextVNode(toDisplayString(product.value.category?.rus || product.value.category?.name || "Без категории"), 1)]),
						_: 1
					})])) : createCommentVNode("", true)])]), loading.value ? (openBlock(), createBlock(VSkeletonLoader, {
						key: 0,
						type: "article, table"
					})) : error.value ? (openBlock(), createBlock(VAlert, {
						key: 1,
						type: "error",
						variant: "tonal"
					}, {
						default: withCtx(() => [createTextVNode(toDisplayString(error.value), 1)]),
						_: 1
					})) : product.value ? (openBlock(), createBlock(VCard, {
						key: 2,
						rounded: "xl",
						class: "product-card"
					}, {
						default: withCtx(() => [
							createVNode("div", { class: "tabs-header" }, [createVNode(VTabs, {
								modelValue: tab.value,
								"onUpdate:modelValue": ($event) => tab.value = $event,
								color: "primary",
								"align-tabs": "start",
								density: "compact",
								"show-arrows": "",
								class: "px-2"
							}, {
								default: withCtx(() => [
									createVNode(VTab, { value: "main" }, {
										default: withCtx(() => [createVNode(VIcon, {
											start: "",
											size: "18"
										}, {
											default: withCtx(() => [createTextVNode("mdi-view-dashboard-outline")]),
											_: 1
										}), createTextVNode(" Основное ")]),
										_: 1
									}),
									createVNode(VTab, { value: "translations" }, {
										default: withCtx(() => [createVNode(VIcon, {
											start: "",
											size: "18"
										}, {
											default: withCtx(() => [createTextVNode("mdi-translate")]),
											_: 1
										}), createTextVNode(" Переводы ")]),
										_: 1
									}),
									createVNode(VTab, { value: "manufacturers" }, {
										default: withCtx(() => [createVNode(VIcon, {
											start: "",
											size: "18"
										}, {
											default: withCtx(() => [createTextVNode("mdi-factory")]),
											_: 1
										}), createTextVNode(" Производители ")]),
										_: 1
									}),
									createVNode(VTab, { value: "components" }, {
										default: withCtx(() => [createVNode(VIcon, {
											start: "",
											size: "18"
										}, {
											default: withCtx(() => [createTextVNode("mdi-puzzle-outline")]),
											_: 1
										}), createTextVNode(" Компоненты ")]),
										_: 1
									}),
									createVNode(VTab, { value: "goods" }, {
										default: withCtx(() => [createVNode(VIcon, {
											start: "",
											size: "18"
										}, {
											default: withCtx(() => [createTextVNode("mdi-package-variant-closed")]),
											_: 1
										}), createTextVNode(" Goods ")]),
										_: 1
									}),
									createVNode(VTab, { value: "units" }, {
										default: withCtx(() => [createVNode(VIcon, {
											start: "",
											size: "18"
										}, {
											default: withCtx(() => [createTextVNode("mdi-domain")]),
											_: 1
										}), createTextVNode(" Units ")]),
										_: 1
									}),
									createVNode(VTab, { value: "consumers" }, {
										default: withCtx(() => [createVNode(VIcon, {
											start: "",
											size: "18"
										}, {
											default: withCtx(() => [createTextVNode("mdi-account-group-outline")]),
											_: 1
										}), createTextVNode(" Consumers ")]),
										_: 1
									}),
									createVNode(VTab, { value: "sales" }, {
										default: withCtx(() => [createVNode(VIcon, {
											start: "",
											size: "18"
										}, {
											default: withCtx(() => [createTextVNode("mdi-cash-multiple")]),
											_: 1
										}), createTextVNode(" Sales ")]),
										_: 1
									})
								]),
								_: 1
							}, 8, ["modelValue", "onUpdate:modelValue"])]),
							createVNode(VDivider),
							createVNode("div", { class: "tabs-body" }, [createVNode(VWindow, {
								modelValue: tab.value,
								"onUpdate:modelValue": ($event) => tab.value = $event,
								class: "window-fill",
								touch: false
							}, {
								default: withCtx(() => [
									createVNode(VWindowItem, {
										value: "main",
										class: "window-pane"
									}, {
										default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [createVNode(VRow, {
											class: "mb-2",
											dense: ""
										}, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(summaryChips.value, (item) => {
												return openBlock(), createBlock(VCol, {
													key: item.label,
													cols: "6",
													md: "4",
													lg: "2"
												}, {
													default: withCtx(() => [createVNode(VCard, {
														rounded: "lg",
														variant: "tonal",
														class: "stat-card"
													}, {
														default: withCtx(() => [createVNode(VCardText, { class: "py-3" }, {
															default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-1" }, toDisplayString(item.label), 1), createVNode("div", { class: "text-h6 font-weight-bold" }, toDisplayString(item.value), 1)]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1024)]),
													_: 2
												}, 1024);
											}), 128))]),
											_: 1
										}), createVNode(VRow, { dense: "" }, {
											default: withCtx(() => [
												createVNode(VCol, {
													cols: "12",
													lg: "7"
												}, {
													default: withCtx(() => [createVNode(VCard, {
														rounded: "lg",
														variant: "tonal",
														class: "h-100"
													}, {
														default: withCtx(() => [
															createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																default: withCtx(() => [createTextVNode(" Общая информация ")]),
																_: 1
															}),
															createVNode(VDivider),
															createVNode(VCardText, { class: "pt-3" }, {
																default: withCtx(() => [createVNode("div", { class: "info-grid" }, [
																	createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "ID"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.id || "—"), 1)]),
																	createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Название"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.rus || "—"), 1)]),
																	createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "English"), createVNode("div", { class: "info-value break-word" }, toDisplayString(product.value.eng || "—"), 1)]),
																	createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Категория"), createVNode("div", { class: "info-value" }, toDisplayString(product.value.category?.rus || product.value.category?.name || "—"), 1)]),
																	createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Создан"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.created_at)), 1)]),
																	createVNode("div", { class: "info-row" }, [createVNode("div", { class: "info-key" }, "Обновлён"), createVNode("div", { class: "info-value" }, toDisplayString(formatDate(product.value.updated_at)), 1)])
																])]),
																_: 1
															})
														]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VCol, {
													cols: "12",
													lg: "5"
												}, {
													default: withCtx(() => [createVNode(VCard, {
														rounded: "lg",
														variant: "tonal",
														class: "mb-3"
													}, {
														default: withCtx(() => [
															createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																default: withCtx(() => [createTextVNode(" Быстрые связи ")]),
																_: 1
															}),
															createVNode(VDivider),
															createVNode(VCardText, { class: "pt-3" }, {
																default: withCtx(() => [createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, " Производители "), product.value.manufacturers?.length ? (openBlock(), createBlock("div", {
																	key: 0,
																	class: "d-flex flex-wrap ga-2"
																}, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
																	return openBlock(), createBlock(unref(Link), {
																		key: manufacturer.id,
																		href: unref(route)("web.unit.show", manufacturer.id),
																		class: "text-decoration-none"
																	}, {
																		default: withCtx(() => [createVNode(VChip, {
																			size: "small",
																			variant: "outlined"
																		}, {
																			default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																			_: 2
																		}, 1024)]),
																		_: 2
																	}, 1032, ["href"]);
																}), 128))])) : (openBlock(), createBlock("div", {
																	key: 1,
																	class: "text-medium-emphasis"
																}, " Нет производителей "))]),
																_: 1
															})
														]),
														_: 1
													}), createVNode(VCard, {
														rounded: "lg",
														variant: "tonal"
													}, {
														default: withCtx(() => [
															createVNode(VCardTitle, { class: "text-subtitle-1" }, {
																default: withCtx(() => [createTextVNode(" Кратко ")]),
																_: 1
															}),
															createVNode(VDivider),
															createVNode(VCardText, { class: "pt-3" }, {
																default: withCtx(() => [createVNode("div", { class: "d-flex flex-column ga-2" }, [
																	createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Goods"), createVNode("strong", null, toDisplayString(product.value.goods?.length || 0), 1)]),
																	createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Components"), createVNode("strong", null, toDisplayString(product.value.components?.length || 0), 1)]),
																	createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Consumers"), createVNode("strong", null, toDisplayString(product.value.consumers?.length || 0), 1)]),
																	createVNode("div", { class: "d-flex justify-space-between ga-4" }, [createVNode("span", { class: "text-medium-emphasis" }, "Sales"), createVNode("strong", null, toDisplayString(product.value.sales?.length || 0), 1)])
																])]),
																_: 1
															})
														]),
														_: 1
													})]),
													_: 1
												}),
												createVNode(VCol, null, {
													default: withCtx(() => [createVNode(_sfc_main$1, {
														"product-id": product.value.id,
														"product-name": product.value.rus
													}, null, 8, ["product-id", "product-name"])]),
													_: 1
												})
											]),
											_: 1
										})])]),
										_: 1
									}),
									createVNode(VWindowItem, {
										value: "translations",
										class: "window-pane"
									}, {
										default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [createVNode(VCard, {
											rounded: "lg",
											variant: "tonal"
										}, {
											default: withCtx(() => [
												createVNode(VCardTitle, { class: "text-subtitle-1" }, {
													default: withCtx(() => [createTextVNode(" Названия и переводы ")]),
													_: 1
												}),
												createVNode(VDivider),
												createVNode(VCardText, { class: "pt-3" }, {
													default: withCtx(() => [createVNode(VTable, {
														density: "compact",
														class: "data-table-fixed"
													}, {
														default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "Поле"), createVNode("th", { class: "text-left" }, "Значение")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(languageRows.value, (row) => {
															return openBlock(), createBlock("tr", { key: row.key }, [createVNode("td", { class: "table-key" }, toDisplayString(row.label), 1), createVNode("td", { class: "break-word" }, toDisplayString(row.value || "—"), 1)]);
														}), 128))])]),
														_: 1
													})]),
													_: 1
												})
											]),
											_: 1
										})])]),
										_: 1
									}),
									createVNode(VWindowItem, {
										value: "manufacturers",
										class: "window-pane"
									}, {
										default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.manufacturers?.length ? (openBlock(), createBlock(VRow, {
											key: 0,
											dense: ""
										}, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(product.value.manufacturers, (manufacturer) => {
												return openBlock(), createBlock(VCol, {
													key: manufacturer.id,
													cols: "12",
													sm: "6",
													md: "4",
													xl: "3"
												}, {
													default: withCtx(() => [createVNode(VCard, {
														rounded: "lg",
														variant: "tonal",
														class: "h-100"
													}, {
														default: withCtx(() => [createVNode(VCardText, null, {
															default: withCtx(() => [createVNode("div", { class: "text-subtitle-2 font-weight-medium mb-2" }, [createVNode(unref(Link), {
																href: unref(route)("web.unit.show", manufacturer.id),
																class: "text-decoration-none"
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(manufacturer)), 1)]),
																_: 2
															}, 1032, ["href"])]), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(manufacturer.id), 1)]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1024)]),
													_: 2
												}, 1024);
											}), 128))]),
											_: 1
										})) : (openBlock(), createBlock(VAlert, {
											key: 1,
											type: "info",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(" Производители не найдены ")]),
											_: 1
										}))])]),
										_: 1
									}),
									createVNode(VWindowItem, {
										value: "components",
										class: "window-pane"
									}, {
										default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.components?.length ? (openBlock(), createBlock(VCard, {
											key: 0,
											rounded: "lg",
											variant: "tonal"
										}, {
											default: withCtx(() => [createVNode(VCardText, { class: "pt-3" }, {
												default: withCtx(() => [createVNode(VTable, {
													density: "compact",
													class: "data-table-fixed"
												}, {
													default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [createVNode("th", { class: "text-left" }, "ID"), createVNode("th", { class: "text-left" }, "Название")])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.components, (component) => {
														return openBlock(), createBlock("tr", { key: component.id }, [createVNode("td", { class: "table-id" }, toDisplayString(component.id), 1), createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(component)), 1)]);
													}), 128))])]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})) : (openBlock(), createBlock(VAlert, {
											key: 1,
											type: "info",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(" Компоненты отсутствуют ")]),
											_: 1
										}))])]),
										_: 1
									}),
									createVNode(VWindowItem, {
										value: "goods",
										class: "window-pane"
									}, {
										default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.goods?.length ? (openBlock(), createBlock(VExpansionPanels, {
											key: 0,
											variant: "accordion"
										}, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(product.value.goods, (good) => {
												return openBlock(), createBlock(VExpansionPanel, { key: good.id }, {
													default: withCtx(() => [createVNode(VExpansionPanelTitle, null, {
														default: withCtx(() => [createVNode("div", { class: "d-flex align-center justify-space-between w-100 ga-3 pr-4" }, [createVNode("div", { class: "min-w-0" }, [createVNode("div", { class: "font-weight-medium text-truncate" }, toDisplayString(entityTitle(good)), 1), createVNode("div", { class: "text-caption text-medium-emphasis" }, " ID: " + toDisplayString(good.id), 1)]), createVNode(VChip, {
															size: "small",
															variant: "outlined"
														}, {
															default: withCtx(() => [createTextVNode(" quotations: " + toDisplayString(good.quotations?.length || 0), 1)]),
															_: 2
														}, 1024)])]),
														_: 2
													}, 1024), createVNode(VExpansionPanelText, null, {
														default: withCtx(() => [good.quotations?.length ? (openBlock(), createBlock(VTable, {
															key: 0,
															density: "compact",
															class: "data-table-fixed"
														}, {
															default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
																createVNode("th", { class: "text-left" }, "ID"),
																createVNode("th", { class: "text-left" }, "Цена"),
																createVNode("th", { class: "text-left" }, "Дата")
															])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(good.quotations, (quotation) => {
																return openBlock(), createBlock("tr", { key: quotation.id }, [
																	createVNode("td", { class: "table-id" }, toDisplayString(quotation.id), 1),
																	createVNode("td", null, toDisplayString(quotation.price ?? quotation.value ?? "—"), 1),
																	createVNode("td", null, toDisplayString(formatDate(quotation.created_at || quotation.date)), 1)
																]);
															}), 128))])]),
															_: 2
														}, 1024)) : (openBlock(), createBlock(VAlert, {
															key: 1,
															type: "info",
															variant: "tonal"
														}, {
															default: withCtx(() => [createTextVNode(" Quotations отсутствуют ")]),
															_: 1
														}))]),
														_: 2
													}, 1024)]),
													_: 2
												}, 1024);
											}), 128))]),
											_: 1
										})) : (openBlock(), createBlock(VAlert, {
											key: 1,
											type: "info",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(" Goods не найдены ")]),
											_: 1
										}))])]),
										_: 1
									}),
									createVNode(VWindowItem, {
										value: "units",
										class: "window-pane"
									}, {
										default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.units?.length ? (openBlock(), createBlock(VCard, {
											key: 0,
											rounded: "lg",
											variant: "tonal"
										}, {
											default: withCtx(() => [createVNode(VCardText, { class: "pt-3" }, {
												default: withCtx(() => [createVNode(VTable, {
													density: "compact",
													class: "data-table-fixed"
												}, {
													default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
														createVNode("th", { class: "text-left" }, "ID"),
														createVNode("th", { class: "text-left" }, "Название"),
														createVNode("th", { class: "text-left" }, "action_id")
													])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.units, (unit) => {
														return openBlock(), createBlock("tr", { key: unit.id }, [
															createVNode("td", { class: "table-id" }, toDisplayString(unit.id), 1),
															createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(unit)), 1),
															createVNode("td", null, toDisplayString(unit.pivot?.action_id ?? "—"), 1)
														]);
													}), 128))])]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})) : (openBlock(), createBlock(VAlert, {
											key: 1,
											type: "info",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(" Units не найдены ")]),
											_: 1
										}))])]),
										_: 1
									}),
									createVNode(VWindowItem, {
										value: "consumers",
										class: "window-pane"
									}, {
										default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4 consumers-pane" }, [product.value.consumers?.length ? (openBlock(), createBlock(VCard, {
											key: 0,
											rounded: "xl",
											variant: "flat",
											class: "consumers-card"
										}, {
											default: withCtx(() => [
												createVNode("div", { class: "consumers-hero px-4 px-md-6 py-4" }, [createVNode("div", { class: "d-flex align-center justify-space-between ga-3 flex-wrap" }, [createVNode("div", null, [
													createVNode("div", { class: "text-overline consumers-overline mb-1" }, " Потребители продукта "),
													createVNode("div", { class: "text-h6 font-weight-bold" }, " Consumers "),
													createVNode("div", { class: "text-body-2 text-medium-emphasis mt-1" }, " Компании и unit, использующие текущий продукт ")
												]), createVNode(VChip, {
													color: "success",
													variant: "flat",
													size: "large"
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(product.value.consumers.length), 1)]),
													_: 1
												})])]),
												createVNode(VDivider),
												createVNode(VCardText, { class: "pt-4" }, {
													default: withCtx(() => [createVNode(VTable, {
														density: "comfortable",
														class: "data-table-fixed consumers-table"
													}, {
														default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
															createVNode("th", { class: "text-left" }, "ID"),
															createVNode("th", { class: "text-left" }, "Unit"),
															createVNode("th", { class: "text-left" }, "Measure"),
															createVNode("th", { class: "text-left" }, "Количество"),
															createVNode("th", { class: "text-left" }, "Сайт")
														])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.consumers, (consumer) => {
															return openBlock(), createBlock("tr", { key: consumer.id }, [
																createVNode("td", { class: "table-id" }, toDisplayString(consumer.id), 1),
																createVNode("td", { class: "break-word" }, [consumer.unit?.id ? (openBlock(), createBlock(unref(Link), {
																	key: 0,
																	href: unref(route)("web.unit.show", consumer.unit.id),
																	class: "text-decoration-none"
																}, {
																	default: withCtx(() => [createVNode(VChip, {
																		color: "success",
																		variant: "tonal",
																		size: "small",
																		class: "consumer-unit-chip"
																	}, {
																		default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.unit)), 1)]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1032, ["href"])) : (openBlock(), createBlock("span", {
																	key: 1,
																	class: "text-medium-emphasis"
																}, "—"))]),
																createVNode("td", { class: "break-word" }, [consumer.measure ? (openBlock(), createBlock(VChip, {
																	key: 0,
																	color: "green-darken-2",
																	variant: "outlined",
																	size: "small"
																}, {
																	default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(consumer.measure)), 1)]),
																	_: 2
																}, 1024)) : (openBlock(), createBlock("span", {
																	key: 1,
																	class: "text-medium-emphasis"
																}, "—"))]),
																createVNode("td", null, [createVNode("span", { class: "consumer-qty" }, toDisplayString(consumer.quantity ?? "—"), 1)]),
																createVNode("td", { class: "break-word" }, [consumerSite(consumer) ? (openBlock(), createBlock("a", {
																	key: 0,
																	href: normalizeUrl(consumerSite(consumer)),
																	target: "_blank",
																	rel: "noopener noreferrer",
																	class: "consumer-site-link"
																}, toDisplayString(consumerSite(consumer)), 9, ["href"])) : (openBlock(), createBlock("span", {
																	key: 1,
																	class: "text-medium-emphasis"
																}, "—"))])
															]);
														}), 128))])]),
														_: 1
													})]),
													_: 1
												})
											]),
											_: 1
										})) : (openBlock(), createBlock(VAlert, {
											key: 1,
											type: "info",
											variant: "tonal",
											color: "success"
										}, {
											default: withCtx(() => [createTextVNode(" Consumers не найдены ")]),
											_: 1
										}))])]),
										_: 1
									}),
									createVNode(VWindowItem, {
										value: "sales",
										class: "window-pane"
									}, {
										default: withCtx(() => [createVNode("div", { class: "tab-scroll pa-4" }, [product.value.sales?.length ? (openBlock(), createBlock(VCard, {
											key: 0,
											rounded: "lg",
											variant: "tonal"
										}, {
											default: withCtx(() => [createVNode(VCardText, { class: "pt-3" }, {
												default: withCtx(() => [createVNode(VTable, {
													density: "compact",
													class: "data-table-fixed"
												}, {
													default: withCtx(() => [createVNode("thead", null, [createVNode("tr", null, [
														createVNode("th", { class: "text-left" }, "ID"),
														createVNode("th", { class: "text-left" }, "Дата"),
														createVNode("th", { class: "text-left" }, "Контрагент"),
														createVNode("th", { class: "text-left" }, "Goods"),
														createVNode("th", { class: "text-left" }, "Сумма")
													])]), createVNode("tbody", null, [(openBlock(true), createBlock(Fragment, null, renderList(product.value.sales, (sale) => {
														return openBlock(), createBlock("tr", { key: sale.id }, [
															createVNode("td", { class: "table-id" }, toDisplayString(sale.id), 1),
															createVNode("td", null, toDisplayString(formatDate(sale.date || sale.created_at)), 1),
															createVNode("td", { class: "break-word" }, toDisplayString(entityTitle(sale.entity)), 1),
															createVNode("td", null, [createVNode("div", { class: "d-flex flex-wrap ga-1" }, [(openBlock(true), createBlock(Fragment, null, renderList(sale.goods, (good) => {
																return openBlock(), createBlock(VChip, {
																	key: good.id,
																	size: "x-small",
																	variant: "outlined"
																}, {
																	default: withCtx(() => [createTextVNode(toDisplayString(entityTitle(good)), 1)]),
																	_: 2
																}, 1024);
															}), 128))])]),
															createVNode("td", null, toDisplayString(sale.total ?? "—"), 1)
														]);
													}), 128))])]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})) : (openBlock(), createBlock(VAlert, {
											key: 1,
											type: "info",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(" Продажи отсутствуют ")]),
											_: 1
										}))])]),
										_: 1
									})
								]),
								_: 1
							}, 8, ["modelValue", "onUpdate:modelValue"])])
						]),
						_: 1
					})) : (openBlock(), createBlock(VAlert, {
						key: 3,
						type: "warning",
						variant: "tonal"
					}, {
						default: withCtx(() => [createTextVNode(" Продукт не найден ")]),
						_: 1
					}))];
				}),
				_: 1
			}, _parent));
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Product_02.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var Product_02_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-0302b41d"]]);
//#endregion
export { Product_02_default as default };

//# sourceMappingURL=Product_02-CuqEmNO8.js.map