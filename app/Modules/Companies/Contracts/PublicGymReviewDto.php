<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

final readonly class PublicGymReviewDto
{
    public function __construct(
        public string $publicId,
        public int $rating,
        public ?string $comment,
        public string $authorName,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}

