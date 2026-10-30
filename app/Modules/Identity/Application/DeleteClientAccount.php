<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Contracts\TokenGenerator;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DeleteClientAccount
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenGenerator $tokenGenerator,
    ) {}

    public function execute(int $userId): void
    {
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            return;
        }

        $user->anonymize(Hash::make(Str::random(32)));
        $this->userRepository->update($user);

        $this->tokenGenerator->revokeAllForUser($userId);
    }
}
