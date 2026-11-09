<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain;

use DateTimeImmutable;

interface ClassSessionRepositoryInterface
{
    /**
     * Идемпотентно создаёт занятие по шаблону на указанный слот.
     * Если занятие с парой (template_id, starts_at) уже существует, возвращает null.
     */
    public function createIfNotExistsFromTemplate(
        ClassTemplate $template,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ): ?ClassSession;

    /**
     * Сохраняет статус и количество занятых мест занятия в рамках его компании.
     */
    public function save(ClassSession $session): void;

    /**
     * Ищет занятие по внутреннему ID только в рамках указанной компании.
     */
    public function findById(int $companyId, int $id): ?ClassSession;

    /**
     * Ищет и блокирует строку занятия (SELECT ... FOR UPDATE) в рамках указанной компании.
     */
    public function findByIdForUpdate(int $companyId, int $id): ?ClassSession;

    /**
     * Возвращает занятия зала компании в интервале [from, to], отсортированные по (starts_at, id).
     *
     * @return list<ClassSession>
     */
    public function listForGymInPeriod(
        int $companyId,
        int $gymId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array;
}
