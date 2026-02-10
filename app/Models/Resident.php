<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resident extends Model
{
    protected $fillable = ['full_name', 'relation'];

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }
}
