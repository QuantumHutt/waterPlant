<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    public function index(): Response
    {
        return Inertia::render('Dashboard/Index', [
            'metrics' => $this->service->metrics(),
        ]);
    }
}
