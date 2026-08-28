<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'neighborhood_id',
        'uf_number',

        'surface_area',
        'front',
        'depth',

        'expense_coefficient',
        'base_expense',
        'active',
    ];

    protected $casts = [
        'surface_area' => 'decimal:2',
        'front' => 'decimal:2',
        'depth' => 'decimal:2',
        'expense_coefficient' => 'decimal:5',
        'base_expense' => 'decimal:2',
        'active' => 'boolean',
    ];

    /* ================= RELACIONES ================= */

    public function neighborhood()
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function owners()
    {
        return $this->hasMany(Owner::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses()
    {
        return $this->hasMany(UnitExpense::class);
    }

    public function paymentPlans()
    {
        return $this->hasMany(PaymentPlan::class);
    }

    /* ================= HELPERS ================= */

    // Expensa proporcional
    public function calculateExpense(float $totalExpense): float
    {
        if (!$this->expense_coefficient) {
            return 0;
        }

        return round($totalExpense * $this->expense_coefficient, 2);
    }
}
