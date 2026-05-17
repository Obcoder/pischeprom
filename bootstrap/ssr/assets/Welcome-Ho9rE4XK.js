import { $ as VAvatar, C as VRow, F as VCardText, I as VCardTitle, K as VDivider, L as VCardSubtitle, P as VCard, R as VCardActions, T as VContainer, U as VTextField, X as VChip, a as VTabs, c as VTab, et as VAlert, it as VProgressLinear, lt as VImg, ot as VIcon, rt as VBtn, ut as VExpandTransition, w as VCol } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import "./consts-BmbOcoGu.js";
import { t as LayoutDefault_default } from "./LayoutDefault-CXC0Lg2S.js";
import axios from "axios";
import { Link, router } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, mergeProps, onMounted, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, vShow, withCtx, withDirectives, withKeys } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderAttr, ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderStyle } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Components/Home/HomeHeroSection.vue
var _sfc_main$8 = {
	__name: "HomeHeroSection",
	__ssrInlineRender: true,
	props: {
		stats: {
			type: Object,
			default: () => ({
				productsCount: 0,
				goodsCount: 0
			})
		},
		heroGoods: {
			type: Array,
			default: () => []
		}
	},
	setup(__props) {
		const props = __props;
		const collageItems = computed(() => {
			return [...props.heroGoods].sort(() => Math.random() - .5).map((item, index) => {
				const patterns = [
					"tall",
					"wide",
					"square",
					"square",
					"medium",
					"square",
					"wide",
					"tall",
					"square",
					"medium"
				];
				return {
					...item,
					layout: patterns[index % patterns.length]
				};
			});
		});
		const quickQueries = [
			"Лецитин",
			"Глицерин",
			"Какао-масло",
			"Эмульгаторы",
			"Красители",
			"Консерванты"
		];
		const heroCategories = [
			{
				title: "Рыба",
				description: "Сырьё и товарные позиции для рыбной промышленности",
				href: "/Seaprom",
				icon: "mdi-fish"
			},
			{
				title: "Овощи",
				description: "Продукция и направления для овощного сегмента",
				href: "/vegetables",
				icon: "mdi-carrot"
			},
			{
				title: "Бакалея",
				description: "Сухие смеси, сыпучие товары и ингредиенты",
				href: "/grocery",
				icon: "mdi-package-variant-closed"
			}
		];
		const advantages = [
			"Фото и описания товаров",
			"Категории и быстрый поиск",
			"Характеристики и назначение",
			"Товары для B2B-задач"
		];
		function submitSearch() {
			const value = search.value?.trim() || "";
			router.get(route("public.goods.index"), { search: value }, {
				preserveState: true,
				preserveScroll: true
			});
		}
		function applyQuickQuery(query) {
			search.value = query;
			submitSearch();
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<section${ssrRenderAttrs(mergeProps({ class: "hero-v2" }, _attrs))} data-v-4c57da3b>`);
			_push(ssrRenderComponent(VContainer, { class: "py-8 py-md-12" }, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VRow, {
						align: "center",
						class: "hero-v2__row"
					}, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCol, {
									cols: "12",
									lg: "7"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<div class="hero-v2__content" data-v-4c57da3b${_scopeId}><div class="hero-v2__badge mb-4" data-v-4c57da3b${_scopeId}> Маркетплейс для пищевой промышленности </div><h1 class="hero-v2__title mb-4" data-v-4c57da3b${_scopeId}> Пищевое сырьё, ингредиенты и товары <span class="hero-v2__title-accent" data-v-4c57da3b${_scopeId}>для производственных задач</span></h1><p class="hero-v2__subtitle mb-6" data-v-4c57da3b${_scopeId}> Главная стартовая страница должна быстро отвечать на вопрос: <strong data-v-4c57da3b${_scopeId}>что можно купить, к какой категории относится товар, как он выглядит и где его применять.</strong> Именно на это и работает этот hero-блок. </p><div class="hero-v2__search mb-4" data-v-4c57da3b${_scopeId}>`);
											_push(ssrRenderComponent(VTextField, {
												modelValue: _ctx.search,
												"onUpdate:modelValue": ($event) => _ctx.search = $event,
												label: "Поиск по каталогу",
												placeholder: "Например: лецитин, глицерин, эмульгаторы, какао-масло",
												variant: "solo",
												"bg-color": "white",
												density: "comfortable",
												rounded: "xl",
												"hide-details": "",
												clearable: "",
												"prepend-inner-icon": "mdi-magnify",
												class: "hero-v2__search-field",
												onKeyup: submitSearch
											}, null, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												color: "#800000",
												size: "x-large",
												rounded: "xl",
												class: "hero-v2__search-btn",
												onClick: submitSearch
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Найти `);
													else return [createTextVNode(" Найти ")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(`</div><div class="mb-8" data-v-4c57da3b${_scopeId}><div class="text-body-2 text-medium-emphasis mb-3" data-v-4c57da3b${_scopeId}> Быстрые запросы: </div><div class="d-flex flex-wrap ga-2" data-v-4c57da3b${_scopeId}><!--[-->`);
											ssrRenderList(quickQueries, (query) => {
												_push(ssrRenderComponent(VChip, {
													key: query,
													variant: "outlined",
													color: "#800000",
													class: "cursor-pointer",
													onClick: ($event) => applyQuickQuery(query)
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`${ssrInterpolate(query)}`);
														else return [createTextVNode(toDisplayString(query), 1)];
													}),
													_: 2
												}, _parent, _scopeId));
											});
											_push(`<!--]--></div></div><div class="hero-v2__advantages mb-8" data-v-4c57da3b${_scopeId}><!--[-->`);
											ssrRenderList(advantages, (item) => {
												_push(`<div class="hero-v2__advantage" data-v-4c57da3b${_scopeId}>`);
												_push(ssrRenderComponent(VIcon, {
													size: "18",
													color: "#800000"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` mdi-check-circle `);
														else return [createTextVNode(" mdi-check-circle ")];
													}),
													_: 2
												}, _parent, _scopeId));
												_push(`<span data-v-4c57da3b${_scopeId}>${ssrInterpolate(item)}</span></div>`);
											});
											_push(`<!--]--></div>`);
											_push(ssrRenderComponent(VRow, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCol, {
															cols: "12",
															sm: "6"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VCard, {
																	rounded: "xl",
																	elevation: "2",
																	class: "hero-v2__stat-card"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VCardText, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`<div class="hero-v2__stat-value" data-v-4c57da3b${_scopeId}>${ssrInterpolate(__props.stats.productsCount)}</div><div class="hero-v2__stat-label" data-v-4c57da3b${_scopeId}> товарных наименований </div>`);
																				else return [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.productsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товарных наименований ")];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		else return [createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.productsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товарных наименований ")]),
																			_: 1
																		})];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VCard, {
																	rounded: "xl",
																	elevation: "2",
																	class: "hero-v2__stat-card"
																}, {
																	default: withCtx(() => [createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.productsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товарных наименований ")]),
																		_: 1
																	})]),
																	_: 1
																})];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCol, {
															cols: "12",
															sm: "6"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VCard, {
																	rounded: "xl",
																	elevation: "2",
																	class: "hero-v2__stat-card"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VCardText, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`<div class="hero-v2__stat-value" data-v-4c57da3b${_scopeId}>${ssrInterpolate(__props.stats.goodsCount)}</div><div class="hero-v2__stat-label" data-v-4c57da3b${_scopeId}> товаров в каталоге </div>`);
																				else return [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.goodsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товаров в каталоге ")];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		else return [createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.goodsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товаров в каталоге ")]),
																			_: 1
																		})];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VCard, {
																	rounded: "xl",
																	elevation: "2",
																	class: "hero-v2__stat-card"
																}, {
																	default: withCtx(() => [createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.goodsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товаров в каталоге ")]),
																		_: 1
																	})]),
																	_: 1
																})];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VCol, {
														cols: "12",
														sm: "6"
													}, {
														default: withCtx(() => [createVNode(VCard, {
															rounded: "xl",
															elevation: "2",
															class: "hero-v2__stat-card"
														}, {
															default: withCtx(() => [createVNode(VCardText, null, {
																default: withCtx(() => [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.productsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товарных наименований ")]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													}), createVNode(VCol, {
														cols: "12",
														sm: "6"
													}, {
														default: withCtx(() => [createVNode(VCard, {
															rounded: "xl",
															elevation: "2",
															class: "hero-v2__stat-card"
														}, {
															default: withCtx(() => [createVNode(VCardText, null, {
																default: withCtx(() => [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.goodsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товаров в каталоге ")]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(`</div>`);
										} else return [createVNode("div", { class: "hero-v2__content" }, [
											createVNode("div", { class: "hero-v2__badge mb-4" }, " Маркетплейс для пищевой промышленности "),
											createVNode("h1", { class: "hero-v2__title mb-4" }, [createTextVNode(" Пищевое сырьё, ингредиенты и товары "), createVNode("span", { class: "hero-v2__title-accent" }, "для производственных задач")]),
											createVNode("p", { class: "hero-v2__subtitle mb-6" }, [
												createTextVNode(" Главная стартовая страница должна быстро отвечать на вопрос: "),
												createVNode("strong", null, "что можно купить, к какой категории относится товар, как он выглядит и где его применять."),
												createTextVNode(" Именно на это и работает этот hero-блок. ")
											]),
											createVNode("div", { class: "hero-v2__search mb-4" }, [createVNode(VTextField, {
												modelValue: _ctx.search,
												"onUpdate:modelValue": ($event) => _ctx.search = $event,
												label: "Поиск по каталогу",
												placeholder: "Например: лецитин, глицерин, эмульгаторы, какао-масло",
												variant: "solo",
												"bg-color": "white",
												density: "comfortable",
												rounded: "xl",
												"hide-details": "",
												clearable: "",
												"prepend-inner-icon": "mdi-magnify",
												class: "hero-v2__search-field",
												onKeyup: withKeys(submitSearch, ["enter"])
											}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VBtn, {
												color: "#800000",
												size: "x-large",
												rounded: "xl",
												class: "hero-v2__search-btn",
												onClick: submitSearch
											}, {
												default: withCtx(() => [createTextVNode(" Найти ")]),
												_: 1
											})]),
											createVNode("div", { class: "mb-8" }, [createVNode("div", { class: "text-body-2 text-medium-emphasis mb-3" }, " Быстрые запросы: "), createVNode("div", { class: "d-flex flex-wrap ga-2" }, [(openBlock(), createBlock(Fragment, null, renderList(quickQueries, (query) => {
												return createVNode(VChip, {
													key: query,
													variant: "outlined",
													color: "#800000",
													class: "cursor-pointer",
													onClick: ($event) => applyQuickQuery(query)
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(query), 1)]),
													_: 2
												}, 1032, ["onClick"]);
											}), 64))])]),
											createVNode("div", { class: "hero-v2__advantages mb-8" }, [(openBlock(), createBlock(Fragment, null, renderList(advantages, (item) => {
												return createVNode("div", {
													key: item,
													class: "hero-v2__advantage"
												}, [createVNode(VIcon, {
													size: "18",
													color: "#800000"
												}, {
													default: withCtx(() => [createTextVNode(" mdi-check-circle ")]),
													_: 1
												}), createVNode("span", null, toDisplayString(item), 1)]);
											}), 64))]),
											createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, {
													cols: "12",
													sm: "6"
												}, {
													default: withCtx(() => [createVNode(VCard, {
														rounded: "xl",
														elevation: "2",
														class: "hero-v2__stat-card"
													}, {
														default: withCtx(() => [createVNode(VCardText, null, {
															default: withCtx(() => [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.productsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товарных наименований ")]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												}), createVNode(VCol, {
													cols: "12",
													sm: "6"
												}, {
													default: withCtx(() => [createVNode(VCard, {
														rounded: "xl",
														elevation: "2",
														class: "hero-v2__stat-card"
													}, {
														default: withCtx(() => [createVNode(VCardText, null, {
															default: withCtx(() => [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.goodsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товаров в каталоге ")]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})
										])];
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
												rounded: "xl",
												elevation: "4",
												class: "hero-v2__visual-main overflow-hidden"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(`<div class="hero-v2__collage" data-v-4c57da3b${_scopeId}><!--[-->`);
														ssrRenderList(collageItems.value, (item) => {
															_push(ssrRenderComponent(unref(Link), {
																key: item.id,
																href: unref(route)("public.goods.show", { good: item.slug }),
																class: ["hero-v2__collage-item", `hero-v2__collage-item--${item.layout}`]
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`<div class="hero-v2__collage-media" data-v-4c57da3b${_scopeId}><img${ssrRenderAttr("src", item.ava_thumb)}${ssrRenderAttr("alt", item.name)} loading="lazy" data-v-4c57da3b${_scopeId}><div class="hero-v2__collage-overlay" data-v-4c57da3b${_scopeId}><div class="hero-v2__collage-name" data-v-4c57da3b${_scopeId}>${ssrInterpolate(item.name)}</div><div class="hero-v2__collage-action" data-v-4c57da3b${_scopeId}> Смотреть товар </div></div></div>`);
																	else return [createVNode("div", { class: "hero-v2__collage-media" }, [createVNode("img", {
																		src: item.ava_thumb,
																		alt: item.name,
																		loading: "lazy"
																	}, null, 8, ["src", "alt"]), createVNode("div", { class: "hero-v2__collage-overlay" }, [createVNode("div", { class: "hero-v2__collage-name" }, toDisplayString(item.name), 1), createVNode("div", { class: "hero-v2__collage-action" }, " Смотреть товар ")])])];
																}),
																_: 2
															}, _parent, _scopeId));
														});
														_push(`<!--]--><div class="hero-v2__visual-overlay" data-v-4c57da3b${_scopeId}><div class="hero-v2__visual-top-chip" data-v-4c57da3b${_scopeId}> Каталог пищевой промышленности </div><div class="hero-v2__visual-bottom-card" data-v-4c57da3b${_scopeId}><div class="hero-v2__visual-bottom-title" data-v-4c57da3b${_scopeId}> Удобный вход в категории </div><div class="hero-v2__visual-bottom-text" data-v-4c57da3b${_scopeId}> Быстрый переход к товарам, фото, описаниям и характеристикам </div></div></div></div>`);
													} else return [createVNode("div", { class: "hero-v2__collage" }, [(openBlock(true), createBlock(Fragment, null, renderList(collageItems.value, (item) => {
														return openBlock(), createBlock(unref(Link), {
															key: item.id,
															href: unref(route)("public.goods.show", { good: item.slug }),
															class: ["hero-v2__collage-item", `hero-v2__collage-item--${item.layout}`]
														}, {
															default: withCtx(() => [createVNode("div", { class: "hero-v2__collage-media" }, [createVNode("img", {
																src: item.ava_thumb,
																alt: item.name,
																loading: "lazy"
															}, null, 8, ["src", "alt"]), createVNode("div", { class: "hero-v2__collage-overlay" }, [createVNode("div", { class: "hero-v2__collage-name" }, toDisplayString(item.name), 1), createVNode("div", { class: "hero-v2__collage-action" }, " Смотреть товар ")])])]),
															_: 2
														}, 1032, ["href", "class"]);
													}), 128)), createVNode("div", { class: "hero-v2__visual-overlay" }, [createVNode("div", { class: "hero-v2__visual-top-chip" }, " Каталог пищевой промышленности "), createVNode("div", { class: "hero-v2__visual-bottom-card" }, [createVNode("div", { class: "hero-v2__visual-bottom-title" }, " Удобный вход в категории "), createVNode("div", { class: "hero-v2__visual-bottom-text" }, " Быстрый переход к товарам, фото, описаниям и характеристикам ")])])])];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(`<div class="hero-v2__category-grid" data-v-4c57da3b${_scopeId}><!--[-->`);
											ssrRenderList(heroCategories, (item) => {
												_push(ssrRenderComponent(unref(Link), {
													key: item.title,
													href: item.href,
													class: "hero-v2__category-link"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VCard, {
															rounded: "xl",
															elevation: "4",
															class: "hero-v2__category-card"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VCardText, { class: "pa-4" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(`<div class="hero-v2__category-icon-wrap mb-3" data-v-4c57da3b${_scopeId}>`);
																			_push(ssrRenderComponent(VIcon, {
																				size: "24",
																				color: "#800000"
																			}, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(`${ssrInterpolate(item.icon)}`);
																					else return [createTextVNode(toDisplayString(item.icon), 1)];
																				}),
																				_: 2
																			}, _parent, _scopeId));
																			_push(`</div><div class="hero-v2__category-title mb-2" data-v-4c57da3b${_scopeId}>${ssrInterpolate(item.title)}</div><div class="hero-v2__category-description" data-v-4c57da3b${_scopeId}>${ssrInterpolate(item.description)}</div>`);
																		} else return [
																			createVNode("div", { class: "hero-v2__category-icon-wrap mb-3" }, [createVNode(VIcon, {
																				size: "24",
																				color: "#800000"
																			}, {
																				default: withCtx(() => [createTextVNode(toDisplayString(item.icon), 1)]),
																				_: 2
																			}, 1024)]),
																			createVNode("div", { class: "hero-v2__category-title mb-2" }, toDisplayString(item.title), 1),
																			createVNode("div", { class: "hero-v2__category-description" }, toDisplayString(item.description), 1)
																		];
																	}),
																	_: 2
																}, _parent, _scopeId));
																else return [createVNode(VCardText, { class: "pa-4" }, {
																	default: withCtx(() => [
																		createVNode("div", { class: "hero-v2__category-icon-wrap mb-3" }, [createVNode(VIcon, {
																			size: "24",
																			color: "#800000"
																		}, {
																			default: withCtx(() => [createTextVNode(toDisplayString(item.icon), 1)]),
																			_: 2
																		}, 1024)]),
																		createVNode("div", { class: "hero-v2__category-title mb-2" }, toDisplayString(item.title), 1),
																		createVNode("div", { class: "hero-v2__category-description" }, toDisplayString(item.description), 1)
																	]),
																	_: 2
																}, 1024)];
															}),
															_: 2
														}, _parent, _scopeId));
														else return [createVNode(VCard, {
															rounded: "xl",
															elevation: "4",
															class: "hero-v2__category-card"
														}, {
															default: withCtx(() => [createVNode(VCardText, { class: "pa-4" }, {
																default: withCtx(() => [
																	createVNode("div", { class: "hero-v2__category-icon-wrap mb-3" }, [createVNode(VIcon, {
																		size: "24",
																		color: "#800000"
																	}, {
																		default: withCtx(() => [createTextVNode(toDisplayString(item.icon), 1)]),
																		_: 2
																	}, 1024)]),
																	createVNode("div", { class: "hero-v2__category-title mb-2" }, toDisplayString(item.title), 1),
																	createVNode("div", { class: "hero-v2__category-description" }, toDisplayString(item.description), 1)
																]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024)];
													}),
													_: 2
												}, _parent, _scopeId));
											});
											_push(`<!--]--></div>`);
										} else return [createVNode(VCard, {
											rounded: "xl",
											elevation: "4",
											class: "hero-v2__visual-main overflow-hidden"
										}, {
											default: withCtx(() => [createVNode("div", { class: "hero-v2__collage" }, [(openBlock(true), createBlock(Fragment, null, renderList(collageItems.value, (item) => {
												return openBlock(), createBlock(unref(Link), {
													key: item.id,
													href: unref(route)("public.goods.show", { good: item.slug }),
													class: ["hero-v2__collage-item", `hero-v2__collage-item--${item.layout}`]
												}, {
													default: withCtx(() => [createVNode("div", { class: "hero-v2__collage-media" }, [createVNode("img", {
														src: item.ava_thumb,
														alt: item.name,
														loading: "lazy"
													}, null, 8, ["src", "alt"]), createVNode("div", { class: "hero-v2__collage-overlay" }, [createVNode("div", { class: "hero-v2__collage-name" }, toDisplayString(item.name), 1), createVNode("div", { class: "hero-v2__collage-action" }, " Смотреть товар ")])])]),
													_: 2
												}, 1032, ["href", "class"]);
											}), 128)), createVNode("div", { class: "hero-v2__visual-overlay" }, [createVNode("div", { class: "hero-v2__visual-top-chip" }, " Каталог пищевой промышленности "), createVNode("div", { class: "hero-v2__visual-bottom-card" }, [createVNode("div", { class: "hero-v2__visual-bottom-title" }, " Удобный вход в категории "), createVNode("div", { class: "hero-v2__visual-bottom-text" }, " Быстрый переход к товарам, фото, описаниям и характеристикам ")])])])]),
											_: 1
										}), createVNode("div", { class: "hero-v2__category-grid" }, [(openBlock(), createBlock(Fragment, null, renderList(heroCategories, (item) => {
											return createVNode(unref(Link), {
												key: item.title,
												href: item.href,
												class: "hero-v2__category-link"
											}, {
												default: withCtx(() => [createVNode(VCard, {
													rounded: "xl",
													elevation: "4",
													class: "hero-v2__category-card"
												}, {
													default: withCtx(() => [createVNode(VCardText, { class: "pa-4" }, {
														default: withCtx(() => [
															createVNode("div", { class: "hero-v2__category-icon-wrap mb-3" }, [createVNode(VIcon, {
																size: "24",
																color: "#800000"
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(item.icon), 1)]),
																_: 2
															}, 1024)]),
															createVNode("div", { class: "hero-v2__category-title mb-2" }, toDisplayString(item.title), 1),
															createVNode("div", { class: "hero-v2__category-description" }, toDisplayString(item.description), 1)
														]),
														_: 2
													}, 1024)]),
													_: 2
												}, 1024)]),
												_: 2
											}, 1032, ["href"]);
										}), 64))])];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [createVNode(VCol, {
								cols: "12",
								lg: "7"
							}, {
								default: withCtx(() => [createVNode("div", { class: "hero-v2__content" }, [
									createVNode("div", { class: "hero-v2__badge mb-4" }, " Маркетплейс для пищевой промышленности "),
									createVNode("h1", { class: "hero-v2__title mb-4" }, [createTextVNode(" Пищевое сырьё, ингредиенты и товары "), createVNode("span", { class: "hero-v2__title-accent" }, "для производственных задач")]),
									createVNode("p", { class: "hero-v2__subtitle mb-6" }, [
										createTextVNode(" Главная стартовая страница должна быстро отвечать на вопрос: "),
										createVNode("strong", null, "что можно купить, к какой категории относится товар, как он выглядит и где его применять."),
										createTextVNode(" Именно на это и работает этот hero-блок. ")
									]),
									createVNode("div", { class: "hero-v2__search mb-4" }, [createVNode(VTextField, {
										modelValue: _ctx.search,
										"onUpdate:modelValue": ($event) => _ctx.search = $event,
										label: "Поиск по каталогу",
										placeholder: "Например: лецитин, глицерин, эмульгаторы, какао-масло",
										variant: "solo",
										"bg-color": "white",
										density: "comfortable",
										rounded: "xl",
										"hide-details": "",
										clearable: "",
										"prepend-inner-icon": "mdi-magnify",
										class: "hero-v2__search-field",
										onKeyup: withKeys(submitSearch, ["enter"])
									}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VBtn, {
										color: "#800000",
										size: "x-large",
										rounded: "xl",
										class: "hero-v2__search-btn",
										onClick: submitSearch
									}, {
										default: withCtx(() => [createTextVNode(" Найти ")]),
										_: 1
									})]),
									createVNode("div", { class: "mb-8" }, [createVNode("div", { class: "text-body-2 text-medium-emphasis mb-3" }, " Быстрые запросы: "), createVNode("div", { class: "d-flex flex-wrap ga-2" }, [(openBlock(), createBlock(Fragment, null, renderList(quickQueries, (query) => {
										return createVNode(VChip, {
											key: query,
											variant: "outlined",
											color: "#800000",
											class: "cursor-pointer",
											onClick: ($event) => applyQuickQuery(query)
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(query), 1)]),
											_: 2
										}, 1032, ["onClick"]);
									}), 64))])]),
									createVNode("div", { class: "hero-v2__advantages mb-8" }, [(openBlock(), createBlock(Fragment, null, renderList(advantages, (item) => {
										return createVNode("div", {
											key: item,
											class: "hero-v2__advantage"
										}, [createVNode(VIcon, {
											size: "18",
											color: "#800000"
										}, {
											default: withCtx(() => [createTextVNode(" mdi-check-circle ")]),
											_: 1
										}), createVNode("span", null, toDisplayString(item), 1)]);
									}), 64))]),
									createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, {
											cols: "12",
											sm: "6"
										}, {
											default: withCtx(() => [createVNode(VCard, {
												rounded: "xl",
												elevation: "2",
												class: "hero-v2__stat-card"
											}, {
												default: withCtx(() => [createVNode(VCardText, null, {
													default: withCtx(() => [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.productsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товарных наименований ")]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										}), createVNode(VCol, {
											cols: "12",
											sm: "6"
										}, {
											default: withCtx(() => [createVNode(VCard, {
												rounded: "xl",
												elevation: "2",
												class: "hero-v2__stat-card"
											}, {
												default: withCtx(() => [createVNode(VCardText, null, {
													default: withCtx(() => [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.goodsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товаров в каталоге ")]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									})
								])]),
								_: 1
							}), createVNode(VCol, {
								cols: "12",
								lg: "5"
							}, {
								default: withCtx(() => [createVNode(VCard, {
									rounded: "xl",
									elevation: "4",
									class: "hero-v2__visual-main overflow-hidden"
								}, {
									default: withCtx(() => [createVNode("div", { class: "hero-v2__collage" }, [(openBlock(true), createBlock(Fragment, null, renderList(collageItems.value, (item) => {
										return openBlock(), createBlock(unref(Link), {
											key: item.id,
											href: unref(route)("public.goods.show", { good: item.slug }),
											class: ["hero-v2__collage-item", `hero-v2__collage-item--${item.layout}`]
										}, {
											default: withCtx(() => [createVNode("div", { class: "hero-v2__collage-media" }, [createVNode("img", {
												src: item.ava_thumb,
												alt: item.name,
												loading: "lazy"
											}, null, 8, ["src", "alt"]), createVNode("div", { class: "hero-v2__collage-overlay" }, [createVNode("div", { class: "hero-v2__collage-name" }, toDisplayString(item.name), 1), createVNode("div", { class: "hero-v2__collage-action" }, " Смотреть товар ")])])]),
											_: 2
										}, 1032, ["href", "class"]);
									}), 128)), createVNode("div", { class: "hero-v2__visual-overlay" }, [createVNode("div", { class: "hero-v2__visual-top-chip" }, " Каталог пищевой промышленности "), createVNode("div", { class: "hero-v2__visual-bottom-card" }, [createVNode("div", { class: "hero-v2__visual-bottom-title" }, " Удобный вход в категории "), createVNode("div", { class: "hero-v2__visual-bottom-text" }, " Быстрый переход к товарам, фото, описаниям и характеристикам ")])])])]),
									_: 1
								}), createVNode("div", { class: "hero-v2__category-grid" }, [(openBlock(), createBlock(Fragment, null, renderList(heroCategories, (item) => {
									return createVNode(unref(Link), {
										key: item.title,
										href: item.href,
										class: "hero-v2__category-link"
									}, {
										default: withCtx(() => [createVNode(VCard, {
											rounded: "xl",
											elevation: "4",
											class: "hero-v2__category-card"
										}, {
											default: withCtx(() => [createVNode(VCardText, { class: "pa-4" }, {
												default: withCtx(() => [
													createVNode("div", { class: "hero-v2__category-icon-wrap mb-3" }, [createVNode(VIcon, {
														size: "24",
														color: "#800000"
													}, {
														default: withCtx(() => [createTextVNode(toDisplayString(item.icon), 1)]),
														_: 2
													}, 1024)]),
													createVNode("div", { class: "hero-v2__category-title mb-2" }, toDisplayString(item.title), 1),
													createVNode("div", { class: "hero-v2__category-description" }, toDisplayString(item.description), 1)
												]),
												_: 2
											}, 1024)]),
											_: 2
										}, 1024)]),
										_: 2
									}, 1032, ["href"]);
								}), 64))])]),
								_: 1
							})];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VRow, {
						align: "center",
						class: "hero-v2__row"
					}, {
						default: withCtx(() => [createVNode(VCol, {
							cols: "12",
							lg: "7"
						}, {
							default: withCtx(() => [createVNode("div", { class: "hero-v2__content" }, [
								createVNode("div", { class: "hero-v2__badge mb-4" }, " Маркетплейс для пищевой промышленности "),
								createVNode("h1", { class: "hero-v2__title mb-4" }, [createTextVNode(" Пищевое сырьё, ингредиенты и товары "), createVNode("span", { class: "hero-v2__title-accent" }, "для производственных задач")]),
								createVNode("p", { class: "hero-v2__subtitle mb-6" }, [
									createTextVNode(" Главная стартовая страница должна быстро отвечать на вопрос: "),
									createVNode("strong", null, "что можно купить, к какой категории относится товар, как он выглядит и где его применять."),
									createTextVNode(" Именно на это и работает этот hero-блок. ")
								]),
								createVNode("div", { class: "hero-v2__search mb-4" }, [createVNode(VTextField, {
									modelValue: _ctx.search,
									"onUpdate:modelValue": ($event) => _ctx.search = $event,
									label: "Поиск по каталогу",
									placeholder: "Например: лецитин, глицерин, эмульгаторы, какао-масло",
									variant: "solo",
									"bg-color": "white",
									density: "comfortable",
									rounded: "xl",
									"hide-details": "",
									clearable: "",
									"prepend-inner-icon": "mdi-magnify",
									class: "hero-v2__search-field",
									onKeyup: withKeys(submitSearch, ["enter"])
								}, null, 8, ["modelValue", "onUpdate:modelValue"]), createVNode(VBtn, {
									color: "#800000",
									size: "x-large",
									rounded: "xl",
									class: "hero-v2__search-btn",
									onClick: submitSearch
								}, {
									default: withCtx(() => [createTextVNode(" Найти ")]),
									_: 1
								})]),
								createVNode("div", { class: "mb-8" }, [createVNode("div", { class: "text-body-2 text-medium-emphasis mb-3" }, " Быстрые запросы: "), createVNode("div", { class: "d-flex flex-wrap ga-2" }, [(openBlock(), createBlock(Fragment, null, renderList(quickQueries, (query) => {
									return createVNode(VChip, {
										key: query,
										variant: "outlined",
										color: "#800000",
										class: "cursor-pointer",
										onClick: ($event) => applyQuickQuery(query)
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(query), 1)]),
										_: 2
									}, 1032, ["onClick"]);
								}), 64))])]),
								createVNode("div", { class: "hero-v2__advantages mb-8" }, [(openBlock(), createBlock(Fragment, null, renderList(advantages, (item) => {
									return createVNode("div", {
										key: item,
										class: "hero-v2__advantage"
									}, [createVNode(VIcon, {
										size: "18",
										color: "#800000"
									}, {
										default: withCtx(() => [createTextVNode(" mdi-check-circle ")]),
										_: 1
									}), createVNode("span", null, toDisplayString(item), 1)]);
								}), 64))]),
								createVNode(VRow, null, {
									default: withCtx(() => [createVNode(VCol, {
										cols: "12",
										sm: "6"
									}, {
										default: withCtx(() => [createVNode(VCard, {
											rounded: "xl",
											elevation: "2",
											class: "hero-v2__stat-card"
										}, {
											default: withCtx(() => [createVNode(VCardText, null, {
												default: withCtx(() => [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.productsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товарных наименований ")]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									}), createVNode(VCol, {
										cols: "12",
										sm: "6"
									}, {
										default: withCtx(() => [createVNode(VCard, {
											rounded: "xl",
											elevation: "2",
											class: "hero-v2__stat-card"
										}, {
											default: withCtx(() => [createVNode(VCardText, null, {
												default: withCtx(() => [createVNode("div", { class: "hero-v2__stat-value" }, toDisplayString(__props.stats.goodsCount), 1), createVNode("div", { class: "hero-v2__stat-label" }, " товаров в каталоге ")]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									})]),
									_: 1
								})
							])]),
							_: 1
						}), createVNode(VCol, {
							cols: "12",
							lg: "5"
						}, {
							default: withCtx(() => [createVNode(VCard, {
								rounded: "xl",
								elevation: "4",
								class: "hero-v2__visual-main overflow-hidden"
							}, {
								default: withCtx(() => [createVNode("div", { class: "hero-v2__collage" }, [(openBlock(true), createBlock(Fragment, null, renderList(collageItems.value, (item) => {
									return openBlock(), createBlock(unref(Link), {
										key: item.id,
										href: unref(route)("public.goods.show", { good: item.slug }),
										class: ["hero-v2__collage-item", `hero-v2__collage-item--${item.layout}`]
									}, {
										default: withCtx(() => [createVNode("div", { class: "hero-v2__collage-media" }, [createVNode("img", {
											src: item.ava_thumb,
											alt: item.name,
											loading: "lazy"
										}, null, 8, ["src", "alt"]), createVNode("div", { class: "hero-v2__collage-overlay" }, [createVNode("div", { class: "hero-v2__collage-name" }, toDisplayString(item.name), 1), createVNode("div", { class: "hero-v2__collage-action" }, " Смотреть товар ")])])]),
										_: 2
									}, 1032, ["href", "class"]);
								}), 128)), createVNode("div", { class: "hero-v2__visual-overlay" }, [createVNode("div", { class: "hero-v2__visual-top-chip" }, " Каталог пищевой промышленности "), createVNode("div", { class: "hero-v2__visual-bottom-card" }, [createVNode("div", { class: "hero-v2__visual-bottom-title" }, " Удобный вход в категории "), createVNode("div", { class: "hero-v2__visual-bottom-text" }, " Быстрый переход к товарам, фото, описаниям и характеристикам ")])])])]),
								_: 1
							}), createVNode("div", { class: "hero-v2__category-grid" }, [(openBlock(), createBlock(Fragment, null, renderList(heroCategories, (item) => {
								return createVNode(unref(Link), {
									key: item.title,
									href: item.href,
									class: "hero-v2__category-link"
								}, {
									default: withCtx(() => [createVNode(VCard, {
										rounded: "xl",
										elevation: "4",
										class: "hero-v2__category-card"
									}, {
										default: withCtx(() => [createVNode(VCardText, { class: "pa-4" }, {
											default: withCtx(() => [
												createVNode("div", { class: "hero-v2__category-icon-wrap mb-3" }, [createVNode(VIcon, {
													size: "24",
													color: "#800000"
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(item.icon), 1)]),
													_: 2
												}, 1024)]),
												createVNode("div", { class: "hero-v2__category-title mb-2" }, toDisplayString(item.title), 1),
												createVNode("div", { class: "hero-v2__category-description" }, toDisplayString(item.description), 1)
											]),
											_: 2
										}, 1024)]),
										_: 2
									}, 1024)]),
									_: 2
								}, 1032, ["href"]);
							}), 64))])]),
							_: 1
						})]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
			_push(`</section>`);
		};
	}
};
var _sfc_setup$8 = _sfc_main$8.setup;
_sfc_main$8.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Home/HomeHeroSection.vue");
	return _sfc_setup$8 ? _sfc_setup$8(props, ctx) : void 0;
};
var HomeHeroSection_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main$8, [["__scopeId", "data-v-4c57da3b"]]);
//#endregion
//#region resources/js/Components/Home/SectionHeader.vue
var _sfc_main$7 = {
	__name: "SectionHeader",
	__ssrInlineRender: true,
	props: {
		title: {
			type: String,
			required: true
		},
		subtitle: {
			type: String,
			default: ""
		}
	},
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${ssrRenderAttrs(mergeProps({ class: "mb-6" }, _attrs))}><div class="text-h4 font-weight-bold mb-2">${ssrInterpolate(__props.title)}</div>`);
			if (__props.subtitle) _push(`<div class="text-body-1 text-medium-emphasis">${ssrInterpolate(__props.subtitle)}</div>`);
			else _push(`<!---->`);
			_push(`</div>`);
		};
	}
};
var _sfc_setup$7 = _sfc_main$7.setup;
_sfc_main$7.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Home/SectionHeader.vue");
	return _sfc_setup$7 ? _sfc_setup$7(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Home/CategoryCard.vue
var _sfc_main$6 = {
	__name: "CategoryCard",
	__ssrInlineRender: true,
	props: { category: {
		type: Object,
		required: true
	} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(unref(Link), mergeProps({
				href: unref(route)("category.show", __props.category.id),
				class: "text-decoration-none"
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VCard, {
						rounded: "xl",
						elevation: "2",
						class: "category-card h-100 overflow-hidden"
					}, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VImg, {
									src: __props.category.image || "/images/placeholders/category.jpg",
									height: "180",
									cover: ""
								}, null, _parent, _scopeId));
								_push(ssrRenderComponent(VCardText, { class: "pa-4" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<div class="text-h6 font-weight-bold text-high-emphasis mb-2" data-v-44e767a6${_scopeId}>${ssrInterpolate(__props.category.name)}</div>`);
											if (__props.category.goods_count !== void 0) _push(`<div class="text-body-2 text-medium-emphasis" data-v-44e767a6${_scopeId}>${ssrInterpolate(__props.category.goods_count)} товаров </div>`);
											else _push(`<!---->`);
										} else return [createVNode("div", { class: "text-h6 font-weight-bold text-high-emphasis mb-2" }, toDisplayString(__props.category.name), 1), __props.category.goods_count !== void 0 ? (openBlock(), createBlock("div", {
											key: 0,
											class: "text-body-2 text-medium-emphasis"
										}, toDisplayString(__props.category.goods_count) + " товаров ", 1)) : createCommentVNode("", true)];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [createVNode(VImg, {
								src: __props.category.image || "/images/placeholders/category.jpg",
								height: "180",
								cover: ""
							}, null, 8, ["src"]), createVNode(VCardText, { class: "pa-4" }, {
								default: withCtx(() => [createVNode("div", { class: "text-h6 font-weight-bold text-high-emphasis mb-2" }, toDisplayString(__props.category.name), 1), __props.category.goods_count !== void 0 ? (openBlock(), createBlock("div", {
									key: 0,
									class: "text-body-2 text-medium-emphasis"
								}, toDisplayString(__props.category.goods_count) + " товаров ", 1)) : createCommentVNode("", true)]),
								_: 1
							})];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VCard, {
						rounded: "xl",
						elevation: "2",
						class: "category-card h-100 overflow-hidden"
					}, {
						default: withCtx(() => [createVNode(VImg, {
							src: __props.category.image || "/images/placeholders/category.jpg",
							height: "180",
							cover: ""
						}, null, 8, ["src"]), createVNode(VCardText, { class: "pa-4" }, {
							default: withCtx(() => [createVNode("div", { class: "text-h6 font-weight-bold text-high-emphasis mb-2" }, toDisplayString(__props.category.name), 1), __props.category.goods_count !== void 0 ? (openBlock(), createBlock("div", {
								key: 0,
								class: "text-body-2 text-medium-emphasis"
							}, toDisplayString(__props.category.goods_count) + " товаров ", 1)) : createCommentVNode("", true)]),
							_: 1
						})]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Home/CategoryCard.vue");
	return _sfc_setup$6 ? _sfc_setup$6(props, ctx) : void 0;
};
var CategoryCard_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main$6, [["__scopeId", "data-v-44e767a6"]]);
//#endregion
//#region resources/js/Components/Home/HomeCategoriesSection.vue
var _sfc_main$5 = {
	__name: "HomeCategoriesSection",
	__ssrInlineRender: true,
	props: { categories: {
		type: Array,
		default: () => []
	} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<section${ssrRenderAttrs(mergeProps({ class: "py-8 py-md-10 bg-white" }, _attrs))}>`);
			_push(ssrRenderComponent(VContainer, null, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(_sfc_main$7, {
							title: "Категории",
							subtitle: "Основные направления пищевого сырья, ингредиентов и добавок"
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<!--[-->`);
									ssrRenderList(__props.categories, (category) => {
										_push(ssrRenderComponent(VCol, {
											key: category.id,
											cols: "12",
											sm: "6",
											md: "4",
											lg: "3"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(CategoryCard_default, { category }, null, _parent, _scopeId));
												else return [createVNode(CategoryCard_default, { category }, null, 8, ["category"])];
											}),
											_: 2
										}, _parent, _scopeId));
									});
									_push(`<!--]-->`);
								} else return [(openBlock(true), createBlock(Fragment, null, renderList(__props.categories, (category) => {
									return openBlock(), createBlock(VCol, {
										key: category.id,
										cols: "12",
										sm: "6",
										md: "4",
										lg: "3"
									}, {
										default: withCtx(() => [createVNode(CategoryCard_default, { category }, null, 8, ["category"])]),
										_: 2
									}, 1024);
								}), 128))];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(_sfc_main$7, {
						title: "Категории",
						subtitle: "Основные направления пищевого сырья, ингредиентов и добавок"
					}), createVNode(VRow, null, {
						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.categories, (category) => {
							return openBlock(), createBlock(VCol, {
								key: category.id,
								cols: "12",
								sm: "6",
								md: "4",
								lg: "3"
							}, {
								default: withCtx(() => [createVNode(CategoryCard_default, { category }, null, 8, ["category"])]),
								_: 2
							}, 1024);
						}), 128))]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
			_push(`</section>`);
		};
	}
};
var _sfc_setup$5 = _sfc_main$5.setup;
_sfc_main$5.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Home/HomeCategoriesSection.vue");
	return _sfc_setup$5 ? _sfc_setup$5(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/Home/ProductCard.vue
var _sfc_main$4 = {
	__name: "ProductCard",
	__ssrInlineRender: true,
	props: { product: {
		type: Object,
		required: true
	} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(unref(Link), mergeProps({
				href: unref(route)("goods.show", __props.product.slug),
				class: "text-decoration-none"
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VCard, {
						rounded: "xl",
						elevation: "2",
						class: "product-card h-100 d-flex flex-column"
					}, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VImg, {
									src: __props.product.image || "/images/placeholders/product.jpg",
									height: "220",
									cover: ""
								}, null, _parent, _scopeId));
								_push(ssrRenderComponent(VCardText, { class: "pa-4 d-flex flex-column flex-grow-1" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<div class="text-caption text-medium-emphasis mb-2" data-v-867b9c97${_scopeId}>${ssrInterpolate(__props.product.category_name || "Каталог")}</div><div class="text-h6 font-weight-bold text-high-emphasis mb-2" data-v-867b9c97${_scopeId}>${ssrInterpolate(__props.product.name)}</div>`);
											if (__props.product.short_description) _push(`<div class="text-body-2 text-medium-emphasis mb-4" data-v-867b9c97${_scopeId}>${ssrInterpolate(__props.product.short_description)}</div>`);
											else _push(`<!---->`);
											_push(`<div class="d-flex flex-wrap ga-2 mb-4" data-v-867b9c97${_scopeId}>`);
											if (__props.product.country) _push(ssrRenderComponent(VChip, {
												size: "small",
												variant: "outlined"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(__props.product.country)}`);
													else return [createTextVNode(toDisplayString(__props.product.country), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											else _push(`<!---->`);
											if (__props.product.packing) _push(ssrRenderComponent(VChip, {
												size: "small",
												variant: "outlined"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(__props.product.packing)}`);
													else return [createTextVNode(toDisplayString(__props.product.packing), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											else _push(`<!---->`);
											_push(`</div><div class="mt-auto d-flex align-center justify-space-between ga-2" data-v-867b9c97${_scopeId}><div class="text-subtitle-1 font-weight-bold text-primary" data-v-867b9c97${_scopeId}>`);
											if (__props.product.price_from) _push(`<!--[--> от ${ssrInterpolate(__props.product.price_from)} ${ssrInterpolate(__props.product.currency || "₽")}<!--]-->`);
											else _push(`<!--[--> По запросу <!--]-->`);
											_push(`</div>`);
											_push(ssrRenderComponent(VBtn, {
												color: "primary",
												variant: "flat"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(` Подробнее `);
													else return [createTextVNode(" Подробнее ")];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(`</div>`);
										} else return [
											createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, toDisplayString(__props.product.category_name || "Каталог"), 1),
											createVNode("div", { class: "text-h6 font-weight-bold text-high-emphasis mb-2" }, toDisplayString(__props.product.name), 1),
											__props.product.short_description ? (openBlock(), createBlock("div", {
												key: 0,
												class: "text-body-2 text-medium-emphasis mb-4"
											}, toDisplayString(__props.product.short_description), 1)) : createCommentVNode("", true),
											createVNode("div", { class: "d-flex flex-wrap ga-2 mb-4" }, [__props.product.country ? (openBlock(), createBlock(VChip, {
												key: 0,
												size: "small",
												variant: "outlined"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(__props.product.country), 1)]),
												_: 1
											})) : createCommentVNode("", true), __props.product.packing ? (openBlock(), createBlock(VChip, {
												key: 1,
												size: "small",
												variant: "outlined"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(__props.product.packing), 1)]),
												_: 1
											})) : createCommentVNode("", true)]),
											createVNode("div", { class: "mt-auto d-flex align-center justify-space-between ga-2" }, [createVNode("div", { class: "text-subtitle-1 font-weight-bold text-primary" }, [__props.product.price_from ? (openBlock(), createBlock(Fragment, { key: 0 }, [createTextVNode(" от " + toDisplayString(__props.product.price_from) + " " + toDisplayString(__props.product.currency || "₽"), 1)], 64)) : (openBlock(), createBlock(Fragment, { key: 1 }, [createTextVNode(" По запросу ")], 64))]), createVNode(VBtn, {
												color: "primary",
												variant: "flat"
											}, {
												default: withCtx(() => [createTextVNode(" Подробнее ")]),
												_: 1
											})])
										];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [createVNode(VImg, {
								src: __props.product.image || "/images/placeholders/product.jpg",
								height: "220",
								cover: ""
							}, null, 8, ["src"]), createVNode(VCardText, { class: "pa-4 d-flex flex-column flex-grow-1" }, {
								default: withCtx(() => [
									createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, toDisplayString(__props.product.category_name || "Каталог"), 1),
									createVNode("div", { class: "text-h6 font-weight-bold text-high-emphasis mb-2" }, toDisplayString(__props.product.name), 1),
									__props.product.short_description ? (openBlock(), createBlock("div", {
										key: 0,
										class: "text-body-2 text-medium-emphasis mb-4"
									}, toDisplayString(__props.product.short_description), 1)) : createCommentVNode("", true),
									createVNode("div", { class: "d-flex flex-wrap ga-2 mb-4" }, [__props.product.country ? (openBlock(), createBlock(VChip, {
										key: 0,
										size: "small",
										variant: "outlined"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(__props.product.country), 1)]),
										_: 1
									})) : createCommentVNode("", true), __props.product.packing ? (openBlock(), createBlock(VChip, {
										key: 1,
										size: "small",
										variant: "outlined"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(__props.product.packing), 1)]),
										_: 1
									})) : createCommentVNode("", true)]),
									createVNode("div", { class: "mt-auto d-flex align-center justify-space-between ga-2" }, [createVNode("div", { class: "text-subtitle-1 font-weight-bold text-primary" }, [__props.product.price_from ? (openBlock(), createBlock(Fragment, { key: 0 }, [createTextVNode(" от " + toDisplayString(__props.product.price_from) + " " + toDisplayString(__props.product.currency || "₽"), 1)], 64)) : (openBlock(), createBlock(Fragment, { key: 1 }, [createTextVNode(" По запросу ")], 64))]), createVNode(VBtn, {
										color: "primary",
										variant: "flat"
									}, {
										default: withCtx(() => [createTextVNode(" Подробнее ")]),
										_: 1
									})])
								]),
								_: 1
							})];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VCard, {
						rounded: "xl",
						elevation: "2",
						class: "product-card h-100 d-flex flex-column"
					}, {
						default: withCtx(() => [createVNode(VImg, {
							src: __props.product.image || "/images/placeholders/product.jpg",
							height: "220",
							cover: ""
						}, null, 8, ["src"]), createVNode(VCardText, { class: "pa-4 d-flex flex-column flex-grow-1" }, {
							default: withCtx(() => [
								createVNode("div", { class: "text-caption text-medium-emphasis mb-2" }, toDisplayString(__props.product.category_name || "Каталог"), 1),
								createVNode("div", { class: "text-h6 font-weight-bold text-high-emphasis mb-2" }, toDisplayString(__props.product.name), 1),
								__props.product.short_description ? (openBlock(), createBlock("div", {
									key: 0,
									class: "text-body-2 text-medium-emphasis mb-4"
								}, toDisplayString(__props.product.short_description), 1)) : createCommentVNode("", true),
								createVNode("div", { class: "d-flex flex-wrap ga-2 mb-4" }, [__props.product.country ? (openBlock(), createBlock(VChip, {
									key: 0,
									size: "small",
									variant: "outlined"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(__props.product.country), 1)]),
									_: 1
								})) : createCommentVNode("", true), __props.product.packing ? (openBlock(), createBlock(VChip, {
									key: 1,
									size: "small",
									variant: "outlined"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(__props.product.packing), 1)]),
									_: 1
								})) : createCommentVNode("", true)]),
								createVNode("div", { class: "mt-auto d-flex align-center justify-space-between ga-2" }, [createVNode("div", { class: "text-subtitle-1 font-weight-bold text-primary" }, [__props.product.price_from ? (openBlock(), createBlock(Fragment, { key: 0 }, [createTextVNode(" от " + toDisplayString(__props.product.price_from) + " " + toDisplayString(__props.product.currency || "₽"), 1)], 64)) : (openBlock(), createBlock(Fragment, { key: 1 }, [createTextVNode(" По запросу ")], 64))]), createVNode(VBtn, {
									color: "primary",
									variant: "flat"
								}, {
									default: withCtx(() => [createTextVNode(" Подробнее ")]),
									_: 1
								})])
							]),
							_: 1
						})]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$4 = _sfc_main$4.setup;
