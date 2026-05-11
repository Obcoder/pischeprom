import { ref } from "vue";
import axios from "axios";
import { route } from "ziggy-js";

export function useGoodPriceTypeValues(goodId) {
    const values = ref([]);
    const loading = ref(false);
    const saving = ref(false);
    const deleting = ref({});

    async function fetchValues() {
        loading.value = true;

        try {
            const { data } = await axios.get(
                route("api.goods.price-type-values.index", goodId)
            );

            values.value = Array.isArray(data) ? data : [];

            return values.value;
        } finally {
            loading.value = false;
        }
    }

    async function storeValue(payload) {
        saving.value = true;

        try {
            const { data } = await axios.post(
                route("api.goods.price-type-values.store", goodId),
                payload
            );

            const exists = values.value.some((item) => item.id === data.id);

            if (exists) {
                values.value = values.value.map((item) =>
                    item.id === data.id ? data : item
                );
            } else {
                values.value.unshift(data);
            }

            return data;
        } finally {
            saving.value = false;
        }
    }

    async function updateValue(valueId, payload) {
        saving.value = true;

        try {
            const { data } = await axios.patch(
                route("api.goods.price-type-values.update", {
                    good: goodId,
                    value: valueId,
                }),
                payload
            );

            values.value = values.value.map((item) =>
                item.id === data.id ? data : item
            );

            return data;
        } finally {
            saving.value = false;
        }
    }

    async function deleteValue(valueId) {
        deleting.value[valueId] = true;

        try {
            await axios.delete(
                route("api.goods.price-type-values.destroy", {
                    good: goodId,
                    value: valueId,
                })
            );

            values.value = values.value.filter((item) => item.id !== valueId);
        } finally {
            deleting.value[valueId] = false;
        }
    }

    return {
        values,
        loading,
        saving,
        deleting,
        fetchValues,
        storeValue,
        updateValue,
        deleteValue,
    };
}
