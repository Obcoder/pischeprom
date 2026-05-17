import { C as VRow, F as VCardText, I as VCardTitle, P as VCard, T as VContainer, w as VCol } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import axios from "axios";
import { createTextVNode, createVNode, onMounted, ref, resolveComponent, toDisplayString, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Ameise/WorkBoard.vue
var _sfc_main = {
	__name: "WorkBoard",
	__ssrInlineRender: true,
	setup(__props) {
		const noContactUnits = ref([]);
		const contactedUnits = ref([]);
		async function fetchUnits() {
			try {
				const response = await axios.get("/api/work-list");
				noContactUnits.value = response.data.no_contact;
				contactedUnits.value = response.data.contacted;
			} catch (error) {
				console.error("Error fetching units:", error);
			}
		}
		async function onDragEnd(event) {
			const { element, to } = event;
			const stage = to === noContactUnits.value ? "no_contact" : "contacted";
			try {
				await axios.put(`/api/units/${element.id}/work-list/stage`, { stage });
				await fetchUnits();
			} catch (error) {
				console.error("Error updating stage:", error);
				await fetchUnits();
			}
		}
		onMounted(fetchUnits);
		return (_ctx, _push, _parent, _attrs) => {
			const _component_draggable = resolveComponent("draggable");
			_push(ssrRenderComponent(VContainer, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) _push(ssrRenderComponent(VCol, { cols: "12" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(`<h1 class="text-h4 mb-4" data-v-59ae9e16${_scopeId}>Рабочая доска</h1>`);
										else return [createVNode("h1", { class: "text-h4 mb-4" }, "Рабочая доска")];
									}),
									_: 1
								}, _parent, _scopeId));
								else return [createVNode(VCol, { cols: "12" }, {
									default: withCtx(() => [createVNode("h1", { class: "text-h4 mb-4" }, "Рабочая доска")]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { cols: "6" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VCard, {
												class: "pa-4",
												elevation: "2"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCardTitle, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Не было контактов`);
																else return [createTextVNode("Не было контактов")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(_component_draggable, {
															modelValue: noContactUnits.value,
															"onUpdate:modelValue": ($event) => noContactUnits.value = $event,
															group: "units",
															"item-key": "id",
															class: "drag-area",
															onEnd: onDragEnd
														}, {
															item: withCtx(({ element }, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VCard, {
																	class: "ma-2",
																	elevation: "1"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VCardText, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`${ssrInterpolate(element.name)}`);
																				else return [createTextVNode(toDisplayString(element.name), 1)];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		else return [createVNode(VCardText, null, {
																			default: withCtx(() => [createTextVNode(toDisplayString(element.name), 1)]),
																			_: 2
																		}, 1024)];
																	}),
																	_: 2
																}, _parent, _scopeId));
																else return [createVNode(VCard, {
																	class: "ma-2",
																	elevation: "1"
																}, {
																	default: withCtx(() => [createVNode(VCardText, null, {
																		default: withCtx(() => [createTextVNode(toDisplayString(element.name), 1)]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1024)];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Не было контактов")]),
														_: 1
													}), createVNode(_component_draggable, {
														modelValue: noContactUnits.value,
														"onUpdate:modelValue": ($event) => noContactUnits.value = $event,
														group: "units",
														"item-key": "id",
														class: "drag-area",
														onEnd: onDragEnd
													}, {
														item: withCtx(({ element }) => [createVNode(VCard, {
															class: "ma-2",
															elevation: "1"
														}, {
															default: withCtx(() => [createVNode(VCardText, null, {
																default: withCtx(() => [createTextVNode(toDisplayString(element.name), 1)]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024)]),
														_: 1
													}, 8, ["modelValue", "onUpdate:modelValue"])];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VCard, {
												class: "pa-4",
												elevation: "2"
											}, {
												default: withCtx(() => [createVNode(VCardTitle, null, {
													default: withCtx(() => [createTextVNode("Не было контактов")]),
													_: 1
												}), createVNode(_component_draggable, {
													modelValue: noContactUnits.value,
													"onUpdate:modelValue": ($event) => noContactUnits.value = $event,
													group: "units",
													"item-key": "id",
													class: "drag-area",
													onEnd: onDragEnd
												}, {
													item: withCtx(({ element }) => [createVNode(VCard, {
														class: "ma-2",
														elevation: "1"
													}, {
														default: withCtx(() => [createVNode(VCardText, null, {
															default: withCtx(() => [createTextVNode(toDisplayString(element.name), 1)]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1024)]),
													_: 1
												}, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "6" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VCard, {
												class: "pa-4",
												elevation: "2"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCardTitle, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Контакт состоялся`);
																else return [createTextVNode("Контакт состоялся")];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(_component_draggable, {
															modelValue: contactedUnits.value,
															"onUpdate:modelValue": ($event) => contactedUnits.value = $event,
															group: "units",
															"item-key": "id",
															class: "drag-area",
															onEnd: onDragEnd
														}, {
															item: withCtx(({ element }, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VCard, {
																	class: "ma-2",
																	elevation: "1"
																}, {
																	default: withCtx((_, _push, _parent, _scopeId) => {
																		if (_push) _push(ssrRenderComponent(VCardText, null, {
																			default: withCtx((_, _push, _parent, _scopeId) => {
																				if (_push) _push(`${ssrInterpolate(element.name)}`);
																				else return [createTextVNode(toDisplayString(element.name), 1)];
																			}),
																			_: 2
																		}, _parent, _scopeId));
																		else return [createVNode(VCardText, null, {
																			default: withCtx(() => [createTextVNode(toDisplayString(element.name), 1)]),
																			_: 2
																		}, 1024)];
																	}),
																	_: 2
																}, _parent, _scopeId));
																else return [createVNode(VCard, {
																	class: "ma-2",
																	elevation: "1"
																}, {
																	default: withCtx(() => [createVNode(VCardText, null, {
																		default: withCtx(() => [createTextVNode(toDisplayString(element.name), 1)]),
																		_: 2
																	}, 1024)]),
																	_: 2
																}, 1024)];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Контакт состоялся")]),
														_: 1
													}), createVNode(_component_draggable, {
														modelValue: contactedUnits.value,
														"onUpdate:modelValue": ($event) => contactedUnits.value = $event,
														group: "units",
														"item-key": "id",
														class: "drag-area",
														onEnd: onDragEnd
													}, {
														item: withCtx(({ element }) => [createVNode(VCard, {
															class: "ma-2",
															elevation: "1"
														}, {
															default: withCtx(() => [createVNode(VCardText, null, {
																default: withCtx(() => [createTextVNode(toDisplayString(element.name), 1)]),
																_: 2
															}, 1024)]),
															_: 2
														}, 1024)]),
														_: 1
													}, 8, ["modelValue", "onUpdate:modelValue"])];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VCard, {
												class: "pa-4",
												elevation: "2"
											}, {
												default: withCtx(() => [createVNode(VCardTitle, null, {
													default: withCtx(() => [createTextVNode("Контакт состоялся")]),
													_: 1
												}), createVNode(_component_draggable, {
													modelValue: contactedUnits.value,
													"onUpdate:modelValue": ($event) => contactedUnits.value = $event,
													group: "units",
													"item-key": "id",
													class: "drag-area",
													onEnd: onDragEnd
												}, {
													item: withCtx(({ element }) => [createVNode(VCard, {
														class: "ma-2",
														elevation: "1"
													}, {
														default: withCtx(() => [createVNode(VCardText, null, {
															default: withCtx(() => [createTextVNode(toDisplayString(element.name), 1)]),
															_: 2
														}, 1024)]),
														_: 2
													}, 1024)]),
													_: 1
												}, 8, ["modelValue", "onUpdate:modelValue"])]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VCol, { cols: "6" }, {
									default: withCtx(() => [createVNode(VCard, {
										class: "pa-4",
										elevation: "2"
									}, {
										default: withCtx(() => [createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Не было контактов")]),
											_: 1
										}), createVNode(_component_draggable, {
											modelValue: noContactUnits.value,
											"onUpdate:modelValue": ($event) => noContactUnits.value = $event,
											group: "units",
											"item-key": "id",
											class: "drag-area",
											onEnd: onDragEnd
										}, {
											item: withCtx(({ element }) => [createVNode(VCard, {
												class: "ma-2",
												elevation: "1"
											}, {
												default: withCtx(() => [createVNode(VCardText, null, {
													default: withCtx(() => [createTextVNode(toDisplayString(element.name), 1)]),
													_: 2
												}, 1024)]),
												_: 2
											}, 1024)]),
											_: 1
										}, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									})]),
									_: 1
								}), createVNode(VCol, { cols: "6" }, {
									default: withCtx(() => [createVNode(VCard, {
										class: "pa-4",
										elevation: "2"
									}, {
										default: withCtx(() => [createVNode(VCardTitle, null, {
											default: withCtx(() => [createTextVNode("Контакт состоялся")]),
											_: 1
										}), createVNode(_component_draggable, {
											modelValue: contactedUnits.value,
											"onUpdate:modelValue": ($event) => contactedUnits.value = $event,
											group: "units",
											"item-key": "id",
											class: "drag-area",
											onEnd: onDragEnd
										}, {
											item: withCtx(({ element }) => [createVNode(VCard, {
												class: "ma-2",
												elevation: "1"
											}, {
												default: withCtx(() => [createVNode(VCardText, null, {
													default: withCtx(() => [createTextVNode(toDisplayString(element.name), 1)]),
													_: 2
												}, 1024)]),
												_: 2
											}, 1024)]),
											_: 1
										}, 8, ["modelValue", "onUpdate:modelValue"])]),
										_: 1
									})]),
									_: 1
								})];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, { cols: "12" }, {
							default: withCtx(() => [createVNode("h1", { class: "text-h4 mb-4" }, "Рабочая доска")]),
							_: 1
						})]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VCol, { cols: "6" }, {
							default: withCtx(() => [createVNode(VCard, {
								class: "pa-4",
								elevation: "2"
							}, {
								default: withCtx(() => [createVNode(VCardTitle, null, {
									default: withCtx(() => [createTextVNode("Не было контактов")]),
									_: 1
								}), createVNode(_component_draggable, {
									modelValue: noContactUnits.value,
									"onUpdate:modelValue": ($event) => noContactUnits.value = $event,
									group: "units",
									"item-key": "id",
									class: "drag-area",
									onEnd: onDragEnd
								}, {
									item: withCtx(({ element }) => [createVNode(VCard, {
										class: "ma-2",
										elevation: "1"
									}, {
										default: withCtx(() => [createVNode(VCardText, null, {
											default: withCtx(() => [createTextVNode(toDisplayString(element.name), 1)]),
											_: 2
										}, 1024)]),
										_: 2
									}, 1024)]),
									_: 1
								}, 8, ["modelValue", "onUpdate:modelValue"])]),
								_: 1
							})]),
							_: 1
						}), createVNode(VCol, { cols: "6" }, {
							default: withCtx(() => [createVNode(VCard, {
								class: "pa-4",
								elevation: "2"
							}, {
								default: withCtx(() => [createVNode(VCardTitle, null, {
									default: withCtx(() => [createTextVNode("Контакт состоялся")]),
									_: 1
								}), createVNode(_component_draggable, {
									modelValue: contactedUnits.value,
									"onUpdate:modelValue": ($event) => contactedUnits.value = $event,
									group: "units",
									"item-key": "id",
									class: "drag-area",
									onEnd: onDragEnd
								}, {
									item: withCtx(({ element }) => [createVNode(VCard, {
										class: "ma-2",
										elevation: "1"
									}, {
										default: withCtx(() => [createVNode(VCardText, null, {
											default: withCtx(() => [createTextVNode(toDisplayString(element.name), 1)]),
											_: 2
										}, 1024)]),
										_: 2
									}, 1024)]),
									_: 1
								}, 8, ["modelValue", "onUpdate:modelValue"])]),
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
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/WorkBoard.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var WorkBoard_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-59ae9e16"]]);
//#endregion
export { WorkBoard_default as default };

//# sourceMappingURL=WorkBoard-C8DSdvmu.js.map