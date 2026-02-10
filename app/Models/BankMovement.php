<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'neighborhood_id',
        'bank_account_id',
        'date',
        'amount',
        'type',
        'description',
        'recipient_name',
        'payment_method',
        'receipt_path',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function neighborhood()
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
