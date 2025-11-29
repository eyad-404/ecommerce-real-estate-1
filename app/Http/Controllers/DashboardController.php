<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\Logger;

class DashboardController extends Controller
{
    public function index()
    {
        $logger = \App\Services\Logger::getInstance();
        $user = auth()->user();

        try {
        $logger->info('Dashboard accessed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    } catch (\Exception $e) {
        $logger->error('Dashboard logging failed', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);
    }
        
        if ($user->role === 'admin') {
            $totalListings = Property::count();
            $last30Listings = Property::where('created_at', '>=', now()->subDays(30))->count();
            $prev30Listings = Property::whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count();
            $listingsDelta = $this->calculateDelta($last30Listings, $prev30Listings);

            $totalReservations = Property::where('status', 'reserved')->count();
            $last30Reservations = Property::where('status', 'reserved')->where('updated_at', '>=', now()->subDays(30))->count();
            $prev30Reservations = Property::where('status', 'reserved')->whereBetween('updated_at', [now()->subDays(60), now()->subDays(30)])->count();
            $reservationsDelta = $this->calculateDelta($last30Reservations, $prev30Reservations);

            $totalVisitors = DB::table('sessions')->count();
            $last30Visitors = DB::table('sessions')->where('last_activity', '>=', now()->subDays(30)->timestamp)->count();
            $prev30Visitors = DB::table('sessions')->whereBetween('last_activity', [now()->subDays(60)->timestamp, now()->subDays(30)->timestamp])->count();
            $visitorsDelta = $this->calculateDelta($last30Visitors, $prev30Visitors);

            $clients = User::where('role', 'seller')->get();

            $initialProperties = Property::all();
            $pieData = $this->generatePieData($initialProperties);
            $salesData = $this->generateMonthlyStats($initialProperties);

            return view('myauth.dashboard', compact(
                'totalListings','listingsDelta',
                'totalReservations','reservationsDelta',
                'totalVisitors','visitorsDelta',
                'clients','user','pieData','salesData'
            ));
        }

        $listings = $user->properties()->count();
        $reservations = $user->properties()->where('status','reserved')->count();
        $visitors = $user->visitors ?? 0;
        $pieData = $this->generatePieData($user->properties);
        $salesData = $this->generateMonthlyStats($user->properties);

        return view('myauth.dashboard', compact(
            'user','listings','reservations','visitors','pieData','salesData'
        ));
    }

    private function calculateDelta($current, $previous)
    {
        if ($previous == 0) return 0;
        return round((($current - $previous) / $previous) * 100);
    }

    // Admin-only AJAX endpoint
    public function getClientData(Request $request)
    {
        $logger = \App\Services\Logger::getInstance();
        $user = auth()->user();
        
        if ($user->role !== 'admin') {
        try {
            $logger->warning('Unauthorized client data access attempt', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            // fail silently
        }
        return response()->json(['error' => 'Unauthorized'], 403);
    }

         try {
        if (empty($request->id)) {
            $properties = Property::all();
            $visitors = DB::table('sessions')->count();

            $logger->info('Admin accessed all clients data', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } else {
            $client = User::with('properties')->find($request->id);
            if (!$client) {
                $logger->warning('Admin attempted to access non-existent client', [
                    'user_id' => $user->id,
                    'client_id' => $request->id,
                ]);
                return response()->json(['error' => 'Client not found'], 404);
            }

            $properties = $client->properties;
            $visitors = $client->visitors ?? 0;

            $logger->info('Admin accessed client data', [
                'user_id' => $user->id,
                'client_id' => $client->id,
                'email' => $user->email,
            ]);
        }
    } catch (\Exception $e) {
        $logger->error('Failed to get client data', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);
        return response()->json(['error' => 'Failed to retrieve data'], 500);
    }

        $sales = $this->generateMonthlyStats($properties);
        $pie = $this->generatePieData($properties);

        return response()->json([
            'listings' => $properties->count(),
            'reservations' => $properties->where('status', 'reserved')->count(),
            'visitors' => $visitors,
            'sales' => $sales,
            'pie' => $pie
        ]);
    }

    private function generateMonthlyStats($properties)
    {
        $sales = [];
        $now = Carbon::now();

        for ($i = 11; $i >= 0; $i--) {
            $start = $now->copy()->startOfMonth()->subMonths($i);
            $end = $start->copy()->endOfMonth();

            $monthlyProperties = $properties->filter(fn($p) => $p->created_at >= $start && $p->created_at <= $end);
            $monthlyReservations = $monthlyProperties->where('status','reserved')->count();

            $sales[] = [
                'label' => $start->format('M'),
                'listings' => $monthlyProperties->count(),
                'reservations' => $monthlyReservations,
                'visitors' => rand(50,150)
            ];
        }

        return $sales;
    }

    private function generatePieData($properties)
    {
        return $properties->groupBy('category')->map->count()->toArray();
    }
}
