<?php

it('has a project README with setup, commands and tests', function () {
    $readme = file_get_contents(dirname(__DIR__, 2).'/README.md');

    expect($readme)->toContain('# E-Print')
        ->toContain('php artisan migrate --seed')
        ->toContain('stations:close-idle')
        ->toContain('files:cleanup')
        ->toContain('sales:import-legacy')
        ->toContain('php artisan test')
        ->not->toContain('laravel-logolockup');
});

it('has a deploy guide with the server checklist, cron line, php.ini limits and LibreOffice note', function () {
    $deploy = file_get_contents(dirname(__DIR__, 2).'/DEPLOY.md');

    expect($deploy)->toContain('## Server checklist')
        ->toContain('* * * * * cd /var/www/eprint && php artisan schedule:run')
        ->toContain('upload_max_filesize')
        ->toContain('post_max_size')
        ->toContain('LibreOffice')
        ->toContain('EPRINT_DOCX_CONVERSION')
        ->toContain('APP_ENV=production');
});

it('documents every artisan command the app schedules', function () {
    $console = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');
    $readme = file_get_contents(dirname(__DIR__, 2).'/README.md');

    preg_match_all("/Schedule::command\\('([^']+)'\\)/", $console, $matches);

    expect($matches[1])->not->toBeEmpty();
    foreach ($matches[1] as $command) {
        expect($readme)->toContain($command);
    }
});
