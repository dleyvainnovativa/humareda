<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Reservation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * Dashboard (T10 replaces the T1 version): KPIs, today's list, and a 7-day
 * covers outlook rendered as CSS bars (no JS chart lib — shared-hosting light).
 */
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

        $todayList = Reservation::confirmed()->with('contact')
            ->whereDate('reserved_date', $today)
            ->orderBy('reserved_time')
            ->get();

        // Next 7 days covers outlook.
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
