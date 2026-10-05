<?php

namespace App\Console\Commands;

use App\Repositories\AnnouncementRepository;
use App\Repositories\CommissionPayoutRepository;
use App\Repositories\GcashPaymentRepository;
use App\Repositories\JobLogRepository;
use App\Repositories\LocationRepository;
use App\Repositories\PrintJobRepository;
use App\Repositories\Repository;
use App\Repositories\SaleRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\SettingRepository;
use App\Repositories\StationSessionRepository;
use App\Repositories\UserRepository;
use App\Repositories\WalkinItemRepository;
use Database\Seeders\Firebase\DatabaseSeeder;
use Illuminate\Console\Command;

class FirebaseSeed extends Command
{
    protected $signature = 'firebase:seed {--fresh : Delete all existing Realtime Database data first}';

    protected $description = 'Seed the Firebase Realtime Database with base data';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->warn('Clearing the Realtime Database tables...');

            foreach ($this->repositories() as $repository) {
                $repository->truncate();
            }

            app(SettingRepository::class)->reset();
        }

        $this->call(DatabaseSeeder::class);

        $this->info('Firebase Realtime Database seeded.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, Repository>
     */
    private function repositories(): array
    {
        return [
            app(UserRepository::class),
            app(LocationRepository::class),
            app(ServiceRepository::class),
            app(StationSessionRepository::class),
            app(PrintJobRepository::class),
            app(SaleRepository::class),
            app(WalkinItemRepository::class),
            app(GcashPaymentRepository::class),
            app(CommissionPayoutRepository::class),
            app(JobLogRepository::class),
            app(AnnouncementRepository::class),
        ];
    }
}
