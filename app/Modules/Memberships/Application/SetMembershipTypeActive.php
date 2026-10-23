<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application;

use App\Modules\Memberships\Domain\MembershipTypeNotFound;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;

final class SetMembershipTypeActive
{
    public function __construct(
        private readonly MembershipTypeRepositoryInterface $types
    ) {}

    /**
     * @throws MembershipTypeNotFound
     */
    public function execute(int $companyId, int $id, bool $isActive): void
    {
        $type = $this->types->findById($id);

        if ($type === null || $type->companyId() !== $companyId) {
            throw MembershipTypeNotFound::withId($id);
        }

        $this->types->setActive($id, $isActive);
    }
}
