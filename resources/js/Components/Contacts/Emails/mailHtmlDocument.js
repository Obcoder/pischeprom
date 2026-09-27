// The iframe also has an opaque origin and no script/form/navigation permissions.
export function mailHtmlDocument(html) {
    return `<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src 'none'; base-uri 'none'; form-action 'none'"><meta name="referrer" content="no-referrer"><base target="_blank"><style>html{color-scheme:light}body{margin:16px;font:14px/1.5 Arial,sans-serif;color:#111;background:white;overflow-wrap:anywhere}img{max-width:100%;height:auto}pre{white-space:pre-wrap}table{max-width:100%}</style></head><body>${String(html || '')}</body></html>`
}
