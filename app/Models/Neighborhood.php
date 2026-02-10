<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Neighborhood extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'expense_calculation_type',
        'fixed_amount'

    ];

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function extraordinaryExpenses()
    {
        return $this->hasMany(ExtraordinaryExpense::class);
    }

    public function fines()
    {
        return $this->hasMany(Fine::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    public function bankMovements()
    {
        return $this->hasMany(BankMovement::class);
    }
}
