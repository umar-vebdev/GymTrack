<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Contracts;

/**
 * Публичный контракт чтения профилей тренеров по списку ID для модуля Schedule (D-015).
 */
interface PublicTrainerQueryInterface
{
    /**
     * Пакетно возвращает профили тренеров по внутренним ID в виде карты [trainer_id => PublicTrainerSummary].
     *
     * @param  list<int>  $trainerIds
     * @return array<int, PublicTrainerSummary>
     */
    public function findByIds(array $trainerIds): array;
}

