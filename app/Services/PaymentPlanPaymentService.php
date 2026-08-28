<?php

namespace App\Services;

use App\Models\PaymentExpense;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentPlanPaymentService
{
    public function register(PaymentPlan $plan, array $data, ?int $userId): PaymentExpense
    {
        return DB::transaction(function () use ($plan, $data, $userId) {
            $plan = PaymentPlan::lockForUpdate()->findOrFail($plan->id);
            if ($plan->status !== 'active') {
                throw ValidationException::withMessages(['payment_plan_id' => 'El plan no está activo.']);
            }
            $installment = PaymentPlanInstallment::where('payment_plan_id', $plan->id)
                ->whereKey($data['payment_plan_installment_id'])->lockForUpdate()->firstOrFail();
            $amount = round((float) $data['amount'], 2);
            $installmentOutstanding = round((float) $installment->amount - (float) $installment->paid_amount, 2);
            if ($amount <= 0 || $amount > $installmentOutstanding || $amount > (float) $plan->outstanding_amount) {
                throw ValidationException::withMessages(['amount' => 'El monto excede el saldo de la cuota o del plan.']);
            }

            $payment = PaymentExpense::create([
                'unit_expense_id' => null, 'unit_id' => $plan->unit_id, 'bank_account_id' => $data['bank_account'] ?? null,
                'payment_plan_id' => $plan->id, 'payment_plan_installment_id' => $installment->id,
                'amount' => $amount, 'payment_date' => $data['payment_date'], 'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null, 'payment_type' => 'payment_plan', 'created_by' => $userId,
            ]);

            $newInstallmentPaid = round((float) $installment->paid_amount + $amount, 2);
            $installment->update([
                'paid_amount' => $newInstallmentPaid,
                'status' => $newInstallmentPaid >= (float) $installment->amount ? 'paid' : 'partial',
                'paid_at' => $newInstallmentPaid >= (float) $installment->amount ? now() : null,
            ]);

            $remaining = $amount;
            foreach ($plan->items()->with('unitExpense:id,period')->get()->sortBy('unitExpense.period') as $item) {
                if ($remaining <= 0) break;
                $itemBalance = round((float) $item->financed_amount - (float) $item->settled_amount, 2);
                $applied = min($remaining, $itemBalance);
                if ($applied > 0) {
                    $item->update(['settled_amount' => round((float) $item->settled_amount + $applied, 2)]);
                    $remaining = round($remaining - $applied, 2);
                }
            }

            $paid = round((float) $plan->paid_amount + $amount, 2);
            $outstanding = round((float) $plan->original_amount - $paid, 2);
            $plan->update([
                'paid_amount' => $paid, 'outstanding_amount' => max(0, $outstanding),
                'status' => $outstanding <= 0 ? 'completed' : 'active',
                'completed_at' => $outstanding <= 0 ? now() : null,
            ]);

            return $payment;
        });
    }
}
