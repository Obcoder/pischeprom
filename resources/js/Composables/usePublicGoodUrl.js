import { usePage } from '@inertiajs/vue3'
import { route as ziggyRoute } from 'ziggy-js'

function isAbsoluteUrl(value) {
    return typeof value === 'string'
        && (
            value.startsWith('http://')
            || value.startsWith('https://')
        )
}

function normalizeLocation(location, fallbackUrl = 'https://пищепром-сервер.рф') {
    if (location instanceof URL) {
        return location
    }

    if (typeof location === 'string' && location.length > 0) {
        try {
            return new URL(location)
        } catch {
            return new URL(fallbackUrl)
        }
    }

    if (typeof window !== 'undefined' && window.location) {
        return window.location
    }

    return new URL(fallbackUrl)
}

function normalizeZiggy(page) {
    const source =
        page.props?.ziggy
        || globalThis.Ziggy
        || {}

    return {
        ...source,
        defaults: source.defaults || {},
        routes: source.routes || {},
        location: normalizeLocation(source.location, source.url),
    }
}

function goodParam(good) {
    if (good === null || good === undefined) {
        return ''
    }

    if (typeof good === 'string' || typeof good === 'number') {
        return good
    }

    return good.slug || good.id || ''
}

function fallbackGoodUrl(good, absolute = true, baseUrl = 'https://пищепром-сервер.рф') {
    const param = goodParam(good)

    if (!param) {
        return absolute ? `${baseUrl.replace(/\/+$/, '')}/g` : '/g'
    }

    const path = `/g/${encodeURIComponent(param)}`

    return absolute
        ? `${baseUrl.replace(/\/+$/, '')}${path}`
        : path
}

export function usePublicGoodUrl() {
    const page = usePage()

    function goodPublicUrl(good, absolute = true) {
        const ziggy = normalizeZiggy(page)

        try {
            if (ziggy.routes?.['public.goods.show']) {
                return ziggyRoute(
                    'public.goods.show',
                    goodParam(good),
                    absolute,
                    ziggy
                )
            }
        } catch (error) {
            if (import.meta.env.DEV) {
                console.warn('[usePublicGoodUrl] Ziggy fallback used:', error)
            }
        }

        const baseUrl = ziggy.url || 'https://пищепром-сервер.рф'

        return fallbackGoodUrl(good, absolute, baseUrl)
    }

    function normalizeGoodUrl(value) {
        if (!value) {
            return ''
        }

        if (isAbsoluteUrl(value)) {
            return value
        }

        const ziggy = normalizeZiggy(page)
        const baseUrl = String(ziggy.url || 'https://пищепром-сервер.рф').replace(/\/+$/, '')
        const path = String(value).startsWith('/') ? value : `/${value}`

        return `${baseUrl}${path}`
    }

    return {
        goodPublicUrl,
        normalizeGoodUrl,
    }
}
