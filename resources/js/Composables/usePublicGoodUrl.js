import { usePage } from '@inertiajs/vue3'
import { route as ziggyRoute } from 'ziggy-js'

export function usePublicGoodUrl() {
    const page = usePage()

    const route = (name, params = {}, absolute = true) => {
        return ziggyRoute(name, params, absolute, page.props.ziggy)
    }

    function goodPublicSlug(good) {
        if (!good) {
            return null
        }

        if (good.seo?.is_active !== false && good.seo?.slug_override) {
            return good.seo.slug_override
        }

        return good.slug || null
    }

    function goodPublicUrl(good) {
        const slug = goodPublicSlug(good)

        if (!slug) {
            return null
        }

        return route('public.goods.show', {
            good: slug,
        })
    }

    return {
        goodPublicSlug,
        goodPublicUrl,
    }
}
