import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'

let runtimePromise = null

export function loadMapRuntime() {
    if (!runtimePromise) {
        runtimePromise = Promise.all([
            import('maplibre-gl'),
            import('pmtiles'),
            import('maplibre-gl/dist/maplibre-gl.css'),
        ]).then(([mapModule, pmtilesModule]) => {
            const maplibregl = mapModule.default || mapModule
            // MapLibre GL JS 6 no longer embeds its worker in bundler builds.
            // Vite's worker pipeline makes the ESM worker self-contained; a
            // plain ?url import silently leaves vector sources unloaded.
            maplibregl.setWorkerUrl(maplibreWorkerUrl)
            const protocol = new pmtilesModule.Protocol()
            maplibregl.addProtocol('pmtiles', protocol.tile)

            return { maplibregl, protocol }
        }).catch((error) => {
            runtimePromise = null
            throw error
        })
    }

    return runtimePromise
}
