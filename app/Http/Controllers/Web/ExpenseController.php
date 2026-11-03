<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('expenses.view'), 403);

        return Inertia::render('Expenses/Index', [
            'expenses' => $this->service->list((int) $request->integer('per_page', 15)),
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.create'), 403);

        $data = $request->validate([
            'category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_via' => ['required', 'in:cash,bank'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'spent_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->create($data);

        return back()->with('success', 'Expense recorded.');
    }
}
