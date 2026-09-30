<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Enums\PaymentStatus;
use App\Enums\SaleSource;
use App\Exceptions\UnreadableDocument;
use App\Models\JobLog;
use App\Models\PrintJob;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class JobSubmissionService
{
    public const DISK = 'local';

    public function __construct(
        private PageCounter $pages,
        private PriceCalculator $prices,
        private ClaimCodeGenerator $claimCodes,
    ) {}

    /**
     * Store the upload privately, count pages, snapshot the price and create a pending job.
     * Any failure removes the stored files.
     *
     * @param  array{location_id: int, service_id: int, copies: int, page_range?: ?string, duplex?: bool, notes?: ?string}  $data
     *
     * @throws ValidationException for unreadable files or page ranges outside the document
     */
    public function submitDocument(User $user, array $data, UploadedFile $file, SaleSource $source = SaleSource::Online): PrintJob
    {
        $service = Service::findOrFail($data['service_id']);
        $fileType = strtolower($file->extension());
        $storedPath = $file->store('uploads', self::DISK);
        $pdfPath = null;

        try {
            $count = $this->countPages($storedPath, $fileType);
            $pdfPath = $count['pdf_path'];
            $pageRange = filled($data['page_range'] ?? null) ? trim($data['page_range']) : null;

            try {
                $estimate = $this->prices->estimate($service, $count['pages'], $pageRange, (int) $data['copies']);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['page_range' => $e->getMessage()]);
            }

            return $this->createPendingJob($user, $file, [
                'location_id' => $data['location_id'],
                'service_id' => $service->id,
                'source' => $source,
                'stored_path' => $storedPath,
                'pdf_path' => $pdfPath,
                'file_type' => $fileType,
                'page_count' => $count['pages'],
                'page_range' => $pageRange,
                'copies' => (int) $data['copies'],
                'duplex' => (bool) ($data['duplex'] ?? false),
                'notes' => $data['notes'] ?? null,
                'unit_price' => $estimate['unit_price'],
                'estimated_total' => $estimate['total'],
            ]);
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete(array_filter([$storedPath, $pdfPath]));

            throw $e;
        }
    }

    /**
     * Photo jobs: one image, priced as sets x unit price.
     *
     * @param  array{location_id: int, service_id: int, sets: int, notes?: ?string}  $data
     */
    public function submitPhoto(User $user, array $data, UploadedFile $file, SaleSource $source = SaleSource::Online): PrintJob
    {
        $service = Service::findOrFail($data['service_id']);
        $storedPath = $file->store('uploads', self::DISK);

        try {
            $estimate = $this->prices->estimate($service, sets: (int) $data['sets']);

            return $this->createPendingJob($user, $file, [
                'location_id' => $data['location_id'],
                'service_id' => $service->id,
                'source' => $source,
                'stored_path' => $storedPath,
                'file_type' => strtolower($file->extension()),
                'page_count' => 1,
                'copies' => 1,
                'sets' => (int) $data['sets'],
                'notes' => $data['notes'] ?? null,
                'unit_price' => $estimate['unit_price'],
                'estimated_total' => $estimate['total'],
            ]);
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($storedPath);

            throw $e;
        }
    }

    /**
     * Create the pending job with its claim code and first job log in one transaction.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createPendingJob(User $user, UploadedFile $file, array $attributes): PrintJob
    {
        return DB::transaction(function () use ($user, $file, $attributes) {
            $job = new PrintJob;
            $job->forceFill($attributes + [
                'claim_code' => $this->claimCodes->generate(),
                'user_id' => $user->id,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'file_size' => $file->getSize(),
                'status' => JobStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
            ])->save();

            JobLog::create([
                'print_job_id' => $job->id,
                'user_id' => $user->id,
                'from_status' => null,
                'to_status' => JobStatus::Pending,
                'note' => 'Submitted',
            ]);

            return $job;
        });
    }

    /**
     * @return array{pages: ?int, pdf_path: ?string} pdf_path relative to the disk
     */
    private function countPages(string $storedPath, string $fileType): array
    {
        try {
            $result = $this->pages->countWithPdf(Storage::disk(self::DISK)->path($storedPath), $fileType);
        } catch (UnreadableDocument $e) {
            throw ValidationException::withMessages(['file' => 'This file could not be read. Please upload a valid PDF, DOCX, JPG or PNG.']);
        }

        return [
            'pages' => $result['pages'],
            'pdf_path' => $result['pdf_path'] ? dirname($storedPath).'/'.basename($result['pdf_path']) : null,
        ];
    }
}
