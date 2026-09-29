<?php

use App\Models\Service;
use App\Services\PriceCalculator;

beforeEach(function () {
    $this->calc = new PriceCalculator;
});

describe('page ranges', function () {
    it('counts all pages when the range is blank', function (?string $range) {
        expect($this->calc->pagesToPrint($range, 12))->toBe(12);
    })->with([null, '', '   ']);

    it('counts single pages, ranges and mixtures', function (string $range, int $expected) {
        expect($this->calc->pagesToPrint($range, 20))->toBe($expected);
    })->with([
        ['5', 1],
        ['1-3', 3],
        ['1-3,5', 4],
        ['1-3, 5, 8-10', 7],
        [' 2 - 4 ', 3],
        ['7-7', 1],
    ]);

    it('does not double count overlapping ranges', function () {
        expect($this->calc->pagesToPrint('1-5,3-7,5', 20))->toBe(7);
    });

    it('rejects ranges outside the document', function (string $range) {
        expect(fn () => $this->calc->pagesToPrint($range, 10))->toThrow(InvalidArgumentException::class);
    })->with(['0', '11', '9-12', '0-3']);

    it('rejects malformed ranges', function (string $range) {
        expect(fn () => $this->calc->pagesToPrint($range, 10))->toThrow(InvalidArgumentException::class);
    })->with(['a', '1-', '-3', '5-2', '1,,3', '1-2-3', '1;3']);

    it('counts the range without a page count when the total is unknown', function () {
        expect($this->calc->pagesToPrint('1-3,6', null))->toBe(4)
            ->and($this->calc->pagesToPrint('', null))->toBeNull();
    });

    it('caps ranges when the total is unknown so they cannot loop unbounded', function () {
        expect(fn () => $this->calc->pagesToPrint('1-100000000', null))->toThrow(InvalidArgumentException::class)
            ->and($this->calc->pagesToPrint('1-'.PriceCalculator::MAX_PAGES, null))->toBe(PriceCalculator::MAX_PAGES);
    });

    it('validates a range string without a page count', function () {
        expect(PriceCalculator::isValidRange('1-3,5'))->toBeTrue()
            ->and(PriceCalculator::isValidRange(''))->toBeTrue()
            ->and(PriceCalculator::isValidRange('3-1'))->toBeFalse()
            ->and(PriceCalculator::isValidRange('x'))->toBeFalse()
            ->and(PriceCalculator::isValidRange('0-2'))->toBeFalse()
            ->and(PriceCalculator::isValidRange('1-100000000'))->toBeFalse()
            ->and(PriceCalculator::isValidRange('1-'.PriceCalculator::MAX_PAGES))->toBeTrue();
    });

    it('rejects ranges with too many segments or characters', function () {
        $manySegments = implode(',', array_fill(0, PriceCalculator::MAX_SEGMENTS + 1, '1-5000'));

        expect(fn () => $this->calc->pagesToPrint($manySegments, null))->toThrow(InvalidArgumentException::class)
            ->and(PriceCalculator::isValidRange($manySegments))->toBeFalse()
            ->and(PriceCalculator::isValidRange(str_repeat(' ', 150).'1-2'.str_repeat(' ', 60)))->toBeFalse();
    });

    it('counts large overlapping ranges quickly without walking every page', function () {
        $range = implode(',', array_fill(0, 28, '1-5000')); // 195 chars, within MAX_RANGE_LENGTH
        $start = microtime(true);

        expect($this->calc->pagesToPrint($range, 5000))->toBe(5000)
            ->and(microtime(true) - $start)->toBeLessThan(0.05);
    });

    it('merges adjacent and nested intervals correctly', function () {
        expect($this->calc->pagesToPrint('1-3,4-6', 10))->toBe(6)
            ->and($this->calc->pagesToPrint('2-9,3-4', 10))->toBe(8)
            ->and($this->calc->pagesToPrint('8-10,1-2', 10))->toBe(5);
    });
});

describe('totals', function () {
    it('prices documents as pages x copies x unit price', function () {
        expect($this->calc->documentTotal(10, 3, '2.50'))->toBe('75.00')
            ->and($this->calc->documentTotal(1, 1, '0.00'))->toBe('0.00');
    });

    it('keeps money exact without float drift', function () {
        expect($this->calc->documentTotal(3, 1, '0.10'))->toBe('0.30')
            ->and($this->calc->documentTotal(7, 13, '1.15'))->toBe('104.65');
    });

    it('prices photos as sets x unit price', function () {
        expect($this->calc->photoTotal(3, '45.00'))->toBe('135.00');
    });

    it('rejects zero or negative quantities', function () {
        expect(fn () => $this->calc->documentTotal(0, 1, '1.00'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $this->calc->documentTotal(1, 0, '1.00'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $this->calc->photoTotal(0, '1.00'))->toThrow(InvalidArgumentException::class);
    });
});

describe('estimate', function () {
    it('estimates a document job from its service, range and copies', function () {
        $service = new Service(['type' => 'document', 'unit' => 'page', 'price' => '3.00']);

        expect($this->calc->estimate($service, pageCount: 20, pageRange: '1-5', copies: 2))
            ->toBe(['pages' => 5, 'unit_price' => '3.00', 'total' => '30.00']);
    });

    it('estimates a photo job from sets', function () {
        $service = new Service(['type' => 'photo', 'unit' => 'set', 'price' => '60.00']);

        expect($this->calc->estimate($service, sets: 2))
            ->toBe(['pages' => null, 'unit_price' => '60.00', 'total' => '120.00']);
    });

    it('returns no total while the page count is unknown, even with a range', function (?string $range) {
        $service = new Service(['type' => 'document', 'unit' => 'page', 'price' => '3.00']);

        expect($this->calc->estimate($service, pageCount: null, pageRange: $range, copies: 2))
            ->toBe(['pages' => null, 'unit_price' => '3.00', 'total' => null]);
    })->with([null, '1-3']);
});
