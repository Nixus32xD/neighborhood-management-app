<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankMovement;
use App\Models\Neighborhood;
use App\Models\Payment;
use App\Models\PaymentExpense;
use App\Models\UnitExpense;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PaymentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $neighborhoodId = session('neighborhood_id');

        $paymentsCollection = Payment::with('bankAccount')
            ->where('neighborhood_id', $neighborhoodId)
            ->latest('date')
            ->get();

        $payments = $paymentsCollection
            ->map(fn($pay) => [
                'id' => $pay->id,
                'date' => $pay->date->toDateString(),
                'amount' => $pay->amount,
                'description' => $pay->description,
                'recipient' => $pay->recipient,
                'payment_method' => $pay->payment_method,
                'bank_account' => $pay->bankAccount
                    ? "{$pay->bankAccount->bank_name} - {$pay->bankAccount->account_type} {$pay->bankAccount->currency}"
                    : null,
                'voucher_url' => $pay->voucher_url,
                'is_high_value' => $pay->is_high_value,
            ]);

        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $monthlyOutflow = $paymentsCollection
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->sum('amount');

        $monthlyIncome = PaymentExpense::whereDate('payment_date', '>=', $monthStart->toDateString())
            ->whereDate('payment_date', '<=', $monthEnd->toDateString())
            ->whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->sum('amount');

        $accounts = BankAccount::where('neighborhood_id', $neighborhoodId)
            ->orderBy('bank_name')
            ->get();

        $accountsSummary = $accounts->map(function ($account) use ($neighborhoodId) {
            $openingDate = $account->opening_balance_date
                ? $account->opening_balance_date->toDateString()
                : null;

            $paymentsOutflow = Payment::where('neighborhood_id', $neighborhoodId)
                ->where('bank_account_id', $account->id)
                ->when($openingDate, fn($q) => $q->whereDate('date', '>=', $openingDate))
                ->sum('amount');

            $manualIncome = BankMovement::where('neighborhood_id', $neighborhoodId)
                ->where('bank_account_id', $account->id)
                ->where('type', 'income')
                ->when($openingDate, fn($q) => $q->whereDate('date', '>=', $openingDate))
                ->sum('amount');

            $manualExpense = BankMovement::where('neighborhood_id', $neighborhoodId)
                ->where('bank_account_id', $account->id)
                ->where('type', 'expense')
                ->when($openingDate, fn($q) => $q->whereDate('date', '>=', $openingDate))
                ->sum('amount');

            $openingBalance = (float) ($account->opening_balance ?? 0);
            $currentBalance = $openingBalance + (float) $manualIncome - ((float) $manualExpense + (float) $paymentsOutflow);

            return [
                'id' => $account->id,
                'label' => "{$account->bank_name} - {$account->account_type} {$account->currency}",
                'opening_balance' => $openingBalance,
                'opening_balance_date' => $openingDate,
                'current_balance' => (float) $currentBalance,
                'payments_outflow' => (float) $paymentsOutflow,
                'manual_income' => (float) $manualIncome,
                'manual_expense' => (float) $manualExpense,
            ];
        })->values();

        $openingBalanceTotal = (float) $accountsSummary->sum('opening_balance');
        $currentBalanceTotal = (float) $accountsSummary->sum('current_balance');
        $estimatedBalance = $currentBalanceTotal + ((float) $monthlyIncome - (float) $monthlyOutflow);

        return Inertia::render('Payments/Index', [
            'movements' => $payments,
            'bankAccounts' => BankAccount::options(),
            'accountsSummary' => $accountsSummary,
            'summary' => [
                'totalOutflow' => (float) $paymentsCollection->sum('amount'),
                'monthlyOutflow' => (float) $monthlyOutflow,
                'monthlyIncome' => (float) $monthlyIncome,
                'openingBalanceTotal' => $openingBalanceTotal,
                'currentBalanceTotal' => $currentBalanceTotal,
                'estimatedBalance' => (float) $estimatedBalance,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $neighborhoodId = session('neighborhood_id');
        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['required', 'string'],
            'recipient' => ['required', 'string'],
            'payment_method' => ['required', 'string'],
            'bank_account' => [
                'nullable',
                Rule::requiredIf(fn() => in_array($request->input('payment_method'), ['Bank Transfer', 'Check'], true)),
                Rule::exists('bank_accounts', 'id')->where(fn($q) => $q->where('neighborhood_id', $neighborhoodId)),
            ],
            'voucher' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ]);

        $voucherPath = null;

        if ($request->hasFile('voucher')) {
            $voucherPath = $request->file('voucher')
                ->store('vouchers');
        }

        Payment::create([
            'neighborhood_id' => $neighborhoodId,
            'date' => $data['date'],
            'amount' => $data['amount'],
            'description' => $data['description'],
            'recipient' => $data['recipient'],
            'payment_method' => $data['payment_method'],
            'bank_account_id' => $data['payment_method'] === 'Cash'
                ? null
                : $data['bank_account'],
            'voucher_path' => $voucherPath,
            'is_high_value' => $data['amount'] > 50000,
        ]);

        return redirect()->back()->with('success', 'Pago Registrado Correctamente');
    }

    public function voucher(Payment $payment)
    {
        abort_unless($payment->voucher_path, 404);

        [$diskName, $disk] = $this->resolveVoucherDisk($payment->voucher_path);
        abort_unless($disk !== null, 404);

        try {
            if (method_exists($disk, 'temporaryUrl')) {
                $temporaryUrl = $disk->temporaryUrl(
                    $payment->voucher_path,
                    now()->addMinutes(10)
                );

                return redirect()->away($temporaryUrl);
            }
        } catch (\Throwable) {
            // If the disk does not support temporary URLs, fall back to streamed response.
        }

        return $disk->response($payment->voucher_path);
    }

    public function updateOpeningBalance(Request $request, BankAccount $bankAccount)
    {
        $neighborhoodId = session('neighborhood_id');
        abort_unless((int) $bankAccount->neighborhood_id === (int) $neighborhoodId, 403);

        $data = $request->validate([
            'opening_balance' => ['required', 'numeric'],
            'opening_balance_date' => ['required', 'date'],
        ]);

        $bankAccount->update([
            'opening_balance' => $data['opening_balance'],
            'opening_balance_date' => $data['opening_balance_date'],
        ]);

        return back()->with('success', 'Saldo inicial actualizado correctamente.');
    }

    public function reconciliation(Request $request)
    {
        $neighborhoodId = session('neighborhood_id');
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        $period = $data['period'];
        $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $start = $periodDate->copy()->startOfMonth();
        $end = $periodDate->copy()->endOfMonth();

        $neighborhood = Neighborhood::findOrFail($neighborhoodId);

        $expenses = UnitExpense::with(['unit.owners', 'payments'])
            ->where('period', $period)
            ->whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->get()
            ->sortBy(fn($expense) => (int) $expense->unit->uf_number)
            ->map(function ($expense) {
                $monthly = (float) $expense->monthly_amount;
                $extraordinary = (float) $expense->extraordinary_amount;
                $fines = (float) $expense->fines_amount;
                $total = $monthly + $extraordinary + $fines;
                $paid = (float) $expense->payments->sum('amount');
                $outstanding = max(0, $total - $paid);

                return [
                    'uf_number' => 'UF-' . $expense->unit->uf_number,
                    'owner' => $expense->unit->owners->pluck('full_name')->join(', '),
                    'monthly' => $monthly,
                    'extraordinary' => $extraordinary,
                    'fines' => $fines,
                    'total' => $total,
                    'paid' => $paid,
                    'outstanding' => $outstanding,
                    'status' => $outstanding <= 0 ? 'Pagado' : 'Pendiente',
                ];
            })
            ->values();

        $debtByOwner = UnitExpense::with(['unit.owners', 'payments'])
            ->where('period', '<=', $period)
            ->whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->get()
            ->groupBy('unit_id')
            ->map(function ($unitExpenses) use ($period) {
                $first = $unitExpenses->first();
                $owner = $first?->unit?->owners?->pluck('full_name')->join(', ') ?: '-';
                $uf = $first?->unit?->uf_number;

                $historicalOutstanding = 0.0;
                $currentOutstanding = 0.0;
                $totalOutstanding = 0.0;

                foreach ($unitExpenses as $expense) {
                    $charged = (float) $expense->monthly_amount
                        + (float) $expense->extraordinary_amount
                        + (float) $expense->fines_amount;
                    $paid = (float) $expense->payments->sum('amount');
                    $outstanding = max(0, $charged - $paid);

                    $totalOutstanding += $outstanding;
                    if ($expense->period < $period) {
                        $historicalOutstanding += $outstanding;
                    }
                    if ($expense->period === $period) {
                        $currentOutstanding += $outstanding;
                    }
                }

                return [
                    'uf_number' => 'UF-' . $uf,
                    'owner' => $owner,
                    'historical_outstanding' => (float) $historicalOutstanding,
                    'current_outstanding' => (float) $currentOutstanding,
                    'total_outstanding' => (float) $totalOutstanding,
                ];
            })
            ->filter(fn($row) => $row['total_outstanding'] > 0)
            ->sortBy(fn($row) => (int) str_replace('UF-', '', (string) $row['uf_number']))
            ->values();

        $movements = Payment::with('bankAccount')
            ->where('neighborhood_id', $neighborhoodId)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderByDesc('date')
            ->get()
            ->map(function ($payment) {
                return [
                    'date' => $payment->date->toDateString(),
                    'description' => $payment->description,
                    'recipient' => $payment->recipient,
                    'method' => $payment->payment_method,
                    'account' => $payment->bankAccount
                        ? "{$payment->bankAccount->bank_name} - {$payment->bankAccount->account_type} {$payment->bankAccount->currency}"
                        : '-',
                    'amount' => (float) $payment->amount,
                ];
            })
            ->values();

        $income = PaymentExpense::whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->whereDate('payment_date', '>=', $start->toDateString())
            ->whereDate('payment_date', '<=', $end->toDateString())
            ->sum('amount');

        $outflow = $movements->sum('amount');

        return response()->view('reports/monthly-reconciliation', [
            'neighborhoodName' => $neighborhood->name,
            'periodLabel' => $periodDate->locale('es')->translatedFormat('F Y'),
            'generatedAt' => now('America/Argentina/Buenos_Aires')->format('d/m/Y H:i'),
            'expenses' => $expenses,
            'debtByOwner' => $debtByOwner,
            'movements' => $movements,
            'totals' => [
                'monthly' => (float) $expenses->sum('monthly'),
                'extraordinary' => (float) $expenses->sum('extraordinary'),
                'fines' => (float) $expenses->sum('fines'),
                'charged' => (float) $expenses->sum('total'),
                'collected' => (float) $expenses->sum('paid'),
                'outstanding' => (float) $expenses->sum('outstanding'),
                'historical_outstanding' => (float) $debtByOwner->sum('historical_outstanding'),
                'cumulative_outstanding' => (float) $debtByOwner->sum('total_outstanding'),
                'income' => (float) $income,
                'outflow' => (float) $outflow,
                'net' => (float) ($income - $outflow),
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    private function resolveVoucherDisk(string $path): array
    {
        $candidates = [
            (string) config('filesystems.default', 'local'),
            'public',
        ];

        foreach (array_unique($candidates) as $diskName) {
            $disk = Storage::disk($diskName);
            if ($disk->exists($path)) {
                return [$diskName, $disk];
            }
        }

        return [null, null];
    }
}


