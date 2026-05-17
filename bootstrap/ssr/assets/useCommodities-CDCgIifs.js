import axios from "axios";
import { ref } from "vue";
import { route } from "ziggy-js";
//#region resources/js/Composables/useCommodities.js
function useCommodities() {
	const commodities = ref([]);
	const commodity = ref(null);
	const history = ref([]);
	const stats = ref({
		weekly: [],
		monthly: [],
		yearly: []
	});
	const loadingCommodities = ref(false);
	const savingCommodity = ref(false);
	const deletingCommodity = ref(false);
	const totalItems = ref(0);
	const options = ref({
		page: 1,
		itemsPerPage: 50,
		sortBy: [{
			key: "created_at",
			order: "desc"
		}]
	});
	const filters = ref({
		search: "",
		check_id: null,
		has_ava: "all",
		created_from: null,
		created_to: null
	});
	function getSortParams() {
		const sort = options.value.sortBy?.[0];
		return {
			sort_by: sort?.key || "created_at",
			sort_desc: (sort?.order || "desc") === "desc"
		};
	}
	async function indexCommodities(newOptions = null) {
		if (newOptions) options.value = {
			...options.value,
			...newOptions
		};
		loadingCommodities.value = true;
		try {
			const response = await axios.get(route("commodities.index"), { params: {
				page: options.value.page,
				per_page: options.value.itemsPerPage || 50,
				search: filters.value.search || null,
				check_id: filters.value.check_id || null,
				has_ava: filters.value.has_ava || "all",
				created_from: filters.value.created_from || null,
				created_to: filters.value.created_to || null,
				...getSortParams()
			} });
			commodities.value = response.data.data || [];
			totalItems.value = response.data.meta?.total || 0;
		} catch (error) {
			console.error("indexCommodities error:", error);
		} finally {
			loadingCommodities.value = false;
		}
	}
	async function showCommodity(id) {
		loadingCommodities.value = true;
		try {
			const response = await axios.get(route("commodities.show", id));
			commodity.value = response.data.data;
			history.value = response.data.history || [];
			stats.value = response.data.stats || {
				weekly: [],
				monthly: [],
				yearly: []
			};
			return response.data;
		} catch (error) {
			console.error("showCommodity error:", error);
			throw error;
		} finally {
			loadingCommodities.value = false;
		}
	}
	async function storeCommodity(payload) {
		savingCommodity.value = true;
		try {
			return (await axios.post(route("commodities.store"), payload)).data.data;
		} catch (error) {
			console.error("storeCommodity error:", error);
			throw error;
		} finally {
			savingCommodity.value = false;
		}
	}
	async function updateCommodity(id, payload) {
		savingCommodity.value = true;
		try {
			return (await axios.patch(route("commodities.update", id), payload)).data.data;
		} catch (error) {
			console.error("updateCommodity error:", error);
			throw error;
		} finally {
			savingCommodity.value = false;
		}
	}
	async function destroyCommodity(id) {
		deletingCommodity.value = true;
		try {
			await axios.delete(route("commodities.destroy", id));
		} catch (error) {
			console.error("destroyCommodity error:", error);
			throw error;
		} finally {
			deletingCommodity.value = false;
		}
	}
	function resetCommodityFilters() {
		filters.value = {
			search: "",
			check_id: null,
			has_ava: "all",
			created_from: null,
			created_to: null
		};
		options.value.page = 1;
	}
	return {
		commodities,
		commodity,
		history,
		stats,
		loadingCommodities,
		savingCommodity,
		deletingCommodity,
		totalItems,
		options,
		filters,
		indexCommodities,
		showCommodity,
		storeCommodity,
		updateCommodity,
		destroyCommodity,
		resetCommodityFilters
	};
}
//#endregion
export { useCommodities as t };

//# sourceMappingURL=useCommodities-CDCgIifs.js.map