<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use App\Shared\Domain\Clock;
use Illuminate\Support\Facades\DB;

final class ExpireDueMembershipRequests
{
    public function __construct(
        private readonly MembershipRequestRepositoryInterface $requests,
        private readonly Clock $clock
    ) {}

    public function execute(): int
    {
        $now = $this->clock->now();
        $expiredRequests = $this->requests->findPendingExpired($now);
        $count = 0;

        foreach ($expiredRequests as $request) {
            DB::transaction(function () use ($request): void {
                $locked = $this->requests->findByIdForUpdate($request->id());
                if ($locked !== null && $locked->status()->isPending()) {
                    $locked->expire();
                    $this->requests->save($locked);
                }
            });

            $count++;
        }

        return $count;
    }
}

