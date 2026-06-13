<?php

namespace App\Services\YandexMaps\DTO;

readonly class ParsedOrganization
{
    /**
     * @param  ParsedReview[]  $reviews
     */
    public function __construct(
        public string $orgId,
        public string $name,
        public float $rating,
        public int $reviewsCount,
        public int $ratingsCount,
        public array $reviews,
    ) {}
}
