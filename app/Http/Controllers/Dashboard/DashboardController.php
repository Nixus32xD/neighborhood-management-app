<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;


class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $neighborhoodId = session('neighborhood_id');

        $totalOwners = Owner::whereHas('unit', function ($query) use ($neighborhoodId) {
            $query->where('neighborhood_id', $neighborhoodId);
        })->count();

        $uf = Unit::where('neighborhood_id','=',$neighborhoodId)->count();

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'totalOwners' => $totalOwners,
                'totalUnits' => $uf,
                'totalCollected' => 125000,
                'totalOutstanding' => 18500,
                'monthlyBalance' => 8500
            ]
        ]);
    }
}
