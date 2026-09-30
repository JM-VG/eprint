<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\WalkinSaleRequest;
use App\Models\Service;
use App\Models\StationSession;
use App\Services\CommissionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WalkinSaleController extends Controller
{
    public function create(): View
    {
        return view('staff.walkin', [
            'services' => Service::where('is_active', true)->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function store(WalkinSaleRequest $request, CommissionService $commissions): RedirectResponse
    {
        /** @var StationSession $station */
        $station = $request->attributes->get('station');

        try {
            $sale = $commissions->recordWalkin(
                $request->user(),
                $station->location_id,
                array_values($request->validated('items')),
                PaymentMethod::from($request->validated('payment_method')),
                $request->validated('reference_no'),
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['reference_no' => 'This GCash reference number was already used.']);
        }

        return redirect()->route('staff.walkin.create')->with('success', "Walk-in sale recorded: ₱{$sale->amount}.");
    }
}
