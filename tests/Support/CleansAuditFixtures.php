<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait CleansAuditFixtures
{
    private ?int $auditFixtureCheckpoint = null;

    protected function beginAuditFixtures(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            throw new \LogicException('Isolated MySQL test DB required');
        }
        $this->auditFixtureCheckpoint = (int) DB::table('audit_events')->max('id');
    }

    protected function cleanAuditFixtures(): void
    {
        if ($this->auditFixtureCheckpoint !== null) {
            if (! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
                throw new \LogicException('Never purge operational audit');
            }
            // Only this sequential isolated test's newly committed fixtures; production has no purge API.
            DB::table('audit_events')->where('id', '>', $this->auditFixtureCheckpoint)->delete();
        }
    }
}
