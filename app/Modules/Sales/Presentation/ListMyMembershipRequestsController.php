<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Sales\Application\ListUserMembershipRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListMyMembershipRequestsController extends Controller
{
    public function __construct(
        private readonly ListUserMembershipRequests $listRequests
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $items = $this->listRequests->execute($user->id);

        return response()->json([
            'data' => $items,
        ]);
    }
}
