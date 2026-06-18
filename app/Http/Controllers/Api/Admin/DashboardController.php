<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\NcpRecord;
use App\Models\RndClientRelationship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin dashboard statistics endpoint.
 * Returns counts, totals, and trend data for the admin panel overview.
 *
 * All queries are optimized with COUNT() and aggregate functions —
 * no full table scans or model hydration for stats.
 */
class DashboardController extends Controller
{
    /**
     * Main dashboard stats — all key metrics in one request.
     * Designed to power the admin overview/home page.
     */
    public function index(): JsonResponse
    {
        // --------------------------------------------------------
        // USER STATS
        // --------------------------------------------------------
        $totalUsers    = User::whereNull('deleted_at')->count();
        $totalClients  = User::where('role', 'client')->whereNull('deleted_at')->count();
        $totalRnds     = User::where('role', 'rnd')->whereNull('deleted_at')->count();
        $pendingRnds   = User::where('role', 'rnd')
            ->whereHas('rndProfile', fn($q) => $q->where('is_verified', false))
            ->whereNull('deleted_at')
            ->count();
        $inactiveUsers = User::where('is_active', false)->whereNull('deleted_at')->count();

        // --------------------------------------------------------
        // APPOINTMENT STATS
        // --------------------------------------------------------
        $totalAppointments   = Appointment::count();
        $pendingAppointments = Appointment::where('status', 'pending')->count();
        $confirmedAppointments = Appointment::where('status', 'confirmed')->count();
        $completedAppointments = Appointment::where('status', 'completed')->count();
        $cancelledAppointments = Appointment::where('status', 'cancelled')->count();

        // Appointments booked this month
        $appointmentsThisMonth = Appointment::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // --------------------------------------------------------
        // RELATIONSHIP STATS
        // --------------------------------------------------------
        $activeRelationships = RndClientRelationship::where('status', 'active')->count();
        $pendingRelationships = RndClientRelationship::where('status', 'pending')->count();

        // --------------------------------------------------------
        // NCP STATS
        // --------------------------------------------------------
        $totalNcpRecords     = NcpRecord::count();
        $draftNcpRecords     = NcpRecord::where('status', 'draft')->count();
        $completedNcpRecords = NcpRecord::where('status', 'completed')->count();

        // --------------------------------------------------------
        // BILLING STATS
        // --------------------------------------------------------
        $totalRevenue = Invoice::where('status', 'paid')->sum('amount');
        $totalCommission = Invoice::where('status', 'paid')->sum('commission_amt');
        $unpaidInvoices = Invoice::where('status', 'unpaid')->count();
        $unpaidTotal    = Invoice::where('status', 'unpaid')->sum('amount');

        // Revenue this month
        $revenueThisMonth = Invoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        // --------------------------------------------------------
        // OPTIONAL EXTERNAL API HOOK — Analytics Platform
        // If you integrate with an external analytics service
        // (e.g. Google Analytics, Mixpanel, or a BI tool),
        // you can push or pull additional metrics here.
        //
        // Example (pulling from external BI):
        // $analyticsData = app(AnalyticsServiceInterface::class)
        //     ->getDashboardMetrics(now()->startOfMonth(), now());
        //
        // Example (pushing events to Mixpanel):
        // app(AnalyticsServiceInterface::class)->track('admin.dashboard.viewed', [
        //     'admin_id' => auth()->id(),
        // ]);
        // --------------------------------------------------------

        return response()->json([
            'users' => [
                'total'        => $totalUsers,
                'clients'      => $totalClients,
                'rnds'         => $totalRnds,
                'pending_rnds' => $pendingRnds,
                'inactive'     => $inactiveUsers,
            ],
            'appointments' => [
                'total'        => $totalAppointments,
                'pending'      => $pendingAppointments,
                'confirmed'    => $confirmedAppointments,
                'completed'    => $completedAppointments,
                'cancelled'    => $cancelledAppointments,
                'this_month'   => $appointmentsThisMonth,
            ],
            'relationships' => [
                'active'  => $activeRelationships,
                'pending' => $pendingRelationships,
            ],
            'ncp_records' => [
                'total'     => $totalNcpRecords,
                'draft'     => $draftNcpRecords,
                'completed' => $completedNcpRecords,
            ],
            'billing' => [
                'total_revenue'     => round($totalRevenue, 2),
                'total_commission'  => round($totalCommission, 2),
                'revenue_this_month'=> round($revenueThisMonth, 2),
                'unpaid_invoices'   => $unpaidInvoices,
                'unpaid_total'      => round($unpaidTotal, 2),
            ],
        ]);
    }

    /**
     * Monthly revenue breakdown for the past 12 months.
     * Powers a revenue trend chart on the admin dashboard.
     */
    public function revenueChart(): JsonResponse
    {
        $months = Invoice::where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths(11)->startOfMonth())
            ->select(
                DB::raw('YEAR(paid_at) as year'),
                DB::raw('MONTH(paid_at) as month'),
                DB::raw('SUM(amount) as total_revenue'),
                DB::raw('SUM(commission_amt) as total_commission'),
                DB::raw('COUNT(*) as invoice_count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->map(fn($row) => [
                'period'           => "{$row->year}-" . str_pad($row->month, 2, '0', STR_PAD_LEFT),
                'total_revenue'    => round($row->total_revenue, 2),
                'total_commission' => round($row->total_commission, 2),
                'invoice_count'    => $row->invoice_count,
            ]);

        return response()->json(['chart' => $months]);
    }

    /**
     * Appointment breakdown by type and status for the past 30 days.
     * Powers the appointment analytics section.
     */
    public function appointmentChart(): JsonResponse
    {
        $byType = Appointment::where('created_at', '>=', now()->subDays(30))
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->get();

        $byStatus = Appointment::where('created_at', '>=', now()->subDays(30))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        // Daily appointment count for the past 30 days
        $daily = Appointment::where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'by_type'   => $byType,
            'by_status' => $byStatus,
            'daily'     => $daily,
        ]);
    }

    /**
     * Recent audit log entries for the admin activity feed.
     * Returns the 50 most recent audit events.
     */
    public function recentActivity(): JsonResponse
    {
        $logs = AuditLog::with('user:id,first_name,last_name,role,email')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['activity' => $logs]);
    }

    /**
     * Top RNDs by completed appointments and revenue.
     * Useful for the admin to identify top-performing RNDs.
     */
    public function topRnds(): JsonResponse
    {
        $topRnds = User::where('role', 'rnd')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->withCount([
                'rndRelationships as active_clients' => fn($q) => $q->where('status', 'active'),
                'rndRelationships as total_clients',
            ])
            ->with('rndProfile:user_id,specialization,consultation_fee,is_verified')
            ->orderBy('active_clients', 'desc')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'email', 'created_at']);

        return response()->json(['top_rnds' => $topRnds]);
    }
}
