<?php

use App\Models\BankAccount;
use App\Models\Neighborhood;
use App\Models\Owner;
use App\Models\PaymentExpense;
use App\Models\PaymentPlan;
use App\Models\Unit;
use App\Models\UnitExpense;
use App\Models\User;
use App\Services\UnitDebtService;
use Inertia\Testing\AssertableInertia;

function paymentPlanScenario(): array
{
    $neighborhood = Neighborhood::create(['name' => 'Barrio Planes', 'expense_calculation_type' => 'fixed', 'fixed_amount' => 0]);
    $unit = Unit::create(['neighborhood_id' => $neighborhood->id, 'uf_number' => '15', 'active' => true]);
    $owner = Owner::create(['unit_id' => $unit->id, 'full_name' => 'Juan Pérez', 'people_count' => 1]);
    $expenses = collect(['2026-05', '2026-06', '2026-07'])->map(fn ($period) => UnitExpense::create([
        'unit_id' => $unit->id, 'period' => $period, 'monthly_amount' => 120000, 'extraordinary_amount' => 0, 'fines_amount' => 0, 'paid_amount' => 0,
    ]));
    return [$neighborhood, $unit, $owner, $expenses];
}

function createPaymentPlanForTest(Neighborhood $neighborhood, Unit $unit, Owner $owner, $expenses): PaymentPlan
{
    $response = test()->actingAs(User::factory()->create())->withSession(['neighborhood_id' => $neighborhood->id])
        ->post(route('payment-plans.store', absolute: false), [
            'unit_id' => $unit->id, 'owner_id' => $owner->id, 'installments_count' => 3, 'start_date' => '2026-08-10',
            'items' => $expenses->map(fn ($expense) => ['unit_expense_id' => $expense->id, 'amount' => 120000])->all(),
        ]);
    $response->assertRedirect()->assertSessionHasNoErrors();
    return PaymentPlan::firstOrFail();
}

test('eligible units are ordered by UF number rather than lexicographic text', function () {
    $neighborhood = Neighborhood::create(['name' => 'Orden UF', 'expense_calculation_type' => 'fixed', 'fixed_amount' => 0]);
    foreach (['1', '10', '2'] as $number) {
        $unit = Unit::create(['neighborhood_id' => $neighborhood->id, 'uf_number' => $number, 'active' => true]);
        Owner::create(['unit_id' => $unit->id, 'full_name' => "Propietario {$number}", 'people_count' => 1]);
        UnitExpense::create(['unit_id' => $unit->id, 'period' => '2026-08', 'monthly_amount' => 100, 'extraordinary_amount' => 0, 'fines_amount' => 0, 'paid_amount' => 0]);
    }

    $this->actingAs(User::factory()->create())->withSession(['neighborhood_id' => $neighborhood->id])
        ->get(route('payment-plans.index', absolute: false))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('eligibleUnits.0.uf_number', 'UF-1')
            ->where('eligibleUnits.1.uf_number', 'UF-2')
            ->where('eligibleUnits.2.uf_number', 'UF-10')
        );
});

test('creates a plan without marking original expenses as paid and excludes active financed debt', function () {
    [$neighborhood, $unit, $owner, $expenses] = paymentPlanScenario();
    $plan = createPaymentPlanForTest($neighborhood, $unit, $owner, $expenses);

    expect($plan->status)->toBe('active')->and((float) $plan->original_amount)->toBe(360000.0)
        ->and((float) $plan->outstanding_amount)->toBe(360000.0)->and($plan->installments()->count())->toBe(3)
        ->and((float) $expenses->first()->refresh()->paid_amount)->toBe(0.0)
        ->and(app(UnitDebtService::class)->currentOutstandingForUnit($unit->id))->toBe(0.0);
});

