<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminCustomerResource;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customers — read-only, Admin and Staff.
 *
 * No write endpoints: a customer's name, email and currency are theirs to
 * change from their own account, and an admin quietly editing them is a support
 * problem rather than a feature. Deletion is a data-protection question that
 * needs a policy, not a button.
 */
class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $customers = $this->withSummary(Customer::query())
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.mb_strtolower(trim((string) $request->string('q'))).'%';

                // LOWER(...) LIKE, not ILIKE — production is Postgres and the
                // test suite is SQLite. See the same note on admin orders.
                $query->where(fn ($scoped) => $scoped
                    ->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]));
            })
            ->latest()
            ->paginate(max(1, min($request->integer('per_page', 50), 500)))
            ->withQueryString();

        return AdminCustomerResource::collection($customers);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer = $this->withSummary(Customer::query())->findOrFail($customer->id);

        return response()->json([
            'data' => new AdminCustomerResource($customer),
            // Their order history alongside the record — it is the reason
            // anyone opens a customer in the first place.
            'orders' => AdminOrderResource::collection(
                Order::query()->with('items')->where('customer_id', $customer->id)->latest()->get()
            ),
        ]);
    }

    /**
     * What the list and the record show about each customer: how many orders,
     * what they've spent, when they last ordered, and where the latest one went.
     *
     * Spend counts only orders in `Order::REVENUE_STATUSES`, and is summed per
     * currency: adding cedis and dollars together would need a rate, and each
     * USD order already locked its own.
     */
    private function withSummary(Builder $query): Builder
    {
        $taken = fn (string $currency) => fn ($orders) => $orders
            ->whereIn('status', Order::REVENUE_STATUSES)
            ->where('currency', $currency);

        return $query
            ->withCount('orders')
            ->withSum(['orders as lifetime_ghs' => $taken('GHS')], 'total')
            ->withSum(['orders as lifetime_usd' => $taken('USD')], 'total')
            ->withMax('orders as last_order_at', 'created_at')
            ->with('latestOrder:orders.id,orders.customer_id,orders.shipping_address');
    }

    /** Customer accounts as CSV, newest first: who they are and how much they've ordered. */
    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['first_name', 'last_name', 'email', 'phone', 'preferred_currency', 'orders', 'joined_at']);
            Customer::query()->withCount('orders')->latest()->chunk(500, function ($customers) use ($out) {
                foreach ($customers as $c) {
                    fputcsv($out, [$c->first_name, $c->last_name, $c->email, $c->phone, $c->preferred_currency, $c->orders_count, $c->created_at?->toIso8601String()]);
                }
            });
            fclose($out);
        }, 'customers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
