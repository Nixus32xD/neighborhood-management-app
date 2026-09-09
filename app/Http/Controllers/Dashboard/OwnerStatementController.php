<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Neighborhood;
use App\Models\Owner;
use App\Models\PaymentExpense;
use App\Models\PaymentPlan;
use App\Models\UnitExpense;
use App\Services\UnitDebtService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OwnerStatementController extends Controller
{
    public function index(Request $request)
    {
        $neighborhoodId = session('neighborhood_id');

        $owners = Owner::query()
            ->with('unit:id,uf_number')
            ->whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->get()
            ->sortBy(fn($owner) => (int) ($owner->unit->uf_number ?? 0), SORT_NATURAL)
            ->map(fn($owner) => [
                'id' => $owner->id,
                'name' => $owner->full_name,
                'uf' => 'UF-' . ($owner->unit->uf_number ?? '-'),
            ])
            ->values();

        if ($owners->isEmpty()) {
            return Inertia::render('Reports/OwnerStatements', [
                'owners' => [],
                'filters' => $this->normalizeFilters($request),
                'statement' => null,
            ]);
        }

        $filters = $this->normalizeFilters($request);
        $ownerId = (int) ($filters['owner_id'] ?: $owners->first()['id']);
        $owner = Owner::with('unit')
            ->whereKey($ownerId)
            ->whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->first();

        if (! $owner) {
            $owner = Owner::with('unit')->findOrFail($owners->first()['id']);
            $filters['owner_id'] = $owner->id;
        }

        return Inertia::render('Reports/OwnerStatements', [
            'owners' => $owners,
            'filters' => $filters,
            'statement' => $this->buildStatement($owner, $filters),
        ]);
    }

    public function print(Request $request)
    {
        $neighborhoodId = session('neighborhood_id');
        $filters = $this->normalizeFilters($request);
        $ownerId = (int) ($filters['owner_id'] ?: 0);

        $owner = Owner::with('unit')
            ->whereKey($ownerId)
            ->whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->firstOrFail();

        $statement = $this->buildStatement($owner, $filters);
        $neighborhood = Neighborhood::find($neighborhoodId);

        return response()->view('reports/owner-statement', [
            'neighborhoodName' => $neighborhood?->name ?? 'Barrio',
            'statement' => $statement,
            'generatedAt' => now('America/Argentina/Buenos_Aires')->format('d/m/Y H:i'),
        ]);
    }

    private function normalizeFilters(Request $request): array
    {
        $filterType = $request->input('filter_type', 'period');

        $periodFrom = $request->input('period_from', now()->subMonths(5)->format('Y-m'));
        $periodTo = $request->input('period_to', now()->format('Y-m'));

        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        if ($filterType === 'date' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        if ($filterType !== 'date' && $periodFrom > $periodTo) {
            [$periodFrom, $periodTo] = [$periodTo, $periodFrom];
        }

        return [
            'owner_id' => $request->input('owner_id'),
            'filter_type' => $filterType === 'date' ? 'date' : 'period',
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private function buildStatement(Owner $owner, array $filters): array
    {
        $unitId = $owner->unit_id;
        $filterType = $filters['filter_type'];
        $periodFrom = $filters['period_from'];
        $periodTo = $filters['period_to'];
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $expensesQuery = UnitExpense::with('payments')
            ->where('unit_id', $unitId)
            ->orderBy('period');

        if ($filterType === 'period') {
            $expensesQuery->whereBetween('period', [$periodFrom, $periodTo]);
        } else {
            $rangeStart = Carbon::parse($dateFrom)->format('Y-m');
            $rangeEnd = Carbon::parse($dateTo)->format('Y-m');
            $expensesQuery->whereBetween('period', [$rangeStart, $rangeEnd]);
        }

        $expenses = $expensesQuery->get();

        $breakdown = app(UnitDebtService::class)->breakdownForExpenses($expenses)->keyBy(fn ($row) => $row['expense']->id);
        $rows = $expenses->map(function ($expense) use ($filterType, $dateFrom, $dateTo, $breakdown) {
            $monthly = (float) $expense->monthly_amount;
            $extraordinary = (float) $expense->extraordinary_amount;
            $fines = (float) $expense->fines_amount;
            $charged = $monthly + $extraordinary + $fines;
            $paidTotal = (float) $breakdown->get($expense->id)['normal_paid'];

            $paidInRange = $filterType === 'period'
                ? $paidTotal
                : (float) $expense->payments
                    ->where('payment_date', '>=', $dateFrom)
                    ->where('payment_date', '<=', $dateTo)
                    ->sum('amount');

            $outstanding = (float) $breakdown->get($expense->id)['current_outstanding'];

            return [
                'period' => $expense->period,
                'monthly' => $monthly,
                'extraordinary' => $extraordinary,
                'fines' => $fines,
                'charged' => $charged,
                'paid_in_filter' => $paidInRange,
                'paid_total' => $paidTotal,
                'outstanding' => $outstanding,
                'status' => $outstanding <= 0 ? 'Pagado' : 'Pendiente',
            ];
        })->values();

        $paymentsQuery = PaymentExpense::query()
            ->where('unit_id', $unitId)
            ->with('unitExpense')
            ->orderBy('payment_date');

        if ($filterType === 'period') {
            $paymentsQuery->whereHas('unitExpense', fn($q) => $q->whereBetween('period', [$periodFrom, $periodTo]));
        } else {
            $paymentsQuery->whereDate('payment_date', '>=', $dateFrom)
                ->whereDate('payment_date', '<=', $dateTo);
        }

        $payments = $paymentsQuery->get()
            ->map(fn($payment) => [
                'date' => $payment->payment_date?->format('Y-m-d'),
                'period' => $payment->unitExpense?->period,
                'method' => $this->paymentMethodLabel((string) $payment->payment_method),
                'amount' => (float) $payment->amount,
            ])
            ->values();

        $plans = PaymentPlan::with('installments')
            ->where('unit_id', $unitId)
            ->whereIn('status', ['active', 'completed', 'cancelled'])
            ->latest()
            ->get()
            ->map(function (PaymentPlan $plan) {
                $next = $plan->installments->first(fn ($item) => $item->status !== 'paid');
                return [
                    'id' => $plan->id, 'status' => $plan->status, 'original_amount' => (float) $plan->original_amount,
                    'financed_debt_amount' => (float) $plan->financed_debt_amount,
                    'financing_charge_amount' => (float) $plan->financing_charge_amount,
                    'paid_amount' => (float) $plan->paid_amount, 'outstanding_amount' => (float) $plan->outstanding_amount,
                    'installments_count' => $plan->installments_count, 'installments_paid' => $plan->installments->where('status', 'paid')->count(),
                    'next_due_date' => $next?->due_date?->toDateString(),
                ];
            })->values();

        $filterLabel = $filterType === 'period'
            ? "Periodo {$periodFrom} a {$periodTo}"
            : 'Fechas ' . Carbon::parse($dateFrom)->format('d/m/Y') . ' a ' . Carbon::parse($dateTo)->format('d/m/Y');

        return [
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->full_name,
                'email' => $owner->email,
                'uf' => 'UF-' . ($owner->unit->uf_number ?? '-'),
            ],
            'filter_label' => $filterLabel,
            'charges' => $rows,
            'payments' => $payments,
            'payment_plans' => $plans,
            'summary' => [
                'charged_total' => (float) $rows->sum('charged'),
                'paid_in_filter_total' => (float) $rows->sum('paid_in_filter'),
                'paid_total' => (float) $rows->sum('paid_total'),
                'outstanding_total' => (float) $rows->sum('outstanding'),
            ],
        ];
    }

    private function paymentMethodLabel(string $method): string
    {
        return match (strtolower(trim($method))) {
            'cash' => 'Efectivo',
            'bank_transfer' => 'Transferencia bancaria',
            'check' => 'Cheque',
            'other' => 'Otro',
            default => $method !== '' ? $method : '-',
        };
    }
}
