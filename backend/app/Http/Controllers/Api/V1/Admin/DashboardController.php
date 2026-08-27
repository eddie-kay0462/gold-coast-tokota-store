<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard metrics (README Feature 9).
 *
 * Direct queries, no caching or pre-aggregation layer — the README is explicit
 * that metrics reflect live data on every load, and says the upgrade to a
 * scheduled pre-aggregation job is a later concern if volume ever demands it.
 */
class DashboardController extends Controller
{
    /** Statuses that represent money actually taken. */
    private const REVENUE_STATUSES = ['paid', 'processing', 'shipped', 'delivered'];

    public function metrics(): JsonResponse
    {
        $today = today();

        $ordersToday = Order::query()->whereDate('created_at', $today)->count();
        $ordersYesterday = Order::query()->whereDate('created_at', $today->copy()->subDay())->count();

        $revenueThisWeek = $this->revenueSince($today->copy()->startOfWeek());
        $revenueLastWeek = $this->revenueBetween(
            $today->copy()->subWeek()->startOfWeek(),
            $today->copy()->subWeek()->endOfWeek(),
        );

        return response()->json(['data' => [
            'orders_today' => $ordersToday,
            'orders_today_delta' => $this->percentageChange($ordersYesterday, $ordersToday),
            'orders_this_week' => Order::query()
                ->where('created_at', '>=', $today->copy()->startOfWeek())
                ->count(),

            // Split by currency rather than summed: a GHS total and a USD total
            // are different quantities, and adding them would need a rate that
            // would then disagree with every order's own locked rate.
            'revenue_ghs' => ['amount' => $revenueThisWeek['GHS'], 'currency' => 'GHS'],
            'revenue_usd' => ['amount' => $revenueThisWeek['USD'], 'currency' => 'USD'],
            'revenue_delta' => $this->percentageChange($revenueLastWeek['GHS'], $revenueThisWeek['GHS']),

            'low_stock_count' => InventoryItem::query()
                ->whereColumn('quantity_available', '<=', 'low_stock_threshold')
                ->count(),

            'pending_bookings' => Booking::query()->where('status', 'pending')->count(),
            'waitlist_count' => Booking::query()->where('status', 'waitlisted')->count(),

            // Null, not zero. There is no inbox and no returns table — those
            // screens exist in the admin app but nothing in the README
            // specifies them and no model backs them. A confident `0` would
            // read as "no open returns" rather than "returns do not exist",
            // and the admin app's own rule is that invented numbers must look
            // invented.
            'unread_messages' => null,
            'open_returns' => null,

            // Feature 9 acceptance criteria: metrics show when they were read.
            'generated_at' => now(),
        ]]);
    }

    /** @return array{GHS: int, USD: int} */
    private function revenueSince(\Carbon\CarbonInterface $from): array
    {
        return $this->sumByCurrency(
            Order::query()->whereIn('status', self::REVENUE_STATUSES)->where('created_at', '>=', $from)
        );
    }

    /** @return array{GHS: int, USD: int} */
    private function revenueBetween(\Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): array
    {
        return $this->sumByCurrency(
            Order::query()->whereIn('status', self::REVENUE_STATUSES)->whereBetween('created_at', [$from, $to])
        );
    }

    /** @return array{GHS: int, USD: int} */
    private function sumByCurrency($query): array
    {
        $totals = $query->selectRaw('currency, SUM(total) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        return [
            'GHS' => (int) ($totals['GHS'] ?? 0),
            'USD' => (int) ($totals['USD'] ?? 0),
        ];
    }

    /** Percentage change, rendered by the admin MetricCard as `+12.50%`. */
    private function percentageChange(int|float $from, int|float $to): float
    {
        if ((float) $from === 0.0) {
            // No baseline to compare against. Reporting "+100%" off a zero
            // base is the kind of number that gets quoted in a meeting.
            return 0.0;
        }

        return round((($to - $from) / $from) * 100, 2);
    }

    /**
     * Year-over-year series for the dashboard charts.
     *
     * Revenue and orders are computed from the orders table. The three traffic
     * series the admin app also asks for (`trafficBySource`, `trafficByDevice`,
     * `trafficByLocation`) come back **null**, not empty arrays: analytics is
     * README Feature 11 and nothing collects that data yet. An empty array
     * would render as a chart with no traffic, which is a different and false
     * claim from "we are not measuring this".
     */
    public function charts(): JsonResponse
    {
        $thisYear = now()->year;

        return response()->json(['data' => [
            'revenue_this_year' => $this->monthlySeries($thisYear, 'revenue'),
            'revenue_last_year' => $this->monthlySeries($thisYear - 1, 'revenue'),
            'orders_this_year' => $this->monthlySeries($thisYear, 'orders'),
            'orders_last_year' => $this->monthlySeries($thisYear - 1, 'orders'),

            'traffic_by_source' => null,
            'traffic_by_device' => null,
            'traffic_by_location' => null,

            'generated_at' => now(),
        ]]);
    }

    /**
     * Twelve points, one per month, zero-filled — a month with no orders is a
     * real zero on the chart, not a gap the line skips over.
     *
     * Revenue is GHS only. A single line cannot honestly mix two currencies,
     * and converting USD orders to plot them would use a rate that disagrees
     * with each order's own locked `fx_rate_applied`.
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function monthlySeries(int $year, string $metric): array
    {
        $query = Order::query()
            ->whereYear('created_at', $year)
            ->when($metric === 'revenue', fn ($q) => $q->whereIn('status', self::REVENUE_STATUSES)->where('currency', 'GHS'));

        $column = $metric === 'revenue' ? 'SUM(total)' : 'COUNT(*)';

        $rows = $query
            ->selectRaw($this->monthExpression().' as month, '.$column.' as value')
            ->groupBy('month')
            ->pluck('value', 'month');

        $series = [];

        for ($month = 1; $month <= 12; $month++) {
            $series[] = [
                'label' => \Carbon\Carbon::create($year, $month, 1)->format('M'),
                'value' => (int) ($rows[$month] ?? $rows[(string) $month] ?? $rows[sprintf('%02d', $month)] ?? 0),
            ];
        }

        return $series;
    }

    /** Month extraction differs by driver — Postgres is EXTRACT, SQLite is strftime. */
    private function monthExpression(): string
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql'
            ? 'EXTRACT(MONTH FROM created_at)'
            : "CAST(strftime('%m', created_at) AS INTEGER)";
    }
}
