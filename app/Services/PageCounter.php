<?php

namespace App\Services;

use App\Exceptions\UnreadableDocument;
use InvalidArgumentException;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Page count per file type: PDF via smalot/pdfparser, images count as 1,
 * DOCX via LibreOffice conversion when enabled (otherwise null; staff enter it on Accept).
 */
class PageCounter
{
    public function __construct(private DocxConverter $converter) {}

    /**
     * @throws UnreadableDocument when a PDF cannot be parsed
     */
    public function count(string $path, string $fileType): ?int
    {
        return $this->countWithPdf($path, $fileType)['pages'];
    }

    /**
     * Page count plus the converted PDF path for DOCX files (null for other types).
     *
     * @return array{pages: ?int, pdf_path: ?string}
     *
     * @throws UnreadableDocument when a PDF cannot be parsed
     */
    public function countWithPdf(string $path, string $fileType): array
    {
        return match (strtolower($fileType)) {
            'pdf' => ['pages' => $this->countPdf($path), 'pdf_path' => null],
            'jpg', 'jpeg', 'png' => ['pages' => 1, 'pdf_path' => null],
            'docx' => $this->countDocx($path),
            default => throw new InvalidArgumentException("Unsupported file type [{$fileType}]."),
        };
    }

    private function countPdf(string $path): int
    {
        try {
            $pages = count((new Parser)->parseFile($path)->getPages());
        } catch (Throwable $e) {
            throw new UnreadableDocument('The PDF could not be read.', previous: $e);
        }

        if ($pages < 1) {
            throw new UnreadableDocument('The PDF has no pages.');
        }

        return $pages;
    }

    /**
     * @return array{pages: ?int, pdf_path: ?string}
     */
    private function countDocx(string $path): array
    {
        if (! config('eprint.docx_conversion')) {
            return ['pages' => null, 'pdf_path' => null];
        }

        $pdf = $this->converter->toPdf($path);

        if (! $pdf) {
            return ['pages' => null, 'pdf_path' => null];
        }

        try {
            return ['pages' => $this->countPdf($pdf), 'pdf_path' => $pdf];
        } catch (UnreadableDocument) {
            return ['pages' => null, 'pdf_path' => null];
        }
    }
}
