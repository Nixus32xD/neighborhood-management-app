<?php

use App\Models\Neighborhood;
use App\Models\Owner;
use App\Models\PaymentExpense;
use App\Models\Unit;
use App\Models\UnitExpense;
use App\Models\User;
use Carbon\Carbon;
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
        ->where('expenses.0.status', 'paid')
        ->where('expenses.1.paid_amount', 200)
        ->where('expenses.1.outstanding_debt', 450)
    );
});

test('expenses index reflects unpaid fine after the monthly charge was already paid', function () {
    $neighborhood = Neighborhood::create([
        'name' => 'CC1',
        'expense_calculation_type' => 'fixed',
        'fixed_amount' => 0,
    ]);

    $unit = Unit::create([
        'neighborhood_id' => $neighborhood->id,
        'uf_number' => '7',
        'active' => true,
    ]);

    Owner::create([
        'unit_id' => $unit->id,
        'full_name' => 'Propietario Multa',
        'email' => 'fine@example.com',
        'people_count' => 1,
    ]);

    $expense = UnitExpense::create([
        'unit_id' => $unit->id,
        'period' => '2026-02',
        'monthly_amount' => 1000,
        'extraordinary_amount' => 0,
        'fines_amount' => 150,
        'paid_amount' => 1000,
    ]);

    $expense->payments()->create([
        'unit_id' => $unit->id,
        'amount' => 1000,
        'payment_date' => '2026-02-08',
        'payment_method' => 'cash',
    ]);

    $response = $this
        ->actingAs(User::factory()->create())
        ->withSession(['neighborhood_id' => $neighborhood->id])
        ->get(route('expenses.index', absolute: false));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Expenses/Index')
        ->where('summary.totalMonthly', 1000)
        ->where('summary.totalExtraordinary', 0)
        ->where('summary.totalFines', 150)
        ->where('summary.totalOutstanding', 150)
        ->where('summary.totalCollected', 1000)
        ->has('expenses', 1)
        ->where('expenses.0.monthly_expense', fn ($value) => (float) $value === 1000.0)
        ->where('expenses.0.fines', fn ($value) => (float) $value === 150.0)
        ->where('expenses.0.paid_amount', 1000)
        ->where('expenses.0.total_balance', fn ($value) => (float) $value === 1150.0)
        ->where('expenses.0.outstanding_debt', 150)
        ->where('expenses.0.status', 'overdue')
    );
});

test('extraordinary expense for proportional neighborhoods uses lot coefficients', function () {
    $neighborhood = Neighborhood::create([
        'name' => 'CC2',
        'expense_calculation_type' => 'proportional',
        'fixed_amount' => null,
    ]);

    $smallUnit = Unit::create([
        'neighborhood_id' => $neighborhood->id,
        'uf_number' => '1',
        'surface_area' => 500,
        'expense_coefficient' => 25,
        'active' => true,
    ]);

    $largeUnit = Unit::create([
        'neighborhood_id' => $neighborhood->id,
        'uf_number' => '2',
        'surface_area' => 1500,
        'expense_coefficient' => 75,
        'active' => true,
    ]);

    $response = $this
        ->actingAs(User::factory()->create())
        ->withSession(['neighborhood_id' => $neighborhood->id])
        ->post(route('expenses.extraordinary', absolute: false), [
            'period' => '2026-06',
            'amount' => 1000,
            'base_meters' => 500,
        ]);

    $response->assertRedirect()->assertSessionHasNoErrors();

    $smallExpense = UnitExpense::where('unit_id', $smallUnit->id)
        ->where('period', '2026-06')
        ->firstOrFail();

    $largeExpense = UnitExpense::where('unit_id', $largeUnit->id)
        ->where('period', '2026-06')
        ->firstOrFail();

    expect((float) $smallExpense->extraordinary_amount)->toBe(1000.0)
        ->and((float) $largeExpense->extraordinary_amount)->toBe(3000.0);
});

