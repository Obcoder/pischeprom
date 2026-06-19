export const productLanguageFields = [
    { key: 'eng', label: 'Английский', native: 'English', code: 'EN' },
    { key: 'zh', label: 'Китайский', native: '中文', code: 'ZH' },
    { key: 'es', label: 'Испанский', native: 'Español', code: 'ES' },
    { key: 'ar', label: 'Арабский', native: 'العربية', code: 'AR' },
    { key: 'hi', label: 'Хинди', native: 'हिन्दी', code: 'HI' },
    { key: 'ur', label: 'Урду', native: 'اردو', code: 'UR' },
    { key: 'de', label: 'Немецкий', native: 'Deutsch', code: 'DE' },
    { key: 'fr', label: 'Французский', native: 'Français', code: 'FR' },
    { key: 'po', label: 'Португальский', native: 'Português', code: 'PT' },
    { key: 'it', label: 'Итальянский', native: 'Italiano', code: 'IT' },
    { key: 'nl', label: 'Голландский', native: 'Nederlands', code: 'NL' },
    { key: 'tu', label: 'Турецкий', native: 'Türkçe', code: 'TR' },
    { key: 'fa', label: 'Фарси', native: 'فارسی', code: 'FA' },
    { key: 'vi', label: 'Вьетнамский', native: 'Tiếng Việt', code: 'VI' },
    { key: 'ja', label: 'Японский', native: '日本語', code: 'JA' },
    { key: 'ko', label: 'Корейский', native: '한국어', code: 'KO' },
    { key: 'he', label: 'Иврит', native: 'עברית', code: 'HE' },
    { key: 'idn', label: 'Индонезийский', native: 'Bahasa Indonesia', code: 'ID' },
]

export const productBaseField = {
    key: 'rus',
    label: 'Русский',
    native: 'Русский',
    code: 'RU',
}

export const productTranslationFields = [
    productBaseField,
    ...productLanguageFields,
]

export function emptyProductTranslationForm() {
    return productTranslationFields.reduce((fields, item) => {
        fields[item.key] = ''

        return fields
    }, {})
}
