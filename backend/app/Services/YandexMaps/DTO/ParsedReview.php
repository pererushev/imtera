<?php

namespace App\Services\YandexMaps\DTO;

readonly class ParsedReview
{
    public function __construct(
        public string $externalId,
        public string $author,
        public ?string $text,
        public int $rating,
        public ?string $date,
    ) {}
}
