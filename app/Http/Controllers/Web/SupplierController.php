<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\SupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(private readonly SupplierService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('suppliers.view'), 403);

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $this->service->list((int) $request->integer('per_page', 15), $request->only(['search'])),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return back()->with('success', 'Supplier created.');
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->service->update($supplier, $request->validated());

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()->can('suppliers.delete'), 403);
        $this->service->delete($supplier);

        return back()->with('success', 'Supplier deleted.');
    }

    public function payment(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()->can('suppliers.update'), 403);
        // Never pay more than we owe this supplier.
        $owed = round(max(0.0, (float) $supplier->current_balance), 2);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.$owed],
            'method' => ['nullable', 'in:cash,bank,cheque'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['amount.max' => 'Supplier ka baqaya sirf Rs :max hai — is se zyada nahi de sakte.']);
        $this->service->recordPayment($supplier, (float) $data['amount'], $data['note'] ?? null, $data['method'] ?? 'cash');

        return back()->with('success', '✓ Paid '.\App\Support\Money::format((float) $data['amount'])." to {$supplier->name}");
    }
}
