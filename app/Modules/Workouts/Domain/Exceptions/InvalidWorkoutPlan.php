<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain\Exceptions;

use DomainException;

/**
 * Нарушение доменных инвариантов тренировочного плана или его пункта.
 */
final class InvalidWorkoutPlan extends DomainException
{
    public static function invalidOwner(): self
    {
        return new self('Идентификатор владельца плана должен быть положительным.');
    }

    public static function invalidSourcePlan(): self
    {
        return new self('Некорректная ссылка на исходный план библиотеки.');
    }

    public static function titleRequired(): self
    {
        return new self('Название тренировочного плана не может быть пустым.');
    }

    public static function titleTooLong(int $maxLength): self
    {
        return new self("Название плана не может быть длиннее {$maxLength} символов.");
    }

    public static function descriptionTooLong(int $maxLength): self
    {
        return new self("Описание плана не может быть длиннее {$maxLength} символов.");
    }

    public static function onlyPlatformPlansCanBePublished(): self
    {
        return new self('Публиковать в библиотеку можно только планы платформы.');
    }

    public static function invalidExerciseId(): self
    {
        return new self('Идентификатор упражнения должен быть положительным.');
    }

    public static function invalidSortOrder(): self
    {
        return new self('Порядковый номер упражнения не может быть отрицательным.');
    }

    public static function invalidTargetSets(int $min, int $max): self
    {
        return new self("Количество подходов должно быть в диапазоне от {$min} до {$max}.");
    }

    public static function invalidTargetReps(int $min, int $max): self
    {
        return new self("Количество повторений должно быть в диапазоне от {$min} до {$max}.");
    }

    public static function invalidTargetWeight(): self
    {
        return new self('Целевой вес не может быть отрицательным.');
    }

    public static function invalidRestSeconds(int $max): self
    {
        return new self("Время отдыха должно быть от 0 до {$max} секунд.");
    }

    public static function inactiveExercise(int $exerciseId): self
    {
        return new self(sprintf('Упражнение #%d неактивно и не может использоваться в плане.', $exerciseId));
    }
}
