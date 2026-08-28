<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Owner;
use App\Models\PaymentPlan;
use App\Models\Unit;
use App\Services\PaymentPlanPaymentService;
use App\Services\PaymentPlanService;
use App\Services\UnitDebtService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PaymentPlanController extends Controller
{
    public function index(Request $request, UnitDebtService $debtService)
    {
        $neighborhoodId = $this->neighborhoodId();
        $status = $request->input('status', 'active');
        $search = trim((string) $request->input('search', ''));

        $plans = PaymentPlan::with(['unit.owners', 'owner', 'installments'])
            ->where('neighborhood_id', $neighborhoodId)
            ->when(in_array($status, ['active', 'completed', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->whereHas('unit', fn ($units) => $units->where('uf_number', 'like', "%{$search}%"))
                        ->orWhereHas('owner', fn ($owners) => $owners->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->get()
            ->map(fn (PaymentPlan $plan) => $this->planPayload($plan))
            ->values();

        $units = Unit::with(['owners', 'expenses.payments'])
            ->where('neighborhood_id', $neighborhoodId)
            ->get()
            ->sortBy('uf_number', SORT_NATURAL)
            ->values();
        $expenseBreakdown = $debtService->breakdownForExpenses($units->pluck('expenses')->flatten())
            ->groupBy(fn ($row) => $row['expense']->unit_id);
        $eligibleUnits = $units->map(function (Unit $unit) use ($expenseBreakdown) {
            $items = $expenseBreakdown->get($unit->id, collect())->filter(fn ($row) => $row['current_outstanding'] > 0);
            if ($items->isEmpty() || $unit->paymentPlans()->where('status', 'active')->exists()) return null;
            return [
                'unit_id' => $unit->id,
                'owners' => $unit->owners->map(fn ($owner) => ['id' => $owner->id, 'full_name' => $owner->full_name])->values(),
                'uf_number' => 'UF-'.$unit->uf_number,
                'items' => $items->map(fn ($row) => [
                    'unit_expense_id' => $row['expense']->id, 'period' => $row['expense']->period,
                    'amount' => $row['current_outstanding'],
                ])->values(),
            ];
        })->filter()->values();

        return Inertia::render('PaymentPlans/Index', [
            'plans' => $plans, 'filters' => ['status' => $status, 'search' => $search],
            'eligibleUnits' => $eligibleUnits, 'bankAccounts' => BankAccount::options(),
        ]);
    }

    public function store(Request $request, PaymentPlanService $service)
    {
        $neighborhoodId = $this->neighborhoodId();
        $data = $request->validate([
            'unit_id' => ['required', Rule::exists('units', 'id')->where('neighborhood_id', $neighborhoodId)],
            'owner_id' => ['nullable', Rule::exists('owners', 'id')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.unit_expense_id' => ['required', 'integer', 'distinct', Rule::exists('unit_expenses', 'id')],
            'items.*.amount' => ['required', 'numeric', 'min:0.01'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:120'],
            'start_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        if (! empty($data['owner_id']) && ! Owner::whereKey($data['owner_id'])->where('unit_id', $data['unit_id'])->exists()) {
            abort(422, 'El propietario no pertenece a la unidad seleccionada.');
        }
        $service->create($neighborhoodId, $data, $request->user()?->id);
        return back()->with('success', 'Plan de pago creado correctamente.');
    }

    public function show(PaymentPlan $paymentPlan)
    {
        $this->ensurePlanNeighborhood($paymentPlan);
        $paymentPlan->load(['unit.owners', 'owner', 'items.unitExpense', 'installments', 'payments.bankAccount', 'createdBy', 'cancelledBy']);
        return Inertia::render('PaymentPlans/Show', ['plan' => $this->detailPayload($paymentPlan), 'bankAccounts' => BankAccount::options()]);
    }

    public function pay(Request $request, PaymentPlan $paymentPlan, PaymentPlanPaymentService $service)
    {
        $neighborhoodId = $this->ensurePlanNeighborhood($paymentPlan);
        $data = $request->validate([
            'payment_plan_installment_id' => ['required', 'integer', Rule::exists('payment_plan_installments', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01'], 'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:100'],
            'bank_account' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($request->input('payment_method'), ['bank_transfer', 'check'], true)),
                Rule::exists('bank_accounts', 'id')->where('neighborhood_id', $neighborhoodId),
            ],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);
        $service->register($paymentPlan, $data, $request->user()?->id);
        return back()->with('success', 'Pago de cuota registrado correctamente.');
    }

    public function cancel(Request $request, PaymentPlan $paymentPlan, PaymentPlanService $service)
    {
        $this->ensurePlanNeighborhood($paymentPlan);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:3000']]);
        $service->cancel($paymentPlan, $data['reason'] ?? null, $request->user()?->id);
        return back()->with('success', 'Plan cancelado. El saldo impago volvió a la deuda corriente.');
    }

    private function neighborhoodId(): int
    {
        $id = (int) session('neighborhood_id');
        abort_unless($id > 0, 403);
        return $id;
    }

    private function ensurePlanNeighborhood(PaymentPlan $plan): int
    {
        $neighborhoodId = $this->neighborhoodId();
        abort_unless((int) $plan->neighborhood_id === $neighborhoodId, 403);
        return $neighborhoodId;
    }

    private function planPayload(PaymentPlan $plan): array
    {
        $next = $plan->installments->first(fn ($item) => $item->status !== 'paid');
        return [
            'id' => $plan->id, 'uf_number' => 'UF-'.$plan->unit->uf_number,
            'owner' => $plan->owner?->full_name ?? $plan->unit->owners->pluck('full_name')->join(', '),
            'original_amount' => (float) $plan->original_amount, 'paid_amount' => (float) $plan->paid_amount,
            'outstanding_amount' => (float) $plan->outstanding_amount, 'installments_count' => $plan->installments_count,
            'installments_paid' => $plan->installments->where('status', 'paid')->count(), 'status' => $plan->status,
            'next_installment' => $next ? ['id' => $next->id, 'number' => $next->installment_number, 'amount' => (float) $next->amount, 'paid_amount' => (float) $next->paid_amount, 'due_date' => $next->due_date?->toDateString()] : null,
        ];
    }

    private function detailPayload(PaymentPlan $plan): array
    {
        return $this->planPayload($plan) + [
            'owner_id' => $plan->owner_id, 'start_date' => $plan->start_date?->toDateString(), 'notes' => $plan->notes,
            'cancellation_reason' => $plan->cancellation_reason, 'completed_at' => $plan->completed_at?->toDateTimeString(),
            'cancelled_at' => $plan->cancelled_at?->toDateTimeString(),
            'installments' => $plan->installments->map(fn ($item) => [
                'id' => $item->id, 'number' => $item->installment_number, 'amount' => (float) $item->amount,
                'paid_amount' => (float) $item->paid_amount, 'due_date' => $item->due_date?->toDateString(), 'status' => $item->status,
            ])->values(),
            'items' => $plan->items->map(fn ($item) => ['period' => $item->unitExpense->period, 'financed_amount' => (float) $item->financed_amount, 'settled_amount' => (float) $item->settled_amount])->values(),
            'payments' => $plan->payments->map(fn ($payment) => [
                'date' => $payment->payment_date?->toDateString(), 'amount' => (float) $payment->amount,
                'method' => $payment->payment_method, 'reference' => $payment->reference,
            ])->values(),
        ];
    }
}
