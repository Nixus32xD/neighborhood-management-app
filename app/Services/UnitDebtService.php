<?php

namespace App\Services;

use App\Models\PaymentPlanItem;
use App\Models\UnitExpense;
use Illuminate\Support\Collection;

class UnitDebtService
{
    /**
     * Calcula la deuda corriente sin alterar los cargos originales. Una porción
     * financiada por un plan activo queda excluida; en planes cerrados se excluye
     * únicamente lo efectivamente cancelado por cuotas.
     */
    public function breakdownForExpenses(Collection $expenses): Collection
    {
        $expenseIds = $expenses->pluck('id')->all();
        if ($expenseIds === []) {
            return collect();
        }

        $itemsByExpense = PaymentPlanItem::query()
            ->with('paymentPlan:id,status')
            ->whereIn('unit_expense_id', $expenseIds)
            ->get()
            ->groupBy('unit_expense_id');

        return $expenses->map(function (UnitExpense $expense) use ($itemsByExpense) {
            $regularPaymentTotal = (float) $expense->payments
                ->where('payment_type', '!=', 'payment_plan')
                ->sum('amount');
            // paid_amount es un acumulado legado. Conservamos el mayor de ambos
            // valores para no reabrir deuda en instalaciones ya existentes.
            $normalPaid = max((float) $expense->paid_amount, $regularPaymentTotal);
            $grossOutstanding = $this->money(max(0, (float) $expense->total_amount - $normalPaid));

            $activeFinanced = 0.0;
            $settledByClosedPlans = 0.0;
            foreach ($itemsByExpense->get($expense->id, collect()) as $item) {
                if ($item->paymentPlan->status === 'active') {
                    $activeFinanced += (float) $item->financed_amount;
                } elseif (in_array($item->paymentPlan->status, ['completed', 'cancelled'], true)) {
                    $settledByClosedPlans += (float) $item->settled_amount;
                }
            }

            $excluded = $this->money(min($grossOutstanding, $activeFinanced + $settledByClosedPlans));
            $currentOutstanding = $this->money(max(0, $grossOutstanding - $excluded));

            return [
                'expense' => $expense,
                'gross_outstanding' => $grossOutstanding,
                'normal_paid' => $this->money($normalPaid),
                'active_financed' => $this->money($activeFinanced),
                'settled_by_plans' => $this->money($settledByClosedPlans),
                'current_outstanding' => $currentOutstanding,
            ];
        });
    }

    public function breakdownForUnit(int $unitId, ?string $throughPeriod = null): Collection
    {
        $expenses = UnitExpense::with('payments')
            ->where('unit_id', $unitId)
            ->when($throughPeriod, fn ($query) => $query->where('period', '<=', $throughPeriod))
            ->orderBy('period')
            ->get();

        return $this->breakdownForExpenses($expenses);
    }

    public function currentOutstandingForUnit(int $unitId, ?string $throughPeriod = null): float
    {
        return $this->money($this->breakdownForUnit($unitId, $throughPeriod)->sum('current_outstanding'));
    }

    public function eligibleForPlan(int $unitId): Collection
    {
        return $this->breakdownForUnit($unitId)
            ->filter(fn (array $row) => $row['current_outstanding'] > 0)
            ->values();
    }

    private function money(float $amount): float
    {
        return round($amount, 2);
    }
}
