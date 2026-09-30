<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UnreadableDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\PriceEstimateRequest;
use App\Models\Service;
use App\Services\PageCounter;
use App\Services\PriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PriceEstimateController extends Controller
{
    /**
     * Estimate before submit. The file is read from its temporary upload location and never stored.
     * DOCX files are not converted here, so they get no total until staff confirm the page count.
     */
    public function __invoke(PriceEstimateRequest $request, PageCounter $counter, PriceCalculator $prices): JsonResponse
    {
        $service = Service::findOrFail($request->validated('service_id'));
        $file = $request->file('file');
        $pageCount = null;

        if ($file && strtolower($file->extension()) !== 'docx') {
            try {
                $pageCount = $counter->count($file->getRealPath(), $file->extension());
            } catch (UnreadableDocument) {
                throw ValidationException::withMessages(['file' => 'This file could not be read.']);
            }
        }

        try {
            $estimate = $prices->estimate(
                $service,
                pageCount: $pageCount,
                pageRange: $request->validated('page_range'),
                copies: (int) ($request->validated('copies') ?? 1),
                sets: (int) ($request->validated('sets') ?? 1),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['page_range' => $e->getMessage()]);
        }

        return response()->json($estimate + [
            'note' => $estimate['total'] === null ? 'The final price is confirmed at the counter.' : null,
        ]);
    }
}
