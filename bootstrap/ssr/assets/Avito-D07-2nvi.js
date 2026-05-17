import axios from "axios";
import { mergeProps, onMounted, ref, useSSRContext } from "vue";
import { ssrInterpolate, ssrRenderAttrs, ssrRenderList } from "vue/server-renderer";
//#region resources/js/Pages/Ameise/Avito.vue
var _sfc_main = {
	__name: "Avito",
	__ssrInlineRender: true,
	setup(__props) {
		const ads = ref([]);
		const error = ref(null);
		const loading = ref(false);
		const fetchAds = async () => {
			loading.value = true;
			try {
				ads.value = (await axios.get("/api/avito/user", { headers: { Authorization: `Bearer ${localStorage.getItem("token")}` } })).data.data;
			} catch (err) {
				error.value = "Ошибка при загрузке объявлений";
				console.error(err);
			} finally {
				loading.value = false;
			}
		};
		onMounted(fetchAds);
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${ssrRenderAttrs(mergeProps({ class: "container mx-auto p-4" }, _attrs))}><h2 class="text-2xl font-bold mb-4">Объявления с Avito</h2>`);
			if (loading.value) _push(`<div class="text-center"><p>Загрузка...</p></div>`);
			else if (error.value) _push(`<div class="text-red-500">${ssrInterpolate(error.value)}</div>`);
			else if (ads.value.length === 0) _push(`<div class="text-gray-500"> Нет объявлений </div>`);
			else {
				_push(`<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"><!--[-->`);
				ssrRenderList(ads.value, (ad) => {
					_push(`<div class="border p-4 rounded-lg shadow"><h3 class="text-lg font-semibold">${ssrInterpolate(ad.title)}</h3><p class="text-gray-600">${ssrInterpolate(ad.description)}</p><p class="text-green-500 font-bold">${ssrInterpolate(ad.price)}</p></div>`);
				});
				_push(`<!--]--></div>`);
			}
			_push(`</div>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Ameise/Avito.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
//#endregion
export { _sfc_main as default };

//# sourceMappingURL=Avito-D07-2nvi.js.map