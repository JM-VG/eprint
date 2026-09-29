<?php

use App\Exceptions\UnreadableDocument;
use App\Services\DocxConverter;
use App\Services\PageCounter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function docFixture(string $name): string
{
    return base_path('tests/Fixtures/'.$name);
}

describe('PageCounter', function () {
    it('counts pages of a PDF', function () {
        $counter = app(PageCounter::class);

        expect($counter->count(docFixture('three-pages.pdf'), 'pdf'))->toBe(3)
            ->and($counter->count(docFixture('one-page.pdf'), 'pdf'))->toBe(1);
    });

    it('counts images as one page', function (string $type) {
        expect(app(PageCounter::class)->count(docFixture('one-page.pdf'), $type))->toBe(1);
    })->with(['jpg', 'jpeg', 'png']);

    it('throws for an unreadable PDF', function () {
        expect(fn () => app(PageCounter::class)->count(docFixture('corrupt.pdf'), 'pdf'))
            ->toThrow(UnreadableDocument::class);
    });

    it('leaves DOCX page count empty when conversion is off', function () {
        config(['eprint.docx_conversion' => false]);
        Process::fake();

        expect(app(PageCounter::class)->count(docFixture('sample.docx'), 'docx'))->toBeNull();
        Process::assertNothingRan();
    });

    it('converts DOCX to PDF and counts it when conversion is on', function () {
        config(['eprint.docx_conversion' => true]);
        $converter = Mockery::mock(DocxConverter::class);
        $converter->shouldReceive('toPdf')->once()->with(docFixture('sample.docx'))->andReturn(docFixture('three-pages.pdf'));
        app()->instance(DocxConverter::class, $converter);

        $result = app(PageCounter::class)->countWithPdf(docFixture('sample.docx'), 'docx');

        expect($result)->toBe(['pages' => 3, 'pdf_path' => docFixture('three-pages.pdf')]);
    });

    it('leaves DOCX page count empty when conversion fails', function () {
        config(['eprint.docx_conversion' => true]);
        $converter = Mockery::mock(DocxConverter::class);
        $converter->shouldReceive('toPdf')->andReturn(null);
        app()->instance(DocxConverter::class, $converter);

        expect(app(PageCounter::class)->countWithPdf(docFixture('sample.docx'), 'docx'))
            ->toBe(['pages' => null, 'pdf_path' => null]);
    });

    it('rejects unsupported file types', function () {
        expect(fn () => app(PageCounter::class)->count(docFixture('one-page.pdf'), 'exe'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('DocxConverter', function () {
    beforeEach(function () {
        $this->workDir = storage_path('framework/testing/docx');
        File::ensureDirectoryExists($this->workDir);
        File::copy(docFixture('sample.docx'), $this->workDir.'/sample.docx');
    });

    afterEach(function () {
        File::deleteDirectory($this->workDir);
    });

    it('runs soffice headless and returns the PDF next to the DOCX', function () {
        config(['eprint.soffice_path' => '/opt/libreoffice/soffice']);
        Process::fake(function () {
            File::put($this->workDir.'/sample.pdf', '%PDF-1.4');

            return Process::result(exitCode: 0);
        });

        $pdf = app(DocxConverter::class)->toPdf($this->workDir.'/sample.docx');

        expect($pdf)->toBe($this->workDir.'/sample.pdf');
        Process::assertRan(fn ($process) => $process->command[0] === '/opt/libreoffice/soffice'
            && in_array('--headless', $process->command, true)
            && in_array('pdf', $process->command, true)
            && $process->timeout === DocxConverter::TIMEOUT_SECONDS);
    });

    it('returns null when soffice fails', function () {
        Process::fake(fn () => Process::result(exitCode: 1, errorOutput: 'boom'));

        expect(app(DocxConverter::class)->toPdf($this->workDir.'/sample.docx'))->toBeNull();
    });

    it('returns null when soffice exits cleanly but writes no PDF', function () {
        Process::fake(fn () => Process::result(exitCode: 0));

        expect(app(DocxConverter::class)->toPdf($this->workDir.'/sample.docx'))->toBeNull();
    });

    it('is disabled by default in config', function () {
        expect(config('eprint.docx_conversion'))->toBeFalse();
    });
});
