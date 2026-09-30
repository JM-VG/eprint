<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => $this->settings->all(),
            'gcashQrUrl' => $this->settings->gcashQrUrl(),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->safe()->except('gcash_qr'), $request->file('gcash_qr'));

        return redirect()->route('admin.settings.edit')->with('success', 'Settings saved.');
    }
}
