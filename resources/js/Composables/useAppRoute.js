import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { route as ziggyRoute } from 'ziggy-js'

const DEFAULT_BASE_URL = 'https://пищепром-сервер.рф'

function trimTrailingSlash(value) {
    return String(value || DEFAULT_BASE_URL).replace(/\/+$/, '')
}

function normalizeLocation(location, baseUrl = DEFAULT_BASE_URL) {
    if (location instanceof URL) {
        return location
    }

    if (typeof location === 'string' && location.length > 0) {
        try {
            return new URL(location)
        } catch {
            return new URL(location, baseUrl)
        }
    }

    if (typeof window !== 'undefined' && window.location) {
        return window.location
    }

    return new URL(baseUrl)
}

function firstParam(params) {
    if (params === null || params === undefined) {
        return ''
    }

    if (typeof params === 'string' || typeof params === 'number') {
        return params
    }

    if (typeof params === 'object') {
        return params.slug
            ?? params.category
            ?? params.good
            ?? params.id
            ?? ''
    }

    return ''
}

function fallbackPath(name, params = {}) {
    const param = firstParam(params)

    switch (name) {
        case 'home':
            return '/'

        case 'goods.published':
            return '/goods/published'

        case 'public.goods.index':
            return '/g'

        case 'public.goods.show':
            return param
                ? `/g/${encodeURIComponent(param)}`
                : '/g'

        case 'public.fields.show':
            return param
                ? `/подборки/${encodeURIComponent(param)}`
                : '/подборки'

        case 'category.show':
            return param
                ? `/категория/${encodeURIComponent(param)}`
                : '#'

        case 'web.sesame':
            return '/кунжут'

        case 'legal.privacy':
            return '/privacy-policy'

        case 'legal.terms':
            return '/terms'

        case 'legal.personal-data-consent':
            return '/personal-data-consent'

        default:
            return '#'
    }
}

function makeAbsolute(path, baseUrl) {
    if (!path || path === '#') {
        return '#'
    }

    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path
    }

    return `${trimTrailingSlash(baseUrl)}${path.startsWith('/') ? path : `/${path}`}`
}

export function useAppRoute() {
    const page = usePage()

    const ziggyConfig = computed(() => {
        const source =
            page.props?.ziggy
            || globalThis.Ziggy
            || {}

        const baseUrl = source.url || DEFAULT_BASE_URL

        return {
            ...source,
            defaults: source.defaults || {},
            routes: source.routes || {},
            location: normalizeLocation(source.location, baseUrl),
        }
    })

    function hasRoute(name) {
        return Boolean(ziggyConfig.value.routes?.[name])
    }

    function route(name, params = {}, absolute = false) {
        const config = ziggyConfig.value

        if (config.routes?.[name]) {
            try {
                return ziggyRoute(name, params, absolute, config)
            } catch (error) {
                if (import.meta.env.DEV) {
                    console.warn(`[useAppRoute] Ziggy fallback used for "${name}"`, error)
                }
            }
        }

        const path = fallbackPath(name, params)

        return absolute
            ? makeAbsolute(path, config.url || DEFAULT_BASE_URL)
            : path
    }

    return {
        route,
        hasRoute,
        ziggyConfig,
    }
}
