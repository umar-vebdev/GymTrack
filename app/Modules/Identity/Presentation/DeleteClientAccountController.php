<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Application\DeleteClientAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteClientAccountController extends Controller
{
    public function __construct(
        private readonly DeleteClientAccount $deleteClientAccount,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->deleteClientAccount->execute($user->id);

        return response()->json([
            'data' => [
                'status' => 'deleted',
            ],
        ]);
    }
}
