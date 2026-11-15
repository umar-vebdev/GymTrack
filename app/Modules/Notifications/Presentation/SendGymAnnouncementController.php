<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Notifications\Application\SendGymAnnouncement;
use App\Modules\Notifications\Presentation\Requests\SendGymAnnouncementRequest;
use Illuminate\Http\JsonResponse;

final class SendGymAnnouncementController extends Controller
{
    public function __construct(
        private readonly StaffCompanyResolverInterface $staffCompanyResolver,
        private readonly SendGymAnnouncement $sendGymAnnouncement,
    ) {}

    public function __invoke(SendGymAnnouncementRequest $request): JsonResponse
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

        $companyId = $this->staffCompanyResolver->companyIdForUser($user->id);
        if ($companyId === null) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Пользователь не состоит в компании.',
                ],
            ], 403);
        }

        $rawChannel = $request->validated('channel');
        $rawTitle = $request->validated('title');
        $rawBody = $request->validated('body');

        $channel = is_string($rawChannel) ? $rawChannel : '';
        $title = is_string($rawTitle) ? $rawTitle : '';
        $body = is_string($rawBody) ? $rawBody : '';

        $queuedCount = $this->sendGymAnnouncement->execute($companyId, $channel, $title, $body);

        return response()->json([
            'data' => [
                'queued_count' => $queuedCount,
                'channel' => $channel,
            ],
        ], 202);
    }
}

