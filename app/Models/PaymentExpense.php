<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentExpense extends Model
{
    protected $fillable = [
        'unit_expense_id',
        'unit_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference',
    ];

    public function unitExpense()
    {
        return $this->belongsTo(UnitExpense::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
