<?php

namespace App\Services\YandexMaps\Exceptions;

use Exception;

class YandexMapsException extends Exception
{
    public static function invalidUrl(string $url): self
    {
        return new self("Некорректная ссылка на Яндекс.Карты: {$url}");
    }

    public static function pageUnavailable(string $url, ?string $reason = null): self
    {
        $message = "Страница недоступна: {$url}";
        if ($reason) {
            $message .= " ({$reason})";
        }

        return new self($message);
    }

    public static function parseFailed(string $reason): self
    {
        return new self("Ошибка парсинга: {$reason}");
    }

    public static function emptyResponse(): self
    {
        return new self('Пустой ответ от Яндекс.Карт');
    }

    public static function markupChanged(): self
    {
        return new self('Изменилась разметка страницы Яндекс.Карт. Парсер требует обновления.');
    }

    public static function playwrightTimeout(int $seconds): self
    {
        return new self("Парсер не уложился в таймаут ({$seconds} с). Увеличьте YANDEX_PARSER_TIMEOUT или отключите аспекты (YANDEX_PARSER_ASPECTS=0).");
    }

    public static function playwrightUnavailable(string $reason): self
    {
        return new self("Playwright-парсер недоступен: {$reason}");
    }
}
