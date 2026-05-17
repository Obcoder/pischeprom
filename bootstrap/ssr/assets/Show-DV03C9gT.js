import { C as VRow, F as VCardText, I as VCardTitle, P as VCard, T as VContainer, X as VChip, et as VAlert, lt as VImg, ot as VIcon, rt as VBtn, w as VCol } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { t as LayoutDefault_default } from "./LayoutDefault-CXC0Lg2S.js";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { Fragment, computed, createBlock, createCommentVNode, createTextVNode, createVNode, onMounted, openBlock, ref, renderList, resolveDynamicComponent, toDisplayString, unref, useSSRContext, watch, withCtx } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderAttr, ssrRenderComponent, ssrRenderList, ssrRenderStyle, ssrRenderVNode } from "vue/server-renderer";
//#region resources/js/Composables/useYandexMetrica.js
function useYandexMetrica(counterId) {
	function enabled() {
		return Boolean(counterId) && typeof window !== "undefined" && typeof window.ym === "function";
	}
	function reachGoal(goal, params = {}) {
		if (!enabled()) return;
		window.ym(counterId, "reachGoal", goal, params);
	}
	function ecommerceViewItem(item) {
		if (typeof window === "undefined") return;
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			event: "view_item",
			ecommerce: {
				currency: item.currency || "RUB",
				items: [{
					item_id: item.id,
					item_name: item.name,
					item_category: item.category,
					price: Number(item.price || 0)
				}]
			}
		});
	}
	return {
		reachGoal,
		ecommerceViewItem
	};
}
//#endregion
//#region resources/js/Pages/Goods/Show.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: LayoutDefault_default }, {
	__name: "Show",
	__ssrInlineRender: true,
	props: {
		good: {
			type: Object,
			required: true
		},
		relatedGoods: {
			type: Array,
			default: () => []
		},
		seo: {
			type: Object,
			default: () => ({})
		}
	},
	setup(__props) {
		const props = __props;
		const page = usePage();
		const route$1 = (name, params = {}, absolute = true) => {
			return route(name, params, absolute, page.props.ziggy);
		};
		const { reachGoal, ecommerceViewItem } = useYandexMetrica(void 0);
		const activeImageId = ref(null);
		const mediaItems = computed(() => {
			return (props.good.published_media || []).filter((item) => item.is_published).sort((a, b) => {
				if (Number(b.is_ava) !== Number(a.is_ava)) return Number(b.is_ava) - Number(a.is_ava);
				const orderA = Number(a.sort_order || 100);
				const orderB = Number(b.sort_order || 100);
				if (orderA !== orderB) return orderA - orderB;
				return Number(a.id) - Number(b.id);
			});
		});
		const imageItems = computed(() => {
			return mediaItems.value.filter((item) => item.type === "image");
		});
		const videoItems = computed(() => {
			return mediaItems.value.filter((item) => {
				return item.type === "video" && mediaVideoUrl(item);
			});
		});
		const mainVideo = computed(() => {
			return videoItems.value.find((item) => {
				return item.is_main_video && item.processing_status === "done";
			}) || videoItems.value.find((item) => item.processing_status === "done") || videoItems.value[0] || null;
		});
		const otherVideoItems = computed(() => {
			if (!mainVideo.value) return videoItems.value;
			return videoItems.value.filter((item) => item.id !== mainVideo.value.id);
		});
		const activeImage = computed(() => {
			if (!imageItems.value.length) return null;
			if (!activeImageId.value) return imageItems.value[0];
			return imageItems.value.find((item) => item.id === activeImageId.value) || imageItems.value[0];
		});
		const fallbackImage = computed(() => {
			return props.good.ava_image || props.good.ava_thumb || null;
		});
		const publicPrices = computed(() => {
			return (props.good.price_type_values || []).filter((item) => item.is_published).sort((a, b) => {
				const orderA = Number(a.price_type?.sort_order || 100);
				const orderB = Number(b.price_type?.sort_order || 100);
				if (orderA !== orderB) return orderA - orderB;
				return String(a.price_type?.name || "").localeCompare(String(b.price_type?.name || ""));
			});
		});
		const productItems = computed(() => {
			return (props.good.products || []).map((product) => ({
				id: product.id,
				title: product.rus || product.name || product.title || product.eng || `Product #${product.id}`
			})).filter((product) => product.id && product.title);
		});
		const pageSeo = computed(() => {
			return props.seo || {};
		});
		const seo = computed(() => {
			return props.good.seo || {};
		});
		const seoIsActive = computed(() => {
			return seo.value?.is_active !== false;
		});
		const pageTitle = computed(() => {
			return pageSeo.value.title || seoIsActive.value && seo.value.meta_title || `${props.good.name} — ПИЩЕПРОМ-СЕРВЕР`;
		});
		const pageDescription = computed(() => {
			return pageSeo.value.description || seoIsActive.value && seo.value.meta_description || String(props.good.description || "").slice(0, 160);
		});
		const pageH1 = computed(() => {
			return pageSeo.value.h1 || seoIsActive.value && seo.value.h1 || props.good.name;
		});
		const ogTitle = computed(() => {
			return pageSeo.value.title || seoIsActive.value && seo.value.og_title || pageTitle.value;
		});
		const ogDescription = computed(() => {
			return pageSeo.value.description || seoIsActive.value && seo.value.og_description || pageDescription.value;
		});
		const ogImage = computed(() => {
			return pageSeo.value.image || seoIsActive.value && seo.value.og_image || fallbackImage.value || "";
		});
		const canonicalUrl = computed(() => {
			return pageSeo.value.canonical || seoIsActive.value && seo.value.canonical_url || "";
		});
		const robots = computed(() => {
			return pageSeo.value.robots || seoIsActive.value && seo.value.robots || "index,follow";
		});
		const structuredData = computed(() => {
			if (pageSeo.value.jsonLd) return pageSeo.value.jsonLd;
			if (seoIsActive.value && seo.value.structured_data) return seo.value.structured_data;
			return {
				"@context": "https://schema.org",
				"@type": "Product",
				name: props.good.name,
				description: pageDescription.value,
				image: imageItems.value.map((item) => item.url).filter(Boolean),
				brand: {
					"@type": "Brand",
					name: "ПИЩЕПРОМ-СЕРВЕР"
				}
			};
		});
		const structuredDataForHead = computed(() => {
			const data = structuredData.value;
			if (!data) return [];
			if (Array.isArray(data)) return data;
			return [data];
		});
		const structuredDataJsonForHead = computed(() => {
			return structuredDataForHead.value.map((item) => {
				return JSON.stringify(item);
			});
		});
		watch(imageItems, (items) => {
			if (!items.length) {
				activeImageId.value = null;
				return;
			}
			const exists = items.some((item) => item.id === activeImageId.value);
			if (!activeImageId.value || !exists) activeImageId.value = items[0].id;
		}, { immediate: true });
		function formatMoney(value) {
			if (value === null || value === void 0 || value === "") return "—";
			return new Intl.NumberFormat("ru-RU", {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			}).format(Number(value));
		}
		function mediaPreview(item) {
			if (!item) return null;
			if (item.type === "image") return item.thumb_url || item.url;
			if (item.type === "video") return item.poster_url || item.thumb_url || null;
			return null;
		}
		function mediaVideoUrl(item) {
			return item?.video_mp4_url || item?.url || null;
		}
		function relatedImage(item) {
			const mediaImage = (item.published_media || []).find((media) => media.type === "image");
			return mediaImage?.thumb_url || mediaImage?.url || item.ava_thumb || item.ava_image || null;
		}
		function requestPrice() {
			reachGoal("request_price_click", {
				good_id: props.good.id,
				good_name: props.good.name
			});
			window.location.href = `mailto:office@180022.ru?subject=${encodeURIComponent("Запрос цены: " + props.good.name)}`;
		}
		function clickPhone() {
			reachGoal("phone_click", {
				good_id: props.good.id,
				good_name: props.good.name
			});
		}
		function clickEmail() {
			reachGoal("email_click", {
				good_id: props.good.id,
				good_name: props.good.name
			});
		}
		onMounted(() => {
			reachGoal("view_good", {
				good_id: props.good.id,
				good_name: props.good.name
			});
			ecommerceViewItem({
				id: String(props.good.id),
				name: props.good.name,
				category: productItems.value[0]?.title || "Товар",
				price: publicPrices.value[0]?.price_gross || 0,
				currency: publicPrices.value[0]?.currency?.code || publicPrices.value[0]?.price_type?.currency?.code || "RUB"
			});
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), null, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<title data-v-0622424e${_scopeId}>${ssrInterpolate(pageTitle.value)}</title><meta head-key="description" name="description"${ssrRenderAttr("content", pageDescription.value)} data-v-0622424e${_scopeId}><meta head-key="robots" name="robots"${ssrRenderAttr("content", robots.value)} data-v-0622424e${_scopeId}>`);
						if (canonicalUrl.value) _push(`<link head-key="canonical" rel="canonical"${ssrRenderAttr("href", canonicalUrl.value)} data-v-0622424e${_scopeId}>`);
						else _push(`<!---->`);
						_push(`<meta head-key="og:title" property="og:title"${ssrRenderAttr("content", ogTitle.value)} data-v-0622424e${_scopeId}><meta head-key="og:description" property="og:description"${ssrRenderAttr("content", ogDescription.value)} data-v-0622424e${_scopeId}><meta head-key="og:type" property="og:type" content="product" data-v-0622424e${_scopeId}>`);
						if (canonicalUrl.value) _push(`<meta head-key="og:url" property="og:url"${ssrRenderAttr("content", canonicalUrl.value)} data-v-0622424e${_scopeId}>`);
						else _push(`<!---->`);
						if (ogImage.value) _push(`<meta head-key="og:image" property="og:image"${ssrRenderAttr("content", ogImage.value)} data-v-0622424e${_scopeId}>`);
						else _push(`<!---->`);
						_push(`<meta head-key="twitter:card" name="twitter:card" content="summary_large_image" data-v-0622424e${_scopeId}><meta head-key="twitter:title" name="twitter:title"${ssrRenderAttr("content", ogTitle.value)} data-v-0622424e${_scopeId}><meta head-key="twitter:description" name="twitter:description"${ssrRenderAttr("content", ogDescription.value)} data-v-0622424e${_scopeId}>`);
						if (ogImage.value) _push(`<meta head-key="twitter:image" name="twitter:image"${ssrRenderAttr("content", ogImage.value)} data-v-0622424e${_scopeId}>`);
						else _push(`<!---->`);
						_push(`<!--[-->`);
						ssrRenderList(structuredDataJsonForHead.value, (json, index) => {
							ssrRenderVNode(_push, createVNode(resolveDynamicComponent("script"), {
								key: `json-ld-${index}`,
								"head-key": `json-ld-${index}`,
								type: "application/ld+json"
							}, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(`${ssrInterpolate(json)}`);
									else return [createTextVNode(toDisplayString(json), 1)];
								}),
								_: 2
							}), _parent, _scopeId);
						});
						_push(`<!--]-->`);
					} else return [
						createVNode("title", null, toDisplayString(pageTitle.value), 1),
						createVNode("meta", {
							"head-key": "description",
							name: "description",
							content: pageDescription.value
						}, null, 8, ["content"]),
						createVNode("meta", {
							"head-key": "robots",
							name: "robots",
							content: robots.value
						}, null, 8, ["content"]),
						canonicalUrl.value ? (openBlock(), createBlock("link", {
							key: 0,
							"head-key": "canonical",
							rel: "canonical",
							href: canonicalUrl.value
						}, null, 8, ["href"])) : createCommentVNode("", true),
						createVNode("meta", {
							"head-key": "og:title",
							property: "og:title",
							content: ogTitle.value
						}, null, 8, ["content"]),
						createVNode("meta", {
							"head-key": "og:description",
							property: "og:description",
							content: ogDescription.value
						}, null, 8, ["content"]),
						createVNode("meta", {
							"head-key": "og:type",
							property: "og:type",
							content: "product"
						}),
						canonicalUrl.value ? (openBlock(), createBlock("meta", {
							key: 1,
							"head-key": "og:url",
							property: "og:url",
							content: canonicalUrl.value
						}, null, 8, ["content"])) : createCommentVNode("", true),
						ogImage.value ? (openBlock(), createBlock("meta", {
							key: 2,
							"head-key": "og:image",
							property: "og:image",
							content: ogImage.value
						}, null, 8, ["content"])) : createCommentVNode("", true),
						createVNode("meta", {
							"head-key": "twitter:card",
							name: "twitter:card",
							content: "summary_large_image"
						}),
						createVNode("meta", {
							"head-key": "twitter:title",
							name: "twitter:title",
							content: ogTitle.value
						}, null, 8, ["content"]),
						createVNode("meta", {
							"head-key": "twitter:description",
							name: "twitter:description",
							content: ogDescription.value
						}, null, 8, ["content"]),
						ogImage.value ? (openBlock(), createBlock("meta", {
							key: 3,
							"head-key": "twitter:image",
							name: "twitter:image",
							content: ogImage.value
						}, null, 8, ["content"])) : createCommentVNode("", true),
						(openBlock(true), createBlock(Fragment, null, renderList(structuredDataJsonForHead.value, (json, index) => {
							return openBlock(), createBlock(resolveDynamicComponent("script"), {
								key: `json-ld-${index}`,
								"head-key": `json-ld-${index}`,
								type: "application/ld+json"
							}, {
								default: withCtx(() => [createTextVNode(toDisplayString(json), 1)]),
								_: 2
							}, 1032, ["head-key"]);
						}), 128))
					];
				}),
				_: 1
			}, _parent));
			_push(ssrRenderComponent(VContainer, { class: "py-8" }, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										md: "5"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												if (mainVideo.value) _push(ssrRenderComponent(VCard, {
													rounded: "xl",
													elevation: "2",
													class: "overflow-hidden mb-4 hero-video-card"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<video class="product-hero-video" autoplay muted loop playsinline preload="metadata" disablepictureinpicture controlslist="nodownload noplaybackrate nofullscreen"${ssrRenderAttr("poster", mainVideo.value.poster_url || void 0)}${ssrRenderAttr("aria-label", mainVideo.value.title || __props.good.name)} data-v-0622424e${_scopeId}><source${ssrRenderAttr("src", mediaVideoUrl(mainVideo.value))} type="video/mp4" data-v-0622424e${_scopeId}> Ваш браузер не поддерживает video. </video><div class="video-overlay" data-v-0622424e${_scopeId}>`);
															_push(ssrRenderComponent(VChip, {
																size: "small",
																color: "black",
																variant: "flat",
																class: "text-white"
															}, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VIcon, {
																			icon: "mdi-play-circle",
																			size: "16",
																			class: "mr-1"
																		}, null, _parent, _scopeId));
																		_push(` Видео товара `);
																	} else return [createVNode(VIcon, {
																		icon: "mdi-play-circle",
																		size: "16",
																		class: "mr-1"
																	}), createTextVNode(" Видео товара ")];
																}),
																_: 1
															}, _parent, _scopeId));
															_push(`</div>`);
														} else return [createVNode("video", {
															class: "product-hero-video",
															autoplay: "",
															muted: "",
															loop: "",
															playsinline: "",
															preload: "metadata",
															disablepictureinpicture: "",
															controlslist: "nodownload noplaybackrate nofullscreen",
															poster: mainVideo.value.poster_url || void 0,
															"aria-label": mainVideo.value.title || __props.good.name
														}, [createVNode("source", {
															src: mediaVideoUrl(mainVideo.value),
															type: "video/mp4"
														}, null, 8, ["src"]), createTextVNode(" Ваш браузер не поддерживает video. ")], 8, ["poster", "aria-label"]), createVNode("div", { class: "video-overlay" }, [createVNode(VChip, {
															size: "small",
															color: "black",
															variant: "flat",
															class: "text-white"
														}, {
															default: withCtx(() => [createVNode(VIcon, {
																icon: "mdi-play-circle",
																size: "16",
																class: "mr-1"
															}), createTextVNode(" Видео товара ")]),
															_: 1
														})])];
													}),
													_: 1
												}, _parent, _scopeId));
												else _push(`<!---->`);
												_push(ssrRenderComponent(VCard, {
													rounded: "xl",
													elevation: "2",
													class: "overflow-hidden"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) if (activeImage.value) _push(ssrRenderComponent(VImg, {
															src: activeImage.value.url,
															alt: activeImage.value.alt || __props.good.name,
															height: "420",
															cover: ""
														}, null, _parent, _scopeId));
														else if (fallbackImage.value) _push(ssrRenderComponent(VImg, {
															src: fallbackImage.value,
															alt: __props.good.name,
															height: "420",
															cover: ""
														}, null, _parent, _scopeId));
														else {
															_push(`<div class="empty-preview" data-v-0622424e${_scopeId}>`);
															_push(ssrRenderComponent(VIcon, {
																icon: "mdi-image-off",
																size: "64",
																class: "mb-3"
															}, null, _parent, _scopeId));
															_push(`<div data-v-0622424e${_scopeId}>Изображение пока не добавлено</div></div>`);
														}
														else return [activeImage.value ? (openBlock(), createBlock(VImg, {
															key: 0,
															src: activeImage.value.url,
															alt: activeImage.value.alt || __props.good.name,
															height: "420",
															cover: ""
														}, null, 8, ["src", "alt"])) : fallbackImage.value ? (openBlock(), createBlock(VImg, {
															key: 1,
															src: fallbackImage.value,
															alt: __props.good.name,
															height: "420",
															cover: ""
														}, null, 8, ["src", "alt"])) : (openBlock(), createBlock("div", {
															key: 2,
															class: "empty-preview"
														}, [createVNode(VIcon, {
															icon: "mdi-image-off",
															size: "64",
															class: "mb-3"
														}), createVNode("div", null, "Изображение пока не добавлено")]))];
													}),
													_: 1
												}, _parent, _scopeId));
												if (imageItems.value.length) _push(ssrRenderComponent(VRow, {
													class: "mt-3",
													dense: ""
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<!--[-->`);
															ssrRenderList(imageItems.value, (item) => {
																_push(ssrRenderComponent(VCol, {
																	key: item.id,
																	cols: "3"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VCard, {
																			class: ["media-thumb", { "media-thumb--active": activeImage.value?.id === item.id }],
																			rounded: "lg",
																			variant: "tonal",
																			onClick: ($event) => activeImageId.value = item.id
																		}, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VImg, {
																					src: mediaPreview(item),
																					height: "84",
																					cover: ""
																				}, null, _parent, _scopeId));
																				else return [createVNode(VImg, {
																					src: mediaPreview(item),
																					height: "84",
																					cover: ""
																				}, null, 8, ["src"])];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		else return [createVNode(VCard, {
																			class: ["media-thumb", { "media-thumb--active": activeImage.value?.id === item.id }],
																			rounded: "lg",
																			variant: "tonal",
																			onClick: ($event) => activeImageId.value = item.id
																		}, {
																			default: withCtx(() => [createVNode(VImg, {
																				src: mediaPreview(item),
																				height: "84",
																				cover: ""
																			}, null, 8, ["src"])]),
																			_: 2
																		}, 1032, ["class", "onClick"])];
																	}),
																	_: 2
																}, _parent, _scopeId));
															});
															_push(`<!--]-->`);
														} else return [(openBlock(true), createBlock(Fragment, null, renderList(imageItems.value, (item) => {
															return openBlock(), createBlock(VCol, {
																key: item.id,
																cols: "3"
															}, {
																default: withCtx(() => [createVNode(VCard, {
																	class: ["media-thumb", { "media-thumb--active": activeImage.value?.id === item.id }],
																	rounded: "lg",
																	variant: "tonal",
																	onClick: ($event) => activeImageId.value = item.id
																}, {
																	default: withCtx(() => [createVNode(VImg, {
																		src: mediaPreview(item),
																		height: "84",
																		cover: ""
																	}, null, 8, ["src"])]),
																	_: 2
																}, 1032, ["class", "onClick"])]),
																_: 2
															}, 1024);
														}), 128))];
													}),
													_: 1
												}, _parent, _scopeId));
												else _push(`<!---->`);
											} else return [
												mainVideo.value ? (openBlock(), createBlock(VCard, {
													key: 0,
													rounded: "xl",
													elevation: "2",
													class: "overflow-hidden mb-4 hero-video-card"
												}, {
													default: withCtx(() => [createVNode("video", {
														class: "product-hero-video",
														autoplay: "",
														muted: "",
														loop: "",
														playsinline: "",
														preload: "metadata",
														disablepictureinpicture: "",
														controlslist: "nodownload noplaybackrate nofullscreen",
														poster: mainVideo.value.poster_url || void 0,
														"aria-label": mainVideo.value.title || __props.good.name
													}, [createVNode("source", {
														src: mediaVideoUrl(mainVideo.value),
														type: "video/mp4"
													}, null, 8, ["src"]), createTextVNode(" Ваш браузер не поддерживает video. ")], 8, ["poster", "aria-label"]), createVNode("div", { class: "video-overlay" }, [createVNode(VChip, {
														size: "small",
														color: "black",
														variant: "flat",
														class: "text-white"
													}, {
														default: withCtx(() => [createVNode(VIcon, {
															icon: "mdi-play-circle",
															size: "16",
															class: "mr-1"
														}), createTextVNode(" Видео товара ")]),
														_: 1
													})])]),
													_: 1
												})) : createCommentVNode("", true),
												createVNode(VCard, {
													rounded: "xl",
													elevation: "2",
													class: "overflow-hidden"
												}, {
													default: withCtx(() => [activeImage.value ? (openBlock(), createBlock(VImg, {
														key: 0,
														src: activeImage.value.url,
														alt: activeImage.value.alt || __props.good.name,
														height: "420",
														cover: ""
													}, null, 8, ["src", "alt"])) : fallbackImage.value ? (openBlock(), createBlock(VImg, {
														key: 1,
														src: fallbackImage.value,
														alt: __props.good.name,
														height: "420",
														cover: ""
													}, null, 8, ["src", "alt"])) : (openBlock(), createBlock("div", {
														key: 2,
														class: "empty-preview"
													}, [createVNode(VIcon, {
														icon: "mdi-image-off",
														size: "64",
														class: "mb-3"
													}), createVNode("div", null, "Изображение пока не добавлено")]))]),
													_: 1
												}),
												imageItems.value.length ? (openBlock(), createBlock(VRow, {
													key: 1,
													class: "mt-3",
													dense: ""
												}, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(imageItems.value, (item) => {
														return openBlock(), createBlock(VCol, {
															key: item.id,
															cols: "3"
														}, {
															default: withCtx(() => [createVNode(VCard, {
																class: ["media-thumb", { "media-thumb--active": activeImage.value?.id === item.id }],
																rounded: "lg",
																variant: "tonal",
																onClick: ($event) => activeImageId.value = item.id
															}, {
																default: withCtx(() => [createVNode(VImg, {
																	src: mediaPreview(item),
																	height: "84",
																	cover: ""
																}, null, 8, ["src"])]),
																_: 2
															}, 1032, ["class", "onClick"])]),
															_: 2
														}, 1024);
													}), 128))]),
													_: 1
												})) : createCommentVNode("", true)
											];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, {
										cols: "12",
										md: "7"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) {
												if (productItems.value.length) {
													_push(`<div class="mb-4 product-links" data-v-0622424e${_scopeId}><!--[-->`);
													ssrRenderList(productItems.value, (product) => {
														_push(ssrRenderComponent(unref(Link), {
															key: product.id,
															href: route$1("shop.products.show", { product: product.id }),
															class: "text-decoration-none d-inline-block mr-2 mb-2"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VChip, {
																	size: "small",
																	class: "product-link-chip"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) {
																			_push(ssrRenderComponent(VIcon, {
																				icon: "mdi-leaf",
																				size: "15",
																				class: "mr-1"
																			}, null, _parent, _scopeId));
																			_push(` ${ssrInterpolate(product.title)}`);
																		} else return [createVNode(VIcon, {
																			icon: "mdi-leaf",
																			size: "15",
																			class: "mr-1"
																		}), createTextVNode(" " + toDisplayString(product.title), 1)];
																	}),
																	_: 2
																}, _parent, _scopeId));
																else return [createVNode(VChip, {
																	size: "small",
																	class: "product-link-chip"
																}, {
																	default: withCtx(() => [createVNode(VIcon, {
																		icon: "mdi-leaf",
																		size: "15",
																		class: "mr-1"
																	}), createTextVNode(" " + toDisplayString(product.title), 1)]),
																	_: 2
																}, 1024)];
															}),
															_: 2
														}, _parent, _scopeId));
													});
													_push(`<!--]--></div>`);
												} else _push(`<!---->`);
												_push(`<h1 class="text-h3 font-weight-bold mb-4" data-v-0622424e${_scopeId}>${ssrInterpolate(pageH1.value)}</h1>`);
												if (publicPrices.value.length) {
													_push(`<section class="price-panel mb-5" data-v-0622424e${_scopeId}><div class="price-panel__header" data-v-0622424e${_scopeId}><div data-v-0622424e${_scopeId}><div class="price-panel__eyebrow" data-v-0622424e${_scopeId}> Актуальные цены </div><h2 class="price-panel__title" data-v-0622424e${_scopeId}> Цены </h2></div>`);
													_push(ssrRenderComponent(VIcon, {
														icon: "mdi-cash-multiple",
														size: "34",
														class: "price-panel__icon"
													}, null, _parent, _scopeId));
													_push(`</div>`);
													_push(ssrRenderComponent(VRow, { dense: "" }, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(`<!--[-->`);
																ssrRenderList(publicPrices.value, (price) => {
																	_push(ssrRenderComponent(VCol, {
																		key: price.id,
																		cols: "12",
																		sm: "6"
																	}, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(`<div class="price-tile" data-v-0622424e${_scopeId}><div class="price-tile__name" data-v-0622424e${_scopeId}>${ssrInterpolate(price.price_type?.name || "Цена")}</div><div class="price-tile__value" data-v-0622424e${_scopeId}>${ssrInterpolate(formatMoney(price.price_gross))} <span data-v-0622424e${_scopeId}>${ssrInterpolate(price.currency?.code || price.price_type?.currency?.code || "RUB")}</span></div><div class="price-tile__note" data-v-0622424e${_scopeId}> с НДС / кг </div></div>`);
																			else return [createVNode("div", { class: "price-tile" }, [
																				createVNode("div", { class: "price-tile__name" }, toDisplayString(price.price_type?.name || "Цена"), 1),
																				createVNode("div", { class: "price-tile__value" }, [createTextVNode(toDisplayString(formatMoney(price.price_gross)) + " ", 1), createVNode("span", null, toDisplayString(price.currency?.code || price.price_type?.currency?.code || "RUB"), 1)]),
																				createVNode("div", { class: "price-tile__note" }, " с НДС / кг ")
																			])];
																		}),
																		_: 2
																	}, _parent, _scopeId));
																});
																_push(`<!--]-->`);
															} else return [(openBlock(true), createBlock(Fragment, null, renderList(publicPrices.value, (price) => {
																return openBlock(), createBlock(VCol, {
																	key: price.id,
																	cols: "12",
																	sm: "6"
																}, {
																	default: withCtx(() => [createVNode("div", { class: "price-tile" }, [
																		createVNode("div", { class: "price-tile__name" }, toDisplayString(price.price_type?.name || "Цена"), 1),
																		createVNode("div", { class: "price-tile__value" }, [createTextVNode(toDisplayString(formatMoney(price.price_gross)) + " ", 1), createVNode("span", null, toDisplayString(price.currency?.code || price.price_type?.currency?.code || "RUB"), 1)]),
																		createVNode("div", { class: "price-tile__note" }, " с НДС / кг ")
																	])]),
																	_: 2
																}, 1024);
															}), 128))];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(`</section>`);
												} else _push(`<!---->`);
												if (__props.good.description) _push(`<div class="text-body-1 mb-6" style="${ssrRenderStyle({ "line-height": "1.8" })}" data-v-0622424e${_scopeId}>${ssrInterpolate(__props.good.description)}</div>`);
												else _push(`<div class="text-body-1 text-medium-emphasis mb-6" data-v-0622424e${_scopeId}> Описание пока не заполнено. </div>`);
												if (seoIsActive.value && seo.value.short_seo_text) _push(`<div class="text-body-2 text-medium-emphasis mb-4" style="${ssrRenderStyle({ "line-height": "1.7" })}" data-v-0622424e${_scopeId}>${ssrInterpolate(seo.value.short_seo_text)}</div>`);
												else _push(`<!---->`);
												_push(`<div class="d-flex flex-wrap ga-3 mb-4" data-v-0622424e${_scopeId}>`);
												_push(ssrRenderComponent(VBtn, {
													color: "deep-purple-darken-1",
													size: "large",
													rounded: "xl",
													onClick: requestPrice
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` Запросить цену `);
														else return [createTextVNode(" Запросить цену ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VBtn, {
													variant: "tonal",
													size: "large",
													rounded: "xl",
													href: "tel:+79650160001",
													onClick: clickPhone
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` Позвонить `);
														else return [createTextVNode(" Позвонить ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(ssrRenderComponent(VBtn, {
													variant: "tonal",
													size: "large",
													rounded: "xl",
													href: "mailto:office@180022.ru",
													onClick: clickEmail
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` Написать на email `);
														else return [createTextVNode(" Написать на email ")];
													}),
													_: 1
												}, _parent, _scopeId));
												_push(`</div>`);
												_push(ssrRenderComponent(VAlert, {
													type: "info",
													variant: "tonal",
													class: "mb-4"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(` Для уточнения цены и условий поставки свяжитесь с нами: <strong data-v-0622424e${_scopeId}>+7-965-016-0001</strong>, <strong data-v-0622424e${_scopeId}>office@180022.ru</strong>`);
														else return [
															createTextVNode(" Для уточнения цены и условий поставки свяжитесь с нами: "),
															createVNode("strong", null, "+7-965-016-0001"),
															createTextVNode(", "),
															createVNode("strong", null, "office@180022.ru")
														];
													}),
													_: 1
												}, _parent, _scopeId));
											} else return [
												productItems.value.length ? (openBlock(), createBlock("div", {
													key: 0,
													class: "mb-4 product-links"
												}, [(openBlock(true), createBlock(Fragment, null, renderList(productItems.value, (product) => {
													return openBlock(), createBlock(unref(Link), {
														key: product.id,
														href: route$1("shop.products.show", { product: product.id }),
														class: "text-decoration-none d-inline-block mr-2 mb-2"
													}, {
														default: withCtx(() => [createVNode(VChip, {
															size: "small",
															class: "product-link-chip"
														}, {
															default: withCtx(() => [createVNode(VIcon, {
																icon: "mdi-leaf",
																size: "15",
																class: "mr-1"
															}), createTextVNode(" " + toDisplayString(product.title), 1)]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1032, ["href"]);
												}), 128))])) : createCommentVNode("", true),
												createVNode("h1", { class: "text-h3 font-weight-bold mb-4" }, toDisplayString(pageH1.value), 1),
												publicPrices.value.length ? (openBlock(), createBlock("section", {
													key: 1,
													class: "price-panel mb-5"
												}, [createVNode("div", { class: "price-panel__header" }, [createVNode("div", null, [createVNode("div", { class: "price-panel__eyebrow" }, " Актуальные цены "), createVNode("h2", { class: "price-panel__title" }, " Цены ")]), createVNode(VIcon, {
													icon: "mdi-cash-multiple",
													size: "34",
													class: "price-panel__icon"
												})]), createVNode(VRow, { dense: "" }, {
													default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(publicPrices.value, (price) => {
														return openBlock(), createBlock(VCol, {
															key: price.id,
															cols: "12",
															sm: "6"
														}, {
															default: withCtx(() => [createVNode("div", { class: "price-tile" }, [
																createVNode("div", { class: "price-tile__name" }, toDisplayString(price.price_type?.name || "Цена"), 1),
																createVNode("div", { class: "price-tile__value" }, [createTextVNode(toDisplayString(formatMoney(price.price_gross)) + " ", 1), createVNode("span", null, toDisplayString(price.currency?.code || price.price_type?.currency?.code || "RUB"), 1)]),
																createVNode("div", { class: "price-tile__note" }, " с НДС / кг ")
															])]),
															_: 2
														}, 1024);
													}), 128))]),
													_: 1
												})])) : createCommentVNode("", true),
												__props.good.description ? (openBlock(), createBlock("div", {
													key: 2,
													class: "text-body-1 mb-6",
													style: { "line-height": "1.8" }
												}, toDisplayString(__props.good.description), 1)) : (openBlock(), createBlock("div", {
													key: 3,
													class: "text-body-1 text-medium-emphasis mb-6"
												}, " Описание пока не заполнено. ")),
												seoIsActive.value && seo.value.short_seo_text ? (openBlock(), createBlock("div", {
													key: 4,
													class: "text-body-2 text-medium-emphasis mb-4",
													style: { "line-height": "1.7" }
												}, toDisplayString(seo.value.short_seo_text), 1)) : createCommentVNode("", true),
												createVNode("div", { class: "d-flex flex-wrap ga-3 mb-4" }, [
													createVNode(VBtn, {
														color: "deep-purple-darken-1",
														size: "large",
														rounded: "xl",
														onClick: requestPrice
													}, {
														default: withCtx(() => [createTextVNode(" Запросить цену ")]),
														_: 1
													}),
													createVNode(VBtn, {
														variant: "tonal",
														size: "large",
														rounded: "xl",
														href: "tel:+79650160001",
														onClick: clickPhone
													}, {
														default: withCtx(() => [createTextVNode(" Позвонить ")]),
														_: 1
													}),
													createVNode(VBtn, {
														variant: "tonal",
														size: "large",
														rounded: "xl",
														href: "mailto:office@180022.ru",
														onClick: clickEmail
													}, {
														default: withCtx(() => [createTextVNode(" Написать на email ")]),
														_: 1
													})
												]),
												createVNode(VAlert, {
													type: "info",
													variant: "tonal",
													class: "mb-4"
												}, {
													default: withCtx(() => [
														createTextVNode(" Для уточнения цены и условий поставки свяжитесь с нами: "),
														createVNode("strong", null, "+7-965-016-0001"),
														createTextVNode(", "),
														createVNode("strong", null, "office@180022.ru")
													]),
													_: 1
												})
											];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, {
									cols: "12",
									md: "5"
								}, {
									default: withCtx(() => [
										mainVideo.value ? (openBlock(), createBlock(VCard, {
											key: 0,
											rounded: "xl",
											elevation: "2",
											class: "overflow-hidden mb-4 hero-video-card"
										}, {
											default: withCtx(() => [createVNode("video", {
												class: "product-hero-video",
												autoplay: "",
												muted: "",
												loop: "",
												playsinline: "",
												preload: "metadata",
												disablepictureinpicture: "",
												controlslist: "nodownload noplaybackrate nofullscreen",
												poster: mainVideo.value.poster_url || void 0,
												"aria-label": mainVideo.value.title || __props.good.name
											}, [createVNode("source", {
												src: mediaVideoUrl(mainVideo.value),
												type: "video/mp4"
											}, null, 8, ["src"]), createTextVNode(" Ваш браузер не поддерживает video. ")], 8, ["poster", "aria-label"]), createVNode("div", { class: "video-overlay" }, [createVNode(VChip, {
												size: "small",
												color: "black",
												variant: "flat",
												class: "text-white"
											}, {
												default: withCtx(() => [createVNode(VIcon, {
													icon: "mdi-play-circle",
													size: "16",
													class: "mr-1"
												}), createTextVNode(" Видео товара ")]),
												_: 1
											})])]),
											_: 1
										})) : createCommentVNode("", true),
										createVNode(VCard, {
											rounded: "xl",
											elevation: "2",
											class: "overflow-hidden"
										}, {
											default: withCtx(() => [activeImage.value ? (openBlock(), createBlock(VImg, {
												key: 0,
												src: activeImage.value.url,
												alt: activeImage.value.alt || __props.good.name,
												height: "420",
												cover: ""
											}, null, 8, ["src", "alt"])) : fallbackImage.value ? (openBlock(), createBlock(VImg, {
												key: 1,
												src: fallbackImage.value,
												alt: __props.good.name,
												height: "420",
												cover: ""
											}, null, 8, ["src", "alt"])) : (openBlock(), createBlock("div", {
												key: 2,
												class: "empty-preview"
											}, [createVNode(VIcon, {
												icon: "mdi-image-off",
												size: "64",
												class: "mb-3"
											}), createVNode("div", null, "Изображение пока не добавлено")]))]),
											_: 1
										}),
										imageItems.value.length ? (openBlock(), createBlock(VRow, {
											key: 1,
											class: "mt-3",
											dense: ""
										}, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(imageItems.value, (item) => {
												return openBlock(), createBlock(VCol, {
													key: item.id,
													cols: "3"
												}, {
													default: withCtx(() => [createVNode(VCard, {
														class: ["media-thumb", { "media-thumb--active": activeImage.value?.id === item.id }],
														rounded: "lg",
														variant: "tonal",
														onClick: ($event) => activeImageId.value = item.id
													}, {
														default: withCtx(() => [createVNode(VImg, {
															src: mediaPreview(item),
															height: "84",
															cover: ""
														}, null, 8, ["src"])]),
														_: 2
													}, 1032, ["class", "onClick"])]),
													_: 2
												}, 1024);
											}), 128))]),
											_: 1
										})) : createCommentVNode("", true)
									]),
									_: 1
								}), createVNode(VCol, {
									cols: "12",
									md: "7"
								}, {
									default: withCtx(() => [
										productItems.value.length ? (openBlock(), createBlock("div", {
											key: 0,
											class: "mb-4 product-links"
										}, [(openBlock(true), createBlock(Fragment, null, renderList(productItems.value, (product) => {
											return openBlock(), createBlock(unref(Link), {
												key: product.id,
												href: route$1("shop.products.show", { product: product.id }),
												class: "text-decoration-none d-inline-block mr-2 mb-2"
											}, {
												default: withCtx(() => [createVNode(VChip, {
													size: "small",
													class: "product-link-chip"
												}, {
													default: withCtx(() => [createVNode(VIcon, {
														icon: "mdi-leaf",
														size: "15",
														class: "mr-1"
													}), createTextVNode(" " + toDisplayString(product.title), 1)]),
													_: 2
												}, 1024)]),
												_: 2
											}, 1032, ["href"]);
										}), 128))])) : createCommentVNode("", true),
										createVNode("h1", { class: "text-h3 font-weight-bold mb-4" }, toDisplayString(pageH1.value), 1),
										publicPrices.value.length ? (openBlock(), createBlock("section", {
											key: 1,
											class: "price-panel mb-5"
										}, [createVNode("div", { class: "price-panel__header" }, [createVNode("div", null, [createVNode("div", { class: "price-panel__eyebrow" }, " Актуальные цены "), createVNode("h2", { class: "price-panel__title" }, " Цены ")]), createVNode(VIcon, {
											icon: "mdi-cash-multiple",
											size: "34",
											class: "price-panel__icon"
										})]), createVNode(VRow, { dense: "" }, {
											default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(publicPrices.value, (price) => {
												return openBlock(), createBlock(VCol, {
													key: price.id,
													cols: "12",
													sm: "6"
												}, {
													default: withCtx(() => [createVNode("div", { class: "price-tile" }, [
														createVNode("div", { class: "price-tile__name" }, toDisplayString(price.price_type?.name || "Цена"), 1),
														createVNode("div", { class: "price-tile__value" }, [createTextVNode(toDisplayString(formatMoney(price.price_gross)) + " ", 1), createVNode("span", null, toDisplayString(price.currency?.code || price.price_type?.currency?.code || "RUB"), 1)]),
														createVNode("div", { class: "price-tile__note" }, " с НДС / кг ")
													])]),
													_: 2
												}, 1024);
											}), 128))]),
											_: 1
										})])) : createCommentVNode("", true),
										__props.good.description ? (openBlock(), createBlock("div", {
											key: 2,
											class: "text-body-1 mb-6",
											style: { "line-height": "1.8" }
										}, toDisplayString(__props.good.description), 1)) : (openBlock(), createBlock("div", {
											key: 3,
											class: "text-body-1 text-medium-emphasis mb-6"
										}, " Описание пока не заполнено. ")),
										seoIsActive.value && seo.value.short_seo_text ? (openBlock(), createBlock("div", {
											key: 4,
											class: "text-body-2 text-medium-emphasis mb-4",
											style: { "line-height": "1.7" }
										}, toDisplayString(seo.value.short_seo_text), 1)) : createCommentVNode("", true),
										createVNode("div", { class: "d-flex flex-wrap ga-3 mb-4" }, [
											createVNode(VBtn, {
												color: "deep-purple-darken-1",
												size: "large",
												rounded: "xl",
												onClick: requestPrice
											}, {
												default: withCtx(() => [createTextVNode(" Запросить цену ")]),
												_: 1
											}),
											createVNode(VBtn, {
												variant: "tonal",
												size: "large",
												rounded: "xl",
												href: "tel:+79650160001",
												onClick: clickPhone
											}, {
												default: withCtx(() => [createTextVNode(" Позвонить ")]),
												_: 1
											}),
											createVNode(VBtn, {
												variant: "tonal",
												size: "large",
												rounded: "xl",
												href: "mailto:office@180022.ru",
												onClick: clickEmail
											}, {
												default: withCtx(() => [createTextVNode(" Написать на email ")]),
												_: 1
											})
										]),
										createVNode(VAlert, {
											type: "info",
											variant: "tonal",
											class: "mb-4"
										}, {
											default: withCtx(() => [
												createTextVNode(" Для уточнения цены и условий поставки свяжитесь с нами: "),
												createVNode("strong", null, "+7-965-016-0001"),
												createTextVNode(", "),
												createVNode("strong", null, "office@180022.ru")
											]),
											_: 1
										})
									]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						if (otherVideoItems.value.length) _push(ssrRenderComponent(VRow, { class: "mt-8" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { cols: "12" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`<h2 class="text-h5 font-weight-bold mb-4" data-v-0622424e${_scopeId}> Другие видео товара </h2>`);
											else return [createVNode("h2", { class: "text-h5 font-weight-bold mb-4" }, " Другие видео товара ")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(`<!--[-->`);
									ssrRenderList(otherVideoItems.value, (video) => {
										_push(ssrRenderComponent(VCol, {
											key: video.id,
											cols: "12",
											md: "6"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VCard, { rounded: "xl" }, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(`<video class="product-video product-video--small" controls playsinline preload="metadata"${ssrRenderAttr("poster", video.poster_url || void 0)} data-v-0622424e${_scopeId}><source${ssrRenderAttr("src", mediaVideoUrl(video))} type="video/mp4" data-v-0622424e${_scopeId}> Ваш браузер не поддерживает video. </video>`);
															if (video.title || video.caption) _push(ssrRenderComponent(VCardText, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		if (video.title) _push(`<div class="font-weight-bold mb-1" data-v-0622424e${_scopeId}>${ssrInterpolate(video.title)}</div>`);
																		else _push(`<!---->`);
																		if (video.caption) _push(`<div class="text-body-2 text-medium-emphasis" data-v-0622424e${_scopeId}>${ssrInterpolate(video.caption)}</div>`);
																		else _push(`<!---->`);
																	} else return [video.title ? (openBlock(), createBlock("div", {
																		key: 0,
																		class: "font-weight-bold mb-1"
																	}, toDisplayString(video.title), 1)) : createCommentVNode("", true), video.caption ? (openBlock(), createBlock("div", {
																		key: 1,
																		class: "text-body-2 text-medium-emphasis"
																	}, toDisplayString(video.caption), 1)) : createCommentVNode("", true)];
																}),
																_: 2
															}, _parent, _scopeId));
															else _push(`<!---->`);
														} else return [createVNode("video", {
															class: "product-video product-video--small",
															controls: "",
															playsinline: "",
															preload: "metadata",
															poster: video.poster_url || void 0
														}, [createVNode("source", {
															src: mediaVideoUrl(video),
															type: "video/mp4"
														}, null, 8, ["src"]), createTextVNode(" Ваш браузер не поддерживает video. ")], 8, ["poster"]), video.title || video.caption ? (openBlock(), createBlock(VCardText, { key: 0 }, {
															default: withCtx(() => [video.title ? (openBlock(), createBlock("div", {
																key: 0,
																class: "font-weight-bold mb-1"
															}, toDisplayString(video.title), 1)) : createCommentVNode("", true), video.caption ? (openBlock(), createBlock("div", {
																key: 1,
																class: "text-body-2 text-medium-emphasis"
															}, toDisplayString(video.caption), 1)) : createCommentVNode("", true)]),
															_: 2
														}, 1024)) : createCommentVNode("", true)];
													}),
													_: 2
												}, _parent, _scopeId));
												else return [createVNode(VCard, { rounded: "xl" }, {
													default: withCtx(() => [createVNode("video", {
														class: "product-video product-video--small",
														controls: "",
														playsinline: "",
														preload: "metadata",
														poster: video.poster_url || void 0
													}, [createVNode("source", {
														src: mediaVideoUrl(video),
														type: "video/mp4"
													}, null, 8, ["src"]), createTextVNode(" Ваш браузер не поддерживает video. ")], 8, ["poster"]), video.title || video.caption ? (openBlock(), createBlock(VCardText, { key: 0 }, {
														default: withCtx(() => [video.title ? (openBlock(), createBlock("div", {
															key: 0,
															class: "font-weight-bold mb-1"
														}, toDisplayString(video.title), 1)) : createCommentVNode("", true), video.caption ? (openBlock(), createBlock("div", {
															key: 1,
															class: "text-body-2 text-medium-emphasis"
														}, toDisplayString(video.caption), 1)) : createCommentVNode("", true)]),
														_: 2
													}, 1024)) : createCommentVNode("", true)]),
													_: 2
												}, 1024)];
											}),
											_: 2
										}, _parent, _scopeId));
									});
									_push(`<!--]-->`);
								} else return [createVNode(VCol, { cols: "12" }, {
									default: withCtx(() => [createVNode("h2", { class: "text-h5 font-weight-bold mb-4" }, " Другие видео товара ")]),
									_: 1
								}), (openBlock(true), createBlock(Fragment, null, renderList(otherVideoItems.value, (video) => {
									return openBlock(), createBlock(VCol, {
										key: video.id,
										cols: "12",
										md: "6"
									}, {
										default: withCtx(() => [createVNode(VCard, { rounded: "xl" }, {
											default: withCtx(() => [createVNode("video", {
												class: "product-video product-video--small",
												controls: "",
												playsinline: "",
												preload: "metadata",
												poster: video.poster_url || void 0
											}, [createVNode("source", {
												src: mediaVideoUrl(video),
												type: "video/mp4"
											}, null, 8, ["src"]), createTextVNode(" Ваш браузер не поддерживает video. ")], 8, ["poster"]), video.title || video.caption ? (openBlock(), createBlock(VCardText, { key: 0 }, {
												default: withCtx(() => [video.title ? (openBlock(), createBlock("div", {
													key: 0,
													class: "font-weight-bold mb-1"
												}, toDisplayString(video.title), 1)) : createCommentVNode("", true), video.caption ? (openBlock(), createBlock("div", {
													key: 1,
													class: "text-body-2 text-medium-emphasis"
												}, toDisplayString(video.caption), 1)) : createCommentVNode("", true)]),
												_: 2
											}, 1024)) : createCommentVNode("", true)]),
											_: 2
										}, 1024)]),
										_: 2
									}, 1024);
								}), 128))];
							}),
							_: 1
						}, _parent, _scopeId));
						else _push(`<!---->`);
						if (seoIsActive.value && seo.value.seo_text) _push(ssrRenderComponent(VRow, { class: "mt-8" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VCard, {
											rounded: "xl",
											variant: "tonal"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) {
													_push(ssrRenderComponent(VCardTitle, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(` Подробнее о товаре `);
															else return [createTextVNode(" Подробнее о товаре ")];
														}),
														_: 1
													}, _parent, _scopeId));
													_push(ssrRenderComponent(VCardText, {
														class: "text-body-1",
														style: { "line-height": "1.8" }
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(`${ssrInterpolate(seo.value.seo_text)}`);
															else return [createTextVNode(toDisplayString(seo.value.seo_text), 1)];
														}),
														_: 1
													}, _parent, _scopeId));
												} else return [createVNode(VCardTitle, null, {
													default: withCtx(() => [createTextVNode(" Подробнее о товаре ")]),
													_: 1
												}), createVNode(VCardText, {
													class: "text-body-1",
													style: { "line-height": "1.8" }
												}, {
													default: withCtx(() => [createTextVNode(toDisplayString(seo.value.seo_text), 1)]),
													_: 1
												})];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VCard, {
											rounded: "xl",
											variant: "tonal"
										}, {
											default: withCtx(() => [createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode(" Подробнее о товаре ")]),
												_: 1
											}), createVNode(VCardText, {
												class: "text-body-1",
												style: { "line-height": "1.8" }
											}, {
												default: withCtx(() => [createTextVNode(toDisplayString(seo.value.seo_text), 1)]),
												_: 1
											})]),
											_: 1
										})];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCol, { cols: "12" }, {
									default: withCtx(() => [createVNode(VCard, {
										rounded: "xl",
										variant: "tonal"
									}, {
										default: withCtx(() => [createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode(" Подробнее о товаре ")]),
											_: 1
										}), createVNode(VCardText, {
											class: "text-body-1",
											style: { "line-height": "1.8" }
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(seo.value.seo_text), 1)]),
											_: 1
										})]),
										_: 1
									})]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						else _push(`<!---->`);
						if (__props.relatedGoods.length) _push(ssrRenderComponent(VRow, { class: "mt-8" }, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { cols: "12" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(`<h2 class="text-h5 font-weight-bold mb-4" data-v-0622424e${_scopeId}> Другие товары </h2>`);
											else return [createVNode("h2", { class: "text-h5 font-weight-bold mb-4" }, " Другие товары ")];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(`<!--[-->`);
									ssrRenderList(__props.relatedGoods, (item) => {
										_push(ssrRenderComponent(VCol, {
											key: item.id,
											cols: "12",
											sm: "6",
											md: "3"
										}, {
											default: withCtx((_, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(unref(Link), {
													href: route$1("public.goods.show", { good: item.slug }),
													class: "text-decoration-none"
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) _push(ssrRenderComponent(VCard, {
															rounded: "xl",
															class: "h-100 related-card"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	if (relatedImage(item)) _push(ssrRenderComponent(VImg, {
																		src: relatedImage(item),
																		height: "180",
																		cover: ""
																	}, null, _parent, _scopeId));
																	else {
																		_push(`<div class="related-empty" data-v-0622424e${_scopeId}>`);
																		_push(ssrRenderComponent(VIcon, {
																			icon: "mdi-image-off",
																			size: "42"
																		}, null, _parent, _scopeId));
																		_push(`</div>`);
																	}
																	_push(ssrRenderComponent(VCardText, null, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) {
																				_push(`<div class="font-weight-bold text-body-1" data-v-0622424e${_scopeId}>${ssrInterpolate(item.name)}</div>`);
																				if (item.description) _push(`<div class="text-caption text-medium-emphasis mt-1 related-description" data-v-0622424e${_scopeId}>${ssrInterpolate(item.description)}</div>`);
																				else _push(`<!---->`);
																			} else return [createVNode("div", { class: "font-weight-bold text-body-1" }, toDisplayString(item.name), 1), item.description ? (openBlock(), createBlock("div", {
																				key: 0,
																				class: "text-caption text-medium-emphasis mt-1 related-description"
																			}, toDisplayString(item.description), 1)) : createCommentVNode("", true)];
																		}),
																		_: 2
																	}, _parent, _scopeId));
																} else return [relatedImage(item) ? (openBlock(), createBlock(VImg, {
																	key: 0,
																	src: relatedImage(item),
																	height: "180",
																	cover: ""
																}, null, 8, ["src"])) : (openBlock(), createBlock("div", {
																	key: 1,
																	class: "related-empty"
																}, [createVNode(VIcon, {
																	icon: "mdi-image-off",
																	size: "42"
																})])), createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode("div", { class: "font-weight-bold text-body-1" }, toDisplayString(item.name), 1), item.description ? (openBlock(), createBlock("div", {
																		key: 0,
																		class: "text-caption text-medium-emphasis mt-1 related-description"
																	}, toDisplayString(item.description), 1)) : createCommentVNode("", true)]),
																	_: 2
																}, 1024)];
															}),
															_: 2
														}, _parent, _scopeId));
														else return [createVNode(VCard, {
															rounded: "xl",
															class: "h-100 related-card"
														}, {
															default: withCtx(() => [relatedImage(item) ? (openBlock(), createBlock(VImg, {
																key: 0,
																src: relatedImage(item),
																height: "180",
																cover: ""
															}, null, 8, ["src"])) : (openBlock(), createBlock("div", {
																key: 1,
																class: "related-empty"
															}, [createVNode(VIcon, {
																icon: "mdi-image-off",
																size: "42"
															})])), createVNode(VCardText, null, {
																default: withCtx(() => [createVNode("div", { class: "font-weight-bold text-body-1" }, toDisplayString(item.name), 1), item.description ? (openBlock(), createBlock("div", {
																	key: 0,
																	class: "text-caption text-medium-emphasis mt-1 related-description"
																}, toDisplayString(item.description), 1)) : createCommentVNode("", true)]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024)];
													}),
													_: 2
												}, _parent, _scopeId));
												else return [createVNode(unref(Link), {
													href: route$1("public.goods.show", { good: item.slug }),
													class: "text-decoration-none"
												}, {
													default: withCtx(() => [createVNode(VCard, {
														rounded: "xl",
														class: "h-100 related-card"
													}, {
														default: withCtx(() => [relatedImage(item) ? (openBlock(), createBlock(VImg, {
															key: 0,
															src: relatedImage(item),
															height: "180",
															cover: ""
														}, null, 8, ["src"])) : (openBlock(), createBlock("div", {
															key: 1,
															class: "related-empty"
														}, [createVNode(VIcon, {
															icon: "mdi-image-off",
															size: "42"
														})])), createVNode(VCardText, null, {
															default: withCtx(() => [createVNode("div", { class: "font-weight-bold text-body-1" }, toDisplayString(item.name), 1), item.description ? (openBlock(), createBlock("div", {
																key: 0,
																class: "text-caption text-medium-emphasis mt-1 related-description"
															}, toDisplayString(item.description), 1)) : createCommentVNode("", true)]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1024)]),
													_: 2
												}, 1032, ["href"])];
											}),
											_: 2
										}, _parent, _scopeId));
									});
									_push(`<!--]-->`);
								} else return [createVNode(VCol, { cols: "12" }, {
									default: withCtx(() => [createVNode("h2", { class: "text-h5 font-weight-bold mb-4" }, " Другие товары ")]),
									_: 1
								}), (openBlock(true), createBlock(Fragment, null, renderList(__props.relatedGoods, (item) => {
									return openBlock(), createBlock(VCol, {
										key: item.id,
										cols: "12",
										sm: "6",
										md: "3"
									}, {
										default: withCtx(() => [createVNode(unref(Link), {
											href: route$1("public.goods.show", { good: item.slug }),
											class: "text-decoration-none"
										}, {
											default: withCtx(() => [createVNode(VCard, {
												rounded: "xl",
												class: "h-100 related-card"
											}, {
												default: withCtx(() => [relatedImage(item) ? (openBlock(), createBlock(VImg, {
													key: 0,
													src: relatedImage(item),
													height: "180",
													cover: ""
												}, null, 8, ["src"])) : (openBlock(), createBlock("div", {
													key: 1,
													class: "related-empty"
												}, [createVNode(VIcon, {
													icon: "mdi-image-off",
													size: "42"
												})])), createVNode(VCardText, null, {
													default: withCtx(() => [createVNode("div", { class: "font-weight-bold text-body-1" }, toDisplayString(item.name), 1), item.description ? (openBlock(), createBlock("div", {
														key: 0,
														class: "text-caption text-medium-emphasis mt-1 related-description"
													}, toDisplayString(item.description), 1)) : createCommentVNode("", true)]),
													_: 2
												}, 1024)]),
												_: 2
											}, 1024)]),
											_: 2
										}, 1032, ["href"])]),
										_: 2
									}, 1024);
								}), 128))];
							}),
							_: 1
						}, _parent, _scopeId));
						else _push(`<!---->`);
					} else return [
						createVNode(VRow, null, {
							default: withCtx(() => [createVNode(VCol, {
								cols: "12",
								md: "5"
							}, {
								default: withCtx(() => [
									mainVideo.value ? (openBlock(), createBlock(VCard, {
										key: 0,
										rounded: "xl",
										elevation: "2",
										class: "overflow-hidden mb-4 hero-video-card"
									}, {
										default: withCtx(() => [createVNode("video", {
											class: "product-hero-video",
											autoplay: "",
											muted: "",
											loop: "",
											playsinline: "",
											preload: "metadata",
											disablepictureinpicture: "",
											controlslist: "nodownload noplaybackrate nofullscreen",
											poster: mainVideo.value.poster_url || void 0,
											"aria-label": mainVideo.value.title || __props.good.name
										}, [createVNode("source", {
											src: mediaVideoUrl(mainVideo.value),
											type: "video/mp4"
										}, null, 8, ["src"]), createTextVNode(" Ваш браузер не поддерживает video. ")], 8, ["poster", "aria-label"]), createVNode("div", { class: "video-overlay" }, [createVNode(VChip, {
											size: "small",
											color: "black",
											variant: "flat",
											class: "text-white"
										}, {
											default: withCtx(() => [createVNode(VIcon, {
												icon: "mdi-play-circle",
												size: "16",
												class: "mr-1"
											}), createTextVNode(" Видео товара ")]),
											_: 1
										})])]),
										_: 1
									})) : createCommentVNode("", true),
									createVNode(VCard, {
										rounded: "xl",
										elevation: "2",
										class: "overflow-hidden"
									}, {
										default: withCtx(() => [activeImage.value ? (openBlock(), createBlock(VImg, {
											key: 0,
											src: activeImage.value.url,
											alt: activeImage.value.alt || __props.good.name,
											height: "420",
											cover: ""
										}, null, 8, ["src", "alt"])) : fallbackImage.value ? (openBlock(), createBlock(VImg, {
											key: 1,
											src: fallbackImage.value,
											alt: __props.good.name,
											height: "420",
											cover: ""
										}, null, 8, ["src", "alt"])) : (openBlock(), createBlock("div", {
											key: 2,
											class: "empty-preview"
										}, [createVNode(VIcon, {
											icon: "mdi-image-off",
											size: "64",
											class: "mb-3"
										}), createVNode("div", null, "Изображение пока не добавлено")]))]),
										_: 1
									}),
									imageItems.value.length ? (openBlock(), createBlock(VRow, {
										key: 1,
										class: "mt-3",
										dense: ""
									}, {
										default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(imageItems.value, (item) => {
											return openBlock(), createBlock(VCol, {
												key: item.id,
												cols: "3"
											}, {
												default: withCtx(() => [createVNode(VCard, {
													class: ["media-thumb", { "media-thumb--active": activeImage.value?.id === item.id }],
													rounded: "lg",
													variant: "tonal",
													onClick: ($event) => activeImageId.value = item.id
												}, {
													default: withCtx(() => [createVNode(VImg, {
														src: mediaPreview(item),
														height: "84",
														cover: ""
													}, null, 8, ["src"])]),
													_: 2
												}, 1032, ["class", "onClick"])]),
												_: 2
											}, 1024);
										}), 128))]),
										_: 1
									})) : createCommentVNode("", true)
								]),
								_: 1
							}), createVNode(VCol, {
								cols: "12",
								md: "7"
							}, {
								default: withCtx(() => [
									productItems.value.length ? (openBlock(), createBlock("div", {
										key: 0,
										class: "mb-4 product-links"
									}, [(openBlock(true), createBlock(Fragment, null, renderList(productItems.value, (product) => {
										return openBlock(), createBlock(unref(Link), {
											key: product.id,
											href: route$1("shop.products.show", { product: product.id }),
											class: "text-decoration-none d-inline-block mr-2 mb-2"
										}, {
											default: withCtx(() => [createVNode(VChip, {
												size: "small",
												class: "product-link-chip"
											}, {
												default: withCtx(() => [createVNode(VIcon, {
													icon: "mdi-leaf",
													size: "15",
													class: "mr-1"
												}), createTextVNode(" " + toDisplayString(product.title), 1)]),
												_: 2
											}, 1024)]),
											_: 2
										}, 1032, ["href"]);
									}), 128))])) : createCommentVNode("", true),
									createVNode("h1", { class: "text-h3 font-weight-bold mb-4" }, toDisplayString(pageH1.value), 1),
									publicPrices.value.length ? (openBlock(), createBlock("section", {
										key: 1,
										class: "price-panel mb-5"
									}, [createVNode("div", { class: "price-panel__header" }, [createVNode("div", null, [createVNode("div", { class: "price-panel__eyebrow" }, " Актуальные цены "), createVNode("h2", { class: "price-panel__title" }, " Цены ")]), createVNode(VIcon, {
										icon: "mdi-cash-multiple",
										size: "34",
										class: "price-panel__icon"
									})]), createVNode(VRow, { dense: "" }, {
										default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(publicPrices.value, (price) => {
											return openBlock(), createBlock(VCol, {
												key: price.id,
												cols: "12",
												sm: "6"
											}, {
												default: withCtx(() => [createVNode("div", { class: "price-tile" }, [
													createVNode("div", { class: "price-tile__name" }, toDisplayString(price.price_type?.name || "Цена"), 1),
													createVNode("div", { class: "price-tile__value" }, [createTextVNode(toDisplayString(formatMoney(price.price_gross)) + " ", 1), createVNode("span", null, toDisplayString(price.currency?.code || price.price_type?.currency?.code || "RUB"), 1)]),
													createVNode("div", { class: "price-tile__note" }, " с НДС / кг ")
												])]),
												_: 2
											}, 1024);
										}), 128))]),
										_: 1
									})])) : createCommentVNode("", true),
									__props.good.description ? (openBlock(), createBlock("div", {
										key: 2,
										class: "text-body-1 mb-6",
										style: { "line-height": "1.8" }
									}, toDisplayString(__props.good.description), 1)) : (openBlock(), createBlock("div", {
										key: 3,
										class: "text-body-1 text-medium-emphasis mb-6"
									}, " Описание пока не заполнено. ")),
									seoIsActive.value && seo.value.short_seo_text ? (openBlock(), createBlock("div", {
										key: 4,
										class: "text-body-2 text-medium-emphasis mb-4",
										style: { "line-height": "1.7" }
									}, toDisplayString(seo.value.short_seo_text), 1)) : createCommentVNode("", true),
									createVNode("div", { class: "d-flex flex-wrap ga-3 mb-4" }, [
										createVNode(VBtn, {
											color: "deep-purple-darken-1",
											size: "large",
											rounded: "xl",
											onClick: requestPrice
										}, {
											default: withCtx(() => [createTextVNode(" Запросить цену ")]),
											_: 1
										}),
										createVNode(VBtn, {
											variant: "tonal",
											size: "large",
											rounded: "xl",
											href: "tel:+79650160001",
											onClick: clickPhone
										}, {
											default: withCtx(() => [createTextVNode(" Позвонить ")]),
											_: 1
										}),
										createVNode(VBtn, {
											variant: "tonal",
											size: "large",
											rounded: "xl",
											href: "mailto:office@180022.ru",
											onClick: clickEmail
										}, {
											default: withCtx(() => [createTextVNode(" Написать на email ")]),
											_: 1
										})
									]),
									createVNode(VAlert, {
										type: "info",
										variant: "tonal",
										class: "mb-4"
									}, {
										default: withCtx(() => [
											createTextVNode(" Для уточнения цены и условий поставки свяжитесь с нами: "),
											createVNode("strong", null, "+7-965-016-0001"),
											createTextVNode(", "),
											createVNode("strong", null, "office@180022.ru")
										]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}),
						otherVideoItems.value.length ? (openBlock(), createBlock(VRow, {
							key: 0,
							class: "mt-8"
						}, {
							default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
								default: withCtx(() => [createVNode("h2", { class: "text-h5 font-weight-bold mb-4" }, " Другие видео товара ")]),
								_: 1
							}), (openBlock(true), createBlock(Fragment, null, renderList(otherVideoItems.value, (video) => {
								return openBlock(), createBlock(VCol, {
									key: video.id,
									cols: "12",
									md: "6"
								}, {
									default: withCtx(() => [createVNode(VCard, { rounded: "xl" }, {
										default: withCtx(() => [createVNode("video", {
											class: "product-video product-video--small",
											controls: "",
											playsinline: "",
											preload: "metadata",
											poster: video.poster_url || void 0
										}, [createVNode("source", {
											src: mediaVideoUrl(video),
											type: "video/mp4"
										}, null, 8, ["src"]), createTextVNode(" Ваш браузер не поддерживает video. ")], 8, ["poster"]), video.title || video.caption ? (openBlock(), createBlock(VCardText, { key: 0 }, {
											default: withCtx(() => [video.title ? (openBlock(), createBlock("div", {
												key: 0,
												class: "font-weight-bold mb-1"
											}, toDisplayString(video.title), 1)) : createCommentVNode("", true), video.caption ? (openBlock(), createBlock("div", {
												key: 1,
												class: "text-body-2 text-medium-emphasis"
											}, toDisplayString(video.caption), 1)) : createCommentVNode("", true)]),
											_: 2
										}, 1024)) : createCommentVNode("", true)]),
										_: 2
									}, 1024)]),
									_: 2
								}, 1024);
							}), 128))]),
							_: 1
						})) : createCommentVNode("", true),
						seoIsActive.value && seo.value.seo_text ? (openBlock(), createBlock(VRow, {
							key: 1,
							class: "mt-8"
						}, {
							default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
								default: withCtx(() => [createVNode(VCard, {
									rounded: "xl",
									variant: "tonal"
								}, {
									default: withCtx(() => [createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode(" Подробнее о товаре ")]),
										_: 1
									}), createVNode(VCardText, {
										class: "text-body-1",
										style: { "line-height": "1.8" }
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(seo.value.seo_text), 1)]),
										_: 1
									})]),
									_: 1
								})]),
								_: 1
							})]),
							_: 1
						})) : createCommentVNode("", true),
						__props.relatedGoods.length ? (openBlock(), createBlock(VRow, {
							key: 2,
							class: "mt-8"
						}, {
							default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
								default: withCtx(() => [createVNode("h2", { class: "text-h5 font-weight-bold mb-4" }, " Другие товары ")]),
								_: 1
							}), (openBlock(true), createBlock(Fragment, null, renderList(__props.relatedGoods, (item) => {
								return openBlock(), createBlock(VCol, {
									key: item.id,
									cols: "12",
									sm: "6",
									md: "3"
								}, {
									default: withCtx(() => [createVNode(unref(Link), {
										href: route$1("public.goods.show", { good: item.slug }),
										class: "text-decoration-none"
									}, {
										default: withCtx(() => [createVNode(VCard, {
											rounded: "xl",
											class: "h-100 related-card"
										}, {
											default: withCtx(() => [relatedImage(item) ? (openBlock(), createBlock(VImg, {
												key: 0,
												src: relatedImage(item),
												height: "180",
												cover: ""
											}, null, 8, ["src"])) : (openBlock(), createBlock("div", {
												key: 1,
												class: "related-empty"
											}, [createVNode(VIcon, {
												icon: "mdi-image-off",
												size: "42"
											})])), createVNode(VCardText, null, {
												default: withCtx(() => [createVNode("div", { class: "font-weight-bold text-body-1" }, toDisplayString(item.name), 1), item.description ? (openBlock(), createBlock("div", {
													key: 0,
													class: "text-caption text-medium-emphasis mt-1 related-description"
												}, toDisplayString(item.description), 1)) : createCommentVNode("", true)]),
												_: 2
											}, 1024)]),
											_: 2
										}, 1024)]),
										_: 2
									}, 1032, ["href"])]),
									_: 2
								}, 1024);
							}), 128))]),
							_: 1
						})) : createCommentVNode("", true)
					];
				}),
				_: 1
			}, _parent));
			_push(`<!--]-->`);
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Goods/Show.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var Show_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-0622424e"]]);
//#endregion
export { Show_default as default };

//# sourceMappingURL=Show-DV03C9gT.js.map