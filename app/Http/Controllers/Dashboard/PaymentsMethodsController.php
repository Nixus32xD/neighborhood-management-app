<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentsMethodsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $neighborhoodId = session('neighborhood_id');

        $paymentMethods = Unit::with('owners')
            ->where('neighborhood_id', $neighborhoodId)
            ->orderByRaw('CAST(uf_number AS UNSIGNED) ASC')
            ->get()
            ->map(function ($unit) {
                $owner = $unit->owners->first();

                return [
                    'id' => $owner?->id,
                    'uf_number' => 'UF-' . $unit->uf_number,
                    'owner' => $owner?->full_name,

                    'preferred_method' => $owner?->preferred_method,
                    'bank_name' => $owner?->bank_name,
                    'account_holder' => $owner?->account_holder,
                    'cbu' => $owner?->cbu,
                    'alias' => $owner?->alias,
                    'custom_method' => $owner?->custom_method,
                ];
            })
            ->filter(fn($row) => $row['id']); // elimina unidades sin dueño

        return Inertia::render('PaymentMethods/Index', [
            'paymentMethods' => $paymentMethods
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
        //
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
    public function update(Request $request, Owner $owner)
    {
        $data = $request->validate([
            'preferred_method' => 'required|string',
            'custom_method' => 'nullable|string|max:255',

            'bank_name' => 'nullable|string|max:255',
            'account_holder' => 'nullable|string|max:255',
            'cbu' => 'nullable|string|max:30',
            'alias' => 'nullable|string|max:50',
        ]);

        // Limpieza lógica según método
        if ($data['preferred_method'] !== 'Bank Transfer') {
            $data['bank_name'] = null;
            $data['account_holder'] = null;
            $data['cbu'] = null;
            $data['alias'] = null;
        }

        if ($data['preferred_method'] !== 'Other') {
            $data['custom_method'] = null;
        }

        $owner->update($data);

        return back()->with('success', 'Payment method updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
