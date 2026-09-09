<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'neighborhood_id',
        'period',
        'base_amount',
        'base_meters',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'base_meters' => 'decimal:2',
    ];

    public function neighborhood()
    {
        return $this->belongsTo(Neighborhood::class);
    }
}
