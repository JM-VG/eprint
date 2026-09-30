<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Services\JobSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way to reach uploaded job files: owner, staff or admin (PrintJobPolicy::view).
 */
class FileController extends Controller
{
    public function show(Request $request, PrintJob $job): StreamedResponse
    {
        Gate::authorize('view', $job);

        abort_if($job->files_deleted_at !== null, 410, 'This file was removed after the retention period.');

        $converted = $request->query('variant') === 'pdf';
        $path = $converted ? $job->pdf_path : $job->stored_path;
        $disk = Storage::disk(JobSubmissionService::DISK);

        abort_unless($path && $disk->exists($path), 404);

        $name = $converted
            ? pathinfo($job->original_name, PATHINFO_FILENAME).'.pdf'
            : $job->original_name;

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ];

        return $request->boolean('download')
            ? $disk->download($path, $name, $headers)
            : $disk->response($path, $name, $headers);
    }
}
