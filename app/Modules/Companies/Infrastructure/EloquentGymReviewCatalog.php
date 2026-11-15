<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Contracts\GymReviewCatalogContractInterface;
use App\Modules\Companies\Contracts\PublicGymReviewDto;
use App\Modules\Companies\Domain\GymReview;
use App\Modules\Companies\Infrastructure\Persistence\GymReviewModel;
use App\Modules\Identity\Contracts\UserQueryInterface;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class EloquentGymReviewCatalog implements GymReviewCatalogContractInterface
{
    public function __construct(
        private readonly UserQueryInterface $users,
    ) {}

    public function upsertReview(
        int $companyId,
        int $gymId,
        int $userId,
        int $rating,
        ?string $comment,
    ): PublicGymReviewDto {
        $normalizedComment = GymReview::validateRatingAndComment($rating, $comment);

        /** @var GymReviewModel $model */
        $model = DB::transaction(static function () use ($companyId, $gymId, $userId, $rating, $normalizedComment): GymReviewModel {
            return GymReviewModel::query()
                ->withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'gym_id' => $gymId,
                        'user_id' => $userId,
                    ],
                    [
                        'company_id' => $companyId,
                        'rating' => $rating,
                        'comment' => $normalizedComment,
                    ],
                );
        });

        $authorSummary = $this->users->findSummaryById($userId);
        $authorName = $this->resolveAuthorName($authorSummary?->name);

        return $this->toDto($model, $authorName);
    }

    public function listForGym(int $gymId, int $page = 1, int $perPage = 20): array
    {
        $query = GymReviewModel::query()
            ->withoutGlobalScopes()
            ->where('gym_id', $gymId);

        $total = (clone $query)->count();
        if ($total === 0) {
            return ['items' => [], 'total' => 0];
        }

        $models = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        /** @var list<int> $userIds */
        $userIds = $models
            ->map(static fn (GymReviewModel $model): int => (int) $model->user_id)
            ->unique()
            ->values()
            ->all();

        $summaries = $this->users->findSummariesByIds($userIds);

        /** @var list<PublicGymReviewDto> $items */
        $items = $models
            ->map(function (GymReviewModel $model) use ($summaries): PublicGymReviewDto {
                $authorName = $this->resolveAuthorName($summaries[(int) $model->user_id]->name ?? null);

                return $this->toDto($model, $authorName);
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    private function resolveAuthorName(?string $name): string
    {
        if ($name === null) {
            return 'Клиент';
        }

        $trimmed = trim($name);

        return $trimmed !== '' ? $trimmed : 'Клиент';
    }

    private function toDto(GymReviewModel $model, string $authorName): PublicGymReviewDto
    {
        $utc = new DateTimeZone('UTC');
        $fallbackNow = (new DateTimeImmutable('now', $utc))->format(DATE_ATOM);

        return new PublicGymReviewDto(
            publicId: (string) $model->public_id,
            rating: (int) $model->rating,
            comment: $model->comment,
            authorName: $authorName,
            createdAt: $model->created_at?->toDateTimeImmutable()->setTimezone($utc)->format(DATE_ATOM) ?? $fallbackNow,
            updatedAt: $model->updated_at?->toDateTimeImmutable()->setTimezone($utc)->format(DATE_ATOM) ?? $fallbackNow,
        );
    }
}