test('accumulated expense payment applies amount to oldest debts first', function () {
    Carbon::setTestNow('2026-08-15');

    try {
        $neighborhood = Neighborhood::create([
            'name' => 'CC1',
            'expense_calculation_type' => 'fixed',
            'fixed_amount' => 0,
        ]);

        $unit = Unit::create([
            'neighborhood_id' => $neighborhood->id,
            'uf_number' => '12',
            'active' => true,
        ]);

        $january = UnitExpense::create([
            'unit_id' => $unit->id,
            'period' => '2026-01',
            'monthly_amount' => 1000,
            'extraordinary_amount' => 0,
            'fines_amount' => 0,
            'paid_amount' => 0,
        ]);

        $february = UnitExpense::create([
            'unit_id' => $unit->id,
            'period' => '2026-02',
            'monthly_amount' => 800,
            'extraordinary_amount' => 0,
            'fines_amount' => 0,
            'paid_amount' => 300,
        ]);

        $february->payments()->create([
            'unit_id' => $unit->id,
            'amount' => 300,
            'payment_date' => '2026-02-10',
            'payment_method' => 'cash',
        ]);

        $march = UnitExpense::create([
            'unit_id' => $unit->id,
            'period' => '2026-03',
            'monthly_amount' => 500,
            'extraordinary_amount' => 0,
            'fines_amount' => 0,
            'paid_amount' => 0,
        ]);

        $future = UnitExpense::create([
            'unit_id' => $unit->id,
            'period' => '2026-09',
            'monthly_amount' => 900,
            'extraordinary_amount' => 0,
            'fines_amount' => 0,
            'paid_amount' => 0,
        ]);

        $response = $this
            ->actingAs(User::factory()->create())
            ->withSession(['neighborhood_id' => $neighborhood->id])
            ->post(route('expenses.accumulated.store', absolute: false), [
                'unit_id' => $unit->id,
                'amount' => 1500,
                'payment_date' => '2026-08-01',
                'payment_method' => 'cash',
                'reference' => 'Pago acumulado test',
            ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $appliedPayments = PaymentExpense::whereIn('unit_expense_id', [$january->id, $february->id, $march->id])
            ->where('reference', 'Pago acumulado test')
            ->orderBy('unit_expense_id')
            ->pluck('amount')
            ->map(fn ($amount) => (float) $amount)
            ->all();

        expect($appliedPayments)->toBe([1000.0, 500.0])
            ->and((float) $january->refresh()->paid_amount)->toBe(1000.0)
            ->and((float) $february->refresh()->paid_amount)->toBe(800.0)
            ->and((float) $march->refresh()->paid_amount)->toBe(0.0)
            ->and($future->payments()->count())->toBe(0);
    } finally {
        Carbon::setTestNow();
    }
});

test('accumulated expense payment rejects amounts above current accumulated debt', function () {
    Carbon::setTestNow('2026-08-15');

    try {
        $neighborhood = Neighborhood::create([
            'name' => 'CC1',
            'expense_calculation_type' => 'fixed',
            'fixed_amount' => 0,
        ]);

        $unit = Unit::create([
            'neighborhood_id' => $neighborhood->id,
            'uf_number' => '15',
            'active' => true,
        ]);

        $expense = UnitExpense::create([
            'unit_id' => $unit->id,
            'period' => '2026-01',
            'monthly_amount' => 1000,
            'extraordinary_amount' => 0,
            'fines_amount' => 0,
            'paid_amount' => 0,
        ]);

        $response = $this
            ->actingAs(User::factory()->create())
            ->withSession(['neighborhood_id' => $neighborhood->id])
            ->post(route('expenses.accumulated.store', absolute: false), [
                'unit_id' => $unit->id,
                'amount' => 1500,
                'payment_date' => '2026-08-01',
                'payment_method' => 'cash',
            ]);

        $response->assertRedirect()->assertSessionHasErrors('amount');

        expect($expense->payments()->count())->toBe(0)
            ->and((float) $expense->refresh()->paid_amount)->toBe(0.0);
    } finally {
        Carbon::setTestNow();
    }
});
