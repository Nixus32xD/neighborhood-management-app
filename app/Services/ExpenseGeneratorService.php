<?php

namespace App\Services;

use App\Models\ExpenseGeneration;
use App\Models\Neighborhood;
use App\Models\Unit;
use App\Models\UnitExpense;
use Illuminate\Support\Facades\DB;

class ExpenseGeneratorService
{
    /**
     * Genera las expensas masivamente para un barrio y periodo.
     *
     * @param  Neighborhood  $neighborhood  El modelo del barrio (CC1 o CC2)
     * @param  string  $period  El periodo en formato 'Y-m' (ej: 2026-02)
     * @param  array  $data  Datos del formulario (monto fijo, o base para calculo)
     */
    public function generate(Neighborhood $neighborhood, string $period, array $data): void
    {
        DB::transaction(function () use ($neighborhood, $period, $data) {
            $units = $neighborhood->units;
            $totalSurface = (float) $units->sum('surface_area');
            $generatedAny = false;

            foreach ($units as $unit) {
                $alreadyExists = UnitExpense::where('unit_id', $unit->id)
                    ->where('period', $period)
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                $amount = 0.0;

                // CC1: monto fijo por unidad
                if ($neighborhood->expense_calculation_type === 'fixed') {
                    $amount = (float) ($data['amount'] ?? $neighborhood->fixed_amount ?? 0);
                }

                // CC2: total distribuible por formula y reparto por porcentaje
                elseif ($neighborhood->expense_calculation_type === 'proportional') {
                    $baseAmount = (float) ($data['base_amount'] ?? 0);
                    $baseMeters = (float) ($data['base_meters'] ?? 0);

                    if ($baseAmount <= 0) {
                        throw new \InvalidArgumentException(
                            'Para CC2 proporcional, base_amount debe ser mayor a 0.'
                        );
                    }
                    if ($baseMeters <= 0) {
                        throw new \InvalidArgumentException(
                            'Para CC2 proporcional, base_meters debe ser mayor a 0.'
                        );
                    }
                    if ($totalSurface <= 0) {
                        throw new \InvalidArgumentException(
                            'La superficie total del barrio debe ser mayor a 0 para CC2.'
                        );
                    }

                    $amount = $this->calculateProportionalAmount(
                        $unit,
                        $baseAmount,
                        $baseMeters,
                        $totalSurface
                    );
                }

                UnitExpense::create([
                    'unit_id' => $unit->id,
                    'period' => $period,
                    'monthly_amount' => $amount,
                    'extraordinary_amount' => $data['extraordinary'] ?? 0,
                    'fines_amount' => 0,
                ]);

                $generatedAny = true;
            }

            // CC2 conserva los parámetros exactos utilizados en cada período.
            // Solo se registra cuando se crean expensas para no reescribir historia
            // al repetir por error una generación ya existente.
            if ($generatedAny && $neighborhood->expense_calculation_type === 'proportional') {
                ExpenseGeneration::firstOrCreate(
                    [
                        'neighborhood_id' => $neighborhood->id,
                        'period' => $period,
                    ],
                    [
                        'base_amount' => $data['base_amount'],
                        'base_meters' => $data['base_meters'],
                    ]
                );
            }
        });
    }

    public function calculateProportionalAmount(
        Unit $unit,
        float $baseAmount,
        float $baseMeters,
        float $totalSurface
    ): float {
        if ($baseAmount <= 0) {
            throw new \InvalidArgumentException(
                'Para CC2 proporcional, base_amount debe ser mayor a 0.'
            );
        }

        if ($baseMeters <= 0) {
            throw new \InvalidArgumentException(
                'Para CC2 proporcional, base_meters debe ser mayor a 0.'
            );
        }

        if ($totalSurface <= 0) {
            throw new \InvalidArgumentException(
                'La superficie total del barrio debe ser mayor a 0 para CC2.'
            );
        }

        // Ejemplo: (46000 / 500) * superficie_total = fondo a distribuir
        $ratePerMeter = $baseAmount / $baseMeters;
        $distributableTotal = $ratePerMeter * $totalSurface;

        // Formatos soportados en expense_coefficient:
        // - 4.68 (porcentaje)
        // - 0.0468 (coeficiente decimal)
        $rawCoefficient = $unit->expense_coefficient;

        if ($rawCoefficient !== null) {
            $coefficient = (float) $rawCoefficient;
            $coefficient = $coefficient > 1 ? ($coefficient / 100) : $coefficient;

            return round($distributableTotal * $coefficient, 2);
        }

        $unitSurface = (float) ($unit->surface_area ?? 0);
        $ratio = $totalSurface > 0 ? ($unitSurface / $totalSurface) : 0;

        return round($distributableTotal * $ratio, 2);
    }
}
