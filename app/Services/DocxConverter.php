<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Converts DOCX to PDF with LibreOffice (soffice --headless) so the page count can be read.
 * Only used when EPRINT_DOCX_CONVERSION=true.
 */
class DocxConverter
{
    public const TIMEOUT_SECONDS = 60;

    /**
     * Returns the absolute path of the PDF written next to the DOCX, or null on failure.
     */
    public function toPdf(string $docxPath): ?string
    {
        $outDir = dirname($docxPath);
        $pdfPath = $outDir.'/'.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';

        $result = Process::timeout(self::TIMEOUT_SECONDS)->run([
            config('eprint.soffice_path'),
            '--headless',
            '--norestore',
            '--convert-to',
            'pdf',
            '--outdir',
            $outDir,
            $docxPath,
        ]);

        if ($result->failed() || ! is_file($pdfPath)) {
            Log::warning('DOCX to PDF conversion failed', [
                'file' => basename($docxPath),
                'exit_code' => $result->exitCode(),
                'error' => $result->errorOutput(),
            ]);

            return null;
        }

        return $pdfPath;
    }
}
