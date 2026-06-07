<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerGallonBalance;
use App\Services\GallonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GallonController extends Controller
{
    public function __construct(private readonly GallonService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('gallons.view'), 403);

        return Inertia::render('Gallons/Index', [
            'transactions' => $this->service->list((int) $request->integer('per_page', 20)),
            'balances' => CustomerGallonBalance::with('customer')->where('held_qty', '>', 0)->get(),
            'customers' => Customer::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('gallons.manage'), 403);

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'type' => ['required', Rule::in(['issue', 'return', 'deposit', 'deposit_refund', 'missing', 'damaged'])],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->record($data);

        return back()->with('success', 'Gallon transaction recorded.');
    }
}
