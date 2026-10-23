<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class NotifyDocumentExpiry extends Command
{
    protected $signature='documents:notify-expiry';
    protected $description='Create configured, deduplicated citizen document expiry alerts';
    public function handle(\App\Services\Notifications\ExpiryAlerts $alerts): int
    {
        try { $count=$alerts->run(); } catch(\InvalidArgumentException $error) { $this->error($error->getMessage()); return self::FAILURE; }
        $this->info('Created expiry notifications: '.$count); return self::SUCCESS;
    }
}
