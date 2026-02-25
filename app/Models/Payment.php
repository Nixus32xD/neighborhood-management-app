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
        if (! $this->voucher_path) {
            return null;
        }

        $candidates = [
            (string) config('filesystems.default', 'local'),
            'public',
        ];

        foreach (array_unique($candidates) as $diskName) {
            $disk = Storage::disk($diskName);

            if (! $disk->exists($this->voucher_path)) {
                continue;
            }

            try {
                if (method_exists($disk, 'temporaryUrl')) {
                    return $disk->temporaryUrl($this->voucher_path, now()->addMinutes(10));
                }
            } catch (\Throwable) {
                // Ignore and use regular URL below.
            }

            return $disk->url($this->voucher_path);
        }

        return null;
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
