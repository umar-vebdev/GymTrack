<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

interface GymPhotoRepositoryInterface
{
    public function create(int $companyId, int $gymId, string $path, int $order): GymPhoto;

    /** @return list<GymPhoto> */
    public function listForGym(int $companyId, int $gymId): array;

    public function delete(int $companyId, int $gymId, int $photoId): bool;
}
