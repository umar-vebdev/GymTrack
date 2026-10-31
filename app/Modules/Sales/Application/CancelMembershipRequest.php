<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Sales\Domain\Events\MembershipRequestCancelled;
use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use App\Modules\Sales\Domain\MembershipRequestStatus;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class CancelMembershipRequest
{
    public function __construct(
        private readonly MembershipRequestRepositoryInterface $requests
    ) {}

    /**
     * @return array{id: int, status: string}
     */
    public function execute(
        int $requestId,
        int $companyId,
        ?int $cancelledByUserId = null,
        ?string $reason = null
    ): array {
        return DB::transaction(function () use ($requestId, $companyId, $cancelledByUserId, $reason): array {
            $request = $this->requests->findByIdForUpdate($requestId);
            if ($request === null || $request->companyId() !== $companyId) {
                throw new DomainException('Заявка не найдена.');
            }

            if ($request->status() !== MembershipRequestStatus::PENDING) {
                throw new DomainException("Невозможно отменить заявку в статусе {$request->status()->value}.");
            }

            $request->cancel();
            $this->requests->save($request);

            Event::dispatch(new MembershipRequestCancelled(
                requestId: $request->id(),
                companyId: $request->companyId(),
                gymId: $request->gymId(),
                userId: $request->userId(),
                clientId: $request->clientId(),
                membershipTypeId: $request->membershipTypeId(),
                cancelledByUserId: $cancelledByUserId,
                reason: $reason
            ));

            return [
                'id' => $request->id(),
                'status' => $request->status()->value,
            ];
        });
    }
}

