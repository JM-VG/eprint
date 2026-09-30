<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PayoutRequest;
use App\Models\CommissionPayout;
use App\Models\User;
use App\Services\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PayoutController extends Controller
{
    public function __construct(private PayoutService $payouts) {}

    /**
     * Payout history plus the form; with staff and a date range filled in, shows the unpaid preview.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $staffMember = isset($filters['user_id'])
            ? User::whereIn('role', [Role::Staff, Role::Admin])->find($filters['user_id'])
            : null;

        $preview = $staffMember && isset($filters['from'], $filters['to'])
            ? $this->payouts->preview($staffMember, $filters['from'], $filters['to'])
            : null;

        return view('admin.payouts.index', [
            'staff' => User::whereIn('role', [Role::Staff, Role::Admin])->orderBy('last_name')->get(),
            'filters' => $filters,
            'preview' => $preview,
            'payouts' => CommissionPayout::with(['user', 'payer'])->latest('paid_at')->latest('id')->paginate(25),
        ]);
    }

    public function store(PayoutRequest $request): RedirectResponse
    {
        $staff = User::findOrFail($request->validated('user_id'));

        try {
            $payout = $this->payouts->create(
                $staff,
                $request->user(),
                $request->validated('from'),
                $request->validated('to'),
                $request->validated('notes'),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payouts.show', $payout)
            ->with('success', "Paid ₱{$payout->amount} commission to {$staff->name}.");
    }

    public function show(CommissionPayout $payout): View
    {
        return view('admin.payouts.show', [
            'payout' => $payout->load(['user', 'payer', 'sales' => fn ($q) => $q->with(['location', 'printJob'])->oldest()]),
        ]);
    }
}
