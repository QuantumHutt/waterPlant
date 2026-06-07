<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryItem;
use App\Models\DeliveryRoute;
use App\Models\User;
use App\Support\Enums\RoleName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('delivery.view'), 403);

        $query = DeliveryAssignment::with(['route', 'driver', 'items.customer'])->latest();
        // Delivery staff see only their own assignments.
        if ($request->user()->hasRole(RoleName::DeliveryStaff->value)) {
            $query->where('driver_id', $request->user()->id);
        }

        return Inertia::render('Delivery/Index', [
            'assignments' => $query->limit(50)->get(),
            'routes' => DeliveryRoute::where('is_active', true)->get(),
            'drivers' => User::role(RoleName::DeliveryStaff->value)->get(['id', 'name']),
            'customers' => Customer::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function storeRoute(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('delivery.create'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'area' => ['nullable', 'string', 'max:255']]);
        DeliveryRoute::create($data);

        return back()->with('success', 'Route added.');
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('delivery.create'), 403);
        $data = $request->validate([
            'route_id' => ['nullable', 'exists:delivery_routes,id'],
            'driver_id' => ['required', 'exists:users,id'],
            'vehicle' => ['nullable', 'string', 'max:255'],
            'assigned_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.customer_id' => ['required', 'exists:customers,id'],
            'items.*.gallons_out' => ['nullable', 'integer', 'min:0'],
        ]);

        $assignment = DeliveryAssignment::create([
            'reference' => 'DLV-'.now()->format('ymd').'-'.strtoupper(Str::random(4)),
            'route_id' => $data['route_id'] ?? null,
            'driver_id' => $data['driver_id'],
            'vehicle' => $data['vehicle'] ?? null,
            'assigned_date' => $data['assigned_date'] ?? now()->toDateString(),
            'status' => 'assigned',
        ]);
        foreach ($data['items'] as $item) {
            $assignment->items()->create([
                'customer_id' => $item['customer_id'],
                'gallons_out' => $item['gallons_out'] ?? 0,
                'status' => 'pending',
            ]);
        }

        return back()->with('success', 'Delivery assigned.');
    }

    public function confirm(Request $request, DeliveryItem $item): RedirectResponse
    {
        abort_unless($request->user()->can('delivery.confirm'), 403);
        $data = $request->validate(['gallons_in' => ['nullable', 'integer', 'min:0']]);

        $item->update([
            'status' => 'delivered',
            'gallons_in' => $data['gallons_in'] ?? 0,
            'delivered_at' => now(),
        ]);

        // Complete the assignment when all its items are delivered.
        $assignment = $item->delivery_assignment_id ? DeliveryAssignment::find($item->delivery_assignment_id) : null;
        if ($assignment && ! $assignment->items()->where('status', 'pending')->exists()) {
            $assignment->update(['status' => 'completed']);
        }

        return back()->with('success', 'Delivery confirmed.');
    }
}
