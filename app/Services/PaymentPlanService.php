<?php

namespace App\Services;

use App\Models\PaymentPlan;
use App\Models\Unit;
use App\Models\UnitExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentPlanService
{
    public function __construct(private UnitDebtService $debtService) {}

    public function create(int $neighborhoodId, array $data, ?int $userId): PaymentPlan
    {
        return DB::transaction(function () use ($neighborhoodId, $data, $userId) {
            $unit = Unit::where('neighborhood_id', $neighborhoodId)->lockForUpdate()->findOrFail($data['unit_id']);
            if (PaymentPlan::where('unit_id', $unit->id)->where('status', 'active')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['unit_id' => 'La unidad ya tiene un plan de pago activo.']);
            }

            $selected = collect($data['items'])->keyBy('unit_expense_id');
            $lockedExpenses = UnitExpense::with('payments')
                ->where('unit_id', $unit->id)
                ->whereIn('id', $selected->keys())
                ->orderBy('period')
                ->lockForUpdate()
                ->get();
            if ($lockedExpenses->count() !== $selected->count()) {
                throw ValidationException::withMessages(['items' => 'Una de las expensas seleccionadas no pertenece a la unidad.']);
            }

            $eligible = $this->debtService->breakdownForExpenses($lockedExpenses)->keyBy(fn ($row) => $row['expense']->id);
            $financedDebtAmount = 0.0;
            foreach ($selected as $expenseId => $item) {
                $amount = round((float) $item['amount'], 2);
                $available = (float) ($eligible->get($expenseId)['current_outstanding'] ?? 0);
                if ($amount <= 0 || $amount > $available) {
                    throw ValidationException::withMessages(['items' => 'El importe financiado supera la deuda corriente elegible.']);
                }
                $financedDebtAmount += $amount;
            }
            $financedDebtAmount = round($financedDebtAmount, 2);
            $totalAmount = round((float) ($data['total_amount'] ?? $financedDebtAmount), 2);
            if ($totalAmount < $financedDebtAmount) {
                throw ValidationException::withMessages(['total_amount' => 'El total del acuerdo no puede ser menor a la deuda financiada.']);
            }
            $financingChargeAmount = round($totalAmount - $financedDebtAmount, 2);

            $plan = PaymentPlan::create([
                'neighborhood_id' => $neighborhoodId,
                'unit_id' => $unit->id,
                'owner_id' => $data['owner_id'] ?? null,
                'original_amount' => $totalAmount,
                'financed_debt_amount' => $financedDebtAmount,
                'financing_charge_amount' => $financingChargeAmount,
                'installments_count' => $data['installments_count'],
                'paid_amount' => 0,
                'outstanding_amount' => $totalAmount,
                'status' => 'active',
                'start_date' => $data['start_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($selected as $expenseId => $item) {
                $plan->items()->create(['unit_expense_id' => $expenseId, 'financed_amount' => round((float) $item['amount'], 2)]);
            }

            $baseAmount = floor(($totalAmount / $data['installments_count']) * 100) / 100;
            $remaining = $totalAmount;
            $startDate = Carbon::parse($data['start_date']);
            for ($number = 1; $number <= $data['installments_count']; $number++) {
                $amount = $number === $data['installments_count'] ? $remaining : $baseAmount;
                $plan->installments()->create([
                    'installment_number' => $number,
                    'amount' => $amount,
                    'due_date' => $startDate->copy()->addMonthsNoOverflow($number - 1)->toDateString(),
                    'status' => 'pending',
                ]);
                $remaining = round($remaining - $amount, 2);
            }

            return $plan->load(['unit.owners', 'owner', 'items.unitExpense', 'installments']);
        });
    }

    public function cancel(PaymentPlan $plan, ?string $reason, ?int $userId): PaymentPlan
    {
        return DB::transaction(function () use ($plan, $reason, $userId) {
            $plan = PaymentPlan::lockForUpdate()->findOrFail($plan->id);
            if ($plan->status !== 'active') {
                throw ValidationException::withMessages(['plan' => 'Solo se pueden cancelar planes activos.']);
            }
            $cancelledDebtAmount = round((float) $plan->items()
                ->selectRaw('COALESCE(SUM(financed_amount - settled_amount), 0) as total')
                ->value('total'), 2);

            $plan->update([
                'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $userId,
                'cancellation_reason' => $reason, 'cancelled_debt_amount' => $cancelledDebtAmount,
            ]);
            return $plan;
        });
    }
}
