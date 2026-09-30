<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreDocumentJobRequest;
use App\Models\Location;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\JobSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DocumentJobController extends Controller
{
    public function create(AvailabilityService $availability): View
    {
        return view('student.jobs.create', [
            'locations' => collect($availability->locations()),
            'services' => Service::where('type', 'document')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreDocumentJobRequest $request, JobSubmissionService $jobs, AvailabilityService $availability): RedirectResponse
    {
        $job = $jobs->submitDocument(
            $request->user(),
            $request->safe()->except('file'),
            $request->file('file'),
        );

        $location = Location::findOrFail($job->location_id);
        $redirect = redirect()->route('student.jobs.show', $job)
            ->with('success', "Job submitted. Your claim code is {$job->claim_code}.");

        if (! $availability->isOpen($location)) {
            $redirect->with('warning', "{$location->name} is closed. Your job waits in the queue until staff open the station.");
        }

        return $redirect;
    }
}
