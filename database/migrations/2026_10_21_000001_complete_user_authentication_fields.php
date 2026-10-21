<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some existing databases predate username/role in the original migration.
        foreach (['username', 'role', 'phone', 'is_active'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                continue;
            }
            Schema::table('users', function (Blueprint $table) use ($column) {
                match ($column) {
                    'username' => $table->string('username')->nullable()->unique(),
                    'role' => $table->string('role')->default('citizen'),
                    'phone' => $table->string('phone', 30)->nullable(),
                    'is_active' => $table->boolean('is_active')->default(true),
                };
            });
        }
    }

    public function down(): void
    {
        // Intentionally retain account data: these columns may predate this migration.
        // Reverting application code must never remove user identifiers or permissions.
    }
};
