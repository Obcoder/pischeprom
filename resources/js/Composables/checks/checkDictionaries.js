/** Read both plain dictionaries and paginated Laravel resources without truncating options. */
export async function loadCheckDictionary(get, url, params = {}, signal) {
    const items = new Map()
    let page = 1

    while (true) {
        const response = await get(url, { params: { ...params, page }, signal })
        const body = response.data
        const rows = Array.isArray(body) ? body : body?.data

        if (!Array.isArray(rows)) {
            throw new Error('Некорректный ответ справочника.')
        }

        for (const row of rows) {
            items.set(String(row.id), row)
        }

        const meta = body?.meta
        if (!meta || Number(meta.last_page) <= page) {
            return [...items.values()]
        }
        if (Number(meta.current_page) !== page || !rows.length) {
            throw new Error('Не удалось получить все страницы справочника.')
        }
        page += 1
    }
}
