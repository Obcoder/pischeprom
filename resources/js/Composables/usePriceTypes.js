import { ref } from "vue";
import axios from "axios";
import { route } from "ziggy-js";

export function usePriceTypes() {
    const priceTypes = ref([]);
    const loading = ref(false);
    const saving = ref(false);
    const deleting = ref({});

    async function fetchPriceTypes(params = {}) {
        loading.value = true;

        try {
            const { data } = await axios.get(route("price-types.index"), {
                params,
            });

            priceTypes.value = Array.isArray(data) ? data : data.data || [];

            return priceTypes.value;
        } finally {
            loading.value = false;
        }
    }

    async function storePriceType(payload) {
        saving.value = true;

        try {
            const { data } = await axios.post(route("price-types.store"), payload);

            priceTypes.value.push(data);
            priceTypes.value.sort((a, b) => {
                const orderA = Number(a.sort_order || 100);
                const orderB = Number(b.sort_order || 100);

                if (orderA !== orderB) {
                    return orderA - orderB;
                }

                return String(a.name || "").localeCompare(String(b.name || ""));
            });

            return data;
        } finally {
            saving.value = false;
        }
    }

    async function updatePriceType(priceTypeId, payload) {
        saving.value = true;

        try {
            const { data } = await axios.patch(
                route("price-types.update", priceTypeId),
                payload
            );

            priceTypes.value = priceTypes.value.map((item) =>
                item.id === data.id ? data : item
            );

            return data;
        } finally {
            saving.value = false;
        }
    }

    async function deletePriceType(priceTypeId) {
        deleting.value[priceTypeId] = true;

        try {
            await axios.delete(route("price-types.destroy", priceTypeId));

            priceTypes.value = priceTypes.value.filter((item) => item.id !== priceTypeId);
        } finally {
            deleting.value[priceTypeId] = false;
        }
    }

    return {
        priceTypes,
        loading,
        saving,
        deleting,
        fetchPriceTypes,
        storePriceType,
        updatePriceType,
        deletePriceType,
    };
}
