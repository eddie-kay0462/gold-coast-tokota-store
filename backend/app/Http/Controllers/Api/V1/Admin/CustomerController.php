<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminCustomerResource;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

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
        $customers = Customer::query()
            ->withCount('orders')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.mb_strtolower(trim((string) $request->string('q'))).'%';

                // LOWER(...) LIKE, not ILIKE — production is Postgres and the
                // test suite is SQLite. See the same note on admin orders.
                $query->where(fn ($scoped) => $scoped
                    ->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]));
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return AdminCustomerResource::collection($customers);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->loadCount('orders');

        return response()->json([
            'data' => new AdminCustomerResource($customer),
            // Their order history alongside the record — it is the reason
            // anyone opens a customer in the first place.
            'orders' => AdminOrderResource::collection(
                Order::query()->with('items')->where('customer_id', $customer->id)->latest()->get()
            ),
        ]);
    }
}