test('plan installment creates ledger income and does not impute a normal expense', function () {
    [$neighborhood, $unit, $owner, $expenses] = paymentPlanScenario();
    $plan = createPaymentPlanForTest($neighborhood, $unit, $owner, $expenses);
    $account = BankAccount::create(['neighborhood_id' => $neighborhood->id, 'bank_name' => 'Banco Test', 'account_type' => 'CC', 'currency' => 'ARS']);
    $installment = $plan->installments()->first();

    $response = $this->actingAs(User::factory()->create())->withSession(['neighborhood_id' => $neighborhood->id])
        ->post(route('payment-plans.pay', $plan, false), ['payment_plan_installment_id' => $installment->id, 'amount' => 120000, 'payment_date' => '2026-08-10', 'payment_method' => 'bank_transfer', 'bank_account' => $account->id, 'reference' => 'TRX-1']);
    $response->assertRedirect()->assertSessionHasNoErrors();

    expect(PaymentExpense::where('payment_type', 'payment_plan')->count())->toBe(1)
        ->and((float) $plan->refresh()->paid_amount)->toBe(120000.0)
        ->and((float) $plan->outstanding_amount)->toBe(240000.0)
        ->and($installment->refresh()->status)->toBe('paid')
        ->and((float) $expenses->first()->refresh()->paid_amount)->toBe(0.0)
        ->and(app(UnitDebtService::class)->currentOutstandingForUnit($unit->id))->toBe(0.0);
});

test('accumulated payment ignores debt financed by an active plan', function () {
    [$neighborhood, $unit, $owner, $expenses] = paymentPlanScenario();
    $plan = createPaymentPlanForTest($neighborhood, $unit, $owner, $expenses);
    $current = UnitExpense::create(['unit_id' => $unit->id, 'period' => now()->format('Y-m'), 'monthly_amount' => 80000, 'extraordinary_amount' => 0, 'fines_amount' => 0, 'paid_amount' => 0]);

    $response = $this->actingAs(User::factory()->create())->withSession(['neighborhood_id' => $neighborhood->id])
        ->post(route('expenses.accumulated.store', absolute: false), ['unit_id' => $unit->id, 'amount' => 80000, 'payment_date' => now()->toDateString(), 'payment_method' => 'cash']);
    $response->assertRedirect()->assertSessionHasNoErrors();

    expect((float) $current->refresh()->paid_amount)->toBe(80000.0)
        ->and((float) $plan->refresh()->paid_amount)->toBe(0.0)
        ->and(PaymentExpense::where('payment_type', 'payment_plan')->count())->toBe(0);
});

test('normal period payment only settles new current debt while the plan remains active', function () {
    [$neighborhood, $unit, $owner, $expenses] = paymentPlanScenario();
    $plan = createPaymentPlanForTest($neighborhood, $unit, $owner, $expenses);
    $current = UnitExpense::create(['unit_id' => $unit->id, 'period' => now()->format('Y-m'), 'monthly_amount' => 80000, 'extraordinary_amount' => 0, 'fines_amount' => 0, 'paid_amount' => 0]);

    $this->actingAs(User::factory()->create())->withSession(['neighborhood_id' => $neighborhood->id])
        ->post(route('expenses.store', absolute: false), ['unit_id' => $current->id, 'amount' => 80000, 'payment_date' => now()->toDateString(), 'payment_method' => 'cash'])
        ->assertSessionHasNoErrors();

    expect((float) $current->refresh()->paid_amount)->toBe(80000.0)
        ->and((float) $plan->refresh()->outstanding_amount)->toBe(360000.0)
        ->and(PaymentExpense::where('payment_type', 'period')->count())->toBe(1);
});

test('completing all installments closes the plan and permanently settles its financed debt', function () {
    [$neighborhood, $unit, $owner, $expenses] = paymentPlanScenario();
    $plan = createPaymentPlanForTest($neighborhood, $unit, $owner, $expenses);
    $this->actingAs(User::factory()->create())->withSession(['neighborhood_id' => $neighborhood->id]);
    foreach ($plan->installments as $installment) {
        $this->post(route('payment-plans.pay', $plan, false), ['payment_plan_installment_id' => $installment->id, 'amount' => 120000, 'payment_date' => '2026-08-10', 'payment_method' => 'cash'])->assertSessionHasNoErrors();
    }

    expect($plan->refresh()->status)->toBe('completed')->and((float) $plan->outstanding_amount)->toBe(0.0)
        ->and($plan->installments()->where('status', 'paid')->count())->toBe(3)
        ->and(app(UnitDebtService::class)->currentOutstandingForUnit($unit->id))->toBe(0.0);
});

