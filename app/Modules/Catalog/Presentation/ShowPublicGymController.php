<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\GetPublicGym;
use App\Modules\Catalog\Presentation\Resources\PublicGymCardResource;
use Illuminate\Http\JsonResponse;

final class ShowPublicGymController extends Controller
{
    public function __construct(
        private readonly GetPublicGym $getPublicGym,
    ) {}

    public function __invoke(string $publicId): JsonResponse
    {
        $card = $this->getPublicGym->execute($publicId);

        if ($card === null) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Зал не найден.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => PublicGymCardResource::toArray($card),
        ]);
    }
}
