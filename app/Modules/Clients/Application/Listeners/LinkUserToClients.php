<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application\Listeners;

use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Identity\Contracts\EmailVerified;

/**
 * Идемпотентно привязывает аккаунт ко всем карточкам с этим email (ТЗ 9.3, D-020).
 */
final class LinkUserToClients
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients
    ) {}

    public function handle(EmailVerified $event): void
    {
        $this->clients->linkUserByEmail($event->userId, $event->email);
    }
}
