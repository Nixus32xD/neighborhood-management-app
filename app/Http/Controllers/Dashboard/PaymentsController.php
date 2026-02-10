<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PaymentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $neighborhoodId = session('neighborhood_id');

        $payments = Payment::where('neighborhood_id', $neighborhoodId)
            ->latest('date')
            ->get()
            ->map(fn($pay) => [
                'id' => $pay->id,
                'date' => $pay->date->toDateString(),
                'amount' => $pay->amount,
                'description' => $pay->description,
                'recipient' => $pay->recipient,
                'payment_method' => $pay->payment_method,
                'bank_account' => $pay->bank_account,
                'voucher_url' => $pay->voucher_url,
                'is_high_value' => $pay->is_high_value,
            ]);
        return Inertia::render('Payments/Index', [
            'movements' => $payments,
            'bankAccounts' => BankAccount::options()
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
            'bank_account' => ['nullable', 'exists:bank_accounts,id'],
            'voucher' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ]);

        $voucherPath = null;

        if ($request->hasFile('voucher')) {
            $voucherPath = $request->file('voucher')
                ->store('vouchers', 'public');
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
            'is_high_value' => $data['amount'] > 10000,
        ]);

        return redirect()->back()->with('success', 'Pago Registrado Correctamente');
    }

    public function voucher(Payment $payment)
    {
        abort_unless($payment->voucher_path, 404);

        return Storage::disk('public')->response($payment->voucher_path);
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
}
