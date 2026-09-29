<?php

use Tests\TestCase;

uses(TestCase::class);

it('runs on Philippine time unless APP_TIMEZONE overrides it', function () {
    expect(config('app.timezone'))->toBe(env('APP_TIMEZONE', 'Asia/Manila'))
        ->and(date_default_timezone_get())->toBe(config('app.timezone'));
});

it('documents APP_TIMEZONE in .env.example', function () {
    expect(file_get_contents(base_path('.env.example')))->toContain('APP_TIMEZONE=Asia/Manila');
});
