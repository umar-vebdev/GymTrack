<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

use App\Modules\Companies\Contracts\GymLookupInterface;
use App\Modules\Schedule\Domain\ClassSession;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassTemplate;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassSession;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Идемпотентная генерация занятий расписания зала на период дат по активным шаблонам (Бизнес-ТЗ 8; D-011).
 */
final class GenerateClassSessions
{
    public const MAX_PERIOD_DAYS = 62;

    public function __construct(
        private readonly GymLookupInterface $gyms,
        private readonly ClassTemplateRepositoryInterface $templates,
        private readonly ClassSessionRepositoryInterface $sessions,
    ) {}

    /**
     * Генерирует недостающие занятия зала на каждую дату интервала [fromDate, toDate] включительно (в UTC).
     * Возвращает список только что созданных занятий; уже существующие слоты пропускаются.
     *
     * @return list<ClassSession>
     *
     * @throws ScheduleResourceNotFound зал не найден или принадлежит другой компании
     * @throws InvalidClassSession период некорректен или превышает MAX_PERIOD_DAYS
     */
    public function execute(
        int $companyId,
        int $gymId,
        DateTimeImmutable $fromDate,
        DateTimeImmutable $toDate,
    ): array {
        if (!$this->gyms->existsInCompany($gymId, $companyId)) {
            throw ScheduleResourceNotFound::gym($gymId);
        }

        $utc = new DateTimeZone('UTC');
        $startDay = $fromDate->setTimezone($utc)->setTime(0, 0, 0);
        $endDay = $toDate->setTimezone($utc)->setTime(0, 0, 0);

        if ($startDay > $endDay) {
            throw InvalidClassSession::invalidGenerationPeriod(self::MAX_PERIOD_DAYS);
        }

        $daysCount = (int) $startDay->diff($endDay)->days + 1;
        if ($daysCount > self::MAX_PERIOD_DAYS) {
            throw InvalidClassSession::invalidGenerationPeriod(self::MAX_PERIOD_DAYS);
        }

        $activeTemplates = $this->templates->listForGym($companyId, $gymId, true);
        if ($activeTemplates === []) {
            return [];
        }

        /** @var array<int, list<ClassTemplate>> $byWeekday */
        $byWeekday = [];
        foreach ($activeTemplates as $template) {
            $byWeekday[$template->weekday()][] = $template;
        }

        /** @var list<ClassSession> $created */
        $created = DB::transaction(function () use ($startDay, $endDay, $byWeekday): array {
            $result = [];
            $cursor = $startDay;

            while ($cursor <= $endDay) {
                $weekday = (int) $cursor->format('N');

                foreach ($byWeekday[$weekday] ?? [] as $template) {
                    $startsAt = $cursor->modify(sprintf('+%d minutes', $template->startTimeMinute()));
                    $endsAt = $startsAt->modify(sprintf('+%d minutes', $template->durationMinutes()));

                    $session = $this->sessions->createIfNotExistsFromTemplate($template, $startsAt, $endsAt);
                    if ($session !== null) {
                        $result[] = $session;
                    }
                }

                $cursor = $cursor->modify('+1 day');
            }

            return $result;
        });

        return $created;
    }
}

