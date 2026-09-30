<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use App\Services\PriceListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(private PriceListService $prices) {}

    public function index(): View
    {
        return view('admin.services.index', [
            'services' => Service::orderBy('type')->orderBy('name')->get()->groupBy('type'),
        ]);
    }

    public function create(): View
    {
        return view('admin.services.form', ['service' => new Service(['type' => 'document', 'color_mode' => 'bw', 'is_active' => true])]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $service = $this->prices->save(new Service, $request->validated());

        return redirect()->route('admin.services.index')->with('success', "{$service->name} added to the price list.");
    }

    public function edit(Service $service): View
    {
        return view('admin.services.form', ['service' => $service]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $this->prices->save($service, $request->validated());

        return redirect()->route('admin.services.index')->with('success', "{$service->name} updated.");
    }
}
