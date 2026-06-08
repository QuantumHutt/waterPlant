<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('customers.view'), 403);

        return Inertia::render('Customers/Index', [
            'customers' => $this->service->list((int) $request->integer('per_page', 15), $request->only(['search', 'type'])),
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    public function ledger(Request $request, Customer $customer): Response
    {
        abort_unless($request->user()->can('customers.view'), 403);

        return Inertia::render('Customers/Index', [
            'customers' => $this->service->list(15, $request->only(['search', 'type'])),
            'filters' => $request->only(['search', 'type']),
            'ledgerCustomer' => $customer,
            'ledger' => $customer->ledgerEntries()->latest('entry_date')->latest('id')->limit(100)->get(),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = $this->service->create($request->validated());

        return back()
            ->with('success', 'Customer created.')
            ->with('new_customer_id', $customer->id);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->service->update($customer, $request->validated());

        return back()->with('success', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->user()->can('customers.delete'), 403);
        $this->service->delete($customer);

        return back()->with('success', 'Customer deleted.');
    }

    public function payment(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->user()->can('customers.update'), 403);
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->service->recordPayment($customer, (float) $data['amount'], $data['note'] ?? null);

        return back()->with('success', 'Payment recorded.');
    }
}
