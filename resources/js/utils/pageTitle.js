export function pageTitle(title) {
    if (!title) return 'ПИЩЕПРОМ-СЕРВЕР — маркетплейс пищевой промышленности'
    return /пищепром-сервер\s*$/iu.test(title) ? title : `${title} — ПИЩЕПРОМ-СЕРВЕР`
}
