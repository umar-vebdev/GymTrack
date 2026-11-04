<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Domain\GymPhoto;
use App\Modules\Companies\Domain\GymPhotoRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class UploadGymPhoto
{
    public function __construct(
        private readonly GymRepositoryInterface $gyms,
        private readonly GymPhotoRepositoryInterface $photos,
        private readonly GymProfileRepositoryInterface $profiles,
    ) {}

    public function execute(int $companyId, int $gymId, UploadedFile $file): GymPhoto
    {
        $path = null;

        try {
            return DB::transaction(function () use ($companyId, $gymId, $file, &$path): GymPhoto {
                $gym = $this->gyms->findByIdForUpdate($gymId);
                if ($gym === null || $gym->companyId() !== $companyId) {
                    throw GymNotFound::withId($gymId);
                }

                $profile = $this->profiles->findByGymId($gymId) ?? GymProfile::draft($gymId);
                $profile->markContentChanged();
                $photos = $this->photos->listForGym($companyId, $gymId);
                if (count($photos) >= 10) {
                    throw new DomainException('Можно загрузить не более 10 фотографий зала.');
                }

                $stored = $file->store("companies/{$companyId}/gyms/{$gymId}", 'public');
                if ($stored === false) {
                    throw new RuntimeException('Не удалось сохранить фотографию.');
                }
                $path = $stored;
                $order = $photos === [] ? 0 : max(array_column($photos, 'order')) + 1;
                $photo = $this->photos->create($companyId, $gymId, $stored, $order);
                $this->profiles->save($profile);

                return $photo;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }
    }
}
