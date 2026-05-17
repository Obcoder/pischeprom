import { C as VRow, K as VDivider, L as VCardSubtitle, P as VCard, R as VCardActions, T as VContainer, U as VTextField, lt as VImg, rt as VBtn, w as VCol } from "../ssr.js";
import "./consts-BmbOcoGu.js";
import { t as LayoutDefault_default } from "./LayoutDefault-CXC0Lg2S.js";
import axios from "axios";
import { Fragment, createBlock, createTextVNode, createVNode, isRef, mergeProps, onMounted, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Goods.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: LayoutDefault_default }, {
	__name: "Goods",
	__ssrInlineRender: true,
	setup(__props) {
		let goods = ref();
		function indexGoods(like) {
			axios.get(route("goods.index"), { params: { search: like } }).then(function(response) {
				goods.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let searchGoods = ref();
		onMounted(() => {
			indexGoods();
		});
		useHead({
			title: `Товары, которые Вы можете приобрести на ПИЩЕПРОМ-СЕРВЕРЕ: пищевое сырьё, пищевые ингредиенты, пищевые добавки`,
			meta: [{
				name: "description",
				content: `Товары, которые Вы можете приобрести на ПИЩЕПРОМ-СЕРВЕРЕ: пищевое сырьё, пищевые ингредиенты, пищевые добавки`
			}]
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, null, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VTextField, {
												modelValue: unref(searchGoods),
												"onUpdate:modelValue": ($event) => isRef(searchGoods) ? searchGoods.value = $event : searchGoods = $event,
												onInput: ($event) => indexGoods(unref(searchGoods)),
												density: "compact",
												variant: "outlined",
												label: "Поиск по товарам",
												placeholder: "вводите название товара"
											}, null, _parent, _scopeId));
											else return [createVNode(VTextField, {
												modelValue: unref(searchGoods),
												"onUpdate:modelValue": ($event) => isRef(searchGoods) ? searchGoods.value = $event : searchGoods = $event,
												onInput: ($event) => indexGoods(unref(searchGoods)),
												density: "compact",
												variant: "outlined",
												label: "Поиск по товарам",
												placeholder: "вводите название товара"
											}, null, 8, [
												"modelValue",
												"onUpdate:modelValue",
												"onInput"
											])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
								} else return [
									createVNode(VCol, null, {
										default: withCtx(() => [createVNode(VTextField, {
											modelValue: unref(searchGoods),
											"onUpdate:modelValue": ($event) => isRef(searchGoods) ? searchGoods.value = $event : searchGoods = $event,
											onInput: ($event) => indexGoods(unref(searchGoods)),
											density: "compact",
											variant: "outlined",
											label: "Поиск по товарам",
											placeholder: "вводите название товара"
										}, null, 8, [
											"modelValue",
											"onUpdate:modelValue",
											"onInput"
										])]),
										_: 1
									}),
									createVNode(VCol),
									createVNode(VCol)
								];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCol, {
									cols: "12",
									class: "flex flex-row flex-wrap"
								}, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<!--[-->`);
											ssrRenderList(unref(goods), (good) => {
												_push(ssrRenderComponent(VCard, {
													key: good.id,
													class: "w-72 mr-2 mb-2",
													rounded: ""
												}, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VImg, { src: good.ava_image || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg") }, null, _parent, _scopeId));
															_push(ssrRenderComponent(VCardSubtitle, { class: "font-sans text-wrap" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`${ssrInterpolate(good.name)}`);
																	else return [createTextVNode(toDisplayString(good.name), 1)];
																}),
																_: 2
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCardActions, null, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) {
																		_push(ssrRenderComponent(VBtn, {
																			text: "заказать",
																			density: "comfortable",
																			class: "ms-2",
																			variant: "tonal"
																		}, null, _parent, _scopeId));
																		_push(ssrRenderComponent(VDivider, {
																			opacity: "80",
																			color: "grey",
																			vertical: ""
																		}, null, _parent, _scopeId));
																		_push(ssrRenderComponent(VBtn, {
																			text: "подробнее",
																			density: "comfortable",
																			class: "ms-2",
																			variant: "flat"
																		}, null, _parent, _scopeId));
																	} else return [
																		createVNode(VBtn, {
																			text: "заказать",
																			density: "comfortable",
																			class: "ms-2",
																			variant: "tonal"
																		}),
																		createVNode(VDivider, {
																			opacity: "80",
																			color: "grey",
																			vertical: ""
																		}),
																		createVNode(VBtn, {
																			text: "подробнее",
																			density: "comfortable",
																			class: "ms-2",
																			variant: "flat"
																		})
																	];
																}),
																_: 2
															}, _parent, _scopeId));
														} else return [
															createVNode(VImg, { src: good.ava_image || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg") }, null, 8, ["src"]),
															createVNode(VCardSubtitle, { class: "font-sans text-wrap" }, {
																default: withCtx(() => [createTextVNode(toDisplayString(good.name), 1)]),
																_: 2
															}, 1024),
															createVNode(VCardActions, null, {
																default: withCtx(() => [
																	createVNode(VBtn, {
																		text: "заказать",
																		density: "comfortable",
																		class: "ms-2",
																		variant: "tonal"
																	}),
																	createVNode(VDivider, {
																		opacity: "80",
																		color: "grey",
																		vertical: ""
																	}),
																	createVNode(VBtn, {
																		text: "подробнее",
																		density: "comfortable",
																		class: "ms-2",
																		variant: "flat"
																	})
																]),
																_: 1
															})
														];
													}),
													_: 2
												}, _parent, _scopeId));
											});
											_push(`<!--]-->`);
										} else return [(openBlock(true), createBlock(Fragment, null, renderList(unref(goods), (good) => {
											return openBlock(), createBlock(VCard, {
												key: good.id,
												class: "w-72 mr-2 mb-2",
												rounded: ""
											}, {
												default: withCtx(() => [
													createVNode(VImg, { src: good.ava_image || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg") }, null, 8, ["src"]),
													createVNode(VCardSubtitle, { class: "font-sans text-wrap" }, {
														default: withCtx(() => [createTextVNode(toDisplayString(good.name), 1)]),
														_: 2
													}, 1024),
													createVNode(VCardActions, null, {
														default: withCtx(() => [
															createVNode(VBtn, {
																text: "заказать",
																density: "comfortable",
																class: "ms-2",
																variant: "tonal"
															}),
															createVNode(VDivider, {
																opacity: "80",
																color: "grey",
																vertical: ""
															}),
															createVNode(VBtn, {
																text: "подробнее",
																density: "comfortable",
																class: "ms-2",
																variant: "flat"
															})
														]),
														_: 1
													})
												]),
												_: 2
											}, 1024);
										}), 128))];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCol, {
									cols: "12",
									class: "flex flex-row flex-wrap"
								}, {
									default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(goods), (good) => {
										return openBlock(), createBlock(VCard, {
											key: good.id,
											class: "w-72 mr-2 mb-2",
											rounded: ""
										}, {
											default: withCtx(() => [
												createVNode(VImg, { src: good.ava_image || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg") }, null, 8, ["src"]),
												createVNode(VCardSubtitle, { class: "font-sans text-wrap" }, {
													default: withCtx(() => [createTextVNode(toDisplayString(good.name), 1)]),
													_: 2
												}, 1024),
												createVNode(VCardActions, null, {
													default: withCtx(() => [
														createVNode(VBtn, {
															text: "заказать",
															density: "comfortable",
															class: "ms-2",
															variant: "tonal"
														}),
														createVNode(VDivider, {
															opacity: "80",
															color: "grey",
															vertical: ""
														}),
														createVNode(VBtn, {
															text: "подробнее",
															density: "comfortable",
															class: "ms-2",
															variant: "flat"
														})
													]),
													_: 1
												})
											]),
											_: 2
										}, 1024);
									}), 128))]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol, null, {
								default: withCtx(() => [createVNode(VTextField, {
									modelValue: unref(searchGoods),
									"onUpdate:modelValue": ($event) => isRef(searchGoods) ? searchGoods.value = $event : searchGoods = $event,
									onInput: ($event) => indexGoods(unref(searchGoods)),
									density: "compact",
									variant: "outlined",
									label: "Поиск по товарам",
									placeholder: "вводите название товара"
								}, null, 8, [
									"modelValue",
									"onUpdate:modelValue",
									"onInput"
								])]),
								_: 1
							}),
							createVNode(VCol),
							createVNode(VCol)
						]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, {
							cols: "12",
							class: "flex flex-row flex-wrap"
						}, {
							default: withCtx(() => [(openBlock(true), createBlock(Fragment, null, renderList(unref(goods), (good) => {
								return openBlock(), createBlock(VCard, {
									key: good.id,
									class: "w-72 mr-2 mb-2",
									rounded: ""
								}, {
									default: withCtx(() => [
										createVNode(VImg, { src: good.ava_image || unref("https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg") }, null, 8, ["src"]),
										createVNode(VCardSubtitle, { class: "font-sans text-wrap" }, {
											default: withCtx(() => [createTextVNode(toDisplayString(good.name), 1)]),
											_: 2
										}, 1024),
										createVNode(VCardActions, null, {
											default: withCtx(() => [
												createVNode(VBtn, {
													text: "заказать",
													density: "comfortable",
													class: "ms-2",
													variant: "tonal"
												}),
												createVNode(VDivider, {
													opacity: "80",
													color: "grey",
													vertical: ""
												}),
												createVNode(VBtn, {
													text: "подробнее",
													density: "comfortable",
													class: "ms-2",
													variant: "flat"
												})
											]),
											_: 1
										})
									]),
									_: 2
								}, 1024);
							}), 128))]),
							_: 1
						})]),
						_: 1
					})];
				}),
				_: 1
			}, _parent));
		};
	}
});
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Goods.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Goods-CjqmagkI.js.map