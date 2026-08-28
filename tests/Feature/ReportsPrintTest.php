<?php

use App\Models\Neighborhood;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\UnitExpense;
use App\Models\User;

function createOwnerScenario(): array
{
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

    $owner = Owner::create([
        'unit_id' => $unit->id,
        'full_name' => 'Propietario Test',
        'email' => 'owner@example.com',
        'people_count' => 1,
    ]);

    $januaryExpense = UnitExpense::create([
        'unit_id' => $unit->id,
        'period' => '2026-01',
        'monthly_amount' => 1000,
        'extraordinary_amount' => 0,
        'fines_amount' => 0,
        'paid_amount' => 0,
    ]);

    $februaryExpense = UnitExpense::create([
        'unit_id' => $unit->id,
        'period' => '2026-02',
        'monthly_amount' => 800,
        'extraordinary_amount' => 0,
        'fines_amount' => 0,
        'paid_amount' => 800,
    ]);

    $februaryExpense->payments()->create([
        'unit_id' => $unit->id,
        'amount' => 800,
        'payment_date' => '2026-02-10',
        'payment_method' => 'cash',
        'reference' => 'Pago febrero',
    ]);

    return [$neighborhood, $owner, $januaryExpense, $februaryExpense];
}

test('owner statement print keeps debt on the original period', function () {
    [$neighborhood, $owner] = createOwnerScenario();

    $response = $this
        ->actingAs(User::factory()->create())
        ->withSession(['neighborhood_id' => $neighborhood->id])
        ->get(route('owner-statements.print', [
            'owner_id' => $owner->id,
            'filter_type' => 'period',
            'period_from' => '2026-01',
            'period_to' => '2026-02',
        ], false));

    $response->assertOk();

    $response->assertViewHas('statement', function (array $statement) {
        expect($statement['summary']['outstanding_total'])->toBe(1000.0);
        expect($statement['charges'])->toHaveCount(2);

        expect($statement['charges'][0]['period'])->toBe('2026-01');
        expect($statement['charges'][0]['outstanding'])->toBe(1000.0);
        expect($statement['charges'][1]['period'])->toBe('2026-02');
        expect((float) $statement['charges'][1]['outstanding'])->toBe(0.0);

        return true;
    });
});

test('monthly reconciliation print keeps fines and historical balance separated', function () {
    [$neighborhood] = createOwnerScenario();

    $response = $this
        ->actingAs(User::factory()->create())
        ->withSession(['neighborhood_id' => $neighborhood->id])
        ->get(route('payments.reconciliation.monthly', [
            'period' => '2026-02',
        ], false));

    $response->assertOk();

    $response->assertViewHas('expenses', function ($expenses) {
        expect($expenses)->toHaveCount(1);
        expect($expenses[0]['paid'])->toBe(800.0);
        expect($expenses[0]['fines'])->toBe(0.0);
        expect($expenses[0]['historical_outstanding'])->toBe(1000.0);
        expect($expenses[0]['total'])->toBe(1800.0);
        expect((float) $expenses[0]['outstanding'])->toBe(1000.0);
        expect($expenses[0]['status'])->toBe('Pendiente');

        return true;
    });

    $response->assertViewHas('debtByOwner', function ($debtByOwner) {
        expect($debtByOwner)->toHaveCount(1);
        expect($debtByOwner[0]['historical_outstanding'])->toBe(1000.0);
        expect((float) $debtByOwner[0]['current_outstanding'])->toBe(0.0);
        expect($debtByOwner[0]['total_outstanding'])->toBe(1000.0);

        return true;
    });

    $response->assertSeeInOrder(['Propietario', 'Total a Pagar', 'Mensual (a pagar)']);
    $response->assertSeeText('Multa / Intereses');
    $response->assertSeeText('Saldo anterior');
    $response->assertSeeText('Imprimir');
    $response->assertSee('no-print', false);
    $response->assertSee('@media print', false);
});

test('static january reconciliation renders pdf data without neighborhood records', function () {
    $response = $this
        ->actingAs(User::factory()->create())
        ->get(route('payments.reconciliation.january-2026-static', absolute: false));

    $response->assertOk();
    $response->assertViewIs('reports.monthly-reconciliation');

    $response->assertViewHas('expenses', function ($expenses) {
        expect($expenses)->toHaveCount(21);
        expect($expenses[0]['owner'])->toBe('CARLOS SPERDUTI');
        expect($expenses[3]['total'])->toBe(2348765.65);

        return true;
    });

    $response->assertViewHas('movements', function ($movements) {
        expect($movements)->toHaveCount(18);
        expect(round((float) $movements->sum('accounting_total'), 2))->toBe(1123051.16);

        return true;
    });

    $response->assertSeeText('Rendicion Enero 2026.pdf');
    $response->assertSeeText('Saldo en c/c bancaria al 30/01/2026');
});
