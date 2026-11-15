<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation\Resources;

use App\Modules\Companies\Contracts\PublicGymReviewDto;

final class PublicGymReviewResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(PublicGymReviewDto $review): array
    {
        return [
            'id' => $review->publicId,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'author_name' => $review->authorName,
            'created_at' => $review->createdAt,
            'updated_at' => $review->updatedAt,
        ];
    }
}

