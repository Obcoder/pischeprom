import { $ as VAvatar, C as VRow, G as VList, T as VContainer, W as VMenu, q as VListItem, rt as VBtn, w as VCol } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { t as logo } from "./consts-BmbOcoGu.js";
import { Link, usePage } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, mergeProps, onBeforeUnmount, onMounted, openBlock, ref, renderList, resolveDynamicComponent, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderAttr, ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderSlot, ssrRenderStyle, ssrRenderVNode } from "vue/server-renderer";
//#region resources/js/Components/Layout/AppHeader.vue
var _sfc_main$2 = {
	__name: "AppHeader",
	__ssrInlineRender: true,
	props: { categories: {
		type: Array,
		default: () => []
	} },
	setup(__props) {
		const route$1 = (name, params = {}, absolute = true) => {
			return route(name, params, absolute, page.props.ziggy);
		};
		const page = usePage();
		const user = computed(() => page.props.auth?.user ?? null);
		ref("");
		const isCompact = ref(false);
		const quickLinks = [
			{
				label: "Рыба",
				href: route$1("category.show", 25)
			},
			{
				label: "Овощи",
				href: route$1("category.show", 30)
			},
			{
				label: "Бакалея",
				href: route$1("category.show", 31)
			}
		];
		const mainContacts = [{
			label: "+7-965-016-00-01",
			href: "tel:+79650160001"
		}, {
			label: "office@180022.ru",
			href: "mailto:office@180022.ru"
		}];
		function handleScroll() {
			isCompact.value = window.scrollY > 24;
		}
		onMounted(() => {
			handleScroll();
			window.addEventListener("scroll", handleScroll, { passive: true });
		});
		onBeforeUnmount(() => {
			window.removeEventListener("scroll", handleScroll);
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<header${ssrRenderAttrs(mergeProps({ class: ["app-header", { "app-header--compact": isCompact.value }] }, _attrs))} data-v-82ccde24><div class="app-header__top" data-v-82ccde24>`);
			_push(ssrRenderComponent(VContainer, { class: "py-0" }, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="app-header__top-inner" data-v-82ccde24${_scopeId}><div class="app-header__contacts" data-v-82ccde24${_scopeId}><!--[-->`);
						ssrRenderList(mainContacts, (contact) => {
							_push(`<a${ssrRenderAttr("href", contact.href)} class="app-header__contact" data-v-82ccde24${_scopeId}>${ssrInterpolate(contact.label)}</a>`);
						});
						_push(`<!--]--></div><div class="app-header__auth" data-v-82ccde24${_scopeId}>`);
						if (user.value) {
							_push(`<div class="app-header__user" data-v-82ccde24${_scopeId}>`);
							_push(ssrRenderComponent(VAvatar, {
								color: "white",
								size: "30"
							}, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(`<span class="text-red-darken-4 font-weight-bold" data-v-82ccde24${_scopeId}>${ssrInterpolate(user.value.name?.[0] || "U")}</span>`);
									else return [createVNode("span", { class: "text-red-darken-4 font-weight-bold" }, toDisplayString(user.value.name?.[0] || "U"), 1)];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(`<div class="app-header__user-meta" data-v-82ccde24${_scopeId}><div class="app-header__user-name" data-v-82ccde24${_scopeId}>${ssrInterpolate(user.value.name)}</div></div>`);
							_push(ssrRenderComponent(unref(Link), { href: route$1("dashboard") }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(ssrRenderComponent(VBtn, {
										color: "white",
										variant: "outlined",
										rounded: "xl",
										size: "small"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Кабинет `);
											else return [createTextVNode(" Кабинет ")];
										}),
										_: 1
									}, _parent, _scopeId));
									else return [createVNode(VBtn, {
										color: "white",
										variant: "outlined",
										rounded: "xl",
										size: "small"
									}, {
										default: withCtx(() => [createTextVNode(" Кабинет ")]),
										_: 1
									})];
								}),
								_: 1
							}, _parent, _scopeId));
							_push(`</div>`);
						} else {
							_push(`<div class="app-header__guest" data-v-82ccde24${_scopeId}>`);
							_push(ssrRenderComponent(unref(Link), { href: route$1("login") }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(ssrRenderComponent(VBtn, {
										color: "white",
										variant: "text",
										size: "small"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Войти `);
											else return [createTextVNode(" Войти ")];
										}),
										_: 1
									}, _parent, _scopeId));
									else return [createVNode(VBtn, {
										color: "white",
										variant: "text",
										size: "small"
									}, {
										default: withCtx(() => [createTextVNode(" Войти ")]),
										_: 1
									})];
								}),
								_: 1
							}, _parent, _scopeId));
							if (unref(page).props.canRegister) _push(ssrRenderComponent(unref(Link), { href: route$1("register") }, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(ssrRenderComponent(VBtn, {
										color: "white",
										variant: "flat",
										rounded: "xl",
										size: "small",
										class: "app-header__register-btn"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(` Регистрация `);
											else return [createTextVNode(" Регистрация ")];
										}),
										_: 1
									}, _parent, _scopeId));
									else return [createVNode(VBtn, {
										color: "white",
										variant: "flat",
										rounded: "xl",
										size: "small",
										class: "app-header__register-btn"
									}, {
										default: withCtx(() => [createTextVNode(" Регистрация ")]),
										_: 1
									})];
								}),
								_: 1
							}, _parent, _scopeId));
							else _push(`<!---->`);
							_push(`</div>`);
						}
						_push(`</div></div>`);
					} else return [createVNode("div", { class: "app-header__top-inner" }, [createVNode("div", { class: "app-header__contacts" }, [(openBlock(), createBlock(Fragment, null, renderList(mainContacts, (contact) => {
						return createVNode("a", {
							key: contact.href,
							href: contact.href,
							class: "app-header__contact"
						}, toDisplayString(contact.label), 9, ["href"]);
					}), 64))]), createVNode("div", { class: "app-header__auth" }, [user.value ? (openBlock(), createBlock("div", {
						key: 0,
						class: "app-header__user"
					}, [
						createVNode(VAvatar, {
							color: "white",
							size: "30"
						}, {
							default: withCtx(() => [createVNode("span", { class: "text-red-darken-4 font-weight-bold" }, toDisplayString(user.value.name?.[0] || "U"), 1)]),
							_: 1
						}),
						createVNode("div", { class: "app-header__user-meta" }, [createVNode("div", { class: "app-header__user-name" }, toDisplayString(user.value.name), 1)]),
						createVNode(unref(Link), { href: route$1("dashboard") }, {
							default: withCtx(() => [createVNode(VBtn, {
								color: "white",
								variant: "outlined",
								rounded: "xl",
								size: "small"
							}, {
								default: withCtx(() => [createTextVNode(" Кабинет ")]),
								_: 1
							})]),
							_: 1
						}, 8, ["href"])
					])) : (openBlock(), createBlock("div", {
						key: 1,
						class: "app-header__guest"
					}, [createVNode(unref(Link), { href: route$1("login") }, {
						default: withCtx(() => [createVNode(VBtn, {
							color: "white",
							variant: "text",
							size: "small"
						}, {
							default: withCtx(() => [createTextVNode(" Войти ")]),
							_: 1
						})]),
						_: 1
					}, 8, ["href"]), unref(page).props.canRegister ? (openBlock(), createBlock(unref(Link), {
						key: 0,
						href: route$1("register")
					}, {
						default: withCtx(() => [createVNode(VBtn, {
							color: "white",
							variant: "flat",
							rounded: "xl",
							size: "small",
							class: "app-header__register-btn"
						}, {
							default: withCtx(() => [createTextVNode(" Регистрация ")]),
							_: 1
						})]),
						_: 1
					}, 8, ["href"])) : createCommentVNode("", true)]))])])];
				}),
				_: 1
			}, _parent));
			_push(`</div><div class="app-header__main" data-v-82ccde24>`);
			_push(ssrRenderComponent(VContainer, { class: "py-0" }, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="app-header__main-inner" data-v-82ccde24${_scopeId}><div class="app-header__brand" data-v-82ccde24${_scopeId}>`);
						_push(ssrRenderComponent(unref(Link), {
							href: "/",
							class: "app-header__logo-link"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(`<div class="app-header__logo-shell" data-v-82ccde24${_scopeId}><img${ssrRenderAttr("src", unref(logo))} alt="ПИЩЕПРОМ-СЕРВЕР" class="app-header__logo" data-v-82ccde24${_scopeId}></div>`);
								else return [createVNode("div", { class: "app-header__logo-shell" }, [createVNode("img", {
									src: unref(logo),
									alt: "ПИЩЕПРОМ-СЕРВЕР",
									class: "app-header__logo"
								}, null, 8, ["src"])])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`<div class="app-header__brand-text" data-v-82ccde24${_scopeId}>`);
						_push(ssrRenderComponent(unref(Link), {
							href: "/",
							class: "app-header__brand-title"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` ПИЩЕПРОМ-СЕРВЕР `);
								else return [createTextVNode(" ПИЩЕПРОМ-СЕРВЕР ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`<div class="app-header__brand-subtitle" data-v-82ccde24${_scopeId}> Маркетплейс для пищевой промышленности </div></div></div><div class="app-header__nav" data-v-82ccde24${_scopeId}>`);
						_push(ssrRenderComponent(unref(Link), {
							href: route$1("public.goods.index"),
							class: "app-header__catalog-link"
						}, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(` Все товары `);
								else return [createTextVNode(" Все товары ")];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VMenu, null, {
							activator: withCtx(({ props: menuProps }, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VBtn, mergeProps(menuProps, {
									color: "#800000",
									variant: "tonal",
									rounded: "xl",
									size: "small"
								}), {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(` Категории `);
										else return [createTextVNode(" Категории ")];
									}),
									_: 2
								}, _parent, _scopeId));
								else return [createVNode(VBtn, mergeProps(menuProps, {
									color: "#800000",
									variant: "tonal",
									rounded: "xl",
									size: "small"
								}), {
									default: withCtx(() => [createTextVNode(" Категории ")]),
									_: 1
								}, 16)];
							}),
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VList, { "min-width": "260" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<!--[-->`);
											ssrRenderList(__props.categories, (category) => {
												_push(ssrRenderComponent(VListItem, { key: category.id }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(unref(Link), {
															href: route$1("category.show", category.id),
															class: "text-decoration-none text-high-emphasis"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`${ssrInterpolate(category.name)}`);
																else return [createTextVNode(toDisplayString(category.name), 1)];
															}),
															_: 2
														}, _parent, _scopeId));
														else return [createVNode(unref(Link), {
															href: route$1("category.show", category.id),
															class: "text-decoration-none text-high-emphasis"
														}, {
															default: withCtx(() => [createTextVNode(toDisplayString(category.name), 1)]),
															_: 2
														}, 1032, ["href"])];
													}),
													_: 2
												}, _parent, _scopeId));
											});
											_push(`<!--]-->`);
										} else return [(openBlock(true), createBlock(Fragment, null, renderList(__props.categories, (category) => {
											return openBlock(), createBlock(VListItem, { key: category.id }, {
												default: withCtx(() => [createVNode(unref(Link), {
													href: route$1("category.show", category.id),
													class: "text-decoration-none text-high-emphasis"
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(category.name), 1)]),
													_: 2
												}, 1032, ["href"])]),
												_: 2
											}, 1024);
										}), 128))];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VList, { "min-width": "260" }, {
									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.categories, (category) => {
										return openBlock(), createBlock(VListItem, { key: category.id }, {
											default: withCtx(() => [createVNode(unref(Link), {
												href: route$1("category.show", category.id),
												class: "text-decoration-none text-high-emphasis"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(category.name), 1)]),
												_: 2
											}, 1032, ["href"])]),
											_: 2
										}, 1024);
									}), 128))]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`<nav class="app-header__quick-links" data-v-82ccde24${_scopeId}><!--[-->`);
						ssrRenderList(quickLinks, (item) => {
							_push(ssrRenderComponent(unref(Link), {
								key: item.label,
								href: item.href,
								class: "app-header__quick-link"
							}, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(`${ssrInterpolate(item.label)}`);
									else return [createTextVNode(toDisplayString(item.label), 1)];
								}),
								_: 2
							}, _parent, _scopeId));
						});
						_push(`<!--]--></nav></div></div>`);
					} else return [createVNode("div", { class: "app-header__main-inner" }, [createVNode("div", { class: "app-header__brand" }, [createVNode(unref(Link), {
						href: "/",
						class: "app-header__logo-link"
					}, {
						default: withCtx(() => [createVNode("div", { class: "app-header__logo-shell" }, [createVNode("img", {
							src: unref(logo),
							alt: "ПИЩЕПРОМ-СЕРВЕР",
							class: "app-header__logo"
						}, null, 8, ["src"])])]),
						_: 1
					}), createVNode("div", { class: "app-header__brand-text" }, [createVNode(unref(Link), {
						href: "/",
						class: "app-header__brand-title"
					}, {
						default: withCtx(() => [createTextVNode(" ПИЩЕПРОМ-СЕРВЕР ")]),
						_: 1
					}), createVNode("div", { class: "app-header__brand-subtitle" }, " Маркетплейс для пищевой промышленности ")])]), createVNode("div", { class: "app-header__nav" }, [
						createVNode(unref(Link), {
							href: route$1("public.goods.index"),
							class: "app-header__catalog-link"
						}, {
							default: withCtx(() => [createTextVNode(" Все товары ")]),
							_: 1
						}, 8, ["href"]),
						createVNode(VMenu, null, {
							activator: withCtx(({ props: menuProps }) => [createVNode(VBtn, mergeProps(menuProps, {
								color: "#800000",
								variant: "tonal",
								rounded: "xl",
								size: "small"
							}), {
								default: withCtx(() => [createTextVNode(" Категории ")]),
								_: 1
							}, 16)]),
							default: withCtx(() => [createVNode(VList, { "min-width": "260" }, {
								default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(__props.categories, (category) => {
									return openBlock(), createBlock(VListItem, { key: category.id }, {
										default: withCtx(() => [createVNode(unref(Link), {
											href: route$1("category.show", category.id),
											class: "text-decoration-none text-high-emphasis"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(category.name), 1)]),
											_: 2
										}, 1032, ["href"])]),
										_: 2
									}, 1024);
								}), 128))]),
								_: 1
							})]),
							_: 1
						}),
						createVNode("nav", { class: "app-header__quick-links" }, [(openBlock(), createBlock(Fragment, null, renderList(quickLinks, (item) => {
							return createVNode(unref(Link), {
								key: item.label,
								href: item.href,
								class: "app-header__quick-link"
							}, {
								default: withCtx(() => [createTextVNode(toDisplayString(item.label), 1)]),
								_: 2
							}, 1032, ["href"]);
						}), 64))])
					])])];
				}),
				_: 1
			}, _parent));
			_push(`</div></header>`);
		};
	}
};
var _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Layout/AppHeader.vue");
	return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
