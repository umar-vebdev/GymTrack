<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain;

use App\Modules\Schedule\Domain\Exceptions\InvalidClassTemplate;

interface ClassTemplateRepositoryInterface
{
    /**
     * Создаёт активный шаблон группового занятия с проверкой инвариантов `ClassTemplate`.
     *
     * @throws InvalidClassTemplate
     */
    public function create(
        int $companyId,
        int $gymId,
        int $trainerId,
        string $title,
        ?string $description,
        int $weekday,
        int $startTimeMinute,
        int $durationMinutes,
        int $capacity,
    ): ClassTemplate;

    /**
     * Сохраняет изменения шаблона внутри его компании.
     */
    public function save(ClassTemplate $template): void;

    /**
     * Ищет шаблон по внутреннему ID только в рамках указанной компании.
     */
    public function findById(int $companyId, int $id): ?ClassTemplate;

    /**
     * Возвращает шаблоны зала компании в порядке (weekday, start_time_minute, id).
     *
     * @return list<ClassTemplate>
     */
    public function listForGym(int $companyId, int $gymId, ?bool $onlyActive = null): array;
}
