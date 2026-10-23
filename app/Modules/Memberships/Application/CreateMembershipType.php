<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application;

use App\Modules\Memberships\Application\Commands\CreateMembershipTypeCommand;
use App\Modules\Memberships\Domain\InvalidMembershipRules;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Shared\Domain\Money;

final class CreateMembershipType
{
    public function __construct(
        private readonly MembershipTypeRepositoryInterface $types
    ) {}

    /**
     * @throws InvalidMembershipRules
     */
    public function execute(CreateMembershipTypeCommand $command): int
    {
        $rules = MembershipRules::create(
            kind: $command->kind,
            startMode: $command->startMode,
            visitsCount: $command->visitsCount,
            durationDays: $command->durationDays,
            validityDays: $command->validityDays,
            dailyLimit: $command->dailyLimit,
            timeFromMinute: $command->timeFromMinute,
            timeToMinute: $command->timeToMinute,
            freezeMaxDays: $command->freezeMaxDays,
            freezeMaxTimes: $command->freezeMaxTimes
        );

        return $this->types->create(
            $command->companyId,
            $command->name,
            new Money($command->priceMinor, $command->currency),
            $rules
        );
    }
}
