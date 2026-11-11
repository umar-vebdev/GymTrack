<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

use App\Modules\Schedule\Domain\ClassSession;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use Illuminate\Support\Facades\DB;

/**
 * Отмена конкретного занятия расписания с блокировкой строки в транзакции (Бизнес-ТЗ 8; D-011).
 */
final class CancelClassSession
{
    public function __construct(
        private readonly ClassSessionRepositoryInterface $sessions,
    ) {}

    /**
     * @throws ScheduleResourceNotFound занятие не найдено или принадлежит другой компании
     */
    public function execute(int $companyId, int $sessionId): ClassSession
    {
        /** @var ClassSession $cancelled */
        $cancelled = DB::transaction(function () use ($companyId, $sessionId): ClassSession {
            $session = $this->sessions->findByIdForUpdate($companyId, $sessionId);
            if ($session === null) {
                throw ScheduleResourceNotFound::session($sessionId);
            }

            $session->cancel();
            $this->sessions->save($session);

            return $session;
        });

        return $cancelled;
    }
}
