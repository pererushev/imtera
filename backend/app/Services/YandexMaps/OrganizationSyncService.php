<?php

namespace App\Services\YandexMaps;

use App\Models\Organization;
use App\Models\Review;
use App\Services\YandexMaps\DTO\ParsedOrganization;
use Illuminate\Support\Facades\DB;

class OrganizationSyncService
{
    public function __construct(
        private readonly YandexMapsParserService $parser,
    ) {}

    public function sync(Organization $organization): Organization
    {
        set_time_limit(900);
        
        $organization->update([
            'parse_status' => 'parsing',
            'parse_error' => null,
        ]);

        try {
            $parsed = $this->parser->parse($organization->yandex_url);

            DB::transaction(function () use ($organization, $parsed) {
                $organization->update([
                    'org_id' => $parsed->orgId,
                    'name' => $parsed->name,
                    'rating' => $parsed->rating,
                    'reviews_count' => $parsed->reviewsCount,
                    'ratings_count' => $parsed->ratingsCount,
                    'parsed_at' => now(),
                    'parse_status' => 'success',
                    'parse_error' => null,
                ]);

                $organization->reviews()->delete();

                $chunks = array_chunk($parsed->reviews, 100);
                foreach ($chunks as $chunk) {
                    $rows = array_map(fn ($review) => [
                        'organization_id' => $organization->id,
                        'external_id' => $review->externalId,
                        'author' => $review->author,
                        'text' => $review->text,
                        'rating' => $review->rating,
                        'reviewed_at' => $this->parseReviewDate($review->date),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $chunk);

                    Review::insert($rows);
                }
            });

            return $organization->fresh();
        } catch (\Throwable $e) {
            $organization->update([
                'parse_status' => 'error',
                'parse_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
    private function parseReviewDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        $timestamp = strtotime($date);
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }
}