_sfc_main$4.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Home/ProductCard.vue");
	return _sfc_setup$4 ? _sfc_setup$4(props, ctx) : void 0;
};
var ProductCard_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main$4, [["__scopeId", "data-v-867b9c97"]]);
//#endregion
//#region resources/js/Components/Home/HomeFeaturedProductsSection.vue
var _sfc_main$3 = {
	__name: "HomeFeaturedProductsSection",
	__ssrInlineRender: true,
	props: {
		title: {
			type: String,
			default: "Товары"
		},
		subtitle: {
			type: String,
			default: ""
		},
		products: {
			type: Array,
			default: () => []
		}
	},
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<section${ssrRenderAttrs(mergeProps({ class: "py-8 py-md-10" }, _attrs))}>`);
			_push(ssrRenderComponent(VContainer, null, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(_sfc_main$7, {
							title: __props.title,
							subtitle: __props.subtitle
						}, null, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<!--[-->`);
									ssrRenderList(__props.products, (product) => {
										_push(ssrRenderComponent(VCol, {
											key: product.id,
											cols: "12",
											sm: "6",
											md: "4",
											lg: "3"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(ProductCard_default, { product }, null, _parent, _scopeId));
												else return [createVNode(ProductCard_default, { product }, null, 8, ["product"])];
											}),
											_: 2
										}, _parent, _scopeId));
									});
									_push(`<!--]-->`);
								} else return [(openBlock(true), createBlock(Fragment, null, renderList(__props.products, (product) => {
									return openBlock(), createBlock(VCol, {
										key: product.id,
										cols: "12",
										sm: "6",
										md: "4",
										lg: "3"
									}, {
										default: withCtx(() => [createVNode(ProductCard_default, { product }, null, 8, ["product"])]),
										_: 2
									}, 1024);
								}), 128))];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(_sfc_main$7, {
						title: __props.title,
						subtitle: __props.subtitle
					}, null, 8, ["title", "subtitle"]), createVNode(VRow, null, {
						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.products, (product) => {
							return openBlock(), createBlock(VCol, {
								key: product.id,
								cols: "12",
								sm: "6",
								md: "4",
								lg: "3"
							}, {
								default: withCtx(() => [createVNode(ProductCard_default, { product }, null, 8, ["product"])]),
								_: 2
							}, 1024);
						}), 128))]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
			_push(`</section>`);
		};
	}
};
var _sfc_setup$3 = _sfc_main$3.setup;
_sfc_main$3.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Home/HomeFeaturedProductsSection.vue");
	return _sfc_setup$3 ? _sfc_setup$3(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Components/CocoaButterClassification.vue
var _sfc_main$2 = {
	__name: "CocoaButterClassification",
	__ssrInlineRender: true,
	setup(__props) {
		const activeTab = ref(0);
		const tabs = [
			{
				label: "Способ получения",
				key: "extraction"
			},
			{
				label: "Степень очистки",
				key: "purity"
			},
			{
				label: "Назначение",
				key: "purpose"
			},
			{
				label: "Страна происхождения",
				key: "country"
			}
		];
		const items = {
			extraction: [{
				title: "Холодный отжим",
				description: "Сохраняет аромат, цвет и максимум полезных веществ. Используется в премиальной косметике и еде."
			}, {
				title: "Горячий отжим / экстракция",
				description: "Производится с нагревом или растворителями. Более доступное, но теряет часть натуральных свойств."
			}],
			purity: [{
				title: "Нерафинированное",
				description: "Натуральный шоколадный аромат, желтоватый цвет. Идеально для органической продукции."
			}, {
				title: "Рафинированное",
				description: "Без запаха и цвета, с увеличенным сроком хранения. Отлично подходит для нейтральных рецептур."
			}],
			purpose: [
				{
					title: "Пищевое",
					description: "Используется в производстве шоколада, глазури, конфет, выпечки. Соответствует пищевым стандартам."
				},
				{
					title: "Косметическое",
					description: "Подходит для мыла, бальзамов, кремов и масок. Богато жирными кислотами и антиоксидантами."
				},
				{
					title: "Фармацевтическое",
					description: "Применяется как основа для суппозиториев, мазей и других лекарственных форм."
				}
			],
			country: [
				{
					title: "Венесуэла",
					description: "Мягкий вкус, ароматный профиль, высококачественные сорта (например, Criollo)."
				},
				{
					title: "Гана",
					description: "Глубокий шоколадный вкус и стабильность характеристик. Часто используется в производстве шоколада."
				},
				{
					title: "Эквадор",
					description: "Фруктовые и цветочные ноты во вкусе. Используется в премиальных шоколадных продуктах."
				},
				{
					title: "Индонезия",
					description: "Нейтральный вкус, подходит для массового производства и коммерческой косметики."
				}
			]
		};
		const filteredItems = computed(() => {
			return items[tabs[activeTab.value].key] || [];
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, { class: "mb-6" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<h2 class="text-h5 font-weight-bold" data-v-93a6a8ef${_scopeId}>Классификация какао-масла</h2>`);
											_push(ssrRenderComponent(VTabs, {
												modelValue: activeTab.value,
												"onUpdate:modelValue": ($event) => activeTab.value = $event,
												class: "mt-4"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(`<!--[-->`);
														ssrRenderList(tabs, (tab, index) => {
															_push(ssrRenderComponent(VTab, { key: index }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`${ssrInterpolate(tab.label)}`);
																	else return [createTextVNode(toDisplayString(tab.label), 1)];
																}),
																_: 2
															}, _parent, _scopeId));
														});
														_push(`<!--]-->`);
													} else return [(openBlock(), createBlock(Fragment, null, renderList(tabs, (tab, index) => {
														return createVNode(VTab, { key: index }, {
															default: withCtx(() => [createTextVNode(toDisplayString(tab.label), 1)]),
															_: 2
														}, 1024);
													}), 64))];
												}),
												_: 1
											}, _parent, _scopeId));
										} else return [createVNode("h2", { class: "text-h5 font-weight-bold" }, "Классификация какао-масла"), createVNode(VTabs, {
											modelValue: activeTab.value,
											"onUpdate:modelValue": ($event) => activeTab.value = $event,
											class: "mt-4"
										}, {
											default: withCtx(() => [(openBlock(), createBlock(Fragment, null, renderList(tabs, (tab, index) => {
												return createVNode(VTab, { key: index }, {
													default: withCtx(() => [createTextVNode(toDisplayString(tab.label), 1)]),
													_: 2
												}, 1024);
											}), 64))]),
											_: 1
										}, 8, ["modelValue", "onUpdate:modelValue"])];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCol, { cols: "12" }, {
									default: withCtx(() => [createVNode("h2", { class: "text-h5 font-weight-bold" }, "Классификация какао-масла"), createVNode(VTabs, {
										modelValue: activeTab.value,
										"onUpdate:modelValue": ($event) => activeTab.value = $event,
										class: "mt-4"
									}, {
										default: withCtx(() => [(openBlock(), createBlock(Fragment, null, renderList(tabs, (tab, index) => {
											return createVNode(VTab, { key: index }, {
												default: withCtx(() => [createTextVNode(toDisplayString(tab.label), 1)]),
												_: 2
											}, 1024);
										}), 64))]),
										_: 1
									}, 8, ["modelValue", "onUpdate:modelValue"])]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(`<!--[-->`);
									ssrRenderList(filteredItems.value, (item, index) => {
										_push(ssrRenderComponent(VCol, {
											key: index,
											cols: "12",
											sm: "6",
											md: "4",
											lg: "3"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VCard, {
													class: "rounded-xl h-100 d-flex flex-column justify-space-between",
													elevation: "3"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`${ssrInterpolate(item.title)}`);
																	else return [createTextVNode(toDisplayString(item.title), 1)];
																}),
																_: 2
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCardText, { class: "text-body-2" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`${ssrInterpolate(item.description)}`);
																	else return [createTextVNode(toDisplayString(item.description), 1)];
																}),
																_: 2
															}, _parent, _scopeId));
														} else return [createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
															default: withCtx(() => [createTextVNode(toDisplayString(item.title), 1)]),
															_: 2
														}, 1024), createVNode(VCardText, { class: "text-body-2" }, {
															default: withCtx(() => [createTextVNode(toDisplayString(item.description), 1)]),
															_: 2
														}, 1024)];
													}),
													_: 2
												}, _parent, _scopeId));
												else return [createVNode(VCard, {
													class: "rounded-xl h-100 d-flex flex-column justify-space-between",
													elevation: "3"
												}, {
													default: withCtx(() => [createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
														default: withCtx(() => [createTextVNode(toDisplayString(item.title), 1)]),
														_: 2
													}, 1024), createVNode(VCardText, { class: "text-body-2" }, {
														default: withCtx(() => [createTextVNode(toDisplayString(item.description), 1)]),
														_: 2
													}, 1024)]),
													_: 2
												}, 1024)];
											}),
											_: 2
										}, _parent, _scopeId));
									});
									_push(`<!--]-->`);
								} else return [(openBlock(true), createBlock(Fragment, null, renderList(filteredItems.value, (item, index) => {
									return openBlock(), createBlock(VCol, {
										key: index,
										cols: "12",
										sm: "6",
										md: "4",
										lg: "3"
									}, {
										default: withCtx(() => [createVNode(VCard, {
											class: "rounded-xl h-100 d-flex flex-column justify-space-between",
											elevation: "3"
										}, {
											default: withCtx(() => [createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
												default: withCtx(() => [createTextVNode(toDisplayString(item.title), 1)]),
												_: 2
											}, 1024), createVNode(VCardText, { class: "text-body-2" }, {
												default: withCtx(() => [createTextVNode(toDisplayString(item.description), 1)]),
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
					} else return [createVNode(VRow, { class: "mb-6" }, {
						default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
							default: withCtx(() => [createVNode("h2", { class: "text-h5 font-weight-bold" }, "Классификация какао-масла"), createVNode(VTabs, {
								modelValue: activeTab.value,
								"onUpdate:modelValue": ($event) => activeTab.value = $event,
								class: "mt-4"
							}, {
								default: withCtx(() => [(openBlock(), createBlock(Fragment, null, renderList(tabs, (tab, index) => {
									return createVNode(VTab, { key: index }, {
										default: withCtx(() => [createTextVNode(toDisplayString(tab.label), 1)]),
										_: 2
									}, 1024);
								}), 64))]),
								_: 1
							}, 8, ["modelValue", "onUpdate:modelValue"])]),
							_: 1
						})]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(filteredItems.value, (item, index) => {
							return openBlock(), createBlock(VCol, {
								key: index,
								cols: "12",
								sm: "6",
								md: "4",
								lg: "3"
							}, {
								default: withCtx(() => [createVNode(VCard, {
									class: "rounded-xl h-100 d-flex flex-column justify-space-between",
									elevation: "3"
								}, {
									default: withCtx(() => [createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
										default: withCtx(() => [createTextVNode(toDisplayString(item.title), 1)]),
										_: 2
									}, 1024), createVNode(VCardText, { class: "text-body-2" }, {
										default: withCtx(() => [createTextVNode(toDisplayString(item.description), 1)]),
										_: 2
									}, 1024)]),
									_: 2
								}, 1024)]),
								_: 2
							}, 1024);
						}), 128))]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/CocoaButterClassification.vue");
	return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
var CocoaButterClassification_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main$2, [["__scopeId", "data-v-93a6a8ef"]]);
//#endregion
//#region resources/js/Components/Home/HomeGoodsSearchCard.vue
var _sfc_main$1 = {
	__name: "HomeGoodsSearchCard",
	__ssrInlineRender: true,
	props: {
		goods: {
			type: Array,
			default: () => []
		},
		loading: {
			type: Boolean,
			default: false
		},
		limit: {
			type: Number,
			default: 18
		}
	},
	setup(__props) {
		const props = __props;
		const search = ref("");
		computed(() => {
			const query = search.value.trim().toLowerCase();
			let items = [...props.goods];
			if (query) items = items.filter((good) => {
				const productText = (good.products || []).map((product) => {
					return [
						product.rus,
						product.eng,
						product.name,
						product.title,
						product.category?.name
					].filter(Boolean).join(" ");
				}).join(" ");
				return [
					good.name,
					good.description,
					good.slug,
					productText
				].filter(Boolean).join(" ").toLowerCase().includes(query);
			});
			return items.slice(0, props.limit);
		});
		const hasSearch = computed(() => {
			return search.value.trim().length > 0;
		});
		const newestGoods = computed(() => {
			return [...props.goods].sort((a, b) => {
				const dateA = new Date(a.created_at || 0).getTime();
				return new Date(b.created_at || 0).getTime() - dateA;
			}).slice(0, props.limit);
		});
		const visibleGoods = computed(() => {
			const query = search.value.trim().toLowerCase();
			if (!query) return newestGoods.value;
			return props.goods.filter((good) => {
				const productText = (good.products || []).map((product) => {
					return [
						product.rus,
						product.eng,
						product.name,
						product.title,
						product.category?.name
					].filter(Boolean).join(" ");
				}).join(" ");
				return [
					good.name,
					good.description,
					good.slug,
					productText
				].filter(Boolean).join(" ").toLowerCase().includes(query);
			}).slice(0, props.limit);
		});
		const groupedGoods = computed(() => {
			const groups = /* @__PURE__ */ new Map();
			visibleGoods.value.forEach((good) => {
				((good.products || []).length ? good.products : [null]).forEach((product) => {
					const key = product?.id ? `product-${product.id}` : "no-product";
					if (!groups.has(key)) groups.set(key, {
						id: product?.id || null,
						title: product?.rus || product?.name || product?.title || "Без Product",
						category: product?.category?.name || null,
						goods: []
					});
					groups.get(key).goods.push(good);
				});
			});
			return [...groups.values()];
		});
		function goodImage(good) {
			return good.ava_thumb || good.ava_image || "https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg";
		}
		function latestPrice(good) {
			return good.latest_price || good.latestPrice || good.prices?.[0] || null;
		}
		function formatMoney(value) {
			if (value === null || value === void 0 || value === "") return "—";
			return new Intl.NumberFormat("ru-RU", {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			}).format(Number(value));
		}
		function priceText(good) {
			const price = latestPrice(good);
			if (!price) return "Цена по запросу";
			const currency = price.currency?.code || "RUB";
			return `${formatMoney(price.price)} ${currency}`;
		}
		function shortDescription(good) {
			const text = String(good.description || "").trim();
			if (!text) return "Описание пока не заполнено";
			return text.length > 96 ? `${text.slice(0, 96)}…` : text;
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VCard, mergeProps({
				rounded: "xl",
				elevation: "2",
				class: "goods-search-card h-100"
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VCardText, { class: "pb-3" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VTextField, {
									modelValue: search.value,
									"onUpdate:modelValue": ($event) => search.value = $event,
									variant: "outlined",
									density: "comfortable",
									label: "Поиск по товарам",
									placeholder: "Например: лецитин, глицерин, какао-масло",
									"hide-details": "",
									clearable: "",
									"prepend-inner-icon": "mdi-magnify"
								}, null, _parent, _scopeId));
								else return [createVNode(VTextField, {
									modelValue: search.value,
									"onUpdate:modelValue": ($event) => search.value = $event,
									variant: "outlined",
									density: "comfortable",
									label: "Поиск по товарам",
									placeholder: "Например: лецитин, глицерин, какао-масло",
									"hide-details": "",
									clearable: "",
									"prepend-inner-icon": "mdi-magnify"
								}, null, 8, ["modelValue", "onUpdate:modelValue"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
						_push(ssrRenderComponent(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(`${ssrInterpolate(hasSearch.value ? "Найденные товары" : "Новинки каталога")}`);
								else return [createTextVNode(toDisplayString(hasSearch.value ? "Найденные товары" : "Новинки каталога"), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardSubtitle, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(`${ssrInterpolate(hasSearch.value ? "Поиск работает по названию, описанию и связанным продуктам" : "Последние добавленные опубликованные товары")}`);
								else return [createTextVNode(toDisplayString(hasSearch.value ? "Поиск работает по названию, описанию и связанным продуктам" : "Последние добавленные опубликованные товары"), 1)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardText, { class: "goods-search-card__body" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									if (__props.loading) _push(ssrRenderComponent(VProgressLinear, {
										indeterminate: "",
										class: "mb-3"
									}, null, _parent, _scopeId));
									else _push(`<!---->`);
									if (groupedGoods.value.length) {
										_push(`<!--[-->`);
										ssrRenderList(groupedGoods.value, (group) => {
											_push(`<div class="product-group" data-v-1300bb33${_scopeId}><div class="product-group__header" data-v-1300bb33${_scopeId}><div data-v-1300bb33${_scopeId}><div class="product-group__title" data-v-1300bb33${_scopeId}>${ssrInterpolate(group.title)}</div>`);
											if (group.category) _push(`<div class="product-group__category" data-v-1300bb33${_scopeId}>${ssrInterpolate(group.category)}</div>`);
											else _push(`<!---->`);
											_push(`</div>`);
											_push(ssrRenderComponent(VChip, {
												size: "x-small",
												color: "#800000",
												variant: "tonal"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(group.goods.length)}`);
													else return [createTextVNode(toDisplayString(group.goods.length), 1)];
												}),
												_: 2
											}, _parent, _scopeId));
											_push(`</div><div class="goods-list" data-v-1300bb33${_scopeId}><!--[-->`);
											ssrRenderList(group.goods, (good) => {
												_push(ssrRenderComponent(unref(Link), {
													key: `${group.id || "no-product"}-${good.id}`,
													href: unref(route)("public.goods.show", { good: good.slug }),
													class: "good-row"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VAvatar, {
																size: "52",
																rounded: "lg",
																class: "good-row__image"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VImg, {
																		src: goodImage(good),
																		cover: ""
																	}, null, _parent, _scopeId));
																	else return [createVNode(VImg, {
																		src: goodImage(good),
																		cover: ""
																	}, null, 8, ["src"])];
																}),
																_: 2
															}, _parent, _scopeId));
															_push(`<div class="good-row__content" data-v-1300bb33${_scopeId}><div class="good-row__name" data-v-1300bb33${_scopeId}>${ssrInterpolate(good.name)}</div><div class="good-row__description" data-v-1300bb33${_scopeId}>${ssrInterpolate(shortDescription(good))}</div></div><div class="good-row__price" data-v-1300bb33${_scopeId}>${ssrInterpolate(priceText(good))}</div>`);
														} else return [
															createVNode(VAvatar, {
																size: "52",
																rounded: "lg",
																class: "good-row__image"
															}, {
																default: withCtx(() => [createVNode(VImg, {
																	src: goodImage(good),
																	cover: ""
																}, null, 8, ["src"])]),
																_: 2
															}, 1024),
															createVNode("div", { class: "good-row__content" }, [createVNode("div", { class: "good-row__name" }, toDisplayString(good.name), 1), createVNode("div", { class: "good-row__description" }, toDisplayString(shortDescription(good)), 1)]),
															createVNode("div", { class: "good-row__price" }, toDisplayString(priceText(good)), 1)
														];
													}),
													_: 2
												}, _parent, _scopeId));
											});
											_push(`<!--]--></div></div>`);
										});
										_push(`<!--]-->`);
									} else _push(ssrRenderComponent(VAlert, {
										type: "info",
										variant: "tonal"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`${ssrInterpolate(hasSearch.value ? "Ничего не найдено." : "Новинки каталога пока не найдены.")}`);
											else return [createTextVNode(toDisplayString(hasSearch.value ? "Ничего не найдено." : "Новинки каталога пока не найдены."), 1)];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [__props.loading ? (openBlock(), createBlock(VProgressLinear, {
									key: 0,
									indeterminate: "",
									class: "mb-3"
								})) : createCommentVNode("", true), groupedGoods.value.length ? (openBlock(true), createBlock(Fragment, { key: 1 }, renderList(groupedGoods.value, (group) => {
									return openBlock(), createBlock("div", {
										key: group.id || "no-product",
										class: "product-group"
									}, [createVNode("div", { class: "product-group__header" }, [createVNode("div", null, [createVNode("div", { class: "product-group__title" }, toDisplayString(group.title), 1), group.category ? (openBlock(), createBlock("div", {
										key: 0,
										class: "product-group__category"
									}, toDisplayString(group.category), 1)) : createCommentVNode("", true)]), createVNode(VChip, {
										size: "x-small",
										color: "#800000",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(group.goods.length), 1)]),
										_: 2
									}, 1024)]), createVNode("div", { class: "goods-list" }, [(openBlock(true), createBlock(Fragment, null, renderList(group.goods, (good) => {
										return openBlock(), createBlock(unref(Link), {
											key: `${group.id || "no-product"}-${good.id}`,
											href: unref(route)("public.goods.show", { good: good.slug }),
											class: "good-row"
										}, {
											default: withCtx(() => [
												createVNode(VAvatar, {
													size: "52",
													rounded: "lg",
													class: "good-row__image"
												}, {
													default: withCtx(() => [createVNode(VImg, {
														src: goodImage(good),
														cover: ""
													}, null, 8, ["src"])]),
													_: 2
												}, 1024),
												createVNode("div", { class: "good-row__content" }, [createVNode("div", { class: "good-row__name" }, toDisplayString(good.name), 1), createVNode("div", { class: "good-row__description" }, toDisplayString(shortDescription(good)), 1)]),
												createVNode("div", { class: "good-row__price" }, toDisplayString(priceText(good)), 1)
											]),
											_: 2
										}, 1032, ["href"]);
									}), 128))])]);
								}), 128)) : (openBlock(), createBlock(VAlert, {
									key: 2,
									type: "info",
									variant: "tonal"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(hasSearch.value ? "Ничего не найдено." : "Новинки каталога пока не найдены."), 1)]),
									_: 1
								}))];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VCardActions, { class: "px-4 pb-4" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(unref(Link), {
									href: unref(route)("public.goods.index"),
									class: "w-100"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VBtn, {
											block: "",
											color: "#800000",
											rounded: "xl"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(` Открыть весь каталог `);
												else return [createTextVNode(" Открыть весь каталог ")];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VBtn, {
											block: "",
											color: "#800000",
											rounded: "xl"
										}, {
											default: withCtx(() => [createTextVNode(" Открыть весь каталог ")]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(unref(Link), {
									href: unref(route)("public.goods.index"),
									class: "w-100"
								}, {
									default: withCtx(() => [createVNode(VBtn, {
										block: "",
										color: "#800000",
										rounded: "xl"
									}, {
										default: withCtx(() => [createTextVNode(" Открыть весь каталог ")]),
										_: 1
									})]),
									_: 1
								}, 8, ["href"])];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [
						createVNode(VCardText, { class: "pb-3" }, {
							default: withCtx(() => [createVNode(VTextField, {
								modelValue: search.value,
								"onUpdate:modelValue": ($event) => search.value = $event,
								variant: "outlined",
								density: "comfortable",
								label: "Поиск по товарам",
								placeholder: "Например: лецитин, глицерин, какао-масло",
								"hide-details": "",
								clearable: "",
								"prepend-inner-icon": "mdi-magnify"
							}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
							_: 1
						}),
						createVNode(VDivider),
						createVNode(VCardTitle, { class: "text-subtitle-1 font-weight-bold" }, {
							default: withCtx(() => [createTextVNode(toDisplayString(hasSearch.value ? "Найденные товары" : "Новинки каталога"), 1)]),
							_: 1
						}),
						createVNode(VCardSubtitle, null, {
							default: withCtx(() => [createTextVNode(toDisplayString(hasSearch.value ? "Поиск работает по названию, описанию и связанным продуктам" : "Последние добавленные опубликованные товары"), 1)]),
							_: 1
						}),
						createVNode(VCardText, { class: "goods-search-card__body" }, {
							default: withCtx(() => [__props.loading ? (openBlock(), createBlock(VProgressLinear, {
								key: 0,
								indeterminate: "",
								class: "mb-3"
							})) : createCommentVNode("", true), groupedGoods.value.length ? (openBlock(true), createBlock(Fragment, { key: 1 }, renderList(groupedGoods.value, (group) => {
								return openBlock(), createBlock("div", {
									key: group.id || "no-product",
									class: "product-group"
								}, [createVNode("div", { class: "product-group__header" }, [createVNode("div", null, [createVNode("div", { class: "product-group__title" }, toDisplayString(group.title), 1), group.category ? (openBlock(), createBlock("div", {
									key: 0,
									class: "product-group__category"
								}, toDisplayString(group.category), 1)) : createCommentVNode("", true)]), createVNode(VChip, {
									size: "x-small",
									color: "#800000",
									variant: "tonal"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(group.goods.length), 1)]),
									_: 2
								}, 1024)]), createVNode("div", { class: "goods-list" }, [(openBlock(true), createBlock(Fragment, null, renderList(group.goods, (good) => {
									return openBlock(), createBlock(unref(Link), {
										key: `${group.id || "no-product"}-${good.id}`,
										href: unref(route)("public.goods.show", { good: good.slug }),
										class: "good-row"
									}, {
										default: withCtx(() => [
											createVNode(VAvatar, {
												size: "52",
												rounded: "lg",
												class: "good-row__image"
											}, {
												default: withCtx(() => [createVNode(VImg, {
													src: goodImage(good),
													cover: ""
												}, null, 8, ["src"])]),
												_: 2
											}, 1024),
											createVNode("div", { class: "good-row__content" }, [createVNode("div", { class: "good-row__name" }, toDisplayString(good.name), 1), createVNode("div", { class: "good-row__description" }, toDisplayString(shortDescription(good)), 1)]),
											createVNode("div", { class: "good-row__price" }, toDisplayString(priceText(good)), 1)
										]),
										_: 2
									}, 1032, ["href"]);
								}), 128))])]);
							}), 128)) : (openBlock(), createBlock(VAlert, {
								key: 2,
								type: "info",
								variant: "tonal"
							}, {
								default: withCtx(() => [createTextVNode(toDisplayString(hasSearch.value ? "Ничего не найдено." : "Новинки каталога пока не найдены."), 1)]),
								_: 1
							}))]),
							_: 1
						}),
						createVNode(VCardActions, { class: "px-4 pb-4" }, {
							default: withCtx(() => [createVNode(unref(Link), {
								href: unref(route)("public.goods.index"),
								class: "w-100"
							}, {
								default: withCtx(() => [createVNode(VBtn, {
									block: "",
									color: "#800000",
									rounded: "xl"
								}, {
									default: withCtx(() => [createTextVNode(" Открыть весь каталог ")]),
									_: 1
								})]),
								_: 1
							}, 8, ["href"])]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Home/HomeGoodsSearchCard.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
var HomeGoodsSearchCard_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main$1, [["__scopeId", "data-v-1300bb33"]]);
//#endregion
//#region resources/js/Pages/Welcome.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: LayoutDefault_default }, {
	__name: "Welcome",
	__ssrInlineRender: true,
	props: {
		canLogin: { type: Boolean },
		canRegister: { type: Boolean },
		laravelVersion: {
			type: String,
			required: true
		},
		phpVersion: {
			type: String,
			required: true
		},
		goodOfTheDay: {
			type: Object,
			default: null
		},
		productsCount: {
			type: Number,
			default: 0
		},
		goodsCount: {
			type: Number,
			default: 0
		},
		categories: {
			type: Array,
			default: () => []
		},
		featuredProducts: {
			type: Array,
			default: () => []
		},
		promoProduct: {
			type: Object,
			default: null
		},
		videos: {
			type: Array,
			default: () => []
		},
		stats: {
			type: Object,
			default: () => ({
				productsCount: 0,
				goodsCount: 0
			})
		},
		heroGoods: Array
	},
	setup(__props) {
		const props = __props;
		const goods = ref([]);
		const goodsLoading = ref(false);
		const showGlycerin = ref(false);
		function indexGoods() {
			goodsLoading.value = true;
			axios.get(route("goods.published")).then((response) => {
				goods.value = Array.isArray(response.data) ? response.data : response.data.data || [];
			}).catch((error) => {
				console.error(error);
				goods.value = [];
			}).finally(() => {
				goodsLoading.value = false;
			});
		}
		const goodOfTheDayTitle = computed(() => {
			return props.goodOfTheDay?.good?.name || "Товар дня скоро появится";
		});
		useHead({
			title: "ПИЩЕПРОМ-СЕРВЕР — маркетплейс для пищевой промышленности: пищевое сырьё, ингредиенты и добавки",
			meta: [{
				name: "description",
				content: "Маркетплейс пищевого сырья, ингредиентов и добавок: категории, фото, характеристики, быстрый поиск и удобный каталог."
			}, {
				name: "yandex-verification",
				content: "9eaa399be3fa6a51"
			}]
		});
		onMounted(() => {
			indexGoods();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${ssrRenderAttrs(mergeProps({ class: "welcome-page" }, _attrs))} data-v-517f0294><section class="welcome-section welcome-section--soft welcome-section--top-search" data-v-517f0294>`);
			_push(ssrRenderComponent(VContainer, null, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="section-heading" data-v-517f0294${_scopeId}><div data-v-517f0294${_scopeId}><div class="welcome-eyebrow" data-v-517f0294${_scopeId}> Быстрый поиск </div><h2 class="section-title" data-v-517f0294${_scopeId}> Найти товар по сайту </h2></div></div>`);
						_push(ssrRenderComponent(VRow, {
							dense: "",
							class: "align-stretch"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "4"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(HomeGoodsSearchCard_default, {
												goods: goods.value,
												loading: goodsLoading.value,
												limit: 24
											}, null, _parent, _scopeId));
											else return [createVNode(HomeGoodsSearchCard_default, {
												goods: goods.value,
												loading: goodsLoading.value,
												limit: 24
											}, null, 8, ["goods", "loading"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "8"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VRow, { dense: "" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCol, { cols: "12" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VCard, {
																	rounded: "xl",
																	elevation: "2",
																	overflow: "hidden"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VImg, {
																			src: "https://storage.yandexcloud.net/pps/banners/%D0%9D%D1%83%D1%82-01%20(2025-08-10-g).png",
																			height: "260",
																			cover: ""
																		}, null, _parent, _scopeId));
																		else return [createVNode(VImg, {
																			src: "https://storage.yandexcloud.net/pps/banners/%D0%9D%D1%83%D1%82-01%20(2025-08-10-g).png",
																			height: "260",
																			cover: ""
																		})];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VCard, {
																	rounded: "xl",
																	elevation: "2",
																	overflow: "hidden"
																}, {
																	default: withCtx(() => [createVNode(VImg, {
																		src: "https://storage.yandexcloud.net/pps/banners/%D0%9D%D1%83%D1%82-01%20(2025-08-10-g).png",
																		height: "260",
																		cover: ""
																	})]),
																	_: 1
																})];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCol, {
															cols: "12",
															md: "6"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VCard, {
																	rounded: "xl",
																	elevation: "2",
																	class: "promo-mini-card h-100"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(ssrRenderComponent(VCardTitle, { class: "text-h6 font-weight-bold" }, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(` Акция дня `);
																					else return [createTextVNode(" Акция дня ")];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VCardText, { class: "text-body-1" }, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(`${ssrInterpolate(goodOfTheDayTitle.value)}`);
																					else return [createTextVNode(toDisplayString(goodOfTheDayTitle.value), 1)];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																		} else return [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
																			default: withCtx(() => [createTextVNode(" Акция дня ")]),
																			_: 1
																		}), createVNode(VCardText, { class: "text-body-1" }, {
																			default: withCtx(() => [createTextVNode(toDisplayString(goodOfTheDayTitle.value), 1)]),
																			_: 1
																		})];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VCard, {
																	rounded: "xl",
																	elevation: "2",
																	class: "promo-mini-card h-100"
																}, {
																	default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
																		default: withCtx(() => [createTextVNode(" Акция дня ")]),
																		_: 1
																	}), createVNode(VCardText, { class: "text-body-1" }, {
																		default: withCtx(() => [createTextVNode(toDisplayString(goodOfTheDayTitle.value), 1)]),
																		_: 1
																	})]),
																	_: 1
																})];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCol, {
															cols: "12",
															md: "6"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VCard, {
																	rounded: "xl",
																	elevation: "2",
																	class: "promo-mini-card h-100"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(ssrRenderComponent(VCardTitle, { class: "text-h6 font-weight-bold" }, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) _push(` Основные категории `);
																					else return [createTextVNode(" Основные категории ")];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																			_push(ssrRenderComponent(VCardText, null, {
																				default: withCtx((_, _push, _parent, _scopeId) => {
																					if (_push) {
																						_push(`<div class="category-tags" data-v-517f0294${_scopeId}><!--[-->`);
																						ssrRenderList(__props.categories.slice(0, 6), (category) => {
																							_push(ssrRenderComponent(unref(Link), {
																								key: category.id,
																								href: unref(route)("category.show", category.id),
																								class: "category-tag"
																							}, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(`${ssrInterpolate(category.name)}`);
																									else return [createTextVNode(toDisplayString(category.name), 1)];
																								}),
																								_: 2
																							}, _parent, _scopeId));
																						});
																						_push(`<!--]--></div>`);
																					} else return [createVNode("div", { class: "category-tags" }, [(openBlock(true), createBlock(Fragment, null, renderList(__props.categories.slice(0, 6), (category) => {
																						return openBlock(), createBlock(unref(Link), {
																							key: category.id,
																							href: unref(route)("category.show", category.id),
																							class: "category-tag"
																						}, {
																							default: withCtx(() => [createTextVNode(toDisplayString(category.name), 1)]),
																							_: 2
																						}, 1032, ["href"]);
																					}), 128))])];
																				}),
																				_: 1
																			}, _parent, _scopeId));
																		} else return [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
																			default: withCtx(() => [createTextVNode(" Основные категории ")]),
																			_: 1
																		}), createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode("div", { class: "category-tags" }, [(openBlock(true), createBlock(Fragment, null, renderList(__props.categories.slice(0, 6), (category) => {
																				return openBlock(), createBlock(unref(Link), {
																					key: category.id,
																					href: unref(route)("category.show", category.id),
																					class: "category-tag"
																				}, {
																					default: withCtx(() => [createTextVNode(toDisplayString(category.name), 1)]),
																					_: 2
																				}, 1032, ["href"]);
																			}), 128))])]),
																			_: 1
																		})];
																	}),
																	_: 1
																}, _parent, _scopeId));
																else return [createVNode(VCard, {
																	rounded: "xl",
																	elevation: "2",
																	class: "promo-mini-card h-100"
																}, {
																	default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
																		default: withCtx(() => [createTextVNode(" Основные категории ")]),
																		_: 1
																	}), createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode("div", { class: "category-tags" }, [(openBlock(true), createBlock(Fragment, null, renderList(__props.categories.slice(0, 6), (category) => {
																			return openBlock(), createBlock(unref(Link), {
																				key: category.id,
																				href: unref(route)("category.show", category.id),
																				class: "category-tag"
																			}, {
																				default: withCtx(() => [createTextVNode(toDisplayString(category.name), 1)]),
																				_: 2
																			}, 1032, ["href"]);
																		}), 128))])]),
																		_: 1
																	})]),
																	_: 1
																})];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [
														createVNode(VCol, { cols: "12" }, {
															default: withCtx(() => [createVNode(VCard, {
																rounded: "xl",
																elevation: "2",
																overflow: "hidden"
															}, {
																default: withCtx(() => [createVNode(VImg, {
																	src: "https://storage.yandexcloud.net/pps/banners/%D0%9D%D1%83%D1%82-01%20(2025-08-10-g).png",
																	height: "260",
																	cover: ""
																})]),
																_: 1
															})]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "6"
														}, {
															default: withCtx(() => [createVNode(VCard, {
																rounded: "xl",
																elevation: "2",
																class: "promo-mini-card h-100"
															}, {
																default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
																	default: withCtx(() => [createTextVNode(" Акция дня ")]),
																	_: 1
																}), createVNode(VCardText, { class: "text-body-1" }, {
																	default: withCtx(() => [createTextVNode(toDisplayString(goodOfTheDayTitle.value), 1)]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														}),
														createVNode(VCol, {
															cols: "12",
															md: "6"
														}, {
															default: withCtx(() => [createVNode(VCard, {
																rounded: "xl",
																elevation: "2",
																class: "promo-mini-card h-100"
															}, {
																default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
																	default: withCtx(() => [createTextVNode(" Основные категории ")]),
																	_: 1
																}), createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode("div", { class: "category-tags" }, [(openBlock(true), createBlock(Fragment, null, renderList(__props.categories.slice(0, 6), (category) => {
																		return openBlock(), createBlock(unref(Link), {
																			key: category.id,
																			href: unref(route)("category.show", category.id),
																			class: "category-tag"
																		}, {
																			default: withCtx(() => [createTextVNode(toDisplayString(category.name), 1)]),
																			_: 2
																		}, 1032, ["href"]);
																	}), 128))])]),
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
											else return [createVNode(VRow, { dense: "" }, {
												default: withCtx(() => [
													createVNode(VCol, { cols: "12" }, {
														default: withCtx(() => [createVNode(VCard, {
															rounded: "xl",
															elevation: "2",
															overflow: "hidden"
														}, {
															default: withCtx(() => [createVNode(VImg, {
																src: "https://storage.yandexcloud.net/pps/banners/%D0%9D%D1%83%D1%82-01%20(2025-08-10-g).png",
																height: "260",
																cover: ""
															})]),
															_: 1
														})]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "6"
													}, {
														default: withCtx(() => [createVNode(VCard, {
															rounded: "xl",
															elevation: "2",
															class: "promo-mini-card h-100"
														}, {
															default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
																default: withCtx(() => [createTextVNode(" Акция дня ")]),
																_: 1
															}), createVNode(VCardText, { class: "text-body-1" }, {
																default: withCtx(() => [createTextVNode(toDisplayString(goodOfTheDayTitle.value), 1)]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													}),
													createVNode(VCol, {
														cols: "12",
														md: "6"
													}, {
														default: withCtx(() => [createVNode(VCard, {
															rounded: "xl",
															elevation: "2",
															class: "promo-mini-card h-100"
														}, {
															default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
																default: withCtx(() => [createTextVNode(" Основные категории ")]),
																_: 1
															}), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode("div", { class: "category-tags" }, [(openBlock(true), createBlock(Fragment, null, renderList(__props.categories.slice(0, 6), (category) => {
																	return openBlock(), createBlock(unref(Link), {
																		key: category.id,
																		href: unref(route)("category.show", category.id),
																		class: "category-tag"
																	}, {
																		default: withCtx(() => [createTextVNode(toDisplayString(category.name), 1)]),
																		_: 2
																	}, 1032, ["href"]);
																}), 128))])]),
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
									}, _parent, _scopeId));
								} else return [createVNode(VCol, {
									cols: "12",
									lg: "4"
								}, {
									default: withCtx(() => [createVNode(HomeGoodsSearchCard_default, {
										goods: goods.value,
										loading: goodsLoading.value,
										limit: 24
									}, null, 8, ["goods", "loading"])]),
									_: 1
								}), createVNode(VCol, {
									cols: "12",
									lg: "8"
								}, {
									default: withCtx(() => [createVNode(VRow, { dense: "" }, {
										default: withCtx(() => [
											createVNode(VCol, { cols: "12" }, {
												default: withCtx(() => [createVNode(VCard, {
													rounded: "xl",
													elevation: "2",
													overflow: "hidden"
												}, {
													default: withCtx(() => [createVNode(VImg, {
														src: "https://storage.yandexcloud.net/pps/banners/%D0%9D%D1%83%D1%82-01%20(2025-08-10-g).png",
														height: "260",
														cover: ""
													})]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VCard, {
													rounded: "xl",
													elevation: "2",
													class: "promo-mini-card h-100"
												}, {
													default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
														default: withCtx(() => [createTextVNode(" Акция дня ")]),
														_: 1
													}), createVNode(VCardText, { class: "text-body-1" }, {
														default: withCtx(() => [createTextVNode(toDisplayString(goodOfTheDayTitle.value), 1)]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCol, {
												cols: "12",
												md: "6"
											}, {
												default: withCtx(() => [createVNode(VCard, {
													rounded: "xl",
													elevation: "2",
													class: "promo-mini-card h-100"
												}, {
													default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
														default: withCtx(() => [createTextVNode(" Основные категории ")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [createVNode("div", { class: "category-tags" }, [(openBlock(true), createBlock(Fragment, null, renderList(__props.categories.slice(0, 6), (category) => {
															return openBlock(), createBlock(unref(Link), {
																key: category.id,
																href: unref(route)("category.show", category.id),
																class: "category-tag"
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(category.name), 1)]),
																_: 2
															}, 1032, ["href"]);
														}), 128))])]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											})
										]),
										_: 1
									})]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode("div", { class: "section-heading" }, [createVNode("div", null, [createVNode("div", { class: "welcome-eyebrow" }, " Быстрый поиск "), createVNode("h2", { class: "section-title" }, " Найти товар по сайту ")])]), createVNode(VRow, {
						dense: "",
						class: "align-stretch"
					}, {
						default: withCtx(() => [createVNode(VCol, {
							cols: "12",
							lg: "4"
						}, {
							default: withCtx(() => [createVNode(HomeGoodsSearchCard_default, {
								goods: goods.value,
								loading: goodsLoading.value,
								limit: 24
							}, null, 8, ["goods", "loading"])]),
							_: 1
						}), createVNode(VCol, {
							cols: "12",
							lg: "8"
						}, {
							default: withCtx(() => [createVNode(VRow, { dense: "" }, {
								default: withCtx(() => [
									createVNode(VCol, { cols: "12" }, {
										default: withCtx(() => [createVNode(VCard, {
											rounded: "xl",
											elevation: "2",
											overflow: "hidden"
										}, {
											default: withCtx(() => [createVNode(VImg, {
												src: "https://storage.yandexcloud.net/pps/banners/%D0%9D%D1%83%D1%82-01%20(2025-08-10-g).png",
												height: "260",
												cover: ""
											})]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										md: "6"
									}, {
										default: withCtx(() => [createVNode(VCard, {
											rounded: "xl",
											elevation: "2",
											class: "promo-mini-card h-100"
										}, {
											default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
												default: withCtx(() => [createTextVNode(" Акция дня ")]),
												_: 1
											}), createVNode(VCardText, { class: "text-body-1" }, {
												default: withCtx(() => [createTextVNode(toDisplayString(goodOfTheDayTitle.value), 1)]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCol, {
										cols: "12",
										md: "6"
									}, {
										default: withCtx(() => [createVNode(VCard, {
											rounded: "xl",
											elevation: "2",
											class: "promo-mini-card h-100"
										}, {
											default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
												default: withCtx(() => [createTextVNode(" Основные категории ")]),
												_: 1
											}), createVNode(VCardText, null, {
												default: withCtx(() => [createVNode("div", { class: "category-tags" }, [(openBlock(true), createBlock(Fragment, null, renderList(__props.categories.slice(0, 6), (category) => {
													return openBlock(), createBlock(unref(Link), {
														key: category.id,
														href: unref(route)("category.show", category.id),
														class: "category-tag"
													}, {
														default: withCtx(() => [createTextVNode(toDisplayString(category.name), 1)]),
														_: 2
													}, 1032, ["href"]);
												}), 128))])]),
												_: 1
											})]),
											_: 1
										})]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						})]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
			_push(`</section>`);
			_push(ssrRenderComponent(HomeHeroSection_default, {
				stats: {
					productsCount: __props.productsCount,
					goodsCount: __props.goodsCount
				},
				"hero-goods": __props.heroGoods
			}, null, _parent));
			_push(ssrRenderComponent(_sfc_main$5, { categories: __props.categories }, null, _parent));
			_push(ssrRenderComponent(_sfc_main$3, {
				title: "Популярные товары",
				subtitle: "Ключевые позиции для пищевой промышленности",
				products: __props.featuredProducts
			}, null, _parent));
			_push(`<section class="welcome-section" data-v-517f0294>`);
			_push(ssrRenderComponent(VContainer, null, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VRow, {
						class: "align-stretch",
						dense: ""
					}, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCol, {
									cols: "12",
									lg: "8"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCard, {
											class: "welcome-banner-card",
											rounded: "xl",
											elevation: "2"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VRow, {
													dense: "",
													class: "fill-height"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																md: "7"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(`<div class="welcome-banner-card__content" data-v-517f0294${_scopeId}><div class="welcome-eyebrow" data-v-517f0294${_scopeId}> Маркетплейс для пищевой промышленности </div><h2 class="welcome-title" data-v-517f0294${_scopeId}> Пищевое сырьё, ингредиенты и добавки </h2><p class="welcome-text" data-v-517f0294${_scopeId}> Подбор номенклатуры, каталог товарных позиций, быстрый поиск и удобная навигация по категориям. </p><div class="welcome-actions" data-v-517f0294${_scopeId}>`);
																		_push(ssrRenderComponent(unref(Link), { href: unref(route)("goods.published") }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VBtn, {
																					color: "#800000",
																					rounded: "xl",
																					size: "large"
																				}, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(` Перейти в каталог `);
																						else return [createTextVNode(" Перейти в каталог ")];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																				else return [createVNode(VBtn, {
																					color: "#800000",
																					rounded: "xl",
																					size: "large"
																				}, {
																					default: withCtx(() => [createTextVNode(" Перейти в каталог ")]),
																					_: 1
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(ssrRenderComponent(unref(Link), { href: unref(route)("web.sesame") }, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VBtn, {
																					variant: "outlined",
																					color: "#800000",
																					rounded: "xl",
																					size: "large"
																				}, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(` Кунжут `);
																						else return [createTextVNode(" Кунжут ")];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																				else return [createVNode(VBtn, {
																					variant: "outlined",
																					color: "#800000",
																					rounded: "xl",
																					size: "large"
																				}, {
																					default: withCtx(() => [createTextVNode(" Кунжут ")]),
																					_: 1
																				})];
																			}),
																			_: 1
																		}, _parent, _scopeId));
																		_push(`</div></div>`);
																	} else return [createVNode("div", { class: "welcome-banner-card__content" }, [
																		createVNode("div", { class: "welcome-eyebrow" }, " Маркетплейс для пищевой промышленности "),
																		createVNode("h2", { class: "welcome-title" }, " Пищевое сырьё, ингредиенты и добавки "),
																		createVNode("p", { class: "welcome-text" }, " Подбор номенклатуры, каталог товарных позиций, быстрый поиск и удобная навигация по категориям. "),
																		createVNode("div", { class: "welcome-actions" }, [createVNode(unref(Link), { href: unref(route)("goods.published") }, {
																			default: withCtx(() => [createVNode(VBtn, {
																				color: "#800000",
																				rounded: "xl",
																				size: "large"
																			}, {
																				default: withCtx(() => [createTextVNode(" Перейти в каталог ")]),
																				_: 1
																			})]),
																			_: 1
																		}, 8, ["href"]), createVNode(unref(Link), { href: unref(route)("web.sesame") }, {
																			default: withCtx(() => [createVNode(VBtn, {
																				variant: "outlined",
																				color: "#800000",
																				rounded: "xl",
																				size: "large"
																			}, {
																				default: withCtx(() => [createTextVNode(" Кунжут ")]),
																				_: 1
																			})]),
																			_: 1
																		}, 8, ["href"])])
																	])];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, {
																cols: "12",
																md: "5"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VImg, {
																		src: "https://storage.yandexcloud.net/pps/banners/%D0%91%D0%B0%D0%BD%D0%BD%D0%B5%D1%80%20%D0%BB%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD_%D0%9B%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD%20%D0%BA%D0%BE%D0%BF%D0%B8%D1%8F.png",
																		height: "100%",
																		cover: "",
																		class: "welcome-banner-card__image"
																	}, null, _parent, _scopeId));
																	else return [createVNode(VImg, {
																		src: "https://storage.yandexcloud.net/pps/banners/%D0%91%D0%B0%D0%BD%D0%BD%D0%B5%D1%80%20%D0%BB%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD_%D0%9B%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD%20%D0%BA%D0%BE%D0%BF%D0%B8%D1%8F.png",
																		height: "100%",
																		cover: "",
																		class: "welcome-banner-card__image"
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
														} else return [createVNode(VCol, {
															cols: "12",
															md: "7"
														}, {
															default: withCtx(() => [createVNode("div", { class: "welcome-banner-card__content" }, [
																createVNode("div", { class: "welcome-eyebrow" }, " Маркетплейс для пищевой промышленности "),
																createVNode("h2", { class: "welcome-title" }, " Пищевое сырьё, ингредиенты и добавки "),
																createVNode("p", { class: "welcome-text" }, " Подбор номенклатуры, каталог товарных позиций, быстрый поиск и удобная навигация по категориям. "),
																createVNode("div", { class: "welcome-actions" }, [createVNode(unref(Link), { href: unref(route)("goods.published") }, {
																	default: withCtx(() => [createVNode(VBtn, {
																		color: "#800000",
																		rounded: "xl",
																		size: "large"
																	}, {
																		default: withCtx(() => [createTextVNode(" Перейти в каталог ")]),
																		_: 1
																	})]),
																	_: 1
																}, 8, ["href"]), createVNode(unref(Link), { href: unref(route)("web.sesame") }, {
																	default: withCtx(() => [createVNode(VBtn, {
																		variant: "outlined",
																		color: "#800000",
																		rounded: "xl",
																		size: "large"
																	}, {
																		default: withCtx(() => [createTextVNode(" Кунжут ")]),
																		_: 1
																	})]),
																	_: 1
																}, 8, ["href"])])
															])]),
															_: 1
														}), createVNode(VCol, {
															cols: "12",
															md: "5"
														}, {
															default: withCtx(() => [createVNode(VImg, {
																src: "https://storage.yandexcloud.net/pps/banners/%D0%91%D0%B0%D0%BD%D0%BD%D0%B5%D1%80%20%D0%BB%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD_%D0%9B%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD%20%D0%BA%D0%BE%D0%BF%D0%B8%D1%8F.png",
																height: "100%",
																cover: "",
																class: "welcome-banner-card__image"
															})]),
															_: 1
														})];
													}),
													_: 1
												}, _parent, _scopeId));
												else return [createVNode(VRow, {
													dense: "",
													class: "fill-height"
												}, {
													default: withCtx(() => [createVNode(VCol, {
														cols: "12",
														md: "7"
													}, {
														default: withCtx(() => [createVNode("div", { class: "welcome-banner-card__content" }, [
															createVNode("div", { class: "welcome-eyebrow" }, " Маркетплейс для пищевой промышленности "),
															createVNode("h2", { class: "welcome-title" }, " Пищевое сырьё, ингредиенты и добавки "),
															createVNode("p", { class: "welcome-text" }, " Подбор номенклатуры, каталог товарных позиций, быстрый поиск и удобная навигация по категориям. "),
															createVNode("div", { class: "welcome-actions" }, [createVNode(unref(Link), { href: unref(route)("goods.published") }, {
																default: withCtx(() => [createVNode(VBtn, {
																	color: "#800000",
																	rounded: "xl",
																	size: "large"
																}, {
																	default: withCtx(() => [createTextVNode(" Перейти в каталог ")]),
																	_: 1
																})]),
																_: 1
															}, 8, ["href"]), createVNode(unref(Link), { href: unref(route)("web.sesame") }, {
																default: withCtx(() => [createVNode(VBtn, {
																	variant: "outlined",
																	color: "#800000",
																	rounded: "xl",
																	size: "large"
																}, {
																	default: withCtx(() => [createTextVNode(" Кунжут ")]),
																	_: 1
																})]),
																_: 1
															}, 8, ["href"])])
														])]),
														_: 1
													}), createVNode(VCol, {
														cols: "12",
														md: "5"
													}, {
														default: withCtx(() => [createVNode(VImg, {
															src: "https://storage.yandexcloud.net/pps/banners/%D0%91%D0%B0%D0%BD%D0%BD%D0%B5%D1%80%20%D0%BB%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD_%D0%9B%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD%20%D0%BA%D0%BE%D0%BF%D0%B8%D1%8F.png",
															height: "100%",
															cover: "",
															class: "welcome-banner-card__image"
														})]),
														_: 1
													})]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VCard, {
											class: "welcome-banner-card",
											rounded: "xl",
											elevation: "2"
										}, {
											default: withCtx(() => [createVNode(VRow, {
												dense: "",
												class: "fill-height"
											}, {
												default: withCtx(() => [createVNode(VCol, {
													cols: "12",
													md: "7"
												}, {
													default: withCtx(() => [createVNode("div", { class: "welcome-banner-card__content" }, [
														createVNode("div", { class: "welcome-eyebrow" }, " Маркетплейс для пищевой промышленности "),
														createVNode("h2", { class: "welcome-title" }, " Пищевое сырьё, ингредиенты и добавки "),
														createVNode("p", { class: "welcome-text" }, " Подбор номенклатуры, каталог товарных позиций, быстрый поиск и удобная навигация по категориям. "),
														createVNode("div", { class: "welcome-actions" }, [createVNode(unref(Link), { href: unref(route)("goods.published") }, {
															default: withCtx(() => [createVNode(VBtn, {
																color: "#800000",
																rounded: "xl",
																size: "large"
															}, {
																default: withCtx(() => [createTextVNode(" Перейти в каталог ")]),
																_: 1
															})]),
															_: 1
														}, 8, ["href"]), createVNode(unref(Link), { href: unref(route)("web.sesame") }, {
															default: withCtx(() => [createVNode(VBtn, {
																variant: "outlined",
																color: "#800000",
																rounded: "xl",
																size: "large"
															}, {
																default: withCtx(() => [createTextVNode(" Кунжут ")]),
																_: 1
															})]),
															_: 1
														}, 8, ["href"])])
													])]),
													_: 1
												}), createVNode(VCol, {
													cols: "12",
													md: "5"
												}, {
													default: withCtx(() => [createVNode(VImg, {
														src: "https://storage.yandexcloud.net/pps/banners/%D0%91%D0%B0%D0%BD%D0%BD%D0%B5%D1%80%20%D0%BB%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD_%D0%9B%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD%20%D0%BA%D0%BE%D0%BF%D0%B8%D1%8F.png",
														height: "100%",
														cover: "",
														class: "welcome-banner-card__image"
													})]),
													_: 1
												})]),
												_: 1
											})]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCol, {
									cols: "12",
									lg: "4"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCard, {
											class: "stats-card h-100",
											rounded: "xl",
											elevation: "2"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(ssrRenderComponent(VCardTitle, { class: "text-h6 font-weight-bold" }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` О проекте `);
															else return [createTextVNode(" О проекте ")];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCardText, { class: "stats-card__body" }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(`<div class="stats-card__numbers" data-v-517f0294${_scopeId}><div class="stats-chip" data-v-517f0294${_scopeId}><span class="stats-chip__value" data-v-517f0294${_scopeId}>${ssrInterpolate(__props.productsCount)}</span><span class="stats-chip__label" data-v-517f0294${_scopeId}>товарных наименований</span></div><div class="stats-chip" data-v-517f0294${_scopeId}><span class="stats-chip__value" data-v-517f0294${_scopeId}>${ssrInterpolate(__props.goodsCount)}</span><span class="stats-chip__label" data-v-517f0294${_scopeId}>товаров</span></div></div><p class="welcome-text mb-0" data-v-517f0294${_scopeId}> ПИЩЕПРОМ-СЕРВЕР — работа по поддержанию широкого ассортимента пищевых добавок, ингредиентов и сырья. </p>`);
															else return [createVNode("div", { class: "stats-card__numbers" }, [createVNode("div", { class: "stats-chip" }, [createVNode("span", { class: "stats-chip__value" }, toDisplayString(__props.productsCount), 1), createVNode("span", { class: "stats-chip__label" }, "товарных наименований")]), createVNode("div", { class: "stats-chip" }, [createVNode("span", { class: "stats-chip__value" }, toDisplayString(__props.goodsCount), 1), createVNode("span", { class: "stats-chip__label" }, "товаров")])]), createVNode("p", { class: "welcome-text mb-0" }, " ПИЩЕПРОМ-СЕРВЕР — работа по поддержанию широкого ассортимента пищевых добавок, ингредиентов и сырья. ")];
														}),
														_: 1
													}, _parent, _scopeId));
												} else return [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
													default: withCtx(() => [createTextVNode(" О проекте ")]),
													_: 1
												}), createVNode(VCardText, { class: "stats-card__body" }, {
													default: withCtx(() => [createVNode("div", { class: "stats-card__numbers" }, [createVNode("div", { class: "stats-chip" }, [createVNode("span", { class: "stats-chip__value" }, toDisplayString(__props.productsCount), 1), createVNode("span", { class: "stats-chip__label" }, "товарных наименований")]), createVNode("div", { class: "stats-chip" }, [createVNode("span", { class: "stats-chip__value" }, toDisplayString(__props.goodsCount), 1), createVNode("span", { class: "stats-chip__label" }, "товаров")])]), createVNode("p", { class: "welcome-text mb-0" }, " ПИЩЕПРОМ-СЕРВЕР — работа по поддержанию широкого ассортимента пищевых добавок, ингредиентов и сырья. ")]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VCard, {
											class: "stats-card h-100",
											rounded: "xl",
											elevation: "2"
										}, {
											default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
												default: withCtx(() => [createTextVNode(" О проекте ")]),
												_: 1
											}), createVNode(VCardText, { class: "stats-card__body" }, {
												default: withCtx(() => [createVNode("div", { class: "stats-card__numbers" }, [createVNode("div", { class: "stats-chip" }, [createVNode("span", { class: "stats-chip__value" }, toDisplayString(__props.productsCount), 1), createVNode("span", { class: "stats-chip__label" }, "товарных наименований")]), createVNode("div", { class: "stats-chip" }, [createVNode("span", { class: "stats-chip__value" }, toDisplayString(__props.goodsCount), 1), createVNode("span", { class: "stats-chip__label" }, "товаров")])]), createVNode("p", { class: "welcome-text mb-0" }, " ПИЩЕПРОМ-СЕРВЕР — работа по поддержанию широкого ассортимента пищевых добавок, ингредиентов и сырья. ")]),
												_: 1
											})]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [createVNode(VCol, {
								cols: "12",
								lg: "8"
							}, {
								default: withCtx(() => [createVNode(VCard, {
									class: "welcome-banner-card",
									rounded: "xl",
									elevation: "2"
								}, {
									default: withCtx(() => [createVNode(VRow, {
										dense: "",
										class: "fill-height"
									}, {
										default: withCtx(() => [createVNode(VCol, {
											cols: "12",
											md: "7"
										}, {
											default: withCtx(() => [createVNode("div", { class: "welcome-banner-card__content" }, [
												createVNode("div", { class: "welcome-eyebrow" }, " Маркетплейс для пищевой промышленности "),
												createVNode("h2", { class: "welcome-title" }, " Пищевое сырьё, ингредиенты и добавки "),
												createVNode("p", { class: "welcome-text" }, " Подбор номенклатуры, каталог товарных позиций, быстрый поиск и удобная навигация по категориям. "),
												createVNode("div", { class: "welcome-actions" }, [createVNode(unref(Link), { href: unref(route)("goods.published") }, {
													default: withCtx(() => [createVNode(VBtn, {
														color: "#800000",
														rounded: "xl",
														size: "large"
													}, {
														default: withCtx(() => [createTextVNode(" Перейти в каталог ")]),
														_: 1
													})]),
													_: 1
												}, 8, ["href"]), createVNode(unref(Link), { href: unref(route)("web.sesame") }, {
													default: withCtx(() => [createVNode(VBtn, {
														variant: "outlined",
														color: "#800000",
														rounded: "xl",
														size: "large"
													}, {
														default: withCtx(() => [createTextVNode(" Кунжут ")]),
														_: 1
													})]),
													_: 1
												}, 8, ["href"])])
											])]),
											_: 1
										}), createVNode(VCol, {
											cols: "12",
											md: "5"
										}, {
											default: withCtx(() => [createVNode(VImg, {
												src: "https://storage.yandexcloud.net/pps/banners/%D0%91%D0%B0%D0%BD%D0%BD%D0%B5%D1%80%20%D0%BB%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD_%D0%9B%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD%20%D0%BA%D0%BE%D0%BF%D0%B8%D1%8F.png",
												height: "100%",
												cover: "",
												class: "welcome-banner-card__image"
											})]),
											_: 1
										})]),
										_: 1
									})]),
									_: 1
								})]),
								_: 1
							}), createVNode(VCol, {
								cols: "12",
								lg: "4"
							}, {
								default: withCtx(() => [createVNode(VCard, {
									class: "stats-card h-100",
									rounded: "xl",
									elevation: "2"
								}, {
									default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
										default: withCtx(() => [createTextVNode(" О проекте ")]),
										_: 1
									}), createVNode(VCardText, { class: "stats-card__body" }, {
										default: withCtx(() => [createVNode("div", { class: "stats-card__numbers" }, [createVNode("div", { class: "stats-chip" }, [createVNode("span", { class: "stats-chip__value" }, toDisplayString(__props.productsCount), 1), createVNode("span", { class: "stats-chip__label" }, "товарных наименований")]), createVNode("div", { class: "stats-chip" }, [createVNode("span", { class: "stats-chip__value" }, toDisplayString(__props.goodsCount), 1), createVNode("span", { class: "stats-chip__label" }, "товаров")])]), createVNode("p", { class: "welcome-text mb-0" }, " ПИЩЕПРОМ-СЕРВЕР — работа по поддержанию широкого ассортимента пищевых добавок, ингредиентов и сырья. ")]),
										_: 1
									})]),
									_: 1
								})]),
								_: 1
							})];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VRow, {
						class: "align-stretch",
						dense: ""
					}, {
						default: withCtx(() => [createVNode(VCol, {
							cols: "12",
							lg: "8"
						}, {
							default: withCtx(() => [createVNode(VCard, {
								class: "welcome-banner-card",
								rounded: "xl",
								elevation: "2"
							}, {
								default: withCtx(() => [createVNode(VRow, {
									dense: "",
									class: "fill-height"
								}, {
									default: withCtx(() => [createVNode(VCol, {
										cols: "12",
										md: "7"
									}, {
										default: withCtx(() => [createVNode("div", { class: "welcome-banner-card__content" }, [
											createVNode("div", { class: "welcome-eyebrow" }, " Маркетплейс для пищевой промышленности "),
											createVNode("h2", { class: "welcome-title" }, " Пищевое сырьё, ингредиенты и добавки "),
											createVNode("p", { class: "welcome-text" }, " Подбор номенклатуры, каталог товарных позиций, быстрый поиск и удобная навигация по категориям. "),
											createVNode("div", { class: "welcome-actions" }, [createVNode(unref(Link), { href: unref(route)("goods.published") }, {
												default: withCtx(() => [createVNode(VBtn, {
													color: "#800000",
													rounded: "xl",
													size: "large"
												}, {
													default: withCtx(() => [createTextVNode(" Перейти в каталог ")]),
													_: 1
												})]),
												_: 1
											}, 8, ["href"]), createVNode(unref(Link), { href: unref(route)("web.sesame") }, {
												default: withCtx(() => [createVNode(VBtn, {
													variant: "outlined",
													color: "#800000",
													rounded: "xl",
													size: "large"
												}, {
													default: withCtx(() => [createTextVNode(" Кунжут ")]),
													_: 1
												})]),
												_: 1
											}, 8, ["href"])])
										])]),
										_: 1
									}), createVNode(VCol, {
										cols: "12",
										md: "5"
									}, {
										default: withCtx(() => [createVNode(VImg, {
											src: "https://storage.yandexcloud.net/pps/banners/%D0%91%D0%B0%D0%BD%D0%BD%D0%B5%D1%80%20%D0%BB%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD_%D0%9B%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD%20%D0%BA%D0%BE%D0%BF%D0%B8%D1%8F.png",
											height: "100%",
											cover: "",
											class: "welcome-banner-card__image"
										})]),
										_: 1
									})]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						}), createVNode(VCol, {
							cols: "12",
							lg: "4"
						}, {
							default: withCtx(() => [createVNode(VCard, {
								class: "stats-card h-100",
								rounded: "xl",
								elevation: "2"
							}, {
								default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
									default: withCtx(() => [createTextVNode(" О проекте ")]),
									_: 1
								}), createVNode(VCardText, { class: "stats-card__body" }, {
									default: withCtx(() => [createVNode("div", { class: "stats-card__numbers" }, [createVNode("div", { class: "stats-chip" }, [createVNode("span", { class: "stats-chip__value" }, toDisplayString(__props.productsCount), 1), createVNode("span", { class: "stats-chip__label" }, "товарных наименований")]), createVNode("div", { class: "stats-chip" }, [createVNode("span", { class: "stats-chip__value" }, toDisplayString(__props.goodsCount), 1), createVNode("span", { class: "stats-chip__label" }, "товаров")])]), createVNode("p", { class: "welcome-text mb-0" }, " ПИЩЕПРОМ-СЕРВЕР — работа по поддержанию широкого ассортимента пищевых добавок, ингредиентов и сырья. ")]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						})]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
			_push(`</section><section class="welcome-section" data-v-517f0294>`);
			_push(ssrRenderComponent(VContainer, null, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="section-heading" data-v-517f0294${_scopeId}><div data-v-517f0294${_scopeId}><div class="welcome-eyebrow" data-v-517f0294${_scopeId}>Тематические материалы</div><h2 class="section-title" data-v-517f0294${_scopeId}>Сырьё и ингредиенты</h2></div></div>`);
						_push(ssrRenderComponent(VRow, {
							dense: "",
							class: "align-stretch"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "6"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VCard, {
												rounded: "xl",
												elevation: "2",
												class: "h-100"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCardTitle, { class: "text-h6 font-weight-bold" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Классификация какао-масла `);
																else return [createTextVNode(" Классификация какао-масла ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCardText, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(CocoaButterClassification_default, null, null, _parent, _scopeId));
																else return [createVNode(CocoaButterClassification_default)];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
														default: withCtx(() => [createTextVNode(" Классификация какао-масла ")]),
														_: 1
													}), createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(CocoaButterClassification_default)]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VCard, {
												rounded: "xl",
												elevation: "2",
												class: "h-100"
											}, {
												default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
													default: withCtx(() => [createTextVNode(" Классификация какао-масла ")]),
													_: 1
												}), createVNode(VCardText, null, {
													default: withCtx(() => [createVNode(CocoaButterClassification_default)]),
													_: 1
												})]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										lg: "6"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VCard, {
												rounded: "xl",
												elevation: "2",
												class: "h-100"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VImg, {
															height: "240",
															src: "https://storage.yandexcloud.net/cold-reserve/Goods/Cocoa/butter/buttercocoa-borrowed-01-330.jpg",
															cover: ""
														}, null, _parent, _scopeId));
														_push(ssrRenderComponent(VCardTitle, { class: "text-h6 font-weight-bold" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(` Какао-масло `);
																else return [createTextVNode(" Какао-масло ")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCardText, { class: "text-body-2" }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`<p data-v-517f0294${_scopeId}><strong data-v-517f0294${_scopeId}>Состав:</strong> диглицериды и триглицериды, смешанные с жирными кислотами. </p><p data-v-517f0294${_scopeId}><strong data-v-517f0294${_scopeId}>Температура плавления:</strong> 32–35 °C. При 40 °C масло прозрачное. </p><p data-v-517f0294${_scopeId}><strong data-v-517f0294${_scopeId}>Применение:</strong> шоколад, кондитерские изделия, косметика, фармацевтика. </p><p class="mb-0" data-v-517f0294${_scopeId}> Существует в нерафинированном и рафинированном виде, отличающихся степенью очистки, запахом и сроком хранения. </p>`);
																else return [
																	createVNode("p", null, [createVNode("strong", null, "Состав:"), createTextVNode(" диглицериды и триглицериды, смешанные с жирными кислотами. ")]),
																	createVNode("p", null, [createVNode("strong", null, "Температура плавления:"), createTextVNode(" 32–35 °C. При 40 °C масло прозрачное. ")]),
																	createVNode("p", null, [createVNode("strong", null, "Применение:"), createTextVNode(" шоколад, кондитерские изделия, косметика, фармацевтика. ")]),
																	createVNode("p", { class: "mb-0" }, " Существует в нерафинированном и рафинированном виде, отличающихся степенью очистки, запахом и сроком хранения. ")
																];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [
														createVNode(VImg, {
															height: "240",
															src: "https://storage.yandexcloud.net/cold-reserve/Goods/Cocoa/butter/buttercocoa-borrowed-01-330.jpg",
															cover: ""
														}),
														createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
															default: withCtx(() => [createTextVNode(" Какао-масло ")]),
															_: 1
														}),
														createVNode(VCardText, { class: "text-body-2" }, {
															default: withCtx(() => [
																createVNode("p", null, [createVNode("strong", null, "Состав:"), createTextVNode(" диглицериды и триглицериды, смешанные с жирными кислотами. ")]),
																createVNode("p", null, [createVNode("strong", null, "Температура плавления:"), createTextVNode(" 32–35 °C. При 40 °C масло прозрачное. ")]),
																createVNode("p", null, [createVNode("strong", null, "Применение:"), createTextVNode(" шоколад, кондитерские изделия, косметика, фармацевтика. ")]),
																createVNode("p", { class: "mb-0" }, " Существует в нерафинированном и рафинированном виде, отличающихся степенью очистки, запахом и сроком хранения. ")
															]),
															_: 1
														})
													];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VCard, {
												rounded: "xl",
												elevation: "2",
												class: "h-100"
											}, {
												default: withCtx(() => [
													createVNode(VImg, {
														height: "240",
														src: "https://storage.yandexcloud.net/cold-reserve/Goods/Cocoa/butter/buttercocoa-borrowed-01-330.jpg",
														cover: ""
													}),
													createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
														default: withCtx(() => [createTextVNode(" Какао-масло ")]),
														_: 1
													}),
													createVNode(VCardText, { class: "text-body-2" }, {
														default: withCtx(() => [
															createVNode("p", null, [createVNode("strong", null, "Состав:"), createTextVNode(" диглицериды и триглицериды, смешанные с жирными кислотами. ")]),
															createVNode("p", null, [createVNode("strong", null, "Температура плавления:"), createTextVNode(" 32–35 °C. При 40 °C масло прозрачное. ")]),
															createVNode("p", null, [createVNode("strong", null, "Применение:"), createTextVNode(" шоколад, кондитерские изделия, косметика, фармацевтика. ")]),
															createVNode("p", { class: "mb-0" }, " Существует в нерафинированном и рафинированном виде, отличающихся степенью очистки, запахом и сроком хранения. ")
														]),
														_: 1
													})
												]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, {
									cols: "12",
									lg: "6"
								}, {
									default: withCtx(() => [createVNode(VCard, {
										rounded: "xl",
										elevation: "2",
										class: "h-100"
									}, {
										default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
											default: withCtx(() => [createTextVNode(" Классификация какао-масла ")]),
											_: 1
										}), createVNode(VCardText, null, {
											default: withCtx(() => [createVNode(CocoaButterClassification_default)]),
											_: 1
										})]),
										_: 1
									})]),
									_: 1
								}), createVNode(VCol, {
									cols: "12",
									lg: "6"
								}, {
									default: withCtx(() => [createVNode(VCard, {
										rounded: "xl",
										elevation: "2",
										class: "h-100"
									}, {
										default: withCtx(() => [
											createVNode(VImg, {
												height: "240",
												src: "https://storage.yandexcloud.net/cold-reserve/Goods/Cocoa/butter/buttercocoa-borrowed-01-330.jpg",
												cover: ""
											}),
											createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
												default: withCtx(() => [createTextVNode(" Какао-масло ")]),
												_: 1
											}),
											createVNode(VCardText, { class: "text-body-2" }, {
												default: withCtx(() => [
													createVNode("p", null, [createVNode("strong", null, "Состав:"), createTextVNode(" диглицериды и триглицериды, смешанные с жирными кислотами. ")]),
													createVNode("p", null, [createVNode("strong", null, "Температура плавления:"), createTextVNode(" 32–35 °C. При 40 °C масло прозрачное. ")]),
													createVNode("p", null, [createVNode("strong", null, "Применение:"), createTextVNode(" шоколад, кондитерские изделия, косметика, фармацевтика. ")]),
													createVNode("p", { class: "mb-0" }, " Существует в нерафинированном и рафинированном виде, отличающихся степенью очистки, запахом и сроком хранения. ")
												]),
												_: 1
											})
										]),
										_: 1
									})]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode("div", { class: "section-heading" }, [createVNode("div", null, [createVNode("div", { class: "welcome-eyebrow" }, "Тематические материалы"), createVNode("h2", { class: "section-title" }, "Сырьё и ингредиенты")])]), createVNode(VRow, {
						dense: "",
						class: "align-stretch"
					}, {
						default: withCtx(() => [createVNode(VCol, {
							cols: "12",
							lg: "6"
						}, {
							default: withCtx(() => [createVNode(VCard, {
								rounded: "xl",
								elevation: "2",
								class: "h-100"
							}, {
								default: withCtx(() => [createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
									default: withCtx(() => [createTextVNode(" Классификация какао-масла ")]),
									_: 1
								}), createVNode(VCardText, null, {
									default: withCtx(() => [createVNode(CocoaButterClassification_default)]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						}), createVNode(VCol, {
							cols: "12",
							lg: "6"
						}, {
							default: withCtx(() => [createVNode(VCard, {
								rounded: "xl",
								elevation: "2",
								class: "h-100"
							}, {
								default: withCtx(() => [
									createVNode(VImg, {
										height: "240",
										src: "https://storage.yandexcloud.net/cold-reserve/Goods/Cocoa/butter/buttercocoa-borrowed-01-330.jpg",
										cover: ""
									}),
									createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
										default: withCtx(() => [createTextVNode(" Какао-масло ")]),
										_: 1
									}),
									createVNode(VCardText, { class: "text-body-2" }, {
										default: withCtx(() => [
											createVNode("p", null, [createVNode("strong", null, "Состав:"), createTextVNode(" диглицериды и триглицериды, смешанные с жирными кислотами. ")]),
											createVNode("p", null, [createVNode("strong", null, "Температура плавления:"), createTextVNode(" 32–35 °C. При 40 °C масло прозрачное. ")]),
											createVNode("p", null, [createVNode("strong", null, "Применение:"), createTextVNode(" шоколад, кондитерские изделия, косметика, фармацевтика. ")]),
											createVNode("p", { class: "mb-0" }, " Существует в нерафинированном и рафинированном виде, отличающихся степенью очистки, запахом и сроком хранения. ")
										]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						})]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
			_push(`</section><section class="welcome-section welcome-section--soft" data-v-517f0294>`);
			_push(ssrRenderComponent(VContainer, null, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VRow, {
						dense: "",
						class: "align-stretch"
					}, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCol, {
									cols: "12",
									md: "6",
									lg: "4"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCard, {
											rounded: "xl",
											elevation: "2",
											class: "h-100"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(ssrRenderComponent(VImg, {
														height: "220",
														src: "https://storage.yandexcloud.net/cold-reserve/Goods/Lecithin/%D0%A1%D0%BD%D0%B8%D0%BC%D0%BE%D0%BA%20%D1%8D%D0%BA%D1%80%D0%B0%D0%BD%D0%B0%202024-08-21%20%D0%B2%2019.51.31.png",
														cover: ""
													}, null, _parent, _scopeId));
													_push(ssrRenderComponent(VCardTitle, { class: "text-h6 font-weight-bold" }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` Лецитин `);
															else return [createTextVNode(" Лецитин ")];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCardSubtitle, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` Подсолнечный `);
															else return [createTextVNode(" Подсолнечный ")];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCardText, { class: "text-body-2" }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` Концентрат фосфатидный подсолнечный. Используется в шоколадном производстве, помогает стабилизировать массу и улучшать текстуру продукта. `);
															else return [createTextVNode(" Концентрат фосфатидный подсолнечный. Используется в шоколадном производстве, помогает стабилизировать массу и улучшать текстуру продукта. ")];
														}),
														_: 1
													}, _parent, _scopeId));
												} else return [
													createVNode(VImg, {
														height: "220",
														src: "https://storage.yandexcloud.net/cold-reserve/Goods/Lecithin/%D0%A1%D0%BD%D0%B8%D0%BC%D0%BE%D0%BA%20%D1%8D%D0%BA%D1%80%D0%B0%D0%BD%D0%B0%202024-08-21%20%D0%B2%2019.51.31.png",
														cover: ""
													}),
													createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
														default: withCtx(() => [createTextVNode(" Лецитин ")]),
														_: 1
													}),
													createVNode(VCardSubtitle, null, {
														default: withCtx(() => [createTextVNode(" Подсолнечный ")]),
														_: 1
													}),
													createVNode(VCardText, { class: "text-body-2" }, {
														default: withCtx(() => [createTextVNode(" Концентрат фосфатидный подсолнечный. Используется в шоколадном производстве, помогает стабилизировать массу и улучшать текстуру продукта. ")]),
														_: 1
													})
												];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VCard, {
											rounded: "xl",
											elevation: "2",
											class: "h-100"
										}, {
											default: withCtx(() => [
												createVNode(VImg, {
													height: "220",
													src: "https://storage.yandexcloud.net/cold-reserve/Goods/Lecithin/%D0%A1%D0%BD%D0%B8%D0%BC%D0%BE%D0%BA%20%D1%8D%D0%BA%D1%80%D0%B0%D0%BD%D0%B0%202024-08-21%20%D0%B2%2019.51.31.png",
													cover: ""
												}),
												createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
													default: withCtx(() => [createTextVNode(" Лецитин ")]),
													_: 1
												}),
												createVNode(VCardSubtitle, null, {
													default: withCtx(() => [createTextVNode(" Подсолнечный ")]),
													_: 1
												}),
												createVNode(VCardText, { class: "text-body-2" }, {
													default: withCtx(() => [createTextVNode(" Концентрат фосфатидный подсолнечный. Используется в шоколадном производстве, помогает стабилизировать массу и улучшать текстуру продукта. ")]),
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
									md: "6",
									lg: "4"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCard, {
											rounded: "xl",
											elevation: "2",
											class: "h-100"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(ssrRenderComponent(VImg, {
														height: "220",
														src: "https://storage.yandexcloud.net/cold-reserve/Goods/Glycerol/glycerol-25.jpg",
														cover: ""
													}, null, _parent, _scopeId));
													_push(ssrRenderComponent(VCardTitle, { class: "text-h6 font-weight-bold" }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` Глицерин `);
															else return [createTextVNode(" Глицерин ")];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCardSubtitle, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` ПК-94, Д-98 `);
															else return [createTextVNode(" ПК-94, Д-98 ")];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCardActions, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(VBtn, {
																color: "#800000",
																variant: "text",
																onClick: ($event) => showGlycerin.value = !showGlycerin.value
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`${ssrInterpolate(showGlycerin.value ? "Скрыть" : "Подробнее")}`);
																	else return [createTextVNode(toDisplayString(showGlycerin.value ? "Скрыть" : "Подробнее"), 1)];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(VBtn, {
																color: "#800000",
																variant: "text",
																onClick: ($event) => showGlycerin.value = !showGlycerin.value
															}, {
																default: withCtx(() => [createTextVNode(toDisplayString(showGlycerin.value ? "Скрыть" : "Подробнее"), 1)]),
																_: 1
															}, 8, ["onClick"])];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VExpandTransition, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(`<div style="${ssrRenderStyle(showGlycerin.value ? null : { display: "none" })}" data-v-517f0294${_scopeId}>`);
																_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
																_push(ssrRenderComponent(VCardText, { class: "text-body-2" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(` Пищевая добавка Е422. Канистры 10 кг и 25 кг. Используется для фармакопейных целей, а также в пищевой, косметической и других отраслях промышленности. Срок хранения до вскрытия упаковки — 5 лет. `);
																		else return [createTextVNode(" Пищевая добавка Е422. Канистры 10 кг и 25 кг. Используется для фармакопейных целей, а также в пищевой, косметической и других отраслях промышленности. Срок хранения до вскрытия упаковки — 5 лет. ")];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(`</div>`);
															} else return [withDirectives(createVNode("div", null, [createVNode(VDivider), createVNode(VCardText, { class: "text-body-2" }, {
																default: withCtx(() => [createTextVNode(" Пищевая добавка Е422. Канистры 10 кг и 25 кг. Используется для фармакопейных целей, а также в пищевой, косметической и других отраслях промышленности. Срок хранения до вскрытия упаковки — 5 лет. ")]),
																_: 1
															})], 512), [[vShow, showGlycerin.value]])];
														}),
														_: 1
													}, _parent, _scopeId));
												} else return [
													createVNode(VImg, {
														height: "220",
														src: "https://storage.yandexcloud.net/cold-reserve/Goods/Glycerol/glycerol-25.jpg",
														cover: ""
													}),
													createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
														default: withCtx(() => [createTextVNode(" Глицерин ")]),
														_: 1
													}),
													createVNode(VCardSubtitle, null, {
														default: withCtx(() => [createTextVNode(" ПК-94, Д-98 ")]),
														_: 1
													}),
													createVNode(VCardActions, null, {
														default: withCtx(() => [createVNode(VBtn, {
															color: "#800000",
															variant: "text",
															onClick: ($event) => showGlycerin.value = !showGlycerin.value
														}, {
															default: withCtx(() => [createTextVNode(toDisplayString(showGlycerin.value ? "Скрыть" : "Подробнее"), 1)]),
															_: 1
														}, 8, ["onClick"])]),
														_: 1
													}),
													createVNode(VExpandTransition, null, {
														default: withCtx(() => [withDirectives(createVNode("div", null, [createVNode(VDivider), createVNode(VCardText, { class: "text-body-2" }, {
															default: withCtx(() => [createTextVNode(" Пищевая добавка Е422. Канистры 10 кг и 25 кг. Используется для фармакопейных целей, а также в пищевой, косметической и других отраслях промышленности. Срок хранения до вскрытия упаковки — 5 лет. ")]),
															_: 1
														})], 512), [[vShow, showGlycerin.value]])]),
														_: 1
													})
												];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VCard, {
											rounded: "xl",
											elevation: "2",
											class: "h-100"
										}, {
											default: withCtx(() => [
												createVNode(VImg, {
													height: "220",
													src: "https://storage.yandexcloud.net/cold-reserve/Goods/Glycerol/glycerol-25.jpg",
													cover: ""
												}),
												createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
													default: withCtx(() => [createTextVNode(" Глицерин ")]),
													_: 1
												}),
												createVNode(VCardSubtitle, null, {
													default: withCtx(() => [createTextVNode(" ПК-94, Д-98 ")]),
													_: 1
												}),
												createVNode(VCardActions, null, {
													default: withCtx(() => [createVNode(VBtn, {
														color: "#800000",
														variant: "text",
														onClick: ($event) => showGlycerin.value = !showGlycerin.value
													}, {
														default: withCtx(() => [createTextVNode(toDisplayString(showGlycerin.value ? "Скрыть" : "Подробнее"), 1)]),
														_: 1
													}, 8, ["onClick"])]),
													_: 1
												}),
												createVNode(VExpandTransition, null, {
													default: withCtx(() => [withDirectives(createVNode("div", null, [createVNode(VDivider), createVNode(VCardText, { class: "text-body-2" }, {
														default: withCtx(() => [createTextVNode(" Пищевая добавка Е422. Канистры 10 кг и 25 кг. Используется для фармакопейных целей, а также в пищевой, косметической и других отраслях промышленности. Срок хранения до вскрытия упаковки — 5 лет. ")]),
														_: 1
													})], 512), [[vShow, showGlycerin.value]])]),
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
									lg: "4"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCard, {
											rounded: "xl",
											elevation: "2",
											class: "h-100 info-card"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(ssrRenderComponent(VCardTitle, { class: "text-h6 font-weight-bold" }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` Что есть на платформе `);
															else return [createTextVNode(" Что есть на платформе ")];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCardText, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(`<ul class="info-list" data-v-517f0294${_scopeId}><li data-v-517f0294${_scopeId}>каталог товаров и ингредиентов</li><li data-v-517f0294${_scopeId}>навигация по категориям</li><li data-v-517f0294${_scopeId}>подбор популярных позиций</li><li data-v-517f0294${_scopeId}>поиск по опубликованным товарам</li><li data-v-517f0294${_scopeId}>витрина для пищевой промышленности</li></ul>`);
															else return [createVNode("ul", { class: "info-list" }, [
																createVNode("li", null, "каталог товаров и ингредиентов"),
																createVNode("li", null, "навигация по категориям"),
																createVNode("li", null, "подбор популярных позиций"),
																createVNode("li", null, "поиск по опубликованным товарам"),
																createVNode("li", null, "витрина для пищевой промышленности")
															])];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCardActions, { class: "px-4 pb-4" }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(ssrRenderComponent(unref(Link), {
																href: unref(route)("public.goods.index"),
																class: "w-100"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(ssrRenderComponent(VBtn, {
																		block: "",
																		color: "#800000",
																		rounded: "xl"
																	}, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(` Смотреть товары `);
																			else return [createTextVNode(" Смотреть товары ")];
																		}),
																		_: 1
																	}, _parent, _scopeId));
																	else return [createVNode(VBtn, {
																		block: "",
																		color: "#800000",
																		rounded: "xl"
																	}, {
																		default: withCtx(() => [createTextVNode(" Смотреть товары ")]),
																		_: 1
																	})];
																}),
																_: 1
															}, _parent, _scopeId));
															else return [createVNode(unref(Link), {
																href: unref(route)("public.goods.index"),
																class: "w-100"
															}, {
																default: withCtx(() => [createVNode(VBtn, {
																	block: "",
																	color: "#800000",
																	rounded: "xl"
																}, {
																	default: withCtx(() => [createTextVNode(" Смотреть товары ")]),
																	_: 1
																})]),
																_: 1
															}, 8, ["href"])];
														}),
														_: 1
													}, _parent, _scopeId));
												} else return [
													createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
														default: withCtx(() => [createTextVNode(" Что есть на платформе ")]),
														_: 1
													}),
													createVNode(VCardText, null, {
														default: withCtx(() => [createVNode("ul", { class: "info-list" }, [
															createVNode("li", null, "каталог товаров и ингредиентов"),
															createVNode("li", null, "навигация по категориям"),
															createVNode("li", null, "подбор популярных позиций"),
															createVNode("li", null, "поиск по опубликованным товарам"),
															createVNode("li", null, "витрина для пищевой промышленности")
														])]),
														_: 1
													}),
													createVNode(VCardActions, { class: "px-4 pb-4" }, {
														default: withCtx(() => [createVNode(unref(Link), {
															href: unref(route)("public.goods.index"),
															class: "w-100"
														}, {
															default: withCtx(() => [createVNode(VBtn, {
																block: "",
																color: "#800000",
																rounded: "xl"
															}, {
																default: withCtx(() => [createTextVNode(" Смотреть товары ")]),
																_: 1
															})]),
															_: 1
														}, 8, ["href"])]),
														_: 1
													})
												];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VCard, {
											rounded: "xl",
											elevation: "2",
											class: "h-100 info-card"
										}, {
											default: withCtx(() => [
												createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
													default: withCtx(() => [createTextVNode(" Что есть на платформе ")]),
													_: 1
												}),
												createVNode(VCardText, null, {
													default: withCtx(() => [createVNode("ul", { class: "info-list" }, [
														createVNode("li", null, "каталог товаров и ингредиентов"),
														createVNode("li", null, "навигация по категориям"),
														createVNode("li", null, "подбор популярных позиций"),
														createVNode("li", null, "поиск по опубликованным товарам"),
														createVNode("li", null, "витрина для пищевой промышленности")
													])]),
													_: 1
												}),
												createVNode(VCardActions, { class: "px-4 pb-4" }, {
													default: withCtx(() => [createVNode(unref(Link), {
														href: unref(route)("public.goods.index"),
														class: "w-100"
													}, {
														default: withCtx(() => [createVNode(VBtn, {
															block: "",
															color: "#800000",
															rounded: "xl"
														}, {
															default: withCtx(() => [createTextVNode(" Смотреть товары ")]),
															_: 1
														})]),
														_: 1
													}, 8, ["href"])]),
													_: 1
												})
											]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCol, {
									cols: "12",
									md: "6",
									lg: "4"
								}, {
									default: withCtx(() => [createVNode(VCard, {
										rounded: "xl",
										elevation: "2",
										class: "h-100"
									}, {
										default: withCtx(() => [
											createVNode(VImg, {
												height: "220",
												src: "https://storage.yandexcloud.net/cold-reserve/Goods/Lecithin/%D0%A1%D0%BD%D0%B8%D0%BC%D0%BE%D0%BA%20%D1%8D%D0%BA%D1%80%D0%B0%D0%BD%D0%B0%202024-08-21%20%D0%B2%2019.51.31.png",
												cover: ""
											}),
											createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
												default: withCtx(() => [createTextVNode(" Лецитин ")]),
												_: 1
											}),
											createVNode(VCardSubtitle, null, {
												default: withCtx(() => [createTextVNode(" Подсолнечный ")]),
												_: 1
											}),
											createVNode(VCardText, { class: "text-body-2" }, {
												default: withCtx(() => [createTextVNode(" Концентрат фосфатидный подсолнечный. Используется в шоколадном производстве, помогает стабилизировать массу и улучшать текстуру продукта. ")]),
												_: 1
											})
										]),
										_: 1
									})]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									md: "6",
									lg: "4"
								}, {
									default: withCtx(() => [createVNode(VCard, {
										rounded: "xl",
										elevation: "2",
										class: "h-100"
									}, {
										default: withCtx(() => [
											createVNode(VImg, {
												height: "220",
												src: "https://storage.yandexcloud.net/cold-reserve/Goods/Glycerol/glycerol-25.jpg",
												cover: ""
											}),
											createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
												default: withCtx(() => [createTextVNode(" Глицерин ")]),
												_: 1
											}),
											createVNode(VCardSubtitle, null, {
												default: withCtx(() => [createTextVNode(" ПК-94, Д-98 ")]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													color: "#800000",
													variant: "text",
													onClick: ($event) => showGlycerin.value = !showGlycerin.value
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(showGlycerin.value ? "Скрыть" : "Подробнее"), 1)]),
													_: 1
												}, 8, ["onClick"])]),
												_: 1
											}),
											createVNode(VExpandTransition, null, {
												default: withCtx(() => [withDirectives(createVNode("div", null, [createVNode(VDivider), createVNode(VCardText, { class: "text-body-2" }, {
													default: withCtx(() => [createTextVNode(" Пищевая добавка Е422. Канистры 10 кг и 25 кг. Используется для фармакопейных целей, а также в пищевой, косметической и других отраслях промышленности. Срок хранения до вскрытия упаковки — 5 лет. ")]),
													_: 1
												})], 512), [[vShow, showGlycerin.value]])]),
												_: 1
											})
										]),
										_: 1
									})]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									lg: "4"
								}, {
									default: withCtx(() => [createVNode(VCard, {
										rounded: "xl",
										elevation: "2",
										class: "h-100 info-card"
									}, {
										default: withCtx(() => [
											createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
												default: withCtx(() => [createTextVNode(" Что есть на платформе ")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode("ul", { class: "info-list" }, [
													createVNode("li", null, "каталог товаров и ингредиентов"),
													createVNode("li", null, "навигация по категориям"),
													createVNode("li", null, "подбор популярных позиций"),
													createVNode("li", null, "поиск по опубликованным товарам"),
													createVNode("li", null, "витрина для пищевой промышленности")
												])]),
												_: 1
											}),
											createVNode(VCardActions, { class: "px-4 pb-4" }, {
												default: withCtx(() => [createVNode(unref(Link), {
													href: unref(route)("public.goods.index"),
													class: "w-100"
												}, {
													default: withCtx(() => [createVNode(VBtn, {
														block: "",
														color: "#800000",
														rounded: "xl"
													}, {
														default: withCtx(() => [createTextVNode(" Смотреть товары ")]),
														_: 1
													})]),
													_: 1
												}, 8, ["href"])]),
												_: 1
											})
										]),
										_: 1
									})]),
									_: 1
								})
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VRow, {
						dense: "",
						class: "align-stretch"
					}, {
						default: withCtx(() => [
							createVNode(VCol, {
								cols: "12",
								md: "6",
								lg: "4"
							}, {
								default: withCtx(() => [createVNode(VCard, {
									rounded: "xl",
									elevation: "2",
									class: "h-100"
								}, {
									default: withCtx(() => [
										createVNode(VImg, {
											height: "220",
											src: "https://storage.yandexcloud.net/cold-reserve/Goods/Lecithin/%D0%A1%D0%BD%D0%B8%D0%BC%D0%BE%D0%BA%20%D1%8D%D0%BA%D1%80%D0%B0%D0%BD%D0%B0%202024-08-21%20%D0%B2%2019.51.31.png",
											cover: ""
										}),
										createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
											default: withCtx(() => [createTextVNode(" Лецитин ")]),
											_: 1
										}),
										createVNode(VCardSubtitle, null, {
											default: withCtx(() => [createTextVNode(" Подсолнечный ")]),
											_: 1
										}),
										createVNode(VCardText, { class: "text-body-2" }, {
											default: withCtx(() => [createTextVNode(" Концентрат фосфатидный подсолнечный. Используется в шоколадном производстве, помогает стабилизировать массу и улучшать текстуру продукта. ")]),
											_: 1
										})
									]),
									_: 1
								})]),
								_: 1
							}),
							createVNode(VCol, {
								cols: "12",
								md: "6",
								lg: "4"
							}, {
								default: withCtx(() => [createVNode(VCard, {
									rounded: "xl",
									elevation: "2",
									class: "h-100"
								}, {
									default: withCtx(() => [
										createVNode(VImg, {
											height: "220",
											src: "https://storage.yandexcloud.net/cold-reserve/Goods/Glycerol/glycerol-25.jpg",
											cover: ""
										}),
										createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
											default: withCtx(() => [createTextVNode(" Глицерин ")]),
											_: 1
										}),
										createVNode(VCardSubtitle, null, {
											default: withCtx(() => [createTextVNode(" ПК-94, Д-98 ")]),
											_: 1
										}),
										createVNode(VCardActions, null, {
											default: withCtx(() => [createVNode(VBtn, {
												color: "#800000",
												variant: "text",
												onClick: ($event) => showGlycerin.value = !showGlycerin.value
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(showGlycerin.value ? "Скрыть" : "Подробнее"), 1)]),
												_: 1
											}, 8, ["onClick"])]),
											_: 1
										}),
										createVNode(VExpandTransition, null, {
											default: withCtx(() => [withDirectives(createVNode("div", null, [createVNode(VDivider), createVNode(VCardText, { class: "text-body-2" }, {
												default: withCtx(() => [createTextVNode(" Пищевая добавка Е422. Канистры 10 кг и 25 кг. Используется для фармакопейных целей, а также в пищевой, косметической и других отраслях промышленности. Срок хранения до вскрытия упаковки — 5 лет. ")]),
												_: 1
											})], 512), [[vShow, showGlycerin.value]])]),
											_: 1
										})
									]),
									_: 1
								})]),
								_: 1
							}),
							createVNode(VCol, {
								cols: "12",
								lg: "4"
							}, {
								default: withCtx(() => [createVNode(VCard, {
									rounded: "xl",
									elevation: "2",
									class: "h-100 info-card"
								}, {
									default: withCtx(() => [
										createVNode(VCardTitle, { class: "text-h6 font-weight-bold" }, {
											default: withCtx(() => [createTextVNode(" Что есть на платформе ")]),
											_: 1
										}),
										createVNode(VCardText, null, {
											default: withCtx(() => [createVNode("ul", { class: "info-list" }, [
												createVNode("li", null, "каталог товаров и ингредиентов"),
												createVNode("li", null, "навигация по категориям"),
												createVNode("li", null, "подбор популярных позиций"),
												createVNode("li", null, "поиск по опубликованным товарам"),
												createVNode("li", null, "витрина для пищевой промышленности")
											])]),
											_: 1
										}),
										createVNode(VCardActions, { class: "px-4 pb-4" }, {
											default: withCtx(() => [createVNode(unref(Link), {
												href: unref(route)("public.goods.index"),
												class: "w-100"
											}, {
												default: withCtx(() => [createVNode(VBtn, {
													block: "",
													color: "#800000",
													rounded: "xl"
												}, {
													default: withCtx(() => [createTextVNode(" Смотреть товары ")]),
													_: 1
												})]),
												_: 1
											}, 8, ["href"])]),
											_: 1
										})
									]),
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
			_push(`</section></div>`);
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Welcome.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var Welcome_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-517f0294"]]);
//#endregion
export { Welcome_default as default };

//# sourceMappingURL=Welcome-Ho9rE4XK.js.map