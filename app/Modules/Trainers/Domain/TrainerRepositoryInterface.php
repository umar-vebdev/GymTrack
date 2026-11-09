<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Domain;

use App\Modules\Trainers\Domain\Exceptions\InvalidTrainerProfile;

interface TrainerRepositoryInterface
{
    /**
     * Создаёт активного тренера. Имя и описание нормализуются правилами `Trainer`.
     *
     * @throws InvalidTrainerProfile
     */
    public function create(int $companyId, int $gymId, string $name, ?string $bio = null, ?int $userId = null): Trainer;

    /**
     * Сохраняет изменения имени, описания и активности. Компания, зал и аккаунт не меняются.
     */
    public function save(Trainer $trainer): void;

    /**
     * Ищет тренера по внутреннему ID только внутри указанной компании.
     */
    public function findById(int $companyId, int $id): ?Trainer;

    /**
     * Тренеры зала компании: по имени, затем по ID. Тренеры другой компании не возвращаются.
     *
     * @return list<Trainer>
     */
    public function listForGym(int $companyId, int $gymId): array;
}