test('cancelling a partially paid plan returns only its unpaid balance to current debt', function () {
    [$neighborhood, $unit, $owner, $expenses] = paymentPlanScenario();
    $plan = createPaymentPlanForTest($neighborhood, $unit, $owner, $expenses);
    $installment = $plan->installments()->first();
    $this->actingAs(User::factory()->create())->withSession(['neighborhood_id' => $neighborhood->id])
        ->post(route('payment-plans.pay', $plan, false), ['payment_plan_installment_id' => $installment->id, 'amount' => 120000, 'payment_date' => '2026-08-10', 'payment_method' => 'cash'])
        ->assertSessionHasNoErrors();
    $this->post(route('payment-plans.cancel', $plan, false), ['reason' => 'Incumplimiento'])->assertSessionHasNoErrors();

    expect($plan->refresh()->status)->toBe('cancelled')
        ->and((float) $plan->paid_amount)->toBe(120000.0)
        ->and(app(UnitDebtService::class)->currentOutstandingForUnit($unit->id))->toBe(240000.0);
});

test('reconciliation keeps an active plan separate from current balance and shows cancelled remainder as prior balance', function () {
    [$neighborhood, $unit, $owner, $expenses] = paymentPlanScenario();
    $plan = createPaymentPlanForTest($neighborhood, $unit, $owner, $expenses);
    $this->actingAs(User::factory()->create())->withSession(['neighborhood_id' => $neighborhood->id]);

    $this->get(route('payments.reconciliation.monthly', ['period' => '2026-07'], false))
        ->assertViewHas('expenses', fn ($rows) => (float) $rows[0]['historical_outstanding'] === 0.0 && (float) $rows[0]['outstanding'] === 0.0)
        ->assertSeeText('Plan de pago vigente')
        ->assertSee('plan-info-row no-print', false);

    $this->post(route('payment-plans.pay', $plan, false), ['payment_plan_installment_id' => $plan->installments()->first()->id, 'amount' => 120000, 'payment_date' => '2026-08-10', 'payment_method' => 'cash'])->assertSessionHasNoErrors();
    $this->post(route('payment-plans.cancel', $plan, false))->assertSessionHasNoErrors();
    $this->get(route('payments.reconciliation.monthly', ['period' => '2026-08'], false))
        ->assertViewHas('expenses', fn ($rows) => (float) $rows[0]['historical_outstanding'] === 240000.0 && (float) $rows[0]['outstanding'] === 240000.0);
});

test('cannot view or mutate a plan from another neighborhood', function () {
    [$neighborhood, $unit, $owner, $expenses] = paymentPlanScenario();
    $plan = createPaymentPlanForTest($neighborhood, $unit, $owner, $expenses);
    $other = Neighborhood::create(['name' => 'Otro', 'expense_calculation_type' => 'fixed', 'fixed_amount' => 0]);
    $this->actingAs(User::factory()->create())->withSession(['neighborhood_id' => $other->id])
        ->get(route('payment-plans.show', $plan, false))->assertForbidden();
});

test('plan income is included in the monthly and bank-account cash ledger', function () {
    [$neighborhood, $unit, $owner, $expenses] = paymentPlanScenario();
    $plan = createPaymentPlanForTest($neighborhood, $unit, $owner, $expenses);
    $account = BankAccount::create(['neighborhood_id' => $neighborhood->id, 'bank_name' => 'Banco Test', 'account_type' => 'CC', 'currency' => 'ARS', 'opening_balance' => 0, 'opening_balance_date' => now()->startOfMonth()]);
    $this->actingAs(User::factory()->create())->withSession(['neighborhood_id' => $neighborhood->id])
        ->post(route('payment-plans.pay', $plan, false), ['payment_plan_installment_id' => $plan->installments()->first()->id, 'amount' => 120000, 'payment_date' => now()->toDateString(), 'payment_method' => 'bank_transfer', 'bank_account' => $account->id])
        ->assertSessionHasNoErrors();

    $this->get(route('payments.index', absolute: false))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('summary.monthlyIncome', 120000)
        ->where('accountsSummary.0.expense_income', 120000)
        ->where('accountsSummary.0.current_balance', 120000)
    );
});
