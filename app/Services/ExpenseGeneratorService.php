<?php

namespace App\Services;

use App\Models\Neighborhood;
use App\Models\UnitExpense;
use Illuminate\Support\Facades\DB;
use Exception;

class ExpenseGeneratorService
{
    /**
     * Genera las expensas masivamente para un barrio y período.
     * * @param Neighborhood $neighborhood El modelo del barrio (CC1 o CC2)
     * @param string $period El período en formato 'Y-m' (ej: 2026-02)
     * @param array $data Datos del formulario (monto fijo, o base para cálculo)
     */
    public function generate(Neighborhood $neighborhood, string $period, array $data)
    {
        // 1. Validamos que no se hayan generado ya para evitar duplicados
        // (Opcional: podés usar updateOrCreate si querés permitir regenerar)
        $exists = UnitExpense::where('period', $period)
            ->whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhood->id))
            ->exists();

        if ($exists) {
            throw new Exception("Ya existen expensas generadas para el período $period en este barrio.");
        }

        // 2. Abrimos una transacción: o se generan todas o ninguna
        DB::transaction(function () use ($neighborhood, $period, $data) {

            // Traemos todas las unidades del barrio
            $units = $neighborhood->units;

            foreach ($units as $unit) {
                $amount = 0;

                // --- ESTRATEGIA 1: MONTO FIJO (CC1) ---
                if ($neighborhood->expense_calculation_type === 'fixed') {
                    // Si viene del input usamos ese, sino el default del barrio
                    $amount = $data['amount'] ?? $neighborhood->fixed_amount;
                }

                // --- ESTRATEGIA 2: PROPORCIONAL / COMPLEJA (CC2) ---
                elseif ($neighborhood->expense_calculation_type === 'proportional') {
                    // Fórmula: ($46000 / 500m) * metros_lote

                    // Validamos que no dividamos por cero
                    $baseMeters = isset($data['base_meters']) && $data['base_meters'] > 0
                        ? $data['base_meters']
                        : 500; // Valor por defecto según tu ejemplo

                    $baseAmount = $data['base_amount'] ?? 46000; // Valor base monetario

                    // Calculamos el valor por m2
                    $ratePerMeter = $baseAmount / $baseMeters;

                    // Si el lote tiene metros definidos, calculamos. Si no, $0 (o podés lanzar error)
                    $unitMeters = $unit->area_m2 ?? 0;

                    // Si usan COEFICIENTE en vez de metros directos, descomentá esto:
                    // $amount = $baseAmount * ($unit->coefficient / 100);

                    // Cálculo actual según tu explicación:
                    $amount = $ratePerMeter * $unitMeters;
                }

                // 3. Guardamos la deuda
                UnitExpense::create([
                    'unit_id' => $unit->id,
                    'period' => $period,
                    'monthly_amount' => $amount,
                    'extraordinary_amount' => $data['extraordinary'] ?? 0, // Por si querés sumar extra a todos
                    'fines_amount' => 0,
                    'status' => 'pending' // Nace como "Pendiente"
                ]);
            }
        });
    }
}
