<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentExpense extends Model
{
    protected $fillable = [
        'unit_expense_id',
        'unit_id',
        'bank_account_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function unitExpense()
    {
        return $this->belongsTo(UnitExpense::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
