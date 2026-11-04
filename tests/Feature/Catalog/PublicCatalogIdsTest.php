<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;
use App\Modules\Memberships\Infrastructure\Persistence\MembershipTypeModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PublicCatalogIdsTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create();
        $this->companyId = CompanyModel::query()->create(['owner_id' => $owner->id, 'name' => 'Corp'])->id;
    }

    /** @return array<string, mixed> */
    private function typeAttributes(): array
    {
        return [
            'company_id' => $this->companyId, 'name' => '12 visits', 'kind' => 'visits',
            'start_mode' => 'first_visit', 'price_minor' => 60000, 'currency' => 'TJS', 'visits_count' => 12,
        ];
    }

    /** @return list<Migration> */
    private function publicIdMigrations(): array
    {
        return [
            require base_path('app/Modules/Companies/Infrastructure/Migrations/2026_11_04_000001_add_public_id_to_gyms_table.php'),
            require base_path('app/Modules/Memberships/Infrastructure/Migrations/2026_11_04_000002_add_public_id_to_membership_types_table.php'),
        ];
    }

    private function runMigration(Migration $migration, string $direction): void
    {
        $callback = [$migration, $direction];
        if (!is_callable($callback)) {
            $this->fail('Migration lifecycle method is missing');
        }
        $callback();
    }

    public function test_new_records_receive_unique_public_ids_while_primary_keys_remain_numeric(): void
    {
        $models = [
            GymModel::query()->create(['company_id' => $this->companyId, 'name' => 'Gym A']),
            GymModel::query()->create(['company_id' => $this->companyId, 'name' => 'Gym B']),
            MembershipTypeModel::query()->create($this->typeAttributes()),
            MembershipTypeModel::query()->create($this->typeAttributes()),
        ];
        $publicIds = [];
        foreach ($models as $model) {
            $this->assertTrue(Str::isUlid($model->public_id));
            $this->assertIsInt($model->getKey());
            $this->assertSame('int', $model->getKeyType());
            $this->assertTrue($model->getIncrementing());
            $publicIds[] = $model->public_id;
            $original = $model->public_id;
            $model->update(['name' => 'Updated']);
            $this->assertSame($original, $model->refresh()->public_id);
        }
        $this->assertCount(4, array_unique($publicIds));
    }

    public function test_migrations_backfill_legacy_records_and_rollback_without_losing_data(): void
    {
        $migrations = $this->publicIdMigrations();
        foreach ($migrations as $migration) {
            $this->runMigration($migration, 'down');
        }
        $gymId = DB::table('gyms')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Legacy gym', 'address' => 'Address',
        ]);
        $typeId = DB::table('membership_types')->insertGetId($this->typeAttributes());
        $secondGymId = DB::table('gyms')->insertGetId(['company_id' => $this->companyId, 'name' => 'Second']);
        $secondTypeId = DB::table('membership_types')->insertGetId($this->typeAttributes());
        foreach ($migrations as $migration) {
            $this->runMigration($migration, 'up');
        }

        $gym = GymModel::query()->findOrFail($gymId);
        $type = MembershipTypeModel::query()->findOrFail($typeId);
        $this->assertTrue(Str::isUlid($gym->public_id));
        $this->assertTrue(Str::isUlid($type->public_id));
        $this->assertNotSame($gym->public_id, GymModel::query()->findOrFail($secondGymId)->public_id);
        $this->assertNotSame($type->public_id, MembershipTypeModel::query()->findOrFail($secondTypeId)->public_id);
        $this->assertSame($this->companyId, $gym->company_id);
        $this->assertSame($this->companyId, $type->company_id);

        foreach ($migrations as $migration) {
            $this->runMigration($migration, 'down');
        }
        $this->assertFalse(Schema::hasColumn('gyms', 'public_id'));
        $this->assertFalse(Schema::hasColumn('membership_types', 'public_id'));
        $this->assertDatabaseHas('gyms', ['id' => $gymId, 'name' => 'Legacy gym', 'address' => 'Address']);
        $this->assertDatabaseHas('membership_types', ['id' => $typeId, 'price_minor' => 60000, 'currency' => 'TJS']);
        foreach ($migrations as $migration) {
            $this->runMigration($migration, 'up');
        }
        $this->assertTrue(Str::isUlid(GymModel::query()->findOrFail($gymId)->public_id));
        $this->assertTrue(Str::isUlid(MembershipTypeModel::query()->findOrFail($typeId)->public_id));
    }

    /** @return array<string, array{string, bool, string}> */
    public static function constraints(): array
    {
        return [
            'gym unique' => ['gyms', false, '23505'],
            'gym required' => ['gyms', true, '23502'],
            'type unique' => ['membership_types', false, '23505'],
            'type required' => ['membership_types', true, '23502'],
        ];
    }

    #[DataProvider('constraints')]
    public function test_database_rejects_duplicate_and_null_public_ids(string $table, bool $null, string $sqlState): void
    {
        if ($table === 'gyms') {
            $first = GymModel::query()->create(['company_id' => $this->companyId, 'name' => 'A']);
            $second = GymModel::query()->create(['company_id' => $this->companyId, 'name' => 'B']);
        } else {
            $first = MembershipTypeModel::query()->create($this->typeAttributes());
            $second = MembershipTypeModel::query()->create($this->typeAttributes());
        }
        try {
            DB::transaction(fn (): int => DB::table($table)->where('id', $second->id)
                ->update(['public_id' => $null ? null : $first->public_id]));
            $this->fail('Invalid public ID was accepted');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->getCode());
        }
        $this->assertDatabaseHas($table, ['id' => $second->id, 'public_id' => $second->public_id]);
    }
}
