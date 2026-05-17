import { C as VRow, D as VDataTable, F as VCardText, I as VCardTitle, P as VCard, R as VCardActions, T as VContainer, V as VAutocomplete, dt as useDate, i as VTextarea, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { createTextVNode, createVNode, isRef, mergeProps, onMounted, ref, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Pages/Ameise/TelegramBot.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "TelegramBot",
	__ssrInlineRender: true,
	setup(__props) {
		const date = useDate();
		const headersMessages = [
			{
				title: "Content",
				key: "content"
			},
			{
				title: "Created",
				key: "created_at"
			},
			{
				title: "Chat",
				key: "chat_id"
			},
			{
				title: "Date",
				key: "date"
			},
			{
				title: "message_id",
				key: "message_id"
			},
			{
				title: "update_id",
				key: "update_id"
			}
		];
		let messages = ref();
		function indexMessages() {
			axios.get(route("messages.index")).then(function(response) {
				messages.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let chats = ref();
		function indexChats() {
			axios.get(route("api.chats")).then(function(response) {
				chats.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		let showFormTelegramMessage = ref(false);
		let chat = ref();
		let message = ref();
		async function sendTelegramMessage(chat, message) {
			try {
				const { data } = await axios.post(route("api.telegram.sendMessage"), {
					chat_id: chat,
					text: message
				});
				message.value = null;
				indexMessages();
				return data;
			} catch (error) {
				console.error("Ошибка при отправке сообщения:", error);
			}
		}
		onMounted(() => {
			indexChats();
			indexMessages();
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, mergeProps({ fluid: "" }, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VBtn, {
										text: "sendMessage",
										onClick: ($event) => isRef(showFormTelegramMessage) ? showFormTelegramMessage.value = !unref(showFormTelegramMessage) : showFormTelegramMessage = !unref(showFormTelegramMessage),
										variant: "elevated"
									}, null, _parent, _scopeId));
									_push(ssrRenderComponent(VDialog, {
										modelValue: unref(showFormTelegramMessage),
										"onUpdate:modelValue": ($event) => isRef(showFormTelegramMessage) ? showFormTelegramMessage.value = $event : showFormTelegramMessage = $event,
										width: "775"
									}, {
										default: withCtx(({ isActive }, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VCard, null, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCardTitle, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`Form Message`);
																else return [createTextVNode("Form Message")];
															}),
															_: 2
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCardText, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	_push(ssrRenderComponent(VRow, null, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VAutocomplete, {
																				items: unref(chats),
																				"item-title": "first_name",
																				"item-value": "numbers",
																				modelValue: unref(chat),
																				"onUpdate:modelValue": ($event) => isRef(chat) ? chat.value = $event : chat = $event,
																				label: "chat",
																				placeholder: "Выбери chat",
																				variant: "outlined",
																				density: "compact",
																				color: "black"
																			}, null, _parent, _scopeId));
																			else return [createVNode(VAutocomplete, {
																				items: unref(chats),
																				"item-title": "first_name",
																				"item-value": "numbers",
																				modelValue: unref(chat),
																				"onUpdate:modelValue": ($event) => isRef(chat) ? chat.value = $event : chat = $event,
																				label: "chat",
																				placeholder: "Выбери chat",
																				variant: "outlined",
																				density: "compact",
																				color: "black"
																			}, null, 8, [
																				"items",
																				"modelValue",
																				"onUpdate:modelValue"
																			])];
																		}),
																		_: 2
																	}, _parent, _scopeId));
																	_push(ssrRenderComponent(VRow, null, {
																		default: withCtx((_, _push, _parent, _scopeId) => {
																			if (_push) _push(ssrRenderComponent(VTextarea, {
																				modelValue: unref(message),
																				"onUpdate:modelValue": ($event) => isRef(message) ? message.value = $event : message = $event,
																				label: "Message",
																				variant: "outlined",
																				color: "grey"
																			}, null, _parent, _scopeId));
																			else return [createVNode(VTextarea, {
																				modelValue: unref(message),
																				"onUpdate:modelValue": ($event) => isRef(message) ? message.value = $event : message = $event,
																				label: "Message",
																				variant: "outlined",
																				color: "grey"
																			}, null, 8, ["modelValue", "onUpdate:modelValue"])];
																		}),
																		_: 2
																	}, _parent, _scopeId));
																} else return [createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VAutocomplete, {
																		items: unref(chats),
																		"item-title": "first_name",
																		"item-value": "numbers",
																		modelValue: unref(chat),
																		"onUpdate:modelValue": ($event) => isRef(chat) ? chat.value = $event : chat = $event,
																		label: "chat",
																		placeholder: "Выбери chat",
																		variant: "outlined",
																		density: "compact",
																		color: "black"
																	}, null, 8, [
																		"items",
																		"modelValue",
																		"onUpdate:modelValue"
																	])]),
																	_: 1
																}), createVNode(VRow, null, {
																	default: withCtx(() => [createVNode(VTextarea, {
																		modelValue: unref(message),
																		"onUpdate:modelValue": ($event) => isRef(message) ? message.value = $event : message = $event,
																		label: "Message",
																		variant: "outlined",
																		color: "grey"
																	}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																	_: 1
																})];
															}),
															_: 2
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCardActions, null, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(ssrRenderComponent(VBtn, {
																	onClick: ($event) => sendTelegramMessage(unref(chat), unref(message)),
																	text: "send",
																	variant: "elevated",
																	density: "comfortable",
																	color: "cyan-accent-4"
																}, null, _parent, _scopeId));
																else return [createVNode(VBtn, {
																	onClick: ($event) => sendTelegramMessage(unref(chat), unref(message)),
																	text: "send",
																	variant: "elevated",
																	density: "comfortable",
																	color: "cyan-accent-4"
																}, null, 8, ["onClick"])];
															}),
															_: 2
														}, _parent, _scopeId));
													} else return [
														createVNode(VCardTitle, null, {
															default: withCtx(() => [createTextVNode("Form Message")]),
															_: 1
														}),
														createVNode(VCardText, null, {
															default: withCtx(() => [createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VAutocomplete, {
																	items: unref(chats),
																	"item-title": "first_name",
																	"item-value": "numbers",
																	modelValue: unref(chat),
																	"onUpdate:modelValue": ($event) => isRef(chat) ? chat.value = $event : chat = $event,
																	label: "chat",
																	placeholder: "Выбери chat",
																	variant: "outlined",
																	density: "compact",
																	color: "black"
																}, null, 8, [
																	"items",
																	"modelValue",
																	"onUpdate:modelValue"
																])]),
																_: 1
															}), createVNode(VRow, null, {
																default: withCtx(() => [createVNode(VTextarea, {
																	modelValue: unref(message),
																	"onUpdate:modelValue": ($event) => isRef(message) ? message.value = $event : message = $event,
																	label: "Message",
																	variant: "outlined",
																	color: "grey"
																}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
																_: 1
															})]),
															_: 1
														}),
														createVNode(VCardActions, null, {
															default: withCtx(() => [createVNode(VBtn, {
																onClick: ($event) => sendTelegramMessage(unref(chat), unref(message)),
																text: "send",
																variant: "elevated",
																density: "comfortable",
																color: "cyan-accent-4"
															}, null, 8, ["onClick"])]),
															_: 1
														})
													];
												}),
												_: 2
											}, _parent, _scopeId));
											else return [createVNode(VCard, null, {
												default: withCtx(() => [
													createVNode(VCardTitle, null, {
														default: withCtx(() => [createTextVNode("Form Message")]),
														_: 1
													}),
													createVNode(VCardText, null, {
														default: withCtx(() => [createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VAutocomplete, {
																items: unref(chats),
																"item-title": "first_name",
																"item-value": "numbers",
																modelValue: unref(chat),
																"onUpdate:modelValue": ($event) => isRef(chat) ? chat.value = $event : chat = $event,
																label: "chat",
																placeholder: "Выбери chat",
																variant: "outlined",
																density: "compact",
																color: "black"
															}, null, 8, [
																"items",
																"modelValue",
																"onUpdate:modelValue"
															])]),
															_: 1
														}), createVNode(VRow, null, {
															default: withCtx(() => [createVNode(VTextarea, {
																modelValue: unref(message),
																"onUpdate:modelValue": ($event) => isRef(message) ? message.value = $event : message = $event,
																label: "Message",
																variant: "outlined",
																color: "grey"
															}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
															_: 1
														})]),
														_: 1
													}),
													createVNode(VCardActions, null, {
														default: withCtx(() => [createVNode(VBtn, {
															onClick: ($event) => sendTelegramMessage(unref(chat), unref(message)),
															text: "send",
															variant: "elevated",
															density: "comfortable",
															color: "cyan-accent-4"
														}, null, 8, ["onClick"])]),
														_: 1
													})
												]),
												_: 1
											})];
										}),
										_: 1
									}, _parent, _scopeId));
								} else return [createVNode(VBtn, {
									text: "sendMessage",
									onClick: ($event) => isRef(showFormTelegramMessage) ? showFormTelegramMessage.value = !unref(showFormTelegramMessage) : showFormTelegramMessage = !unref(showFormTelegramMessage),
									variant: "elevated"
								}, null, 8, ["onClick"]), createVNode(VDialog, {
									modelValue: unref(showFormTelegramMessage),
									"onUpdate:modelValue": ($event) => isRef(showFormTelegramMessage) ? showFormTelegramMessage.value = $event : showFormTelegramMessage = $event,
									width: "775"
								}, {
									default: withCtx(({ isActive }) => [createVNode(VCard, null, {
										default: withCtx(() => [
											createVNode(VCardTitle, null, {
												default: withCtx(() => [createTextVNode("Form Message")]),
												_: 1
											}),
											createVNode(VCardText, null, {
												default: withCtx(() => [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VAutocomplete, {
														items: unref(chats),
														"item-title": "first_name",
														"item-value": "numbers",
														modelValue: unref(chat),
														"onUpdate:modelValue": ($event) => isRef(chat) ? chat.value = $event : chat = $event,
														label: "chat",
														placeholder: "Выбери chat",
														variant: "outlined",
														density: "compact",
														color: "black"
													}, null, 8, [
														"items",
														"modelValue",
														"onUpdate:modelValue"
													])]),
													_: 1
												}), createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VTextarea, {
														modelValue: unref(message),
														"onUpdate:modelValue": ($event) => isRef(message) ? message.value = $event : message = $event,
														label: "Message",
														variant: "outlined",
														color: "grey"
													}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VCardActions, null, {
												default: withCtx(() => [createVNode(VBtn, {
													onClick: ($event) => sendTelegramMessage(unref(chat), unref(message)),
													text: "send",
													variant: "elevated",
													density: "comfortable",
													color: "cyan-accent-4"
												}, null, 8, ["onClick"])]),
												_: 1
											})
										]),
										_: 1
									})]),
									_: 1
								}, 8, ["modelValue", "onUpdate:modelValue"])];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(ssrRenderComponent(VRow, null, {
							default: withCtx((_, _push, _parent, _scopeId) => {
								if (_push) {
									_push(ssrRenderComponent(VCol, { cols: "8" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VDataTable, {
												items: unref(messages),
												headers: headersMessages,
												"items-per-page": "500",
												density: "compact",
												hover: "true"
											}, {
												"item.content": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) _push(`<span class="font-sans text-sm"${_scopeId}>${ssrInterpolate(item.content)}</span>`);
													else return [createVNode("span", { class: "font-sans text-sm" }, toDisplayString(item.content), 1)];
												}),
												"item.created_at": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) _push(`<span class="font-sans text-sm"${_scopeId}>${ssrInterpolate(unref(date).format(item.created_at, "fullDateTime24h"))}</span>`);
													else return [createVNode("span", { class: "font-sans text-sm" }, toDisplayString(unref(date).format(item.created_at, "fullDateTime24h")), 1)];
												}),
												"item.chat_id": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) _push(`<span${_scopeId}>${ssrInterpolate(item.chat.first_name)}</span>`);
													else return [createVNode("span", null, toDisplayString(item.chat.first_name), 1)];
												}),
												"item.date": withCtx(({ item }, _push, _parent, _scopeId) => {
													if (_push) _push(`<span${_scopeId}>${ssrInterpolate(unref(date).format(item.date, "fullDate"))}</span>`);
													else return [createVNode("span", null, toDisplayString(unref(date).format(item.date, "fullDate")), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											else return [createVNode(VDataTable, {
												items: unref(messages),
												headers: headersMessages,
												"items-per-page": "500",
												density: "compact",
												hover: "true"
											}, {
												"item.content": withCtx(({ item }) => [createVNode("span", { class: "font-sans text-sm" }, toDisplayString(item.content), 1)]),
												"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "font-sans text-sm" }, toDisplayString(unref(date).format(item.created_at, "fullDateTime24h")), 1)]),
												"item.chat_id": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.chat.first_name), 1)]),
												"item.date": withCtx(({ item }) => [createVNode("span", null, toDisplayString(unref(date).format(item.date, "fullDate")), 1)]),
												_: 1
											}, 8, ["items"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, { cols: "3" }, {
										default: withCtx((_, _push, _parent, _scopeId) => {
											if (_push) _push(ssrRenderComponent(VDataTable, {
												items: unref(chats),
												density: "comfortable",
												hover: "true"
											}, null, _parent, _scopeId));
											else return [createVNode(VDataTable, {
												items: unref(chats),
												density: "comfortable",
												hover: "true"
											}, null, 8, ["items"])];
										}),
										_: 1
									}, _parent, _scopeId));
									_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
								} else return [
									createVNode(VCol, { cols: "8" }, {
										default: withCtx(() => [createVNode(VDataTable, {
											items: unref(messages),
											headers: headersMessages,
											"items-per-page": "500",
											density: "compact",
											hover: "true"
										}, {
											"item.content": withCtx(({ item }) => [createVNode("span", { class: "font-sans text-sm" }, toDisplayString(item.content), 1)]),
											"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "font-sans text-sm" }, toDisplayString(unref(date).format(item.created_at, "fullDateTime24h")), 1)]),
											"item.chat_id": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.chat.first_name), 1)]),
											"item.date": withCtx(({ item }) => [createVNode("span", null, toDisplayString(unref(date).format(item.date, "fullDate")), 1)]),
											_: 1
										}, 8, ["items"])]),
										_: 1
									}),
									createVNode(VCol, { cols: "3" }, {
										default: withCtx(() => [createVNode(VDataTable, {
											items: unref(chats),
											density: "comfortable",
											hover: "true"
										}, null, 8, ["items"])]),
										_: 1
									}),
									createVNode(VCol)
								];
							}),
							_: 1
						}, _parent, _scopeId));
					} else return [createVNode(VRow, null, {
						default: withCtx(() => [createVNode(VBtn, {
							text: "sendMessage",
							onClick: ($event) => isRef(showFormTelegramMessage) ? showFormTelegramMessage.value = !unref(showFormTelegramMessage) : showFormTelegramMessage = !unref(showFormTelegramMessage),
							variant: "elevated"
						}, null, 8, ["onClick"]), createVNode(VDialog, {
							modelValue: unref(showFormTelegramMessage),
							"onUpdate:modelValue": ($event) => isRef(showFormTelegramMessage) ? showFormTelegramMessage.value = $event : showFormTelegramMessage = $event,
							width: "775"
						}, {
							default: withCtx(({ isActive }) => [createVNode(VCard, null, {
								default: withCtx(() => [
									createVNode(VCardTitle, null, {
										default: withCtx(() => [createTextVNode("Form Message")]),
										_: 1
									}),
									createVNode(VCardText, null, {
										default: withCtx(() => [createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VAutocomplete, {
												items: unref(chats),
												"item-title": "first_name",
												"item-value": "numbers",
												modelValue: unref(chat),
												"onUpdate:modelValue": ($event) => isRef(chat) ? chat.value = $event : chat = $event,
												label: "chat",
												placeholder: "Выбери chat",
												variant: "outlined",
												density: "compact",
												color: "black"
											}, null, 8, [
												"items",
												"modelValue",
												"onUpdate:modelValue"
											])]),
											_: 1
										}), createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VTextarea, {
												modelValue: unref(message),
												"onUpdate:modelValue": ($event) => isRef(message) ? message.value = $event : message = $event,
												label: "Message",
												variant: "outlined",
												color: "grey"
											}, null, 8, ["modelValue", "onUpdate:modelValue"])]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VCardActions, null, {
										default: withCtx(() => [createVNode(VBtn, {
											onClick: ($event) => sendTelegramMessage(unref(chat), unref(message)),
											text: "send",
											variant: "elevated",
											density: "comfortable",
											color: "cyan-accent-4"
										}, null, 8, ["onClick"])]),
										_: 1
									})
								]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "onUpdate:modelValue"])]),
						_: 1
					}), createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol, { cols: "8" }, {
								default: withCtx(() => [createVNode(VDataTable, {
									items: unref(messages),
									headers: headersMessages,
									"items-per-page": "500",
									density: "compact",
									hover: "true"
								}, {
									"item.content": withCtx(({ item }) => [createVNode("span", { class: "font-sans text-sm" }, toDisplayString(item.content), 1)]),
									"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "font-sans text-sm" }, toDisplayString(unref(date).format(item.created_at, "fullDateTime24h")), 1)]),
									"item.chat_id": withCtx(({ item }) => [createVNode("span", null, toDisplayString(item.chat.first_name), 1)]),
									"item.date": withCtx(({ item }) => [createVNode("span", null, toDisplayString(unref(date).format(item.date, "fullDate")), 1)]),
									_: 1
								}, 8, ["items"])]),
								_: 1
							}),
							createVNode(VCol, { cols: "3" }, {
								default: withCtx(() => [createVNode(VDataTable, {
									items: unref(chats),
									density: "comfortable",
									hover: "true"
								}, null, 8, ["items"])]),
								_: 1
							}),
							createVNode(VCol)
						]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/TelegramBot.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=TelegramBot-DSqua_MX.js.map