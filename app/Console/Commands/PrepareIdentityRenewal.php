<?php

namespace App\Console\Commands;

use App\Models\GovernmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareIdentityRenewal extends Command
{
    protected $signature = 'identity:prepare-renewal {--database= : Explicit local database name}';
    protected $description = 'Prepare only the local academic identity renewal service without changing existing configured fees';

    public function handle(): int
    {
        $connection = DB::connection();
        $test = app()->environment('testing') && $connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:';
        $local = app()->environment('local') && $connection->getDriverName() === 'mysql'
            && in_array($connection->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)
            && ! filled($connection->getConfig('url')) && $connection->getDatabaseName() === 'e_issuance';
        if ((! $test && ! $local) || $this->option('database') !== $connection->getDatabaseName()) {
            $this->error('Explicit verified local database required.');
            return self::FAILURE;
        }
        DB::transaction(function () {
            $service = GovernmentService::where('code', 'identity-renewal')->lockForUpdate()->firstOrFail();
            abort_unless($service->document_type === 'identity' && $service->operation === 'renewal', 422);
            // Amount comes from the project owner's civil_registry_guides identity fee card, not a legal tariff.
            $patch = ['is_active' => true, 'rules_approved' => true];
            if ($service->fee === null && $service->currency === null) {
                $patch += ['fee' => '6500.00', 'currency' => 'YER'];
            }
            if (! $service->renewal_statuses) $patch['renewal_statuses'] = ['active'];
            if (! filled($service->source_reference)) $patch['source_reference'] = 'Local academic renewal configuration; project service guide';
            if ($service->verification_rules === null) $patch['verification_rules'] = ['renewal_validity' => 'any'];
            $service->update($patch);
        });
        $this->info('Local renewal service prepared; existing fees and issuance policy preserved.');
        return self::SUCCESS;
    }
}
