import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { route as ziggyRoute } from "ziggy-js";

export function useAppRoute() {
    const page = usePage();

    const ziggyConfig = computed(() => {
        const source = page.props.ziggy || {};

        return {
            ...source,
            defaults: source.defaults || {},
            routes: source.routes || {},
            location: source.location
                ? (
                    typeof source.location === "string"
                        ? new URL(source.location)
                        : source.location
                )
                : undefined,
        };
    });

    function route(name, params = {}, absolute = true) {
        const config = ziggyConfig.value;

        if (!config.routes || !Object.prototype.hasOwnProperty.call(config.routes, name)) {
            if (import.meta.env.DEV) {
                console.warn(`[Ziggy] Route "${name}" is missing in Ziggy config`);
            }

            return "#";
        }

        return ziggyRoute(name, params, absolute, config);
    }

    return {
        route,
    };
}
