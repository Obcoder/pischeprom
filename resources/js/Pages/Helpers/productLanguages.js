export const productBaseField = {
    key: 'rus',
    label: 'Русский',
    native: 'Русский',
    code: 'RU',
}

export const productLanguageFields = [
    { key: 'eng', label: 'Английский', native: 'English', code: 'EN' },
    { key: 'zh', label: 'Китайский', native: '中文', code: 'ZH' },
    { key: 'hi', label: 'Хинди', native: 'हिन्दी', code: 'HI' },
    { key: 'es', label: 'Испанский', native: 'Español', code: 'ES' },
    { key: 'fr', label: 'Французский', native: 'Français', code: 'FR' },
    { key: 'ar', label: 'Арабский', native: 'العربية', code: 'AR' },
    { key: 'po', label: 'Португальский', native: 'Português', code: 'PT' },
    { key: 'ur', label: 'Урду', native: 'اردو', code: 'UR' },
    { key: 'idn', label: 'Индонезийский', native: 'Bahasa Indonesia', code: 'ID' },
    { key: 'de', label: 'Немецкий', native: 'Deutsch', code: 'DE' },
    { key: 'ja', label: 'Японский', native: '日本語', code: 'JA' },
    { key: 'fa', label: 'Фарси', native: 'فارسی', code: 'FA' },
    { key: 'vi', label: 'Вьетнамский', native: 'Tiếng Việt', code: 'VI' },
    { key: 'tu', label: 'Турецкий', native: 'Türkçe', code: 'TR' },
    { key: 'ko', label: 'Корейский', native: '한국어', code: 'KO' },
    { key: 'it', label: 'Итальянский', native: 'Italiano', code: 'IT' },
    { key: 'nl', label: 'Голландский', native: 'Nederlands', code: 'NL' },
    { key: 'he', label: 'Иврит', native: 'עברית', code: 'HE' },
]

export const productTranslationFields = [
    ...productLanguageFields.slice(0, 7),
    productBaseField,
    ...productLanguageFields.slice(7),
]

export function emptyProductTranslationForm() {
    return productTranslationFields.reduce((fields, item) => {
        fields[item.key] = ''

        return fields
    }, {})
}
