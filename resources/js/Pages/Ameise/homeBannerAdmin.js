const slots = [1, 2, 3, 4, 5, 6]

export function moscowDateTimeInput(value) {
    if (!value) return ''
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return ''
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Europe/Moscow', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(date).map(({ type, value: part }) => [type, part]))
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`
}

export function moscowDateTimePayload(value) {
    return value ? `${value}:00+03:00` : null
}

export function validMobileOrder(value) {
    return Array.isArray(value) && value.length === 6 &&
        [...value].sort((a, b) => a - b).every((number, index) => number === slots[index])
}

export function moveMobileSlot(order, index, direction) {
    const next = [...order]
    const target = index + direction
    if (index < 0 || index >= next.length || target < 0 || target >= next.length) return next
    ;[next[index], next[target]] = [next[target], next[index]]
    return next
}

export function bannerProductionBrief(banner, device = 'desktop') {
    const alignment = device === 'mobile' ? banner.mobile_image_position : banner.image_position
    return [
        banner.content_mode === 'text'
            ? 'Подготовь короткое текстовое предложение для интернет-магазина пищевой отрасли «Пищепром».'
            : 'Создай рекламный баннер для интернет-магазина пищевой отрасли «Пищепром».',
        `Размещение: компактная лента на главной, ${device === 'mobile' ? 'мобильная версия' : 'компьютер'}, слот ${banner.slot_number || 'не выбран'}.`,
        'Размер: 1600 × 800 px (минимум 800 × 400 px), соотношение сторон 2:1. Формат WebP или PNG, целевой вес до 1 МБ.',
        'Безопасная зона: 10% от каждого края. Крупные хорошо читаемые надписи, высокий контраст, минимум мелких деталей.',
        'Баннер будет показан в невысокой ленте; композиция должна оставаться понятной при ширине около 200 px.',
        `Заголовок / смысл: ${banner.title || 'указать предложение'}.`,
        banner.subtitle ? `Подзаголовок: ${banner.subtitle}.` : '',
        banner.description ? `Описание предложения: ${banner.description}.` : '',
        `Цвета: фон ${banner.background_color}, текст ${banner.text_color}, акцент ${banner.accent_color}.`,
        `Ключевой объект: ${alignment || 'center center'}.`,
        banner.content_mode === 'text'
            ? 'Изображение не требуется. Заголовок до двух коротких строк и подзаголовок в одну строку будут показаны на цветном фоне.'
            : banner.content_mode === 'image'
                ? 'Текст включи в изображение. Кнопку не рисуй: весь баннер будет ссылкой.'
                : 'Не добавляй текст и кнопки в изображение: надписи будут наложены на сайте.',
        device === 'mobile' ? 'Адаптируй композицию для просмотра на телефоне.' : '',
    ].filter(Boolean).join('\n')
}
