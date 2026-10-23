<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application;

use App\Modules\Clients\Domain\Client;
use App\Modules\Clients\Domain\ClientNotFound;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MergeClients
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients
    ) {}

    /**
     * Сливает карточку source в target: source удаляется, а аккаунт (если был только у source) переходит к target.
     *
     * @throws ClientNotFound
     */
    public function execute(int $companyId, int $sourceId, int $targetId): void
    {
        if ($sourceId === $targetId) {
            throw new InvalidArgumentException('Нельзя объединить карточку саму с собой.');
        }

        DB::transaction(function () use ($companyId, $sourceId, $targetId): void {
            $source = $this->findInCompany($companyId, $sourceId);
            $target = $this->findInCompany($companyId, $targetId);

            $this->clients->delete($source->id());

            if ($target->userId() === null && $source->userId() !== null) {
                $this->clients->assignUser($target->id(), $source->userId());
            }
        });
    }

    private function findInCompany(int $companyId, int $id): Client
    {
        $client = $this->clients->findById($id);

        if ($client === null || $client->companyId() !== $companyId) {
            throw ClientNotFound::withId($id);
        }

        return $client;
    }
}
