import { C as VRow, D as VDataTable, F as VCardText, K as VDivider, P as VCard, R as VCardActions, S as VSpacer, T as VContainer, U as VTextField, h as VForm, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { Link, useForm } from "@inertiajs/vue3";
import { createTextVNode, createVNode, isRef, mergeProps, onMounted, ref, toDisplayString, unref, useSSRContext, withCtx, withModifiers } from "vue";
import { ssrInterpolate, ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Ameise/Products.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Products",
	__ssrInlineRender: true,
	setup(__props) {
		let products = ref();
		let searchProducts = ref();
		function indexProducts() {
			axios.get(route("products.index")).then(function(response) {
				products.value = response.data;
			}).catch(function(error) {
				console.log(error);
			}).finally(function() {});
		}
		const headersProducts = [{
			title: "rus",
			key: "rus"
		}, {
			title: "eng",
			key: "eng"
		}];
		let showFormProduct = ref(false);
		const formProduct = useForm({
			rus: null,
			eng: null,
			zh: null,
			es: null
		});
		function storeProduct() {
			formProduct.post(route("products.store"), {
				replace: false,
				preserveState: true,
				preserveScroll: true,
				onSuccess: () => {
					formProduct.reset();
					indexProducts(searchProducts);
				}
			});
		}
		onMounted(() => {
			indexProducts();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VRow, null, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) _push(ssrRenderComponent(VCol, null, {
								default: withCtx((_, _push, _parent, _scopeId) => {
									if (_push) _push(ssrRenderComponent(VCard, {
										flat: "",
										color: "grey"
									}, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VDataTable, {
												headers: headersProducts,
												items: unref(products),
												search: unref(searchProducts),
												"items-per-page": "124",
												density: "compact",
												hover: "hover"
											}, {
												top: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(VRow, null, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) {
																_push(ssrRenderComponent(VCol, { cols: "9" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VTextField, {
																			class: "py-2",
																			modelValue: unref(searchProducts),
																			"onUpdate:modelValue": ($event) => isRef(searchProducts) ? searchProducts.value = $event : searchProducts = $event,
																			label: "Filter products",
																			"prepend-inner-icon": "mdi-magnify",
																			variant: "outlined",
																			clearable: ""
																		}, null, _parent, _scopeId));
																		else return [createVNode(VTextField, {
																			class: "py-2",
																			modelValue: unref(searchProducts),
																			"onUpdate:modelValue": ($event) => isRef(searchProducts) ? searchProducts.value = $event : searchProducts = $event,
																			label: "Filter products",
																			"prepend-inner-icon": "mdi-magnify",
																			variant: "outlined",
																			clearable: ""
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
																_push(ssrRenderComponent(VCol, { cols: "3" }, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VDialog, {
																			modelValue: unref(showFormProduct),
																			"onUpdate:modelValue": ($event) => isRef(showFormProduct) ? showFormProduct.value = $event : showFormProduct = $event,
																			"max-width": "500px"
																		}, {
																			activator: withCtx(({ props }, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VBtn, mergeProps({
																					class: "mb-2",
																					color: "primary",
																					dark: ""
																				}, props), {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) _push(` New Item `);
																						else return [createTextVNode(" New Item ")];
																					}),
																					_: 2
																				}, _parent, _scopeId));
																				else return [createVNode(VBtn, mergeProps({
																					class: "mb-2",
																					color: "primary",
																					dark: ""
																				}, props), {
																					default: withCtx(() => [createTextVNode(" New Item ")]),
																					_: 1
																				}, 16)];
																			}),
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(ssrRenderComponent(VCard, null, {
																					default: withCtx((_, _push, _parent, _scopeId) => {
																						if (_push) {
																							_push(ssrRenderComponent(VCardText, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) _push(ssrRenderComponent(VForm, { onSubmit: () => {} }, {
																										default: withCtx((_, _push, _parent, _scopeId) => {
																											if (_push) {
																												_push(ssrRenderComponent(VRow, null, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) _push(ssrRenderComponent(VTextField, {
																															modelValue: unref(formProduct).rus,
																															"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																															label: "Product"
																														}, null, _parent, _scopeId));
																														else return [createVNode(VTextField, {
																															modelValue: unref(formProduct).rus,
																															"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																															label: "Product"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																													}),
																													_: 1
																												}, _parent, _scopeId));
																												_push(ssrRenderComponent(VRow, null, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) _push(ssrRenderComponent(VTextField, {
																															modelValue: unref(formProduct).eng,
																															"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																															label: "Product eng"
																														}, null, _parent, _scopeId));
																														else return [createVNode(VTextField, {
																															modelValue: unref(formProduct).eng,
																															"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																															label: "Product eng"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																													}),
																													_: 1
																												}, _parent, _scopeId));
																												_push(ssrRenderComponent(VRow, null, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) _push(ssrRenderComponent(VTextField, {
																															modelValue: unref(formProduct).zh,
																															"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																															label: "Product zh"
																														}, null, _parent, _scopeId));
																														else return [createVNode(VTextField, {
																															modelValue: unref(formProduct).zh,
																															"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																															label: "Product zh"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																													}),
																													_: 1
																												}, _parent, _scopeId));
																												_push(ssrRenderComponent(VRow, null, {
																													default: withCtx((_, _push, _parent, _scopeId) => {
																														if (_push) _push(ssrRenderComponent(VTextField, {
																															modelValue: unref(formProduct).es,
																															"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																															label: "Product es"
																														}, null, _parent, _scopeId));
																														else return [createVNode(VTextField, {
																															modelValue: unref(formProduct).es,
																															"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																															label: "Product es"
																														}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																													}),
																													_: 1
																												}, _parent, _scopeId));
																											} else return [
																												createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formProduct).rus,
																														"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																														label: "Product"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												}),
																												createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formProduct).eng,
																														"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																														label: "Product eng"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												}),
																												createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formProduct).zh,
																														"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																														label: "Product zh"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												}),
																												createVNode(VRow, null, {
																													default: withCtx(() => [createVNode(VTextField, {
																														modelValue: unref(formProduct).es,
																														"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																														label: "Product es"
																													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																													_: 1
																												})
																											];
																										}),
																										_: 1
																									}, _parent, _scopeId));
																									else return [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																										default: withCtx(() => [
																											createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formProduct).rus,
																													"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																													label: "Product"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											}),
																											createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formProduct).eng,
																													"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																													label: "Product eng"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											}),
																											createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formProduct).zh,
																													"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																													label: "Product zh"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											}),
																											createVNode(VRow, null, {
																												default: withCtx(() => [createVNode(VTextField, {
																													modelValue: unref(formProduct).es,
																													"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																													label: "Product es"
																												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																												_: 1
																											})
																										]),
																										_: 1
																									}, 8, ["onSubmit"])];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																							_push(ssrRenderComponent(VCardActions, null, {
																								default: withCtx((_, _push, _parent, _scopeId) => {
																									if (_push) {
																										_push(ssrRenderComponent(VSpacer, null, null, _parent, _scopeId));
																										_push(ssrRenderComponent(VBtn, {
																											color: "blue-darken-1",
																											variant: "text",
																											onClick: _ctx.close
																										}, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(` Cancel `);
																												else return [createTextVNode(" Cancel ")];
																											}),
																											_: 1
																										}, _parent, _scopeId));
																										_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
																										_push(ssrRenderComponent(VBtn, {
																											color: "blue-darken-1",
																											variant: "text",
																											onClick: storeProduct
																										}, {
																											default: withCtx((_, _push, _parent, _scopeId) => {
																												if (_push) _push(` Store `);
																												else return [createTextVNode(" Store ")];
																											}),
																											_: 1
																										}, _parent, _scopeId));
																									} else return [
																										createVNode(VSpacer),
																										createVNode(VBtn, {
																											color: "blue-darken-1",
																											variant: "text",
																											onClick: _ctx.close
																										}, {
																											default: withCtx(() => [createTextVNode(" Cancel ")]),
																											_: 1
																										}, 8, ["onClick"]),
																										createVNode(VDivider),
																										createVNode(VBtn, {
																											color: "blue-darken-1",
																											variant: "text",
																											onClick: storeProduct
																										}, {
																											default: withCtx(() => [createTextVNode(" Store ")]),
																											_: 1
																										})
																									];
																								}),
																								_: 1
																							}, _parent, _scopeId));
																						} else return [createVNode(VCardText, null, {
																							default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																								default: withCtx(() => [
																									createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formProduct).rus,
																											"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																											label: "Product"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									}),
																									createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formProduct).eng,
																											"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																											label: "Product eng"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									}),
																									createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formProduct).zh,
																											"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																											label: "Product zh"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									}),
																									createVNode(VRow, null, {
																										default: withCtx(() => [createVNode(VTextField, {
																											modelValue: unref(formProduct).es,
																											"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																											label: "Product es"
																										}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																										_: 1
																									})
																								]),
																								_: 1
																							}, 8, ["onSubmit"])]),
																							_: 1
																						}), createVNode(VCardActions, null, {
																							default: withCtx(() => [
																								createVNode(VSpacer),
																								createVNode(VBtn, {
																									color: "blue-darken-1",
																									variant: "text",
																									onClick: _ctx.close
																								}, {
																									default: withCtx(() => [createTextVNode(" Cancel ")]),
																									_: 1
																								}, 8, ["onClick"]),
																								createVNode(VDivider),
																								createVNode(VBtn, {
																									color: "blue-darken-1",
																									variant: "text",
																									onClick: storeProduct
																								}, {
																									default: withCtx(() => [createTextVNode(" Store ")]),
																									_: 1
																								})
																							]),
																							_: 1
																						})];
																					}),
																					_: 1
																				}, _parent, _scopeId));
																				else return [createVNode(VCard, null, {
																					default: withCtx(() => [createVNode(VCardText, null, {
																						default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																							default: withCtx(() => [
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formProduct).rus,
																										"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																										label: "Product"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}),
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formProduct).eng,
																										"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																										label: "Product eng"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}),
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formProduct).zh,
																										"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																										label: "Product zh"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								}),
																								createVNode(VRow, null, {
																									default: withCtx(() => [createVNode(VTextField, {
																										modelValue: unref(formProduct).es,
																										"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																										label: "Product es"
																									}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																									_: 1
																								})
																							]),
																							_: 1
																						}, 8, ["onSubmit"])]),
																						_: 1
																					}), createVNode(VCardActions, null, {
																						default: withCtx(() => [
																							createVNode(VSpacer),
																							createVNode(VBtn, {
																								color: "blue-darken-1",
																								variant: "text",
																								onClick: _ctx.close
																							}, {
																								default: withCtx(() => [createTextVNode(" Cancel ")]),
																								_: 1
																							}, 8, ["onClick"]),
																							createVNode(VDivider),
																							createVNode(VBtn, {
																								color: "blue-darken-1",
																								variant: "text",
																								onClick: storeProduct
																							}, {
																								default: withCtx(() => [createTextVNode(" Store ")]),
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
																		else return [createVNode(VDialog, {
																			modelValue: unref(showFormProduct),
																			"onUpdate:modelValue": ($event) => isRef(showFormProduct) ? showFormProduct.value = $event : showFormProduct = $event,
																			"max-width": "500px"
																		}, {
																			activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps({
																				class: "mb-2",
																				color: "primary",
																				dark: ""
																			}, props), {
																				default: withCtx(() => [createTextVNode(" New Item ")]),
																				_: 1
																			}, 16)]),
																			default: withCtx(() => [createVNode(VCard, null, {
																				default: withCtx(() => [createVNode(VCardText, null, {
																					default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																						default: withCtx(() => [
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formProduct).rus,
																									"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																									label: "Product"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							}),
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formProduct).eng,
																									"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																									label: "Product eng"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							}),
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formProduct).zh,
																									"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																									label: "Product zh"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							}),
																							createVNode(VRow, null, {
																								default: withCtx(() => [createVNode(VTextField, {
																									modelValue: unref(formProduct).es,
																									"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																									label: "Product es"
																								}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																								_: 1
																							})
																						]),
																						_: 1
																					}, 8, ["onSubmit"])]),
																					_: 1
																				}), createVNode(VCardActions, null, {
																					default: withCtx(() => [
																						createVNode(VSpacer),
																						createVNode(VBtn, {
																							color: "blue-darken-1",
																							variant: "text",
																							onClick: _ctx.close
																						}, {
																							default: withCtx(() => [createTextVNode(" Cancel ")]),
																							_: 1
																						}, 8, ["onClick"]),
																						createVNode(VDivider),
																						createVNode(VBtn, {
																							color: "blue-darken-1",
																							variant: "text",
																							onClick: storeProduct
																						}, {
																							default: withCtx(() => [createTextVNode(" Store ")]),
																							_: 1
																						})
																					]),
																					_: 1
																				})]),
																				_: 1
																			})]),
																			_: 1
																		}, 8, ["modelValue", "onUpdate:modelValue"])];
																	}),
																	_: 1
																}, _parent, _scopeId));
															} else return [createVNode(VCol, { cols: "9" }, {
																default: withCtx(() => [createVNode(VTextField, {
																	class: "py-2",
																	modelValue: unref(searchProducts),
																	"onUpdate:modelValue": ($event) => isRef(searchProducts) ? searchProducts.value = $event : searchProducts = $event,
																	label: "Filter products",
																	"prepend-inner-icon": "mdi-magnify",
																	variant: "outlined",
																	clearable: ""
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															}), createVNode(VCol, { cols: "3" }, {
																default: withCtx(() => [createVNode(VDialog, {
																	modelValue: unref(showFormProduct),
																	"onUpdate:modelValue": ($event) => isRef(showFormProduct) ? showFormProduct.value = $event : showFormProduct = $event,
																	"max-width": "500px"
																}, {
																	activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps({
																		class: "mb-2",
																		color: "primary",
																		dark: ""
																	}, props), {
																		default: withCtx(() => [createTextVNode(" New Item ")]),
																		_: 1
																	}, 16)]),
																	default: withCtx(() => [createVNode(VCard, null, {
																		default: withCtx(() => [createVNode(VCardText, null, {
																			default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																				default: withCtx(() => [
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formProduct).rus,
																							"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																							label: "Product"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formProduct).eng,
																							"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																							label: "Product eng"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formProduct).zh,
																							"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																							label: "Product zh"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					}),
																					createVNode(VRow, null, {
																						default: withCtx(() => [createVNode(VTextField, {
																							modelValue: unref(formProduct).es,
																							"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																							label: "Product es"
																						}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																						_: 1
																					})
																				]),
																				_: 1
																			}, 8, ["onSubmit"])]),
																			_: 1
																		}), createVNode(VCardActions, null, {
																			default: withCtx(() => [
																				createVNode(VSpacer),
																				createVNode(VBtn, {
																					color: "blue-darken-1",
																					variant: "text",
																					onClick: _ctx.close
																				}, {
																					default: withCtx(() => [createTextVNode(" Cancel ")]),
																					_: 1
																				}, 8, ["onClick"]),
																				createVNode(VDivider),
																				createVNode(VBtn, {
																					color: "blue-darken-1",
																					variant: "text",
																					onClick: storeProduct
																				}, {
																					default: withCtx(() => [createTextVNode(" Store ")]),
																					_: 1
																				})
																			]),
																			_: 1
																		})]),
																		_: 1
																	})]),
																	_: 1
																}, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})];
														}),
														_: 1
													}, _parent, _scopeId));
													else return [createVNode(VRow, null, {
														default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
															default: withCtx(() => [createVNode(VTextField, {
																class: "py-2",
																modelValue: unref(searchProducts),
																"onUpdate:modelValue": ($event) => isRef(searchProducts) ? searchProducts.value = $event : searchProducts = $event,
																label: "Filter products",
																"prepend-inner-icon": "mdi-magnify",
																variant: "outlined",
																clearable: ""
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														}), createVNode(VCol, { cols: "3" }, {
															default: withCtx(() => [createVNode(VDialog, {
																modelValue: unref(showFormProduct),
																"onUpdate:modelValue": ($event) => isRef(showFormProduct) ? showFormProduct.value = $event : showFormProduct = $event,
																"max-width": "500px"
															}, {
																activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps({
																	class: "mb-2",
																	color: "primary",
																	dark: ""
																}, props), {
																	default: withCtx(() => [createTextVNode(" New Item ")]),
																	_: 1
																}, 16)]),
																default: withCtx(() => [createVNode(VCard, null, {
																	default: withCtx(() => [createVNode(VCardText, null, {
																		default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																			default: withCtx(() => [
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formProduct).rus,
																						"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																						label: "Product"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formProduct).eng,
																						"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																						label: "Product eng"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formProduct).zh,
																						"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																						label: "Product zh"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				}),
																				createVNode(VRow, null, {
																					default: withCtx(() => [createVNode(VTextField, {
																						modelValue: unref(formProduct).es,
																						"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																						label: "Product es"
																					}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																					_: 1
																				})
																			]),
																			_: 1
																		}, 8, ["onSubmit"])]),
																		_: 1
																	}), createVNode(VCardActions, null, {
																		default: withCtx(() => [
																			createVNode(VSpacer),
																			createVNode(VBtn, {
																				color: "blue-darken-1",
																				variant: "text",
																				onClick: _ctx.close
																			}, {
																				default: withCtx(() => [createTextVNode(" Cancel ")]),
																				_: 1
																			}, 8, ["onClick"]),
																			createVNode(VDivider),
																			createVNode(VBtn, {
																				color: "blue-darken-1",
																				variant: "text",
																				onClick: storeProduct
																			}, {
																				default: withCtx(() => [createTextVNode(" Store ")]),
																				_: 1
																			})
																		]),
																		_: 1
																	})]),
																	_: 1
																})]),
																_: 1
															}, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})]),
														_: 1
													})];
												}),
												"item.rus": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) _push(ssrRenderComponent(unref(Link), {
														href: _ctx.route("product.show", item.id),
														class: "text-blue-950"
													}, {
														default: withCtx((_, _push, _parent, _scopeId) => {
															if (_push) _push(`<span class="font-ComfortaaVariableFont text-xl" data-v-83530b06${_scopeId}>${ssrInterpolate(item.rus)}</span>`);
															else return [createVNode("span", { class: "font-ComfortaaVariableFont text-xl" }, toDisplayString(item.rus), 1)];
														}),
														_: 2
													}, _parent, _scopeId));
													else return [createVNode(unref(Link), {
														href: _ctx.route("product.show", item.id),
														class: "text-blue-950"
													}, {
														default: withCtx(() => [createVNode("span", { class: "font-ComfortaaVariableFont text-xl" }, toDisplayString(item.rus), 1)]),
														_: 2
													}, 1032, ["href"])];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VDataTable, {
												headers: headersProducts,
												items: unref(products),
												search: unref(searchProducts),
												"items-per-page": "124",
												density: "compact",
												hover: "hover"
											}, {
												top: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
														default: withCtx(() => [createVNode(VTextField, {
															class: "py-2",
															modelValue: unref(searchProducts),
															"onUpdate:modelValue": ($event) => isRef(searchProducts) ? searchProducts.value = $event : searchProducts = $event,
															label: "Filter products",
															"prepend-inner-icon": "mdi-magnify",
															variant: "outlined",
															clearable: ""
														}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													}), createVNode(VCol, { cols: "3" }, {
														default: withCtx(() => [createVNode(VDialog, {
															modelValue: unref(showFormProduct),
															"onUpdate:modelValue": ($event) => isRef(showFormProduct) ? showFormProduct.value = $event : showFormProduct = $event,
															"max-width": "500px"
														}, {
															activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps({
																class: "mb-2",
																color: "primary",
																dark: ""
															}, props), {
																default: withCtx(() => [createTextVNode(" New Item ")]),
																_: 1
															}, 16)]),
															default: withCtx(() => [createVNode(VCard, null, {
																default: withCtx(() => [createVNode(VCardText, null, {
																	default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																		default: withCtx(() => [
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formProduct).rus,
																					"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																					label: "Product"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formProduct).eng,
																					"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																					label: "Product eng"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formProduct).zh,
																					"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																					label: "Product zh"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			}),
																			createVNode(VRow, null, {
																				default: withCtx(() => [createVNode(VTextField, {
																					modelValue: unref(formProduct).es,
																					"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																					label: "Product es"
																				}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																				_: 1
																			})
																		]),
																		_: 1
																	}, 8, ["onSubmit"])]),
																	_: 1
																}), createVNode(VCardActions, null, {
																	default: withCtx(() => [
																		createVNode(VSpacer),
																		createVNode(VBtn, {
																			color: "blue-darken-1",
																			variant: "text",
																			onClick: _ctx.close
																		}, {
																			default: withCtx(() => [createTextVNode(" Cancel ")]),
																			_: 1
																		}, 8, ["onClick"]),
																		createVNode(VDivider),
																		createVNode(VBtn, {
																			color: "blue-darken-1",
																			variant: "text",
																			onClick: storeProduct
																		}, {
																			default: withCtx(() => [createTextVNode(" Store ")]),
																			_: 1
																		})
																	]),
																	_: 1
																})]),
																_: 1
															})]),
															_: 1
														}, 8, ["modelValue", "onUpdate:modelValue"])]),
														_: 1
													})]),
													_: 1
												})]),
												"item.rus": withCtx(({ item }) => [createVNode(unref(Link), {
													href: _ctx.route("product.show", item.id),
													class: "text-blue-950"
												}, {
													default: withCtx(() => [createVNode("span", { class: "font-ComfortaaVariableFont text-xl" }, toDisplayString(item.rus), 1)]),
													_: 2
												}, 1032, ["href"])]),
												_: 1
											}, 8, ["items", "search"])];
										}),
										_: 1
									}, _parent, _scopeId));
									else return [createVNode(VCard, {
										flat: "",
										color: "grey"
									}, {
										default: withCtx(() => [createVNode(VDataTable, {
											headers: headersProducts,
											items: unref(products),
											search: unref(searchProducts),
											"items-per-page": "124",
											density: "compact",
											hover: "hover"
										}, {
											top: withCtx(() => [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
													default: withCtx(() => [createVNode(VTextField, {
														class: "py-2",
														modelValue: unref(searchProducts),
														"onUpdate:modelValue": ($event) => isRef(searchProducts) ? searchProducts.value = $event : searchProducts = $event,
														label: "Filter products",
														"prepend-inner-icon": "mdi-magnify",
														variant: "outlined",
														clearable: ""
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												}), createVNode(VCol, { cols: "3" }, {
													default: withCtx(() => [createVNode(VDialog, {
														modelValue: unref(showFormProduct),
														"onUpdate:modelValue": ($event) => isRef(showFormProduct) ? showFormProduct.value = $event : showFormProduct = $event,
														"max-width": "500px"
													}, {
														activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps({
															class: "mb-2",
															color: "primary",
															dark: ""
														}, props), {
															default: withCtx(() => [createTextVNode(" New Item ")]),
															_: 1
														}, 16)]),
														default: withCtx(() => [createVNode(VCard, null, {
															default: withCtx(() => [createVNode(VCardText, null, {
																default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																	default: withCtx(() => [
																		createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formProduct).rus,
																				"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																				label: "Product"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formProduct).eng,
																				"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																				label: "Product eng"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formProduct).zh,
																				"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																				label: "Product zh"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		}),
																		createVNode(VRow, null, {
																			default: withCtx(() => [createVNode(VTextField, {
																				modelValue: unref(formProduct).es,
																				"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																				label: "Product es"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																			_: 1
																		})
																	]),
																	_: 1
																}, 8, ["onSubmit"])]),
																_: 1
															}), createVNode(VCardActions, null, {
																default: withCtx(() => [
																	createVNode(VSpacer),
																	createVNode(VBtn, {
																		color: "blue-darken-1",
																		variant: "text",
																		onClick: _ctx.close
																	}, {
																		default: withCtx(() => [createTextVNode(" Cancel ")]),
																		_: 1
																	}, 8, ["onClick"]),
																	createVNode(VDivider),
																	createVNode(VBtn, {
																		color: "blue-darken-1",
																		variant: "text",
																		onClick: storeProduct
																	}, {
																		default: withCtx(() => [createTextVNode(" Store ")]),
																		_: 1
																	})
																]),
																_: 1
															})]),
															_: 1
														})]),
														_: 1
													}, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})]),
												_: 1
											})]),
											"item.rus": withCtx(({ item }) => [createVNode(unref(Link), {
												href: _ctx.route("product.show", item.id),
												class: "text-blue-950"
											}, {
												default: withCtx(() => [createVNode("span", { class: "font-ComfortaaVariableFont text-xl" }, toDisplayString(item.rus), 1)]),
												_: 2
											}, 1032, ["href"])]),
											_: 1
										}, 8, ["items", "search"])]),
										_: 1
									})];
								}),
								_: 1
							}, _parent, _scopeId));
							else return [createVNode(VCol, null, {
								default: withCtx(() => [createVNode(VCard, {
									flat: "",
									color: "grey"
								}, {
									default: withCtx(() => [createVNode(VDataTable, {
										headers: headersProducts,
										items: unref(products),
										search: unref(searchProducts),
										"items-per-page": "124",
										density: "compact",
										hover: "hover"
									}, {
										top: withCtx(() => [createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
												default: withCtx(() => [createVNode(VTextField, {
													class: "py-2",
													modelValue: unref(searchProducts),
													"onUpdate:modelValue": ($event) => isRef(searchProducts) ? searchProducts.value = $event : searchProducts = $event,
													label: "Filter products",
													"prepend-inner-icon": "mdi-magnify",
													variant: "outlined",
													clearable: ""
												}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											}), createVNode(VCol, { cols: "3" }, {
												default: withCtx(() => [createVNode(VDialog, {
													modelValue: unref(showFormProduct),
													"onUpdate:modelValue": ($event) => isRef(showFormProduct) ? showFormProduct.value = $event : showFormProduct = $event,
													"max-width": "500px"
												}, {
													activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps({
														class: "mb-2",
														color: "primary",
														dark: ""
													}, props), {
														default: withCtx(() => [createTextVNode(" New Item ")]),
														_: 1
													}, 16)]),
													default: withCtx(() => [createVNode(VCard, null, {
														default: withCtx(() => [createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
																default: withCtx(() => [
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formProduct).rus,
																			"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																			label: "Product"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formProduct).eng,
																			"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																			label: "Product eng"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formProduct).zh,
																			"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																			label: "Product zh"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	}),
																	createVNode(VRow, null, {
																		default: withCtx(() => [createVNode(VTextField, {
																			modelValue: unref(formProduct).es,
																			"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																			label: "Product es"
																		}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																		_: 1
																	})
																]),
																_: 1
															}, 8, ["onSubmit"])]),
															_: 1
														}), createVNode(VCardActions, null, {
															default: withCtx(() => [
																createVNode(VSpacer),
																createVNode(VBtn, {
																	color: "blue-darken-1",
																	variant: "text",
																	onClick: _ctx.close
																}, {
																	default: withCtx(() => [createTextVNode(" Cancel ")]),
																	_: 1
																}, 8, ["onClick"]),
																createVNode(VDivider),
																createVNode(VBtn, {
																	color: "blue-darken-1",
																	variant: "text",
																	onClick: storeProduct
																}, {
																	default: withCtx(() => [createTextVNode(" Store ")]),
																	_: 1
																})
															]),
															_: 1
														})]),
														_: 1
													})]),
													_: 1
												}, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})]),
											_: 1
										})]),
										"item.rus": withCtx(({ item }) => [createVNode(unref(Link), {
											href: _ctx.route("product.show", item.id),
											class: "text-blue-950"
										}, {
											default: withCtx(() => [createVNode("span", { class: "font-ComfortaaVariableFont text-xl" }, toDisplayString(item.rus), 1)]),
											_: 2
										}, 1032, ["href"])]),
										_: 1
									}, 8, ["items", "search"])]),
									_: 1
								})]),
								_: 1
							})];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, null, {
							default: withCtx(() => [createVNode(VCard, {
								flat: "",
								color: "grey"
							}, {
								default: withCtx(() => [createVNode(VDataTable, {
									headers: headersProducts,
									items: unref(products),
									search: unref(searchProducts),
									"items-per-page": "124",
									density: "compact",
									hover: "hover"
								}, {
									top: withCtx(() => [createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
											default: withCtx(() => [createVNode(VTextField, {
												class: "py-2",
												modelValue: unref(searchProducts),
												"onUpdate:modelValue": ($event) => isRef(searchProducts) ? searchProducts.value = $event : searchProducts = $event,
												label: "Filter products",
												"prepend-inner-icon": "mdi-magnify",
												variant: "outlined",
												clearable: ""
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										}), createVNode(VCol, { cols: "3" }, {
											default: withCtx(() => [createVNode(VDialog, {
												modelValue: unref(showFormProduct),
												"onUpdate:modelValue": ($event) => isRef(showFormProduct) ? showFormProduct.value = $event : showFormProduct = $event,
												"max-width": "500px"
											}, {
												activator: withCtx(({ props }) => [createVNode(VBtn, mergeProps({
													class: "mb-2",
													color: "primary",
													dark: ""
												}, props), {
													default: withCtx(() => [createTextVNode(" New Item ")]),
													_: 1
												}, 16)]),
												default: withCtx(() => [createVNode(VCard, null, {
													default: withCtx(() => [createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VForm, { onSubmit: withModifiers(() => {}, ["prevent"]) }, {
															default: withCtx(() => [
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formProduct).rus,
																		"onUpdate:modelValue": ($event) => unref(formProduct).rus = $event,
																		label: "Product"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formProduct).eng,
																		"onUpdate:modelValue": ($event) => unref(formProduct).eng = $event,
																		label: "Product eng"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formProduct).zh,
																		"onUpdate:modelValue": ($event) => unref(formProduct).zh = $event,
																		label: "Product zh"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																}),
																createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VTextField, {
																		modelValue: unref(formProduct).es,
																		"onUpdate:modelValue": ($event) => unref(formProduct).es = $event,
																		label: "Product es"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																})
															]),
															_: 1
														}, 8, ["onSubmit"])]),
														_: 1
													}), createVNode(VCardActions, null, {
														default: withCtx(() => [
															createVNode(VSpacer),
															createVNode(VBtn, {
																color: "blue-darken-1",
																variant: "text",
																onClick: _ctx.close
															}, {
																default: withCtx(() => [createTextVNode(" Cancel ")]),
																_: 1
															}, 8, ["onClick"]),
															createVNode(VDivider),
															createVNode(VBtn, {
																color: "blue-darken-1",
																variant: "text",
																onClick: storeProduct
															}, {
																default: withCtx(() => [createTextVNode(" Store ")]),
																_: 1
															})
														]),
														_: 1
													})]),
													_: 1
												})]),
												_: 1
											}, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})]),
										_: 1
									})]),
									"item.rus": withCtx(({ item }) => [createVNode(unref(Link), {
										href: _ctx.route("product.show", item.id),
										class: "text-blue-950"
									}, {
										default: withCtx(() => [createVNode("span", { class: "font-ComfortaaVariableFont text-xl" }, toDisplayString(item.rus), 1)]),
										_: 2
									}, 1032, ["href"])]),
									_: 1
								}, 8, ["items", "search"])]),
								_: 1
							})]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Products.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var Products_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-83530b06"]]);
//#endregion
export { Products_default as default };

//# sourceMappingURL=Products-Bl5fRWaC.js.map