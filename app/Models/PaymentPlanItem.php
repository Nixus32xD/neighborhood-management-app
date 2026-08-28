<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentPlanItem extends Model
{
    protected $fillable = ['payment_plan_id', 'unit_expense_id', 'financed_amount', 'settled_amount'];
    protected $casts = ['financed_amount' => 'decimal:2', 'settled_amount' => 'decimal:2'];

    public function paymentPlan() { return $this->belongsTo(PaymentPlan::class); }
    public function unitExpense() { return $this->belongsTo(UnitExpense::class); }
}
