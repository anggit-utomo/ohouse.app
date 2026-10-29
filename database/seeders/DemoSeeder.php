<?php

namespace Database\Seeders;

use App\Actions\CreateHousehold;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $me = User::factory()->create(['name' => 'Anggit', 'email' => 'anggit@example.test']);
        $wife = User::factory()->create(['name' => 'Wife', 'email' => 'wife@example.test']);

        $household = app(CreateHousehold::class)->handle($me, 'Our household');
        $household->members()->attach($wife->id, ['role' => 'editor']);
        $wife->forceFill(['current_household_id' => $household->id])->save();

        $plan = [
            'Food' => [2_000_000, [85_000, 120_000, 1_435_000]],
            'Internet' => [400_000, [400_000]],
            'Kitchen items' => [800_000, [62_000, 468_000]],
            'Gasoline' => [700_000, [50_000, 540_000]],
            'Transport' => [600_000, [38_000, 672_000]],
        ];

        $period = now()->startOfMonth();
        $i = 0;

        foreach ($plan as $name => [$amount, $spends]) {
            $budget = $household->budgets()->create(['name' => $name, 'default_amount' => $amount, 'sort' => $i++]);
            $month = $budget->months()->create(['period_start' => $period->toDateString(), 'allocated' => $amount, 'carried_in' => 0]);

            foreach ($spends as $n => $spent) {
                $month->transactions()->create([
                    'household_id' => $household->id,
                    'user_id' => $n % 2 ? $wife->id : $me->id,
                    'type' => 'expense',
                    'amount' => $spent,
                    'note' => "Demo {$name}",
                    'occurred_at' => $period->copy()->addDays(min($n * 3, 20))->setTime(12, 0),
                ]);
            }
        }
    }
}
