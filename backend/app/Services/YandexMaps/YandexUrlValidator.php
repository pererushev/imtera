<?php

namespace App\Services\YandexMaps;

use App\Services\YandexMaps\Exceptions\YandexMapsException;

class YandexUrlValidator
{
    private const PATTERNS = [
        '#^https?://yandex\.(ru|com|by|kz|ua)/maps/org/[^/]+/\d+#i',
        '#^https?://yandex\.(ru|com|by|kz|ua)/maps/-/[^/]+/\d+#i',
    ];

    public function validate(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw YandexMapsException::invalidUrl($url);
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw YandexMapsException::invalidUrl($url);
        }

        $isValid = false;
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $url)) {
                $isValid = true;
                break;
            }
        }

        if (! $isValid) {
            throw YandexMapsException::invalidUrl($url);
        }

        return $this->normalize($url);
    }

    public function extractOrgId(string $url): string
    {
        if (preg_match('#/(\d+)(?:/|$)#', $url, $matches)) {
            return $matches[1];
        }

        throw YandexMapsException::invalidUrl($url);
    }

    private function normalize(string $url): string
    {
        $url = rtrim($url, '/');

        if (! str_contains($url, '/reviews')) {
            $url .= '/reviews';
        }

        return $url.'/';
    }
}
