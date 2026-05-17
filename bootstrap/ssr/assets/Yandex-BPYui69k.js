import { C as VRow, D as VDataTable, T as VContainer, w as VCol } from "../ssr.js";
import { t as _sfc_main$1 } from "./VerwalterLayout-BLmFLvbQ.js";
import axios from "axios";
import { createVNode, onMounted, ref, useSSRContext, withCtx } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
import { useHead } from "@vueuse/head";
//#region resources/js/Pages/Ameise/Yandex.vue
var _sfc_main = /* @__PURE__ */ Object.assign({ layout: _sfc_main$1 }, {
	__name: "Yandex",
	__ssrInlineRender: true,
	setup(__props) {
		const yandexRequests = ref([]);
		function indexYandexRequests() {
			axios.get(route("yandex-requests.index")).then(function(response) {
				yandexRequests.value = response.data;
			}).catch(function(error) {
				console.log(error);
			});
		}
		onMounted(() => {
			indexYandexRequests();
		});
		useHead({
			title: `Работа с возможностями Yandex`,
			meta: [{
				name: "description",
				content: `Работа с возможностями Yandex`
			}]
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(ssrRenderComponent(VContainer, _attrs, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(ssrRenderComponent(VRow, null, {
						default: withCtx((_, _push, _parent, _scopeId) => {
							if (_push) {
								_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
								_push(ssrRenderComponent(VCol, null, {
									default: withCtx((_, _push, _parent, _scopeId) => {
										if (_push) _push(ssrRenderComponent(VDataTable, { items: yandexRequests.value }, null, _parent, _scopeId));
										else return [createVNode(VDataTable, { items: yandexRequests.value }, null, 8, ["items"])];
									}),
									_: 1
								}, _parent, _scopeId));
								_push(ssrRenderComponent(VCol, null, null, _parent, _scopeId));
							} else return [
								createVNode(VCol),
								createVNode(VCol, null, {
									default: withCtx(() => [createVNode(VDataTable, { items: yandexRequests.value }, null, 8, ["items"])]),
									_: 1
								}),
								createVNode(VCol)
							];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [createVNode(VRow, null, {
						default: withCtx(() => [
							createVNode(VCol),
							createVNode(VCol, null, {
								default: withCtx(() => [createVNode(VDataTable, { items: yandexRequests.value }, null, 8, ["items"])]),
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Yandex.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Yandex-BPYui69k.js.map