var AppHeader_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main$2, [["__scopeId", "data-v-82ccde24"]]);
//#endregion
//#region resources/js/Components/Seo/YandexMetricaCounter.vue
var _sfc_main$1 = {
	__name: "YandexMetricaCounter",
	__ssrInlineRender: true,
	setup(__props) {
		const counterId = void 0;
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			if (unref(counterId)) ssrRenderVNode(_push, createVNode(resolveDynamicComponent("script"), null, null), _parent);
			else _push(`<!---->`);
			if (unref(counterId)) _push(`<noscript><div><img${ssrRenderAttr("src", `https://mc.yandex.ru/watch/${unref(counterId)}`)} style="${ssrRenderStyle({
				"position": "absolute",
				"left": "-9999px"
			})}" alt=""></div></noscript>`);
			else _push(`<!---->`);
			_push(`<!--]-->`);
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Seo/YandexMetricaCounter.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
//#endregion
//#region resources/js/Layouts/LayoutDefault.vue
var _sfc_main = {
	__name: "LayoutDefault",
	__ssrInlineRender: true,
	props: {
		categories: {
			type: Array,
			default: () => []
		},
		canRegister: {
			type: Boolean,
			default: false
		}
	},
	setup(__props) {
		const footerLinks = [
			"Home",
			"About Us",
			"Team",
			"Services",
			"Blog",
			"Contact Us"
		];
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(_sfc_main$1, null, null, _parent));
			_push(`<div class="layout-shell" data-v-fbd9e954>`);
			_push(ssrRenderComponent(AppHeader_default, { categories: __props.categories }, null, _parent));
			_push(`<main class="layout-main" data-v-fbd9e954>`);
			ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
			_push(`</main><footer class="layout-footer" data-v-fbd9e954>`);
			_push(ssrRenderComponent(VContainer, null, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VRow, {
						class: "py-6",
						justify: "center"
					}, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCol, {
									cols: "12",
									md: "4",
									class: "text-center"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(`<div class="text-subtitle-1 font-weight-bold mb-2" data-v-fbd9e954${_scopeId}> Телефон </div><div data-v-fbd9e954${_scopeId}>+7-965-016-0001</div>`);
										else return [createVNode("div", { class: "text-subtitle-1 font-weight-bold mb-2" }, " Телефон "), createVNode("div", null, "+7-965-016-0001")];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCol, {
									cols: "12",
									md: "4",
									class: "text-center"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<div class="d-flex flex-wrap justify-center ga-2" data-v-fbd9e954${_scopeId}><!--[-->`);
											ssrRenderList(footerLinks, (link) => {
												_push(ssrRenderComponent(VBtn, {
													key: link,
													color: "white",
													rounded: "xl",
													variant: "text",
													size: "small"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(`${ssrInterpolate(link)}`);
														else return [createTextVNode(toDisplayString(link), 1)];
													}),
													_: 2
												}, _parent, _scopeId));
											});
											_push(`<!--]--></div>`);
										} else return [createVNode("div", { class: "d-flex flex-wrap justify-center ga-2" }, [(openBlock(), createBlock(Fragment, null, renderList(footerLinks, (link) => {
											return createVNode(VBtn, {
												key: link,
												color: "white",
												rounded: "xl",
												variant: "text",
												size: "small"
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(link), 1)]),
												_: 2
											}, 1024);
										}), 64))])];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCol, {
									cols: "12",
									md: "4",
									class: "text-center"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(`<div data-v-fbd9e954${_scopeId}> 2022 - ${ssrInterpolate((/* @__PURE__ */ new Date()).getFullYear())} — <strong data-v-fbd9e954${_scopeId}>ООО &quot;Пищепром-сервер&quot;</strong></div>`);
										else return [createVNode("div", null, [createTextVNode(" 2022 - " + toDisplayString((/* @__PURE__ */ new Date()).getFullYear()) + " — ", 1), createVNode("strong", null, "ООО \"Пищепром-сервер\"")])];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCol, {
									cols: "12",
									md: "4",
									class: "text-center"
								}, {
									default: withCtx(() => [createVNode("div", { class: "text-subtitle-1 font-weight-bold mb-2" }, " Телефон "), createVNode("div", null, "+7-965-016-0001")]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									md: "4",
									class: "text-center"
								}, {
									default: withCtx(() => [createVNode("div", { class: "d-flex flex-wrap justify-center ga-2" }, [(openBlock(), createBlock(Fragment, null, renderList(footerLinks, (link) => {
										return createVNode(VBtn, {
											key: link,
											color: "white",
											rounded: "xl",
											variant: "text",
											size: "small"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(link), 1)]),
											_: 2
										}, 1024);
									}), 64))])]),
									_: 1
								}),
								createVNode(VCol, {
									cols: "12",
									md: "4",
									class: "text-center"
								}, {
									default: withCtx(() => [createVNode("div", null, [createTextVNode(" 2022 - " + toDisplayString((/* @__PURE__ */ new Date()).getFullYear()) + " — ", 1), createVNode("strong", null, "ООО \"Пищепром-сервер\"")])]),
									_: 1
								})
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VRow, {
						class: "py-6",
						justify: "center"
					}, {
						default: withCtx(() => [
							createVNode(VCol, {
								cols: "12",
								md: "4",
								class: "text-center"
							}, {
								default: withCtx(() => [createVNode("div", { class: "text-subtitle-1 font-weight-bold mb-2" }, " Телефон "), createVNode("div", null, "+7-965-016-0001")]),
								_: 1
							}),
							createVNode(VCol, {
								cols: "12",
								md: "4",
								class: "text-center"
							}, {
								default: withCtx(() => [createVNode("div", { class: "d-flex flex-wrap justify-center ga-2" }, [(openBlock(), createBlock(Fragment, null, renderList(footerLinks, (link) => {
									return createVNode(VBtn, {
										key: link,
										color: "white",
										rounded: "xl",
										variant: "text",
										size: "small"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(link), 1)]),
										_: 2
									}, 1024);
								}), 64))])]),
								_: 1
							}),
							createVNode(VCol, {
								cols: "12",
								md: "4",
								class: "text-center"
							}, {
								default: withCtx(() => [createVNode("div", null, [createTextVNode(" 2022 - " + toDisplayString((/* @__PURE__ */ new Date()).getFullYear()) + " — ", 1), createVNode("strong", null, "ООО \"Пищепром-сервер\"")])]),
								_: 1
							})
						]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
			_push(`</footer></div><!--]-->`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Layouts/LayoutDefault.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var LayoutDefault_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-fbd9e954"]]);
//#endregion
export { LayoutDefault_default as t };

//# sourceMappingURL=LayoutDefault-CXC0Lg2S.js.map