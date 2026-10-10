export function safeSearchUrl(value) {
    try {
        const url = new URL(value)
        return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password ? url.href : null
    } catch {
        return null
    }
}

export function searchErrorMessage(code) {
    const messages = {
        yandex_search_not_configured: 'Поиск Яндекса не настроен. Администратору нужно проверить ключ API и каталог Yandex Cloud.',
        yandex_search_query_invalid: 'Укажите поисковый запрос длиной от 1 до 255 символов.',
        yandex_search_connection_failed: 'Не удалось связаться с Яндексом. Попробуйте повторить поиск через минуту.',
        yandex_search_http_401: 'Яндекс отклонил ключ доступа. Администратору нужно проверить настройки поиска.',
        yandex_search_http_403: 'У сервиса нет доступа к поиску Яндекса. Проверьте права ключа и настройки Yandex Cloud.',
        yandex_search_http_429: 'Достигнут лимит запросов Яндекса. Повторите поиск позже.',
        yandex_product_search_failed_safely: 'Не удалось обработать результаты поиска. Повторите запрос; если ошибка сохранится, передайте администратору номер запроса.',
        yandex_search_storage_failed: 'Не удалось сохранить выдачу. Повторите поиск; если ошибка сохранится, передайте администратору номер запроса.',
        yandex_search_timed_out: 'Поиск не завершился вовремя. Запустите его повторно с меньшим количеством результатов.',
        yandex_search_queue_failed: 'Не удалось запустить задачу поиска. Администратору нужно проверить очередь задач.',
        yandex_search_queue_unavailable: 'Задача не запустилась вовремя. Администратору нужно проверить очередь задач.',
    }
    if (messages[code]) return messages[code]
    if (/^yandex_search_http_5\d\d$/.test(code || '')) return 'Сервис Яндекса временно недоступен. Повторите поиск позже.'
    if (/^yandex_search_(xml|json|content_type|response|compressed)/.test(code || '')) return 'Яндекс вернул ответ, который не удалось обработать. Повторите поиск позже.'
    return 'Поиск не завершён. Повторите запрос; если ошибка сохранится, передайте администратору технические сведения.'
}
