<?php

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\Household;
use App\Models\Transaction;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;

it('stores every sensitive column as an encrypted payload and reads it back', function () {
    $household = Household::factory()->create(['name' => 'Rumah Rahasia Kami']);
    $budget = Budget::factory()->for($household)
        ->create([
            'name' => 'Tabungan Liburan Jepang',
            'default_amount' => 7_391_204,
        ]);
    $month = BudgetMonth::factory()->for($budget)->create([
        'allocated' => 8_460_137,
        'carried_in' => -5_283_091,
    ]);
    $transaction = Transaction::factory()->create([
        'household_id' => $household->id,
        'budget_month_id' => $month->id,
        'amount' => 6_172_845,
        'note' => 'Kado ulang tahun kejutan',
    ]);
    $goal = Goal::factory()->for($household)->create([
        'name' => 'Liburan Pantai Rahasia',
        'target_amount' => 4_915_372,
    ]);
    $movement = GoalMovement::factory()->create([
        'goal_id' => $goal->id,
        'household_id' => $household->id,
        'amount' => 3_184_902,
        'note' => 'Setoran liburan rahasia',
    ]);

    $columns = [
        ['households', 'name'],
        ['budgets', 'name'],
        ['budgets', 'default_amount'],
        ['budget_months', 'allocated'],
        ['budget_months', 'carried_in'],
        ['transactions', 'amount'],
        ['transactions', 'note'],
        ['goals', 'name'],
        ['goals', 'target_amount'],
        ['goal_movements', 'amount'],
        ['goal_movements', 'note'],
    ];

    foreach ($columns as [$table, $column]) {
        $payload = json_decode(base64_decode((string) DB::table($table)->value($column)), true);

        expect($payload)->toHaveKeys(['iv', 'value', 'mac'], "{$table}.{$column} is not encrypted");
    }

    // nothing readable anywhere in these tables, not even in a column I forgot about
    $everything = collect([
        'households',
        'budgets',
        'budget_months',
        'transactions',
        'goals',
        'goal_movements',
        'household_invites',
        'household_user',
    ])
        ->map(fn ($table) => DB::table($table)->get()->toJson())->implode(' ');

    foreach (
        [
            'Rumah Rahasia Kami',
            'Tabungan Liburan Jepang',
            '7391204',
            '8460137',
            '5283091',
            '6172845',
            'Kado ulang tahun kejutan',
            'Liburan Pantai Rahasia',
            '4915372',
            'Setoran liburan rahasia',
            '3184902',
        ] as $secret
    ) {
        expect($everything)->not->toContain($secret);
    }

    expect($household->fresh()->name)->toBe('Rumah Rahasia Kami')
        ->and($budget->fresh()->default_amount)->toBe(7_391_204)
        ->and($month->fresh()->carried_in)->toBe(-5_283_091)
        ->and($transaction->fresh()->amount)->toBe(6_172_845)
        ->and($transaction->fresh()->note)->toBe('Kado ulang tahun kejutan')
        ->and($goal->fresh()->name)->toBe('Liburan Pantai Rahasia')
        ->and($goal->fresh()->target_amount)->toBe(4_915_372)
        ->and($movement->fresh()->amount)->toBe(3_184_902)
        ->and($movement->fresh()->note)->toBe('Setoran liburan rahasia');
});

it('cannot read the data with a different app key', function () {
    $household = Household::factory()->create(['name' => 'Rumah Rahasia Kami']);
    $raw = DB::table('households')->value('name');

    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    app()->forgetInstance('encrypter');

    expect(fn () => decrypt($raw))->toThrow(DecryptException::class);
});
