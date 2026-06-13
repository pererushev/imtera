<?php

namespace App\Services\YandexMaps;

use App\Services\YandexMaps\DTO\ParsedOrganization;
use App\Services\YandexMaps\DTO\ParsedReview;
use App\Services\YandexMaps\Exceptions\YandexMapsException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class YandexMapsParserService
{
    public function __construct(
        private readonly YandexUrlValidator $urlValidator,
    ) {}

    public function parse(string $url): ParsedOrganization
    {
        $normalizedUrl = $this->urlValidator->validate($url);
        $orgId = $this->urlValidator->extractOrgId($normalizedUrl);

        $result = $this->parseWithPlaywright($normalizedUrl);

        if ($result === null) {
            Log::warning('Playwright parser unavailable, falling back to HTTP parser', ['url' => $normalizedUrl]);
            $result = $this->parseWithHttp($normalizedUrl);
        }

        if (empty($result['reviews'])) {
            throw YandexMapsException::emptyResponse();
        }

        $reviews = array_map(
            fn (array $r) => new ParsedReview(
                externalId: $r['review_id'] ?? $r['external_id'] ?? uniqid('review_'),
                author: $r['author'] ?? '',
                text: $r['text'] ?? null,
                rating: (int) ($r['rating'] ?? 0),
                date: $r['date'] ?? null,
            ),
            $result['reviews'],
        );

        $company = $result['company'] ?? [];

        return new ParsedOrganization(
            orgId: $orgId,
            name: $company['name'] ?? '',
            rating: (float) ($company['rating'] ?? 0),
            reviewsCount: (int) ($company['reviewCount'] ?? count($reviews)),
            ratingsCount: (int) ($company['ratingCount'] ?? 0),
            reviews: $reviews,
        );
    }

    private function parseWithPlaywright(string $url): ?array
    {
        $scriptPath = base_path('parser/scrape.js');

        if (! file_exists($scriptPath)) {
            return null;
        }

        $nodePath = config('services.yandex_parser.node_path', 'node');
        $timeout = config('services.yandex_parser.timeout', 180);

        try {
            $result = Process::timeout($timeout)
                ->run([$nodePath, $scriptPath, $url]);

            if (! $result->successful()) {
                Log::error('Playwright parser failed', [
                    'stderr' => $result->errorOutput(),
                    'stdout' => $result->output(),
                ]);

                return null;
            }

            $data = json_decode($result->output(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Playwright parser returned invalid JSON', ['output' => $result->output()]);

                return null;
            }

            if (isset($data['error'])) {
                throw YandexMapsException::parseFailed($data['error']);
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('Playwright parser exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function parseWithHttp(string $url): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept-Language' => 'ru-RU,ru;q=0.9',
            'Accept' => 'text/html,application/xhtml+xml',
        ])
            ->timeout(30)
            ->get($url);

        if (! $response->successful()) {
            throw YandexMapsException::pageUnavailable($url, "HTTP {$response->status()}");
        }

        $html = $response->body();

        if (strlen($html) < 1000) {
            throw YandexMapsException::emptyResponse();
        }

        return $this->extractFromHtml($html);
    }

    private function extractFromHtml(string $html): array
    {
        $company = [
            'name' => '',
            'rating' => 0,
            'reviewCount' => 0,
            'ratingCount' => 0,
        ];

        if (preg_match('/"ratingValue"\s*:\s*([\d.]+)/', $html, $m)) {
            $company['rating'] = (float) $m[1];
        }
        if (preg_match('/"reviewCount"\s*:\s*(\d+)/', $html, $m)) {
            $company['reviewCount'] = (int) $m[1];
        }
        if (preg_match('/"ratingCount"\s*:\s*(\d+)/', $html, $m)) {
            $company['ratingCount'] = (int) $m[1];
        }
        if (preg_match('/"shortTitle"\s*:\s*"([^"]+)"/', $html, $m)) {
            $company['name'] = $m[1];
        } elseif (preg_match('/<meta[^>]*property="og:title"[^>]*content="([^"]+)"/', $html, $m)) {
            $company['name'] = preg_replace('/\s*—.*$/', '', $m[1]);
        }

        $reviews = $this->extractReviewsFromJson($html);

        if (empty($reviews)) {
            $reviews = $this->extractReviewsFromDom($html);
        }

        if (empty($reviews)) {
            throw YandexMapsException::markupChanged();
        }

        return [
            'company' => $company,
            'reviews' => $reviews,
            'total_fetched' => count($reviews),
        ];
    }

    private function extractReviewsFromJson(string $html): array
    {
        if (! preg_match('/"reviews":\[/', $html, $match, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $start = $match[0][1] + strlen('"reviews":');
        $depth = 0;
        $arrayStr = '';

        for ($i = $start; $i < strlen($html); $i++) {
            $char = $html[$i];
            $arrayStr .= $char;

            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
        }

        try {
            $rawReviews = json_decode($arrayStr, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return array_map(fn (array $r) => [
            'review_id' => $r['reviewId'] ?? uniqid('review_'),
            'author' => $r['author']['name'] ?? '',
            'text' => $r['text'] ?? '',
            'rating' => (int) ($r['rating'] ?? 0),
            'date' => $r['updatedTime'] ?? null,
        ], $rawReviews);
    }

    private function extractReviewsFromDom(string $html): array
    {
        $reviews = [];

        if (! preg_match_all(
            '/class="business-review-view__info"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/s',
            $html,
            $blocks,
        )) {
            return [];
        }

        foreach ($blocks[1] as $i => $block) {
            $author = '';
            $date = '';
            $text = '';
            $rating = 0;

            if (preg_match('/itemprop="name"[^>]*>([^<]+)/', $block, $m)) {
                $author = trim(html_entity_decode($m[1]));
            }
            if (preg_match('/business-review-view__date[^>]*>([^<]+)/', $block, $m)) {
                $date = trim($m[1]);
            }
            if (preg_match('/business-review-view__body[^>]*>([^<]+)/', $block, $m)) {
                $text = trim(html_entity_decode($m[1]));
            }
            if (preg_match('/itemprop="ratingValue"[^>]*content="(\d+)"/', $block, $m)) {
                $rating = (int) $m[1];
            }

            if ($author || $text) {
                $reviews[] = [
                    'review_id' => "dom-{$i}",
                    'author' => $author,
                    'text' => $text,
                    'rating' => $rating,
                    'date' => $date,
                ];
            }
        }

        return $reviews;
    }
}
