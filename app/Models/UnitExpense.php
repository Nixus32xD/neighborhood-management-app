<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnitExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'period',
        'monthly_amount',
        'extraordinary_amount',
        'fines_amount',
        'paid_amount',
        'monthly_interest_applied_at',
        'extraordinary_interest_applied_at',
    ];

    protected $casts = [
        'monthly_amount' => 'decimal:2',
        'extraordinary_amount' => 'decimal:2',
        'fines_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'monthly_interest_applied_at' => 'date',
        'extraordinary_interest_applied_at' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors (helpers)
    |--------------------------------------------------------------------------
    */

    public function getTotalAmountAttribute(): float
    {
        return
            $this->monthly_amount +
            $this->extraordinary_amount +
            $this->fines_amount;
    }

    public function getOutstandingAmountAttribute(): float
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    public function getStatusAttribute(): string
    {
        if ($this->outstanding_amount <= 0) {
            return 'paid';
        }

        return $this->period === now()->format('Y-m')
            ? 'pending'
            : 'overdue';
    }

    public function payments()
    {
        return $this->hasMany(PaymentExpense::class);
    }



}
