<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingsRequest;
use App\Models\TaxRate;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingService $service) {}

    public function index(): Response
    {
        abort_unless(auth()->user()?->can('settings.view'), 403);

        return Inertia::render('Settings/Index', [
            'settings' => $this->service->all(),
            'taxRates' => TaxRate::orderBy('rate')->get(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->service->updateGroups($request->validated());

        return back()->with('success', 'Settings saved.');
    }
}
