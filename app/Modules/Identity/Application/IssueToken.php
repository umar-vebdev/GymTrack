<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Contracts\TokenGenerator;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

final class IssueToken
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenGenerator $tokenGenerator
    ) {}

    public function execute(string $phone, string $password, string $device = 'mobile'): string
    {
        $user = $this->userRepository->findByPhone($phone);

        if (!$user || !Hash::check($password, $user->passwordHash())) {
            throw new InvalidArgumentException('Неверный телефон или пароль.');
        }

        return $this->tokenGenerator->generateForUser($user->id(), $device);
    }
}
