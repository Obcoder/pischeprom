import { C as VRow, F as VCardText, I as VCardTitle, K as VDivider, P as VCard, R as VCardActions, S as VSpacer, X as VChip, it as VProgressLinear, rt as VBtn, w as VCol, z as VDialog } from "../ssr.js";
import { t as _plugin_vue_export_helper_default } from "./_plugin-vue_export-helper-DMwexRDj.js";
import { computed, createBlock, createCommentVNode, createTextVNode, createVNode, mergeModels, mergeProps, openBlock, toDisplayString, useModel, useSSRContext, withCtx } from "vue";
import { ssrInterpolate, ssrRenderComponent } from "vue/server-renderer";
//#region resources/js/Components/Contacts/Emails/MailMessageReaderDialog.vue
var _sfc_main = {
	__name: "MailMessageReaderDialog",
	__ssrInlineRender: true,
	props: /* @__PURE__ */ mergeModels({
		message: {
			type: Object,
			default: null
		},
		loading: Boolean
	}, {
		"modelValue": {
			type: Boolean,
			default: false
		},
		"modelModifiers": {}
	}),
	emits: /* @__PURE__ */ mergeModels(["reload"], ["update:modelValue"]),
	setup(__props, { emit: __emit }) {
		const model = useModel(__props, "modelValue");
		const props = __props;
		const emit = __emit;
		const bodyHtml = computed(() => {
			return props.message?.html || null;
		});
		const bodyText = computed(() => {
			return props.message?.text || "Тело письма не загружено или письмо пустое.";
		});
		function formatDate(value) {
			if (!value) return "—";
			return new Intl.DateTimeFormat("ru-RU", {
				day: "2-digit",
				month: "2-digit",
				year: "numeric",
				hour: "2-digit",
				minute: "2-digit"
			}).format(new Date(value));
		}
		function recipients(list) {
			return list?.map((item) => item.address).join(", ") || "—";
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VDialog, mergeProps({
				modelValue: model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				width: "1200",
				scrollable: ""
			}, _attrs), {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VCard, { class: "rounded border border-blue-900 bg-slate-950" }, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCardTitle, { class: "d-flex justify-space-between align-start" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(`<div data-v-51292b8b${_scopeId}><div class="text-blue-lighten-3 text-base" data-v-51292b8b${_scopeId}>${ssrInterpolate(__props.message?.subject || "Без темы")}</div><div class="text-[11px] text-grey mt-1" data-v-51292b8b${_scopeId}>${ssrInterpolate(formatDate(__props.message?.message_date))}</div></div><div class="d-flex ga-1" data-v-51292b8b${_scopeId}>`);
											_push(ssrRenderComponent(VChip, {
												size: "small",
												color: __props.message?.direction === "incoming" ? "purple" : "blue",
												variant: "tonal"
											}, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) _push(`${ssrInterpolate(__props.message?.direction === "incoming" ? "Входящее" : "Исходящее")}`);
													else return [createTextVNode(toDisplayString(__props.message?.direction === "incoming" ? "Входящее" : "Исходящее"), 1)];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												icon: "mdi-refresh",
												size: "small",
												variant: "text",
												color: "blue",
												loading: __props.loading,
												onClick: ($event) => emit("reload")
											}, null, _parent, _scopeId));
											_push(`</div>`);
										} else return [createVNode("div", null, [createVNode("div", { class: "text-blue-lighten-3 text-base" }, toDisplayString(__props.message?.subject || "Без темы"), 1), createVNode("div", { class: "text-[11px] text-grey mt-1" }, toDisplayString(formatDate(__props.message?.message_date)), 1)]), createVNode("div", { class: "d-flex ga-1" }, [createVNode(VChip, {
											size: "small",
											color: __props.message?.direction === "incoming" ? "purple" : "blue",
											variant: "tonal"
										}, {
											default: withCtx(() => [createTextVNode(toDisplayString(__props.message?.direction === "incoming" ? "Входящее" : "Исходящее"), 1)]),
											_: 1
										}, 8, ["color"]), createVNode(VBtn, {
											icon: "mdi-refresh",
											size: "small",
											variant: "text",
											color: "blue",
											loading: __props.loading,
											onClick: ($event) => emit("reload")
										}, null, 8, ["loading", "onClick"])])];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VDivider, null, null, _parent, _scopeId));
								_push(ssrRenderComponent(VCardText, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VRow, { dense: "" }, {
												default: withCtx((_, _push, _parent, _scopeId) => {
													if (_push) {
														_push(ssrRenderComponent(VCol, {
															cols: "12",
															lg: "6"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`<div class="text-[11px] text-grey" data-v-51292b8b${_scopeId}>From</div><div class="text-sm text-purple-lighten-3" data-v-51292b8b${_scopeId}>${ssrInterpolate(__props.message?.from_name || "")} ${ssrInterpolate(__props.message?.from_address || "—")}</div>`);
																else return [createVNode("div", { class: "text-[11px] text-grey" }, "From"), createVNode("div", { class: "text-sm text-purple-lighten-3" }, toDisplayString(__props.message?.from_name || "") + " " + toDisplayString(__props.message?.from_address || "—"), 1)];
															}),
															_: 1
														}, _parent, _scopeId));
														_push(ssrRenderComponent(VCol, {
															cols: "12",
															lg: "6"
														}, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) {
																	_push(`<div class="text-[11px] text-grey" data-v-51292b8b${_scopeId}>To</div><div class="text-sm text-blue-lighten-3" data-v-51292b8b${_scopeId}>${ssrInterpolate(recipients(__props.message?.to))}</div>`);
																	if (__props.message?.cc?.length) _push(`<div class="mt-1" data-v-51292b8b${_scopeId}><div class="text-[11px] text-grey" data-v-51292b8b${_scopeId}>CC</div><div class="text-sm text-blue-lighten-3" data-v-51292b8b${_scopeId}>${ssrInterpolate(recipients(__props.message?.cc))}</div></div>`);
																	else _push(`<!---->`);
																} else return [
																	createVNode("div", { class: "text-[11px] text-grey" }, "To"),
																	createVNode("div", { class: "text-sm text-blue-lighten-3" }, toDisplayString(recipients(__props.message?.to)), 1),
																	__props.message?.cc?.length ? (openBlock(), createBlock("div", {
																		key: 0,
																		class: "mt-1"
																	}, [createVNode("div", { class: "text-[11px] text-grey" }, "CC"), createVNode("div", { class: "text-sm text-blue-lighten-3" }, toDisplayString(recipients(__props.message?.cc)), 1)])) : createCommentVNode("", true)
																];
															}),
															_: 1
														}, _parent, _scopeId));
													} else return [createVNode(VCol, {
														cols: "12",
														lg: "6"
													}, {
														default: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, "From"), createVNode("div", { class: "text-sm text-purple-lighten-3" }, toDisplayString(__props.message?.from_name || "") + " " + toDisplayString(__props.message?.from_address || "—"), 1)]),
														_: 1
													}), createVNode(VCol, {
														cols: "12",
														lg: "6"
													}, {
														default: withCtx(() => [
															createVNode("div", { class: "text-[11px] text-grey" }, "To"),
															createVNode("div", { class: "text-sm text-blue-lighten-3" }, toDisplayString(recipients(__props.message?.to)), 1),
															__props.message?.cc?.length ? (openBlock(), createBlock("div", {
																key: 0,
																class: "mt-1"
															}, [createVNode("div", { class: "text-[11px] text-grey" }, "CC"), createVNode("div", { class: "text-sm text-blue-lighten-3" }, toDisplayString(recipients(__props.message?.cc)), 1)])) : createCommentVNode("", true)
														]),
														_: 1
													})];
												}),
												_: 1
											}, _parent, _scopeId));
											_push(ssrRenderComponent(VDivider, { class: "my-4" }, null, _parent, _scopeId));
											if (__props.loading) _push(ssrRenderComponent(VProgressLinear, {
												indeterminate: "",
												color: "blue",
												class: "mb-3"
											}, null, _parent, _scopeId));
											else _push(`<!---->`);
											if (bodyHtml.value) _push(`<div class="mail-body bg-white text-black rounded pa-4" data-v-51292b8b${_scopeId}>${bodyHtml.value ?? ""}</div>`);
											else _push(`<pre class="mail-body-text rounded border border-blue-900 bg-slate-900 text-grey-lighten-2 pa-4" data-v-51292b8b${_scopeId}>${ssrInterpolate(bodyText.value)}</pre>`);
										} else return [
											createVNode(VRow, { dense: "" }, {
												default: withCtx(() => [createVNode(VCol, {
													cols: "12",
													lg: "6"
												}, {
													default: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, "From"), createVNode("div", { class: "text-sm text-purple-lighten-3" }, toDisplayString(__props.message?.from_name || "") + " " + toDisplayString(__props.message?.from_address || "—"), 1)]),
													_: 1
												}), createVNode(VCol, {
													cols: "12",
													lg: "6"
												}, {
													default: withCtx(() => [
														createVNode("div", { class: "text-[11px] text-grey" }, "To"),
														createVNode("div", { class: "text-sm text-blue-lighten-3" }, toDisplayString(recipients(__props.message?.to)), 1),
														__props.message?.cc?.length ? (openBlock(), createBlock("div", {
															key: 0,
															class: "mt-1"
														}, [createVNode("div", { class: "text-[11px] text-grey" }, "CC"), createVNode("div", { class: "text-sm text-blue-lighten-3" }, toDisplayString(recipients(__props.message?.cc)), 1)])) : createCommentVNode("", true)
													]),
													_: 1
												})]),
												_: 1
											}),
											createVNode(VDivider, { class: "my-4" }),
											__props.loading ? (openBlock(), createBlock(VProgressLinear, {
												key: 0,
												indeterminate: "",
												color: "blue",
												class: "mb-3"
											})) : createCommentVNode("", true),
											bodyHtml.value ? (openBlock(), createBlock("div", {
												key: 1,
												class: "mail-body bg-white text-black rounded pa-4",
												innerHTML: bodyHtml.value
											}, null, 8, ["innerHTML"])) : (openBlock(), createBlock("pre", {
												key: 2,
												class: "mail-body-text rounded border border-blue-900 bg-slate-900 text-grey-lighten-2 pa-4"
											}, toDisplayString(bodyText.value), 1))
										];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCardActions, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) {
											_push(ssrRenderComponent(VSpacer, null, null, _parent, _scopeId));
											_push(ssrRenderComponent(VBtn, {
												text: "Закрыть",
												variant: "text",
												onClick: ($event) => model.value = false
											}, null, _parent, _scopeId));
										} else return [createVNode(VSpacer), createVNode(VBtn, {
											text: "Закрыть",
											variant: "text",
											onClick: ($event) => model.value = false
										}, null, 8, ["onClick"])];
									}),
									_: 1
								}, _parent, _scopeId));
							} else return [
								createVNode(VCardTitle, { class: "d-flex justify-space-between align-start" }, {
									default: withCtx(() => [createVNode("div", null, [createVNode("div", { class: "text-blue-lighten-3 text-base" }, toDisplayString(__props.message?.subject || "Без темы"), 1), createVNode("div", { class: "text-[11px] text-grey mt-1" }, toDisplayString(formatDate(__props.message?.message_date)), 1)]), createVNode("div", { class: "d-flex ga-1" }, [createVNode(VChip, {
										size: "small",
										color: __props.message?.direction === "incoming" ? "purple" : "blue",
										variant: "tonal"
									}, {
										default: withCtx(() => [createTextVNode(toDisplayString(__props.message?.direction === "incoming" ? "Входящее" : "Исходящее"), 1)]),
										_: 1
									}, 8, ["color"]), createVNode(VBtn, {
										icon: "mdi-refresh",
										size: "small",
										variant: "text",
										color: "blue",
										loading: __props.loading,
										onClick: ($event) => emit("reload")
									}, null, 8, ["loading", "onClick"])])]),
									_: 1
								}),
								createVNode(VDivider),
								createVNode(VCardText, null, {
									default: withCtx(() => [
										createVNode(VRow, { dense: "" }, {
											default: withCtx(() => [createVNode(VCol, {
												cols: "12",
												lg: "6"
											}, {
												default: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, "From"), createVNode("div", { class: "text-sm text-purple-lighten-3" }, toDisplayString(__props.message?.from_name || "") + " " + toDisplayString(__props.message?.from_address || "—"), 1)]),
												_: 1
											}), createVNode(VCol, {
												cols: "12",
												lg: "6"
											}, {
												default: withCtx(() => [
													createVNode("div", { class: "text-[11px] text-grey" }, "To"),
													createVNode("div", { class: "text-sm text-blue-lighten-3" }, toDisplayString(recipients(__props.message?.to)), 1),
													__props.message?.cc?.length ? (openBlock(), createBlock("div", {
														key: 0,
														class: "mt-1"
													}, [createVNode("div", { class: "text-[11px] text-grey" }, "CC"), createVNode("div", { class: "text-sm text-blue-lighten-3" }, toDisplayString(recipients(__props.message?.cc)), 1)])) : createCommentVNode("", true)
												]),
												_: 1
											})]),
											_: 1
										}),
										createVNode(VDivider, { class: "my-4" }),
										__props.loading ? (openBlock(), createBlock(VProgressLinear, {
											key: 0,
											indeterminate: "",
											color: "blue",
											class: "mb-3"
										})) : createCommentVNode("", true),
										bodyHtml.value ? (openBlock(), createBlock("div", {
											key: 1,
											class: "mail-body bg-white text-black rounded pa-4",
											innerHTML: bodyHtml.value
										}, null, 8, ["innerHTML"])) : (openBlock(), createBlock("pre", {
											key: 2,
											class: "mail-body-text rounded border border-blue-900 bg-slate-900 text-grey-lighten-2 pa-4"
										}, toDisplayString(bodyText.value), 1))
									]),
									_: 1
								}),
								createVNode(VCardActions, null, {
									default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
										text: "Закрыть",
										variant: "text",
										onClick: ($event) => model.value = false
									}, null, 8, ["onClick"])]),
									_: 1
								})
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VCard, { class: "rounded border border-blue-900 bg-slate-950" }, {
						default: withCtx(() => [
							createVNode(VCardTitle, { class: "d-flex justify-space-between align-start" }, {
								default: withCtx(() => [createVNode("div", null, [createVNode("div", { class: "text-blue-lighten-3 text-base" }, toDisplayString(__props.message?.subject || "Без темы"), 1), createVNode("div", { class: "text-[11px] text-grey mt-1" }, toDisplayString(formatDate(__props.message?.message_date)), 1)]), createVNode("div", { class: "d-flex ga-1" }, [createVNode(VChip, {
									size: "small",
									color: __props.message?.direction === "incoming" ? "purple" : "blue",
									variant: "tonal"
								}, {
									default: withCtx(() => [createTextVNode(toDisplayString(__props.message?.direction === "incoming" ? "Входящее" : "Исходящее"), 1)]),
									_: 1
								}, 8, ["color"]), createVNode(VBtn, {
									icon: "mdi-refresh",
									size: "small",
									variant: "text",
									color: "blue",
									loading: __props.loading,
									onClick: ($event) => emit("reload")
								}, null, 8, ["loading", "onClick"])])]),
								_: 1
							}),
							createVNode(VDivider),
							createVNode(VCardText, null, {
								default: withCtx(() => [
									createVNode(VRow, { dense: "" }, {
										default: withCtx(() => [createVNode(VCol, {
											cols: "12",
											lg: "6"
										}, {
											default: withCtx(() => [createVNode("div", { class: "text-[11px] text-grey" }, "From"), createVNode("div", { class: "text-sm text-purple-lighten-3" }, toDisplayString(__props.message?.from_name || "") + " " + toDisplayString(__props.message?.from_address || "—"), 1)]),
											_: 1
										}), createVNode(VCol, {
											cols: "12",
											lg: "6"
										}, {
											default: withCtx(() => [
												createVNode("div", { class: "text-[11px] text-grey" }, "To"),
												createVNode("div", { class: "text-sm text-blue-lighten-3" }, toDisplayString(recipients(__props.message?.to)), 1),
												__props.message?.cc?.length ? (openBlock(), createBlock("div", {
													key: 0,
													class: "mt-1"
												}, [createVNode("div", { class: "text-[11px] text-grey" }, "CC"), createVNode("div", { class: "text-sm text-blue-lighten-3" }, toDisplayString(recipients(__props.message?.cc)), 1)])) : createCommentVNode("", true)
											]),
											_: 1
										})]),
										_: 1
									}),
									createVNode(VDivider, { class: "my-4" }),
									__props.loading ? (openBlock(), createBlock(VProgressLinear, {
										key: 0,
										indeterminate: "",
										color: "blue",
										class: "mb-3"
									})) : createCommentVNode("", true),
									bodyHtml.value ? (openBlock(), createBlock("div", {
										key: 1,
										class: "mail-body bg-white text-black rounded pa-4",
										innerHTML: bodyHtml.value
									}, null, 8, ["innerHTML"])) : (openBlock(), createBlock("pre", {
										key: 2,
										class: "mail-body-text rounded border border-blue-900 bg-slate-900 text-grey-lighten-2 pa-4"
									}, toDisplayString(bodyText.value), 1))
								]),
								_: 1
							}),
							createVNode(VCardActions, null, {
								default: withCtx(() => [createVNode(VSpacer), createVNode(VBtn, {
									text: "Закрыть",
									variant: "text",
									onClick: ($event) => model.value = false
								}, null, 8, ["onClick"])]),
								_: 1
							})
						]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Contacts/Emails/MailMessageReaderDialog.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var MailMessageReaderDialog_default = /* @__PURE__ */ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-51292b8b"]]);
//#endregion
export { MailMessageReaderDialog_default as t };

//# sourceMappingURL=MailMessageReaderDialog-BQOlI2wo.js.map