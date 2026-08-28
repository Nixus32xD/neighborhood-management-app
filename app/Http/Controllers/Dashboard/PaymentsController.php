<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankMovement;
use App\Models\Neighborhood;
use App\Models\Payment;
use App\Models\PaymentExpense;
use App\Models\PaymentPlan;
use App\Models\UnitExpense;
use App\Services\UnitDebtService;
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

    public function reconciliation(Request $request, UnitDebtService $debtService)
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

        $allExpenses = UnitExpense::with(['unit.owners', 'payments'])
            ->where('period', '<=', $period)
            ->whereHas('unit', fn($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->get()
            ->sortBy('period');
        $breakdownByUnit = $debtService->breakdownForExpenses($allExpenses)->groupBy(fn ($row) => $row['expense']->unit_id);
        $activePlans = PaymentPlan::with('installments')
            ->where('neighborhood_id', $neighborhoodId)->where('status', 'active')->get()->keyBy('unit_id');

        $debtByOwner = $breakdownByUnit
            ->map(function ($unitRows) use ($period) {
                $unitExpenses = $unitRows->pluck('expense');
                $first = $unitExpenses->first();
                $owner = $first?->unit?->owners?->pluck('full_name')->join(', ') ?: '-';
                $uf = $first?->unit?->uf_number;
                $historicalOutstanding = (float) $unitRows->filter(fn ($row) => $row['expense']->period < $period)->sum('current_outstanding');
                $currentOutstanding = (float) $unitRows->filter(fn ($row) => $row['expense']->period === $period)->sum('current_outstanding');

                return [
                    'unit_id' => $first?->unit_id,
                    'uf_number' => 'UF-' . $uf,
                    'owner' => $owner,
                    'historical_outstanding' => (float) $historicalOutstanding,
                    'current_outstanding' => (float) $currentOutstanding,
                    'total_outstanding' => round($historicalOutstanding + $currentOutstanding, 2),
                ];
            })
            ->sortBy(fn($row) => (int) str_replace('UF-', '', (string) $row['uf_number']))
            ->values();

        $expenses = $breakdownByUnit->map(function ($unitRows, $unitId) use ($period, $activePlans) {
            $current = $unitRows->first(fn ($row) => $row['expense']->period === $period);
            $first = $unitRows->first()['expense'];
            $currentExpense = $current['expense'] ?? null;
            $monthly = (float) ($currentExpense?->monthly_amount ?? 0);
            $extraordinary = (float) ($currentExpense?->extraordinary_amount ?? 0);
            $fines = (float) ($currentExpense?->fines_amount ?? 0);
            $paid = (float) ($current['normal_paid'] ?? 0);
            $historical = (float) $unitRows->filter(fn ($row) => $row['expense']->period < $period)->sum('current_outstanding');
            $currentOutstanding = (float) ($current['current_outstanding'] ?? 0);
            $total = round($monthly + $extraordinary + $fines + $historical, 2);
            $outstanding = round($historical + $currentOutstanding, 2);
            $plan = $activePlans->get($unitId);
            $next = $plan?->installments->first(fn ($installment) => $installment->status !== 'paid');

            return [
                'unit_id' => $unitId, 'uf_number' => 'UF-'.$first->unit->uf_number,
                'owner' => $first->unit->owners->pluck('full_name')->join(', '), 'monthly' => $monthly,
                'extraordinary' => $extraordinary, 'fines' => $fines, 'historical_outstanding' => $historical,
                'total' => $total, 'paid' => $paid, 'outstanding' => $outstanding,
                'status' => $outstanding <= 0 ? 'Pagado' : 'Pendiente',
                'active_plan' => $plan ? [
                    'id' => $plan->id, 'original_amount' => (float) $plan->original_amount,
                    'paid_amount' => (float) $plan->paid_amount, 'outstanding_amount' => (float) $plan->outstanding_amount,
                    'installments_count' => $plan->installments_count, 'installments_paid' => $plan->installments->where('status', 'paid')->count(),
                    'next_due_date' => $next?->due_date?->toDateString(),
                ] : null,
            ];
        })->filter(fn ($row) => $row['total'] > 0 || $row['active_plan'])->sortBy(fn ($row) => (int) str_replace('UF-', '', $row['uf_number']))->values();

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

    public function january2026StaticReconciliation()
    {
        $expenses = collect($this->january2026StaticExpenses());
        $movements = collect($this->january2026StaticMovements());
        $income = 3896484.21;
        $outflow = (float) $movements->sum('accounting_total');

        return response()->view('reports/monthly-reconciliation', [
            'neighborhoodName' => 'Consorcio Habitacional Casa de Campo II',
            'periodLabel' => 'enero 2026',
            'generatedAt' => now('America/Argentina/Buenos_Aires')->format('d/m/Y H:i'),
            'expenses' => $expenses,
            'debtByOwner' => collect(),
            'movements' => $movements,
            'staticSummary' => [
                'source' => 'Rendicion Enero 2026.pdf',
                'income' => $income,
                'outflow' => $outflow,
                'estimated_result' => $income - $outflow,
                'bank_balance' => 3896484.21,
                'cash_balance' => 0.0,
            ],
            'availability' => [
                ['description' => 'Saldo en c/c bancaria al 30/01/2026', 'amount' => 3896484.21],
                ['description' => 'Saldo en efectivo al 30/01/2026', 'amount' => 0.0],
            ],
            'notes' => [
                'Datos estaticos tomados del PDF. No se lee ni se escribe la base de datos.',
                'La hoja de expensas incluida en el PDF corresponde a Expensas Febrero 2026.',
                'El PDF indica: "De mas Gastos se desconoce y se encuentra la cuenta bancaria con este resto".',
            ],
            'totals' => [
                'monthly' => (float) $expenses->sum('monthly'),
                'extraordinary' => (float) $expenses->sum('extraordinary'),
                'fines' => (float) $expenses->sum('fines'),
                'charged' => (float) $expenses->sum('total'),
                'collected' => (float) $expenses->sum('paid'),
                'outstanding' => (float) $expenses->sum('outstanding'),
                'historical_outstanding' => (float) $expenses->sum('fines'),
                'cumulative_outstanding' => (float) $expenses->sum('outstanding'),
                'income' => $income,
                'outflow' => $outflow,
                'debit_taxes' => 0.0,
                'credit_taxes' => 0.0,
                'outflow_with_taxes' => $outflow,
                'net' => $income - $outflow,
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

    private function january2026StaticExpenses(): array
    {
        $rows = [
            ['1', 'CARLOS SPERDUTI', 48708.96, 63321.65, 112030.61],
            ['2', 'JOFRE MUNELLO ALEJANDRA', 47183.63, 191338.72, 238522.35],
            ['3', 'MOSCARDELLI CORIA DANIELA', 46471.82, 0.0, 46471.82],
            ['4', 'PARRA JOSE LUIS', 46573.50, 2302192.15, 2348765.65],
            ['5', 'HERRERA, ROSANA', 46471.82, 150000.00, 196471.82],
            ['6', 'FERNANDEZ, MARIANA', 46573.50, 150000.00, 196573.50],
            ['7', 'FERNANDEZ, JOSE MANUEL', 46573.50, 0.0, 46573.50],
            ['8', 'CALIRI PICON, MARIA LUCIA', 46573.50, 0.0, 46573.50],
            ['9', 'CALDERON MARIANO', 46776.87, 0.0, 46776.87],
            ['10', 'VEGA MARTIN CARLOS', 46980.26, 451099.42, 498079.68],
            ['11', 'CHIARELLO FABIAN', 49522.47, 1676608.08, 1726130.55],
            ['12', 'JOFRE MUNELLO, JOSEFINA', 61130.55, 1177348.58, 1238479.13],
            ['13', 'TORRES ALEJANDRA', 47488.70, 0.0, 47488.70],
            ['14', 'BUENO DAVID', 47183.63, 0.0, 47183.63],
            ['15', 'MANNUCCIA, RICARDO', 46900.00, 0.0, 46900.00],
            ['16', 'MOHAMMAD, DIEGO', 47997.14, 0.0, 47997.14],
            ['17', 'MOHAMMAD, LUIS', 48505.59, 0.0, 48505.59],
            ['18', 'RAMIREZ, JIMENA LUCIANA', 48912.34, 150000.00, 198912.34],
            ['19', 'ALARCON, PAOLA DAIANA', 49420.79, 150000.00, 199420.79],
            ['20', 'ALVAREZ, MARIA ALISA', 46675.19, 150000.00, 196675.19],
            ['21', 'PELAYES NATALIA', 46675.19, 150000.00, 196675.19],
        ];

        return array_map(fn($row) => [
            'unit_id' => (int) $row[0],
            'uf_number' => 'UF-' . $row[0],
            'owner' => $row[1],
            'monthly' => $row[2],
            'extraordinary' => 0.0,
            'fines' => $row[3],
            'total' => $row[4],
            'paid' => 0.0,
            'outstanding' => $row[4],
            'status' => 'Pendiente',
            'historical_outstanding' => $row[3],
        ], $rows);
    }

    private function january2026StaticMovements(): array
    {
        return [
            ['date' => '2026-01-31', 'description' => 'Impuesto a los Debitos y Creditos Bancarios', 'recipient' => 'Extracto Bancario', 'method' => 'Debito bancario', 'account' => 'C/C bancaria', 'accounting_total' => 16122.59],
            ['date' => '2026-01-31', 'description' => 'Gastos y Mantenimiento de Cuentas', 'recipient' => 'Extracto Bancario', 'method' => 'Debito bancario', 'account' => 'C/C bancaria', 'accounting_total' => 65343.00],
            ['date' => '2026-01-31', 'description' => 'Otros Gastos Bancarios (IVA)', 'recipient' => 'Extracto Bancario', 'method' => 'Debito bancario', 'account' => 'C/C bancaria', 'accounting_total' => 13722.03],
            ['date' => '2026-01-05', 'description' => 'Seguro Federacion Patronal', 'recipient' => 'Federacion Patronal', 'method' => 'Debito automatico', 'account' => 'C/C bancaria', 'accounting_total' => 5944.00],
            ['date' => '2026-01-28', 'description' => 'Seguro Mercantil Andina', 'recipient' => 'Mercantil Andina', 'method' => 'Debito automatico', 'account' => 'C/C bancaria', 'accounting_total' => 12895.00],
            ['date' => '2026-01-05', 'description' => 'Debito a Cuenta: 27-25352536-9', 'recipient' => '27-25352536-9', 'method' => 'Transferencia', 'account' => 'C/C bancaria', 'accounting_total' => 110000.00],
            ['date' => '2026-01-07', 'description' => 'Debito a Cuenta: Noble S.A', 'recipient' => 'Noble S.A', 'method' => 'Transferencia', 'account' => 'C/C bancaria', 'accounting_total' => 19799.38],
            ['date' => '2026-01-09', 'description' => 'Gastos Varios', 'recipient' => 'Segun PDF', 'method' => 'Debin', 'account' => 'C/C bancaria', 'accounting_total' => 180000.00],
            ['date' => '2026-01-19', 'description' => 'Debito a Cuenta: 27-25352536-9', 'recipient' => '27-25352536-9', 'method' => 'Transferencia', 'account' => 'C/C bancaria', 'accounting_total' => 50600.00],
            ['date' => '2026-01-20', 'description' => 'Debito a Cuenta: Noble S.A', 'recipient' => 'Noble S.A', 'method' => 'Transferencia', 'account' => 'C/C bancaria', 'accounting_total' => 19172.27],
            ['date' => '2026-01-21', 'description' => 'Debito Debin', 'recipient' => 'Segun PDF', 'method' => 'Debin', 'account' => 'C/C bancaria', 'accounting_total' => 58000.00],
            ['date' => '2027-01-21', 'description' => 'Debito Debin - fecha segun PDF', 'recipient' => 'Segun PDF', 'method' => 'Debin', 'account' => 'C/C bancaria', 'accounting_total' => 10000.00],
            ['date' => '2026-01-22', 'description' => 'Debito a Cuenta: Fabio', 'recipient' => 'Fabio Alejandro Noroa', 'method' => 'Transferencia', 'account' => 'C/C bancaria', 'accounting_total' => 150000.00],
            ['date' => '2026-01-31', 'description' => 'Factura Nro 1 - Carga de Nafta', 'recipient' => 'Segun factura', 'method' => 'Factura', 'account' => 'C/C bancaria', 'accounting_total' => 50605.89],
            ['date' => '2026-01-31', 'description' => 'Factura Nro 2 - Ferreteria Varios ISOFER', 'recipient' => 'ISOFER', 'method' => 'Factura', 'account' => 'C/C bancaria', 'accounting_total' => 56000.00],
            ['date' => '2026-01-21', 'description' => 'Factura Nro 3 - Copias de llaves Cerrajeria', 'recipient' => 'Cerrajeria', 'method' => 'Debin', 'account' => 'C/C bancaria', 'accounting_total' => 10000.00],
            ['date' => '2026-01-31', 'description' => 'Factura Nro 4 - Pago de Jardineria Fabio - Cancelacion', 'recipient' => 'Fabio', 'method' => 'Factura', 'account' => 'C/C bancaria', 'accounting_total' => 150000.00],
            ['date' => '2026-01-19', 'description' => 'Factura Nro 5 - Edensa 50% venc. 23/1', 'recipient' => 'Edensa', 'method' => 'Transferencia', 'account' => 'C/C bancaria', 'accounting_total' => 144847.00],
        ];
    }
}


