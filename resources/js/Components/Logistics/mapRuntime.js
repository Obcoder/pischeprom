let runtimePromise = null

export function loadMapRuntime() {
    if (!runtimePromise) {
        runtimePromise = Promise.all([
            import('maplibre-gl'),
            import('pmtiles'),
            import('maplibre-gl/dist/maplibre-gl.css'),
        ]).then(([mapModule, pmtilesModule]) => {
            const maplibregl = mapModule.default || mapModule
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
