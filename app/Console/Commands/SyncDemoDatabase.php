<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncDemoDatabase extends Command
{
    protected $signature = 'demo:sync-db';

    protected $description = 'Apply pending migrations and restore the shared local demo registry';

    public function handle(): int
    {
        $name = config('database.default');
        $connection = config('database.connections.'.$name, []);
        $driver = $connection['driver'] ?? null;
        $local = $driver === 'sqlite' || ($driver === 'mysql'
            && in_array($connection['host'] ?? null, ['localhost', '127.0.0.1', '::1'], true));

        if (! app()->environment('local') || ! $local || filled($connection['url'] ?? null)) {
            $this->error('Configure APP_ENV=local and a local MySQL or SQLite connection first.');

            return self::FAILURE;
        }

        return $this->call('migrate');
    }
}
