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

    public function execute(
        string $password,
        ?string $email = null,
        ?string $phone = null,
        string $device = 'mobile',
    ): string {
        $normalizedEmail = $email !== null && trim($email) !== '' ? mb_strtolower(trim($email)) : null;
        $normalizedPhone = $phone !== null && trim($phone) !== '' ? trim($phone) : null;

        $user = match (true) {
            $normalizedEmail !== null => $this->userRepository->findByEmail($normalizedEmail),
            $normalizedPhone !== null => $this->userRepository->findByPhone($normalizedPhone),
            default => null,
        };

        if (!$user || !Hash::check($password, $user->passwordHash())) {
            throw new InvalidArgumentException('Неверный логин или пароль.');
        }

        return $this->tokenGenerator->generateForUser($user->id(), $device);
    }
}
