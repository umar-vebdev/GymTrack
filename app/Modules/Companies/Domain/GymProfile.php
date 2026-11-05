<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

use App\Modules\Companies\Domain\Exceptions\InvalidGymProfileTransition;
use DateTimeImmutable;

final class GymProfile
{
    /**
     * @param  array<string, mixed>  $workingHours
     * @param  list<string>  $amenities
     */
    public function __construct(
        private readonly int $gymId,
        private ?string $description,
        private ?string $address,
        private ?float $latitude,
        private ?float $longitude,
        private ?string $phone,
        private array $workingHours,
        private array $amenities,
        private ModerationStatus $status,
        private ?string $rejectionReason,
        private ?DateTimeImmutable $publishedAt,
        private ?string $district = null,
        private bool $isPublic = false,
    ) {
        if ($this->isPublic && $this->status !== ModerationStatus::APPROVED) {
            throw InvalidGymProfileTransition::from($this->status, 'publish');
        }
    }

    public static function draft(int $gymId): self
    {
        return new self($gymId, null, null, null, null, null, [], [], ModerationStatus::DRAFT, null, null);
    }

    /**
     * Правка содержимого всегда возвращает витрину в черновик: изменённое нужно модерировать заново.
     *
     * @param  array<string, mixed>  $workingHours
     * @param  list<string>  $amenities
     */
    public function update(
        ?string $description,
        ?string $address,
        ?float $latitude,
        ?float $longitude,
        ?string $phone,
        array $workingHours,
        array $amenities,
        ?string $district = null,
    ): void {
        $this->markContentChanged();

        $this->description = $description;
        $this->address = $address;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->phone = $phone;
        $this->workingHours = $workingHours;
        $this->amenities = $amenities;
        $this->district = $district;
    }

    public function markContentChanged(): void
    {
        if ($this->status === ModerationStatus::PENDING) {
            throw InvalidGymProfileTransition::from($this->status, 'update');
        }

        $this->status = ModerationStatus::DRAFT;
        $this->isPublic = false;
        $this->rejectionReason = null;
        $this->publishedAt = null;
    }

    public function submit(): void
    {
        if (!in_array($this->status, [ModerationStatus::DRAFT, ModerationStatus::REJECTED], true)) {
            throw InvalidGymProfileTransition::from($this->status, 'submit');
        }
        if (!$this->isComplete()) {
            throw InvalidGymProfileTransition::incomplete();
        }

        $this->status = ModerationStatus::PENDING;
        $this->rejectionReason = null;
    }

    public function approve(DateTimeImmutable $at): void
    {
        $this->assertPending('approve');

        $this->status = ModerationStatus::APPROVED;
        $this->rejectionReason = null;
        $this->publishedAt = $at;
    }

    public function reject(string $reason): void
    {
        $this->assertPending('reject');
        if (trim($reason) === '') {
            throw InvalidGymProfileTransition::reasonRequired();
        }

        $this->status = ModerationStatus::REJECTED;
        $this->rejectionReason = $reason;
        $this->publishedAt = null;
    }

    public function isComplete(): bool
    {
        return $this->filled($this->description) && $this->filled($this->address) && $this->filled($this->phone);
    }

    public function gymId(): int
    {
        return $this->gymId;
    }

    public function setPublication(bool $isPublic): void
    {
        if ($isPublic && $this->status !== ModerationStatus::APPROVED) {
            throw InvalidGymProfileTransition::from($this->status, 'publish');
        }

        $this->isPublic = $isPublic;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function district(): ?string
    {
        return $this->district;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function latitude(): ?float
    {
        return $this->latitude;
    }

    public function longitude(): ?float
    {
        return $this->longitude;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    /** @return array<string, mixed> */
    public function workingHours(): array
    {
        return $this->workingHours;
    }

    /** @return list<string> */
    public function amenities(): array
    {
        return $this->amenities;
    }

    public function status(): ModerationStatus
    {
        return $this->status;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    private function assertPending(string $action): void
    {
        if ($this->status !== ModerationStatus::PENDING) {
            throw InvalidGymProfileTransition::from($this->status, $action);
        }
    }

    private function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
