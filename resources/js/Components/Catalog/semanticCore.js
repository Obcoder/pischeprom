const clean = value => typeof value === 'string' ? value.replace(/\s+/gu, ' ').trim() : ''
const key = value => clean(value).toLocaleLowerCase('ru-RU')

export function normalizeSemanticCoreRows(rows) {
    const seen = new Set()
    return (Array.isArray(rows) ? rows : []).flatMap(row => {
        const group = clean(typeof row === 'object' && row ? row.group : '')
        const phrase = clean(typeof row === 'string' ? row : row?.phrase)
        const identity = JSON.stringify([key(group), key(phrase)])
        if (!phrase || seen.has(identity)) return []
        seen.add(identity)
        return [{ group, phrase }]
    })
}

export function semanticCorePhrases(rows) {
    const seen = new Set()
    return normalizeSemanticCoreRows(rows).flatMap(({ phrase }) => {
        if (seen.has(key(phrase))) return []
        seen.add(key(phrase))
        return [phrase]
    })
}

function markdownCells(line) {
    const cells = []
    let cell = ''
    for (let index = 0; index < line.length; index++) {
        if (line[index] === '\\' && ['|', '\\'].includes(line[index + 1])) cell += line[++index]
        else if (line[index] === '|') { cells.push(cell.trim()); cell = '' }
        else cell += line[index]
    }
    cells.push(cell.trim())
    if (line.startsWith('|')) cells.shift()
    if (line.endsWith('|') && cells.at(-1) === '') cells.pop()
    return cells
}

function unwrap(value) {
    const text = clean(value)
    return text.replace(/^(?:\*\*(.*?)\*\*|__(.*?)__|`(.*?)`)$/u, (_, bold, underline, code) => bold ?? underline ?? code)
}

const semanticHeader = cells => cells.length === 2 && ['группа', 'group', 'кластер'].includes(key(cells[0]))
    && ['поисковая фраза', 'ключевая фраза', 'фраза', 'search phrase', 'keyword', 'query'].includes(key(cells[1]))
const otherHeader = cells => cells.length === 2 && [['поле', 'значение'], ['вопрос', 'ответ'], ['field', 'value'], ['question', 'answer']]
    .some(([first, second]) => key(cells[0]) === first && key(cells[1]) === second)
const cellsFrom = raw => (raw.includes('\t') ? raw.replace(/\r$/, '').split('\t') : markdownCells(raw.trim())).map(unwrap)

export function parseSemanticCoreTable(input) {
    const rows = [], errors = []
    const text = String(input || '').replace(/^\uFEFF/, '')
    if (text.length > 500_000) return { rows, errors: ['Слишком много текста. Вставьте таблицу до 2 000 строк.'], duplicates: 0 }
    const sourceLines = text.split(/\r?\n/)
    const hasSemanticHeader = sourceLines.some(raw => semanticHeader(cellsFrom(raw)))
    let candidates = 0, inSemanticTable = !hasSemanticHeader
    for (const [index, raw] of sourceLines.entries()) {
        const line = raw.trim()
        const tabular = raw.includes('\t')
        // Keep the leading tab: an empty group is a valid first column.
        let cells = cellsFrom(raw)
        if (semanticHeader(cells)) { inSemanticTable = true; continue }
        if (!line) continue
        if (/^```|^~~~/.test(line) || (!tabular && !line.includes('|'))) {
            if (hasSemanticHeader) inSemanticTable = false
            continue
        }
        if (!inSemanticTable) continue
        if (otherHeader(cells)) {
            if (hasSemanticHeader) { inSemanticTable = false; continue }
            errors.push(`Строка ${index + 1}: это другая таблица. Вставьте колонки «Группа» и «Поисковая фраза».`)
            continue
        }
        if (tabular) {
            if (cells.some(cell => cell.startsWith('"') && !/^"(?:[^"]|"")*"$/u.test(cell))) {
                errors.push(`Строка ${index + 1}: незакрытая кавычка или перенос строки внутри ячейки Excel. Замените перенос пробелом.`)
                continue
            }
            cells = cells.map(cell => cell.startsWith('"') ? cell.slice(1, -1).replace(/""/g, '"') : cell)
        }
        if (cells.every(cell => /^:?-{3,}:?$/.test(cell))) continue
        candidates++
        if (candidates > 2000) { errors.push('В одной таблице допускается не более 2 000 строк.'); break }
        if (cells.length !== 2) { errors.push(`Строка ${index + 1}: нужны две колонки «Группа» и «Поисковая фраза».`); continue }
        if (!cells[1]) { errors.push(`Строка ${index + 1}: поисковая фраза пуста.`); continue }
        if (cells[0].length > 255 || cells[1].length > 1000) { errors.push(`Строка ${index + 1}: группа — до 255 символов, фраза — до 1 000.`); continue }
        rows.push({ group: cells[0], phrase: cells[1] })
    }
    if (!rows.length && !errors.length) errors.push('Вставьте таблицу с двумя колонками: «Группа» и «Поисковая фраза» (Markdown или Excel).')
    const normalized = normalizeSemanticCoreRows(rows)
    return { rows: normalized, errors, duplicates: rows.length - normalized.length }
}
