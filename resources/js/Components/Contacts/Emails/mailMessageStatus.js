export function sentCopyNotice(message) {
    if (!message || message.direction !== 'outgoing') return null

    if (message.delivery_status === 'unknown') {
        return 'Почтовый сервер не подтвердил результат отправки. Проверьте доставку перед повторной отправкой.'
    }
    if (message.delivery_status === 'failed') return 'Письмо не отправлено.'
    if (['prepared', 'sending'].includes(message.delivery_status)) return 'Отправка письма ещё не завершена.'
    if (message.sent_copy_status === 'reconstructed') return 'Архивная копия восстановлена в «Отправленных» из сохранённых данных.'
    if (message.imap_uid) return null

    if (message.sent_copy_status === 'legacy' || String(message.message_id || '').includes('@local.pischeprom')) {
        return 'Сохранена локальная архивная копия. Связь с письмом на почтовом сервере не установлена.'
    }
    if (message.sent_copy_status === 'failed') {
        return 'Письмо отправлено. Сохранить серверную копию пока не удалось; повторно отправлять письмо не нужно.'
    }
    if (message.sent_copy_status === 'pending') return 'Письмо отправлено. Копия в «Отправленных» синхронизируется.'

    return 'Серверная копия письма недоступна.'
}

export function readStatusTitle(message) {
    if (!message.imap_uid) return sentCopyNotice(message) || 'Отметка недоступна: серверная копия письма не найдена.'
    if (message.is_seen === true) return 'Прочитано на почтовом сервере'

    return message.is_seen == null
        ? 'Статус на сервере ещё неизвестен. Отметить прочитанным'
        : 'Отметить прочитанным на почтовом сервере'
}
