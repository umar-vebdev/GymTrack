<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Domain\Exceptions\GymPhotoNotFound;
use App\Modules\Companies\Domain\GymPhotoRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class DeleteGymPhoto
{
    public function __construct(
        private readonly GymRepositoryInterface $gyms,
        private readonly GymPhotoRepositoryInterface $photos,
        private readonly GymProfileRepositoryInterface $profiles,
    ) {}

    public function execute(int $companyId, int $gymId, int $photoId): void
    {
        $path = null;
        $backup = null;
        $disk = Storage::disk('public');

        try {
            DB::transaction(function () use ($companyId, $gymId, $photoId, $disk, &$path, &$backup): void {
                $gym = $this->gyms->findByIdForUpdate($gymId);
                if ($gym === null || $gym->companyId() !== $companyId) {
                    throw GymNotFound::withId($gymId);
                }

                $photo = null;
                foreach ($this->photos->listForGym($companyId, $gymId) as $candidate) {
                    if ($candidate->id === $photoId) {
                        $photo = $candidate;
                        break;
                    }
                }
                if ($photo === null) {
                    throw GymPhotoNotFound::withId($photoId);
                }

                $profile = $this->profiles->findByGymId($gymId) ?? GymProfile::draft($gymId);
                $profile->markContentChanged();
                if (!$this->photos->delete($companyId, $gymId, $photoId)) {
                    throw GymPhotoNotFound::withId($photoId);
                }
                $this->profiles->save($profile);

                $path = $photo->path;
                if ($disk->exists($path)) {
                    $backup = $disk->get($path);
                    if ($backup === null || !$disk->delete($path)) {
                        throw new RuntimeException('Не удалось удалить фотографию.');
                    }
                }
            });
        } catch (Throwable $exception) {
            if ($path !== null && $backup !== null && !$disk->exists($path) && !$disk->put($path, $backup)) {
                throw new RuntimeException('Не удалось восстановить фотографию после сбоя.', 0, $exception);
            }

            throw $exception;
        }
    }
}
