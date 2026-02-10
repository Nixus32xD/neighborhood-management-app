<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    protected $fillable = [
        'neighborhood_id',
        'date',
        'amount',
        'description',
        'recipient',
        'payment_method',
        'bank_account_id',
        'voucher_path',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    /* Helpers */
    public function getIsHighValueAttribute()
    {
        return $this->amount > 10000;
    }

    public function getVoucherUrlAttribute()
    {
        return $this->voucher_path
            ? Storage::url($this->voucher_path)
            : null;
    }

    public function neighborhood()
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
