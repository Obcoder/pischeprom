export function useYandexMetrica(counterId) {
    function enabled() {
        return Boolean(counterId)
            && typeof window !== "undefined"
            && typeof window.ym === "function";
    }

    function reachGoal(goal, params = {}) {
        if (!enabled()) return;

        window.ym(counterId, "reachGoal", goal, params);
    }

    function ecommerceViewItem(item) {
        if (typeof window === "undefined") return;

        window.dataLayer = window.dataLayer || [];

        window.dataLayer.push({
            event: "view_item",
            ecommerce: {
                currency: item.currency || "RUB",
                items: [
                    {
                        item_id: item.id,
                        item_name: item.name,
                        item_category: item.category,
                        price: Number(item.price || 0),
                    },
                ],
            },
        });
    }

    return {
        reachGoal,
        ecommerceViewItem,
    };
}
