<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\ListPublicGymReviews;
use App\Modules\Catalog\Presentation\Requests\ListPublicGymReviewsRequest;
use App\Modules\Catalog\Presentation\Resources\PublicGymReviewResource;
use App\Modules\Companies\Contracts\PublicGymReviewDto;
use Illuminate\Http\JsonResponse;

final class ListPublicGymReviewsController extends Controller
{
    public function __construct(
        private readonly ListPublicGymReviews $listPublicGymReviews,
    ) {}

    public function __invoke(ListPublicGymReviewsRequest $request, string $publicId): JsonResponse
    {
        $rawPage = $request->validated('page');
        $rawPerPage = $request->validated('per_page');
        $page = is_numeric($rawPage) ? (int) $rawPage : 1;
        $perPage = is_numeric($rawPerPage) ? (int) $rawPerPage : 20;

        $result = $this->listPublicGymReviews->execute($publicId, $page, $perPage);

        if ($result === null) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Зал не найден.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => array_map(
                static fn (PublicGymReviewDto $review): array => PublicGymReviewResource::toArray($review),
                $result['items'],
            ),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
            ],
        ]);
    }
}

