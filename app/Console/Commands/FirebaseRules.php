<?php

namespace App\Console\Commands;

use App\Firebase\ServiceAccountTokenProvider;
use App\Realtime\PublicProjections;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FirebaseRules extends Command
{
    protected $signature = 'firebase:rules
        {--deploy : Replace the Realtime Database security rules with database.rules.json}
        {--publish : Write the public summaries (/public/...) the browser subscribes to}';

    protected $description = 'Show or deploy the Realtime Database security rules (only /public is readable by browsers)';

    public function handle(): int
    {
        $rules = (string) file_get_contents(base_path('database.rules.json'));

        if (json_decode($rules) === null) {
            $this->error('database.rules.json is not valid JSON.');

            return self::FAILURE;
        }

        $this->line($rules);

        if ($this->option('deploy')) {
            Http::withToken(app(ServiceAccountTokenProvider::class)->token())
                ->withBody($rules, 'application/json')
                ->put(rtrim((string) config('firebase.database_url'), '/').'/.settings/rules.json')
                ->throw();

            $this->info('Security rules deployed.');
        }

        if ($this->option('publish')) {
            app(PublicProjections::class)->publishAll();
            $this->info('Public summaries published.');
        }

        return self::SUCCESS;
    }
}
