<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface CurrentCompanyInterface
{
    /**
     * Получить ID текущей компании.
     *
     * @throws \RuntimeException если компания не установлена.
     */
    public function id(): int;

    /**
     * Установить ID текущей компании.
     */
    public function setId(int $id): void;

    /**
     * Проверить, установлена ли компания.
     */
    public function hasId(): bool;
}
