<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Neighborhood;
use App\Models\UnitExpense;
use App\Services\ExpenseGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ExpensesController extends Controller
{
    /**
     * Vista principal de expensas
     */
    public function index()
    {
        $neighborhoodId = session('neighborhood_id');
        $period = now()->format('Y-m');

        // 1. Buscamos el barrio para obtener su configuración (CC1 o CC2)
        $neighborhood = Neighborhood::findOrFail($neighborhoodId);

        $rows = UnitExpense::with(['unit', 'unit.owners', 'payments'])
            ->whereHas('unit', fn ($q) => $q->where('neighborhood_id', $neighborhoodId))
            ->get()

            // Ordenamos en memoria (PHP)
            ->sortBy('unit.uf_number', SORT_NATURAL)

            ->map(function ($e) {
                $total = $e->monthly_amount
                    + $e->extraordinary_amount
                    + $e->fines_amount;

                $paid = $e->payments->sum('amount');
                $outstanding = max(0, $total - $paid);

                return [
                    'id' => $e->id,                 // unit_expense_id
                    'unit_id' => $e->unit->id,      // unit_id real
                    'uf_number' => 'UF-'.$e->unit->uf_number,
                    'period' => $e->period,
                    'owner' => $e->unit->owners
                        ->map(fn ($owner) => $owner->full_name)
                        ->join(', '),
                    'monthly_expense' => $e->monthly_amount,
                    'extraordinary' => $e->extraordinary_amount,
                    'fines' => $e->fines_amount,
                    'paid_amount' => (float) $paid,
                    'outstanding_debt' => $outstanding,
                    'total_balance' => $total,
                    'status' => $outstanding === 0
                        ? 'paid'
                        : ($e->period < now()->format('Y-m') ? 'overdue' : 'pending'),
                ];
            })->values();

        // dd(Carbon::now()->format('Y-m-d H:i:s'));
        return Inertia::render('Expenses/Index', [
            'expenses' => $rows,
            'bankAccounts' => BankAccount::options(),
            'summary' => [
                'totalMonthly' => $rows->sum('monthly_expense'),
                'totalExtraordinary' => $rows->sum('extraordinary'),
                'totalFines' => $rows->sum('fines'),
                'totalOutstanding' => $rows->sum('outstanding_debt'),
                'totalCollected' => $rows->sum('paid_amount'),
            ],
            // 2. AGREGADO: Pasamos la configuración al frontend
            'neighborhoodConfig' => [
                'type' => $neighborhood->expense_calculation_type, // 'fixed' o 'proportional'
                'fixed_amount' => $neighborhood->fixed_amount,     // Valor default si es fijo
            ],
        ]);
    }

    /**
     * Registrar pago de expensa
     * Ruta: expenses.store
     */
    public function store(Request $request)
    {
        $neighborhoodId = session('neighborhood_id');

        // 1. Validamos.
        // OJO: En tu frontend el campo se llama 'unit_id', pero trae el ID de la expensa.
        // Lo validamos contra la tabla 'unit_expenses'.
        $data = $request->validate([
            'unit_id' => 'required|exists:unit_expenses,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'bank_account' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($request->input('payment_method'), ['bank_transfer', 'check'], true)),
                Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('neighborhood_id', $neighborhoodId)),
            ],
            'reference' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data) {
            // 2. Buscamos la expensa "Padre"
            $expense = UnitExpense::lockForUpdate()->find($data['unit_id']);

            // 3. Creamos el pago usando la relación
            // Laravel asigna automáticamente el unit_expense_id
            $expense->payments()->create([
                'unit_id' => $expense->unit_id, // Obtenemos el ID real de la unidad desde la expensa (Seguro)
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'bank_account_id' => $data['payment_method'] === 'cash'
                    ? null
                    : ($data['bank_account'] ?? null),
                'reference' => $data['reference'] ?? null,
            ]);

            // 4. Actualizamos el acumulado pagado en la tabla padre
            // Esto es clave para que el 'outstanding_debt' baje a 0.
            $expense->increment('paid_amount', $data['amount']);
        });

        return redirect()->back()->with('success', 'Pago registrado correctamente.');
    }

    public function storeAccumulated(Request $request)
    {
        $neighborhoodId = session('neighborhood_id');

        $data = $request->validate([
            'unit_id' => [
                'required',
                Rule::exists('units', 'id')->where(fn ($q) => $q->where('neighborhood_id', $neighborhoodId)),
            ],
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'bank_account' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($request->input('payment_method'), ['bank_transfer', 'check'], true)),
                Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('neighborhood_id', $neighborhoodId)),
            ],
            'reference' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data) {
            $periodLimit = now()->format('Y-m');
            $remaining = round((float) $data['amount'], 2);
            $totalDebt = 0.0;

            $debts = UnitExpense::where('unit_id', $data['unit_id'])
                ->where('period', '<=', $periodLimit)
                ->orderBy('period')
                ->lockForUpdate()
                ->get()
                ->map(function (UnitExpense $expense) use (&$totalDebt) {
                    $total = (float) $expense->monthly_amount
                        + (float) $expense->extraordinary_amount
                        + (float) $expense->fines_amount;

                    $paid = (float) $expense->payments()->sum('amount');
                    $debt = round(max(0, $total - $paid), 2);
                    $totalDebt = round($totalDebt + $debt, 2);

                    return [
                        'expense' => $expense,
                        'debt' => $debt,
                    ];
                })
                ->filter(fn ($row) => $row['debt'] > 0)
                ->values();

            if ($totalDebt <= 0) {
                throw ValidationException::withMessages([
                    'unit_id' => 'La unidad no tiene deuda acumulada hasta el periodo actual.',
                ]);
            }

            if ($remaining > $totalDebt) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto ingresado supera la deuda acumulada. Maximo permitido: $'.number_format($totalDebt, 2, ',', '.'),
                ]);
            }

            foreach ($debts as $row) {
                if ($remaining <= 0) {
                    break;
                }

                $expense = $row['expense'];
                $amountToApply = round(min($remaining, $row['debt']), 2);

                if ($amountToApply <= 0) {
                    continue;
                }

                $expense->payments()->create([
                    'unit_id' => $expense->unit_id,
                    'amount' => $amountToApply,
                    'payment_date' => $data['payment_date'],
                    'payment_method' => $data['payment_method'],
                    'bank_account_id' => $data['payment_method'] === 'cash'
                        ? null
                        : ($data['bank_account'] ?? null),
                    'reference' => $data['reference'] ?? null,
                ]);

                $expense->increment('paid_amount', $amountToApply);
                $remaining = round($remaining - $amountToApply, 2);
            }
        });

        return redirect()->back()->with('success', 'Pago acumulado registrado correctamente.');
    }

    public function generate(Request $request, ExpenseGeneratorService $generator)
    {
        // 1. Validar inputs
        $data = $request->validate([
            'period' => 'required|date_format:Y-m',
            'amount' => 'nullable|numeric', // Para CC1
            'base_amount' => 'nullable|numeric', // Para CC2
            'base_meters' => 'nullable|numeric', // Para CC2
        ]);

        $neighborhood = Neighborhood::findOrFail(session('neighborhood_id'));

        try {
            // 2. Llamar al servicio mágico
            $generator->generate($neighborhood, $data['period'], $data);

            return redirect()->back()->with('success', 'Expensas generadas correctamente para todo el barrio.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function addFine(Request $request, UnitExpense $expense)
    {
        $neighborhoodId = session('neighborhood_id');
        $belongsToActiveNeighborhood = $expense->unit()
            ->where('neighborhood_id', $neighborhoodId)
            ->exists();

        abort_unless($belongsToActiveNeighborhood, 403);

        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        $expense->increment('fines_amount', $data['amount']);

        return back()->with('success', 'Multa aplicada correctamente.');
    }

    public function addExtraordinary(Request $request, ExpenseGeneratorService $generator)
    {
        $neighborhoodId = session('neighborhood_id');
        $data = $request->validate([
            'period' => 'required|date_format:Y-m',
            'amount' => 'required|numeric|min:1',
            'base_meters' => 'nullable|numeric|min:0.01',
        ]);

        $neighborhood = Neighborhood::with('units:id,neighborhood_id,surface_area,expense_coefficient')
            ->findOrFail($neighborhoodId);

        try {
            DB::transaction(function () use ($neighborhood, $data, $generator) {
                $amount = (float) $data['amount'];
                $baseMeters = (float) ($data['base_meters'] ?? 500);
                $totalSurface = (float) $neighborhood->units->sum('surface_area');

                foreach ($neighborhood->units as $unit) {
                    $extraordinaryAmount = $neighborhood->expense_calculation_type === 'proportional'
                        ? $generator->calculateProportionalAmount($unit, $amount, $baseMeters, $totalSurface)
                        : $amount;

                    $expense = UnitExpense::firstOrCreate(
                        [
                            'unit_id' => $unit->id,
                            'period' => $data['period'],
                        ],
                        [
                            'monthly_amount' => 0,
                            'extraordinary_amount' => 0,
                            'fines_amount' => 0,
                            'paid_amount' => 0,
                        ]
                    );

                    $expense->increment('extraordinary_amount', $extraordinaryAmount);
                }
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Expensa extraordinaria aplicada.');
    }
}
