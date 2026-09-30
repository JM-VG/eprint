<?php

namespace App\Http\Controllers\Staff;

use App\Enums\SaleSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreDocumentJobRequest;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\JobSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Staff and admins submit their own jobs through the student document form; they carry source staff_job.
 */
class SubmitJobController extends Controller
{
    public function create(AvailabilityService $availability): View
    {
        return view('student.jobs.create', [
            'locations' => collect($availability->locations()),
            'services' => Service::where('type', 'document')->where('is_active', true)->orderBy('name')->get(),
            'action' => route('staff.jobs.store'),
            'heading' => __('Submit Job'),
        ]);
    }

    public function store(StoreDocumentJobRequest $request, JobSubmissionService $jobs): RedirectResponse
    {
        $job = $jobs->submitDocument(
            $request->user(),
            $request->safe()->except('file'),
            $request->file('file'),
            SaleSource::StaffJob,
        );

        return redirect()->route('staff.jobs.create')
            ->with('success', "Job submitted to the queue. Claim code {$job->claim_code}.");
    }
}
