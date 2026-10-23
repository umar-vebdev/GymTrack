<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application\Listeners;

use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Identity\Contracts\PhoneVerified;

/**
 * Идемпотентно привязывает аккаунт ко всем карточкам с этим телефоном (ТЗ 9.3).
 */
final class LinkUserToClients
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients
    ) {}

    public function handle(PhoneVerified $event): void
    {
        $this->clients->linkUserByPhone($event->userId, $event->phone);
    }
}
