<?php

namespace App\Services\Yandex;

use RuntimeException;

class YandexSearchException extends RuntimeException
{
    public function __construct(
        public readonly string $category,
        public readonly string $safeCode,
        string $message = 'Yandex Search request failed safely.',
    ) {
        parent::__construct($message);
    }

    public static function userMessage(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return match (true) {
            in_array($code, ['yandex_search_not_configured', 'yandex_search_host_not_allowlisted'], true) => 'Поиск Яндекса не настроен. Обратитесь к администратору.',
            in_array($code, ['yandex_search_http_401', 'yandex_search_http_403', 'yandex_search_xml_error_31', 'yandex_search_xml_error_33', 'yandex_search_xml_error_42'], true) => 'Яндекс отклонил доступ. Администратору нужно проверить ключ и права сервиса.',
            in_array($code, ['yandex_search_http_429', 'yandex_search_xml_error_32', 'yandex_search_xml_error_55'], true) => 'Достигнут лимит запросов Яндекса. Повторите поиск позже.',
            $code === 'yandex_search_connection_failed' => 'Не удалось соединиться с Яндексом. Повторите поиск позже.',
            $code === 'yandex_search_timed_out' => 'Яндекс не ответил за отведённое время. Повторите поиск или уменьшите число результатов.',
            in_array($code, ['yandex_search_queue_failed', 'yandex_search_queue_unavailable'], true) => 'Не удалось выполнить задачу поиска. Повторите запуск; если ошибка повторится, обратитесь к администратору.',
            $code === 'yandex_search_storage_failed' => 'Не удалось сохранить выдачу. Повторите поиск; если ошибка повторится, обратитесь к администратору.',
            $code === 'yandex_search_query_invalid' => 'Укажите поисковый запрос длиной до 255 символов.',
            str_starts_with($code, 'yandex_search_http_5') || $code === 'yandex_search_xml_error_20' => 'Сервис Яндекса временно недоступен. Повторите поиск позже.',
            str_starts_with($code, 'yandex_search_xml_error_') || $code === 'yandex_search_http_400' => 'Яндекс отклонил запрос. Измените поисковую фразу и повторите поиск.',
            str_starts_with($code, 'yandex_search_xml_')
                || str_starts_with($code, 'yandex_search_json_')
                || str_starts_with($code, 'yandex_search_response_')
                || in_array($code, ['yandex_search_content_type_invalid', 'yandex_search_redirect_blocked', 'yandex_search_compressed_response_blocked'], true) => 'Яндекс вернул некорректный ответ. Повторите поиск позже.',
            default => 'Не удалось получить выдачу Яндекса. Повторите поиск; если ошибка повторится, обратитесь к администратору.',
        };
    }
}
