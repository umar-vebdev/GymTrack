<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Workouts\Domain\MuscleGroup;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Infrastructure\Persistence\ExerciseModel;
use App\Modules\Workouts\Infrastructure\Persistence\WorkoutPlanItemModel;
use App\Modules\Workouts\Infrastructure\Persistence\WorkoutPlanModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Сидер базового справочника упражнений и готовых тренировочных планов платформы (Бизнес-ТЗ 9; D-016).
 */
final class WorkoutsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $exercisesByName = $this->seedExercises();
            $this->seedPlans($exercisesByName);
        });
    }

    /**
     * @return array<string, ExerciseModel>
     */
    private function seedExercises(): array
    {
        $seeded = [];

        foreach ($this->exercises() as $item) {
            $exercise = ExerciseModel::query()->updateOrCreate(
                [
                    'user_id' => null,
                    'name' => $item['name'],
                ],
                [
                    'muscle_group' => $item['muscle_group']->value,
                    'description' => $item['description'],
                    'is_active' => true,
                ],
            );

            $seeded[$item['name']] = $exercise;
        }

        return $seeded;
    }

    /**
     * @param  array<string, ExerciseModel>  $exercisesByName
     */
    private function seedPlans(array $exercisesByName): void
    {
        foreach ($this->plans() as $planData) {
            $plan = WorkoutPlanModel::query()->updateOrCreate(
                [
                    'user_id' => null,
                    'title' => $planData['title'],
                ],
                [
                    'source_plan_id' => null,
                    'description' => $planData['description'],
                    'level' => $planData['level']->value,
                    'is_published' => true,
                    'is_active' => true,
                ],
            );

            $this->syncPlanItems($plan->id, $planData['items'], $exercisesByName);
        }
    }

    /**
     * @param  list<array{
     *     exercise: string,
     *     sets: int,
     *     reps: int,
     *     weight_grams: int|null,
     *     rest_seconds: int|null,
     * }>  $items
     * @param  array<string, ExerciseModel>  $exercisesByName
     */
    private function syncPlanItems(int $planId, array $items, array $exercisesByName): void
    {
        WorkoutPlanItemModel::query()->where('plan_id', $planId)->delete();

        foreach ($items as $sortOrder => $item) {
            WorkoutPlanItemModel::query()->create([
                'plan_id' => $planId,
                'exercise_id' => $exercisesByName[$item['exercise']]->id,
                'sort_order' => $sortOrder,
                'target_sets' => $item['sets'],
                'target_reps' => $item['reps'],
                'target_weight_grams' => $item['weight_grams'],
                'rest_seconds' => $item['rest_seconds'],
            ]);
        }
    }

    /**
     * @return list<array{name: string, muscle_group: MuscleGroup, description: string}>
     */
    private function exercises(): array
    {
        return [
            ['name' => 'Жим штанги лёжа', 'muscle_group' => MuscleGroup::CHEST, 'description' => 'Базовое упражнение для грудных мышц, трицепсов и передних дельт.'],
            ['name' => 'Отжимания на брусьях', 'muscle_group' => MuscleGroup::CHEST, 'description' => 'Упражнение с собственным весом для нижней части груди и трицепсов.'],
            ['name' => 'Подтягивания широким хватом', 'muscle_group' => MuscleGroup::BACK, 'description' => 'Базовое упражнение для широчайших мышц спины и бицепсов.'],
            ['name' => 'Тяга верхнего блока', 'muscle_group' => MuscleGroup::BACK, 'description' => 'Упражнение на широчайшие и ромбовидные мышцы спины.'],
            ['name' => 'Приседания со штангой', 'muscle_group' => MuscleGroup::LEGS, 'description' => 'Базовое многосуставное упражнение на квадрицепсы, ягодицы и кор.'],
            ['name' => 'Румынская тяга с гантелями', 'muscle_group' => MuscleGroup::LEGS, 'description' => 'Упражнение на заднюю поверхность бедра и ягодичные мышцы.'],
            ['name' => 'Жим гантелей сидя', 'muscle_group' => MuscleGroup::SHOULDERS, 'description' => 'Базовое упражнение для переднего и среднего пучков плеч.'],
            ['name' => 'Махи гантелями в стороны', 'muscle_group' => MuscleGroup::SHOULDERS, 'description' => 'Изолированное упражнение на средний пучок дельтовидных мышц.'],
            ['name' => 'Подъём штанги на бицепс', 'muscle_group' => MuscleGroup::ARMS, 'description' => 'Классическое упражнение для развития двуглавой мышцы плеча.'],
            ['name' => 'Разгибания рук на верхнем блоке', 'muscle_group' => MuscleGroup::ARMS, 'description' => 'Изолированное упражнение для проработки трицепса.'],
            ['name' => 'Скручивания на пресс', 'muscle_group' => MuscleGroup::CORE, 'description' => 'Базовое упражнение для прямой мышцы живота.'],
            ['name' => 'Планка', 'muscle_group' => MuscleGroup::CORE, 'description' => 'Статическое упражнение для укрепления мышц кора и стабилизаторов.'],
            ['name' => 'Беговая дорожка (интервалы)', 'muscle_group' => MuscleGroup::CARDIO, 'description' => 'Кардионагрузка для развития выносливости.'],
            ['name' => 'Бёрпи', 'muscle_group' => MuscleGroup::FULL_BODY, 'description' => 'Функциональное упражнение на всё тело и общую выносливость.'],
        ];
    }

    /**
     * @return list<array{
     *     title: string,
     *     description: string,
     *     level: PlanLevel,
     *     items: list<array{
     *         exercise: string,
     *         sets: int,
     *         reps: int,
     *         weight_grams: int|null,
     *         rest_seconds: int|null,
     *     }>,
     * }>
     */
    private function plans(): array
    {
        return [
            [
                'title' => 'Базовый старт (Всё тело)',
                'description' => 'Универсальная программа на всё тело для освоения техники базовых движений.',
                'level' => PlanLevel::Beginner,
                'items' => [
                    ['exercise' => 'Приседания со штангой', 'sets' => 3, 'reps' => 10, 'weight_grams' => null, 'rest_seconds' => 90],
                    ['exercise' => 'Жим штанги лёжа', 'sets' => 3, 'reps' => 10, 'weight_grams' => null, 'rest_seconds' => 90],
                    ['exercise' => 'Тяга верхнего блока', 'sets' => 3, 'reps' => 12, 'weight_grams' => null, 'rest_seconds' => 90],
                    ['exercise' => 'Жим гантелей сидя', 'sets' => 3, 'reps' => 12, 'weight_grams' => null, 'rest_seconds' => 60],
                    ['exercise' => 'Скручивания на пресс', 'sets' => 3, 'reps' => 15, 'weight_grams' => null, 'rest_seconds' => 60],
                ],
            ],
            [
                'title' => 'Силовой баланс (Верх и низ)',
                'description' => 'Сбалансированная силовая тренировка со свободными весами для среднего уровня.',
                'level' => PlanLevel::Intermediate,
                'items' => [
                    ['exercise' => 'Приседания со штангой', 'sets' => 4, 'reps' => 8, 'weight_grams' => 60000, 'rest_seconds' => 120],
                    ['exercise' => 'Жим штанги лёжа', 'sets' => 4, 'reps' => 8, 'weight_grams' => 50000, 'rest_seconds' => 120],
                    ['exercise' => 'Подтягивания широким хватом', 'sets' => 4, 'reps' => 8, 'weight_grams' => null, 'rest_seconds' => 90],
                    ['exercise' => 'Румынская тяга с гантелями', 'sets' => 3, 'reps' => 10, 'weight_grams' => 20000, 'rest_seconds' => 90],
                    ['exercise' => 'Подъём штанги на бицепс', 'sets' => 3, 'reps' => 12, 'weight_grams' => 25000, 'rest_seconds' => 60],
                ],
            ],
            [
                'title' => 'Интенсивная выносливость',
                'description' => 'Высокообъёмная тренировка для развития силовой и кардио-выносливости.',
                'level' => PlanLevel::Advanced,
                'items' => [
                    ['exercise' => 'Приседания со штангой', 'sets' => 5, 'reps' => 6, 'weight_grams' => 80000, 'rest_seconds' => 120],
                    ['exercise' => 'Подтягивания широким хватом', 'sets' => 4, 'reps' => 12, 'weight_grams' => null, 'rest_seconds' => 90],
                    ['exercise' => 'Отжимания на брусьях', 'sets' => 4, 'reps' => 12, 'weight_grams' => null, 'rest_seconds' => 90],
                    ['exercise' => 'Бёрпи', 'sets' => 4, 'reps' => 15, 'weight_grams' => null, 'rest_seconds' => 60],
                    ['exercise' => 'Беговая дорожка (интервалы)', 'sets' => 1, 'reps' => 20, 'weight_grams' => null, 'rest_seconds' => 60],
                ],
            ],
        ];
    }
}

