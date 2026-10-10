import schema from '../../../../landings/schema.json'

export const heroFields = schema.hero
export const catalogFields = schema.catalog
export const contactFields = schema.contact
export const sourcesFields = schema.sources
export const blockTypes = schema.blockTypes

export function defaultFields(fields) {
    return Object.fromEntries(fields.map(field => [field.key, field.default ?? (
        field.type === 'object' ? defaultFields(field.fields)
            : field.type === 'list' ? []
                : field.type === 'boolean' ? false
                    : field.type === 'number' ? 0 : ''
    )]))
}

export function createLandingBlock(type) {
    const definition = blockTypes[type]
    if (!definition) return null
    return {
        id: `block-${globalThis.crypto?.randomUUID?.() || Math.random().toString(36).slice(2)}`,
        type, title: definition.label, navTitle: '', enabled: true,
        data: defaultFields(definition.fields),
    }
}
