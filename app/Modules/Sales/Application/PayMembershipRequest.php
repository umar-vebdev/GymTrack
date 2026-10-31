<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Sales\Domain\Events\MembershipRequestPaid;
use App\Modules\Sales\Domain\Events\PaymentMarked;
use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use App\Modules\Sales\Domain\MembershipRequestStatus;
use App\Modules\Sales\Domain\PaymentMark;
use App\Modules\Sales\Domain\PaymentMethod;
use App\Modules\Sales\Domain\SaleItem;
use App\Modules\Sales\Domain\SaleRepositoryInterface;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class PayMembershipRequest
{
    public function __construct(
        private readonly MembershipRequestRepositoryInterface $requests,
        private readonly SaleRepositoryInterface $sales,
        private readonly MembershipIssuerInterface $membershipIssuer,
        private readonly ClientLookupInterface $clients,
        private readonly Clock $clock
    ) {}

    /**
     * @return array{
     *     request_id: int,
     *     sale_id: int,
     *     membership_id: int,
     *     client_id: int,
     *     status: string,
     *     paid_at: string
     * }
     */
    public function execute(
        int $requestId,
        int $companyId,
        int $staffUserId,
        PaymentMethod $paymentMethod,
        ?string $idempotencyKey = null
    ): array {
        $now = $this->clock->now();

        /** @var array{
         *     request_id: int,
         *     sale_id: int,
         *     membership_id: int,
         *     client_id: int,
         *     status: string,
         *     paid_at: string,
         *     is_new: bool,
         *     gym_id: int,
         *     user_id: int,
         *     membership_type_id: int,
         *     amount: Money
         * } $result */
        $result = DB::transaction(function () use (
            $requestId,
            $companyId,
            $staffUserId,
            $paymentMethod,
            $idempotencyKey,
            $now
        ): array {
            $request = $this->requests->findByIdForUpdate($requestId);
            if ($request === null || $request->companyId() !== $companyId) {
                throw new DomainException('Заявка не найдена.');
            }

            if ($idempotencyKey !== null && $request->status() === MembershipRequestStatus::PAID) {
                $existingSale = $this->sales->findByIdempotencyKey($companyId, 'pay-req-'.$idempotencyKey);
                if ($existingSale !== null && $request->saleId() !== null && $request->membershipId() !== null && $request->paidAt() !== null && $request->clientId() !== null) {
                    return [
                        'request_id' => $request->id(),
                        'sale_id' => $request->saleId(),
                        'membership_id' => $request->membershipId(),
                        'client_id' => $request->clientId(),
                        'status' => $request->status()->value,
                        'paid_at' => $request->paidAt()->format(DateTimeImmutable::ATOM),
                        'is_new' => false,
                        'gym_id' => $request->gymId(),
                        'user_id' => $request->userId(),
                        'membership_type_id' => $request->membershipTypeId(),
                        'amount' => $existingSale->paymentMark()->amount,
                    ];
                }
            }

            if ($request->status() !== MembershipRequestStatus::PENDING) {
                throw new DomainException("Невозможно оплатить заявку в статусе {$request->status()->value}.");
            }

            if ($request->isExpired($now)) {
                throw new DomainException('Срок действия заявки истёк.');
            }

            $clientId = $request->clientId();
            if ($clientId === null) {
                $clientId = $this->clients->findOrCreateForUser($request->userId(), $companyId);
                $request->linkClient($clientId);
            }

            $quote = $this->membershipIssuer->quoteType($companyId, $request->membershipTypeId());

            $issuedMembershipId = $this->membershipIssuer->issue(
                companyId: $companyId,
                clientId: $clientId,
                typeId: $request->membershipTypeId(),
                at: $now,
                issuedByUserId: $staffUserId
            );

            $item = SaleItem::forMembership(
                membershipTypeId: $request->membershipTypeId(),
                name: $quote->name,
                unitPrice: $quote->price,
                issuedMembershipId: $issuedMembershipId
            );

            $paymentMark = new PaymentMark(
                method: $paymentMethod,
                amount: $quote->price,
                acceptedByUserId: $staffUserId,
                paidAt: $now
            );

            $sale = $this->sales->create(
                companyId: $companyId,
                clientId: $clientId,
                paymentMark: $paymentMark,
                items: [$item],
                idempotencyKey: $idempotencyKey !== null ? 'pay-req-'.$idempotencyKey : null
            );

            $request->markPaid(
                saleId: $sale->id(),
                membershipId: $issuedMembershipId,
                paidAt: $now,
                clientId: $clientId
            );

            $this->requests->save($request);

            return [
                'request_id' => $request->id(),
                'sale_id' => $sale->id(),
                'membership_id' => $issuedMembershipId,
                'client_id' => $clientId,
                'status' => $request->status()->value,
                'paid_at' => $now->format(DateTimeImmutable::ATOM),
                'is_new' => true,
                'gym_id' => $request->gymId(),
                'user_id' => $request->userId(),
                'membership_type_id' => $request->membershipTypeId(),
                'amount' => $quote->price,
            ];
        });

        if ($result['is_new']) {
            Event::dispatch(new PaymentMarked(
                saleId: $result['sale_id'],
                companyId: $companyId,
                clientId: $result['client_id'],
                method: $paymentMethod,
                amount: $result['amount'],
                acceptedByUserId: $staffUserId
            ));

            Event::dispatch(new MembershipRequestPaid(
                requestId: $result['request_id'],
                companyId: $companyId,
                gymId: $result['gym_id'],
                userId: $result['user_id'],
                clientId: $result['client_id'],
                membershipTypeId: $result['membership_type_id'],
                saleId: $result['sale_id'],
                membershipId: $result['membership_id'],
                acceptedByUserId: $staffUserId,
                paidAt: $now
            ));
        }

        return [
            'request_id' => $result['request_id'],
            'sale_id' => $result['sale_id'],
            'membership_id' => $result['membership_id'],
            'client_id' => $result['client_id'],
            'status' => $result['status'],
            'paid_at' => $result['paid_at'],
        ];
    }
}
