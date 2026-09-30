<?php

namespace App\Http\Controllers\Staff;

use App\Enums\JobStatus;
use App\Exceptions\InvalidJobTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AcceptJobRequest;
use App\Http\Requests\Staff\RejectJobRequest;
use App\Models\PrintJob;
use App\Services\JobStatusService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JobActionController extends Controller
{
    public function __construct(private JobStatusService $statuses) {}

    public function accept(AcceptJobRequest $request, PrintJob $job): RedirectResponse
    {
        return $this->act($job, 'accepted', fn () => $this->statuses->accept(
            $job, $request->user(), (int) $request->validated('page_count'), (string) $request->validated('final_total'),
        ));
    }

    public function reject(RejectJobRequest $request, PrintJob $job): RedirectResponse
    {
        return $this->act($job, 'rejected', fn () => $this->statuses->reject($job, $request->user(), $request->validated('reason')));
    }

    public function printing(Request $request, PrintJob $job): RedirectResponse
    {
        return $this->act($job, 'marked printing', fn () => $this->statuses->transition($job, JobStatus::Printing, $request->user()));
    }

    public function ready(Request $request, PrintJob $job): RedirectResponse
    {
        return $this->act($job, 'marked ready for pickup', fn () => $this->statuses->transition($job, JobStatus::Ready, $request->user()));
    }

    private function act(PrintJob $job, string $done, Closure $action): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidJobTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.queue', $job->location_id === request()->attributes->get('station')?->location_id ? [] : ['location' => $job->location_id])
            ->with('success', "{$job->claim_code} {$done}.");
    }
}
