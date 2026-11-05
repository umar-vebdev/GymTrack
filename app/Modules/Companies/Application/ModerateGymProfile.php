<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Contracts\GymProfileApproved;
use App\Modules\Companies\Contracts\GymProfileRejected;
use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Shared\Domain\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class ModerateGymProfile
{
    public function __construct(
        private readonly GymRepositoryInterface $gyms,
        private readonly GymProfileRepositoryInterface $profiles,
        private readonly Clock $clock,
    ) {}

    public function execute(ModerateGymProfileCommand $command): GymProfile
    {
        /** @var array{0: GymProfile, 1: GymProfileApproved|GymProfileRejected} $result */
        $result = DB::transaction(function () use ($command): array {
            $gym = $this->gyms->findByIdForUpdate($command->gymId);
            if ($gym === null) {
                throw GymNotFound::withId($command->gymId);
            }

            $profile = $this->profiles->findByGymId($command->gymId) ?? GymProfile::draft($command->gymId);

            $now = $this->clock->now();

            if ($command->decision === 'approve') {
                $profile->approve($now);
                $this->profiles->save($profile);

                $event = new GymProfileApproved(
                    gymId: $command->gymId,
                    companyId: $gym->companyId(),
                    moderatorUserId: $command->moderatorUserId,
                    occurredOn: $now,
                );
            } elseif ($command->decision === 'reject') {
                $reason = (string) $command->reason;
                $profile->reject($reason);
                $this->profiles->save($profile);

                $event = new GymProfileRejected(
                    gymId: $command->gymId,
                    companyId: $gym->companyId(),
                    moderatorUserId: $command->moderatorUserId,
                    reason: $reason,
                    occurredOn: $now,
                );
            } else {
                throw new InvalidArgumentException(sprintf('Неизвестное решение модерации: «%s».', $command->decision));
            }

            return [$profile, $event];
        });

        [$profile, $event] = $result;
        Event::dispatch($event);

        return $profile;
    }
}
