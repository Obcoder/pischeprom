import { productTranslationFields } from './productLanguages.js'

// Catalogue translations use ISO language codes; legacy product columns retain
// their historical names (rus, eng, po, idn and tu).
export const catalogTranslationFields = productTranslationFields
    .map(field => ({ ...field, key: field.code.toLowerCase() }))
    .sort((left, right) => Number(right.key === 'ru') - Number(left.key === 'ru'))

export function emptyCatalogTranslationForm() {
    return Object.fromEntries(catalogTranslationFields.map(field => [field.key, '']))
}

export function catalogTranslationForm(node) {
    return Object.fromEntries(catalogTranslationFields.map(field => [
        field.key,
        String(node?.translations?.[field.key] ?? (field.key === 'ru' ? node?.name : '') ?? ''),
    ]))
}
