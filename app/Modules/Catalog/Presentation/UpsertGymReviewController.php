<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Application\UpsertGymReview;
use App\Modules\Catalog\Domain\Exceptions\GymReviewDenied;
use App\Modules\Catalog\Presentation\Requests\UpsertGymReviewRequest;
use App\Modules\Catalog\Presentation\Resources\PublicGymReviewResource;
use Illuminate\Http\JsonResponse;

final class UpsertGymReviewController extends Controller
{
    public function __construct(
        private readonly UpsertGymReview $upsertGymReview,
    ) {}

    public function __invoke(UpsertGymReviewRequest $request, string $publicId): JsonResponse
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Требуется авторизация.',
                ],
            ], 401);
        }

        $rawRating = $request->validated('rating');
        $rating = is_numeric($rawRating) ? (int) $rawRating : 0;
        $rawComment = $request->validated('comment');
        $comment = is_string($rawComment) ? $rawComment : null;

        try {
            $review = $this->upsertGymReview->execute($publicId, $user->id, $rating, $comment);
        } catch (GymReviewDenied $e) {
            return response()->json([
                'error' => [
                    'code' => $e->reasonCode(),
                    'message' => $e->getMessage(),
                ],
            ], 403);
        }

        if ($review === null) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Зал не найден.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => PublicGymReviewResource::toArray($review),
        ]);
    }
}

