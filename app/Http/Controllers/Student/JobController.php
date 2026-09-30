<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateJobRequest;
use App\Models\PrintJob;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\JobSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobController extends Controller
{
    public function __construct(private JobSubmissionService $jobs) {}

    public function index(Request $request): View
    {
        return view('student.jobs.index', [
            'jobs' => $request->user()->printJobs()
                ->with(['location', 'service'])
                ->latest()
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function show(PrintJob $job): View
    {
        Gate::authorize('view', $job);

        return view('student.jobs.show', [
            'job' => $job->load(['location', 'service', 'logs' => fn ($q) => $q->oldest()->oldest('id')]),
        ]);
    }

    public function edit(PrintJob $job, AvailabilityService $availability): View
    {
        Gate::authorize('update', $job);

        return view('student.jobs.edit', [
            'job' => $job->load('service'),
            'locations' => collect($availability->locations()),
            'services' => Service::where('type', $job->service->type)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateJobRequest $request, PrintJob $job): RedirectResponse
    {
        $this->jobs->update($job, $request->user(), $request->validated());

        return redirect()->route('student.jobs.show', $job)->with('success', 'Job updated.');
    }

    public function destroy(PrintJob $job): RedirectResponse
    {
        Gate::authorize('delete', $job);

        $this->jobs->delete($job);

        return redirect()->route('student.jobs.index')->with('success', "Job {$job->claim_code} deleted.");
    }
}
