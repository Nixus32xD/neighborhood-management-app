<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'neighborhood_id', 'unit_id', 'owner_id', 'original_amount', 'installments_count',
        'paid_amount', 'outstanding_amount', 'status', 'start_date', 'completed_at',
        'cancelled_at', 'cancelled_by', 'cancellation_reason', 'notes', 'created_by',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'outstanding_amount' => 'decimal:2',
        'start_date' => 'date', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function neighborhood() { return $this->belongsTo(Neighborhood::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function owner() { return $this->belongsTo(Owner::class); }
    public function items() { return $this->hasMany(PaymentPlanItem::class); }
    public function installments() { return $this->hasMany(PaymentPlanInstallment::class)->orderBy('installment_number'); }
    public function payments() { return $this->hasMany(PaymentExpense::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by'); }
}
