<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'neighborhood_id',
        'bank_name',
        'account_type',
        'currency',
        'alias',
        'opening_balance',
        'opening_balance_date',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
    ];

    public function neighborhood()
    {
        return $this->belongsTo(Neighborhood::class);
    }

    /**
     * Opciones para selects
     */
    public static function options()
    {
        return self::where('neighborhood_id', session('neighborhood_id'))
            ->orderBy('bank_name')
            ->get()
            ->map(fn($account) => [
                'value' => $account->id,
                'label' => "{$account->bank_name} - {$account->account_type} {$account->currency}",
            ]);
    }
}
