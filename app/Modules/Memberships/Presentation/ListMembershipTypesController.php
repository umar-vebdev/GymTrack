<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Memberships\Application\ListMembershipTypes;
use App\Modules\Memberships\Domain\MembershipType;
use App\Modules\Memberships\Presentation\Requests\ListMembershipTypesRequest;
use Illuminate\Http\JsonResponse;

final class ListMembershipTypesController extends Controller
{
    public function __construct(
        private readonly ListMembershipTypes $listTypes,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(ListMembershipTypesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null) {
            return response()->json(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Доступ только для сотрудников зала.',
            ]], 403);
        }

        $onlyActive = $request->has('only_active')
            ? $request->boolean('only_active')
            : null;

        $types = $this->listTypes->execute($companyId, $onlyActive);

        return response()->json([
            'data' => array_map(static fn (MembershipType $type): array => [
                'id' => $type->id(),
                'name' => $type->name(),
                'kind' => $type->rules()->kind->value,
                'start_mode' => $type->rules()->startMode->value,
                'price_minor' => $type->price()->amount(),
                'currency' => $type->price()->currency(),
                'visits_count' => $type->rules()->visitsCount,
                'duration_days' => $type->rules()->durationDays,
                'validity_days' => $type->rules()->validityDays,
                'daily_limit' => $type->rules()->dailyLimit,
                'time_from_minute' => $type->rules()->timeFromMinute,
                'time_to_minute' => $type->rules()->timeToMinute,
                'freeze_max_days' => $type->rules()->freezeMaxDays,
                'freeze_max_times' => $type->rules()->freezeMaxTimes,
                'is_active' => $type->isActive(),
            ], $types),
        ]);
    }
}
