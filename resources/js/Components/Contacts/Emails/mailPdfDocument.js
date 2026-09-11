import { getDocument, GlobalWorkerOptions, TextLayer } from 'pdfjs-dist'
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?worker&url'

// Ship fonts, character maps and image decoders with the app. Attachments never
// need to leave our origin to load a viewer or one of its supporting assets.
const assets = import.meta.glob('/node_modules/pdfjs-dist/{cmaps,standard_fonts,wasm}/*.{bcmap,pfb,ttf,wasm,js}', {
    eager: true,
    query: '?url',
    import: 'default',
})
const directories = { cMapUrl: 'cmaps', standardFontDataUrl: 'standard_fonts', wasmUrl: 'wasm' }

class BundledPdfData {
    async fetch({ kind, filename }) {
        const url = assets[`/node_modules/pdfjs-dist/${directories[kind]}/${filename}`]
        if (!url) throw new Error('PDF supporting asset not found')
        const response = await fetch(url)
        if (!response.ok) throw new Error('PDF supporting asset could not be loaded')
        return new Uint8Array(await response.arrayBuffer())
    }
}

GlobalWorkerOptions.workerSrc = workerUrl

export function loadMailPdf(url) {
    return getDocument({
        url,
        withCredentials: true,
        isEvalSupported: false,
        enableXfa: false,
        useWorkerFetch: false,
        BinaryDataFactory: BundledPdfData,
    })
}

export { TextLayer }
