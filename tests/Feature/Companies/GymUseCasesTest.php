<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Application\CreateGym;
use App\Modules\Companies\Application\ListCompanyGyms;
use App\Modules\Companies\Application\UpdateGym;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class GymUseCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_lists_and_updates_company_gyms(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        /** @var CompanyRepositoryInterface $companies */
        $companies = $this->app->make(CompanyRepositoryInterface::class);
        $companyA = $companies->create($ownerA->id, 'Alpha Fitness');
        $companyB = $companies->create($ownerB->id, 'Beta Fitness');

        /** @var CreateGym $createGym */
        $createGym = $this->app->make(CreateGym::class);
        /** @var ListCompanyGyms $listGyms */
        $listGyms = $this->app->make(ListCompanyGyms::class);
        /** @var UpdateGym $updateGym */
        $updateGym = $this->app->make(UpdateGym::class);
        /** @var GymRepositoryInterface $repo */
        $repo = $this->app->make(GymRepositoryInterface::class);

        $gym1 = $createGym->execute($companyA, '  Центральный  ', '  ул. Рудаки 10  ');
        $gym2 = $createGym->execute($companyA, 'Филиал Север', null, 'Europe/Moscow');
        $gymB = $createGym->execute($companyB, 'Чужой зал', null);

        expect($gym1->name())->toBe('Центральный')
            ->and($gym1->address())->toBe('ул. Рудаки 10')
            ->and($gym1->timezone())->toBe('Asia/Dushanbe')
            ->and($gym2->timezone())->toBe('Europe/Moscow');

        $listA = $listGyms->execute($companyA);
        expect($listA)->toHaveCount(2)
            ->and($listA[0]->id())->toBe($gym1->id())
            ->and($listA[1]->id())->toBe($gym2->id());

        $updated = $updateGym->execute($companyA, $gym1->id(), 'Центральный Pro', null, 'Asia/Almaty');
        expect($updated->name())->toBe('Центральный Pro')
            ->and($updated->address())->toBeNull()
            ->and($updated->timezone())->toBe('Asia/Almaty');

        $reloaded = $repo->findById($gym1->id());
        expect($reloaded?->name())->toBe('Центральный Pro')
            ->and($reloaded?->timezone())->toBe('Asia/Almaty');

        // Обновление чужого зала запрещено
        expect(fn () => $updateGym->execute($companyA, $gymB->id(), 'Hack', null))
            ->toThrow(GymNotFound::class);
    }

    public function test_it_rejects_empty_name_or_invalid_timezone(): void
    {
        $owner = User::factory()->create();

        /** @var CompanyRepositoryInterface $companies */
        $companies = $this->app->make(CompanyRepositoryInterface::class);
        $companyId = $companies->create($owner->id, 'Alpha Fitness');

        /** @var CreateGym $createGym */
        $createGym = $this->app->make(CreateGym::class);

        expect(fn () => $createGym->execute($companyId, '   '))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $createGym->execute($companyId, 'Valid', null, 'Mars/Olympus'))
            ->toThrow(InvalidArgumentException::class);
    }
}

