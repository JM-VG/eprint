<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PaymentMethod;
use App\Exceptions\InvalidJobTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ClaimRequest;
use App\Models\PrintJob;
use App\Services\CommissionService;
use App\Services\SettingsService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClaimController extends Controller
{
    /**
     * Staff enter or scan the claim code, see the final total, and pick Cash or GCash.
     */
    public function show(Request $request, SettingsService $settings): View
    {
        $code = strtoupper(trim((string) $request->query('code')));
        $job = $code !== '' ? PrintJob::with(['user', 'location', 'service'])->where('claim_code', $code)->first() : null;

        return view('staff.claim', [
            'code' => $code,
            'job' => $job,
            'gcash' => $settings->all(),
            'gcashQrUrl' => $settings->gcashQrUrl(),
        ]);
    }

    public function store(ClaimRequest $request, CommissionService $commissions): RedirectResponse
    {
        $job = PrintJob::where('claim_code', $request->validated('code'))->firstOrFail();
        $method = PaymentMethod::from($request->validated('payment_method'));

        try {
            $sale = $commissions->claim($job, $request->user(), $method, $request->validated('reference_no'));
        } catch (InvalidJobTransition) {
            return back()->with('error', "{$job->claim_code} is {$job->fresh()->status->value}, not ready for pickup.");
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['reference_no' => 'This GCash reference number was already used.']);
        }

        return redirect()->route('staff.claim')
            ->with('success', "{$job->claim_code} claimed. Collected ₱{$sale->amount} by ".($method === PaymentMethod::Gcash ? 'GCash' : 'cash').'.');
    }
}
