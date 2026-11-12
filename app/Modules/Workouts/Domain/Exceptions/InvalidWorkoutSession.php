<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain\Exceptions;

use DomainException;

/**
 * Нарушение доменных инвариантов тренировочной сессии или подхода.
 */
final class InvalidWorkoutSession extends DomainException
{
    public static function invalidUserId(): self
    {
        return new self('Идентификатор клиента должен быть положительным.');
    }

    public static function invalidPlanId(): self
    {
        return new self('Идентификатор тренировочного плана должен быть положительным.');
    }

    public static function titleRequired(): self
    {
        return new self('Название тренировочной сессии не может быть пустым.');
    }

    public static function titleTooLong(int $maxLength): self
    {
        return new self("Название тренировочной сессии не может быть длиннее {$maxLength} символов.");
    }

    public static function notesTooLong(int $maxLength): self
    {
        return new self("Заметка к тренировке не может быть длиннее {$maxLength} символов.");
    }

    public static function sessionNotInProgress(): self
    {
        return new self('Изменять подходы или завершать можно только активную тренировку.');
    }

    public static function finishedBeforeStarted(): self
    {
        return new self('Время завершения тренировки не может быть раньше её начала.');
    }

    public static function invalidExerciseId(): self
    {
        return new self('Идентификатор упражнения должен быть положительным.');
    }

    public static function invalidSetNumber(): self
    {
        return new self('Порядковый номер подхода должен быть не меньше 1.');
    }

    public static function invalidWeightGrams(): self
    {
        return new self('Вес в подходе не может быть отрицательным.');
    }

    public static function invalidReps(int $min, int $max): self
    {
        return new self("Количество повторений в подходе должно быть от {$min} до {$max}.");
    }

    public static function inactiveExercise(int $exerciseId): self
    {
        return new self(sprintf('Упражнение #%d неактивно и не может использоваться в тренировке.', $exerciseId));
    }
}
