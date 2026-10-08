<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Reservation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * Dashboard (T11): adds a "por autorizar" KPI linking to the pending queue.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now()->toDateString();

        $stats = [
            'pending'            => Reservation::pending()->upcoming()->count(),
            'today_covers'       => (int) Reservation::confirmed()->whereDate('reserved_date', $today)->sum('party_size'),
            'upcoming'           => Reservation::confirmed()->upcoming()->count(),
            'contacts'           => Contact::count(),
        ];

        $todayList = Reservation::confirmed()->with('contact')
            ->whereDate('reserved_date', $today)
            ->orderBy('reserved_time')
            ->get();

        $outlook = [];
        $max = 1;
        for ($i = 0; $i < 7; $i++) {
            $d = Carbon::parse($today)->addDays($i);
            $covers = (int) Reservation::confirmed()->whereDate('reserved_date', $d->toDateString())->sum('party_size');
            $outlook[] = ['label' => $d->locale('es')->translatedFormat('D j'), 'covers' => $covers];
            $max = max($max, $covers);
        }

        return view('dashboard', compact('stats', 'todayList', 'outlook', 'max'));
    }
}
