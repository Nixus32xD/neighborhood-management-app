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
use Illuminate\Support\Facades\DB;
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
                'tax_debit' => (float) ($pay->tax_debit ?? 0),
                'tax_credit' => (float) ($pay->tax_credit ?? 0),
                'accounting_total' => (float) $pay->amount + (float) ($pay->tax_debit ?? 0) - (float) ($pay->tax_credit ?? 0),
                'description' => $pay->description,
                'recipient' => $pay->recipient,
                'payment_method' => $pay->payment_method,
                'bank_account' => $pay->bankAccount
                    ? "{$pay->bankAccount->bank_name} - {$pay->bankAccount->account_type} {$pay->bankAccount->currency}"
                    : null,
                'voucher_url' => $pay->voucher_path ? route('payments.voucher', $pay) : null,
                'voucher_extension' => $pay->voucher_path ? strtolower(pathinfo($pay->voucher_path, PATHINFO_EXTENSION)) : null,
                'is_high_value' => $pay->is_high_value,
            ]);

        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $monthlyOutflow = $paymentsCollection
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->sum('amount');
        $monthlyDebitTaxes = $paymentsCollection
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->sum('tax_debit');
        $monthlyCreditTaxes = $paymentsCollection
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->sum('tax_credit');
        $monthlyOutflowWithTaxes = (float) $monthlyOutflow + (float) $monthlyDebitTaxes - (float) $monthlyCreditTaxes;

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
                ->select(DB::raw('COALESCE(SUM(amount + tax_debit - tax_credit), 0) as total'))
                ->value('total');

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

            $expenseIncome = PaymentExpense::where('bank_account_id', $account->id)
                ->whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
                ->when($openingDate, fn($q) => $q->whereDate('payment_date', '>=', $openingDate))
                ->sum('amount');

            $openingBalance = (float) ($account->opening_balance ?? 0);
            $currentBalance = $openingBalance + (float) $manualIncome + (float) $expenseIncome - ((float) $manualExpense + (float) $paymentsOutflow);

            return [
                'id' => $account->id,
                'label' => "{$account->bank_name} - {$account->account_type} {$account->currency}",
                'opening_balance' => $openingBalance,
                'opening_balance_date' => $openingDate,
                'current_balance' => (float) $currentBalance,
                'payments_outflow' => (float) $paymentsOutflow,
                'manual_income' => (float) $manualIncome,
                'expense_income' => (float) $expenseIncome,
                'manual_expense' => (float) $manualExpense,
            ];
        })->values();

        $openingBalanceTotal = (float) $accountsSummary->sum('opening_balance');
        $currentBalanceTotal = (float) $accountsSummary->sum('current_balance');
        $estimatedBalance = $currentBalanceTotal + ((float) $monthlyIncome - (float) $monthlyOutflowWithTaxes);

        return Inertia::render('Payments/Index', [
            'movements' => $payments,
            'bankAccounts' => BankAccount::options(),
            'accountsSummary' => $accountsSummary,
            'summary' => [
                'totalOutflow' => (float) $paymentsCollection->sum('amount'),
                'monthlyOutflow' => (float) $monthlyOutflow,
                'monthlyIncome' => (float) $monthlyIncome,
                'totalDebitTaxes' => (float) $paymentsCollection->sum('tax_debit'),
                'totalCreditTaxes' => (float) $paymentsCollection->sum('tax_credit'),
                'monthlyDebitTaxes' => (float) $monthlyDebitTaxes,
                'monthlyCreditTaxes' => (float) $monthlyCreditTaxes,
                'monthlyOutflowWithTaxes' => (float) $monthlyOutflowWithTaxes,
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'is_tax' => ['nullable', 'boolean'],
            'tax_type' => [
                Rule::requiredIf(fn() => $request->boolean('is_tax')),
                Rule::in(['debit', 'credit']),
            ],
            'tax_debit' => ['nullable', 'numeric', 'min:0'],
            'tax_credit' => ['nullable', 'numeric', 'min:0'],
            'description' => [Rule::requiredIf(fn() => !$request->boolean('is_tax')), 'nullable', 'string'],
            'recipient' => [Rule::requiredIf(fn() => !$request->boolean('is_tax')), 'nullable', 'string'],
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

        $isTax = $request->boolean('is_tax');
        $amount = (float) $data['amount'];
        $taxDebit = (float) ($data['tax_debit'] ?? 0);
        $taxCredit = (float) ($data['tax_credit'] ?? 0);
        $description = (string) ($data['description'] ?? '');
        $recipient = (string) ($data['recipient'] ?? '');

        if ($isTax) {
            $taxDebit = ($data['tax_type'] ?? null) === 'debit' ? $amount : 0.0;
            $taxCredit = ($data['tax_type'] ?? null) === 'credit' ? $amount : 0.0;
            $amount = 0.0;
            $description = ($data['tax_type'] ?? null) === 'debit'
                ? 'Impuesto débito'
                : 'Impuesto crédito';
            $recipient = 'AFIP / Entes fiscales';
        }

        Payment::create([
            'neighborhood_id' => $neighborhoodId,
            'date' => $data['date'],
            'amount' => $amount,
            'tax_debit' => $taxDebit,
            'tax_credit' => $taxCredit,
            'description' => $description,
            'recipient' => $recipient,
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
                    'unit_id' => $expense->unit_id,
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
                    'unit_id' => $first?->unit_id,
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

        $debtByUnit = $debtByOwner->keyBy('unit_id');

        $expenses = $expenses
            ->map(function ($expense) use ($debtByUnit) {
                $historicalOutstanding = (float) ($debtByUnit->get($expense['unit_id'])['historical_outstanding'] ?? 0);
                $displayFines = (float) $expense['fines'] + $historicalOutstanding;
                $displayOutstanding = (float) $expense['outstanding'] + $historicalOutstanding;

                $expense['historical_outstanding'] = $historicalOutstanding;
                $expense['fines'] = $displayFines;
                $expense['total'] = (float) $expense['monthly'] + (float) $expense['extraordinary'] + $displayFines;
                $expense['outstanding'] = $displayOutstanding;
                $expense['status'] = $displayOutstanding <= 0 ? 'Pagado' : 'Pendiente';

                return $expense;
            })
            ->concat(
                $debtByOwner
                    ->filter(fn($debt) => (float) $debt['historical_outstanding'] > 0)
                    ->reject(fn($debt) => $expenses->contains('unit_id', $debt['unit_id']))
                    ->map(fn($debt) => [
                        'unit_id' => $debt['unit_id'],
                        'uf_number' => $debt['uf_number'],
                        'owner' => $debt['owner'],
                        'monthly' => 0.0,
                        'extraordinary' => 0.0,
                        'fines' => (float) $debt['historical_outstanding'],
                        'paid' => 0.0,
                        'outstanding' => (float) $debt['historical_outstanding'],
                        'total' => (float) $debt['historical_outstanding'],
                        'status' => 'Pendiente',
                        'historical_outstanding' => (float) $debt['historical_outstanding'],
                    ])
            )
            ->sortBy(fn($row) => (int) str_replace('UF-', '', (string) $row['uf_number']))
            ->values();

        $paymentsForPeriod = Payment::with('bankAccount')
            ->where('neighborhood_id', $neighborhoodId)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderByDesc('date')
            ->get();

        $outflow = (float) $paymentsForPeriod->sum('amount');
        $debitTaxes = (float) $paymentsForPeriod->sum('tax_debit');
        $creditTaxes = (float) $paymentsForPeriod->sum('tax_credit');
        $netOutflowWithTaxes = (float) $outflow + $debitTaxes - $creditTaxes;

        $movements = $paymentsForPeriod
            ->filter(function ($payment) {
                $isTaxOnly = (float) $payment->amount <= 0
                    && (((float) ($payment->tax_debit ?? 0) > 0) || ((float) ($payment->tax_credit ?? 0) > 0));

                return !$isTaxOnly;
            })
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
                    'tax_debit' => 0.0,
                    'tax_credit' => 0.0,
                    'accounting_total' => (float) $payment->amount,
                ];
            });

        if ($debitTaxes > 0 || $creditTaxes > 0) {
            $movements->push([
                'date' => $end->toDateString(),
                'description' => 'Impuestos acumulados del período',
                'recipient' => 'AFIP / Entes fiscales',
                'method' => 'Ajuste fiscal',
                'account' => '-',
                'amount' => 0.0,
                'tax_debit' => $debitTaxes,
                'tax_credit' => $creditTaxes,
                'accounting_total' => $debitTaxes - $creditTaxes,
            ]);
        }

        $movements = $movements->values();

        $income = PaymentExpense::whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->whereDate('payment_date', '>=', $start->toDateString())
            ->whereDate('payment_date', '<=', $end->toDateString())
            ->sum('amount');

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
                'debit_taxes' => (float) $debitTaxes,
                'credit_taxes' => (float) $creditTaxes,
                'outflow_with_taxes' => (float) $netOutflowWithTaxes,
                'net' => (float) ($income - $netOutflowWithTaxes),
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


