<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

interface TokenGenerator
{
    public function generateForUser(int $userId, string $device): string;
}
