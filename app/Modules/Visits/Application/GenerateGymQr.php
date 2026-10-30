<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application;

use App\Modules\Companies\Contracts\GymQrLookupInterface;
use App\Modules\Companies\Contracts\GymQrSummary;
use App\Modules\Visits\Domain\Exceptions\GymNotFound;
use App\Modules\Visits\Domain\QrPayload;
use App\Modules\Visits\Domain\QrTokenServiceInterface;
use App\Shared\Domain\Clock;

final class GenerateGymQr
{
    public function __construct(
        private readonly GymQrLookupInterface $gyms,
        private readonly QrTokenServiceInterface $qrService,
        private readonly Clock $clock,
    ) {}

    /**
     * @return array{gym: GymQrSummary, payload: QrPayload}
     *
     * @throws GymNotFound
     */
    public function execute(int $companyId, int $gymId): array
    {
        $gym = $this->gyms->findById($gymId);

        if ($gym === null || $gym->companyId !== $companyId) {
            throw new GymNotFound;
        }

        $payload = $this->qrService->generate($gym->id, $gym->qrSecret, $this->clock->now());

        return [
            'gym' => $gym,
            'payload' => $payload,
        ];
    }
}
