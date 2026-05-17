import { C as VRow, D as VDataTable, T as VContainer, dt as useDate, w as VCol } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import { Fragment, createBlock, createTextVNode, createVNode, onMounted, openBlock, ref, renderList, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { route } from "ziggy-js";
import { ssrInterpolate, ssrRenderComponent, ssrRenderList } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Ameise/ContactsCentre.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "ContactsCentre",
	__ssrInlineRender: true,
	setup(__props) {
		const date = useDate();
		const emails = ref();
		const sendings = ref([]);
		const headersEmails = [
			{
				title: "date",
				key: "created_at"
			},
			{
				title: "email",
				key: "address"
			},
			{
				title: "кол-во отправок",
				key: "sendings.length"
			}
		];
		const headersSendings = [
			{
				title: "Date",
				key: "created_at"
			},
			{
				title: "Subject",
				key: "subject"
			},
			{
				title: "Email",
				key: "email"
			}
		];
		function indexEmails() {
			axios.get(route("emails.index")).then(function(response) {
				emails.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		function indexSendings() {
			axios.get(route("sendings.index")).then(function(response) {
				sendings.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		const daysSinceSending = (createdAt) => {
			const sendingDate = new Date(createdAt);
			const diffTime = /* @__PURE__ */ new Date() - sendingDate;
			return Math.floor(diffTime / (1e3 * 60 * 60 * 24));
		};
		onMounted(() => {
			indexEmails();
			indexSendings();
		});
		useHead({
			title: `Contacts: emails, telephones, uris, addresses`,
			meta: [{
				name: "description",
				content: `Все contacts in db`
			}]
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VRow, null, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCol, { lg: "3" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VDataTable, {
											items: emails.value,
											headers: headersEmails,
											density: "compact",
											hover: ""
										}, {
											"item.created_at": withCtx(({ item }, _push, _parent, _scopeId) => {
												if (_push) _push(`<span class="text-xs"${_scopeId}>${ssrInterpolate(unref(date).format(item.created_at, "fullDate"))}</span>`);
												else return [createVNode("span", { class: "text-xs" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VDataTable, {
											items: emails.value,
											headers: headersEmails,
											density: "compact",
											hover: ""
										}, {
											"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
											_: 1
										}, 8, ["items"])];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCol, { lg: "6" }, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VDataTable, {
											items: sendings.value,
											headers: headersSendings,
											"items-per-page": "36",
											density: "compact",
											hover: ""
										}, {
											"item.created_at": withCtx(({ item }, _push, _parent, _scopeId) => {
												if (_push) _push(ssrRenderComponent(VRow, null, {
													default: withCtx((_, _push, _parent, _scopeId) => {
														if (_push) {
															_push(ssrRenderComponent(VCol, { cols: "9" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`<span${_scopeId}>${ssrInterpolate(unref(date).format(item.created_at, "fullDateTime"))}</span>`);
																	else return [createVNode("span", null, toDisplayString(unref(date).format(item.created_at, "fullDateTime")), 1)];
																}),
																_: 2
															}, _parent, _scopeId));
															_push(ssrRenderComponent(VCol, { cols: "3" }, {
																default: withCtx((_, _push, _parent, _scopeId) => {
																	if (_push) _push(`<span${_scopeId}>${ssrInterpolate(daysSinceSending(item.created_at))}</span>`);
																	else return [createVNode("span", null, toDisplayString(daysSinceSending(item.created_at)), 1)];
																}),
																_: 2
															}, _parent, _scopeId));
														} else return [createVNode(VCol, { cols: "9" }, {
															default: withCtx(() => [createVNode("span", null, toDisplayString(unref(date).format(item.created_at, "fullDateTime")), 1)]),
															_: 2
														}, 1024), createVNode(VCol, { cols: "3" }, {
															default: withCtx(() => [createVNode("span", null, toDisplayString(daysSinceSending(item.created_at)), 1)]),
															_: 2
														}, 1024)];
													}),
													_: 2
												}, _parent, _scopeId));
												else return [createVNode(VRow, null, {
													default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
														default: withCtx(() => [createVNode("span", null, toDisplayString(unref(date).format(item.created_at, "fullDateTime")), 1)]),
														_: 2
													}, 1024), createVNode(VCol, { cols: "3" }, {
														default: withCtx(() => [createVNode("span", null, toDisplayString(daysSinceSending(item.created_at)), 1)]),
														_: 2
													}, 1024)]),
													_: 2
												}, 1024)];
											}),
											"item.subject": withCtx(({ item }, _push, _parent, _scopeId) => {
												if (_push) _push(`<span class="text-xs"${_scopeId}>${ssrInterpolate(item.subject)}</span>`);
												else return [createVNode("span", { class: "text-xs" }, toDisplayString(item.subject), 1)];
											}),
											"item.email": withCtx(({ item }, _push, _parent, _scopeId) => {
												if (_push) {
													_push(`<div${_scopeId}>${ssrInterpolate(item.email.address)}</div><div${_scopeId}><!--[-->`);
													ssrRenderList(item.email.units, (unit) => {
														_push(ssrRenderComponent(unref(Link), { href: unref(route)("web.unit.show", unit.id) }, {
															default: withCtx((_, _push, _parent, _scopeId) => {
																if (_push) _push(`${ssrInterpolate(unit.name)}`);
																else return [createTextVNode(toDisplayString(unit.name), 1)];
															}),
															_: 2
														}, _parent, _scopeId));
													});
													_push(`<!--]--></div>`);
												} else return [createVNode("div", null, toDisplayString(item.email.address), 1), createVNode("div", null, [(openBlock(true), createBlock(Fragment, null, renderList(item.email.units, (unit) => {
													return openBlock(), createBlock(unref(Link), { href: unref(route)("web.unit.show", unit.id) }, {
														default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
														_: 2
													}, 1032, ["href"]);
												}), 256))])];
											}),
											_: 1
										}, _parent, _scopeId));
										else return [createVNode(VDataTable, {
											items: sendings.value,
											headers: headersSendings,
											"items-per-page": "36",
											density: "compact",
											hover: ""
										}, {
											"item.created_at": withCtx(({ item }) => [createVNode(VRow, null, {
												default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
													default: withCtx(() => [createVNode("span", null, toDisplayString(unref(date).format(item.created_at, "fullDateTime")), 1)]),
													_: 2
												}, 1024), createVNode(VCol, { cols: "3" }, {
													default: withCtx(() => [createVNode("span", null, toDisplayString(daysSinceSending(item.created_at)), 1)]),
													_: 2
												}, 1024)]),
												_: 2
											}, 1024)]),
											"item.subject": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.subject), 1)]),
											"item.email": withCtx(({ item }) => [createVNode("div", null, toDisplayString(item.email.address), 1), createVNode("div", null, [(openBlock(true), createBlock(Fragment, null, renderList(item.email.units, (unit) => {
												return openBlock(), createBlock(unref(Link), { href: unref(route)("web.unit.show", unit.id) }, {
													default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
													_: 2
												}, 1032, ["href"]);
											}), 256))])]),
											_: 1
										}, 8, ["items"])];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
							} else return [
								createVNode(VCol, { lg: "3" }, {
									default: withCtx(() => [createVNode(VDataTable, {
										items: emails.value,
										headers: headersEmails,
										density: "compact",
										hover: ""
									}, {
										"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
										_: 1
									}, 8, ["items"])]),
									_: 1
								}),
								createVNode(VCol, { lg: "6" }, {
									default: withCtx(() => [createVNode(VDataTable, {
										items: sendings.value,
										headers: headersSendings,
										"items-per-page": "36",
										density: "compact",
										hover: ""
									}, {
										"item.created_at": withCtx(({ item }) => [createVNode(VRow, null, {
											default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
												default: withCtx(() => [createVNode("span", null, toDisplayString(unref(date).format(item.created_at, "fullDateTime")), 1)]),
												_: 2
											}, 1024), createVNode(VCol, { cols: "3" }, {
												default: withCtx(() => [createVNode("span", null, toDisplayString(daysSinceSending(item.created_at)), 1)]),
												_: 2
											}, 1024)]),
											_: 2
										}, 1024)]),
										"item.subject": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.subject), 1)]),
										"item.email": withCtx(({ item }) => [createVNode("div", null, toDisplayString(item.email.address), 1), createVNode("div", null, [(openBlock(true), createBlock(Fragment, null, renderList(item.email.units, (unit) => {
											return openBlock(), createBlock(unref(Link), { href: unref(route)("web.unit.show", unit.id) }, {
												default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
												_: 2
											}, 1032, ["href"]);
										}), 256))])]),
										_: 1
									}, 8, ["items"])]),
									_: 1
								}),
								createVNode(VCol)
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol, { lg: "3" }, {
								default: withCtx(() => [createVNode(VDataTable, {
									items: emails.value,
									headers: headersEmails,
									density: "compact",
									hover: ""
								}, {
									"item.created_at": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(unref(date).format(item.created_at, "fullDate")), 1)]),
									_: 1
								}, 8, ["items"])]),
								_: 1
							}),
							createVNode(VCol, { lg: "6" }, {
								default: withCtx(() => [createVNode(VDataTable, {
									items: sendings.value,
									headers: headersSendings,
									"items-per-page": "36",
									density: "compact",
									hover: ""
								}, {
									"item.created_at": withCtx(({ item }) => [createVNode(VRow, null, {
										default: withCtx(() => [createVNode(VCol, { cols: "9" }, {
											default: withCtx(() => [createVNode("span", null, toDisplayString(unref(date).format(item.created_at, "fullDateTime")), 1)]),
											_: 2
										}, 1024), createVNode(VCol, { cols: "3" }, {
											default: withCtx(() => [createVNode("span", null, toDisplayString(daysSinceSending(item.created_at)), 1)]),
											_: 2
										}, 1024)]),
										_: 2
									}, 1024)]),
									"item.subject": withCtx(({ item }) => [createVNode("span", { class: "text-xs" }, toDisplayString(item.subject), 1)]),
									"item.email": withCtx(({ item }) => [createVNode("div", null, toDisplayString(item.email.address), 1), createVNode("div", null, [(openBlock(true), createBlock(Fragment, null, renderList(item.email.units, (unit) => {
										return openBlock(), createBlock(unref(Link), { href: unref(route)("web.unit.show", unit.id) }, {
											default: withCtx(() => [createTextVNode(toDisplayString(unit.name), 1)]),
											_: 2
										}, 1032, ["href"]);
									}), 256))])]),
									_: 1
								}, 8, ["items"])]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/ContactsCentre.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=ContactsCentre-DNjCIpgw.js.map