<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\PaymentExpense;
use App\Models\Unit;
use App\Models\UnitExpense;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;


class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $neighborhoodId = session('neighborhood_id');
        $currentPeriod = now()->format('Y-m');
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $totalOwners = Owner::whereHas('unit', function ($query) use ($neighborhoodId) {
            $query->where('neighborhood_id', $neighborhoodId);
        })->count();

        $totalUnits = Unit::where('neighborhood_id', $neighborhoodId)->count();

        $periodExpenses = UnitExpense::with(['payments', 'unit'])
            ->where('period', $currentPeriod)
            ->whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->get();

        $totalCharged = $periodExpenses->sum(fn($expense) => (float) $expense->monthly_amount
            + (float) $expense->extraordinary_amount
            + (float) $expense->fines_amount);

        $totalPaid = $periodExpenses->sum(fn($expense) => (float) $expense->payments->sum('amount'));

        $totalOutstanding = max(0, $totalCharged - $totalPaid);

        $monthlyIncome = PaymentExpense::whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->whereDate('payment_date', '>=', $monthStart)
            ->whereDate('payment_date', '<=', $monthEnd)
            ->sum('amount');

        $monthlyOutflow = Payment::where('neighborhood_id', $neighborhoodId)
            ->whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $monthEnd)
            ->sum('amount');

        $monthlyBalance = (float) $monthlyIncome - (float) $monthlyOutflow;

        $recentPayments = PaymentExpense::with(['unit.owners'])
            ->whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->latest('payment_date')
            ->limit(5)
            ->get()
            ->map(function ($payment) {
                $owner = $payment->unit?->owners?->first();
                return [
                    'id' => $payment->id,
                    'uf' => $payment->unit ? 'UF-' . $payment->unit->uf_number : '-',
                    'owner' => $owner?->full_name,
                    'amount' => (float) $payment->amount,
                    'date' => Carbon::parse($payment->payment_date)->toDateString(),
                    'status' => 'paid',
                ];
            })
            ->values();

        $overdueUnits = UnitExpense::with(['unit.owners', 'payments'])
            ->where('period', '<', $currentPeriod)
            ->whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->get()
            ->map(function ($expense) {
                $total = (float) $expense->monthly_amount
                    + (float) $expense->extraordinary_amount
                    + (float) $expense->fines_amount;
                $paid = (float) $expense->payments->sum('amount');
                $outstanding = max(0, $total - $paid);

                if ($outstanding <= 0) {
                    return null;
                }

                $periodDate = Carbon::createFromFormat('Y-m', $expense->period)->startOfMonth();
                $months = $periodDate->diffInMonths(now()->startOfMonth());
                $owner = $expense->unit?->owners?->first();

                return [
                    'id' => $expense->id,
                    'uf' => 'UF-' . $expense->unit->uf_number,
                    'owner' => $owner?->full_name,
                    'amount' => $outstanding,
                    'months' => max(1, $months),
                ];
            })
            ->filter()
            ->sortByDesc('amount')
            ->take(5)
            ->values();

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'totalOwners' => $totalOwners,
                'totalUnits' => $totalUnits,
                'totalCollected' => (float) $totalPaid,
                'totalOutstanding' => (float) $totalOutstanding,
                'monthlyBalance' => (float) $monthlyBalance,
            ],
            'recentPayments' => $recentPayments,
            'overdueUnits' => $overdueUnits,
        ]);
    }
}
