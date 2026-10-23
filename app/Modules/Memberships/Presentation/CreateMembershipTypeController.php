<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Memberships\Application\Commands\CreateMembershipTypeCommand;
use App\Modules\Memberships\Application\CreateMembershipType;
use App\Modules\Memberships\Domain\InvalidMembershipRules;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Memberships\Presentation\Requests\CreateMembershipTypeRequest;
use Illuminate\Http\JsonResponse;

final class CreateMembershipTypeController extends Controller
{
    public function __construct(
        private readonly CreateMembershipType $createType,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(CreateMembershipTypeRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null || !$this->companies->isOwner($user->id, $companyId)) {
            return $this->error('FORBIDDEN', 'Создавать типы абонементов может только владелец.', 403);
        }

        try {
            $id = $this->createType->execute(new CreateMembershipTypeCommand(
                companyId: $companyId,
                name: $request->validated('name'),
                priceMinor: (int) $request->validated('price_minor'),
                currency: strtoupper((string) $request->validated('currency', 'TJS')),
                kind: MembershipKind::from($request->validated('kind')),
                startMode: StartMode::from($request->validated('start_mode')),
                visitsCount: $this->intOrNull($request->validated('visits_count')),
                durationDays: $this->intOrNull($request->validated('duration_days')),
                validityDays: $this->intOrNull($request->validated('validity_days')),
                dailyLimit: (int) ($request->validated('daily_limit') ?? 1),
                timeFromMinute: $this->toMinutes($request->validated('time_from')),
                timeToMinute: $this->toMinutes($request->validated('time_to')),
                freezeMaxDays: (int) ($request->validated('freeze_max_days') ?? 0),
                freezeMaxTimes: (int) ($request->validated('freeze_max_times') ?? 0)
            ));
        } catch (InvalidMembershipRules $e) {
            return $this->error('INVALID_MEMBERSHIP_RULES', $e->getMessage(), 422);
        }

        return response()->json(['data' => ['id' => $id]], 201);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function toMinutes(?string $time): ?int
    {
        if ($time === null) {
            return null;
        }

        [$hours, $minutes] = explode(':', $time);

        return ((int) $hours * 60) + (int) $minutes;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
