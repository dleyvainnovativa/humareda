<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Reservation;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now()->toDateString();

        $stats = [
            'today_reservations' => Reservation::confirmed()->whereDate('reserved_date', $today)->count(),
            'today_covers'       => (int) Reservation::confirmed()->whereDate('reserved_date', $today)->sum('party_size'),
            'upcoming'           => Reservation::confirmed()->upcoming()->count(),
            'contacts'           => Contact::count(),
        ];

        // Placeholder until T5/T10 build the full list views.
        $todayList = Reservation::confirmed()
            ->with('contact')
            ->whereDate('reserved_date', $today)
            ->orderBy('reserved_time')
            ->get();

        return view('dashboard', compact('stats', 'todayList'));
    }
}
