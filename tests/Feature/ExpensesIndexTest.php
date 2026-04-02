<?php

use App\Models\Neighborhood;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\UnitExpense;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('expenses index exposes actual paid amounts and summary collected total', function () {
    $neighborhood = Neighborhood::create([
        'name' => 'CC1',
        'expense_calculation_type' => 'fixed',
        'fixed_amount' => 0,
    ]);

    $unit = Unit::create([
        'neighborhood_id' => $neighborhood->id,
        'uf_number' => '1',
        'active' => true,
    ]);

    Owner::create([
        'unit_id' => $unit->id,
        'full_name' => 'Propietario Test',
        'email' => 'owner@example.com',
        'people_count' => 1,
    ]);

    $expensePaidAboveCharge = UnitExpense::create([
        'unit_id' => $unit->id,
        'period' => '2026-01',
        'monthly_amount' => 1000,
        'extraordinary_amount' => 0,
        'fines_amount' => 0,
        'paid_amount' => 1200,
    ]);

    $expensePaidAboveCharge->payments()->create([
        'unit_id' => $unit->id,
        'amount' => 1200,
        'payment_date' => '2026-01-15',
        'payment_method' => 'cash',
    ]);

    $expensePartiallyPaid = UnitExpense::create([
        'unit_id' => $unit->id,
        'period' => '2026-02',
        'monthly_amount' => 500,
        'extraordinary_amount' => 100,
        'fines_amount' => 50,
        'paid_amount' => 200,
    ]);

    $expensePartiallyPaid->payments()->create([
        'unit_id' => $unit->id,
        'amount' => 200,
        'payment_date' => '2026-02-10',
        'payment_method' => 'cash',
    ]);

    $response = $this
        ->actingAs(User::factory()->create())
        ->withSession(['neighborhood_id' => $neighborhood->id])
        ->get(route('expenses.index', absolute: false));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Expenses/Index')
        ->where('summary.totalMonthly', 1500)
        ->where('summary.totalExtraordinary', 100)
        ->where('summary.totalFines', 50)
        ->where('summary.totalOutstanding', 450)
        ->where('summary.totalCollected', 1400)
        ->has('expenses', 2)
        ->where('expenses.0.paid_amount', 1200)
        ->where('expenses.0.outstanding_debt', 0)
        ->where('expenses.1.paid_amount', 200)
        ->where('expenses.1.outstanding_debt', 450)
    );
});
