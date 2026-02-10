<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ResidentRelation;
use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OwnerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $neighborhoodId = session('neighborhood_id');

        $owners = Owner::select('owners.*') // Evitá colisión de IDs
            ->join('units', 'owners.unit_id', '=', 'units.id')
            ->where('units.neighborhood_id', $neighborhoodId)
            ->with(['unit', 'residents']) // Cargá residents acá para evitar N+1
            // Para MySQL usamos +0 o CAST. +0 es un truco rápido y efectivo.
            ->orderByRaw('CAST(units.uf_number AS UNSIGNED) ASC')
            ->get()
            ->map(function ($owner) {
                return [
                    'id' => $owner->id,
                    'uf_number' => 'UF-' . $owner->unit->uf_number,
                    'name' => $owner->full_name,
                    'email' => $owner->email,
                    'residents' => $owner->residents->map(fn($r) => [
                        'name' => $r->full_name,
                        'relation' => $r->relation,
                    ])
                ];
            });

        return Inertia::render('Owners/Index', [
            'owners' => $owners,
            'residentRelations' => collect(ResidentRelation::cases())->map(fn($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
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
        $request->validate([
            'uf_number' => 'required|string',
            'name' => 'required|string',
            'email' => 'required|email',
            'residents' => 'required|array|min:1',
            'residents.*.name' => 'required|string|max:255',
            'residents.*.relation' => 'required|string|in:' . collect(ResidentRelation::cases())->pluck('value')->implode(','),

        ]);

        $neighborhoodId = session('neighborhood_id');

        // Creamos la unidad
        $unit = Unit::create([
            'neighborhood_id' => $neighborhoodId,
            'uf_number' => str_replace('UF-', '', $request->uf_number)
        ]);

        // Creamos el owner
        $owner = Owner::create([
            'unit_id' => $unit->id,
            'full_name' => $request->name,
            'email' => $request->email,
        ]);

        // Creamos los residentes
        $this->syncResidents($owner, $request->input('residents'));


        return redirect()->back();
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
        $request->validate([
            'uf_number' => 'required|string',
            'name' => 'required|string',
            'email' => 'required|email',
            'residents' => 'required|array|min:1',
            'residents.*.name' => 'required|string|max:255',
            'residents.*.relation' => 'required|string|in:' . collect(ResidentRelation::cases())->pluck('value')->implode(','),

        ]);

        $owner->update([
            'full_name' => $request->name,
            'email' => $request->email,
        ]);

        $owner->unit->update([
            'uf_number' => str_replace('UF-', '', $request->uf_number),
        ]);

        $owner->residents()->delete();
        $this->syncResidents($owner, $request->input('residents'));


        return redirect()->back();
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Owner $owner)
    {
        $owner->delete();
        return redirect()->back();
    }


    private function syncResidents(Owner $owner, array $residents): void
    {
        foreach ($residents as $resident) {
            $owner->residents()->create([
                'full_name' => $resident['name'],
                'relation' => $resident['relation'],
            ]);
        }
    }
}
