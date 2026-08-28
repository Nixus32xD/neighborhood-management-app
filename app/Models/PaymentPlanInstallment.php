<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentPlanInstallment extends Model
{
    protected $fillable = ['payment_plan_id', 'installment_number', 'amount', 'paid_amount', 'due_date', 'paid_at', 'status'];
    protected $casts = ['amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'due_date' => 'date', 'paid_at' => 'datetime'];

    public function paymentPlan() { return $this->belongsTo(PaymentPlan::class); }
    public function payments() { return $this->hasMany(PaymentExpense::class); }
}
