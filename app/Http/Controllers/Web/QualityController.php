<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceSchedule;
use App\Models\ProductionBatch;
use App\Models\WaterQualityTest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QualityController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('quality.view'), 403);

        return Inertia::render('Quality/Index', [
            'tests' => WaterQualityTest::with('batch')->latest('tested_at')->limit(50)->get(),
            'schedules' => MaintenanceSchedule::orderBy('next_due_at')->get(),
            'batches' => ProductionBatch::latest()->limit(50)->get(['id', 'batch_number']),
        ]);
    }

    public function storeTest(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('quality.create'), 403);
        $data = $request->validate([
            'production_batch_id' => ['nullable', 'exists:production_batches,id'],
            'tds_value' => ['nullable', 'numeric', 'min:0'],
            'ph_value' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'temperature' => ['nullable', 'numeric'],
            'passed' => ['boolean'],
            'tested_at' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);
        $data['tested_by'] = $request->user()->id;
        $data['tested_at'] = $data['tested_at'] ?? now()->toDateString();
        WaterQualityTest::create($data);

        return back()->with('success', 'Water test recorded.');
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('quality.create'), 403);
        $data = $request->validate([
            'asset' => ['required', 'in:filter,ro_membrane,uv,pump'],
            'title' => ['required', 'string', 'max:255'],
            'frequency_days' => ['required', 'integer', 'min:1'],
        ]);
        $data['next_due_at'] = now()->addDays((int) $data['frequency_days'])->toDateString();
        MaintenanceSchedule::create($data);

        return back()->with('success', 'Maintenance schedule added.');
    }

    public function logMaintenance(Request $request, MaintenanceSchedule $schedule): RedirectResponse
    {
        abort_unless($request->user()->can('quality.update'), 403);
        $data = $request->validate(['cost' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:255']]);

        $schedule->logs()->create([
            'done_at' => now()->toDateString(),
            'done_by' => $request->user()->id,
            'cost' => $data['cost'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);
        $schedule->update([
            'last_done_at' => now()->toDateString(),
            'next_due_at' => Carbon::now()->addDays((int) $schedule->frequency_days)->toDateString(),
        ]);

        return back()->with('success', 'Maintenance logged.');
    }
}
