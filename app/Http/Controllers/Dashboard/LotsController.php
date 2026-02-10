<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LotsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $neighborhoodId = session('neighborhood_id');

        // Base query (la usamos para stats y data)
        $unitsQuery = Unit::with(['owners.residents'])
            ->where('neighborhood_id', $neighborhoodId);

        /*
    |--------------------------------------------------------------------------
    | STATS (antes del map)
    |--------------------------------------------------------------------------
    */

        $totalLots = $unitsQuery->count();

        $totalSurface = $unitsQuery->sum('surface_area');

        $averageSurface = $totalLots > 0
            ? $totalSurface / $totalLots
            : 0;

        $totalCoefficient = $unitsQuery->sum('expense_coefficient');

        $stats = [
            'totalLots' => $totalLots,
            'totalSurface' => round($totalSurface, 2),
            'averageSurface' => round($averageSurface, 2),
            'totalCoefficient' => round($totalCoefficient, 4),
        ];

        /*
    |--------------------------------------------------------------------------
    | LOTS DATA
    |--------------------------------------------------------------------------
    */

        $units = $unitsQuery
            ->orderByRaw('CAST(uf_number AS UNSIGNED) ASC')
            ->get()
            ->map(function ($unit) {
                $owner = $unit->owners->first();

                return [
                    'id' => $unit->id,
                    'uf_number' => 'UF-' . $unit->uf_number,
                    'surface_area' => $unit->surface_area,
                    'expense_percentage' => $unit->expense_percentage,
                    'base_expense' => null, // luego se puede calcular dinámico
                    'status' => $unit->active ? 'active' : 'inactive',

                    'owner_name' => $owner?->full_name,
                    'owner_email' => $owner?->email,
                    'owner_phone' => $owner?->phone ?? null,
                    'owner_dni' => $owner?->dni ?? null,

                    'residents' => $owner?->residents?->map(fn($r) => [
                        'id' => $r->id,
                        'name' => $r->full_name,
                        'relation' => $r->relation,
                    ]) ?? [],

                    'dimensions' => $unit->dimensions,
                    'notes' => $unit->notes,
                ];
            });

        return Inertia::render('Lots/Index', [
            'lots' => $units,
            'stats' => $stats,
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
