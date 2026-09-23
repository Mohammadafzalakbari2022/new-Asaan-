<?php

namespace Cartxis\Sales\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the delivery staff home screen.
     */
    public function index(): Response
    {
        $driver = Auth::guard('delivery')->user();

        return Inertia::render('Delivery/Dashboard', [
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
            ],
        ]);
    }
}