<?php

namespace App\Services;

use App\Models\Service;
use InvalidArgumentException;

/**
 * Document total = pages printed x copies x unit price. Photo total = sets x unit price.
 * Money is handled as decimal strings with bcmath so totals are exact.
 */
class PriceCalculator
{
    /**
     * Upper bound for any page number, so a range can never loop unbounded.
     */
    public const MAX_PAGES = 5000;

    /**
     * Limits on the range string itself.
     */
    public const MAX_SEGMENTS = 50;

    public const MAX_RANGE_LENGTH = 200;

    private const RANGE_PATTERN = '/^\s*\d+\s*(-\s*\d+\s*)?(,\s*\d+\s*(-\s*\d+\s*)?)*$/';

    /**
     * Pages that will be printed for a range like "1-3, 5, 8-10". A blank range means all pages.
     * With an unknown total (DOCX before staff enter it) only an explicit range can be counted.
     *
     * @throws InvalidArgumentException for malformed ranges or pages outside the document
     */
    public function pagesToPrint(?string $range, ?int $totalPages): ?int
    {
        if (blank($range)) {
            return $totalPages;
        }

        $segments = $this->parse($range);

        foreach ($segments as [$start, $end]) {
            if ($end > ($totalPages ?? self::MAX_PAGES)) {
                throw new InvalidArgumentException("Page range {$start}-{$end} is outside the document.");
            }
        }

        return $this->countUnion($segments);
    }

    public static function isValidRange(?string $range): bool
    {
        if (blank($range)) {
            return true;
        }

        try {
            return (new self)->pagesToPrint($range, null) !== null;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function documentTotal(int $pages, int $copies, string $unitPrice): string
    {
        if ($pages < 1 || $copies < 1) {
            throw new InvalidArgumentException('Pages and copies must be at least 1.');
        }

        return bcmul(bcmul((string) $pages, (string) $copies, 0), $unitPrice, 2);
    }

    public function photoTotal(int $sets, string $unitPrice): string
    {
        if ($sets < 1) {
            throw new InvalidArgumentException('Sets must be at least 1.');
        }

        return bcmul((string) $sets, $unitPrice, 2);
    }

    /**
     * Price snapshot for a job: pages to print (documents), unit price and total.
     * The total is null while a document's page count is unknown.
     *
     * @return array{pages: ?int, unit_price: string, total: ?string}
     */
    public function estimate(Service $service, ?int $pageCount = null, ?string $pageRange = null, int $copies = 1, int $sets = 1): array
    {
        $unitPrice = number_format((float) $service->price, 2, '.', '');

        if ($service->type === 'photo') {
            return ['pages' => null, 'unit_price' => $unitPrice, 'total' => $this->photoTotal($sets, $unitPrice)];
        }

        // Without a known page count (DOCX before staff enter it) there is no total yet.
        if ($pageCount === null) {
            return ['pages' => null, 'unit_price' => $unitPrice, 'total' => null];
        }

        $pages = $this->pagesToPrint($pageRange, $pageCount);

        return [
            'pages' => $pages,
            'unit_price' => $unitPrice,
            'total' => $this->documentTotal($pages, $copies, $unitPrice),
        ];
    }

    /**
     * Number of distinct pages covered by the segments, by merging sorted intervals.
     *
     * @param  list<array{0: int, 1: int}>  $segments
     */
    private function countUnion(array $segments): int
    {
        usort($segments, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $count = 0;
        $coveredTo = 0;
        foreach ($segments as [$start, $end]) {
            if ($end <= $coveredTo) {
                continue;
            }

            $count += $end - max($start, $coveredTo + 1) + 1;
            $coveredTo = $end;
        }

        return $count;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function parse(string $range): array
    {
        if (strlen($range) > self::MAX_RANGE_LENGTH || substr_count($range, ',') >= self::MAX_SEGMENTS) {
            throw new InvalidArgumentException('Page range is too long.');
        }

        if (! preg_match(self::RANGE_PATTERN, $range)) {
            throw new InvalidArgumentException("Page range \"{$range}\" is not valid. Use a format like 1-3, 5.");
        }

        return array_map(function (string $part) {
            $bounds = array_map('intval', array_map('trim', explode('-', $part)));
            [$start, $end] = [$bounds[0], $bounds[1] ?? $bounds[0]];

            if ($start < 1) {
                throw new InvalidArgumentException('Pages start at 1.');
            }

            if ($end < $start) {
                throw new InvalidArgumentException("Page range {$start}-{$end} goes backwards.");
            }

            return [$start, $end];
        }, explode(',', $range));
    }
}
