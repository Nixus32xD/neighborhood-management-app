<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Owner extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'full_name',
        'email',
        'people_count',
        'preferred_method',
        'bank_name',
        'account_holder',
        'cbu',
        'alias',
        'custom_method',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function residents()
    {
        return $this->hasMany(Resident::class);
    }

    public function unitExpenses()
    {
        return $this->hasManyThrough(
            UnitExpense::class,
            Unit::class
        );
    }

    public function paymentPlans()
    {
        return $this->hasMany(PaymentPlan::class);
    }
}
